<?php
require_once dirname(__DIR__) . '/config/config.php';

// Si ya está logueado, redirigir al dashboard
if (is_logged_in()) {
    redirect(base_url('public/dashboard.php'));
}

$error = '';

if (is_post()) {
    $email = sanitize(input('email'));
    $password = input('password');

    if (!verify_csrf_token(input('csrf_token'))) {
        $error = 'Token de seguridad inválido';
    } else {
        $result = login_user($email, $password);

        if ($result['success']) {
            redirect(base_url('public/dashboard.php'));
        } else {
            $error = $result['message'];
        }
    }
}

$flash_messages = get_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="auth-wrapper">
            <div class="auth-container">
                <div class="auth-header">
                    <div class="auth-logo">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h1>TrazaFI</h1>
                    <p>Red Social Académica - Facultad de Ingeniería UAEMEX</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= sanitize($error) ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($flash_messages as $flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <i class="fas fa-info-circle"></i>
                        <?= sanitize($flash['message']) ?>
                    </div>
                <?php endforeach; ?>

                <form method="POST" action="" class="auth-form">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Correo Electrónico
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control"
                               placeholder="tu.correo@uaemex.mx"
                               value="<?= sanitize(input('email', '')) ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Contraseña
                        </label>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control"
                               placeholder="Tu contraseña"
                               required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i>
                        Iniciar Sesión
                    </button>
                </form>

                <div class="auth-footer">
                    <p>¿No tienes cuenta? <a href="<?= base_url('public/register.php') ?>">Regístrate aquí</a></p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-6);
        }

        .auth-container {
            max-width: 450px;
            width: 100%;
            background: var(--color-gray-900);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-10);
        }

        .auth-header {
            text-align: center;
            margin-bottom: var(--space-8);
        }

        .auth-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: var(--radius-xl);
            font-size: var(--font-size-4xl);
            color: var(--color-white);
            margin-bottom: var(--space-4);
        }

        .auth-header h1 {
            font-size: var(--font-size-3xl);
            margin-bottom: var(--space-2);
        }

        .auth-header p {
            color: var(--color-gray-400);
            margin-bottom: 0;
        }

        .auth-form {
            margin-bottom: var(--space-6);
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
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 153, 255, 0.1);
        }

        .form-control::placeholder {
            color: var(--color-gray-500);
        }

        .btn-block {
            width: 100%;
        }

        .auth-footer {
            text-align: center;
            padding-top: var(--space-6);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .auth-footer p {
            color: var(--color-gray-400);
            margin-bottom: 0;
        }

        .auth-footer a {
            color: var(--color-primary);
            font-weight: var(--font-weight-medium);
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

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: var(--color-success);
        }

        .alert-info {
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            color: var(--color-primary);
        }

        .alert-warning {
            background: rgba(255, 170, 0, 0.1);
            border: 1px solid rgba(255, 170, 0, 0.3);
            color: var(--color-warning);
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: var(--space-6);
            }
        }
    </style>
</body>
</html>
