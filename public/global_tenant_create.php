<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/TenantManager.php';
require_once __DIR__ . '/../config/CommunityService.php';

function return_to_global_tenant_create(string $message, array $old = []): never
{
    $_SESSION['global_tenant_create_error'] = $message;
    $_SESSION['global_tenant_create_old'] = $old;
    header('Location: index.php?p=admin-global#admin-nova-casa');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$community = new CommunityService();
if ($userId <= 0 || !$community->isGlobalAdmin($userId)) {
    http_response_code(403);
    exit('Acesso não autorizado.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?p=admin-global#admin-nova-casa');
    exit;
}
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    return_to_global_tenant_create('Sua sessão expirou. Tente novamente.');
}

$old = [
    'nome_terreiro' => trim((string) ($_POST['nome_terreiro'] ?? '')),
    'nacao' => trim((string) ($_POST['nacao'] ?? '')),
    'fundacao' => trim((string) ($_POST['fundacao'] ?? '')),
    'cidade_publica' => trim((string) ($_POST['cidade_publica'] ?? '')),
    'estado_publico' => strtoupper(trim((string) ($_POST['estado_publico'] ?? ''))),
    'endereco_publico' => trim((string) ($_POST['endereco_publico'] ?? '')),
    'numero_publico' => trim((string) ($_POST['numero_publico'] ?? '')),
    'bairro_publico' => trim((string) ($_POST['bairro_publico'] ?? '')),
    'latitude_publica' => trim((string) ($_POST['latitude_publica'] ?? '')),
    'longitude_publica' => trim((string) ($_POST['longitude_publica'] ?? '')),
    'descricao_publica' => trim((string) ($_POST['descricao_publica'] ?? '')),
    'horarios_publicos' => trim((string) ($_POST['horarios_publicos'] ?? '')),
    'whatsapp_publico' => trim((string) ($_POST['whatsapp_publico'] ?? '')),
    'email_responsavel' => mb_strtolower(trim((string) ($_POST['email_responsavel'] ?? ''))),
];

$logoUpload = $_FILES['logo_publico'] ?? null;
$manager = new TenantManager();
$result = $manager->createTenantForGlobalAdmin(
    $userId,
    $old['nome_terreiro'],
    $old['nacao'],
    $old['fundacao'] ?: null,
    !empty($_POST['aceite_responsabilidade']),
    $old,
    $old['email_responsavel'] ?: null,
    $logoUpload
);
if (isset($result['error'])) {
    return_to_global_tenant_create($result['error'], $old);
}

unset($_SESSION['global_tenant_create_old'], $_SESSION['global_tenant_create_error']);
$_SESSION['flash_success'] = 'A casa foi criada pelo administrador global sem vínculo automático com sua conta. Ela já está disponível para configuração e aprovação de um dirigente.';
header('Location: index.php?p=admin-global#supervisao-casas');
exit;
