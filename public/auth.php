<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?p=login');
    exit;
}

$csrfSession = $_SESSION['csrf_token'] ?? '';
$csrfPosted = $_POST['csrf_token'] ?? '';
if (!is_string($csrfPosted) || $csrfSession === '' || !hash_equals($csrfSession, $csrfPosted)) {
    $_SESSION['login_error'] = 'Sua sessão expirou. Tente entrar novamente.';
    header('Location: index.php?p=login');
    exit;
}

$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$db = CentralDB::getInstance()->getConnection();
$stmt = $db->prepare('SELECT id, nome, email, password_hash, role, global_role, status FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

$valid = $user && ($user['status'] ?? 'Inativo') === 'Ativo' && password_verify($password, $user['password_hash']);
if ($valid) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['nome'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['global_role'] = $user['global_role'] ?? 'Usuario';
    unset($_SESSION['tenant_id'], $_SESSION['tenant_slug']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $db->prepare('UPDATE users SET ultimo_acesso_em = NOW() WHERE id = ?')->execute([(int) $user['id']]);
    $_SESSION['login_success'] = 'Entrada realizada. Escolha uma casa, encontre uma perto de você ou cadastre uma nova.';

    $afterLogin = (string) ($_SESSION['after_login'] ?? '');
    unset($_SESSION['after_login']);
    if (str_starts_with($afterLogin, 'membership:')) {
        $slug = substr($afterLogin, strlen('membership:'));
        header('Location: terreiro.php?c=' . rawurlencode($slug));
    } else {
        header('Location: index.php?p=comunidade');
    }
    exit;
}

$_SESSION['login_error'] = 'E-mail ou senha inválidos, ou acesso indisponível.';
header('Location: index.php?p=login');
exit;
