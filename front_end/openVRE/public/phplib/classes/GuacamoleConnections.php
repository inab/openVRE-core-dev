<?php

namespace OpenVRE;

/**
 * Guacamole connections for desktop sessions, written straight into
 * Guacamole's MySQL database (its "mysql" auth extension).
 *
 * Users log in to Guacamole through Keycloak (OpenID). Guacamole then finds
 * the user by name in the database and shows the connections they may READ.
 * So for each desktop session this class creates a connection (host + port of
 * the session's Service) and gives the user READ permission on it.
 *
 * Environment variables: GUACAMOLE_DB_HOST, GUACAMOLE_DB_PORT (default 3306),
 * GUACAMOLE_DB_NAME, GUACAMOLE_DB_USER, GUACAMOLE_DB_PASSWORD.
 */
class GuacamoleConnections
{
    private ?\PDO $db = null;

    /** Whether the database settings are present. */
    public static function configured(): bool
    {
        foreach (array('GUACAMOLE_DB_HOST', 'GUACAMOLE_DB_NAME', 'GUACAMOLE_DB_USER') as $name) {
            if ((string)getenv($name) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Creates the connection and returns its link (/guacamole/#/client/...).
     * Throws on database errors.
     */
    public function create(string $username, string $name, string $host, int $port, string $protocol): string
    {
        if ($username === "") {
            throw new \RuntimeException("no username in the session");
        }
        $db = $this->db();
        $entityId = $this->entityId($username);
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO guacamole_connection (connection_name, protocol) VALUES (?, ?)")
                ->execute(array($name, $protocol));
            $connectionId = (int)$db->lastInsertId();
            $param = $db->prepare(
                "INSERT INTO guacamole_connection_parameter (connection_id, parameter_name, parameter_value) VALUES (?, ?, ?)"
            );
            $param->execute(array($connectionId, 'hostname', $host));
            $param->execute(array($connectionId, 'port', (string)$port));
            $db->prepare(
                "INSERT INTO guacamole_connection_permission (entity_id, connection_id, permission) VALUES (?, ?, 'READ')"
            )->execute(array($entityId, $connectionId));
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return "/guacamole/#/client/" . self::clientToken($connectionId);
    }

    /** Deletes the session's connection (parameters and permissions cascade). */
    public function delete(string $name): void
    {
        if ($name !== "" && self::configured()) {
            $this->db()->prepare("DELETE FROM guacamole_connection WHERE connection_name = ?")->execute(array($name));
        }
    }

    /** Guacamole's client id: base64url("<id>\0c\0mysql"). */
    private static function clientToken(int $connectionId): string
    {
        return rtrim(strtr(base64_encode($connectionId . "\0c\0mysql"), '+/', '-_'), '=');
    }

    private function db(): \PDO
    {
        if ($this->db === null) {
            if (!self::configured()) {
                throw new \RuntimeException("GUACAMOLE_DB_HOST, GUACAMOLE_DB_NAME and GUACAMOLE_DB_USER must be set");
            }
            $port = (string)getenv('GUACAMOLE_DB_PORT') ?: '3306';
            $this->db = new \PDO(
                "mysql:host=" . getenv('GUACAMOLE_DB_HOST') . ";port={$port};dbname=" . getenv('GUACAMOLE_DB_NAME') . ";charset=utf8mb4",
                (string)getenv('GUACAMOLE_DB_USER'),
                (string)getenv('GUACAMOLE_DB_PASSWORD'),
                array(
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                )
            );
        }
        return $this->db;
    }

    /**
     * The user's Guacamole entity id, creating the user when it is missing (it
     * gets a random password: the login is always through Keycloak).
     */
    private function entityId(string $username): int
    {
        $existing = $this->findEntityId($username);
        if ($existing !== null) {
            return $existing;
        }
        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO guacamole_entity (name, type) VALUES (?, 'USER')")->execute(array($username));
            $entityId = (int)$db->lastInsertId();
            $db->prepare(
                "INSERT INTO guacamole_user (entity_id, password_hash, password_salt, password_date) VALUES (?, UNHEX(?), UNHEX(?), NOW())"
            )->execute(array($entityId, bin2hex(random_bytes(32)), bin2hex(random_bytes(32))));
            $db->commit();
            return $entityId;
        } catch (\Throwable $e) {
            $db->rollBack();
            // Created at the same moment by another request (or by Guacamole itself).
            $existing = $this->findEntityId($username);
            if ($existing !== null) {
                return $existing;
            }
            throw $e;
        }
    }

    private function findEntityId(string $username): ?int
    {
        $stmt = $this->db()->prepare("SELECT entity_id FROM guacamole_entity WHERE name = ? AND type = 'USER'");
        $stmt->execute(array($username));
        $row = $stmt->fetch();
        return $row ? (int)$row['entity_id'] : null;
    }
}
