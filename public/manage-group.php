<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$grupo_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$grupo_id) {
    set_flash('error', 'Grupo no encontrado');
    redirect(base_url('public/my-groups.php'));
}

$group_model = new Group();
$permission_model = new Permission();

// Verificar que el usuario sea coordinador o admin
if (!is_group_coordinator($grupo_id, $user_id) && !is_admin()) {
    set_flash('error', 'No tienes permisos para administrar este grupo');
    redirect(base_url('public/group.php?id=' . $grupo_id));
}

$grupo = $group_model->getById($grupo_id);
if (!$grupo) {
    set_flash('error', 'Grupo no encontrado');
    redirect(base_url('public/my-groups.php'));
}

$tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'requests';
$stats = $group_model->getStats($grupo_id);

// Procesar acciones
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
        redirect($_SERVER['REQUEST_URI']);
    }

    $action = input('action');

    switch ($action) {
        case 'approve':
            $membership_id = (int) input('membership_id');
            $result = $group_model->approveMembership($membership_id, $user_id);
            set_flash($result ? 'success' : 'error', $result ? 'Solicitud aprobada' : 'Error al aprobar');
            break;

        case 'reject':
            $membership_id = (int) input('membership_id');
            $result = $group_model->rejectMembership($membership_id);
            set_flash($result ? 'success' : 'error', $result ? 'Solicitud rechazada' : 'Error al rechazar');
            break;

        case 'remove':
            $usuario_id = (int) input('usuario_id');
            if ($usuario_id === $user_id) {
                set_flash('error', 'No puedes eliminarte a ti mismo');
            } else {
                $result = $group_model->removeMember($grupo_id, $usuario_id);
                set_flash($result ? 'success' : 'error', $result ? 'Miembro eliminado' : 'Error al eliminar');
            }
            break;

        case 'set_coordinator':
            $usuario_id = (int) input('usuario_id');
            $is_coordinator = (int) input('is_coordinator');
            $result = $group_model->setCoordinator($grupo_id, $usuario_id, $is_coordinator);
            set_flash($result ? 'success' : 'error', $result ? 'Rol actualizado' : 'Error al actualizar');
            break;

        case 'update_role':
            $usuario_id = (int) input('usuario_id');
            $rol_id = (int) input('rol_id');
            $result = $group_model->updateMemberRole($grupo_id, $usuario_id, $rol_id);
            set_flash($result ? 'success' : 'error', $result ? 'Rol actualizado' : 'Error al actualizar');
            break;
    }

    redirect($_SERVER['REQUEST_URI']);
}

$pending_requests = $group_model->getPendingRequests($grupo_id);
$members = $group_model->getMembers($grupo_id, 'aprobado');
$roles = $permission_model->getAllRoles();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar <?= sanitize($grupo['nombre']) ?> - TrazaFI</title>
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

            <div class="page-header">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= base_url('public/my-groups.php') ?>">Mis Grupos</a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="<?= base_url('public/group.php?id=' . $grupo_id) ?>"><?= sanitize($grupo['nombre']) ?></a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Administrar</span>
                    </nav>
                    <h1>Administrar Grupo</h1>
                    <p><?= sanitize($grupo['nombre']) ?></p>
                </div>
                <a href="<?= base_url('public/group.php?id=' . $grupo_id) ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al Grupo
                </a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div>
                        <div class="stat-value"><?= $stats['miembros'] ?></div>
                        <div class="stat-label">Miembros</div>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div>
                        <div class="stat-value"><?= $stats['pendientes'] ?></div>
                        <div class="stat-label">Solicitudes Pendientes</div>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-bullhorn"></i>
                    <div>
                        <div class="stat-value"><?= $stats['avisos'] ?></div>
                        <div class="stat-label">Avisos Publicados</div>
                    </div>
                </div>
            </div>

            <div class="tabs">
                <a href="?id=<?= $grupo_id ?>&tab=requests" class="tab <?= $tab === 'requests' ? 'active' : '' ?>">
                    <i class="fas fa-user-clock"></i>
                    Solicitudes
                    <?php if (count($pending_requests) > 0): ?>
                        <span class="badge"><?= count($pending_requests) ?></span>
                    <?php endif; ?>
                </a>
                <a href="?id=<?= $grupo_id ?>&tab=members" class="tab <?= $tab === 'members' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    Miembros
                </a>
            </div>

            <?php if ($tab === 'requests'): ?>
                <div class="tab-content">
                    <h2>Solicitudes Pendientes</h2>

                    <?php if (empty($pending_requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No hay solicitudes pendientes</p>
                        </div>
                    <?php else: ?>
                        <div class="requests-list">
                            <?php foreach ($pending_requests as $request): ?>
                                <div class="request-card">
                                    <div class="request-user">
                                        <div class="user-avatar">
                                            <?php if ($request['imagen_perfil']): ?>
                                                <img src="<?= upload_url($request['imagen_perfil']) ?>" alt="<?= sanitize($request['nombre']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-info">
                                            <h3><?= sanitize($request['nombre'] . ' ' . $request['apellidos']) ?></h3>
                                            <p><?= sanitize($request['email']) ?></p>
                                            <span class="request-date">
                                                <i class="fas fa-clock"></i>
                                                Solicitado <?= time_ago($request['fecha_solicitud']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="request-actions">
                                        <form method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="membership_id" value="<?= $request['membership_id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Aprobar
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="membership_id" value="<?= $request['membership_id'] ?>">
                                            <button type="submit" class="btn btn-error btn-sm">
                                                <i class="fas fa-times"></i> Rechazar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab === 'members'): ?>
                <div class="tab-content">
                    <h2>Miembros del Grupo</h2>

                    <div class="members-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Coordinador</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td>
                                            <div class="member-cell">
                                                <div class="user-avatar-sm">
                                                    <?php if ($member['imagen_perfil']): ?>
                                                        <img src="<?= upload_url($member['imagen_perfil']) ?>" alt="<?= sanitize($member['nombre']) ?>">
                                                    <?php else: ?>
                                                        <i class="fas fa-user-circle"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="member-name"><?= sanitize($member['nombre'] . ' ' . $member['apellidos']) ?></div>
                                                    <div class="member-email"><?= sanitize($member['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($member['id'] === $user_id): ?>
                                                <span class="badge"><?= sanitize($member['rol_nombre'] ?? 'Miembro') ?></span>
                                            <?php else: ?>
                                                <select class="role-select" data-user-id="<?= $member['id'] ?>">
                                                    <?php foreach ($roles as $role): ?>
                                                        <option value="<?= $role['id'] ?>" <?= $member['rol_nombre'] === $role['nombre'] ? 'selected' : '' ?>>
                                                            <?= sanitize($role['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($member['id'] === $user_id): ?>
                                                <span class="badge badge-success">Sí</span>
                                            <?php else: ?>
                                                <label class="switch">
                                                    <input type="checkbox"
                                                           class="coordinator-toggle"
                                                           data-user-id="<?= $member['id'] ?>"
                                                           <?= $member['es_coordinador'] ? 'checked' : '' ?>>
                                                    <span class="slider"></span>
                                                </label>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($member['id'] !== $user_id): ?>
                                                <button class="btn-icon btn-error" onclick="confirmRemove(<?= $member['id'] ?>, '<?= sanitize($member['nombre']) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <form id="removeForm" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="usuario_id" id="removeUserId">
    </form>

    <form id="coordinatorForm" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_coordinator">
        <input type="hidden" name="usuario_id" id="coordUserId">
        <input type="hidden" name="is_coordinator" id="coordValue">
    </form>

    <form id="roleForm" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_role">
        <input type="hidden" name="usuario_id" id="roleUserId">
        <input type="hidden" name="rol_id" id="roleValue">
    </form>

    <script>
    function confirmRemove(userId, userName) {
        if (confirm(`¿Estás seguro de eliminar a ${userName} del grupo?`)) {
            document.getElementById('removeUserId').value = userId;
            document.getElementById('removeForm').submit();
        }
    }

    document.querySelectorAll('.coordinator-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            document.getElementById('coordUserId').value = this.dataset.userId;
            document.getElementById('coordValue').value = this.checked ? '1' : '0';
            document.getElementById('coordinatorForm').submit();
        });
    });

    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('roleUserId').value = this.dataset.userId;
            document.getElementById('roleValue').value = this.value;
            document.getElementById('roleForm').submit();
        });
    });
    </script>

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

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-3);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .breadcrumb a {
            color: var(--color-gray-400);
        }

        .breadcrumb i {
            font-size: 0.7em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
        }

        .stat-card i {
            font-size: var(--font-size-3xl);
            color: var(--color-primary);
        }

        .stat-value {
            font-size: var(--font-size-2xl);
            font-weight: var(--font-weight-bold);
        }

        .stat-label {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .tabs {
            display: flex;
            gap: var(--space-2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: var(--space-8);
        }

        .tab {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-4) var(--space-6);
            color: var(--color-gray-400);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all var(--transition-fast);
            position: relative;
        }

        .tab:hover {
            color: var(--color-white);
        }

        .tab.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }

        .tab .badge {
            padding: 2px 8px;
            background: var(--color-error);
            color: var(--color-white);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
        }

        .tab-content h2 {
            margin-bottom: var(--space-6);
        }

        .requests-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }

        .request-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-6);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
        }

        .request-user {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            flex: 1;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--color-gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-3xl);
            color: var(--color-gray-600);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info h3 {
            margin-bottom: var(--space-1);
            font-size: var(--font-size-lg);
        }

        .user-info p {
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
            margin-bottom: var(--space-2);
        }

        .request-date {
            font-size: var(--font-size-xs);
            color: var(--color-gray-500);
        }

        .request-actions {
            display: flex;
            gap: var(--space-2);
        }

        .btn-success {
            background: var(--color-success);
            color: var(--color-black);
        }

        .btn-success:hover {
            background: var(--color-secondary);
        }

        .btn-error {
            background: var(--color-error);
        }

        .btn-error:hover {
            background: #ff2222;
        }

        .members-table {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: var(--space-4);
            text-align: left;
        }

        th {
            background: var(--color-gray-800);
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-gray-300);
        }

        tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tr:last-child {
            border-bottom: none;
        }

        .member-cell {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .user-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--color-gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-xl);
            color: var(--color-gray-600);
        }

        .user-avatar-sm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-name {
            font-weight: var(--font-weight-medium);
        }

        .member-email {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .role-select {
            padding: var(--space-2) var(--space-3);
            background: var(--color-gray-800);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            color: var(--color-white);
            font-size: var(--font-size-sm);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--color-gray-700);
            transition: var(--transition-normal);
            border-radius: var(--radius-full);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: var(--transition-normal);
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--color-success);
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .empty-state {
            text-align: center;
            padding: var(--space-12);
            color: var(--color-gray-400);
        }

        .empty-state i {
            font-size: var(--font-size-5xl);
            margin-bottom: var(--space-4);
            color: var(--color-success);
        }

        .badge {
            padding: var(--space-1) var(--space-3);
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }

        .badge-success {
            background: rgba(0, 255, 136, 0.2);
            color: var(--color-success);
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
            .page-header {
                flex-direction: column;
            }

            .request-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .request-actions {
                width: 100%;
            }

            .request-actions form {
                flex: 1;
            }

            .request-actions button {
                width: 100%;
            }

            .members-table {
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }
        }
    </style>
</body>
</html>
