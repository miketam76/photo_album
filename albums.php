<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';

use App\Auth;
use App\DB;
use function App\uuid;

Auth::startSession();
if (empty($_SESSION['user'])) {
  header('Location: /login.php');
  exit;
}
$user = $_SESSION['user'];
$pdo = DB::getConnection();

function fetchUserAlbums(\PDO $pdo, int $userId): array
{
  $stmt = $pdo->prepare('SELECT id, uuid, name, created_at FROM albums WHERE user_id = ? ORDER BY created_at DESC');
  $stmt->execute([$userId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

function renderAlbumsPage(string $csrf, array $albums, array $fieldErrors = [], ?string $formError = null, string $name = ''): void
{
  require_once __DIR__ . '/templates/header.php';
?>
  <section class="page-panel p-4 p-md-5 mb-3">
    <h2>Your Albums</h2>
    <?php if ($formError): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
    <?php endif; ?>
    <form method="post" class="mb-3" novalidate>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="input-group">
        <input name="name" class="form-control<?= isset($fieldErrors['name']) ? ' is-invalid' : '' ?>" placeholder="New album name" value="<?= htmlspecialchars($name) ?>" required maxlength="120">
        <button class="btn btn-primary">Create</button>
      </div>
      <?php if (isset($fieldErrors['name'])): ?>
        <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['name']) ?></div>
      <?php endif; ?>
    </form>

    <?php if (empty($albums)): ?>
      <p>No albums yet.</p>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($albums as $a): ?>
          <a class="list-group-item list-group-item-action bg-dark text-light" href="/album.php?uuid=<?= urlencode($a['uuid']) ?>"><?= htmlspecialchars($a['name']) ?> <small class="text-muted">(<?= htmlspecialchars(formatFriendlyDate((string)($a['created_at'] ?? ''))) ?>)</small></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php
  require_once __DIR__ . '/templates/footer.php';
}

$csrf = Auth::csrfToken();
$albums = fetchUserAlbums($pdo, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim((string)($_POST['name'] ?? ''));

  if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    renderAlbumsPage($csrf, $albums, [], 'Your session expired. Please try again.', $name);
    exit;
  }

  if ($name === '') {
    http_response_code(400);
    renderAlbumsPage($csrf, $albums, ['name' => 'Album name is required.'], null, $name);
    exit;
  }

  if (mb_strlen($name) > 120) {
    http_response_code(400);
    renderAlbumsPage($csrf, $albums, ['name' => 'Album name must be 120 characters or fewer.'], null, $name);
    exit;
  }

  $albumUuid = uuid();
  $stmt = $pdo->prepare('INSERT INTO albums (uuid, user_id, name) VALUES (?, ?, ?)');
  $stmt->execute([$albumUuid, (int)$user['id'], $name]);

  // create storage dir
  $storageDir = __DIR__ . '/storage/uploads/' . $user['uuid'] . '/' . $albumUuid;
  if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
  }

  header('Location: /albums.php');
  exit;
}

renderAlbumsPage($csrf, $albums);
