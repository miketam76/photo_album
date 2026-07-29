<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';

use App\Auth;
use App\DB;
use function App\validate_signed_sig;

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

// Allow signed URL parameters for public access
$photoUuid = $_GET['photo'] ?? null;
$size = $_GET['size'] ?? 'original';
$sig = $_GET['sig'] ?? null;
$exp = $_GET['exp'] ?? null;
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
// If a signed URL is provided and valid, allow access. Otherwise require session auth.
$allowed = false;
if ($sig !== null && $exp !== null) {
    if (validate_signed_sig((string)$photoUuid, (string)$size, (string)$exp, (string)$sig)) {
        $allowed = true;
    }
}
if (!$allowed) {
    if ($currentUser === null) {
        header('Location: /login.php');
        exit;
    }
    if (!($currentUser['role'] === 'admin' || (int)$currentUser['id'] === (int)$row['owner_id'])) {
        renderImageError('You do not have permission to view this photo.', 403);
        exit;
    }
}

// determine file path
// Primary: respect stored path and private cache. If the stored path is missing (moved installs),
// perform a non-destructive fallback search under known storage locations.
$stored = (string)($row['file_path'] ?? '');
if ($size === 'original') {
    $file = $stored;
} else {
    $base = basename($stored);
    $file = __DIR__ . '/storage/private/cache/' . $row['user_uuid'] . '/' . $row['album_uuid'] . '/' . $size . '/' . $base . '.webp';
    if (!is_file($file)) {
        // fallback to original if cached size is missing
        $file = $stored;
    }
}

// If the resolved file doesn't exist on disk, try non-destructive fallbacks:
if (!is_file($file)) {
    $base = basename($stored);
    // 1) check private uploads in this workspace
    $cand = __DIR__ . '/storage/private/uploads/' . $row['user_uuid'] . '/' . $row['album_uuid'] . '/' . $base;
    if (is_file($cand)) {
        $file = $cand;
    }
}

if (!is_file($file)) {
    // 2) check public uploads directory in this workspace
    $cand2 = __DIR__ . '/storage/uploads/' . $row['user_uuid'] . '/' . $row['album_uuid'] . '/' . $base;
    if (is_file($cand2)) {
        $file = $cand2;
    }
}

if (!is_file($file)) {
    // 3) last resort: scan storage/uploads tree for matching basename (non-destructive but potentially expensive)
    $found = null;
    $searchRoot = __DIR__ . '/storage/uploads';
    if (is_dir($searchRoot)) {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($searchRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getBasename() === $base) {
                $found = $f->getPathname();
                break;
            }
        }
    }
    if ($found !== null && is_file($found)) {
        $file = $found;
    }
}

if (!is_file($file)) {
    renderImageError('Image file not found on disk.', 404);
    exit;
}

$mime = mime_content_type($file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
// Signed URL requests should use short caches; owner/admin requests can be cached longer.
if ($sig !== null) {
    header('Cache-Control: public, max-age=300');
} else {
    header('Cache-Control: public, max-age=31536000');
}
readfile($file);
exit;
