<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$notif_model = new Notificacion();

// Filtro
$filtro = isset($_GET['filtro']) ? sanitize($_GET['filtro']) : 'todas'; // todas, no_leidas

// Procesar acciones
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }

    // Marcar como leída
    if (isset($_POST['mark_read'])) {
        $notif_id = (int) input('notif_id');
        $result = $notif_model->markAsRead($notif_id, $user_id);
        echo json_encode($result);
        exit;
    }

    // Marcar todas como leídas
    if (isset($_POST['mark_all_read'])) {
        $result = $notif_model->markAllAsRead($user_id);
        echo json_encode($result);
        exit;
    }

    // Eliminar notificación
    if (isset($_POST['delete'])) {
        $notif_id = (int) input('notif_id');
        $result = $notif_model->delete($notif_id, $user_id);
        echo json_encode($result);
        exit;
    }

    // Eliminar todas las leídas
    if (isset($_POST['delete_all_read'])) {
        $result = $notif_model->deleteAllRead($user_id);
        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Obtener notificaciones
$notificaciones = $filtro === 'no_leidas'
    ? $notif_model->getUnread($user_id)
    : $notif_model->getUserNotifications($user_id);

$stats = $notif_model->getStats($user_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - TrazaFI</title>
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

            <div class="page-header">
                <div>
                    <h1><i class="fas fa-bell"></i> Notificaciones</h1>
                    <p>Mantente al tanto de las novedades</p>
                </div>
                <div class="header-actions">
                    <?php if ($stats['no_leidas'] > 0): ?>
                        <button onclick="markAllAsRead()" class="btn btn-outline">
                            <i class="fas fa-check-double"></i>
                            Marcar Todas como Leídas
                        </button>
                    <?php endif; ?>
                    <?php if ($stats['leidas'] > 0): ?>
                        <form method="POST" style="display: inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_all_read" value="1">
                            <button type="submit"
                                    class="btn btn-outline"
                                    onclick="return confirm('¿Eliminar todas las notificaciones leídas?')">
                                <i class="fas fa-trash"></i>
                                Limpiar Leídas
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-box stat-primary">
                    <div class="stat-value"><?= $stats['no_leidas'] ?></div>
                    <div class="stat-label">No Leídas</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= $stats['leidas'] ?></div>
                    <div class="stat-label">Leídas</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= $stats['ultimas_24h'] ?></div>
                    <div class="stat-label">Últimas 24h</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filter-tabs">
                <a href="<?= base_url('public/notificaciones.php?filtro=todas') ?>"
                   class="filter-tab <?= $filtro === 'todas' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Todas
                </a>
                <a href="<?= base_url('public/notificaciones.php?filtro=no_leidas') ?>"
                   class="filter-tab <?= $filtro === 'no_leidas' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> No Leídas
                    <?php if ($stats['no_leidas'] > 0): ?>
                        <span class="badge"><?= $stats['no_leidas'] ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Lista de notificaciones -->
            <?php if (empty($notificaciones)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No hay notificaciones</h3>
                    <p>
                        <?= $filtro === 'no_leidas' ? 'No tienes notificaciones sin leer' : 'Aún no has recibido notificaciones' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="notificaciones-list">
                    <?php foreach ($notificaciones as $notif): ?>
                        <?php
                        $icon = $notif_model->getNotificationIcon($notif['tipo']);
                        $color = $notif_model->getNotificationColor($notif['tipo']);
                        $url = $notif_model->getNotificationUrl($notif);
                        ?>
                        <div class="notif-item <?= !$notif['leida'] ? 'unread' : '' ?>"
                             data-notif-id="<?= $notif['id'] ?>">
                            <div class="notif-icon notif-<?= $color ?>">
                                <i class="fas <?= $icon ?>"></i>
                            </div>

                            <div class="notif-content">
                                <a href="<?= $url ?>"
                                   class="notif-message"
                                   onclick="markAsRead(<?= $notif['id'] ?>)">
                                    <?= sanitize($notif['mensaje']) ?>
                                </a>

                                <div class="notif-meta">
                                    <span class="notif-time">
                                        <i class="fas fa-clock"></i>
                                        <?= time_ago($notif['fecha_creacion']) ?>
                                    </span>
                                    <?php if (!$notif['leida']): ?>
                                        <span class="notif-unread-indicator">
                                            <i class="fas fa-circle"></i>
                                            No leída
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="notif-actions">
                                <?php if (!$notif['leida']): ?>
                                    <button class="notif-action-btn"
                                            onclick="markAsRead(<?= $notif['id'] ?>)"
                                            title="Marcar como leída">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="notif-action-btn delete-btn"
                                        onclick="deleteNotif(<?= $notif['id'] ?>)"
                                        title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
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
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-6);
            gap: var(--space-6);
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

        .header-actions {
            display: flex;
            gap: var(--space-3);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .stat-box {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            text-align: center;
        }

        .stat-box.stat-primary {
            border-color: var(--color-primary);
            background: rgba(0, 153, 255, 0.1);
        }

        .stat-value {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            margin-bottom: var(--space-1);
        }

        .stat-label {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .filter-tabs {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-6);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .filter-tab {
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

        .filter-tab:hover {
            color: var(--color-white);
        }

        .filter-tab.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }

        .filter-tab .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 var(--space-2);
            background: var(--color-primary);
            color: var(--color-black);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-bold);
        }

        .notificaciones-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .notif-item {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            transition: all var(--transition-fast);
        }

        .notif-item.unread {
            background: rgba(0, 153, 255, 0.05);
            border-left: 4px solid var(--color-primary);
        }

        .notif-item:hover {
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(0, 153, 255, 0.1);
        }

        .notif-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: var(--font-size-xl);
        }

        .notif-primary {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .notif-secondary {
            background: rgba(0, 255, 170, 0.2);
            color: var(--color-secondary);
        }

        .notif-warning {
            background: rgba(255, 170, 0, 0.2);
            color: var(--color-warning);
        }

        .notif-success {
            background: rgba(0, 255, 136, 0.2);
            color: var(--color-success);
        }

        .notif-info {
            background: rgba(0, 204, 255, 0.2);
            color: #00ccff;
        }

        .notif-gray {
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-gray-400);
        }

        .notif-content {
            flex: 1;
            min-width: 0;
        }

        .notif-message {
            display: block;
            color: var(--color-white);
            text-decoration: none;
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-2);
            transition: color var(--transition-fast);
        }

        .notif-message:hover {
            color: var(--color-primary);
        }

        .notif-meta {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .notif-time {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .notif-unread-indicator {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-primary);
            font-weight: var(--font-weight-semibold);
        }

        .notif-unread-indicator i {
            font-size: 8px;
        }

        .notif-actions {
            display: flex;
            gap: var(--space-2);
            align-items: flex-start;
        }

        .notif-action-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-400);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .notif-action-btn:hover {
            background: rgba(0, 153, 255, 0.1);
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        .notif-action-btn.delete-btn:hover {
            background: rgba(255, 68, 68, 0.1);
            border-color: var(--color-error);
            color: var(--color-error);
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .header-actions .btn {
                width: 100%;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .notif-item {
                flex-wrap: wrap;
            }

            .notif-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>

    <script>
    const csrfToken = '<?= csrf_token() ?>';

    function markAsRead(notifId) {
        const formData = new FormData();
        formData.append('mark_read', '1');
        formData.append('notif_id', notifId);
        formData.append('csrf_token', csrfToken);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = document.querySelector(`[data-notif-id="${notifId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    const indicator = item.querySelector('.notif-unread-indicator');
                    if (indicator) indicator.remove();
                    const readBtn = item.querySelector('.notif-action-btn:not(.delete-btn)');
                    if (readBtn) readBtn.remove();
                }
                // Actualizar contador en navbar
                updateNavbarCount();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function markAllAsRead() {
        const formData = new FormData();
        formData.append('mark_all_read', '1');
        formData.append('csrf_token', csrfToken);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function deleteNotif(notifId) {
        if (!confirm('¿Eliminar esta notificación?')) return;

        const formData = new FormData();
        formData.append('delete', '1');
        formData.append('notif_id', notifId);
        formData.append('csrf_token', csrfToken);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = document.querySelector(`[data-notif-id="${notifId}"]`);
                if (item) {
                    item.style.opacity = '0';
                    setTimeout(() => item.remove(), 300);
                }
                updateNavbarCount();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function updateNavbarCount() {
        // Actualizar el contador del navbar
        setTimeout(() => location.reload(), 500);
    }
    </script>
</body>
</html>
