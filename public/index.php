<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }

$publicPages = ['login', 'register'];
$resourcePages = ['filhos','agenda','obrigacoes_tipo','registros_obrigacoes','entidades','mensalidades','financeiro','estoque','movimentacoes_estoque','fornecedores','compras','preparos','oferendas','tarefas','patrimonio','biblioteca','album','locais','comunicados','incidentes'];
$page = $_GET['p'] ?? (isset($_SESSION['user_id']) ? 'dashboard' : 'login');
if (!in_array($page, array_merge($publicPages, $resourcePages, ['dashboard', 'configuracoes']), true)) {
    $page = isset($_SESSION['user_id']) ? 'dashboard' : 'login';
}
if (isset($_SESSION['user_id']) && in_array($page, $publicPages, true)) {
    $page = 'dashboard';
}
if (!isset($_SESSION['user_id']) && !in_array($page, $publicPages, true)) {
    $page = 'login';
}

$loginSuccess = $_SESSION['login_success'] ?? null;
unset($_SESSION['login_success']);
$loginError = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
$registerError = $_SESSION['register_error'] ?? null;
unset($_SESSION['register_error']);
$registerOld = $_SESSION['register_old'] ?? [];
unset($_SESSION['register_old']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#5a3324">
    <meta name="description" content="Administração segura, simples e respeitosa para terreiros e comunidades de axé.">
    <title>Meu Terreiro — Administração da Casa</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<?php if (!isset($_SESSION['user_id'])): ?>
    <main class="mt-login-shell">
        <div class="container py-4 py-md-5">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-10">
                    <section class="row g-0 mt-access-card shadow-sm">
                        <div class="col-12 col-md-5 mt-access-intro p-4 p-md-5">
                            <span class="mt-stat-icon mb-4"><i class="fa-solid fa-hands-holding-circle fa-xl"></i></span>
                            <p class="mt-eyebrow text-white-50">Administração com respeito</p>
                            <h1 class="display-6 fw-bold">Meu Terreiro</h1>
                            <p class="lead mb-4">Organização para que a casa tenha mais tempo para acolher, cuidar e preservar sua memória.</p>
                            <ul class="list-unstyled small mb-0">
                                <li class="mb-2"><i class="fa-solid fa-lock me-2"></i>Dados isolados por terreiro</li>
                                <li class="mb-2"><i class="fa-solid fa-mobile-screen-button me-2"></i>Funciona bem no celular</li>
                                <li><i class="fa-solid fa-heart me-2"></i>Conhecimentos protegidos pela casa</li>
                            </ul>
                        </div>
                        <div class="col-12 col-md-7 bg-white p-4 p-md-5">
                            <?php if ($page === 'register'): ?>
                                <span class="mt-eyebrow">Começar agora</span>
                                <h2 class="h2 mt-page-title mb-2">Cadastrar novo terreiro</h2>
                                <p class="mt-subtitle">A pessoa responsável será criada como dirigente e a casa receberá um banco de dados próprio.</p>
                                <?php if ($registerError): ?><div class="alert alert-danger" role="alert"><?php echo e($registerError); ?></div><?php endif; ?>
                                <form method="post" action="register.php" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                                    <div class="row g-3">
                                        <div class="col-12"><label class="form-label fw-semibold" for="nome_terreiro">Nome do terreiro <span class="text-danger">*</span></label><input class="form-control form-control-lg" id="nome_terreiro" name="nome_terreiro" value="<?php echo e($registerOld['nome_terreiro'] ?? ''); ?>" required></div>
                                        <div class="col-12 col-sm-6"><label class="form-label fw-semibold" for="nacao">Nação ou tradição</label><input class="form-control" id="nacao" name="nacao" value="<?php echo e($registerOld['nacao'] ?? ''); ?>" placeholder="Conforme a casa se identifica"></div>
                                        <div class="col-12 col-sm-6"><label class="form-label fw-semibold" for="fundacao">Fundação (opcional)</label><input class="form-control" id="fundacao" type="date" name="fundacao" value="<?php echo e($registerOld['fundacao'] ?? ''); ?>"></div>
                                        <div class="col-12"><label class="form-label fw-semibold" for="nome_responsavel">Nome da pessoa responsável <span class="text-danger">*</span></label><input class="form-control" id="nome_responsavel" name="nome_responsavel" value="<?php echo e($registerOld['nome_responsavel'] ?? ''); ?>" required></div>
                                        <div class="col-12"><label class="form-label fw-semibold" for="email_cadastro">E-mail de acesso <span class="text-danger">*</span></label><input class="form-control" id="email_cadastro" type="email" name="email" autocomplete="email" value="<?php echo e($registerOld['email'] ?? ''); ?>" required></div>
                                        <div class="col-12 col-sm-6"><label class="form-label fw-semibold" for="password_cadastro">Senha <span class="text-danger">*</span></label><input class="form-control" id="password_cadastro" type="password" name="password" autocomplete="new-password" minlength="12" required><div class="form-text">Use pelo menos 12 caracteres.</div></div>
                                        <div class="col-12 col-sm-6"><label class="form-label fw-semibold" for="password_confirmation">Confirmar senha <span class="text-danger">*</span></label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></div>
                                        <div class="col-12"><div class="form-check"><input class="form-check-input" id="aceite_responsabilidade" type="checkbox" name="aceite_responsabilidade" value="1" required><label class="form-check-label" for="aceite_responsabilidade">Confirmo que tenho autorização para cadastrar a casa e que os dados inseridos serão protegidos conforme as decisões e regras do terreiro.</label></div></div>
                                    </div>
                                    <div class="d-grid gap-2 d-sm-flex mt-4"><button class="btn btn-primary btn-lg" type="submit"><i class="fa-solid fa-house-circle-check me-2"></i>Cadastrar minha casa</button><a class="btn btn-outline-secondary btn-lg" href="?p=login">Já tenho acesso</a></div>
                                </form>
                            <?php else: ?>
                                <span class="mt-eyebrow">Acesso da casa</span>
                                <h2 class="h2 mt-page-title mb-2">Entrar na administração</h2>
                                <p class="mt-subtitle">Use o seu e-mail e senha. O acesso é reservado às pessoas autorizadas.</p>
                                <?php if ($loginSuccess): ?><div class="alert alert-success" role="status"><?php echo e($loginSuccess); ?></div><?php endif; ?>
                                <?php if ($loginError): ?><div class="alert alert-danger" role="alert"><?php echo e($loginError); ?></div><?php endif; ?>
                                <form method="post" action="auth.php" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                                    <div class="mb-3"><label class="form-label fw-semibold" for="email">E-mail</label><input class="form-control form-control-lg" id="email" type="email" name="email" autocomplete="username" required></div>
                                    <div class="mb-4"><label class="form-label fw-semibold" for="password">Senha</label><input class="form-control form-control-lg" id="password" type="password" name="password" autocomplete="current-password" required></div>
                                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="fa-solid fa-right-to-bracket me-2"></i>Entrar</button>
                                </form>
                                <hr class="my-4"><p class="mb-2 fw-semibold">Ainda não usa o Meu Terreiro?</p><a class="btn btn-outline-primary btn-lg w-100" href="?p=register"><i class="fa-solid fa-plus me-2"></i>Cadastrar novo terreiro</a>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>
<?php else: ?>
    <nav class="navbar mt-navbar navbar-expand-xl navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand mt-brand" href="?p=dashboard"><span class="mt-brand-mark" aria-hidden="true"><i class="fa-solid fa-leaf"></i></span><span>Meu Terreiro</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-xl-center gap-xl-1">
                    <li class="nav-item"><a class="nav-link" href="?p=dashboard"><i class="fa-solid fa-house me-1"></i>Início</a></li><li class="nav-item"><a class="nav-link" href="?p=configuracoes"><i class="fa-solid fa-gear me-1"></i>Casa</a></li>
                    <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Pessoas</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="?p=filhos">Filhos de santo</a></li><li><a class="dropdown-item" href="?p=entidades">Entidades</a></li><li><a class="dropdown-item" href="?p=obrigacoes_tipo">Tipos de obrigações</a></li><li><a class="dropdown-item" href="?p=registros_obrigacoes">Histórico de obrigações</a></li></ul></li>
                    <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Rotina</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="?p=agenda">Agenda</a></li><li><a class="dropdown-item" href="?p=tarefas">Tarefas da casa</a></li><li><a class="dropdown-item" href="?p=comunicados">Comunicados</a></li><li><a class="dropdown-item" href="?p=preparos">Cozinha e preparos</a></li><li><a class="dropdown-item" href="?p=oferendas">Registros de oferendas</a></li></ul></li>
                    <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Gestão</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="?p=financeiro">Financeiro</a></li><li><a class="dropdown-item" href="?p=mensalidades">Mensalidades</a></li><li><a class="dropdown-item" href="?p=estoque">Estoque</a></li><li><a class="dropdown-item" href="?p=movimentacoes_estoque">Movimentações</a></li><li><a class="dropdown-item" href="?p=compras">Compras</a></li><li><a class="dropdown-item" href="?p=fornecedores">Fornecedores</a></li><li><a class="dropdown-item" href="?p=patrimonio">Patrimônio</a></li></ul></li>
                    <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Memória e cuidado</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="?p=biblioteca">Biblioteca</a></li><li><a class="dropdown-item" href="?p=album">Álbum de memória</a></li><li><a class="dropdown-item" href="?p=locais">Locais e referências</a></li><li><a class="dropdown-item" href="?p=incidentes">Segurança e ocorrências</a></li></ul></li>
                    <li class="nav-item"><a class="nav-link text-warning" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container pb-5">
        <?php
        if ($page === 'dashboard') {
            include __DIR__ . '/../modules/dashboard.php';
        } elseif ($page === 'configuracoes') {
            include __DIR__ . '/../modules/configuracoes.php';
        } elseif (in_array($page, $resourcePages, true)) {
            $resourceKey = $page;
            include __DIR__ . '/../modules/resource.php';
        }
        ?>
    </main>
    <nav class="mobile-nav" aria-label="Navegação principal"><a href="?p=dashboard"><i class="fa-solid fa-house"></i><span>Início</span></a><a href="?p=filhos"><i class="fa-solid fa-people-group"></i><span>Pessoas</span></a><a href="?p=agenda"><i class="fa-solid fa-calendar-days"></i><span>Agenda</span></a><a href="?p=estoque"><i class="fa-solid fa-boxes-stacked"></i><span>Estoque</span></a><a href="?p=financeiro"><i class="fa-solid fa-coins"></i><span>Gestão</span></a></nav>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
