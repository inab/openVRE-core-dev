<?php

namespace OpenVRE;


class JobDirectoriesFactory
{
    public static function create($cloudName)
    {
        return new JobDirectories(
            $GLOBALS['clouds'][$cloudName]["pubDir_host"],
            $GLOBALS['pubDir'],
            $GLOBALS['clouds'][$cloudName]['scriptsDir_host'],
            $GLOBALS['userDataDir'] . "/" . $_SESSION['internalUserId'],
            $GLOBALS['clouds'][$cloudName]["dataDir_host"] . "/" . $_SESSION['internalUserId']
        );
    }
}
