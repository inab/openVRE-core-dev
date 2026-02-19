<?php

namespace OpenVRE;

use Monolog\Logger;
use UnexpectedValueException;


class Tooljob
{
	public $_id;
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

	// Paths to files genereted during ToolJob execution

	public $stageout_data 	= [];
	public $input_files     = [];
	public $input_files_pub = [];
	public $input_paths_pub = [];
	public $arguments       = [];
	public $metadata        = [];
	public $pid             = 0;
	public $hasExecutionFolder = true;

	public Logger $logger;
	public JobDirectories $jobDirectories;
	public ExecutionDirectories $executionDirectories;


	/**
	 * Creates new toolExecutor instance
	 * @param string $toolId Tool Id as appears in Mongo
	 */
	public function __construct($tool, $description, $project, $execution = "", $sites = [], $outputDir = "", $logFilename = null)
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

		$this->cloudName = $this->extractCloudName($tool, $sites);
		$this->jobDirectories = JobDirectoriesFactory::create($this->cloudName);
		$this->executionDirectories = ExecutionDirectoriesFactory::create($this->jobDirectories, $project, $execution, $logFilename);

		if (!empty($sites)) {
			// The second element in site_list is the launcher
			$this->launcher = Launcher::from(str_replace($this->cloudName . "_", "", $sites[1]));
		} else {
			// If not enough information is provided, fall back to default method
			$this->launcher = Launcher::from($tool['infrastructure']['clouds'][$this->cloudName]['launcher']);
		}

		// Creating execution folder
		if (empty($execution)) {
			//internalTool
			$this->hasExecutionFolder = false;
			$this->outputDir = $outputDir;
			$this->execution = $tool['_id'] . "_" . rand(10000, 99999);
		} else {
			//create Project Folder
			$this->hasExecutionFolder = true;
			$dataDirPath = getAttr_fromGSFileId($_SESSION['User']['dataDir'], "path");
			$localWorkingDir = "$dataDirPath/$execution";
			$prevs = $GLOBALS['filesCol']->findOne(['path' => $localWorkingDir, 'owner' => $_SESSION['internalUserId']]);
			if ($prevs) {
				for ($n = 1; $n < 99; $n++) {
					$executionN = $execution . "_" . $n;
					$localWorkingDir = "$dataDirPath/$executionN";
					$prevs = $GLOBALS['filesCol']->findOne(['path' => $localWorkingDir, 'owner' => $_SESSION['internalUserId']]);
					if ($prevs) {
						$execution = $executionN;
						break;
					}
				}
			}

			$this->execution = $execution;
			$this->outputDir = $this->executionDirectories->executionDir;
		}
	}


	/**
	 * Create working directory
	 */
	public function createWorking_dir()
	{
		if (is_null($this->executionDirectories->executionDir)) {
			$this->logger->error("Cannot create working directory. Not set yet");
			throw new UnexpectedValueException("Cannot create working directory. Not set yet");
		}

		$dirPath = str_replace($GLOBALS['userDataDir'] . "/", "", $this->executionDirectories->executionDir);
		$this->logger->info("Creating execution folder from replacing '" . $GLOBALS['userDataDir'] . "' in ''" . $this->executionDirectories->executionDir . "' and getting '" . $dirPath . "'");
		if (!is_dir($this->executionDirectories->executionDir)) {
			$this->_id = 1;
			if ($this->hasExecutionFolder) {
				try {
					$this->_id = createGSDirBNS($dirPath);
				} catch (UnexpectedValueException $e) {
					$this->logger->error("Cannot create execution folder: '" . $this->executionDirectories->executionDir . "'");
					throw new UnexpectedValueException("Cannot create execution folder: '" . $this->executionDirectories->executionDir . "'" . $e->getMessage());
				}
			}

			if (!mkdir($this->executionDirectories->executionDir, 0777, true)) {
				$this->logger->error("Failed to create directory: '" . $this->executionDirectories->executionDir . "'");
				throw new UnexpectedValueException("Failed to create directory: '" . $this->executionDirectories->executionDir . "'");
			}

			chmod($this->executionDirectories->executionDir, 0777);
			// if exists, recover working dir id
		} else {
			if ($this->hasExecutionFolder) {
				$this->logger->error("Cannot set job. Requested execution folder (" . basename($dirPath) . ") already exists.");
				throw new UnexpectedValueException("Cannot set job. Requested execution folder (" . basename($dirPath) . ") already exists.");
			}

			$this->_id = 1;
		}

		// set dir metadata
		if ($this->_id != 1) {
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
	}


	/**
	 * Creates tool configuration JSON
	 * @param array $tool Fill in config file: input_files, arguments and output_files
	 */
	public function setConfiguration_file($tool)
	{
		if (is_null($this->executionDirectories->executionDir)) {
			$this->logger->error("Cannot create tool configuration file. No 'working_directory' set");
			throw new UnexpectedValueException("Cannot create tool configuration file. No 'working_directory' set");
		}

		$data = [
			'input_files' => [],
			'arguments' => [
				["name" => "execution", "value" => $this->jobDirectories->userDir . "/" . $this->project . "/" . $this->execution],
				["name" => "project", "value" => $this->jobDirectories->userDir . "/" . $this->project . "/" . $this->execution],
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


	/**
	 * Set Arguments
	 * @param array $arguments Arguments as received from inputs.php
	 */
	public function setArguments($arguments, $tool = [])
	{
		foreach ($arguments as $arg_name => $arg_value) {
			if (count($tool)) {
				// checking coherence between JSON and REQUEST
				if (is_null($tool['arguments'][$arg_name])) {
					$this->logger->error("Argument '$arg_name' not found in tool '$this->toolId' definition");
					$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
					redirect($GLOBALS['BASEURL'] . "workspace/");
				}

				if ($arg_value == "") {
					if ($tool['arguments'][$arg_name]['required']) {
						$this->logger->error("No value given for argument '$arg_name'");
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
					}

					continue;
				}

				switch ($tool['arguments'][$arg_name]['type']) {
					case "enum":
						if (is_null($tool['arguments'][$arg_name]['enum_items']) || (is_null($tool['arguments'][$arg_name]['enum_items']['name']))) {
							$this->logger->error("Invalid argument enum in tool definition. '$arg_name' has no 'enum_items' or 'enum_items['name]");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						if (!in_array($arg_value, $tool['arguments'][$arg_name]['enum_items']['name'])) {
							$this->logger->error("Invalid argument. In '$arg_name' these values are accepted [" . implode(", ", $tool['arguments'][$arg_name]['enum_items']['name']) . "], but found $arg_value");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						break;

					case "enum_multiple":
						if (is_null($tool['arguments'][$arg_name]['enum_items']) || (is_null($tool['arguments'][$arg_name]['enum_items']['name']))) {
							$this->logger->error("Invalid argument enum in tool definition. '$arg_name' has no 'enum_items' or 'enum_items['name]");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						if (!is_array($arg_value)) {
							$arg_value = [$arg_value];
						}

						foreach ($arg_value as $v) {
							if (!in_array($v, $tool['arguments'][$arg_name]['enum_items']['name'])) {
								$this->logger->error("Invalid argument. In '$arg_name' these values are accepted [" . implode(", ", $tool['arguments'][$arg_name]['enum_items']['name']) . "], but found " . implode(", ", $arg_value));
								$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
								redirect($GLOBALS['BASEURL'] . "workspace/");
							}
						}

						break;

					case "boolean":
						if ($arg_value === true || $arg_value == "on" || $arg_value == "1" || $arg_value == 1) {
							$arg_value = true;
						} elseif ($arg_value === false || $arg_value == "off" || $arg_value == "0" || $arg_value == 0) {
							$arg_value = false;
						} else {
							$_SESSION['errorData']['Error'][] = "Invalid argument. In '$arg_name' a boolean was expected, but found: $arg_value";
							$this->logger->error("Invalid argument. In '$arg_name' a boolean was expected, but found: $arg_value");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						break;

					case "integer":
						if (!is_numeric($arg_value)) {
							$this->logger->error("Invalid argument. In '$arg_name' an integer was expected, but found: $arg_value");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						$arg_value = intval($arg_value);
						break;

					case "number":
						if (!is_numeric($arg_value)) {
							$this->logger->error("Invalid argument. In '$arg_name' a number was expected, but found: $arg_value");
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						break;

					case "hidden":
					case "string":
						if (is_array($arg_value)) {
							$this->logger->error("Invalid argument. In '$arg_name' a string was expected, but found an array: " . implode(",", $arg_value));
							$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
							redirect($GLOBALS['BASEURL'] . "workspace/");
						}

						$arg_value = strval($arg_value);
						break;

					default:
						$this->logger->error("Invalid argument type in tool definition. '$arg_name' is of type " . $tool['arguments'][$arg_name]['type']);
						$_SESSION['errorData']['Internal'][] = "There was an internal error launching the tool.";
						redirect($GLOBALS['BASEURL'] . "workspace/");
				}
			}

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

		$fileMuGs = [];
		// add input_files metadata
		foreach ($metadata as $file) {
			// convert metadata to DMP format
			$fileMuG = $this->fromVREfile_toMUGfile($file);

			if ($fileMuG['data_source'] == "EGA") {
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
	 * Creates execution Command Line and Submission File
	 */
	public function prepareExecution($tool, $metadata, $metadata_pub = [])
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
			$this->setConfiguration_file($tool);
			$this->setMetadata_file($metadata, $metadata_pub);
			if (!is_file($this->executionDirectories->executionConfigFile) && !is_file($this->executionDirectories->executionMetadataFile)) {
				$this->logger->error("Cannot set tool command line. It required configuration file ($this->executionDirectories->executionConfigFile) and metadata file ($this->executionDirectories->executionMetadataFile)");
				throw new UnexpectedValueException("Cannot set tool command line. It required configuration file ($this->executionDirectories->executionConfigFile) and metadata file ($this->executionDirectories->executionMetadataFile)");
			}

			switch ($this->launcher) {
				case Launcher::SGE:
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
				case Launcher::slurm:
					$cmd = $this->setHPCRequest($this->cloudName, $tool);
					if (!$cmd) {
						return 0;
					}

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


	protected function setBashCommandDockerSgeInteractive($tool, $cmd_envs)
	{
		$this->job_type = "interactive";
		$container_port = $tool['infrastructure']['container_port'];
		$hostPort = $this->getFreePort();
		if ($hostPort === null) {
			$this->logger->error("No free ports available to run the interactive tool.");
			throw new UnexpectedValueException("No free ports available to run the interactive tool.");
		}

		$checkEnvironment = <<<EOF
			FREE_PORT=$hostPort

			current_user=\$(whoami)
			current_groups=\$(groups)
			checking=\$(getent group | grep docker)
			docker_socket_permissions=\$(ls -l /var/run/docker.sock)

			echo "Free port: \$FREE_PORT"
			echo "Current user: \$current_user"
			echo "Groups: \$current_groups"
			echo "Checking: \$checking"
			echo "Docker socket permissions: \$docker_socket_permissions"
		EOF;

		$configureDockerGroup = <<<EOF
			if echo "\$current_groups" | grep -q "docker"; then
				echo "User \$current_user is already in the 'docker' group."
			else
				echo "User \$current_user is not in the 'docker' group. Attempting to add..."

				sudo usermod -aG docker "\$current_user"

				if [ \$? -eq 0 ]; then
					echo "User \$current_user has been added to the 'docker' group."
					echo "Please log out and log back in for the group changes to take effect."
				else
					echo "Failed to add user \$current_user to the 'docker' group."
				fi
			fi
		EOF;


		$runContainer = <<<EOF
			CONTAINER_ID=\$(docker run \
			--rm \
			--privileged \
			-v /var/run/docker.sock:/var/run/docker.sock -d \
			--net=\$NET_NAME --name $this->containerName \
			$cmd_envs \
			-v {$this->jobDirectories->projectDirHost}:{$GLOBALS['shared']}public_tmp/ \
			-v {$this->jobDirectories->userDirHost}:{$GLOBALS['shared']}userdata_tmp/{$_SESSION['internalUserId']} \
			--hostname $this->containerName \
			-p \$FREE_PORT:{$tool['infrastructure']['container_port']} {$tool['infrastructure']['container_image']});
		EOF;

		$checkContainerStatus = <<<EOF
			if ! docker top \$CONTAINER_ID &>/dev/null; then
				printf '%s | %s\n' "$(date)" "Container crashed unexpectedly...";
				exit 1;
			fi

			if ! docker inspect --format='{{.State.Running}}' \$CONTAINER_ID | grep -q true; then
				printf '%s | %s\n' "$(date)" "Container not running anymore";
				exit 1;
			fi
		EOF;

		$reportContainerInfo = <<<EOF
			CONTAINER_URL="http://$this->containerName:$container_port"
			printf '%s | %s\n' "\$(date)" "ContainerID: \$CONTAINER_ID";
			printf '%s | %s\n' "\$(date)" "ExposedPort: \$FREE_PORT";
			printf '%s | %s\n' "\$(date)" "ContainerURL: \$CONTAINER_URL";
		EOF;

		$monitorContainer = <<<EOF
			docker logs -f \$CONTAINER_ID &> $this->executionDirectories->executionLogFile &

			printf '%s | %s\n' "\$(date)" "Waiting for the service URL to become available in the internal network...";
			if timeout 420 wget --retry-connrefused --tries=10 --waitretry=100 -O /dev/null \$CONTAINER_URL; then
				printf '%s | %s\n' "\$(date)" "Service UP";
			else
				printf '%s | %s\n' "\$(date)" "Service TIMEOUT (7 minutes)";
			fi

			printf '%s | %s\n' "\$(date)" "Wait while container is running...";
			exit_code="\$(docker wait \$CONTAINER_ID)";
			printf '%s | Container has stopped (exit code = %s) \n' "\$(date)" "\$exit_code";

			echo '# End time:' \$(date) >> $this->executionDirectories->executionLogFile;
		EOF;

		return $checkEnvironment . "\n" . $configureDockerGroup . "\n" . $runContainer . "\n" . $checkContainerStatus . "\n" . $reportContainerInfo . "\n" . $monitorContainer;
	}


	protected function setBashCommandDockerCompose($tool)
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

		return $cmd . "\n" . $monitorContainer;
	}


	protected function setBashCommandDockerSge($tool)
	{
		if (is_null($tool['infrastructure']['executable']) && is_null($tool['infrastructure']['container_image'])) {
			$this->logger->error("Tool '$this->toolId' not properly registered. Missing 'executable' or 'container_image' properties");
			throw new UnexpectedValueException("Tool '$this->toolId' not properly registered.");
		}

		$timestamp = date('Y-m-d_H-i-s');
		$this->containerName = $tool['infrastructure']['container_image'] . "_" . $_SESSION['internalUserId'] . "_" . $timestamp;
		$cmd_envs = "";
		foreach ($tool['infrastructure']['container_env'] as $env_key => $env_value) {
			$cmd_envs .= "-e $env_key=$env_value ";
		}

		foreach ($tool['infrastructure']['volumes'] as $hostDir => $containerDir) {
			$userHomeDir = $GLOBALS['shared'] . "userdata_tmp/{$_SESSION['internalUserId']}" . "/" . $this->project;
			$cmd_envs .= "-v $userHomeDir" . "$hostDir:$containerDir ";
		}

		if ($tool['infrastructure']['interactive']) {
			if ($tool['infrastructure']['docker_type'] == "compose") {
				$cmd = $this->setBashCommandDockerCompose($tool, $cmd_envs);
			} else {
				$cmd = $this->setBashCommandDockerSgeInteractive($tool, $cmd_envs);
			}
		} else {
			$cmd_vre = $tool['infrastructure']['executable'] .
				" --config "         . $this->executionDirectories->executionConfigFile .
				" --in_metadata "    . $this->executionDirectories->executionMetadataFile .
				" --out_metadata "   . $this->executionDirectories->executionStageoutFile .
				" --log_file "       . $this->executionDirectories->executionLogFile;


			$cmd =  "docker run --privileged -v /var/run/docker.sock:/var/run/docker.sock -d" .
				" " . $cmd_envs .
				"--memory=" . $tool['infrastructure']['memory'] . "g" .
				" -v " . $this->jobDirectories->projectDirHost . ":" . $GLOBALS['shared'] . "public_tmp/ " .
				" -v " . $this->jobDirectories->userDirHost . ":" . $GLOBALS['shared'] . "userdata_tmp/{$_SESSION['internalUserId']}" .
				" " . $tool['infrastructure']['container_image'] . " $cmd_vre";
		}

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

		$cmd_envs = "";
		foreach ($tool['infrastructure']['container_env'][0] as $env_key => $env_value) {
			$cmd_envs .= "-e $env_key=$env_value ";
		}

		$vaultKey = $_SESSION['userVaultInfo']['vaultKey'];
		$vaultAddress = $GLOBALS['vaultUrl'] . "/" . $GLOBALS['secretPath'] . $_SESSION['User']['secretsId'] . '/EGA';
		$userFolder = "/shared_data/userdata/" . $_SESSION['internalUserId'];
		$configFilePath = $userFolder . '/env.yml';
		$configContent = "VAULT_TOKEN={$vaultKey}\nVAULT_ADDRESS={$vaultAddress}\n";

		if (file_put_contents($configFilePath, $configContent) === false) {
			$this->logger->error("Failed to write configuration file: $configFilePath");
			throw new UnexpectedValueException("Failed to write configuration file: $configFilePath");
		}

		$cmd = "docker run --device /dev/fuse --security-opt apparmor:unconfined --cap-add SYS_ADMIN -v /var/run/docker.sock:/var/run/docker.sock " .
			" " . $cmd_envs .
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
		$jobLauncher = $this->getLauncher_Info($this->cloudName)['launcher']['job_manager'] ?? $tool['infrastructure']['clouds'][$this->cloudName]['launcher'];
		switch (Launcher::from($jobLauncher)) {
			case Launcher::SGE:
			case Launcher::docker_SGE_EGA:
			case Launcher::docker_SGE:
				return $this->enqueue($tool);
			default:
				$this->logger->error("Tool '$this->toolId' not properly registered. Launcher for '$this->toolId' is set to: \"" . $tool['infrastructure']['clouds'][$this->cloudName]['launcher'] . "\". Case not implemented.");
				throw new UnexpectedValueException("Tool '$this->toolId' not properly registered. Launcher for '$this->toolId' is set to: \"" . $tool['infrastructure']['clouds'][$this->cloudName]['launcher'] . "\". Case not implemented.");
		}
	}


	protected function enqueue($tool)
	{
		$launcherInfo = $this->getLauncher_Info($this->cloudName);
		if (is_null($launcherInfo)) {
			$this->logger->error("Launcher information is incomplete or missing.");
			throw new UnexpectedValueException("Launcher information is incomplete or missing.");
		}

		$memory = $launcherInfo['memory'] ?? $tool['infrastructure']['memory'];
		$cpus = $launcherInfo['cpus'] ?? $tool['infrastructure']['cpus'];
		$queue = $launcherInfo['queue'] ?? $tool['infrastructure']['clouds'][$this->cloudName]['queue'];
		$this->logger->info("Resolved Parameters: Queue=$queue, CPUs=$cpus, Memory=$memory");

		$pid = execJob($this->executionDirectories->executionDir, $this->executionDirectories->executionSubmissionFile, $queue, $cpus, $memory);
		$this->logger->info("Tool job submitted to SGE queue '$queue' (PID=$pid)");
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
			if (preg_match('/^\//', $file['path']) || preg_match('/^' . $_SESSION['internalUserId'] . '/', $file['path'])) {
				$path = explode("/", $file['path']);
				$mugfile['file_path'] = implode("/", array_slice($path, -3, 3));
			} else {
				$mugfile['file_path'] = $file['path'];
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
		$mugfile['creation_time'] = $file['mtime'] ?? new MongoDB\BSON\UTCDateTime(strtotime("now") * 1000);

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


	private function extractCloudName($tool, $sites)
	{
		if (!empty($sites)) {
			return $sites[0];
		}

		$available_clouds = array_keys($GLOBALS['clouds']);
		if (empty($available_clouds)) {
			$this->logger->error("Internal Error: No cloud infrastructure available in the current VRE installation.");
			throw new UnexpectedValueException("Internal Error: No cloud infrastructure available in the current VRE installation.");
		}

		if (isset($tool['infrastructure']['clouds'])) {
			foreach ($tool['infrastructure']['clouds'] as $cloudName => $cloudInfo) {
				if ($cloudInfo['default_cloud'] && in_array($cloudName, $available_clouds)) {
					return $cloudName;
				}
			}
		}

		$this->logger->error("Internal Error: No cloud infrastructure available in the current VRE installation.");
		throw new UnexpectedValueException("Internal Error: No cloud infrastructure available in the current VRE installation.");
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


	protected function setHPCRequest($cloudName, $tool)
	{
		// To be implemented
		return null;
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

	public function toDocument(): array
	{
		$data = get_object_vars($this);
		unset($data['logger']);
		return $data;
	}
}
