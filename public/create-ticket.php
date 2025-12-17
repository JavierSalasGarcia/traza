<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$ticket_model = new Ticket();
$group_model = new Group();

// Obtener grupos del usuario
$user_groups = get_user_groups($user_id);

// Tipos y prioridades
$tipos = [
    'modulo_personalizado' => 'Módulo Personalizado',
    'mejora' => 'Mejora',
    'error' => 'Reporte de Error',
    'consulta' => 'Consulta'
];

$prioridades = [
    'baja' => 'Baja',
    'media' => 'Media',
    'alta' => 'Alta'
];

// Procesar formulario
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        $errors = [];
        $data = [
            'titulo' => trim(input('titulo')),
            'descripcion' => trim(input('descripcion')),
            'tipo' => input('tipo'),
            'prioridad' => input('prioridad'),
            'grupo_id' => input('grupo_id') ?: null,
            'solicitante_id' => $user_id
        ];

        // Validaciones
        if (empty($data['titulo'])) {
            $errors[] = 'El título es requerido';
        }

        if (empty($data['descripcion'])) {
            $errors[] = 'La descripción es requerida';
        }

        if (empty($data['tipo'])) {
            $errors[] = 'El tipo es requerido';
        }

        if (empty($errors)) {
            $result = $ticket_model->create($data);

            if ($result['success']) {
                set_flash('success', $result['message']);
                redirect(base_url('public/view-ticket.php?id=' . $result['id']));
            } else {
                $errors[] = $result['message'];
            }
        }

        if (!empty($errors)) {
            set_flash('error', implode('<br>', $errors));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Ticket - TrazaFI</title>
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
                <div class="breadcrumb">
                    <a href="<?= base_url('public/dashboard.php') ?>">Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="<?= base_url('public/tickets.php') ?>">Tickets</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Nuevo Ticket</span>
                </div>
                <h1>
                    <i class="fas fa-ticket-alt"></i>
                    Nuevo Ticket
                </h1>
                <p>Solicita nuevas funcionalidades, reporta errores o pide ayuda</p>
            </div>

            <div class="form-container">
                <form method="POST" class="ticket-form">
                    <?= csrf_field() ?>

                    <div class="form-section">
                        <h2>Información del Ticket</h2>

                        <div class="form-group">
                            <label for="titulo" class="required">Título</label>
                            <input type="text"
                                   id="titulo"
                                   name="titulo"
                                   class="form-control"
                                   placeholder="Título descriptivo del ticket..."
                                   required>
                            <small class="form-hint">Sé específico y conciso. Ejemplo: "Módulo de reportes en PDF para Ingeniería Mecánica"</small>
                        </div>

                        <div class="form-group">
                            <label for="descripcion" class="required">Descripción</label>
                            <textarea id="descripcion"
                                      name="descripcion"
                                      class="form-control"
                                      rows="8"
                                      placeholder="Describe detalladamente tu solicitud..."
                                      required></textarea>
                            <small class="form-hint">
                                Incluye: ¿Qué necesitas? ¿Por qué es útil? ¿Cómo funcionaría? ¿Qué beneficios aporta?
                            </small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="tipo" class="required">Tipo de Solicitud</label>
                                <select id="tipo" name="tipo" class="form-control" required>
                                    <option value="">Selecciona un tipo</option>
                                    <?php foreach ($tipos as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint">
                                    <strong>Módulo Personalizado:</strong> Nueva funcionalidad específica para tu grupo<br>
                                    <strong>Mejora:</strong> Optimización de algo existente<br>
                                    <strong>Error:</strong> Algo no funciona correctamente<br>
                                    <strong>Consulta:</strong> Pregunta o ayuda
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="prioridad">Prioridad Sugerida</label>
                                <select id="prioridad" name="prioridad" class="form-control">
                                    <option value="media" selected>Media</option>
                                    <?php foreach ($prioridades as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint">Los administradores pueden ajustar la prioridad final</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="grupo_id">Grupo Asociado (Opcional)</label>
                            <select id="grupo_id" name="grupo_id" class="form-control">
                                <option value="">General (para todos)</option>
                                <?php foreach ($user_groups as $group): ?>
                                    <option value="<?= $group['id'] ?>">
                                        <?= sanitize($group['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Si la funcionalidad es específica para un grupo, selecciónalo aquí</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Consejos para un buen ticket</h2>
                        <ul class="tips-list">
                            <li><i class="fas fa-check"></i> Sé específico: describe exactamente qué necesitas</li>
                            <li><i class="fas fa-check"></i> Explica el contexto: ¿por qué es importante esta funcionalidad?</li>
                            <li><i class="fas fa-check"></i> Proporciona ejemplos: si es posible, menciona casos de uso</li>
                            <li><i class="fas fa-check"></i> Define el alcance: ¿qué debe incluir y qué no?</li>
                            <li><i class="fas fa-check"></i> Para errores: incluye pasos para reproducirlo</li>
                        </ul>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>¿Qué sucede después de crear el ticket?</strong>
                            <ol>
                                <li>Los administradores revisarán tu solicitud</li>
                                <li>Pueden ajustar la prioridad según la demanda</li>
                                <li>Si es viable, asignarán el ticket para desarrollo</li>
                                <li>Recibirás notificaciones sobre el progreso</li>
                                <li>Podrás votar por otros tickets que te interesen</li>
                            </ol>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i>
                            Crear Ticket
                        </button>
                        <a href="<?= base_url('public/tickets.php') ?>" class="btn btn-outline btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
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

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-4);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .breadcrumb a {
            color: var(--color-primary);
            text-decoration: none;
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

        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .ticket-form {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
        }

        .form-section {
            margin-bottom: var(--space-8);
            padding-bottom: var(--space-8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-section:last-of-type {
            border-bottom: none;
            padding-bottom: 0;
        }

        .form-section h2 {
            font-size: var(--font-size-xl);
            margin-bottom: var(--space-6);
            color: var(--color-primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-6);
        }

        .tips-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tips-list li {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-3);
            margin-bottom: var(--space-2);
            background: rgba(0, 255, 170, 0.05);
            border-radius: var(--radius-lg);
            color: var(--color-gray-300);
        }

        .tips-list i {
            color: var(--color-secondary);
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-box {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-6);
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
        }

        .info-box i {
            font-size: var(--font-size-2xl);
            color: var(--color-primary);
            flex-shrink: 0;
        }

        .info-box strong {
            display: block;
            margin-bottom: var(--space-3);
            color: var(--color-white);
        }

        .info-box ol {
            margin: 0;
            padding-left: var(--space-5);
            color: var(--color-gray-300);
        }

        .info-box li {
            margin-bottom: var(--space-2);
        }

        .form-actions {
            display: flex;
            gap: var(--space-4);
            padding-top: var(--space-6);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
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
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tituloInput = document.getElementById('titulo');
        const descripcionInput = document.getElementById('descripcion');

        // Auto-resize textarea
        descripcionInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    </script>
</body>
</html>
