<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../config/db_config.php';

$allowedPages = ['dashboard', 'filhos', 'agenda', 'financeiro', 'filhos_novo', 'agenda_novo'];
$page = $_GET['p'] ?? 'dashboard';
$page = in_array($page, $allowedPages, true) ? $page : 'dashboard';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#6b3f2a">
    <meta name="description" content="Administração simples e respeitosa para terreiros e comunidades de axé.">
    <title>Meu Terreiro - Administração</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<?php if (!isset($_SESSION['user_id'])): ?>
    <main class="mt-login-shell">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-7 col-lg-5">
                    <div class="card mt-login-card p-4 p-md-5">
                        <div class="text-center mb-4">
                            <span class="mt-stat-icon mb-3"><i class="fa-solid fa-hands-holding-circle fa-xl"></i></span>
                            <h1 class="h2 mt-page-title mb-2">Meu Terreiro</h1>
                            <p class="mt-subtitle mb-0">Cuidado, organização e memória para a sua casa.</p>
                        </div>
                        <form method="POST" action="auth.php" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="email">E-mail</label>
                                <input class="form-control form-control-lg" id="email" type="email" name="email" autocomplete="username" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="password">Senha</label>
                                <input class="form-control form-control-lg" id="password" type="password" name="password" autocomplete="current-password" required>
                            </div>
                            <button class="btn btn-primary btn-lg w-100" type="submit">Entrar na administração</button>
                        </form>
                    </div>
                    <p class="text-center mt-4 text-muted small">Acesso reservado às pessoas autorizadas pelo terreiro.</p>
                </div>
            </div>
        </div>
    </main>
<?php else: ?>
    <nav class="navbar mt-navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand mt-brand" href="?p=dashboard">
                <span class="mt-brand-mark" aria-hidden="true"><i class="fa-solid fa-leaf"></i></span>
                <span>Meu Terreiro</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="?p=filhos"><i class="fa-solid fa-people-group me-1"></i> Filhos</a></li>
                    <li class="nav-item"><a class="nav-link" href="?p=agenda"><i class="fa-solid fa-calendar-days me-1"></i> Agenda</a></li>
                    <li class="nav-item"><a class="nav-link" href="?p=financeiro"><i class="fa-solid fa-coins me-1"></i> Finanças</a></li>
                    <li class="nav-item"><a class="nav-link text-warning" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php
        $modulePath = __DIR__ . "/../modules/{$page}.php";
        if (is_file($modulePath)) {
            include $modulePath;
        } else {
            echo '<div class="card p-4"><h1 class="h3 mt-page-title">Em preparação</h1><p class="mb-0 mt-subtitle">Este módulo está sendo construído com cuidado. Enquanto isso, utilize o dashboard e o cadastro de filhos.</p></div>';
        }
        ?>
    </main>

    <nav class="mobile-nav" aria-label="Navegação principal">
        <a href="?p=dashboard"><i class="fa-solid fa-house"></i><span>Início</span></a>
        <a href="?p=filhos"><i class="fa-solid fa-people-group"></i><span>Filhos</span></a>
        <a href="?p=agenda"><i class="fa-solid fa-calendar-days"></i><span>Giras</span></a>
        <a href="?p=financeiro"><i class="fa-solid fa-coins"></i><span>Finanças</span></a>
    </nav>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
