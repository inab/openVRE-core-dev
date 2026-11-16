<?php

/**
 * Airlock export, step 1: stage the workspace files in the NFS shared with
 * Airlock and create the landing move. See airlock.inc.php for the flow.
 */

use OpenVRE\AirlockClient;
use OpenVRE\AirlockException;
use OpenVRE\AirlockExportState;
use OpenVRE\AirlockStagingException;
use OpenVRE\User;


/**
 * Location of a file in the NFS shared with Airlock. Workspace files are copied
 * there; remote files that already live in that NFS will only need to resolve
 * their location (not implemented yet).
 */
function stageFileForAirlock(array $file, string $exportId): array
{
	$name = basename($file['path']);
	$source = $GLOBALS['userDataDir'] . "/" . $file['path'];
	if (!is_file($source) || !is_readable($source)) {
		throw new AirlockStagingException("File '$name' not found in disk.");
	}

	$targetDir = $GLOBALS['airlockStagingDir'] . "/" . $_SESSION['internalUserId'] . "/" . $exportId;
	if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
		throw new AirlockStagingException("Cannot create the export staging directory.");
	}

	$target = $targetDir . "/" . $name;
	if (!copy($source, $target)) {
		throw new AirlockStagingException("Cannot copy '$name' to the export staging area.");
	}

	return array(
		"name"       => $name,
		"stagedPath" => $target,
		"contentUrl" => airlockTreUri("files", $_SESSION['internalUserId'], $exportId, $name),
		"size"       => filesize($target),
		"sha256"     => hash_file('sha256', $target),
		"mimeType"   => mime_content_type($target) ?: "application/octet-stream",
	);
}


/**
 * Remove the copies staged for Airlock. Only the export's own directory is
 * touched.
 */
function cleanAirlockStaging(array $export): void
{
	$dir = $GLOBALS['airlockStagingDir'] . "/" . $export['owner'] . "/" . $export['_id'];
	if (empty($GLOBALS['airlockStagingDir']) || !is_dir($dir)) {
		return;
	}

	foreach ($export['stagedPaths'] ?? array() as $path) {
		if (is_file($path) && dirname($path) === $dir) {
			unlink($path);
		}
	}
	if (count(scandir($dir)) == 2) { // only '.' and '..'
		rmdir($dir);
	}
}


/**
 * Check that the user and the instance can export, returning the contract URIs.
 */
function assertExportAvailable(): array
{
	if (!canExport()) {
		throw new UnexpectedValueException(AIRLOCK_MSG_NOT_ALLOWED);
	}
	if (empty($GLOBALS['airlockStagingDir']) || !is_dir($GLOBALS['airlockStagingDir'])) {
		throw new UnexpectedValueException("Airlock staging area is not available (AIRLOCK_STAGING_DIR).");
	}

	return airlockConformsTo();
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
 * Croissant file object describing a staged file in the landing move request.
 */
function buildLandingFileObject(array $file, array $staged, string $activityId): array
{
	$fileObject = array(
		"@type"                => "cr:FileObject",
		"@id"                  => airlockTreUri("file", $file['_id']),
		"name"                 => $staged['name'],
		"contentUrl"           => $staged['contentUrl'],
		"contentSize"          => $staged['size'],
		"encodingFormat"       => $staged['mimeType'],
		"airlock:fileFormat"   => !empty($file['format']) ? $file['format'] : "UNK",
		"sha256"               => $staged['sha256'],
		AIRLOCK_FILE_ROLE_KEY  => AIRLOCK_ROLE_CANDIDATE_RELEASE,
		"prov:wasGeneratedBy"  => array("@id" => $activityId),
		"tre:sourceArtifactId" => $file['_id'],
	);
	if (!empty($file['data_type'])) {
		$fileObject["tre:dataType"] = $file['data_type'];
	}
	if (!empty($file['description'])) {
		$fileObject["description"] = (string) $file['description'];
	}

	return $fileObject;
}


/**
 * Artifact set of the landing move request, including its manifest digest.
 */
function buildLandingArtifactSet(array $record, array $distribution, User $user): array
{
	$fileNames = $record['fileNames'];
	$artifactSet = array(
		"@type"                   => "sc:Dataset",
		"@id"                     => airlockTreUri("export", $record['_id']),
		"name"                    => count($fileNames) == 1 ? $fileNames[0] : "openVRE export of " . count($fileNames) . " files",
		"description"             => "Files exported from the openVRE workspace by " . $user->getEmail(),
		// ASSUMPTION: role of the set as a whole; not constrained by the Airlock API schema
		"airlock:artifactSetRole" => "egress_candidate",
		"airlock:releaseUnitType" => "file_set",
		"distribution"            => $distribution,
	);

	// ASSUMPTION: the "source artifact set manifest" projection is the
	// artifact set itself without its digest. Adjust if Airlock rejects it.
	$artifactSet["airlock:artifactSetDigest"] = array(
		"airlock:digestProfile"    => "airlock-source-artifact-set-manifest-v1",
		"airlock:algorithm"        => "sha256",
		"airlock:canonicalization" => "rfc8785-jcs",
		"airlock:value"            => hash('sha256', jcsCanonicalize($artifactSet)),
		"airlock:computedBy"       => "openVRE",
		"airlock:computedAt"       => date(DATE_ATOM),
	);

	return $artifactSet;
}


/**
 * LandingMoveCreateRequestV1 for an export record.
 */
function buildLandingMoveRequest(array $record, array $artifactSet, User $user, array $conformsTo): array
{
	return array(
		"@type"                   => "airlock:LandingMoveCreateRequest",
		"dct:conformsTo"          => $conformsTo,
		"airlock:clientRequestId" => "move-" . $record['_id'],
		"airlock:sourceSystem"    => array(
			"@type"               => "airlock:SourceSystem",
			"airlock:systemType"  => "VRE",
			"airlock:systemName"  => "openVRE",
		),
		"airlock:requestedBy"     => array("@type" => "sc:Person", "@id" => airlockTreUri("user", $user->getSecretsId())),
		"airlock:project"         => array("@type" => "airlock:TREProject", "@id" => airlockTreUri("project", $record['project'])),
		"airlock:artifactSet"     => $artifactSet,
		"airlock:sourceActivity"  => array(
			"@type"                           => "prov:Activity",
			"@id"                             => $record['activityId'],
			"name"                            => "Export request from the openVRE workspace",
			"prov:startedAtTime"              => $record['createdAt'],
			"airlock:provenanceCompleteness"  => array(
				"airlock:level"  => "source_record_only",
				"airlock:reason" => "Files selected by the user from the openVRE workspace; execution provenance is not tracked.",
			),
		),
	);
}


/**
 * Landing artifact ids allocated by Airlock in a landing move plan.
 */
function landingArtifactIdsFromPlan(array $plan): array
{
	$ids = array_map(fn($assignment) => $assignment['airlock:landingArtifact']['@id'] ?? null, $plan['airlock:artifactAssignments'] ?? array());

	return array_values(array_filter($ids));
}


/**
 * Stage the given workspace files and create an Airlock landing move for them.
 */
function requestLandingMove(array $fileIds, ?string $projectId = null): array
{
	$conformsTo = assertExportAvailable();

	$fileIds = array_values(array_unique(array_filter($fileIds, fn($id) => !empty($id) && $id !== 'undefined')));
	if (!$fileIds) {
		throw new UnexpectedValueException("No file selected for export.");
	}
	// validate every file before staging anything
	$files = array_map('getExportableFile', $fileIds);

	$user = getUserById($_SESSION['userId']);
	$exportId = uniqid($_SESSION['internalUserId'] . "_export_", false) . bin2hex(random_bytes(3));
	$record = array(
		"_id"        => $exportId,
		"owner"      => $_SESSION['internalUserId'],
		"project"    => $projectId ?: ($files[0]['project'] ?? $user->getActiveProject()),
		"fileIds"    => $fileIds,
		"fileNames"  => array_map(fn($f) => basename($f['path']), $files),
		"activityId" => airlockTreUri("activity", "export", $exportId),
		"state"      => AirlockExportState::MoveRequested->value,
		"createdAt"  => date(DATE_ATOM),
		"updatedAt"  => date(DATE_ATOM),
	);
	$GLOBALS['airlockExportsCol']->insertOne($record);

	$staged = array();
	try {
		$distribution = array();
		foreach ($files as $file) {
			$stagedFile = stageFileForAirlock($file, $exportId);
			$staged[] = $stagedFile['stagedPath'];
			$distribution[] = buildLandingFileObject($file, $stagedFile, $record['activityId']);
		}

		$request = buildLandingMoveRequest($record, buildLandingArtifactSet($record, $distribution, $user), $user, $conformsTo);
		$plan = airlockResponseData(AirlockClient::forCurrentUser()->createLandingMove($request), "Export $exportId: landing move failed");

		updateAirlockExport($exportId, array(
			"stagedPaths"        => $staged,
			"moveId"             => $plan['@id'] ?? null,
			"artifactSetId"      => $plan['airlock:landingArtifactSet']['@id'] ?? null,
			"landingArtifactIds" => landingArtifactIdsFromPlan($plan),
			"moveExpiresAt"      => $plan['airlock:expiresAt'] ?? null,
		));
		getAirlockLogger()->info("Landing move " . ($plan['@id'] ?? "?") . " created for export $exportId (" . implode(", ", $fileIds) . ")");
	} catch (Throwable $e) {
		// Airlock errors are already logged with their context
		if (!$e instanceof AirlockException) {
			getAirlockLogger()->error("Export $exportId: " . $e->getMessage());
		}
		cleanAirlockStaging(array("_id" => $exportId, "owner" => $_SESSION['internalUserId'], "stagedPaths" => $staged));
		updateAirlockExport($exportId, array("state" => AirlockExportState::Failed->value, "error" => $e->getMessage()));
		throw $e;
	}

	return airlockExportSummary(getAirlockExport($exportId));
}
