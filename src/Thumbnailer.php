<?php
declare(strict_types=1);

namespace App;

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

        if (!file_exists($srcPath) || !is_readable($srcPath)) {
            throw new \RuntimeException('Source image not found or not readable: ' . $srcPath);
        }

        $result = [];

        if (extension_loaded('imagick')) {
            try {
                $image = new \Imagick($srcPath);
                if (method_exists($image, 'autoOrient')) {
                    $image->autoOrient();
                }
                $image->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                $image->stripImage();

                $origW = $image->getImageWidth();

                foreach ($sizes as $label => $width) {
                    // avoid upscaling
                    $useWidth = min((int)$width, $origW);
                        $thumb = clone $image;
                    $thumb->thumbnailImage($useWidth, 0);
                    $outDir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $label;
                    if (!is_dir($outDir)) {
                        mkdir($outDir, 0755, true);
                    }
                    $outPath = $outDir . DIRECTORY_SEPARATOR . pathinfo($srcPath, PATHINFO_FILENAME) . '.jpg';
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
            } catch (\Throwable $e) {
                // If Imagick fails for any reason, fall back to GD below.
                if (isset($image) && $image instanceof \Imagick) {
                    try {
                        $image->clear();
                        $image->destroy();
                    } catch (\Throwable $_) {
                        // ignore
                    }
                }
            }
        }

        // Fallback to GD if Imagick not available or failed above
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('No image library available (Imagick or GD).');
        }

        $data = file_get_contents($srcPath);

        // Read EXIF orientation if available (before creating image)
        $orientation = null;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($srcPath);
            $orientation = $exif['Orientation'] ?? null;
        }

        $srcImg = imagecreatefromstring($data);
        if ($srcImg === false) {
            throw new \RuntimeException('Failed to create image from source for GD processing.');
        }

        // Apply EXIF orientation for GD
        if ($orientation) {
            switch ($orientation) {
                case 3:
                    $srcImg = imagerotate($srcImg, 180, 0);
                    break;
                case 6:
                    $srcImg = imagerotate($srcImg, -90, 0);
                    break;
                case 8:
                    $srcImg = imagerotate($srcImg, 90, 0);
                    break;
            }
        }

        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        foreach ($sizes as $label => $width) {
            // prevent upscaling
            $scale = min(1.0, $width / $srcW);
            $newW = (int)max(1, ($srcW * $scale));
            $newH = (int)max(1, ($srcH * $scale));
            $dst = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            $outDir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $label;
            if (!is_dir($outDir)) mkdir($outDir, 0755, true);
            $outPath = $outDir . DIRECTORY_SEPARATOR . pathinfo($srcPath, PATHINFO_FILENAME) . '.jpg';
            imagejpeg($dst, $outPath, 85);
            imagedestroy($dst);
            $result[$label] = $outPath;
        }
        imagedestroy($srcImg);
        return $result;
    }
}
