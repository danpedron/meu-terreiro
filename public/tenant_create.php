<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/TenantManager.php';

function return_to_create_tenant(string $message, array $old = []): never
{
    $_SESSION['tenant_create_error'] = $message;
    $_SESSION['tenant_create_old'] = $old;
    header('Location: index.php?p=nova-casa');
    exit;
}

if (empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Entre ou crie sua conta antes de cadastrar uma casa.';
    header('Location: index.php?p=login');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?p=nova-casa');
    exit;
}
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    return_to_create_tenant('Sua sessão expirou. Tente novamente.');
}

$old = [
    'nome_terreiro' => trim((string) ($_POST['nome_terreiro'] ?? '')),
    'nacao' => trim((string) ($_POST['nacao'] ?? '')),
    'fundacao' => trim((string) ($_POST['fundacao'] ?? '')),
    'cidade_publica' => trim((string) ($_POST['cidade_publica'] ?? '')),
    'estado_publico' => strtoupper(trim((string) ($_POST['estado_publico'] ?? ''))),
    'latitude_publica' => trim((string) ($_POST['latitude_publica'] ?? '')),
    'longitude_publica' => trim((string) ($_POST['longitude_publica'] ?? '')),
    'descricao_publica' => trim((string) ($_POST['descricao_publica'] ?? '')),
    'horarios_publicos' => trim((string) ($_POST['horarios_publicos'] ?? '')),
    'papel' => (string) ($_POST['papel'] ?? 'Colaborador'),
];
$manager = new TenantManager();
$result = $manager->createTenantForUser(
    (int) $_SESSION['user_id'],
    $old['nome_terreiro'],
    $old['nacao'],
    $old['fundacao'] ?: null,
    $old['papel'],
    !empty($_POST['aceite_responsabilidade']),
    $old
);
if (isset($result['error'])) {
    return_to_create_tenant($result['error'], $old);
}

$_SESSION['flash_success'] = !empty($result['leadership_pending'])
    ? 'A casa foi criada. Seu pedido de reconhecimento como dirigente foi encaminhado à administração global para análise.'
    : 'A casa foi criada e será exibida no diretório com a localização informada. Você pode ajustar ou ocultar o perfil público a qualquer momento.';
header('Location: index.php?p=comunidade');
exit;
