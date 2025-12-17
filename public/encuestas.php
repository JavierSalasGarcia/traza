<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$encuesta_model = new Encuesta();
$db = Database::getInstance();

// Filtro
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'activas'; // activas, finalizadas, todas

// Obtener encuestas según filtro
$now = date('Y-m-d H:i:s');

if ($filtro === 'finalizadas') {
    $query = "SELECT e.*,
                     u.nombre, u.apellidos,
                     g.nombre as grupo_nombre,
                     (SELECT COUNT(*) FROM encuestas_votos WHERE encuesta_id = e.id) +
                     (SELECT COUNT(*) FROM encuestas_votos_anonimos WHERE encuesta_id = e.id) as total_votos
              FROM encuestas e
              LEFT JOIN usuarios u ON e.autor_id = u.id
              LEFT JOIN grupos g ON e.grupo_id = g.id
              WHERE e.eliminado = 0
              AND (e.fecha_fin IS NOT NULL AND e.fecha_fin < :now OR e.activa = 0)
              ORDER BY e.fecha_creacion DESC
              LIMIT 50";
} elseif ($filtro === 'todas') {
    $query = "SELECT e.*,
                     u.nombre, u.apellidos,
                     g.nombre as grupo_nombre,
                     (SELECT COUNT(*) FROM encuestas_votos WHERE encuesta_id = e.id) +
                     (SELECT COUNT(*) FROM encuestas_votos_anonimos WHERE encuesta_id = e.id) as total_votos
              FROM encuestas e
              LEFT JOIN usuarios u ON e.autor_id = u.id
              LEFT JOIN grupos g ON e.grupo_id = g.id
              WHERE e.eliminado = 0
              ORDER BY e.fecha_creacion DESC
              LIMIT 100";
} else {
    // Activas
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
              AND (e.fecha_fin IS NULL OR e.fecha_fin >= :now2)
              ORDER BY e.fecha_creacion DESC";
}

$db->query($query);
$db->bind(':now', $now);
if ($filtro === 'activas') {
    $db->bind(':now2', $now);
}
$encuestas = $db->resultSet();

// Verificar si el usuario votó en cada encuesta
foreach ($encuestas as &$encuesta) {
    $encuesta['user_voted'] = $encuesta_model->hasVoted($encuesta['id'], $user['id']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuestas - TrazaFI</title>
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

            <div class="page-header">
                <div>
                    <h1><i class="fas fa-poll"></i> Encuestas</h1>
                    <p>Participa en encuestas y consultas comunitarias</p>
                </div>
                <a href="<?= base_url('public/create-encuesta.php') ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Encuesta
                </a>
            </div>

            <!-- Filtros -->
            <div class="tabs">
                <a href="?filtro=activas" class="tab <?= $filtro === 'activas' ? 'active' : '' ?>">
                    <i class="fas fa-circle"></i> Activas
                </a>
                <a href="?filtro=finalizadas" class="tab <?= $filtro === 'finalizadas' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Finalizadas
                </a>
                <a href="?filtro=todas" class="tab <?= $filtro === 'todas' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Todas
                </a>
            </div>

            <!-- Grid de Encuestas -->
            <?php if (empty($encuestas)): ?>
                <div class="empty-state">
                    <i class="fas fa-poll"></i>
                    <h3>No hay encuestas <?= $filtro ?></h3>
                    <p>No se encontraron encuestas en esta categoría</p>
                    <?php if ($filtro !== 'activas'): ?>
                        <a href="?filtro=activas" class="btn btn-primary">Ver Encuestas Activas</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="encuestas-grid">
                    <?php foreach ($encuestas as $encuesta): ?>
                        <?php
                        $is_active = $encuesta['activa']
                                     && $encuesta['fecha_inicio'] <= $now
                                     && (!$encuesta['fecha_fin'] || $encuesta['fecha_fin'] >= $now);
                        $finalizada = $encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $now;
                        ?>
                        <div class="encuesta-card <?= !$is_active ? 'finalizada' : '' ?>">
                            <div class="encuesta-header">
                                <h3>
                                    <a href="<?= base_url('public/view-encuesta.php?id=' . $encuesta['id']) ?>">
                                        <?= sanitize($encuesta['titulo']) ?>
                                    </a>
                                </h3>
                                <div class="encuesta-badges">
                                    <?php if ($encuesta['anonima']): ?>
                                        <span class="badge badge-info">
                                            <i class="fas fa-user-secret"></i> Anónima
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($is_active): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-circle"></i> Activa
                                        </span>
                                    <?php elseif ($finalizada): ?>
                                        <span class="badge badge-error">
                                            <i class="fas fa-circle"></i> Finalizada
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-circle"></i> Inactiva
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($encuesta['descripcion']): ?>
                                <p class="encuesta-desc"><?= truncate(sanitize($encuesta['descripcion']), 120) ?></p>
                            <?php endif; ?>

                            <div class="encuesta-meta">
                                <span><i class="fas fa-user"></i> <?= sanitize($encuesta['nombre'] . ' ' . $encuesta['apellidos']) ?></span>
                                <?php if ($encuesta['grupo_nombre']): ?>
                                    <span><i class="fas fa-users"></i> <?= sanitize($encuesta['grupo_nombre']) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock"></i> <?= time_ago($encuesta['fecha_creacion']) ?></span>
                            </div>

                            <div class="encuesta-stats">
                                <div class="stat">
                                    <i class="fas fa-chart-pie"></i>
                                    <span><?= number_format($encuesta['total_votos']) ?> votos</span>
                                </div>
                                <?php if ($encuesta['user_voted']): ?>
                                    <div class="stat voted">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Ya votaste</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="encuesta-actions">
                                <?php if ($encuesta['user_voted'] || !$is_active): ?>
                                    <a href="<?= base_url('public/view-encuesta.php?id=' . $encuesta['id']) ?>" class="btn btn-secondary btn-block">
                                        <i class="fas fa-chart-bar"></i> Ver Resultados
                                    </a>
                                <?php else: ?>
                                    <a href="<?= base_url('public/view-encuesta.php?id=' . $encuesta['id']) ?>" class="btn btn-primary btn-block">
                                        <i class="fas fa-vote-yea"></i> Responder Encuesta
                                    </a>
                                <?php endif; ?>

                                <?php if ($encuesta['anonima'] && $encuesta['token_recibos']): ?>
                                    <a href="<?= base_url('public/encuesta-recibos.php?token=' . $encuesta['token_recibos']) ?>"
                                       class="btn btn-sm btn-secondary" target="_blank">
                                        <i class="fas fa-receipt"></i> Recibos
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <style>
        .encuestas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--space-4);
        }

        .encuesta-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: var(--space-5);
            transition: all var(--transition-fast);
        }

        .encuesta-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 153, 255, 0.2);
            border-color: var(--color-primary);
        }

        .encuesta-card.finalizada {
            opacity: 0.7;
        }

        .encuesta-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-3);
            margin-bottom: var(--space-3);
        }

        .encuesta-header h3 {
            flex: 1;
            margin: 0;
        }

        .encuesta-header h3 a {
            color: var(--color-text);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .encuesta-header h3 a:hover {
            color: var(--color-primary);
        }

        .encuesta-badges {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
            align-items: flex-end;
        }

        .encuesta-desc {
            color: var(--color-text-muted);
            margin-bottom: var(--space-3);
            line-height: 1.5;
        }

        .encuesta-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3);
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid var(--border-color);
        }

        .encuesta-meta span {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .encuesta-stats {
            display: flex;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .encuesta-stats .stat {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-sm);
        }

        .encuesta-stats .stat.voted {
            color: var(--color-success);
            font-weight: var(--font-weight-semibold);
        }

        .encuesta-actions {
            display: flex;
            gap: var(--space-2);
        }

        @media (max-width: 768px) {
            .encuestas-grid {
                grid-template-columns: 1fr;
            }

            .encuesta-header {
                flex-direction: column;
            }

            .encuesta-badges {
                align-items: flex-start;
            }

            .encuesta-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
