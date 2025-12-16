<?php

class Propuesta {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crear una nueva propuesta
     */
    public function create($data) {
        $query = "INSERT INTO propuestas
                  (titulo, descripcion, categoria, autor_id, grupo_id, umbral_firmas, fecha_creacion, estado)
                  VALUES (:titulo, :descripcion, :categoria, :autor_id, :grupo_id, :umbral_firmas, NOW(), 'votacion')";

        $this->db->query($query);
        $this->db->bind(':titulo', $data['titulo']);
        $this->db->bind(':descripcion', $data['descripcion']);
        $this->db->bind(':categoria', $data['categoria']);
        $this->db->bind(':autor_id', $data['autor_id']);
        $this->db->bind(':grupo_id', $data['grupo_id'] ?? null);
        $this->db->bind(':umbral_firmas', $data['umbral_firmas'] ?? get_config('umbral_firmas_default', 200));

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Propuesta creada exitosamente',
                'id' => $this->db->lastInsertId()
            ];
        }

        return ['success' => false, 'message' => 'Error al crear la propuesta'];
    }

    /**
     * Obtener propuesta por ID con información completa
     */
    public function getById($id) {
        $query = "SELECT p.*,
                         u.nombre as autor_nombre,
                         u.imagen_perfil as autor_imagen,
                         g.nombre as grupo_nombre,
                         c.nombre as comision_nombre,
                         COUNT(DISTINCT pf.usuario_id) as total_firmas
                  FROM propuestas p
                  LEFT JOIN usuarios u ON p.autor_id = u.id
                  LEFT JOIN grupos g ON p.grupo_id = g.id
                  LEFT JOIN comisiones c ON p.comision_id = c.id
                  LEFT JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
                  WHERE p.id = :id
                  GROUP BY p.id";

        $this->db->query($query);
        $this->db->bind(':id', $id);
        return $this->db->fetch();
    }

    /**
     * Obtener propuestas por estado
     */
    public function getByEstado($estado = null, $grupo_id = null, $limit = null) {
        $query = "SELECT p.*,
                         u.nombre as autor_nombre,
                         g.nombre as grupo_nombre,
                         COUNT(DISTINCT pf.usuario_id) as total_firmas
                  FROM propuestas p
                  LEFT JOIN usuarios u ON p.autor_id = u.id
                  LEFT JOIN grupos g ON p.grupo_id = g.id
                  LEFT JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
                  WHERE 1=1";

        if ($estado) {
            $query .= " AND p.estado = :estado";
        }

        if ($grupo_id) {
            $query .= " AND (p.grupo_id = :grupo_id OR p.grupo_id IS NULL)";
        }

        $query .= " GROUP BY p.id ORDER BY p.fecha_creacion DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
        }

        $this->db->query($query);

        if ($estado) {
            $this->db->bind(':estado', $estado);
        }
        if ($grupo_id) {
            $this->db->bind(':grupo_id', $grupo_id);
        }
        if ($limit) {
            $this->db->bind(':limit', $limit);
        }

        return $this->db->fetchAll();
    }

    /**
     * Obtener propuestas del usuario (creadas)
     */
    public function getUserProposals($user_id) {
        $query = "SELECT p.*,
                         g.nombre as grupo_nombre,
                         COUNT(DISTINCT pf.usuario_id) as total_firmas
                  FROM propuestas p
                  LEFT JOIN grupos g ON p.grupo_id = g.id
                  LEFT JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
                  WHERE p.autor_id = :user_id
                  GROUP BY p.id
                  ORDER BY p.fecha_creacion DESC";

        $this->db->query($query);
        $this->db->bind(':user_id', $user_id);
        return $this->db->fetchAll();
    }

    /**
     * Obtener propuestas firmadas por el usuario
     */
    public function getSignedByUser($user_id) {
        $query = "SELECT p.*,
                         u.nombre as autor_nombre,
                         g.nombre as grupo_nombre,
                         pf.es_anonima,
                         pf.fecha_firma,
                         COUNT(DISTINCT pf2.usuario_id) as total_firmas
                  FROM propuestas p
                  INNER JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
                  LEFT JOIN usuarios u ON p.autor_id = u.id
                  LEFT JOIN grupos g ON p.grupo_id = g.id
                  LEFT JOIN propuestas_firmas pf2 ON p.id = pf2.propuesta_id
                  WHERE pf.usuario_id = :user_id
                  GROUP BY p.id
                  ORDER BY pf.fecha_firma DESC";

        $this->db->query($query);
        $this->db->bind(':user_id', $user_id);
        return $this->db->fetchAll();
    }

    /**
     * Firmar una propuesta
     */
    public function sign($propuesta_id, $usuario_id, $es_anonima = false) {
        // Verificar que la propuesta existe y está en votación
        $propuesta = $this->getById($propuesta_id);

        if (!$propuesta) {
            return ['success' => false, 'message' => 'Propuesta no encontrada'];
        }

        if ($propuesta['estado'] !== 'votacion') {
            return ['success' => false, 'message' => 'Esta propuesta ya no acepta firmas'];
        }

        // Verificar si ya firmó
        if ($this->hasSigned($propuesta_id, $usuario_id)) {
            return ['success' => false, 'message' => 'Ya has firmado esta propuesta'];
        }

        // Insertar firma
        $query = "INSERT INTO propuestas_firmas (propuesta_id, usuario_id, es_anonima, fecha_firma)
                  VALUES (:propuesta_id, :usuario_id, :es_anonima, NOW())";

        $this->db->query($query);
        $this->db->bind(':propuesta_id', $propuesta_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $this->db->bind(':es_anonima', $es_anonima ? 1 : 0);

        if ($this->db->execute()) {
            // Verificar si alcanzó el umbral
            $firma_count = $this->getSignatureCount($propuesta_id);
            $threshold_met = $firma_count >= $propuesta['umbral_firmas'];

            return [
                'success' => true,
                'message' => 'Firma registrada exitosamente',
                'total_firmas' => $firma_count,
                'threshold_met' => $threshold_met
            ];
        }

        return ['success' => false, 'message' => 'Error al registrar la firma'];
    }

    /**
     * Remover firma de una propuesta
     */
    public function unsign($propuesta_id, $usuario_id) {
        $propuesta = $this->getById($propuesta_id);

        if ($propuesta['estado'] !== 'votacion') {
            return ['success' => false, 'message' => 'No puedes retirar tu firma de esta propuesta'];
        }

        $query = "DELETE FROM propuestas_firmas
                  WHERE propuesta_id = :propuesta_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':propuesta_id', $propuesta_id);
        $this->db->bind(':usuario_id', $usuario_id);

        if ($this->db->execute()) {
            return [
                'success' => true,
                'message' => 'Firma retirada exitosamente',
                'total_firmas' => $this->getSignatureCount($propuesta_id)
            ];
        }

        return ['success' => false, 'message' => 'Error al retirar la firma'];
    }

    /**
     * Verificar si un usuario firmó una propuesta
     */
    public function hasSigned($propuesta_id, $usuario_id) {
        $query = "SELECT COUNT(*) as count FROM propuestas_firmas
                  WHERE propuesta_id = :propuesta_id AND usuario_id = :usuario_id";

        $this->db->query($query);
        $this->db->bind(':propuesta_id', $propuesta_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->fetch();

        return $result['count'] > 0;
    }

    /**
     * Obtener conteo de firmas
     */
    public function getSignatureCount($propuesta_id) {
        $query = "SELECT COUNT(DISTINCT usuario_id) as count FROM propuestas_firmas
                  WHERE propuesta_id = :propuesta_id";

        $this->db->query($query);
        $this->db->bind(':propuesta_id', $propuesta_id);
        $result = $this->db->fetch();

        return (int) $result['count'];
    }

    /**
     * Obtener firmantes (solo los no anónimos)
     */
    public function getSigners($propuesta_id, $include_anonymous = false) {
        $query = "SELECT pf.*, u.nombre, u.imagen_perfil, u.email
                  FROM propuestas_firmas pf
                  LEFT JOIN usuarios u ON pf.usuario_id = u.id
                  WHERE pf.propuesta_id = :propuesta_id";

        if (!$include_anonymous) {
            $query .= " AND pf.es_anonima = 0";
        }

        $query .= " ORDER BY pf.fecha_firma DESC";

        $this->db->query($query);
        $this->db->bind(':propuesta_id', $propuesta_id);
        return $this->db->fetchAll();
    }

    /**
     * Actualizar estado de la propuesta
     */
    public function updateEstado($propuesta_id, $nuevo_estado, $admin_id) {
        $valid_states = ['votacion', 'revision', 'en_progreso', 'completada', 'rechazada'];

        if (!in_array($nuevo_estado, $valid_states)) {
            return ['success' => false, 'message' => 'Estado inválido'];
        }

        $query = "UPDATE propuestas SET estado = :estado";

        // Si cambia a revisión, cerrar la votación
        if ($nuevo_estado === 'revision') {
            $query .= ", fecha_cierre = NOW()";
        }

        $query .= " WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':estado', $nuevo_estado);
        $this->db->bind(':id', $propuesta_id);

        if ($this->db->execute()) {
            // Crear notificación para el autor
            $propuesta = $this->getById($propuesta_id);
            $this->notifyAuthor($propuesta_id, $nuevo_estado);

            return ['success' => true, 'message' => 'Estado actualizado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al actualizar el estado'];
    }

    /**
     * Asignar propuesta a comisión
     */
    public function assignToCommission($propuesta_id, $comision_id, $admin_id) {
        $query = "UPDATE propuestas
                  SET comision_id = :comision_id,
                      fecha_asignacion_comision = NOW(),
                      estado = 'revision'
                  WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':comision_id', $comision_id);
        $this->db->bind(':id', $propuesta_id);

        if ($this->db->execute()) {
            // Notificar a los coordinadores de la comisión
            $this->notifyCommission($propuesta_id, $comision_id);

            return ['success' => true, 'message' => 'Propuesta asignada a comisión exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al asignar la comisión'];
    }

    /**
     * Comisión acepta la propuesta
     */
    public function acceptByCommission($propuesta_id, $comision_user_id) {
        // Verificar que el usuario pertenece a la comisión
        $propuesta = $this->getById($propuesta_id);

        if (!$propuesta['comision_id']) {
            return ['success' => false, 'message' => 'Esta propuesta no está asignada a ninguna comisión'];
        }

        if ($propuesta['estado'] !== 'revision') {
            return ['success' => false, 'message' => 'Esta propuesta no está en revisión'];
        }

        // Verificar plazo de 4 días
        $dias_config = get_config('dias_aceptar_comision', 4);
        $fecha_limite = strtotime($propuesta['fecha_asignacion_comision'] . " + {$dias_config} days");

        if (time() > $fecha_limite) {
            return ['success' => false, 'message' => 'El plazo para aceptar ha expirado'];
        }

        $query = "UPDATE propuestas SET estado = 'en_progreso' WHERE id = :id";

        $this->db->query($query);
        $this->db->bind(':id', $propuesta_id);

        if ($this->db->execute()) {
            $this->notifyAuthor($propuesta_id, 'en_progreso');
            return ['success' => true, 'message' => 'Propuesta aceptada y en progreso'];
        }

        return ['success' => false, 'message' => 'Error al aceptar la propuesta'];
    }

    /**
     * Marcar propuesta como completada con evidencias
     */
    public function complete($propuesta_id, $evidencias, $comision_user_id) {
        $query = "UPDATE propuestas
                  SET estado = 'completada',
                      evidencias = :evidencias,
                      fecha_completada = NOW()
                  WHERE id = :id AND estado = 'en_progreso'";

        $this->db->query($query);
        $this->db->bind(':evidencias', $evidencias);
        $this->db->bind(':id', $propuesta_id);

        if ($this->db->execute()) {
            $this->notifyAuthor($propuesta_id, 'completada');
            return ['success' => true, 'message' => 'Propuesta marcada como completada'];
        }

        return ['success' => false, 'message' => 'Error al completar la propuesta'];
    }

    /**
     * Obtener comisiones disponibles
     */
    public function getCommissions($grupo_id = null) {
        $query = "SELECT c.*, g.nombre as grupo_nombre
                  FROM comisiones c
                  LEFT JOIN grupos g ON c.grupo_id = g.id
                  WHERE 1=1";

        if ($grupo_id) {
            $query .= " AND (c.grupo_id = :grupo_id OR c.grupo_id IS NULL)";
        }

        $query .= " ORDER BY c.nombre";

        $this->db->query($query);

        if ($grupo_id) {
            $this->db->bind(':grupo_id', $grupo_id);
        }

        return $this->db->fetchAll();
    }

    /**
     * Obtener estadísticas de propuestas
     */
    public function getStats($user_id = null) {
        if ($user_id) {
            $query = "SELECT
                        COUNT(DISTINCT p.id) as total_propuestas,
                        COUNT(DISTINCT CASE WHEN p.estado = 'votacion' THEN p.id END) as en_votacion,
                        COUNT(DISTINCT CASE WHEN p.estado = 'en_progreso' THEN p.id END) as en_progreso,
                        COUNT(DISTINCT CASE WHEN p.estado = 'completada' THEN p.id END) as completadas,
                        COUNT(DISTINCT pf.propuesta_id) as firmadas
                      FROM propuestas p
                      LEFT JOIN propuestas_firmas pf ON pf.usuario_id = :user_id
                      WHERE p.autor_id = :user_id";

            $this->db->query($query);
            $this->db->bind(':user_id', $user_id);
        } else {
            $query = "SELECT
                        COUNT(*) as total_propuestas,
                        COUNT(CASE WHEN estado = 'votacion' THEN 1 END) as en_votacion,
                        COUNT(CASE WHEN estado = 'en_progreso' THEN 1 END) as en_progreso,
                        COUNT(CASE WHEN estado = 'completada' THEN 1 END) as completadas
                      FROM propuestas";

            $this->db->query($query);
        }

        return $this->db->fetch();
    }

    /**
     * Buscar propuestas
     */
    public function search($search_term, $filters = []) {
        $query = "SELECT p.*,
                         u.nombre as autor_nombre,
                         g.nombre as grupo_nombre,
                         COUNT(DISTINCT pf.usuario_id) as total_firmas
                  FROM propuestas p
                  LEFT JOIN usuarios u ON p.autor_id = u.id
                  LEFT JOIN grupos g ON p.grupo_id = g.id
                  LEFT JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
                  WHERE (p.titulo LIKE :search OR p.descripcion LIKE :search)";

        if (isset($filters['estado'])) {
            $query .= " AND p.estado = :estado";
        }

        if (isset($filters['categoria'])) {
            $query .= " AND p.categoria = :categoria";
        }

        if (isset($filters['grupo_id'])) {
            $query .= " AND (p.grupo_id = :grupo_id OR p.grupo_id IS NULL)";
        }

        $query .= " GROUP BY p.id ORDER BY p.fecha_creacion DESC";

        $this->db->query($query);
        $this->db->bind(':search', '%' . $search_term . '%');

        if (isset($filters['estado'])) {
            $this->db->bind(':estado', $filters['estado']);
        }
        if (isset($filters['categoria'])) {
            $this->db->bind(':categoria', $filters['categoria']);
        }
        if (isset($filters['grupo_id'])) {
            $this->db->bind(':grupo_id', $filters['grupo_id']);
        }

        return $this->db->fetchAll();
    }

    /**
     * Verificar si el usuario puede editar la propuesta
     */
    public function canEdit($propuesta_id, $user_id) {
        $propuesta = $this->getById($propuesta_id);

        if (!$propuesta) {
            return false;
        }

        // Solo el autor puede editar y solo si está en votación
        return $propuesta['autor_id'] == $user_id && $propuesta['estado'] === 'votacion';
    }

    /**
     * Notificar al autor sobre cambios de estado
     */
    private function notifyAuthor($propuesta_id, $nuevo_estado) {
        $propuesta = $this->getById($propuesta_id);

        $mensajes = [
            'revision' => 'Tu propuesta "' . $propuesta['titulo'] . '" ha pasado a revisión',
            'en_progreso' => 'Tu propuesta "' . $propuesta['titulo'] . '" ha sido aceptada y está en progreso',
            'completada' => 'Tu propuesta "' . $propuesta['titulo'] . '" ha sido completada',
            'rechazada' => 'Tu propuesta "' . $propuesta['titulo'] . '" ha sido rechazada'
        ];

        if (isset($mensajes[$nuevo_estado])) {
            $query = "INSERT INTO notificaciones (usuario_id, tipo, mensaje, referencia_id, referencia_tipo, fecha_creacion)
                      VALUES (:usuario_id, 'propuesta', :mensaje, :propuesta_id, 'propuesta', NOW())";

            $this->db->query($query);
            $this->db->bind(':usuario_id', $propuesta['autor_id']);
            $this->db->bind(':mensaje', $mensajes[$nuevo_estado]);
            $this->db->bind(':propuesta_id', $propuesta_id);
            $this->db->execute();
        }
    }

    /**
     * Notificar a la comisión sobre asignación
     */
    private function notifyCommission($propuesta_id, $comision_id) {
        $propuesta = $this->getById($propuesta_id);

        // Obtener coordinadores de la comisión
        $query = "SELECT DISTINCT gm.usuario_id
                  FROM comisiones c
                  INNER JOIN grupos g ON c.grupo_id = g.id
                  INNER JOIN grupo_miembros gm ON g.id = gm.grupo_id
                  WHERE c.id = :comision_id
                  AND gm.es_coordinador = 1
                  AND gm.estado = 'aprobado'";

        $this->db->query($query);
        $this->db->bind(':comision_id', $comision_id);
        $coordinadores = $this->db->fetchAll();

        $mensaje = 'Se ha asignado la propuesta "' . $propuesta['titulo'] . '" a tu comisión';

        foreach ($coordinadores as $coord) {
            $query = "INSERT INTO notificaciones (usuario_id, tipo, mensaje, referencia_id, referencia_tipo, fecha_creacion)
                      VALUES (:usuario_id, 'comision', :mensaje, :propuesta_id, 'propuesta', NOW())";

            $this->db->query($query);
            $this->db->bind(':usuario_id', $coord['usuario_id']);
            $this->db->bind(':mensaje', $mensaje);
            $this->db->bind(':propuesta_id', $propuesta_id);
            $this->db->execute();
        }
    }
}
