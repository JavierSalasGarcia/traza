<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_verified_email();

header('Content-Type: application/json');

$user_id = current_user_id();
$ticket_model = new Ticket();

// Votar por ticket
if (is_post() && isset($_POST['vote'])) {
    if (!verify_csrf_token(input('csrf_token'))) {
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
        exit;
    }

    $ticket_id = (int) input('ticket_id');
    $result = $ticket_model->vote($ticket_id, $user_id);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
