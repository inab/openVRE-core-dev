<?php

require dirname(__FILE__) . "/../../config/globals.inc.php";
use OpenVRE\LoggerFactory;
use OpenVRE\NotFoundException;
use OpenVRE\UserType;


function getInteractiveLogger()
{
    static $logger = null;

    if ($logger === null) {
        $logger = LoggerFactory::getLogger('Get interactive info');
    }

    return $logger;
}

function createGuacamoleConnectionForJob($toolId, $username, $containerName, $port)
{

    $logger = getInteractiveLogger();
    $db = $GLOBALS['guacamoleConn'];
    $logger->info("=== GUACAMOLE CONNECTION START ===");
    $logger->info("Tool ID: " . $toolId);
    $logger->info("OpenVRE username: " . $username);
    $logger->info("Container name: " . $containerName);
    $logger->info("VNC port: " . $port);
    if ($username === '' || $containerName === '' || $port < 1 || $port > 65535) {
        throw new InvalidArgumentException("Invalid Guacamole user or connection parameters.");
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT e.entity_id, e.name, e.type, u.user_id
            FROM guacamole_entity e
            LEFT JOIN guacamole_user u ON u.entity_id = e.entity_id
            WHERE e.name = ?
              AND e.type = 'USER'
            FOR UPDATE
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
      
        if (!$user) {
            $logger->info("Provisioning missing Guacamole user: " . $username);

            $stmt = $db->prepare("
                INSERT INTO guacamole_entity (name, type)
                VALUES (?, 'USER')
            ");
            $stmt->execute([$username]);

            $entityId = (int) $db->lastInsertId();
            $userId = null;
        } else {
            $entityId = (int) $user['entity_id'];
            $userId = $user['user_id'] !== null
                ? (int) $user['user_id']
                : null;

            $logger->info("Guacamole entity found: entity_id=" . $entityId);
        }
        if ($userId === null) {
            $randomPassword = bin2hex(random_bytes(32));
            $salt = random_bytes(32);

            // Guacamole JDBC: SHA-256(password + HEX(salt)).
            // MySQL HEX() uses uppercase hexadecimal characters.
            $passwordHash = hash(
                'sha256',
                $randomPassword . strtoupper(bin2hex($salt)),
                true
            );

            $stmt = $db->prepare("
                INSERT INTO guacamole_user
                    (entity_id, password_hash, password_salt, password_date)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$entityId, $passwordHash, $salt]);

            $logger->info("Guacamole JDBC user record provisioned: entity_id=" . $entityId);

            unset($randomPassword, $salt, $passwordHash);
        }
        $stmt = $db->prepare("
            SELECT c.connection_id, c.connection_name
            FROM guacamole_connection c
            INNER JOIN guacamole_connection_parameter p
                ON p.connection_id = c.connection_id
            WHERE p.parameter_name = 'hostname'
              AND p.parameter_value = ?
            LIMIT 1
        ");
        $stmt->execute([$containerName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $connectionId = (int) $existing['connection_id'];

            $logger->info(
                "Existing connection found: connection_id=" . $connectionId
            );
        } else {
            /*
             * Create the connection and its parameters.
             */
            $connectionName = $toolId . " - " . $username . " - " . $containerName;

            $stmt = $db->prepare("
                INSERT INTO guacamole_connection (connection_name, protocol)
                VALUES (?, 'vnc')
            ");
            $stmt->execute([$connectionName]);

            $connectionId = (int) $db->lastInsertId();

            $stmt = $db->prepare("
                INSERT INTO guacamole_connection_parameter
                    (connection_id, parameter_name, parameter_value)
                VALUES (?, 'hostname', ?), (?, 'port', ?)
            ");
            $stmt->execute([
                $connectionId, $containerName,
                $connectionId, (string) $port
            ]);

            $logger->info(
                "Created connection: connection_id=" . $connectionId
            );
        }
        $stmt = $db->prepare("
            INSERT INTO guacamole_connection_permission
                (entity_id, connection_id, permission)
            SELECT ?, ?, 'READ'
            WHERE NOT EXISTS (
                SELECT 1
                FROM guacamole_connection_permission
                WHERE entity_id = ?
                  AND connection_id = ?
                  AND permission = 'READ'
            )
        ");
        $stmt->execute([
            $entityId, $connectionId,
            $entityId, $connectionId
        ]);

        $db->commit();

        $logger->info(
            "=== GUACAMOLE CONNECTION END === connection_id=" . $connectionId
        );
        return $connectionId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $logger->error(
            "Guacamole provisioning failed: " .
            get_class($e) . ": " . $e->getMessage()
        );
        throw $e;
    }
}

function checkStatus($pid)
{

    $logger = getInteractiveLogger();
    $logger->error("CHECKSTATUS REACHED - PID: " . $pid);
    $interactiveToolprefix = "/interactive-tool/";

    $login = $_SESSION['User']['_id'];
    $jobs  = getUserJobPid($login, $pid);

    if (!isset($jobs[$pid])) {
        return [
            "ready"   => false,
            "reload"  => false,
            "title"   => "Job not found",
            "message" => "The requested interactive session could not be found."
        ];
    }
    $job = $jobs[$pid];

    /*----------------------------------------------------
     * Job state
     *---------------------------------------------------*/

    if ($job['state'] == "PENDING") {
        return [
            "ready"   => false,
            "reload"  => true,
            "title"   => "Waiting for scheduler",
            "message" => "The interactive session is waiting for compute resources."
        ];
    }

    if ($job['state'] != "RUNNING") {
        return [
            "ready"   => false,
            "reload"  => false,
            "title"   => "Session finished",
            "message" => "The interactive session is no longer running."
        ];
    }

    /*----------------------------------------------------
     * Wait until stdout exists
     *---------------------------------------------------*/

    if (!is_file($job['stdout_file'])) {
        return [
            "ready"   => false,
            "reload"  => true,
            "title"   => "Starting container",
            "message" => "Waiting for launcher output..."
        ];
    }

    $stdout = file_get_contents($job['stdout_file']);

    /*----------------------------------------------------
     * Save metadata in the session (KEEP THIS)
     *---------------------------------------------------*/

    /*
    if (preg_match('/ExposedPort: (\d+)/', $stdout, $matches)) {
        $_SESSION['User']['lastjobs'][$pid]['interactive_tool']['port'] = $matches[1];
    }

    if (preg_match('/ContainerID: (\w+)/', $stdout, $matches)) {
        $_SESSION['User']['lastjobs'][$pid]['interactive_tool']['container_id'] = $matches[1];
    }

    if (preg_match('/ContainerName: (\S+)/', $stdout, $matches)) {
        $_SESSION['User']['lastjobs'][$pid]['interactive_tool']['containerName'] = $matches[1];
    }
    */

    /*----------------------------------------------------
     * Has the service started?
     *---------------------------------------------------*/   

    $isServiceUp = (strpos($stdout, "Service UP") !== false);

    if (!$isServiceUp) {

        return [
            "ready"   => false,
            "reload"  => true,
            "title"   => "Preparing interactive session",
            "message" => "The container is running. Waiting for the application to become available..."
        ];
    }
    /*  Guacamole connection trigger  */
    $guacamoleConnectionId = null;

    if (($job['interactive_backend'] ?? '') === 'guacamole') {

        $username = (string) $_SESSION['User']['_id'];
        $containerName = (string) $job['containerName'];
        $port = (int) ($job['interactive_port'] ?? 5900);
        $toolId = (string) $job['toolId'];

        try {

            $guacamoleConnectionId =
                createGuacamoleConnectionForJob(
                    $toolId,
                    $username,
                    $containerName,
                    $port
                );

        } catch (Throwable $e) {

             error_log(
                '[Guacamole connection creation failed] ' .
                'toolId=' . $toolId .
                ', username=' . $username .
                ', container=' . $containerName .
                ', port=' . $port .
                ', exception=' . get_class($e) .
                ', message=' . $e->getMessage() .
                ', file=' . $e->getFile() .
                ':' . $e->getLine() .
                "\n" . $e->getTraceAsString()
            );

            // not another polling attempt.
            if (
                $e instanceof RuntimeException &&
                $e->getMessage() === 'GUACAMOLE_USER_NOT_PROVISIONED'
            ) {
                return [
                        "ready"   => false,
                        "reload"  => false,
                        "login_required" => true,
                        "title"   => "Guacamole login required",
                        "message" => "Please sign in to Guacamole, then return here to retry your session.",
                        "auth_url" => $GLOBALS['SERVER']
                            . $interactiveToolprefix
                            . "guacamole/"
                    ];
            }

            return [
                "ready"   => false,
                "reload"  => true,
                "title"   => "Preparing Guacamole session",
                "message" => "The VNC service is ready, but the Guacamole connection is still being prepared."
            ];
        }
    }

    if (($job['interactive_backend'] ?? '') === 'guacamole') {

        $url = $GLOBALS['SERVER']
            . $interactiveToolprefix
            . "guacamole/";
    } else {
        $identifier = rtrim(
            strtr(
                base64_encode(
                    $guacamoleConnectionId . "\0c\0mysql"
                ),
                '+/',
                '-_'    
            ),
            '='
        );

        $url = $GLOBALS['SERVER']
        . '/interactive-tool/guacamole/#/client/'
        . $identifier;
    }
    return [
        "ready"   => true,
        "reload"  => false,
        "title"   => "Interactive session ready",
        "message" => "Your session is ready.", 
        "guacamole_connection_id" => $guacamoleConnectionId,
        "url"     => $url,
        "job"     => $job
    ];
}
