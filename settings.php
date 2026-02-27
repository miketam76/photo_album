<?php

declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';

use App\Auth;
use App\DB;

Auth::startSession();

if (empty($_SESSION['user']['id'])) {
    header('Location: /login.php');
    exit;
}

$themeOptions = [
    'terracotta' => 'Terracotta + Cream',
    'forest' => 'Forest + Sand',
    'slate' => 'Slate + Teal',
    'ocean' => 'Deep Ocean Light',
    'olive' => 'Olive + Stone',
    'charcoal' => 'Charcoal + Copper',
    'sunset' => 'Sunset Coral + Peach',
    'midnight' => 'Midnight Blue + Ice',
    'lavender' => 'Lavender + Mist',
    'berry' => 'Berry + Rose',
    'sandstone' => 'Sandstone + Clay',
    'high-contrast-dark' => 'High Contrast Dark',
];

function formatFriendlyDate(?string $value): string
{
    if (!$value) {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if ($dt instanceof DateTime) {
        return $dt->format('F j, Y');
    }

    return $value;
}

use function App\validateUserText;

function fetchCurrentUserSettings(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, email, role, created_at, theme, password_hash, first_name, last_name, bio FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

$userId = (int)$_SESSION['user']['id'];
$pdo = DB::getConnection();
$csrf = Auth::csrfToken();
$formError = null;
$formSuccess = null;
$passwordErrors = [];
$themeError = null;
$profileErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf'] ?? null)) {
        $formError = 'Your session expired. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if ($currentPassword === '') {
                $passwordErrors['current_password'] = 'Current password is required.';
            }
            if (strlen($newPassword) < 6) {
                $passwordErrors['new_password'] = 'New password must be at least 6 characters long.';
            }
            if ($newPassword !== $confirmPassword) {
                $passwordErrors['confirm_password'] = 'Password confirmation does not match.';
            }

            $userRow = fetchCurrentUserSettings($pdo, $userId);
            if (!$userRow) {
                $formError = 'Unable to load your account settings.';
            } elseif (empty($passwordErrors) && !Auth::verifyPassword($currentPassword, (string)$userRow['password_hash'])) {
                $passwordErrors['current_password'] = 'Current password is incorrect.';
            }

            if (empty($passwordErrors) && $formError === null) {
                $newHash = Auth::hashPassword($newPassword);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([$newHash, $userId]);
                $formSuccess = 'Password updated successfully.';
            }
        } elseif ($action === 'theme') {
            $theme = (string)($_POST['theme'] ?? 'terracotta');
            if (!array_key_exists($theme, $themeOptions)) {
                $themeError = 'Please choose a valid theme.';
            } else {
                try {
                    $stmt = $pdo->prepare('UPDATE users SET theme = ? WHERE id = ?');
                    $stmt->execute([$theme, $userId]);
                    $_SESSION['user']['theme'] = $theme;
                    $formSuccess = 'Theme updated successfully.';
                } catch (Throwable $e) {
                    $formError = 'Unable to save theme preference. Run migrations, then try again.';
                }
            }
        } elseif ($action === 'profile') {
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $bio = trim((string)($_POST['bio'] ?? ''));

            $fnErr = validateUserText($firstName, 100, 'First name');
            if ($fnErr !== null) $profileErrors['first_name'] = $fnErr;
            $lnErr = validateUserText($lastName, 100, 'Last name');
            if ($lnErr !== null) $profileErrors['last_name'] = $lnErr;
            $bioErr = validateUserText($bio, 1000, 'Bio');
            if ($bioErr !== null) $profileErrors['bio'] = $bioErr;

            if (empty($profileErrors)) {
                try {
                    $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, bio = ? WHERE id = ?');
                    $stmt->execute([$firstName ?: null, $lastName ?: null, $bio ?: null, $userId]);
                    $_SESSION['user']['first_name'] = $firstName;
                    $_SESSION['user']['last_name'] = $lastName;
                    $formSuccess = 'Profile updated successfully.';
                    // refresh settings
                    $userSettings = fetchCurrentUserSettings($pdo, $userId);
                } catch (\Throwable $e) {
                    $formError = 'Unable to save profile. Please try again.';
                }
            }
        } else {
            $formError = 'Invalid settings action.';
        }
    }
}

$userSettings = fetchCurrentUserSettings($pdo, $userId);
if (!$userSettings) {
    http_response_code(404);
    require __DIR__ . '/templates/header.php';
    echo '<section class="page-panel p-4 p-md-5 mb-3">';
    echo '<p class="alert alert-danger">Unable to load your settings.</p>';
    echo '</section>';
    require __DIR__ . '/templates/footer.php';
    exit;
}

$selectedTheme = (string)($userSettings['theme'] ?? ($_SESSION['user']['theme'] ?? 'terracotta'));
if (!array_key_exists($selectedTheme, $themeOptions)) {
    $selectedTheme = 'terracotta';
}

require __DIR__ . '/templates/header.php';
?>
<section class="page-panel p-4 p-md-5 mb-3">
    <h2>User Settings</h2>

    <?php if ($formError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
    <?php endif; ?>
    <?php if ($formSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($formSuccess) ?></div>
    <?php endif; ?>

    <div class="mb-4">
        <h3 class="h5">Profile</h3>
        <form method="post" class="row g-3" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="profile">

            <div class="col-12">
                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars((string)($userSettings['email'] ?? '')) ?></p>
                <p class="mb-1"><strong>Role:</strong> <?= htmlspecialchars((string)($userSettings['role'] ?? 'user')) ?></p>
                <p class="mb-0"><strong>Member Since:</strong> <?= htmlspecialchars(formatFriendlyDate((string)($userSettings['created_at'] ?? ''))) ?></p>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="first_name">First name</label>
                <input id="first_name" name="first_name" type="text" class="form-control<?= isset($profileErrors['first_name']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars((string)($userSettings['first_name'] ?? '')) ?>" maxlength="100">
                <?php if (isset($profileErrors['first_name'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($profileErrors['first_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="last_name">Last name</label>
                <input id="last_name" name="last_name" type="text" class="form-control<?= isset($profileErrors['last_name']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars((string)($userSettings['last_name'] ?? '')) ?>" maxlength="100">
                <?php if (isset($profileErrors['last_name'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($profileErrors['last_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label" for="bio">Bio</label>
                <textarea id="bio" name="bio" class="form-control<?= isset($profileErrors['bio']) ? ' is-invalid' : '' ?>" rows="4" maxlength="1000"><?= htmlspecialchars((string)($userSettings['bio'] ?? '')) ?></textarea>
                <?php if (isset($profileErrors['bio'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($profileErrors['bio']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit">Save Profile</button>
            </div>
        </form>
    </div>

    <div class="mb-4">
        <h3 class="h5">Change Password</h3>
        <form method="post" class="row g-3" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="password">

            <div class="col-12 col-md-6">
                <label class="form-label" for="current_password">Current Password</label>
                <input id="current_password" name="current_password" type="password" class="form-control<?= isset($passwordErrors['current_password']) ? ' is-invalid' : '' ?>" autocomplete="current-password" required>
                <?php if (isset($passwordErrors['current_password'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($passwordErrors['current_password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="new_password">New Password</label>
                <input id="new_password" name="new_password" type="password" class="form-control<?= isset($passwordErrors['new_password']) ? ' is-invalid' : '' ?>" minlength="6" autocomplete="new-password" required>
                <?php if (isset($passwordErrors['new_password'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($passwordErrors['new_password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input id="confirm_password" name="confirm_password" type="password" class="form-control<?= isset($passwordErrors['confirm_password']) ? ' is-invalid' : '' ?>" minlength="6" autocomplete="new-password" required>
                <?php if (isset($passwordErrors['confirm_password'])): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($passwordErrors['confirm_password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit">Update Password</button>
            </div>
        </form>
    </div>

    <div>
        <h3 class="h5">Theme Preference</h3>
        <form method="post" class="row g-3" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="theme">

            <div class="col-12 col-md-6">
                <label class="form-label" for="theme">Choose Theme</label>
                <select id="theme" name="theme" class="form-select<?= $themeError ? ' is-invalid' : '' ?>" onchange="this.form.submit()">
                    <?php foreach ($themeOptions as $themeValue => $themeLabel): ?>
                        <option value="<?= htmlspecialchars($themeValue) ?>" <?= $selectedTheme === $themeValue ? 'selected' : '' ?>><?= htmlspecialchars($themeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($themeError): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($themeError) ?></div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>
<?php require __DIR__ . '/templates/footer.php';
