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

   

    public function syncFiles(): string

    {
        // Step 1: Get Data Locations
    
        $dataLocations = $this->getDataLocation();
        echo "<br>Data Locations: " . print_r($dataLocations, true) . "<br>";
        // Step 2: Check if there are no files to transfer
        if (empty($dataLocations)) {
            $this->log("No files to transfer.");
            return "No files to transfer.";
        }

        foreach ($dataLocations as $fileData) {
            // Get the site details for each file
            $siteDetails = $fileData['site_details'];
        
            // Print out the site details
            echo "Site Name: " . $siteDetails['name'] . "<br>";
            echo "Server: " . $siteDetails['server'] . "<br>";
            echo "Root Path: " . $siteDetails['root_path'] . "<br>";
            echo "Job Manager: " . $siteDetails['job_manager'] . "<br>";
            echo "Container: " . $siteDetails['container'] . "<br><br>";
        }

        $vaultUrl = $GLOBALS['vaultUrl'];
        $vaultToken = $_SESSION['User']['Vault']['vaultToken'];
        $accessToken = $_SESSION['User']['Token']['access_token'];
        $vaultRolename = $_SESSION['User']['Vault']['vaultRolename'];
        $username = $_SESSION['User']['_id'];

        // Debug: Print Vault credentials for debugging
    
        echo "<br>Vault URL: " . $vaultUrl . "<br>";
        echo "<br>Vault Token: " . $vaultToken . "<br>";
        echo "<br>Access Token: " . $accessToken . "<br>";
        echo "<br>Vault Rolename: " . $vaultRolename . "<br>";
        echo "<br>Username: " . $username . "<br>";

        $sshCredentials = $this->getSSHcredentials($vaultUrl, $vaultToken, $accessToken, $vaultRolename, $username);
        if (!$sshCredentials) {
            return "Error: Failed to retrieve SSH credentials from Vault.";
        }
        // Step 5: Create the rsync command using data locations
        $syncCommand = $this->prepareSyncCommand($dataLocations);
        if (empty($syncCommand)) {
            return "Error: Failed to generate rsync command.";
        }
        echo "<br>Sync Command: " . $syncCommand . "<br>";

        // Step 6: Execute the sync command
        $result = $this->executeTransfer($syncCommand, $sshCredentials);
        return $result;        
 
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

        $siteDetails = $this->getSiteDetailsFromMongoDB($site);
            if (!$siteDetails) {
                echo "Warning: Site '{$site}' not found in MongoDB collection!\n";
                continue;
        }   
        
        if ($site === 'local') {
            $this->log("Skipping file {$fileId} as it is already local.");
            continue;
        }
        
        // Append file information to the dataLocations array
        $dataLocations[] = [
            'filename' => basename($absolutePath), // Extract just the filename from the absolute path
            'site' => $site,
            'absolute_path' => $absolutePath,
            'file_type' => is_dir($absolutePath) ? 'directory' : 'file',
            'site_details' => $siteDetails
        ];
    }

    return $dataLocations;
}

    public function getSSHcredentials($vaultUrl,$vaultToken,$accessToken, $vaultRolename,$username  )
    {

        $vaultClient = new VaultClient($vaultUrl, $vaultToken, $accessToken, $vaultRolename, $username);
        
        $vaultKey = $_SESSION['User']['Vault']['vaultKey'];
        
        if (!$vaultKey) {
            $_SESSION['errorData']['Error'][]="Vault Key is empty, are you sure you saved your credentials?";
            exit;
        }
        echo "Vault Key: {$vaultKey}\n";
        
        #Failing

        $credentials = $vaultClient->retrieveDatafromVault('SSH', $vaultKey, $vaultUrl, 'secret/mysecret/data/', $_SESSION['User']['_id'] . '_credentials.txt');
        echo "Credentials:\n";
        var_dump($credentials);
        if (!$credentials) {
            return ['error' => 'Failed to retrieve SSH credentials from Vault, not present.'];
        }

        // Extract SSH credentials
        $sshPrivateKey = $credentials['priv_key'];
        $sshPublicKey = $credentials['pub_key'];
        $sshUsername = $credentials['hpc_username'];

        // Store credentials in class properties instead of database
        $this->sshCredentials = [
            'private_key' => $sshPrivateKey,
            'public_key' => $sshPublicKey,
            'username' => $sshUsername
        ];

    }


    public function getSiteDetailsFromMongoDB(string $site): array|false {
        $result = $GLOBALS['sitesCol']->findOne(['_id' => $site]);

        if (!$result) {
            return false;
        }

        return [
            'name' => $result['name'] ?? null,
            'server' => $result['server'] ?? null,
            'root_path' => $result['openvre_remote_rootpath_default'] ?? null,
            'job_manager' => $result['launcher']['job_manager'] ?? null,
            'container' => $result['launcher']['container'] ?? null
        ];
    }

 
    public function prepareSyncCommand(array $dataLocations): string
    {
        if (empty($dataLocations)) {
            $this->log("No files to transfer.");
            return "";
        }



        $commands = [];
        foreach ($dataLocations as $file) {
            $commands[] = "rsync --avz --progress --partial --mkpath {$file['absolute_path']} username@{$this->$dataLocations['siteDetails']['server']}:{$file['filename']}";
        }
        return implode(" && ", $commands);
    }

    public function executeTransfer(string $command, $sshCredentials): void
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

