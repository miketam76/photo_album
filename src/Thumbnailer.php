<?php
declare(strict_types=1);

namespace App;

use Imagick;

final class Thumbnailer
{
    /**
     * Generate thumbnails for given image path.
     * Returns array of size => path
     */
    public static function generate(string $srcPath, string $destDir, array $sizes = [
        'large' => 1200,
        'medium' => 800,
        'thumb' => 320
    ]): array
    {
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $result = [];

        if (extension_loaded('imagick')) {
            $image = new Imagick($srcPath);
            $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $image->stripImage();

            foreach ($sizes as $label => $width) {
                $thumb = clone $image;
                $thumb->thumbnailImage((int)$width, 0);
                $outDir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $label;
                if (!is_dir($outDir)) {
                    mkdir($outDir, 0755, true);
                }
                $outPath = $outDir . DIRECTORY_SEPARATOR . basename($srcPath) . '.jpg';
                $thumb->setImageFormat('jpeg');
                $thumb->setImageCompressionQuality(85);
                $thumb->writeImage($outPath);
                $thumb->clear();
                $thumb->destroy();
                $result[$label] = $outPath;
            }

            $image->clear();
            $image->destroy();
            return $result;
        }

        // Fallback to GD if Imagick not available
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('No image library available (Imagick or GD).');
        }

        $data = file_get_contents($srcPath);
        $srcImg = imagecreatefromstring($data);
        if ($srcImg === false) {
            throw new \RuntimeException('Failed to create image from source for GD processing.');
        }
        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        foreach ($sizes as $label => $width) {
            $scale = $width / $srcW;
            $newW = (int)($srcW * $scale);
            $newH = (int)($srcH * $scale);
            if ($newW <= 0) $newW = 1;
            if ($newH <= 0) $newH = 1;
            $dst = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            $outDir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $label;
            if (!is_dir($outDir)) mkdir($outDir, 0755, true);
            $outPath = $outDir . DIRECTORY_SEPARATOR . basename($srcPath) . '.jpg';
            imagejpeg($dst, $outPath, 85);
            imagedestroy($dst);
            $result[$label] = $outPath;
        }
        imagedestroy($srcImg);
        return $result;
    }
}
