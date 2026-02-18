<?php

/*
 * users.inc.php
 * 
 */

function check_password($password, $hash)
{
    if ($hash == '') {
        return false;
    }

    if (substr($hash, 0, 7) == '{crypt}') {
        if (crypt($password, substr($hash, 7)) == substr($hash, 7))
            return true;
        return false;
    } elseif (substr($hash, 0, 4) == '$2y$') {
        if (password_verify($password, $hash))
            return true;
        return false;
    } elseif (substr($hash, 0, 5) == '{MD5}') {
        $encrypted_password = '{MD5}' . base64_encode(md5($password, true));
    } elseif (substr($hash, 0, 6) == '{SHA1}') {
        $encrypted_password = '{SHA}' . base64_encode(sha1($password, true));
    } elseif (substr($hash, 0, 6) == '{SSHA}') {
        $salt = substr(base64_decode(substr($hash, 6)), 20);
        $encrypted_password = '{SSHA}' . base64_encode(sha1($password . $salt, true) . $salt);
    } else {
        $_SESSION['ErrorData']['Error'][] = "Unsupported password hash format " . substr($hash, 0, 9) . "...";
        return false;
    }

    if ($hash == $encrypted_password)
        return true;
    return false;
}


