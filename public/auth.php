<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$csrfSession = $_SESSION['csrf_token'] ?? '';
$csrfPosted = $_POST['csrf_token'] ?? '';
if ($csrfSession === '' || $csrfPosted === '' || !hash_equals($csrfSession, $csrfPosted)) {
    http_response_code(419);
    $error = 'Sua sessão expirou. Volte à tela de acesso e tente novamente.';
} else {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $db = CentralDB::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT u.*, t.slug FROM users u LEFT JOIN tenants t ON u.id_tenant = t.id WHERE u.email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['tenant_id'] = $user['id_tenant'];
        $_SESSION['tenant_slug'] = $user['slug'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: index.php');
        exit;
    }

    http_response_code(401);
    $error = 'E-mail ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso - Meu Terreiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="mt-login-shell">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-7 col-lg-5">
                <div class="card mt-login-card p-4 p-md-5">
                    <h1 class="h3 mt-page-title mb-3">Não foi possível entrar</h1>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <a class="btn btn-primary btn-lg w-100" href="index.php">Voltar à tela de acesso</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
