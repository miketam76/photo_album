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
    header('Location: /login.php'); exit;
}
$user = $_SESSION['user'];
$pdo = DB::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) { http_response_code(403); echo 'CSRF'; exit; }
    $name = trim($_POST['name'] ?? '');
    if ($name === '') { echo 'Name required'; exit; }
    $albumUuid = uuid();
    $stmt = $pdo->prepare('INSERT INTO albums (uuid, user_id, name) VALUES (?, ?, ?)');
    $stmt->execute([$albumUuid, (int)$user['id'], $name]);
    // create storage dir
    $storageDir = __DIR__ . '/storage/uploads/' . $user['uuid'] . '/' . $albumUuid;
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
    header('Location: /albums.php'); exit;
}

$csrf = Auth::csrfToken();
$stmt = $pdo->prepare('SELECT id, uuid, name, created_at FROM albums WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([(int)$user['id']]);
$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/templates/header.php';
?>
<h2>Your Albums</h2>
<form method="post" class="mb-3">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
  <div class="input-group">
    <input name="name" class="form-control" placeholder="New album name">
    <button class="btn btn-primary">Create</button>
  </div>
  </form>

<?php if (empty($albums)): ?>
  <p>No albums yet.</p>
<?php else: ?>
  <div class="list-group">
  <?php foreach ($albums as $a): ?>
    <a class="list-group-item list-group-item-action bg-dark text-light" href="/album.php?uuid=<?= urlencode($a['uuid']) ?>"><?= htmlspecialchars($a['name']) ?> <small class="text-muted">(<?= $a['created_at'] ?>)</small></a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
