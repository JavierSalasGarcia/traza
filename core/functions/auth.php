<?php
/**
 * TrazaFI - Funciones de Autenticación
 * Manejo de autenticación, registro y verificación
 */

/**
 * Registra un nuevo usuario
 */
function register_user($nombre, $apellidos, $email, $password) {
    $db = Database::getInstance();

    // Validar email institucional
    if (!is_institutional_email($email)) {
        return ['success' => false, 'message' => 'Debe usar un correo institucional @uaemex.mx'];
    }

    // Verificar si el email ya existe
    $exists = $db->query("SELECT id FROM usuarios WHERE email = :email")
                ->bind(':email', $email)
                ->fetch();

    if ($exists) {
        return ['success' => false, 'message' => 'El correo electrónico ya está registrado'];
    }

    // Hash de contraseña
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Generar código de verificación
    $codigo = generate_numeric_code(VERIFICATION_CODE_LENGTH);
    $expiracion = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY_MINUTES . ' minutes'));

    // Insertar usuario
    $db->query("INSERT INTO usuarios (nombre, apellidos, email, password_hash, codigo_verificacion, codigo_expiracion)
               VALUES (:nombre, :apellidos, :email, :password_hash, :codigo, :expiracion)")
      ->bind(':nombre', $nombre)
      ->bind(':apellidos', $apellidos)
      ->bind(':email', $email)
      ->bind(':password_hash', $password_hash)
      ->bind(':codigo', $codigo)
      ->bind(':expiracion', $expiracion)
      ->execute();

    $user_id = $db->lastInsertId();

    // Enviar email de verificación
    send_verification_email($email, $nombre, $codigo);

    return ['success' => true, 'user_id' => $user_id, 'message' => 'Registro exitoso. Revisa tu correo para verificar tu cuenta.'];
}

/**
 * Verifica el código de verificación de email
 */
function verify_email_code($email, $codigo) {
    $db = Database::getInstance();

    $user = $db->query("SELECT id, codigo_verificacion, codigo_expiracion, email_verificado
                       FROM usuarios WHERE email = :email")
              ->bind(':email', $email)
              ->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Usuario no encontrado'];
    }

    if ($user['email_verificado']) {
        return ['success' => false, 'message' => 'El correo ya está verificado'];
    }

    if ($user['codigo_verificacion'] !== $codigo) {
        return ['success' => false, 'message' => 'Código de verificación inválido'];
    }

    if (strtotime($user['codigo_expiracion']) < time()) {
        return ['success' => false, 'message' => 'El código de verificación ha expirado'];
    }

    // Marcar email como verificado
    $db->query("UPDATE usuarios SET email_verificado = 1, codigo_verificacion = NULL, codigo_expiracion = NULL
               WHERE id = :id")
      ->bind(':id', $user['id'])
      ->execute();

    return ['success' => true, 'message' => 'Email verificado exitosamente'];
}

/**
 * Reenvía código de verificación
 */
function resend_verification_code($email) {
    $db = Database::getInstance();

    $user = $db->query("SELECT id, nombre, email_verificado FROM usuarios WHERE email = :email")
              ->bind(':email', $email)
              ->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Usuario no encontrado'];
    }

    if ($user['email_verificado']) {
        return ['success' => false, 'message' => 'El correo ya está verificado'];
    }

    // Generar nuevo código
    $codigo = generate_numeric_code(VERIFICATION_CODE_LENGTH);
    $expiracion = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY_MINUTES . ' minutes'));

    $db->query("UPDATE usuarios SET codigo_verificacion = :codigo, codigo_expiracion = :expiracion
               WHERE id = :id")
      ->bind(':codigo', $codigo)
      ->bind(':expiracion', $expiracion)
      ->bind(':id', $user['id'])
      ->execute();

    // Enviar email
    send_verification_email($email, $user['nombre'], $codigo);

    return ['success' => true, 'message' => 'Código de verificación reenviado'];
}

/**
 * Inicia sesión de usuario
 */
function login_user($email, $password) {
    $db = Database::getInstance();

    $user = $db->query("SELECT * FROM usuarios WHERE email = :email AND activo = 1")
              ->bind(':email', $email)
              ->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Credenciales inválidas'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Credenciales inválidas'];
    }

    // Actualizar último acceso
    $db->query("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id")
      ->bind(':id', $user['id'])
      ->execute();

    // Guardar en sesión
    $_SESSION['user'] = [
        'id' => $user['id'],
        'nombre' => $user['nombre'],
        'apellidos' => $user['apellidos'],
        'email' => $user['email'],
        'email_verificado' => $user['email_verificado'],
        'es_admin' => $user['es_admin'],
        'imagen_perfil' => $user['imagen_perfil']
    ];

    return ['success' => true, 'user' => $_SESSION['user']];
}

/**
 * Cierra sesión de usuario
 */
function logout_user() {
    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }

    session_destroy();
}

/**
 * Requiere que el usuario esté autenticado
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Debes iniciar sesión para acceder a esta página');
        redirect(base_url('login.php'));
    }
}

/**
 * Requiere que el usuario tenga email verificado
 */
function require_verified_email() {
    require_login();

    if (!$_SESSION['user']['email_verificado']) {
        set_flash('warning', 'Debes verificar tu correo electrónico');
        redirect(base_url('verify-email.php'));
    }
}

/**
 * Requiere que el usuario sea administrador
 */
function require_admin() {
    require_login();

    if (!is_admin()) {
        set_flash('error', 'No tienes permisos para acceder a esta página');
        redirect(base_url('dashboard.php'));
    }
}

/**
 * Verifica si el usuario tiene un permiso específico
 */
function has_permission($permission_key, $grupo_id = null) {
    if (!is_logged_in()) {
        return false;
    }

    if (is_admin()) {
        return true;
    }

    $db = Database::getInstance();
    $user_id = current_user_id();

    if ($grupo_id) {
        // Verificar permiso en grupo específico
        $count = $db->query("SELECT COUNT(*) FROM grupo_miembros gm
                            INNER JOIN rol_permisos rp ON gm.rol_id = rp.rol_id
                            INNER JOIN permisos p ON rp.permiso_id = p.id
                            WHERE gm.usuario_id = :user_id
                            AND gm.grupo_id = :grupo_id
                            AND gm.estado = 'aprobado'
                            AND p.clave = :permission")
                   ->bind(':user_id', $user_id)
                   ->bind(':grupo_id', $grupo_id)
                   ->bind(':permission', $permission_key)
                   ->fetchColumn();

        return $count > 0;
    } else {
        // Verificar permiso en cualquier grupo
        $count = $db->query("SELECT COUNT(*) FROM grupo_miembros gm
                            INNER JOIN rol_permisos rp ON gm.rol_id = rp.rol_id
                            INNER JOIN permisos p ON rp.permiso_id = p.id
                            WHERE gm.usuario_id = :user_id
                            AND gm.estado = 'aprobado'
                            AND p.clave = :permission")
                   ->bind(':user_id', $user_id)
                   ->bind(':permission', $permission_key)
                   ->fetchColumn();

        return $count > 0;
    }
}

/**
 * Verifica si el usuario es coordinador de un grupo
 */
function is_group_coordinator($grupo_id, $user_id = null) {
    if (is_admin()) {
        return true;
    }

    $user_id = $user_id ?? current_user_id();
    if (!$user_id) {
        return false;
    }

    $db = Database::getInstance();
    $count = $db->query("SELECT COUNT(*) FROM grupo_miembros
                        WHERE usuario_id = :user_id
                        AND grupo_id = :grupo_id
                        AND es_coordinador = 1
                        AND estado = 'aprobado'")
               ->bind(':user_id', $user_id)
               ->bind(':grupo_id', $grupo_id)
               ->fetchColumn();

    return $count > 0;
}

/**
 * Verifica si el usuario pertenece a un grupo
 */
function is_group_member($grupo_id, $user_id = null) {
    $user_id = $user_id ?? current_user_id();
    if (!$user_id) {
        return false;
    }

    $db = Database::getInstance();
    $count = $db->query("SELECT COUNT(*) FROM grupo_miembros
                        WHERE usuario_id = :user_id
                        AND grupo_id = :grupo_id
                        AND estado = 'aprobado'")
               ->bind(':user_id', $user_id)
               ->bind(':grupo_id', $grupo_id)
               ->fetchColumn();

    return $count > 0;
}

/**
 * Obtiene los grupos a los que pertenece un usuario
 */
function get_user_groups($user_id = null) {
    $user_id = $user_id ?? current_user_id();
    if (!$user_id) {
        return [];
    }

    $db = Database::getInstance();
    return $db->query("SELECT g.*, gm.es_coordinador, gm.rol_id
                      FROM grupos g
                      INNER JOIN grupo_miembros gm ON g.id = gm.grupo_id
                      WHERE gm.usuario_id = :user_id
                      AND gm.estado = 'aprobado'
                      ORDER BY g.nombre")
             ->bind(':user_id', $user_id)
             ->fetchAll();
}

/**
 * Envía email de verificación
 */
function send_verification_email($email, $nombre, $codigo) {
    $subject = 'Verifica tu cuenta en TrazaFI';
    $message = "Hola {$nombre},\n\n";
    $message .= "Tu código de verificación es: {$codigo}\n\n";
    $message .= "Este código expirará en " . VERIFICATION_CODE_EXPIRY_MINUTES . " minutos.\n\n";
    $message .= "Si no solicitaste este código, puedes ignorar este mensaje.\n\n";
    $message .= "Saludos,\nEquipo TrazaFI";

    return send_email($email, $subject, $message);
}

/**
 * Envía notificación de asignación a comisión
 */
function send_commission_notification($email, $nombre, $propuesta_titulo) {
    $subject = 'Has sido asignado a una comisión en TrazaFI';
    $message = "Hola {$nombre},\n\n";
    $message .= "Has sido asignado a la comisión para la propuesta: {$propuesta_titulo}\n\n";
    $message .= "Tienes " . COMMISSION_ACCEPTANCE_DAYS . " días para aceptar o rechazar esta asignación.\n\n";
    $message .= "Ingresa a la plataforma para más detalles.\n\n";
    $message .= "Saludos,\nEquipo TrazaFI";

    return send_email($email, $subject, $message);
}

/**
 * Envía email genérico (wrapper para configuración SMTP)
 */
function send_email($to, $subject, $message) {
    // TODO: Implementar envío real con SMTP cuando se configure
    // Por ahora solo registramos en log
    error_log("EMAIL TO: {$to} | SUBJECT: {$subject} | MESSAGE: {$message}");

    // En producción usar PHPMailer o similar con configuración SMTP
    return true;
}
