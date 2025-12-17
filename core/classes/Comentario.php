<?php

class Comentario {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crear un nuevo comentario
     */
    public function create($data) {
        $query = "INSERT INTO comentarios
                  (contenido, usuario_id, referencia_tipo, referencia_id, es_anonimo, fecha_creacion)
                  VALUES (:contenido, :usuario_id, :referencia_tipo, :referencia_id, :es_anonimo, NOW())";

        $this->db->query($query);
        $this->db->bind(':contenido', $data['contenido']);
        $this->db->bind(':usuario_id', $data['usuario_id']);
        $this->db->bind(':referencia_tipo', $data['referencia_tipo']);
        $this->db->bind(':referencia_id', $data['referencia_id']);
        $this->db->bind(':es_anonimo', $data['es_anonimo'] ?? 0);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Comentario publicado exitosamente',
                'id' => $this->db->lastInsertId()
            ];
        }

        return ['success' => false, 'message' => 'Error al publicar el comentario'];
    }

    /**
     * Obtener comentarios por referencia
     */
    public function getByReference($referencia_tipo, $referencia_id, $include_deleted = false) {
        $query = "SELECT c.*,
                         u.nombre as usuario_nombre,
                         u.imagen_perfil as usuario_imagen,
                         COUNT(DISTINCT cl.usuario_id) as total_likes,
                         (SELECT COUNT(*) FROM comentarios WHERE comentario_padre_id = c.id AND eliminado = 0) as total_respuestas
                  FROM comentarios c
                  LEFT JOIN usuarios u ON c.usuario_id = u.id
                  LEFT JOIN comentarios_likes cl ON c.id = cl.comentario_id
                  WHERE c.referencia_tipo = :referencia_tipo
                  AND c.referencia_id = :referencia_id
                  AND c.comentario_padre_id IS NULL";

        if (!$include_deleted) {
            $query .= " AND c.eliminado = 0";
        }

        $query .= " GROUP BY c.id ORDER BY c.fecha_creacion DESC";

        $this->db->query($query);
        $this->db->bind(':referencia_tipo', $referencia_tipo);
        $this->db->bind(':referencia_id', $referencia_id);

        $comentarios = $this->db->fetchAll();

        // Procesar comentarios anónimos
        foreach ($comentarios as &$comentario) {
            if ($comentario['es_anonimo']) {
                $comentario['usuario_nombre'] = 'Anónimo';
                $comentario['usuario_imagen'] = null;
            }
        }

        return $comentarios;
    }

    /**
     * Obtener respuestas de un comentario
     */
    public function getReplies($comentario_id, $include_deleted = false) {
        $query = "SELECT c.*,
                         u.nombre as usuario_nombre,
                         u.imagen_perfil as usuario_imagen,
                         COUNT(DISTINCT cl.usuario_id) as total_likes
                  FROM comentarios c
                  LEFT JOIN usuarios u ON c.usuario_id = u.id
                  LEFT JOIN comentarios_likes cl ON c.id = cl.comentario_id
                  WHERE c.comentario_padre_id = :comentario_id";

        if (!$include_deleted) {
            $query .= " AND c.eliminado = 0";
        }

        $query .= " GROUP BY c.id ORDER BY c.fecha_creacion ASC";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);

        $respuestas = $this->db->fetchAll();

        // Procesar comentarios anónimos
        foreach ($respuestas as &$respuesta) {
            if ($respuesta['es_anonimo']) {
                $respuesta['usuario_nombre'] = 'Anónimo';
                $respuesta['usuario_imagen'] = null;
            }
        }

        return $respuestas;
    }

    /**
     * Obtener comentario por ID con información completa
     */
    public function getById($id, $show_real_user = false) {
        $query = "SELECT c.*,
                         u.nombre as usuario_nombre,
                         u.imagen_perfil as usuario_imagen,
                         u.email as usuario_email,
                         COUNT(DISTINCT cl.usuario_id) as total_likes
                  FROM comentarios c
                  LEFT JOIN usuarios u ON c.usuario_id = u.id
                  LEFT JOIN comentarios_likes cl ON c.id = cl.comentario_id
                  WHERE c.id = :id
                  GROUP BY c.id";

        $this->db->query($query);
        $this->db->bind(':id', $id);
        $comentario = $this->db->fetch();

        if ($comentario && $comentario['es_anonimo'] && !$show_real_user) {
            $comentario['usuario_nombre'] = 'Anónimo';
            $comentario['usuario_imagen'] = null;
            $comentario['usuario_email'] = null;
        }

        return $comentario;
    }

    /**
     * Dar like a un comentario
     */
    public function like($comentario_id, $usuario_id) {
        // Verificar que el comentario existe
        $comentario = $this->getById($comentario_id);

        if (!$comentario) {
            return ['success' => false, 'message' => 'Comentario no encontrado'];
        }

        // Verificar si ya dio like
        if ($this->hasLiked($comentario_id, $usuario_id)) {
            return ['success' => false, 'message' => 'Ya diste like a este comentario'];
        }

        $query = "INSERT INTO comentarios_likes (comentario_id, usuario_id, fecha_like)
                  VALUES (:comentario_id, :usuario_id, NOW())";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);
        $this->db->bind(':usuario_id', $usuario_id);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Like registrado',
                'total_likes' => $this->getLikeCount($comentario_id)
            ];
        }

        return ['success' => false, 'message' => 'Error al dar like'];
    }

    /**
     * Quitar like de un comentario
     */
    public function unlike($comentario_id, $usuario_id) {
        $query = "DELETE FROM comentarios_likes
                  WHERE comentario_id = :comentario_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);
        $this->db->bind(':usuario_id', $usuario_id);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Like retirado',
                'total_likes' => $this->getLikeCount($comentario_id)
            ];
        }

        return ['success' => false, 'message' => 'Error al quitar like'];
    }

    /**
     * Verificar si un usuario dio like a un comentario
     */
    public function hasLiked($comentario_id, $usuario_id) {
        $query = "SELECT COUNT(*) as count FROM comentarios_likes
                  WHERE comentario_id = :comentario_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->fetch();

        return $result['count'] > 0;
    }

    /**
     * Obtener conteo de likes
     */
    public function getLikeCount($comentario_id) {
        $query = "SELECT COUNT(DISTINCT usuario_id) as count FROM comentarios_likes
                  WHERE comentario_id = :comentario_id";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);
        $result = $this->db->fetch();

        return (int) $result['count'];
    }

    /**
     * Eliminar comentario (soft delete)
     */
    public function delete($comentario_id, $usuario_id) {
        $comentario = $this->getById($comentario_id, true); // Mostrar usuario real

        if (!$comentario) {
            return ['success' => false, 'message' => 'Comentario no encontrado'];
        }

        // Verificar permisos: solo el autor o admin pueden eliminar
        if ($comentario['usuario_id'] != $usuario_id && !is_admin()) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar este comentario'];
        }

        $query = "UPDATE comentarios
                  SET eliminado = 1,
                      fecha_eliminacion = NOW()
                  WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':id', $comentario_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Comentario eliminado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al eliminar el comentario'];
    }

    /**
     * Editar comentario
     */
    public function update($comentario_id, $nuevo_contenido, $usuario_id) {
        $comentario = $this->getById($comentario_id, true); // Mostrar usuario real

        if (!$comentario) {
            return ['success' => false, 'message' => 'Comentario no encontrado'];
        }

        // Solo el autor puede editar
        if ($comentario['usuario_id'] != $usuario_id) {
            return ['success' => false, 'message' => 'No tienes permiso para editar este comentario'];
        }

        // No permitir editar comentarios muy antiguos (más de 24 horas)
        $fecha_limite = strtotime($comentario['fecha_creacion'] . ' + 24 hours');
        if (time() > $fecha_limite) {
            return ['success' => false, 'message' => 'No puedes editar comentarios después de 24 horas'];
        }

        $query = "UPDATE comentarios
                  SET contenido = :contenido,
                      editado = 1,
                      fecha_edicion = NOW()
                  WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':contenido', $nuevo_contenido);
        $this->db->bind(':id', $comentario_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Comentario actualizado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al actualizar el comentario'];
    }

    /**
     * Obtener comentarios del usuario
     */
    public function getUserComments($usuario_id) {
        $query = "SELECT c.*,
                         COUNT(DISTINCT cl.usuario_id) as total_likes,
                         COUNT(DISTINCT r.id) as total_respuestas
                  FROM comentarios c
                  LEFT JOIN comentarios_likes cl ON c.id = cl.comentario_id
                  LEFT JOIN comentarios r ON c.id = r.comentario_padre_id AND r.eliminado = 0
                  WHERE c.usuario_id = :usuario_id
                  AND c.eliminado = 0
                  GROUP BY c.id
                  ORDER BY c.fecha_creacion DESC";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $usuario_id);
        return $this->db->fetchAll();
    }

    /**
     * Obtener estadísticas de comentarios
     */
    public function getStats($usuario_id = null) {
        if ($usuario_id) {
            $query = "SELECT
                        COUNT(*) as total_comentarios,
                        COUNT(CASE WHEN es_anonimo = 1 THEN 1 END) as comentarios_anonimos,
                        COUNT(CASE WHEN eliminado = 1 THEN 1 END) as comentarios_eliminados
                      FROM comentarios
                      WHERE usuario_id = :usuario_id";

            $this->db->query($query);
            $this->db->bind(':usuario_id', $usuario_id);
        } else {
            $query = "SELECT
                        COUNT(*) as total_comentarios,
                        COUNT(CASE WHEN es_anonimo = 1 THEN 1 END) as comentarios_anonimos,
                        COUNT(CASE WHEN eliminado = 1 THEN 1 END) as comentarios_eliminados,
                        COUNT(DISTINCT usuario_id) as usuarios_activos
                      FROM comentarios";

            $this->db->query($query);
        }

        return $this->db->fetch();
    }

    /**
     * Obtener comentarios recientes (para moderación)
     */
    public function getRecent($limit = 50, $include_anonymous = true) {
        $query = "SELECT c.*,
                         u.nombre as usuario_nombre,
                         u.imagen_perfil as usuario_imagen,
                         u.email as usuario_email,
                         COUNT(DISTINCT cl.usuario_id) as total_likes
                  FROM comentarios c
                  LEFT JOIN usuarios u ON c.usuario_id = u.id
                  LEFT JOIN comentarios_likes cl ON c.id = cl.comentario_id
                  WHERE c.eliminado = 0";

        if (!$include_anonymous) {
            $query .= " AND c.es_anonimo = 0";
        }

        $query .= " GROUP BY c.id ORDER BY c.fecha_creacion DESC LIMIT :limit";

        $this->db->query($query);
        $this->db->bind(':limit', $limit);

        return $this->db->fetchAll();
    }

    /**
     * Reportar comentario
     */
    public function report($comentario_id, $usuario_id, $razon) {
        // Verificar que el comentario existe
        $comentario = $this->getById($comentario_id);

        if (!$comentario) {
            return ['success' => false, 'message' => 'Comentario no encontrado'];
        }

        // Verificar si ya reportó este comentario
        $query = "SELECT COUNT(*) as count FROM comentarios_reportes
                  WHERE comentario_id = :comentario_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->fetch();

        if ($result['count'] > 0) {
            return ['success' => false, 'message' => 'Ya reportaste este comentario'];
        }

        // Insertar reporte
        $query = "INSERT INTO comentarios_reportes (comentario_id, usuario_id, razon, fecha_reporte)
                  VALUES (:comentario_id, :usuario_id, :razon, NOW())";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $this->db->bind(':razon', $razon);

        if ($this->db->execute()) {
            // Verificar si alcanzó umbral de reportes (por ejemplo, 3 reportes)
            $total_reportes = $this->getReportCount($comentario_id);

            if ($total_reportes >= 3) {
                // Auto-eliminar comentario por múltiples reportes
                $this->delete($comentario_id, $usuario_id);
                return [
                    'success' => true,
                    'message' => 'Comentario reportado y eliminado automáticamente por múltiples reportes'
                ];
            }

            return [
                'success' => true,
                'message' => 'Reporte enviado exitosamente'
            ];
        }

        return ['success' => false, 'message' => 'Error al enviar el reporte'];
    }

    /**
     * Obtener conteo de reportes
     */
    public function getReportCount($comentario_id) {
        $query = "SELECT COUNT(DISTINCT usuario_id) as count FROM comentarios_reportes
                  WHERE comentario_id = :comentario_id";

        $this->db->query($query);
        $this->db->bind(':comentario_id', $comentario_id);
        $result = $this->db->fetch();

        return (int) $result['count'];
    }

    /**
     * Obtener comentarios reportados (para moderación)
     */
    public function getReported() {
        $query = "SELECT c.*,
                         u.nombre as usuario_nombre,
                         u.email as usuario_email,
                         COUNT(DISTINCT cr.usuario_id) as total_reportes
                  FROM comentarios c
                  INNER JOIN comentarios_reportes cr ON c.id = cr.comentario_id
                  LEFT JOIN usuarios u ON c.usuario_id = u.id
                  WHERE c.eliminado = 0
                  GROUP BY c.id
                  HAVING total_reportes > 0
                  ORDER BY total_reportes DESC, c.fecha_creacion DESC";

        $this->db->query($query);
        return $this->db->fetchAll();
    }

    /**
     * Obtener conteo de comentarios por referencia
     */
    public function getCountByReference($referencia_tipo, $referencia_id) {
        $query = "SELECT COUNT(*) as count FROM comentarios
                  WHERE referencia_tipo = :referencia_tipo
                  AND referencia_id = :referencia_id
                  AND eliminado = 0";

        $this->db->query($query);
        $this->db->bind(':referencia_tipo', $referencia_tipo);
        $this->db->bind(':referencia_id', $referencia_id);
        $result = $this->db->fetch();

        return (int) $result['count'];
    }
}
