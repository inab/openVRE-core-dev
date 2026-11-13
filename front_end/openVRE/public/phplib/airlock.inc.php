<?php

/**
 * Export of workspace files through the TRE Airlock.
 *
 * Flow:
 *  1. requestLandingMove(): the files are staged in the NFS shared with
 *     Airlock and a landing move is created. Airlock copies and verifies the
 *     bytes asynchronously, producing an immutable landing artifact set.
 *  2. refreshExportStatus(): polls the landing artifact set until it is
 *     READY_FOR_SUBMISSION (or failed).
 *  3. createAirlockSubmission(): optional, creates the egress submission over
 *     the landed artifacts. It can also be done from the Airlock UI.
 *
 * Export requests are tracked in the 'airlockExports' collection.
 */

use OpenVRE\AirlockClient;
use OpenVRE\LoggerFactory;


const AIRLOCK_EXPORT_MOVE_REQUESTED = "MOVE_REQUESTED";
const AIRLOCK_EXPORT_READY          = "READY";
const AIRLOCK_EXPORT_SUBMITTED      = "SUBMITTED";
const AIRLOCK_EXPORT_FAILED         = "FAILED";

const AIRLOCK_TRI_STATES = array("yes", "no", "unknown", "not_applicable");
const AIRLOCK_REQUIRED_DECLARATIONS = array(
	"airlock:containsDirectIdentifiers",
	"airlock:containsPersonalData",
	"airlock:containsFreeText",
	"airlock:containsImagesOrMedia",
	"airlock:containsCode",
	"airlock:containsModelOrParameters",
);
const AIRLOCK_OPTIONAL_DECLARATIONS = array(
	"airlock:containsGenomicOrBiomedicalData",
	"airlock:smallCountOrOutlierRisk",
);


function getAirlockLogger()
{
	static $logger = null;

	if ($logger === null) {
		$logger = LoggerFactory::getLogger('Airlock interface');
	}

	return $logger;
}


/**
 * Logical TRE URI for an entity, e.g. tre://user/<id>. Airlock resolves these
 * to physical locations, so the contract does not depend on the storage.
 */
function airlockTreUri(string ...$parts): string
{
	$prefix = (string) $GLOBALS['airlockTreUriPrefix'];
	$prefix = str_ends_with($prefix, "://") ? $prefix : rtrim($prefix, "/") . "/";

	return $prefix . implode("/", array_map('rawurlencode', $parts));
}


function airlockConformsTo(): array
{
	$uris = array_values(array_filter(array_map('trim', explode(",", (string) $GLOBALS['airlockConformsTo']))));
	if (!$uris) {
		throw new UnexpectedValueException("Airlock contract is not configured (AIRLOCK_CONFORMS_TO is empty).");
	}

	return $uris;
}


/**
 * RFC 8785 (JCS) canonical JSON. Covers the subset of JSON produced here:
 * strings, integers, booleans, null, lists and objects with ASCII keys.
 */
function jcsCanonicalize($value): string
{
	if (is_array($value)) {
		if ($value !== array() && !array_is_list($value)) {
			ksort($value, SORT_STRING);
			$members = array();
			foreach ($value as $k => $v) {
				$members[] = json_encode((string) $k, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ":" . jcsCanonicalize($v);
			}
			return "{" . implode(",", $members) . "}";
		}
		return "[" . implode(",", array_map('jcsCanonicalize', $value)) . "]";
	}
	if (is_float($value)) {
		throw new InvalidArgumentException("Floating point numbers are not supported in Airlock manifests.");
	}

	return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}


function getAirlockExport(string $exportId): ?array
{
	return $GLOBALS['airlockExportsCol']->findOne(array('_id' => $exportId, 'owner' => $_SESSION['internalUserId']));
}


function updateAirlockExport(string $exportId, array $fields): void
{
	$fields['updatedAt'] = date(DATE_ATOM);
	$GLOBALS['airlockExportsCol']->updateOne(array('_id' => $exportId), array('$set' => $fields));
}


/**
 * Public view of an export record, as returned to the browser.
 */
function airlockExportSummary(array $export): array
{
	return array(
		"id"           => $export['_id'],
		"state"        => $export['state'],
		"files"        => $export['fileNames'] ?? array(),
		"submissionId" => $export['submissionId'] ?? null,
		"airlockUrl"   => $GLOBALS['airlockUrl'],
		"error"        => $export['error'] ?? null,
	);
}


/**
 * Location of a file in the NFS shared with Airlock. Workspace files are copied
 * there; remote files that already live in that NFS will only need to resolve
 * their location (not implemented yet).
 */
function stageFileForAirlock(array $file, string $exportId): array
{
	$source = $GLOBALS['userDataDir'] . "/" . $file['path'];
	if (!is_file($source) || !is_readable($source)) {
		throw new RuntimeException("File '" . basename($file['path']) . "' not found in disk.");
	}

	$name = basename($file['path']);
	$relDir = $_SESSION['internalUserId'] . "/" . $exportId;
	$targetDir = $GLOBALS['airlockStagingDir'] . "/" . $relDir;
	if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
		throw new RuntimeException("Cannot create the export staging directory.");
	}

	$target = $targetDir . "/" . $name;
	if (!copy($source, $target)) {
		throw new RuntimeException("Cannot copy '$name' to the export staging area.");
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
	@rmdir($dir);
}


/**
 * Stage the given workspace files and create an Airlock landing move for them.
 */
function requestLandingMove(array $fileIds, ?string $projectId = null): array
{
	if (!canExport()) {
		throw new UnexpectedValueException("User not allowed to export files.");
	}
	if (empty($GLOBALS['airlockStagingDir']) || !is_dir($GLOBALS['airlockStagingDir'])) {
		throw new UnexpectedValueException("Airlock staging area is not available (AIRLOCK_STAGING_DIR).");
	}
	$conformsTo = airlockConformsTo();

	$fileIds = array_values(array_unique(array_filter($fileIds, fn($id) => !empty($id) && $id !== 'undefined')));
	if (!$fileIds) {
		throw new UnexpectedValueException("No file selected for export.");
	}

	// validate ownership and kind of every file before staging anything
	$files = array();
	foreach ($fileIds as $fileId) {
		$file = getGSFile_fromId($fileId);
		if (is_null($file)) {
			throw new UnexpectedValueException("File $fileId not found or not owned by the user.");
		}
		if (isset($file['files'])) {
			throw new UnexpectedValueException("Folders cannot be exported. Select the files instead.");
		}
		if (preg_match('/^\/gpfs\//', $file['path']) || !empty($file['remote'])) {
			throw new UnexpectedValueException("Remote file '" . basename($file['path']) . "' cannot be exported yet.");
		}
		$files[] = $file;
	}

	$user = getUserById($_SESSION['userId']);
	$exportId = uniqid($_SESSION['internalUserId'] . "_export_", false) . bin2hex(random_bytes(3));
	$projectId = $projectId ?: ($files[0]['project'] ?? $user->getActiveProject());
	$activityId = airlockTreUri("activity", "export", $exportId);

	$record = array(
		"_id"       => $exportId,
		"owner"     => $_SESSION['internalUserId'],
		"project"   => $projectId,
		"fileIds"   => $fileIds,
		"fileNames" => array_map(fn($f) => basename($f['path']), $files),
		"state"     => AIRLOCK_EXPORT_MOVE_REQUESTED,
		"createdAt" => date(DATE_ATOM),
		"updatedAt" => date(DATE_ATOM),
	);
	$GLOBALS['airlockExportsCol']->insertOne($record);

	$staged = array();
	try {
		$distribution = array();
		foreach ($files as $file) {
			$s = stageFileForAirlock($file, $exportId);
			$staged[] = $s['stagedPath'];

			$fileObject = array(
				"@type"                => "cr:FileObject",
				"@id"                  => airlockTreUri("file", $file['_id']),
				"name"                 => $s['name'],
				"contentUrl"           => $s['contentUrl'],
				"contentSize"          => $s['size'],
				"encodingFormat"       => $s['mimeType'],
				"airlock:fileFormat"   => !empty($file['format']) ? $file['format'] : "UNK",
				"sha256"               => $s['sha256'],
				"airlock:fileRole"     => "candidate_release",
				"prov:wasGeneratedBy"  => array("@id" => $activityId),
				"tre:sourceArtifactId" => $file['_id'],
			);
			if (!empty($file['data_type'])) {
				$fileObject["tre:dataType"] = $file['data_type'];
			}
			if (!empty($file['description'])) {
				$fileObject["description"] = (string) $file['description'];
			}
			$distribution[] = $fileObject;
		}

		$artifactSet = array(
			"@type"                   => "sc:Dataset",
			"@id"                     => airlockTreUri("export", $exportId),
			"name"                    => count($files) == 1 ? $record['fileNames'][0] : "openVRE export of " . count($files) . " files",
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

		$request = array(
			"@type"                   => "airlock:LandingMoveCreateRequest",
			"dct:conformsTo"          => $conformsTo,
			"airlock:clientRequestId" => "move-" . $exportId,
			"airlock:sourceSystem"    => array(
				"@type"               => "airlock:SourceSystem",
				"airlock:systemType"  => "VRE",
				"airlock:systemName"  => "openVRE",
			),
			"airlock:requestedBy"     => array("@type" => "sc:Person", "@id" => airlockTreUri("user", $user->getSecretsId())),
			"airlock:project"         => array("@type" => "airlock:TREProject", "@id" => airlockTreUri("project", $projectId)),
			"airlock:artifactSet"     => $artifactSet,
			"airlock:sourceActivity"  => array(
				"@type"                           => "prov:Activity",
				"@id"                             => $activityId,
				"name"                            => "Export request from the openVRE workspace",
				"prov:startedAtTime"              => $record['createdAt'],
				"airlock:provenanceCompleteness"  => array(
					"airlock:level"  => "source_record_only",
					"airlock:reason" => "Files selected by the user from the openVRE workspace; execution provenance is not tracked.",
				),
			),
		);

		$response = AirlockClient::forCurrentUser()->createLandingMove($request);
		if (!$response['ok']) {
			throw new RuntimeException($response['error']);
		}

		$plan = $response['data'];
		$landingArtifactIds = array();
		foreach ($plan['airlock:artifactAssignments'] ?? array() as $assignment) {
			if (isset($assignment['airlock:landingArtifact']['@id'])) {
				$landingArtifactIds[] = $assignment['airlock:landingArtifact']['@id'];
			}
		}

		updateAirlockExport($exportId, array(
			"stagedPaths"        => $staged,
			"moveId"             => $plan['@id'] ?? null,
			"artifactSetId"      => $plan['airlock:landingArtifactSet']['@id'] ?? null,
			"landingArtifactIds" => $landingArtifactIds,
			"moveExpiresAt"      => $plan['airlock:expiresAt'] ?? null,
		));
		getAirlockLogger()->info("Landing move " . ($plan['@id'] ?? "?") . " created for export $exportId (" . implode(", ", $fileIds) . ")");
	} catch (Throwable $e) {
		getAirlockLogger()->error("Export $exportId failed: " . $e->getMessage());
		cleanAirlockStaging(array("_id" => $exportId, "owner" => $_SESSION['internalUserId'], "stagedPaths" => $staged));
		updateAirlockExport($exportId, array("state" => AIRLOCK_EXPORT_FAILED, "error" => $e->getMessage()));
		throw $e;
	}

	return airlockExportSummary(getAirlockExport($exportId));
}


/**
 * Check in Airlock whether the landed artifacts are verified and ready.
 */
function refreshExportStatus(string $exportId): array
{
	$export = getAirlockExport($exportId);
	if (is_null($export)) {
		throw new UnexpectedValueException("Export request $exportId not found.");
	}
	if ($export['state'] !== AIRLOCK_EXPORT_MOVE_REQUESTED || empty($export['artifactSetId'])) {
		return airlockExportSummary($export);
	}

	$response = AirlockClient::forCurrentUser()->getLandingArtifactSet($export['artifactSetId']);
	if (!$response['ok']) {
		// the set may not be materialized while the transfer is running
		if ($response['status'] == 404) {
			return airlockExportSummary($export);
		}
		throw new RuntimeException($response['error']);
	}

	$set = $response['data'];
	switch ($set['airlock:state'] ?? "") {
		case "READY_FOR_SUBMISSION":
			// use the verified landed artifacts that can be released
			$ids = array();
			foreach ($set['airlock:artifactSet']['distribution'] ?? array() as $landed) {
				if (($landed['airlock:fileRole'] ?? "candidate_release") === "candidate_release" && isset($landed['@id'])) {
					$ids[] = $landed['@id'];
				}
			}
			$fields = array("state" => AIRLOCK_EXPORT_READY);
			if ($ids) {
				$fields["landingArtifactIds"] = $ids;
			}
			updateAirlockExport($exportId, $fields);
			// Airlock holds its own verified copy now
			cleanAirlockStaging($export);
			getAirlockLogger()->info("Landing artifact set " . $export['artifactSetId'] . " ready for export $exportId");
			break;

		case "FAILED":
		case "QUARANTINED":
		case "EXPIRED":
			$error = "Airlock could not land the files (state " . $set['airlock:state'] . ").";
			updateAirlockExport($exportId, array("state" => AIRLOCK_EXPORT_FAILED, "error" => $error));
			cleanAirlockStaging($export);
			getAirlockLogger()->error("Export $exportId: $error");
			break;

		default: // VERIFYING
			break;
	}

	return airlockExportSummary(getAirlockExport($exportId));
}


/**
 * Create the egress submission for a landed export. The form carries the
 * release purpose, review context and disclosure risk declarations.
 */
function createAirlockSubmission(string $exportId, array $form): array
{
	if (!canExport()) {
		throw new UnexpectedValueException("User not allowed to export files.");
	}
	$export = getAirlockExport($exportId);
	if (is_null($export)) {
		throw new UnexpectedValueException("Export request $exportId not found.");
	}
	if ($export['state'] === AIRLOCK_EXPORT_SUBMITTED) {
		return airlockExportSummary($export);
	}
	if ($export['state'] !== AIRLOCK_EXPORT_READY) {
		throw new UnexpectedValueException("Files are not ready in the Airlock yet.");
	}
	if (empty($GLOBALS['airlockReleaseTarget']) || empty($GLOBALS['airlockRecipientGroup'])) {
		throw new UnexpectedValueException("Airlock release destination is not configured (AIRLOCK_RELEASE_TARGET, AIRLOCK_RECIPIENT_GROUP).");
	}
	if (empty($export['landingArtifactIds'])) {
		throw new UnexpectedValueException("No landed artifacts available for the submission.");
	}

	$text = fn($key) => trim((string) ($form[$key] ?? ""));
	foreach (array("title" => "Title", "description" => "Description", "purpose" => "Release purpose", "minimization" => "Data minimisation statement") as $key => $label) {
		if ($text($key) === "") {
			throw new UnexpectedValueException("$label is required.");
		}
	}

	$declarations = array(
		"@type"                              => "airlock:DisclosureRiskDeclaration",
		"airlock:dataMinimizationStatement"  => $text("minimization"),
	);
	$formDeclarations = is_array($form['declarations'] ?? null) ? $form['declarations'] : array();
	foreach (array_merge(AIRLOCK_REQUIRED_DECLARATIONS, AIRLOCK_OPTIONAL_DECLARATIONS) as $field) {
		$key = substr($field, strlen("airlock:"));
		$value = $formDeclarations[$key] ?? "";
		if ($value === "" && in_array($field, AIRLOCK_OPTIONAL_DECLARATIONS)) {
			continue;
		}
		if (!in_array($value, AIRLOCK_TRI_STATES, true)) {
			throw new UnexpectedValueException("Please, answer all the disclosure risk declarations.");
		}
		$declarations[$field] = $value;
	}

	$clientRequestId = $export['submissionClientRequestId'] ?? ("sub-" . $exportId);
	updateAirlockExport($exportId, array("submissionClientRequestId" => $clientRequestId));

	$request = array(
		"@type"                          => "airlock:EgressSubmissionCreateRequest",
		"dct:conformsTo"                 => airlockConformsTo(),
		"airlock:clientRequestId"        => $clientRequestId,
		"airlock:usesLandingArtifactSet" => array(array("@id" => $export['artifactSetId'])),
		"airlock:selectedFiles"          => array_map(fn($id) => array("@id" => $id), $export['landingArtifactIds']),
		"airlock:releaseRequest"         => array(
			"@type"                         => "airlock:ReleaseRequest",
			"airlock:releasePurpose"        => $text("purpose"),
			"airlock:requestedDestination"  => array(
				"@type"                  => "airlock:ReleaseDestination",
				"airlock:target"         => array("@id" => $GLOBALS['airlockReleaseTarget']),
				"airlock:recipientGroup" => array("@id" => $GLOBALS['airlockRecipientGroup']),
			),
		),
		"airlock:submitterDeclarations"  => $declarations,
		"airlock:reviewContext"          => array(
			"@type"                         => "airlock:ReviewContext",
			"airlock:submissionTitle"       => $text("title"),
			"airlock:submissionDescription" => $text("description"),
		),
	);

	$response = AirlockClient::forCurrentUser()->createEgressSubmission($request);
	if (!$response['ok']) {
		getAirlockLogger()->error("Submission for export $exportId failed: " . $response['error']);
		updateAirlockExport($exportId, array("error" => $response['error']));
		throw new RuntimeException($response['error']);
	}

	$submission = $response['data'];
	updateAirlockExport($exportId, array(
		"state"        => AIRLOCK_EXPORT_SUBMITTED,
		"submissionId" => $submission['@id'] ?? null,
		"caseId"       => $submission['airlock:case']['@id'] ?? null,
		"error"        => null,
	));
	getAirlockLogger()->info("Egress submission " . ($submission['@id'] ?? "?") . " created for export $exportId");

	return airlockExportSummary(getAirlockExport($exportId));
}
