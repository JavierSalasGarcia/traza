<?php
/**
 * TrazaFI - Clase Config
 * Maneja configuraciones del sistema almacenadas en base de datos
 */

class Config {
    private static $instance = null;
    private $db;
    private $cache = [];

    private function __construct() {
        $this->db = Database::getInstance();
        $this->loadConfig();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig() {
        $result = $this->db->query("SELECT clave, valor, tipo FROM configuracion_sistema")->fetchAll();

        foreach ($result as $row) {
            $this->cache[$row['clave']] = $this->castValue($row['valor'], $row['tipo']);
        }
    }

    private function castValue($value, $type) {
        switch ($type) {
            case 'numero':
                return (int) $value;
            case 'boolean':
                return (bool) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    public function get($key, $default = null) {
        return $this->cache[$key] ?? $default;
    }

    public function set($key, $value, $type = 'texto', $descripcion = null) {
        $tipoActual = is_int($value) ? 'numero' :
                     (is_bool($value) ? 'boolean' :
                     (is_array($value) ? 'json' : 'texto'));

        if ($type === 'texto') {
            $type = $tipoActual;
        }

        $valorGuardar = is_array($value) ? json_encode($value) : $value;

        $exists = $this->db->query("SELECT id FROM configuracion_sistema WHERE clave = :clave")
                          ->bind(':clave', $key)
                          ->fetch();

        if ($exists) {
            $this->db->query("UPDATE configuracion_sistema
                             SET valor = :valor, tipo = :tipo
                             WHERE clave = :clave")
                    ->bind(':valor', $valorGuardar)
                    ->bind(':tipo', $type)
                    ->bind(':clave', $key)
                    ->execute();
        } else {
            $this->db->query("INSERT INTO configuracion_sistema (clave, valor, tipo, descripcion)
                             VALUES (:clave, :valor, :tipo, :descripcion)")
                    ->bind(':clave', $key)
                    ->bind(':valor', $valorGuardar)
                    ->bind(':tipo', $type)
                    ->bind(':descripcion', $descripcion)
                    ->execute();
        }

        $this->cache[$key] = $value;
        return true;
    }

    public function delete($key) {
        $this->db->query("DELETE FROM configuracion_sistema WHERE clave = :clave")
                ->bind(':clave', $key)
                ->execute();

        unset($this->cache[$key]);
        return true;
    }

    public function all() {
        return $this->cache;
    }

    public function reload() {
        $this->cache = [];
        $this->loadConfig();
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
