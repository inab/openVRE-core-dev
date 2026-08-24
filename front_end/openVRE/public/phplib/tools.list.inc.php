<?php

use OpenVRE\LoggerFactory;
use OpenVRE\NotFoundException;
use OpenVRE\Tooljob;
use OpenVRE\User;
use OpenVRE\UserType;


function getToolsLogger()
{
	static $logger = null;

	if ($logger === null) {
		$logger = LoggerFactory::getLogger('Tools interface');
	}

	return $logger;
}


function getTools_List(User $user, $status = 1)
{
	if ($_SESSION['userType'] == UserType::Guest->value) {
		$tools = $GLOBALS['toolsCol']->find(array('external' => true, 'status' => $status, 'owner.license' => array('$ne' => "free_for_academics")), array('name' => 1, 'title' => 1, 'short_description' => 1, 'keywords' => 1), array('title' => 1));
	} elseif ($_SESSION['userType'] == UserType::Admin->value || $_SESSION['userType'] == UserType::ToolDev->value) {
		$tools = $GLOBALS['toolsCol']->find(array('external' => true, 'status' => $status), array('name' => 1, 'title' => 1, 'short_description' => 1, 'keywords' => 1, 'status' => 1), array('title' => 1));
	} else {
		$tools = $GLOBALS['toolsCol']->find(array('external' => true, 'status' => $status), array('name' => 1, 'title' => 1, 'short_description' => 1, 'keywords' => 1), array('title' => 1));
	}

	if ($_SESSION['userType'] == UserType::ToolDev->value) {
		$tools_list = iterator_to_array($tools);
		foreach ($tools_list as $key => $tool) {
			if ($tool["status"] == 3 && !in_array($tool["_id"], $user->getDevelopedTools())) {
				unset($tools_list[$key]);
			}
		}

		return $tools_list;
	} else {
		return iterator_to_array($tools);
	}
}

// list tools

function getTools_ListComplete(User $user)
{
	getToolsLogger()->debug("Get list of tools");
	getToolsLogger()->debug("User type: " . $user->getType());

	if ($user->getType() === UserType::Guest->value) {
		$tools = $GLOBALS['toolsCol']->find(array('external' => true, 'status' => 1, 'owner.license' => array('$ne' => "free_for_academics")), array(), array('title' => 1));
	} elseif ($user->getType() === UserType::Admin->value || $user->getType() === UserType::ToolDev->value) {
		$tools = $GLOBALS['toolsCol']->find(array('external' => true, 'status' => array('$ne' => 2)), array(), array('title' => 1));
	} else {
		$tools = $GLOBALS['toolsCol']->find(array('external' => true, 'status' => 1), array(), array('title' => 1));
	}

	if ($user->getType() == UserType::ToolDev->value) {
		$tools_list = iterator_to_array($tools);
		foreach ($tools_list as $key => $tool) {
			if ($tool["status"] == 3 && !in_array($tool["_id"], $user->getDevelopedTools())) {
				unset($tools_list[$key]);
			}
		}

		return $tools_list;
	} else {
		return iterator_to_array($tools);
	}
}


// list tools
function getTool_fromId(string $toolId, $indexByName = false)
{
	$tool = $GLOBALS['toolsCol']->findOne(['_id' => $toolId]);
	if (is_null($tool)) {
		return null;
	}

	if ($indexByName) {
		$toolIndexed = [];
		foreach ($tool as $attribute => $value) {
			if (is_array($value)) {
				$shouldReindex = 0;
				foreach ($value as $v) {
					if (isset($v['name'])) {
						$shouldReindex = 1;
						$toolIndexed[$attribute][$v['name']] = $v;
					}
				}

				if (!$shouldReindex) {
					$toolIndexed[$attribute] = $value;
				}
			} else {
				$toolIndexed[$attribute] = $value;
			}
		}

		$tool = $toolIndexed;
	}

	return $tool;
}


function launchToolInternal($toolId, $projectDir, $args = [], $outs = [], $outputDir = "", $logName = "")
{
	getToolsLogger()->debug("launchToolInternal(" . $toolId . ", " . json_encode($args) . ", " . json_encode($outs) . ", " . $outputDir . ", " . $logName . ")");

	$tool = getTool_fromId($toolId, true);
	if (is_null($tool)) {
		getToolsLogger()->error("Tool '$toolId' not registered");
		throw new NotFoundException("Internal tool not registered");
	}

	if ($tool['external']) {
		getToolsLogger()->error("Tool '$toolId' is not internal");
		throw new UnexpectedValueException("Tool is not internal");
	}

	$description = "Internal job execution of " . $tool['name'];
	$site = getSite($args['site_list']);
	$execution = $tool['_id'] . "_" . rand(10000, 99999);
	$jobMeta = new Tooljob($tool, description: $description, project: $projectDir, site: $site, execution: $execution, outputDir: $outputDir, logFilename: $logName, isInternal: true);

	$args['working_dir'] = $jobMeta->executionDirectories->executionDir; // hardcoded at wget tool JSON
	$jobMeta->setArguments($args, $tool);
	$jobMeta->createWorkingDir($projectDir);

	// Set outfiles metadata -- for register latter
	$jobMeta->setStageout_data($outs);

	// Setting Command line. Adding parameters
	$jobMeta->prepareExecution($tool, []);
	$jobMeta->submit($tool);
	addUserJob($_SESSION['userId'], $jobMeta->toDocument(), $jobMeta->pid);

	return $jobMeta->pid;
}


// list visualizers
function getVisualizers_List($status = 1)
{

	$visualizers = $GLOBALS['visualizersCol']->find(array('external' => true, 'status' => $status), array('name' => 1, 'title' => 1, 'short_description' => 1, 'keywords' => 1), array('title' => 1));

	return iterator_to_array($visualizers);
}


// list visualizers
function getVisualizers_ListComplete($status = 1)
{

	$visualizers = $GLOBALS['visualizersCol']->find(array('external' => true, 'status' => $status), array(), array('title' => 1));

	return iterator_to_array($visualizers);
}


// list a tool input file combination
function getInputFilesCombinations($tool)
{

	$descriptions = [];
	foreach ($tool["input_files_combinations"] as $t) {

		$descriptions[] = $t["description"];
	}

	return implode("~", $descriptions);
}


function getSitesInfo(string $toolId)
{
	$toolDocument = $GLOBALS['toolsCol']->findOne(['_id' => $toolId]);
	if (is_null($toolDocument)) {
		return null;
	}

	$filterFields = ["_id" => 1, "name" => 1, "launcher" => 1];
	$executionSitesData = $toolDocument['sites'];
	$executionSites = [];

	foreach ($executionSitesData as $siteData) {
		if ($siteData['status'] === 1) {
			$siteId = $siteData['site_id'];
			$siteDetails = $GLOBALS['sitesCol']->findOne(array('_id' => $siteId), $filterFields);
			if (is_null($siteDetails)) {
				throw new NotFoundException("Site document not found for site ID: {$siteId}");
			}

			$executionSites[] = $siteDetails;
		}
	}

	return $executionSites;
}
