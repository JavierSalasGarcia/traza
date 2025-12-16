<?php
/**
 * TrazaFI - Script de Instalación
 * Este script instala la base de datos y verifica la configuración
 */

// Configuración temporal para instalación
$db_host = 'localhost';
$db_name = 'trazafi';
$db_user = 'root';
$db_pass = '';

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Paso 1: Verificar conexión y crear base de datos
if ($step === 1 && isset($_POST['install_db'])) {
    try {
        // Actualizar credenciales si se enviaron
        if (isset($_POST['db_host'])) $db_host = $_POST['db_host'];
        if (isset($_POST['db_name'])) $db_name = $_POST['db_name'];
        if (isset($_POST['db_user'])) $db_user = $_POST['db_user'];
        if (isset($_POST['db_pass'])) $db_pass = $_POST['db_pass'];

        // Conectar sin especificar base de datos
        $pdo = new PDO("mysql:host={$db_host}", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Crear base de datos si no existe
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Seleccionar base de datos
        $pdo->exec("USE `{$db_name}`");

        // Leer y ejecutar el archivo SQL
        $sql = file_get_contents(__DIR__ . '/database.sql');

        // Eliminar comentarios y dividir en declaraciones
        $statements = array_filter(
            array_map('trim',
                preg_split('/;\s*$/m', $sql)
            )
        );

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        $success = 'Base de datos instalada correctamente';
        header('Location: ?step=2&success=1');
        exit;

    } catch (PDOException $e) {
        $error = 'Error al instalar la base de datos: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - TrazaFI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #000000;
            background-image:
                radial-gradient(circle at 25% 25%, rgba(0, 153, 255, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(0, 255, 170, 0.05) 0%, transparent 50%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .install-container {
            max-width: 600px;
            width: 100%;
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 3rem;
        }

        .install-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .install-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0099ff, #00ffaa);
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #999999;
            font-size: 0.9rem;
        }

        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            z-index: -1;
        }

        .step:last-child::after {
            display: none;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: #2a2a2a;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .step.active .step-number {
            background: linear-gradient(135deg, #0099ff, #00ffaa);
            border-color: transparent;
        }

        .step-label {
            font-size: 0.8rem;
            color: #666666;
        }

        .step.active .step-label {
            color: #0099ff;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.3);
            color: #ff4444;
        }

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: #00ff88;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e0e0e0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        input {
            width: 100%;
            padding: 0.75rem;
            background: #2a2a2a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.25s ease;
        }

        input:focus {
            outline: none;
            border-color: #0099ff;
            box-shadow: 0 0 0 3px rgba(0, 153, 255, 0.1);
        }

        .form-help {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #999999;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #0099ff, #00ffaa);
            border: none;
            border-radius: 0.5rem;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(0, 153, 255, 0.3);
        }

        .btn-block {
            width: 100%;
        }

        .requirements {
            background: #2a2a2a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .requirements h3 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .req-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            background: #1a1a1a;
            border-radius: 0.5rem;
        }

        .req-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .req-icon.ok {
            color: #00ff88;
        }

        .req-icon.error {
            color: #ff4444;
        }

        .success-message {
            text-align: center;
            padding: 2rem 0;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0099ff, #00ffaa);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <div class="install-logo">🎓</div>
            <h1>TrazaFI</h1>
            <p class="subtitle">Instalación de la Plataforma</p>
        </div>

        <div class="steps">
            <div class="step <?= $step === 1 ? 'active' : '' ?>">
                <div class="step-number">1</div>
                <div class="step-label">Base de Datos</div>
            </div>
            <div class="step <?= $step === 2 ? 'active' : '' ?>">
                <div class="step-number">2</div>
                <div class="step-label">Completado</div>
            </div>
        </div>

        <?php if ($step === 1): ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="requirements">
                <h3>Requisitos del Sistema</h3>
                <?php
                $phpVersion = version_compare(PHP_VERSION, '7.4.0', '>=');
                $pdoAvailable = extension_loaded('pdo') && extension_loaded('pdo_mysql');
                ?>
                <div class="req-item">
                    <span class="req-icon <?= $phpVersion ? 'ok' : 'error' ?>">
                        <?= $phpVersion ? '✓' : '✗' ?>
                    </span>
                    <span>PHP >= 7.4.0 (Actual: <?= PHP_VERSION ?>)</span>
                </div>
                <div class="req-item">
                    <span class="req-icon <?= $pdoAvailable ? 'ok' : 'error' ?>">
                        <?= $pdoAvailable ? '✓' : '✗' ?>
                    </span>
                    <span>PDO MySQL Extension</span>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="db_host">Host de Base de Datos</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($db_host) ?>" required>
                    <div class="form-help">Generalmente "localhost"</div>
                </div>

                <div class="form-group">
                    <label for="db_name">Nombre de Base de Datos</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($db_name) ?>" required>
                    <div class="form-help">La base de datos se creará si no existe</div>
                </div>

                <div class="form-group">
                    <label for="db_user">Usuario de Base de Datos</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($db_user) ?>" required>
                </div>

                <div class="form-group">
                    <label for="db_pass">Contraseña de Base de Datos</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($db_pass) ?>">
                </div>

                <button type="submit" name="install_db" class="btn btn-block">
                    Instalar Base de Datos →
                </button>
            </form>

        <?php elseif ($step === 2): ?>
            <div class="alert alert-success">
                ✓ ¡Instalación completada exitosamente!
            </div>

            <div class="success-message">
                <div class="success-icon">✓</div>
                <h2>¡Todo listo!</h2>
                <p style="color: #999999; margin: 1rem 0 2rem;">La plataforma TrazaFI ha sido instalada correctamente.</p>

                <div style="background: #2a2a2a; padding: 1.5rem; border-radius: 0.5rem; margin-bottom: 2rem; text-align: left;">
                    <h3 style="margin-bottom: 1rem;">Próximos Pasos:</h3>
                    <ol style="margin-left: 1.5rem; color: #e0e0e0; line-height: 1.8;">
                        <li>Actualiza las credenciales en <code style="background: #1a1a1a; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">config/config.php</code></li>
                        <li>Configura el servidor SMTP para envío de emails</li>
                        <li>Elimina o protege la carpeta <code style="background: #1a1a1a; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">install/</code></li>
                        <li>Crea tu primera cuenta de usuario</li>
                    </ol>
                </div>

                <a href="../public/register.php" class="btn btn-block">
                    Ir a Registro →
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
