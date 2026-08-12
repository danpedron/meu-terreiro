<?php
declare(strict_types=1);

$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/CommunityService.php';

$redirect = 'index.php?p=admin-global';
if (empty($_SESSION['user_id'])) {
    header('Location: index.php?p=login');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash_error'] = 'Sua sessão expirou. Faça login novamente antes de moderar um centro.';
    header('Location: ' . $redirect);
    exit;
}

$tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
$action = trim((string) ($_POST['action'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));
$confirmation = trim((string) ($_POST['confirmation'] ?? ''));

if (!$tenantId || !in_array($action, ['hide', 'publish', 'suspend', 'reactivate', 'delete'], true)) {
    $_SESSION['flash_error'] = 'Centro ou ação de moderação inválidos.';
    header('Location: ' . $redirect);
    exit;
}

$service = new CommunityService();
$result = $service->moderateTenant((int) $_SESSION['user_id'], (int) $tenantId, $action, $reason, $confirmation);
if (isset($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $messages = [
        'hide' => 'O centro foi ocultado do diretório público.',
        'publish' => 'O centro voltou a aparecer no diretório público.',
        'suspend' => 'O centro foi suspenso e retirado do diretório.',
        'reactivate' => 'O centro foi reativado e publicado no diretório.',
        'delete' => 'O centro foi excluído definitivamente após a criação do backup.',
    ];
    $_SESSION['flash_success'] = $messages[$action];
    if ($action === 'delete' && (int) ($_SESSION['tenant_id'] ?? 0) === (int) $tenantId) {
        unset($_SESSION['tenant_id'], $_SESSION['tenant_slug'], $_SESSION['user_role']);
    }
}

header('Location: ' . $redirect);
exit;
