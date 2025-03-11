<?php


class DataTransfer {
    private array $filesId;
    private string $mode; // "async" or "sync"
    private string $toolId;
    private string $inputDirVirtual;
    private string $workingDirPath; 

    private string $execution;
    private string $project;
    private string $description;
    private array $logs = [];
    private array $movedFiles = [];
    private array $siteList;

    public function __construct(
        array $filesId,
        string $mode,
        string $toolId,
        string $workingDirPath,
        string $execution = "",
        string $project = "",
        string $description = "",
        array $siteList = []
    ) {
        $this->filesId = $filesId;
        $this->mode = $mode;
        $this->toolId = $toolId;
        $this->workingDirPath = $workingDirPath;
        $this->execution = $execution;
        $this->project = $project;
        $this->description = $description;
        $this->siteList = $siteList;
    }

   
    /**
     * Get data locations, combining the base directory and file paths.
     *
     * @return array
     */
    public function getDataLocation(): array
{
    $dataLocations = [];
    
    // Assuming you want to use $workingDirPath and $inputDirVirtual to compute the absolute path
    $workingDirPath = $this->workingDirPath ?? '';  // Get the working directory (you might need to pass this from the constructor)
   
    echo "Working {$workingDirPath}\n";

    


    // Loop through files and resolve their absolute paths
    foreach ($this->filesId as $fileId => $fileData) {
        // Combine baseDir with file's relative path to form the full path
        echo "File ID: {$fileId}\n";
    
        echo "Original Path: {$fileData['path']}\n";
        echo "Basename Extracted: " . basename($fileData['path']) . "\n";

        $fullPath = $this->generateFinalPath($workingDirPath, $fileData['path'] );
        // Form the full path
        echo "Full Path Before realpath(): {$fullPath}\n";

        $absolutePath = realpath($fullPath);
        if ($absolutePath === false) {
            echo "realpath() failed: File does not exist or invalid path.\n";
        } else {
            echo "Absolute Path: {$absolutePath}\n";
        }

        // Get the site (using the first element of site_list or 'local')
        $site = in_array('local', $this->siteList, true) ? 'local' : $this->siteList['site_list'][0];
        echo "Site: {$site}\n";

        if ($site === 'local') {
            $this->log("Skipping file {$fileId} as it is already local.");
            continue;
        }
        
        // Append file information to the dataLocations array
        $dataLocations[] = [
            'filename' => basename($absolutePath), // Extract just the filename from the absolute path
            'site' => $site,
            'absolute_path' => $absolutePath,
            'file_type' => is_dir($absolutePath) ? 'directory' : 'file'
        ];
    }

    return $dataLocations;
}

 
    public function prepareSyncCommand(array $dataLocations): string
    {
        if (empty($dataLocations)) {
            $this->log("No files to transfer.");
            return "";
        }

        $commands = [];
        foreach ($dataLocations as $file) {
            $commands[] = "rsync --avz --progress --partial --mkpath {$file['absolute_path']} username@{$this->destinationSite}:{$file['filename']}";
        }
        return implode(" && ", $commands);
    }

    public function executeTransfer(string $command): void
    {
        if (empty($command)) {
            $this->log("No transfer command to execute.");
            return;
        }

        $this->log("Executing: $command");
        if ($this->mode === 'async') {
            shell_exec($command . " > /dev/null 2>&1 &");
        } else {
            shell_exec($command);
        }
    }

    public function registerMongoTransferredFile(): void
    {
        // Example of logging transfer to MongoDB (actual implementation needed)
        $this->log("Registering transferred files in MongoDB");
    }

    private function log(string $message): void
    {
        $this->logs[] = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    }

    private function generateFinalPath($workingDir, $originalPath) {
        // Normalize paths: Remove trailing slashes from the working directory
        $workingDir = rtrim($workingDir, DIRECTORY_SEPARATOR);
    
        // Ensure originalPath is relative to the base folder (remove extra directories like 'runXXX')
        $originalPath = ltrim($originalPath, DIRECTORY_SEPARATOR);
    
        // Strip 'runXXX' part from workingDir
        // First, split the workingDir into parts
        $workingDirParts = explode(DIRECTORY_SEPARATOR, $workingDir);
        
        // Remove the last part which is the 'runXXX' folder
        array_pop($workingDirParts);
    
        // Rebuild the base directory without the 'runXXX' part
        $baseDirWithoutRun = implode(DIRECTORY_SEPARATOR, $workingDirParts);
    
        // Combine the cleaned baseDir with the 'uploads' directory and the file name
        $pathParts = explode(DIRECTORY_SEPARATOR, $originalPath);
        
        // Extract the original file name with its extension
        $pathInfo = pathinfo($originalPath);
        
        // Get the original file name with its extension (no change to the extension)
        $finalFileName = $pathInfo['basename'];  // Keeps the original file extension
    
        // Construct the final path without 'runXXX' part, keeping the original extension
        $finalPath = $baseDirWithoutRun . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $finalFileName;
        
        return $finalPath;
    }
        
    }

