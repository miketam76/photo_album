<?php
declare(strict_types=1);

// Integration-style upload test (CLI): creates a small PNG, simulates storing, thumbnails, and DB insert
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/Thumbnailer.php';

use App\DB;
use function App\uuid;
use App\Thumbnailer;

$root = __DIR__ . '/..';
if (!is_dir($root . '/storage/uploads')) mkdir($root . '/storage/uploads', 0755, true);
if (!is_dir($root . '/storage/cache')) mkdir($root . '/storage/cache', 0755, true);

// create a valid tiny PNG via GD
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'testimg_' . bin2hex(random_bytes(6)) . '.png';
$img = imagecreatetruecolor(10, 10);
$bg = imagecolorallocate($img, 16, 128, 64);
imagefill($img, 0, 0, $bg);
$white = imagecolorallocate($img, 255, 255, 255);
imagestring($img, 2, 2, 2, 'T', $white);
imagepng($img, $tmp);
imagedestroy($img);

$user_uuid = 'cliuser';
$album_uuid = 'clialbum';

$pdo = DB::getConnection();

$pdo->beginTransaction();
// insert user if missing
$stmt = $pdo->prepare('SELECT id FROM users WHERE uuid = ?');
$stmt->execute([$user_uuid]);
$user = $stmt->fetchColumn();
if (!$user) {
    $pwd = password_hash('test', PASSWORD_ARGON2ID);
    $ins = $pdo->prepare('INSERT INTO users (uuid, email, password_hash, role, first_name) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$user_uuid, 'cli@local', $pwd, 'user', 'CLI']);
    $user = $pdo->lastInsertId();
}

// insert album if missing
$stmt = $pdo->prepare('SELECT id FROM albums WHERE uuid = ?');
$stmt->execute([$album_uuid]);
$album = $stmt->fetchColumn();
if (!$album) {
    $ins = $pdo->prepare('INSERT INTO albums (uuid, user_id, name) VALUES (?, ?, ?)');
    $ins->execute([$album_uuid, $user, 'CLI Album']);
    $album = $pdo->lastInsertId();
}
$pdo->commit();

$u = uuid();
$storageDir = $root . '/storage/uploads/' . $user_uuid . '/' . $album_uuid;
if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
$dest = $storageDir . '/' . $u . '.png';
if (!rename($tmp, $dest)) {
    echo "Failed to move test image to storage.\n";
    exit(1);
}

echo "Stored test image at: $dest\n";

// generate thumbnails
try {
    $thumbs = Thumbnailer::generate($dest, $root . '/storage/cache/' . $user_uuid . '/' . $album_uuid);
    echo "Thumbnails: " . implode(', ', array_values($thumbs)) . "\n";
} catch (Exception $e) {
    echo "Thumbnailing failed: " . $e->getMessage() . "\n";
}

// insert into DB (use real album/user ids) and include width/height
$info = getimagesize($dest);
$w = $info[0] ?? null;
$h = $info[1] ?? null;
$stmt = $pdo->prepare('INSERT INTO photos (uuid, album_id, user_id, file_path, original_name, mime, size_bytes, width, height, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$u, (int)$album, (int)$user, $dest, 'test.png', 'image/png', filesize($dest), $w, $h, 'CLI test']);
echo "Inserted photo record with uuid: $u\n";

exit(0);
