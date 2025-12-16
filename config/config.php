<?php
/**
 * TrazaFI - Configuración Principal
 * Facultad de Ingeniería UAEMEX
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // Cambiar a 1 en desarrollo
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Solo HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Constantes del sistema
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CORE_PATH', ROOT_PATH . '/core');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// URLs
define('BASE_URL', 'https://fingenieria.mx');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'trazafi');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración SMTP
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', 'contacto@fingenieria.mx');
define('SMTP_PASS', '');
define('SMTP_FROM_NAME', 'TrazaFI - Facultad de Ingeniería UAEMEX');
define('SMTP_FROM_EMAIL', 'contacto@fingenieria.mx');

// Configuración de archivos
define('MAX_FILE_SIZE', 5242880); // 5MB en bytes
define('ALLOWED_FILE_TYPES', [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
]);

// Configuración de propuestas
define('DEFAULT_PROPOSAL_THRESHOLD', 200);
define('COMMISSION_ACCEPTANCE_DAYS', 4);

// Configuración de verificación de email
define('VERIFICATION_CODE_LENGTH', 6);
define('VERIFICATION_CODE_EXPIRY_MINUTES', 30);

// Configuración de seguridad
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_LIFETIME', 3600 * 24); // 24 horas
define('CSRF_TOKEN_NAME', 'trazafi_csrf_token');

// Patrón de email institucional
define('INSTITUTIONAL_EMAIL_PATTERN', '/@.*\.uaemex\.mx$/i');

// Configuración PWA
define('PWA_NAME', 'TrazaFI');
define('PWA_SHORT_NAME', 'TrazaFI');
define('PWA_DESCRIPTION', 'Red Social Académica - Facultad de Ingeniería UAEMEX');
define('PWA_THEME_COLOR', '#000000');
define('PWA_BACKGROUND_COLOR', '#000000');

// Autoloader
spl_autoload_register(function ($class) {
    $paths = [
        CORE_PATH . '/classes/' . $class . '.php',
        CORE_PATH . '/classes/' . str_replace('\\', '/', $class) . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Funciones helper
require_once CORE_PATH . '/functions/helpers.php';
require_once CORE_PATH . '/functions/auth.php';
require_once CORE_PATH . '/functions/validation.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
