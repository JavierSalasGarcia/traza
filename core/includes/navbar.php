<?php
$user = current_user();
$user_model = new User();
$unread_notifications = $user_model->countUnreadNotifications($user['id']);
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <a href="<?= base_url('public/dashboard.php') ?>">
                <i class="fas fa-graduation-cap"></i>
                <span class="logo-text">TrazaFI</span>
            </a>
        </div>

        <button class="nav-toggle" id="navToggle">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <div class="nav-menu" id="navMenu">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?= base_url('public/dashboard.php') ?>" class="nav-link">
                        <i class="fas fa-home"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-users"></i> <span>Grupos</span> <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?= base_url('public/my-groups.php') ?>" class="dropdown-link"><i class="fas fa-folder"></i> Mis Grupos</a></li>
                        <li><a href="<?= base_url('public/groups.php') ?>" class="dropdown-link"><i class="fas fa-search"></i> Explorar Grupos</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a href="<?= base_url('public/dashboard.php') ?>" class="nav-link dropdown-toggle">
                        <i class="fas fa-bullhorn"></i> <span>Avisos</span> <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?= base_url('public/dashboard.php') ?>" class="dropdown-link"><i class="fas fa-home"></i> Ver Avisos</a></li>
                        <li><a href="<?= base_url('public/create-aviso.php') ?>" class="dropdown-link"><i class="fas fa-plus"></i> Crear Aviso</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a href="<?= base_url('public/proposals.php') ?>" class="nav-link dropdown-toggle">
                        <i class="fas fa-lightbulb"></i> <span>Propuestas</span> <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="<?= base_url('public/proposals.php') ?>" class="dropdown-link"><i class="fas fa-list"></i> Ver Propuestas</a></li>
                        <li><a href="<?= base_url('public/create-proposal.php') ?>" class="dropdown-link"><i class="fas fa-plus"></i> Nueva Propuesta</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('public/tickets.php') ?>" class="nav-link">
                        <i class="fas fa-ticket-alt"></i> <span>Tickets</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('public/historicos.php') ?>" class="nav-link">
                        <i class="fas fa-archive"></i> <span>Históricos</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-actions">
            <div class="user-menu">
                <button class="user-menu-toggle">
                    <span class="user-avatar">
                        <?php if ($user['imagen_perfil']): ?>
                            <img src="<?= upload_url($user['imagen_perfil']) ?>" alt="<?= sanitize($user['nombre']) ?>">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </span>
                    <span class="user-name"><?= sanitize($user['nombre']) ?></span>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge"><?= $unread_notifications ?></span>
                    <?php endif; ?>
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
                            <?php if ($unread_notifications > 0): ?>
                                <span class="badge"><?= $unread_notifications ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="dropdown-link">
                            <i class="fas fa-cog"></i> Configuración
                        </a>
                    </li>
                    <li>
                        <a href="<?= base_url('public/comision-panel.php') ?>" class="dropdown-link">
                            <i class="fas fa-clipboard-list"></i> Panel de Comisión
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li><div class="dropdown-divider"></div></li>
                        <li class="dropdown-header">
                            <i class="fas fa-shield-alt"></i> Administración
                        </li>
                        <li>
                            <a href="#" class="dropdown-link admin-link">
                                <i class="fas fa-users-cog"></i> Gestionar Usuarios
                            </a>
                        </li>
                        <li>
                            <a href="#" class="dropdown-link admin-link">
                                <i class="fas fa-key"></i> Roles y Permisos
                            </a>
                        </li>
                        <li>
                            <a href="#" class="dropdown-link admin-link">
                                <i class="fas fa-puzzle-piece"></i> Módulos
                            </a>
                        </li>
                    <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle) {
        navToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.nav-container')) {
            navToggle?.classList.remove('active');
            navMenu?.classList.remove('active');
        }
    });

    // Dropdown functionality for mobile
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                }
            });
        }
    });
});
</script>

<style>
.verification-banner {
    background: rgba(255, 170, 0, 0.1);
    border-bottom: 1px solid rgba(255, 170, 0, 0.3);
    padding: var(--space-3);
}

.banner-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-3);
    color: var(--color-warning);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: 10px;
    background: var(--color-error);
    color: var(--color-white);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-bold);
    padding: 2px 6px;
    border-radius: var(--radius-full);
    min-width: 18px;
    text-align: center;
}

.user-menu {
    position: relative;
}

.user-avatar img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}
</style>
