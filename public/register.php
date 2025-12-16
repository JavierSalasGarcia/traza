<?php
require_once dirname(__DIR__) . '/config/config.php';

// Si ya está logueado, redirigir al dashboard
if (is_logged_in()) {
    redirect(base_url('public/dashboard.php'));
}

$errors = [];
$old_input = [];

if (is_post()) {
    $old_input = [
        'nombre' => input('nombre'),
        'apellidos' => input('apellidos'),
        'email' => input('email')
    ];

    if (!verify_csrf_token(input('csrf_token'))) {
        $errors['general'] = 'Token de seguridad inválido';
    } else {
        $validator = validator($_POST);
        $isValid = $validator->validate([
            'nombre' => 'required|min:2|max:100|alpha',
            'apellidos' => 'required|min:2|max:100|alpha',
            'email' => 'required|email|institutional|unique:usuarios,email',
            'password' => 'required|min:' . PASSWORD_MIN_LENGTH,
            'password_confirm' => 'required|matches:password'
        ]);

        if (!$isValid) {
            $errors = $validator->errors();
        } else {
            $result = register_user(
                sanitize(input('nombre')),
                sanitize(input('apellidos')),
                sanitize(input('email')),
                input('password')
            );

            if ($result['success']) {
                $_SESSION['pending_verification_email'] = input('email');
                set_flash('success', $result['message']);
                redirect(base_url('public/verify-email.php'));
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
    <title>Registro - TrazaFI</title>
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
                    <h1>Crear Cuenta</h1>
                    <p>Únete a la comunidad académica de la Facultad de Ingeniería</p>
                </div>

                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= sanitize($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="nombre">
                            <i class="fas fa-user"></i>
                            Nombre(s)
                        </label>
                        <input type="text"
                               id="nombre"
                               name="nombre"
                               class="form-control <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>"
                               placeholder="Tu nombre"
                               value="<?= sanitize($old_input['nombre'] ?? '') ?>"
                               required>
                        <?php if (isset($errors['nombre'])): ?>
                            <div class="invalid-feedback">
                                <?= sanitize($errors['nombre'][0]) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="apellidos">
                            <i class="fas fa-user"></i>
                            Apellidos
                        </label>
                        <input type="text"
                               id="apellidos"
                               name="apellidos"
                               class="form-control <?= isset($errors['apellidos']) ? 'is-invalid' : '' ?>"
                               placeholder="Tus apellidos"
                               value="<?= sanitize($old_input['apellidos'] ?? '') ?>"
                               required>
                        <?php if (isset($errors['apellidos'])): ?>
                            <div class="invalid-feedback">
                                <?= sanitize($errors['apellidos'][0]) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Correo Institucional
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               placeholder="tu.correo@uaemex.mx"
                               value="<?= sanitize($old_input['email'] ?? '') ?>"
                               required>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Debe ser un correo institucional @uaemex.mx
                        </div>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback">
                                <?= sanitize($errors['email'][0]) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Contraseña
                        </label>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               placeholder="Mínimo <?= PASSWORD_MIN_LENGTH ?> caracteres"
                               required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback">
                                <?= sanitize($errors['password'][0]) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">
                            <i class="fas fa-lock"></i>
                            Confirmar Contraseña
                        </label>
                        <input type="password"
                               id="password_confirm"
                               name="password_confirm"
                               class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                               placeholder="Repite tu contraseña"
                               required>
                        <?php if (isset($errors['password_confirm'])): ?>
                            <div class="invalid-feedback">
                                <?= sanitize($errors['password_confirm'][0]) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i>
                        Crear Cuenta
                    </button>
                </form>

                <div class="auth-footer">
                    <p>¿Ya tienes cuenta? <a href="<?= base_url('public/login.php') ?>">Inicia sesión aquí</a></p>
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
            max-width: 500px;
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

        .form-control.is-invalid {
            border-color: var(--color-error);
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(255, 68, 68, 0.1);
        }

        .form-control::placeholder {
            color: var(--color-gray-500);
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

        @media (max-width: 480px) {
            .auth-container {
                padding: var(--space-6);
            }
        }
    </style>
</body>
</html>
