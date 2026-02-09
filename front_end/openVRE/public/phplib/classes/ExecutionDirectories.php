<?php

namespace OpenVRE;


class ExecutionDirectories
{
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


    public function __construct(string $executionDir)
    {
        $this->executionDir = $executionDir;
    }
}
