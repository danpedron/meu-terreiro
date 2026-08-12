<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/CommunityService.php';

function return_to_consultation_tenant(string $slug, string $message): never
{
    $_SESSION['public_error'] = $message;
    header('Location: terreiro.php?c=' . rawurlencode($slug));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: directory.php');
    exit;
}
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    return_to_consultation_tenant((string) ($_POST['tenant_slug'] ?? ''), 'Sua sessão expirou. Tente novamente.');
}
if (empty($_POST['aceite_contato'])) {
    return_to_consultation_tenant((string) ($_POST['tenant_slug'] ?? ''), 'Confirme que autoriza a casa a usar seus dados de contato apenas para responder a este pedido.');
}

$lastRequest = (int) ($_SESSION['consulta_ultimo_envio'] ?? 0);
if ($lastRequest > 0 && (time() - $lastRequest) < 60) {
    return_to_consultation_tenant((string) ($_POST['tenant_slug'] ?? ''), 'Aguarde um minuto antes de enviar outra solicitação.');
}

$service = new CommunityService();
$tenant = $service->getTenantForPublicDirectory((string) ($_POST['tenant_slug'] ?? ''));
if (!$tenant) {
    header('Location: directory.php');
    exit;
}
$result = $service->requestConsultation(
    isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
    (int) $tenant['id'],
    (string) ($_POST['nome_contato'] ?? ''),
    (string) ($_POST['whatsapp_contato'] ?? ''),
    (string) ($_POST['email_contato'] ?? ''),
    (string) ($_POST['disponibilidade'] ?? ''),
    (string) ($_POST['mensagem'] ?? '')
);
if (isset($result['error'])) {
    return_to_consultation_tenant((string) $tenant['slug'], $result['error']);
}
$_SESSION['consulta_ultimo_envio'] = time();
$_SESSION['public_success'] = 'Seu pedido foi enviado. A casa responderá pelos canais e horários que tiver definido.';
header('Location: terreiro.php?c=' . rawurlencode((string) $tenant['slug']));
exit;
