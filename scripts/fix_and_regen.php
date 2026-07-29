<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/Thumbnailer.php';

use App\DB;
use App\Thumbnailer;

$pdo = DB::getConnection();
$stmt = $pdo->prepare('SELECT p.*, u.uuid AS user_uuid, a.uuid AS album_uuid FROM photos p JOIN users u ON p.user_id = u.id JOIN albums a ON p.album_id = a.id');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$backup = [];
$updates = [];
$generated = [];

$root = dirname(__DIR__);
$uploadsRoot = $root . '/storage/uploads';
$cacheRoot = $root . '/storage/private/cache';

foreach ($rows as $r) {
    $uuid = $r['uuid'];
    $file = $r['file_path'];
    if (is_file($file)) continue;

    // search for file by basename in storage/uploads
    $found = null;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsRoot));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        if ($f->getBasename() === $uuid) {
            $found = $f->getPathname();
            break;
        }
    }

    $backup[] = $r;
    if ($found) {
        echo "Found file for {$uuid}: {$found}\n";
        $newpath = $found;
        $uStmt = $pdo->prepare('UPDATE photos SET file_path = ? WHERE uuid = ?');
        $uStmt->execute([$newpath, $uuid]);
        $updates[] = [$uuid, $newpath];

        // regenerate thumbnails into private cache
        $userUuid = $r['user_uuid'] ?? 'unknown';
        $albumUuid = $r['album_uuid'] ?? 'unknown';
        $cacheDir = $cacheRoot . '/' . $userUuid . '/' . $albumUuid;
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        try {
            Thumbnailer::generate($newpath, $cacheDir);
            $generated[] = $uuid;
            echo "Generated thumbnails for {$uuid}\n";
        } catch (Exception $e) {
            echo "Thumbnail generation failed for {$uuid}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No file found for {$uuid}, leaving row unchanged.\n";
    }
}

$bakFile = __DIR__ . '/backup_photos_before_repair_' . time() . '.json';
file_put_contents($bakFile, json_encode($backup, JSON_PRETTY_PRINT));

echo "Backed up " . count($backup) . " rows to {$bakFile}\n";
if (count($updates) > 0) {
    echo "Updated " . count($updates) . " file_path rows.\n";
}
if (count($generated) > 0) {
    echo "Regenerated thumbnails for " . count($generated) . " photos.\n";
}

echo "Done.\n";
