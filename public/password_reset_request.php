<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/PasswordResetService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?p=recuperar-senha');
    exit;
}

$csrfSession = $_SESSION['csrf_token'] ?? '';
$csrfPosted = $_POST['csrf_token'] ?? '';
if (!is_string($csrfPosted) || $csrfSession === '' || !hash_equals($csrfSession, $csrfPosted)) {
    $_SESSION['password_reset_error'] = 'Sua sessão expirou. Tente solicitar a recuperação novamente.';
    header('Location: index.php?p=recuperar-senha');
    exit;
}

$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
$resetUrl = $scheme . '://' . $host . ($basePath === '' || $basePath === '.' ? '' : $basePath) . '/index.php?p=redefinir-senha';

try {
    $service = new PasswordResetService(CentralDB::getInstance()->getConnection());
    $service->request($email, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $resetUrl);
} catch (Throwable $e) {
    error_log('Meu Terreiro: falha no fluxo de solicitação de recuperação de senha.');
}

$_SESSION['password_reset_message'] = 'Se houver uma conta ativa com esse e-mail, enviaremos instruções para criar uma nova senha. Verifique também a pasta de spam.';
header('Location: index.php?p=recuperar-senha');
exit;
