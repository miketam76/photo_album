<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/Thumbnailer.php';

use App\Auth;
use App\DB;
use function App\uuid;
use App\Thumbnailer;

Auth::startSession();
// allow optional album via query/post for redirect/back links
$album_uuid = $_REQUEST['album'] ?? 'default';
$album_name = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $csrf = Auth::csrfToken();
        
        // Validate album if requested by UUID (for logged-in users)
        if ($album_uuid !== 'default' && !empty($_SESSION['user']['id'])) {
            $pdo = DB::getConnection();
            $stmt = $pdo->prepare('SELECT id, name FROM albums WHERE uuid = ? AND user_id = ?');
            $stmt->execute([$album_uuid, (int)$_SESSION['user']['id']]);
            $albumRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$albumRow) {
                http_response_code(404);
                require __DIR__ . '/templates/header.php';
                echo '<p class="alert alert-danger">Album not found or you do not have access.</p>';
                require __DIR__ . '/templates/footer.php';
                exit;
            }
            $album_name = $albumRow['name'];
        }
        
        require __DIR__ . '/templates/header.php';
        ?>
        <h2>Upload Photo<?php if ($album_name): ?> to <strong><?= htmlspecialchars($album_name) ?></strong><?php endif; ?></h2>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="album" value="<?= htmlspecialchars($album_uuid) ?>">
            <div class="col-12">
                <input class="form-control" type="file" name="photo" required>
            </div>
            <div class="col-12">
                <input class="form-control" name="description" placeholder="Caption (optional)">
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Upload</button>
                <a class="btn btn-secondary" href="/album.php?uuid=<?= urlencode($album_uuid) ?>">Back</a>
            </div>
        </form>
        <?php
        require __DIR__ . '/templates/footer.php';
        exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        exit;
    }

    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo 'No file uploaded';
        exit;
    }

    $tmp = $_FILES['photo']['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if (strpos($mime, 'image/') !== 0) {
        http_response_code(400);
        echo 'Uploaded file is not an image';
        exit;
    }

    $size = filesize($tmp);
    if ($size > 10_000_000) { // 10MB limit
        http_response_code(400);
        echo 'File too large';
        exit;
    }

    $info = getimagesize($tmp);
    if ($info === false) {
        http_response_code(400);
        echo 'Not a valid image';
        exit;
    }
    [$width, $height] = [$info[0], $info[1]];

    // determine user and album; use session if logged in
    $user_uuid = 'anon';
    $user_id = null;
    $requested_album_uuid = $_REQUEST['album'] ?? null;
    $album_uuid = $requested_album_uuid ?? 'default';
    $album_id = null;
    if (!empty($_SESSION['user']['id'])) {
        $user_id = (int)$_SESSION['user']['id'];
        $pdo = DB::getConnection();
        $stmt = $pdo->prepare('SELECT uuid FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user_uuid = $stmt->fetchColumn() ?: $user_uuid;
        // if an album uuid was requested (e.g. coming from album page), try to resolve it
        if ($requested_album_uuid) {
            $stmt = $pdo->prepare('SELECT id, uuid, user_id FROM albums WHERE uuid = ? AND user_id = ?');
            $stmt->execute([$requested_album_uuid, $user_id]);
            $albumRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($albumRow) {
                $album_id = (int)$albumRow['id'];
                $album_uuid = $albumRow['uuid'];
            } else {
                // Check if album exists but belongs to someone else
                $stmt2 = $pdo->prepare('SELECT id, uuid, user_id FROM albums WHERE uuid = ?');
                $stmt2->execute([$requested_album_uuid]);
                $other = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($other) {
                    http_response_code(403);
                    echo 'Forbidden: album belongs to another user';
                    exit;
                }
                http_response_code(404);
                echo 'Album not found';
                exit;
            }
        } else {
            // ensure default album exists for user
            $stmt = $pdo->prepare('SELECT id, uuid FROM albums WHERE user_id = ? AND name = ?');
            $stmt->execute([$user_id, 'default']);
            $albumRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($albumRow) {
                $album_id = (int)$albumRow['id'];
                $album_uuid = $albumRow['uuid'];
            } else {
                $newAlbumUuid = uuid();
                $ins = $pdo->prepare('INSERT INTO albums (uuid, user_id, name) VALUES (?, ?, ?)');
                $ins->execute([$newAlbumUuid, $user_id, 'default']);
                $album_id = (int)$pdo->lastInsertId();
                $album_uuid = $newAlbumUuid;
            }
        }
    } else {
        // anonymous uploads: do not support album-specific uploads
        if ($requested_album_uuid) {
            http_response_code(403);
            echo 'Login required to upload to that album';
            exit;
        }
        $album_id = 1; // fallback to default album id
    }
    $u = uuid();
    $storageDir = __DIR__ . '/storage/uploads/' . $user_uuid . '/' . $album_uuid;
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
    $dest = $storageDir . '/' . $u;
    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        echo 'Failed to move file';
        exit;
    }

    // generate thumbnails
    $thumbs = [];
    try {
        $thumbs = Thumbnailer::generate($dest, __DIR__ . '/storage/cache/' . $user_uuid . '/' . $album_uuid);
    } catch (Exception $e) {
        // continue; thumbnails are optional
    }

    // persist minimal metadata to DB (include width/height and optional description)
    $description = trim((string)($_POST['description'] ?? '')) ?: null;
    // prefer album from POST if provided
    $album_uuid = $_POST['album'] ?? $album_uuid;

    $pdo = DB::getConnection();
    $stmt = $pdo->prepare('INSERT INTO photos (uuid, album_id, user_id, file_path, original_name, mime, size_bytes, width, height, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$u, $album_id, $user_id ?? 0, $dest, $_FILES['photo']['name'], $mime, $size, $width, $height, $description]);

    // after successful upload, redirect back to the album view
    header('Location: /album.php?uuid=' . urlencode($album_uuid));
    exit;
}
