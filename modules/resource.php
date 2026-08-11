<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/resources.php';

$resources = meu_terreiro_resources();
$resourceKey = $resourceKey ?? '';
$resource = $resources[$resourceKey] ?? null;
if (!$resource) {
    http_response_code(404);
    echo '<div class="alert alert-warning">Módulo não encontrado.</div>';
    return;
}

$accessAllowed = match ($resource['access']) {
    'finance' => can_manage_finance(),
    'stock' => can_manage_stock(),
    default => can_manage(),
};
if (!$accessAllowed) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Você não possui permissão para administrar este módulo.</div>';
    return;
}

function resource_options(PDO $conn, array $field): array
{
    if (($field['type'] ?? '') !== 'select_db') {
        return $field['options'] ?? [];
    }
    $source = $field['source'];
    $allowedTables = ['filhos', 'obrigacoes_tipo', 'itens_estoque', 'fornecedores'];
    if (!in_array($source['table'], $allowedTables, true)) {
        return [];
    }
    $sql = 'SELECT `' . $source['value'] . '` AS value_id, `' . $source['label'] . '` AS label_text FROM `' . $source['table'] . '`';
    if ($source['table'] === 'filhos') {
        $sql .= " WHERE status = 'Ativo'";
    }
    $sql .= ' ORDER BY `' . $source['order'] . '` ASC';
    $rows = $conn->query($sql)->fetchAll();
    $options = ['' => 'Selecione'];
    foreach ($rows as $row) {
        $options[(string) $row['value_id']] = $row['label_text'];
    }
    return $options;
}

function resource_label(PDO $conn, array $resource, string $fieldName, mixed $value): string
{
    foreach ($resource['fields'] as $field) {
        if ($field['name'] !== $fieldName) {
            continue;
        }
        if (($field['type'] ?? '') === 'select_db') {
            $options = resource_options($conn, $field);
            return $options[(string) $value] ?? (string) $value;
        }
        if (($field['type'] ?? '') === 'select') {
            return $field['options'][(string) $value] ?? (string) $value;
        }
        if (($field['type'] ?? '') === 'checkbox') {
            return (int) $value === 1 ? 'Sim' : 'Não';
        }
    }
    return (string) $value;
}

function resource_value_from_post(array $field): mixed
{
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    if ($type === 'checkbox') {
        return isset($_POST[$name]) ? 1 : 0;
    }
    $value = trim((string) ($_POST[$name] ?? ''));
    if ($value === '') {
        return null;
    }
    if ($type === 'decimal') {
        $normalized = str_replace(',', '.', $value);
        return is_numeric($normalized) ? number_format((float) $normalized, 3, '.', '') : '__invalid__';
    }
    if ($type === 'number') {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : '__invalid__';
    }
    if ($type === 'url') {
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '__invalid__';
    }
    if ($type === 'datetime-local') {
        $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
        return $date ? $date->format('Y-m-d H:i:s') : '__invalid__';
    }
    return $value;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$action = $_GET['action'] ?? 'list';
$record = [];
if ($id && $action === 'edit') {
    $stmt = $tenantConn->prepare('SELECT * FROM `' . $resource['table'] . '` WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $record = $stmt->fetch() ?: [];
    if (!$record) {
        flash('error', 'Registro não encontrado.');
        header('Location: index.php?p=' . rawurlencode($resourceKey));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Sua sessão expirou. Tente novamente.');
        header('Location: index.php?p=' . rawurlencode($resourceKey));
        exit;
    }

    $postedAction = $_POST['action'] ?? '';
    if ($postedAction === 'save') {
        $data = [];
        $errors = [];
        foreach ($resource['fields'] as $field) {
            $value = resource_value_from_post($field);
            if (($field['required'] ?? false) && ($value === null || $value === '')) {
                $errors[] = 'Preencha o campo "' . $field['label'] . '".';
            }
            if ($value === '__invalid__') {
                $errors[] = 'Verifique o valor informado em "' . $field['label'] . '".';
            }
            $data[$field['name']] = $value === '__invalid__' ? null : $value;
        }
        if ($resourceKey === 'album' && empty($data['consentimento_confirmado'])) {
            $errors[] = 'Confirme que as pessoas retratadas autorizaram o uso da imagem.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            $_SESSION['old_input'][$resourceKey] = $data;
            header('Location: index.php?p=' . rawurlencode($resourceKey) . '&action=' . ($id ? 'edit&id=' . $id : 'new'));
            exit;
        }

        if ($resourceKey === 'movimentacoes_estoque') {
            $itemStmt = $tenantConn->prepare('SELECT quantidade_atual FROM itens_estoque WHERE id = ? FOR UPDATE');
            try {
                $tenantConn->beginTransaction();
                $itemStmt->execute([(int) $data['id_item']]);
                $currentQty = $itemStmt->fetchColumn();
                if ($currentQty === false) {
                    throw new RuntimeException('Item de estoque não encontrado.');
                }
                $delta = (float) $data['quantidade'];
                $newQty = match ($data['tipo']) {
                    'Entrada' => (float) $currentQty + $delta,
                    'Saída', 'Perda' => (float) $currentQty - $delta,
                    default => $delta,
                };
                if ($newQty < 0) {
                    throw new RuntimeException('A saída não pode deixar o estoque com quantidade negativa.');
                }
                $stmt = $tenantConn->prepare('INSERT INTO movimentacoes_estoque (id_item, tipo, quantidade, motivo, registrado_por) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([(int) $data['id_item'], $data['tipo'], $data['quantidade'], $data['motivo'], $_SESSION['user_id']]);
                $tenantConn->prepare('UPDATE itens_estoque SET quantidade_atual = ? WHERE id = ?')->execute([$newQty, (int) $data['id_item']]);
                $tenantConn->commit();
                audit($tenantConn, 'criar', $resource['table'], (int) $tenantConn->lastInsertId(), 'Movimentação de estoque registrada');
                flash('success', 'Movimentação registrada e estoque atualizado.');
            } catch (Throwable $e) {
                if ($tenantConn->inTransaction()) {
                    $tenantConn->rollBack();
                }
                flash('error', $e->getMessage());
            }
            header('Location: index.php?p=' . rawurlencode($resourceKey));
            exit;
        }

        $columns = array_keys($data);
        if ($id) {
            $sets = implode(', ', array_map(static fn($column) => '`' . $column . '` = ?', $columns));
            $stmt = $tenantConn->prepare('UPDATE `' . $resource['table'] . '` SET ' . $sets . ' WHERE id = ?');
            $stmt->execute([...array_values($data), $id]);
            audit($tenantConn, 'editar', $resource['table'], $id, 'Registro atualizado');
            flash('success', 'Registro atualizado com sucesso.');
        } else {
            if (in_array($resourceKey, ['registros_obrigacoes', 'financeiro', 'compras', 'comunicados'], true)) {
                $data['registrado_por'] = $_SESSION['user_id'] ?? null;
                if ($resourceKey === 'financeiro') {
                    unset($data['registrado_por']);
                    $data['criado_por'] = $_SESSION['user_id'] ?? null;
                }
                if ($resourceKey === 'compras' || $resourceKey === 'comunicados') {
                    unset($data['registrado_por']);
                    $data['criado_por'] = $_SESSION['user_id'] ?? null;
                }
            }
            $columns = array_keys($data);
            $stmt = $tenantConn->prepare('INSERT INTO `' . $resource['table'] . '` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
            $stmt->execute(array_values($data));
            $recordId = (int) $tenantConn->lastInsertId();
            audit($tenantConn, 'criar', $resource['table'], $recordId, 'Registro criado');
            flash('success', 'Registro adicionado com sucesso.');
        }
        header('Location: index.php?p=' . rawurlencode($resourceKey));
        exit;
    }

    if ($postedAction === 'delete' && $id) {
        $stmt = $tenantConn->prepare('DELETE FROM `' . $resource['table'] . '` WHERE id = ?');
        $stmt->execute([$id]);
        audit($tenantConn, 'excluir', $resource['table'], $id, 'Registro excluído');
        flash('success', 'Registro removido.');
        header('Location: index.php?p=' . rawurlencode($resourceKey));
        exit;
    }
}

$oldInput = $_SESSION['old_input'][$resourceKey] ?? [];
unset($_SESSION['old_input'][$resourceKey]);
$success = flash('success');
$error = flash('error');
?>
<section class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <span class="mt-eyebrow"><i class="fa-solid <?php echo e($resource['icon']); ?> me-2"></i>Administração</span>
        <h1 class="mt-page-title h2 mb-1"><?php echo e($resource['title']); ?></h1>
        <p class="mt-subtitle mb-0"><?php echo e($resource['description']); ?></p>
    </div>
    <?php if ($action !== 'new' && $action !== 'edit'): ?>
        <a class="btn btn-primary btn-lg" href="?p=<?php echo e($resourceKey); ?>&action=new"><i class="fa-solid fa-plus me-2"></i>Novo registro</a>
    <?php endif; ?>
</section>

<?php if ($success): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>

<?php if ($action === 'new' || ($action === 'edit' && $id)): ?>
    <?php $formRecord = $oldInput ?: $record; ?>
    <section class="card mt-form-card p-3 p-md-4">
        <form method="post" action="?p=<?php echo e($resourceKey); ?><?php echo $id ? '&action=edit&id=' . (int) $id : '&action=new'; ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="action" value="save">
            <div class="row g-3">
                <?php foreach ($resource['fields'] as $field): ?>
                    <?php $type = $field['type'] ?? 'text'; $value = $formRecord[$field['name']] ?? ($type === 'checkbox' ? 0 : ''); if ($type === 'datetime-local' && $value) { $value = date('Y-m-d\TH:i', strtotime((string) $value)); } ?>
                    <div class="col-12 <?php echo in_array($type, ['textarea'], true) ? '' : 'col-md-6'; ?>">
                        <?php if ($type === 'checkbox'): ?>
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" id="<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" value="1" <?php echo (int) $value === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="<?php echo e($field['name']); ?>"><?php echo e($field['label']); ?></label>
                            </div>
                        <?php else: ?>
                            <label class="form-label fw-semibold" for="<?php echo e($field['name']); ?>"><?php echo e($field['label']); ?><?php echo ($field['required'] ?? false) ? ' <span class="text-danger">*</span>' : ''; ?></label>
                            <?php if ($type === 'textarea'): ?>
                                <textarea class="form-control" id="<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" rows="4" <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>><?php echo e((string) $value); ?></textarea>
                            <?php elseif ($type === 'select' || $type === 'select_db'): ?>
                                <select class="form-select" id="<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                                    <?php foreach (resource_options($tenantConn, $field) as $optionValue => $optionLabel): ?>
                                        <option value="<?php echo e((string) $optionValue); ?>" <?php echo (string) $value === (string) $optionValue ? 'selected' : ''; ?>><?php echo e((string) $optionLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input class="form-control" id="<?php echo e($field['name']); ?>" type="<?php echo e($type === 'decimal' ? 'number' : $type); ?>" <?php echo $type === 'decimal' ? 'step="0.001" min="0"' : ''; ?> name="<?php echo e($field['name']); ?>" value="<?php echo e((string) $value); ?>" placeholder="<?php echo e($field['placeholder'] ?? ''); ?>" <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                <button class="btn btn-primary btn-lg" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Salvar registro</button>
                <a class="btn btn-outline-secondary btn-lg" href="?p=<?php echo e($resourceKey); ?>">Cancelar</a>
            </div>
        </form>
    </section>
<?php else: ?>
    <?php
    $columns = $resource['list'];
    $sql = 'SELECT * FROM `' . $resource['table'] . '` ORDER BY id DESC LIMIT 100';
    if ($resourceKey === 'filhos') { $sql = 'SELECT * FROM filhos ORDER BY nome ASC LIMIT 100'; }
    if ($resourceKey === 'agenda') { $sql = 'SELECT * FROM agenda ORDER BY data_hora ASC LIMIT 100'; }
    if ($resourceKey === 'mensalidades') { $sql = 'SELECT * FROM mensalidades ORDER BY referencia_mes_ano DESC, data_pagamento DESC LIMIT 100'; }
    $rows = $tenantConn->query($sql)->fetchAll();
    ?>
    <section class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Lista de registros de <?php echo e($resource['title']); ?></caption>
                <thead class="table-light"><tr>
                    <?php foreach ($columns as $column): ?>
                        <?php $label = $column; foreach ($resource['fields'] as $field) { if ($field['name'] === $column) { $label = $field['label']; break; } } ?>
                        <th scope="col"><?php echo e($label); ?></th>
                    <?php endforeach; ?>
                    <th scope="col" class="text-end">Ações</th>
                </tr></thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td colspan="<?php echo count($columns) + 1; ?>" class="text-center p-5 text-muted">Nenhum registro ainda. Use “Novo registro” para começar.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?><td><?php echo e(resource_label($tenantConn, $resource, $column, $row[$column] ?? '')); ?></td><?php endforeach; ?>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="?p=<?php echo e($resourceKey); ?>&action=edit&id=<?php echo (int) $row['id']; ?>" aria-label="Editar"><i class="fa-solid fa-pen"></i></a>
                                <form method="post" action="?p=<?php echo e($resourceKey); ?>&id=<?php echo (int) $row['id']; ?>" class="d-inline" onsubmit="return confirm('Remover este registro? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="delete">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Remover"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
