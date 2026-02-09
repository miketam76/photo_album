<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

use App\Auth;
use App\DB;

Auth::startSession();

$photoUuid = $_GET['photo'] ?? null;
$size = $_GET['size'] ?? 'original';
if (!$photoUuid) { http_response_code(400); echo 'Missing photo id'; exit; }

$pdo = DB::getConnection();
$stmt = $pdo->prepare('SELECT p.*, a.uuid AS album_uuid, u.uuid AS user_uuid, u.id AS owner_id FROM photos p JOIN albums a ON p.album_id = a.id JOIN users u ON a.user_id = u.id WHERE p.uuid = ?');
$stmt->execute([$photoUuid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo 'Not found'; exit; }

// access control: only owner or admin can view for now
$currentUser = $_SESSION['user'] ?? null;
if ($currentUser === null) {
    http_response_code(403); echo 'Login required'; exit;
}
if (!($currentUser['role'] === 'admin' || (int)$currentUser['id'] === (int)$row['owner_id'])) {
    http_response_code(403); echo 'Forbidden'; exit;
}

// determine file path
if ($size === 'original') {
    $file = $row['file_path'];
} else {
    $base = basename($row['file_path']);
    $file = __DIR__ . '/storage/cache/' . $row['user_uuid'] . '/' . $row['album_uuid'] . '/' . $size . '/' . $base . '.jpg';
    if (!is_file($file)) {
        $file = $row['file_path'];
    }
}

if (!is_file($file)) { http_response_code(404); echo 'File not found'; exit; }

$mime = mime_content_type($file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000');
readfile($file);
exit;
