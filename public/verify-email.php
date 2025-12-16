<?php
require_once dirname(__DIR__) . '/config/config.php';

// Si ya está logueado y verificado, redirigir
if (is_logged_in() && $_SESSION['user']['email_verificado']) {
    redirect(base_url('public/dashboard.php'));
}

$email = $_SESSION['pending_verification_email'] ?? ($_SESSION['user']['email'] ?? '');
$errors = [];
$success = '';

if (empty($email)) {
    redirect(base_url('public/login.php'));
}

if (is_post()) {
    if (!verify_csrf_token(input('csrf_token'))) {
        $errors['general'] = 'Token de seguridad inválido';
    } else {
        $action = input('action');

        if ($action === 'verify') {
            $codigo = sanitize(input('codigo'));

            if (empty($codigo)) {
                $errors['codigo'] = 'Ingresa el código de verificación';
            } else {
                $result = verify_email_code($email, $codigo);

                if ($result['success']) {
                    unset($_SESSION['pending_verification_email']);

                    // Si ya estaba logueado, actualizar sesión
                    if (is_logged_in()) {
                        $_SESSION['user']['email_verificado'] = 1;
                    }

                    set_flash('success', $result['message']);
                    redirect(base_url('public/login.php'));
                } else {
                    $errors['general'] = $result['message'];
                }
            }
        } elseif ($action === 'resend') {
            $result = resend_verification_code($email);

            if ($result['success']) {
                $success = $result['message'];
            } else {
                $errors['general'] = $result['message'];
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
    <title>Verificar Email - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="auth-wrapper">
            <div class="auth-container">
                <div class="auth-header">
                    <div class="auth-logo">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <h1>Verifica tu Email</h1>
                    <p>Hemos enviado un código de verificación a</p>
                    <p class="highlight-email"><?= sanitize($email) ?></p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= sanitize($success) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= sanitize($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">

                    <div class="form-group">
                        <label for="codigo">
                            <i class="fas fa-key"></i>
                            Código de Verificación
                        </label>
                        <input type="text"
                               id="codigo"
                               name="codigo"
                               class="form-control code-input <?= isset($errors['codigo']) ? 'is-invalid' : '' ?>"
                               placeholder="000000"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               autocomplete="off"
                               required>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Ingresa el código de 6 dígitos que recibiste por correo
                        </div>
                        <?php if (isset($errors['codigo'])): ?>
                            <div class="invalid-feedback">
                                <?= sanitize($errors['codigo']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-check"></i>
                        Verificar Email
                    </button>
                </form>

                <div class="resend-section">
                    <p>¿No recibiste el código?</p>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="resend">
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <i class="fas fa-redo"></i>
                            Reenviar Código
                        </button>
                    </form>
                </div>

                <div class="auth-footer">
                    <p><a href="<?= base_url('public/login.php') ?>">Volver al inicio de sesión</a></p>
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
            margin-bottom: var(--space-2);
        }

        .highlight-email {
            color: var(--color-primary) !important;
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-lg);
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

        .code-input {
            text-align: center;
            font-size: var(--font-size-2xl);
            letter-spacing: 0.5em;
            font-family: var(--font-family-mono);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 153, 255, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--color-error);
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

        .btn-block {
            width: 100%;
        }

        .btn-sm {
            padding: var(--space-2) var(--space-4);
            font-size: var(--font-size-sm);
        }

        .resend-section {
            text-align: center;
            padding: var(--space-6) 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: var(--space-6);
        }

        .resend-section p {
            color: var(--color-gray-400);
            margin-bottom: var(--space-3);
        }

        .auth-footer {
            text-align: center;
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

        @media (max-width: 480px) {
            .auth-container {
                padding: var(--space-6);
            }

            .code-input {
                font-size: var(--font-size-xl);
            }
        }
    </style>

    <script>
        // Auto-format código de verificación
        document.getElementById('codigo').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
