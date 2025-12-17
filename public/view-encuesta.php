<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$encuesta_model = new Encuesta();

// Obtener ID
$encuesta_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$mostrar_recibo = isset($_GET['recibo']) ? $_GET['recibo'] : null;

if (!$encuesta_id) {
    set_flash('error', 'Encuesta no encontrada');
    redirect('public/encuestas.php');
}

$encuesta = $encuesta_model->getById($encuesta_id);

if (!$encuesta) {
    set_flash('error', 'Encuesta no encontrada');
    redirect('public/encuestas.php');
}

// Obtener preguntas
$preguntas = $encuesta_model->getPreguntas($encuesta_id);

// Verificar si ya votó
$has_voted = $encuesta_model->hasVoted($encuesta_id, $user['id']);

// Verificar si está activa
$now = date('Y-m-d H:i:s');
$is_active = $encuesta['activa']
             && $encuesta['fecha_inicio'] <= $now
             && (!$encuesta['fecha_fin'] || $encuesta['fecha_fin'] >= $now);
$finalizada = $encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $now;

// Procesar voto
if (is_post() && isset($_POST['votar'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        // Construir respuestas desde POST
        $respuestas = [];

        foreach ($preguntas as $pregunta) {
            $pregunta_key = 'pregunta_' . $pregunta['id'];

            if ($pregunta['tipo'] == 'abierta') {
                if (isset($_POST[$pregunta_key]) && !empty(trim($_POST[$pregunta_key]))) {
                    $respuestas[$pregunta['id']] = trim($_POST[$pregunta_key]);
                } elseif ($pregunta['requerida']) {
                    set_flash('error', 'La pregunta "' . $pregunta['texto_pregunta'] . '" es requerida');
                    redirect($_SERVER['REQUEST_URI']);
                }
            } else {
                // Opción única o múltiple
                if (isset($_POST[$pregunta_key])) {
                    $respuestas[$pregunta['id']] = $_POST[$pregunta_key];
                } elseif ($pregunta['requerida']) {
                    set_flash('error', 'La pregunta "' . $pregunta['texto_pregunta'] . '" es requerida');
                    redirect($_SERVER['REQUEST_URI']);
                }
            }
        }

        // Votar según tipo de encuesta
        if ($encuesta['anonima']) {
            $result = $encuesta_model->voteAnonimo($encuesta_id, $user['id'], $respuestas);
            if ($result['success']) {
                // Redirigir mostrando el recibo
                redirect('public/view-encuesta.php?id=' . $encuesta_id . '&recibo=' . $result['recibo']);
            }
        } else {
            $result = $encuesta_model->voteNormal($encuesta_id, $user['id'], $respuestas);
        }

        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success'] && !$encuesta['anonima']) {
            redirect($_SERVER['REQUEST_URI']);
        }
    }
}

// Cerrar encuesta
if (is_post() && isset($_POST['cerrar'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        $result = $encuesta_model->close($encuesta_id, $user['id']);
        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Obtener resultados
$resultados = $encuesta_model->getResultados($encuesta_id);

$is_owner = $encuesta['autor_id'] == $user['id'];
$can_close = $is_owner && $is_active;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($encuesta['titulo']) ?> - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../core/includes/pwa-head.php'; ?>
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

            <?php if ($mostrar_recibo): ?>
                <!-- Pantalla de Recibo (solo para anónimas) -->
                <div class="recibo-container">
                    <div class="recibo-card">
                        <div class="recibo-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1>¡Voto Registrado Exitosamente!</h1>
                        <p class="recibo-subtitle">Tu voto ha sido registrado de forma anónima</p>

                        <div class="recibo-code">
                            <label>Tu Recibo de Verificación:</label>
                            <div class="recibo-value" id="reciboValue"><?= sanitize($mostrar_recibo) ?></div>
                            <button class="btn btn-secondary btn-sm" onclick="copyRecibo()">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>

                        <div class="recibo-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>⚠️ IMPORTANTE: Guarda tu recibo</strong>
                                <ul>
                                    <li>Toma una <strong>captura de pantalla</strong> o <strong>fotografía</strong> de este recibo</li>
                                    <li>El sistema <strong>NO guarda</strong> qué recibo es tuyo</li>
                                    <li><strong>No podrás recuperarlo</strong> después de cerrar esta página</li>
                                    <li>Usa tu recibo para <strong>verificar</strong> que tu voto fue contado en la página pública de recibos</li>
                                </ul>
                            </div>
                        </div>

                        <div class="recibo-actions">
                            <a href="<?= base_url('public/encuesta-recibos.php?token=' . $encuesta['token_recibos']) ?>"
                               class="btn btn-primary" target="_blank">
                                <i class="fas fa-list"></i> Ver Todos los Recibos (Página Pública)
                            </a>
                            <a href="<?= base_url('public/view-encuesta.php?id=' . $encuesta_id) ?>" class="btn btn-secondary">
                                <i class="fas fa-chart-bar"></i> Ver Resultados
                            </a>
                            <a href="<?= base_url('public/encuestas.php') ?>" class="btn btn-secondary">
                                <i class="fas fa-list"></i> Todas las Encuestas
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Vista Normal de Encuesta -->
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-poll"></i> <?= sanitize($encuesta['titulo']) ?></h1>
                        <?php if ($encuesta['descripcion']): ?>
                            <p><?= sanitize($encuesta['descripcion']) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="<?= base_url('public/encuestas.php') ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>

                <div class="encuesta-grid">
                    <!-- Columna Principal -->
                    <div class="encuesta-main">
                        <!-- Información de la Encuesta -->
                        <div class="card">
                            <div class="card-header">
                                <h2><i class="fas fa-info-circle"></i> Información</h2>
                            </div>
                            <div class="card-body">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <i class="fas fa-user"></i>
                                        <div>
                                            <strong>Creada por</strong>
                                            <span><?= sanitize($encuesta['nombre'] . ' ' . $encuesta['apellidos']) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($encuesta['grupo_nombre']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-users"></i>
                                            <div>
                                                <strong>Grupo</strong>
                                                <span><?= sanitize($encuesta['grupo_nombre']) ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <i class="fas fa-calendar"></i>
                                        <div>
                                            <strong>Inicio</strong>
                                            <span><?= date('d/m/Y H:i', strtotime($encuesta['fecha_inicio'])) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($encuesta['fecha_fin']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-calendar-times"></i>
                                            <div>
                                                <strong>Finalización</strong>
                                                <span><?= date('d/m/Y H:i', strtotime($encuesta['fecha_fin'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <i class="fas fa-chart-pie"></i>
                                        <div>
                                            <strong>Total de votos</strong>
                                            <span><?= number_format($encuesta['total_votos']) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($encuesta['anonima']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-user-secret"></i>
                                            <div>
                                                <strong>Tipo</strong>
                                                <span class="badge badge-info">Anónima con Recibos</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Formulario de Votación o Resultados -->
                        <?php if (!$has_voted && $is_active): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h2><i class="fas fa-vote-yea"></i> Responder Encuesta</h2>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="encuesta-form">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                                        <?php foreach ($preguntas as $index => $pregunta): ?>
                                            <div class="pregunta-block">
                                                <div class="pregunta-header">
                                                    <span class="pregunta-numero">Pregunta <?= $index + 1 ?></span>
                                                    <?php if ($pregunta['requerida']): ?>
                                                        <span class="badge badge-error">Requerida</span>
                                                    <?php endif; ?>
                                                </div>

                                                <h3 class="pregunta-texto"><?= sanitize($pregunta['texto_pregunta']) ?></h3>

                                                <?php
                                                $opciones = $encuesta_model->getOpciones($pregunta['id']);
                                                $pregunta_name = 'pregunta_' . $pregunta['id'];
                                                ?>

                                                <?php if ($pregunta['tipo'] == 'unica'): ?>
                                                    <!-- Respuesta Única (Radio) -->
                                                    <div class="opciones-list">
                                                        <?php foreach ($opciones as $opcion): ?>
                                                            <label class="opcion-item">
                                                                <input type="radio" name="<?= $pregunta_name ?>"
                                                                       value="<?= $opcion['id'] ?>"
                                                                       <?= $pregunta['requerida'] ? 'required' : '' ?>>
                                                                <span><?= sanitize($opcion['texto']) ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>

                                                <?php elseif ($pregunta['tipo'] == 'multiple'): ?>
                                                    <!-- Respuesta Múltiple (Checkbox) -->
                                                    <div class="opciones-list">
                                                        <?php foreach ($opciones as $opcion): ?>
                                                            <label class="opcion-item">
                                                                <input type="checkbox" name="<?= $pregunta_name ?>[]"
                                                                       value="<?= $opcion['id'] ?>">
                                                                <span><?= sanitize($opcion['texto']) ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>

                                                <?php else: ?>
                                                    <!-- Respuesta Abierta (Textarea) -->
                                                    <textarea name="<?= $pregunta_name ?>" class="form-control" rows="4"
                                                              placeholder="Escribe tu respuesta aquí..."
                                                              <?= $pregunta['requerida'] ? 'required' : '' ?>></textarea>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <div class="form-actions">
                                            <button type="submit" name="votar" class="btn btn-primary btn-lg">
                                                <i class="fas fa-check"></i> Enviar Respuestas
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- Mostrar Resultados -->
                            <div class="card">
                                <div class="card-header">
                                    <h2><i class="fas fa-chart-bar"></i> Resultados</h2>
                                    <?php if ($encuesta['anonima'] && $encuesta['token_recibos']): ?>
                                        <a href="<?= base_url('public/encuesta-recibos.php?token=' . $encuesta['token_recibos']) ?>"
                                           class="btn btn-sm btn-secondary" target="_blank">
                                            <i class="fas fa-receipt"></i> Ver Recibos
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if ($has_voted && !$is_active): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            Esta encuesta ha finalizado. Mostrando resultados finales.
                                        </div>
                                    <?php elseif ($has_voted): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle"></i>
                                            Ya has votado en esta encuesta. Mostrando resultados actuales.
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-circle"></i>
                                            No puedes votar en esta encuesta (ya finalizó o está inactiva).
                                        </div>
                                    <?php endif; ?>

                                    <?php foreach ($resultados as $index => $resultado): ?>
                                        <div class="resultado-block">
                                            <div class="pregunta-header">
                                                <span class="pregunta-numero">Pregunta <?= $index + 1 ?></span>
                                            </div>
                                            <h3 class="pregunta-texto"><?= sanitize($resultado['pregunta']['texto_pregunta']) ?></h3>

                                            <?php if ($resultado['pregunta']['tipo'] == 'abierta'): ?>
                                                <!-- Respuestas Abiertas -->
                                                <div class="respuestas-abiertas">
                                                    <?php if (empty($resultado['respuestas_abiertas'])): ?>
                                                        <p class="empty-text">No hay respuestas aún</p>
                                                    <?php else: ?>
                                                        <?php foreach ($resultado['respuestas_abiertas'] as $respuesta): ?>
                                                            <div class="respuesta-abierta-item">
                                                                <p><?= nl2br(sanitize($respuesta['texto_respuesta'])) ?></p>
                                                                <div class="respuesta-meta">
                                                                    <?php if (!$encuesta['anonima']): ?>
                                                                        <span><i class="fas fa-user"></i> <?= sanitize($respuesta['nombre'] . ' ' . $respuesta['apellidos']) ?></span>
                                                                    <?php endif; ?>
                                                                    <span><i class="fas fa-clock"></i> <?= time_ago($respuesta['fecha_creacion']) ?></span>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>

                                            <?php else: ?>
                                                <!-- Resultados de Opciones -->
                                                <?php
                                                $max_votos = 0;
                                                foreach ($resultado['opciones'] as $opcion) {
                                                    if ($opcion['total_votos'] > $max_votos) {
                                                        $max_votos = $opcion['total_votos'];
                                                    }
                                                }
                                                ?>
                                                <div class="resultados-opciones">
                                                    <?php foreach ($resultado['opciones'] as $opcion): ?>
                                                        <div class="resultado-item <?= $opcion['total_votos'] == $max_votos && $max_votos > 0 ? 'leading' : '' ?>">
                                                            <div class="resultado-header">
                                                                <span class="resultado-texto"><?= sanitize($opcion['texto']) ?></span>
                                                                <span class="resultado-stats">
                                                                    <strong><?= number_format($opcion['porcentaje'], 1) ?>%</strong>
                                                                    <span class="resultado-votos">(<?= $opcion['total_votos'] ?> votos)</span>
                                                                </span>
                                                            </div>
                                                            <div class="resultado-bar">
                                                                <div class="resultado-fill" style="width: <?= $opcion['porcentaje'] ?>%"></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="encuesta-sidebar">
                        <?php if ($can_close): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-cog"></i> Administración</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST" onsubmit="return confirm('¿Estás seguro de cerrar esta encuesta?')">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <button type="submit" name="cerrar" class="btn btn-danger btn-block">
                                            <i class="fas fa-times-circle"></i> Cerrar Encuesta
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-info-circle"></i> Estado</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($is_active): ?>
                                    <div class="status-badge status-active">
                                        <i class="fas fa-circle"></i> Activa
                                    </div>
                                <?php elseif ($finalizada): ?>
                                    <div class="status-badge status-closed">
                                        <i class="fas fa-circle"></i> Finalizada
                                    </div>
                                <?php else: ?>
                                    <div class="status-badge status-inactive">
                                        <i class="fas fa-circle"></i> Inactiva
                                    </div>
                                <?php endif; ?>

                                <?php if ($has_voted): ?>
                                    <div class="status-badge status-voted">
                                        <i class="fas fa-check-circle"></i> Ya Votaste
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <style>
        .recibo-container {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-6);
        }

        .recibo-card {
            background: var(--card-bg);
            border: 2px solid var(--color-success);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            max-width: 700px;
            text-align: center;
        }

        .recibo-icon {
            font-size: 80px;
            color: var(--color-success);
            margin-bottom: var(--space-4);
        }

        .recibo-card h1 {
            color: var(--color-success);
            margin-bottom: var(--space-2);
        }

        .recibo-subtitle {
            color: var(--color-text-muted);
            margin-bottom: var(--space-6);
        }

        .recibo-code {
            background: rgba(0, 153, 255, 0.1);
            border: 2px solid var(--color-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin: var(--space-6) 0;
        }

        .recibo-code label {
            display: block;
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
            margin-bottom: var(--space-2);
        }

        .recibo-value {
            font-size: 32px;
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            margin-bottom: var(--space-3);
            user-select: all;
        }

        .recibo-warning {
            background: rgba(255, 170, 0, 0.1);
            border: 2px solid var(--color-warning);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin: var(--space-6) 0;
            display: flex;
            gap: var(--space-3);
            text-align: left;
        }

        .recibo-warning i {
            font-size: 32px;
            color: var(--color-warning);
            flex-shrink: 0;
        }

        .recibo-warning ul {
            margin-top: var(--space-2);
            padding-left: var(--space-4);
        }

        .recibo-warning li {
            margin-bottom: var(--space-2);
        }

        .recibo-actions {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
            margin-top: var(--space-6);
        }

        .encuesta-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--space-6);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-4);
        }

        .info-item {
            display: flex;
            gap: var(--space-3);
            align-items: flex-start;
        }

        .info-item i {
            color: var(--color-primary);
            font-size: var(--font-size-lg);
            margin-top: 4px;
        }

        .info-item strong {
            display: block;
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
            margin-bottom: 4px;
        }

        .pregunta-block,
        .resultado-block {
            padding: var(--space-5);
            background: rgba(0, 153, 255, 0.03);
            border: 1px solid rgba(0, 153, 255, 0.1);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-5);
        }

        .pregunta-header {
            display: flex;
            gap: var(--space-2);
            align-items: center;
            margin-bottom: var(--space-3);
        }

        .pregunta-numero {
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            text-transform: uppercase;
        }

        .pregunta-texto {
            font-size: var(--font-size-lg);
            margin-bottom: var(--space-4);
        }

        .opciones-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .opcion-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3);
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .opcion-item:hover {
            border-color: var(--color-primary);
            background: rgba(0, 153, 255, 0.05);
        }

        .opcion-item input {
            cursor: pointer;
        }

        .resultado-item {
            margin-bottom: var(--space-4);
        }

        .resultado-item.leading .resultado-texto {
            color: var(--color-success);
            font-weight: var(--font-weight-bold);
        }

        .resultado-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-2);
        }

        .resultado-stats {
            display: flex;
            gap: var(--space-2);
            align-items: center;
        }

        .resultado-votos {
            color: var(--color-text-muted);
            font-size: var(--font-size-sm);
        }

        .resultado-bar {
            height: 32px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .resultado-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--color-primary), var(--color-success));
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: var(--space-2);
        }

        .respuestas-abiertas {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .respuesta-abierta-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
        }

        .respuesta-abierta-item p {
            margin-bottom: var(--space-2);
        }

        .respuesta-meta {
            display: flex;
            gap: var(--space-4);
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--space-2);
        }

        .status-active { background: rgba(0, 255, 136, 0.1); color: var(--color-success); }
        .status-closed { background: rgba(255, 68, 68, 0.1); color: var(--color-error); }
        .status-inactive { background: rgba(255, 255, 255, 0.1); color: var(--color-text-muted); }
        .status-voted { background: rgba(0, 153, 255, 0.1); color: var(--color-primary); }

        @media (max-width: 1024px) {
            .encuesta-grid {
                grid-template-columns: 1fr;
            }

            .recibo-value {
                font-size: 24px;
            }
        }
    </style>

    <script>
        function copyRecibo() {
            const reciboValue = document.getElementById('reciboValue').textContent;
            navigator.clipboard.writeText(reciboValue).then(() => {
                alert('Recibo copiado al portapapeles: ' + reciboValue);
            });
        }
    </script>
</body>
</html>
