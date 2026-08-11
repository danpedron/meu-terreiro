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
$stmt = $db->prepare("SELECT u.*, t.slug, t.status AS tenant_status FROM users u LEFT JOIN tenants t ON u.id_tenant = t.id WHERE u.email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

$valid = $user && ($user['status'] ?? 'Ativo') === 'Ativo' && ($user['id_tenant'] === null || $user['tenant_status'] === 'Ativo') && password_verify($password, $user['password_hash']);
if ($valid) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['nome'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['tenant_id'] = $user['id_tenant'] ? (int) $user['id_tenant'] : null;
    $_SESSION['tenant_slug'] = $user['slug'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $db->prepare('UPDATE users SET ultimo_acesso_em = NOW() WHERE id = ?')->execute([(int) $user['id']]);
    header('Location: index.php?p=dashboard');
    exit;
}

$_SESSION['login_error'] = 'E-mail ou senha inválidos, ou acesso indisponível.';
header('Location: index.php?p=login');
exit;
