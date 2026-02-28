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
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            throw new \RuntimeException('Failed to create thumbnail destination directory: ' . $destDir);
        }

        if (!file_exists($srcPath) || !is_readable($srcPath)) {
            throw new \RuntimeException('Source image not found or not readable: ' . $srcPath);
        }

        $result = [];

        $imagickClass = 'Imagick';
        if (extension_loaded('imagick') && class_exists($imagickClass)) {
            try {
                $image = new $imagickClass($srcPath);
                if (method_exists($image, 'autoOrient')) {
                    $image->autoOrient();
                }

                $srgbColorspace = null;
                if (defined($imagickClass . '::COLORSPACE_SRGB')) {
                    $srgbColorspace = constant($imagickClass . '::COLORSPACE_SRGB');
                }
                if ($srgbColorspace !== null) {
                    $image->setImageColorspace($srgbColorspace);
                }

                $image->stripImage();

                $origW = $image->getImageWidth();

                foreach ($sizes as $label => $width) {
                    // avoid upscaling
                    $useWidth = min((int)$width, $origW);
                    $useWidth = max(1, $useWidth);
                    $thumb = clone $image;
                    $thumb->thumbnailImage($useWidth, 0);
                    $outDir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $label;
                    if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
                        throw new \RuntimeException('Failed to create thumbnail subdirectory: ' . $outDir);
                    }
                    $outPath = $outDir . DIRECTORY_SEPARATOR . pathinfo($srcPath, PATHINFO_FILENAME) . '.webp';
                    $thumb->setImageFormat('webp');
                    $thumb->setImageCompressionQuality(85);
                    if (!$thumb->writeImage($outPath)) {
                        throw new \RuntimeException('Failed to write thumbnail: ' . $outPath);
                    }
                    $thumb->clear();
                    $thumb->destroy();
                    $result[$label] = $outPath;
                }

                $image->clear();
                $image->destroy();
                return $result;
            } catch (\Throwable $e) {
                // If Imagick fails for any reason, fall back to GD below.
                if (isset($image) && is_object($image) && method_exists($image, 'clear') && method_exists($image, 'destroy')) {
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
        if (
            !function_exists('imagecreatefromstring') ||
            !function_exists('imagecreatetruecolor') ||
            !function_exists('imagecopyresampled') ||
            !function_exists('imagewebp')
        ) {
            throw new \RuntimeException('No image library available (Imagick or GD) or WebP support missing.');
        }

        $data = file_get_contents($srcPath);
        if ($data === false) {
            throw new \RuntimeException('Failed to read source image data: ' . $srcPath);
        }

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
                    $rotated = imagerotate($srcImg, 180, 0);
                    if ($rotated !== false) {
                        imagedestroy($srcImg);
                        $srcImg = $rotated;
                    }
                    break;
                case 6:
                    $rotated = imagerotate($srcImg, -90, 0);
                    if ($rotated !== false) {
                        imagedestroy($srcImg);
                        $srcImg = $rotated;
                    }
                    break;
                case 8:
                    $rotated = imagerotate($srcImg, 90, 0);
                    if ($rotated !== false) {
                        imagedestroy($srcImg);
                        $srcImg = $rotated;
                    }
                    break;
            }
        }

        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($srcImg);
            throw new \RuntimeException('Invalid source image dimensions for GD processing.');
        }

        foreach ($sizes as $label => $width) {
            // prevent upscaling
            $scale = min(1.0, $width / $srcW);
            $newW = (int)max(1, ($srcW * $scale));
            $newH = (int)max(1, ($srcH * $scale));
            $dst = imagecreatetruecolor($newW, $newH);
            if ($dst === false) {
                imagedestroy($srcImg);
                throw new \RuntimeException('Failed to allocate GD target image for size: ' . $label);
            }

            if (!imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH)) {
                imagedestroy($dst);
                imagedestroy($srcImg);
                throw new \RuntimeException('Failed to resample image for size: ' . $label);
            }

            $outDir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $label;
            if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
                imagedestroy($dst);
                imagedestroy($srcImg);
                throw new \RuntimeException('Failed to create thumbnail subdirectory: ' . $outDir);
            }

            $outPath = $outDir . DIRECTORY_SEPARATOR . pathinfo($srcPath, PATHINFO_FILENAME) . '.webp';
            if (!imagewebp($dst, $outPath, 85)) {
                imagedestroy($dst);
                imagedestroy($srcImg);
                throw new \RuntimeException('Failed to write thumbnail: ' . $outPath);
            }

            imagedestroy($dst);
            $result[$label] = $outPath;
        }
        imagedestroy($srcImg);
        return $result;
    }
}
