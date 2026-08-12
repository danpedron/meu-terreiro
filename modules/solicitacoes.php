<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/CommunityService.php';
$community = new CommunityService();
$tenantId = (int) $_SESSION['tenant_id'];
if (!$community->canManageTenant((int) $_SESSION['user_id'], $tenantId)) {
    http_response_code(403);
    echo '<div class="alert alert-warning">Somente a dirigência ou a administração global pode analisar solicitações.</div>';
    return;
}
$pending = $community->listPendingMembershipsForTenant($tenantId, $community->isGlobalAdmin((int) $_SESSION['user_id']));
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
?>
<section class="mb-4"><span class="mt-eyebrow">Comunidade da casa</span><h1 class="mt-page-title">Solicitações de participação</h1><p class="mt-subtitle">Avalie cada pedido com cuidado. A aprovação libera apenas o papel solicitado, e informações religiosas sensíveis continuam restritas às regras da casa.</p></section>
<?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
<section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4 mb-3">Pendentes</h2><?php if (!$pending): ?><p class="text-muted mb-0">Não há solicitações pendentes nesta casa.</p><?php else: ?><div class="vstack gap-3"><?php foreach ($pending as $request): ?><article class="border rounded-4 p-3"><div class="d-flex flex-column flex-md-row justify-content-between gap-2"><div><h3 class="h5 mb-1"><?php echo e($request['nome']); ?></h3><p class="mb-1"><span class="badge text-bg-warning"><?php echo e($request['papel']); ?></span> <span class="small text-muted">Recebida em <?php echo e(date('d/m/Y H:i', strtotime($request['solicitado_em']))); ?></span></p><p class="small mb-1"><?php echo e($request['email']); ?><?php echo $request['whatsapp'] ? ' · ' . e($request['whatsapp']) : ''; ?></p><?php if ($request['solicitacao']): ?><p class="mb-0"><strong>Mensagem:</strong> <?php echo nl2br(e($request['solicitacao'])); ?></p><?php endif; ?></div><form class="mt-decision-form" method="post" action="membership_decision.php"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="membership_id" value="<?php echo (int) $request['id']; ?>"><input type="hidden" name="return_page" value="solicitacoes"><label class="visually-hidden" for="note-<?php echo (int) $request['id']; ?>">Observação de decisão</label><input class="form-control form-control-sm mb-2" id="note-<?php echo (int) $request['id']; ?>" name="note" maxlength="1000" placeholder="Observação opcional"><div class="d-flex gap-2"><button class="btn btn-success btn-sm" name="decision" value="approve" type="submit">Aprovar</button><button class="btn btn-outline-danger btn-sm" name="decision" value="reject" type="submit">Recusar</button></div></form></div></article><?php endforeach; ?></div><?php endif; ?></div></section>
