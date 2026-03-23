<?php

namespace OpenVRE;

use MongoDB\BSON\Persistable;
use Monolog\Logger;
use UnexpectedValueException;


class User implements Persistable
{
    private string $_id;
    private string $email;
    private string $secretsId;
    private string $surname;
    private string $name;
    private string $institution;
    private string $lastLogin;
    private string $registrationDate;
    private int $type;
    private int $status;
    private int $diskQuota;
    private string $dataDir;
    private ?string $authProvider;
    private string $internalId;
    private string $activeProject;
    private bool $termsAccepted = false;
    private array $developedTools = [];
    private array $lastJobs = [];
    private Logger $logger;

    public function __construct(string $email, string $secretsId, string $surname, string $name, string $inst, int $type, int $diskQuota, string $dataDir, ?string $authProvider, string $activeProject, array $developedTools)
    {
        $this->logger = LoggerFactory::getLogger('User');
        if ($type != UserType::Guest->value && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->error("Invalid email address: $email");
            throw new UnexpectedValueException("Invalid email address: $email");
        }

        if ($type != UserType::Guest->value && is_null($_SESSION['userToken'])) {
            $this->logger->error("User not logged in");
            throw new UnexpectedValueException("User not logged in");
        }

        $this->type = $type ?? UserType::Registered->value; // TODO: check if this is ok
        $this->email = sanitizeString($email);
        $this->secretsId = sanitizeString($secretsId);
        $this->_id = sanitizeString($email);
        $this->surname = ucfirst(sanitizeString($surname));
        $this->name = ucfirst(sanitizeString($name));
        $this->institution = sanitizeString($inst);
        $this->dataDir = sanitizeString($dataDir);
        $this->authProvider = sanitizeString($authProvider);
        $this->internalId = $this->type == UserType::Guest->value
            ? uniqid($GLOBALS['AppPrefix'] . "ANON")
            : uniqid($GLOBALS['AppPrefix'] . "USER");
        $this->activeProject = sanitizeString($activeProject) ?: createLabel_proj();
        $this->status = UserStatus::Active->value;
        $this->lastLogin = moment();
        $this->registrationDate = moment();
        $this->diskQuota  = $diskQuota || $this->type == UserType::Guest->value // TODO: check if this is ok
            ? $GLOBALS['DISKLIMIT_ANON']
            : $GLOBALS['DISKLIMIT'];

        $this->surname = ucfirst($this->surname);
        $this->name    = ucfirst($this->name);
        $this->developedTools = $developedTools;
        $this->termsAccepted = "0";

        $_SESSION['userVaultInfo'] = array(
            "vaultKey"     => null
        );
    }


    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getSecretsId(): string
    {
        return $this->secretsId;
    }

    public function setSecretsId(string $secretsId): void
    {
        $this->secretsId = $secretsId;
    }

    public function get_id(): string
    {
        return $this->_id;
    }

    public function set_id(string $id): void
    {
        $this->_id = $id;
    }

    public function getInternalId(): string
    {
        return $this->internalId;
    }

    public function setInternalId(string $internalId): void
    {
        $this->internalId = $internalId;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): void
    {
        $this->surname = $surname;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getInst(): string
    {
        return $this->institution;
    }

    public function setInst(string $inst): void
    {
        $this->institution = $inst;
    }

    public function getDiskQuota(): int
    {
        return $this->diskQuota;
    }

    public function setDiskQuota(string $diskQuota): void
    {
        $this->diskQuota = $diskQuota;
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function setDataDir(string $dataDir): void
    {
        $this->dataDir = $dataDir;
    }

    public function getauthProvider(): string
    {
        return $this->authProvider;
    }

    public function setauthProvider(string $authProvider): void
    {
        $this->authProvider = $authProvider;
    }

    public function getActiveProject(): string
    {
        return $this->activeProject;
    }

    public function setActiveProject(string $activeProject): void
    {
        $this->activeProject = $activeProject;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getLastLogin(): string
    {
        return $this->lastLogin;
    }

    public function setLastLogin(string $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    public function getRegistrationDate(): string
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(string $registrationDate): void
    {
        $this->registrationDate = $registrationDate;
    }

    public function getTermsAccepted()
    {
        return $this->termsAccepted;
    }

    public function setTermsAccepted($termsAccepted)
    {
        $this->termsAccepted = $termsAccepted;
    }

    public function getDevelopedTools(): array
    {
        return $this->developedTools;
    }

    public function setDevelopedTools(array $developedTools): void
    {
        $this->developedTools = $developedTools;
    }

    public function getLastJobs(): array
    {
        return $this->lastJobs;
    }

    public function setLastJobs(array $lastJobs): void
    {
        $this->lastJobs = $lastJobs;
    }

    public function getLogger(): Logger
    {
        return $this->logger ??= LoggerFactory::getLogger('User');
    }


    public function bsonSerialize(): array
    {
        $data = get_object_vars($this);
        unset($data['logger']);
        return $data;
    }

    public function bsonUnserialize(array $data): void
    {
        $this->_id = $data['_id'];
        $this->email = $data['email'];
        $this->secretsId = $data['secretsId'];
        $this->surname = $data['surname'];
        $this->name = $data['name'];
        $this->institution = $data['institution'];
        $this->type = $data['type'];
        $this->diskQuota = $data['diskQuota'];
        $this->dataDir = $data['dataDir'];
        $this->authProvider = $data['authProvider'];
        $this->activeProject = $data['activeProject'];
        $this->status = $data['status'];
        $this->lastLogin = $data['lastLogin'];
        $this->registrationDate = $data['registrationDate'];
        $this->internalId = $data['internalId'];
        $this->developedTools = $data['developedTools'];
        $this->lastJobs = $data['lastJobs'];
        $this->termsAccepted = $data['termsAccepted'];
    }
}
