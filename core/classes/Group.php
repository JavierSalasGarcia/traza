<?php
/**
 * TrazaFI - Clase Group
 * Modelo para gestión de grupos
 */

class Group {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtiene un grupo por ID
     */
    public function getById($id) {
        return $this->db->query("SELECT * FROM grupos WHERE id = :id AND activo = 1")
                       ->bind(':id', $id)
                       ->fetch();
    }

    /**
     * Obtiene un grupo por slug
     */
    public function getBySlug($slug) {
        return $this->db->query("SELECT * FROM grupos WHERE slug = :slug AND activo = 1")
                       ->bind(':slug', $slug)
                       ->fetch();
    }

    /**
     * Obtiene todos los grupos activos
     */
    public function getAll($tipo = null) {
        $sql = "SELECT * FROM grupos WHERE activo = 1";

        if ($tipo) {
            $sql .= " AND tipo_grupo = :tipo";
        }

        $sql .= " ORDER BY nombre";

        $query = $this->db->query($sql);

        if ($tipo) {
            $query->bind(':tipo', $tipo);
        }

        return $query->fetchAll();
    }

    /**
     * Obtiene grupos por tipo
     */
    public function getByType($tipo) {
        return $this->db->query("SELECT * FROM grupos
                                WHERE tipo_grupo = :tipo AND activo = 1
                                ORDER BY nombre")
                       ->bind(':tipo', $tipo)
                       ->fetchAll();
    }

    /**
     * Obtiene miembros de un grupo
     */
    public function getMembers($grupo_id, $estado = 'aprobado') {
        $sql = "SELECT u.id, u.nombre, u.apellidos, u.email, u.imagen_perfil,
                       gm.es_coordinador, gm.estado, gm.fecha_aprobacion,
                       r.nombre as rol_nombre
                FROM usuarios u
                INNER JOIN grupo_miembros gm ON u.id = gm.usuario_id
                LEFT JOIN roles r ON gm.rol_id = r.id
                WHERE gm.grupo_id = :grupo_id";

        if ($estado) {
            $sql .= " AND gm.estado = :estado";
        }

        $sql .= " ORDER BY gm.es_coordinador DESC, u.nombre";

        $query = $this->db->query($sql)->bind(':grupo_id', $grupo_id);

        if ($estado) {
            $query->bind(':estado', $estado);
        }

        return $query->fetchAll();
    }

    /**
     * Cuenta miembros de un grupo
     */
    public function countMembers($grupo_id, $estado = 'aprobado') {
        $sql = "SELECT COUNT(*) FROM grupo_miembros
                WHERE grupo_id = :grupo_id";

        if ($estado) {
            $sql .= " AND estado = :estado";
        }

        $query = $this->db->query($sql)->bind(':grupo_id', $grupo_id);

        if ($estado) {
            $query->bind(':estado', $estado);
        }

        return $query->fetchColumn();
    }

    /**
     * Obtiene solicitudes pendientes de un grupo
     */
    public function getPendingRequests($grupo_id) {
        return $this->db->query("SELECT u.id, u.nombre, u.apellidos, u.email,
                                        u.imagen_perfil, gm.fecha_solicitud, gm.id as membership_id
                                FROM usuarios u
                                INNER JOIN grupo_miembros gm ON u.id = gm.usuario_id
                                WHERE gm.grupo_id = :grupo_id
                                AND gm.estado = 'pendiente'
                                ORDER BY gm.fecha_solicitud")
                       ->bind(':grupo_id', $grupo_id)
                       ->fetchAll();
    }

    /**
     * Solicita ingreso a un grupo
     */
    public function requestJoin($grupo_id, $usuario_id) {
        // Verificar si ya existe una solicitud o membresía
        $exists = $this->db->query("SELECT id, estado FROM grupo_miembros
                                    WHERE grupo_id = :grupo_id AND usuario_id = :usuario_id")
                          ->bind(':grupo_id', $grupo_id)
                          ->bind(':usuario_id', $usuario_id)
                          ->fetch();

        if ($exists) {
            if ($exists['estado'] === 'aprobado') {
                return ['success' => false, 'message' => 'Ya eres miembro de este grupo'];
            } elseif ($exists['estado'] === 'pendiente') {
                return ['success' => false, 'message' => 'Ya tienes una solicitud pendiente'];
            } elseif ($exists['estado'] === 'rechazado') {
                // Actualizar solicitud rechazada a pendiente
                $this->db->query("UPDATE grupo_miembros
                                 SET estado = 'pendiente', fecha_solicitud = NOW()
                                 WHERE id = :id")
                        ->bind(':id', $exists['id'])
                        ->execute();
                return ['success' => true, 'message' => 'Solicitud enviada nuevamente'];
            }
        }

        // Crear nueva solicitud
        $this->db->query("INSERT INTO grupo_miembros (grupo_id, usuario_id, estado, fecha_solicitud)
                         VALUES (:grupo_id, :usuario_id, 'pendiente', NOW())")
                ->bind(':grupo_id', $grupo_id)
                ->bind(':usuario_id', $usuario_id)
                ->execute();

        return ['success' => true, 'message' => 'Solicitud enviada correctamente'];
    }

    /**
     * Aprueba una solicitud de ingreso
     */
    public function approveMembership($membership_id, $aprobador_id, $rol_id = null) {
        // Si no se especifica rol, asignar rol de "Miembro" por defecto
        if (!$rol_id) {
            $rol_id = $this->db->query("SELECT id FROM roles WHERE clave = 'miembro'")
                              ->fetchColumn();
        }

        return $this->db->query("UPDATE grupo_miembros
                                SET estado = 'aprobado',
                                    fecha_aprobacion = NOW(),
                                    aprobado_por = :aprobador_id,
                                    rol_id = :rol_id
                                WHERE id = :id")
                       ->bind(':id', $membership_id)
                       ->bind(':aprobador_id', $aprobador_id)
                       ->bind(':rol_id', $rol_id)
                       ->execute();
    }

    /**
     * Rechaza una solicitud de ingreso
     */
    public function rejectMembership($membership_id) {
        return $this->db->query("UPDATE grupo_miembros
                                SET estado = 'rechazado'
                                WHERE id = :id")
                       ->bind(':id', $membership_id)
                       ->execute();
    }

    /**
     * Elimina un miembro del grupo
     */
    public function removeMember($grupo_id, $usuario_id) {
        return $this->db->query("DELETE FROM grupo_miembros
                                WHERE grupo_id = :grupo_id AND usuario_id = :usuario_id")
                       ->bind(':grupo_id', $grupo_id)
                       ->bind(':usuario_id', $usuario_id)
                       ->execute();
    }

    /**
     * Asigna rol de coordinador a un usuario
     */
    public function setCoordinator($grupo_id, $usuario_id, $is_coordinator = true) {
        return $this->db->query("UPDATE grupo_miembros
                                SET es_coordinador = :is_coordinator
                                WHERE grupo_id = :grupo_id AND usuario_id = :usuario_id")
                       ->bind(':grupo_id', $grupo_id)
                       ->bind(':usuario_id', $usuario_id)
                       ->bind(':is_coordinator', $is_coordinator ? 1 : 0)
                       ->execute();
    }

    /**
     * Actualiza el rol de un miembro
     */
    public function updateMemberRole($grupo_id, $usuario_id, $rol_id) {
        return $this->db->query("UPDATE grupo_miembros
                                SET rol_id = :rol_id
                                WHERE grupo_id = :grupo_id AND usuario_id = :usuario_id")
                       ->bind(':grupo_id', $grupo_id)
                       ->bind(':usuario_id', $usuario_id)
                       ->bind(':rol_id', $rol_id)
                       ->execute();
    }

    /**
     * Obtiene coordinadores de un grupo
     */
    public function getCoordinators($grupo_id) {
        return $this->db->query("SELECT u.id, u.nombre, u.apellidos, u.email, u.imagen_perfil
                                FROM usuarios u
                                INNER JOIN grupo_miembros gm ON u.id = gm.usuario_id
                                WHERE gm.grupo_id = :grupo_id
                                AND gm.es_coordinador = 1
                                AND gm.estado = 'aprobado'
                                ORDER BY u.nombre")
                       ->bind(':grupo_id', $grupo_id)
                       ->fetchAll();
    }

    /**
     * Busca grupos por término
     */
    public function search($term, $limit = 20) {
        $term = '%' . $term . '%';
        return $this->db->query("SELECT * FROM grupos
                                WHERE (nombre LIKE :term OR descripcion LIKE :term)
                                AND activo = 1
                                ORDER BY nombre
                                LIMIT :limit")
                       ->bind(':term', $term)
                       ->bind(':limit', $limit, PDO::PARAM_INT)
                       ->fetchAll();
    }

    /**
     * Obtiene grupos con su información de membresía para un usuario
     */
    public function getWithMembershipInfo($usuario_id, $tipo = null) {
        $sql = "SELECT g.*,
                       gm.estado as membership_estado,
                       gm.es_coordinador,
                       (SELECT COUNT(*) FROM grupo_miembros WHERE grupo_id = g.id AND estado = 'aprobado') as total_miembros
                FROM grupos g
                LEFT JOIN grupo_miembros gm ON g.id = gm.grupo_id AND gm.usuario_id = :usuario_id
                WHERE g.activo = 1";

        if ($tipo) {
            $sql .= " AND g.tipo_grupo = :tipo";
        }

        $sql .= " ORDER BY g.nombre";

        $query = $this->db->query($sql)->bind(':usuario_id', $usuario_id);

        if ($tipo) {
            $query->bind(':tipo', $tipo);
        }

        return $query->fetchAll();
    }

    /**
     * Crea un nuevo grupo
     */
    public function create($data) {
        $slug = slugify($data['nombre']);

        // Verificar que el slug sea único
        $exists = $this->getBySlug($slug);
        if ($exists) {
            $slug = $slug . '-' . uniqid();
        }

        $this->db->query("INSERT INTO grupos (nombre, slug, descripcion, tipo_grupo, imagen_portada)
                         VALUES (:nombre, :slug, :descripcion, :tipo, :imagen)")
                ->bind(':nombre', $data['nombre'])
                ->bind(':slug', $slug)
                ->bind(':descripcion', $data['descripcion'] ?? null)
                ->bind(':tipo', $data['tipo_grupo'])
                ->bind(':imagen', $data['imagen_portada'] ?? null)
                ->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Actualiza información de un grupo
     */
    public function update($grupo_id, $data) {
        $allowed = ['nombre', 'descripcion', 'imagen_portada'];
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

        // Si se actualiza el nombre, actualizar también el slug
        if (isset($data['nombre'])) {
            $fields[] = "slug = :slug";
            $values['slug'] = slugify($data['nombre']);
        }

        $sql = "UPDATE grupos SET " . implode(', ', $fields) . " WHERE id = :id";
        $query = $this->db->query($sql);

        foreach ($values as $key => $value) {
            $query->bind(":{$key}", $value);
        }

        return $query->bind(':id', $grupo_id)->execute();
    }

    /**
     * Obtiene estadísticas de un grupo
     */
    public function getStats($grupo_id) {
        // Total de miembros
        $miembros = $this->countMembers($grupo_id, 'aprobado');

        // Solicitudes pendientes
        $pendientes = $this->countMembers($grupo_id, 'pendiente');

        // Avisos publicados
        $avisos = $this->db->query("SELECT COUNT(*) FROM avisos
                                    WHERE grupo_id = :grupo_id AND publicado = 1")
                          ->bind(':grupo_id', $grupo_id)
                          ->fetchColumn();

        // Propuestas del grupo
        $propuestas = $this->db->query("SELECT COUNT(*) FROM propuestas
                                       WHERE grupo_asignado = :grupo_id")
                              ->bind(':grupo_id', $grupo_id)
                              ->fetchColumn();

        return [
            'miembros' => $miembros,
            'pendientes' => $pendientes,
            'avisos' => $avisos,
            'propuestas' => $propuestas
        ];
    }
}
