<?php
/**
 * Procesa una imagen: redimensiona, convierte a WebP y guarda en el destino.
 * @param string $tmpFile Ruta temporal del archivo subido
 * @param string $destDir Directorio destino (debe existir o se crea)
 * @param string $baseName Nombre base para el archivo (sin extensión)
 * @param int $maxWidth Ancho máximo
 * @param int $maxHeight Alto máximo
 * @param int $quality Calidad WebP (0-100)
 * @return string|false Nombre del archivo WebP generado, o false en error
 */
function procesarImagenWebP($tmpFile, $destDir, $baseName, $maxWidth = 1200, $maxHeight = 1200, $quality = 80) {
    if (!file_exists($destDir)) {
        mkdir($destDir, 0777, true);
    }
    $imageInfo = getimagesize($tmpFile);
    if (!$imageInfo) return false;

    // Cargar imagen según tipo
    switch ($imageInfo['mime']) {
        case 'image/jpeg': $src = imagecreatefromjpeg($tmpFile); break;
        case 'image/png':  $src = imagecreatefrompng($tmpFile); break;
        case 'image/gif':  $src = imagecreatefromgif($tmpFile); break;
        case 'image/webp': $src = imagecreatefromwebp($tmpFile); break;
        default: return false;
    }

    // Redimensionar si es necesario
    $ratio = min($maxWidth / $imageInfo[0], $maxHeight / $imageInfo[1]);
    $newW = $ratio < 1 ? intval($imageInfo[0] * $ratio) : $imageInfo[0];
    $newH = $ratio < 1 ? intval($imageInfo[1] * $ratio) : $imageInfo[1];
    $dst = imagecreatetruecolor($newW, $newH);
    // Preservar transparencia para PNG/GIF
    if ($imageInfo['mime'] === 'image/png' || $imageInfo['mime'] === 'image/gif') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $imageInfo[0], $imageInfo[1]);

    // Guardar como WebP
    $webpName = pathinfo($baseName, PATHINFO_FILENAME) . '.webp';
    $webpPath = rtrim($destDir, '/'). '/' . $webpName;
    $ok = imagewebp($dst, $webpPath, $quality);

    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? $webpName : false;
}