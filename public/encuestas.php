<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$encuesta_model = new Encuesta();

// Filtros
$vista = isset($_GET['vista']) ? sanitize($_GET['vista']) : 'activas'; // activas, finalizadas, mis_encuestas

// Obtener encuestas según la vista
if ($vista === 'mis_encuestas') {
    $encuestas = $encuesta_model->getUserEncuestas($user_id);
} elseif ($vista === 'finalizadas') {
    $now = date('Y-m-d H:i:s');
    $db = Database::getInstance();
    $encuestas = $db->query("SELECT e.*,
                                    u.nombre as autor_nombre,
                                    g.nombre as grupo_nombre,
                                    COUNT(DISTINCT ev.id) as total_votos
                             FROM encuestas e
                             LEFT JOIN usuarios u ON e.autor_id = u.id
                             LEFT JOIN grupos g ON e.grupo_id = g.id
                             LEFT JOIN encuestas_votos ev ON e.id = ev.encuesta_id
                             WHERE e.fecha_fin IS NOT NULL AND e.fecha_fin < :now
                             GROUP BY e.id
                             ORDER BY e.fecha_fin DESC
                             LIMIT 50")
                      ->bind(':now', $now)
                      ->fetchAll();
} else {
    $encuestas = $encuesta_model->getActivas();
}

// Añadir información si el usuario votó
foreach ($encuestas as &$encuesta) {
    $encuesta['user_voted'] = $encuesta_model->hasVoted($encuesta['id'], $user_id);
}

// Estadísticas
$stats = $encuesta_model->getStats($user_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuestas - TrazaFI</title>
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
                        <h1><i class="fas fa-poll"></i> Encuestas</h1>
                        <p>Participa en encuestas y conoce la opinión de la comunidad en tiempo real</p>
                    </div>
                    <a href="<?= base_url('public/create-encuesta.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Encuesta
                    </a>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $stats['total_encuestas'] ?></div>
                    <div class="stat-mini-label">Mis Encuestas</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $stats['activas'] ?></div>
                    <div class="stat-mini-label">Activas</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $stats['finalizadas'] ?></div>
                    <div class="stat-mini-label">Finalizadas</div>
                </div>
            </div>

            <!-- Tabs de vista -->
            <div class="view-tabs">
                <a href="<?= base_url('public/encuestas.php?vista=activas') ?>"
                   class="tab <?= $vista === 'activas' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> Activas
                </a>
                <a href="<?= base_url('public/encuestas.php?vista=finalizadas') ?>"
                   class="tab <?= $vista === 'finalizadas' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Finalizadas
                </a>
                <a href="<?= base_url('public/encuestas.php?vista=mis_encuestas') ?>"
                   class="tab <?= $vista === 'mis_encuestas' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i> Mis Encuestas
                </a>
            </div>

            <!-- Lista de encuestas -->
            <?php if (empty($encuestas)): ?>
                <div class="empty-state">
                    <i class="fas fa-poll"></i>
                    <h3>No hay encuestas</h3>
                    <p>
                        <?php if ($vista === 'mis_encuestas'): ?>
                            Aún no has creado ninguna encuesta
                        <?php elseif ($vista === 'finalizadas'): ?>
                            No hay encuestas finalizadas
                        <?php else: ?>
                            No hay encuestas activas en este momento
                        <?php endif; ?>
                    </p>
                    <a href="<?= base_url('public/create-encuesta.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Crear Primera Encuesta
                    </a>
                </div>
            <?php else: ?>
                <div class="encuestas-grid">
                    <?php foreach ($encuestas as $encuesta): ?>
                        <?php
                        $now = date('Y-m-d H:i:s');
                        $is_active = (!$encuesta['fecha_fin'] || $encuesta['fecha_fin'] >= $now)
                                     && $encuesta['fecha_inicio'] <= $now;
                        $finalizada = $encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $now;
                        ?>
                        <div class="encuesta-card <?= $finalizada ? 'finalizada' : '' ?>">
                            <div class="encuesta-header">
                                <div class="encuesta-badges">
                                    <?php if ($encuesta['anonima']): ?>
                                        <span class="anonima-badge">
                                            <i class="fas fa-user-secret"></i> Anónima
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($encuesta['multiple_respuestas']): ?>
                                        <span class="multiple-badge">
                                            <i class="fas fa-check-double"></i> Múltiple
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($finalizada): ?>
                                        <span class="estado-badge finalizada">
                                            <i class="fas fa-flag-checkered"></i> Finalizada
                                        </span>
                                    <?php elseif ($is_active): ?>
                                        <span class="estado-badge activa">
                                            <i class="fas fa-circle"></i> Activa
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($encuesta['grupo_nombre']): ?>
                                        <span class="grupo-badge">
                                            <i class="fas fa-users"></i> <?= sanitize($encuesta['grupo_nombre']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($encuesta['user_voted']): ?>
                                    <div class="voted-indicator">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Ya votaste</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h3 class="encuesta-title">
                                <a href="<?= base_url('public/view-encuesta.php?id=' . $encuesta['id']) ?>">
                                    <?= sanitize($encuesta['titulo']) ?>
                                </a>
                            </h3>

                            <?php if ($encuesta['descripcion']): ?>
                                <p class="encuesta-description">
                                    <?= truncate(sanitize($encuesta['descripcion']), 120) ?>
                                </p>
                            <?php endif; ?>

                            <div class="encuesta-meta">
                                <div class="meta-left">
                                    <span class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <?= sanitize($encuesta['autor_nombre']) ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?= format_date($encuesta['fecha_inicio']) ?>
                                    </span>
                                    <?php if ($encuesta['fecha_fin']): ?>
                                        <span class="meta-item <?= $finalizada ? 'text-muted' : 'text-warning' ?>">
                                            <i class="fas fa-hourglass-end"></i>
                                            <?= $finalizada ? 'Finalizó' : 'Finaliza' ?> <?= time_ago($encuesta['fecha_fin']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="meta-right">
                                    <span class="votos-count">
                                        <i class="fas fa-users"></i>
                                        <?= number_format($encuesta['total_votos']) ?> voto<?= $encuesta['total_votos'] != 1 ? 's' : '' ?>
                                    </span>
                                </div>
                            </div>

                            <div class="encuesta-actions">
                                <a href="<?= base_url('public/view-encuesta.php?id=' . $encuesta['id']) ?>"
                                   class="btn <?= $encuesta['user_voted'] || $finalizada ? 'btn-outline' : 'btn-primary' ?> btn-block">
                                    <i class="fas fa-<?= $encuesta['user_voted'] || $finalizada ? 'chart-bar' : 'vote-yea' ?>"></i>
                                    <?php if ($encuesta['user_voted']): ?>
                                        Ver Resultados
                                    <?php elseif ($finalizada): ?>
                                        Ver Resultados Finales
                                    <?php else: ?>
                                        Votar Ahora
                                    <?php endif; ?>
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
            margin-bottom: var(--space-6);
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

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .stat-mini {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            text-align: center;
        }

        .stat-mini-value {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            margin-bottom: var(--space-1);
        }

        .stat-mini-label {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .view-tabs {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-8);
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

        .encuestas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: var(--space-6);
        }

        .encuesta-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            transition: all var(--transition-normal);
            display: flex;
            flex-direction: column;
        }

        .encuesta-card:hover {
            border-color: var(--color-primary);
            box-shadow: 0 10px 30px rgba(0, 153, 255, 0.1);
        }

        .encuesta-card.finalizada {
            opacity: 0.8;
        }

        .encuesta-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-4);
        }

        .encuesta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .anonima-badge, .multiple-badge, .estado-badge, .grupo-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }

        .anonima-badge {
            background: rgba(255, 170, 0, 0.2);
            color: var(--color-warning);
        }

        .multiple-badge {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .estado-badge {
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-gray-300);
        }

        .estado-badge.activa {
            background: rgba(0, 255, 170, 0.2);
            color: var(--color-secondary);
        }

        .estado-badge.finalizada {
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-gray-500);
        }

        .grupo-badge {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-gray-400);
        }

        .voted-indicator {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            background: rgba(0, 255, 170, 0.1);
            border: 1px solid rgba(0, 255, 170, 0.3);
            border-radius: var(--radius-lg);
            color: var(--color-secondary);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
        }

        .encuesta-title {
            font-size: var(--font-size-xl);
            margin-bottom: var(--space-3);
        }

        .encuesta-title a {
            color: var(--color-white);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .encuesta-title a:hover {
            color: var(--color-primary);
        }

        .encuesta-description {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
            flex-grow: 1;
        }

        .encuesta-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-4) 0;
            margin-bottom: var(--space-4);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .meta-left {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .text-muted {
            color: var(--color-gray-500) !important;
        }

        .text-warning {
            color: var(--color-warning) !important;
        }

        .votos-count {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
        }

        .encuesta-actions {
            margin-top: auto;
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

            .encuestas-grid {
                grid-template-columns: 1fr;
            }

            .encuesta-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-3);
            }

            .view-tabs {
                overflow-x: auto;
            }
        }
    </style>
</body>
</html>
