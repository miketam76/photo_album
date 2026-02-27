<?php require_once __DIR__ . '/templates/header.php'; ?>
<section class="landing-hero p-4 p-md-5 mb-3">
    <h1 class="mb-3">Welcome to Your Photo Album</h1>
    <p class="lead mb-4">Create albums, upload photos, and browse your memories in a clean, accessible gallery.</p>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($user)): ?>
            <a class="btn btn-primary" href="/albums.php">View Albums</a>
        <?php else: ?>
            <a class="btn btn-primary" href="/login.php">Login to Start</a>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/templates/footer.php'; ?>