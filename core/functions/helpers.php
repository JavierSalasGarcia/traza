<?php
/**
 * TrazaFI - Funciones Helper
 * Funciones de utilidad general
 */

/**
 * Sanitiza entrada HTML
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirecciona a una URL
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit();
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
        exit();
    }
}

/**
 * Obtiene la URL base
 */
function base_url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Obtiene la URL de assets
 */
function asset_url($path) {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Obtiene la URL de uploads
 */
function upload_url($path) {
    return UPLOAD_URL . '/' . ltrim($path, '/');
}

/**
 * Establece un mensaje flash en sesión
 */
function set_flash($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Obtiene y limpia mensajes flash
 */
function get_flash() {
    if (isset($_SESSION['flash_messages'])) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    return [];
}

/**
 * Verifica si hay mensajes flash
 */
function has_flash() {
    return isset($_SESSION['flash_messages']) && count($_SESSION['flash_messages']) > 0;
}

/**
 * Genera un token CSRF y lo guarda en sesión
 */
function csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verifica un token CSRF
 */
function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Genera campo oculto con token CSRF
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Formatea una fecha
 */
function format_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Formatea una fecha con hora
 */
function format_datetime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * Obtiene el tiempo transcurrido en formato legible
 */
function time_ago($datetime) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Hace ' . $diff . ' segundo' . ($diff != 1 ? 's' : '');
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return 'Hace ' . $mins . ' minuto' . ($mins != 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return 'Hace ' . $hours . ' hora' . ($hours != 1 ? 's' : '');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return 'Hace ' . $days . ' día' . ($days != 1 ? 's' : '');
    } else {
        return format_datetime($datetime);
    }
}

/**
 * Trunca texto a una longitud específica
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Genera un slug a partir de un texto
 */
function slugify($text) {
    $text = strtolower($text);
    $text = preg_replace('/[áàâãª]/', 'a', $text);
    $text = preg_replace('/[éèê]/', 'e', $text);
    $text = preg_replace('/[íìî]/', 'i', $text);
    $text = preg_replace('/[óòôõº]/', 'o', $text);
    $text = preg_replace('/[úùû]/', 'u', $text);
    $text = preg_replace('/ñ/', 'n', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Formatea un tamaño de archivo
 */
function format_filesize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Verifica si una extensión de archivo es permitida
 */
function is_allowed_file_type($extension) {
    return array_key_exists(strtolower($extension), ALLOWED_FILE_TYPES);
}

/**
 * Genera un código aleatorio numérico
 */
function generate_numeric_code($length = 6) {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= mt_rand(0, 9);
    }
    return $code;
}

/**
 * Genera un nombre de archivo único
 */
function generate_unique_filename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $filename = pathinfo($original_filename, PATHINFO_FILENAME);
    $filename = slugify($filename);
    return $filename . '_' . uniqid() . '.' . $extension;
}

/**
 * Valida patrón de email institucional
 */
function is_institutional_email($email) {
    return preg_match(INSTITUTIONAL_EMAIL_PATTERN, $email) === 1;
}

/**
 * Escape para uso en JSON
 */
function json_escape($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/**
 * Debug helper
 */
function dd($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}

/**
 * Verifica si la petición es AJAX
 */
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Envía respuesta JSON
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Obtiene el usuario actual de sesión
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Obtiene el ID del usuario actual
 */
function current_user_id() {
    return $_SESSION['user']['id'] ?? null;
}

/**
 * Verifica si hay un usuario autenticado
 */
function is_logged_in() {
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

/**
 * Verifica si el usuario actual es administrador
 */
function is_admin() {
    return isset($_SESSION['user']['es_admin']) && $_SESSION['user']['es_admin'] == 1;
}

/**
 * Obtiene el valor de un parámetro GET o POST
 */
function input($key, $default = null) {
    if (isset($_POST[$key])) {
        return $_POST[$key];
    } elseif (isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

/**
 * Verifica si el método de la petición es POST
 */
function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Verifica si el método de la petición es GET
 */
function is_get() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}
