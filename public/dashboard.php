<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$user_model = new User();
$stats = $user_model->getStats($user['id']);
$grupos = get_user_groups();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php if (!$user['email_verificado']): ?>
        <div class="verification-banner">
            <div class="container">
                <div class="banner-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Tu email no ha sido verificado.</span>
                    <a href="<?= base_url('public/verify-email.php') ?>" class="btn btn-sm btn-primary">Verificar Ahora</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="<?= base_url('public/dashboard.php') ?>">
                    <i class="fas fa-graduation-cap"></i>
                    <span class="logo-text">TrazaFI</span>
                </a>
            </div>

            <div class="nav-menu">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="<?= base_url('public/dashboard.php') ?>" class="nav-link active">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-bullhorn"></i> Avisos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-lightbulb"></i> Propuestas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-users"></i> Mis Grupos
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-actions">
                <div class="user-menu">
                    <button class="user-menu-toggle">
                        <span class="user-avatar">
                            <i class="fas fa-user-circle"></i>
                        </span>
                        <span class="user-name"><?= sanitize($user['nombre']) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul class="user-dropdown">
                        <li>
                            <a href="#" class="dropdown-link">
                                <i class="fas fa-user"></i> Mi Perfil
                            </a>
                        </li>
                        <li>
                            <a href="#" class="dropdown-link">
                                <i class="fas fa-bell"></i> Notificaciones
                            </a>
                        </li>
                        <li>
                            <a href="#" class="dropdown-link">
                                <i class="fas fa-cog"></i> Configuración
                            </a>
                        </li>
                        <li><div class="dropdown-divider"></div></li>
                        <li>
                            <a href="<?= base_url('public/logout.php') ?>" class="dropdown-link">
                                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

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
                <h1>Bienvenido, <?= sanitize($user['nombre']) ?></h1>
                <p>Red Social Académica - Facultad de Ingeniería UAEMEX</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['grupos'] ?></div>
                        <div class="stat-label">Mis Grupos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['propuestas'] ?></div>
                        <div class="stat-label">Propuestas Creadas</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-signature"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['firmas'] ?></div>
                        <div class="stat-label">Propuestas Firmadas</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['avisos'] ?></div>
                        <div class="stat-label">Avisos Publicados</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-main">
                    <div class="card">
                        <div class="card-header">
                            <h2>Avisos Recientes</h2>
                            <a href="#" class="card-link">Ver todos</a>
                        </div>
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No hay avisos disponibles</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-sidebar">
                    <div class="card">
                        <div class="card-header">
                            <h3>Mis Grupos</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($grupos)): ?>
                                <div class="empty-state-small">
                                    <p>No perteneces a ningún grupo</p>
                                    <a href="#" class="btn btn-sm btn-primary">Explorar Grupos</a>
                                </div>
                            <?php else: ?>
                                <ul class="group-list">
                                    <?php foreach ($grupos as $grupo): ?>
                                        <li class="group-item">
                                            <a href="#">
                                                <i class="fas fa-folder"></i>
                                                <?= sanitize($grupo['nombre']) ?>
                                                <?php if ($grupo['es_coordinador']): ?>
                                                    <span class="badge">Coordinador</span>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .verification-banner {
            background: rgba(255, 170, 0, 0.1);
            border-bottom: 1px solid rgba(255, 170, 0, 0.3);
            padding: var(--space-3);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .banner-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-3);
            color: var(--color-warning);
        }

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            transition: all var(--transition-normal);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--color-primary);
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-2xl);
            color: var(--color-white);
        }

        .stat-value {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-white);
        }

        .stat-label {
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-6);
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-header h2, .card-header h3 {
            margin: 0;
            font-size: var(--font-size-xl);
        }

        .card-link {
            color: var(--color-primary);
            font-size: var(--font-size-sm);
        }

        .card-body {
            padding: var(--space-6);
        }

        .empty-state {
            text-align: center;
            padding: var(--space-10);
            color: var(--color-gray-400);
        }

        .empty-state i {
            font-size: var(--font-size-5xl);
            margin-bottom: var(--space-4);
            color: var(--color-gray-600);
        }

        .empty-state-small {
            text-align: center;
            padding: var(--space-6);
            color: var(--color-gray-400);
        }

        .group-list {
            list-style: none;
        }

        .group-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .group-item:last-child {
            border-bottom: none;
        }

        .group-item a {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            color: var(--color-gray-200);
            text-decoration: none;
            transition: all var(--transition-fast);
            border-radius: var(--radius-md);
        }

        .group-item a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-white);
        }

        .badge {
            margin-left: auto;
            padding: var(--space-1) var(--space-2);
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
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

        .alert-info {
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            color: var(--color-primary);
        }
    </style>
</body>
</html>
