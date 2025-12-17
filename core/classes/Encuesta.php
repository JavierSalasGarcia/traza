<?php

class Encuesta {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crear una nueva encuesta
     */
    public function create($data) {
        $query = "INSERT INTO encuestas
                  (titulo, descripcion, autor_id, grupo_id, fecha_inicio, fecha_fin, anonima, multiple_respuestas, fecha_creacion)
                  VALUES (:titulo, :descripcion, :autor_id, :grupo_id, :fecha_inicio, :fecha_fin, :anonima, :multiple_respuestas, NOW())";

        $this->db->query($query);
        $this->db->bind(':titulo', $data['titulo']);
        $this->db->bind(':descripcion', $data['descripcion']);
        $this->db->bind(':autor_id', $data['autor_id']);
        $this->db->bind(':grupo_id', $data['grupo_id'] ?? null);
        $this->db->bind(':fecha_inicio', $data['fecha_inicio'] ?? date('Y-m-d H:i:s'));
        $this->db->bind(':fecha_fin', $data['fecha_fin'] ?? null);
        $this->db->bind(':anonima', $data['anonima'] ?? 0);
        $this->db->bind(':multiple_respuestas', $data['multiple_respuestas'] ?? 0);

        if ($this->db->execute()) {
            $encuesta_id = $this->db->lastInsertId();

            // Insertar opciones
            if (isset($data['opciones']) && is_array($data['opciones'])) {
                foreach ($data['opciones'] as $index => $opcion_texto) {
                    if (!empty(trim($opcion_texto))) {
                        $this->addOpcion($encuesta_id, trim($opcion_texto), $index);
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Encuesta creada exitosamente',
                'id' => $encuesta_id
            ];
        }

        return ['success' => false, 'message' => 'Error al crear la encuesta'];
    }

    /**
     * Añadir opción a la encuesta
     */
    private function addOpcion($encuesta_id, $texto, $orden = 0) {
        $query = "INSERT INTO encuestas_opciones (encuesta_id, texto, orden)
                  VALUES (:encuesta_id, :texto, :orden)";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        $this->db->bind(':texto', $texto);
        $this->db->bind(':orden', $orden);
        return $this->db->execute();
    }

    /**
     * Obtener encuesta por ID con opciones y resultados
     */
    public function getById($id) {
        $query = "SELECT e.*,
                         u.nombre as autor_nombre,
                         u.imagen_perfil as autor_imagen,
                         g.nombre as grupo_nombre,
                         COUNT(DISTINCT ev.id) as total_votos
                  FROM encuestas e
                  LEFT JOIN usuarios u ON e.autor_id = u.id
                  LEFT JOIN grupos g ON e.grupo_id = g.id
                  LEFT JOIN encuestas_votos ev ON e.id = ev.encuesta_id
                  WHERE e.id = :id
                  GROUP BY e.id";

        $this->db->query($query);
        $this->db->bind(':id', $id);
        $encuesta = $this->db->fetch();

        if ($encuesta) {
            $encuesta['opciones'] = $this->getOpciones($id);
        }

        return $encuesta;
    }

    /**
     * Obtener opciones de una encuesta con conteo de votos
     */
    public function getOpciones($encuesta_id) {
        $query = "SELECT o.*,
                         COUNT(DISTINCT ev.id) as total_votos
                  FROM encuestas_opciones o
                  LEFT JOIN encuestas_votos ev ON o.id = ev.opcion_id
                  WHERE o.encuesta_id = :encuesta_id
                  GROUP BY o.id
                  ORDER BY o.orden ASC";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        return $this->db->fetchAll();
    }

    /**
     * Obtener encuestas activas
     */
    public function getActivas($grupo_id = null, $limit = 20) {
        $now = date('Y-m-d H:i:s');

        $query = "SELECT e.*,
                         u.nombre as autor_nombre,
                         g.nombre as grupo_nombre,
                         COUNT(DISTINCT ev.id) as total_votos
                  FROM encuestas e
                  LEFT JOIN usuarios u ON e.autor_id = u.id
                  LEFT JOIN grupos g ON e.grupo_id = g.id
                  LEFT JOIN encuestas_votos ev ON e.id = ev.encuesta_id
                  WHERE e.fecha_inicio <= :now
                  AND (e.fecha_fin IS NULL OR e.fecha_fin >= :now)";

        if ($grupo_id) {
            $query .= " AND (e.grupo_id = :grupo_id OR e.grupo_id IS NULL)";
        }

        $query .= " GROUP BY e.id ORDER BY e.fecha_creacion DESC LIMIT :limit";

        $this->db->query($query);
        $this->db->bind(':now', $now);

        if ($grupo_id) {
            $this->db->bind(':grupo_id', $grupo_id);
        }

        $this->db->bind(':limit', $limit, PDO::PARAM_INT);
        return $this->db->fetchAll();
    }

    /**
     * Votar en una encuesta
     */
    public function vote($encuesta_id, $opciones_ids, $usuario_id) {
        // Verificar que la encuesta existe y está activa
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return ['success' => false, 'message' => 'Encuesta no encontrada'];
        }

        $now = date('Y-m-d H:i:s');

        if ($encuesta['fecha_inicio'] > $now) {
            return ['success' => false, 'message' => 'La encuesta aún no ha iniciado'];
        }

        if ($encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $now) {
            return ['success' => false, 'message' => 'La encuesta ha finalizado'];
        }

        // Verificar si ya votó
        if ($this->hasVoted($encuesta_id, $usuario_id)) {
            return ['success' => false, 'message' => 'Ya has votado en esta encuesta'];
        }

        // Validar opciones
        if (!is_array($opciones_ids)) {
            $opciones_ids = [$opciones_ids];
        }

        // Si no permite múltiples respuestas, solo permitir una opción
        if (!$encuesta['multiple_respuestas'] && count($opciones_ids) > 1) {
            return ['success' => false, 'message' => 'Esta encuesta solo permite una respuesta'];
        }

        // Insertar votos
        $this->db->query("START TRANSACTION");

        try {
            foreach ($opciones_ids as $opcion_id) {
                $query = "INSERT INTO encuestas_votos (encuesta_id, opcion_id, usuario_id, fecha_voto)
                          VALUES (:encuesta_id, :opcion_id, :usuario_id, NOW())";

                $this->db->query($query);
                $this->db->bind(':encuesta_id', $encuesta_id);
                $this->db->bind(':opcion_id', $opcion_id);
                $this->db->bind(':usuario_id', $usuario_id);
                $this->db->execute();
            }

            $this->db->query("COMMIT");

            return [
                'success' => true,
                'message' => 'Voto registrado exitosamente',
                'total_votos' => $this->getTotalVotos($encuesta_id)
            ];
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Error al registrar el voto'];
        }
    }

    /**
     * Verificar si el usuario ya votó
     */
    public function hasVoted($encuesta_id, $usuario_id) {
        $query = "SELECT COUNT(*) as count FROM encuestas_votos
                  WHERE encuesta_id = :encuesta_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->fetch();

        return $result['count'] > 0;
    }

    /**
     * Obtener total de votos de una encuesta
     */
    public function getTotalVotos($encuesta_id) {
        $query = "SELECT COUNT(DISTINCT usuario_id) as count FROM encuestas_votos
                  WHERE encuesta_id = :encuesta_id";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        $result = $this->db->fetch();

        return (int) $result['count'];
    }

    /**
     * Obtener resultados de la encuesta
     */
    public function getResultados($encuesta_id) {
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return null;
        }

        $total_votos = $encuesta['total_votos'];
        $opciones = $encuesta['opciones'];

        // Calcular porcentajes
        foreach ($opciones as &$opcion) {
            $opcion['porcentaje'] = $total_votos > 0
                ? ($opcion['total_votos'] / $total_votos) * 100
                : 0;
        }

        return [
            'encuesta' => $encuesta,
            'opciones' => $opciones,
            'total_votos' => $total_votos
        ];
    }

    /**
     * Obtener votantes (solo si no es anónima)
     */
    public function getVotantes($encuesta_id, $opcion_id = null) {
        $encuesta = $this->getById($encuesta_id);

        if ($encuesta['anonima']) {
            return [];
        }

        $query = "SELECT ev.*, u.nombre, u.imagen_perfil, eo.texto as opcion_texto
                  FROM encuestas_votos ev
                  INNER JOIN usuarios u ON ev.usuario_id = u.id
                  INNER JOIN encuestas_opciones eo ON ev.opcion_id = eo.id
                  WHERE ev.encuesta_id = :encuesta_id";

        if ($opcion_id) {
            $query .= " AND ev.opcion_id = :opcion_id";
        }

        $query .= " ORDER BY ev.fecha_voto DESC";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);

        if ($opcion_id) {
            $this->db->bind(':opcion_id', $opcion_id);
        }

        return $this->db->fetchAll();
    }

    /**
     * Cerrar encuesta manualmente
     */
    public function cerrar($encuesta_id, $usuario_id) {
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return ['success' => false, 'message' => 'Encuesta no encontrada'];
        }

        // Solo el autor o admin pueden cerrar
        if ($encuesta['autor_id'] != $usuario_id && !is_admin($usuario_id)) {
            return ['success' => false, 'message' => 'No tienes permiso para cerrar esta encuesta'];
        }

        $query = "UPDATE encuestas SET fecha_fin = NOW() WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':id', $encuesta_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Encuesta cerrada exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al cerrar la encuesta'];
    }

    /**
     * Obtener encuestas del usuario
     */
    public function getUserEncuestas($usuario_id) {
        $query = "SELECT e.*,
                         g.nombre as grupo_nombre,
                         COUNT(DISTINCT ev.id) as total_votos
                  FROM encuestas e
                  LEFT JOIN grupos g ON e.grupo_id = g.id
                  LEFT JOIN encuestas_votos ev ON e.id = ev.encuesta_id
                  WHERE e.autor_id = :usuario_id
                  GROUP BY e.id
                  ORDER BY e.fecha_creacion DESC";

        $this->db->query($query);
        $this->db->bind(':usuario_id', $usuario_id);
        return $this->db->fetchAll();
    }

    /**
     * Obtener estadísticas de encuestas
     */
    public function getStats($usuario_id = null) {
        if ($usuario_id) {
            $query = "SELECT
                        COUNT(*) as total_encuestas,
                        COUNT(CASE WHEN fecha_fin IS NULL OR fecha_fin >= NOW() THEN 1 END) as activas,
                        COUNT(CASE WHEN fecha_fin IS NOT NULL AND fecha_fin < NOW() THEN 1 END) as finalizadas
                      FROM encuestas
                      WHERE autor_id = :usuario_id";

            $this->db->query($query);
            $this->db->bind(':usuario_id', $usuario_id);
        } else {
            $query = "SELECT
                        COUNT(*) as total_encuestas,
                        COUNT(CASE WHEN fecha_fin IS NULL OR fecha_fin >= NOW() THEN 1 END) as activas,
                        COUNT(CASE WHEN fecha_fin IS NOT NULL AND fecha_fin < NOW() THEN 1 END) as finalizadas
                      FROM encuestas";

            $this->db->query($query);
        }

        return $this->db->fetch();
    }

    /**
     * Eliminar encuesta (solo si no tiene votos)
     */
    public function delete($encuesta_id, $usuario_id) {
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return ['success' => false, 'message' => 'Encuesta no encontrada'];
        }

        if ($encuesta['autor_id'] != $usuario_id && !is_admin($usuario_id)) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar esta encuesta'];
        }

        if ($encuesta['total_votos'] > 0) {
            return ['success' => false, 'message' => 'No se puede eliminar una encuesta con votos. Puedes cerrarla en su lugar.'];
        }

        $this->db->query("START TRANSACTION");

        try {
            // Eliminar opciones
            $this->db->query("DELETE FROM encuestas_opciones WHERE encuesta_id = :id");
            $this->db->bind(':id', $encuesta_id);
            $this->db->execute();

            // Eliminar encuesta
            $this->db->query("DELETE FROM encuestas WHERE id = :id");
            $this->db->bind(':id', $encuesta_id);
            $this->db->execute();

            $this->db->query("COMMIT");

            return ['success' => true, 'message' => 'Encuesta eliminada exitosamente'];
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Error al eliminar la encuesta'];
        }
    }

    /**
     * Buscar encuestas
     */
    public function search($search_term, $filters = []) {
        $query = "SELECT e.*,
                         u.nombre as autor_nombre,
                         g.nombre as grupo_nombre,
                         COUNT(DISTINCT ev.id) as total_votos
                  FROM encuestas e
                  LEFT JOIN usuarios u ON e.autor_id = u.id
                  LEFT JOIN grupos g ON e.grupo_id = g.id
                  LEFT JOIN encuestas_votos ev ON e.id = ev.encuesta_id
                  WHERE (e.titulo LIKE :search OR e.descripcion LIKE :search)";

        if (isset($filters['grupo_id'])) {
            $query .= " AND (e.grupo_id = :grupo_id OR e.grupo_id IS NULL)";
        }

        if (isset($filters['activas']) && $filters['activas']) {
            $now = date('Y-m-d H:i:s');
            $query .= " AND e.fecha_inicio <= :now AND (e.fecha_fin IS NULL OR e.fecha_fin >= :now2)";
        }

        $query .= " GROUP BY e.id ORDER BY e.fecha_creacion DESC";

        $this->db->query($query);
        $this->db->bind(':search', '%' . $search_term . '%');

        if (isset($filters['grupo_id'])) {
            $this->db->bind(':grupo_id', $filters['grupo_id']);
        }

        if (isset($filters['activas']) && $filters['activas']) {
            $now = date('Y-m-d H:i:s');
            $this->db->bind(':now', $now);
            $this->db->bind(':now2', $now);
        }

        return $this->db->fetchAll();
    }

    /**
     * Verificar si puede editar
     */
    public function canEdit($encuesta_id, $usuario_id) {
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return false;
        }

        // Solo el autor puede editar y solo si no tiene votos
        return $encuesta['autor_id'] == $usuario_id && $encuesta['total_votos'] == 0;
    }
}
