<?php

/**
 * Airlock export, step 2: follow the landing artifact set until Airlock has
 * copied and verified the files. See airlock.inc.php for the flow.
 */

use OpenVRE\AirlockClient;
use OpenVRE\AirlockExportState;
use OpenVRE\LandingArtifactSetState;


/**
 * Ids of the verified landed artifacts that can be selected for release.
 */
function releasableLandedArtifactIds(array $artifactSetRecord): array
{
	$releasable = array_filter(
		$artifactSetRecord['airlock:artifactSet']['distribution'] ?? array(),
		fn($landed) => isset($landed['@id']) && ($landed[AIRLOCK_FILE_ROLE_KEY] ?? AIRLOCK_ROLE_CANDIDATE_RELEASE) === AIRLOCK_ROLE_CANDIDATE_RELEASE
	);

	return array_values(array_map(fn($landed) => $landed['@id'], $releasable));
}


/**
 * Check in Airlock whether the landed artifacts are verified and ready.
 */
function refreshExportStatus(string $exportId): array
{
	$export = getAirlockExport($exportId);
	if (is_null($export)) {
		throw new UnexpectedValueException(sprintf(AIRLOCK_MSG_EXPORT_NOT_FOUND, $exportId));
	}
	if ($export['state'] !== AirlockExportState::MoveRequested->value || empty($export['artifactSetId'])) {
		return airlockExportSummary($export);
	}

	$response = AirlockClient::forCurrentUser()->getLandingArtifactSet($export['artifactSetId']);
	// the set may not be materialized while the transfer is running
	if (!$response['ok'] && $response['status'] == 404) {
		return airlockExportSummary($export);
	}
	$set = airlockResponseData($response, "Export $exportId: status check of " . $export['artifactSetId'] . " failed");

	$setState = LandingArtifactSetState::tryFrom($set[AIRLOCK_STATE_KEY] ?? "");
	switch ($setState) {
		case LandingArtifactSetState::ReadyForSubmission:
			$fields = array("state" => AirlockExportState::Ready->value);
			$ids = releasableLandedArtifactIds($set);
			if ($ids) {
				$fields["landingArtifactIds"] = $ids;
			}
			updateAirlockExport($exportId, $fields);
			// Airlock holds its own verified copy now
			cleanAirlockStaging($export);
			getAirlockLogger()->info("Landing artifact set " . $export['artifactSetId'] . " ready for export $exportId");
			break;

		case LandingArtifactSetState::Failed:
		case LandingArtifactSetState::Quarantined:
		case LandingArtifactSetState::Expired:
			$error = "Airlock could not land the files (state " . $setState->value . ").";
			updateAirlockExport($exportId, array("state" => AirlockExportState::Failed->value, "error" => $error));
			cleanAirlockStaging($export);
			getAirlockLogger()->error("Export $exportId: $error");
			break;

		default: // still verifying, or a state unknown to this client
			break;
	}

	return airlockExportSummary(getAirlockExport($exportId));
}
