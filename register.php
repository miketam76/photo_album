<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';

use App\Auth;
use App\DB;
use function App\uuid;
use function App\validateUserText;

Auth::startSession();

function renderRegisterForm(string $csrf, array $fieldErrors = [], ?string $formError = null, string $email = '', string $firstName = '', string $lastName = '', string $bio = ''): void
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
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <input class="form-control<?= isset($fieldErrors['first_name']) ? ' is-invalid' : '' ?>" name="first_name" type="text" placeholder="First name" value="<?= htmlspecialchars($firstName) ?>" maxlength="100">
                            <?php if (isset($fieldErrors['first_name'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['first_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <input class="form-control<?= isset($fieldErrors['last_name']) ? ' is-invalid' : '' ?>" name="last_name" type="text" placeholder="Last name" value="<?= htmlspecialchars($lastName) ?>" maxlength="100">
                            <?php if (isset($fieldErrors['last_name'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['last_name']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <textarea name="bio" class="form-control<?= isset($fieldErrors['bio']) ? ' is-invalid' : '' ?>" placeholder="Short bio (optional)" rows="3" maxlength="1000"><?= htmlspecialchars($bio) ?></textarea>
                        <?php if (isset($fieldErrors['bio'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['bio']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <input class="form-control<?= isset($fieldErrors['password']) ? ' is-invalid' : '' ?>" name="password" type="password" placeholder="Password (min 6 characters)" minlength="6" required autocomplete="new-password">
                        <?php if (isset($fieldErrors['password'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <input class="form-control<?= isset($fieldErrors['password_confirm']) ? ' is-invalid' : '' ?>" name="password_confirm" type="password" placeholder="Confirm password" minlength="6" required autocomplete="new-password">
                        <?php if (isset($fieldErrors['password_confirm'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['password_confirm']) ?></div>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary">Register</button>
                    <p class="mt-3 text-muted">Already have an account? <a href="/login.php">Login here</a></p>
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
    $postedFirst = trim((string)($_POST['first_name'] ?? ''));
    $postedLast = trim((string)($_POST['last_name'] ?? ''));
    $postedBio = trim((string)($_POST['bio'] ?? ''));

    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        renderRegisterForm($csrf, [], 'Your session expired. Please try again.', $postedEmail, $postedFirst, $postedLast, $postedBio);
        exit;
    }
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $firstName = $postedFirst;
    $lastName = $postedLast;
    $bio = $postedBio;

    $fieldErrors = [];
    if (!$email) {
        http_response_code(400);
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        $fieldErrors['password'] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $passwordConfirm) {
        http_response_code(400);
        $fieldErrors['password_confirm'] = 'Passwords do not match.';
    }

    // Validate names and bio
    $fnErr = validateUserText($firstName, 100, 'First name');
    if ($fnErr !== null) {
        $fieldErrors['first_name'] = $fnErr;
    }
    $lnErr = validateUserText($lastName, 100, 'Last name');
    if ($lnErr !== null) {
        $fieldErrors['last_name'] = $lnErr;
    }
    $bioErr = validateUserText($bio, 1000, 'Bio');
    if ($bioErr !== null) {
        $fieldErrors['bio'] = $bioErr;
    }

    if (!empty($fieldErrors)) {
        renderRegisterForm($csrf, $fieldErrors, null, $postedEmail, $firstName, $lastName, $bio);
        exit;
    }

    $pdo = DB::getConnection();
    $userUuid = uuid();
    $hash = Auth::hashPassword($password);
    $stmt = $pdo->prepare('INSERT INTO users (uuid, email, password_hash, role, first_name, last_name, bio) VALUES (?, ?, ?, ?, ?, ?, ?)');
    try {
        $stmt->execute([$userUuid, $email, $hash, 'user', $firstName ?: null, $lastName ?: null, $bio ?: null]);
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
    $_SESSION['user'] = ['id' => $id, 'uuid' => $userUuid, 'email' => $email, 'role' => 'user', 'theme' => 'terracotta', 'first_name' => $firstName, 'last_name' => $lastName];
    header('Location: /albums.php');
    exit;
}
