<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$encuesta_model = new Encuesta();
$grupos = get_user_groups();

// Procesar formulario
if (is_post()) {
    $csrf_token = input('csrf_token');
    if (!verify_csrf_token($csrf_token)) {
        set_flash('error', 'Token de seguridad inválido');
        redirect('public/create-encuesta.php');
    }

    // Construir estructura de datos
    $data = [
        'titulo' => input('titulo'),
        'descripcion' => input('descripcion'),
        'autor_id' => $user['id'],
        'grupo_id' => input('grupo_id') ?: null,
        'fecha_inicio' => input('fecha_inicio') ?: date('Y-m-d H:i:s'),
        'fecha_fin' => input('fecha_fin') ?: null,
        'anonima' => input('anonima') ? 1 : 0,
        'preguntas' => []
    ];

    // Procesar preguntas
    $preguntas_texto = $_POST['preguntas_texto'] ?? [];
    $preguntas_tipo = $_POST['preguntas_tipo'] ?? [];
    $preguntas_requerida = $_POST['preguntas_requerida'] ?? [];

    foreach ($preguntas_texto as $index => $texto) {
        if (!empty(trim($texto))) {
            $pregunta = [
                'texto' => trim($texto),
                'tipo' => $preguntas_tipo[$index] ?? 'unica',
                'requerida' => isset($preguntas_requerida[$index]) ? 1 : 0,
                'opciones' => []
            ];

            // Si la pregunta tiene opciones
            if (isset($_POST["pregunta_{$index}_opciones"])) {
                $opciones = $_POST["pregunta_{$index}_opciones"];
                foreach ($opciones as $opcion_texto) {
                    if (!empty(trim($opcion_texto))) {
                        $pregunta['opciones'][] = trim($opcion_texto);
                    }
                }
            }

            $data['preguntas'][] = $pregunta;
        }
    }

    $result = $encuesta_model->create($data);

    if ($result['success']) {
        set_flash('success', $result['message']);
        redirect('public/view-encuesta.php?id=' . $result['id']);
    } else {
        set_flash('error', $result['message']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Encuesta - TrazaFI</title>
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

            <div class="page-header">
                <div>
                    <h1><i class="fas fa-poll"></i> Crear Nueva Encuesta</h1>
                    <p>Diseña encuestas con múltiples preguntas y tipos de respuesta</p>
                </div>
                <a href="<?= base_url('public/encuestas.php') ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>

            <form method="POST" id="encuestaForm" class="form-card">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <!-- Información General -->
                <div class="form-section">
                    <h2><i class="fas fa-info-circle"></i> Información General</h2>

                    <div class="form-group">
                        <label for="titulo">Título de la Encuesta *</label>
                        <input type="text" id="titulo" name="titulo" class="form-control" required
                               placeholder="Ej: Evaluación de Instalaciones 2024">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3"
                                  placeholder="Describe el propósito de esta encuesta..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="grupo_id">Grupo</label>
                            <select id="grupo_id" name="grupo_id" class="form-control">
                                <option value="">General (todos los usuarios)</option>
                                <?php foreach ($grupos as $grupo): ?>
                                    <option value="<?= $grupo['id'] ?>"><?= sanitize($grupo['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="anonima" value="1" id="anonimaCheckbox">
                                <strong>Encuesta Anónima</strong>
                            </label>
                            <small class="form-hint">Las respuestas serán anónimas y verificables con recibos</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha de Inicio</label>
                            <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" class="form-control"
                                   value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="form-group">
                            <label for="fecha_fin">Fecha de Finalización</label>
                            <input type="datetime-local" id="fecha_fin" name="fecha_fin" class="form-control">
                            <small class="form-hint">Opcional - Deja vacío para sin límite</small>
                        </div>
                    </div>
                </div>

                <!-- Preguntas -->
                <div class="form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-question-circle"></i> Preguntas</h2>
                        <button type="button" class="btn btn-primary btn-sm" id="addPreguntaBtn">
                            <i class="fas fa-plus"></i> Agregar Pregunta
                        </button>
                    </div>

                    <div id="preguntasContainer">
                        <!-- Las preguntas se agregarán aquí dinámicamente -->
                    </div>

                    <div class="empty-state" id="emptyState">
                        <i class="fas fa-question-circle"></i>
                        <p>No has agregado preguntas aún</p>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('addPreguntaBtn').click()">
                            <i class="fas fa-plus"></i> Agregar Primera Pregunta
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-check"></i> Crear Encuesta
                    </button>
                    <a href="<?= base_url('public/encuestas.php') ?>" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </main>

    <style>
        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
        }

        .form-section {
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-6);
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-4);
        }

        .pregunta-card {
            background: rgba(0, 153, 255, 0.05);
            border: 1px solid rgba(0, 153, 255, 0.2);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
            position: relative;
        }

        .pregunta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-3);
        }

        .pregunta-number {
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            font-size: var(--font-size-lg);
        }

        .pregunta-actions {
            display: flex;
            gap: var(--space-2);
        }

        .btn-icon {
            padding: var(--space-2);
            min-width: auto;
            aspect-ratio: 1;
        }

        .opciones-container {
            margin-top: var(--space-3);
            padding-left: var(--space-4);
            border-left: 2px solid var(--color-primary);
        }

        .opcion-item {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-2);
        }

        .opcion-item input {
            flex: 1;
        }

        .empty-state {
            text-align: center;
            padding: var(--space-8) var(--space-4);
            color: var(--color-text-muted);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: var(--space-4);
            opacity: 0.3;
        }

        .empty-state p {
            margin-bottom: var(--space-4);
        }

        .tipo-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            text-transform: uppercase;
        }

        .tipo-unica { background: rgba(0, 153, 255, 0.1); color: var(--color-primary); }
        .tipo-multiple { background: rgba(0, 255, 136, 0.1); color: var(--color-success); }
        .tipo-abierta { background: rgba(255, 170, 0, 0.1); color: var(--color-warning); }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }

            .section-header {
                flex-direction: column;
                gap: var(--space-2);
                align-items: stretch;
            }
        }
    </style>

    <script>
        let preguntaCount = 0;

        const addPreguntaBtn = document.getElementById('addPreguntaBtn');
        const preguntasContainer = document.getElementById('preguntasContainer');
        const emptyState = document.getElementById('emptyState');
        const form = document.getElementById('encuestaForm');

        addPreguntaBtn.addEventListener('click', function() {
            addPregunta();
        });

        function addPregunta(tipo = 'unica') {
            preguntaCount++;
            emptyState.style.display = 'none';

            const preguntaCard = document.createElement('div');
            preguntaCard.className = 'pregunta-card';
            preguntaCard.dataset.preguntaIndex = preguntaCount - 1;

            preguntaCard.innerHTML = `
                <div class="pregunta-header">
                    <span class="pregunta-number">Pregunta ${preguntaCount}</span>
                    <div class="pregunta-actions">
                        <button type="button" class="btn btn-sm btn-icon btn-danger" onclick="removePregunta(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Texto de la Pregunta *</label>
                    <input type="text" name="preguntas_texto[]" class="form-control" required
                           placeholder="Escribe tu pregunta aquí...">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Pregunta</label>
                        <select name="preguntas_tipo[]" class="form-control tipo-select" onchange="toggleOpciones(this)">
                            <option value="unica">Respuesta Única (radio)</option>
                            <option value="multiple">Respuesta Múltiple (checkbox)</option>
                            <option value="abierta">Respuesta Abierta (texto)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="preguntas_requerida[${preguntaCount - 1}]" value="1" checked>
                            Pregunta Requerida
                        </label>
                    </div>
                </div>

                <div class="opciones-section">
                    <div class="opciones-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                        <label style="margin: 0;">Opciones de Respuesta</label>
                        <button type="button" class="btn btn-sm btn-primary add-opcion-btn" onclick="addOpcion(this)">
                            <i class="fas fa-plus"></i> Agregar Opción
                        </button>
                    </div>
                    <div class="opciones-container">
                        <!-- Opciones se agregan aquí -->
                    </div>
                </div>
            `;

            preguntasContainer.appendChild(preguntaCard);

            // Agregar 2 opciones por defecto si es tipo opción
            if (tipo === 'unica' || tipo === 'multiple') {
                const opcionBtn = preguntaCard.querySelector('.add-opcion-btn');
                addOpcion(opcionBtn);
                addOpcion(opcionBtn);
            }
        }

        function removePregunta(btn) {
            const card = btn.closest('.pregunta-card');
            card.remove();

            // Renumerar preguntas
            const preguntas = preguntasContainer.querySelectorAll('.pregunta-card');
            preguntas.forEach((pregunta, index) => {
                pregunta.querySelector('.pregunta-number').textContent = `Pregunta ${index + 1}`;
            });

            // Mostrar empty state si no hay preguntas
            if (preguntas.length === 0) {
                emptyState.style.display = 'block';
            }
        }

        function toggleOpciones(select) {
            const card = select.closest('.pregunta-card');
            const opcionesSection = card.querySelector('.opciones-section');

            if (select.value === 'abierta') {
                opcionesSection.style.display = 'none';
            } else {
                opcionesSection.style.display = 'block';

                // Si no tiene opciones, agregar 2 por defecto
                const opciones = card.querySelectorAll('.opcion-item');
                if (opciones.length === 0) {
                    const btn = card.querySelector('.add-opcion-btn');
                    addOpcion(btn);
                    addOpcion(btn);
                }
            }
        }

        function addOpcion(btn) {
            const card = btn.closest('.pregunta-card');
            const container = card.querySelector('.opciones-container');
            const preguntaIndex = card.dataset.preguntaIndex;
            const opcionCount = container.querySelectorAll('.opcion-item').length + 1;

            const opcionItem = document.createElement('div');
            opcionItem.className = 'opcion-item';
            opcionItem.innerHTML = `
                <input type="text" name="pregunta_${preguntaIndex}_opciones[]"
                       class="form-control" placeholder="Opción ${opcionCount}" required>
                <button type="button" class="btn btn-sm btn-icon btn-danger" onclick="removeOpcion(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;

            container.appendChild(opcionItem);
        }

        function removeOpcion(btn) {
            const card = btn.closest('.pregunta-card');
            const opciones = card.querySelectorAll('.opcion-item');

            // No permitir eliminar si solo quedan 2 opciones
            if (opciones.length <= 2) {
                alert('Debe haber al menos 2 opciones');
                return;
            }

            btn.closest('.opcion-item').remove();
        }

        // Validación del formulario
        form.addEventListener('submit', function(e) {
            const preguntas = preguntasContainer.querySelectorAll('.pregunta-card');

            if (preguntas.length === 0) {
                e.preventDefault();
                alert('Debes agregar al menos una pregunta');
                return false;
            }

            // Validar que cada pregunta de tipo opción tenga al menos 2 opciones
            let valid = true;
            preguntas.forEach(pregunta => {
                const tipo = pregunta.querySelector('.tipo-select').value;
                if (tipo !== 'abierta') {
                    const opciones = pregunta.querySelectorAll('.opcion-item');
                    if (opciones.length < 2) {
                        valid = false;
                        alert('Cada pregunta de opción debe tener al menos 2 opciones');
                    }
                }
            });

            if (!valid) {
                e.preventDefault();
                return false;
            }
        });

        // Agregar primera pregunta al cargar
        addPregunta();
    </script>
</body>
</html>
