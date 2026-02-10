<?php

namespace OpenVRE;


class JobDirectoriesFactory
{
    public static function create($cloudName)
    {
        return new JobDirectories(
            $GLOBALS["host_path"].$GLOBALS['pubDir'],
            $GLOBALS['pubDir'],
            $GLOBALS['clouds'][$cloudName]['scriptsDir_host'],
            $GLOBALS['dataDir'] . "/" . $_SESSION['User']['id'],
            $GLOBALS["host_path"].$GLOBALS['dataDir'] . "/" . $_SESSION['User']['id']
        );
    }
}
