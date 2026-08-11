<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (!can_manage()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">A exportação e a importação são restritas à dirigência ou à secretaria autorizada.</div>';
    return;
}

function portability_text(array $source, string $field, int $max = 0): ?string
{
    $value = trim((string) ($source[$field] ?? ''));
    if ($value === '') {
        return null;
    }
    return $max > 0 ? mb_substr($value, 0, $max) : $value;
}

function portability_date(mixed $value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Sua sessão expirou. Tente novamente.');
        header('Location: index.php?p=portabilidade');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'exportar') {
        $idFilho = filter_input(INPUT_POST, 'id_filho', FILTER_VALIDATE_INT);
        if (!$idFilho || empty($_POST['confirmar_exportacao'])) {
            flash('error', 'Selecione uma pessoa e confirme que possui autorização para exportar os dados.');
            header('Location: index.php?p=portabilidade');
            exit;
        }

        $stmt = $tenantConn->prepare('SELECT nome, cpf, data_nascimento, whatsapp, endereco, data_entrada, cargo, status FROM filhos WHERE id = ? LIMIT 1');
        $stmt->execute([$idFilho]);
        $filho = $stmt->fetch();
        if (!$filho) {
            flash('error', 'Pessoa não encontrada.');
            header('Location: index.php?p=portabilidade');
            exit;
        }

        $entityStmt = $tenantConn->prepare('SELECT nome, tipo, cor_vela, ponto_riscado_url, recados, nivel_sigilo FROM entidades WHERE id_filho = ? ORDER BY id ASC');
        $entityStmt->execute([$idFilho]);
        $obligationStmt = $tenantConn->prepare(
            'SELECT ot.nome AS obrigacao, ot.nivel_sigilo, fo.data_realizacao, fo.observacoes
             FROM filhos_obrigacoes fo
             INNER JOIN obrigacoes_tipo ot ON ot.id = fo.id_obrigacao
             WHERE fo.id_filho = ? ORDER BY fo.data_realizacao ASC, fo.id ASC'
        );
        $obligationStmt->execute([$idFilho]);

        $payload = [
            'formato' => 'meu-terreiro-portabilidade',
            'versao' => 1,
            'exportado_em' => gmdate('c'),
            'aviso' => 'Arquivo confidencial. Compartilhe somente com a pessoa titular e com uma casa autorizada.',
            'filho' => $filho,
            'entidades' => $entityStmt->fetchAll(),
            'obrigacoes' => $obligationStmt->fetchAll(),
        ];
        audit($tenantConn, 'exportar', 'filhos', $idFilho, 'Arquivo portátil autorizado e gerado');
        $filename = 'meu-terreiro-' . preg_replace('/[^a-z0-9]+/i', '-', (string) $filho['nome']) . '-' . date('Ymd') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    if ($action === 'importar') {
        if (empty($_POST['confirmar_importacao']) || empty($_FILES['arquivo_portatil']) || $_FILES['arquivo_portatil']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Selecione o arquivo exportado e confirme que possui autorização para importar os dados.');
            header('Location: index.php?p=portabilidade');
            exit;
        }
        if ((int) $_FILES['arquivo_portatil']['size'] > 2 * 1024 * 1024) {
            flash('error', 'O arquivo ultrapassa o limite de 2 MB.');
            header('Location: index.php?p=portabilidade');
            exit;
        }

        try {
            $raw = file_get_contents($_FILES['arquivo_portatil']['tmp_name']);
            $payload = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || ($payload['formato'] ?? '') !== 'meu-terreiro-portabilidade' || (int) ($payload['versao'] ?? 0) !== 1 || !is_array($payload['filho'] ?? null)) {
                throw new RuntimeException('O arquivo não segue o formato de portabilidade do Meu Terreiro.');
            }

            $source = $payload['filho'];
            $nome = portability_text($source, 'nome', 255);
            if (!$nome) {
                throw new RuntimeException('O arquivo não possui um nome válido para cadastro.');
            }
            $cpf = portability_text($source, 'cpf', 14);
            if ($cpf) {
                $cpfCheck = $tenantConn->prepare('SELECT id FROM filhos WHERE cpf = ? LIMIT 1');
                $cpfCheck->execute([$cpf]);
                if ($cpfCheck->fetch()) {
                    throw new RuntimeException('Já existe uma pessoa cadastrada com o CPF presente no arquivo. Revise antes de importar.');
                }
            }

            $tenantConn->beginTransaction();
            $insertFilho = $tenantConn->prepare(
                'INSERT INTO filhos (nome, cpf, data_nascimento, whatsapp, endereco, data_entrada, cargo, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $status = portability_text($source, 'status', 20);
            $status = in_array($status, ['Ativo', 'Inativo', 'Afastado'], true) ? $status : 'Ativo';
            $insertFilho->execute([
                $nome,
                $cpf,
                portability_date($source['data_nascimento'] ?? null),
                portability_text($source, 'whatsapp', 20),
                portability_text($source, 'endereco'),
                portability_date($source['data_entrada'] ?? null),
                portability_text($source, 'cargo', 100),
                $status,
            ]);
            $newFilhoId = (int) $tenantConn->lastInsertId();

            $insertEntity = $tenantConn->prepare(
                'INSERT INTO entidades (id_filho, nome, tipo, cor_vela, ponto_riscado_url, recados, nivel_sigilo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach (($payload['entidades'] ?? []) as $entity) {
                if (!is_array($entity) || !($entityName = portability_text($entity, 'nome', 255))) {
                    continue;
                }
                $sigilo = portability_text($entity, 'nivel_sigilo', 20);
                $insertEntity->execute([
                    $newFilhoId,
                    $entityName,
                    portability_text($entity, 'tipo', 100),
                    portability_text($entity, 'cor_vela', 50),
                    portability_text($entity, 'ponto_riscado_url'),
                    portability_text($entity, 'recados'),
                    in_array($sigilo, ['Restrito', 'Dirigência'], true) ? $sigilo : 'Restrito',
                ]);
            }

            $findType = $tenantConn->prepare('SELECT id FROM obrigacoes_tipo WHERE nome = ? LIMIT 1');
            $insertType = $tenantConn->prepare('INSERT INTO obrigacoes_tipo (nome, nivel_sigilo) VALUES (?, ?)');
            $insertObligation = $tenantConn->prepare('INSERT INTO filhos_obrigacoes (id_filho, id_obrigacao, data_realizacao, observacoes, registrado_por) VALUES (?, ?, ?, ?, ?)');
            foreach (($payload['obrigacoes'] ?? []) as $obligation) {
                if (!is_array($obligation) || !($typeName = portability_text($obligation, 'obrigacao', 255)) || !($date = portability_date($obligation['data_realizacao'] ?? null))) {
                    continue;
                }
                $findType->execute([$typeName]);
                $typeId = $findType->fetchColumn();
                if (!$typeId) {
                    $sigilo = portability_text($obligation, 'nivel_sigilo', 20);
                    $insertType->execute([$typeName, in_array($sigilo, ['Interno', 'Restrito', 'Dirigência'], true) ? $sigilo : 'Restrito']);
                    $typeId = (int) $tenantConn->lastInsertId();
                }
                $insertObligation->execute([$newFilhoId, $typeId, $date, portability_text($obligation, 'observacoes'), $_SESSION['user_id']]);
            }

            $tenantConn->commit();
            audit($tenantConn, 'importar', 'filhos', $newFilhoId, 'Arquivo portátil importado com confirmação');
            flash('success', 'Dados importados com sucesso. Revise o cadastro e os registros restritos antes de liberá-los.');
        } catch (Throwable $e) {
            if ($tenantConn->inTransaction()) {
                $tenantConn->rollBack();
            }
            flash('error', $e->getMessage());
        }
        header('Location: index.php?p=portabilidade');
        exit;
    }
}

$filhos = $tenantConn->query('SELECT id, nome, cargo, status FROM filhos ORDER BY nome ASC')->fetchAll();
$success = flash('success');
$error = flash('error');
?>
<section class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <span class="mt-eyebrow"><i class="fa-solid fa-file-export me-2"></i>Direitos e continuidade</span>
        <h1 class="mt-page-title h2 mb-1">Portabilidade de dados</h1>
        <p class="mt-subtitle mb-0">Entregue à pessoa um arquivo que possa ser levado para outra casa autorizada, sem misturar bancos de terreiros.</p>
    </div>
</section>
<?php if ($success): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <section class="card mt-form-card h-100 p-3 p-md-4">
            <h2 class="h4"><i class="fa-solid fa-download me-2"></i>Exportar dados autorizados</h2>
            <p class="small text-muted">Inclui cadastro, entidades e histórico de obrigações. Não inclui finanças, fotos, comunicados ou dados de outras pessoas.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <input type="hidden" name="action" value="exportar">
                <label class="form-label fw-semibold" for="id_filho">Pessoa</label>
                <select class="form-select" id="id_filho" name="id_filho" required>
                    <option value="">Selecione</option>
                    <?php foreach ($filhos as $filho): ?><option value="<?php echo (int) $filho['id']; ?>"><?php echo e($filho['nome'] . ($filho['cargo'] ? ' — ' . $filho['cargo'] : '')); ?></option><?php endforeach; ?>
                </select>
                <div class="form-check my-3"><input class="form-check-input" id="confirmar_exportacao" type="checkbox" name="confirmar_exportacao" value="1" required><label class="form-check-label" for="confirmar_exportacao">Confirmo que a pessoa titular ou a casa autorizou esta exportação.</label></div>
                <button class="btn btn-primary btn-lg w-100" type="submit"><i class="fa-solid fa-file-arrow-down me-2"></i>Gerar arquivo portátil</button>
            </form>
        </section>
    </div>
    <div class="col-12 col-lg-6">
        <section class="card mt-form-card h-100 p-3 p-md-4">
            <h2 class="h4"><i class="fa-solid fa-file-import me-2"></i>Importar para esta casa</h2>
            <p class="small text-muted">Use somente um arquivo confiável e autorizado. Após importar, revise os dados e os conteúdos restritos antes de qualquer uso.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <input type="hidden" name="action" value="importar">
                <label class="form-label fw-semibold" for="arquivo_portatil">Arquivo de portabilidade (.json)</label>
                <input class="form-control" id="arquivo_portatil" type="file" name="arquivo_portatil" accept="application/json,.json" required>
                <div class="form-text">Limite de 2 MB. O arquivo não é guardado após o processamento.</div>
                <div class="form-check my-3"><input class="form-check-input" id="confirmar_importacao" type="checkbox" name="confirmar_importacao" value="1" required><label class="form-check-label" for="confirmar_importacao">Confirmo que possuo autorização para importar estes dados para esta casa.</label></div>
                <button class="btn btn-outline-primary btn-lg w-100" type="submit"><i class="fa-solid fa-shield-heart me-2"></i>Importar dados autorizados</button>
            </form>
        </section>
    </div>
</div>
<div class="alert alert-warning mt-4 mb-0"><strong>Proteção da casa e da pessoa:</strong> pontos riscados, recados e obrigações são tratados como conteúdo restrito. O arquivo deve ser armazenado e compartilhado com segurança.</div>
