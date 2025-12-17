<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$encuesta_model = new Encuesta();
$group_model = new Group();

// Obtener grupos del usuario
$user_groups = get_user_groups($user_id);

// Procesar formulario
if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        set_flash('error', 'Token de seguridad inválido');
    } else {
        $errors = [];
        $data = [
            'titulo' => trim(input('titulo')),
            'descripcion' => trim(input('descripcion')),
            'autor_id' => $user_id,
            'grupo_id' => input('grupo_id') ?: null,
            'fecha_inicio' => input('fecha_inicio') ?: date('Y-m-d H:i:s'),
            'fecha_fin' => input('fecha_fin') ?: null,
            'anonima' => isset($_POST['anonima']) && $_POST['anonima'] === '1' ? 1 : 0,
            'multiple_respuestas' => isset($_POST['multiple_respuestas']) && $_POST['multiple_respuestas'] === '1' ? 1 : 0,
            'opciones' => []
        ];

        // Recoger opciones
        $opciones_raw = $_POST['opciones'] ?? [];
        foreach ($opciones_raw as $opcion) {
            $opcion_trimmed = trim($opcion);
            if (!empty($opcion_trimmed)) {
                $data['opciones'][] = $opcion_trimmed;
            }
        }

        // Validaciones
        if (empty($data['titulo'])) {
            $errors[] = 'El título es requerido';
        }

        if (count($data['opciones']) < 2) {
            $errors[] = 'Debes proporcionar al menos 2 opciones';
        }

        if (empty($errors)) {
            $result = $encuesta_model->create($data);

            if ($result['success']) {
                set_flash('success', $result['message']);
                redirect(base_url('public/view-encuesta.php?id=' . $result['id']));
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
    <title>Nueva Encuesta - TrazaFI</title>
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
                    <a href="<?= base_url('public/encuestas.php') ?>">Encuestas</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Nueva Encuesta</span>
                </div>
                <h1>
                    <i class="fas fa-poll"></i>
                    Nueva Encuesta
                </h1>
                <p>Crea una encuesta para conocer la opinión de la comunidad</p>
            </div>

            <div class="form-container">
                <form method="POST" class="encuesta-form" id="encuestaForm">
                    <?= csrf_field() ?>

                    <div class="form-section">
                        <h2>Información General</h2>

                        <div class="form-group">
                            <label for="titulo" class="required">Título</label>
                            <input type="text"
                                   id="titulo"
                                   name="titulo"
                                   class="form-control"
                                   placeholder="¿Cuál es tu pregunta?"
                                   maxlength="200"
                                   required>
                            <small class="form-hint">Sé claro y conciso. Ejemplo: "¿Qué horario prefieres para el evento?"</small>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">Descripción (Opcional)</label>
                            <textarea id="descripcion"
                                      name="descripcion"
                                      class="form-control"
                                      rows="3"
                                      placeholder="Proporciona contexto adicional si es necesario..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="grupo_id">Grupo (Opcional)</label>
                            <select id="grupo_id" name="grupo_id" class="form-control">
                                <option value="">General (toda la comunidad)</option>
                                <?php foreach ($user_groups as $group): ?>
                                    <option value="<?= $group['id'] ?>">
                                        <?= sanitize($group['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Opciones de Respuesta</h2>

                        <div class="opciones-container" id="opcionesContainer">
                            <div class="opcion-item">
                                <input type="text" name="opciones[]" class="form-control" placeholder="Opción 1" required>
                            </div>
                            <div class="opcion-item">
                                <input type="text" name="opciones[]" class="form-control" placeholder="Opción 2" required>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline btn-sm" id="addOpcionBtn">
                            <i class="fas fa-plus"></i> Añadir Opción
                        </button>
                    </div>

                    <div class="form-section">
                        <h2>Configuración</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fecha_inicio">Fecha de Inicio</label>
                                <input type="datetime-local"
                                       id="fecha_inicio"
                                       name="fecha_inicio"
                                       class="form-control"
                                       value="<?= date('Y-m-d\TH:i') ?>">
                                <small class="form-hint">Deja vacío para iniciar inmediatamente</small>
                            </div>

                            <div class="form-group">
                                <label for="fecha_fin">Fecha de Finalización (Opcional)</label>
                                <input type="datetime-local"
                                       id="fecha_fin"
                                       name="fecha_fin"
                                       class="form-control">
                                <small class="form-hint">Deja vacío para que no tenga límite</small>
                            </div>
                        </div>

                        <div class="form-checks">
                            <div class="form-check">
                                <input type="checkbox" id="multiple_respuestas" name="multiple_respuestas" value="1">
                                <label for="multiple_respuestas">
                                    <i class="fas fa-check-double"></i>
                                    Permitir múltiples respuestas
                                    <small>Los usuarios podrán seleccionar más de una opción</small>
                                </label>
                            </div>

                            <div class="form-check">
                                <input type="checkbox" id="anonima" name="anonima" value="1">
                                <label for="anonima">
                                    <i class="fas fa-user-secret"></i>
                                    Encuesta anónima
                                    <small>No se mostrará quién votó por cada opción</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-lightbulb"></i>
                        <div>
                            <strong>Consejos para crear buenas encuestas</strong>
                            <ul>
                                <li>Formula preguntas claras y sin ambigüedades</li>
                                <li>Proporciona opciones mutuamente excluyentes (a menos que permitas múltiples respuestas)</li>
                                <li>Considera añadir una opción "Otro" si aplica</li>
                                <li>Define un plazo de finalización para urgir a participar</li>
                                <li>Las encuestas anónimas suelen recibir más respuestas honestas</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i>
                            Publicar Encuesta
                        </button>
                        <a href="<?= base_url('public/encuestas.php') ?>" class="btn btn-outline btn-lg">
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

        .encuesta-form {
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

        .opciones-container {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
            margin-bottom: var(--space-4);
        }

        .opcion-item {
            display: flex;
            gap: var(--space-3);
            align-items: center;
        }

        .opcion-item input {
            flex: 1;
        }

        .opcion-item .remove-btn {
            padding: var(--space-2) var(--space-3);
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.3);
            border-radius: var(--radius-lg);
            color: var(--color-error);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .opcion-item .remove-btn:hover {
            background: rgba(255, 68, 68, 0.2);
        }

        .form-checks {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }

        .form-check {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-4);
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-lg);
        }

        .form-check input[type="checkbox"] {
            margin-top: 2px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .form-check label {
            flex: 1;
            cursor: pointer;
            margin: 0;
        }

        .form-check label i {
            margin-right: var(--space-2);
            color: var(--color-primary);
        }

        .form-check label small {
            display: block;
            margin-top: var(--space-1);
            color: var(--color-gray-400);
            font-size: var(--font-size-xs);
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

        .info-box ul {
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
        const opcionesContainer = document.getElementById('opcionesContainer');
        const addOpcionBtn = document.getElementById('addOpcionBtn');
        let opcionCount = 2;

        addOpcionBtn.addEventListener('click', function() {
            opcionCount++;

            const opcionItem = document.createElement('div');
            opcionItem.className = 'opcion-item';
            opcionItem.innerHTML = `
                <input type="text" name="opciones[]" class="form-control" placeholder="Opción ${opcionCount}">
                <button type="button" class="remove-btn" onclick="removeOpcion(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;

            opcionesContainer.appendChild(opcionItem);
        });
    });

    function removeOpcion(btn) {
        const opcionesContainer = document.getElementById('opcionesContainer');
        const opcionItem = btn.closest('.opcion-item');

        // No permitir menos de 2 opciones
        if (opcionesContainer.children.length > 2) {
            opcionItem.remove();
        } else {
            alert('Debes mantener al menos 2 opciones');
        }
    }
    </script>
</body>
</html>
