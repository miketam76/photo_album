<?php
// header.php - renders nav and starts session
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

use App\Auth;
use App\DB;

Auth::startSession();
$user = $_SESSION['user'] ?? null;

$allowedThemes = ['terracotta', 'forest', 'slate', 'ocean', 'olive', 'charcoal', 'sunset', 'midnight', 'lavender', 'berry', 'sandstone', 'high-contrast-dark'];
$activeTheme = 'terracotta';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($user && isset($user['theme']) && in_array((string)$user['theme'], $allowedThemes, true)) {
  $activeTheme = (string)$user['theme'];
} elseif ($user && !empty($user['id'])) {
  try {
    $pdo = DB::getConnection();
    $stmt = $pdo->prepare('SELECT theme FROM users WHERE id = ?');
    $stmt->execute([(int)$user['id']]);
    $theme = $stmt->fetchColumn();
    if (is_string($theme) && in_array($theme, $allowedThemes, true)) {
      $activeTheme = $theme;
      $_SESSION['user']['theme'] = $theme;
    }
  } catch (\Throwable $_) {
    // Keep default theme if DB lookup fails.
  }
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css">
</head>

<body class="bg-dark text-light" data-theme="<?= htmlspecialchars($activeTheme) ?>">
  <div class="container py-4">
    <nav class="navbar navbar-expand-lg navbar-light mb-3">
      <div class="container-fluid">
        <a class="navbar-brand" href="/">Photo Album App</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a
                class="nav-link nav-icon-link<?= $currentPath === '/albums.php' ? ' active' : '' ?>"
                href="/albums.php"
                <?= $currentPath === '/albums.php' ? 'aria-current="page"' : '' ?>
                aria-label="Albums"
                title="Albums">
                <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                  <path d="M2.5 3A1.5 1.5 0 0 0 1 4.5v8A1.5 1.5 0 0 0 2.5 14h11a1.5 1.5 0 0 0 1.5-1.5v-8A1.5 1.5 0 0 0 13.5 3h-3.293a1.5 1.5 0 0 1-1.06-.44l-.414-.413A1.5 1.5 0 0 0 7.672 1.5H2.5Zm0 1h5.172a.5.5 0 0 1 .354.146l.414.414A2.5 2.5 0 0 0 10.207 5H13.5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-8a.5.5 0 0 1 .5-.5Z" fill="currentColor" />
                </svg>
              </a>
            </li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <?php if ($user): ?>
              <li class="nav-item">
                <a
                  class="nav-link nav-icon-link<?= $currentPath === '/settings.php' ? ' active' : '' ?>"
                  href="/settings.php"
                  <?= $currentPath === '/settings.php' ? 'aria-current="page"' : '' ?>
                  aria-label="Settings"
                  title="Settings">
                  <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                    <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.094.319a1.464 1.464 0 0 1-2.105.872l-.286-.166c-1.27-.736-2.67.665-1.934 1.934l.165.286a1.464 1.464 0 0 1-.872 2.105l-.319.094c-1.4.413-1.4 2.397 0 2.81l.319.094a1.464 1.464 0 0 1 .872 2.105l-.166.286c-.736 1.27.665 2.67 1.934 1.934l.286-.165a1.464 1.464 0 0 1 2.105.872l.094.319c.413 1.4 2.397 1.4 2.81 0l.094-.319a1.464 1.464 0 0 1 2.105-.872l.286.166c1.27.736 2.67-.665 1.934-1.934l-.165-.286a1.464 1.464 0 0 1 .872-2.105l.319-.094c1.4-.413 1.4-2.397 0-2.81l-.319-.094a1.464 1.464 0 0 1-.872-2.105l.166-.286c.736-1.27-.665-2.67-1.934-1.934l-.286.165a1.464 1.464 0 0 1-2.105-.872l-.094-.319ZM8 10.5A2.5 2.5 0 1 1 8 5.5a2.5 2.5 0 0 1 0 5Z" fill="currentColor" />
                  </svg>
                </a>
              </li>
              <li class="nav-item"><span class="nav-link">Hello, <?= htmlspecialchars($user['email'] ?? 'user') ?></span></li>
              <li class="nav-item">
                <a class="nav-link nav-icon-link" href="/logout.php" aria-label="Logout" title="Logout">
                  <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                    <path d="M10.146 11.854a.5.5 0 0 0 .708 0l3.5-3.5a.5.5 0 0 0 0-.708l-3.5-3.5a.5.5 0 1 0-.708.708L12.793 7.5H6.5a.5.5 0 0 0 0 1h6.293l-2.647 2.646a.5.5 0 0 0 0 .708Z" fill="currentColor" />
                    <path d="M3.5 2A1.5 1.5 0 0 0 2 3.5v9A1.5 1.5 0 0 0 3.5 14h4a.5.5 0 0 0 0-1h-4a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h4a.5.5 0 0 0 0-1h-4Z" fill="currentColor" />
                  </svg>
                </a>
              </li>
            <?php else: ?>
              <li class="nav-item">
                <a
                  class="nav-link nav-icon-link<?= $currentPath === '/login.php' ? ' active' : '' ?>"
                  href="/login.php"
                  <?= $currentPath === '/login.php' ? 'aria-current="page"' : '' ?>
                  aria-label="Login"
                  title="Login">
                  <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                    <path d="M10.146 11.854a.5.5 0 0 0 .708 0l3.5-3.5a.5.5 0 0 0 0-.708l-3.5-3.5a.5.5 0 1 0-.708.708L12.793 7.5H6.5a.5.5 0 0 0 0 1h6.293l-2.647 2.646a.5.5 0 0 0 0 .708Z" fill="currentColor" />
                    <path d="M3.5 2A1.5 1.5 0 0 0 2 3.5v9A1.5 1.5 0 0 0 3.5 14h4a.5.5 0 0 0 0-1h-4a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h4a.5.5 0 0 0 0-1h-4Z" fill="currentColor" />
                  </svg>
                </a>
              </li>
              <li class="nav-item">
                <a
                  class="nav-link nav-icon-link<?= $currentPath === '/register.php' ? ' active' : '' ?>"
                  href="/register.php"
                  <?= $currentPath === '/register.php' ? 'aria-current="page"' : '' ?>
                  aria-label="Register"
                  title="Register">
                  <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                    <path d="M8 3a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Zm-3.5 2.5a3.5 3.5 0 1 0 7 0 3.5 3.5 0 0 0-7 0Zm-1 8a4.5 4.5 0 0 1 9 0 .5.5 0 0 1-1 0 3.5 3.5 0 0 0-7 0 .5.5 0 0 1-1 0Zm9-8a.5.5 0 0 1 .5-.5h1v-1a.5.5 0 0 1 1 0v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1-.5-.5Z" fill="currentColor" />
                  </svg>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>