<?php
/**
 * TrazaFI - Clase Permission
 * Modelo para gestión de permisos y roles
 */

class Permission {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtiene todos los permisos
     */
    public function getAllPermissions() {
        return $this->db->query("SELECT * FROM permisos ORDER BY categoria, nombre")
                       ->fetchAll();
    }

    /**
     * Obtiene permisos por categoría
     */
    public function getPermissionsByCategory() {
        $permissions = $this->getAllPermissions();
        $grouped = [];

        foreach ($permissions as $permission) {
            $category = $permission['categoria'] ?? 'general';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $permission;
        }

        return $grouped;
    }

    /**
     * Obtiene todos los roles
     */
    public function getAllRoles() {
        return $this->db->query("SELECT * FROM roles ORDER BY nombre")
                       ->fetchAll();
    }

    /**
     * Obtiene un rol por ID
     */
    public function getRoleById($id) {
        return $this->db->query("SELECT * FROM roles WHERE id = :id")
                       ->bind(':id', $id)
                       ->fetch();
    }

    /**
     * Obtiene permisos de un rol
     */
    public function getRolePermissions($rol_id) {
        return $this->db->query("SELECT p.*
                                FROM permisos p
                                INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
                                WHERE rp.rol_id = :rol_id
                                ORDER BY p.categoria, p.nombre")
                       ->bind(':rol_id', $rol_id)
                       ->fetchAll();
    }

    /**
     * Obtiene IDs de permisos de un rol
     */
    public function getRolePermissionIds($rol_id) {
        $permissions = $this->getRolePermissions($rol_id);
        return array_column($permissions, 'id');
    }

    /**
     * Verifica si un rol tiene un permiso específico
     */
    public function roleHasPermission($rol_id, $permission_key) {
        $count = $this->db->query("SELECT COUNT(*)
                                  FROM rol_permisos rp
                                  INNER JOIN permisos p ON rp.permiso_id = p.id
                                  WHERE rp.rol_id = :rol_id AND p.clave = :key")
                         ->bind(':rol_id', $rol_id)
                         ->bind(':key', $permission_key)
                         ->fetchColumn();

        return $count > 0;
    }

    /**
     * Crea un nuevo rol
     */
    public function createRole($nombre, $clave, $descripcion = null) {
        // Verificar que la clave sea única
        $exists = $this->db->query("SELECT id FROM roles WHERE clave = :clave")
                          ->bind(':clave', $clave)
                          ->fetch();

        if ($exists) {
            return ['success' => false, 'message' => 'La clave del rol ya existe'];
        }

        $this->db->query("INSERT INTO roles (nombre, clave, descripcion, es_sistema)
                         VALUES (:nombre, :clave, :descripcion, 0)")
                ->bind(':nombre', $nombre)
                ->bind(':clave', $clave)
                ->bind(':descripcion', $descripcion)
                ->execute();

        $rol_id = $this->db->lastInsertId();

        return ['success' => true, 'rol_id' => $rol_id];
    }

    /**
     * Actualiza un rol
     */
    public function updateRole($rol_id, $nombre, $descripcion = null) {
        // No permitir actualizar roles del sistema
        $role = $this->getRoleById($rol_id);
        if ($role['es_sistema']) {
            return ['success' => false, 'message' => 'No se pueden modificar roles del sistema'];
        }

        $this->db->query("UPDATE roles SET nombre = :nombre, descripcion = :descripcion
                         WHERE id = :id")
                ->bind(':nombre', $nombre)
                ->bind(':descripcion', $descripcion)
                ->bind(':id', $rol_id)
                ->execute();

        return ['success' => true];
    }

    /**
     * Elimina un rol
     */
    public function deleteRole($rol_id) {
        // No permitir eliminar roles del sistema
        $role = $this->getRoleById($rol_id);
        if ($role['es_sistema']) {
            return ['success' => false, 'message' => 'No se pueden eliminar roles del sistema'];
        }

        // Verificar que no haya usuarios con este rol
        $count = $this->db->query("SELECT COUNT(*) FROM grupo_miembros WHERE rol_id = :rol_id")
                         ->bind(':rol_id', $rol_id)
                         ->fetchColumn();

        if ($count > 0) {
            return ['success' => false, 'message' => 'No se puede eliminar un rol que está siendo usado'];
        }

        $this->db->query("DELETE FROM roles WHERE id = :id")
                ->bind(':id', $rol_id)
                ->execute();

        return ['success' => true];
    }

    /**
     * Asigna permisos a un rol
     */
    public function assignPermissionsToRole($rol_id, $permission_ids) {
        // Limpiar permisos existentes
        $this->db->query("DELETE FROM rol_permisos WHERE rol_id = :rol_id")
                ->bind(':rol_id', $rol_id)
                ->execute();

        // Asignar nuevos permisos
        foreach ($permission_ids as $permission_id) {
            $this->db->query("INSERT INTO rol_permisos (rol_id, permiso_id)
                             VALUES (:rol_id, :permiso_id)")
                    ->bind(':rol_id', $rol_id)
                    ->bind(':permiso_id', $permission_id)
                    ->execute();
        }

        return true;
    }

    /**
     * Agrega un permiso a un rol
     */
    public function addPermissionToRole($rol_id, $permiso_id) {
        // Verificar que no exista ya
        $exists = $this->db->query("SELECT id FROM rol_permisos
                                   WHERE rol_id = :rol_id AND permiso_id = :permiso_id")
                          ->bind(':rol_id', $rol_id)
                          ->bind(':permiso_id', $permiso_id)
                          ->fetch();

        if ($exists) {
            return false;
        }

        return $this->db->query("INSERT INTO rol_permisos (rol_id, permiso_id)
                                VALUES (:rol_id, :permiso_id)")
                       ->bind(':rol_id', $rol_id)
                       ->bind(':permiso_id', $permiso_id)
                       ->execute();
    }

    /**
     * Elimina un permiso de un rol
     */
    public function removePermissionFromRole($rol_id, $permiso_id) {
        return $this->db->query("DELETE FROM rol_permisos
                                WHERE rol_id = :rol_id AND permiso_id = :permiso_id")
                       ->bind(':rol_id', $rol_id)
                       ->bind(':permiso_id', $permiso_id)
                       ->execute();
    }

    /**
     * Obtiene permisos de un usuario en un grupo específico
     */
    public function getUserPermissionsInGroup($usuario_id, $grupo_id) {
        return $this->db->query("SELECT DISTINCT p.clave, p.nombre
                                FROM permisos p
                                INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
                                INNER JOIN grupo_miembros gm ON rp.rol_id = gm.rol_id
                                WHERE gm.usuario_id = :usuario_id
                                AND gm.grupo_id = :grupo_id
                                AND gm.estado = 'aprobado'")
                       ->bind(':usuario_id', $usuario_id)
                       ->bind(':grupo_id', $grupo_id)
                       ->fetchAll();
    }

    /**
     * Obtiene todas las claves de permisos de un usuario en un grupo
     */
    public function getUserPermissionKeysInGroup($usuario_id, $grupo_id) {
        $permissions = $this->getUserPermissionsInGroup($usuario_id, $grupo_id);
        return array_column($permissions, 'clave');
    }

    /**
     * Verifica si un usuario tiene un permiso específico en un grupo
     */
    public function userHasPermissionInGroup($usuario_id, $grupo_id, $permission_key) {
        $count = $this->db->query("SELECT COUNT(*)
                                  FROM permisos p
                                  INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
                                  INNER JOIN grupo_miembros gm ON rp.rol_id = gm.rol_id
                                  WHERE gm.usuario_id = :usuario_id
                                  AND gm.grupo_id = :grupo_id
                                  AND gm.estado = 'aprobado'
                                  AND p.clave = :key")
                         ->bind(':usuario_id', $usuario_id)
                         ->bind(':grupo_id', $grupo_id)
                         ->bind(':key', $permission_key)
                         ->fetchColumn();

        return $count > 0;
    }

    /**
     * Crea un permiso personalizado
     */
    public function createPermission($nombre, $clave, $descripcion = null, $categoria = 'personalizado') {
        // Verificar que la clave sea única
        $exists = $this->db->query("SELECT id FROM permisos WHERE clave = :clave")
                          ->bind(':clave', $clave)
                          ->fetch();

        if ($exists) {
            return ['success' => false, 'message' => 'La clave del permiso ya existe'];
        }

        $this->db->query("INSERT INTO permisos (nombre, clave, descripcion, categoria)
                         VALUES (:nombre, :clave, :descripcion, :categoria)")
                ->bind(':nombre', $nombre)
                ->bind(':clave', $clave)
                ->bind(':descripcion', $descripcion)
                ->bind(':categoria', $categoria)
                ->execute();

        $permiso_id = $this->db->lastInsertId();

        return ['success' => true, 'permiso_id' => $permiso_id];
    }
}
