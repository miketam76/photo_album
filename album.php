<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

use App\Auth;
use App\DB;

Auth::startSession();

function formatFriendlyDate(?string $value): string
{
  if (!$value) {
    return '';
  }

  $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
  if ($dt instanceof \DateTime) {
    return $dt->format('F j, Y');
  }

  return $value;
}

function formatFriendlyDateTime(?string $value): string
{
  if (!$value) {
    return '';
  }

  $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
  if ($dt instanceof \DateTime) {
    return $dt->format('F j, Y');
  }

  return $value;
}

function renderAlbumError(string $message, int $status = 400): void
{
  http_response_code($status);
  require_once __DIR__ . '/templates/header.php';
  echo '<section class="page-panel p-4 p-md-5 mb-3">';
  echo '<p class="alert alert-danger">' . htmlspecialchars($message) . '</p>';
  echo '<p><a class="btn btn-secondary" href="/albums.php">Back to albums</a></p>';
  echo '</section>';
  require_once __DIR__ . '/templates/footer.php';
}

function deletePhotoFiles(array $photo): void
{
  $original = (string)($photo['file_path'] ?? '');
  if ($original !== '' && is_file($original)) {
    @unlink($original);
  }

  $userUuid = (string)($photo['user_uuid'] ?? '');
  $albumUuid = (string)($photo['album_uuid'] ?? '');
  $base = basename($original);
  if ($userUuid === '' || $albumUuid === '' || $base === '') {
    return;
  }

  $cacheRoot = __DIR__ . '/storage/cache/' . $userUuid . '/' . $albumUuid;
  foreach (['large', 'medium', 'thumb'] as $size) {
    $cached = $cacheRoot . '/' . $size . '/' . $base . '.jpg';
    if (is_file($cached)) {
      @unlink($cached);
    }
  }
}

if (empty($_GET['uuid'])) {
  renderAlbumError('Missing album id.', 400);
  exit;
}
$albumUuid = $_GET['uuid'];

$pdo = DB::getConnection();
$stmt = $pdo->prepare('SELECT a.*, u.uuid AS user_uuid, u.id AS owner_id, u.email FROM albums a JOIN users u ON a.user_id = u.id WHERE a.uuid = ?');
$stmt->execute([$albumUuid]);
$album = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$album) {
  renderAlbumError('Album not found.', 404);
  exit;
}

$currentUser = $_SESSION['user'] ?? null;
if ($currentUser === null) {
  header('Location: /login.php');
  exit;
}
if (!($currentUser['role'] === 'admin' || (int)$currentUser['id'] === (int)$album['owner_id'])) {
  renderAlbumError('You do not have permission to view this album.', 403);
  exit;
}

$csrf = Auth::csrfToken();
$formError = null;
$formSuccess = null;
$isAlbumOwner = (int)$currentUser['id'] === (int)$album['owner_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    $formError = 'Your session expired. Please try again.';
  } else {
    $action = (string)($_POST['action'] ?? '');
    $photoUuid = (string)($_POST['photo_uuid'] ?? '');

    $photoStmt = $pdo->prepare('SELECT p.id, p.file_path, a.uuid AS album_uuid, u.uuid AS user_uuid FROM photos p JOIN albums a ON p.album_id = a.id JOIN users u ON p.user_id = u.id WHERE p.uuid = ? AND p.album_id = ?');
    $photoStmt->execute([$photoUuid, (int)$album['id']]);
    $photoRow = $photoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$photoRow) {
      $formError = 'Photo not found in this album.';
    } elseif ($action === 'delete_photo') {
      if (!$isAlbumOwner) {
        http_response_code(403);
        $formError = 'You can only delete photos from your own albums.';
      } else {
        $deleteStmt = $pdo->prepare('DELETE FROM photos WHERE id = ?');
        $deleteStmt->execute([(int)$photoRow['id']]);
        deletePhotoFiles($photoRow);
        $formSuccess = 'Photo deleted successfully.';
      }
    } else {
      $formError = 'Invalid photo action.';
    }
  }
}

$stmt = $pdo->prepare('SELECT uuid, file_path, original_name, mime, uploaded_at, width, height, description FROM photos WHERE album_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([(int)$album['id']]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/templates/header.php';
?>
<section class="page-panel p-4 p-md-5 mb-3">
  <h2><?= htmlspecialchars($album['name']) ?></h2>
  <p class="text-muted">Created: <?= htmlspecialchars(formatFriendlyDate((string)($album['created_at'] ?? ''))) ?></p>

  <?php if ($formError): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
  <?php endif; ?>
  <?php if ($formSuccess): ?>
    <div class="alert alert-success"><?= htmlspecialchars($formSuccess) ?></div>
  <?php endif; ?>

  <?php if ($currentUser && ($currentUser['role'] === 'admin' || (int)$currentUser['id'] === (int)$album['owner_id'])): ?>
    <p class="mb-3">
      <a
        class="btn btn-primary btn-upload-cta btn-icon-only"
        href="/upload.php?album=<?= urlencode($album['uuid']) ?>"
        aria-label="Upload photo to this album"
        title="Upload photo to this album">
        <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
          <path d="M8 1.5a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5V2a.5.5 0 0 1 .5-.5Z" fill="currentColor" />
        </svg>
      </a>
    </p>
  <?php endif; ?>

  <?php if (empty($photos)): ?>
    <p>No photos yet.</p>
  <?php else: ?>
    <div class="d-flex justify-content-end mb-3">
      <button id="startSlideshow" type="button" class="btn btn-outline-secondary btn-sm">
        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false" class="me-1" style="vertical-align:-1px">
          <path d="M11.596 8.697l-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z" fill="currentColor" />
        </svg>Slideshow
      </button>
    </div>
    <div id="gallery" class="row g-3">
      <?php foreach ($photos as $p): ?>
        <div class="col-6 col-md-3">
          <div class="card photo-card bg-dark text-light shadow-sm">
            <?php $w = (int)($p['width'] ?? 0);
            $h = (int)($p['height'] ?? 0); ?>
            <button
              type="button"
              class="photo-tile-trigger"
              data-preview-src="/image.php?photo=<?= urlencode($p['uuid']) ?>&size=large"
              data-preview-alt="<?= htmlspecialchars((string)($p['original_name'] ?? 'Photo preview')) ?>"
              data-description="<?= htmlspecialchars(trim((string)($p['description'] ?? ''))) ?>"
              data-width="<?= $w ?>"
              data-height="<?= $h ?>"
              aria-label="Open larger photo preview"
              title="Open larger photo preview">
              <img src="/image.php?photo=<?= urlencode($p['uuid']) ?>&size=thumb" class="card-img-top" alt="<?= htmlspecialchars($p['original_name'] ?? '') ?>">
            </button>
            <div class="card-body py-2 px-2">
              <p class="small mb-1"><strong>Caption:</strong> <?= htmlspecialchars(trim((string)($p['description'] ?? '')) !== '' ? (string)$p['description'] : 'No caption') ?></p>
              <p class="text-muted small mb-0"><strong>Uploaded:</strong> <?= htmlspecialchars(formatFriendlyDateTime((string)($p['uploaded_at'] ?? ''))) ?></p>

              <?php if ($isAlbumOwner): ?>
                <div class="d-flex gap-2 mt-2">
                  <a
                    class="btn btn-sm btn-primary btn-icon-only"
                    href="/edit_photo.php?album=<?= urlencode((string)$album['uuid']) ?>&photo=<?= urlencode((string)$p['uuid']) ?>"
                    aria-label="Edit caption"
                    title="Edit caption">
                    <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                      <path d="M2.5 1A1.5 1.5 0 0 0 1 2.5v11A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5V8.793a.5.5 0 0 0-1 0V13.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5h4.707a.5.5 0 0 0 0-1H2.5Zm11.354.146a.5.5 0 0 1 0 .708l-7.5 7.5a.5.5 0 0 1-.224.13l-2 .5a.5.5 0 0 1-.606-.606l.5-2a.5.5 0 0 1 .13-.224l7.5-7.5a.5.5 0 0 1 .708 0l1.5 1.5Zm-1.146 2.061L11.793 2.293 4.94 9.146l-.293 1.171 1.171-.293 6.854-6.853Z" fill="currentColor" />
                    </svg>
                  </a>
                  <form method="post" class="d-inline" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="photo_uuid" value="<?= htmlspecialchars((string)$p['uuid']) ?>">
                    <button
                      class="btn btn-sm btn-outline-danger btn-icon-only"
                      type="submit"
                      name="action"
                      value="delete_photo"
                      aria-label="Delete photo"
                      title="Delete photo"
                      onclick="return confirm('Delete this photo?');">
                      <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                        <path d="M6.5 1h3a1 1 0 0 1 1 1V3h3a.5.5 0 0 1 0 1h-.61l-.622 9.337A2 2 0 0 1 10.272 15H5.728a2 2 0 0 1-1.996-1.663L3.11 4H2.5a.5.5 0 0 1 0-1h3V2a1 1 0 0 1 1-1Zm3 2V2h-3v1h3ZM4.108 4l.619 9.28a1 1 0 0 0 .998.833h4.55a1 1 0 0 0 .998-.833L11.892 4H4.108Zm2.392 2.5a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V7a.5.5 0 0 1 .5-.5Zm3 0a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V7a.5.5 0 0 1 .5-.5Z" fill="currentColor" />
                      </svg>
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</section>

<dialog id="photoPreviewDialog" class="photo-preview-dialog" aria-label="Photo preview dialog">
  <button class="slideshow-close" id="slideshowClose" aria-label="Close">&times;</button>
  <img id="photoPreviewImage" class="photo-preview-image" src="" alt="">
  <div id="photoCaption" class="photo-caption"></div>
  <div class="slideshow-controls">
    <button class="slideshow-nav slideshow-prev" id="slideshowPrev" aria-label="Previous photo">&#8592;</button>
    <button class="slideshow-play-btn" id="slideshowPlayPause" aria-label="Play slideshow">&#9654;</button>
    <span class="slideshow-counter" id="slideshowCounter"></span>
    <button class="slideshow-nav slideshow-next" id="slideshowNext" aria-label="Next photo">&#8594;</button>
  </div>
</dialog>

<script>
  (function() {
    const dialog = document.getElementById('photoPreviewDialog');
    const previewImg = document.getElementById('photoPreviewImage');
    const closeBtn = document.getElementById('slideshowClose');
    const prevBtn = document.getElementById('slideshowPrev');
    const nextBtn = document.getElementById('slideshowNext');
    const playBtn = document.getElementById('slideshowPlayPause');
    const counter = document.getElementById('slideshowCounter');
    const startBtn = document.getElementById('startSlideshow');

    if (!dialog || !previewImg) return;

    // Build photo list from tile triggers
    const photos = Array.from(document.querySelectorAll('.photo-tile-trigger')).map(t => ({
      src: t.getAttribute('data-preview-src'),
      alt: t.getAttribute('data-preview-alt') || 'Photo preview',
      description: t.getAttribute('data-description') || '',
    }));

    let current = 0;
    let playing = false;
    let intervalId = null;
    const INTERVAL = 4000;

    function showPhoto(index) {
      current = ((index % photos.length) + photos.length) % photos.length;
      previewImg.src = photos[current].src;
      previewImg.alt = photos[current].alt;
      const captionEl = document.getElementById('photoCaption');
      if (captionEl) {
        captionEl.textContent = photos[current].description;
        captionEl.style.display = photos[current].description ? 'block' : 'none';
      }
      counter.textContent = (current + 1) + ' / ' + photos.length;
      const multi = photos.length > 1;
      prevBtn.style.visibility = multi ? 'visible' : 'hidden';
      nextBtn.style.visibility = multi ? 'visible' : 'hidden';
    }

    function openAt(index) {
      showPhoto(index);
      if (typeof dialog.showModal === 'function') dialog.showModal();
    }

    function stopSlideshow() {
      clearInterval(intervalId);
      intervalId = null;
      playing = false;
      playBtn.innerHTML = '&#9654;';
      playBtn.setAttribute('aria-label', 'Play slideshow');
    }

    function startSlideshow() {
      playing = true;
      playBtn.innerHTML = '&#9646;&#9646;';
      playBtn.setAttribute('aria-label', 'Pause slideshow');
      intervalId = setInterval(() => showPhoto(current + 1), INTERVAL);
    }

    // Tile clicks — open at that index, not auto-playing
    document.querySelectorAll('.photo-tile-trigger').forEach((trigger, i) => {
      trigger.addEventListener('click', () => {
        stopSlideshow();
        openAt(i);
      });
    });

    // Slideshow start button
    if (startBtn) {
      startBtn.addEventListener('click', () => {
        stopSlideshow();
        openAt(0);
        startSlideshow();
      });
    }

    closeBtn.addEventListener('click', () => {
      stopSlideshow();
      dialog.close();
    });
    prevBtn.addEventListener('click', () => {
      stopSlideshow();
      showPhoto(current - 1);
    });
    nextBtn.addEventListener('click', () => {
      stopSlideshow();
      showPhoto(current + 1);
    });
    playBtn.addEventListener('click', () => playing ? stopSlideshow() : startSlideshow());

    // Close on backdrop click
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) {
        stopSlideshow();
        dialog.close();
      }
    });
    dialog.addEventListener('close', () => {
      stopSlideshow();
      previewImg.removeAttribute('src');
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      if (!dialog.open) return;
      if (e.key === 'ArrowLeft') {
        stopSlideshow();
        showPhoto(current - 1);
      }
      if (e.key === 'ArrowRight') {
        stopSlideshow();
        showPhoto(current + 1);
      }
      if (e.key === ' ') {
        e.preventDefault();
        playing ? stopSlideshow() : startSlideshow();
      }
    });
  })();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>