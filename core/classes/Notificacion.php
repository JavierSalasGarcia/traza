<?php

class Notificacion {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crear una notificación
     */
    public function create($data) {
        $query = "INSERT INTO notificaciones
                  (usuario_id, tipo, mensaje, referencia_id, referencia_tipo, fecha_creacion)
                  VALUES (:usuario_id, :tipo, :mensaje, :referencia_id, :referencia_tipo, NOW())";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $data['usuario_id']);
        $this->db->bind(':tipo', $data['tipo']);
        $this->db->bind(':mensaje', $data['mensaje']);
        $this->db->bind(':referencia_id', $data['referencia_id'] ?? null);
        $this->db->bind(':referencia_tipo', $data['referencia_tipo'] ?? null);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Notificación creada',
                'id' => $this->db->lastInsertId()
            ];
        }

        return ['success' => false, 'message' => 'Error al crear notificación'];
    }

    /**
     * Obtener notificaciones del usuario
     */
    public function getUserNotifications($usuario_id, $limit = 50, $solo_no_leidas = false) {
        $query = "SELECT * FROM notificaciones
                  WHERE usuario_id = :usuario_id";

        if ($solo_no_leidas) {
            $query .= " AND leida = 0";
        }

        $query .= " ORDER BY fecha_creacion DESC LIMIT :limit";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $usuario_id);
        $this->db->bind(':limit', $limit, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Obtener notificaciones no leídas
     */
    public function getUnread($usuario_id) {
        return $this->getUserNotifications($usuario_id, 50, true);
    }

    /**
     * Contar notificaciones no leídas
     */
    public function getUnreadCount($usuario_id) {
        $query = "SELECT COUNT(*) as count FROM notificaciones
                  WHERE usuario_id = :usuario_id AND leida = 0";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->fetch();

        return (int) $result['count'];
    }

    /**
     * Marcar notificación como leída
     */
    public function markAsRead($notificacion_id, $usuario_id) {
        $query = "UPDATE notificaciones
                  SET leida = 1, fecha_lectura = NOW()
                  WHERE id = :id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':id', $notificacion_id);
        $this->db->bind(':usuario_id', $usuario_id);

        if ($this->db->execute()) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    /**
     * Marcar todas como leídas
     */
    public function markAllAsRead($usuario_id) {
        $query = "UPDATE notificaciones
                  SET leida = 1, fecha_lectura = NOW()
                  WHERE usuario_id = :usuario_id AND leida = 0";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $usuario_id);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Todas las notificaciones marcadas como leídas'
            ];
        }

        return ['success' => false, 'message' => 'Error al marcar notificaciones'];
    }

    /**
     * Eliminar notificación
     */
    public function delete($notificacion_id, $usuario_id) {
        $query = "DELETE FROM notificaciones
                  WHERE id = :id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':id', $notificacion_id);
        $this->db->bind(':usuario_id', $usuario_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Notificación eliminada'];
        }

        return ['success' => false, 'message' => 'Error al eliminar'];
    }

    /**
     * Eliminar todas las notificaciones leídas
     */
    public function deleteAllRead($usuario_id) {
        $query = "DELETE FROM notificaciones
                  WHERE usuario_id = :usuario_id AND leida = 1";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $usuario_id);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Notificaciones leídas eliminadas'
            ];
        }

        return ['success' => false, 'message' => 'Error al eliminar'];
    }

    /**
     * Obtener URL de la notificación según el tipo
     */
    public function getNotificationUrl($notificacion) {
        $base = base_url('public/');

        switch ($notificacion['referencia_tipo']) {
            case 'aviso':
                return $base . 'view-aviso.php?id=' . $notificacion['referencia_id'];

            case 'propuesta':
                return $base . 'view-proposal.php?id=' . $notificacion['referencia_id'];

            case 'ticket':
                return $base . 'view-ticket.php?id=' . $notificacion['referencia_id'];

            case 'encuesta':
                return $base . 'view-encuesta.php?id=' . $notificacion['referencia_id'];

            case 'comentario':
                // Necesita saber de qué es el comentario
                return $base . 'dashboard.php';

            case 'grupo':
                return $base . 'manage-group.php?id=' . $notificacion['referencia_id'];

            default:
                return $base . 'dashboard.php';
        }
    }

    /**
     * Obtener icono según el tipo
     */
    public function getNotificationIcon($tipo) {
        $icons = [
            'aviso' => 'fa-bullhorn',
            'propuesta' => 'fa-lightbulb',
            'ticket' => 'fa-ticket-alt',
            'encuesta' => 'fa-poll',
            'comentario' => 'fa-comment',
            'grupo' => 'fa-users',
            'comision' => 'fa-users-cog',
            'sistema' => 'fa-cog'
        ];

        return $icons[$tipo] ?? 'fa-bell';
    }

    /**
     * Obtener color según el tipo
     */
    public function getNotificationColor($tipo) {
        $colors = [
            'aviso' => 'primary',
            'propuesta' => 'secondary',
            'ticket' => 'warning',
            'encuesta' => 'primary',
            'comentario' => 'info',
            'grupo' => 'success',
            'comision' => 'warning',
            'sistema' => 'gray'
        ];

        return $colors[$tipo] ?? 'gray';
    }

    /**
     * Notificar a múltiples usuarios
     */
    public function notifyMultiple($usuarios_ids, $tipo, $mensaje, $referencia_id = null, $referencia_tipo = null) {
        $this->db->query("START TRANSACTION");

        try {
            foreach ($usuarios_ids as $usuario_id) {
                $this->create([
                    'usuario_id' => $usuario_id,
                    'tipo' => $tipo,
                    'mensaje' => $mensaje,
                    'referencia_id' => $referencia_id,
                    'referencia_tipo' => $referencia_tipo
                ]);
            }

            $this->db->query("COMMIT");
            return ['success' => true, 'message' => 'Notificaciones enviadas'];
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Error al enviar notificaciones'];
        }
    }

    /**
     * Notificar a todos los miembros de un grupo
     */
    public function notifyGroup($grupo_id, $tipo, $mensaje, $referencia_id = null, $referencia_tipo = null, $exclude_user_id = null) {
        // Obtener miembros del grupo
        $query = "SELECT DISTINCT usuario_id FROM grupo_miembros
                  WHERE grupo_id = :grupo_id AND estado = 'aprobado'";

        if ($exclude_user_id) {
            $query .= " AND usuario_id != :exclude_user_id";
        }

        $this->db->query($query);
        $this->db->bind(':grupo_id', $grupo_id);

        if ($exclude_user_id) {
            $this->db->bind(':exclude_user_id', $exclude_user_id);
        }

        $miembros = $this->db->fetchAll();
        $usuarios_ids = array_column($miembros, 'usuario_id');

        if (!empty($usuarios_ids)) {
            return $this->notifyMultiple($usuarios_ids, $tipo, $mensaje, $referencia_id, $referencia_tipo);
        }

        return ['success' => true, 'message' => 'No hay usuarios para notificar'];
    }

    /**
     * Limpiar notificaciones antiguas (más de 30 días y leídas)
     */
    public function cleanOldNotifications($days = 30) {
        $query = "DELETE FROM notificaciones
                  WHERE leida = 1
                  AND fecha_lectura < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $this->db->query($query);
        $this->db->bind(':days', $days);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Notificaciones antiguas eliminadas'
            ];
        }

        return ['success' => false, 'message' => 'Error al limpiar'];
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public function getStats($usuario_id) {
        $query = "SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN leida = 0 THEN 1 END) as no_leidas,
                    COUNT(CASE WHEN leida = 1 THEN 1 END) as leidas,
                    COUNT(CASE WHEN fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as ultimas_24h
                  FROM notificaciones
                  WHERE usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $usuario_id);

        return $this->db->fetch();
    }
}
