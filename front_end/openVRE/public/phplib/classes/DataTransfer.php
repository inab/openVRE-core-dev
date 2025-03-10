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
        string $inputDirVirtual,
        string $workingDirPath,
        string $execution = "",
        string $project = "",
        string $description = "",
        array $siteList = []
    ) {
        $this->filesId = $filesId;
        $this->mode = $mode;
        $this->toolId = $toolId;
        $this->inputDirVirtual = $inputDirVirtual;
        $this->workingDirPath = $workingDirPath;
        $this->execution = $execution;
        $this->project = $project;
        $this->description = $description;
        $this->siteList = $siteList;
    }

    /**
     * Combines the working directory path and the virtual input directory to form the absolute path.
     *
     * @param string $workingDirPath The base working directory.
     * @param string $inputDirVirtual The virtual input directory.
     * @return string The absolute path.
     */
    public function getAbsolutePath(string $workingDirPath, string $inputDirVirtual): string
    {
        // Ensure paths are combined correctly using DIRECTORY_SEPARATOR
        $combinedPath = rtrim($workingDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($inputDirVirtual, DIRECTORY_SEPARATOR);

        // Return the absolute path, resolving any symlinks or relative paths
        return realpath($combinedPath) ?: $combinedPath;
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
        $inputDirVirtual = $this->inputDirVirtual ?? '';  // Get the virtual directory (you might need to pass this from the constructor)
        
        // Combine the working directory and input directory to get the absolute path
        $baseDir = rtrim($workingDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($inputDirVirtual, DIRECTORY_SEPARATOR);

        // Loop through files and resolve their absolute paths
        foreach ($this->files as $fileId => $fileData) {
            // Combine baseDir with file's relative path to form the full path
            $absolutePath = realpath("{$baseDir}/{$fileData['path']}") ?: "{$baseDir}/{$fileData['path']}";
            
            $site = in_array('local', $this->siteList, true) ? 'local' : $this->destinationSite;
            
            if ($site === 'local') {
                $this->log("Skipping file {$fileId} as it is already local.");
                continue;
            }
            
            $dataLocations[] = [
                'filename' => basename($absolutePath),
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


}
