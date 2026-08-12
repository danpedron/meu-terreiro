<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (!in_array(current_role(), ['SuperAdmin', 'Regente'], true)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Somente a regência pode alterar as configurações da casa.</div>';
    return;
}

$centralConn = CentralDB::getInstance()->getConnection();
$tenantId = (int) $_SESSION['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Sua sessão expirou. Tente novamente.');
        header('Location: index.php?p=configuracoes');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'save_house') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        if (mb_strlen($nome) < 3) {
            flash('error', 'Informe o nome da casa.');
            header('Location: index.php?p=configuracoes');
            exit;
        }
        $houseData = [
            $nome,
            $_POST['fundacao'] ?: null,
            trim((string) ($_POST['nacao'] ?? '')) ?: null,
            $_POST['latitude'] !== '' ? str_replace(',', '.', (string) $_POST['latitude']) : null,
            $_POST['longitude'] !== '' ? str_replace(',', '.', (string) $_POST['longitude']) : null,
            trim((string) ($_POST['babalorixa'] ?? '')) ?: null,
            trim((string) ($_POST['yalorixa'] ?? '')) ?: null,
            $_POST['mensalidade_valor'] !== '' ? str_replace(',', '.', (string) $_POST['mensalidade_valor']) : 0,
        ];
        $existingId = $tenantConn->query('SELECT id FROM terreiro_info ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($existingId) {
            $stmt = $tenantConn->prepare('UPDATE terreiro_info SET nome=?, fundacao=?, nacao=?, latitude=?, longitude=?, babalorixa=?, yalorixa=?, mensalidade_valor=? WHERE id=?');
            $stmt->execute([...$houseData, $existingId]);
        } else {
            $stmt = $tenantConn->prepare('INSERT INTO terreiro_info (nome,fundacao,nacao,latitude,longitude,babalorixa,yalorixa,mensalidade_valor) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute($houseData);
        }

        $detailData = [
            trim((string) ($_POST['descricao'] ?? '')) ?: null,
            trim((string) ($_POST['cidade'] ?? '')) ?: null,
            strtoupper(trim((string) ($_POST['estado'] ?? ''))) ?: null,
            trim((string) ($_POST['telefone'] ?? '')) ?: null,
            trim((string) ($_POST['email_contato'] ?? '')) ?: null,
            trim((string) ($_POST['endereco_publico'] ?? '')) ?: null,
            trim((string) ($_POST['politica_privacidade'] ?? '')) ?: null,
        ];
        $existingDetail = $tenantConn->query('SELECT id FROM terreiro_detalhes ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($existingDetail) {
            $stmt = $tenantConn->prepare('UPDATE terreiro_detalhes SET descricao=?, cidade=?, estado=?, telefone=?, email_contato=?, endereco_publico=?, politica_privacidade=? WHERE id=?');
            $stmt->execute([...$detailData, $existingDetail]);
        } else {
            $stmt = $tenantConn->prepare('INSERT INTO terreiro_detalhes (descricao,cidade,estado,telefone,email_contato,endereco_publico,politica_privacidade) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute($detailData);
        }
        // O diretório parte de cidade/UF e posição aproximada quando a casa já as informou aqui.
        // Contatos, endereço e outras informações continuam sob controle do Perfil público.
        $latitudePublica = $houseData[3] !== null && $houseData[4] !== null ? (float) $houseData[3] : null;
        $longitudePublica = $houseData[3] !== null && $houseData[4] !== null ? (float) $houseData[4] : null;
        $centralStmt = $centralConn->prepare(
            "UPDATE tenants
             SET nome_exibicao = ?, listar_publicamente = 1, mostrar_no_mapa = ?, localizacao_publica = ?, cidade_publica = ?, estado_publico = ?, latitude_publica = ?, longitude_publica = ?, descricao_publica = ?, nacao_publica = ?
             WHERE id = ?"
        );
        $centralStmt->execute([
            $nome,
            $latitudePublica !== null ? 1 : 0,
            $latitudePublica !== null ? 'Aproximada' : 'Bairro',
            $detailData[1],
            strtoupper((string) ($detailData[2] ?? '')) ?: null,
            $latitudePublica,
            $longitudePublica,
            $detailData[0],
            $houseData[2],
            $tenantId,
        ]);
        audit($tenantConn, 'editar', 'terreiro_info', (int) ($existingId ?: $tenantConn->lastInsertId()), 'Dados institucionais atualizados e localização pública sincronizada.');
        flash('success', 'Dados da casa atualizados. Cidade e localização aproximada foram sincronizadas com o diretório público; você pode ocultar o perfil público a qualquer momento.');
    }

    if ($action === 'create_user') {
        $nome = trim((string) ($_POST['nome_usuario'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email_usuario'] ?? '')));
        $password = (string) ($_POST['senha_usuario'] ?? '');
        $role = (string) ($_POST['role_usuario'] ?? 'Leitor');
        $roles = ['Secretario','Financeiro','Estoque','Leitor'];
        if (mb_strlen($nome) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || !in_array($role, $roles, true)) {
            flash('error', 'Para criar um acesso, informe nome, e-mail válido, perfil permitido e senha de ao menos 12 caracteres.');
        } else {
            try {
                $stmt = $centralConn->prepare('INSERT INTO users (id_tenant,nome,email,password_hash,role,status) VALUES (?,?,?,?,?,\'Ativo\')');
                $stmt->execute([$tenantId, $nome, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                audit($tenantConn, 'criar', 'usuarios_central', (int) $centralConn->lastInsertId(), 'Novo perfil administrativo criado');
                flash('success', 'Novo acesso administrativo criado.');
            } catch (PDOException $e) {
                flash('error', 'Não foi possível criar este acesso. Verifique se o e-mail já está em uso.');
            }
        }
    }

    if ($action === 'toggle_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = (string) ($_POST['new_status'] ?? 'Inativo');
        if ($userId > 0 && in_array($newStatus, ['Ativo','Inativo'], true) && $userId !== (int) $_SESSION['user_id']) {
            $stmt = $centralConn->prepare("UPDATE users SET status = ? WHERE id = ? AND id_tenant = ? AND role <> 'Regente'");
            $stmt->execute([$newStatus, $userId, $tenantId]);
            audit($tenantConn, 'editar', 'usuarios_central', $userId, 'Status de perfil atualizado');
            flash('success', 'Status do acesso atualizado.');
        } else {
            flash('error', 'Não é possível desativar o próprio acesso ou um perfil de regência por esta tela.');
        }
    }
    header('Location: index.php?p=configuracoes');
    exit;
}

$house = $tenantConn->query('SELECT * FROM terreiro_info ORDER BY id ASC LIMIT 1')->fetch() ?: [];
$details = $tenantConn->query('SELECT * FROM terreiro_detalhes ORDER BY id ASC LIMIT 1')->fetch() ?: [];
$usersStmt = $centralConn->prepare('SELECT id,nome,email,role,status,ultimo_acesso_em FROM users WHERE id_tenant = ? ORDER BY role = \'Regente\' DESC, nome ASC');
$usersStmt->execute([$tenantId]);
$users = $usersStmt->fetchAll();
$success = flash('success');
$error = flash('error');
?>
<section class="mb-4"><span class="mt-eyebrow"><i class="fa-solid fa-house-circle-check me-2"></i>Casa</span><h1 class="mt-page-title h2 mb-1">Configurações do terreiro</h1><p class="mt-subtitle mb-0">Defina a identificação da casa e os acessos da equipe. Evite registrar informações que a casa considere sigilosas nesta tela geral.</p></section>
<?php if ($success): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
<section class="card mt-form-card p-3 p-md-4 mb-4"><h2 class="h4 mt-page-title">Dados da casa</h2><form method="post"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="save_house"><div class="row g-3">
<div class="col-12 col-md-8"><label class="form-label fw-semibold">Nome do terreiro</label><input class="form-control" name="nome" required value="<?php echo e($house['nome'] ?? ''); ?>"></div><div class="col-12 col-md-4"><label class="form-label fw-semibold">Fundação</label><input class="form-control" type="date" name="fundacao" value="<?php echo e($house['fundacao'] ?? ''); ?>"></div>
<div class="col-12 col-md-4"><label class="form-label fw-semibold">Nação ou tradição</label><input class="form-control" name="nacao" value="<?php echo e($house['nacao'] ?? ''); ?>"></div><div class="col-12 col-md-4"><label class="form-label fw-semibold">Babalorixá</label><input class="form-control" name="babalorixa" value="<?php echo e($house['babalorixa'] ?? ''); ?>"></div><div class="col-12 col-md-4"><label class="form-label fw-semibold">Yalorixá</label><input class="form-control" name="yalorixa" value="<?php echo e($house['yalorixa'] ?? ''); ?>"></div>
<div class="col-12 col-md-4"><label class="form-label fw-semibold">Latitude (opcional)</label><input class="form-control" inputmode="decimal" name="latitude" value="<?php echo e($house['latitude'] ?? ''); ?>"></div><div class="col-12 col-md-4"><label class="form-label fw-semibold">Longitude (opcional)</label><input class="form-control" inputmode="decimal" name="longitude" value="<?php echo e($house['longitude'] ?? ''); ?>"></div><div class="col-12 col-md-4"><label class="form-label fw-semibold">Mensalidade sugerida (R$)</label><input class="form-control" type="number" step="0.01" min="0" name="mensalidade_valor" value="<?php echo e((string) ($house['mensalidade_valor'] ?? '0')); ?>"></div>
<div class="col-12"><label class="form-label fw-semibold">Descrição institucional</label><textarea class="form-control" name="descricao" rows="3"><?php echo e($details['descricao'] ?? ''); ?></textarea></div><div class="col-12 col-md-6"><label class="form-label fw-semibold">Cidade</label><input class="form-control" name="cidade" value="<?php echo e($details['cidade'] ?? ''); ?>"></div><div class="col-12 col-md-2"><label class="form-label fw-semibold">UF</label><input class="form-control" maxlength="2" name="estado" value="<?php echo e($details['estado'] ?? ''); ?>"></div><div class="col-12 col-md-4"><label class="form-label fw-semibold">Telefone</label><input class="form-control" name="telefone" value="<?php echo e($details['telefone'] ?? ''); ?>"></div>
<div class="col-12 col-md-6"><label class="form-label fw-semibold">E-mail de contato</label><input class="form-control" type="email" name="email_contato" value="<?php echo e($details['email_contato'] ?? ''); ?>"></div><div class="col-12 col-md-6"><label class="form-label fw-semibold">Endereço de referência (restrito)</label><input class="form-control" name="endereco_publico" value="<?php echo e($details['endereco_publico'] ?? ''); ?>"></div><div class="col-12"><label class="form-label fw-semibold">Política de privacidade interna</label><textarea class="form-control" name="politica_privacidade" rows="3"><?php echo e($details['politica_privacidade'] ?? ''); ?></textarea></div>
</div><button class="btn btn-primary btn-lg mt-4" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Salvar dados da casa</button></form></section>
<section class="card p-3 p-md-4"><div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3"><div><h2 class="h4 mt-page-title mb-1">Equipe administrativa</h2><p class="mt-subtitle mb-0">Crie acessos somente para pessoas autorizadas. Perfis de regência não são desativados por esta tela.</p></div></div><form method="post" class="row g-3 border rounded-3 p-3 m-0 mb-4"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="create_user"><div class="col-12 col-md-4"><label class="form-label fw-semibold">Nome</label><input class="form-control" name="nome_usuario" required></div><div class="col-12 col-md-4"><label class="form-label fw-semibold">E-mail</label><input class="form-control" type="email" name="email_usuario" required></div><div class="col-12 col-md-2"><label class="form-label fw-semibold">Perfil</label><select class="form-select" name="role_usuario"><option value="Secretario">Secretaria</option><option value="Financeiro">Financeiro</option><option value="Estoque">Estoque</option><option value="Leitor">Leitura</option></select></div><div class="col-12 col-md-2"><label class="form-label fw-semibold">Senha inicial</label><input class="form-control" type="password" name="senha_usuario" minlength="12" required></div><div class="col-12"><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-user-plus me-2"></i>Criar acesso</button></div></form><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Último acesso</th><th class="text-end">Ação</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><?php echo e($user['nome']); ?></td><td><?php echo e($user['email']); ?></td><td><?php echo e($user['role']); ?></td><td><span class="badge <?php echo $user['status'] === 'Ativo' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo e($user['status']); ?></span></td><td><?php echo $user['ultimo_acesso_em'] ? e(date('d/m/Y H:i', strtotime($user['ultimo_acesso_em']))) : '—'; ?></td><td class="text-end"><?php if ($user['role'] !== 'Regente' && (int) $user['id'] !== (int) $_SESSION['user_id']): ?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>"><input type="hidden" name="new_status" value="<?php echo $user['status'] === 'Ativo' ? 'Inativo' : 'Ativo'; ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo $user['status'] === 'Ativo' ? 'Desativar' : 'Ativar'; ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
