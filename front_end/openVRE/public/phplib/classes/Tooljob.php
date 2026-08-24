<?php

namespace OpenVRE;

use MongoDB\BSON\UTCDateTime;
use Monolog\Logger;
use UnexpectedValueException;


class Tooljob
{
	public $_id; // directory or file id
	public $title;
	public $execution;         // User defined. Correspond to the execution folder name
	public $project;           // User defined. Correspond to the project
	public $toolId;
	public $cloudName;         // Cloud name where tool should be executed. Available clouds set in GLOBALS['clouds']
	public $description;
	public $outputDir;
	public Launcher $launcher;
	public $job_type;
	public $containerName;
	public $isInternal;

	// Paths to files genereted during ToolJob execution

	public $stageout_data   = [];
	public $input_files     = [];
	public $input_files_pub = [];
	public $input_paths_pub = [];
	public $arguments       = [];
	public $metadata        = [];
	public $pid             = 0;

	private array $site;

	public Logger $logger;
	public JobDirectories $jobDirectories;
	public ExecutionDirectories $executionDirectories;


	public function __construct($tool, $description, $project, $site, $execution, $outputDir, $logFilename, $isInternal)
	{
		$this->logger = LoggerFactory::getLogger("Tool job");

		$this->toolId    = $tool['_id'];
		$this->title     = $tool['name'] . " job";
		$this->execution = $execution;
		$this->description = $description ?? "Execution directory for tool " . $tool['name'];
		if (!isProject($project)) {
			$this->logger->error("Project $project does not exist");
			throw new UnexpectedValueException("Project $project does not exist");
		}

		$this->project = $project;
		$this->site = $site;
		$this->jobDirectories = JobDirectoriesFactory::create($this->site['_id']);
		$this->executionDirectories = ExecutionDirectoriesFactory::create($this->jobDirectories, $project, $execution, $logFilename);

		$this->launcher = Launcher::from($this->site['launcher']['job_manager']);
		$this->outputDir = $isInternal ? $outputDir : $this->executionDirectories->executionDir;
	}


	/**
	 * Create working directory
	 */
	public function createWorkingDir($projectDir)
	{
		if (is_null($this->executionDirectories->executionDir)) {
			$this->logger->error("Cannot create working directory. Not set yet");
			throw new UnexpectedValueException("Cannot create working directory. Not set yet");
		}

		if (is_dir($this->executionDirectories->executionDir)) {
			if (!$this->isInternal) {
				$this->logger->error("Cannot set job. Requested execution folder (" . basename($this->executionDirectories->executionDir) . ") already exists.");
				throw new UnexpectedValueException("Cannot set job. Requested execution folder (" . basename($this->executionDirectories->executionDir) . ") already exists.");
			}
		} else {
			if (!$this->isInternal) {
				try {
					$dirPath = str_replace($GLOBALS['userDataDir'] . "/", "", $this->executionDirectories->executionDir);
					$this->_id = createGSDirBNS($projectDir, $dirPath);
				} catch (UnexpectedValueException $e) {
					$this->logger->error("Cannot create execution folder: '" . $this->executionDirectories->executionDir . "'");
					throw new UnexpectedValueException("Cannot create execution folder: '" . $this->executionDirectories->executionDir . "'" . $e->getMessage());
				}
			}

			if (!mkdir($this->executionDirectories->executionDir, 0777, true)) {
				$this->logger->error("Failed to create directory: '" . $this->executionDirectories->executionDir . "'");
				throw new UnexpectedValueException("Failed to create directory: '" . $this->executionDirectories->executionDir . "'");
			}

			if (isset($this->_id)) {
				$this->setDirMetadata();
			}
		}
	}


	private function setDirMetadata()
	{
		if (!is_dir($this->executionDirectories->executionDir)) {
			$this->logger->error("Cannot write and set new execution directory: '" . $this->executionDirectories->executionDir . "' with id '$this->_id'");
			throw new UnexpectedValueException("Cannot write and set new execution directory: '" . $this->executionDirectories->executionDir . "' with id '$this->_id'");
		}

		$input_ids = [];
		array_walk_recursive($this->input_files, function ($v, $k) use (&$input_ids) {
			$input_ids[] = $v;
		});
		$input_ids = array_unique($input_ids);
		$projDirMeta = [
			'description'     => $this->description,
			'input_files'     => $input_ids,
			'tool'            => $this->toolId,
			'submission_file' => $this->executionDirectories->executionSubmissionFile,
			'log_file'        => $this->executionDirectories->executionLogFile,
			'arguments'       => array_merge($this->arguments, $this->input_paths_pub)
		];

		try {
			addMetadataToFile($this->_id, $projDirMeta);
		} catch (UnexpectedValueException $e) {
			$this->logger->error("Project folder created. But cannot set metadata for '" . $this->executionDirectories->executionDir . "' with id '$this->_id'");
			throw new UnexpectedValueException("Project folder created. But cannot set metadata for '" . $this->executionDirectories->executionDir . "' with id '$this->_id'. " . $e->getMessage());
		}
	}


	/**
	 * Creates tool configuration JSON
	 * @param array $tool Fill in config file: input_files, arguments and output_files
	 */
	private function setConfigurationFile($tool)
	{
		if (is_null($this->executionDirectories->executionDir)) {
			$this->logger->error("Cannot create tool configuration file. No 'working_directory' set");
			throw new UnexpectedValueException("Cannot create tool configuration file. No 'working_directory' set");
		}

		$data = [
			'input_files' => [],
			'arguments' => [
				["name" => "execution", "value" => $this->executionDirectories->executionDir],
				["name" => "project", "value" => $this->executionDirectories->executionDir],
				["name" => "description", "value" => $this->description],
			],
			'output_files' => []
		];

		foreach ($this->input_files as $key => $values) {
			foreach ($values as $value) {
				array_push(
					$data['input_files'],
					[
						"name"           => $key,
						"value"          => $value,
						"required"       => $tool['input_files'][$key]['required'],
						"allow_multiple" => $tool['input_files'][$key]['allow_multiple']
					]
				);
			}
		}

		foreach ($this->input_files_pub as $key => $values) {
			foreach ($values as $v) {
				array_push(
					$data['input_files'],
					[
						"name"           => $key,
						"value"          => $v,
						"required"       => $tool['input_files_public_dir'][$key]['required'],
						"allow_multiple" => $tool['input_files_public_dir'][$key]['allow_multiple']
					]
				);
			}
		}

		foreach ($this->arguments as $key => $value) {
			array_push($data['arguments'], ["name" => $key, "value" => $value]);
		}

		if ($tool['output_files']) {
			foreach ($tool['output_files'] as $key => $value) {
				if (isset($value['file']['path'])) {
					$value['file']['file_path'] = $this->jobDirectories->userDir . "/" . $this->project . "/" . $this->execution . "/" . $value['file']['path'];
					$value['file']['file_type'] = $value['file']['format'];
				}

				$data['output_files'][] = $value;
			}
		}

		$file = fopen($this->executionDirectories->executionConfigFile, "w");
		if ($file === false) {
			$this->logger->error("Failed to create tool configuration file '" . $this->executionDirectories->executionConfigFile . "'.");
			throw new UnexpectedValueException("Failed to create tool configuration file '" . $this->executionDirectories->executionConfigFile . "'.");
		}

		fwrite($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		fclose($file);
	}


	private function checkArguments($arg_name, $arg_value, $tool): void
	{
		if (count($tool)) {
			// checking coherence between JSON and REQUEST
			if (is_null($tool['arguments'][$arg_name])) {
				$this->logger->error("Argument '$arg_name' not found in tool '$this->toolId' definition");
				throw new UnexpectedValueException("Argument '$arg_name' not found in tool '$this->toolId' definition");
			}

			if (empty($arg_value)) {
				if ($tool['arguments'][$arg_name]['required']) {
					$this->logger->error("No value given for argument '$arg_name'");
					throw new UnexpectedValueException("No value given for argument '$arg_name'");
				}

				return;
			}

			switch ($tool['arguments'][$arg_name]['type']) {
				case "enum":
					//Values as string
					if (is_array($arg_value)) {
						$arg_value = reset($arg_value); // take first value
					}
					$arg_value = strval($arg_value);

					//If enum exists then validate

					if (isset($tool['arguments'][$arg_name]['enum_items']) && (isset($tool['arguments'][$arg_name]['enum_items']['name']))) {
						if (!in_array($arg_value, $tool['arguments'][$arg_name]['enum_items']['name'])) {
							$this->logger->error("Invalid argument. In '$arg_name' these values are accepted [" . implode(", ", $tool['arguments'][$arg_name]['enum_items']['name']) . "], but found $arg_value");
							throw new UnexpectedValueException("Invalid argument. In '$arg_name' these values are accepted [" . implode(", ", $tool['arguments'][$arg_name]['enum_items']['name']) . "], but found $arg_value");
						}
					} else {
						//No enum definition
						// Treat it as a string
						if (!is_string($arg_value)) {
							$this->logger->info("Enum '$arg_name' has no enum_items defined. Treated as string..");
						}
					}

					break;

				case "enum_multiple":
					if (is_null($tool['arguments'][$arg_name]['enum_items']) || (is_null($tool['arguments'][$arg_name]['enum_items']['name']))) {
						$this->logger->error("Invalid argument enum in tool definition. '$arg_name' has no 'enum_items' or 'enum_items['name]");
						throw new UnexpectedValueException("Invalid argument enum in tool definition. '$arg_name' has no 'enum_items' or 'enum_items['name]");
					}

					if (!is_array($arg_value)) {
						$arg_value = [$arg_value];
					}

					foreach ($arg_value as $v) {
						if (!in_array($v, $tool['arguments'][$arg_name]['enum_items']['name'])) {
							$this->logger->error("Invalid argument. In '$arg_name' these values are accepted [" . implode(", ", $tool['arguments'][$arg_name]['enum_items']['name']) . "], but found " . implode(", ", $arg_value));
							throw new UnexpectedValueException("Invalid argument. In '$arg_name' these values are accepted [" . implode(", ", $tool['arguments'][$arg_name]['enum_items']['name']) . "], but found " . implode(", ", $arg_value));
						}
					}

					break;

				case "boolean":
					if ($arg_value === true || $arg_value == "on" || $arg_value == "1" || $arg_value == 1) {
						$arg_value = true;
					} elseif ($arg_value === false || $arg_value == "off" || $arg_value == "0" || $arg_value == 0) {
						$arg_value = false;
					} else {
						$this->logger->error("Invalid argument. In '$arg_name' a boolean was expected, but found: $arg_value");
						throw new UnexpectedValueException("Invalid argument. In '$arg_name' a boolean was expected, but found: $arg_value");
					}

					break;

				case "integer":
					if (!is_numeric($arg_value)) {
						$this->logger->error("Invalid argument. In '$arg_name' an integer was expected, but found: $arg_value");
						throw new UnexpectedValueException("Invalid argument. In '$arg_name' an integer was expected, but found: $arg_value");
					}

					$arg_value = intval($arg_value);
					break;

				case "number":
					if (!is_numeric($arg_value)) {
						$this->logger->error("Invalid argument. In '$arg_name' a number was expected, but found: $arg_value");
						throw new UnexpectedValueException("Invalid argument. In '$arg_name' a number was expected, but found: $arg_value");
					}

					break;

				case "hidden":
				case "string":
					if (is_array($arg_value)) {
						$this->logger->error("Invalid argument. In '$arg_name' a string was expected, but found an array: " . implode(",", $arg_value));
						throw new UnexpectedValueException("Invalid argument. In '$arg_name' a string was expected, but found an array: " . implode(",", $arg_value));
					}

					$arg_value = strval($arg_value);
					break;

				default:
					$this->logger->error("Invalid argument type in tool definition. '$arg_name' is of type " . $tool['arguments'][$arg_name]['type']);
					throw new UnexpectedValueException("Invalid argument type in tool definition. '$arg_name' is of type " . $tool['arguments'][$arg_name]['type']);
			}
		}
	}


	/**
	 * Set Arguments
	 * @param array $arguments Arguments as received from inputs.php
	 */
	public function setArguments($arguments, $tool = [])
	{
		foreach ($arguments as $arg_name => $arg_value) {
			$this->checkArguments($arg_name, $arg_value, $tool);
			$this->arguments[$arg_name] = $arg_value;
		}
	}


	/**
	 * Set inputFiles
	 * @param array $input_files  Input_files as received from inputs.php
	 * @param array $tool Tool array containing input_files type and requirements
	 * @param array $metadata Files metadata extracted from DB
	 */
	public function setInput_files($input_files, $tool = [], $metadata = [])
	{
		foreach ($input_files as $input_name => $filenames) {
			if (count($tool) && count($metadata)) {
				if (!is_array($filenames)) {
					$filenames = [$filenames];
				}

				foreach ($filenames as $filename) {
					// checking coherence between JSON and REQUEST
					if (is_null($tool['input_files'][$input_name])) {
						$this->logger->error("Input file '$input_name' not found in tool definition. '$this->toolId' is not properly registered");
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
					}

					if (empty($filename)) {
						if ($tool['input_files'][$input_name]['required'] === true) {
							$this->logger->error("No file given for '$input_name'");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						if (($k = array_search($filename, $filenames)) !== false) {
							unset($filenames[$k]);
						}

						continue;
					}

					if (is_null($metadata[$filename]) && $tool['input_files'][$input_name]['required'] === true) {
						$_SESSION['errorData']['Error'][] = "Given file in '$input_name' has no metadata";
						$this->logger->error("Given file in '$input_name' has no metadata");
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
					}
				}
			}

			if (count($filenames)) {
				$this->input_files[$input_name] = $filenames;
			}
		}
	}

	/**
	 * Set inputFiles from public directory
	 * @param array $input_files_public Input_files_public_dir as received from inputs.php
	 * @param array $tool Tool array containing input_files type and requirements
	 * @param array $metadata_pub Files metadata extracted from DB
	 */


	public function setInput_files_public($input_files_public, $tool = array(), $metadata_pub = array())
	{
		foreach ($input_files_public as $input_name => $input_values) {
			$fns = array();
			if (count($tool) && count($metadata_pub)) {
				if (!is_array($input_values)) {
					$input_values = array($input_values);
				}

				foreach ($input_values as $input_value) {
					if (empty($input_value)) {
						$this->logger->error("No value given public file '$input_name'");
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
					}

					// checking coherence between JSON and REQUEST
					if (is_null($tool['input_files_public_dir'][$input_name])) {
						$this->logger->error("Input file public '$input_name' not found in tool definition. '$this->toolId' is not properly registered");
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
					}

					$fn = array_search($metadata_pub, array('path' => $input_value));
					if ($fn === false) {
						$this->logger->error("Input file public '$input_name' with value '$input_value' not found in public directory");
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
					}

					array_push($fns, $fn);
				}
			}

			$this->input_files_pub[$input_name] = $fns;
			$this->input_paths_pub[$input_name] = $input_values[0];
		}
	}

	/**
	 * Store its metadata in Tooljob for recovering it latter, while stageout register
	 * Needed when tool has not APP (internal), and no out_metadata is generated.
	 * @param array $outs Array of outputfiles
	 */
	public function setStageout_data($out_files)
	{
		$this->logger->debug("Stageout data: ", $out_files);
		if (!isset($out_files['output_files'])) {
			$_SESSION['errorData']['Error'][] = "Internal tool may have problems registering outfiles: Stageout_data mal formatted";
			return 0;
		}

		foreach ($out_files['output_files'] as $out_name => $info) {
			//Add output file metadata
			$this->stageout_data['output_files'][$out_name] = $info;
		}
	}


	/**
	 * Creates metadata JSON
	 */
	public function setMetadata_file($metadata, $metadata_pub = [])
	{
		if (is_null($this->executionDirectories->executionDir)) {
			$this->logger->error("Cannot create metadata file. No working directory set");
			throw new UnexpectedValueException("Cannot create metadata file. No working directory set");
		}
		$this->logger->debug("Starting setMetadata_file()");
		$fileMuGs = [];
		// add input_files metadata
		foreach ($metadata as $file) {
			// convert metadata to DMP format
			$fileMuG = $this->fromVREfile_toMUGfile($file);
			// adapt metadata to App requirements
			if (isset($fileMuG['sources'])) {
				$source_list = [];
				foreach ($fileMuG['sources'] as $sourceid) {
					if ($sourceid) {
						$source_path = getAttr_fromGSFileId($sourceid, "path");
						$this->logger->debug("DEBUG: Source ID: $sourceid -> Path: " . $source_path);
						if ($source_path) {
							$this->logger->debug("DEBUG: Full source path: " . $this->jobDirectories->userDir . "/" . $source_path);
							array_push($source_list, $this->jobDirectories->userDir . "/" . $source_path);
						}
					}
				}

				$fileMuG['sources'] = $source_list;
			}

			if ($fileMuG['data_source'] == Site::EGA->value) {
				$fileMuG['file_path'] = "/clean_files/" . $file['ega_path']; // hardcoded ega path
			}

			if ($fileMuG['file_path']) {
				$fileMuG['file_path'] = $this->jobDirectories->userDir . "/" . $fileMuG['file_path'];
			}

			if ($fileMuG['parentDir']) {
				$parent_path = getAttr_fromGSFileId($fileMuG['parentDir'], "path");
				if (isset($parent_path)) {
					$fileMuG['parentDir'] = $this->jobDirectories->userDir . "/" . $parent_path;
				}
			}

			array_push($fileMuGs, $fileMuG);
		}

		// add input_files public metadata
		if (count($metadata_pub)) {
			foreach ($metadata_pub as $fileMuG) {
				$fileMuG['file_path'] ??= $this->jobDirectories->projectDir . "/" . $fileMuG['file_path'];
				if ($fileMuG['parentDir']) {
					$parent_path = getAttr_fromGSFileId($fileMuG['parentDir'], "path");
					if (isset($parent_path)) {
						$fileMuG['parentDir'] = $this->jobDirectories->userDir . "/" . $parent_path;
					}
				}

				array_push($fileMuGs, $fileMuG);
			}
		}

		$file = fopen($this->executionDirectories->executionMetadataFile, "w");
		if ($file === false) {
			$this->logger->error('Failed to create metadata file for tool execution: ' . $this->executionDirectories->executionMetadataFile);
			throw new UnexpectedValueException('Failed to create metadata file for tool execution: ' . $this->executionDirectories->executionMetadataFile);
		}

		fwrite($file, json_encode($fileMuGs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		fclose($file);
	}

	/**
	 * Creates metadata JSON for results, considering remote paths and input sources.
	 */
	public function setResults_file($metadata)
	{
		if (!$this->executionDirectories->executionDir) {
			$_SESSION['errorData']['Internal Error'][] = "Cannot create results file. No 'executionDir' set";
			return 0;
		}

		$sources = [];
		$remoteBase = null;

		// Collect sources and detect remote base path
		foreach ($metadata as $file) {
			$fileMuG = $this->fromVREfile_toMUGfile($file);

			// Add local file path to sources
			if (!empty($fileMuG['file_path'])) {
				$sources[] = rtrim($this->jobDirectories->userDir, '/') . '/' . ltrim($fileMuG['file_path'], '/');
			}

			// Determine remote base path from the first remote_path
			if (!$remoteBase && !empty($file['meta_data']['remote_paths'][0]['remote_path'])) {
				$remoteFull = preg_replace('#/+#', '/', $file['meta_data']['remote_paths'][0]['remote_path']);
				$localFull  = preg_replace('#/+#', '/', $file['file_path'] ?? '');
				if (strpos($remoteFull, $localFull) !== false) {
					$remoteBase = str_replace($localFull, '', $remoteFull);
					$this->logger->debug("Remote base detected: " . $remoteBase);
				}
			}
		}

		// Load configuration file
		$config = json_decode(file_get_contents($this->executionDirectories->executionConfigFile), true);
		if (!$config || empty($config['output_files'])) {
			$_SESSION['errorData']['Internal Error'][] = "Invalid config file or missing output_files";
			return 0;
		}

		$output_files = [];

		foreach ($config['output_files'] as $out) {
			$fileName = $out['name'] . "." . strtolower($out['file']['file_type'] ?? "txt");

			$localOutputPath = rtrim($this->jobDirectories->userDir, '/') . '/' . $this->execution . "/" . $fileName;

			$entry = [
				"name"       => $out['name'],
				"type"       => $out['type'] ?? "file",
				"file_path"  => $localOutputPath,
				"data_type"  => $out['file']['data_type'] ?? "unknown",
				"file_type"  => $out['file']['file_type'] ?? "TXT",
				"sources"    => $sources,
				"meta_data"  => [
					"visible"     => $out['file']['metadata']['visible'] ?? true,
					"description" => $out['file']['metadata']['description'] ?? "",
					"tool"        => $this->toolId
				]
			];

			// Update parentDir if present
			if (!empty($out['meta_data']['parentDir'])) {
				$parent_path = getAttr_fromGSFileId($out['meta_data']['parentDir'], "path");
				if ($parent_path) {
					$this->logger->debug("ParentDir ID: " . $out['meta_data']['parentDir'] . " -> Path: " . $parent_path);
					$entry['meta_data']['parentDir'] = rtrim($this->jobDirectories->userDir, '/') . '/' . ltrim($parent_path, '/');
				}
			}

			// Override with remote path if remoteBase is detected
			$firstKey = array_key_first($metadata);
			$firstRemote = $metadata[$firstKey]['remote_paths'][0]['remote_path'] ?? null;

			$this->logger->debug("remote_paths: " . print_r($firstRemote, true));

			if ($firstRemote) {
				$remoteOutputPath = rtrim(dirname($firstRemote), '/') . '/' . basename($localOutputPath);

				$entry['file_path'] = null;
				$entry['meta_data']['remote_paths'] = [[
					"remote_path" => preg_replace('#/+#', '/', $remoteOutputPath),
					"location"    => "MareNostrum"
				]];

				$this->logger->debug("Remote output path set to: " . $entry['meta_data']['remote_paths'][0]['remote_path']);
			}

			$output_files[] = $entry;

			$this->logger->debug("Output entry built:");
			$this->logger->debug(json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		}

		$this->logger->debug("Output files:");
		$this->logger->debug(json_encode($output_files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		$results = ["output_files" => $output_files];
		$resultsFile = rtrim($this->executionDirectories->executionDir, '/') . "/.results.json";

		$this->logger->debug("Writing results file to: " . $resultsFile);

		$filePointer = fopen($resultsFile, "w");
		if (!$filePointer) {
			throw new UnexpectedValueException('Failed to create results file for tool execution ' . $resultsFile);
		}

		fwrite($filePointer, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		fclose($filePointer);

		$this->logger->debug("Results file written to: " . $resultsFile);
		$this->logger->debug("FINAL RESULTS JSON:\n" . json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}


	public function setToolLog_file($metadata)
	{
		if (!$this->executionDirectories->executionDir) {
			$this->logger->error("Cannot create tool log file. No 'executionDir' set");
			throw new UnexpectedValueException('Cannot create tool log file. No "executionDir" set');
		}

		// -----------------------------
		// 1. Detect remote base from metadata
		// -----------------------------
		$remoteBase = null;

		foreach ($metadata as $file) {
			if (!empty($file['meta_data']['remote_paths'][0]['remote_path'])) {
				$remoteFull = preg_replace('#/+#', '/', $file['meta_data']['remote_paths'][0]['remote_path']);
				$localFull  = preg_replace('#/+#', '/', $file['file_path'] ?? '');
				if (strpos($remoteFull, $localFull) !== false) {
					$remoteBase = str_replace($localFull, '', $remoteFull);
					$this->logger->debug("Remote base detected for log: " . $remoteBase);
				}
				break;
			}
		}

		// -----------------------------
		// 2. Define local log path
		// -----------------------------
		$localLogPath = rtrim($this->executionDirectories->executionDir, '/') . '/' . $this->executionDirectories->executionLogFile;

		// -----------------------------
		// 3. Map to remote path if applicable (TODO: adapt for remote)
		// -----------------------------

		/*
		 if (!empty($remoteBase)) {
			$relativePath = str_replace(
				rtrim($this->jobDirectories->userDir, '/'),
				'',
				$localLogPath
			);

			$this->log_file = preg_replace('#/+#', '/', rtrim($remoteBase, '/') . '/' . ltrim($relativePath, '/'));
		} else {
			$this->log_file = $localLogPath;
		}
		 */


		// -----------------------------
		// 4. Create local placeholder file
		// -----------------------------
		$filePointer = fopen($localLogPath, "a"); // append mode
		if (!$filePointer) {
			$this->logger->error("Failed to create tool log file " . $localLogPath);
			throw new UnexpectedValueException("Failed to create tool log file " . $localLogPath);
		}

		fwrite($filePointer, "=== TOOL EXECUTION LOG ===\n");
		fwrite($filePointer, "Execution: " . $this->execution . "\n");
		fwrite($filePointer, "Tool: " . $this->toolId . "\n");
		fwrite($filePointer, "Date: " . date("Y-m-d H:i:s") . "\n");
		fwrite($filePointer, "--------------------------\n");

		fclose($filePointer);

		//$this->logger->debug("Tool log file path set to: " . $this->log_file);
	}


	/**
	 * Creates execution Command Line and Submission File
	 */
	public function prepareExecution($tool, $metadata, $dataLocations = [], $metadata_pub = [])
	{
		if ($tool['external'] === false) {
			if ($this->launcher == Launcher::SGE) {
				$cmd = $this->setBashCmd_withoutApp($tool, $metadata);
				$this->createSubmitFile_SGE($cmd);
			} else {
				$this->logger->error("Internal tool not properly registered. Launcher for '" . $this->toolId . "' is set to \"" . $this->launcher->value . "\". Case not implemented.");
				throw new UnexpectedValueException("Internal tool not properly registered. Launcher for '" . $this->toolId . "' is set to \"" . $this->launcher->value . "\". Case not implemented.");
			}
		} else {
			$this->setConfigurationFile($tool);
			$this->setMetadata_file($metadata, $metadata_pub);
			if (!is_file($this->executionDirectories->executionConfigFile) && !is_file($this->executionDirectories->executionMetadataFile)) {
				$this->logger->error("Cannot set tool command line. It required configuration file ($this->executionDirectories->executionConfigFile) and metadata file ($this->executionDirectories->executionMetadataFile)");
				throw new UnexpectedValueException("Cannot set tool command line. It required configuration file ($this->executionDirectories->executionConfigFile) and metadata file ($this->executionDirectories->executionMetadataFile)");
			}

			switch ($this->launcher) {
				case Launcher::SGE:
				case Launcher::kubernetes_native:
					$cmd  = $this->setBashCmd_SGE($tool);
					$this->createSubmitFile_SGE($cmd);

					break;
				case Launcher::docker_SGE:
					$cmd  = $this->setBashCommandDockerSge($tool);
					$this->createSubmitFile_SGE($cmd);

					break;
				case Launcher::docker_SGE_EGA:
					$cmd  = $this->setBashCmd_docker_EGA($tool);
					$this->createSubmitFile_EGA($cmd);

					break;
				case "Slurm_Singularity":
					if (empty($dataLocations)) {
						$this->logger->error("Tool '$this->toolId' not properly registered. Launcher for '$this->toolId' is set to \"$this->launcher\". Case not implemented.");
						throw new UnexpectedValueException("Tool '$this->toolId' not properly registered. Launcher for '$this->toolId' is set to \"$this->launcher\". Case not implemented.");
					}
					$this->setResults_file($metadata);
					$this->setToolLog_file($metadata);
					$cmd = $this->setBashCmd_Singularity($tool, $dataLocations);
					$this->createSubmitFile_Slurm($cmd);

					break;
				default:
					$this->logger->error("Launcher '$this->launcher->value'  not implemented");
					throw new UnexpectedValueException("Launcher '$this->launcher->value'  not implemented.");
			}
		}
	}

	protected function setBashCmd_SGE($tool)
	{
		if (is_null($tool['infrastructure']['executable'])) {
			$this->logger->error("Tool '$this->toolId' not properly registered. Missing 'executable' property");
			throw new UnexpectedValueException("Tool '$this->toolId' not properly registered.");
		}

		return $tool['infrastructure']['executable'] .
			" --config "         . $this->executionDirectories->executionConfigFile .
			" --in_metadata "    . $this->executionDirectories->executionMetadataFile .
			" --out_metadata "   . $this->executionDirectories->executionStageoutFile .
			" --log_file "       . $this->executionDirectories->executionLogFile;
	}


	protected function getFreePort()
	{
		$networkIP = $GLOBALS['NETWORK_IP'];
		$startPort = $GLOBALS['interactive_range_start_port'];
		$endPort = $startPort + $GLOBALS['max_parallel_independent_tools'];

		for ($port = $startPort; $port <= $endPort; $port++) {
			$connection = @fsockopen($networkIP, $port);
			if ($connection) {
				fclose($connection);
				continue;
			}

			return $port;
		}

		return null;
	}


	protected function setBashCommandDockerSgeInteractive($tool, $customToolParameters)
	{
		$this->job_type = "interactive";
		$container_port = $tool['infrastructure']['container_port'];
		$hostPort = $this->getFreePort();
		if ($hostPort === null) {
			$this->logger->error("No free ports available to run the interactive tool.");
			throw new UnexpectedValueException("No free ports available to run the interactive tool.");
		}

		$networkName = $GLOBALS['NETWORK_NAME'];

		$cmd = <<<EOF
			CONTAINER_ID=\$(docker run \
			--rm \
			--privileged \
			-v /var/run/docker.sock:/var/run/docker.sock -d \
			--name $this->containerName \
			--net $networkName \
			$customToolParameters \
			-v {$this->jobDirectories->projectDirHost}:{$GLOBALS['shared']}public_tmp/ \
			-v {$this->jobDirectories->userDirHost}:{$GLOBALS['shared']}userdata_tmp/{$_SESSION['internalUserId']} \
			--hostname $this->containerName \
			-p $hostPort:{$tool['infrastructure']['container_port']} {$tool['infrastructure']['container_image']} {$tool['infrastructure']['executable']});
		EOF;


		$monitorContainer = <<<EOF
			docker logs -f \$CONTAINER_ID &> $this->executionDirectories->executionLogFile &
			CONTAINER_URL="http://$this->containerName:$container_port";
			EXIT_CODE_FILE="/tmp/exit_code_$this->containerName"
			printf '%s | %s\n' "\$(date)" "Waiting for the service URL to become available in the internal network...";
			if timeout 420 wget --retry-connrefused --tries=0 --wait=7 -O /dev/null \$CONTAINER_URL; then
				printf '%s | %s\n' "\$(date)" "Service UP";
			else
				printf '%s | %s\n' "\$(date)" "Service TIMEOUT (7 minutes)";
			fi

			cleanup() {
				printf '%s | %s\n' "\$(date)" "Stop container...";
				docker stop $this->containerName 2>/dev/null
				wait $!  2>/dev/null
				exit_code=$(cat \$EXIT_CODE_FILE)
				rm -f \$EXIT_CODE_FILE
				printf '%s | Container has stopped (exit code = %s) \n' "\$(date)" "\$exit_code";
				exit 0
			}

			trap cleanup SIGTERM SIGUSR1 SIGUSR2

			printf '%s | %s\n' "\$(date)" "Wait while container is running...";
			docker wait $this->containerName > \$EXIT_CODE_FILE &
			wait $!
		EOF;

		return $cmd . "\n" . $monitorContainer;
	}


	protected function setBashCommandDockerCompose($tool, $customToolParameters)
	{
		$this->job_type = "interactive";
		$dockerComposeFile = $GLOBALS['toolsPath'] . $tool['infrastructure']['docker_path'];
		$container_port = $tool['infrastructure']['container_port'];
		$hostPort = $this->getFreePort();
		if ($hostPort === null) {
			$_SESSION['errorData']['Internal Error'][] = "No free ports available to run the interactive tool.";
			$this->logger->error("No free ports available to run the interactive tool.");
			throw new UnexpectedValueException("No free ports available to run the interactive tool.");
		}


		$this->containerName = $tool['infrastructure']['container_image'];
		$monitorContainer = <<<EOF
			CONTAINER_URL="http://$this->containerName:$container_port"
			whoami;
			printf '%s | %s\n' "\$(date)" "Waiting for the service URL to become available in the internal network...";
			if timeout 420 wget --retry-connrefused --tries=10 --wait=7 -O /dev/null \$CONTAINER_URL; then
				printf '%s | %s\n' "\$(date)" "Service UP";
			else
				printf '%s | %s\n' "\$(date)" "Service TIMEOUT (7 minutes)";
			fi

			printf '%s | %s\n' "\$(date)" "Wait while container is running...";
			exit_code="\$(docker wait $this->containerName)";
			printf '%s | Container has stopped (exit code = %s) \n' "\$(date)" "\$exit_code";

			echo '# End time:' \$(date) >> $this->executionDirectories->executionLogFile;
		EOF;
		$cmd = "HOST_PORT=$hostPort docker compose -f $dockerComposeFile up -d";
		$this->containerName = $tool['infrastructure']['container_image'];

		$monitorContainer = <<<EOF
		CONTAINER_URL="http://$this->containerName:$container_port"
		whoami;
		printf '%s | %s\n' "\$(date)" "Waiting for the service URL to become available in the internal network...";
		if timeout 420 wget --retry-connrefused --tries=10 --wait=7 -O /dev/null \$CONTAINER_URL; then
			printf '%s | %s\n' "\$(date)" "Service UP";
		else
			printf '%s | %s\n' "\$(date)" "Service TIMEOUT (7 minutes)";
		fi

		printf '%s | %s\n' "\$(date)" "Wait while container is running...";
		exit_code="\$(docker wait $this->containerName)";
		printf '%s | Container has stopped (exit code = %s) \n' "\$(date)" "\$exit_code";

		echo '# End time:' \$(date) >> $this->executionDirectories->executionLogFile;
		EOF;

		return $cmd . "\n" . $monitorContainer . $customToolParameters;
	}


	protected function isToolRunning()
	{
		$ch = curl_init();
		$defaultInternalPort = 8787;
		curl_setopt_array($ch, [
			CURLOPT_URL            => $this->containerName . ":" . $defaultInternalPort,
			CURLOPT_NOBODY         => true,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
		]);

		curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $httpCode !== 0;
	}


	protected function setBashCommandDockerSge($tool)
	{
		if (is_null($tool['infrastructure']['executable']) && is_null($tool['infrastructure']['container_image'])) {
			$this->logger->error("Tool '$this->toolId' not properly registered. Missing 'executable' or 'container_image' properties");
			throw new UnexpectedValueException("Tool '$this->toolId' not properly registered.");
		}

		$this->containerName = $tool['infrastructure']['container_image'] . "_" . $this->project;
		$customToolParameters = "";
		$envReplacements = ['$this->containerName' => $this->containerName];
		foreach ($tool['infrastructure']['container_env'] as $env_key => $env_value) {
			$env_value = str_replace(array_keys($envReplacements), array_values($envReplacements), $env_value);
			$customToolParameters .= "-e $env_key=$env_value ";
		}

		foreach ($tool['infrastructure']['volumes'] as $hostDir => $containerDir) {
			$userHomeDir = $this->jobDirectories->userDirHost . "/" . $this->project;
			$customToolParameters .= "-v $userHomeDir" . "$hostDir:$containerDir ";

			$user = getUserById($_SESSION['userId']);
			$dataDir = $user->getInternalId() . "/" . $user->getActiveProject();
			$upDirId  = createGSDirBNS($dataDir . $hostDir, 1);
			getProjectLogger()->info("Creating directory:" . $dataDir . $hostDir . "($upDirId)");
			addMetadataToFile($upDirId, array(
				"expiration" => -1,
				"description" => "Uploaded personal data"
			));

			$dataDirP  = $GLOBALS['userDataDir'] . "/$dataDir";
			if (!is_dir("$dataDirP" . $hostDir)) {
				mkdir("$dataDirP" . $hostDir, 0775);
			}
		}

		if (!empty($tool['infrastructure']['user'])) {
			$customToolParameters .= "--user " . escapeshellarg($tool['infrastructure']['user'] . " ");
		}

		if ($tool['infrastructure']['interactive']) {
			if ($tool['infrastructure']['docker_type'] == "compose") {
				$cmd = $this->setBashCommandDockerCompose($tool, $customToolParameters);
			} else {
				if ($this->isToolRunning()) {
					$toolUrl = $GLOBALS['URL'] . "interactive-tool/" . $this->containerName . "/";
					$_SESSION['errorData']['Error'][] = "There is already a running instance of this tool at: <a href='$toolUrl' target='_blank'>" . $toolUrl . "</a>";
				}

				$cmd = $this->setBashCommandDockerSgeInteractive($tool, $customToolParameters);
			}
		} else {
			$cmd_vre = $tool['infrastructure']['executable'] .
				" --config "         . $this->executionDirectories->executionConfigFile .
				" --in_metadata "    . $this->executionDirectories->executionMetadataFile .
				" --out_metadata "   . $this->executionDirectories->executionStageoutFile .
				" --log_file "       . $this->executionDirectories->executionLogFile;


			$cmd =  "docker run --privileged -v /var/run/docker.sock:/var/run/docker.sock -d" .
				" " . $customToolParameters .
				"--memory=" . $tool['infrastructure']['memory'] . "g" .
				" -v " . $this->jobDirectories->projectDirHost . ":" . $GLOBALS['shared'] . "public_tmp/ " .
				" -v " . $this->jobDirectories->userDirHost . ":" . $GLOBALS['shared'] . "userdata_tmp/{$_SESSION['internalUserId']}" .
				" " . $tool['infrastructure']['container_image'] . " $cmd_vre";
		}

		return $cmd;
	}


	protected function setBashCmd_Singularity($tool, $dataLocations)
	{
		if (empty($dataLocations)) {
			$this->logger->error("dataLocations is empty — cannot build paths.");
			throw new UnexpectedValueException("dataLocations is empty — cannot build paths.");
		}

		// Configuration files
		$runFolder = $_REQUEST['execution'];
		$first = $dataLocations[0];
		$pathDir = dirname($first['absolute_path']);
		$baseDir = dirname($pathDir);

		$sBase = rtrim(preg_replace('#/shared_data.*$#', '/', $first['remote_path']), '/');

		// Singularity image and executable
		$singularityExec = $tool['infrastructure']['executable'];
		$singularityImage =  $sBase . "/shared_data/public/" . $tool['infrastructure']['singularity_image']; //doing it automatically
		$this->logger->debug("setBashCmd_Singularity - singularityExec: $singularityExec, singularityImage: $singularityImage");
		//Singularity overlay
		$overlayPath  = $sBase . "/shared_data/public/" . $tool['infrastructure']['singularity_overlay'];

		// Example paths using runFolder
		$configFile     = "$baseDir/$runFolder/.config.json";
		$inputMetadata  = "$baseDir/$runFolder/.input_metadata.json";
		$outputMetadata = "$baseDir/$runFolder/.results.json";
		$logFile        = "$baseDir/$runFolder/.tool.log";

		// Build command
		$cmd  = "singularity exec ";
		$cmd .= "--overlay $overlayPath ";
		$cmd .= "--env HOST_GID=100 --env HOST_UID=1000 ";
		$cmd .= "--bind $sBase/shared_data/public:/shared_data/public_tmp ";
		$cmd .= "--bind $sBase/shared_data/userdata:/shared_data/userdata ";
		$cmd .= "$singularityImage ";
		$cmd .= "$singularityExec ";
		$cmd .= "--config $configFile ";
		$cmd .= "--in_metadata $inputMetadata ";
		$cmd .= "--out_metadata $outputMetadata ";
		$cmd .= "--log_file $logFile ";

		return $cmd;
	}


	protected function setBashCmd_docker_EGA($tool)
	{
		if (is_null($tool['infrastructure']['executable']) && is_null($tool['infrastructure']['container_image'])) {
			$this->logger->error("Tool '$this->toolId' not properly registered. Missing 'executable' or 'container_image' properties");
			throw new UnexpectedValueException("Tool '$this->toolId' not properly registered.");
		}

		$cmd_vre = $tool['infrastructure']['executable'] .
			" --config "       . $this->executionDirectories->executionConfigFile .
			" --in_metadata "  . $this->executionDirectories->executionMetadataFile .
			" --out_metadata " . $this->executionDirectories->executionStageoutFile .
			" --log_file "     . $this->executionDirectories->executionLogFile;

		$customToolParameters = "";
		foreach ($tool['infrastructure']['container_env'][0] as $env_key => $env_value) {
			$customToolParameters .= "-e $env_key=$env_value ";
		}

		$vaultKey = $_SESSION['userVaultInfo']['vaultKey'];
		$user = getUserById($_SESSION['userId']);
		$vaultAddress = $GLOBALS['vaultUrl'] . "/" . $GLOBALS['secretPath'] . $user->getSecretsId() . '/EGA';
		$userFolder = "/shared_data/userdata/" . $_SESSION['internalUserId'];
		$configFilePath = $userFolder . '/env.yml';
		$configContent = "VAULT_TOKEN={$vaultKey}\nVAULT_ADDRESS={$vaultAddress}\n";

		if (file_put_contents($configFilePath, $configContent) === false) {
			$this->logger->error("Failed to write configuration file: $configFilePath");
			throw new UnexpectedValueException("Failed to write configuration file: $configFilePath");
		}

		$cmd = "docker run --device /dev/fuse --security-opt apparmor:unconfined --cap-add SYS_ADMIN -v /var/run/docker.sock:/var/run/docker.sock " .
			" " . $customToolParameters .
			" -v " . $this->jobDirectories->projectDirHost .                            ":" . $GLOBALS['shared'] . "public_tmp/ " .
			" -v " . $this->jobDirectories->userDirHost . "/" . $_SESSION['internalUserId'] . ":" . $GLOBALS['shared'] . "userdata_tmp/" . $_SESSION['internalUserId'] .
			" --tmpfs " . "/clean_files:rw,uid=1000,gid=1000" .
			" --env-file " . $configFilePath .
			" --network=new_vre_open-vre" .
			" -v " . $this->jobDirectories->scriptsDirHost . ":/shared_scripts_tmp" .
			" " . $tool['infrastructure']['container_image'] . " $cmd_vre";

		return $cmd;
	}


	protected function setBashCmd_withoutApp($tool, $metadata)
	{
		if (is_null($tool['infrastructure']['executable'])) {
			$this->logger->error("Tool '$this->toolId' not properly registered. Missing 'executable' property");
			throw new NotFoundException("Tool '$this->toolId' not properly registered. Missing 'executable' property");
		}

		$cmd = $tool['infrastructure']['executable'];
		foreach ($this->input_files as $input_name => $fileIds) {
			foreach ($fileIds as $fnId) {
				$filePath  = $metadata[$fnId]['path'];
				$filename = $GLOBALS['userDataDir'] . "/$filePath";
				$cmd .= " --$input_name $filename";
			}
		}

		// Add to Cmd: --argument_name value
		foreach ($this->arguments as $key => $value) {
			$cmd .= " --$key $value";
		}

		return $cmd;
	}


	protected function createSubmitFile_SGE($cmd)
	{
		$fout = fopen($this->executionDirectories->executionSubmissionFile, "w");
		if ($fout === false) {
			$this->logger->error('Failed to create tool configuration file: ' . $this->executionDirectories->executionSubmissionFile);
			throw new UnexpectedValueException('Failed to create queue submission file: ' . $this->executionDirectories->executionSubmissionFile);
		}

		fwrite($fout, "#!/bin/bash\n");
		fwrite($fout, "# Generated by MuG VRE\n");
		fwrite($fout, "cd " . $this->executionDirectories->executionDir . "\n");

		fwrite($fout, "\n# Running $this->toolId tool ...\n");
		fwrite($fout, "\necho '# Start time:' \$(date) > " . $this->executionDirectories->executionLogFile . "\n");

		fwrite($fout, "\n$cmd >> " . $this->executionDirectories->executionLogFile . " 2>&1\n");
		fwrite($fout, "\necho '# End time:' \$(date) >> " . $this->executionDirectories->executionLogFile . "\n");
		fclose($fout);
	}

	protected function createSubmitFile_Slurm($cmd)
	{
		$bashFilename = $this->executionDirectories->executionSubmissionFile;
		$siteDetails = $this->getLauncher_SlurmInfo($this->site['_id']);
		$fout = fopen($bashFilename, "w");
		if ($fout === false) {
			$this->logger->error("Failed to create SLURM submission file. " . $bashFilename);
			throw new UnexpectedValueException("Failed to create SLURM submission file. " . $bashFilename);
		}

		// Write SLURM headers
		fwrite($fout, "#!/bin/bash\n");
		fwrite($fout, "#SBATCH --job-name=" . $this->toolId . "_job\n");
		fwrite($fout, "#SBATCH --qos " . $siteDetails['queue_name'] . "\n");
		fwrite($fout, "#SBATCH -A " . $siteDetails['domain'] . "\n");
		fwrite($fout, "#SBATCH --cpus-per-task=" . $siteDetails['cpu_count'] . "\n");
		fwrite($fout, "#SBATCH --output=serial_%j.out\n");
		fwrite($fout, "#SBATCH --error=serial_%j.err\n");
		fwrite($fout, "#SBATCH -N " . $siteDetails['n_tasks'] . "\n");
		fwrite($fout, "#SBATCH -n " . $siteDetails['n_nodes'] . "\n");
		fwrite($fout, "#SBATCH --time=00:05:00\n\n\n");
		fwrite($fout, "srun " . "$cmd\n");

		fclose($fout);

		return $bashFilename;
	}


	protected function createSubmitFile_EGA($cmd)
	{
		if (!is_file($this->executionDirectories->executionSubmissionFile)) {
			$this->logger->error("Failed to create queue submission file. " . "File '" . $this->executionDirectories->executionSubmissionFile . "' does not exist");
			throw new UnexpectedValueException("Failed to create queue submission file. " . "File '" . $this->executionDirectories->executionSubmissionFile . "' does not exist");
		}

		$fout = fopen($this->executionDirectories->executionSubmissionFile, "w");
		if ($fout === false) {
			$this->logger->error('Failed to create tool configuration file: ' . $this->executionDirectories->executionSubmissionFile);
			throw new UnexpectedValueException('Failed to create tool configuration file: ' . $this->executionDirectories->executionSubmissionFile);
		}

		fwrite($fout, "#!/bin/bash\n");
		fwrite($fout, "# Generated by  VRE\n");

		fwrite($fout, "\n# Running $this->toolId tool ...\n");

		fwrite($fout, "cd " . $this->executionDirectories->executionDir . "\n");
		fwrite($fout, "\necho '# Start time:' \$(date) > " . $this->executionDirectories->executionLogFile . "\n");


		fwrite($fout, "\n$cmd >> " . $this->executionDirectories->executionLogFile . " 2>&1\n");
		fwrite($fout, "\necho '# End time:' \$(date) >> " . $this->executionDirectories->executionLogFile . "\n");

		fclose($fout);
	}

	/**
	 * Submits
	 * @param string $inputs_request _REQUEST data from inputs.php form
	 */
	public function submit($tool)
	{
		$jobLauncher = $this->getLauncher_Info($this->site['_id'])['launcher']['job_manager'] ?? $tool['infrastructure']['clouds'][$this->site['_id']]['launcher'];
		switch (Launcher::from($jobLauncher)) {
			case Launcher::SGE:
			case Launcher::docker_SGE_EGA:
			case Launcher::docker_SGE:
			case Launcher::kubernetes_native:
			case "Slurm_Singularity":
				return $this->enqueue($tool);
			default:
				$this->logger->error("submit - Tool '$this->toolId' not properly registered. Launcher for '$this->toolId' is set to: \"" . $tool['infrastructure']['clouds'][$this->site['_id']]['launcher'] . "\". Case not implemented.");
				throw new UnexpectedValueException("submit - Tool '$this->toolId' not properly registered. Launcher for '$this->toolId' is set to: \"" . $tool['infrastructure']['clouds'][$this->site['_id']]['launcher'] . "\". Case not implemented.");
		}
	}


	protected function enqueue($tool)
	{
		$launcherInfo = $this->getLauncher_Info($this->site['_id']);
		if (is_null($launcherInfo)) {
			$this->logger->error("Launcher information is incomplete or missing.");
			throw new UnexpectedValueException("Launcher information is incomplete or missing.");
		}

		$jobManager = $launcherInfo['launcher']['job_manager'] ?? $tool['infrastructure']['clouds'][$this->site['_id']]['launcher'];
		$memory = $launcherInfo['memory'] ?? $tool['infrastructure']['memory'];
		$cpus = $launcherInfo['cpus'] ?? $tool['infrastructure']['cpus'];
		$queue = $launcherInfo['queue'] ?? $tool['infrastructure']['clouds'][$this->site['_id']]['queue'];
		$this->logger->info("Resolved Parameters: Queue=$queue, CPUs=$cpus, Memory=$memory");
		$jobOptions = array();
		if ($jobManager == Launcher::kubernetes_native) {
			$jobOptions["image"] = $tool['infrastructure']['container_image'] ?? "";
			if ($jobOptions["image"] === "") {
				throw new UnexpectedValueException("Missing infrastructure.container_image for kubernetes_native launcher.");
			}
			$jobOptions["env"] = array();
			if (isset($tool['infrastructure']['container_env']) && is_array($tool['infrastructure']['container_env'])) {
				foreach ($tool['infrastructure']['container_env'] as $env_key => $env_value) {
					$jobOptions["env"][$env_key] = (string)$env_value;
				}
			}
		}

		$pid = execJob($this->executionDirectories->executionDir, $this->executionDirectories->executionSubmissionFile, $queue, $jobManager, $cpus, $memory, $jobOptions);
		$this->logger->info("Tool job submitted to SGE queue '$queue' (PID=$pid)");
		LoggerFactory::getPersistentLogger()->info("Job {pid} for tool {toolId} submitted to SGE queue {queue}", array("toolId" => $this->toolId, "queue" => $queue, "pid" => $pid));

		$this->pid = $pid;
		return $pid;
	}


	/**
	 * Convert internal VRE file format into DM MuG file
	 * @file  VRE file object, resulting from merging MuGVRE Mongo collections Files + FilesMetadata
	 */
	protected function fromVREfile_toMUGfile($file)
	{
		$mugfile = [];
		$compressions = $GLOBALS['compressions'];
		$mugfile['_id'] = $file['_id'];

		if (isset($file['path'])) {
			$path = $file['path'];
			$userId = $file['owner'] ?? $_SESSION['internalUserId'];

			if (preg_match('/^\//', $path)) {
				$dataPrefix = rtrim($GLOBALS['userDataDir'], '/') . '/';
				$path = (strpos($path, $dataPrefix) === 0)
					? substr($path, strlen($dataPrefix))
					: ltrim($path, '/');
			}

			if ($userId && preg_match('/^' . preg_quote($userId, '/') . '\//', $path)) {
				$mugfile['file_path'] = substr($path, strlen($userId) + 1);
			} else {
				$mugfile['file_path'] = $path;
			}
		} else {
			$mugfile['file_path'] = null;
		}

		$mugfile['file_type'] = $file['format'] ?? "UNK";
		$mugfile['data_type'] = $file['data_type'] ?? null;
		$mugfile['data_source'] = $file['data_source'] ?? null;

		if (isset($file['path'])) {
			$ext = pathinfo($file['path'], PATHINFO_EXTENSION);
			$ext = preg_replace('/_\d+$/', "", $ext);
			$ext = strtolower($ext);
			$mugfile['compressed'] = in_array($ext, array_keys($compressions)) ? $compressions[$ext] : 0;
		}

		$mugfile['sources'] = $file['input_files'] ?? [];
		if (!is_array($file['input_files'])) {
			$mugfile['sources'] = [$file['input_files']];
		}

		$mugfile['user_id'] = $file['owner'] ?? $_SESSION['internalUserId'];
		$mugfile['creation_time'] = $file['mtime'] ?? new UTCDateTime(strtotime("now") * 1000);

		$mugfile['taxon_id'] = $file['taxon_id'] ?? (isset($file['refGenome'])
			? ($this->refGenome_to_taxon[$file['refGenome']] ?? 0)
			: 0);

		unset($file['_id']);
		unset($file['path']);
		unset($file['mtime']);
		unset($file['format']);
		unset($file['data_type']);
		unset($file['tracktype']);
		unset($file['submission_file']);
		unset($file['log_file']);
		unset($file['input_files']);
		unset($file['owner']);

		$mugfile['meta_data'] = $file;
		if (isset($mugfile['meta_data']['refGenome'])) {
			$mugfile['meta_data']['assembly'] = $mugfile['meta_data']['refGenome'];
			unset($mugfile['meta_data']['refGenome']);
		}

		return $mugfile;
	}


	/**
	 * Recreate metadata for input files not included in DMP/Mongo
	 * @param array $input_files Input_files_public_dir as received from inputs.php
	 * @param array $tool Tool array containing input_files type and requirements
	 * @param array $metadata Files metadata extracted from DB
	 */
	public function createMetadata_from_Input_files_public($input_files_public, $tool)
	{
		$metadata_public = array();

		foreach ($input_files_public as $input_name => $input_value) {
			if (count($tool)) {
				// checking coherence between JSON and REQUEST
				if (is_null($tool['input_files_public_dir'][$input_name])) {
					$this->logger->error("Input file public '$input_name' not found in tool definition. '$this->toolId' is not properly registered");
					$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
					redirect($GLOBALS['BASEURL'] . "workspace/");
				}

				if ($input_value != "") {
					switch ($tool['input_files_public_dir'][$input_name]['type']) {
						case 'enum':
							if (is_null($tool['input_files_public_dir'][$input_name]['enum_items']) || (is_null($tool['input_files_public_dir'][$input_name]['enum_items']['name']))) {
								$this->logger->error("Invalid input_files_public_dir enum in tool definition. '$input_name' has no 'enum_items' or 'enum_items['name].");
								$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
								redirect($GLOBALS['BASEURL'] . "workspace/");
							}

							if (!in_array($input_value, $tool['input_files_public_dir'][$input_name]['enum_items']['name'])) {
								$this->logger->error("Invalid input_files_public_dir. In '$input_name' these values are accepted [" . implode(", ", $tool['input_files_public_dir'][$input_name]['enum_items']['name']) . "], but found $input_value");
								$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
								redirect($GLOBALS['BASEURL'] . "workspace/");
							}

							$input_value = strval($input_value);
							break;
						case 'hidden':
						case 'string':
							if (is_array($input_value)) {
								$this->logger->error("Invalid file public. In '$input_name' a string was expected, but found an array: " . implode(",", $input_value));
								$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
								redirect($GLOBALS['BASEURL'] . "workspace/");
							}
							$input_value = strval($input_value);
							break;
						default:
							$this->logger->error("Input file public '$input_name' has unsupported type (" . $tool['input_files_public_dir'][$input_name]['type'] . "). '$this->toolId' is not properly registered");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
					}

					$rfn_public = $this->jobDirectories->projectDir . "/$input_value";
					if (!is_file($rfn_public) && !is_dir($rfn_public) && !preg_match('/\$\(.+\)/', $rfn_public)) {
						$_SESSION['errorData']['Error'][] = "Input file public '$input_name' not found in public directory: $rfn_public";
						$this->logger->error("Input file public '$input_name' not found in public directory: $rfn_public");
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
					}

					// get fn and  metadata from DMP #TODO : right now this data is not registered!!
					// create fake metadata
					$fn  = createLabel() . "_dummy";
					$file = array(
						'_id'       => $fn,
						'path' => $input_value,
						'meta_data' => array(),
						'sources'   => array(0)
					);

					if (isset($tool['input_files_public_dir'][$input_name]['data_type']) && is_array($tool['input_files_public_dir'][$input_name]['data_type'])) {
						$file['data_type'] = $tool['input_files_public_dir'][$input_name]['data_type'][0];
					}
					if (isset($tool['input_files_public_dir'][$input_name]['format']) && is_array($tool['input_files_public_dir'][$input_name]['format'])) {
						$file['format'] = $tool['input_files_public_dir'][$input_name]['format'][0];
					}
					$file['owner'] = "public";
					if (is_file($rfn_public)) {
						$file['type'] = "file";
					}
					if (is_dir($rfn_public)) {
						$file['type'] = "dir";
					}
					$metadata_public[$fn] = $file;
				}
			}
		}

		return $metadata_public;
	}


	/**
	 * Parse submission File
	 */
	public function parseSubmissionFile()
	{
		return 1;
	}


	function getLauncher_Info($siteId)
	{
		$siteDocument = $GLOBALS['sitesCol']->findOne(['_id' => $siteId]);
		if (is_null($siteDocument)) {
			return null;
		}

		return [
			'site_id' => $siteDocument['_id'],
			'name' => $siteDocument['name'],
			'launcher' => $siteDocument['launcher']
		];
	}

	public static function getLauncher_SlurmInfo($siteId)
	{
		$siteDocument = $GLOBALS['sitesCol']->findOne(['_id' => $siteId]);
		if (is_null($siteDocument)) {
			return null;
		}
		$launcher = $siteDocument['launcher'] ?? [];

		$launcherInfo = [
			'site_id' => $siteDocument['_id'],
			'queue_name' => $launcher['queue_name'] ?? 'default',
			'queue_p'    => $launcher['partition']  ?? '',
			'cpu_count'  => $launcher['cpu_count'] ?? 1,
			'n_tasks'    => $launcher['n_tasks']   ?? 1,
			'n_nodes'    => $launcher['n_nodes']   ?? 1,
			'domain'     => $launcher['access_credentials']['domain'] ?? null,
			'server'      => $launcher['access_credentials']['server'] ?? null,
			'root_path'   => $launcher['access_credentials']['rootpath_default'] ?? null,
			'username'    => $launcher['access_credentials']['username'] ?? null,
			'job_manager' => $launcher['job_manager'] ?? 'Slurm_Singularity',
		];
		return $launcherInfo;
	}


	public function toDocument(): array
	{
		$data = get_object_vars($this);
		unset($data['logger']);
		return $data;
	}
}
