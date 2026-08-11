<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/TenantManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?p=register');
    exit;
}

$csrfSession = $_SESSION['csrf_token'] ?? '';
$csrfPosted = $_POST['csrf_token'] ?? '';
if (!is_string($csrfPosted) || $csrfSession === '' || !hash_equals($csrfSession, $csrfPosted)) {
    $_SESSION['register_error'] = 'Sua sessão expirou. Revise o formulário e tente novamente.';
    header('Location: index.php?p=register');
    exit;
}

$password = $_POST['password'] ?? '';
$passwordConfirmation = $_POST['password_confirmation'] ?? '';
if (!is_string($password) || $password !== $passwordConfirmation) {
    $_SESSION['register_error'] = 'A confirmação de senha não corresponde à senha informada.';
    $_SESSION['register_old'] = $_POST;
    header('Location: index.php?p=register');
    exit;
}

$manager = new TenantManager();
$result = $manager->createTenantWithAdmin(
    (string) ($_POST['nome_terreiro'] ?? ''),
    (string) ($_POST['nacao'] ?? ''),
    (string) ($_POST['fundacao'] ?? ''),
    (string) ($_POST['nome_responsavel'] ?? ''),
    (string) ($_POST['email'] ?? ''),
    $password,
    isset($_POST['aceite_responsabilidade'])
);

if (isset($result['error'])) {
    $_SESSION['register_error'] = $result['error'];
    $_SESSION['register_old'] = $_POST;
    header('Location: index.php?p=register');
    exit;
}

$_SESSION['login_success'] = 'Terreiro cadastrado com sucesso. Entre com o e-mail e a senha do dirigente para concluir a configuração da casa.';
unset($_SESSION['register_old']);
header('Location: index.php?p=login');
exit;
