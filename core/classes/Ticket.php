<?php

class Ticket {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crear un nuevo ticket
     */
    public function create($data) {
        $query = "INSERT INTO tickets
                  (titulo, descripcion, tipo, prioridad, grupo_id, solicitante_id, estado, fecha_creacion)
                  VALUES (:titulo, :descripcion, :tipo, :prioridad, :grupo_id, :solicitante_id, 'pendiente', NOW())";

        $this->db->query($query);
        $this->db->bind(':titulo', $data['titulo']);
        $this->db->bind(':descripcion', $data['descripcion']);
        $this->db->bind(':tipo', $data['tipo']);
        $this->db->bind(':prioridad', $data['prioridad'] ?? 'media');
        $this->db->bind(':grupo_id', $data['grupo_id'] ?? null);
        $this->db->bind(':solicitante_id', $data['solicitante_id']);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Ticket creado exitosamente',
                'id' => $this->db->lastInsertId()
            ];
        }

        return ['success' => false, 'message' => 'Error al crear el ticket'];
    }

    /**
     * Obtener ticket por ID
     */
    public function getById($id) {
        $query = "SELECT t.*,
                         u.nombre as solicitante_nombre,
                         u.email as solicitante_email,
                         u.imagen_perfil as solicitante_imagen,
                         g.nombre as grupo_nombre,
                         a.nombre as asignado_nombre
                  FROM tickets t
                  LEFT JOIN usuarios u ON t.solicitante_id = u.id
                  LEFT JOIN grupos g ON t.grupo_id = g.id
                  LEFT JOIN usuarios a ON t.asignado_a = a.id
                  WHERE t.id = :id";

        $this->db->query($query);
        $this->db->bind(':id', $id);
        return $this->db->fetch();
    }

    /**
     * Obtener tickets con filtros
     */
    public function getTickets($filters = []) {
        $query = "SELECT t.*,
                         u.nombre as solicitante_nombre,
                         g.nombre as grupo_nombre,
                         a.nombre as asignado_nombre,
                         COUNT(DISTINCT tc.id) as total_comentarios
                  FROM tickets t
                  LEFT JOIN usuarios u ON t.solicitante_id = u.id
                  LEFT JOIN grupos g ON t.grupo_id = g.id
                  LEFT JOIN usuarios a ON t.asignado_a = a.id
                  LEFT JOIN tickets_comentarios tc ON t.id = tc.ticket_id
                  WHERE 1=1";

        if (isset($filters['estado'])) {
            $query .= " AND t.estado = :estado";
        }

        if (isset($filters['tipo'])) {
            $query .= " AND t.tipo = :tipo";
        }

        if (isset($filters['prioridad'])) {
            $query .= " AND t.prioridad = :prioridad";
        }

        if (isset($filters['grupo_id'])) {
            $query .= " AND (t.grupo_id = :grupo_id OR t.grupo_id IS NULL)";
        }

        if (isset($filters['solicitante_id'])) {
            $query .= " AND t.solicitante_id = :solicitante_id";
        }

        if (isset($filters['asignado_a'])) {
            $query .= " AND t.asignado_a = :asignado_a";
        }

        $query .= " GROUP BY t.id ORDER BY
                    FIELD(t.prioridad, 'alta', 'media', 'baja'),
                    t.fecha_creacion DESC";

        $this->db->query($query);

        if (isset($filters['estado'])) {
            $this->db->bind(':estado', $filters['estado']);
        }
        if (isset($filters['tipo'])) {
            $this->db->bind(':tipo', $filters['tipo']);
        }
        if (isset($filters['prioridad'])) {
            $this->db->bind(':prioridad', $filters['prioridad']);
        }
        if (isset($filters['grupo_id'])) {
            $this->db->bind(':grupo_id', $filters['grupo_id']);
        }
        if (isset($filters['solicitante_id'])) {
            $this->db->bind(':solicitante_id', $filters['solicitante_id']);
        }
        if (isset($filters['asignado_a'])) {
            $this->db->bind(':asignado_a', $filters['asignado_a']);
        }

        return $this->db->fetchAll();
    }

    /**
     * Actualizar estado del ticket
     */
    public function updateEstado($ticket_id, $nuevo_estado, $user_id) {
        $valid_states = ['pendiente', 'en_revision', 'en_desarrollo', 'completado', 'rechazado', 'cancelado'];

        if (!in_array($nuevo_estado, $valid_states)) {
            return ['success' => false, 'message' => 'Estado inválido'];
        }

        $query = "UPDATE tickets SET estado = :estado";

        if ($nuevo_estado === 'en_desarrollo') {
            $query .= ", fecha_inicio = NOW()";
        }

        if ($nuevo_estado === 'completado') {
            $query .= ", fecha_completado = NOW()";
        }

        $query .= " WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':estado', $nuevo_estado);
        $this->db->bind(':id', $ticket_id);

        if ($this->db->execute()) {
            // Notificar al solicitante
            $this->notifySolicitante($ticket_id, $nuevo_estado);

            return ['success' => true, 'message' => 'Estado actualizado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al actualizar el estado'];
    }

    /**
     * Asignar ticket a un usuario (dev/admin)
     */
    public function assignTo($ticket_id, $asignado_id, $asignador_id) {
        if (!is_admin($asignador_id)) {
            return ['success' => false, 'message' => 'No tienes permiso para asignar tickets'];
        }

        $query = "UPDATE tickets
                  SET asignado_a = :asignado_id,
                      estado = 'en_revision'
                  WHERE id = :id AND estado = 'pendiente'";

        $this->db->query($query);
        $this->db->bind(':asignado_id', $asignado_id);
        $this->db->bind(':id', $ticket_id);

        if ($this->db->execute()) {
            // Notificar al asignado
            $this->notifyAsignado($ticket_id, $asignado_id);

            return ['success' => true, 'message' => 'Ticket asignado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al asignar el ticket'];
    }

    /**
     * Añadir comentario al ticket
     */
    public function addComment($ticket_id, $usuario_id, $comentario, $es_solucion = false) {
        $query = "INSERT INTO tickets_comentarios
                  (ticket_id, usuario_id, comentario, es_solucion, fecha_creacion)
                  VALUES (:ticket_id, :usuario_id, :comentario, :es_solucion, NOW())";

        $this->db->query($query);
        $this->db->bind(':ticket_id', $ticket_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $this->db->bind(':comentario', $comentario);
        $this->db->bind(':es_solucion', $es_solucion ? 1 : 0);

        if ($this->db->execute()) {
            // Si es una solución, actualizar el ticket
            if ($es_solucion) {
                $this->updateEstado($ticket_id, 'completado', $usuario_id);
            }

            return [
                'success' => true,
                'message' => 'Comentario añadido exitosamente',
                'id' => $this->db->lastInsertId()
            ];
        }

        return ['success' => false, 'message' => 'Error al añadir el comentario'];
    }

    /**
     * Obtener comentarios del ticket
     */
    public function getComments($ticket_id) {
        $query = "SELECT tc.*,
                         u.nombre as usuario_nombre,
                         u.imagen_perfil as usuario_imagen
                  FROM tickets_comentarios tc
                  LEFT JOIN usuarios u ON tc.usuario_id = u.id
                  WHERE tc.ticket_id = :ticket_id
                  ORDER BY tc.fecha_creacion ASC";

        $this->db->query($query);
        $this->db->bind(':ticket_id', $ticket_id);
        return $this->db->fetchAll();
    }

    /**
     * Actualizar prioridad
     */
    public function updatePrioridad($ticket_id, $prioridad, $user_id) {
        if (!is_admin($user_id)) {
            return ['success' => false, 'message' => 'No tienes permiso para cambiar la prioridad'];
        }

        $valid_priorities = ['baja', 'media', 'alta'];

        if (!in_array($prioridad, $valid_priorities)) {
            return ['success' => false, 'message' => 'Prioridad inválida'];
        }

        $query = "UPDATE tickets SET prioridad = :prioridad WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':prioridad', $prioridad);
        $this->db->bind(':id', $ticket_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Prioridad actualizada'];
        }

        return ['success' => false, 'message' => 'Error al actualizar la prioridad'];
    }

    /**
     * Obtener estadísticas de tickets
     */
    public function getStats($user_id = null) {
        if ($user_id) {
            $query = "SELECT
                        COUNT(*) as total_tickets,
                        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                        COUNT(CASE WHEN estado = 'en_revision' THEN 1 END) as en_revision,
                        COUNT(CASE WHEN estado = 'en_desarrollo' THEN 1 END) as en_desarrollo,
                        COUNT(CASE WHEN estado = 'completado' THEN 1 END) as completados
                      FROM tickets
                      WHERE solicitante_id = :user_id";

            $this->db->query($query);
            $this->db->bind(':user_id', $user_id);
        } else {
            $query = "SELECT
                        COUNT(*) as total_tickets,
                        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                        COUNT(CASE WHEN estado = 'en_revision' THEN 1 END) as en_revision,
                        COUNT(CASE WHEN estado = 'en_desarrollo' THEN 1 END) as en_desarrollo,
                        COUNT(CASE WHEN estado = 'completado' THEN 1 END) as completados,
                        COUNT(CASE WHEN prioridad = 'alta' THEN 1 END) as alta_prioridad
                      FROM tickets";

            $this->db->query($query);
        }

        return $this->db->fetch();
    }

    /**
     * Votar por un ticket (para priorización comunitaria)
     */
    public function vote($ticket_id, $usuario_id) {
        // Verificar si ya votó
        $query = "SELECT COUNT(*) as count FROM tickets_votos
                  WHERE ticket_id = :ticket_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':ticket_id', $ticket_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->fetch();

        if ($result['count'] > 0) {
            // Remover voto
            $query = "DELETE FROM tickets_votos
                      WHERE ticket_id = :ticket_id AND usuario_id = :usuario_id";

            $this->db->query($query);
            $this->db->bind(':ticket_id', $ticket_id);
            $this->db->bind(':usuario_id', $usuario_id);
            $this->db->execute();

            return [
                'success' => true,
                'voted' => false,
                'total_votos' => $this->getVoteCount($ticket_id)
            ];
        } else {
            // Añadir voto
            $query = "INSERT INTO tickets_votos (ticket_id, usuario_id, fecha_voto)
                      VALUES (:ticket_id, :usuario_id, NOW())";

            $this->db->query($query);
            $this->db->bind(':ticket_id', $ticket_id);
            $this->db->bind(':usuario_id', $usuario_id);
            $this->db->execute();

            return [
                'success' => true,
                'voted' => true,
                'total_votos' => $this->getVoteCount($ticket_id)
            ];
        }
    }

    /**
     * Obtener conteo de votos
     */
    public function getVoteCount($ticket_id) {
        $query = "SELECT COUNT(*) as count FROM tickets_votos WHERE ticket_id = :ticket_id";

        $this->db->query($query);
        $this->db->bind(':ticket_id', $ticket_id);
        $result = $this->db->fetch();

        return (int) $result['count'];
    }

    /**
     * Verificar si el usuario votó
     */
    public function hasVoted($ticket_id, $usuario_id) {
        $query = "SELECT COUNT(*) as count FROM tickets_votos
                  WHERE ticket_id = :ticket_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':ticket_id', $ticket_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->fetch();

        return $result['count'] > 0;
    }

    /**
     * Notificar al solicitante sobre cambios
     */
    private function notifySolicitante($ticket_id, $nuevo_estado) {
        $ticket = $this->getById($ticket_id);

        $mensajes = [
            'en_revision' => 'Tu ticket "' . $ticket['titulo'] . '" está siendo revisado',
            'en_desarrollo' => 'Tu ticket "' . $ticket['titulo'] . '" está en desarrollo',
            'completado' => 'Tu ticket "' . $ticket['titulo'] . '" ha sido completado',
            'rechazado' => 'Tu ticket "' . $ticket['titulo'] . '" ha sido rechazado',
            'cancelado' => 'Tu ticket "' . $ticket['titulo'] . '" ha sido cancelado'
        ];

        if (isset($mensajes[$nuevo_estado])) {
            $query = "INSERT INTO notificaciones (usuario_id, tipo, mensaje, referencia_id, referencia_tipo, fecha_creacion)
                      VALUES (:usuario_id, 'ticket', :mensaje, :ticket_id, 'ticket', NOW())";

            $this->db->query($query);
            $this->db->bind(':usuario_id', $ticket['solicitante_id']);
            $this->db->bind(':mensaje', $mensajes[$nuevo_estado]);
            $this->db->bind(':ticket_id', $ticket_id);
            $this->db->execute();
        }
    }

    /**
     * Notificar al asignado
     */
    private function notifyAsignado($ticket_id, $asignado_id) {
        $ticket = $this->getById($ticket_id);

        $mensaje = 'Se te ha asignado el ticket "' . $ticket['titulo'] . '"';

        $query = "INSERT INTO notificaciones (usuario_id, tipo, mensaje, referencia_id, referencia_tipo, fecha_creacion)
                  VALUES (:usuario_id, 'ticket', :mensaje, :ticket_id, 'ticket', NOW())";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $asignado_id);
        $this->db->bind(':mensaje', $mensaje);
        $this->db->bind(':ticket_id', $ticket_id);
        $this->db->execute();
    }

    /**
     * Buscar tickets
     */
    public function search($search_term, $filters = []) {
        $query = "SELECT t.*,
                         u.nombre as solicitante_nombre,
                         g.nombre as grupo_nombre,
                         a.nombre as asignado_nombre,
                         COUNT(DISTINCT tc.id) as total_comentarios
                  FROM tickets t
                  LEFT JOIN usuarios u ON t.solicitante_id = u.id
                  LEFT JOIN grupos g ON t.grupo_id = g.id
                  LEFT JOIN usuarios a ON t.asignado_a = a.id
                  LEFT JOIN tickets_comentarios tc ON t.id = tc.ticket_id
                  WHERE (t.titulo LIKE :search OR t.descripcion LIKE :search)";

        if (isset($filters['estado'])) {
            $query .= " AND t.estado = :estado";
        }

        if (isset($filters['tipo'])) {
            $query .= " AND t.tipo = :tipo";
        }

        $query .= " GROUP BY t.id ORDER BY t.fecha_creacion DESC";

        $this->db->query($query);
        $this->db->bind(':search', '%' . $search_term . '%');

        if (isset($filters['estado'])) {
            $this->db->bind(':estado', $filters['estado']);
        }
        if (isset($filters['tipo'])) {
            $this->db->bind(':tipo', $filters['tipo']);
        }

        return $this->db->fetchAll();
    }
}
