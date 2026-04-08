<?php

use OpenVRE\User;

$connectionUri = "mongodb://" . getenv('MONGO_CREDENTIALS') . "@" . getenv('MONGO_SERVER') . "/?authSource=" . getenv('MONGO_MAIN_DB');
$clientTypeMap = array('typeMap' => array('root' => 'array', 'document' => 'array', 'array' => 'array'));
$VREConn =  new MongoDB\Client($connectionUri, array('readConcernLevel' => 'local'), $clientTypeMap);


$dbname = getenv('MONGO_MAIN_DB');
$databaseConnection = $VREConn->getDatabase($dbname);
$userTypeMap = ['typeMap' => ['array' => 'array', 'root' => User::class, 'document' => 'array']];

$GLOBALS['usersCol']        = $databaseConnection->getCollection('users', $userTypeMap);
$GLOBALS['filesCol']        = $databaseConnection->files;
$GLOBALS['filesMetaCol']    = $databaseConnection->filesMetadata;
$GLOBALS['logMailCol']      = $databaseConnection->checkMail;
$GLOBALS['toolsCol']        = $databaseConnection->getCollection('tools');
$GLOBALS['visualizersCol']  = $databaseConnection->visualizers;
$GLOBALS['fileFormatsCol']    = $databaseConnection->file_formats;
$GLOBALS['dataTypesCol']    = $databaseConnection->data_types;
$GLOBALS['helpsCol']        = $databaseConnection->helps;
$GLOBALS['sampleDataCol']   = $databaseConnection->sampleData;
$GLOBALS['logExecutionsCol'] = $databaseConnection->log_executions;
$GLOBALS['sitesCol']   = $databaseConnection->sites;
