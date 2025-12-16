<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$group_model = new Group();

// Filtrar por tipo si se especifica
$tipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : null;
$valid_types = ['departamento', 'licenciatura', 'posgrado', 'capitulo'];

if ($tipo && !in_array($tipo, $valid_types)) {
    $tipo = null;
}

// Obtener grupos con información de membresía
$grupos = $group_model->getWithMembershipInfo($user_id, $tipo);

// Procesar solicitud de ingreso
if (is_post() && isset($_POST['request_join'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        $grupo_id = (int) input('grupo_id');
        $result = $group_model->requestJoin($grupo_id, $user_id);

        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('public/groups.php' . ($tipo ? '?tipo=' . $tipo : '')));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorar Grupos - TrazaFI</title>
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
                <h1>Explorar Grupos</h1>
                <p>Solicita ingreso a los grupos de tu interés</p>
            </div>

            <div class="filter-tabs">
                <a href="<?= base_url('public/groups.php') ?>" class="tab <?= !$tipo ? 'active' : '' ?>">
                    <i class="fas fa-th"></i> Todos
                </a>
                <a href="<?= base_url('public/groups.php?tipo=departamento') ?>" class="tab <?= $tipo === 'departamento' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i> Departamentos
                </a>
                <a href="<?= base_url('public/groups.php?tipo=licenciatura') ?>" class="tab <?= $tipo === 'licenciatura' ? 'active' : '' ?>">
                    <i class="fas fa-graduation-cap"></i> Licenciaturas
                </a>
                <a href="<?= base_url('public/groups.php?tipo=posgrado') ?>" class="tab <?= $tipo === 'posgrado' ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i> Posgrado
                </a>
                <a href="<?= base_url('public/groups.php?tipo=capitulo') ?>" class="tab <?= $tipo === 'capitulo' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Capítulos
                </a>
            </div>

            <?php if (empty($grupos)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No hay grupos disponibles</p>
                </div>
            <?php else: ?>
                <div class="groups-grid">
                    <?php foreach ($grupos as $grupo): ?>
                        <div class="group-card">
                            <?php if ($grupo['imagen_portada']): ?>
                                <div class="group-image" style="background-image: url('<?= upload_url($grupo['imagen_portada']) ?>')"></div>
                            <?php else: ?>
                                <div class="group-image group-icon">
                                    <i class="fas fa-<?= $grupo['tipo_grupo'] === 'departamento' ? 'building' :
                                                       ($grupo['tipo_grupo'] === 'licenciatura' ? 'graduation-cap' :
                                                       ($grupo['tipo_grupo'] === 'posgrado' ? 'user-graduate' : 'users')) ?>"></i>
                                </div>
                            <?php endif; ?>

                            <div class="group-content">
                                <div class="group-type">
                                    <?= ucfirst($grupo['tipo_grupo']) ?>
                                </div>

                                <h3 class="group-name"><?= sanitize($grupo['nombre']) ?></h3>

                                <?php if ($grupo['descripcion']): ?>
                                    <p class="group-description"><?= sanitize($grupo['descripcion']) ?></p>
                                <?php endif; ?>

                                <div class="group-meta">
                                    <span>
                                        <i class="fas fa-users"></i>
                                        <?= $grupo['total_miembros'] ?> miembros
                                    </span>
                                </div>

                                <div class="group-actions">
                                    <?php if ($grupo['membership_estado'] === 'aprobado'): ?>
                                        <a href="<?= base_url('public/group.php?id=' . $grupo['id']) ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-folder-open"></i> Ver Grupo
                                        </a>
                                        <?php if ($grupo['es_coordinador']): ?>
                                            <span class="badge badge-coordinator">Coordinador</span>
                                        <?php endif; ?>
                                    <?php elseif ($grupo['membership_estado'] === 'pendiente'): ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-clock"></i> Solicitud Pendiente
                                        </button>
                                    <?php elseif ($grupo['membership_estado'] === 'rechazado'): ?>
                                        <form method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="grupo_id" value="<?= $grupo['id'] ?>">
                                            <button type="submit" name="request_join" class="btn btn-outline btn-sm">
                                                <i class="fas fa-redo"></i> Solicitar Nuevamente
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="grupo_id" value="<?= $grupo['id'] ?>">
                                            <button type="submit" name="request_join" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Solicitar Ingreso
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
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
            margin-bottom: var(--space-8);
        }

        .page-header h1 {
            margin-bottom: var(--space-2);
        }

        .page-header p {
            color: var(--color-gray-400);
            margin-bottom: 0;
        }

        .filter-tabs {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-8);
            overflow-x: auto;
            padding-bottom: var(--space-2);
        }

        .tab {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
            text-decoration: none;
            font-weight: var(--font-weight-medium);
            font-size: var(--font-size-sm);
            transition: all var(--transition-fast);
            white-space: nowrap;
        }

        .tab:hover {
            background: rgba(0, 153, 255, 0.1);
            border-color: var(--color-primary);
            color: var(--color-white);
        }

        .tab.active {
            background: var(--gradient-primary);
            border-color: transparent;
            color: var(--color-white);
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
            overflow: hidden;
            transition: all var(--transition-normal);
        }

        .group-card:hover {
            transform: translateY(-3px);
            border-color: var(--color-primary);
            box-shadow: 0 20px 40px rgba(0, 153, 255, 0.1);
        }

        .group-image {
            height: 150px;
            background-size: cover;
            background-position: center;
            background-color: var(--color-gray-800);
        }

        .group-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-5xl);
            color: var(--color-primary);
            background: var(--gradient-primary);
        }

        .group-content {
            padding: var(--space-6);
        }

        .group-type {
            display: inline-block;
            padding: var(--space-1) var(--space-3);
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-3);
        }

        .group-name {
            font-size: var(--font-size-xl);
            margin-bottom: var(--space-3);
        }

        .group-description {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
        }

        .group-meta {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            padding: var(--space-3) 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: var(--space-4);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .group-meta span {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .group-actions {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .badge-coordinator {
            padding: var(--space-1) var(--space-3);
            background: rgba(0, 255, 170, 0.2);
            color: var(--color-secondary);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }

        .empty-state {
            text-align: center;
            padding: var(--space-16);
            color: var(--color-gray-400);
        }

        .empty-state i {
            font-size: var(--font-size-6xl);
            margin-bottom: var(--space-4);
            color: var(--color-gray-600);
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
            .groups-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
