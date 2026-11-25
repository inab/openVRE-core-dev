<?php

use OpenVRE\LoggerFactory;
use OpenVRE\User;
use OpenVRE\UserType;


////////////////////////////////////////////
/////// DATATPYE INPUT FILE COMBINATIONS
////////////////////////////////////////////


function getToolsFilteringLogger()
{
	static $logger = null;

	if ($logger === null) {
		$logger = LoggerFactory::getLogger('Tools filtering interface');
	}

	return $logger;
}


// return datatype for a list of files
function getFiles_DataTypes($fn)
{

	$fdt = $GLOBALS['filesMetaCol']->find(array('_id' => array('$in' => $fn)), array("_id" => false, "data_type" => true));

	$a = array();

	foreach ($fdt as $v) $a[] = $v["data_type"];

	return $a;
}


// FILTER TOOLS


// return valid combination of input file datatypes for all tools

function getTools_DataTypes()
{

	$dt = $GLOBALS['toolsCol']->find(array("external" => true), array("input_files_combinations_internal" => true));

	$array = array();

	$c = 0;

	foreach ($dt as $tool) {

		$combinations = $tool["input_files_combinations_internal"] ?? deriveInternalCombinations($tool);

		foreach ($combinations as $combination) {

			$array[$c]["id"] = $tool["_id"];
			$array[$c]["list1"] = array();
			$array[$c]["list2"] = array();
			$array[$c]["list3"] = array();
			$array[$c]["list4"] = array();

			foreach ($combination as $single_c) {

				foreach ($single_c as $k => $v) {

					// one item
					if ($v == 1) $array[$c]["list1"][] = $k;

					// one or more (n mandatory)
					if ($v == "+") {
						$array[$c]["list1"][] = $k;
						$array[$c]["list2"][] = $k;
					}

					// 0 or more (n non mandatory)
					if ($v == "*") $array[$c]["list2"][] = $k;

					// 0 or 1 (single, non mandatory)
					if ($v == "?") $array[$c]["list4"][] = $k;

					// special cases where no combinations internal are possible
					if ($v == "-") $array[$c]["list3"][] = $k;
				}
			}

			$c++;
		}
	}

	return $array;
}

// build "input_files_combinations_internal" from "input_files" and "input_files_combinations"
// for tools that do not define it. Inputs accepting several data types expand into one
// combination per data type choice.
function deriveInternalCombinations($tool)
{
	$inputsByName = array();
	foreach ($tool["input_files"] ?? [] as $input) {
		$inputsByName[$input["name"]] = $input;
	}

	$internal = array();
	foreach ($tool["input_files_combinations"] ?? [] as $combination) {
		$variants = expandCombination($combination["input_files"] ?? [], $inputsByName);
		$internal = array_merge($internal, array_filter($variants));
	}

	return $internal;
}

// expand a list of input names into every data type choice, as lists of {data_type: cardinality}
function expandCombination($inputNames, $inputsByName)
{
	$variants = array(array());

	foreach ($inputNames as $name) {
		if (!isset($inputsByName[$name])) {
			continue;
		}

		$input = $inputsByName[$name];
		$cardinality = getInputCardinality($input);

		$expanded = array();
		foreach ($variants as $variant) {
			foreach ($input["data_type"] ?? [] as $dataType) {
				$expanded[] = array_merge($variant, array(array($dataType => $cardinality)));
			}
		}
		$variants = $expanded;
	}

	return $variants;
}

// 1: exactly one, "+": one or more, "?": zero or one, "*": zero or more
function getInputCardinality($input)
{
	$multiple = !empty($input["allow_multiple"]);

	if (empty($input["required"])) {
		return $multiple ? "*" : "?";
	}

	return $multiple ? "+" : 1;
}

// return valid combination of input file datatypes for a given tool

function getAvailableDTbyTool($tool)
{
	$dt = $GLOBALS['toolsCol']->find(array("external" => true, "_id" => $tool), array("input_files_combinations_internal" => true))->toArray();

	$array = array();

	$c = 0;

	foreach ($dt as $tool) {

		foreach ($tool["input_files_combinations_internal"] as $combination) {

			$array[$c]["id"] = $tool["_id"];

			foreach ($combination as $single_c) {

				foreach ($single_c as $k => $v) {

					$array[$c]["list"][] = $k;
				}
			}
		}
	}

	$array[0]["list"] = array_unique($array[0]["list"]);

	$array = $array[0];

	return $array;
}

// return tools valid for a list of given files

function getTools_ByDT($toolsDT, $filesDT)
{

	$toolsList = array();

	// foreach de totes les possible combinacions de tools
	foreach ($toolsDT as $tdt) {

		// només entrem si hi ha igual o més fitxers seleccionats que a list1 (obligatoris)
		if (sizeof($tdt["list1"]) <= sizeof($filesDT)) {

			$list1 = $tdt["list1"];
			$list2 = $tdt["list2"];
			$list3 = $tdt["list3"];
			$list4 = $tdt["list4"] ?? array();
			$listF = $filesDT;

			// foreach de tots els fitxers seleccionats
			foreach ($listF as $itemWS) {

				// if item is in list1, unset
				if (in_array($itemWS, $list1)) {

					$key = array_search($itemWS, $list1);
					unset($list1[$key]);

					$key = array_search($itemWS, $listF);
					unset($listF[$key]);
				} elseif (in_array($itemWS, $list4)) {
					// optional single file: each "?" entry accepts at most one file

					$key = array_search($itemWS, $list4);
					unset($list4[$key]);

					$key = array_search($itemWS, $listF);
					unset($listF[$key]);
				}
				if (in_array($itemWS, $list2)) {
					// només que n'hi hagi un els esborrem tots???

					$key = array_search($itemWS, $list2);

					unset($list2[$key]);

					/*$key = array_search($itemWS, $listF);
					unset($listF[$key]);*/

					/*if (($key = array_search($itemWS, $listF)) !== false) {
						unset($listF[$key]);
					}*/

					$listF = array_diff($listF, [$itemWS]);
				}
				if (in_array($itemWS, $list3)) {
					$toolsList[] = $tdt["id"];
				}

				/*else {
				
					break;

					}*/

				//if(!in_array($itemWS, $list1) && !in_array($itemWS, $list2)) break;

			}

			// matching if all mandatory types are covered and every file was accepted
			// (list2 holds "+" and "*" types, which may legitimately remain unselected)
			if ((sizeof($list1) == 0) && (sizeof($listF) == 0)) $toolsList[] = $tdt["id"];
		}
	}

	$toolsList = array_unique($toolsList);

	return $toolsList;
}

function getTools_ListByID(User $user, $array)
{
	$array = array_values($array);
	$tl = $GLOBALS['toolsCol']->find(array('_id' => array('$in' => $array), 'status' => array('$in' => [1, 3])), array("name" => true, "status" => true));
	if ($_SESSION['userType'] == UserType::ToolDev->value) {

		$tools_list = iterator_to_array($tl, false);

		foreach ($tools_list as $key => $tool) {

			if ($tool["status"] == 3 && !in_array($tool["_id"], $user->getDevelopedTools())) {
				unset($tools_list[$key]);
			}
		}

		return $tools_list;
	} else {

		return iterator_to_array($tl, false);
	}
}

function getSingleTool_Help($toolID, $op)
{

	$tool = $GLOBALS['toolsCol']->findOne(array("external" => true, "_id" => $toolID), array("input_files_combinations" => true, "input_files" => true));

	$c = 0;

	if (is_null($tool["input_files_combinations"])) {
		$_SESSION['errorData']['Error'][] = "TOOL " . $tool['_id'] . " no internal comb";
		next;
	}

	foreach ($tool["input_files_combinations"] as $combination) {

		if ($c == $op) {

			$array[$c]["id"] = $tool["_id"];
			$array[$c]["operation"] = $combination["description"];
			$array[$c]["content"] = [];

			foreach ($tool["input_files"] as $inputf) {

				if (in_array($inputf["name"], $combination["input_files"])) {
					$a = [];
					$a["description"] = $inputf["description"];

					if ($inputf["allow_multiple"] === true)	$a["description"] .= " <span title='Multiple files of this type accepted' style='color:#6d91b5;font-size:0.9em;'>(multiple)</span>";

					if ($inputf["required"] === true) $a["description"] .= " <span title='Mandatory file' style='color:#6d91b5;font-size:0.9em;'>(mandatory)</span>";
					else $a["description"] .= " <span title='Optional file' style='color:#6d91b5;font-size:0.9em;'>(optional)</span>";

					$a["format"] = $inputf["format"];

					$b = [];
					foreach ($inputf["data_type"] as $dt) {

						$b[] = getDataTypeName($dt);
						//$n  = iterator_to_array($n, true);
						//$b[] = $n[$dt]["name"];

					}

					$a["data_type"] = $b;

					$array[$c]["content"][] = $a;
				}
			}
		}

		$c++;
	}

	return $array;
}

function getTools_Help()
{
	$tools = $GLOBALS['toolsCol']->find(array("external" => true), array("input_files_combinations" => true, "input_files" => true));
	$array = array();
	$counter = 0;

	foreach ($tools as $tool) {
		if (is_null($tool["input_files_combinations"])) {
			getToolsFilteringLogger()->error("Tool " . $tool['_id'] . " no internal comb");
			continue;
		}

		foreach ($tool["input_files_combinations"] as $combination) {
			$array[$counter]["id"] = $tool["_id"];
			$array[$counter]["operation"] = $combination["description"];
			$array[$counter]["content"] = [];

			foreach ($tool["input_files"] as $inputf) {
				if (in_array($inputf["name"], $combination["input_files"])) {
					$a = [];
					$a["description"] = $inputf["description"];

					if ($inputf["allow_multiple"] === true) {
						$a["description"] .= " <span title='Multiple files of this type accepted' style='color:#6d91b5;font-size:0.9em;'>(multiple)</span>";
					}

					if ($inputf["required"] === true) {
						$a["description"] .= " <span title='Mandatory file' style='color:#6d91b5;font-size:0.9em;'>(mandatory)</span>";
					} else {
						$a["description"] .= " <span title='Optional file' style='color:#6d91b5;font-size:0.9em;'>(optional)</span>";
					}

					$a["format"] = $inputf["format"];
					$b = [];
					foreach ($inputf["data_type"] as $dt) {
						$b[] = getDataTypeName($dt);
					}

					$a["data_type"] = $b;
					$array[$counter]["content"][] = $a;
				}
			}

			$counter++;
		}
	}

	return $array;
}
