<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$user_groups = get_user_groups($user_id);
$group_model = new Group();

// Agrupar por tipo
$grouped_groups = [
    'departamento' => [],
    'licenciatura' => [],
    'posgrado' => [],
    'capitulo' => []
];

foreach ($user_groups as $group) {
    $grouped_groups[$group['tipo_grupo']][] = $group;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Grupos - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../core/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>Mis Grupos</h1>
                <p>Grupos a los que perteneces</p>
            </div>

            <?php if (empty($user_groups)): ?>
                <div class="empty-state-card">
                    <i class="fas fa-users"></i>
                    <h3>No perteneces a ningún grupo</h3>
                    <p>Explora los grupos disponibles y solicita ingreso</p>
                    <a href="<?= base_url('public/groups.php') ?>" class="btn btn-primary">
                        <i class="fas fa-search"></i> Explorar Grupos
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($grouped_groups as $tipo => $grupos): ?>
                    <?php if (!empty($grupos)): ?>
                        <div class="group-section">
                            <h2 class="section-title">
                                <i class="fas fa-<?= $tipo === 'departamento' ? 'building' :
                                                   ($tipo === 'licenciatura' ? 'graduation-cap' :
                                                   ($tipo === 'posgrado' ? 'user-graduate' : 'users')) ?>"></i>
                                <?= ucfirst($tipo) ?>s
                            </h2>

                            <div class="groups-grid">
                                <?php foreach ($grupos as $grupo): ?>
                                    <?php $stats = $group_model->getStats($grupo['id']); ?>
                                    <div class="group-card">
                                        <div class="group-header">
                                            <h3><?= sanitize($grupo['nombre']) ?></h3>
                                            <?php if ($grupo['es_coordinador']): ?>
                                                <span class="badge badge-coordinator">
                                                    <i class="fas fa-star"></i> Coordinador
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($grupo['descripcion']): ?>
                                            <p class="group-description"><?= truncate(sanitize($grupo['descripcion']), 150) ?></p>
                                        <?php endif; ?>

                                        <div class="group-stats">
                                            <div class="stat">
                                                <i class="fas fa-users"></i>
                                                <span><?= $stats['miembros'] ?> miembros</span>
                                            </div>
                                            <div class="stat">
                                                <i class="fas fa-bullhorn"></i>
                                                <span><?= $stats['avisos'] ?> avisos</span>
                                            </div>
                                            <?php if ($grupo['es_coordinador'] && $stats['pendientes'] > 0): ?>
                                                <div class="stat highlight">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?= $stats['pendientes'] ?> pendientes</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="group-actions">
                                            <a href="<?= base_url('public/group.php?id=' . $grupo['id']) ?>" class="btn btn-primary btn-sm btn-block">
                                                <i class="fas fa-folder-open"></i> Ver Grupo
                                            </a>
                                            <?php if ($grupo['es_coordinador']): ?>
                                                <a href="<?= base_url('public/manage-group.php?id=' . $grupo['id']) ?>" class="btn btn-outline btn-sm btn-block">
                                                    <i class="fas fa-cog"></i> Administrar
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="action-card">
                    <div class="action-content">
                        <i class="fas fa-search"></i>
                        <div>
                            <h3>¿Buscas más grupos?</h3>
                            <p>Explora todos los grupos disponibles y solicita ingreso</p>
                        </div>
                    </div>
                    <a href="<?= base_url('public/groups.php') ?>" class="btn btn-primary">
                        Explorar <i class="fas fa-arrow-right"></i>
                    </a>
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
            margin-bottom: var(--space-2);
        }

        .page-header p {
            color: var(--color-gray-400);
            margin-bottom: 0;
        }

        .empty-state-card {
            text-align: center;
            padding: var(--space-16);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
        }

        .empty-state-card i {
            font-size: var(--font-size-6xl);
            color: var(--color-gray-600);
            margin-bottom: var(--space-4);
        }

        .empty-state-card h3 {
            margin-bottom: var(--space-2);
        }

        .empty-state-card p {
            color: var(--color-gray-400);
            margin-bottom: var(--space-6);
        }

        .group-section {
            margin-bottom: var(--space-12);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--font-size-2xl);
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: var(--space-6);
        }

        .group-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            transition: all var(--transition-normal);
        }

        .group-card:hover {
            transform: translateY(-3px);
            border-color: var(--color-primary);
            box-shadow: 0 15px 30px rgba(0, 153, 255, 0.1);
        }

        .group-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--space-3);
            margin-bottom: var(--space-4);
        }

        .group-header h3 {
            font-size: var(--font-size-xl);
            margin: 0;
        }

        .badge-coordinator {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            background: rgba(0, 255, 170, 0.2);
            color: var(--color-secondary);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            white-space: nowrap;
        }

        .group-description {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
            font-size: var(--font-size-sm);
        }

        .group-stats {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-4);
            padding: var(--space-4) 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: var(--space-4);
        }

        .stat {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .stat.highlight {
            color: var(--color-warning);
        }

        .group-actions {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .btn-block {
            width: 100%;
        }

        .action-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-6);
            background: var(--gradient-hero);
            border: 1px solid rgba(0, 153, 255, 0.2);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            margin-top: var(--space-8);
        }

        .action-content {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }

        .action-content i {
            font-size: var(--font-size-4xl);
            color: var(--color-primary);
        }

        .action-content h3 {
            margin-bottom: var(--space-1);
            font-size: var(--font-size-xl);
        }

        .action-content p {
            color: var(--color-gray-400);
            margin: 0;
        }

        @media (max-width: 768px) {
            .groups-grid {
                grid-template-columns: 1fr;
            }

            .action-card {
                flex-direction: column;
                text-align: center;
            }

            .action-content {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</body>
</html>
