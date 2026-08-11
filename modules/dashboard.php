<?php
if (!isset($_SESSION['user_id'])) {
    exit;
}

require_once __DIR__ . '/../config/TenantManager.php';
$tenantManager = new TenantManager();
$tenantConn = $tenantManager->getTenantConnection($_SESSION['tenant_slug']);

$countFilhos = (int) $tenantConn->query("SELECT COUNT(*) FROM filhos WHERE status = 'Ativo'")->fetchColumn();
$countAgenda = (int) $tenantConn->query("SELECT COUNT(*) FROM agenda WHERE data_hora >= NOW()")->fetchColumn();
?>

<section class="mb-4">
    <h1 class="mt-page-title display-6">Olá, <?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?>.</h1>
    <p class="mt-subtitle lead mb-0">Que este espaço ajude a cuidar da rotina da sua casa com simplicidade e respeito.</p>
</section>

<section class="row g-4" aria-label="Resumo da administração">
    <div class="col-12 col-md-4">
        <div class="card mt-stat-card p-3">
            <div class="card-body">
                <span class="mt-stat-icon mb-3"><i class="fa-solid fa-people-group fa-xl"></i></span>
                <h2 class="h5 mt-page-title">Filhos ativos</h2>
                <p class="display-5 fw-bold mb-3"><?php echo $countFilhos; ?></p>
                <a href="?p=filhos" class="btn btn-outline-primary w-100">Ver cadastro</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card mt-stat-card mt-stat-green p-3">
            <div class="card-body">
                <span class="mt-stat-icon mb-3"><i class="fa-solid fa-calendar-days fa-xl"></i></span>
                <h2 class="h5 mt-page-title">Próximos compromissos</h2>
                <p class="display-5 fw-bold mb-3"><?php echo $countAgenda; ?></p>
                <a href="?p=agenda" class="btn btn-outline-success w-100">Abrir agenda</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card mt-stat-card mt-stat-blue p-3">
            <div class="card-body">
                <span class="mt-stat-icon mb-3"><i class="fa-solid fa-coins fa-xl"></i></span>
                <h2 class="h5 mt-page-title">Organização financeira</h2>
                <p class="lead fw-semibold mb-3">Mensalidades e registros</p>
                <a href="?p=financeiro" class="btn btn-outline-primary w-100">Gerenciar</a>
            </div>
        </div>
    </div>
</section>

<section class="card p-4 mt-5">
    <h2 class="h4 mt-page-title"><i class="fa-solid fa-compass me-2"></i>Atalhos do dia</h2>
    <p class="mt-subtitle">Acesse rapidamente as tarefas mais comuns da secretaria.</p>
    <div class="d-grid gap-2 d-md-flex">
        <a href="?p=filhos_novo" class="btn btn-primary btn-lg"><i class="fa-solid fa-user-plus me-2"></i>Novo filho</a>
        <a href="?p=agenda_novo" class="btn btn-outline-primary btn-lg"><i class="fa-solid fa-calendar-plus me-2"></i>Nova gira</a>
    </div>
</section>
