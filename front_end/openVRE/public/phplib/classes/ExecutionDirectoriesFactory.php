<?php

namespace OpenVRE;

use OpenVRE\ExecutionDirectories;
use OpenVRE\JobDirectories;


class JobDirectoriesFactory
{
    public static function create(JobDirectories $jobDirectories, $project, $execution)
    {
        $executionDir = empty($execution)
            ? $jobDirectories->userDir . "/" . $project . "/" . $GLOBALS['tmpUser_dir'] . $execution
            : $jobDirectories->userDir . "/" . $project . "/" . $execution;

        return new ExecutionDirectories(
            $executionDir
        );
    }
}
