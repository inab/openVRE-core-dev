<?php

namespace OpenVRE;


class ExecutionDirectoriesFactory
{
    public static function create(JobDirectories $jobDirectories, $project, $execution, $logFilename)
    {
        if (empty($execution)) {
            $execution = $GLOBALS['tmpUser_dir'];
        }

        if (empty($logFilename)) {
            $logFilename = $GLOBALS['tool_log_file'];
        }

        $executionDir = $jobDirectories->userDir . "/" . $project . "/" . $execution;
        $executionConfigFile = $jobDirectories->userDir . "/" . $project . "/" . $execution . "/" . $GLOBALS['tool_config_file'];
        $executionStageoutFile = $jobDirectories->userDir . "/" . $project . "/" . $execution . "/" . $GLOBALS['tool_stageout_file'];
        $executionMetadataFile = $jobDirectories->userDir . "/" . $project . "/" . $execution . "/" . $GLOBALS['tool_metadata_file'];
        $executionLogFile = $jobDirectories->userDir . "/" . $project . "/" . $execution . "/" . $logFilename;
        $executionSubmissionFile = $jobDirectories->userDir . "/" . $project . "/" . $execution . "/" . $GLOBALS['tool_submission_file'];

        return new ExecutionDirectories(
            $executionDir,
            $executionConfigFile,
            $executionStageoutFile,
            $executionMetadataFile,
            $executionLogFile,
            $executionSubmissionFile
        );
    }
}
