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

    $logger->info("=== GUACAMOLE CONNECTION START ===");
    $logger->info("Tool ID: " . $toolId);
    $logger->info("OpenVRE username: " . $username);
    $logger->info("Container name: " . $containerName);
    $logger->info("VNC port: " . $port);

    $db = $GLOBALS['guacamoleConn'];

    $logger->info("Guacamole DB connection available.");

    /*
     * Find the Guacamole entity corresponding to the OpenVRE user.
     */
    $stmt = $db->prepare("
        SELECT
            e.entity_id,
            e.name,
            e.type
        FROM guacamole_entity e
        WHERE e.name = ?
          AND e.type = 'USER'
    ");

    $stmt->execute([$username]);

    $user = $stmt->fetch();

    if (!$user) {

        $logger->error(
            "Guacamole user NOT FOUND: " . $username
        );

        throw new RuntimeException(
            "Guacamole user not found: " . $username
        );
    }

    $entityId = (int) $user['entity_id'];

    $logger->info(
        "Guacamole user found: " .
        "entity_id=" . $entityId .
        ", name=" . $user['name']
    );

    /*
     * Check whether this container already has a Guacamole connection.
     */
    $stmt = $db->prepare("
        SELECT
            c.connection_id,
            c.connection_name
        FROM guacamole_connection c
        INNER JOIN guacamole_connection_parameter p
            ON p.connection_id = c.connection_id
        WHERE p.parameter_name = 'hostname'
          AND p.parameter_value = ?
    ");

    $stmt->execute([$containerName]);

    $existing = $stmt->fetch();

    if ($existing) {

        $logger->info(
            "Existing Guacamole connection found: " .
            "connection_id=" . $existing['connection_id'] .
            ", name=" . $existing['connection_name']
        );

        $logger->info("=== GUACAMOLE CONNECTION END (EXISTING) ===");

        return (int) $existing['connection_id'];
    }

    $logger->info(
        "No existing Guacamole connection found for container."
    );

    /*
     * Create connection + parameters + permission atomically.
     */
    $db->beginTransaction();

    try {

        $connectionName =
            $toolId . " - " . $username . " - " . $containerName;

        $logger->info(
            "Creating Guacamole connection: " . $connectionName
        );

        $stmt = $db->prepare("
            INSERT INTO guacamole_connection
                (connection_name, protocol)
            VALUES
                (?, 'vnc')
        ");

        $stmt->execute([$connectionName]);

        $connectionId = (int) $db->lastInsertId();

        $logger->info(
            "Created guacamole_connection: connection_id=" .
            $connectionId
        );

        /*
         * Hostname
         */
        $stmt = $db->prepare("
            INSERT INTO guacamole_connection_parameter
                (connection_id, parameter_name, parameter_value)
            VALUES
                (?, 'hostname', ?)
        ");

        $stmt->execute([
            $connectionId,
            $containerName
        ]);

        $logger->info(
            "Added hostname parameter: " . $containerName
        );

        /*
         * Port
         */
        $stmt = $db->prepare("
            INSERT INTO guacamole_connection_parameter
                (connection_id, parameter_name, parameter_value)
            VALUES
                (?, 'port', ?)
        ");

        $stmt->execute([
            $connectionId,
            (string) $port
        ]);

        $logger->info(
            "Added port parameter: " . $port
        );

        /*
         * User READ permission
         */
        $stmt = $db->prepare("
            INSERT INTO guacamole_connection_permission
                (entity_id, connection_id, permission)
            VALUES
                (?, ?, 'READ')
        ");

        $stmt->execute([
            $entityId,
            $connectionId
        ]);

        $logger->info(
            "Added READ permission: " .
            "entity_id=" . $entityId .
            ", connection_id=" . $connectionId
        );

        $db->commit();

        $logger->info(
            "Guacamole connection committed successfully: " .
            "connection_id=" . $connectionId
        );

        $logger->info("=== GUACAMOLE CONNECTION END (CREATED) ===");

        return $connectionId;

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $logger->error(
            "Guacamole connection creation FAILED: " .
            $e->getMessage()
        );

        $logger->error(
            "Exception class: " . get_class($e)
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

            return [
                "ready"   => false,
                "reload"  => true,
                "title"   => "Preparing Guacamole session",
                "message" => "The VNC service is ready, but the Guacamole connection is still being prepared."
            ];
        }
    }

    /*----------------------------------------------------
     * Mark service ready
     *---------------------------------------------------*/

    if (($job['interactive_backend'] ?? '') === 'guacamole') {

        $url = $GLOBALS['SERVER']
            . $interactiveToolprefix
            . "guacamole/";

    } else {

        $url = $GLOBALS['SERVER']
            . $interactiveToolprefix
            . $job['containerName']
            . "/";
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
