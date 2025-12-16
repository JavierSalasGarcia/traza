<?php
/**
 * TrazaFI - Clase Database
 * Maneja la conexión y operaciones con la base de datos
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $stmt;
    private $error;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log('Database Connection Error: ' . $this->error);
            throw new Exception('Error de conexión a la base de datos');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query($sql) {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    public function execute() {
        try {
            return $this->stmt->execute();
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log('Database Execute Error: ' . $this->error);
            return false;
        }
    }

    public function fetchAll() {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    public function fetch() {
        $this->execute();
        return $this->stmt->fetch();
    }

    public function fetchColumn() {
        $this->execute();
        return $this->stmt->fetchColumn();
    }

    public function rowCount() {
        return $this->stmt->rowCount();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }

    public function getError() {
        return $this->error;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
