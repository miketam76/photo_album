<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

use App\Auth;
use App\DB;

Auth::startSession();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $csrf = Auth::csrfToken();
        require __DIR__ . '/templates/header.php';
        ?>
        <div class="row justify-content-center">
            <div class="col-12 col-md-6">
                <h2>Login</h2>
                <form method="post" class="mt-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-3">
                        <input class="form-control" name="email" placeholder="Email">
                    </div>
                    <div class="mb-3">
                        <input class="form-control" name="password" type="password" placeholder="Password">
                    </div>
                    <button class="btn btn-primary">Login</button>
                </form>
            </div>
        </div>
        <?php
        require __DIR__ . '/templates/footer.php';
        exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) { http_response_code(403); echo 'CSRF'; exit; }
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if (!$email) { echo 'Invalid'; exit; }
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare('SELECT id, uuid, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !Auth::verifyPassword($password, $row['password_hash'])) {
        echo 'Invalid credentials'; exit;
    }
    $_SESSION['user'] = ['id' => (int)$row['id'], 'uuid' => $row['uuid'], 'email' => $email, 'role' => $row['role']];
    header('Location: /'); exit;
}
