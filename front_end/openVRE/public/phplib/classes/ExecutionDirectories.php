<?php

namespace OpenVRE;


class ExecutionDirectories
{
    readonly string $executionDir; // $this->jobDirectories->userDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution or without tmpUser_dir

    readonly string $executionConfigFile; // $this->jobDirectories->userDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $GLOBALS['tool_config_file']
    readonly string $executionStageoutFile; // $this->jobDirectories->userDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $GLOBALS['tool_stageout_file']
    readonly string $executionMetadataFile; // $this->jobDirectories->userDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $GLOBALS['tool_metadata_file']
    readonly string $executionLogFile; // $this->jobDirectories->userDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $this->logName
    readonly string $executionSubmissionFile; // $this->jobDirectories->userDir . "/" . $this->project . "/" . $GLOBALS['tmpUser_dir'] . $this->execution . "/" . $GLOBALS['tool_submission_file']


    public function __construct(string $executionDir, string $executionConfigFile, string $executionStageoutFile, string $executionMetadataFile, string $executionLogFile, string $executionSubmissionFile)
    {
        $this->executionDir = $executionDir;
        $this->executionConfigFile = $executionConfigFile;
        $this->executionStageoutFile = $executionStageoutFile;
        $this->executionMetadataFile = $executionMetadataFile;
        $this->executionLogFile = $executionLogFile;
        $this->executionSubmissionFile = $executionSubmissionFile;
    }
}
