<?php
/**
 * Generador de iconos PWA
 * Ejecuta este script una vez para generar todos los iconos necesarios
 */

// Tamaños de iconos requeridos
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Colores del diseño Starlink
$bgColor = [0, 0, 0]; // Negro
$primaryColor = [0, 153, 255]; // Azul primario

foreach ($sizes as $size) {
    // Crear imagen
    $image = imagecreatetruecolor($size, $size);

    // Colores
    $bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
    $primary = imagecolorallocate($image, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $white = imagecolorallocate($image, 255, 255, 255);

    // Fondo negro
    imagefill($image, 0, 0, $bg);

    // Círculo azul en el centro
    $centerX = $size / 2;
    $centerY = $size / 2;
    $radius = $size * 0.35;

    imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $primary);

    // Borde blanco
    imagesetthickness($image, max(2, $size / 64));
    imageellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $white);

    // Texto "TF" (TrazaFI) en el centro
    $fontSize = $size / 5;
    $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'; // Font común en servidores Linux

    // Si no existe el font, usar el texto básico
    if (file_exists($fontFile)) {
        $text = "TF";
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);
        $x = $centerX - ($textWidth / 2);
        $y = $centerY + ($textHeight / 2);

        imagettftext($image, $fontSize, 0, $x, $y, $white, $fontFile, $text);
    } else {
        // Fallback: usar imagestring si no hay TTF
        $text = "TF";
        $textWidth = imagefontwidth(5) * strlen($text);
        $textHeight = imagefontheight(5);
        $x = $centerX - ($textWidth / 2);
        $y = $centerY - ($textHeight / 2);

        imagestring($image, 5, $x, $y, $text, $white);
    }

    // Guardar imagen
    $filename = __DIR__ . "/icons/icon-{$size}x{$size}.png";
    imagepng($image, $filename);
    imagedestroy($image);

    echo "✓ Generado: icon-{$size}x{$size}.png\n";
}

// Generar screenshot placeholder
$screenshotWidth = 1280;
$screenshotHeight = 720;
$screenshot = imagecreatetruecolor($screenshotWidth, $screenshotHeight);

$bg = imagecolorallocate($screenshot, $bgColor[0], $bgColor[1], $bgColor[2]);
$primary = imagecolorallocate($screenshot, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
$white = imagecolorallocate($screenshot, 255, 255, 255);

imagefill($screenshot, 0, 0, $bg);

// Título grande
$fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
if (file_exists($fontFile)) {
    $title = "TrazaFI";
    $fontSize = 80;
    $bbox = imagettfbbox($fontSize, 0, $fontFile, $title);
    $textWidth = abs($bbox[4] - $bbox[0]);
    $x = ($screenshotWidth - $textWidth) / 2;
    $y = $screenshotHeight / 2 - 50;

    imagettftext($screenshot, $fontSize, 0, $x, $y, $primary, $fontFile, $title);

    // Subtítulo
    $subtitle = "Facultad de Ingenieria UAEMEX";
    $fontSize2 = 30;
    $bbox2 = imagettfbbox($fontSize2, 0, $fontFile, $subtitle);
    $textWidth2 = abs($bbox2[4] - $bbox2[0]);
    $x2 = ($screenshotWidth - $textWidth2) / 2;
    $y2 = $screenshotHeight / 2 + 50;

    imagettftext($screenshot, $fontSize2, 0, $x2, $y2, $white, $fontFile, $subtitle);
}

$screenshotFile = __DIR__ . "/screenshots/screenshot1.png";
imagepng($screenshot, $screenshotFile);
imagedestroy($screenshot);

echo "✓ Generado: screenshot1.png\n";
echo "\n¡Iconos PWA generados exitosamente!\n";
echo "Ahora puedes eliminar este archivo (generate-icons.php) si lo deseas.\n";
