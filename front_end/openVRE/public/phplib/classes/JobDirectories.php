<?php

namespace OpenVRE;


class JobDirectories
{
    readonly ?string $projectDirHost; // pub_dir_host = $GLOBALS['clouds'][$this->cloudName]['pubDir_host']
    readonly ?string $projectDir;         // pub_dir = $GLOBALS['pubDir'] Public dir mounted to VMs. Path as seen by VRE
    readonly ?string $scriptsDirHost; // scripts_dir_host = $GLOBALS['clouds'][$this->cloudName]['scriptsDir_host']
    readonly ?string $userDir;          // root_dir  = $GLOBALS['dataDir'] . "/" . $_SESSION['User']['id'] User dataDir. Path as seen by VRE
    readonly ?string $userDirHost; // root_dir_host  = $GLOBALS['clouds'][$this->cloudName]['dataDir_host'] . "/" . $_SESSION['User']['id']
    readonly ?string $virtualProjectDir;   // pub_dir_virtual  = $GLOBALS['clouds'][$this->cloudName]['pubDir_virtual'] Public dir mounted to VMs. Path as seen by VMs
    readonly ?string $virtualUserDir;  // root_dir_virtual = $GLOBALS['clouds'][$this->cloudName]['dataDir_virtual'] . "/" . $_SESSION['User']['id']  User dataDir. Path as seen by VMs


    readonly ?string $executionDir; // $this->jobDirectories->userDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution or without tmpUser_dir

    readonly ?string $config_file_virtual; // $this->jobDirectories->virtualUserDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $GLOBALS['tool_config_file']
    readonly ?string $stageout_file_virtual; // $this->jobDirectories->virtualUserDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $GLOBALS['tool_stageout_file']
    readonly ?string $metadata_file_virtual; // $this->jobDirectories->virtualUserDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $GLOBALS['tool_metadata_file']
    readonly ?string $log_file_virtual; // $this->jobDirectories->virtualUserDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $this->logName


    readonly ?string $logName; // $GLOBALS['tool_log_file']
    readonly ?string $config_file; // $this->working_dir . "/" . $GLOBALS['tool_config_file']
    readonly ?string $stageout_file; // $this->working_dir . "/" . $GLOBALS['tool_stageout_file']
    readonly ?string $submission_file; // $this->working_dir . "/" . $GLOBALS['tool_submission_file']
    readonly ?string $log_file; // $this->working_dir . "/" . $this->logName
    readonly ?string $metadata_file; // $this->working_dir . "/" . $GLOBALS['tool_metadata_file']


    public function __construct(?string $projectDirHost, ?string $projectDir, ?string $scriptsDirHost, ?string $userDir, ?string $userDirHost, ?string $virtualProjectDir, ?string $virtualUserDir)
    {
        $this->projectDirHost = $projectDirHost;
        $this->projectDir = $projectDir;
        $this->userDir = $userDir;
        $this->scriptsDirHost = $scriptsDirHost;
        $this->userDirHost = $userDirHost;
        $this->virtualProjectDir = $virtualProjectDir;
        $this->virtualUserDir = $virtualUserDir;
    }
}
