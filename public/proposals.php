<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$propuesta_model = new Propuesta();

// Filtros
$estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : null;
$categoria = isset($_GET['categoria']) ? sanitize($_GET['categoria']) : null;
$vista = isset($_GET['vista']) ? sanitize($_GET['vista']) : 'todas'; // todas, mis_propuestas, firmadas

// Obtener propuestas según la vista
if ($vista === 'mis_propuestas') {
    $propuestas = $propuesta_model->getUserProposals($user_id);
} elseif ($vista === 'firmadas') {
    $propuestas = $propuesta_model->getSignedByUser($user_id);
} else {
    $propuestas = $propuesta_model->getByEstado($estado);
}

// Filtrar por categoría si se especifica
if ($categoria && $propuestas) {
    $propuestas = array_filter($propuestas, function($p) use ($categoria) {
        return $p['categoria'] === $categoria;
    });
}

// Categorías disponibles
$categorias = [
    'academico' => 'Académico',
    'infraestructura' => 'Infraestructura',
    'servicios' => 'Servicios',
    'social' => 'Social',
    'ambiental' => 'Ambiental',
    'tecnologia' => 'Tecnología',
    'otro' => 'Otro'
];

// Estados
$estados = [
    'votacion' => 'En Votación',
    'revision' => 'En Revisión',
    'en_progreso' => 'En Progreso',
    'completada' => 'Completada',
    'rechazada' => 'Rechazada'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propuestas Comunitarias - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../core/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1><i class="fas fa-lightbulb"></i> Propuestas Comunitarias</h1>
                        <p>Propón ideas, firma iniciativas y haz la diferencia</p>
                    </div>
                    <a href="<?= base_url('public/create-proposal.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Propuesta
                    </a>
                </div>
            </div>

            <!-- Tabs de vista -->
            <div class="view-tabs">
                <a href="<?= base_url('public/proposals.php?vista=todas') ?>"
                   class="tab <?= $vista === 'todas' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Todas
                </a>
                <a href="<?= base_url('public/proposals.php?vista=mis_propuestas') ?>"
                   class="tab <?= $vista === 'mis_propuestas' ? 'active' : '' ?>">
                    <i class="fas fa-user-edit"></i> Mis Propuestas
                </a>
                <a href="<?= base_url('public/proposals.php?vista=firmadas') ?>"
                   class="tab <?= $vista === 'firmadas' ? 'active' : '' ?>">
                    <i class="fas fa-signature"></i> Firmadas
                </a>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <div class="filter-group">
                    <label>Estado:</label>
                    <div class="filter-buttons">
                        <a href="<?= base_url('public/proposals.php?vista=' . $vista) ?>"
                           class="filter-btn <?= !$estado ? 'active' : '' ?>">
                            Todos
                        </a>
                        <?php foreach ($estados as $key => $label): ?>
                            <a href="<?= base_url('public/proposals.php?vista=' . $vista . '&estado=' . $key . ($categoria ? '&categoria=' . $categoria : '')) ?>"
                               class="filter-btn <?= $estado === $key ? 'active' : '' ?>">
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Categoría:</label>
                    <div class="filter-buttons">
                        <a href="<?= base_url('public/proposals.php?vista=' . $vista . ($estado ? '&estado=' . $estado : '')) ?>"
                           class="filter-btn <?= !$categoria ? 'active' : '' ?>">
                            Todas
                        </a>
                        <?php foreach ($categorias as $key => $label): ?>
                            <a href="<?= base_url('public/proposals.php?vista=' . $vista . ($estado ? '&estado=' . $estado : '') . '&categoria=' . $key) ?>"
                               class="filter-btn <?= $categoria === $key ? 'active' : '' ?>">
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Lista de propuestas -->
            <?php if (empty($propuestas)): ?>
                <div class="empty-state">
                    <i class="fas fa-lightbulb"></i>
                    <h3>No hay propuestas</h3>
                    <p>
                        <?php if ($vista === 'mis_propuestas'): ?>
                            Aún no has creado ninguna propuesta
                        <?php elseif ($vista === 'firmadas'): ?>
                            Aún no has firmado ninguna propuesta
                        <?php else: ?>
                            No hay propuestas disponibles con estos filtros
                        <?php endif; ?>
                    </p>
                    <?php if ($vista !== 'todas'): ?>
                        <a href="<?= base_url('public/proposals.php') ?>" class="btn btn-outline">
                            Ver todas las propuestas
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="proposals-list">
                    <?php foreach ($propuestas as $propuesta): ?>
                        <?php
                        $progreso = ($propuesta['total_firmas'] / $propuesta['umbral_firmas']) * 100;
                        $progreso = min($progreso, 100);
                        $alcanzado = $propuesta['total_firmas'] >= $propuesta['umbral_firmas'];
                        ?>
                        <div class="proposal-card">
                            <div class="proposal-header">
                                <div class="proposal-meta">
                                    <span class="category-badge badge-<?= $propuesta['categoria'] ?>">
                                        <?= $categorias[$propuesta['categoria']] ?? $propuesta['categoria'] ?>
                                    </span>
                                    <span class="estado-badge estado-<?= $propuesta['estado'] ?>">
                                        <?= $estados[$propuesta['estado']] ?>
                                    </span>
                                    <?php if ($propuesta['grupo_nombre']): ?>
                                        <span class="group-badge">
                                            <i class="fas fa-users"></i> <?= sanitize($propuesta['grupo_nombre']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <h3 class="proposal-title">
                                <a href="<?= base_url('public/view-proposal.php?id=' . $propuesta['id']) ?>">
                                    <?= sanitize($propuesta['titulo']) ?>
                                </a>
                            </h3>

                            <p class="proposal-description">
                                <?= truncate(sanitize($propuesta['descripcion']), 200) ?>
                            </p>

                            <div class="proposal-author">
                                <i class="fas fa-user"></i>
                                <span>Por <?= sanitize($propuesta['autor_nombre']) ?></span>
                                <span class="separator">•</span>
                                <i class="fas fa-clock"></i>
                                <span><?= time_ago($propuesta['fecha_creacion']) ?></span>
                            </div>

                            <?php if ($propuesta['estado'] === 'votacion'): ?>
                                <div class="signature-progress">
                                    <div class="progress-header">
                                        <span class="progress-label">
                                            <i class="fas fa-signature"></i>
                                            <?= number_format($propuesta['total_firmas']) ?> de <?= number_format($propuesta['umbral_firmas']) ?> firmas
                                        </span>
                                        <span class="progress-percentage"><?= number_format($progreso, 1) ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?= $alcanzado ? 'completed' : '' ?>"
                                             style="width: <?= $progreso ?>%"></div>
                                    </div>
                                    <?php if ($alcanzado): ?>
                                        <div class="threshold-reached">
                                            <i class="fas fa-check-circle"></i>
                                            ¡Meta alcanzada! Esta propuesta pasará a revisión
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="proposal-stats">
                                    <div class="stat">
                                        <i class="fas fa-signature"></i>
                                        <span><?= number_format($propuesta['total_firmas']) ?> firmas</span>
                                    </div>
                                    <?php if ($propuesta['comision_nombre']): ?>
                                        <div class="stat">
                                            <i class="fas fa-users-cog"></i>
                                            <span><?= sanitize($propuesta['comision_nombre']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="proposal-actions">
                                <a href="<?= base_url('public/view-proposal.php?id=' . $propuesta['id']) ?>"
                                   class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> Ver Detalles
                                </a>
                            </div>
                        </div>
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

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-6);
        }

        .header-content h1 {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-2);
        }

        .header-content p {
            color: var(--color-gray-400);
            margin: 0;
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
            margin-bottom: var(--space-8);
        }

        .filter-group {
            margin-bottom: var(--space-4);
        }

        .filter-group:last-child {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--space-3);
            color: var(--color-gray-300);
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .filter-btn {
            padding: var(--space-2) var(--space-4);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
            text-decoration: none;
            font-size: var(--font-size-sm);
            transition: all var(--transition-fast);
        }

        .filter-btn:hover {
            background: rgba(0, 153, 255, 0.1);
            border-color: var(--color-primary);
            color: var(--color-white);
        }

        .filter-btn.active {
            background: var(--gradient-primary);
            border-color: transparent;
            color: var(--color-white);
        }

        .proposals-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .proposal-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            transition: all var(--transition-normal);
        }

        .proposal-card:hover {
            border-color: var(--color-primary);
            box-shadow: 0 10px 30px rgba(0, 153, 255, 0.1);
        }

        .proposal-header {
            margin-bottom: var(--space-4);
        }

        .proposal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .category-badge, .estado-badge, .group-badge {
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

        .estado-badge {
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-gray-300);
        }

        .estado-votacion {
            background: rgba(0, 255, 170, 0.2);
            color: var(--color-secondary);
        }

        .estado-revision {
            background: rgba(255, 170, 0, 0.2);
            color: var(--color-warning);
        }

        .estado-en_progreso {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .estado-completada {
            background: rgba(0, 255, 136, 0.2);
            color: var(--color-success);
        }

        .estado-rechazada {
            background: rgba(255, 68, 68, 0.2);
            color: var(--color-error);
        }

        .group-badge {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-gray-400);
        }

        .proposal-title {
            font-size: var(--font-size-2xl);
            margin-bottom: var(--space-3);
        }

        .proposal-title a {
            color: var(--color-white);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .proposal-title a:hover {
            color: var(--color-primary);
        }

        .proposal-description {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
        }

        .proposal-author {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
            margin-bottom: var(--space-4);
        }

        .separator {
            color: var(--color-gray-600);
        }

        .signature-progress {
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-2);
            font-size: var(--font-size-sm);
        }

        .progress-label {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-300);
        }

        .progress-percentage {
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
        }

        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-full);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            transition: width var(--transition-normal);
        }

        .progress-fill.completed {
            background: var(--gradient-secondary);
        }

        .threshold-reached {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-top: var(--space-3);
            padding: var(--space-3);
            background: rgba(0, 255, 170, 0.1);
            border: 1px solid rgba(0, 255, 170, 0.3);
            border-radius: var(--radius-lg);
            color: var(--color-secondary);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
        }

        .proposal-stats {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-3) 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: var(--space-4);
        }

        .stat {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .proposal-actions {
            display: flex;
            gap: var(--space-3);
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
            margin-bottom: var(--space-6);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .view-tabs {
                overflow-x: auto;
            }

            .filter-buttons {
                max-height: 200px;
                overflow-y: auto;
            }
        }
    </style>
</body>
</html>
