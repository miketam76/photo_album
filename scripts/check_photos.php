<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

use App\DB;

$pdo = null;
try {
    $pdo = DB::getConnection();
} catch (Throwable $e) {
    echo "ERROR: Could not connect to DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$stmt = $pdo->prepare('SELECT p.uuid, p.file_path, p.original_name, p.user_id, u.uuid AS user_uuid, a.uuid AS album_uuid, p.width, p.height FROM photos p JOIN users u ON p.user_id = u.id JOIN albums a ON p.album_id = a.id ORDER BY p.uploaded_at DESC');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
$missingOriginal = [];
$missingThumb = [];

foreach ($rows as $r) {
    $uuid = $r['uuid'];
    $file = $r['file_path'];
    $userUuid = $r['user_uuid'] ?? 'unknown';
    $albumUuid = $r['album_uuid'] ?? 'unknown';
    if (!is_file($file)) {
        $missingOriginal[] = [$uuid, $file, $r['original_name'] ?? ''];
    }
    $base = basename((string)$file);
    $thumb = __DIR__ . '/../storage/private/cache/' . $userUuid . '/' . $albumUuid . '/thumb/' . $base . '.webp';
    if (!is_file($thumb)) {
        $missingThumb[] = [$uuid, $thumb];
    }
}

echo "Total photos: $total\n";
echo "Missing original files: " . count($missingOriginal) . "\n";
if (count($missingOriginal) > 0) {
    foreach ($missingOriginal as $m) {
        echo " - {$m[0]} => {$m[1]} (orig name: {$m[2]})\n";
    }
}
echo "Missing thumbnail files: " . count($missingThumb) . "\n";
if (count($missingThumb) > 0) {
    foreach ($missingThumb as $m) {
        echo " - {$m[0]} => {$m[1]}\n";
    }
}

exit(0);
