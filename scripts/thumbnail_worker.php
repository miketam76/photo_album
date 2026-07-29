<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Thumbnailer.php';

use App\Thumbnailer;

$root = dirname(__DIR__);
$queueDir = $root . '/storage/queue';
if (!is_dir($queueDir)) {
    echo "No queue directory found.\n";
    exit(0);
}

$files = glob($queueDir . '/*.json');
if (!$files) {
    echo "No jobs in queue.\n";
    exit(0);
}

foreach ($files as $jobFile) {
    echo "Processing job: $jobFile\n";
    $data = json_decode(file_get_contents($jobFile), true);
    if (!is_array($data) || empty($data['src']) || empty($data['cache'])) {
        echo "Invalid job, removing: $jobFile\n";
        @unlink($jobFile);
        continue;
    }
    $src = $data['src'];
    $cacheDir = $data['cache'];
    try {
        Thumbnailer::generate($src, $cacheDir);
        echo "Generated thumbnails for: $src\n";
    } catch (Exception $e) {
        echo "Thumbnail generation failed for $src: " . $e->getMessage() . "\n";
        // leave job file for retry or move to failed/ folder
    }
    // remove job file on success
    @unlink($jobFile);
}

echo "Queue processing complete.\n";
