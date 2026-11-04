<?php
require __DIR__."/../../config/bootstrap.php";
redirectOutside();

// jobs before update
$jobs_ori = getUserJobs($_SESSION['userId']);

// updating jobs
updatePendingFiles($_SESSION['userId']);

// jobs after update
$jobs_last = getUserJobs($_SESSION['userId']);

$diff = strcmp(json_encode($jobs_ori), json_encode($jobs_last));

if ($diff){
    echo '{ "hasChanged":1 }';
}else{
    echo '{ "hasChanged": 0 }';
}
