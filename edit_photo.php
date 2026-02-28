<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';

use App\Auth;
use App\DB;
use function App\validateUserText;

Auth::startSession();

function renderEditError(string $message, int $status = 400): void
{
    http_response_code($status);
    require_once __DIR__ . '/templates/header.php';
    echo '<section class="page-panel p-4 p-md-5 mb-3">';
    echo '<p class="alert alert-danger">' . htmlspecialchars($message) . '</p>';
    echo '<p><a class="btn btn-secondary" href="/albums.php">Back to albums</a></p>';
    echo '</section>';
    require_once __DIR__ . '/templates/footer.php';
}

if (empty($_GET['album']) || empty($_GET['photo'])) {
    renderEditError('Missing album or photo id.', 400);
    exit;
}

$albumUuid = (string)$_GET['album'];
$photoUuid = (string)$_GET['photo'];

$currentUser = $_SESSION['user'] ?? null;
if ($currentUser === null) {
    header('Location: /login.php');
    exit;
}

$pdo = DB::getConnection();
$stmt = $pdo->prepare(
    'SELECT p.id, p.uuid AS photo_uuid, p.description, a.id AS album_id, a.uuid AS album_uuid, a.name AS album_name, a.user_id AS owner_id
     FROM photos p
     JOIN albums a ON p.album_id = a.id
     WHERE p.uuid = ? AND a.uuid = ?'
);
$stmt->execute([$photoUuid, $albumUuid]);
$photo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$photo) {
    renderEditError('Photo not found in this album.', 404);
    exit;
}

$isOwner = (int)$currentUser['id'] === (int)$photo['owner_id'];
if (!$isOwner) {
    renderEditError('You can only edit captions in your own albums.', 403);
    exit;
}

$csrf = Auth::csrfToken();
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        $formError = 'Your session expired. Please try again.';
    } else {
        $newCaption = trim((string)($_POST['description'] ?? ''));
        $captionError = validateUserText($newCaption, 5000, 'Caption');
        if ($captionError !== null) {
            http_response_code(400);
            $formError = $captionError;
            $photo['description'] = $newCaption;
        } else {
            $newCaption = $newCaption === '' ? null : $newCaption;

            $update = $pdo->prepare('UPDATE photos SET description = ? WHERE id = ?');
            $update->execute([$newCaption, (int)$photo['id']]);

            header('Location: /album.php?uuid=' . urlencode((string)$photo['album_uuid']));
            exit;
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>
<section class="page-panel p-4 p-md-5 mb-3">
    <h2>Edit Photo Caption</h2>
    <p class="text-muted mb-3">Album: <?= htmlspecialchars((string)$photo['album_name']) ?></p>

    <?php if ($formError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="col-12">
            <label class="form-label" for="description">Caption</label>
            <textarea id="description" class="form-control" name="description" rows="8" maxlength="5000" placeholder="Tell the story behind your photo..."><?= htmlspecialchars((string)($photo['description'] ?? '')) ?></textarea>
            <div id="captionCounter" style="margin-top: 0.5rem; font-size: 1rem; font-weight: 600; color: #2c5530;">0 / 5,000 characters (~0 words)</div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary btn-icon-only" type="submit" aria-label="Update caption" title="Update caption">
                <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                    <path d="M13.854 3.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L6.5 9.793l6.646-6.647a.5.5 0 0 1 .708 0Z" fill="currentColor" />
                </svg>
            </button>
            <a class="btn btn-secondary btn-icon-only" href="/album.php?uuid=<?= urlencode((string)$photo['album_uuid']) ?>" aria-label="Cancel and return to album" title="Cancel and return to album">
                <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                    <path d="M3.146 3.146a.5.5 0 0 1 .708 0L8 7.293l4.146-4.147a.5.5 0 0 1 .708.708L8.707 8l4.147 4.146a.5.5 0 0 1-.708.708L8 8.707l-4.146 4.147a.5.5 0 1 1-.708-.708L7.293 8 3.146 3.854a.5.5 0 0 1 0-.708Z" fill="currentColor" />
                </svg>
            </a>
        </div>
    </form>
</section>
<script>
    const descTextarea = document.getElementById('description');
    const counter = document.getElementById('captionCounter');

    function updateCounter() {
        const len = descTextarea.value.length;
        const words = descTextarea.value.trim().split(/\s+/).filter(w => w.length > 0).length;
        counter.textContent = len + ' / 5,000 characters (~' + words + ' word' + (words === 1 ? '' : 's') + ')';
    }
    if (descTextarea && counter) {
        descTextarea.addEventListener('input', updateCounter);
        updateCounter();
    }
</script>
<?php require_once __DIR__ . '/templates/footer.php';
