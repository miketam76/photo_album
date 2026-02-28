<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

use App\Auth;
use App\DB;

Auth::startSession();

function renderImageError(string $message, int $status = 400): void
{
    http_response_code($status);
    require_once __DIR__ . '/templates/header.php';
    echo '<section class="page-panel p-4 p-md-5 mb-3">';
    echo '<p class="alert alert-danger">' . htmlspecialchars($message) . '</p>';
    echo '<p><a class="btn btn-secondary" href="/albums.php">Back to albums</a></p>';
    echo '</section>';
    require_once __DIR__ . '/templates/footer.php';
}

$photoUuid = $_GET['photo'] ?? null;
$size = $_GET['size'] ?? 'original';
if (!$photoUuid) {
    renderImageError('Missing photo id.', 400);
    exit;
}

$pdo = DB::getConnection();
$stmt = $pdo->prepare('SELECT p.*, a.uuid AS album_uuid, u.uuid AS user_uuid, u.id AS owner_id FROM photos p JOIN albums a ON p.album_id = a.id JOIN users u ON a.user_id = u.id WHERE p.uuid = ?');
$stmt->execute([$photoUuid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    renderImageError('Photo not found.', 404);
    exit;
}

// access control: only owner or admin can view for now
$currentUser = $_SESSION['user'] ?? null;
if ($currentUser === null) {
    header('Location: /login.php');
    exit;
}
if (!($currentUser['role'] === 'admin' || (int)$currentUser['id'] === (int)$row['owner_id'])) {
    renderImageError('You do not have permission to view this photo.', 403);
    exit;
}

// determine file path
if ($size === 'original') {
    $file = $row['file_path'];
} else {
    $base = basename($row['file_path']);
    $file = __DIR__ . '/storage/cache/' . $row['user_uuid'] . '/' . $row['album_uuid'] . '/' . $size . '/' . $base . '.webp';
    if (!is_file($file)) {
        $file = $row['file_path'];
    }
}

if (!is_file($file)) {
    renderImageError('Image file not found on disk.', 404);
    exit;
}

$mime = mime_content_type($file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000');
readfile($file);
exit;
