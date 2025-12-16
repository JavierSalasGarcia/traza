<?php
/**
 * TrazaFI - Clase Aviso
 * Modelo para gestión de avisos con fechas programables
 */

class Aviso {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtiene un aviso por ID
     */
    public function getById($id) {
        return $this->db->query("SELECT a.*, u.nombre, u.apellidos, u.imagen_perfil,
                                g.nombre as grupo_nombre
                                FROM avisos a
                                INNER JOIN usuarios u ON a.autor_id = u.id
                                LEFT JOIN grupos g ON a.grupo_id = g.id
                                WHERE a.id = :id")
                       ->bind(':id', $id)
                       ->fetch();
    }

    /**
     * Obtiene avisos publicados (activos en este momento)
     */
    public function getPublished($grupo_id = null, $limit = 20, $offset = 0) {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT a.*, u.nombre, u.apellidos, u.imagen_perfil,
                       g.nombre as grupo_nombre,
                       (SELECT COUNT(*) FROM likes WHERE tipo_contenido = 'aviso' AND contenido_id = a.id) as total_likes,
                       (SELECT COUNT(*) FROM comentarios WHERE tipo_contenido = 'aviso' AND contenido_id = a.id) as total_comentarios
                FROM avisos a
                INNER JOIN usuarios u ON a.autor_id = u.id
                LEFT JOIN grupos g ON a.grupo_id = g.id
                WHERE a.publicado = 1
                AND (a.fecha_inicio_publicacion IS NULL OR a.fecha_inicio_publicacion <= :now)
                AND (a.fecha_fin_publicacion IS NULL OR a.fecha_fin_publicacion > :now)";

        if ($grupo_id) {
            $sql .= " AND a.grupo_id = :grupo_id";
        }

        $sql .= " ORDER BY a.fecha_creacion DESC LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql)
                         ->bind(':now', $now);

        if ($grupo_id) {
            $query->bind(':grupo_id', $grupo_id);
        }

        return $query->bind(':limit', $limit, PDO::PARAM_INT)
                     ->bind(':offset', $offset, PDO::PARAM_INT)
                     ->fetchAll();
    }

    /**
     * Obtiene avisos generales (sin grupo específico)
     */
    public function getGeneralAvisos($limit = 20, $offset = 0) {
        $now = date('Y-m-d H:i:s');
        return $this->db->query("SELECT a.*, u.nombre, u.apellidos, u.imagen_perfil,
                                (SELECT COUNT(*) FROM likes WHERE tipo_contenido = 'aviso' AND contenido_id = a.id) as total_likes,
                                (SELECT COUNT(*) FROM comentarios WHERE tipo_contenido = 'aviso' AND contenido_id = a.id) as total_comentarios
                                FROM avisos a
                                INNER JOIN usuarios u ON a.autor_id = u.id
                                WHERE a.publicado = 1
                                AND a.grupo_id IS NULL
                                AND (a.fecha_inicio_publicacion IS NULL OR a.fecha_inicio_publicacion <= :now)
                                AND (a.fecha_fin_publicacion IS NULL OR a.fecha_fin_publicacion > :now)
                                ORDER BY a.destacado DESC, a.fecha_creacion DESC
                                LIMIT :limit OFFSET :offset")
                       ->bind(':now', $now)
                       ->bind(':limit', $limit, PDO::PARAM_INT)
                       ->bind(':offset', $offset, PDO::PARAM_INT)
                       ->fetchAll();
    }

    /**
     * Obtiene avisos programados (aún no publicados)
     */
    public function getScheduled($grupo_id = null, $autor_id = null) {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT a.*, u.nombre, u.apellidos, g.nombre as grupo_nombre
                FROM avisos a
                INNER JOIN usuarios u ON a.autor_id = u.id
                LEFT JOIN grupos g ON a.grupo_id = g.id
                WHERE a.fecha_inicio_publicacion > :now";

        if ($grupo_id) {
            $sql .= " AND a.grupo_id = :grupo_id";
        }

        if ($autor_id) {
            $sql .= " AND a.autor_id = :autor_id";
        }

        $sql .= " ORDER BY a.fecha_inicio_publicacion";

        $query = $this->db->query($sql)->bind(':now', $now);

        if ($grupo_id) {
            $query->bind(':grupo_id', $grupo_id);
        }

        if ($autor_id) {
            $query->bind(':autor_id', $autor_id);
        }

        return $query->fetchAll();
    }

    /**
     * Obtiene avisos finalizados (para históricos)
     */
    public function getFinished($grupo_id = null, $limit = 20, $offset = 0) {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT a.*, u.nombre, u.apellidos, g.nombre as grupo_nombre
                FROM avisos a
                INNER JOIN usuarios u ON a.autor_id = u.id
                LEFT JOIN grupos g ON a.grupo_id = g.id
                WHERE a.fecha_fin_publicacion IS NOT NULL
                AND a.fecha_fin_publicacion <= :now";

        if ($grupo_id) {
            $sql .= " AND a.grupo_id = :grupo_id";
        }

        $sql .= " ORDER BY a.fecha_fin_publicacion DESC LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql)->bind(':now', $now);

        if ($grupo_id) {
            $query->bind(':grupo_id', $grupo_id);
        }

        return $query->bind(':limit', $limit, PDO::PARAM_INT)
                     ->bind(':offset', $offset, PDO::PARAM_INT)
                     ->fetchAll();
    }

    /**
     * Crea un nuevo aviso
     */
    public function create($data) {
        $this->db->query("INSERT INTO avisos (titulo, contenido, grupo_id, autor_id,
                         fecha_inicio_publicacion, fecha_fin_publicacion, publicado, destacado, categoria, etiquetas)
                         VALUES (:titulo, :contenido, :grupo_id, :autor_id,
                         :fecha_inicio, :fecha_fin, :publicado, :destacado, :categoria, :etiquetas)")
                ->bind(':titulo', $data['titulo'])
                ->bind(':contenido', $data['contenido'])
                ->bind(':grupo_id', $data['grupo_id'] ?? null)
                ->bind(':autor_id', $data['autor_id'])
                ->bind(':fecha_inicio', $data['fecha_inicio_publicacion'] ?? null)
                ->bind(':fecha_fin', $data['fecha_fin_publicacion'] ?? null)
                ->bind(':publicado', $data['publicado'] ?? 0)
                ->bind(':destacado', $data['destacado'] ?? 0)
                ->bind(':categoria', $data['categoria'] ?? null)
                ->bind(':etiquetas', $data['etiquetas'] ?? null)
                ->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Actualiza un aviso
     */
    public function update($aviso_id, $data) {
        $allowed = ['titulo', 'contenido', 'fecha_inicio_publicacion', 'fecha_fin_publicacion',
                   'publicado', 'destacado', 'categoria', 'etiquetas'];
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

        $sql = "UPDATE avisos SET " . implode(', ', $fields) . " WHERE id = :id";
        $query = $this->db->query($sql);

        foreach ($values as $key => $value) {
            $query->bind(":{$key}", $value);
        }

        return $query->bind(':id', $aviso_id)->execute();
    }

    /**
     * Elimina un aviso
     */
    public function delete($aviso_id) {
        return $this->db->query("DELETE FROM avisos WHERE id = :id")
                       ->bind(':id', $aviso_id)
                       ->execute();
    }

    /**
     * Publica un aviso
     */
    public function publish($aviso_id) {
        return $this->db->query("UPDATE avisos SET publicado = 1 WHERE id = :id")
                       ->bind(':id', $aviso_id)
                       ->execute();
    }

    /**
     * Despublica un aviso
     */
    public function unpublish($aviso_id) {
        return $this->db->query("UPDATE avisos SET publicado = 0 WHERE id = :id")
                       ->bind(':id', $aviso_id)
                       ->execute();
    }

    /**
     * Marca/desmarca aviso como destacado
     */
    public function toggleDestacado($aviso_id) {
        return $this->db->query("UPDATE avisos SET destacado = NOT destacado WHERE id = :id")
                       ->bind(':id', $aviso_id)
                       ->execute();
    }

    /**
     * Obtiene archivos adjuntos de un aviso
     */
    public function getArchivos($aviso_id) {
        return $this->db->query("SELECT * FROM aviso_archivos
                                WHERE aviso_id = :aviso_id
                                ORDER BY fecha_subida")
                       ->bind(':aviso_id', $aviso_id)
                       ->fetchAll();
    }

    /**
     * Agrega un archivo adjunto
     */
    public function addArchivo($aviso_id, $nombre, $ruta, $tipo = null, $tamano = null) {
        return $this->db->query("INSERT INTO aviso_archivos (aviso_id, nombre_archivo, ruta_archivo, tipo_archivo, tamano_bytes)
                                VALUES (:aviso_id, :nombre, :ruta, :tipo, :tamano)")
                       ->bind(':aviso_id', $aviso_id)
                       ->bind(':nombre', $nombre)
                       ->bind(':ruta', $ruta)
                       ->bind(':tipo', $tipo)
                       ->bind(':tamano', $tamano)
                       ->execute();
    }

    /**
     * Elimina un archivo adjunto
     */
    public function deleteArchivo($archivo_id) {
        $archivo = $this->db->query("SELECT ruta_archivo FROM aviso_archivos WHERE id = :id")
                           ->bind(':id', $archivo_id)
                           ->fetch();

        if ($archivo) {
            $filepath = UPLOAD_PATH . '/' . $archivo['ruta_archivo'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            return $this->db->query("DELETE FROM aviso_archivos WHERE id = :id")
                           ->bind(':id', $archivo_id)
                           ->execute();
        }

        return false;
    }

    /**
     * Busca avisos por término
     */
    public function search($term, $grupo_id = null, $limit = 20) {
        $now = date('Y-m-d H:i:s');
        $term = '%' . $term . '%';
        $sql = "SELECT a.*, u.nombre, u.apellidos, g.nombre as grupo_nombre
                FROM avisos a
                INNER JOIN usuarios u ON a.autor_id = u.id
                LEFT JOIN grupos g ON a.grupo_id = g.id
                WHERE a.publicado = 1
                AND (a.fecha_inicio_publicacion IS NULL OR a.fecha_inicio_publicacion <= :now)
                AND (a.fecha_fin_publicacion IS NULL OR a.fecha_fin_publicacion > :now)
                AND (a.titulo LIKE :term OR a.contenido LIKE :term OR a.etiquetas LIKE :term)";

        if ($grupo_id) {
            $sql .= " AND a.grupo_id = :grupo_id";
        }

        $sql .= " ORDER BY a.fecha_creacion DESC LIMIT :limit";

        $query = $this->db->query($sql)
                         ->bind(':now', $now)
                         ->bind(':term', $term);

        if ($grupo_id) {
            $query->bind(':grupo_id', $grupo_id);
        }

        return $query->bind(':limit', $limit, PDO::PARAM_INT)
                     ->fetchAll();
    }

    /**
     * Verifica si un usuario puede editar un aviso
     */
    public function canEdit($aviso_id, $user_id) {
        $aviso = $this->getById($aviso_id);

        if (!$aviso) {
            return false;
        }

        // El autor puede editar si no está publicado
        if ($aviso['autor_id'] == $user_id && !$aviso['publicado']) {
            return true;
        }

        // Admin puede editar siempre
        if (is_admin()) {
            return true;
        }

        // Coordinador del grupo puede editar
        if ($aviso['grupo_id'] && is_group_coordinator($aviso['grupo_id'], $user_id)) {
            return true;
        }

        return false;
    }

    /**
     * Cuenta avisos por grupo
     */
    public function countByGroup($grupo_id, $status = 'published') {
        $now = date('Y-m-d H:i:s');

        switch ($status) {
            case 'published':
                $sql = "SELECT COUNT(*) FROM avisos
                       WHERE grupo_id = :grupo_id
                       AND publicado = 1
                       AND (fecha_inicio_publicacion IS NULL OR fecha_inicio_publicacion <= :now)
                       AND (fecha_fin_publicacion IS NULL OR fecha_fin_publicacion > :now)";
                break;

            case 'scheduled':
                $sql = "SELECT COUNT(*) FROM avisos
                       WHERE grupo_id = :grupo_id
                       AND fecha_inicio_publicacion > :now";
                break;

            case 'finished':
                $sql = "SELECT COUNT(*) FROM avisos
                       WHERE grupo_id = :grupo_id
                       AND fecha_fin_publicacion IS NOT NULL
                       AND fecha_fin_publicacion <= :now";
                break;

            default:
                $sql = "SELECT COUNT(*) FROM avisos WHERE grupo_id = :grupo_id";
        }

        $query = $this->db->query($sql)->bind(':grupo_id', $grupo_id);

        if (in_array($status, ['published', 'scheduled', 'finished'])) {
            $query->bind(':now', $now);
        }

        return $query->fetchColumn();
    }

    /**
     * Mueve avisos finalizados a históricos
     */
    public function moveToHistoricos($aviso_id) {
        $aviso = $this->getById($aviso_id);

        if (!$aviso) {
            return false;
        }

        // Crear entrada en históricos
        $this->db->query("INSERT INTO historicos (aviso_original_id, titulo, descripcion, grupo_id, creado_por, fecha_evento)
                         VALUES (:aviso_id, :titulo, :descripcion, :grupo_id, :autor_id, :fecha_evento)")
                ->bind(':aviso_id', $aviso_id)
                ->bind(':titulo', $aviso['titulo'])
                ->bind(':descripcion', $aviso['contenido'])
                ->bind(':grupo_id', $aviso['grupo_id'])
                ->bind(':autor_id', $aviso['autor_id'])
                ->bind(':fecha_evento', $aviso['fecha_fin_publicacion'])
                ->execute();

        return $this->db->lastInsertId();
    }
}
