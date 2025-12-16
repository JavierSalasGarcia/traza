<?php
/**
 * TrazaFI - Clase User
 * Modelo para gestión de usuarios
 */

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtiene un usuario por ID
     */
    public function getById($id) {
        return $this->db->query("SELECT id, nombre, apellidos, email, email_verificado, imagen_perfil,
                                biografia, fecha_registro, ultimo_acceso, es_admin
                                FROM usuarios WHERE id = :id AND activo = 1")
                       ->bind(':id', $id)
                       ->fetch();
    }

    /**
     * Obtiene un usuario por email
     */
    public function getByEmail($email) {
        return $this->db->query("SELECT * FROM usuarios WHERE email = :email")
                       ->bind(':email', $email)
                       ->fetch();
    }

    /**
     * Actualiza información del perfil
     */
    public function updateProfile($user_id, $data) {
        $allowed = ['nombre', 'apellidos', 'biografia', 'imagen_perfil'];
        $fields = [];
        $values = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $values[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = :id";
        $query = $this->db->query($sql);

        foreach ($values as $key => $value) {
            $query->bind(":{$key}", $value);
        }

        return $query->bind(':id', $user_id)->execute();
    }

    /**
     * Actualiza la contraseña del usuario
     */
    public function updatePassword($user_id, $new_password) {
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);

        return $this->db->query("UPDATE usuarios SET password_hash = :password WHERE id = :id")
                       ->bind(':password', $password_hash)
                       ->bind(':id', $user_id)
                       ->execute();
    }

    /**
     * Desactiva un usuario
     */
    public function deactivate($user_id) {
        return $this->db->query("UPDATE usuarios SET activo = 0 WHERE id = :id")
                       ->bind(':id', $user_id)
                       ->execute();
    }

    /**
     * Obtiene notificaciones no leídas de un usuario
     */
    public function getUnreadNotifications($user_id, $limit = 10) {
        return $this->db->query("SELECT * FROM notificaciones
                                WHERE usuario_id = :user_id AND leida = 0
                                ORDER BY fecha_creacion DESC
                                LIMIT :limit")
                       ->bind(':user_id', $user_id)
                       ->bind(':limit', $limit, PDO::PARAM_INT)
                       ->fetchAll();
    }

    /**
     * Cuenta notificaciones no leídas
     */
    public function countUnreadNotifications($user_id) {
        return $this->db->query("SELECT COUNT(*) FROM notificaciones
                                WHERE usuario_id = :user_id AND leida = 0")
                       ->bind(':user_id', $user_id)
                       ->fetchColumn();
    }

    /**
     * Marca una notificación como leída
     */
    public function markNotificationRead($notification_id, $user_id) {
        return $this->db->query("UPDATE notificaciones SET leida = 1, fecha_leida = NOW()
                                WHERE id = :id AND usuario_id = :user_id")
                       ->bind(':id', $notification_id)
                       ->bind(':user_id', $user_id)
                       ->execute();
    }

    /**
     * Marca todas las notificaciones como leídas
     */
    public function markAllNotificationsRead($user_id) {
        return $this->db->query("UPDATE notificaciones SET leida = 1, fecha_leida = NOW()
                                WHERE usuario_id = :user_id AND leida = 0")
                       ->bind(':user_id', $user_id)
                       ->execute();
    }

    /**
     * Busca usuarios por término
     */
    public function search($term, $limit = 20) {
        $term = '%' . $term . '%';
        return $this->db->query("SELECT id, nombre, apellidos, email, imagen_perfil
                                FROM usuarios
                                WHERE (nombre LIKE :term OR apellidos LIKE :term OR email LIKE :term)
                                AND activo = 1
                                AND email_verificado = 1
                                LIMIT :limit")
                       ->bind(':term', $term)
                       ->bind(':limit', $limit, PDO::PARAM_INT)
                       ->fetchAll();
    }

    /**
     * Obtiene estadísticas del usuario
     */
    public function getStats($user_id) {
        // Propuestas creadas
        $propuestas = $this->db->query("SELECT COUNT(*) FROM propuestas WHERE autor_id = :user_id")
                              ->bind(':user_id', $user_id)
                              ->fetchColumn();

        // Propuestas firmadas
        $firmas = $this->db->query("SELECT COUNT(*) FROM propuesta_firmas WHERE usuario_id = :user_id")
                          ->bind(':user_id', $user_id)
                          ->fetchColumn();

        // Grupos a los que pertenece
        $grupos = $this->db->query("SELECT COUNT(*) FROM grupo_miembros
                                   WHERE usuario_id = :user_id AND estado = 'aprobado'")
                          ->bind(':user_id', $user_id)
                          ->fetchColumn();

        // Avisos creados
        $avisos = $this->db->query("SELECT COUNT(*) FROM avisos WHERE autor_id = :user_id")
                          ->bind(':user_id', $user_id)
                          ->fetchColumn();

        return [
            'propuestas' => $propuestas,
            'firmas' => $firmas,
            'grupos' => $grupos,
            'avisos' => $avisos
        ];
    }
}
