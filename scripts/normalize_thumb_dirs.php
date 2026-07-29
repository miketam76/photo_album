<?php

declare(strict_types=1);
$root = dirname(__DIR__);
$cacheRoot = $root . '/storage/private/cache';

$it = new RecursiveDirectoryIterator($cacheRoot, RecursiveDirectoryIterator::SKIP_DOTS);
$ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
foreach ($ri as $fileinfo) {
    if ($fileinfo->isDir() && strtolower($fileinfo->getFilename()) === 'thumb') {
        $parent = $fileinfo->getPath();
        $nested = $fileinfo->getPathname() . DIRECTORY_SEPARATOR . 'thumb';
        if (is_dir($nested)) {
            echo "Normalizing: moving files from {$nested} to {$fileinfo->getPathname()}\n";
            $entries = new DirectoryIterator($nested);
            foreach ($entries as $e) {
                if ($e->isFile()) {
                    $src = $e->getPathname();
                    $dest = $fileinfo->getPathname() . DIRECTORY_SEPARATOR . $e->getFilename();
                    if (!file_exists($dest)) {
                        rename($src, $dest);
                        echo " - moved {$src} -> {$dest}\n";
                    } else {
                        unlink($src);
                    }
                }
            }
            // remove nested dir if empty
            @rmdir($nested);
        }
    }
}

echo "Normalization complete.\n";
