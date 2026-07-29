<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/Thumbnailer.php';

use App\DB;
use App\Thumbnailer;

$pdo = DB::getConnection();
$stmt = $pdo->prepare('SELECT p.uuid, p.file_path, u.uuid AS user_uuid, a.uuid AS album_uuid FROM photos p JOIN users u ON p.user_id = u.id JOIN albums a ON p.album_id = a.id');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$root = dirname(__DIR__);
$cacheRoot = $root . '/storage/private/cache';
$missing = [];

foreach ($rows as $r) {
    $uuid = $r['uuid'];
    $file = $r['file_path'];
    $userUuid = $r['user_uuid'];
    $albumUuid = $r['album_uuid'];
    $base = basename($file);
    $thumb = $cacheRoot . '/' . $userUuid . '/' . $albumUuid . '/thumb/' . $base . '.webp';
    if (!is_file($thumb)) {
        $missing[] = [$uuid, $file, $thumb];
    }
}

if (count($missing) === 0) {
    echo "No missing thumbnails.\n";
    exit(0);
}

foreach ($missing as [$uuid, $file, $thumb]) {
    echo "Generating for {$uuid} -> {$thumb}\n";
    $cacheDir = dirname($thumb);
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    try {
        Thumbnailer::generate($file, dirname($thumb));
        echo "Generated thumbnail for {$uuid}\n";
    } catch (Exception $e) {
        echo "Failed to generate for {$uuid}: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
