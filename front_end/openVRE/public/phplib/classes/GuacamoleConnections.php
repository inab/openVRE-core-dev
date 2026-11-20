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
 * GUACAMOLE_DB_NAME (or GUACAMOLE_DB, as in docker-compose), GUACAMOLE_DB_USER,
 * GUACAMOLE_DB_PASSWORD.
 */
class GuacamoleConnections
{
    /** MySQL "duplicate key": another request created the same row first. */
    private const DUPLICATE_KEY = '23000';

    private ?\PDO $db = null;

    /** Whether the database settings are present. */
    public static function configured(): bool
    {
        return (string)getenv('GUACAMOLE_DB_HOST') !== ''
            && self::databaseName() !== ''
            && (string)getenv('GUACAMOLE_DB_USER') !== '';
    }

    /**
     * Creates the connection, gives the user READ permission on it, and
     * returns its link (/guacamole/#/client/...). Throws on database errors.
     */
    public function create(string $username, string $name, string $host, int $port, string $protocol): string
    {
        if ($username === "") {
            throw new \RuntimeException("no username in the session");
        }
        try {
            $connectionId = $this->createInTransaction($username, $name, $host, $port, $protocol);
        } catch (\PDOException $e) {
            // The user was created at the same moment by another request (or
            // by Guacamole's own login): its row now exists, so one retry finds it.
            if ((string)$e->getCode() !== self::DUPLICATE_KEY) {
                throw $e;
            }
            $connectionId = $this->createInTransaction($username, $name, $host, $port, $protocol);
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

    /** User, connection and permission in one transaction; returns the connection id. */
    private function createInTransaction(string $username, string $name, string $host, int $port, string $protocol): int
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $entityId = $this->lockOrCreateUser($username);

            $db->prepare("INSERT INTO guacamole_connection (connection_name, protocol) VALUES (?, ?)")
                ->execute(array($name, $protocol));
            $connectionId = (int)$db->lastInsertId();
            $db->prepare(
                "INSERT INTO guacamole_connection_parameter (connection_id, parameter_name, parameter_value)
                 VALUES (?, 'hostname', ?), (?, 'port', ?)"
            )->execute(array($connectionId, $host, $connectionId, (string)$port));
            $db->prepare(
                "INSERT INTO guacamole_connection_permission (entity_id, connection_id, permission) VALUES (?, ?, 'READ')"
            )->execute(array($entityId, $connectionId));

            $db->commit();
            return $connectionId;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * The user's entity id, locked for this transaction. Creates the entity
     * and/or its user record when missing (Guacamole only lists connections
     * for users that have both).
     */
    private function lockOrCreateUser(string $username): int
    {
        $db = $this->db();
        $stmt = $db->prepare(
            "SELECT e.entity_id, u.user_id
             FROM guacamole_entity e
             LEFT JOIN guacamole_user u ON u.entity_id = e.entity_id
             WHERE e.name = ? AND e.type = 'USER'
             FOR UPDATE"
        );
        $stmt->execute(array($username));
        $row = $stmt->fetch();

        if ($row) {
            $entityId = (int)$row['entity_id'];
        } else {
            $db->prepare("INSERT INTO guacamole_entity (name, type) VALUES (?, 'USER')")->execute(array($username));
            $entityId = (int)$db->lastInsertId();
        }
        if (!$row || $row['user_id'] === null) {
            // A valid Guacamole password record for a password nobody knows:
            // the login is always through Keycloak.
            // Guacamole JDBC hash: SHA-256(password + uppercase hex of the salt).
            $salt = random_bytes(32);
            $hash = hash('sha256', bin2hex(random_bytes(32)) . strtoupper(bin2hex($salt)), true);
            $db->prepare(
                "INSERT INTO guacamole_user (entity_id, password_hash, password_salt, password_date)
                 VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
            )->execute(array($entityId, $hash, $salt));
        }
        return $entityId;
    }

    /** Guacamole's client id: base64url("<id>\0c\0mysql"). */
    private static function clientToken(int $connectionId): string
    {
        return rtrim(strtr(base64_encode($connectionId . "\0c\0mysql"), '+/', '-_'), '=');
    }

    private static function databaseName(): string
    {
        return (string)getenv('GUACAMOLE_DB_NAME') ?: (string)getenv('GUACAMOLE_DB');
    }

    private function db(): \PDO
    {
        if ($this->db === null) {
            if (!self::configured()) {
                throw new \RuntimeException("GUACAMOLE_DB_HOST, GUACAMOLE_DB_NAME and GUACAMOLE_DB_USER must be set");
            }
            $port = (string)getenv('GUACAMOLE_DB_PORT') ?: '3306';
            $this->db = new \PDO(
                "mysql:host=" . getenv('GUACAMOLE_DB_HOST') . ";port={$port};dbname=" . self::databaseName() . ";charset=utf8mb4",
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
}
