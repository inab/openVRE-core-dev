<?php

/**
 * ================================================
 * ⚠️ WORK IN PROGRESS (WIP) — DRAFT VERSION ⚠️
 * -----------------------------------------------
 * This file is currently under development.
 * Code structure, logic, and output may change.
 * Do NOT use this version in production environments.
 * -----------------------------------------------
 */

class Pipeline {
    private $working_dir;
    private $hasExecutionFolder = true;
    private $_id;
    private $description;
    private $input_files = array();
    private $toolId;
    private $submission_file;
    private $log_file;
    private $arguments = array();
    private $input_paths_pub = array();
    private $root_dir;
    private $project;
    private $execution;
    private $config_file;
    
    // Stage-specific properties
    private $stages = array();
    private $stage_dirs = array();
    private $stage_pids = array();
    private $stage_statuses = array();
    
    /**
     * Constructor
     * 
     * @param string $working_dir Base working directory (optional)
     * @param bool $hasExecutionFolder Whether to create execution folder in GS
     * @param string $execution Execution name
     * @param string $project Project name
     * @param string $description Pipeline description
     */
    /**
     * Constructor
     * 
     * @param array $tool Tool information array
     * @param string $execution Execution name
     * @param string $project Project name
     * @param string $description Pipeline description
     * @param array $arguments_exec Arguments for execution
     */
    public function __construct($tool = null, $execution = "", $project = "", $description = "", $arguments_exec = []) {
        // Base directory for user data
        $this->root_dir = $GLOBALS['dataDir']."/".$_SESSION['User']['id'];
        
        // Store tool ID if provided
        if (is_array($tool) && isset($tool['_id'])) {
            $this->toolId = $tool['_id'];
        }
        
        // Set project
        if ($project == "0" || $project == "") {
            $this->project = $_SESSION['User']['activeProject'];
        } else {
            // Check if project exists
            if (function_exists('isProject') && isProject($project)) {
                $this->project = $project;
            } else {
                $_SESSION['errorData']['Warning'][] = "Given project code '$project' not valid. Setting job as part of last active project.";
                $this->project = $_SESSION['User']['activeProject'];
            }
        }
        
        // Set execution name
        $this->execution = $execution;
        
        // Set arguments
        if (!empty($arguments_exec)) {
            $this->arguments = $arguments_exec;
        }
        
        // Set working directory based on execution name
        if ($execution != "") {
            $this->setWorkingDir($execution);
        }
        
        // Set description
        if ($description != "") {
            $this->setDescription($description);
        } else {
            $this->description = "Pipeline execution directory";
        }
    }
        
    /**
     * Set description
     * @param string $description Pipeline description
     */
    public function setDescription($description) {
        $this->description = $description;
        return $this;
    }
    
    /**
     * Set working directory
     * @param string $execution Execution name
     * @param boolean $overwrite Whether to overwrite existing directory
     */
    public function setWorkingDir($execution, $overwrite = false) {
        if (function_exists('getAttr_fromGSFileId')) {
            $dataDirPath = getAttr_fromGSFileId($_SESSION['User']['dataDir'], "path");
            $localWorkingDir = "$dataDirPath/$execution";
            
            if (!$overwrite && isset($GLOBALS['filesCol'])) {
                $prevs = $GLOBALS['filesCol']->findOne(['path' => $localWorkingDir, 'owner' => $_SESSION['User']['id']]);
                if ($prevs) {
                    for ($n = 1; $n < 99; $n++) {
                        $executionN = $execution . "_" . $n;
                        $localWorkingDir = "$dataDirPath/$executionN";
                        $prevs = $GLOBALS['filesCol']->findOne(['path' => $localWorkingDir, 'owner' => $_SESSION['User']['id']]);
                        if (!$prevs) {
                            $execution = $executionN;
                            break;
                        }
                    }
                }
            }
        }
        
        $this->execution = $execution;
        $this->working_dir = "{$this->root_dir}/{$this->project}/{$this->execution}";
        
        if (isset($GLOBALS['tool_config_file'])) {
            $this->config_file = "{$this->working_dir}/{$GLOBALS['tool_config_file']}";
            $this->submission_file = "{$this->working_dir}/{$GLOBALS['tool_submission_file']}";
            $this->log_file = "{$this->working_dir}/{$GLOBALS['tool_log_file']}";
        }
        
        return $this;
    }
    
    /**
     * Creates working directory for the pipeline
     * @return int Directory ID or 0 on failure
     */
    public function createWorkingDir() {
        if (!$this->working_dir) {
            $_SESSION['errorData']['Internal Error'][] = "Cannot create working_dir. Not set yet";
            return 0;
        }
        print "<br/>CREATING WORKING DIRECTORY<br/>";
        print "Working Dir: " . $this->working_dir . "<br/>";
        print "Has Execution Folder: " . ($this->hasExecutionFolder ? 'true' : 'false') . "<br/>";
        
        $dirPath = str_replace($GLOBALS['dataDir']."/", "", $this->working_dir);
        $hasExecutionFolder = $this->hasExecutionFolder;
        
        // Create working dir - disk and db
        if (!is_dir($this->working_dir)) {
            $this->_id = 1;
            if ($hasExecutionFolder && function_exists('createGSDirBNS')) {
                $dirId = createGSDirBNS($dirPath);
                if ($dirId == "0") {
                    $_SESSION['errorData']['Error'][] = "Cannot create execution folder: '$this->working_dir'";
                    return 0;
                }
                
                $this->_id = $dirId;
            }

            if (!mkdir($this->working_dir, 0777, true)) {
                $_SESSION['errorData']['Error'][] = "Failed to create directory: '$this->working_dir'";
                return 0;
            }
            
            chmod($this->working_dir, 0777);

        // If exists, recover working dir id
        } else {
            if ($hasExecutionFolder && function_exists('getGSFileId_fromPath')) {
                $dirId = getGSFileId_fromPath($dirPath);
                $_SESSION['errorData']['Error'][] = "Cannot set job. Requested execution folder (".basename($dirPath).") already exists. Please, set another execution name.<br>";
            
                return 0;
            }

            $this->_id = 1;
        }

        // Set dir metadata
        if ($this->_id != 1 && function_exists('addMetadataBNS')) {
            if (!is_dir($this->working_dir)) {
                $_SESSION['errorData']['Error'][] = "Cannot write and set new execution directory: '$this->working_dir' with id '$this->_id'";
                return 0;
            }
        
            $input_ids = [];
            if (!empty($this->input_files)) {
                array_walk_recursive($this->input_files, function($v, $k) use (&$input_ids){ $input_ids[] = $v; });
                $input_ids = array_unique($input_ids);
            }
            
            $projDirMeta = [
                'description'     => $this->description,
                'input_files'     => $input_ids
            ];
            
            if (!empty($this->toolId)) {
                $projDirMeta['tool'] = $this->toolId;
            }
            
            if (!empty($this->submission_file)) {
                $projDirMeta['submission_file'] = $this->submission_file;
            }
            
            if (!empty($this->log_file)) {
                $projDirMeta['log_file'] = $this->log_file;
            }
            
            if (!empty($this->arguments)) {
                $projDirMeta['arguments'] = $this->arguments;
                if (!empty($this->input_paths_pub)) {
                    $projDirMeta['arguments'] = array_merge($this->arguments, $this->input_paths_pub);
                }
            }

            $addedMetadata = addMetadataBNS($this->_id, $projDirMeta);
            if ($addedMetadata == "0") {
                $_SESSION['errorData']['Error'][] = "Project folder created. But cannot set metadata for '$this->working_dir' with id '$this->_id'";
                return 0;
            }
        }

        return $this->_id;
    }
    
    /**
     * Add input files to the pipeline
     * @param array $input_files Array of input files
     */
    public function addInputFiles($input_files) {
        $this->input_files = array_merge($this->input_files, $input_files);
        return $this;
    }
    
    /**
     * Set arguments for the pipeline
     * @param array $arguments Array of arguments
     */
    public function setArguments($arguments) {
        $this->arguments = $arguments;
        return $this;
    }
    
    /**
     * Set tool ID for the pipeline
     * @param string $toolId Tool ID
     */
    public function setToolId($toolId) {
        $this->toolId = $toolId;
        return $this;
    }
    
    /**
     * Add a stage to the pipeline
     * @param object $stage Stage object
     * @return Pipeline Pipeline instance for method chaining
     */
    public function addStage($stage) {
        $stageId = count($this->stages);
        $this->stages[$stageId] = $stage;
        $this->stage_statuses[$stageId] = 'pending';
        return $this;
    }

    /**
     * Run all stages in the pipeline with dependencies
     * @return bool Success status
     */
    public function run() {
        // Implementation would go here
        return true;
    }
}