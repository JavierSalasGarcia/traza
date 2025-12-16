<?php
require_once dirname(__DIR__) . '/config/config.php';
require_verified_email();

$user_id = current_user_id();
$aviso_model = new Aviso();
$group_model = new Group();

// Obtener grupos donde el usuario puede crear avisos
$user_groups = get_user_groups($user_id);
$allowed_groups = [];

foreach ($user_groups as $group) {
    if (has_permission('puede_crear_avisos', $group['id']) || is_admin()) {
        $allowed_groups[] = $group;
    }
}

$errors = [];
$old_input = [];

// Edición de aviso existente
$aviso_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$aviso = null;
$is_edit = false;

if ($aviso_id) {
    $aviso = $aviso_model->getById($aviso_id);

    if (!$aviso) {
        set_flash('error', 'Aviso no encontrado');
        redirect(base_url('public/dashboard.php'));
    }

    if (!$aviso_model->canEdit($aviso_id, $user_id)) {
        set_flash('error', 'No tienes permisos para editar este aviso');
        redirect(base_url('public/dashboard.php'));
    }

    $is_edit = true;
    $old_input = $aviso;
}

if (is_post()) {
    $old_input = $_POST;

    if (!verify_csrf_token(input('csrf_token'))) {
        $errors['general'] = 'Token de seguridad inválido';
    } else {
        $validator = validator($_POST);
        $rules = [
            'titulo' => 'required|min:5|max:255',
            'contenido' => 'required|min:10',
            'grupo_id' => 'numeric'
        ];

        if (!$validator->validate($rules)) {
            $errors = $validator->errors();
        } else {
            $data = [
                'titulo' => sanitize(input('titulo')),
                'contenido' => input('contenido'), // No sanitizar contenido para preservar formato
                'grupo_id' => input('grupo_id') ? (int) input('grupo_id') : null,
                'autor_id' => $user_id,
                'categoria' => sanitize(input('categoria')),
                'etiquetas' => sanitize(input('etiquetas')),
                'destacado' => input('destacado') ? 1 : 0
            ];

            // Fechas de publicación
            $fecha_inicio = input('fecha_inicio_publicacion');
            $fecha_fin = input('fecha_fin_publicacion');

            $data['fecha_inicio_publicacion'] = !empty($fecha_inicio) ? $fecha_inicio : null;
            $data['fecha_fin_publicacion'] = !empty($fecha_fin) ? $fecha_fin : null;

            // Determinar si se publica inmediatamente
            $publicar_ahora = input('publicar_ahora') ? true : false;

            if ($publicar_ahora && empty($fecha_inicio)) {
                $data['publicado'] = 1;
                $data['fecha_inicio_publicacion'] = date('Y-m-d H:i:s');
            } else {
                $data['publicado'] = 0;
            }

            try {
                if ($is_edit) {
                    $result = $aviso_model->update($aviso_id, $data);
                    $current_aviso_id = $aviso_id;
                } else {
                    $current_aviso_id = $aviso_model->create($data);
                    $result = $current_aviso_id > 0;
                }

                if ($result) {
                    // Manejar archivos adjuntos
                    if (isset($_FILES['archivos'])) {
                        $upload_dir = UPLOAD_PATH . '/avisos/' . $current_aviso_id;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        foreach ($_FILES['archivos']['name'] as $key => $filename) {
                            if ($_FILES['archivos']['error'][$key] === UPLOAD_ERR_OK) {
                                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                                if (is_allowed_file_type($extension) && $_FILES['archivos']['size'][$key] <= MAX_FILE_SIZE) {
                                    $new_filename = generate_unique_filename($filename);
                                    $filepath = $upload_dir . '/' . $new_filename;

                                    if (move_uploaded_file($_FILES['archivos']['tmp_name'][$key], $filepath)) {
                                        $relative_path = 'avisos/' . $current_aviso_id . '/' . $new_filename;
                                        $aviso_model->addArchivo(
                                            $current_aviso_id,
                                            $filename,
                                            $relative_path,
                                            ALLOWED_FILE_TYPES[$extension],
                                            $_FILES['archivos']['size'][$key]
                                        );
                                    }
                                }
                            }
                        }
                    }

                    set_flash('success', $is_edit ? 'Aviso actualizado correctamente' : 'Aviso creado correctamente');
                    redirect(base_url('public/view-aviso.php?id=' . $current_aviso_id));
                } else {
                    $errors['general'] = 'Error al guardar el aviso';
                }
            } catch (Exception $e) {
                $errors['general'] = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Editar' : 'Crear' ?> Aviso - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../core/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= base_url('public/dashboard.php') ?>">Dashboard</a>
                        <i class="fas fa-chevron-right"></i>
                        <span><?= $is_edit ? 'Editar' : 'Crear' ?> Aviso</span>
                    </nav>
                    <h1><?= $is_edit ? 'Editar' : 'Crear' ?> Aviso</h1>
                </div>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= sanitize($errors['general']) ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" enctype="multipart/form-data" class="aviso-form">
                    <?= csrf_field() ?>

                    <div class="form-section">
                        <h2>Información Básica</h2>

                        <div class="form-group">
                            <label for="titulo">
                                <i class="fas fa-heading"></i>
                                Título del Aviso *
                            </label>
                            <input type="text"
                                   id="titulo"
                                   name="titulo"
                                   class="form-control <?= isset($errors['titulo']) ? 'is-invalid' : '' ?>"
                                   value="<?= sanitize($old_input['titulo'] ?? '') ?>"
                                   required>
                            <?php if (isset($errors['titulo'])): ?>
                                <div class="invalid-feedback"><?= sanitize($errors['titulo'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="grupo_id">
                                <i class="fas fa-users"></i>
                                Grupo
                            </label>
                            <select id="grupo_id" name="grupo_id" class="form-control">
                                <option value="">Aviso General (Toda la comunidad)</option>
                                <?php foreach ($allowed_groups as $group): ?>
                                    <option value="<?= $group['id'] ?>"
                                            <?= (isset($old_input['grupo_id']) && $old_input['grupo_id'] == $group['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($group['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">
                                <i class="fas fa-info-circle"></i>
                                Si no seleccionas un grupo, el aviso será visible para toda la comunidad
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="categoria">
                                    <i class="fas fa-tag"></i>
                                    Categoría
                                </label>
                                <input type="text"
                                       id="categoria"
                                       name="categoria"
                                       class="form-control"
                                       placeholder="Ej: Académico, Evento, Convocatoria"
                                       value="<?= sanitize($old_input['categoria'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="etiquetas">
                                    <i class="fas fa-tags"></i>
                                    Etiquetas
                                </label>
                                <input type="text"
                                       id="etiquetas"
                                       name="etiquetas"
                                       class="form-control"
                                       placeholder="Separadas por comas"
                                       value="<?= sanitize($old_input['etiquetas'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contenido">
                                <i class="fas fa-align-left"></i>
                                Contenido *
                            </label>
                            <textarea id="contenido"
                                      name="contenido"
                                      class="form-control textarea-large <?= isset($errors['contenido']) ? 'is-invalid' : '' ?>"
                                      rows="10"
                                      required><?= htmlspecialchars($old_input['contenido'] ?? '') ?></textarea>
                            <?php if (isset($errors['contenido'])): ?>
                                <div class="invalid-feedback"><?= sanitize($errors['contenido'][0]) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Fechas de Publicación</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fecha_inicio_publicacion">
                                    <i class="fas fa-calendar-alt"></i>
                                    Fecha de Inicio
                                </label>
                                <input type="datetime-local"
                                       id="fecha_inicio_publicacion"
                                       name="fecha_inicio_publicacion"
                                       class="form-control"
                                       value="<?= isset($old_input['fecha_inicio_publicacion']) ? date('Y-m-d\TH:i', strtotime($old_input['fecha_inicio_publicacion'])) : '' ?>">
                                <div class="form-help">
                                    <i class="fas fa-info-circle"></i>
                                    Deja vacío para publicar inmediatamente
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="fecha_fin_publicacion">
                                    <i class="fas fa-calendar-times"></i>
                                    Fecha de Fin
                                </label>
                                <input type="datetime-local"
                                       id="fecha_fin_publicacion"
                                       name="fecha_fin_publicacion"
                                       class="form-control"
                                       value="<?= isset($old_input['fecha_fin_publicacion']) ? date('Y-m-d\TH:i', strtotime($old_input['fecha_fin_publicacion'])) : '' ?>">
                                <div class="form-help">
                                    <i class="fas fa-info-circle"></i>
                                    Deja vacío para que no expire
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Archivos Adjuntos</h2>

                        <?php if ($is_edit): ?>
                            <?php $archivos = $aviso_model->getArchivos($aviso_id); ?>
                            <?php if (!empty($archivos)): ?>
                                <div class="archivos-existentes">
                                    <h3>Archivos Actuales</h3>
                                    <div class="archivos-list">
                                        <?php foreach ($archivos as $archivo): ?>
                                            <div class="archivo-item">
                                                <i class="fas fa-file"></i>
                                                <span><?= sanitize($archivo['nombre_archivo']) ?></span>
                                                <span class="archivo-size"><?= format_filesize($archivo['tamano_bytes']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="archivos">
                                <i class="fas fa-paperclip"></i>
                                Adjuntar Archivos
                            </label>
                            <input type="file"
                                   id="archivos"
                                   name="archivos[]"
                                   class="form-control-file"
                                   multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp">
                            <div class="form-help">
                                <i class="fas fa-info-circle"></i>
                                Puedes adjuntar múltiples archivos. Máximo <?= format_filesize(MAX_FILE_SIZE) ?> por archivo.
                                Formatos permitidos: PDF, Word, Excel, PowerPoint, imágenes.
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Opciones</h2>

                        <div class="form-check">
                            <input type="checkbox"
                                   id="publicar_ahora"
                                   name="publicar_ahora"
                                   value="1"
                                   <?= (!$is_edit || (isset($old_input['publicado']) && $old_input['publicado'])) ? 'checked' : '' ?>>
                            <label for="publicar_ahora">
                                Publicar inmediatamente
                            </label>
                        </div>

                        <?php if (is_admin()): ?>
                            <div class="form-check">
                                <input type="checkbox"
                                       id="destacado"
                                       name="destacado"
                                       value="1"
                                       <?= (isset($old_input['destacado']) && $old_input['destacado']) ? 'checked' : '' ?>>
                                <label for="destacado">
                                    Marcar como destacado
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-save"></i>
                            <?= $is_edit ? 'Actualizar' : 'Crear' ?> Aviso
                        </button>
                        <a href="<?= base_url('public/dashboard.php') ?>" class="btn btn-secondary btn-large">
                            <i class="fas fa-times"></i>
                            Cancelar
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

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-3);
            font-size: var(--font-size-sm);
            color: var(--color-gray-400);
        }

        .breadcrumb a {
            color: var(--color-gray-400);
        }

        .breadcrumb i {
            font-size: 0.7em;
        }

        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .aviso-form {
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
        }

        .form-section {
            margin-bottom: var(--space-10);
            padding-bottom: var(--space-8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .form-section h2 {
            font-size: var(--font-size-2xl);
            margin-bottom: var(--space-6);
            color: var(--color-primary);
        }

        .form-group {
            margin-bottom: var(--space-5);
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-2);
            color: var(--color-gray-200);
            font-weight: var(--font-weight-medium);
            font-size: var(--font-size-sm);
        }

        .form-control {
            width: 100%;
            padding: var(--space-4);
            background: var(--color-gray-800);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--color-white);
            font-size: var(--font-size-base);
            transition: all var(--transition-fast);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 153, 255, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--color-error);
        }

        .textarea-large {
            min-height: 250px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-4);
        }

        .form-help {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-top: var(--space-2);
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .invalid-feedback {
            display: block;
            margin-top: var(--space-2);
            color: var(--color-error);
            font-size: var(--font-size-sm);
        }

        .form-control-file {
            width: 100%;
            padding: var(--space-3);
            background: var(--color-gray-800);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            color: var(--color-white);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .form-control-file:hover {
            border-color: var(--color-primary);
            background: rgba(0, 153, 255, 0.05);
        }

        .archivos-existentes {
            margin-bottom: var(--space-6);
            padding: var(--space-4);
            background: var(--color-gray-800);
            border-radius: var(--radius-lg);
        }

        .archivos-existentes h3 {
            font-size: var(--font-size-lg);
            margin-bottom: var(--space-4);
        }

        .archivos-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .archivo-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3);
            background: var(--color-gray-900);
            border-radius: var(--radius-md);
        }

        .archivo-item i {
            color: var(--color-primary);
        }

        .archivo-size {
            margin-left: auto;
            color: var(--color-gray-400);
            font-size: var(--font-size-sm);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-4);
        }

        .form-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .form-check label {
            margin: 0;
            cursor: pointer;
            font-weight: var(--font-weight-normal);
        }

        .form-actions {
            display: flex;
            gap: var(--space-4);
            margin-top: var(--space-8);
        }

        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-3);
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

            .aviso-form {
                padding: var(--space-6);
            }
        }
    </style>
</body>
</html>
