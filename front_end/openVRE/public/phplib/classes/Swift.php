<?php

namespace OpenVRE;

use Monolog\Logger;

class SwiftClient
{
	private $app_id;
	private $app_secret;
	private Logger $logger;


	public function __construct($app_id, $app_secret)
	{
		$this->logger = LoggerFactory::getLogger("Swift interface");
		$this->app_id = $app_id;
		$this->app_secret = $app_secret;
	}


	private function generateCredentialsCommand()
	{
		return "export OS_AUTH_TYPE=v3applicationcredential && " .
			"export OS_AUTH_URL=https://ncloud.bsc.es:5000/v3/ && " .
			"export OS_IDENTITY_API_VERSION=3 && " .
			"export OS_INTERFACE=public && " .
			"export OS_APPLICATION_CREDENTIAL_ID={$this->app_id} && " .
			"export OS_APPLICATION_CREDENTIAL_SECRET={$this->app_secret}";
	}


	private function downlFileCommand($localPath, $containerName, $fileName)
	{
		$path = $localPath . basename($fileName);

		return "openstack object save --file $path $containerName $fileName";
	}


	private function listContCommand($containerName)
	{
		return "openstack object list --all $containerName -f json";
	}


	public function runList()
	{
		$credentialsCommand = $this->generateCredentialsCommand();
		$listCommand = "openstack container list";
		$fullCommand = "$credentialsCommand && $listCommand";

		return shell_exec($fullCommand);
	}


	public function runListContainer($containerName)
	{
		$credentialsCommand = $this->generateCredentialsCommand();
		$listCommand = $this->listContCommand($containerName);
		$fullCommand = "$credentialsCommand && $listCommand";

		return shell_exec($fullCommand);
	}


	public function runDownloadFile($localPath, $containerName, $fileName)
	{
		$credentialsCommand = $this->generateCredentialsCommand();
		$downloadCommand = $this->downlFileCommand($localPath, $containerName, $fileName);
		$fullCommand = "$credentialsCommand && $downloadCommand";
		$output = shell_exec("$fullCommand 2>&1");

		$fullFilePath = $localPath . basename($fileName);
		if (!file_exists($fullFilePath)) {
			$this->logger->error("Failed to download file $fileName. File does not exist at: $fullFilePath. Command output: $output");
			throw new NotFoundException("Failed to download file $fileName. File does not exist at: $fullFilePath. Command output: $output");
		}
	}
}
