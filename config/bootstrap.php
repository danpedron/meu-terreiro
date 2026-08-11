<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/TenantManager.php';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    return is_string($posted) && is_string($stored) && $posted !== '' && hash_equals($stored, $posted);
}

function current_role(): string
{
    return $_SESSION['user_role'] ?? '';
}

function can_manage(): bool
{
    return in_array(current_role(), ['SuperAdmin', 'Regente', 'Secretario'], true);
}

function can_manage_finance(): bool
{
    return in_array(current_role(), ['SuperAdmin', 'Regente', 'Secretario', 'Financeiro'], true);
}

function can_manage_stock(): bool
{
    return in_array(current_role(), ['SuperAdmin', 'Regente', 'Secretario', 'Estoque'], true);
}

function audit(PDO $conn, string $action, string $table, ?int $recordId = null, ?string $details = null): void
{
    try {
        $stmt = $conn->prepare('INSERT INTO auditoria (usuario_central_id, acao, tabela_referencia, registro_id, detalhes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'] ?? null, $action, $table, $recordId, $details]);
    } catch (Throwable $e) {
        error_log('Auditoria indisponível: ' . $e->getMessage());
    }
}

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id']) || empty($_SESSION['tenant_slug'])) {
    header('Location: index.php?p=login');
    exit;
}

$tenantManager = new TenantManager();
$tenantConn = $tenantManager->getTenantConnection((string) $_SESSION['tenant_slug'], (int) $_SESSION['tenant_id']);
if (!$tenantConn) {
    http_response_code(403);
    exit('Acesso ao terreiro não autorizado ou indisponível.');
}

$csrfToken = csrf_token();
