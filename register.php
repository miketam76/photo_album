<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';

use App\Auth;
use App\DB;
use function App\uuid;

Auth::startSession();

function renderRegisterForm(string $csrf, array $fieldErrors = [], ?string $formError = null, string $email = ''): void
{
    require __DIR__ . '/templates/header.php';
?>
    <section class="page-panel p-4 p-md-5 mb-3">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6">
                <h2>Register</h2>
                <?php if ($formError): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
                <?php endif; ?>
                <form method="post" class="mt-3" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-3">
                        <input class="form-control<?= isset($fieldErrors['email']) ? ' is-invalid' : '' ?>" name="email" type="email" placeholder="Email" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
                        <?php if (isset($fieldErrors['email'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <input class="form-control<?= isset($fieldErrors['password']) ? ' is-invalid' : '' ?>" name="password" type="password" placeholder="Password (min 6 characters)" minlength="6" required autocomplete="new-password">
                        <?php if (isset($fieldErrors['password'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary">Register</button>
                </form>
            </div>
        </div>
    </section>
<?php
    require __DIR__ . '/templates/footer.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $csrf = Auth::csrfToken();
    renderRegisterForm($csrf);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = Auth::csrfToken();
    $postedEmail = (string)($_POST['email'] ?? '');

    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        renderRegisterForm($csrf, [], 'Your session expired. Please try again.', $postedEmail);
        exit;
    }
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) {
        http_response_code(400);
        renderRegisterForm($csrf, ['email' => 'Please enter a valid email address.'], null, $postedEmail);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        renderRegisterForm($csrf, ['password' => 'Password must be at least 6 characters long.'], null, $postedEmail);
        exit;
    }

    $pdo = DB::getConnection();
    $userUuid = uuid();
    $hash = Auth::hashPassword($password);
    $stmt = $pdo->prepare('INSERT INTO users (uuid, email, password_hash, role) VALUES (?, ?, ?, ?)');
    try {
        $stmt->execute([$userUuid, $email, $hash, 'user']);
    } catch (\PDOException $e) {
        // SQLite uniqueness violations should not crash the request.
        if (($e->getCode() ?? '') === '23000') {
            http_response_code(409);
            renderRegisterForm($csrf, ['email' => 'Email is already registered.'], null, (string)$email);
            exit;
        }
        throw $e;
    }
    $id = (int)$pdo->lastInsertId();
    // log in
    $_SESSION['user'] = ['id' => $id, 'uuid' => $userUuid, 'email' => $email, 'role' => 'user', 'theme' => 'terracotta'];
    header('Location: /albums.php');
    exit;
}
