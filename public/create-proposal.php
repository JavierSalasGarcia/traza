<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$propuesta_model = new Propuesta();
$group_model = new Group();

// Obtener ID si estamos editando
$propuesta_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$propuesta = null;
$is_edit = false;

if ($propuesta_id) {
    $propuesta = $propuesta_model->getById($propuesta_id);

    if (!$propuesta || !$propuesta_model->canEdit($propuesta_id, $user_id)) {
        set_flash('error', 'No tienes permiso para editar esta propuesta');
        redirect(base_url('public/proposals.php'));
    }

    $is_edit = true;
}

// Obtener grupos del usuario
$user_groups = get_user_groups($user_id);

// Categorías
$categorias = [
    'academico' => 'Académico',
    'infraestructura' => 'Infraestructura',
    'servicios' => 'Servicios',
    'social' => 'Social',
    'ambiental' => 'Ambiental',
    'tecnologia' => 'Tecnología',
    'otro' => 'Otro'
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
            'categoria' => input('categoria'),
            'grupo_id' => input('grupo_id') ?: null,
            'autor_id' => $user_id
        ];

        // Umbral personalizado
        $umbral_custom = input('umbral_firmas');
        if ($umbral_custom && is_numeric($umbral_custom) && $umbral_custom > 0) {
            $data['umbral_firmas'] = (int) $umbral_custom;
        }

        // Validaciones
        if (empty($data['titulo'])) {
            $errors[] = 'El título es requerido';
        }

        if (empty($data['descripcion'])) {
            $errors[] = 'La descripción es requerida';
        }

        if (empty($data['categoria'])) {
            $errors[] = 'La categoría es requerida';
        }

        if (empty($errors)) {
            if ($is_edit) {
                // Actualizar propuesta existente
                $query = "UPDATE propuestas
                          SET titulo = :titulo,
                              descripcion = :descripcion,
                              categoria = :categoria,
                              grupo_id = :grupo_id
                          WHERE id = :id";

                $propuesta_model->db->query($query);
                $propuesta_model->db->bind(':titulo', $data['titulo']);
                $propuesta_model->db->bind(':descripcion', $data['descripcion']);
                $propuesta_model->db->bind(':categoria', $data['categoria']);
                $propuesta_model->db->bind(':grupo_id', $data['grupo_id']);
                $propuesta_model->db->bind(':id', $propuesta_id);

                if ($propuesta_model->db->execute()) {
                    set_flash('success', 'Propuesta actualizada exitosamente');
                    redirect(base_url('public/view-proposal.php?id=' . $propuesta_id));
                } else {
                    $errors[] = 'Error al actualizar la propuesta';
                }
            } else {
                // Crear nueva propuesta
                $result = $propuesta_model->create($data);

                if ($result['success']) {
                    set_flash('success', $result['message']);
                    redirect(base_url('public/view-proposal.php?id=' . $result['id']));
                } else {
                    $errors[] = $result['message'];
                }
            }
        }

        if (!empty($errors)) {
            set_flash('error', implode('<br>', $errors));
        }
    }
}

// Valores por defecto
$titulo = $propuesta['titulo'] ?? '';
$descripcion = $propuesta['descripcion'] ?? '';
$categoria = $propuesta['categoria'] ?? '';
$grupo_id = $propuesta['grupo_id'] ?? '';
$umbral_firmas = $propuesta['umbral_firmas'] ?? get_config('umbral_firmas_default', 200);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Editar' : 'Nueva' ?> Propuesta - TrazaFI</title>
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
                    <a href="<?= base_url('public/proposals.php') ?>">Propuestas</a>
                    <i class="fas fa-chevron-right"></i>
                    <span><?= $is_edit ? 'Editar' : 'Nueva' ?> Propuesta</span>
                </div>
                <h1>
                    <i class="fas fa-lightbulb"></i>
                    <?= $is_edit ? 'Editar' : 'Nueva' ?> Propuesta
                </h1>
                <p>
                    <?= $is_edit ? 'Actualiza tu propuesta antes de que alcance el umbral de firmas' : 'Comparte tu idea con la comunidad y consigue el apoyo necesario' ?>
                </p>
            </div>

            <div class="form-container">
                <form method="POST" class="proposal-form">
                    <?= csrf_field() ?>

                    <div class="form-section">
                        <h2>Información Básica</h2>

                        <div class="form-group">
                            <label for="titulo" class="required">Título</label>
                            <input type="text"
                                   id="titulo"
                                   name="titulo"
                                   class="form-control"
                                   value="<?= sanitize($titulo) ?>"
                                   placeholder="Título claro y descriptivo de tu propuesta"
                                   required>
                            <small class="form-hint">Máximo 200 caracteres. Sé claro y específico.</small>
                        </div>

                        <div class="form-group">
                            <label for="descripcion" class="required">Descripción</label>
                            <textarea id="descripcion"
                                      name="descripcion"
                                      class="form-control"
                                      rows="8"
                                      placeholder="Describe tu propuesta detalladamente: ¿Qué problema resuelve? ¿Cómo beneficia a la comunidad? ¿Qué recursos se necesitan?"
                                      required><?= sanitize($descripcion) ?></textarea>
                            <small class="form-hint">Incluye todos los detalles relevantes. Una buena descripción ayuda a conseguir más firmas.</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="categoria" class="required">Categoría</label>
                                <select id="categoria" name="categoria" class="form-control" required>
                                    <option value="">Selecciona una categoría</option>
                                    <?php foreach ($categorias as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $categoria === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="grupo_id">Grupo (Opcional)</label>
                                <select id="grupo_id" name="grupo_id" class="form-control">
                                    <option value="">Propuesta General</option>
                                    <?php foreach ($user_groups as $group): ?>
                                        <option value="<?= $group['id'] ?>" <?= $grupo_id == $group['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($group['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint">Si está asociada a un grupo específico, selecciónalo aquí.</small>
                            </div>
                        </div>
                    </div>

                    <?php if (!$is_edit): ?>
                        <div class="form-section">
                            <h2>Configuración de Firmas</h2>

                            <div class="info-box">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Umbral de Firmas</strong>
                                    <p>Define cuántas firmas necesita tu propuesta para pasar a revisión. Por defecto es <?= number_format(get_config('umbral_firmas_default', 200)) ?> firmas.</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="umbral_firmas">Número de firmas requeridas</label>
                                <input type="number"
                                       id="umbral_firmas"
                                       name="umbral_firmas"
                                       class="form-control"
                                       value="<?= $umbral_firmas ?>"
                                       min="50"
                                       max="1000"
                                       step="10">
                                <small class="form-hint">Mínimo 50, máximo 1000. Deja el valor por defecto si no estás seguro.</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-section">
                        <h2>Consejos para una propuesta exitosa</h2>
                        <ul class="tips-list">
                            <li><i class="fas fa-check"></i> Sé específico sobre el problema y la solución propuesta</li>
                            <li><i class="fas fa-check"></i> Explica cómo beneficiará a la comunidad</li>
                            <li><i class="fas fa-check"></i> Incluye un plan de acción realista</li>
                            <li><i class="fas fa-check"></i> Menciona recursos necesarios si aplica</li>
                            <li><i class="fas fa-check"></i> Usa un lenguaje claro y respetuoso</li>
                        </ul>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-<?= $is_edit ? 'save' : 'paper-plane' ?>"></i>
                            <?= $is_edit ? 'Guardar Cambios' : 'Publicar Propuesta' ?>
                        </button>
                        <a href="<?= base_url('public/proposals.php') ?>" class="btn btn-outline btn-lg">
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

        .breadcrumb a:hover {
            text-decoration: underline;
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

        .proposal-form {
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

        .info-box {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-4);
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
            margin-bottom: var(--space-2);
            color: var(--color-white);
        }

        .info-box p {
            color: var(--color-gray-300);
            margin: 0;
            font-size: var(--font-size-sm);
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

        // Límite de caracteres para el título
        tituloInput.addEventListener('input', function() {
            if (this.value.length > 200) {
                this.value = this.value.substring(0, 200);
            }
        });

        // Auto-resize textarea
        descripcionInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    </script>
</body>
</html>
