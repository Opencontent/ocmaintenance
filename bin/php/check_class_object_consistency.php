<?php
require 'autoload.php';

$script = eZScript::instance(array('description' => ("Controlla che gli oggetti della classe selezionata siano consistenti con la definizione"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true));

$script->startup();

$options = $script->getOptions('[class:][attribute:][fix]',
    '',
    array(
        'class' => 'Identificatore della classe',
        'attribute' => 'Identificatore dell\'attributo',
        'fix' => 'Corregge ottusamente (usa -v per selezionare cosa correggere)'
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();

function persitentInTable($rows, $output)
{
    $table = new ezcConsoleTable($output, 350);
    $headers = array_keys($rows[0]);

    foreach ($headers as $cell) {
        $table[0][]->content = $cell;
    }
    foreach ($rows as $index => $row) {
        $index++;
        foreach ($row as $cell) {
            $table[$index][]->content = (string)$cell;
        }
    }

    $table->outputTable();
    $output->outputLine();
}

function fixDatatype($contentclassattribute_id, $contentobject_id, $data_type_string)
{
    $wrongs = eZPersistentObject::fetchObjectList(
        eZContentObjectAttribute::definition(),
        null,
        array(
            'contentclassattribute_id' => $contentclassattribute_id,
            'contentobject_id' => $contentobject_id,
            'data_type_string' => array('!=', $data_type_string)
        ),
        array('version' => true),
        null,
        true
    );

    foreach ($wrongs as $wrong) {
        $wrong->setAttribute('data_type_string', $data_type_string);
        $wrong->store();
    }
}

function checkClass(eZContentClass $class, $options)
{
    $cli = eZCLI::instance();
    $output = new ezcConsoleOutput();

    $cli->warning("Classe {$class->attribute('name')} ({$class->attribute('identifier')})");
    $dataMap = $class->dataMap();

    $list = $class->objectList();

    /** @var eZContentObject $object */
    foreach ($list as $object) {
        $objectCount = $object->getVersionCount();
        /** @var eZContentClassAttribute $classAttribute */
        foreach ($dataMap as $classAttribute) {

            if ($options['attribute']) {
                if ($options['attribute'] != $classAttribute->attribute('identifier')) {
                    continue;
                }
            }

            $attributeCount = eZPersistentObject::count(
                eZContentObjectAttribute::definition(),
                array(
                    'contentclassattribute_id' => $classAttribute->attribute('id'),
                    'contentobject_id' => $object->attribute('id'),
                )
            );

            if ($attributeCount != $objectCount){
                $cli->error('#' . $object->attribute('id') . ' ' . $classAttribute->attribute('identifier'));
                $validVersion = array();
                $invalidVersion = array();
                foreach ($object->versions() as $version){
                    $attributeVersionCount  = eZPersistentObject::count(
                        eZContentObjectAttribute::definition(),
                        array(
                            'contentclassattribute_id' => $classAttribute->attribute('id'),
                            'contentobject_id' => $object->attribute('id'),
                            'version' => $version->attribute('version')
                        )
                    );
                    if ($attributeVersionCount == 0){
                        $cli->error('     ' . ' version ' . $version->attribute('version'));
                        $invalidVersion[] = $version->attribute('version');
                    }else{
                        $validVersion[] = $version->attribute('version');
                    }
                }

                if ($options['fix']){
                    $opts = new ezcConsoleQuestionDialogOptions();
                    $opts->text = "Con quale versione correggo gli attributi mancanti?";
                    $opts->showResults = true;
                    $opts->validator = new ezcConsoleQuestionDialogMappingValidator($validVersion);
                    $question = new ezcConsoleQuestionDialog( $output, $opts );
                    $rightVersion = ezcConsoleDialogViewer::displayDialog( $question );

                    $attributeRightVersion = eZPersistentObject::fetchObject(
                        eZContentObjectAttribute::definition(),
                        null,
                        array(
                            'contentclassattribute_id' => $classAttribute->attribute('id'),
                            'contentobject_id' => $object->attribute('id'),
                            'version' => $rightVersion
                        ),
                        false
                    );
                    foreach ($invalidVersion as $invalidVersion){
                        $newAttributeRow = $attributeRightVersion;
                        $newAttributeRow['version'] = $invalidVersion;
                        $newAttribute = new eZContentObjectAttribute($newAttributeRow);
                        $newAttribute->store();
                    }

                }
            }

        }
    }
    $cli->notice();
}

function checkClassDatatype($class, $options){

    $cli = eZCLI::instance();
    $output = new ezcConsoleOutput();


    $cli->warning("Classe {$class->attribute('name')} ({$class->attribute('identifier')})");

    $hasError = false;

    foreach($class->dataMap() as $classAttribute){

        if ($options['attribute']){
            if ($options['attribute'] != $classAttribute->attribute('identifier')){
                continue;
            }
        }

        $data = eZPersistentObject::fetchObjectList( eZContentObjectAttribute::definition(),
            null,
            array( 'contentclassattribute_id' => $classAttribute->attribute('id') ),
            array('contentobject_id' => true),
            null,
            false );
        $errors = array();
        foreach ($data as $item){
            if ($item['data_type_string'] !== $classAttribute->attribute('data_type_string')){
                $errors[$item['contentobject_id']] = 'il datatype dovrebbe essere ' . $classAttribute->attribute('data_type_string');
            }
        }
        if (empty($errors)){

            $cli->notice( " - {$classAttribute->attribute('name')} ({$classAttribute->attribute('identifier')} {$classAttribute->attribute('id')}) OK" );

        }else{

            $hasError = true;

            $cli->error( " - {$classAttribute->attribute('name')} ({$classAttribute->attribute('identifier')}  {$classAttribute->attribute('id')}) " . count($errors) . ' oggetti errati');

            foreach( $errors as $id => $error){

                if ($options['verbose']){

                    $cli->error("   Oggetto #{$id}: {$error}");

                    $rows = eZPersistentObject::fetchObjectList( eZContentObjectAttribute::definition(),
                        null,
                        array(
                            'contentclassattribute_id' => $classAttribute->attribute('id'),
                            'contentobject_id' => $id
                        ),
                        array('version' => true),
                        null,
                        false );

                    persitentInTable($rows,$output);

                    if ($options['fix']){
                        $question = ezcConsoleQuestionDialog::YesNoQuestion( $output, "Correggo gli elementi errati nella tabella? ", "n" );
                        if ( ezcConsoleDialogViewer::displayDialog( $question ) == "y" )
                        {
                            fixDatatype($classAttribute->attribute('id'),$id,$classAttribute->attribute('data_type_string'));
                            $cli->warning('Elementi corretti');
                        }
                    }

                }elseif ($options['fix']){

                    fixDatatype($classAttribute->attribute('id'),$id,$classAttribute->attribute('data_type_string'));
                    $hasError = false;

                }
            }
        }
    }
    $cli->notice();
    return $hasError;
}

try {
    if ($options['class']) {
        $class = eZContentClass::fetchByIdentifier($options['class']);
        if (!$class instanceof eZContentClass) {
            throw new Exception("Classe $class non trovata");
        }
        checkClass($class, $options);
    } else {

        $query = "SELECT id, identifier FROM ezcontentclass where version=0";
        $db = eZDB::instance();
        $identifierArray = $db->arrayQuery($query);
        foreach ($identifierArray as $identifierRow) {
            $class = eZContentClass::fetch((int)$identifierRow['id']);
            if (checkClass($class, $options)) {
                $cli->notice('Premi un tasto per continuare');
                $handle = fopen("php://stdin", "r");
                $line = fgets($handle);
            }
        }
    }


    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
    $script->shutdown($errCode, $e->getMessage());
}