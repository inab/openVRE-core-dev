<?php


class DataTransfer {
    private array $filesId;
    private string $mode; // "async" or "sync"
    private string $destinationSite;
    private string $toolId;
    private string $execution;
    private string $project;
    private string $description;
    private array $logs = [];
    private array $movedFiles = [];

    public function __construct(
        array $filesId,
        string $mode,
        string $destinationSite,
        string $toolId,
        string $execution = "",
        string $project = "",
        string $description = ""
    ) {
        $this->filesId = $filesId;
        $this->mode = $mode;
        $this->destinationSite = $destinationSite;
        $this->toolId = $toolId;
        $this->execution = $execution;
        $this->project = $project;
        $this->description = $description;
    }

    public function getDataLocation(): array
    {
        $dataLocations = [];
        
        foreach ($this->filesId as $fileId) {
            $absolutePath = realpath("/data/{$fileId}") ?: "/data/{$fileId}";
            
            $dataLocations[] = [
                'filename' => basename($absolutePath),
                'site' => 'storage.example.com',
                'absolute_path' => $absolutePath,
                'file_type' => is_dir($absolutePath) ? 'directory' : 'file'
            ];
        }

        return $dataLocations;
    }
 
    public function prepareSyncCommand(array $dataLocations): string
    {
        $commands = [];
        foreach ($dataLocations as $file) {
            $commands[] = "rsync --avz --progress --partial --mkpath {$file['absolute_path']} username@{$this->destinationSite}:{$file['filename']}";
        }
        return implode(" && ", $commands);
    }

    public function executeTransfer(string $command): void
    {
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
