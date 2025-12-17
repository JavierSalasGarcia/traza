<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$ticket_model = new Ticket();

// Obtener ID del ticket
$ticket_id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!$ticket_id) {
    set_flash('error', 'Ticket no encontrado');
    redirect(base_url('public/tickets.php'));
}

$ticket = $ticket_model->getById($ticket_id);

if (!$ticket) {
    set_flash('error', 'Ticket no encontrado');
    redirect(base_url('public/tickets.php'));
}

// Obtener comentarios
$comentarios = $ticket_model->getComments($ticket_id);

// Verificar si el usuario votó
$user_voted = $ticket_model->hasVoted($ticket_id, $user_id);
$total_votos = $ticket_model->getVoteCount($ticket_id);

// Procesar acciones
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        // Añadir comentario
        if (isset($_POST['add_comment'])) {
            $comentario = trim(input('comentario'));
            $es_solucion = isset($_POST['es_solucion']) && $_POST['es_solucion'] === '1';

            if (!empty($comentario)) {
                $result = $ticket_model->addComment($ticket_id, $user_id, $comentario, $es_solucion);
                set_flash($result['success'] ? 'success' : 'error', $result['message']);
                redirect($_SERVER['REQUEST_URI']);
            }
        }

        // Cambiar estado (solo admins)
        if (isset($_POST['change_estado']) && is_admin()) {
            $nuevo_estado = input('nuevo_estado');
            $result = $ticket_model->updateEstado($ticket_id, $nuevo_estado, $user_id);
            set_flash($result['success'] ? 'success' : 'error', $result['message']);
            redirect($_SERVER['REQUEST_URI']);
        }

        // Cambiar prioridad (solo admins)
        if (isset($_POST['change_prioridad']) && is_admin()) {
            $nueva_prioridad = input('nueva_prioridad');
            $result = $ticket_model->updatePrioridad($ticket_id, $nueva_prioridad, $user_id);
            set_flash($result['success'] ? 'success' : 'error', $result['message']);
            redirect($_SERVER['REQUEST_URI']);
        }

        // Asignar ticket (solo admins)
        if (isset($_POST['assign_ticket']) && is_admin()) {
            $asignado_id = (int) input('asignado_id');
            $result = $ticket_model->assignTo($ticket_id, $asignado_id, $user_id);
            set_flash($result['success'] ? 'success' : 'error', $result['message']);
            redirect($_SERVER['REQUEST_URI']);
        }
    }
}

$is_owner = $ticket['solicitante_id'] == $user_id;
$is_assigned = $ticket['asignado_a'] == $user_id;

// Estados y tipos
$estados = [
    'pendiente' => 'Pendiente',
    'en_revision' => 'En Revisión',
    'en_desarrollo' => 'En Desarrollo',
    'completado' => 'Completado',
    'rechazado' => 'Rechazado',
    'cancelado' => 'Cancelado'
];

$tipos = [
    'modulo_personalizado' => 'Módulo Personalizado',
    'mejora' => 'Mejora',
    'error' => 'Reporte de Error',
    'consulta' => 'Consulta'
];

// Obtener usuarios para asignación (solo admins)
$developers = [];
if (is_admin()) {
    $db = Database::getInstance();
    $developers = $db->query("SELECT id, nombre, email FROM usuarios WHERE es_admin = 1 OR id = :user_id ORDER BY nombre")
                     ->bind(':user_id', $user_id)
                     ->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket_id ?> - <?= sanitize($ticket['titulo']) ?></title>
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
                <a href="<?= base_url('public/tickets.php') ?>">Tickets</a>
                <i class="fas fa-chevron-right"></i>
                <span>Ticket #<?= $ticket_id ?></span>
            </div>

            <div class="ticket-container">
                <!-- Columna principal -->
                <div class="ticket-main">
                    <div class="ticket-header-full">
                        <div class="ticket-meta-header">
                            <div class="ticket-badges-large">
                                <span class="tipo-badge-large tipo-<?= $ticket['tipo'] ?>">
                                    <?= $tipos[$ticket['tipo']] ?>
                                </span>
                                <span class="estado-badge-large estado-<?= $ticket['estado'] ?>">
                                    <?= $estados[$ticket['estado']] ?>
                                </span>
                                <span class="prioridad-badge-large prioridad-<?= $ticket['prioridad'] ?>">
                                    <i class="fas fa-flag"></i>
                                    Prioridad: <?= ucfirst($ticket['prioridad']) ?>
                                </span>
                            </div>
                            <div class="ticket-id-large">#<?= $ticket_id ?></div>
                        </div>

                        <h1 class="ticket-title-full"><?= sanitize($ticket['titulo']) ?></h1>

                        <div class="ticket-author-info">
                            <div class="author-avatar">
                                <?php if ($ticket['solicitante_imagen']): ?>
                                    <img src="<?= upload_url($ticket['solicitante_imagen']) ?>" alt="<?= sanitize($ticket['solicitante_nombre']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="author-details">
                                <strong><?= sanitize($ticket['solicitante_nombre']) ?></strong>
                                <div class="author-meta">
                                    <span><i class="fas fa-calendar"></i> Creado <?= time_ago($ticket['fecha_creacion']) ?></span>
                                    <?php if ($ticket['grupo_nombre']): ?>
                                        <span class="separator">•</span>
                                        <span><i class="fas fa-users"></i> <?= sanitize($ticket['grupo_nombre']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ticket-content-full">
                        <h2>Descripción</h2>
                        <div class="description-text">
                            <?= nl2br(sanitize($ticket['descripcion'])) ?>
                        </div>
                    </div>

                    <?php if ($ticket['asignado_nombre']): ?>
                        <div class="assigned-info">
                            <i class="fas fa-user-check"></i>
                            <span>Asignado a <strong><?= sanitize($ticket['asignado_nombre']) ?></strong></span>
                            <?php if ($ticket['fecha_inicio']): ?>
                                <span class="separator">•</span>
                                <span>Iniciado el <?= format_date($ticket['fecha_inicio']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Comentarios -->
                    <div class="comments-section-ticket">
                        <h2><i class="fas fa-comments"></i> Discusión (<?= count($comentarios) ?>)</h2>

                        <?php if (!empty($comentarios)): ?>
                            <div class="ticket-comments-list">
                                <?php foreach ($comentarios as $com): ?>
                                    <div class="ticket-comment <?= $com['es_solucion'] ? 'is-solution' : '' ?>">
                                        <div class="comment-avatar">
                                            <?php if ($com['usuario_imagen']): ?>
                                                <img src="<?= upload_url($com['usuario_imagen']) ?>" alt="<?= sanitize($com['usuario_nombre']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="comment-content">
                                            <div class="comment-header">
                                                <strong><?= sanitize($com['usuario_nombre']) ?></strong>
                                                <?php if ($com['es_solucion']): ?>
                                                    <span class="solution-badge">
                                                        <i class="fas fa-check-circle"></i> Solución
                                                    </span>
                                                <?php endif; ?>
                                                <span class="comment-time"><?= time_ago($com['fecha_creacion']) ?></span>
                                            </div>
                                            <div class="comment-body">
                                                <?= nl2br(sanitize($com['comentario'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Formulario de comentario -->
                        <div class="add-comment-form">
                            <h3>Añadir Comentario</h3>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="add_comment" value="1">

                                <textarea name="comentario"
                                          class="form-control"
                                          rows="4"
                                          placeholder="Escribe tu comentario..."
                                          required></textarea>

                                <div class="comment-form-actions">
                                    <?php if (is_admin() || $is_assigned): ?>
                                        <div class="form-check">
                                            <input type="checkbox" id="es_solucion" name="es_solucion" value="1">
                                            <label for="es_solucion">
                                                <i class="fas fa-check-circle"></i>
                                                Marcar como solución (completará el ticket)
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-comment"></i> Añadir Comentario
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="ticket-sidebar">
                    <!-- Votación -->
                    <div class="sidebar-card">
                        <h3>Votos Comunitarios</h3>
                        <div class="vote-section">
                            <button class="vote-btn-large <?= $user_voted ? 'voted' : '' ?>"
                                    onclick="toggleVote(<?= $ticket_id ?>)">
                                <i class="fas fa-arrow-up"></i>
                                <span class="vote-count-large"><?= $total_votos ?></span>
                            </button>
                            <p class="vote-help">
                                <?= $user_voted ? 'Ya votaste por este ticket' : 'Vota si te interesa esta funcionalidad' ?>
                            </p>
                        </div>
                    </div>

                    <!-- Acciones para admins -->
                    <?php if (is_admin()): ?>
                        <div class="sidebar-card">
                            <h3>Administración</h3>

                            <!-- Cambiar estado -->
                            <form method="POST" class="admin-action-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="change_estado" value="1">
                                <label for="nuevo_estado">Estado:</label>
                                <select name="nuevo_estado" id="nuevo_estado" class="form-control">
                                    <?php foreach ($estados as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $ticket['estado'] === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-outline btn-sm btn-block">
                                    <i class="fas fa-exchange-alt"></i> Cambiar Estado
                                </button>
                            </form>

                            <!-- Cambiar prioridad -->
                            <form method="POST" class="admin-action-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="change_prioridad" value="1">
                                <label for="nueva_prioridad">Prioridad:</label>
                                <select name="nueva_prioridad" id="nueva_prioridad" class="form-control">
                                    <option value="baja" <?= $ticket['prioridad'] === 'baja' ? 'selected' : '' ?>>Baja</option>
                                    <option value="media" <?= $ticket['prioridad'] === 'media' ? 'selected' : '' ?>>Media</option>
                                    <option value="alta" <?= $ticket['prioridad'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                                </select>
                                <button type="submit" class="btn btn-outline btn-sm btn-block">
                                    <i class="fas fa-flag"></i> Cambiar Prioridad
                                </button>
                            </form>

                            <!-- Asignar desarrollador -->
                            <?php if ($ticket['estado'] === 'pendiente' || !$ticket['asignado_a']): ?>
                                <form method="POST" class="admin-action-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="assign_ticket" value="1">
                                    <label for="asignado_id">Asignar a:</label>
                                    <select name="asignado_id" id="asignado_id" class="form-control">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($developers as $dev): ?>
                                            <option value="<?= $dev['id'] ?>" <?= $ticket['asignado_a'] == $dev['id'] ? 'selected' : '' ?>>
                                                <?= sanitize($dev['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-success btn-sm btn-block">
                                        <i class="fas fa-user-plus"></i> Asignar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Información adicional -->
                    <div class="sidebar-card">
                        <h3>Información</h3>
                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label">Estado:</span>
                                <span class="info-value estado-<?= $ticket['estado'] ?>">
                                    <?= $estados[$ticket['estado']] ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Creado:</span>
                                <span class="info-value"><?= format_date($ticket['fecha_creacion']) ?></span>
                            </div>
                            <?php if ($ticket['fecha_completado']): ?>
                                <div class="info-item">
                                    <span class="info-label">Completado:</span>
                                    <span class="info-value"><?= format_date($ticket['fecha_completado']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <span class="info-label">Comentarios:</span>
                                <span class="info-value"><?= count($comentarios) ?></span>
                            </div>
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

        .ticket-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--space-8);
        }

        .ticket-main {
            min-width: 0;
        }

        .ticket-header-full {
            margin-bottom: var(--space-8);
        }

        .ticket-meta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-4);
        }

        .ticket-badges-large {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .tipo-badge-large, .estado-badge-large, .prioridad-badge-large {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
        }

        .tipo-badge-large {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .estado-badge-large {
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-gray-300);
        }

        .estado-pendiente {
            background: rgba(255, 170, 0, 0.2);
            color: var(--color-warning);
        }

        .estado-en_revision, .estado-en_desarrollo {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .estado-completado {
            background: rgba(0, 255, 136, 0.2);
            color: var(--color-success);
        }

        .prioridad-badge-large.prioridad-alta {
            background: rgba(255, 68, 68, 0.2);
            color: var(--color-error);
        }

        .prioridad-badge-large.prioridad-media {
            background: rgba(255, 170, 0, 0.2);
            color: var(--color-warning);
        }

        .ticket-id-large {
            font-family: monospace;
            font-size: var(--font-size-2xl);
            color: var(--color-gray-500);
            font-weight: var(--font-weight-bold);
        }

        .ticket-title-full {
            font-size: var(--font-size-4xl);
            line-height: var(--line-height-tight);
            margin-bottom: var(--space-6);
        }

        .ticket-author-info {
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

        .ticket-content-full {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            margin-bottom: var(--space-6);
        }

        .ticket-content-full h2 {
            font-size: var(--font-size-2xl);
            margin-bottom: var(--space-4);
            color: var(--color-primary);
        }

        .description-text {
            line-height: var(--line-height-relaxed);
            color: var(--color-gray-300);
            font-size: var(--font-size-lg);
        }

        .assigned-info {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-4);
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            border-radius: var(--radius-lg);
            color: var(--color-primary);
            margin-bottom: var(--space-6);
        }

        .comments-section-ticket {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
        }

        .comments-section-ticket h2 {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-6);
        }

        .ticket-comments-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .ticket-comment {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-4);
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-lg);
            border-left: 3px solid transparent;
        }

        .ticket-comment.is-solution {
            border-left-color: var(--color-success);
            background: rgba(0, 255, 136, 0.05);
        }

        .comment-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--color-gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .comment-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .comment-avatar i {
            font-size: var(--font-size-2xl);
            color: var(--color-gray-600);
        }

        .comment-content {
            flex: 1;
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-2);
        }

        .comment-time {
            font-size: var(--font-size-sm);
            color: var(--color-gray-500);
        }

        .solution-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-2);
            background: rgba(0, 255, 136, 0.2);
            color: var(--color-success);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
        }

        .comment-body {
            line-height: var(--line-height-relaxed);
            color: var(--color-gray-300);
        }

        .add-comment-form {
            padding-top: var(--space-6);
            border-top: 2px solid rgba(255, 255, 255, 0.1);
        }

        .add-comment-form h3 {
            margin-bottom: var(--space-4);
        }

        .comment-form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--space-4);
        }

        .ticket-sidebar {
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

        .vote-section {
            text-align: center;
        }

        .vote-btn-large {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: var(--space-6);
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-400);
            cursor: pointer;
            transition: all var(--transition-fast);
            margin-bottom: var(--space-3);
        }

        .vote-btn-large:hover {
            background: rgba(0, 153, 255, 0.1);
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        .vote-btn-large.voted {
            background: var(--gradient-primary);
            border-color: transparent;
            color: var(--color-black);
        }

        .vote-btn-large i {
            font-size: var(--font-size-3xl);
            margin-bottom: var(--space-2);
        }

        .vote-count-large {
            font-size: var(--font-size-4xl);
            font-weight: var(--font-weight-bold);
        }

        .vote-help {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
            margin: 0;
        }

        .admin-action-form {
            padding: var(--space-4);
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
        }

        .admin-action-form label {
            display: block;
            margin-bottom: var(--space-2);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
        }

        .admin-action-form .form-control {
            margin-bottom: var(--space-3);
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
            .ticket-container {
                grid-template-columns: 1fr;
            }

            .ticket-sidebar {
                position: static;
            }
        }
    </style>

    <script>
    function toggleVote(ticketId) {
        const btn = document.querySelector('.vote-btn-large');
        const formData = new FormData();
        formData.append('vote', '1');
        formData.append('ticket_id', ticketId);
        formData.append('csrf_token', '<?= csrf_token() ?>');

        fetch('<?= base_url('public/api/tickets.php') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                btn.classList.toggle('voted', data.voted);
                btn.querySelector('.vote-count-large').textContent = data.total_votos;
                document.querySelector('.vote-help').textContent = data.voted
                    ? 'Ya votaste por este ticket'
                    : 'Vota si te interesa esta funcionalidad';
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html>
