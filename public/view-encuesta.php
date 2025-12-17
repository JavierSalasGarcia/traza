<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$encuesta_model = new Encuesta();

// Obtener ID
$encuesta_id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!$encuesta_id) {
    set_flash('error', 'Encuesta no encontrada');
    redirect(base_url('public/encuestas.php'));
}

$encuesta = $encuesta_model->getById($encuesta_id);

if (!$encuesta) {
    set_flash('error', 'Encuesta no encontrada');
    redirect(base_url('public/encuestas.php'));
}

// Verificar si ya votó
$has_voted = $encuesta_model->hasVoted($encuesta_id, $user_id);

// Verificar si está activa
$now = date('Y-m-d H:i:s');
$is_active = (!$encuesta['fecha_fin'] || $encuesta['fecha_fin'] >= $now)
             && $encuesta['fecha_inicio'] <= $now;
$finalizada = $encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $now;

// Procesar voto
if (is_post() && isset($_POST['votar'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        $opciones_ids = isset($_POST['opciones']) ? $_POST['opciones'] : [];

        if (!is_array($opciones_ids)) {
            $opciones_ids = [$opciones_ids];
        }

        $result = $encuesta_model->vote($encuesta_id, $opciones_ids, $user_id);
        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Cerrar encuesta
if (is_post() && isset($_POST['cerrar'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        $result = $encuesta_model->cerrar($encuesta_id, $user_id);
        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Obtener resultados
$resultados = $encuesta_model->getResultados($encuesta_id);
$opciones = $resultados['opciones'];
$total_votos = $resultados['total_votos'];

$is_owner = $encuesta['autor_id'] == $user_id;
$can_close = $is_owner && !$finalizada;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($encuesta['titulo']) ?> - Encuestas</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../core/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <?php $flash_messages = get_flash(); ?>
            <?php foreach ($flash_messages as $flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <?= $flash['message'] ?>
                </div>
            <?php endforeach; ?>

            <div class="breadcrumb">
                <a href="<?= base_url('public/dashboard.php') ?>">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= base_url('public/encuestas.php') ?>">Encuestas</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= truncate(sanitize($encuesta['titulo']), 50) ?></span>
            </div>

            <div class="encuesta-container">
                <!-- Columna principal -->
                <div class="encuesta-main">
                    <div class="encuesta-header-full">
                        <div class="encuesta-badges-large">
                            <?php if ($finalizada): ?>
                                <span class="estado-badge-lg finalizada">
                                    <i class="fas fa-flag-checkered"></i> Finalizada
                                </span>
                            <?php elseif ($is_active): ?>
                                <span class="estado-badge-lg activa">
                                    <i class="fas fa-circle"></i> Activa
                                </span>
                            <?php endif; ?>

                            <?php if ($encuesta['anonima']): ?>
                                <span class="anonima-badge-lg">
                                    <i class="fas fa-user-secret"></i> Anónima
                                </span>
                            <?php endif; ?>

                            <?php if ($encuesta['multiple_respuestas']): ?>
                                <span class="multiple-badge-lg">
                                    <i class="fas fa-check-double"></i> Respuesta Múltiple
                                </span>
                            <?php endif; ?>

                            <?php if ($encuesta['grupo_nombre']): ?>
                                <span class="grupo-badge-lg">
                                    <i class="fas fa-users"></i> <?= sanitize($encuesta['grupo_nombre']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h1 class="encuesta-title-full"><?= sanitize($encuesta['titulo']) ?></h1>

                        <?php if ($encuesta['descripcion']): ?>
                            <p class="encuesta-description-full">
                                <?= nl2br(sanitize($encuesta['descripcion'])) ?>
                            </p>
                        <?php endif; ?>

                        <div class="encuesta-author-info">
                            <div class="author-avatar">
                                <?php if ($encuesta['autor_imagen']): ?>
                                    <img src="<?= upload_url($encuesta['autor_imagen']) ?>" alt="<?= sanitize($encuesta['autor_nombre']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="author-details">
                                <strong><?= sanitize($encuesta['autor_nombre']) ?></strong>
                                <div class="author-meta">
                                    <span><i class="fas fa-calendar"></i> Publicado <?= time_ago($encuesta['fecha_inicio']) ?></span>
                                    <?php if ($encuesta['fecha_fin']): ?>
                                        <span class="separator">•</span>
                                        <span class="<?= $finalizada ? '' : 'text-warning' ?>">
                                            <i class="fas fa-hourglass-end"></i>
                                            <?= $finalizada ? 'Finalizó' : 'Finaliza' ?> <?= time_ago($encuesta['fecha_fin']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de votación o resultados -->
                    <?php if (!$has_voted && $is_active): ?>
                        <!-- Formulario de votación -->
                        <div class="voting-section">
                            <h2><i class="fas fa-vote-yea"></i> Emite tu voto</h2>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="votar" value="1">

                                <div class="opciones-voting">
                                    <?php foreach ($opciones as $opcion): ?>
                                        <label class="opcion-vote">
                                            <input type="<?= $encuesta['multiple_respuestas'] ? 'checkbox' : 'radio' ?>"
                                                   name="opciones<?= $encuesta['multiple_respuestas'] ? '[]' : '' ?>"
                                                   value="<?= $opcion['id'] ?>"
                                                   required>
                                            <span class="opcion-text"><?= sanitize($opcion['texto']) ?></span>
                                            <i class="fas fa-check-circle check-icon"></i>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($encuesta['multiple_respuestas']): ?>
                                    <p class="vote-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Puedes seleccionar múltiples opciones
                                    </p>
                                <?php endif; ?>

                                <div class="vote-actions">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane"></i> Enviar Voto
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Resultados en tiempo real -->
                        <div class="results-section">
                            <h2>
                                <i class="fas fa-chart-bar"></i>
                                Resultados <?= $finalizada ? 'Finales' : 'en Tiempo Real' ?>
                            </h2>

                            <?php if ($has_voted && !$finalizada): ?>
                                <div class="vote-confirmation">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Tu voto ha sido registrado. Estos resultados se actualizan en tiempo real.</span>
                                </div>
                            <?php endif; ?>

                            <div class="resultados-list">
                                <?php foreach ($opciones as $opcion): ?>
                                    <?php $max_votos = max(array_column($opciones, 'total_votos')); ?>
                                    <div class="resultado-item <?= $opcion['total_votos'] == $max_votos && $max_votos > 0 ? 'leading' : '' ?>">
                                        <div class="resultado-header">
                                            <span class="resultado-texto"><?= sanitize($opcion['texto']) ?></span>
                                            <div class="resultado-stats">
                                                <span class="resultado-votos"><?= number_format($opcion['total_votos']) ?> voto<?= $opcion['total_votos'] != 1 ? 's' : '' ?></span>
                                                <span class="resultado-porcentaje"><?= number_format($opcion['porcentaje'], 1) ?>%</span>
                                            </div>
                                        </div>
                                        <div class="resultado-bar">
                                            <div class="resultado-fill" style="width: <?= $opcion['porcentaje'] ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="total-participacion">
                                <i class="fas fa-users"></i>
                                <strong><?= number_format($total_votos) ?></strong> persona<?= $total_votos != 1 ? 's' : '' ?> ha<?= $total_votos != 1 ? 'n' : '' ?> participado
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="encuesta-sidebar">
                    <!-- Información -->
                    <div class="sidebar-card">
                        <h3>Información</h3>
                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label">Estado:</span>
                                <span class="info-value <?= $finalizada ? 'text-muted' : 'text-success' ?>">
                                    <?= $finalizada ? 'Finalizada' : 'Activa' ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Participantes:</span>
                                <span class="info-value"><?= number_format($total_votos) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tipo:</span>
                                <span class="info-value">
                                    <?= $encuesta['multiple_respuestas'] ? 'Múltiple' : 'Única' ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Privacidad:</span>
                                <span class="info-value">
                                    <?= $encuesta['anonima'] ? 'Anónima' : 'Pública' ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Inicio:</span>
                                <span class="info-value"><?= format_date($encuesta['fecha_inicio']) ?></span>
                            </div>
                            <?php if ($encuesta['fecha_fin']): ?>
                                <div class="info-item">
                                    <span class="info-label">Finaliza:</span>
                                    <span class="info-value"><?= format_date($encuesta['fecha_fin']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Acciones del propietario -->
                    <?php if ($can_close): ?>
                        <div class="sidebar-card">
                            <h3>Acciones</h3>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="cerrar" value="1">
                                <button type="submit"
                                        class="btn btn-outline btn-block"
                                        onclick="return confirm('¿Estás seguro de cerrar esta encuesta? Esta acción no se puede deshacer.')">
                                    <i class="fas fa-lock"></i> Cerrar Encuesta
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Compartir -->
                    <div class="sidebar-card">
                        <h3>Compartir</h3>
                        <button class="btn btn-outline btn-block" onclick="copyToClipboard()">
                            <i class="fas fa-link"></i> Copiar Enlace
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .main-content {
            padding: var(--space-8) 0;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-6);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .breadcrumb a {
            color: var(--color-primary);
            text-decoration: none;
        }

        .encuesta-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--space-8);
        }

        .encuesta-main {
            min-width: 0;
        }

        .encuesta-header-full {
            margin-bottom: var(--space-8);
        }

        .encuesta-badges-large {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            margin-bottom: var(--space-4);
        }

        .estado-badge-lg, .anonima-badge-lg, .multiple-badge-lg, .grupo-badge-lg {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
        }

        .estado-badge-lg.activa {
            background: rgba(0, 255, 170, 0.2);
            color: var(--color-secondary);
        }

        .estado-badge-lg.finalizada {
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-gray-400);
        }

        .anonima-badge-lg {
            background: rgba(255, 170, 0, 0.2);
            color: var(--color-warning);
        }

        .multiple-badge-lg {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .grupo-badge-lg {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-gray-400);
        }

        .encuesta-title-full {
            font-size: var(--font-size-4xl);
            line-height: var(--line-height-tight);
            margin-bottom: var(--space-4);
        }

        .encuesta-description-full {
            font-size: var(--font-size-lg);
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-6);
        }

        .encuesta-author-info {
            display: flex;
            gap: var(--space-4);
            align-items: center;
            padding: var(--space-4);
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-lg);
        }

        .author-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--color-gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author-avatar i {
            font-size: var(--font-size-3xl);
            color: var(--color-gray-600);
        }

        .author-details strong {
            display: block;
            margin-bottom: var(--space-1);
        }

        .author-meta {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .separator {
            color: var(--color-gray-600);
        }

        .text-warning {
            color: var(--color-warning) !important;
        }

        .voting-section, .results-section {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
        }

        .voting-section h2, .results-section h2 {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--font-size-2xl);
            margin-bottom: var(--space-6);
        }

        .opciones-voting {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
            margin-bottom: var(--space-6);
        }

        .opcion-vote {
            position: relative;
            display: flex;
            align-items: center;
            gap: var(--space-4);
            padding: var(--space-5);
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .opcion-vote:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--color-primary);
        }

        .opcion-vote input {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }

        .opcion-vote input:checked ~ .opcion-text {
            color: var(--color-primary);
            font-weight: var(--font-weight-semibold);
        }

        .opcion-vote input:checked ~ .check-icon {
            opacity: 1;
        }

        .opcion-text {
            flex: 1;
            font-size: var(--font-size-lg);
        }

        .check-icon {
            color: var(--color-secondary);
            font-size: var(--font-size-xl);
            opacity: 0;
            transition: opacity var(--transition-fast);
        }

        .vote-hint {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
            margin: 0;
        }

        .vote-actions {
            display: flex;
            gap: var(--space-3);
        }

        .vote-confirmation {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            background: rgba(0, 255, 170, 0.1);
            border: 1px solid rgba(0, 255, 170, 0.3);
            border-radius: var(--radius-lg);
            color: var(--color-secondary);
            margin-bottom: var(--space-6);
            font-weight: var(--font-weight-medium);
        }

        .resultados-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-5);
            margin-bottom: var(--space-6);
        }

        .resultado-item {
            position: relative;
            padding: var(--space-4);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            transition: all var(--transition-fast);
        }

        .resultado-item.leading {
            border-color: var(--color-secondary);
            background: rgba(0, 255, 170, 0.05);
        }

        .resultado-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-3);
        }

        .resultado-texto {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-medium);
        }

        .resultado-stats {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }

        .resultado-votos {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .resultado-porcentaje {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
        }

        .resultado-bar {
            height: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-full);
            overflow: hidden;
        }

        .resultado-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            transition: width 0.5s ease;
        }

        .resultado-item.leading .resultado-fill {
            background: var(--gradient-secondary);
        }

        .total-participacion {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-3);
            padding: var(--space-5);
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-lg);
        }

        .total-participacion i {
            font-size: var(--font-size-2xl);
            color: var(--color-primary);
        }

        .total-participacion strong {
            color: var(--color-primary);
            font-size: var(--font-size-2xl);
        }

        .encuesta-sidebar {
            position: sticky;
            top: var(--space-6);
            height: fit-content;
        }

        .sidebar-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .sidebar-card h3 {
            font-size: var(--font-size-lg);
            margin-bottom: var(--space-4);
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-2) 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .info-value {
            font-weight: var(--font-weight-semibold);
        }

        .text-muted {
            color: var(--color-gray-500) !important;
        }

        .text-success {
            color: var(--color-secondary) !important;
        }

        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: var(--color-success);
        }

        .alert-error {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.3);
            color: var(--color-error);
        }

        @media (max-width: 1024px) {
            .encuesta-container {
                grid-template-columns: 1fr;
            }

            .encuesta-sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .resultado-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-2);
            }
        }
    </style>

    <script>
    function copyToClipboard() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(function() {
            alert('Enlace copiado al portapapeles');
        }, function() {
            alert('No se pudo copiar el enlace');
        });
    }

    // Auto-refresh results every 10 seconds if viewing results
    <?php if (($has_voted || $finalizada) && !empty($opciones)): ?>
    setInterval(function() {
        location.reload();
    }, 10000); // 10 segundos
    <?php endif; ?>
    </script>
</body>
</html>
