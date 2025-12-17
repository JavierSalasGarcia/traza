<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$propuesta_model = new Propuesta();
$aviso_model = new Aviso();

// Filtros
$tipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : 'propuestas'; // propuestas, avisos
$categoria = isset($_GET['categoria']) ? sanitize($_GET['categoria']) : null;
$fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : null;
$fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : null;
$busqueda = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : null;

// Obtener datos según el tipo
$items = [];

if ($tipo === 'propuestas') {
    // Obtener propuestas completadas
    $query = "SELECT p.*,
                     u.nombre as autor_nombre,
                     g.nombre as grupo_nombre,
                     c.nombre as comision_nombre,
                     COUNT(DISTINCT pf.usuario_id) as total_firmas
              FROM propuestas p
              LEFT JOIN usuarios u ON p.autor_id = u.id
              LEFT JOIN grupos g ON p.grupo_id = g.id
              LEFT JOIN comisiones c ON p.comision_id = c.id
              LEFT JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
              WHERE p.estado = 'completada'";

    if ($categoria) {
        $query .= " AND p.categoria = :categoria";
    }

    if ($fecha_desde) {
        $query .= " AND p.fecha_completada >= :fecha_desde";
    }

    if ($fecha_hasta) {
        $query .= " AND p.fecha_completada <= :fecha_hasta";
    }

    if ($busqueda) {
        $query .= " AND (p.titulo LIKE :busqueda OR p.descripcion LIKE :busqueda)";
    }

    $query .= " GROUP BY p.id ORDER BY p.fecha_completada DESC";

    $db = Database::getInstance();
    $db->query($query);

    if ($categoria) {
        $db->bind(':categoria', $categoria);
    }
    if ($fecha_desde) {
        $db->bind(':fecha_desde', $fecha_desde . ' 00:00:00');
    }
    if ($fecha_hasta) {
        $db->bind(':fecha_hasta', $fecha_hasta . ' 23:59:59');
    }
    if ($busqueda) {
        $db->bind(':busqueda', '%' . $busqueda . '%');
    }

    $items = $db->fetchAll();

} else {
    // Obtener avisos del histórico
    $query = "SELECT a.*,
                     u.nombre as autor_nombre,
                     u.imagen_perfil as autor_imagen,
                     g.nombre as grupo_nombre
              FROM avisos_historicos a
              LEFT JOIN usuarios u ON a.autor_id = u.id
              LEFT JOIN grupos g ON a.grupo_id = g.id
              WHERE 1=1";

    if ($categoria) {
        $query .= " AND a.categoria = :categoria";
    }

    if ($fecha_desde) {
        $query .= " AND a.fecha_archivado >= :fecha_desde";
    }

    if ($fecha_hasta) {
        $query .= " AND a.fecha_archivado <= :fecha_hasta";
    }

    if ($busqueda) {
        $query .= " AND (a.titulo LIKE :busqueda OR a.contenido LIKE :busqueda)";
    }

    $query .= " ORDER BY a.fecha_archivado DESC";

    $db = Database::getInstance();
    $db->query($query);

    if ($categoria) {
        $db->bind(':categoria', $categoria);
    }
    if ($fecha_desde) {
        $db->bind(':fecha_desde', $fecha_desde . ' 00:00:00');
    }
    if ($fecha_hasta) {
        $db->bind(':fecha_hasta', $fecha_hasta . ' 23:59:59');
    }
    if ($busqueda) {
        $db->bind(':busqueda', '%' . $busqueda . '%');
    }

    $items = $db->fetchAll();
}

// Categorías
$categorias_propuestas = [
    'academico' => 'Académico',
    'infraestructura' => 'Infraestructura',
    'servicios' => 'Servicios',
    'social' => 'Social',
    'ambiental' => 'Ambiental',
    'tecnologia' => 'Tecnología',
    'otro' => 'Otro'
];

$categorias_avisos = [
    'general' => 'General',
    'academico' => 'Académico',
    'administrativo' => 'Administrativo',
    'evento' => 'Evento',
    'urgente' => 'Urgente'
];

$categorias = $tipo === 'propuestas' ? $categorias_propuestas : $categorias_avisos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Históricos - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../core/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-archive"></i> Históricos</h1>
                    <p>Archivo de propuestas completadas y avisos pasados</p>
                </div>
            </div>

            <!-- Tabs de tipo -->
            <div class="view-tabs">
                <a href="<?= base_url('public/historicos.php?tipo=propuestas') ?>"
                   class="tab <?= $tipo === 'propuestas' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Propuestas Completadas
                </a>
                <a href="<?= base_url('public/historicos.php?tipo=avisos') ?>"
                   class="tab <?= $tipo === 'avisos' ? 'active' : '' ?>">
                    <i class="fas fa-file-archive"></i> Avisos Archivados
                </a>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <input type="hidden" name="tipo" value="<?= $tipo ?>">

                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="busqueda">Buscar</label>
                            <input type="text"
                                   id="busqueda"
                                   name="busqueda"
                                   class="form-control"
                                   placeholder="Buscar por título o contenido..."
                                   value="<?= sanitize($busqueda ?? '') ?>">
                        </div>

                        <div class="filter-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria" class="form-control">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $categoria === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="fecha_desde">Desde</label>
                            <input type="date"
                                   id="fecha_desde"
                                   name="fecha_desde"
                                   class="form-control"
                                   value="<?= sanitize($fecha_desde ?? '') ?>">
                        </div>

                        <div class="filter-group">
                            <label for="fecha_hasta">Hasta</label>
                            <input type="date"
                                   id="fecha_hasta"
                                   name="fecha_hasta"
                                   class="form-control"
                                   value="<?= sanitize($fecha_hasta ?? '') ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?= base_url('public/historicos.php?tipo=' . $tipo) ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Resultados -->
            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>No hay registros</h3>
                    <p>
                        <?php if ($tipo === 'propuestas'): ?>
                            No se encontraron propuestas completadas con estos filtros
                        <?php else: ?>
                            No se encontraron avisos archivados con estos filtros
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="results-header">
                    <span><?= count($items) ?> resultado(s) encontrado(s)</span>
                </div>

                <div class="historical-list">
                    <?php foreach ($items as $item): ?>
                        <?php if ($tipo === 'propuestas'): ?>
                            <!-- Propuesta completada -->
                            <div class="historical-card propuesta-card">
                                <div class="card-header">
                                    <div class="header-left">
                                        <span class="category-badge">
                                            <?= $categorias[$item['categoria']] ?? $item['categoria'] ?>
                                        </span>
                                        <?php if ($item['grupo_nombre']): ?>
                                            <span class="group-badge">
                                                <i class="fas fa-users"></i>
                                                <?= sanitize($item['grupo_nombre']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="completion-badge">
                                        <i class="fas fa-check-circle"></i>
                                        Completada
                                    </div>
                                </div>

                                <h3 class="card-title">
                                    <a href="<?= base_url('public/view-proposal.php?id=' . $item['id']) ?>">
                                        <?= sanitize($item['titulo']) ?>
                                    </a>
                                </h3>

                                <p class="card-description">
                                    <?= truncate(sanitize($item['descripcion']), 250) ?>
                                </p>

                                <div class="card-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>Por <?= sanitize($item['autor_nombre']) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Completada el <?= format_date($item['fecha_completada']) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-signature"></i>
                                        <span><?= number_format($item['total_firmas']) ?> firmas</span>
                                    </div>
                                    <?php if ($item['comision_nombre']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-users-cog"></i>
                                            <span><?= sanitize($item['comision_nombre']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($item['evidencias']): ?>
                                    <div class="evidencias-preview">
                                        <div class="evidencias-header">
                                            <i class="fas fa-paperclip"></i>
                                            <strong>Evidencias:</strong>
                                        </div>
                                        <div class="evidencias-content">
                                            <?= truncate(sanitize($item['evidencias']), 200) ?>
                                        </div>
                                        <a href="<?= base_url('public/view-proposal.php?id=' . $item['id']) ?>" class="evidencias-link">
                                            Ver evidencias completas <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <!-- Aviso archivado -->
                            <div class="historical-card aviso-card">
                                <div class="card-header">
                                    <div class="header-left">
                                        <span class="category-badge">
                                            <?= $categorias[$item['categoria']] ?? $item['categoria'] ?>
                                        </span>
                                        <?php if ($item['grupo_nombre']): ?>
                                            <span class="group-badge">
                                                <i class="fas fa-users"></i>
                                                <?= sanitize($item['grupo_nombre']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="archive-badge">
                                        <i class="fas fa-archive"></i>
                                        Archivado
                                    </div>
                                </div>

                                <h3 class="card-title">
                                    <?= sanitize($item['titulo']) ?>
                                </h3>

                                <p class="card-description">
                                    <?= truncate(sanitize($item['contenido']), 250) ?>
                                </p>

                                <div class="card-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>Por <?= sanitize($item['autor_nombre']) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Publicado el <?= format_date($item['fecha_creacion']) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-archive"></i>
                                        <span>Archivado el <?= format_date($item['fecha_archivado']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <style>
        .main-content {
            padding: var(--space-8) 0;
        }

        .page-header {
            margin-bottom: var(--space-8);
        }

        .page-header h1 {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-2);
        }

        .page-header p {
            color: var(--color-gray-400);
        }

        .view-tabs {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-6);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            color: var(--color-gray-400);
            text-decoration: none;
            font-weight: var(--font-weight-medium);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all var(--transition-fast);
        }

        .tab:hover {
            color: var(--color-white);
        }

        .tab.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }

        .filters-section {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .filter-actions {
            display: flex;
            gap: var(--space-3);
        }

        .results-header {
            padding: var(--space-3) 0;
            margin-bottom: var(--space-4);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .historical-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .historical-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            transition: all var(--transition-normal);
        }

        .historical-card:hover {
            border-color: var(--color-primary);
            box-shadow: 0 10px 30px rgba(0, 153, 255, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-4);
        }

        .header-left {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
        }

        .category-badge, .group-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }

        .category-badge {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .group-badge {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-gray-400);
        }

        .completion-badge, .archive-badge {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
        }

        .completion-badge {
            background: rgba(0, 255, 136, 0.1);
            color: var(--color-success);
            border: 1px solid rgba(0, 255, 136, 0.3);
        }

        .archive-badge {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-gray-400);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-title {
            font-size: var(--font-size-2xl);
            margin-bottom: var(--space-3);
        }

        .card-title a {
            color: var(--color-white);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .card-title a:hover {
            color: var(--color-primary);
        }

        .card-description {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-4);
            padding: var(--space-3) 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .evidencias-preview {
            margin-top: var(--space-4);
            padding: var(--space-4);
            background: rgba(0, 255, 136, 0.05);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: var(--radius-lg);
        }

        .evidencias-header {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-2);
            color: var(--color-success);
            font-size: var(--font-size-sm);
        }

        .evidencias-content {
            color: var(--color-gray-300);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-3);
        }

        .evidencias-link {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-primary);
            text-decoration: none;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
        }

        .evidencias-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: var(--space-16);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
        }

        .empty-state i {
            font-size: var(--font-size-6xl);
            color: var(--color-gray-600);
            margin-bottom: var(--space-4);
        }

        .empty-state h3 {
            margin-bottom: var(--space-2);
        }

        .empty-state p {
            color: var(--color-gray-400);
            margin: 0;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .btn {
                width: 100%;
            }

            .view-tabs {
                overflow-x: auto;
            }

            .card-meta {
                flex-direction: column;
                gap: var(--space-2);
            }
        }
    </style>
</body>
</html>
