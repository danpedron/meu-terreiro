<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/PasswordResetService.php';

$token = trim((string) ($_POST['token'] ?? ''));
$redirect = 'index.php?p=redefinir-senha&token=' . rawurlencode($token);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$csrfSession = $_SESSION['csrf_token'] ?? '';
$csrfPosted = $_POST['csrf_token'] ?? '';
if (!is_string($csrfPosted) || $csrfSession === '' || !hash_equals($csrfSession, $csrfPosted)) {
    $_SESSION['password_reset_error'] = 'Sua sessão expirou. Abra o link de recuperação novamente.';
    header('Location: ' . $redirect);
    exit;
}

$password = (string) ($_POST['password'] ?? '');
$confirmation = (string) ($_POST['password_confirmation'] ?? '');
if (strlen($password) < 12) {
    $_SESSION['password_reset_error'] = 'Crie uma senha com pelo menos 12 caracteres.';
    header('Location: ' . $redirect);
    exit;
}
if (!hash_equals($password, $confirmation)) {
    $_SESSION['password_reset_error'] = 'A confirmação da senha não confere.';
    header('Location: ' . $redirect);
    exit;
}

$success = false;
try {
    $service = new PasswordResetService(CentralDB::getInstance()->getConnection());
    $success = $service->reset($token, $password);
} catch (Throwable $e) {
    error_log('Meu Terreiro: falha no fluxo de redefinição de senha.');
}

if ($success) {
    $_SESSION['password_reset_success'] = 'Senha alterada com sucesso. Agora você já pode entrar com a nova senha.';
    header('Location: index.php?p=login');
    exit;
}

$_SESSION['password_reset_error'] = 'Este link é inválido, já foi utilizado ou expirou. Solicite uma nova recuperação de senha.';
header('Location: ' . $redirect);
exit;
