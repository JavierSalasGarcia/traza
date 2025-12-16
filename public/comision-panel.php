<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$propuesta_model = new Propuesta();
$db = Database::getInstance();

// Obtener comisiones del usuario (debe ser coordinador del grupo asociado a la comisión)
$query = "SELECT DISTINCT c.*,
                 g.nombre as grupo_nombre
          FROM comisiones c
          INNER JOIN grupos g ON c.grupo_id = g.id
          INNER JOIN grupo_miembros gm ON g.id = gm.grupo_id
          WHERE gm.usuario_id = :user_id
          AND gm.es_coordinador = 1
          AND gm.estado = 'aprobado'
          ORDER BY c.nombre";

$db->query($query);
$db->bind(':user_id', $user_id);
$comisiones = $db->fetchAll();

if (empty($comisiones) && !is_admin()) {
    set_flash('error', 'No tienes acceso a ninguna comisión');
    redirect(base_url('public/dashboard.php'));
}

// Si es admin, puede ver todas las comisiones
if (is_admin()) {
    $comisiones = $propuesta_model->getCommissions();
}

// Filtro de comisión
$comision_id = isset($_GET['comision']) ? (int) $_GET['comision'] : ($comisiones[0]['id'] ?? null);

// Obtener propuestas asignadas a esta comisión
$propuestas_revision = [];
$propuestas_en_progreso = [];

if ($comision_id) {
    // En revisión (necesitan aceptación)
    $query = "SELECT p.*,
                     u.nombre as autor_nombre,
                     g.nombre as grupo_nombre,
                     COUNT(DISTINCT pf.usuario_id) as total_firmas,
                     DATEDIFF(NOW(), p.fecha_asignacion_comision) as dias_transcurridos
              FROM propuestas p
              LEFT JOIN usuarios u ON p.autor_id = u.id
              LEFT JOIN grupos g ON p.grupo_id = g.id
              LEFT JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
              WHERE p.comision_id = :comision_id
              AND p.estado = 'revision'
              GROUP BY p.id
              ORDER BY p.fecha_asignacion_comision ASC";

    $db->query($query);
    $db->bind(':comision_id', $comision_id);
    $propuestas_revision = $db->fetchAll();

    // En progreso (aceptadas, necesitan completarse)
    $query = "SELECT p.*,
                     u.nombre as autor_nombre,
                     g.nombre as grupo_nombre,
                     COUNT(DISTINCT pf.usuario_id) as total_firmas
              FROM propuestas p
              LEFT JOIN usuarios u ON p.autor_id = u.id
              LEFT JOIN grupos g ON p.grupo_id = g.id
              LEFT JOIN propuestas_firmas pf ON p.id = pf.propuesta_id
              WHERE p.comision_id = :comision_id
              AND p.estado = 'en_progreso'
              GROUP BY p.id
              ORDER BY p.fecha_asignacion_comision ASC";

    $db->query($query);
    $db->bind(':comision_id', $comision_id);
    $propuestas_en_progreso = $db->fetchAll();
}

// Procesar acciones
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        $action = input('action');
        $propuesta_id = (int) input('propuesta_id');

        if ($action === 'aceptar') {
            $result = $propuesta_model->acceptByCommission($propuesta_id, $user_id);
            set_flash($result['success'] ? 'success' : 'error', $result['message']);
            redirect($_SERVER['REQUEST_URI']);
        }

        if ($action === 'completar') {
            $evidencias = trim(input('evidencias'));

            if (empty($evidencias)) {
                set_flash('error', 'Debes proporcionar evidencias de la completación');
            } else {
                $result = $propuesta_model->complete($propuesta_id, $evidencias, $user_id);
                set_flash($result['success'] ? 'success' : 'error', $result['message']);
                redirect($_SERVER['REQUEST_URI']);
            }
        }
    }
}

$dias_limite = get_config('dias_aceptar_comision', 4);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Comisión - TrazaFI</title>
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
                    <h1><i class="fas fa-users-cog"></i> Panel de Comisión</h1>
                    <p>Gestiona las propuestas asignadas a tus comisiones</p>
                </div>
            </div>

            <!-- Selector de comisión -->
            <?php if (count($comisiones) > 1): ?>
                <div class="comision-selector">
                    <label for="comision-select">Comisión:</label>
                    <select id="comision-select" onchange="window.location.href='?comision=' + this.value">
                        <?php foreach ($comisiones as $com): ?>
                            <option value="<?= $com['id'] ?>" <?= $com['id'] == $comision_id ? 'selected' : '' ?>>
                                <?= sanitize($com['nombre']) ?>
                                <?php if ($com['grupo_nombre']): ?>
                                    (<?= sanitize($com['grupo_nombre']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif (!empty($comisiones)): ?>
                <div class="current-comision">
                    <h2><?= sanitize($comisiones[0]['nombre']) ?></h2>
                    <?php if ($comisiones[0]['grupo_nombre']): ?>
                        <p><?= sanitize($comisiones[0]['grupo_nombre']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Propuestas en revisión -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Pendientes de Aceptación</h2>
                    <span class="section-count"><?= count($propuestas_revision) ?></span>
                </div>

                <?php if (empty($propuestas_revision)): ?>
                    <div class="empty-section">
                        <i class="fas fa-check-circle"></i>
                        <p>No hay propuestas pendientes de aceptación</p>
                    </div>
                <?php else: ?>
                    <div class="propuestas-grid">
                        <?php foreach ($propuestas_revision as $propuesta): ?>
                            <?php
                            $dias_restantes = $dias_limite - $propuesta['dias_transcurridos'];
                            $urgente = $dias_restantes <= 1;
                            ?>
                            <div class="propuesta-comision-card <?= $urgente ? 'urgente' : '' ?>">
                                <div class="propuesta-header-comision">
                                    <h3><?= sanitize($propuesta['titulo']) ?></h3>
                                    <?php if ($urgente): ?>
                                        <span class="urgente-badge">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Urgente
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <p class="propuesta-description-short">
                                    <?= truncate(sanitize($propuesta['descripcion']), 150) ?>
                                </p>

                                <div class="propuesta-meta-comision">
                                    <div class="meta-row">
                                        <span><i class="fas fa-user"></i> <?= sanitize($propuesta['autor_nombre']) ?></span>
                                        <span><i class="fas fa-signature"></i> <?= number_format($propuesta['total_firmas']) ?> firmas</span>
                                    </div>
                                    <div class="meta-row">
                                        <span><i class="fas fa-calendar-plus"></i> Asignada hace <?= $propuesta['dias_transcurridos'] ?> día(s)</span>
                                        <span class="<?= $urgente ? 'text-error' : 'text-warning' ?>">
                                            <i class="fas fa-hourglass-half"></i>
                                            <?= $dias_restantes > 0 ? $dias_restantes . ' día(s) restante(s)' : 'Plazo vencido' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="propuesta-actions-comision">
                                    <a href="<?= base_url('public/view-proposal.php?id=' . $propuesta['id']) ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> Ver Detalles
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="aceptar">
                                        <input type="hidden" name="propuesta_id" value="<?= $propuesta['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Aceptar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Propuestas en progreso -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-tasks"></i> En Progreso</h2>
                    <span class="section-count"><?= count($propuestas_en_progreso) ?></span>
                </div>

                <?php if (empty($propuestas_en_progreso)): ?>
                    <div class="empty-section">
                        <i class="fas fa-clipboard-check"></i>
                        <p>No hay propuestas en progreso</p>
                    </div>
                <?php else: ?>
                    <div class="propuestas-grid">
                        <?php foreach ($propuestas_en_progreso as $propuesta): ?>
                            <div class="propuesta-comision-card en-progreso">
                                <div class="propuesta-header-comision">
                                    <h3><?= sanitize($propuesta['titulo']) ?></h3>
                                    <span class="progreso-badge">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        En Progreso
                                    </span>
                                </div>

                                <p class="propuesta-description-short">
                                    <?= truncate(sanitize($propuesta['descripcion']), 150) ?>
                                </p>

                                <div class="propuesta-meta-comision">
                                    <div class="meta-row">
                                        <span><i class="fas fa-user"></i> <?= sanitize($propuesta['autor_nombre']) ?></span>
                                        <span><i class="fas fa-signature"></i> <?= number_format($propuesta['total_firmas']) ?> firmas</span>
                                    </div>
                                </div>

                                <div class="completar-section">
                                    <form method="POST" class="completar-form" id="form-<?= $propuesta['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="completar">
                                        <input type="hidden" name="propuesta_id" value="<?= $propuesta['id'] ?>">

                                        <label for="evidencias-<?= $propuesta['id'] ?>">
                                            <strong>Evidencias de Completación:</strong>
                                        </label>
                                        <textarea id="evidencias-<?= $propuesta['id'] ?>"
                                                  name="evidencias"
                                                  class="form-control"
                                                  rows="4"
                                                  placeholder="Describe las acciones tomadas, resultados obtenidos y evidencias de la completación de esta propuesta..."
                                                  required></textarea>

                                        <div class="form-actions-inline">
                                            <a href="<?= base_url('public/view-proposal.php?id=' . $propuesta['id']) ?>" class="btn btn-outline btn-sm">
                                                <i class="fas fa-eye"></i> Ver Propuesta
                                            </a>
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check-double"></i> Marcar como Completada
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
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
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-2);
        }

        .comision-selector {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-4);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .comision-selector label {
            font-weight: var(--font-weight-semibold);
            color: var(--color-gray-300);
            margin: 0;
        }

        .comision-selector select {
            flex: 1;
            padding: var(--space-3);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-white);
            font-size: var(--font-size-base);
        }

        .current-comision {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .current-comision h2 {
            color: var(--color-primary);
            margin-bottom: var(--space-2);
        }

        .current-comision p {
            color: var(--color-gray-400);
            margin: 0;
        }

        .section-card {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-4);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .section-header h2 {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin: 0;
        }

        .section-count {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 var(--space-3);
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            color: var(--color-black);
            font-weight: var(--font-weight-bold);
        }

        .empty-section {
            text-align: center;
            padding: var(--space-12);
        }

        .empty-section i {
            font-size: var(--font-size-5xl);
            color: var(--color-gray-600);
            margin-bottom: var(--space-4);
        }

        .empty-section p {
            color: var(--color-gray-400);
            margin: 0;
        }

        .propuestas-grid {
            display: grid;
            gap: var(--space-4);
        }

        .propuesta-comision-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            transition: all var(--transition-normal);
        }

        .propuesta-comision-card:hover {
            border-color: var(--color-primary);
        }

        .propuesta-comision-card.urgente {
            border-color: var(--color-error);
            background: rgba(255, 68, 68, 0.05);
        }

        .propuesta-comision-card.en-progreso {
            border-color: var(--color-primary);
            background: rgba(0, 153, 255, 0.05);
        }

        .propuesta-header-comision {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-3);
            margin-bottom: var(--space-3);
        }

        .propuesta-header-comision h3 {
            font-size: var(--font-size-lg);
            margin: 0;
        }

        .urgente-badge, .progreso-badge {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            flex-shrink: 0;
        }

        .urgente-badge {
            background: rgba(255, 68, 68, 0.2);
            color: var(--color-error);
        }

        .progreso-badge {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
        }

        .propuesta-description-short {
            color: var(--color-gray-400);
            line-height: var(--line-height-relaxed);
            margin-bottom: var(--space-4);
        }

        .propuesta-meta-comision {
            margin-bottom: var(--space-4);
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-4);
            padding: var(--space-2) 0;
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .meta-row span {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .text-warning {
            color: var(--color-warning) !important;
        }

        .text-error {
            color: var(--color-error) !important;
            font-weight: var(--font-weight-semibold);
        }

        .propuesta-actions-comision {
            display: flex;
            gap: var(--space-3);
            padding-top: var(--space-4);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .completar-section {
            padding-top: var(--space-4);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .completar-form label {
            display: block;
            margin-bottom: var(--space-2);
            color: var(--color-gray-300);
        }

        .completar-form textarea {
            margin-bottom: var(--space-3);
        }

        .form-actions-inline {
            display: flex;
            gap: var(--space-3);
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
            .meta-row {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-2);
            }

            .propuesta-actions-comision,
            .form-actions-inline {
                flex-direction: column;
            }

            .propuesta-actions-comision .btn,
            .form-actions-inline .btn {
                width: 100%;
            }
        }
    </style>
</body>
</html>
