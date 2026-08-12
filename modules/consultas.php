<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/CommunityService.php';
$community = new CommunityService();
$tenantId = (int) $_SESSION['tenant_id'];
if (!$community->canManageTenant((int) $_SESSION['user_id'], $tenantId)) {
    http_response_code(403);
    echo '<div class="alert alert-warning">Somente a dirigência ou a administração global pode consultar estes pedidos.</div>';
    return;
}
$requests = $community->listConsultationRequests((int) $_SESSION['user_id'], $tenantId);
?>
<section class="mb-4"><span class="mt-eyebrow">Acolhimento</span><h1 class="mt-page-title">Pedidos de consulta</h1><p class="mt-subtitle">Informações enviadas pelo diretório público. Utilize apenas para retorno e acolhimento; não registre fundamentos ou dados íntimos em canais públicos.</p></section>
<section class="card border-0 shadow-sm"><div class="card-body p-4"><?php if (!$requests): ?><p class="text-muted mb-0">Ainda não há pedidos de consulta para esta casa.</p><?php else: ?><div class="vstack gap-3"><?php foreach ($requests as $request): ?><article class="border rounded-4 p-3"><div class="d-flex flex-column flex-md-row justify-content-between gap-3"><div><span class="badge text-bg-<?php echo $request['status'] === 'Pendente' ? 'warning' : 'secondary'; ?> mb-2"><?php echo e($request['status']); ?></span><h2 class="h5 mb-1"><?php echo e($request['nome_contato']); ?></h2><p class="small mb-1"><?php echo $request['whatsapp_contato'] ? 'WhatsApp: ' . e($request['whatsapp_contato']) : ''; ?><?php echo ($request['whatsapp_contato'] && $request['email_contato']) ? ' · ' : ''; ?><?php echo $request['email_contato'] ? 'E-mail: ' . e($request['email_contato']) : ''; ?></p><?php if ($request['disponibilidade']): ?><p class="mb-1"><strong>Disponibilidade:</strong> <?php echo e($request['disponibilidade']); ?></p><?php endif; ?><?php if ($request['mensagem']): ?><p class="mb-1"><strong>Mensagem:</strong> <?php echo nl2br(e($request['mensagem'])); ?></p><?php endif; ?><p class="small text-muted mb-0">Enviado em <?php echo e(date('d/m/Y H:i', strtotime($request['solicitado_em']))); ?></p></div><?php if ($request['whatsapp_contato']): ?><a class="btn btn-success align-self-md-start" target="_blank" rel="noopener" href="https://wa.me/<?php echo e($request['whatsapp_contato']); ?>"><i class="fa-brands fa-whatsapp me-2"></i>Responder</a><?php elseif ($request['email_contato']): ?><a class="btn btn-outline-primary align-self-md-start" href="mailto:<?php echo rawurlencode($request['email_contato']); ?>"><i class="fa-regular fa-envelope me-2"></i>Responder</a><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?></div></section>
