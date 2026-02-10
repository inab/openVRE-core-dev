<?php

namespace OpenVRE;


class JobDirectories
{
    readonly ?string $projectDirHost; // pub_dir_host = $GLOBALS["host_path"].$GLOBALS['pubDir']
    readonly ?string $projectDir;         // pub_dir = $GLOBALS['pubDir'] Public dir mounted to VMs. Path as seen by VRE
    readonly ?string $scriptsDirHost; // scripts_dir_host = $GLOBALS['clouds'][$this->cloudName]['scriptsDir_host']
    readonly ?string $userDir;          // root_dir  = $GLOBALS['dataDir'] . "/" . $_SESSION['User']['id'] User dataDir. Path as seen by VRE
    readonly ?string $userDirHost; // root_dir_host  = $GLOBALS["host_path"].$GLOBALS['pubDir'] . "/" . $_SESSION['User']['id']


    public function __construct(?string $projectDirHost, ?string $projectDir, ?string $scriptsDirHost, ?string $userDir, ?string $userDirHost)
    {
        $this->projectDirHost = $projectDirHost;
        $this->projectDir = $projectDir;
        $this->scriptsDirHost = $scriptsDirHost;
        $this->userDir = $userDir;
        $this->userDirHost = $userDirHost;
    }
}
