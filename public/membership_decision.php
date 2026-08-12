<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/CommunityService.php';

if (empty($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?p=login');
    exit;
}
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash_error'] = 'Sua sessão expirou. Tente novamente.';
    header('Location: index.php?p=comunidade');
    exit;
}
$membershipId = filter_input(INPUT_POST, 'membership_id', FILTER_VALIDATE_INT);
$decision = (string) ($_POST['decision'] ?? '');
$returnPage = (string) ($_POST['return_page'] ?? 'comunidade');
if (!$membershipId || !in_array($decision, ['approve', 'reject'], true)) {
    $_SESSION['flash_error'] = 'A solicitação informada não é válida.';
    header('Location: index.php?p=' . rawurlencode($returnPage));
    exit;
}
$service = new CommunityService();
$result = $service->approveMembership((int) $_SESSION['user_id'], $membershipId, $decision === 'approve', (string) ($_POST['note'] ?? ''));
if (isset($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $_SESSION['flash_success'] = $decision === 'approve' ? 'Solicitação aprovada e registrada.' : 'Solicitação recusada e registrada.';
}
header('Location: index.php?p=' . rawurlencode(in_array($returnPage, ['comunidade','solicitacoes','admin-global'], true) ? $returnPage : 'comunidade'));
exit;
