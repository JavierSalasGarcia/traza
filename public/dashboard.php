<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$user_model = new User();
$aviso_model = new Aviso();
$stats = $user_model->getStats($user['id']);
$grupos = get_user_groups();

// Obtener avisos generales y de grupos del usuario
$avisos_generales = $aviso_model->getGeneralAvisos(5);
$avisos_grupos = [];

foreach ($grupos as $grupo) {
    $avisos_grupo = $aviso_model->getPublished($grupo['id'], 3);
    if (!empty($avisos_grupo)) {
        $avisos_grupos = array_merge($avisos_grupos, $avisos_grupo);
    }
}

// Ordenar avisos de grupos por fecha
usort($avisos_grupos, function($a, $b) {
    return strtotime($b['fecha_creacion']) - strtotime($a['fecha_creacion']);
});

$avisos_grupos = array_slice($avisos_grupos, 0, 5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../core/includes/pwa-head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../core/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <?php $flash_messages = get_flash(); ?>
            <?php foreach ($flash_messages as $flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <i class="fas fa-info-circle"></i>
                    <?= sanitize($flash['message']) ?>
                </div>
            <?php endforeach; ?>

            <!-- PWA Install Button (se muestra automáticamente si es posible instalar) -->
            <button id="pwa-install-btn">
                <i class="fas fa-download"></i> Instalar TrazaFI
            </button>

            <div class="page-header">
                <div>
                    <h1>Bienvenido, <?= sanitize($user['nombre']) ?></h1>
                    <p>Red Social Académica - Facultad de Ingeniería UAEMEX</p>
                </div>
                <?php if (has_permission('puede_crear_avisos') || is_admin()): ?>
                    <a href="<?= base_url('public/create-aviso.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Crear Aviso
                    </a>
                <?php endif; ?>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['grupos'] ?></div>
                        <div class="stat-label">Mis Grupos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['propuestas'] ?></div>
                        <div class="stat-label">Propuestas Creadas</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-signature"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['firmas'] ?></div>
                        <div class="stat-label">Propuestas Firmadas</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['avisos'] ?></div>
                        <div class="stat-label">Avisos Publicados</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-main">
                    <?php if (!empty($avisos_generales)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h2><i class="fas fa-bullhorn"></i> Avisos Generales</h2>
                            </div>
                            <div class="card-body">
                                <div class="avisos-list">
                                    <?php foreach ($avisos_generales as $aviso): ?>
                                        <div class="aviso-item">
                                            <div class="aviso-item-header">
                                                <h3>
                                                    <a href="<?= base_url('public/view-aviso.php?id=' . $aviso['id']) ?>">
                                                        <?= sanitize($aviso['titulo']) ?>
                                                    </a>
                                                </h3>
                                                <?php if ($aviso['destacado']): ?>
                                                    <span class="badge-destacado">
                                                        <i class="fas fa-star"></i> Destacado
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="aviso-excerpt"><?= truncate(sanitize($aviso['contenido']), 150) ?></p>
                                            <div class="aviso-item-footer">
                                                <div class="aviso-author-small">
                                                    <i class="fas fa-user"></i>
                                                    <?= sanitize($aviso['nombre'] . ' ' . $aviso['apellidos']) ?>
                                                </div>
                                                <div class="aviso-date-small">
                                                    <i class="fas fa-clock"></i>
                                                    <?= time_ago($aviso['fecha_creacion']) ?>
                                                </div>
                                                <div class="aviso-stats-small">
                                                    <span><i class="far fa-heart"></i> <?= $aviso['total_likes'] ?></span>
                                                    <span><i class="far fa-comment"></i> <?= $aviso['total_comentarios'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($avisos_grupos)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h2><i class="fas fa-users"></i> Avisos de Mis Grupos</h2>
                            </div>
                            <div class="card-body">
                                <div class="avisos-list">
                                    <?php foreach ($avisos_grupos as $aviso): ?>
                                        <div class="aviso-item">
                                            <div class="aviso-item-header">
                                                <h3>
                                                    <a href="<?= base_url('public/view-aviso.php?id=' . $aviso['id']) ?>">
                                                        <?= sanitize($aviso['titulo']) ?>
                                                    </a>
                                                </h3>
                                                <span class="badge-group">
                                                    <?= sanitize($aviso['grupo_nombre']) ?>
                                                </span>
                                            </div>
                                            <p class="aviso-excerpt"><?= truncate(sanitize($aviso['contenido']), 150) ?></p>
                                            <div class="aviso-item-footer">
                                                <div class="aviso-author-small">
                                                    <i class="fas fa-user"></i>
                                                    <?= sanitize($aviso['nombre'] . ' ' . $aviso['apellidos']) ?>
                                                </div>
                                                <div class="aviso-date-small">
                                                    <i class="fas fa-clock"></i>
                                                    <?= time_ago($aviso['fecha_creacion']) ?>
                                                </div>
                                                <div class="aviso-stats-small">
                                                    <span><i class="far fa-heart"></i> <?= $aviso['total_likes'] ?></span>
                                                    <span><i class="far fa-comment"></i> <?= $aviso['total_comentarios'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($avisos_generales) && empty($avisos_grupos)): ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No hay avisos disponibles</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-sidebar">
                    <div class="card">
                        <div class="card-header">
                            <h3>Mis Grupos</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($grupos)): ?>
                                <div class="empty-state-small">
                                    <p>No perteneces a ningún grupo</p>
                                    <a href="<?= base_url('public/groups.php') ?>" class="btn btn-sm btn-primary">Explorar Grupos</a>
                                </div>
                            <?php else: ?>
                                <ul class="group-list">
                                    <?php foreach (array_slice($grupos, 0, 5) as $grupo): ?>
                                        <li class="group-item">
                                            <a href="<?= base_url('public/group.php?id=' . $grupo['id']) ?>">
                                                <i class="fas fa-folder"></i>
                                                <?= sanitize($grupo['nombre']) ?>
                                                <?php if ($grupo['es_coordinador']): ?>
                                                    <span class="badge">Coordinador</span>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (count($grupos) > 5): ?>
                                    <a href="<?= base_url('public/my-groups.php') ?>" class="btn btn-sm btn-outline btn-block" style="margin-top: var(--space-4);">
                                        Ver Todos
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .main-content {
            padding: var(--space-8) 0;
        }

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .page-header h1 {
            margin-bottom: var(--space-2);
        }

        .page-header p {
            color: var(--color-gray-400);
            margin-bottom: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            transition: all var(--transition-normal);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--color-primary);
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-2xl);
            color: var(--color-white);
        }

        .stat-value {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-white);
        }

        .stat-label {
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-6);
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
            }

            .page-header .btn {
                width: 100%;
            }
        }

        .card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            overflow: hidden;
            margin-bottom: var(--space-6);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-header h2, .card-header h3 {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin: 0;
            font-size: var(--font-size-xl);
        }

        .card-body {
            padding: var(--space-6);
        }

        .avisos-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .aviso-item {
            padding-bottom: var(--space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .aviso-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .aviso-item-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--space-3);
            margin-bottom: var(--space-3);
        }

        .aviso-item-header h3 {
            margin: 0;
            font-size: var(--font-size-lg);
        }

        .aviso-item-header h3 a {
            color: var(--color-white);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .aviso-item-header h3 a:hover {
            color: var(--color-primary);
        }

        .badge-destacado {
            display: flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-2);
            background: linear-gradient(135deg, var(--color-warning), var(--color-secondary));
            color: var(--color-black);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            white-space: nowrap;
        }

        .badge-group {
            padding: var(--space-1) var(--space-3);
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
            white-space: nowrap;
        }

        .aviso-excerpt {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
        }

        .aviso-item-footer {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            flex-wrap: wrap;
            font-size: var(--font-size-sm);
            color: var(--color-gray-500);
        }

        .aviso-author-small,
        .aviso-date-small,
        .aviso-stats-small {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .aviso-stats-small {
            display: flex;
            gap: var(--space-3);
            margin-left: auto;
        }

        .empty-state {
            text-align: center;
            padding: var(--space-10);
            color: var(--color-gray-400);
        }

        .empty-state i {
            font-size: var(--font-size-5xl);
            margin-bottom: var(--space-4);
            color: var(--color-gray-600);
        }

        .empty-state-small {
            text-align: center;
            padding: var(--space-6);
            color: var(--color-gray-400);
        }

        .group-list {
            list-style: none;
        }

        .group-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .group-item:last-child {
            border-bottom: none;
        }

        .group-item a {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            color: var(--color-gray-200);
            text-decoration: none;
            transition: all var(--transition-fast);
            border-radius: var(--radius-md);
        }

        .group-item a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-white);
        }

        .badge {
            margin-left: auto;
            padding: var(--space-1) var(--space-2);
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }

        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: var(--color-success);
        }

        .alert-info {
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            color: var(--color-primary);
        }
    </style>
</body>
</html>
