<?php

require __DIR__."/../../config/bootstrap.php";

//redirectOutside();

if (! $_SESSION['User']){
    $_SESSION['errorData']['Error'][] = "Sorry, session is lost. Please, login again";
    redirect($_SERVER['HTTP_REFERER']."#tab_1_4");
}

$force = (isset($_REQUEST['force'])?true:false);

$r =refresh_vault_token($force);
//var_dump($r);
if (!$r) $_SESSION['errorData']['Error'][] = "An error occurred while refreshing Vault token. Sorry, try it again.";

redirect($_SERVER['HTTP_REFERER']."#tab_1_4");

//redirect($_SERVER['HTTP_REFERER']);
