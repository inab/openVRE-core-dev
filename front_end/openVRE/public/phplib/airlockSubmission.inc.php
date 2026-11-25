<?php

/**
 * Airlock export, step 3 (optional): create the egress submission over the
 * landed files. It can also be done from the Airlock UI. See airlock.inc.php.
 */

use OpenVRE\AirlockClient;
use OpenVRE\AirlockException;
use OpenVRE\AirlockExportState;


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

/**
 * Export record ready for an egress submission. Returns null if it was already
 * submitted.
 */
function getSubmittableExport(string $exportId): ?array
{
	if (!canExport()) {
		throw new UnexpectedValueException(AIRLOCK_MSG_NOT_ALLOWED);
	}
	$export = getAirlockExport($exportId);
	if (is_null($export)) {
		throw new UnexpectedValueException(sprintf(AIRLOCK_MSG_EXPORT_NOT_FOUND, $exportId));
	}
	if ($export['state'] === AirlockExportState::Submitted->value) {
		return null;
	}
	if ($export['state'] !== AirlockExportState::Ready->value) {
		throw new UnexpectedValueException("Files are not ready in the Airlock yet.");
	}
	if (empty($export['landingArtifactIds'])) {
		throw new UnexpectedValueException("No landed artifacts available for the submission.");
	}
	if (empty($GLOBALS['airlockReleaseTarget']) || empty($GLOBALS['airlockRecipientGroup'])) {
		throw new UnexpectedValueException("Airlock release destination is not configured (AIRLOCK_RELEASE_TARGET, AIRLOCK_RECIPIENT_GROUP).");
	}

	return $export;
}


/**
 * Trimmed text fields of the submission form; all of them are required.
 */
function readSubmissionTexts(array $form): array
{
	$labels = array("title" => "Title", "description" => "Description", "purpose" => "Release purpose", "minimization" => "Data minimisation statement");

	$texts = array();
	foreach ($labels as $key => $label) {
		$texts[$key] = trim((string) ($form[$key] ?? ""));
		if ($texts[$key] === "") {
			throw new UnexpectedValueException("$label is required.");
		}
	}

	return $texts;
}


/**
 * DisclosureRiskDeclaration from the submission form answers.
 */
function buildDisclosureDeclarations(array $answers, string $minimizationStatement): array
{
	$declarations = array(
		"@type"                             => "airlock:DisclosureRiskDeclaration",
		"airlock:dataMinimizationStatement" => $minimizationStatement,
	);

	foreach (array_merge(AIRLOCK_REQUIRED_DECLARATIONS, AIRLOCK_OPTIONAL_DECLARATIONS) as $field) {
		$value = $answers[substr($field, strlen(AIRLOCK_PREFIX))] ?? "";
		$isOptionalUnanswered = $value === "" && in_array($field, AIRLOCK_OPTIONAL_DECLARATIONS, true);
		if (!$isOptionalUnanswered && !in_array($value, AIRLOCK_TRI_STATES, true)) {
			throw new UnexpectedValueException("Please, answer all the disclosure risk declarations.");
		}
		if (!$isOptionalUnanswered) {
			$declarations[$field] = $value;
		}
	}

	return $declarations;
}


/**
 * EgressSubmissionCreateRequestV1 for a landed export.
 */
function buildEgressSubmissionRequest(array $export, string $clientRequestId, array $texts, array $declarations): array
{
	$user = getUserById($_SESSION['userId']);

	return airlockRequestHead("airlock:EgressSubmissionCreateRequest", 'airlockSubmissionConformsTo') + array(
		"airlock:clientRequestId"        => $clientRequestId,
		"airlock:requestedBy"            => array("@type" => "sc:Person", "@id" => airlockTreUri("user", $user->getSecretsId())),
		"airlock:usesLandingArtifactSet" => array(array("@id" => $export['artifactSetId'])),
		"airlock:selectedFiles"          => array_map(fn($id) => array("@id" => $id), $export['landingArtifactIds']),
		"airlock:releaseRequest"         => array(
			"@type"                         => "airlock:ReleaseRequest",
			"airlock:releasePurpose"        => $texts['purpose'],
			"airlock:requestedDestination"  => array(
				"@type"                  => "airlock:ReleaseDestination",
				"airlock:target"         => array("@id" => $GLOBALS['airlockReleaseTarget']),
				"airlock:recipientGroup" => array("@id" => $GLOBALS['airlockRecipientGroup']),
			),
		),
		"airlock:submitterDeclarations"  => $declarations,
		"airlock:reviewContext"          => array(
			"@type"                         => "airlock:ReviewContext",
			"airlock:submissionTitle"       => $texts['title'],
			"airlock:submissionDescription" => $texts['description'],
		),
	);
}


/**
 * Create the egress submission for a landed export. The form carries the
 * release purpose, review context and disclosure risk declarations.
 */
function createAirlockSubmission(string $exportId, array $form): array
{
	$export = getSubmittableExport($exportId);
	if (is_null($export)) {
		return airlockExportSummary(getAirlockExport($exportId));
	}

	$texts = readSubmissionTexts($form);
	$answers = is_array($form['declarations'] ?? null) ? $form['declarations'] : array();
	$declarations = buildDisclosureDeclarations($answers, $texts['minimization']);

	$clientRequestId = $export['submissionClientRequestId'] ?? ($exportId . "-egress");
	updateAirlockExport($exportId, array("submissionClientRequestId" => $clientRequestId));

	$request = buildEgressSubmissionRequest($export, $clientRequestId, $texts, $declarations);
	try {
		$submission = airlockResponseData(AirlockClient::forCurrentUser()->createEgressSubmission($request), "Export $exportId: submission failed");
	} catch (AirlockException $e) {
		updateAirlockExport($exportId, array("error" => $e->getMessage()));
		throw $e;
	}

	updateAirlockExport($exportId, array(
		"state"        => AirlockExportState::Submitted->value,
		"submissionId" => $submission['@id'] ?? null,
		"caseId"       => $submission['airlock:case']['@id'] ?? null,
		"error"        => null,
	));
	getAirlockLogger()->info("Egress submission " . ($submission['@id'] ?? "?") . " created for export $exportId");

	return airlockExportSummary(getAirlockExport($exportId));
}
