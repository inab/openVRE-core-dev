<?php

/**
 * Airlock export, step 1: builders of the landing move request
 * (OpenVRE landing move profile). See airlock.inc.php for the flow.
 */

use OpenVRE\User;


/**
 * Software agent of an openVRE tool, as required by the OpenVRE profile.
 */
function buildToolAgent(string $toolId, ?array $tool): array
{
	$agent = array(
		"@type" => array("sc:SoftwareApplication", "prov:SoftwareAgent"),
		"@id"   => airlockTreUri("tool", $toolId),
		"name"  => $toolId,
	);
	foreach ($tool['infrastructure']['clouds'] ?? array() as $cloud) {
		if (!empty($cloud['launcher'])) {
			$agent["airlock:launcher"] = $cloud['launcher'];
		}
		if (!empty($cloud['default_cloud'])) {
			break;
		}
	}

	return $agent;
}


/**
 * Source activity of an export: the tool job that produced the files of the
 * execution folder, or the upload when the folder has no job (e.g. uploads).
 */
function buildSourceActivity(array $folder): array
{
	$activity = array(
		"@type"                  => "prov:Activity",
		"@id"                    => airlockTreUri("job", $folder['_id']),
		"airlock:executionId"    => basename($folder['path']),
		"airlock:jobStateAtMove" => "DONE",
	);

	if (empty($folder['tool'])) {
		return $activity + array(
			"name"                           => "Files uploaded to the openVRE workspace",
			"airlock:jobType"                => "upload",
			"prov:wasAssociatedWith"         => buildToolAgent("openvre-workspace", null),
			"airlock:provenanceCompleteness" => array(
				"airlock:level"  => "source_record_only",
				"airlock:reason" => "Files uploaded or imported into the openVRE workspace; no producing job is recorded.",
			),
		);
	}

	$tool = $GLOBALS['toolsCol']->findOne(array('_id' => $folder['tool']));
	$activity += array(
		"name"                           => ($tool['name'] ?? $folder['tool']) . " job",
		"airlock:jobType"                => isset($tool['infrastructure']['container_port']) ? "interactive" : "batch",
		"prov:wasAssociatedWith"         => buildToolAgent($folder['tool'], $tool),
		"prov:used"                      => buildJobInputs($folder),
		"airlock:provenanceCompleteness" => array(
			"airlock:level"  => "job_level",
			"airlock:reason" => "openVRE tool job; tool, inputs and arguments are recorded, command-level lineage is not captured.",
		),
	);
	if (isset($tool['infrastructure']['cpus'], $tool['infrastructure']['memory'])) {
		$activity["airlock:executionEnvironment"] = array(
			"airlock:cpus"     => (int) $tool['infrastructure']['cpus'],
			"airlock:memoryGb" => (int) $tool['infrastructure']['memory'],
		);
	}

	return $activity;
}


/**
 * Input files of a job, as Croissant file references.
 */
function buildJobInputs(array $folder): array
{
	$inputs = array();
	foreach ($folder['input_files'] ?? array() as $inputId) {
		$input = getGSFile_fromId($inputId, "onlyData", 1);
		if (!is_null($input)) {
			$inputs[] = array("@type" => "cr:FileObject", "@id" => airlockTreUri("artifact", $inputId), "name" => basename($input['path']));
		}
	}

	return $inputs;
}


/**
 * Croissant file object describing a staged file in the landing move request.
 */
function buildLandingFileObject(array $file, array $staged, array $activity, string $role): array
{
	$fileObject = array(
		"@type"                => "cr:FileObject",
		"@id"                  => airlockTreUri("artifact", $file['_id']),
		"name"                 => basename($file['path']),
		"contentUrl"           => $staged['contentUrl'],
		"contentSize"          => $staged['size'],
		"encodingFormat"       => $staged['mimeType'],
		"airlock:fileFormat"   => !empty($file['format']) ? $file['format'] : "UNK",
		"sha256"               => $staged['sha256'],
		AIRLOCK_FILE_ROLE_KEY  => $role,
		"tre:sourceArtifactId" => $file['_id'],
		"prov:wasGeneratedBy"  => array("@id" => $activity['@id']),
		"prov:wasDerivedFrom"  => array(),
	);
	if (!empty($file['data_type'])) {
		$fileObject["tre:dataType"] = $file['data_type'];
	}
	if (!empty($file['description'])) {
		$fileObject["description"] = (string) $file['description'];
	}
	if ($role === AIRLOCK_ROLE_CANDIDATE_RELEASE) {
		$fileObject["prov:wasDerivedFrom"] = array_map(fn($input) => array("@id" => $input['@id']), $activity['prov:used'] ?? array());
	}

	return $fileObject;
}


/**
 * Log file of the job of an execution folder, as a workspace file record, so
 * it can be landed as a supporting artifact. Null when there is none.
 */
function getJobLogFile(array $folder, array $selectedIds): ?array
{
	$logFile = (string) ($folder['log_file'] ?? "");
	$path = str_starts_with($logFile, "/") ? fromAbsPath_toPath($logFile) : $logFile;
	$exists = $path !== "" && is_file($GLOBALS['userDataDir'] . "/" . $path);
	$logId = null;
	if ($exists) {
		$logId = getGSFileId_fromPath($path) ?: ("log_" . $folder['_id']);
	}
	// already exported as a releasable file, or no log at all
	if (is_null($logId) || in_array($logId, $selectedIds, true)) {
		return null;
	}

	return array("_id" => $logId, "path" => $path, "format" => "LOG", "data_type" => "job_log", "description" => "Log of the job that produced the exported files; supporting context, not for release.");
}


/**
 * Projection of the artifact set hashed for 'airlock-source-artifact-set-manifest-v1',
 * as in Airlock's build_source_artifact_set_manifest (contracts/canonicalization.py):
 * a fixed set of properties, missing or null ones left out, set-like arrays sorted by @id.
 */
function airlockSourceManifestProjection(array $artifactSet): array
{
	$byId = fn($a, $b) => strcmp($a['@id'], $b['@id']);
	$fileKeys = array("@type", "@id", "name", "contentUrl", "contentSize", "encodingFormat", "airlock:fileFormat", "sha256", AIRLOCK_FILE_ROLE_KEY, "tre:sourceArtifactId", "tre:dataType");

	$files = array();
	foreach ($artifactSet['distribution'] as $file) {
		$projected = array_filter(array_intersect_key($file, array_flip($fileKeys)), fn($v) => !is_null($v));
		$projected["prov:wasGeneratedBy"] = array("@id" => $file['prov:wasGeneratedBy']['@id']);
		$derivedFrom = array_map(fn($ref) => array("@id" => $ref['@id']), $file['prov:wasDerivedFrom'] ?? array());
		usort($derivedFrom, $byId);
		$projected["prov:wasDerivedFrom"] = $derivedFrom;
		$files[] = $projected;
	}
	usort($files, $byId);

	$setKeys = array("@type", "@id", "name", "airlock:artifactSetRole", "airlock:releaseUnitType");
	$projection = array_filter(array_intersect_key($artifactSet, array_flip($setKeys)), fn($v) => !is_null($v));
	$projection["distribution"] = $files;

	return $projection;
}


/**
 * Artifact set of the landing move request, including its manifest digest.
 */
function buildLandingArtifactSet(array $record, array $distribution, User $user): array
{
	// stable order, so the digest does not depend on the selection order
	usort($distribution, fn($a, $b) => strcmp($a['@id'], $b['@id']));
	$fileNames = $record['fileNames'];
	$artifactSet = array(
		"@type"                   => "sc:Dataset",
		"@id"                     => airlockTreUri("artifact-set", $record['_id']),
		"name"                    => count($fileNames) == 1 ? $fileNames[0] : "openVRE export of " . count($fileNames) . " files",
		"description"             => "Files exported from the openVRE workspace by " . $user->getEmail(),
		"airlock:artifactSetRole" => "landing_artifact_set",
		"airlock:releaseUnitType" => "file_set",
		"distribution"            => $distribution,
	);
	$artifactSet["airlock:artifactSetDigest"] = array(
		"@type"                    => "airlock:ManifestDigest",
		"airlock:algorithm"        => "sha256",
		"airlock:canonicalization" => "rfc8785-jcs",
		"airlock:digestProfile"    => "airlock-source-artifact-set-manifest-v1",
		"airlock:value"            => hash('sha256', jcsCanonicalize(airlockSourceManifestProjection($artifactSet))),
		"airlock:computedBy"       => "TRE",
		"airlock:computedAt"       => gmdate("Y-m-d\TH:i:s\Z"),
	);

	return $artifactSet;
}


/**
 * LandingMoveCreateRequestV1 (OpenVRE profile) for an export record.
 */
function buildLandingMoveRequest(array $record, array $artifactSet, array $activity, User $user, ?string $logArtifactId): array
{
	$request = airlockRequestHead("airlock:LandingMoveCreateRequest", 'airlockLandingConformsTo') + array(
		"airlock:clientRequestId" => $record['_id'],
		"airlock:sourceSystem"    => array("@type" => "airlock:SourceSystem", "airlock:systemType" => "TRE", "airlock:systemName" => "OpenVRE"),
		"airlock:requestedBy"     => array("@type" => "sc:Person", "@id" => airlockTreUri("user", $user->getSecretsId())),
		"airlock:project"         => array("@type" => "airlock:TREProject", "@id" => airlockTreUri("project", $record['project'])),
		"airlock:artifactSet"     => $artifactSet,
		"airlock:sourceActivity"  => $activity,
	);
	if ($logArtifactId) {
		$request["airlock:internalTREReferences"] = array(
			"@type"              => "airlock:TREProvenanceReferenceSet",
			"airlock:references" => array(array(
				"@type"            => "airlock:TREProvenanceReference",
				"airlock:role"     => "log",
				"airlock:artifact" => array("@id" => $logArtifactId),
			)),
		);
	}

	return $request;
}
