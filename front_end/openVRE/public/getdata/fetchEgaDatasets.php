<?php

require_once __DIR__."/../../config/bootstrap.php";


use OpenVRE\EGALinkedAccount;
use OpenVRE\LoggerFactory;


$logger = LoggerFactory::getLogger('Fetch EGA datasets');

$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($currentPage - 1) * 10;

$user = getUserById($_SESSION['userId']);
$egaLinkedAccount = new EGALinkedAccount();
$data = $egaLinkedAccount->getCredentials($user->getSecretsId());

$egaUsername = $data['username'] ?? null;
$egaPassword = $data['password'] ?? null;

if ($egaUsername === null || $egaPassword === null) {
    $logger->error('EGA credentials not found in Vault.');
    throw new UnexpectedValueException('EGA credentials not found. Try to link your EGA account again.');
}

$logger->info('EGA credentials loaded from Vault.');


$accessToken = $egaLinkedAccount->getAuthToken($egaPassword, $egaUsername);

// Check if we're fetching files for a specific dataset
if (isset($_GET['action']) && $_GET['action'] === 'fetch_files') {
    $accession_id = htmlspecialchars($_GET['accession_id']);
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $files = $egaLinkedAccount->fetchDatasetFiles($accession_id, $accessToken, $offset, $limit);
    $logger->info('Fetched files for dataset ' . $accession_id);
    header('Content-Type: application/json');
    echo json_encode($files);
    exit;
}

$dataArray = $egaLinkedAccount->fetchDatasets($accessToken, $offset, 10);
$total_count = count($dataArray);
$total_pages = ceil($total_count / 10);
