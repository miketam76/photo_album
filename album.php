<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

use App\Auth;
use App\DB;

Auth::startSession();
if (empty($_GET['uuid'])) { http_response_code(400); echo 'Missing album id'; exit; }
$albumUuid = $_GET['uuid'];

$pdo = DB::getConnection();
$stmt = $pdo->prepare('SELECT a.*, u.uuid AS user_uuid, u.id AS owner_id, u.email FROM albums a JOIN users u ON a.user_id = u.id WHERE a.uuid = ?');
$stmt->execute([$albumUuid]);
$album = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$album) { http_response_code(404); echo 'Album not found'; exit; }

$currentUser = $_SESSION['user'] ?? null;
if ($currentUser === null) { header('Location: /login.php'); exit; }
if (!($currentUser['role'] === 'admin' || (int)$currentUser['id'] === (int)$album['owner_id'])) {
    http_response_code(403); echo 'Forbidden'; exit;
}

$stmt = $pdo->prepare('SELECT uuid, file_path, original_name, mime, uploaded_at, width, height, description FROM photos WHERE album_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([(int)$album['id']]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/templates/header.php';
?>
<h2><?= htmlspecialchars($album['name']) ?></h2>
<p class="text-muted">Owner: <?= htmlspecialchars($album['email']) ?> — Created: <?= $album['created_at'] ?></p>

<?php if ($currentUser && ($currentUser['role'] === 'admin' || (int)$currentUser['id'] === (int)$album['owner_id'])): ?>
  <p class="mb-3">
    <a class="btn btn-primary" href="/upload.php?album=<?= urlencode($album['uuid']) ?>">Upload to this album</a>
  </p>
<?php endif; ?>

<?php if (empty($photos)): ?>
  <p>No photos yet.</p>
<?php else: ?>
  <div id="gallery" class="row g-3">
  <?php foreach ($photos as $p): ?>
    <div class="col-6 col-md-3">
      <div class="card photo-card bg-dark text-light shadow-sm">
        <?php $w = (int)($p['width'] ?? 0); $h = (int)($p['height'] ?? 0); ?>
        <a data-pswp href="/image.php?photo=<?= urlencode($p['uuid']) ?>&size=large" <?= $w && $h ? 'data-pswp-width="' . $w . '" data-pswp-height="' . $h . '"' : '' ?> >
          <img src="/image.php?photo=<?= urlencode($p['uuid']) ?>&size=thumb" class="card-img-top" alt="<?= htmlspecialchars($p['original_name'] ?? '') ?>">
        </a>
        <div class="card-body py-2 px-2">
          <p class="small mb-0 text-truncate"><?= htmlspecialchars($p['description'] ?? $p['original_name'] ?? '') ?></p>
          <p class="text-muted small mb-0"><?= $p['uploaded_at'] ?></p>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
