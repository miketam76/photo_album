<?php
// header.php - renders nav and starts session
require_once __DIR__ . '/../src/auth.php';
use App\Auth;
Auth::startSession();
$user = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-4">
  <nav class="navbar navbar-expand-lg navbar-dark mb-3">
    <div class="container-fluid">
      <a class="navbar-brand text-light" href="/">Photo-App</a>
      <div class="collapse navbar-collapse">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link text-light" href="/albums.php">Albums</a></li>
        </ul>
        <ul class="navbar-nav ms-auto">
<?php if ($user): ?>
          <li class="nav-item"><span class="nav-link">Hello, <?= htmlspecialchars($user['email'] ?? 'user') ?></span></li>
          <li class="nav-item"><a class="nav-link" href="/logout.php">Logout</a></li>
<?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="/register.php">Register</a></li>
<?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>