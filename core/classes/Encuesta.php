<?php

class Encuesta {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Generar token único para recibos
     */
    private function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generar recibo único formato RTFI-XXXXX
     */
    private function generateRecibo() {
        do {
            $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
            $recibo = 'RTFI-' . $random;

            // Verificar que no existe
            $query = "SELECT COUNT(*) as count FROM encuestas_votos_anonimos WHERE recibo = :recibo";
            $this->db->query($query);
            $this->db->bind(':recibo', $recibo);
            $result = $this->db->single();
        } while ($result['count'] > 0);

        return $recibo;
    }

    /**
     * Crear una nueva encuesta con preguntas
     */
    public function create($data) {
        $this->db->query("START TRANSACTION");

        try {
            // Generar token si es anónima
            $token_recibos = null;
            if (isset($data['anonima']) && $data['anonima'] == 1) {
                $token_recibos = $this->generateToken();
            }

            // Crear encuesta
            $query = "INSERT INTO encuestas
                      (titulo, descripcion, autor_id, grupo_id, fecha_inicio, fecha_fin,
                       anonima, activa, token_recibos, fecha_creacion)
                      VALUES (:titulo, :descripcion, :autor_id, :grupo_id, :fecha_inicio,
                              :fecha_fin, :anonima, 1, :token_recibos, NOW())";

            $this->db->query($query);
            $this->db->bind(':titulo', $data['titulo']);
            $this->db->bind(':descripcion', $data['descripcion']);
            $this->db->bind(':autor_id', $data['autor_id']);
            $this->db->bind(':grupo_id', $data['grupo_id'] ?? null);
            $this->db->bind(':fecha_inicio', $data['fecha_inicio'] ?? date('Y-m-d H:i:s'));
            $this->db->bind(':fecha_fin', $data['fecha_fin'] ?? null);
            $this->db->bind(':anonima', $data['anonima'] ?? 0);
            $this->db->bind(':token_recibos', $token_recibos);

            $this->db->execute();
            $encuesta_id = $this->db->lastInsertId();

            // Insertar preguntas
            if (isset($data['preguntas']) && is_array($data['preguntas'])) {
                foreach ($data['preguntas'] as $index => $pregunta) {
                    $pregunta_id = $this->addPregunta($encuesta_id, [
                        'texto' => $pregunta['texto'],
                        'tipo' => $pregunta['tipo'],
                        'requerida' => $pregunta['requerida'] ?? 1,
                        'orden' => $index + 1
                    ]);

                    // Si la pregunta tiene opciones (tipo unica o multiple)
                    if (($pregunta['tipo'] == 'unica' || $pregunta['tipo'] == 'multiple')
                        && isset($pregunta['opciones']) && is_array($pregunta['opciones'])) {
                        foreach ($pregunta['opciones'] as $opcion_index => $opcion_texto) {
                            if (!empty(trim($opcion_texto))) {
                                $this->addOpcion($pregunta_id, trim($opcion_texto), $opcion_index + 1);
                            }
                        }
                    }
                }
            }

            $this->db->query("COMMIT");

            return [
                'success' => true,
                'message' => 'Encuesta creada exitosamente',
                'id' => $encuesta_id,
                'token_recibos' => $token_recibos
            ];

        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Error al crear la encuesta: ' . $e->getMessage()];
        }
    }

    /**
     * Agregar una pregunta a una encuesta
     */
    public function addPregunta($encuesta_id, $data) {
        $query = "INSERT INTO encuestas_preguntas
                  (encuesta_id, texto_pregunta, tipo, requerida, orden, fecha_creacion)
                  VALUES (:encuesta_id, :texto, :tipo, :requerida, :orden, NOW())";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        $this->db->bind(':texto', $data['texto']);
        $this->db->bind(':tipo', $data['tipo']);
        $this->db->bind(':requerida', $data['requerida'] ?? 1);
        $this->db->bind(':orden', $data['orden'] ?? 0);

        $this->db->execute();
        return $this->db->lastInsertId();
    }

    /**
     * Agregar una opción a una pregunta
     */
    public function addOpcion($pregunta_id, $texto, $orden = 0) {
        $query = "INSERT INTO encuestas_opciones
                  (pregunta_id, texto, orden, fecha_creacion)
                  VALUES (:pregunta_id, :texto, :orden, NOW())";

        $this->db->query($query);
        $this->db->bind(':pregunta_id', $pregunta_id);
        $this->db->bind(':texto', $texto);
        $this->db->bind(':orden', $orden);

        $this->db->execute();
        return $this->db->lastInsertId();
    }

    /**
     * Obtener encuesta por ID
     */
    public function getById($id) {
        $query = "SELECT e.*,
                         u.nombre, u.apellidos, u.email,
                         g.nombre as grupo_nombre,
                         (SELECT COUNT(*) FROM encuestas_votos WHERE encuesta_id = e.id) +
                         (SELECT COUNT(*) FROM encuestas_votos_anonimos WHERE encuesta_id = e.id) as total_votos
                  FROM encuestas e
                  LEFT JOIN usuarios u ON e.autor_id = u.id
                  LEFT JOIN grupos g ON e.grupo_id = g.id
                  WHERE e.id = :id AND e.eliminado = 0";

        $this->db->query($query);
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    /**
     * Obtener encuesta por token de recibos
     */
    public function getByToken($token) {
        $query = "SELECT e.*,
                         u.nombre, u.apellidos,
                         (SELECT COUNT(*) FROM encuestas_votos_anonimos WHERE encuesta_id = e.id) as total_votos
                  FROM encuestas e
                  LEFT JOIN usuarios u ON e.autor_id = u.id
                  WHERE e.token_recibos = :token AND e.eliminado = 0";

        $this->db->query($query);
        $this->db->bind(':token', $token);
        return $this->db->single();
    }

    /**
     * Obtener preguntas de una encuesta
     */
    public function getPreguntas($encuesta_id) {
        $query = "SELECT * FROM encuestas_preguntas
                  WHERE encuesta_id = :encuesta_id
                  ORDER BY orden ASC";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        return $this->db->resultSet();
    }

    /**
     * Obtener opciones de una pregunta
     */
    public function getOpciones($pregunta_id) {
        $query = "SELECT * FROM encuestas_opciones
                  WHERE pregunta_id = :pregunta_id
                  ORDER BY orden ASC";

        $this->db->query($query);
        $this->db->bind(':pregunta_id', $pregunta_id);
        return $this->db->resultSet();
    }

    /**
     * Verificar si un usuario ha votado en una encuesta
     */
    public function hasVoted($encuesta_id, $usuario_id) {
        $encuesta = $this->getById($encuesta_id);

        if ($encuesta['anonima']) {
            // Para anónimas, revisar tabla de participación
            $query = "SELECT COUNT(*) as count FROM encuestas_participacion
                      WHERE encuesta_id = :encuesta_id AND usuario_id = :usuario_id";
        } else {
            // Para normales, revisar tabla de votos
            $query = "SELECT COUNT(*) as count FROM encuestas_votos
                      WHERE encuesta_id = :encuesta_id AND usuario_id = :usuario_id";
        }

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        $this->db->bind(':usuario_id', $usuario_id);
        $result = $this->db->single();

        return $result['count'] > 0;
    }

    /**
     * Votar en una encuesta normal (con usuario_id)
     */
    public function voteNormal($encuesta_id, $usuario_id, $respuestas) {
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return ['success' => false, 'message' => 'Encuesta no encontrada'];
        }

        if ($encuesta['anonima']) {
            return ['success' => false, 'message' => 'Esta encuesta es anónima, use voteAnonimo()'];
        }

        // Verificar si ya votó
        if ($this->hasVoted($encuesta_id, $usuario_id)) {
            return ['success' => false, 'message' => 'Ya has votado en esta encuesta'];
        }

        // Verificar estado de la encuesta
        $now = date('Y-m-d H:i:s');
        if ($encuesta['fecha_inicio'] > $now) {
            return ['success' => false, 'message' => 'La encuesta aún no ha iniciado'];
        }
        if ($encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $now) {
            return ['success' => false, 'message' => 'La encuesta ha finalizado'];
        }
        if (!$encuesta['activa']) {
            return ['success' => false, 'message' => 'La encuesta está cerrada'];
        }

        $this->db->query("START TRANSACTION");

        try {
            // Crear voto
            $query = "INSERT INTO encuestas_votos (encuesta_id, usuario_id, fecha_voto)
                      VALUES (:encuesta_id, :usuario_id, NOW())";
            $this->db->query($query);
            $this->db->bind(':encuesta_id', $encuesta_id);
            $this->db->bind(':usuario_id', $usuario_id);
            $this->db->execute();
            $voto_id = $this->db->lastInsertId();

            // Guardar respuestas
            foreach ($respuestas as $pregunta_id => $respuesta) {
                $pregunta = $this->getPreguntaById($pregunta_id);

                if ($pregunta['tipo'] == 'abierta') {
                    // Respuesta abierta
                    $query = "INSERT INTO encuestas_respuestas_abiertas
                              (voto_id, pregunta_id, texto_respuesta, fecha_creacion)
                              VALUES (:voto_id, :pregunta_id, :texto, NOW())";
                    $this->db->query($query);
                    $this->db->bind(':voto_id', $voto_id);
                    $this->db->bind(':pregunta_id', $pregunta_id);
                    $this->db->bind(':texto', $respuesta);
                    $this->db->execute();
                } else {
                    // Respuesta de opción (puede ser array para múltiple)
                    $opciones = is_array($respuesta) ? $respuesta : [$respuesta];

                    foreach ($opciones as $opcion_id) {
                        $query = "INSERT INTO encuestas_respuestas
                                  (voto_id, pregunta_id, opcion_id, fecha_creacion)
                                  VALUES (:voto_id, :pregunta_id, :opcion_id, NOW())";
                        $this->db->query($query);
                        $this->db->bind(':voto_id', $voto_id);
                        $this->db->bind(':pregunta_id', $pregunta_id);
                        $this->db->bind(':opcion_id', $opcion_id);
                        $this->db->execute();
                    }
                }
            }

            $this->db->query("COMMIT");

            return [
                'success' => true,
                'message' => 'Voto registrado exitosamente'
            ];

        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Error al votar: ' . $e->getMessage()];
        }
    }

    /**
     * Votar en una encuesta anónima (genera recibo)
     */
    public function voteAnonimo($encuesta_id, $usuario_id, $respuestas) {
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return ['success' => false, 'message' => 'Encuesta no encontrada'];
        }

        if (!$encuesta['anonima']) {
            return ['success' => false, 'message' => 'Esta encuesta no es anónima, use voteNormal()'];
        }

        // Verificar si ya votó
        if ($this->hasVoted($encuesta_id, $usuario_id)) {
            return ['success' => false, 'message' => 'Ya has votado en esta encuesta'];
        }

        // Verificar estado de la encuesta
        $now = date('Y-m-d H:i:s');
        if ($encuesta['fecha_inicio'] > $now) {
            return ['success' => false, 'message' => 'La encuesta aún no ha iniciado'];
        }
        if ($encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $now) {
            return ['success' => false, 'message' => 'La encuesta ha finalizado'];
        }
        if (!$encuesta['activa']) {
            return ['success' => false, 'message' => 'La encuesta está cerrada'];
        }

        $this->db->query("START TRANSACTION");

        try {
            // Generar recibo único
            $recibo = $this->generateRecibo();

            // Marcar participación (solo bandera, sin respuestas)
            $query = "INSERT INTO encuestas_participacion (encuesta_id, usuario_id, ha_votado, fecha_participacion)
                      VALUES (:encuesta_id, :usuario_id, 1, NOW())";
            $this->db->query($query);
            $this->db->bind(':encuesta_id', $encuesta_id);
            $this->db->bind(':usuario_id', $usuario_id);
            $this->db->execute();

            // Crear voto anónimo CON recibo (SIN usuario_id)
            $query = "INSERT INTO encuestas_votos_anonimos (encuesta_id, recibo, fecha_voto)
                      VALUES (:encuesta_id, :recibo, NOW())";
            $this->db->query($query);
            $this->db->bind(':encuesta_id', $encuesta_id);
            $this->db->bind(':recibo', $recibo);
            $this->db->execute();
            $voto_anonimo_id = $this->db->lastInsertId();

            // Guardar respuestas anónimas
            foreach ($respuestas as $pregunta_id => $respuesta) {
                $pregunta = $this->getPreguntaById($pregunta_id);

                if ($pregunta['tipo'] == 'abierta') {
                    // Respuesta abierta anónima
                    $query = "INSERT INTO encuestas_respuestas_abiertas_anonimas
                              (voto_anonimo_id, pregunta_id, texto_respuesta, fecha_creacion)
                              VALUES (:voto_id, :pregunta_id, :texto, NOW())";
                    $this->db->query($query);
                    $this->db->bind(':voto_id', $voto_anonimo_id);
                    $this->db->bind(':pregunta_id', $pregunta_id);
                    $this->db->bind(':texto', $respuesta);
                    $this->db->execute();
                } else {
                    // Respuesta de opción anónima
                    $opciones = is_array($respuesta) ? $respuesta : [$respuesta];

                    foreach ($opciones as $opcion_id) {
                        $query = "INSERT INTO encuestas_respuestas_anonimas
                                  (voto_anonimo_id, pregunta_id, opcion_id, fecha_creacion)
                                  VALUES (:voto_id, :pregunta_id, :opcion_id, NOW())";
                        $this->db->query($query);
                        $this->db->bind(':voto_id', $voto_anonimo_id);
                        $this->db->bind(':pregunta_id', $pregunta_id);
                        $this->db->bind(':opcion_id', $opcion_id);
                        $this->db->execute();
                    }
                }
            }

            $this->db->query("COMMIT");

            return [
                'success' => true,
                'message' => 'Voto anónimo registrado exitosamente',
                'recibo' => $recibo
            ];

        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Error al votar: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener pregunta por ID
     */
    public function getPreguntaById($id) {
        $query = "SELECT * FROM encuestas_preguntas WHERE id = :id";
        $this->db->query($query);
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    /**
     * Obtener resultados de una encuesta
     */
    public function getResultados($encuesta_id) {
        $encuesta = $this->getById($encuesta_id);
        $preguntas = $this->getPreguntas($encuesta_id);
        $resultados = [];

        foreach ($preguntas as $pregunta) {
            $resultado = [
                'pregunta' => $pregunta,
                'opciones' => [],
                'respuestas_abiertas' => []
            ];

            if ($pregunta['tipo'] == 'abierta') {
                // Obtener respuestas abiertas
                if ($encuesta['anonima']) {
                    $query = "SELECT texto_respuesta, fecha_creacion
                              FROM encuestas_respuestas_abiertas_anonimas
                              WHERE pregunta_id = :pregunta_id
                              ORDER BY fecha_creacion DESC";
                } else {
                    $query = "SELECT era.texto_respuesta, era.fecha_creacion, u.nombre, u.apellidos
                              FROM encuestas_respuestas_abiertas era
                              INNER JOIN encuestas_votos ev ON era.voto_id = ev.id
                              INNER JOIN usuarios u ON ev.usuario_id = u.id
                              WHERE era.pregunta_id = :pregunta_id
                              ORDER BY era.fecha_creacion DESC";
                }

                $this->db->query($query);
                $this->db->bind(':pregunta_id', $pregunta['id']);
                $resultado['respuestas_abiertas'] = $this->db->resultSet();

            } else {
                // Obtener conteo de opciones
                $opciones = $this->getOpciones($pregunta['id']);

                foreach ($opciones as $opcion) {
                    if ($encuesta['anonima']) {
                        $query = "SELECT COUNT(*) as total
                                  FROM encuestas_respuestas_anonimas
                                  WHERE pregunta_id = :pregunta_id AND opcion_id = :opcion_id";
                    } else {
                        $query = "SELECT COUNT(*) as total
                                  FROM encuestas_respuestas
                                  WHERE pregunta_id = :pregunta_id AND opcion_id = :opcion_id";
                    }

                    $this->db->query($query);
                    $this->db->bind(':pregunta_id', $pregunta['id']);
                    $this->db->bind(':opcion_id', $opcion['id']);
                    $count = $this->db->single();

                    $opcion['total_votos'] = $count['total'];
                    $opcion['porcentaje'] = $encuesta['total_votos'] > 0
                        ? ($count['total'] / $encuesta['total_votos']) * 100
                        : 0;

                    $resultado['opciones'][] = $opcion;
                }
            }

            $resultados[] = $resultado;
        }

        return $resultados;
    }

    /**
     * Obtener todos los recibos de una encuesta anónima
     */
    public function getAllRecibos($encuesta_id) {
        $query = "SELECT eva.recibo, eva.fecha_voto,
                         ep.texto_pregunta, ep.tipo,
                         eo.texto as opcion_texto,
                         eraa.texto_respuesta
                  FROM encuestas_votos_anonimos eva
                  LEFT JOIN encuestas_respuestas_anonimas era ON eva.id = era.voto_anonimo_id
                  LEFT JOIN encuestas_preguntas ep ON era.pregunta_id = ep.id
                  LEFT JOIN encuestas_opciones eo ON era.opcion_id = eo.id
                  LEFT JOIN encuestas_respuestas_abiertas_anonimas eraa ON eva.id = eraa.voto_anonimo_id AND ep.id = eraa.pregunta_id
                  WHERE eva.encuesta_id = :encuesta_id
                  ORDER BY eva.fecha_voto DESC, ep.orden ASC";

        $this->db->query($query);
        $this->db->bind(':encuesta_id', $encuesta_id);
        return $this->db->resultSet();
    }

    /**
     * Obtener encuestas activas
     */
    public function getActivas($limit = null, $grupo_id = null) {
        $now = date('Y-m-d H:i:s');

        $query = "SELECT e.*,
                         u.nombre, u.apellidos,
                         g.nombre as grupo_nombre,
                         (SELECT COUNT(*) FROM encuestas_votos WHERE encuesta_id = e.id) +
                         (SELECT COUNT(*) FROM encuestas_votos_anonimos WHERE encuesta_id = e.id) as total_votos
                  FROM encuestas e
                  LEFT JOIN usuarios u ON e.autor_id = u.id
                  LEFT JOIN grupos g ON e.grupo_id = g.id
                  WHERE e.eliminado = 0
                  AND e.activa = 1
                  AND e.fecha_inicio <= :now
                  AND (e.fecha_fin IS NULL OR e.fecha_fin >= :now2)";

        if ($grupo_id) {
            $query .= " AND (e.grupo_id = :grupo_id OR e.grupo_id IS NULL)";
        } else {
            $query .= " AND e.grupo_id IS NULL";
        }

        $query .= " ORDER BY e.fecha_creacion DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
        }

        $this->db->query($query);
        $this->db->bind(':now', $now);
        $this->db->bind(':now2', $now);
        if ($grupo_id) {
            $this->db->bind(':grupo_id', $grupo_id);
        }
        if ($limit) {
            $this->db->bind(':limit', $limit);
        }

        return $this->db->resultSet();
    }

    /**
     * Cerrar encuesta
     */
    public function close($encuesta_id, $usuario_id) {
        $encuesta = $this->getById($encuesta_id);

        if (!$encuesta) {
            return ['success' => false, 'message' => 'Encuesta no encontrada'];
        }

        if ($encuesta['autor_id'] != $usuario_id && !is_admin()) {
            return ['success' => false, 'message' => 'No tienes permiso para cerrar esta encuesta'];
        }

        $query = "UPDATE encuestas SET activa = 0 WHERE id = :id";
        $this->db->query($query);
        $this->db->bind(':id', $encuesta_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Encuesta cerrada exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al cerrar la encuesta'];
    }
}
