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
 *
 * Files: airlock.inc.php (shared helpers), airlockLandingMove.inc.php and
 * airlockLandingRequest.inc.php (1), airlockExportStatus.inc.php (2) and
 * airlockSubmission.inc.php (3).
 *
 * Identifiers are logical TRE URIs (tre://...). The staged copy of each file
 * is implied by convention: AIRLOCK_STAGING_DIR/<project>/<stagingId>/<path>,
 * where <path> is the file path inside the project and <stagingId> is the
 * landing move clientRequestId.
 */

use OpenVRE\AirlockException;
use OpenVRE\LoggerFactory;


const AIRLOCK_PREFIX                  = "airlock:";
const AIRLOCK_STATE_KEY               = "airlock:state";
const AIRLOCK_FILE_ROLE_KEY           = "airlock:fileRole";
const AIRLOCK_ROLE_CANDIDATE_RELEASE  = "candidate_release";
const AIRLOCK_ROLE_SUPPORTING         = "supporting";

const AIRLOCK_MSG_NOT_ALLOWED         = "User not allowed to export files.";
const AIRLOCK_MSG_EXPORT_NOT_FOUND    = "Export request %s not found.";

// JSON-LD mappings and vocabularies of the Airlock contract v1
const AIRLOCK_JSONLD_CONTEXT = array(
	"@vocab"  => "http://schema.org/",
	"sc"      => "http://schema.org/",
	"cr"      => "http://mlcommons.org/croissant/",
	"prov"    => "http://www.w3.org/ns/prov#",
	"dct"     => "http://purl.org/dc/terms/",
	"airlock" => "https://example.org/airlock#",
	"tre"     => "https://example.org/tre#",
);
const AIRLOCK_VOCABULARY = array("http://schema.org/", "http://mlcommons.org/croissant/", "http://www.w3.org/ns/prov#");
const AIRLOCK_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;


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


/**
 * Common head of an Airlock create request: JSON-LD context, type and the
 * contract profiles it conforms to (comma separated in the given setting).
 */
function airlockRequestHead(string $type, string $profilesSetting): array
{
	$profiles = array_values(array_filter(array_map('trim', explode(",", (string) ($GLOBALS[$profilesSetting] ?? "")))));
	if (!$profiles) {
		throw new UnexpectedValueException("Airlock contract profiles are not configured ($profilesSetting).");
	}

	return array(
		"@context"               => AIRLOCK_JSONLD_CONTEXT,
		"@type"                  => $type,
		"dct:conformsTo"         => $profiles,
		"airlock:usesVocabulary" => AIRLOCK_VOCABULARY,
	);
}


/**
 * RFC 8785 (JCS) canonical JSON. Covers the subset of JSON produced here:
 * strings, integers, booleans, null, lists and objects with ASCII keys.
 */
function jcsCanonicalize(mixed $value): string
{
	if (is_array($value)) {
		if ($value !== array() && !array_is_list($value)) {
			ksort($value, SORT_STRING);
			$members = array();
			foreach ($value as $k => $v) {
				$members[] = json_encode((string) $k, AIRLOCK_JSON_FLAGS) . ":" . jcsCanonicalize($v);
			}
			return "{" . implode(",", $members) . "}";
		}
		return "[" . implode(",", array_map('jcsCanonicalize', $value)) . "]";
	}
	if (is_float($value)) {
		throw new InvalidArgumentException("Floating point numbers are not supported in Airlock manifests.");
	}

	return json_encode($value, AIRLOCK_JSON_FLAGS);
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
 * Data of an Airlock response, or AirlockException if the request failed.
 */
function airlockResponseData(array $response, string $context): array
{
	if (!$response['ok']) {
		getAirlockLogger()->error("$context: " . $response['error']);
		throw AirlockException::fromResponse($response);
	}

	return $response['data'];
}
