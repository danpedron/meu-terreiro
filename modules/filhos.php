<?php
if (!isset($_SESSION['user_id'])) {
    exit;
}

require_once __DIR__ . '/../config/TenantManager.php';
$tenantManager = new TenantManager();
$tenantConn = $tenantManager->getTenantConnection($_SESSION['tenant_slug']);
$filhos = $tenantConn->query("SELECT * FROM filhos ORDER BY nome ASC")->fetchAll();
?>

<section class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="mt-page-title h2 mb-1"><i class="fa-solid fa-people-group me-2"></i>Filhos de santo</h1>
        <p class="mt-subtitle mb-0">Cadastros autorizados e organizados para o cuidado da casa.</p>
    </div>
    <a href="?p=filhos_novo" class="btn btn-primary btn-lg"><i class="fa-solid fa-user-plus me-2"></i>Novo cadastro</a>
</section>

<div class="card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 1.05rem;">
            <caption class="visually-hidden">Lista de filhos de santo cadastrados</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">Cargo</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filhos)): ?>
                    <tr><td colspan="4" class="text-center p-5 text-muted">Nenhum filho cadastrado ainda.</td></tr>
                <?php else: ?>
                    <?php foreach ($filhos as $filho): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($filho['nome'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars($filho['cargo'] ?? 'Não informado', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge rounded-pill <?php echo $filho['status'] === 'Ativo' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo htmlspecialchars($filho['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td class="text-end">
                                <a href="?p=filhos_view&id=<?php echo (int) $filho['id']; ?>" class="btn btn-sm btn-outline-primary" aria-label="Ver cadastro de <?php echo htmlspecialchars($filho['nome'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-eye"></i></a>
                                <a href="?p=filhos_edit&id=<?php echo (int) $filho['id']; ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar cadastro de <?php echo htmlspecialchars($filho['nome'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-pen"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
