<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/CommunityService.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php?p=login');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash_error'] = 'Sua sessão expirou. Escolha a casa novamente.';
    header('Location: index.php?p=comunidade');
    exit;
}
$tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
if (!$tenantId) {
    $_SESSION['flash_error'] = 'Escolha uma casa válida.';
    header('Location: index.php?p=comunidade');
    exit;
}
$service = new CommunityService();
$selection = $service->selectMembership((int) $_SESSION['user_id'], $tenantId);
if (isset($selection['error'])) {
    $_SESSION['flash_error'] = $selection['error'];
    header('Location: index.php?p=comunidade');
    exit;
}
$service->log((int) $_SESSION['user_id'], (int) $selection['tenant_id'], 'casa_selecionada', 'tenants', (int) $selection['tenant_id'], $service->isGlobalAdmin((int) $_SESSION['user_id']) ? 'Contexto aberto pela administração global.' : 'Contexto aberto por vínculo ativo.');
$_SESSION['tenant_id'] = (int) $selection['tenant_id'];
$_SESSION['tenant_slug'] = (string) $selection['slug'];
$_SESSION['user_role'] = (string) $selection['role'];
$_SESSION['flash_success'] = 'Você está acessando a área da casa selecionada.';
header('Location: index.php?p=dashboard');
exit;
