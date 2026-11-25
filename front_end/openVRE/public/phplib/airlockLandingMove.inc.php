<?php

/**
 * Airlock export, step 1: stage the workspace files in the NFS shared with
 * Airlock and create the landing move. See airlock.inc.php for the flow.
 */

use OpenVRE\AirlockClient;
use OpenVRE\AirlockException;
use OpenVRE\AirlockExportState;
use OpenVRE\AirlockStagingException;


/**
 * Project folder and path inside the project of a workspace file, whose path
 * is <user>/<project>/<path inside the project>.
 */
function exportFileLocation(array $file): array
{
	$parts = explode("/", trim($file['path'], "/"));
	if (count($parts) < 3) {
		throw new UnexpectedValueException("File '" . basename($file['path']) . "' is not inside a project.");
	}

	return array("project" => $parts[1], "path" => implode("/", array_slice($parts, 2)));
}


/**
 * Copy a workspace file to AIRLOCK_STAGING_DIR/<project>/<stagingId>/<path>,
 * the location Airlock derives from its logical contentUrl
 * tre://project/<project>/<path>. Remote files already in that NFS will only
 * need to resolve their location (not implemented yet).
 */
function stageFileForAirlock(array $file, string $stagingDir): array
{
	$name = basename($file['path']);
	$source = $GLOBALS['userDataDir'] . "/" . $file['path'];
	if (!is_file($source) || !is_readable($source)) {
		throw new AirlockStagingException("File '$name' not found in disk.");
	}

	$location = exportFileLocation($file);
	$target = $stagingDir . "/" . $location['path'];
	$targetDir = dirname($target);
	if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
		throw new AirlockStagingException("Cannot create the export staging directory.");
	}
	if (!copy($source, $target)) {
		throw new AirlockStagingException("Cannot copy '$name' to the export staging area.");
	}

	return array(
		"stagedPath" => $target,
		"contentUrl" => airlockTreUri("project", $location['project'], ...explode("/", $location['path'])),
		"size"       => filesize($target),
		"sha256"     => hash_file('sha256', $target),
		"mimeType"   => mime_content_type($target) ?: "application/octet-stream",
	);
}


/**
 * Remove the copies staged for Airlock, and the folders left empty. Only the
 * export's own staging directory is touched.
 */
function cleanAirlockStaging(array $export): void
{
	$stagingDir = $export['stagingDir'] ?? "";
	if ($stagingDir === "" || !is_dir($stagingDir)) {
		return;
	}

	$dirs = array();
	foreach ($export['stagedPaths'] ?? array() as $path) {
		if (is_file($path) && str_starts_with($path, $stagingDir . "/")) {
			unlink($path);
			for ($dir = dirname($path); $dir !== $stagingDir && str_starts_with($dir, $stagingDir); $dir = dirname($dir)) {
				$dirs[$dir] = substr_count($dir, "/");
			}
		}
	}
	arsort($dirs); // deepest first
	foreach (array_merge(array_keys($dirs), array($stagingDir)) as $dir) {
		if (is_dir($dir) && count(scandir($dir)) == 2) { // only '.' and '..'
			rmdir($dir);
		}
	}
}


function assertExportAvailable(): void
{
	if (!canExport()) {
		throw new UnexpectedValueException(AIRLOCK_MSG_NOT_ALLOWED);
	}
	if (empty($GLOBALS['airlockStagingDir']) || !is_dir($GLOBALS['airlockStagingDir'])) {
		throw new UnexpectedValueException("Airlock staging area is not available (AIRLOCK_STAGING_DIR).");
	}
}


/**
 * A workspace file the user owns and that can be exported.
 */
function getExportableFile(string $fileId): array
{
	$file = getGSFile_fromId($fileId);
	if (is_null($file)) {
		throw new UnexpectedValueException("File $fileId not found or not owned by the user.");
	}
	if (isset($file['files'])) {
		throw new UnexpectedValueException("Folders cannot be exported. Select the files instead.");
	}
	if (str_starts_with($file['path'], "/gpfs/") || !empty($file['remote'])) {
		throw new UnexpectedValueException("Remote file '" . basename($file['path']) . "' cannot be exported yet.");
	}

	return $file;
}


/**
 * Execution folder (with its job metadata) shared by all the files. An export
 * describes a single source activity, so files of different executions are
 * exported separately.
 */
function getExportExecutionFolder(array $files): array
{
	$folderIds = array_unique(array_map(fn($file) => (string) ($file['parentDir'] ?? ""), $files));
	if (count($folderIds) != 1 || reset($folderIds) === "") {
		throw new UnexpectedValueException("Files of different executions cannot be exported together. Export them separately.");
	}
	$folder = getGSFile_fromId(reset($folderIds));
	if (is_null($folder)) {
		throw new UnexpectedValueException("Execution folder of the files not found.");
	}

	return $folder;
}


/**
 * Landing artifact ids allocated by Airlock for the releasable files.
 */
function landingArtifactIdsFromPlan(array $plan, array $releasableIds): array
{
	$ids = array();
	foreach ($plan['airlock:artifactAssignments'] ?? array() as $assignment) {
		if (in_array($assignment['airlock:sourceArtifact']['@id'] ?? null, $releasableIds, true) && isset($assignment['airlock:landingArtifact']['@id'])) {
			$ids[] = $assignment['airlock:landingArtifact']['@id'];
		}
	}

	return $ids;
}


/**
 * Stage the given workspace files and create an Airlock landing move for them.
 * The job log, if any, is landed too as a supporting (not releasable) file.
 */
function requestLandingMove(array $fileIds): array
{
	assertExportAvailable();

	$fileIds = array_values(array_unique(array_filter($fileIds, fn($id) => !empty($id) && $id !== 'undefined')));
	if (!$fileIds) {
		throw new UnexpectedValueException("No file selected for export.");
	}
	// validate every file before staging anything
	$files = array_map('getExportableFile', $fileIds);
	$folder = getExportExecutionFolder($files);
	$project = exportFileLocation($files[0])['project'];
	$logFile = getJobLogFile($folder, $fileIds);
	$activity = buildSourceActivity($folder);

	$user = getUserById($_SESSION['userId']);
	// staging id: also the landing move clientRequestId, so it is stable for retries
	$exportId = "export-" . bin2hex(random_bytes(10));
	$record = array(
		"_id"        => $exportId,
		"owner"      => $_SESSION['internalUserId'],
		"project"    => $project,
		"folderId"   => $folder['_id'],
		"fileIds"    => $fileIds,
		"fileNames"  => array_map(fn($f) => basename($f['path']), $files),
		"stagingDir" => $GLOBALS['airlockStagingDir'] . "/" . $project . "/" . $exportId,
		"state"      => AirlockExportState::MoveRequested->value,
		"createdAt"  => date(DATE_ATOM),
		"updatedAt"  => date(DATE_ATOM),
	);
	$GLOBALS['airlockExportsCol']->insertOne($record);

	$staged = array();
	try {
		$distribution = array();
		$toStage = array_merge(array_map(fn($f) => array($f, AIRLOCK_ROLE_CANDIDATE_RELEASE), $files), $logFile ? array(array($logFile, AIRLOCK_ROLE_SUPPORTING)) : array());
		foreach ($toStage as [$file, $role]) {
			$stagedFile = stageFileForAirlock($file, $record['stagingDir']);
			$staged[] = $stagedFile['stagedPath'];
			$distribution[] = buildLandingFileObject($file, $stagedFile, $activity, $role);
		}
		updateAirlockExport($exportId, array("stagedPaths" => $staged));

		$logArtifactId = $logFile ? airlockTreUri("artifact", $logFile['_id']) : null;
		$request = buildLandingMoveRequest($record, buildLandingArtifactSet($record, $distribution, $user), $activity, $user, $logArtifactId);
		$plan = airlockResponseData(AirlockClient::forCurrentUser()->createLandingMove($request), "Export $exportId: landing move failed");

		updateAirlockExport($exportId, array(
			"moveId"             => $plan['@id'] ?? null,
			"artifactSetId"      => $plan['airlock:landingArtifactSet']['@id'] ?? null,
			"landingArtifactIds" => landingArtifactIdsFromPlan($plan, array_map(fn($f) => airlockTreUri("artifact", $f['_id']), $files)),
			"moveExpiresAt"      => $plan['airlock:expiresAt'] ?? null,
		));
		getAirlockLogger()->info("Landing move " . ($plan['@id'] ?? "?") . " created for export $exportId (" . implode(", ", $fileIds) . ")");
	} catch (Throwable $e) {
		// Airlock errors are already logged with their context
		if (!$e instanceof AirlockException) {
			getAirlockLogger()->error("Export $exportId: " . $e->getMessage());
		}
		cleanAirlockStaging($record + array("stagedPaths" => $staged));
		updateAirlockExport($exportId, array("state" => AirlockExportState::Failed->value, "error" => $e->getMessage()));
		throw $e;
	}

	return airlockExportSummary(getAirlockExport($exportId));
}
