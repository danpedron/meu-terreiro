<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db_config.php';

function return_to_register(string $message, array $old = []): never
{
    $_SESSION['account_register_error'] = $message;
    $_SESSION['account_register_old'] = $old;
    header('Location: index.php?p=cadastro');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?p=cadastro');
    exit;
}
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    return_to_register('Sua sessão expirou. Tente novamente.');
}

$old = [
    'nome' => trim((string) ($_POST['nome'] ?? '')),
    'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
    'whatsapp' => trim((string) ($_POST['whatsapp'] ?? '')),
    'cidade' => trim((string) ($_POST['cidade'] ?? '')),
    'estado' => strtoupper(trim((string) ($_POST['estado'] ?? ''))),
];
$password = (string) ($_POST['password'] ?? '');
$confirmation = (string) ($_POST['password_confirmation'] ?? '');
$phoneDigits = preg_replace('/\D+/', '', $old['whatsapp']) ?? '';

if (mb_strlen($old['nome']) < 3 || mb_strlen($old['nome']) > 255) {
    return_to_register('Informe seu nome com pelo menos 3 caracteres.', $old);
}
if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
    return_to_register('Informe um e-mail válido.', $old);
}
if (strlen($password) < 12) {
    return_to_register('Crie uma senha com pelo menos 12 caracteres.', $old);
}
if (!hash_equals($password, $confirmation)) {
    return_to_register('A confirmação de senha não confere.', $old);
}
if (empty($_POST['aceite_termos'])) {
    return_to_register('Você precisa aceitar as regras de uso e privacidade para criar uma conta.', $old);
}
if ($old['estado'] !== '' && !preg_match('/^[A-Z]{2}$/', $old['estado'])) {
    return_to_register('Informe a sigla do estado com duas letras.', $old);
}

try {
    $db = CentralDB::getInstance()->getConnection();
    $existing = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$old['email']]);
    if ($existing->fetch()) {
        return_to_register('Este e-mail já possui uma conta. Use a tela de entrada.');
    }
    $db->beginTransaction();
    $userStmt = $db->prepare(
        "INSERT INTO users (nome, email, password_hash, role, global_role, whatsapp, status, termos_aceitos_em)
         VALUES (?, ?, ?, 'Leitor', 'Usuario', ?, 'Ativo', NOW())"
    );
    $userStmt->execute([$old['nome'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $phoneDigits ?: null]);
    $userId = (int) $db->lastInsertId();
    $profileStmt = $db->prepare('INSERT INTO user_profiles (user_id, cidade, estado) VALUES (?, ?, ?)');
    $profileStmt->execute([$userId, $old['cidade'] ?: null, $old['estado'] ?: null]);
    $audit = $db->prepare("INSERT INTO central_audit_log (actor_user_id, action, reference_type, reference_id, details) VALUES (?, 'conta_criada', 'users', ?, 'Cadastro global de participante.')");
    $audit->execute([$userId, $userId]);
    $db->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $old['nome'];
    $_SESSION['user_role'] = 'Leitor';
    $_SESSION['global_role'] = 'Usuario';
    unset($_SESSION['tenant_id'], $_SESSION['tenant_slug']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['flash_success'] = 'Sua conta foi criada. Agora você pode procurar uma casa, solicitar vínculo ou cadastrar uma nova casa.';
    header('Location: index.php?p=comunidade');
    exit;
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Falha no cadastro de participante: ' . $e->getMessage());
    return_to_register('Não foi possível criar sua conta agora. Tente novamente.', $old);
}
