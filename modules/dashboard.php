<?php
require_once __DIR__ . '/../config/bootstrap.php';

function dashboard_count(PDO $conn, string $sql, array $params = []): int
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$filhosAtivos = dashboard_count($tenantConn, "SELECT COUNT(*) FROM filhos WHERE status = 'Ativo'");
$proximosEventos = dashboard_count($tenantConn, "SELECT COUNT(*) FROM agenda WHERE data_hora >= NOW() AND status IN ('Planejado','Confirmado')");
$tarefasPendentes = dashboard_count($tenantConn, "SELECT COUNT(*) FROM tarefas_casa WHERE status IN ('Pendente','Em andamento')");
$estoqueBaixo = dashboard_count($tenantConn, 'SELECT COUNT(*) FROM itens_estoque WHERE ativo = 1 AND quantidade_atual <= estoque_minimo');
$saldoMes = dashboard_count($tenantConn, "SELECT COALESCE(SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE -valor END),0) FROM lancamentos_financeiros WHERE DATE_FORMAT(data_lancamento, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$eventos = $tenantConn->query("SELECT titulo, tipo, data_hora, status FROM agenda WHERE data_hora >= NOW() AND status IN ('Planejado','Confirmado') ORDER BY data_hora ASC LIMIT 5")->fetchAll();
$comunicados = $tenantConn->query("SELECT titulo, mensagem, publico FROM comunicados WHERE expira_em IS NULL OR expira_em >= NOW() ORDER BY publicado_em DESC LIMIT 3")->fetchAll();
?>
<section class="mb-4">
    <span class="mt-eyebrow">Painel da casa</span>
    <h1 class="mt-page-title display-6">Olá, <?php echo e($_SESSION['user_name']); ?>.</h1>
    <p class="mt-subtitle lead mb-0">Uma visão simples da rotina, preservando a autonomia e os cuidados da sua casa.</p>
</section>

<section class="row g-3" aria-label="Resumo da administração">
    <div class="col-6 col-lg"><article class="card mt-stat-card h-100 p-3"><span class="mt-stat-icon"><i class="fa-solid fa-people-group"></i></span><p class="mt-stat-label">Filhos ativos</p><p class="display-6 fw-bold mb-0"><?php echo $filhosAtivos; ?></p><a href="?p=filhos" class="stretched-link"><span class="visually-hidden">Abrir filhos de santo</span></a></article></div>
    <div class="col-6 col-lg"><article class="card mt-stat-card mt-stat-green h-100 p-3"><span class="mt-stat-icon"><i class="fa-solid fa-calendar-days"></i></span><p class="mt-stat-label">Próximos eventos</p><p class="display-6 fw-bold mb-0"><?php echo $proximosEventos; ?></p><a href="?p=agenda" class="stretched-link"><span class="visually-hidden">Abrir agenda</span></a></article></div>
    <div class="col-6 col-lg"><article class="card mt-stat-card mt-stat-blue h-100 p-3"><span class="mt-stat-icon"><i class="fa-solid fa-list-check"></i></span><p class="mt-stat-label">Tarefas pendentes</p><p class="display-6 fw-bold mb-0"><?php echo $tarefasPendentes; ?></p><a href="?p=tarefas" class="stretched-link"><span class="visually-hidden">Abrir tarefas</span></a></article></div>
    <div class="col-6 col-lg"><article class="card mt-stat-card mt-stat-warning h-100 p-3"><span class="mt-stat-icon"><i class="fa-solid fa-boxes-stacked"></i></span><p class="mt-stat-label">Estoque baixo</p><p class="display-6 fw-bold mb-0"><?php echo $estoqueBaixo; ?></p><a href="?p=estoque" class="stretched-link"><span class="visually-hidden">Abrir estoque</span></a></article></div>
    <div class="col-12 col-lg"><article class="card mt-stat-card h-100 p-3"><span class="mt-stat-icon"><i class="fa-solid fa-coins"></i></span><p class="mt-stat-label">Saldo do mês</p><p class="h3 fw-bold mb-0">R$ <?php echo number_format($saldoMes, 2, ',', '.'); ?></p><a href="?p=financeiro" class="stretched-link"><span class="visually-hidden">Abrir financeiro</span></a></article></div>
</section>

<section class="card p-3 p-md-4 mt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div><h2 class="h4 mt-page-title mb-1">Atalhos do dia</h2><p class="mt-subtitle mb-0">Registros comuns, em poucos passos.</p></div>
        <div class="d-grid d-md-flex gap-2"><a href="?p=filhos&action=new" class="btn btn-primary"><i class="fa-solid fa-user-plus me-2"></i>Novo filho</a><a href="?p=agenda&action=new" class="btn btn-outline-primary"><i class="fa-solid fa-calendar-plus me-2"></i>Agendar atividade</a><a href="?p=movimentacoes_estoque&action=new" class="btn btn-outline-primary"><i class="fa-solid fa-box me-2"></i>Movimentar estoque</a></div>
    </div>
</section>

<section class="row g-4 mt-1">
    <div class="col-12 col-lg-7"><article class="card h-100 p-3 p-md-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mt-page-title mb-0">Próxima agenda</h2><a class="btn btn-sm btn-outline-primary" href="?p=agenda">Ver agenda</a></div><?php if (!$eventos): ?><p class="text-muted mb-0">Nenhuma atividade futura registrada.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($eventos as $evento): ?><div class="list-group-item px-0"><div class="d-flex justify-content-between gap-3"><div><strong><?php echo e($evento['titulo']); ?></strong><div class="small text-muted"><?php echo e($evento['tipo']); ?> · <?php echo date('d/m/Y H:i', strtotime($evento['data_hora'])); ?></div></div><span class="badge text-bg-light align-self-start"><?php echo e($evento['status']); ?></span></div></div><?php endforeach; ?></div><?php endif; ?></article></div>
    <div class="col-12 col-lg-5"><article class="card h-100 p-3 p-md-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mt-page-title mb-0">Comunicados</h2><a class="btn btn-sm btn-outline-primary" href="?p=comunicados">Gerenciar</a></div><?php if (!$comunicados): ?><p class="text-muted mb-0">Nenhum comunicado ativo.</p><?php else: ?><?php foreach ($comunicados as $comunicado): ?><div class="border-start border-4 ps-3 mb-3"><strong><?php echo e($comunicado['titulo']); ?></strong><p class="small mb-1"><?php echo e(mb_strimwidth($comunicado['mensagem'], 0, 140, '…')); ?></p><span class="small text-muted"><?php echo e($comunicado['publico']); ?></span></div><?php endforeach; ?><?php endif; ?></article></div>
</section>
