<?php

require dirname(__FILE__) . "/../../config/globals.inc.php";


// Ask the queue for the current state; fall back to the state stored in Mongo
function getInteractiveJobState(array $job, $pid): string
{
    try {
        $jobProcess = getRunningJobInfo($pid, $job['launcher'] ?? null);
    } catch (Exception $e) {
        getProjectLogger()->warning("checkStatus: cannot query job $pid: " . $e->getMessage());
        return $job['state'] ?? "";
    }

    return empty($jobProcess) ? "FINISHED" : ($jobProcess['state'] ?? "");
}


function tailFile(?string $file, int $lines = 50): string
{
    if (!$file || !is_file($file)) {
        return "";
    }
    $content = file($file, FILE_IGNORE_NEW_LINES) ?: [];

    return implode("\n", array_slice($content, -$lines));
}


/**
 * @return array{ready: bool, reload: bool, title: string, message: string, url?: string, log?: string, logPath?: string}
 */
function checkStatus($pid)
{
    $jobs = getUserJobPid($_SESSION['userId'], $pid);
    $job  = $jobs[$pid] ?? null;

    if (!$job) {
        return [
            "ready"   => false,
            "reload"  => false,
            "title"   => "Job not found",
            "message" => "The requested interactive session could not be found."
        ];
    }

    // the launcher script prints "Service UP" to the queue stdout
    $stdoutFile = getJobStdoutFile($job);
    $stdout     = ($stdoutFile && is_file($stdoutFile)) ? file_get_contents($stdoutFile) : "";
    $logInfo    = [
        "log"     => tailFile($stdoutFile),
        "logPath" => $stdoutFile ? fromAbsPath_toPath($stdoutFile) : ""
    ];

    $state = getInteractiveJobState($job, $pid);

    if ($state == "PENDING" || $state == "HOLD") {
        return $logInfo + [
            "ready"   => false,
            "reload"  => true,
            "title"   => "Waiting for scheduler",
            "message" => "The interactive session is waiting for compute resources."
        ];
    }

    if ($state != "RUNNING" && $state != "TRANSFERING") {
        return $logInfo + [
            "ready"   => false,
            "reload"  => false,
            "title"   => "Session finished",
            "message" => "The interactive session is no longer running."
        ];
    }

    if ($stdout === "") {
        return $logInfo + [
            "ready"   => false,
            "reload"  => true,
            "title"   => "Starting container",
            "message" => "Waiting for launcher output..."
        ];
    }

    if (strpos($stdout, "Service TIMEOUT") !== false) {
        return $logInfo + [
            "ready"   => false,
            "reload"  => false,
            "title"   => "Session failed to start",
            "message" => "The application did not become available in time. Check the launcher logs below."
        ];
    }

    if (strpos($stdout, "Service UP") === false) {
        return $logInfo + [
            "ready"   => false,
            "reload"  => true,
            "title"   => "Preparing interactive session",
            "message" => "The container is running. Waiting for the application to become available..."
        ];
    }

    return $logInfo + [
        "ready"   => true,
        "reload"  => false,
        "title"   => "Interactive session ready",
        "message" => "Your session is ready.",
        "url"     => $GLOBALS['URL'] . "interactive-tool/" . $job['containerName'] . "/"
    ];
}
