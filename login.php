<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

use App\Auth;
use App\DB;

Auth::startSession();

function renderLoginForm(string $csrf, array $fieldErrors = [], ?string $formError = null, string $email = ''): void
{
    require __DIR__ . '/templates/header.php';
?>
    <section class="page-panel p-4 p-md-5 mb-3">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6">
                <h2>Login</h2>
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
                        <input class="form-control<?= isset($fieldErrors['password']) ? ' is-invalid' : '' ?>" name="password" type="password" placeholder="Password" required autocomplete="current-password">
                        <?php if (isset($fieldErrors['password'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary">Login</button>
                </form>
            </div>
        </div>
    </section>
<?php
    require __DIR__ . '/templates/footer.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $csrf = Auth::csrfToken();
    renderLoginForm($csrf);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = Auth::csrfToken();
    $postedEmail = (string)($_POST['email'] ?? '');

    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        renderLoginForm($csrf, [], 'Your session expired. Please try again.', $postedEmail);
        exit;
    }

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if (!$email) {
        http_response_code(400);
        renderLoginForm($csrf, ['email' => 'Please enter a valid email address.'], null, $postedEmail);
        exit;
    }
    if ($password === '') {
        http_response_code(400);
        renderLoginForm($csrf, ['password' => 'Password is required.'], null, $postedEmail);
        exit;
    }

    $pdo = DB::getConnection();
    $stmt = $pdo->prepare('SELECT id, uuid, password_hash, role, theme FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !Auth::verifyPassword($password, $row['password_hash'])) {
        http_response_code(401);
        renderLoginForm($csrf, ['password' => 'Invalid email or password.'], null, (string)$email);
        exit;
    }
    $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'uuid' => $row['uuid'],
        'email' => $email,
        'role' => $row['role'],
        'theme' => $row['theme'] ?? 'terracotta',
    ];
    header('Location: /albums.php');
    exit;
}
