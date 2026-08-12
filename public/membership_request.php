<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/CommunityService.php';

function return_to_tenant_page(string $slug, string $message): never
{
    $_SESSION['public_error'] = $message;
    header('Location: terreiro.php?c=' . rawurlencode($slug));
    exit;
}

if (empty($_SESSION['user_id'])) {
    $_SESSION['after_login'] = 'membership:' . (string) ($_POST['tenant_slug'] ?? '');
    $_SESSION['login_error'] = 'Entre ou crie uma conta antes de solicitar vínculo com uma casa.';
    header('Location: index.php?p=login');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: directory.php');
    exit;
}
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    return_to_tenant_page((string) ($_POST['tenant_slug'] ?? ''), 'Sua sessão expirou. Tente novamente.');
}
if (empty($_POST['aceite_vinculo'])) {
    return_to_tenant_page((string) ($_POST['tenant_slug'] ?? ''), 'Confirme que concorda em enviar a solicitação e seus dados básicos de contato à casa.');
}

$service = new CommunityService();
$tenant = $service->getTenantForPublicDirectory((string) ($_POST['tenant_slug'] ?? ''));
if (!$tenant) {
    header('Location: directory.php');
    exit;
}
$result = $service->requestMembership(
    (int) $_SESSION['user_id'],
    (int) $tenant['id'],
    (string) ($_POST['papel'] ?? ''),
    (string) ($_POST['mensagem'] ?? '')
);
if (isset($result['error'])) {
    return_to_tenant_page((string) $tenant['slug'], $result['error']);
}
$_SESSION['public_success'] = ($result['status'] ?? '') === 'PendenteAdminGlobal'
    ? 'Seu pedido de reconhecimento como dirigente foi recebido e seguirá para análise da administração global.'
    : 'Sua solicitação foi enviada à casa. Você será avisado quando houver uma decisão.';
header('Location: terreiro.php?c=' . rawurlencode((string) $tenant['slug']));
exit;
