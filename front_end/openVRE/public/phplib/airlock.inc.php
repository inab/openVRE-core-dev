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
 * Files: airlock.inc.php (shared helpers), airlockLandingMove.inc.php (1),
 * airlockExportStatus.inc.php (2) and airlockSubmission.inc.php (3).
 */

use OpenVRE\AirlockException;
use OpenVRE\LoggerFactory;


const AIRLOCK_PREFIX                  = "airlock:";
const AIRLOCK_STATE_KEY               = "airlock:state";
const AIRLOCK_FILE_ROLE_KEY           = "airlock:fileRole";
const AIRLOCK_ROLE_CANDIDATE_RELEASE  = "candidate_release";

const AIRLOCK_MSG_NOT_ALLOWED         = "User not allowed to export files.";
const AIRLOCK_MSG_EXPORT_NOT_FOUND    = "Export request %s not found.";


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
function jcsCanonicalize(mixed $value): string
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
