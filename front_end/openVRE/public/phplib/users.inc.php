<?php

use OpenVRE\LoggerFactory;
use OpenVRE\NotFoundException;
use OpenVRE\User;
use OpenVRE\UserStatus;
use OpenVRE\UserType;


function getUsersLogger()
{
    static $logger = null;

    if ($logger === null) {
        $logger = LoggerFactory::getLogger('Users interface');
    }

    return $logger;
}


function checkLoggedIn()
{
    if (isset($_SESSION['userId'])) {
        $user = getUserById($_SESSION['userId']);
    }

    return isset($user) && ($user->getStatus() == UserStatus::Active->value);
}

function checkTermsOfUse(User $user): bool
{
    return $user->getTermsAccepted();
}

function checkAdmin()
{
    $user = getUserById($_SESSION['userId']);

    return isset($user) && ($user->getStatus() == UserStatus::Active->value) && (in_array($user->getType(), $GLOBALS['ADMIN']));
}

function checkToolDev()
{
    $user = getUserById($_SESSION['userId']);

    return isset($user) && ($user->getStatus() == UserStatus::Active->value) && (in_array($user->getType(), $GLOBALS['TOOLDEV']) || in_array($user->getType(), $GLOBALS['ADMIN']));
}

// create user - after being authentified by the Auth Server
function createUserFromToken($token, $userInfo = array())
{
    $userAttributes = array(
        "email"        => $userInfo['email'],
        "type"         => UserType::Registered->value
    );

    $_SESSION['userToken'] = $token;
    if (isset($userInfo) && $userInfo) {
        if (isset($userInfo['family_name'])) {
            $userAttributes['surname'] = $userInfo['family_name'];
        }

        if (isset($userInfo['given_name'])) {
            $userAttributes['name'] = $userInfo['given_name'];
        }

        if (isset($userInfo['provider'])) {
            $userAttributes['AuthProvider'] = $userInfo['provider'];
        }

        if (isset($userInfo['sub'])) {
            $userAttributes['secretsId'] = $userInfo['sub'];
        }

        $_SESSION['tokenInfo'] = $userInfo;
    }

    $user = new User($userAttributes['email'], $userAttributes['secretsId'], $userAttributes['surname'], $userAttributes['name'], "", $userAttributes['type'], 0, "", $userAttributes['AuthProvider'], "", []);

    $_SESSION['userId'] = $userAttributes['email']; // TODO: rename if email will not replace internalId attribute in User class (currently it is the same as _id but no as internalId)
    $_SESSION['internalUserId'] = $user->getInternalId();
    $_SESSION['userType'] = $user->getType();

    return $user;
}


// create anonymous user - without being authentified by the Auth Server
function createUserAnonymous($sampleData)
{
    getUsersLogger()->info("Creating anonymous user");
    $userAttributes = array(
        "email"        => substr(md5(rand()), 0, 25) . "",
        "type"         => UserType::Guest->value,
        "name"         => "Guest",
        "surname"      => "User",
        "institution"  => "institution",
        "AuthProvider" => "VRE"
    );

    $objUser = new User($userAttributes['email'], "", $userAttributes['surname'], $userAttributes['name'], $userAttributes['institution'], $userAttributes['type'], 0, "", $userAttributes['AuthProvider'], "", []);
    if (!$objUser) {
        return false;
    }

    $objUser->setTermsAccepted(true);
    $_SESSION['userId'] = $userAttributes['email']; // TODO: rename if email will not replace id attribute in User class
    $_SESSION['internalUserId'] = $objUser->getInternalId();
    $_SESSION['userType'] = $objUser->getType();

    $dataDirId = prepUserWorkSpace($objUser->getActiveProject(), $objUser->getInternalId(), $sampleData);
    $objUser->setDataDir($dataDirId);

    // register user in mongo. NOT in ldap nor in the oauth2 provider
    try {
        saveNewUser($objUser);
    } catch (Exception $e) {
        getUsersLogger()->error("Error saving new user into Mongo database");
        getUsersLogger()->error($e->getMessage());
        exit('Login error: cannot create anonymous user');
    }

    return $objUser;
}


function getUserById($id, $options = array()): ?User
{
    return $GLOBALS['usersCol']->findOne(["_id" => $id], $options);
}


function getUsersByFilter($filter, $options = array()): array
{
    return $GLOBALS['usersCol']->find($filter, $options);
}


//delete user data from Mongo and disk
function delUser($id)
{
    $homePath =  $id;
    $homeId = getGSFileId_fromPath($homePath, 1);
    if (is_null($homeId)) {
        getUsersLogger()->error("Cannot delete directory from database.");
        throw new NotFoundException("Cannot delete directory from database. Path $homePath not found.");
    }

    deleteGSDirBNS($homeId, 1);

    $rfn =  $GLOBALS['userDataDir'] . "/" . $homePath;
    if (is_dir($rfn)) {
        exec("rm -r \"$rfn\" 2>&1", $output);
    }

    $GLOBALS['usersCol']->deleteOne(array('id' => $id));
}


function logoutUser()
{
    getUsersLogger()->info("User " . $_SESSION['internalUserId'] . " logging out");
    session_unset();
}

function logoutAnon()
{
    unset($_SESSION['userId']);
    unset($_SESSION['userToken']);
    unset($_SESSION['userInfo']);
}

function saveNewUser(User $user)
{
    return $GLOBALS['usersCol']->insertOne($user);
}

function updateUser($user)
{
    getUsersLogger()->info("Updating user " . $user->get_id());
    $GLOBALS['usersCol']->updateOne(array('_id' => $user->get_id()), array('$set' => $user), array('upsert=>true'));
}


// update attribute user document in Mongo
function modifyUser(string $login, array $attributeValueSet)
{
    $GLOBALS['usersCol']->updateOne(
        array('_id'   => $login),
        array('$set'  => $attributeValueSet),
        array('upsert' => true)
    );
}


function loadUser($userId, $impersonate)
{
    $user = getUserById($userId);
    if (empty($user->get_id()) || $user->getStatus() == UserStatus::Inactive->value) {
        getUsersLogger()->error("Requested user (_id = $userId) not found. Cannot load user.");
        throw new NotFoundException("Requested user (_id = $userId) not found. Cannot load user.");
    }

    $impersonating =  isset($_SESSION['userId']) && $_SESSION['userType'] == UserType::Admin->value && $impersonate;
    if ($impersonating) {
        getUsersLogger()->info("User $userId successfully impersonated");
    }

    return $user;
}


function loadUserWithToken(User $user, $userInfo, $token)
{
    if ($user->getStatus() == UserStatus::Inactive->value) {
        getUsersLogger()->error("Requested user is inactive. Cannot load user.");
        throw new UnexpectedValueException("Requested user is inactive. Cannot load user.");
    }

    $user->setLastLogin(moment());
    $user->setSecretsId($userInfo['sub']);
    $_SESSION['userToken'] = $token;
    $_SESSION['tokenInfo'] = $userInfo;
    $_SESSION['userId'] = $user->get_id();
    $_SESSION['userType'] = $user->getType();
    $_SESSION['internalUserId'] = $user->getInternalId();

    updateUser($user);

    $_SESSION['userVaultInfo'] = array(
        "vaultKey"     => null,
    );

    return $user;
}


function saveUserJobs($login, $jobInfo)
{
    getUsersLogger()->debug("Updating user $login with job data: " . json_encode($jobInfo));
    $GLOBALS['usersCol']->updateOne(
        array('_id' => $login),
        array('$set'   => array('lastJobs' => $jobInfo)),
        array('upsert' => true)
    );
}

function delUserJob($login, $pid)
{
    getUsersLogger()->debug("Deleting job $pid from user $login");
    $GLOBALS['usersCol']->updateOne(
        array('_id' => $login),
        array('$unset' => array("lastJobs.$pid" => 1))
    );
}


function addUserJob($login, $data, $pid)
{
    $pid = strval($pid);
    $lastJobs = getUserJobs($login);
    $lastJobs[$pid] = $data;
    getUsersLogger()->debug("Adding job $pid to user $login");
    getUsersLogger()->debug("Job data: " . json_encode($data));
    $GLOBALS['usersCol']->updateOne(
        array('_id' => $login),
        array('$set'   => array('lastJobs' => $lastJobs)),
        array('upsert' => true)
    );
}


function getUserJobs($login) : array
{
    /** @var OpenVRE\User */
    $userWithJobs = $GLOBALS['usersCol']->findOne(array(
        '_id'  => $login,
        'lastJobs' => array('$exists' => true)
    ), array('lastJobs' => 1));

    getUsersLogger()->debug("Jobs for user $login: " . json_encode($userWithJobs));

    return $userWithJobs->getLastJobs();
}

function getAllUserJobs() : User
{
    /** @var OpenVRE\User */
    $usersWithJobs = $GLOBALS['usersCol']->find(
        array(
            '$nor' => array(
                array('lastJobs' => array('$exists' => false)),
                array('lastJobs' => array('$size' => 0)),
            )
        ),
        array("_id" => 1, "lastJobs" => 1, "id" => 1)
    );

    getUsersLogger()->debug("Jobs for all users: " . json_encode($usersWithJobs));

    return $usersWithJobs;
}


function getUserJobPid($login, $pid)
{
    $r = $GLOBALS['usersCol']->findOne(array(
        "_id"      => $login,
        "lastJobs.$pid" => array('$exists' => true)
    ), array("lastJobs.$pid" => 1));

    return $r['lastJobs'] ?? array();
}
