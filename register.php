<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';

use App\Auth;
use App\DB;
use function App\uuid;

Auth::startSession();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $csrf = Auth::csrfToken();
        require __DIR__ . '/templates/header.php';
        ?>
        <div class="row justify-content-center">
            <div class="col-12 col-md-6">
                <h2>Register</h2>
                <form method="post" class="mt-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-3">
                        <input class="form-control" name="email" placeholder="Email">
                    </div>
                    <div class="mb-3">
                        <input class="form-control" name="password" type="password" placeholder="Password">
                    </div>
                    <button class="btn btn-primary">Register</button>
                </form>
            </div>
        </div>
        <?php
        require __DIR__ . '/templates/footer.php';
        exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403); echo 'CSRF'; exit;
    }
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if (!$email || strlen($password) < 6) { echo 'Invalid input'; exit; }

    $pdo = DB::getConnection();
    $userUuid = uuid();
    $hash = Auth::hashPassword($password);
    $stmt = $pdo->prepare('INSERT INTO users (uuid, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userUuid, $email, $hash, 'user']);
    $id = (int)$pdo->lastInsertId();
    // log in
    $_SESSION['user'] = ['id' => $id, 'uuid' => $userUuid, 'email' => $email, 'role' => 'user'];
    header('Location: /');
    exit;
}
