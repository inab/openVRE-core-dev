<?php

namespace OpenVRE;

use Monolog\Logger;

/**
 * Kubernetes batch Jobs: tools with launcher "kubernetes_native" that are not
 * interactive. Each run is saved with launcher "kubernetes_batch" (older runs:
 * "kubernetes_native"), which routes its status and stop requests here.
 *
 * A Job is sent to the scheduler as the template "openvre-batch-job" plus
 * parameters (scheduler/templates/). User, volumes, node placement and
 * security settings are set by the scheduler, not here. Interactive sessions
 * are in K8sSession.
 *
 * Environment variables (besides those of SchedulerClient):
 *   OPENVRE_K8S_JOB_IMAGE       image when the tool sets none (may be empty)
 *   OPENVRE_K8S_JOB_TTL         seconds to keep a finished Job (default 120)
 *   OPENVRE_K8S_JOB_DEADLINE    max seconds a Job may run (default 86400)
 *   OPENVRE_K8S_JOB_MEMORY_REQUEST / _LIMIT  memory when the tool sets none
 */
class ProcessK8s
{
    /** Prefix of batch Job names, which are also the OpenVRE pid. */
    public const BATCH_JOB_PREFIX = 'openvre-bj-';

    /** Scheduler template for batch runs (scheduler/templates/openvre-batch-job.yaml). */
    public const BATCH_TEMPLATE = 'openvre-batch-job';

    /** Kubernetes Job state -> OpenVRE job state. */
    private const JOB_STATES = array(
        "Running" => "RUNNING",
        "Pending" => "PENDING",
    );

    private Logger $logger;
    private SchedulerClient $scheduler;

    private string $pid = "";
    private string $command = "";
    private string $workDir = "";
    private string $jobname = "";
    private string $stdout = "";
    private string $stderr = "";

    private string $jobImage;
    private int $jobTtl;
    private int $jobDeadline;
    private array $jobEnv = [];
    private array $jobPullSecrets = [];

    /**
     * With a submission script path ($cl), submits the Job at once.
     * With $cl = false, the object is used for getRunningJobInfo, status and stop.
     */
    public function __construct(false|string $cl = false, string $workDir = "", string $jobname = "", int $cpu = 1, int $mem = 0, array $jobOptions = [])
    {
        $this->logger = LoggerFactory::getLogger("K8s interface");
        $this->scheduler = new SchedulerClient();
        $this->jobImage = SchedulerClient::env("OPENVRE_K8S_JOB_IMAGE");
        $this->jobTtl = (int)SchedulerClient::optionalEnv("OPENVRE_K8S_JOB_TTL", "120");
        $this->jobDeadline = (int)SchedulerClient::optionalEnv("OPENVRE_K8S_JOB_DEADLINE", "86400");

        // Per-tool settings: image, environment variables, registry secrets.
        if (!empty($jobOptions["image"])) {
            $this->jobImage = $jobOptions["image"];
        }
        if (!empty($jobOptions["env"]) && is_array($jobOptions["env"])) {
            $this->jobEnv = $jobOptions["env"];
        }
        if (!empty($jobOptions["image_pull_secrets"]) && is_array($jobOptions["image_pull_secrets"])) {
            $this->jobPullSecrets = array_values(array_filter(array_map("strval", $jobOptions["image_pull_secrets"])));
        }

        if ($cl) {
            $this->workDir = $workDir;
            $this->command = $cl;
            $this->jobname = $jobname ?: basename($cl);
            $this->submit($cpu, $mem);
        }
    }

    /** A unique, Kubernetes-safe Job name (at most 63 characters). */
    private function jobName(string $name): string
    {
        $maxSlugLen = 63 - strlen(self::BATCH_JOB_PREFIX) - 9; // "-" + 8-char hash
        $name = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($name)), '-');
        if ($name === "") {
            $name = "job";
        }
        $name = rtrim(substr($name, 0, $maxSlugLen), '-');
        return self::BATCH_JOB_PREFIX . $name . "-" . substr(md5(uniqid("", true)), 0, 8);
    }

    private function submit(int $cpu, int $mem): void
    {
        $err = $this->jobImage === "" ? "OPENVRE_K8S_JOB_IMAGE is not set" : $this->scheduler->configError();
        if ($err !== "") {
            $this->fail($err);
            return;
        }

        $this->pid = $this->jobName($this->jobname);
        $this->logger->info("K8s job submission via scheduler: POST /v1/jobs (template " . self::BATCH_TEMPLATE . ")");
        $response = $this->scheduler->request("POST", "/v1/jobs", array(
            "namespace" => $this->scheduler->namespace(),
            "template" => self::BATCH_TEMPLATE,
            "params" => $this->jobParams($cpu, $mem),
        ));

        if ($response["ok"] !== true) {
            $this->pid = "";
            $this->fail("K8s job submission failed: " . trim($response["error"]));
            return;
        }
        $this->stdout = (string)($response["data"]["stdout"] ?? "");
        $this->stderr = (string)($response["data"]["stderr"] ?? "");
        $this->logger->info("K8s job submitted: " . $this->pid);
    }

    private function fail(string $msg): void
    {
        $this->stderr = $msg;
        $this->stdout = "";
        $this->logger->error($msg);
        $_SESSION['errorData']['Error'][] = $msg;
    }

    /** Parameters of the batch template; the scheduler checks each one's type. */
    private function jobParams(int $cpu, int $mem): array
    {
        $params = array(
            "job_name" => $this->pid,
            "image" => $this->jobImage,
            "workdir" => $this->workDir,
            "submit_script" => $this->command,
            "cpu" => (string)max(1, $cpu),
            "deadline_seconds" => $this->jobDeadline > 0 ? $this->jobDeadline : 86400,
            "ttl_seconds" => max(0, $this->jobTtl),
        );

        // Memory: the tool's value, else the deployment default, else the template default.
        $memRequest = $mem > 0 ? ($mem . "Gi") : SchedulerClient::optionalEnv("OPENVRE_K8S_JOB_MEMORY_REQUEST");
        $memLimit = $mem > 0 ? ($mem . "Gi") : SchedulerClient::optionalEnv("OPENVRE_K8S_JOB_MEMORY_LIMIT");
        if ($memRequest !== "") {
            $params["memory_request"] = $memRequest;
        }
        if ($memLimit !== "") {
            $params["memory_limit"] = $memLimit;
        }

        // Only sent when not empty: an empty PHP array encodes as a JSON list, not a mapping.
        $env = array();
        foreach ($this->jobEnv as $name => $value) {
            if ((string)$name !== "" && $value !== null && $value !== "") {
                $env[(string)$name] = (string)$value;
            }
        }
        if (!empty($env)) {
            $params["env"] = $env;
        }
        if (!empty($this->jobPullSecrets)) {
            $params["image_pull_secrets"] = $this->jobPullSecrets;
        }
        return $params;
    }

    /**
     * Status of a batch Job. An empty array means "finished": OpenVRE then
     * collects the outputs and stops polling, so it is returned only when the
     * scheduler says the Job is gone or done. When the scheduler cannot be
     * reached, the Job stays PENDING.
     */
    public function getRunningJobInfo(string $pid): array
    {
        if ($pid === "") {
            return array();
        }
        $response = $this->scheduler->request("GET", $this->scheduler->objectPath("jobs", $pid));
        if ($response["ok"] !== true) {
            $this->logger->warning("K8s job $pid: status unknown, scheduler error: " . $response["error"]);
            return SchedulerClient::unknownStateInfo($pid);
        }
        if (empty($response["data"]["exists"])) {
            return array();
        }

        $state = $response["data"]["status"]["state"] ?? "Pending";
        if ($state === "Succeeded" || $state === "Failed") {
            if ($state === "Failed") {
                $reason = $response["data"]["status"]["reason"] ?? "";
                $this->logger->warning("K8s job $pid failed" . ($reason !== "" ? ": $reason" : ""));
            }
            return array();
        }
        return array(
            "pid" => $pid,
            "state" => self::JOB_STATES[$state] ?? "PENDING",
            "job_name" => $pid,
        );
    }

    /**
     * Whether the submission worked (called by execJob right after submitting).
     * A Job that already finished, or one the scheduler accepted but cannot
     * report on right now, still counts as submitted.
     */
    public function status()
    {
        if (!$this->pid) {
            return false;
        }
        $response = $this->scheduler->request("GET", $this->scheduler->objectPath("jobs", $this->pid));
        if ($response["ok"] !== true) {
            $this->logger->warning("K8s job {$this->pid}: submitted, but status check failed: " . $response["error"]);
            return true;
        }
        return !empty($response["data"]["exists"]);
    }

    public function stop(?string $pid = null): array
    {
        if (!$pid) {
            return array(false, "No job id given");
        }
        $response = $this->scheduler->request("DELETE", $this->scheduler->objectPath("jobs", $pid));
        if ($response["ok"] === true) {
            return array(true, trim((string)($response["data"]["stdout"] ?? "")));
        }
        return array(false, $response["error"] ?: "Failed to delete kubernetes job");
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function getErr()
    {
        return $this->stderr ? trim($this->stdout . " " . $this->stderr) : null;
    }
}
