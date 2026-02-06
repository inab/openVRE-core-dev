<?php

namespace OpenVRE;

use OpenVRE\JobDirectories;

class JobDirectoriesFactory
{
    public static function create($cloudName)
    {
        return new JobDirectories(
            $GLOBALS['clouds'][$cloudName]['pubDir_host'],
            $GLOBALS['pubDir'],
            $GLOBALS['clouds'][$cloudName]['scriptsDir_host'],
            $GLOBALS['dataDir'] . "/" . $_SESSION['User']['id'],
            $GLOBALS['clouds'][$cloudName]['dataDir_host'] . "/" . $_SESSION['User']['id'],
            $GLOBALS['clouds'][$cloudName]['pubDir_virtual'],
            $GLOBALS['clouds'][$cloudName]['dataDir_virtual'] . "/" . $_SESSION['User']['id']
        );
    }
}
