<?php


use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;



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
        $syncCommand = $this->prepareSyncCommand($dataLocations, $sshCredentials);

        if (empty($syncCommand)) {
            return "Error: Failed to generate rsync command.";
        }
        echo "<br>Sync Command: " . $syncCommand . "<br>";

        // Step 6: Execute the rsync command using SSH credentials        
        $rsyncResult = $this->executeRsyncCommand($sshCredentials, $syncCommand, $dataLocations);

        echo "<br>" . $rsyncResult;       
        
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
        #echo "Vault Key: {$vaultKey}\n";
        
        #Failing

        $credentials = $vaultClient->retrieveDatafromVault('SSH', $vaultKey, $vaultUrl, 'secret/mysecret/data/', $_SESSION['User']['_id'] . '_credentials.txt');
        
        #echo "Credentials:\n";
        #var_dump($credentials);

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

        return $this->sshCredentials; 

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

     public function prepareSyncCommand(array $dataLocations, array $sshCredentials): string
    {
        echo "<br> Preparing the Sync Command";

        if (empty($dataLocations)) {
            $this->log("No files to transfer.");
            return "";
        }

        if (empty($sshCredentials)) {
            error_log("No credentials for the transfer.");
            return "";
        }

        $username = $sshCredentials['username'];
        $commands = [];
        foreach ($dataLocations as $file) {
            $server = $file['site_details']['server'];
            $destinationPath = "{$file['site_details']['root_path']}" . substr($username, 0, 6) . "/{$username}/uploads/{$file['filename']}";
            $commands[] = "rsync --avz --progress --partial --mkpath {$file['absolute_path']} {$username}@{$server}:{$destinationPath}";
        }
        return implode(" && ", $commands);
    }

   
        /**
     * Execute rsync command using retrieved SSH credentials.
     *
     * @param array $sshCredentials SSH credentials (private key, public key, username)
     * @param string $syncCommand The rsync command to execute
     * @return string The output of the rsync command or an error message
     */


    private function executeRsyncCommand($sshCredentials, $syncCommand, $dataLocations) {
        // Extract SSH credentials
        $sshPrivateKey = trim($sshCredentials['private_key']);
        $username = $sshCredentials['username'];
        $server = $dataLocations[0]['site_details']['server']; // Assuming all files go to the same server
        $rootPath = $dataLocations[0]['site_details']['root_path'];
        $remoteDir = "{$rootPath}{$username}/uploads";

        // Ensure credentials are valid
        if (empty($sshPrivateKey) || empty($username) || empty($server)) {
            return "Error: Missing SSH credentials.";
        }


        try {
            // Initialize SSH connection
            $ssh = new SSH2($server);

            // Load private key for authentication
            var_dump($sshPrivateKey);
            $formattedKey = formatSSHPrivateKey($sshPrivateKey);
            $key = PublicKeyLoader::load($formattedKey);

            // Output the PEM formatted key
            echo "Formatted Private Key:\n";
            echo $formattedKey . "\n";

            // If loading the private key fails
            if (!$key) {
                return "Error: Failed to load RSA private key.";
            }
            if (!$ssh->login($username, $formattedKey)) {
                return "Error: SSH authentication failed.";
            }

            // Step 1: Check if the remote directory exists
            $checkDirCommand = "[ -d \"$remoteDir\" ] && echo 'Exists' || echo 'NotExists'";
            $dirStatus = trim($ssh->exec($checkDirCommand));

            // Step 2: If directory does not exist, create it
            if ($dirStatus === "NotExists") {
                $createDirCommand = "mkdir -p \"$remoteDir\"";
                $ssh->exec($createDirCommand);
            }

            // Step 3: Execute the rsync command
            $output = $ssh->exec($syncCommand);

            return "Rsync Output:<br>" . nl2br(htmlspecialchars($output));
        } catch (Exception $e) {
            return "SSH Error: " . $e->getMessage();
        }
    }

    public function formatSSHPrivateKey($singleLineKey) {
        // Function to insert newlines every 64 characters in the key
        function formatSSHKey($key) {
            // Remove any existing newlines or spaces, just in case
            $key = str_replace(array("\n", "\r", " "), "", $key);
    
            // Break the key into chunks of 64 characters
            return chunk_split($key, 64, "\n");
        }
    
        // Format the key and add BEGIN/END markers
        $formattedKey = "-----BEGIN OPENSSH PRIVATE KEY-----\n";
        $formattedKey .= formatSSHKey($singleLineKey);
        $formattedKey .= "-----END OPENSSH PRIVATE KEY-----";
    
        // Return the formatted key
        return $formattedKey;
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

    private function checkRemoteDirectory (string $server, string $username, string $remoteDir, string $privateKey): bool {

        $sshCommand = "ssh -i /tmp/private_key -o StrictHostKeyChecking=no {$username}@{$server} 'test -d \"{$directory}\" && echo EXISTS'";
        // Save private key temporarily
        file_put_contents('/tmp/private_key', $privateKey);
        chmod('/tmp/private_key', 0600);

        $output = shell_exec($sshCommand);
        
        // Clean up the private key file
        unlink('/tmp/private_key');

        return trim($output) === "EXISTS";
    }

    private function createRemoteDirectory ($server, $username, $remoteDir, $privateKey){
        $sshCommand = "ssh -i /tmp/private_key -o StrictHostKeyChecking=no {$username}@{$server} 'mkdir -p \"{$directory}\"'";

        // Save private key temporarily
        file_put_contents('/tmp/private_key', $privateKey);
        chmod('/tmp/private_key', 0600);

        shell_exec($sshCommand);

        // Clean up the private key file
        unlink('/tmp/private_key');

        error_log("<br> Directory created: $directory");
    }
        
    }

