<?php

use OpenVRE\LoggerFactory;
use OpenVRE\User;
use OpenVRE\UserType;


function getToolsFilteringLogger()
{
	static $logger = null;

	if ($logger === null) {
		$logger = LoggerFactory::getLogger('Tools filtering interface');
	}

	return $logger;
}

function getFiles_DataTypes($fileIds)
{
	$files = $GLOBALS['filesMetaCol']->find(['_id' => ['$in' => $fileIds]]);

	return array_map(fn($file) => $file['data_type'] ?? null, iterator_to_array($files, false));
}


function getTools_DataTypes()
{
	$tools = $GLOBALS['toolsCol']->find(['external' => true]);
	$requirements = [];

	foreach ($tools as $tool) {
		foreach (getToolInternalCombinations($tool) as $combination) {
			$requirements[] = buildCombinationRequirements($tool['_id'], $combination);
		}
	}

	return $requirements;
}

function getToolInternalCombinations($tool)
{
	return $tool['input_files_combinations_internal'] ?? deriveInternalCombinations($tool);
}

function buildCombinationRequirements($toolId, $combination)
{
	$requirements = [
		'id'             => $toolId,
		'required'       => [],
		'repeatable'     => [],
		'optionalSingle' => [],
		'standalone'     => [],
	];

	foreach ($combination as $entry) {
		foreach ($entry as $dataType => $cardinality) {
			foreach (getRequirementGroups($cardinality) as $group) {
				$requirements[$group][] = $dataType;
			}
		}
	}

	return $requirements;
}

function getRequirementGroups($cardinality)
{
	return match ((string) $cardinality) {
		'1'     => ['required'],
		'+'     => ['required', 'repeatable'],
		'*'     => ['repeatable'],
		'?'     => ['optionalSingle'],
		'-'     => ['standalone'],
		default => [],
	};
}

function deriveInternalCombinations($tool)
{
	$inputsByName = array_column($tool['input_files'] ?? [], null, 'name');
	$internal = [];

	foreach ($tool['input_files_combinations'] ?? [] as $combination) {
		$variants = expandCombination($combination['input_files'] ?? [], $inputsByName);
		$internal = array_merge($internal, array_filter($variants));
	}

	return $internal;
}

function expandCombination($inputNames, $inputsByName)
{
	$variants = [[]];

	foreach ($inputNames as $name) {
		if (!isset($inputsByName[$name])) {
			continue;
		}

		$input = $inputsByName[$name];
		$cardinality = getInputCardinality($input);
		$expanded = [];

		foreach ($variants as $variant) {
			foreach ($input['data_type'] ?? [] as $dataType) {
				$expanded[] = [...$variant, [$dataType => $cardinality]];
			}
		}

		$variants = $expanded;
	}

	return $variants;
}

function getInputCardinality($input)
{
	$isMultiple = !empty($input['allow_multiple']);

	if (empty($input['required'])) {
		return $isMultiple ? '*' : '?';
	}

	return $isMultiple ? '+' : 1;
}

function getAvailableDTbyTool($toolId)
{
	$tool = $GLOBALS['toolsCol']->findOne(['external' => true, '_id' => $toolId]);

	if ($tool === null) {
		return [];
	}

	$dataTypes = [];
	foreach (getToolInternalCombinations($tool) as $combination) {
		foreach ($combination as $entry) {
			$dataTypes = array_merge($dataTypes, array_keys($entry));
		}
	}

	return ['id' => $tool['_id'], 'list' => array_values(array_unique($dataTypes))];
}


function getTools_ByDT($toolsRequirements, $fileDataTypes)
{
	$matchingTools = [];

	foreach ($toolsRequirements as $requirements) {
		if (filesMatchRequirements($fileDataTypes, $requirements)) {
			$matchingTools[] = $requirements['id'];
		}
	}

	return array_unique($matchingTools);
}

function filesMatchRequirements($fileDataTypes, $requirements)
{
	if (array_intersect($fileDataTypes, $requirements['standalone'])) {
		return true;
	}

	$missingRequired = $requirements['required'];
	$freeOptionalSlots = $requirements['optionalSingle'];

	foreach ($fileDataTypes as $dataType) {
		$isAccepted = removeFirstOccurrence($missingRequired, $dataType)
			|| removeFirstOccurrence($freeOptionalSlots, $dataType)
			|| in_array($dataType, $requirements['repeatable']);

		if (!$isAccepted) {
			return false;
		}
	}

	return empty($missingRequired);
}

function removeFirstOccurrence(&$list, $value)
{
	$key = array_search($value, $list);

	if ($key === false) {
		return false;
	}

	unset($list[$key]);
	return true;
}

function getTools_ListByID(User $user, $toolIds)
{
	$tools = $GLOBALS['toolsCol']->find(
		['_id' => ['$in' => array_values($toolIds)], 'status' => ['$in' => [1, 3]]],
		['projection' => ['name' => true, 'status' => true]]
	);
	$tools = iterator_to_array($tools, false);

	if ($_SESSION['userType'] != UserType::ToolDev->value) {
		return $tools;
	}

	$developedTools = $user->getDevelopedTools();
	$isVisible = fn($tool) => $tool['status'] != 3 || in_array($tool['_id'], $developedTools);

	return array_values(array_filter($tools, $isVisible));
}


function getSingleTool_Help($toolId, $combinationIndex)
{
	$tool = $GLOBALS['toolsCol']->findOne(['external' => true, '_id' => $toolId]);
	$combination = $tool['input_files_combinations'][$combinationIndex] ?? null;

	if ($combination === null) {
		$_SESSION['errorData']['Error'][] = "TOOL " . $toolId . " no internal comb";
		return [];
	}

	return [$combinationIndex => buildCombinationHelp($tool, $combination)];
}

function getTools_Help()
{
	$tools = $GLOBALS['toolsCol']->find(['external' => true]);
	$help = [];

	foreach ($tools as $tool) {
		if (!isset($tool['input_files_combinations'])) {
			getToolsFilteringLogger()->error("Tool " . $tool['_id'] . " no internal comb");
			continue;
		}

		foreach ($tool['input_files_combinations'] as $combination) {
			$help[] = buildCombinationHelp($tool, $combination);
		}
	}

	return $help;
}

function buildCombinationHelp($tool, $combination)
{
	$content = [];

	foreach ($tool['input_files'] as $input) {
		if (in_array($input['name'], $combination['input_files'])) {
			$content[] = [
				'description' => $input['description'] . describeInputConstraints($input),
				'format'      => $input['format'],
				'data_type'   => array_map('getDataTypeName', $input['data_type']),
			];
		}
	}

	return [
		'id'        => $tool['_id'],
		'operation' => $combination['description'],
		'content'   => $content,
	];
}

function describeInputConstraints($input)
{
	$labels = '';

	if ($input['allow_multiple'] === true) {
		$labels .= helpLabel('Multiple files of this type accepted', 'multiple');
	}

	$labels .= $input['required'] === true
		? helpLabel('Mandatory file', 'mandatory')
		: helpLabel('Optional file', 'optional');

	return $labels;
}

function helpLabel($title, $text)
{
	return " <span title='" . $title . "' style='color:#6d91b5;font-size:0.9em;'>(" . $text . ")</span>";
}
