<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$aviso_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$aviso_id) {
    set_flash('error', 'Aviso no encontrado');
    redirect(base_url('public/dashboard.php'));
}

$aviso_model = new Aviso();
$aviso = $aviso_model->getById($aviso_id);

if (!$aviso) {
    set_flash('error', 'Aviso no encontrado');
    redirect(base_url('public/dashboard.php'));
}

// Verificar permisos de visualización
$can_view = false;

if (!$aviso['grupo_id']) {
    // Aviso general, todos pueden ver
    $can_view = true;
} else {
    // Aviso de grupo, solo miembros pueden ver
    $can_view = is_group_member($aviso['grupo_id'], $user_id) || is_admin();
}

if (!$can_view) {
    set_flash('error', 'No tienes permisos para ver este aviso');
    redirect(base_url('public/dashboard.php'));
}

$can_edit = $aviso_model->canEdit($aviso_id, $user_id);
$archivos = $aviso_model->getArchivos($aviso_id);

// Verificar si el usuario ya dio like
$db = Database::getInstance();
$user_liked = $db->query("SELECT id FROM likes
                         WHERE usuario_id = :user_id
                         AND tipo_contenido = 'aviso'
                         AND contenido_id = :aviso_id")
                ->bind(':user_id', $user_id)
                ->bind(':aviso_id', $aviso_id)
                ->fetch();

$total_likes = $db->query("SELECT COUNT(*) FROM likes
                          WHERE tipo_contenido = 'aviso'
                          AND contenido_id = :aviso_id")
                 ->bind(':aviso_id', $aviso_id)
                 ->fetchColumn();

// Procesar acciones
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
        redirect($_SERVER['REQUEST_URI']);
    }

    $action = input('action');

    switch ($action) {
        case 'like':
            if (!$user_liked) {
                $db->query("INSERT INTO likes (usuario_id, tipo_contenido, contenido_id)
                           VALUES (:user_id, 'aviso', :aviso_id)")
                   ->bind(':user_id', $user_id)
                   ->bind(':aviso_id', $aviso_id)
                   ->execute();
            }
            break;

        case 'unlike':
            if ($user_liked) {
                $db->query("DELETE FROM likes
                           WHERE usuario_id = :user_id
                           AND tipo_contenido = 'aviso'
                           AND contenido_id = :aviso_id")
                   ->bind(':user_id', $user_id)
                   ->bind(':aviso_id', $aviso_id)
                   ->execute();
            }
            break;

        case 'delete':
            if ($can_edit) {
                $aviso_model->delete($aviso_id);
                set_flash('success', 'Aviso eliminado correctamente');
                redirect(base_url('public/dashboard.php'));
            }
            break;
    }

    redirect($_SERVER['REQUEST_URI']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($aviso['titulo']) ?> - TrazaFI</title>
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
                    <i class="fas fa-info-circle"></i>
                    <?= sanitize($flash['message']) ?>
                </div>
            <?php endforeach; ?>

            <div class="aviso-header">
                <nav class="breadcrumb">
                    <a href="<?= base_url('public/dashboard.php') ?>">Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <?php if ($aviso['grupo_id']): ?>
                        <a href="<?= base_url('public/group.php?id=' . $aviso['grupo_id']) ?>">
                            <?= sanitize($aviso['grupo_nombre']) ?>
                        </a>
                        <i class="fas fa-chevron-right"></i>
                    <?php endif; ?>
                    <span>Aviso</span>
                </nav>

                <div class="aviso-meta-header">
                    <div class="aviso-author">
                        <div class="author-avatar">
                            <?php if ($aviso['imagen_perfil']): ?>
                                <img src="<?= upload_url($aviso['imagen_perfil']) ?>" alt="<?= sanitize($aviso['nombre']) ?>">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="author-name"><?= sanitize($aviso['nombre'] . ' ' . $aviso['apellidos']) ?></div>
                            <div class="aviso-date">
                                <i class="fas fa-clock"></i>
                                <?= time_ago($aviso['fecha_creacion']) ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($can_edit): ?>
                        <div class="aviso-actions">
                            <a href="<?= base_url('public/create-aviso.php?id=' . $aviso_id) ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <button onclick="confirmDelete()" class="btn btn-error btn-sm">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <article class="aviso-content-card">
                <?php if ($aviso['destacado']): ?>
                    <div class="destacado-badge">
                        <i class="fas fa-star"></i> Destacado
                    </div>
                <?php endif; ?>

                <?php if ($aviso['categoria'] || $aviso['grupo_nombre']): ?>
                    <div class="aviso-tags">
                        <?php if ($aviso['grupo_nombre']): ?>
                            <span class="tag tag-group">
                                <i class="fas fa-users"></i>
                                <?= sanitize($aviso['grupo_nombre']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($aviso['categoria']): ?>
                            <span class="tag tag-category">
                                <i class="fas fa-tag"></i>
                                <?= sanitize($aviso['categoria']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($aviso['etiquetas']): ?>
                            <?php foreach (explode(',', $aviso['etiquetas']) as $etiqueta): ?>
                                <span class="tag">
                                    <?= sanitize(trim($etiqueta)) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <h1 class="aviso-title"><?= sanitize($aviso['titulo']) ?></h1>

                <div class="aviso-content">
                    <?= nl2br(htmlspecialchars($aviso['contenido'])) ?>
                </div>

                <?php if (!empty($archivos)): ?>
                    <div class="aviso-attachments">
                        <h3>
                            <i class="fas fa-paperclip"></i>
                            Archivos Adjuntos
                        </h3>
                        <div class="attachments-grid">
                            <?php foreach ($archivos as $archivo): ?>
                                <a href="<?= upload_url($archivo['ruta_archivo']) ?>"
                                   class="attachment-card"
                                   target="_blank"
                                   download>
                                    <i class="fas fa-file-<?= strpos($archivo['tipo_archivo'], 'pdf') !== false ? 'pdf' :
                                                            (strpos($archivo['tipo_archivo'], 'word') !== false ? 'word' :
                                                            (strpos($archivo['tipo_archivo'], 'excel') !== false ? 'excel' :
                                                            (strpos($archivo['tipo_archivo'], 'powerpoint') !== false ? 'powerpoint' :
                                                            (strpos($archivo['tipo_archivo'], 'image') !== false ? 'image' : 'alt')))) ?>"></i>
                                    <div class="attachment-info">
                                        <div class="attachment-name"><?= sanitize($archivo['nombre_archivo']) ?></div>
                                        <div class="attachment-size"><?= format_filesize($archivo['tamano_bytes']) ?></div>
                                    </div>
                                    <i class="fas fa-download"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($aviso['fecha_inicio_publicacion'] || $aviso['fecha_fin_publicacion']): ?>
                    <div class="aviso-schedule">
                        <h3>
                            <i class="fas fa-calendar"></i>
                            Programación
                        </h3>
                        <div class="schedule-info">
                            <?php if ($aviso['fecha_inicio_publicacion']): ?>
                                <div class="schedule-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Inicio de publicación: <?= format_datetime($aviso['fecha_inicio_publicacion']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($aviso['fecha_fin_publicacion']): ?>
                                <div class="schedule-item">
                                    <i class="fas fa-calendar-times"></i>
                                    <span>Fin de publicación: <?= format_datetime($aviso['fecha_fin_publicacion']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="aviso-interactions">
                    <form method="POST" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $user_liked ? 'unlike' : 'like' ?>">
                        <button type="submit" class="btn-interaction <?= $user_liked ? 'active' : '' ?>">
                            <i class="<?= $user_liked ? 'fas' : 'far' ?> fa-heart"></i>
                            <span><?= $total_likes ?> Me gusta</span>
                        </button>
                    </form>

                    <button class="btn-interaction">
                        <i class="far fa-comment"></i>
                        <span>Comentar</span>
                    </button>

                    <button class="btn-interaction">
                        <i class="fas fa-share"></i>
                        <span>Compartir</span>
                    </button>
                </div>
            </article>

            <?php
            // Incluir componente de comentarios
            $referencia_tipo = 'aviso';
            $referencia_id = $aviso_id;
            include __DIR__ . '/../core/includes/comments.php';
            ?>
        </div>
    </main>

    <form id="deleteForm" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
    </form>

    <script>
    function confirmDelete() {
        if (confirm('¿Estás seguro de eliminar este aviso? Esta acción no se puede deshacer.')) {
            document.getElementById('deleteForm').submit();
        }
    }
    </script>

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
            color: var(--color-gray-400);
        }

        .breadcrumb i {
            font-size: 0.7em;
        }

        .aviso-header {
            margin-bottom: var(--space-8);
        }

        .aviso-meta-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-6);
        }

        .aviso-author {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--color-gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-3xl);
            color: var(--color-gray-600);
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author-name {
            font-weight: var(--font-weight-semibold);
            color: var(--color-white);
        }

        .aviso-date {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .aviso-actions {
            display: flex;
            gap: var(--space-2);
        }

        .aviso-content-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            margin-bottom: var(--space-6);
            position: relative;
        }

        .destacado-badge {
            position: absolute;
            top: var(--space-6);
            right: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            background: linear-gradient(135deg, var(--color-warning), var(--color-secondary));
            color: var(--color-black);
            border-radius: var(--radius-full);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
        }

        .aviso-tags {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            margin-bottom: var(--space-6);
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            color: var(--color-gray-300);
        }

        .tag-group {
            background: rgba(0, 153, 255, 0.2);
            border-color: rgba(0, 153, 255, 0.3);
            color: var(--color-primary);
        }

        .tag-category {
            background: rgba(0, 255, 170, 0.2);
            border-color: rgba(0, 255, 170, 0.3);
            color: var(--color-secondary);
        }

        .aviso-title {
            font-size: var(--font-size-4xl);
            margin-bottom: var(--space-6);
            line-height: var(--line-height-tight);
        }

        .aviso-content {
            font-size: var(--font-size-lg);
            line-height: var(--line-height-relaxed);
            color: var(--color-gray-200);
            margin-bottom: var(--space-8);
        }

        .aviso-attachments,
        .aviso-schedule {
            margin-bottom: var(--space-8);
            padding: var(--space-6);
            background: var(--color-gray-800);
            border-radius: var(--radius-lg);
        }

        .aviso-attachments h3,
        .aviso-schedule h3 {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-4);
            font-size: var(--font-size-xl);
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--space-3);
        }

        .attachment-card {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--color-white);
            transition: all var(--transition-fast);
        }

        .attachment-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-2px);
        }

        .attachment-card > i:first-child {
            font-size: var(--font-size-2xl);
            color: var(--color-primary);
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-weight: var(--font-weight-medium);
            margin-bottom: var(--space-1);
        }

        .attachment-size {
            font-size: var(--font-size-xs);
            color: var(--color-gray-400);
        }

        .schedule-info {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: var(--color-gray-300);
        }

        .schedule-item i {
            color: var(--color-primary);
        }

        .aviso-interactions {
            display: flex;
            gap: var(--space-4);
            padding-top: var(--space-6);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-interaction {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
            font-size: var(--font-size-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-interaction:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--color-primary);
            color: var(--color-white);
        }

        .btn-interaction.active {
            background: rgba(255, 68, 68, 0.1);
            border-color: var(--color-error);
            color: var(--color-error);
        }

        .comments-section {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
        }

        .comments-section h2 {
            margin-bottom: var(--space-4);
        }

        .text-muted {
            color: var(--color-gray-400);
        }

        .btn-error {
            background: var(--color-error);
        }

        .btn-error:hover {
            background: #ff2222;
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

        .alert-error {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.3);
            color: var(--color-error);
        }

        @media (max-width: 768px) {
            .aviso-meta-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .aviso-actions {
                width: 100%;
            }

            .aviso-actions .btn {
                flex: 1;
            }

            .aviso-title {
                font-size: var(--font-size-3xl);
            }

            .attachments-grid {
                grid-template-columns: 1fr;
            }

            .aviso-interactions {
                flex-wrap: wrap;
            }

            .btn-interaction {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</body>
</html>
