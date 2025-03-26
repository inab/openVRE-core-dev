<?php


use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;


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
     * Calling functions to check the locations of file, and in case copy them to remote system. 
     * Updating also their path in MongoDB.
     *
     * @return boolean
     */
    public function syncFiles(): string
    {
        // Step 1: Get Data Locations
    
        $dataLocations = $this->getDataLocation();
        echo "<br>Data Locations: " . print_r($dataLocations, true) . "<br>";
        // Step 2: Check if there are no files to transfer
        if ($dataLocations == 0) {
            
            $this->log("No files to transfer.");
            $_SESSION['errorData']['Info'][] ="No files to transfer.";
            exit; 
        } else {
            $_SESSION['errorData']['Info'][] = "Files are gonna be transferred in remote system.";

            foreach ($dataLocations as $fileData) {
                // Get the site details for each file
                $_SESSION['errorData']['Info'][] = " Transferring file $fileData to remote system";
                $siteDetails = $fileData['site_details'];
            }

            $vaultUrl = $GLOBALS['vaultUrl'];
            $vaultToken = $_SESSION['User']['Vault']['vaultToken'];
            $accessToken = $_SESSION['User']['Token']['access_token'];
            $vaultRolename = $_SESSION['User']['Vault']['vaultRolename'];
            $username = $_SESSION['User']['_id'];

            $sshCredentials = $this->getSSHcredentials($vaultUrl, $vaultToken, $accessToken, $vaultRolename, $username);
            
            if ($sshCredentials == 0) {
                $_SESSION['errorData']['Info'][] = "Error: Failed to retrieve SSH credentials from Vault.";
                exit;
            }

            // Step 5: Create the rsync command using data locations
            $syncCommand = $this->prepareSyncCommand($dataLocations, $sshCredentials);

            if (empty($syncCommand)) {
                $_SESSION['errorData']['Info'][] = "Error: Failed to generate rsync command.";
                exit;
                
            }
            
            // Step 6: Execute the rsync command using SSH credentials        
            $rsyncResult = $this->executeRsyncCommand($sshCredentials, $syncCommand, $dataLocations);

            echo "<br>" . $rsyncResult;
        }       
   
    }

    
    /**
     * Get data locations, combining the base directory and file paths.
     *
     * @return array|int Returns an array of paths if conditions are met, otherwise returns 0.
     */
    public function getDataLocation(): array
{
    $dataLocations = [];
    
    // Assuming you want to use $workingDirPath and $inputDirVirtual to compute the absolute path
    $workingDirPath = $this->workingDirPath ?? '';  // Get the working directory (you might need to pass this from the constructor)

    // Loop through files and resolve their absolute paths
    foreach ($this->filesId as $fileId => $fileData) {
        // Combine baseDir with file's relative path to form the full path
        $fullPath = $this->generateFinalPath($workingDirPath, $fileData['path'] );
        
        // Form the full path
        $absolutePath = realpath($fullPath);
        if ($absolutePath === false) {
            $_SESSION['errorData']['Info'][] = "realpath() failed: File does not exist or invalid path.";
            return 0;
        } else {
            $_SESSION['errorData']['Info'][] = "Absolute Path: {$absolutePath}";
        }

        // Get the site (using the first element of site_list or 'local')
        $site = in_array('local', $this->siteList, true) ? 'local' : $this->siteList['site_list'][0];

        $siteDetails = $this->getSiteDetailsFromMongoDB($site);
            if (!$siteDetails) {
                 $_SESSION['errorData']['Info'][] = "Site '{$site}' not found in MongoDB collection!";
                return 0;
            }   
        
        if ($site === 'local') {
            $_SESSION['errorData']['Info'][] = "Skipping file {$fileId} as it is already local.";
            return 0; 
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
            $_SESSION['errorData']['Error']="Vault Key is empty, are you sure you saved your credentials?";
            exit;
        }

        $credentials = $vaultClient->retrieveDatafromVault('SSH', $vaultKey, $vaultUrl, 'secret/mysecret/data/', $_SESSION['User']['_id'] . '_credentials.txt');

        if (!$credentials) {
             $_SESSION['errorData']['Error']="Failed to retrieve SSH credentials from Vault, not present.";
             return 0;
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
        if (empty($dataLocations)) {
            $_SESSION['errorData']['Error']="prepareSync: No files to transfer.";
            return "";
        }

        if (empty($sshCredentials)) {
            $_SESSION['errorData']['Error']="prepareSync: No credentials for the transfer.";
            return "";
        }

        $username = $sshCredentials['username'];
        $sshPrivateKey = trim($sshCredentials['private_key']);
        $formattedKey = $this->formatSSHPrivateKey($sshPrivateKey);

        $tempKeyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
        file_put_contents($tempKeyFile, $formattedKey);
        chmod($tempKeyFile, 0600);

        $commands = [];
        foreach ($dataLocations as $file) {
            $server = $file['site_details']['server'];
            $destinationPath = $this->constructingDestination_MN($file['site_details']['root_path'],$username); 
            $commands[] = "rsync -avz -e 'ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i $tempKeyFile' --progress {$file['absolute_path']} {$username}@{$server}:{$destinationPath}";
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
        $remotePath = $dataLocations[0]['site_details']['root_path'];

        $remoteDir = $this->constructingDestination_MN($remotePath, $username);

        // Ensure credentials are valid
        if (empty($sshPrivateKey) || empty($username) || empty($server)) {
            $_SESSION['errorData']['Error']="Error executeRsync: Missing SSH credentials.";
        }

        try {
            // Initialize SSH connection
            $ssh = new SSH2($server);
            $ssh->setTimeout(60);
            // Load private key for authentication
            $formattedKey = $this->formatSSHPrivateKey($sshPrivateKey);
            $key = PublicKeyLoader::load($formattedKey);

            // If loading the private key fails
            if (!$key) {
                $_SESSION['errorData']['Error'] = "Error: Failed to load RSA private key.";
                return 0;
            }
            if (!$ssh->login($username, $key)) {
                $_SESSION['errorData']['Error'] = "Error: SSH authentication failed.";
                return 0;
            }

            // Step 1: Check if the remote directory exists
            $checkDirCommand = "[ -d \"$remoteDir\" ] && echo 'Exists' || echo 'NotExists'";
            $dirStatus = trim($ssh->exec($checkDirCommand));

            // Extract 'Exists' or 'NotExists' from the entire output
            if (preg_match('/(Exists|NotExists)/', $dirStatus, $matches)) {
                $dirStatus = $matches[1]; // Get the matched word
            } else {
                $dirStatus = "Unknown"; // Handle unexpected output
            }

            // Step 2: If directory does not exist, create it
            if ($dirStatus === "NotExists") {
                error_log("Directory does not exist, creating it...");
                $createDirCommand = "mkdir -p \"$remoteDir\" && echo 'Created' || echo 'Failed'";
                error_log("Executing: $createDirCommand");
                $createStatus = trim($ssh->exec($createDirCommand));
                

                if (preg_match('/(Created|Failed)/', $createStatus, $matches)) {
                    $dirStatusAfter = $matches[1]; // Get the matched word
                } else {
                    $dirStatusAfter = "Unknown"; // Handle unexpected output
                }

                error_log("Directory Creation Status:" . htmlspecialchars($dirStatusAfter));

                if (preg_match('/Created/', $dirStatusAfter)) {
                    $_SESSION['errorData']['Info'][] = "Mirror Directory for $remotePath created in the system: $server";
                } else {
                    $_SESSION['errorData']['Info'][] = "Directory creation failed! Check permissions.";
                    return 0;
                }
            } else {
                $_SESSION['errorData']['Info'][] = "Directory already exists in $server, no need to create it.";
            }
           

            // Step 3: Execute the rsync command
            error_log("Command to be executed: $syncCommand");
            $output = shell_exec($syncCommand);
        
            return "Rsync Output:<br>" . nl2br(htmlspecialchars($output));
        
        } catch (Exception $e) {
            return "SSH Error: " . $e->getMessage();
        }
    }

    private function formatSSHPrivateKey($singleLineKey) {
        // Function to insert newlines every 64 characters in the key
         // First, ensure the key content is well-formatted by removing existing newlines or spaces
        $key = str_replace(array("\n", "\r", " "), "", $singleLineKey);

        // Extract the BEGIN and END markers
        $start = '-----BEGIN OPENSSH PRIVATE KEY-----';
        $end = '-----END OPENSSH PRIVATE KEY-----';

        // Check if key contains the BEGIN and END markers
        if (strpos($singleLineKey, $start) === false || strpos($singleLineKey, $end) === false) {
            throw new Exception("Invalid SSH key format: missing BEGIN or END markers.");
        }

        // Extract the key body (between BEGIN and END)
        $keyBody = str_replace(array($start, $end), "", $singleLineKey);

        // Remove any spaces or newlines (in case they were added within the key body)
        $keyBody = str_replace(array("\n", "\r", " "), "", $keyBody);

        // Break the key body into chunks of 64 characters
        $formattedKeyBody = chunk_split($keyBody, 64, "\n");

        // Format the key with the markers and properly chunked key body
        $formattedKey = $start . "\n" . $formattedKeyBody . $end . "\n";
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

    private function constructingDestination_MN ( string $rootPath, string $username, string $filename = '') {

        //Constructing MN Path
        // Taking the numeric part from Username
        $numericPart = substr($username, 3);
        $numericPartWithoutZero = ltrim($numericPart, '0'); // To adjust to old path of MN4 still maintained in MN5

        $dynamicDir1 = substr($numericPartWithoutZero, 0, 2);
        $dynamicDir2 = substr($numericPartWithoutZero, 0, 5);
        // Now construct the full destination path dynamically

        if (empty($filename)) {
            $destinationPath = "{$rootPath}bsc{$dynamicDir1}/MN4/bsc{$dynamicDir1}/bsc{$dynamicDir2}/uploads";
        } else {
            $destinationPath = "{$rootPath}bsc{$dynamicDir1}/MN4/bsc{$dynamicDir1}/bsc{$dynamicDir2}/uploads/{$filename}";
        }

        return $destinationPath;

    }
        
    }

