<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$ticket_model = new Ticket();

// Filtros
$vista = isset($_GET['vista']) ? sanitize($_GET['vista']) : 'todos'; // todos, mis_tickets, votados
$estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : null;
$tipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : null;
$prioridad = isset($_GET['prioridad']) ? sanitize($_GET['prioridad']) : null;

// Obtener tickets según la vista
$filters = [];

if ($vista === 'mis_tickets') {
    $filters['solicitante_id'] = $user_id;
} elseif ($vista === 'asignados' && is_admin()) {
    $filters['asignado_a'] = $user_id;
}

if ($estado) {
    $filters['estado'] = $estado;
}

if ($tipo) {
    $filters['tipo'] = $tipo;
}

if ($prioridad) {
    $filters['prioridad'] = $prioridad;
}

$tickets = $ticket_model->getTickets($filters);

// Añadir información de votos
foreach ($tickets as &$ticket) {
    $ticket['total_votos'] = $ticket_model->getVoteCount($ticket['id']);
    $ticket['user_voted'] = $ticket_model->hasVoted($ticket['id'], $user_id);
}

// Tipos de tickets
$tipos = [
    'modulo_personalizado' => 'Módulo Personalizado',
    'mejora' => 'Mejora',
    'error' => 'Reporte de Error',
    'consulta' => 'Consulta'
];

// Estados
$estados = [
    'pendiente' => 'Pendiente',
    'en_revision' => 'En Revisión',
    'en_desarrollo' => 'En Desarrollo',
    'completado' => 'Completado',
    'rechazado' => 'Rechazado',
    'cancelado' => 'Cancelado'
];

// Prioridades
$prioridades = [
    'baja' => 'Baja',
    'media' => 'Media',
    'alta' => 'Alta'
];

// Estadísticas
$stats = $ticket_model->getStats($user_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../core/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1><i class="fas fa-ticket-alt"></i> Sistema de Tickets</h1>
                        <p>Solicita nuevas funcionalidades y módulos personalizados</p>
                    </div>
                    <a href="<?= base_url('public/create-ticket.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo Ticket
                    </a>
                </div>
            </div>

            <!-- Estadísticas rápidas -->
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $stats['total_tickets'] ?></div>
                    <div class="stat-mini-label">Mis Tickets</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $stats['pendientes'] ?></div>
                    <div class="stat-mini-label">Pendientes</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $stats['en_desarrollo'] ?></div>
                    <div class="stat-mini-label">En Desarrollo</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $stats['completados'] ?></div>
                    <div class="stat-mini-label">Completados</div>
                </div>
            </div>

            <!-- Tabs de vista -->
            <div class="view-tabs">
                <a href="<?= base_url('public/tickets.php?vista=todos') ?>"
                   class="tab <?= $vista === 'todos' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Todos
                </a>
                <a href="<?= base_url('public/tickets.php?vista=mis_tickets') ?>"
                   class="tab <?= $vista === 'mis_tickets' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i> Mis Tickets
                </a>
                <?php if (is_admin()): ?>
                    <a href="<?= base_url('public/tickets.php?vista=asignados') ?>"
                       class="tab <?= $vista === 'asignados' ? 'active' : '' ?>">
                        <i class="fas fa-tasks"></i> Asignados a Mí
                    </a>
                <?php endif; ?>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <div class="filter-group">
                    <label>Estado:</label>
                    <div class="filter-buttons">
                        <a href="<?= base_url('public/tickets.php?vista=' . $vista) ?>"
                           class="filter-btn <?= !$estado ? 'active' : '' ?>">
                            Todos
                        </a>
                        <?php foreach ($estados as $key => $label): ?>
                            <a href="<?= base_url('public/tickets.php?vista=' . $vista . '&estado=' . $key) ?>"
                               class="filter-btn <?= $estado === $key ? 'active' : '' ?>">
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Tipo:</label>
                    <div class="filter-buttons">
                        <a href="<?= base_url('public/tickets.php?vista=' . $vista . ($estado ? '&estado=' . $estado : '')) ?>"
                           class="filter-btn <?= !$tipo ? 'active' : '' ?>">
                            Todos
                        </a>
                        <?php foreach ($tipos as $key => $label): ?>
                            <a href="<?= base_url('public/tickets.php?vista=' . $vista . ($estado ? '&estado=' . $estado : '') . '&tipo=' . $key) ?>"
                               class="filter-btn <?= $tipo === $key ? 'active' : '' ?>">
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Prioridad:</label>
                    <div class="filter-buttons">
                        <a href="<?= base_url('public/tickets.php?vista=' . $vista . ($estado ? '&estado=' . $estado : '') . ($tipo ? '&tipo=' . $tipo : '')) ?>"
                           class="filter-btn <?= !$prioridad ? 'active' : '' ?>">
                            Todas
                        </a>
                        <?php foreach ($prioridades as $key => $label): ?>
                            <a href="<?= base_url('public/tickets.php?vista=' . $vista . ($estado ? '&estado=' . $estado : '') . ($tipo ? '&tipo=' . $tipo : '') . '&prioridad=' . $key) ?>"
                               class="filter-btn <?= $prioridad === $key ? 'active' : '' ?>">
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Lista de tickets -->
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>No hay tickets</h3>
                    <p>
                        <?php if ($vista === 'mis_tickets'): ?>
                            Aún no has creado ningún ticket
                        <?php else: ?>
                            No hay tickets con estos filtros
                        <?php endif; ?>
                    </p>
                    <a href="<?= base_url('public/create-ticket.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Crear Primer Ticket
                    </a>
                </div>
            <?php else: ?>
                <div class="tickets-list">
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="ticket-card prioridad-<?= $ticket['prioridad'] ?>">
                            <div class="ticket-header">
                                <div class="ticket-badges">
                                    <span class="tipo-badge tipo-<?= $ticket['tipo'] ?>">
                                        <?= $tipos[$ticket['tipo']] ?? $ticket['tipo'] ?>
                                    </span>
                                    <span class="estado-badge estado-<?= $ticket['estado'] ?>">
                                        <?= $estados[$ticket['estado']] ?>
                                    </span>
                                    <span class="prioridad-badge prioridad-<?= $ticket['prioridad'] ?>">
                                        <i class="fas fa-flag"></i>
                                        <?= ucfirst($ticket['prioridad']) ?>
                                    </span>
                                    <?php if ($ticket['grupo_nombre']): ?>
                                        <span class="grupo-badge">
                                            <i class="fas fa-users"></i>
                                            <?= sanitize($ticket['grupo_nombre']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-id">#<?= $ticket['id'] ?></div>
                            </div>

                            <h3 class="ticket-title">
                                <a href="<?= base_url('public/view-ticket.php?id=' . $ticket['id']) ?>">
                                    <?= sanitize($ticket['titulo']) ?>
                                </a>
                            </h3>

                            <p class="ticket-description">
                                <?= truncate(sanitize($ticket['descripcion']), 200) ?>
                            </p>

                            <div class="ticket-meta">
                                <div class="meta-left">
                                    <span class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <?= sanitize($ticket['solicitante_nombre']) ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?= time_ago($ticket['fecha_creacion']) ?>
                                    </span>
                                    <?php if ($ticket['asignado_nombre']): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-user-check"></i>
                                            Asignado a <?= sanitize($ticket['asignado_nombre']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="meta-right">
                                    <button class="vote-btn <?= $ticket['user_voted'] ? 'voted' : '' ?>"
                                            onclick="toggleVote(<?= $ticket['id'] ?>)"
                                            data-ticket-id="<?= $ticket['id'] ?>">
                                        <i class="fas fa-arrow-up"></i>
                                        <span class="vote-count"><?= $ticket['total_votos'] ?></span>
                                    </button>
                                    <span class="meta-item">
                                        <i class="fas fa-comments"></i>
                                        <?= $ticket['total_comentarios'] ?>
                                    </span>
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
            margin-bottom: var(--space-6);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-6);
        }

        .header-content h1 {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-2);
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .stat-mini {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            text-align: center;
        }

        .stat-mini-value {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            margin-bottom: var(--space-1);
        }

        .stat-mini-label {
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .view-tabs {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-6);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab {
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

        .tab:hover {
            color: var(--color-white);
        }

        .tab.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }

        .filters-section {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .filter-group {
            margin-bottom: var(--space-4);
        }

        .filter-group:last-child {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--space-3);
            color: var(--color-gray-300);
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .filter-btn {
            padding: var(--space-2) var(--space-4);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
            text-decoration: none;
            font-size: var(--font-size-sm);
            transition: all var(--transition-fast);
        }

        .filter-btn:hover {
            background: rgba(0, 153, 255, 0.1);
            border-color: var(--color-primary);
            color: var(--color-white);
        }

        .filter-btn.active {
            background: var(--gradient-primary);
            border-color: transparent;
            color: var(--color-black);
        }

        .tickets-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }

        .ticket-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            transition: all var(--transition-normal);
        }

        .ticket-card:hover {
            border-left-color: var(--color-primary);
            box-shadow: 0 10px 30px rgba(0, 153, 255, 0.1);
        }

        .ticket-card.prioridad-alta {
            border-left-color: var(--color-error);
        }

        .ticket-card.prioridad-media {
            border-left-color: var(--color-warning);
        }

        .ticket-card.prioridad-baja {
            border-left-color: var(--color-gray-600);
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-4);
        }

        .ticket-badges {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .tipo-badge, .estado-badge, .prioridad-badge, .grupo-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }

        .tipo-badge {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .estado-badge {
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

        .estado-rechazado, .estado-cancelado {
            background: rgba(255, 68, 68, 0.2);
            color: var(--color-error);
        }

        .prioridad-badge {
            background: rgba(255, 255, 255, 0.05);
        }

        .prioridad-alta {
            color: var(--color-error);
        }

        .prioridad-media {
            color: var(--color-warning);
        }

        .prioridad-baja {
            color: var(--color-gray-400);
        }

        .grupo-badge {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-gray-400);
        }

        .ticket-id {
            font-family: monospace;
            color: var(--color-gray-500);
            font-size: var(--font-size-sm);
        }

        .ticket-title {
            font-size: var(--font-size-xl);
            margin-bottom: var(--space-3);
        }

        .ticket-title a {
            color: var(--color-white);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .ticket-title a:hover {
            color: var(--color-primary);
        }

        .ticket-description {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
        }

        .ticket-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--space-4);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .meta-left, .meta-right {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .vote-btn {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-gray-400);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .vote-btn:hover {
            background: rgba(0, 153, 255, 0.1);
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        .vote-btn.voted {
            background: var(--gradient-primary);
            border-color: transparent;
            color: var(--color-black);
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
            margin-bottom: var(--space-6);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-mini {
                grid-template-columns: repeat(2, 1fr);
            }

            .view-tabs {
                overflow-x: auto;
            }

            .filter-buttons {
                max-height: 200px;
                overflow-y: auto;
            }

            .ticket-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-3);
            }
        }
    </style>

    <script>
    function toggleVote(ticketId) {
        const btn = document.querySelector(`.vote-btn[data-ticket-id="${ticketId}"]`);
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
                btn.querySelector('.vote-count').textContent = data.total_votos;
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html>
