<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_verified_email();

header('Content-Type: application/json');

$user_id = current_user_id();
$comentario_model = new Comentario();

// Eliminar comentario
if (is_post() && isset($_POST['delete_comment'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
        exit;
    }

    $comentario_id = (int) input('comentario_id');
    $result = $comentario_model->delete($comentario_id, $user_id);
    echo json_encode($result);
    exit;
}

// Reportar comentario
if (is_post() && isset($_POST['report_comment'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
        exit;
    }

    $comentario_id = (int) input('comentario_id');
    $razon = trim(input('razon'));

    if (empty($razon)) {
        echo json_encode(['success' => false, 'message' => 'Debes proporcionar una razón']);
        exit;
    }

    $result = $comentario_model->report($comentario_id, $user_id, $razon);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
