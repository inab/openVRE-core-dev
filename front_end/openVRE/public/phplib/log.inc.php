<?php

/**
 * Helpers to keep log lines short and readable.
 */

// JSON for log lines: no "\/" escaping, no "é" escaping
function logJson($data): string
{
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

// One-line summary of a job, e.g. "pid 2: rstudio/run006 (interactive, RUNNING)"
function logJobSummary($job): string
{
    if (!is_array($job)) {
        return logJson($job);
    }

    $details = array_filter([$job['job_type'] ?? null, $job['state'] ?? null]);

    return sprintf(
        "pid %s: %s/%s%s",
        $job['pid'] ?? "?",
        $job['toolId'] ?? "?",
        $job['execution'] ?? "?",
        $details ? " (" . implode(", ", $details) . ")" : ""
    );
}

// Summary of a pid => job map, e.g. "2 job(s): pid 2: ...; pid 5: ..."
function logJobsSummary($jobs): string
{
    if (empty($jobs)) {
        return "no jobs";
    }

    return count($jobs) . " job(s): " . implode("; ", array_map('logJobSummary', $jobs));
}
