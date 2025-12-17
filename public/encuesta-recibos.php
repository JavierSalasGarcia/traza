<?php
require_once dirname(__DIR__) . '/config/config.php';
// NO require_login() - esta página es PÚBLICA

$encuesta_model = new Encuesta();

// Obtener token
$token = isset($_GET['token']) ? $_GET['token'] : null;

if (!$token) {
    http_response_code(404);
    die('Token no proporcionado');
}

$encuesta = $encuesta_model->getByToken($token);

if (!$encuesta || !$encuesta['anonima']) {
    http_response_code(404);
    die('Encuesta no encontrada o no es anónima');
}

// Obtener todos los recibos
$recibos_data = $encuesta_model->getAllRecibos($encuesta['id']);

// Agrupar por recibo
$recibos = [];
foreach ($recibos_data as $row) {
    $recibo_code = $row['recibo'];

    if (!isset($recibos[$recibo_code])) {
        $recibos[$recibo_code] = [
            'recibo' => $recibo_code,
            'fecha_voto' => $row['fecha_voto'],
            'respuestas' => []
        ];
    }

    if ($row['texto_pregunta']) {
        $pregunta_texto = $row['texto_pregunta'];

        if (!isset($recibos[$recibo_code]['respuestas'][$pregunta_texto])) {
            $recibos[$recibo_code]['respuestas'][$pregunta_texto] = [
                'tipo' => $row['tipo'],
                'opciones' => [],
                'texto_respuesta' => null
            ];
        }

        if ($row['tipo'] == 'abierta') {
            $recibos[$recibo_code]['respuestas'][$pregunta_texto]['texto_respuesta'] = $row['texto_respuesta'];
        } else {
            if ($row['opcion_texto'] && !in_array($row['opcion_texto'], $recibos[$recibo_code]['respuestas'][$pregunta_texto]['opciones'])) {
                $recibos[$recibo_code]['respuestas'][$pregunta_texto]['opciones'][] = $row['opcion_texto'];
            }
        }
    }
}

// Ordenar por fecha (más recientes primero)
usort($recibos, function($a, $b) {
    return strtotime($b['fecha_voto']) - strtotime($a['fecha_voto']);
});

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="recibos_' . $encuesta['id'] . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Encabezados
    fputcsv($output, ['Recibo', 'Fecha Voto', 'Pregunta', 'Tipo', 'Respuesta']);

    foreach ($recibos as $recibo) {
        foreach ($recibo['respuestas'] as $pregunta => $respuesta_data) {
            $respuesta_texto = '';

            if ($respuesta_data['tipo'] == 'abierta') {
                $respuesta_texto = $respuesta_data['texto_respuesta'];
            } else {
                $respuesta_texto = implode(', ', $respuesta_data['opciones']);
            }

            fputcsv($output, [
                $recibo['recibo'],
                $recibo['fecha_voto'],
                $pregunta,
                $respuesta_data['tipo'],
                $respuesta_texto
            ]);
        }
    }

    fclose($output);
    exit;
}

// Exportar PDF (simple HTML to print)
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Recibos - <?= sanitize($encuesta['titulo']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            h1 { font-size: 18px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
            th { background-color: #f2f2f2; }
            @media print { button { display: none; } }
        </style>
    </head>
    <body>
        <h1>Recibos de Encuesta Anónima: <?= sanitize($encuesta['titulo']) ?></h1>
        <p>Fecha de generación: <?= date('d/m/Y H:i') ?></p>
        <p>Total de votos: <?= count($recibos) ?></p>

        <button onclick="window.print()">Imprimir / Guardar PDF</button>
        <button onclick="window.close()">Cerrar</button>

        <table>
            <thead>
                <tr>
                    <th>Recibo</th>
                    <th>Fecha</th>
                    <th>Pregunta</th>
                    <th>Respuesta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recibos as $recibo): ?>
                    <?php foreach ($recibo['respuestas'] as $pregunta => $respuesta_data): ?>
                        <tr>
                            <td><?= sanitize($recibo['recibo']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($recibo['fecha_voto'])) ?></td>
                            <td><?= sanitize($pregunta) ?></td>
                            <td>
                                <?php if ($respuesta_data['tipo'] == 'abierta'): ?>
                                    <?= sanitize($respuesta_data['texto_respuesta']) ?>
                                <?php else: ?>
                                    <?= sanitize(implode(', ', $respuesta_data['opciones'])) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibos - <?= sanitize($encuesta['titulo']) ?></title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../core/includes/pwa-head.php'; ?>
</head>
<body>
    <!-- Navbar simplificado para página pública -->
    <nav class="navbar public-navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="<?= base_url('public/dashboard.php') ?>">
                    <i class="fas fa-graduation-cap"></i>
                    <span class="logo-text">TrazaFI</span>
                </a>
            </div>
            <div class="nav-title">
                <i class="fas fa-receipt"></i>
                <span>Recibos Públicos - Encuesta Anónima</span>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-receipt"></i> Recibos de Verificación</h1>
                    <p class="encuesta-title"><?= sanitize($encuesta['titulo']) ?></p>
                    <?php if ($encuesta['descripcion']): ?>
                        <p class="encuesta-desc"><?= sanitize($encuesta['descripcion']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-banner">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Página Pública de Recibos</strong>
                    <p>Esta es una página pública que muestra TODOS los recibos de esta encuesta anónima para efectos de transparencia y auditoría.
                    Cualquier persona puede ver esta página para verificar que su voto fue contado correctamente.</p>
                </div>
            </div>

            <div class="stats-bar">
                <div class="stat-item">
                    <i class="fas fa-chart-pie"></i>
                    <div>
                        <strong><?= count($recibos) ?></strong>
                        <span>Total de Votos</span>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <strong><?= sanitize($encuesta['nombre'] . ' ' . $encuesta['apellidos']) ?></strong>
                        <span>Creada por</span>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-calendar"></i>
                    <div>
                        <strong><?= date('d/m/Y', strtotime($encuesta['fecha_inicio'])) ?></strong>
                        <span>Fecha de Inicio</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Lista Completa de Recibos</h2>
                    <div class="header-actions">
                        <a href="?token=<?= $token ?>&export=csv" class="btn btn-sm btn-secondary">
                            <i class="fas fa-file-csv"></i> Exportar CSV
                        </a>
                        <a href="?token=<?= $token ?>&export=pdf" class="btn btn-sm btn-secondary" target="_blank">
                            <i class="fas fa-file-pdf"></i> Exportar PDF
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="search-info">
                        <i class="fas fa-search"></i>
                        <p>Usa Ctrl+F (Cmd+F en Mac) para buscar tu recibo en esta página</p>
                    </div>

                    <?php if (empty($recibos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No hay votos registrados aún</p>
                        </div>
                    <?php else: ?>
                        <div class="recibos-table-container">
                            <table class="recibos-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Recibo</th>
                                        <th>Fecha de Voto</th>
                                        <th>Respuestas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recibos as $index => $recibo): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td class="recibo-code"><?= sanitize($recibo['recibo']) ?></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($recibo['fecha_voto'])) ?></td>
                                            <td class="respuestas-cell">
                                                <?php if (empty($recibo['respuestas'])): ?>
                                                    <em class="empty-text">Sin respuestas</em>
                                                <?php else: ?>
                                                    <div class="respuestas-list">
                                                        <?php foreach ($recibo['respuestas'] as $pregunta => $respuesta_data): ?>
                                                            <div class="respuesta-item">
                                                                <strong class="pregunta"><?= sanitize($pregunta) ?></strong>
                                                                <div class="respuesta">
                                                                    <?php if ($respuesta_data['tipo'] == 'abierta'): ?>
                                                                        <span class="respuesta-abierta"><?= sanitize($respuesta_data['texto_respuesta']) ?></span>
                                                                    <?php else: ?>
                                                                        <?php foreach ($respuesta_data['opciones'] as $opcion): ?>
                                                                            <span class="badge badge-opcion"><?= sanitize($opcion) ?></span>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="footer-info">
                <p><i class="fas fa-shield-alt"></i> Esta página es pública y accesible sin autenticación para garantizar la transparencia del proceso de votación.</p>
                <p><i class="fas fa-lock"></i> Los recibos NO están asociados a identidades de usuarios, garantizando el anonimato.</p>
            </div>
        </div>
    </main>

    <style>
        .public-navbar {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.9), rgba(26, 26, 46, 0.9));
            border-bottom: 2px solid var(--color-primary);
        }

        .nav-title {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-primary);
            font-weight: var(--font-weight-semibold);
        }

        .encuesta-title {
            font-size: var(--font-size-xl);
            color: var(--color-primary);
            margin-top: var(--space-2);
        }

        .encuesta-desc {
            color: var(--color-text-muted);
            margin-top: var(--space-2);
        }

        .info-banner {
            background: rgba(0, 153, 255, 0.1);
            border: 1px solid rgba(0, 153, 255, 0.3);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-6);
            display: flex;
            gap: var(--space-3);
        }

        .info-banner i {
            font-size: 32px;
            color: var(--color-primary);
            flex-shrink: 0;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .stat-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            display: flex;
            gap: var(--space-3);
            align-items: center;
        }

        .stat-item i {
            font-size: 32px;
            color: var(--color-primary);
        }

        .stat-item strong {
            display: block;
            font-size: var(--font-size-lg);
            margin-bottom: 4px;
        }

        .stat-item span {
            color: var(--color-text-muted);
            font-size: var(--font-size-sm);
        }

        .header-actions {
            display: flex;
            gap: var(--space-2);
        }

        .search-info {
            background: rgba(255, 170, 0, 0.1);
            border: 1px solid rgba(255, 170, 0, 0.3);
            border-radius: var(--radius-lg);
            padding: var(--space-3);
            margin-bottom: var(--space-4);
            display: flex;
            gap: var(--space-2);
            align-items: center;
        }

        .search-info i {
            color: var(--color-warning);
        }

        .recibos-table-container {
            overflow-x: auto;
        }

        .recibos-table {
            width: 100%;
            border-collapse: collapse;
        }

        .recibos-table th,
        .recibos-table td {
            padding: var(--space-3);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .recibos-table th {
            background: rgba(0, 153, 255, 0.1);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            position: sticky;
            top: 0;
        }

        .recibos-table tbody tr:hover {
            background: rgba(0, 153, 255, 0.05);
        }

        .recibo-code {
            font-family: 'Courier New', monospace;
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            font-size: var(--font-size-lg);
        }

        .respuestas-cell {
            max-width: 600px;
        }

        .respuestas-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .respuesta-item {
            padding: var(--space-2);
            background: rgba(0, 153, 255, 0.05);
            border-radius: var(--radius-md);
        }

        .pregunta {
            display: block;
            color: var(--color-text);
            margin-bottom: var(--space-2);
            font-size: var(--font-size-sm);
        }

        .respuesta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .respuesta-abierta {
            font-style: italic;
            color: var(--color-text-muted);
        }

        .badge-opcion {
            background: rgba(0, 153, 255, 0.2);
            color: var(--color-primary);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
        }

        .footer-info {
            margin-top: var(--space-6);
            padding: var(--space-4);
            background: rgba(0, 255, 136, 0.05);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: var(--radius-lg);
        }

        .footer-info p {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-2);
            color: var(--color-text-muted);
        }

        .footer-info i {
            color: var(--color-success);
        }

        @media (max-width: 768px) {
            .nav-title span {
                display: none;
            }

            .header-actions {
                flex-direction: column;
            }

            .recibos-table {
                font-size: var(--font-size-sm);
            }

            .recibo-code {
                font-size: var(--font-size-sm);
            }
        }
    </style>
</body>
</html>
