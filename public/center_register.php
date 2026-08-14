<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/TenantManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}
if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
    $_SESSION['public_center_error'] = 'Sua sessão expirou. Tente novamente.';
    header('Location: index.php?p=cadastrar-centro');
    exit;
}
$old = [
    'responsavel_nome' => trim((string) ($_POST['responsavel_nome'] ?? '')),
    'responsavel_email' => trim((string) ($_POST['responsavel_email'] ?? '')),
    'nome_terreiro' => trim((string) ($_POST['nome_terreiro'] ?? '')),
    'nacao' => trim((string) ($_POST['nacao'] ?? '')),
    'fundacao' => trim((string) ($_POST['fundacao'] ?? '')),
    'cidade_publica' => trim((string) ($_POST['cidade_publica'] ?? '')),
    'estado_publico' => strtoupper(trim((string) ($_POST['estado_publico'] ?? ''))),
    'latitude_publica' => trim((string) ($_POST['latitude_publica'] ?? '')),
    'longitude_publica' => trim((string) ($_POST['longitude_publica'] ?? '')),
    'descricao_publica' => trim((string) ($_POST['descricao_publica'] ?? '')),
    'horarios_publicos' => trim((string) ($_POST['horarios_publicos'] ?? '')),
];
$logoUpload = $_FILES['logo_publico'] ?? null;
$manager = new TenantManager();
$result = $manager->createPublicTenantSubmission(
    $old['responsavel_nome'],
    $old['responsavel_email'],
    $old['nome_terreiro'],
    $old['nacao'],
    $old['fundacao'] ?: null,
    $old,
    !empty($_POST['autoriza_cadastro']),
    $logoUpload
);
if (isset($result['error'])) {
    $_SESSION['public_center_error'] = $result['error'];
    $_SESSION['public_center_old'] = $old;
    header('Location: index.php?p=cadastrar-centro');
    exit;
}
unset($_SESSION['public_center_old']);
$_SESSION['public_center_success'] = 'Cadastro enviado para análise da administração global. Após a aprovação, o centro será publicado no diretório. Para administrar a casa, crie uma conta com este mesmo e-mail e solicite o vínculo de gestão.';
header('Location: index.php?p=cadastrar-centro');
exit;
