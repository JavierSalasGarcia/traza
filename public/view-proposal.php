<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$propuesta_model = new Propuesta();

// Obtener ID de la propuesta
$propuesta_id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!$propuesta_id) {
    set_flash('error', 'Propuesta no encontrada');
    redirect(base_url('public/proposals.php'));
}

$propuesta = $propuesta_model->getById($propuesta_id);

if (!$propuesta) {
    set_flash('error', 'Propuesta no encontrada');
    redirect(base_url('public/proposals.php'));
}

// Verificar si el usuario ya firmó
$has_signed = $propuesta_model->hasSigned($propuesta_id, $user_id);

// Procesar acciones
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        // Firmar propuesta
        if (isset($_POST['sign'])) {
            $es_anonima = isset($_POST['es_anonima']) && $_POST['es_anonima'] === '1';
            $result = $propuesta_model->sign($propuesta_id, $user_id, $es_anonima);
            set_flash($result['success'] ? 'success' : 'error', $result['message']);
            redirect(base_url('public/view-proposal.php?id=' . $propuesta_id));
        }

        // Quitar firma
        if (isset($_POST['unsign'])) {
            $result = $propuesta_model->unsign($propuesta_id, $user_id);
            set_flash($result['success'] ? 'success' : 'error', $result['message']);
            redirect(base_url('public/view-proposal.php?id=' . $propuesta_id));
        }
    }
}

// Obtener firmantes (solo los no anónimos)
$firmantes = $propuesta_model->getSigners($propuesta_id);

// Calcular progreso
$progreso = ($propuesta['total_firmas'] / $propuesta['umbral_firmas']) * 100;
$progreso = min($progreso, 100);
$alcanzado = $propuesta['total_firmas'] >= $propuesta['umbral_firmas'];

// Categorías
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

$can_edit = $propuesta_model->canEdit($propuesta_id, $user_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($propuesta['titulo']) ?> - TrazaFI</title>
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
                <a href="<?= base_url('public/proposals.php') ?>">Propuestas</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= truncate(sanitize($propuesta['titulo']), 50) ?></span>
            </div>

            <div class="proposal-container">
                <!-- Columna principal -->
                <div class="proposal-main">
                    <div class="proposal-header">
                        <div class="proposal-meta">
                            <span class="category-badge">
                                <?= $categorias[$propuesta['categoria']] ?? $propuesta['categoria'] ?>
                            </span>
                            <span class="estado-badge estado-<?= $propuesta['estado'] ?>">
                                <i class="fas fa-circle"></i>
                                <?= $estados[$propuesta['estado']] ?>
                            </span>
                            <?php if ($propuesta['grupo_nombre']): ?>
                                <span class="group-badge">
                                    <i class="fas fa-users"></i>
                                    <?= sanitize($propuesta['grupo_nombre']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h1 class="proposal-title"><?= sanitize($propuesta['titulo']) ?></h1>

                        <div class="proposal-author-info">
                            <div class="author-avatar">
                                <?php if ($propuesta['autor_imagen']): ?>
                                    <img src="<?= upload_url($propuesta['autor_imagen']) ?>" alt="<?= sanitize($propuesta['autor_nombre']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="author-details">
                                <strong><?= sanitize($propuesta['autor_nombre']) ?></strong>
                                <div class="author-meta">
                                    <span><i class="fas fa-calendar"></i> <?= format_date($propuesta['fecha_creacion']) ?></span>
                                    <span class="separator">•</span>
                                    <span><i class="fas fa-clock"></i> <?= time_ago($propuesta['fecha_creacion']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="proposal-content">
                        <h2>Descripción</h2>
                        <div class="description-text">
                            <?= nl2br(sanitize($propuesta['descripcion'])) ?>
                        </div>
                    </div>

                    <?php if ($propuesta['evidencias']): ?>
                        <div class="proposal-section">
                            <h2><i class="fas fa-check-circle"></i> Evidencias de Completación</h2>
                            <div class="evidencias-box">
                                <?= nl2br(sanitize($propuesta['evidencias'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($propuesta['comision_nombre']): ?>
                        <div class="proposal-section">
                            <h2><i class="fas fa-users-cog"></i> Comisión Asignada</h2>
                            <div class="comision-box">
                                <strong><?= sanitize($propuesta['comision_nombre']) ?></strong>
                                <?php if ($propuesta['fecha_asignacion_comision']): ?>
                                    <p>Asignada el <?= format_date($propuesta['fecha_asignacion_comision']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Firmantes -->
                    <?php if (!empty($firmantes)): ?>
                        <div class="proposal-section">
                            <h2><i class="fas fa-signature"></i> Firmantes (<?= count($firmantes) ?> públicos)</h2>
                            <div class="firmantes-grid">
                                <?php foreach (array_slice($firmantes, 0, 12) as $firmante): ?>
                                    <div class="firmante-card">
                                        <div class="firmante-avatar">
                                            <?php if ($firmante['imagen_perfil']): ?>
                                                <img src="<?= upload_url($firmante['imagen_perfil']) ?>" alt="<?= sanitize($firmante['nombre']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="firmante-info">
                                            <strong><?= sanitize($firmante['nombre']) ?></strong>
                                            <small><?= time_ago($firmante['fecha_firma']) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($firmantes) > 12): ?>
                                    <div class="firmante-card more-firmantes">
                                        <i class="fas fa-plus"></i>
                                        <span>+<?= count($firmantes) - 12 ?> más</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="proposal-sidebar">
                    <!-- Estado y progreso -->
                    <?php if ($propuesta['estado'] === 'votacion'): ?>
                        <div class="sidebar-card">
                            <h3>Progreso de Firmas</h3>
                            <div class="signature-progress-large">
                                <div class="progress-circle">
                                    <svg viewBox="0 0 100 100">
                                        <circle cx="50" cy="50" r="45" class="progress-bg"></circle>
                                        <circle cx="50" cy="50" r="45" class="progress-fill"
                                                style="stroke-dashoffset: <?= 283 - (283 * $progreso / 100) ?>"></circle>
                                    </svg>
                                    <div class="progress-text">
                                        <span class="progress-number"><?= number_format($progreso, 0) ?>%</span>
                                    </div>
                                </div>
                                <div class="progress-info">
                                    <div class="progress-stat">
                                        <strong><?= number_format($propuesta['total_firmas']) ?></strong>
                                        <span>de <?= number_format($propuesta['umbral_firmas']) ?> firmas</span>
                                    </div>
                                    <?php if ($alcanzado): ?>
                                        <div class="threshold-alert success">
                                            <i class="fas fa-check-circle"></i>
                                            ¡Meta alcanzada!
                                        </div>
                                    <?php else: ?>
                                        <div class="threshold-alert">
                                            <i class="fas fa-bullseye"></i>
                                            Faltan <?= number_format($propuesta['umbral_firmas'] - $propuesta['total_firmas']) ?> firmas
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Formulario de firma -->
                        <div class="sidebar-card">
                            <?php if ($has_signed): ?>
                                <div class="signed-status">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Ya firmaste esta propuesta</span>
                                </div>
                                <form method="POST">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="unsign" class="btn btn-outline btn-block">
                                        <i class="fas fa-times"></i> Retirar Firma
                                    </button>
                                </form>
                            <?php else: ?>
                                <h3>Apoya esta propuesta</h3>
                                <form method="POST" id="signForm">
                                    <?= csrf_field() ?>
                                    <div class="form-check">
                                        <input type="checkbox" id="es_anonima" name="es_anonima" value="1">
                                        <label for="es_anonima">
                                            Firmar de forma anónima
                                            <small>Tu firma será contada pero tu nombre no aparecerá públicamente</small>
                                        </label>
                                    </div>
                                    <button type="submit" name="sign" class="btn btn-primary btn-block btn-lg">
                                        <i class="fas fa-signature"></i> Firmar Propuesta
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="sidebar-card">
                            <h3>Estado Actual</h3>
                            <div class="estado-info estado-<?= $propuesta['estado'] ?>">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong><?= $estados[$propuesta['estado']] ?></strong>
                                    <p>
                                        <?php if ($propuesta['estado'] === 'revision'): ?>
                                            Esta propuesta está siendo revisada por las autoridades correspondientes
                                        <?php elseif ($propuesta['estado'] === 'en_progreso'): ?>
                                            Esta propuesta está siendo implementada
                                        <?php elseif ($propuesta['estado'] === 'completada'): ?>
                                            Esta propuesta ha sido completada exitosamente
                                        <?php elseif ($propuesta['estado'] === 'rechazada'): ?>
                                            Esta propuesta fue rechazada
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="firmas-finales">
                                <i class="fas fa-signature"></i>
                                <span><?= number_format($propuesta['total_firmas']) ?> firmas totales</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Acciones -->
                    <?php if ($can_edit || is_admin()): ?>
                        <div class="sidebar-card">
                            <h3>Acciones</h3>
                            <?php if ($can_edit): ?>
                                <a href="<?= base_url('public/create-proposal.php?id=' . $propuesta_id) ?>"
                                   class="btn btn-outline btn-block">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Compartir -->
                    <div class="sidebar-card">
                        <h3>Compartir</h3>
                        <div class="share-buttons">
                            <button class="share-btn" onclick="copyToClipboard()">
                                <i class="fas fa-link"></i>
                                <span>Copiar enlace</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comentarios -->
            <?php
            $referencia_tipo = 'propuesta';
            $referencia_id = $propuesta_id;
            include __DIR__ . '/../core/includes/comments.php';
            ?>
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

        .proposal-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: var(--space-8);
        }

        .proposal-main {
            min-width: 0;
        }

        .proposal-header {
            margin-bottom: var(--space-8);
        }

        .proposal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            margin-bottom: var(--space-4);
        }

        .category-badge, .estado-badge, .group-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
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
            font-size: var(--font-size-4xl);
            line-height: var(--line-height-tight);
            margin-bottom: var(--space-6);
        }

        .proposal-author-info {
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

        .proposal-content {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            margin-bottom: var(--space-6);
        }

        .proposal-content h2 {
            font-size: var(--font-size-2xl);
            margin-bottom: var(--space-4);
            color: var(--color-primary);
        }

        .description-text {
            line-height: var(--line-height-relaxed);
            color: var(--color-gray-300);
            font-size: var(--font-size-lg);
        }

        .proposal-section {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .proposal-section h2 {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-xl);
            margin-bottom: var(--space-4);
        }

        .evidencias-box, .comision-box {
            padding: var(--space-4);
            background: rgba(0, 255, 136, 0.05);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
            line-height: var(--line-height-relaxed);
        }

        .firmantes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: var(--space-3);
        }

        .firmante-card {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3);
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-lg);
        }

        .firmante-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--color-gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .firmante-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .firmante-avatar i {
            color: var(--color-gray-600);
        }

        .firmante-info {
            min-width: 0;
        }

        .firmante-info strong {
            display: block;
            font-size: var(--font-size-sm);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .firmante-info small {
            display: block;
            font-size: var(--font-size-xs);
            color: var(--color-gray-400);
        }

        .more-firmantes {
            justify-content: center;
            color: var(--color-primary);
            font-weight: var(--font-weight-medium);
        }

        .proposal-sidebar {
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

        .signature-progress-large {
            text-align: center;
        }

        .progress-circle {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto var(--space-4);
        }

        .progress-circle svg {
            transform: rotate(-90deg);
        }

        .progress-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 8;
        }

        .progress-fill {
            fill: none;
            stroke: url(#gradient);
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 283;
            transition: stroke-dashoffset 1s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .progress-number {
            font-size: var(--font-size-4xl);
            font-weight: var(--font-weight-bold);
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .progress-info {
            text-align: center;
        }

        .progress-stat {
            margin-bottom: var(--space-3);
        }

        .progress-stat strong {
            display: block;
            font-size: var(--font-size-3xl);
            color: var(--color-primary);
        }

        .progress-stat span {
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .threshold-alert {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-3);
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            border-radius: var(--radius-lg);
            color: var(--color-primary);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
        }

        .threshold-alert.success {
            background: rgba(0, 255, 170, 0.1);
            border-color: rgba(0, 255, 170, 0.3);
            color: var(--color-secondary);
        }

        .signed-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-4);
            background: rgba(0, 255, 170, 0.1);
            border: 1px solid rgba(0, 255, 170, 0.3);
            border-radius: var(--radius-lg);
            color: var(--color-secondary);
            margin-bottom: var(--space-4);
            font-weight: var(--font-weight-medium);
        }

        .form-check {
            margin-bottom: var(--space-4);
        }

        .form-check label {
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
            cursor: pointer;
        }

        .form-check small {
            color: var(--color-gray-400);
            font-size: var(--font-size-xs);
        }

        .estado-info {
            display: flex;
            gap: var(--space-3);
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
        }

        .estado-info i {
            font-size: var(--font-size-2xl);
            flex-shrink: 0;
        }

        .estado-info strong {
            display: block;
            margin-bottom: var(--space-1);
        }

        .estado-info p {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
            margin: 0;
        }

        .estado-info.estado-revision {
            background: rgba(255, 170, 0, 0.1);
            border: 1px solid rgba(255, 170, 0, 0.3);
        }

        .estado-info.estado-en_progreso {
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
        }

        .estado-info.estado-completada {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
        }

        .estado-info.estado-rechazada {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.3);
        }

        .firmas-finales {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-3);
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
        }

        .share-buttons {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .share-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-3);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .share-btn:hover {
            background: rgba(0, 153, 255, 0.1);
            border-color: var(--color-primary);
            color: var(--color-primary);
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
            .proposal-container {
                grid-template-columns: 1fr;
            }

            .proposal-sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .proposal-title {
                font-size: var(--font-size-2xl);
            }

            .firmantes-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <!-- SVG Gradient Definition -->
    <svg width="0" height="0" style="position: absolute;">
        <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#0099ff;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#00ffaa;stop-opacity:1" />
            </linearGradient>
        </defs>
    </svg>

    <script>
    function copyToClipboard() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(function() {
            alert('Enlace copiado al portapapeles');
        }, function() {
            alert('No se pudo copiar el enlace');
        });
    }
    </script>
</body>
</html>
