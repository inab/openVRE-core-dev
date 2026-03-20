<?php

use OpenVRE\LinkedAccount;
use OpenVRE\LoggerFactory;


function getLinkedAccountLogger()
{
	static $logger = null;

	if ($logger === null) {
		$logger = LoggerFactory::getLogger('Linked account interface');
	}

	return $logger;
}


function updateAccount(LinkedAccount $linkedAccount, $credentials)
{
	try {
		$linkedAccount->storeCredentials($credentials);
		$_SESSION['errorData']['Info'][] = $linkedAccount->getSite() . " account successfully linked.";
		redirect($_SERVER['HTTP_REFERER']);
	} catch (Exception $e) {
		getLinkedAccountLogger()->error("Could not connect to" . $linkedAccount->getSite() . ": " . $e->getMessage());
		throw new UnexpectedValueException("Could not connect to " . $linkedAccount->getSite() . ". Check your credentials and try again.");
	}
}


function removeAccount(LinkedAccount $linkedAccount)
{
	$linkedAccount->removeCredentials();
	redirect($_SERVER['HTTP_REFERER']);
}


function generateSSHButtons()
{
	// Check if $GLOBALS['sitesCol'] is set
	if (isset($GLOBALS['sitesCol'])) {
		// Fetch the documents that have "SSH" in the "accessible_via" array
		$documents = $GLOBALS['sitesCol']->find([
			'launcher.accessible_via' => 'SSH'  // Filter condition
		]);

		// Initialize HTML output and results flag
		$buttonsHTML = '';
		$documentsFound = false;

		// Iterate through the documents
		foreach ($documents as $document) {
			$documentsFound = true;

			// Prepare the data to fill up the buttons
			$siteId = (string) $document['_id'];
			$siteSigla = (string) $document['sigla'];

			// Create the button for each site
			$buttonsHTML .= '
                <div class="row" style="margin-left:0px;margin-bottom:5px">
                    <div class="col-md-6">
                        <a href="' . $GLOBALS['BASEURL'] . 'user/linkedAccount.php?account=SSH&action=new" class="btn green" data-site-id="' . $siteId . '">
                            <i class="fa fa-plus"></i> &nbsp; Link your account (' . $siteSigla . ')
                        </a>
                    </div>
                </div>';
		}

		// Check if no documents were found
		if (!$documentsFound) {
			$_SESSION['errorData']['Error'][] = "No SSH-accessible sites found.";
			$buttonsHTML = '<p>No HPC accounts found with SSH access.</p>';
		}

		return $buttonsHTML;
	} else {
		// Log if $GLOBALS['sitesCol'] is not set
		$_SESSION['errorData']['Error'][] = "Sites collection is not available.";
		return '<p>Sites collection is not available.</p>';
	}
}
