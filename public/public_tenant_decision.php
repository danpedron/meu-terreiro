<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/CommunityService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}
if (!verify_csrf()) {
    flash('error', 'Sua sessão expirou. Tente novamente.');
    header('Location: index.php?p=admin-global');
    exit;
}
$community = new CommunityService();
$actorId = (int) ($_SESSION['user_id'] ?? 0);
$tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
$decision = (string) ($_POST['decision'] ?? '');
$note = trim((string) ($_POST['note'] ?? ''));
if (!$tenantId || !in_array($decision, ['approve', 'reject'], true)) {
    flash('error', 'A decisão informada não é válida.');
    header('Location: index.php?p=admin-global');
    exit;
}
$result = $community->decidePublicTenantSubmission($actorId, (int) $tenantId, $decision === 'approve', $note);
if (isset($result['error'])) {
    flash('error', $result['error']);
} elseif ($decision === 'approve') {
    flash('success', 'Centro aprovado e publicado no diretório. A pessoa responsável poderá criar uma conta com o mesmo e-mail e solicitar o vínculo de gestão.');
} else {
    flash('success', 'Cadastro de centro recusado e removido do diretório público.');
}
header('Location: index.php?p=admin-global');
exit;
