<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/Thumbnailer.php';

use App\Auth;
use App\DB;
use function App\uuid;
use function App\validateUserText;
use App\Thumbnailer;

Auth::startSession();

function renderUploadForm(string $csrf, string $albumUuid = 'default', ?string $albumName = null, array $fieldErrors = [], ?string $formError = null, string $description = ''): void
{
    require __DIR__ . '/templates/header.php';
?>
    <section class="page-panel p-4 p-md-5 mb-3">
        <h2>Upload Photo<?php if ($albumName): ?> to <strong><?= htmlspecialchars($albumName) ?></strong><?php endif; ?></h2>
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="row g-3" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="album" value="<?= htmlspecialchars($albumUuid) ?>">
            <div class="col-12">
                <input class="form-control<?= isset($fieldErrors['photo']) ? ' is-invalid' : '' ?>" type="file" name="photo" required>
                <?php if (isset($fieldErrors['photo'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['photo']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <input class="form-control<?= isset($fieldErrors['description']) ? ' is-invalid' : '' ?>" name="description" placeholder="Caption (optional)" value="<?= htmlspecialchars($description) ?>">
                <?php if (isset($fieldErrors['description'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['description']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Upload</button>
                <a class="btn btn-secondary" href="/album.php?uuid=<?= urlencode($albumUuid) ?>">Back</a>
            </div>
        </form>
    </section>
<?php
    require __DIR__ . '/templates/footer.php';
}

function renderUploadError(string $message, int $status = 400, string $albumUuid = 'default'): void
{
    http_response_code($status);
    require __DIR__ . '/templates/header.php';
    echo '<section class="page-panel p-4 p-md-5 mb-3">';
    echo '<p class="alert alert-danger">' . htmlspecialchars($message) . '</p>';
    echo '<p><a class="btn btn-secondary" href="/upload.php?album=' . urlencode($albumUuid) . '">Back to upload</a></p>';
    echo '</section>';
    require __DIR__ . '/templates/footer.php';
}

// allow optional album via query/post for redirect/back links
$album_uuid = (string)($_GET['album'] ?? 'default');
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

    renderUploadForm($csrf, $album_uuid, $album_name);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = Auth::csrfToken();
    $postedAlbumUuid = (string)($_POST['album'] ?? 'default');
    $postedDescription = trim((string)($_POST['description'] ?? ''));

    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        renderUploadForm($csrf, $postedAlbumUuid, null, [], 'Your session expired. Please retry the upload.', $postedDescription);
        exit;
    }

    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        renderUploadForm($csrf, $postedAlbumUuid, null, ['photo' => 'No file was uploaded. Please choose an image and try again.'], null, $postedDescription);
        exit;
    }

    $descriptionError = validateUserText($postedDescription, 500, 'Caption');
    if ($descriptionError !== null) {
        http_response_code(400);
        renderUploadForm($csrf, $postedAlbumUuid, null, ['description' => $descriptionError], null, $postedDescription);
        exit;
    }

    $tmp = $_FILES['photo']['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if (strpos($mime, 'image/') !== 0) {
        http_response_code(400);
        renderUploadForm($csrf, $postedAlbumUuid, null, ['photo' => 'Uploaded file is not an image.'], null, $postedDescription);
        exit;
    }

    $size = filesize($tmp);
    if ($size > 10_000_000) { // 10MB limit
        http_response_code(400);
        renderUploadForm($csrf, $postedAlbumUuid, null, ['photo' => 'File too large. Maximum allowed size is 10MB.'], null, $postedDescription);
        exit;
    }

    $info = getimagesize($tmp);
    if ($info === false) {
        http_response_code(400);
        renderUploadForm($csrf, $postedAlbumUuid, null, ['photo' => 'Invalid image file.'], null, $postedDescription);
        exit;
    }
    [$width, $height] = [$info[0], $info[1]];

    // determine user and album; use session if logged in
    $user_uuid = 'anon';
    $user_id = null;
    $requested_album_uuid = $_POST['album'] ?? null;
    if ($requested_album_uuid === 'default') {
        $requested_album_uuid = null;
    }
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
                    renderUploadError('Forbidden: album belongs to another user.', 403, (string)$requested_album_uuid);
                    exit;
                }
                renderUploadError('Album not found.', 404, (string)$requested_album_uuid);
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
        // anonymous uploads only target the default anon album
        if ($requested_album_uuid !== null) {
            renderUploadError('Login required to upload to that album.', 403, $album_uuid);
            exit;
        }

        $pdo = DB::getConnection();

        // Ensure anon user exists so photos.user_id FK is valid.
        $stmt = $pdo->prepare('SELECT id, uuid FROM users WHERE email = ? LIMIT 1');
        $stmt->execute(['anon@local']);
        $anonUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$anonUser) {
            $anonUserUuid = uuid();
            $anonPwd = Auth::hashPassword(bin2hex(random_bytes(16)));
            $insertUser = $pdo->prepare('INSERT INTO users (uuid, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $insertUser->execute([$anonUserUuid, 'anon@local', $anonPwd, 'user']);
            $user_id = (int)$pdo->lastInsertId();
            $user_uuid = $anonUserUuid;
        } else {
            $user_id = (int)$anonUser['id'];
            $user_uuid = (string)$anonUser['uuid'];
        }

        // Ensure anon default album exists so photos.album_id FK is valid.
        $stmt = $pdo->prepare('SELECT id, uuid FROM albums WHERE user_id = ? AND name = ? LIMIT 1');
        $stmt->execute([$user_id, 'default']);
        $anonAlbum = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$anonAlbum) {
            $anonAlbumUuid = 'default';
            $insertAlbum = $pdo->prepare('INSERT INTO albums (uuid, user_id, name) VALUES (?, ?, ?)');
            try {
                $insertAlbum->execute([$anonAlbumUuid, $user_id, 'default']);
                $album_id = (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                // If "default" uuid already exists globally, retry with random UUID.
                $anonAlbumUuid = uuid();
                $insertAlbum->execute([$anonAlbumUuid, $user_id, 'default']);
                $album_id = (int)$pdo->lastInsertId();
            }
            $album_uuid = $anonAlbumUuid;
        } else {
            $album_id = (int)$anonAlbum['id'];
            $album_uuid = (string)$anonAlbum['uuid'];
        }
    }
    $u = uuid();
    $storageDir = __DIR__ . '/storage/uploads/' . $user_uuid . '/' . $album_uuid;
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
    $dest = $storageDir . '/' . $u;
    if (!move_uploaded_file($tmp, $dest)) {
        renderUploadError('Failed to store uploaded file.', 500, $album_uuid);
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

    $pdo = DB::getConnection();
    $stmt = $pdo->prepare('INSERT INTO photos (uuid, album_id, user_id, file_path, original_name, mime, size_bytes, width, height, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$u, $album_id, $user_id, $dest, $_FILES['photo']['name'], $mime, $size, $width, $height, $description]);

    // after successful upload, redirect back to the album view
    header('Location: /album.php?uuid=' . urlencode($album_uuid));
    exit;
}
