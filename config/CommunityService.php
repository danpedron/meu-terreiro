<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/LogoStorage.php';

final class CommunityService
{
    private PDO $db;
    private const MEMBERSHIP_ROLES = ['Consulente', 'Assistencia', 'FilhoDeSanto', 'Babalorixa', 'Yalorixa', 'Colaborador'];

    public function __construct()
    {
        $this->db = CentralDB::getInstance()->getConnection();
    }

    public function getConnection(): PDO
    {
        return $this->db;
    }

    public function log(?int $actorId, ?int $tenantId, string $action, ?string $referenceType = null, ?int $referenceId = null, ?string $details = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO central_audit_log (actor_user_id, tenant_id, action, reference_type, reference_id, details) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$actorId, $tenantId, $action, $referenceType, $referenceId, $details]);
    }

    public function getUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, email, whatsapp, role, global_role, status, ultimo_acesso_em, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function isGlobalAdmin(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = ? AND status = 'Ativo' AND global_role = 'AdminGlobal' LIMIT 1");
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function listMemberships(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*, t.slug, t.nome_exibicao, t.status AS tenant_status, t.listar_publicamente
             FROM tenant_memberships m
             INNER JOIN tenants t ON t.id = m.tenant_id
             WHERE m.user_id = ?
             ORDER BY FIELD(m.status, 'Ativo', 'PendenteAdminGlobal', 'Pendente', 'Recusado', 'Cancelado'), t.nome_exibicao"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getTenantForPublicDirectory(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, slug, db_name, nome_exibicao, descricao_publica, nacao_publica, dirigente_publico, dirigente_status,
                    linha_presenca_publica, horarios_publicos, whatsapp_publico, email_publico, aceita_consultas,
                    aceita_solicitacoes_vinculo, localizacao_publica, endereco_publico, bairro_publico, cidade_publica,
                    estado_publico, latitude_publica, longitude_publica, mostrar_no_mapa, logo_publico
             FROM tenants
             WHERE slug = ? AND status = 'Ativo' AND listar_publicamente = 1
             LIMIT 1"
        );
        $stmt->execute([$slug]);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            return null;
        }
        $tenant['filhos_ativos'] = $this->countActiveChildren((string) ($tenant['db_name'] ?? ''));
        return $tenant;
    }

    private function countActiveChildren(string $databaseName): int
    {
        if (!preg_match('/^meuterreiro_[a-z0-9-]+$/', $databaseName)) {
            return 0;
        }
        try {
            $tenantDb = database_connection($databaseName, CENTRAL_DB_USER, CENTRAL_DB_PASS);
            return (int) $tenantDb->query("SELECT COUNT(*) FROM filhos WHERE status = 'Ativo'")->fetchColumn();
        } catch (Throwable $e) {
            error_log('Não foi possível calcular o total público de filhos: ' . $e->getMessage());
            return 0;
        }
    }

    public function findPublicTenants(?float $latitude = null, ?float $longitude = null, ?string $city = null, int $radiusKm = 25, ?string $state = null): array
    {
        $radiusKm = max(1, min($radiusKm, 50));
        $city = trim((string) $city);
        $state = strtoupper(trim((string) $state));
        if (!preg_match('/^[A-Z]{2}$/', $state)) {
            $state = '';
        }
        $params = [];
        $selectDistance = 'NULL AS distancia_km';
        $where = "t.status = 'Ativo' AND t.listar_publicamente = 1";

        if ($latitude !== null && $longitude !== null && $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
            // Coordenadas provêm da ação explícita do visitante e não são persistidas.
            $selectDistance = '(6371 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(?)) * COS(RADIANS(t.latitude_publica)) * COS(RADIANS(t.longitude_publica) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(t.latitude_publica)))))) AS distancia_km';
            $params = [$latitude, $longitude, $latitude];
            $where .= ' AND t.mostrar_no_mapa = 1 AND t.latitude_publica IS NOT NULL AND t.longitude_publica IS NOT NULL';
        } elseif ($city !== '') {
            $where .= ' AND t.cidade_publica LIKE ?';
            $params[] = '%' . mb_substr($city, 0, 120) . '%';
            if ($state !== '') {
                $where .= ' AND t.estado_publico = ?';
                $params[] = $state;
            }
        }

        $sql = "SELECT t.id, t.slug, t.nome_exibicao, t.descricao_publica, t.nacao_publica, t.dirigente_publico,
                       t.linha_presenca_publica, t.horarios_publicos, t.localizacao_publica, t.bairro_publico,
                       t.cidade_publica, t.estado_publico, t.latitude_publica, t.longitude_publica, t.mostrar_no_mapa, t.logo_publico,
                       $selectDistance
                FROM tenants t
                WHERE $where";
        if ($latitude !== null && $longitude !== null) {
            $sql .= ' HAVING distancia_km <= ? ORDER BY distancia_km ASC, t.nome_exibicao ASC LIMIT 50';
            $params[] = $radiusKm;
        } else {
            $sql .= ' ORDER BY t.nome_exibicao ASC LIMIT 50';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retorna somente perfis que autorizaram listagem pública, com a data real da última atualização.
     * O resultado é usado exclusivamente pelo sitemap, nunca para expor dados internos.
     */
    public function listPublicTenantSitemapEntries(): array
    {
        $stmt = $this->db->query(
            "SELECT slug, updated_at
             FROM tenants
             WHERE status = 'Ativo' AND listar_publicamente = 1
             ORDER BY slug ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lista localidades que já têm ao menos uma casa publicamente visível.
     * Evita criar páginas geográficas vazias ou baseadas em informações privadas.
     */
    public function listPublicLocations(): array
    {
        $stmt = $this->db->query(
            "SELECT cidade_publica, estado_publico, MAX(updated_at) AS updated_at, COUNT(*) AS total_casas
             FROM tenants
             WHERE status = 'Ativo'
               AND listar_publicamente = 1
               AND cidade_publica IS NOT NULL
               AND TRIM(cidade_publica) <> ''
             GROUP BY cidade_publica, estado_publico
             ORDER BY cidade_publica ASC, estado_publico ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Mantido para compatibilidade com instalações que já chamavam este método.
     */
    public function listPublicTenantSlugs(): array
    {
        return array_column($this->listPublicTenantSitemapEntries(), 'slug');
    }

    public function requestMembership(int $userId, int $tenantId, string $role, string $message): array
    {
        if (!in_array($role, self::MEMBERSHIP_ROLES, true)) {
            return ['error' => 'Escolha uma forma válida de participação.'];
        }
        $tenant = $this->getTenantForRequest($tenantId);
        if (!$tenant || !(int) $tenant['aceita_solicitacoes_vinculo']) {
            return ['error' => 'Esta casa não está recebendo novas solicitações de vínculo neste momento.'];
        }

        $message = trim(mb_substr($message, 0, 1200));
        $hasVerifiedLeader = $this->tenantHasVerifiedLeader($tenantId);
        $needsGlobalApproval = in_array($role, ['Babalorixa', 'Yalorixa'], true) && !$hasVerifiedLeader;
        $status = $needsGlobalApproval ? 'PendenteAdminGlobal' : 'Pendente';

        $existing = $this->db->prepare('SELECT id, status FROM tenant_memberships WHERE tenant_id = ? AND user_id = ? AND papel = ? LIMIT 1');
        $existing->execute([$tenantId, $userId, $role]);
        $existingRow = $existing->fetch();
        if ($existingRow && in_array($existingRow['status'], ['Ativo', 'Pendente', 'PendenteAdminGlobal'], true)) {
            return ['error' => 'Já existe uma solicitação em andamento ou um vínculo ativo nesta modalidade.'];
        }

        if ($existingRow) {
            $stmt = $this->db->prepare(
                "UPDATE tenant_memberships SET status = ?, solicitacao = ?, consentimento_em = NOW(), solicitado_em = NOW(), aprovado_em = NULL, aprovado_por_user_id = NULL, observacao_decisao = NULL WHERE id = ?"
            );
            $stmt->execute([$status, $message ?: null, (int) $existingRow['id']]);
            $membershipId = (int) $existingRow['id'];
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO tenant_memberships (tenant_id, user_id, papel, status, solicitacao, consentimento_em, solicitado_em) VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$tenantId, $userId, $role, $status, $message ?: null]);
            $membershipId = (int) $this->db->lastInsertId();
        }

        $this->log($userId, $tenantId, $needsGlobalApproval ? 'solicitacao_dirigencia_global' : 'solicitacao_vinculo', 'tenant_memberships', $membershipId, 'Papel solicitado: ' . $role);
        return ['id' => $membershipId, 'status' => $status];
    }

    public function approveMembership(int $actorId, int $membershipId, bool $approve, string $note = ''): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*, u.nome AS solicitante_nome, u.email AS solicitante_email, u.whatsapp AS solicitante_whatsapp,
                    t.slug, t.dirigente_status
             FROM tenant_memberships m
             INNER JOIN users u ON u.id = m.user_id
             INNER JOIN tenants t ON t.id = m.tenant_id
             WHERE m.id = ? LIMIT 1"
        );
        $stmt->execute([$membershipId]);
        $membership = $stmt->fetch();
        if (!$membership || !in_array($membership['status'], ['Pendente', 'PendenteAdminGlobal'], true)) {
            return ['error' => 'A solicitação não está disponível para decisão.'];
        }

        $isGlobalAdmin = $this->isGlobalAdmin($actorId);
        $isLocalLeader = $this->isLocalLeader($actorId, (int) $membership['tenant_id']);
        if ($membership['status'] === 'PendenteAdminGlobal' && !$isGlobalAdmin) {
            return ['error' => 'A solicitação de dirigente precisa ser analisada pela administração global.'];
        }
        if ($membership['status'] === 'Pendente' && !$isGlobalAdmin && !$isLocalLeader) {
            return ['error' => 'Você não tem permissão para analisar solicitações desta casa.'];
        }

        $newStatus = $approve ? 'Ativo' : 'Recusado';
        $note = trim(mb_substr($note, 0, 1000));
        $update = $this->db->prepare(
            'UPDATE tenant_memberships SET status = ?, aprovado_em = NOW(), aprovado_por_user_id = ?, observacao_decisao = ? WHERE id = ?'
        );
        $update->execute([$newStatus, $actorId, $note ?: null, $membershipId]);

        if ($approve && in_array($membership['papel'], ['Babalorixa', 'Yalorixa'], true)) {
            $role = $membership['papel'] === 'Babalorixa' ? 'Babalorixá' : 'Yalorixá';
            $tenantUpdate = $this->db->prepare("UPDATE tenants SET dirigente_status = 'Verificado', dirigente_publico = COALESCE(dirigente_publico, ?) WHERE id = ?");
            $tenantUpdate->execute([$membership['solicitante_nome'], (int) $membership['tenant_id']]);
            $userUpdate = $this->db->prepare("UPDATE users SET id_tenant = COALESCE(id_tenant, ?), role = 'Regente' WHERE id = ?");
            $userUpdate->execute([(int) $membership['tenant_id'], (int) $membership['user_id']]);
            $this->log($actorId, (int) $membership['tenant_id'], 'dirigencia_aprovada', 'tenant_memberships', $membershipId, $role . ' verificado(a).');
        }

        if ($approve && $membership['papel'] === 'FilhoDeSanto') {
            $this->syncApprovedChildToTenant($membership);
        }
        $this->log($actorId, (int) $membership['tenant_id'], $approve ? 'vinculo_aprovado' : 'vinculo_recusado', 'tenant_memberships', $membershipId, 'Papel: ' . $membership['papel']);
        return ['status' => $newStatus, 'tenant_id' => (int) $membership['tenant_id']];
    }

    public function listPendingMembershipsForTenant(int $tenantId, bool $includeGlobal = false): array
    {
        $statuses = $includeGlobal ? "('Pendente','PendenteAdminGlobal')" : "('Pendente')";
        $stmt = $this->db->prepare(
            "SELECT m.*, u.nome, u.email, u.whatsapp FROM tenant_memberships m INNER JOIN users u ON u.id = m.user_id
             WHERE m.tenant_id = ? AND m.status IN $statuses ORDER BY m.solicitado_em ASC"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function listPendingPublicTenantSubmissions(): array
    {
        $stmt = $this->db->query(
            "SELECT id, nome_exibicao, solicitante_cadastro_nome, email_responsavel, cidade_publica, estado_publico, nacao_publica, descricao_publica, horarios_publicos, created_at
             FROM tenants
             WHERE cadastro_publico_pendente = 1 AND status = 'Suspenso'
             ORDER BY created_at ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function decidePublicTenantSubmission(int $actorId, int $tenantId, bool $approve, string $note = ''): array
    {
        if (!$this->isGlobalAdmin($actorId)) {
            return ['error' => 'Somente a administração global pode analisar cadastros públicos de centros.'];
        }
        $stmt = $this->db->prepare("SELECT id, nome_exibicao FROM tenants WHERE id = ? AND cadastro_publico_pendente = 1 AND status = 'Suspenso' LIMIT 1");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            return ['error' => 'Este cadastro não está disponível para decisão.'];
        }
        $note = trim(mb_substr($note, 0, 1000));
        $update = $this->db->prepare(
            $approve
                ? "UPDATE tenants SET status = 'Ativo', cadastro_publico_pendente = 0, listar_publicamente = 1 WHERE id = ?"
                : "UPDATE tenants SET status = 'Inativo', cadastro_publico_pendente = 0, listar_publicamente = 0 WHERE id = ?"
        );
        $update->execute([$tenantId]);
        $this->log($actorId, $tenantId, $approve ? 'cadastro_publico_centro_aprovado' : 'cadastro_publico_centro_recusado', 'tenants', $tenantId, $note ?: 'Decisão da administração global.');
        return ['status' => $approve ? 'Ativo' : 'Inativo', 'tenant_id' => $tenantId, 'nome_exibicao' => $tenant['nome_exibicao']];
    }

    public function listGlobalPendingLeadership(): array
    {
        $stmt = $this->db->query(
            "SELECT m.*, u.nome, u.email, t.nome_exibicao, t.slug
             FROM tenant_memberships m
             INNER JOIN users u ON u.id = m.user_id
             INNER JOIN tenants t ON t.id = m.tenant_id
             WHERE m.status = 'PendenteAdminGlobal'
             ORDER BY m.solicitado_em ASC"
        );
        return $stmt->fetchAll();
    }

    public function requestConsultation(?int $userId, int $tenantId, string $name, ?string $whatsapp, ?string $email, ?string $availability, ?string $message): array
    {
        $tenant = $this->getTenantForRequest($tenantId);
        if (!$tenant || !(int) $tenant['aceita_consultas']) {
            return ['error' => 'Esta casa não está recebendo solicitações de consulta pelo sistema neste momento.'];
        }
        $name = trim(mb_substr($name, 0, 255));
        $whatsapp = $this->normalizePhone($whatsapp);
        $email = mb_strtolower(trim((string) $email));
        if (mb_strlen($name) < 3) {
            return ['error' => 'Informe seu nome para que a casa possa responder.'];
        }
        if ($whatsapp === null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Informe WhatsApp ou e-mail para retorno.'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO tenant_consultation_requests (tenant_id, user_id, nome_contato, whatsapp_contato, email_contato, disponibilidade, mensagem, consentimento_contato_em) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $userId, $name, $whatsapp, filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null, trim(mb_substr((string) $availability, 0, 255)) ?: null, trim(mb_substr((string) $message, 0, 1500)) ?: null]);
        $id = (int) $this->db->lastInsertId();
        $this->log($userId, $tenantId, 'consulta_solicitada', 'tenant_consultation_requests', $id, 'Solicitação criada pelo diretório público.');
        return ['id' => $id];
    }

    public function selectMembership(int $userId, int $tenantId): array
    {
        if ($this->isGlobalAdmin($userId)) {
            $tenant = $this->getTenantById($tenantId);
            if ($tenant) {
                return ['tenant_id' => (int) $tenant['id'], 'slug' => $tenant['slug'], 'role' => 'SuperAdmin'];
            }
        }
        $stmt = $this->db->prepare(
            "SELECT m.tenant_id, t.slug,
                    CASE m.papel
                        WHEN 'Babalorixa' THEN 'Regente'
                        WHEN 'Yalorixa' THEN 'Regente'
                        WHEN 'Colaborador' THEN 'Secretario'
                        ELSE 'Leitor'
                    END AS app_role
             FROM tenant_memberships m
             INNER JOIN tenants t ON t.id = m.tenant_id
             WHERE m.user_id = ? AND m.tenant_id = ? AND m.status = 'Ativo' AND t.status = 'Ativo'
             ORDER BY FIELD(m.papel, 'Babalorixa', 'Yalorixa', 'Colaborador', 'FilhoDeSanto', 'Assistencia', 'Consulente')
             LIMIT 1"
        );
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['error' => 'Você não possui um vínculo ativo com esta casa.'];
        }
        return ['tenant_id' => (int) $row['tenant_id'], 'slug' => $row['slug'], 'role' => $row['app_role']];
    }

    public function canManageTenant(int $userId, int $tenantId): bool
    {
        return $this->isGlobalAdmin($userId) || $this->isLocalLeader($userId, $tenantId);
    }

    private function getTenantForRequest(int $tenantId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, aceita_solicitacoes_vinculo, aceita_consultas, dirigente_status FROM tenants WHERE id = ? AND status = 'Ativo' LIMIT 1");
        $stmt->execute([$tenantId]);
        return $stmt->fetch() ?: null;
    }

    private function getTenantById(int $tenantId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, slug FROM tenants WHERE id = ? AND status = 'Ativo' LIMIT 1");
        $stmt->execute([$tenantId]);
        return $stmt->fetch() ?: null;
    }

    private function tenantHasVerifiedLeader(int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM tenant_memberships WHERE tenant_id = ? AND status = 'Ativo' AND papel IN ('Babalorixa', 'Yalorixa') LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    private function isLocalLeader(int $userId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM tenant_memberships WHERE tenant_id = ? AND user_id = ? AND status = 'Ativo' AND papel IN ('Babalorixa', 'Yalorixa') LIMIT 1"
        );
        $stmt->execute([$tenantId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private function syncApprovedChildToTenant(array $membership): void
    {
        try {
            require_once __DIR__ . '/TenantManager.php';
            $manager = new TenantManager();
            $conn = $manager->getTenantConnection((string) $membership['slug'], (int) $membership['tenant_id']);
            if (!$conn) {
                throw new RuntimeException('Conexão isolada indisponível.');
            }
            $exists = $conn->prepare('SELECT id FROM filhos WHERE nome = ? AND whatsapp <=> ? LIMIT 1');
            $exists->execute([$membership['solicitante_nome'], $membership['solicitante_whatsapp'] ?: null]);
            if (!$exists->fetch()) {
                $insert = $conn->prepare("INSERT INTO filhos (nome, whatsapp, data_entrada, cargo, status) VALUES (?, ?, CURDATE(), 'Filho de santo', 'Ativo')");
                $insert->execute([$membership['solicitante_nome'], $membership['solicitante_whatsapp'] ?: null]);
            }
        } catch (Throwable $e) {
            error_log('Vínculo aprovado sem espelhamento local de filho: ' . $e->getMessage());
        }
    }

    /**
     * Moderação exclusiva da administração global.
     * Ocultar/publicar novamente e suspender/reativar são reversíveis.
     * A exclusão definitiva exige backup, motivo e a frase EXCLUIR <slug>.
     */
    public function moderateTenant(int $actorId, int $tenantId, string $action, string $reason = '', string $confirmation = ''): array
    {
        if (!$this->isGlobalAdmin($actorId)) {
            return ['error' => 'Somente a administração global pode moderar centros.'];
        }

        $allowed = ['hide', 'publish', 'suspend', 'reactivate', 'delete'];
        if (!in_array($action, $allowed, true)) {
            return ['error' => 'Ação de moderação inválida.'];
        }

        $stmt = $this->db->prepare(
            'SELECT id, slug, db_name, nome_exibicao, status, listar_publicamente FROM tenants WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            return ['error' => 'Centro não encontrado.'];
        }

        $reason = trim(mb_substr($reason, 0, 1000));
        $tenantName = (string) $tenant['nome_exibicao'];
        $slug = (string) $tenant['slug'];

        if ($action === 'delete') {
            if (strlen($reason) < 10) {
                return ['error' => 'Informe um motivo com pelo menos 10 caracteres para a exclusão.'];
            }
            $expected = 'EXCLUIR ' . $slug;
            if (!hash_equals($expected, trim($confirmation))) {
                return ['error' => 'Para confirmar, digite exatamente: ' . $expected];
            }

            try {
                $backupPath = $this->backupTenantDatabase((string) $tenant['db_name'], $slug);
                $quarantine = $this->db->prepare("UPDATE tenants SET status = 'Inativo', listar_publicamente = 0, mostrar_no_mapa = 0 WHERE id = ?");
                $quarantine->execute([$tenantId]);
                $this->log($actorId, $tenantId, 'centro_exclusao_iniciada', 'tenants', $tenantId, 'Backup: ' . basename($backupPath) . '. Motivo: ' . ($reason ?: 'não informado'));
                $this->dropTenantDatabase((string) $tenant['db_name']);

                $delete = $this->db->prepare('DELETE FROM tenants WHERE id = ?');
                $delete->execute([$tenantId]);
                if ($delete->rowCount() !== 1) {
                    throw new RuntimeException('O registro central não foi removido após a exclusão do banco.');
                }
                $this->log($actorId, null, 'centro_excluido_definitivamente', 'tenants', $tenantId, 'Centro: ' . $tenantName . ' (' . $slug . '). Backup: ' . basename($backupPath) . '. Motivo: ' . $reason);
                return ['status' => 'deleted', 'tenant_id' => $tenantId, 'nome_exibicao' => $tenantName, 'backup' => $backupPath];
            } catch (Throwable $e) {
                error_log('Falha na exclusão definitiva do tenant ' . $tenantId . ': ' . $e->getMessage());
                try {
                    $this->log($actorId, $tenantId, 'centro_exclusao_falhou', 'tenants', $tenantId, mb_substr($e->getMessage(), 0, 900));
                } catch (Throwable $auditError) {
                    error_log('Falha ao registrar auditoria de exclusão: ' . $auditError->getMessage());
                }
                return ['error' => 'A exclusão não foi concluída. O centro foi retirado do diretório para análise e o erro foi registrado.'];
            }
        }

        $sql = match ($action) {
            'hide' => "UPDATE tenants SET listar_publicamente = 0, mostrar_no_mapa = 0 WHERE id = ?",
            'publish' => "UPDATE tenants SET status = 'Ativo', listar_publicamente = 1 WHERE id = ? AND status = 'Ativo'",
            'suspend' => "UPDATE tenants SET status = 'Suspenso', listar_publicamente = 0, mostrar_no_mapa = 0 WHERE id = ?",
            'reactivate' => "UPDATE tenants SET status = 'Ativo', listar_publicamente = 1, cadastro_publico_pendente = 0 WHERE id = ? AND status = 'Suspenso'",
        };
        $update = $this->db->prepare($sql);
        $update->execute([$tenantId]);
        if ($update->rowCount() < 1) {
            return ['error' => 'A ação não se aplica ao estado atual deste centro.'];
        }

        $labels = [
            'hide' => 'centro_ocultado_do_diretorio',
            'publish' => 'centro_publicado_no_diretorio',
            'suspend' => 'centro_suspenso',
            'reactivate' => 'centro_reativado',
        ];
        $this->log($actorId, $tenantId, $labels[$action], 'tenants', $tenantId, $reason ?: 'Ação de moderação global.');
        return ['status' => $action, 'tenant_id' => $tenantId, 'nome_exibicao' => $tenantName];
    }

    public function listTenantsForGlobalAdmin(int $limit = 100): array
    {
        $limit = max(1, min($limit, 100));
        $stmt = $this->db->query(
            "SELECT t.id, t.slug, t.nome_exibicao, t.status, t.dirigente_status, t.listar_publicamente,
                    t.cidade_publica, t.estado_publico, t.created_at,
                    (SELECT COUNT(*) FROM tenant_memberships m WHERE m.tenant_id = t.id AND m.status = 'Ativo') AS membros_ativos,
                    (SELECT COUNT(*) FROM tenant_memberships m WHERE m.tenant_id = t.id AND m.status IN ('Pendente','PendenteAdminGlobal')) AS pendencias
             FROM tenants t ORDER BY t.created_at DESC LIMIT $limit"
        );
        return $stmt->fetchAll();
    }

    private function backupTenantDatabase(string $dbName, string $slug): string
    {
        if (!function_exists('exec')) {
            throw new RuntimeException('A função de backup não está disponível no PHP.');
        }
        $directory = defined('MEUTERREIRO_BACKUP_DIR') ? MEUTERREIRO_BACKUP_DIR : (__DIR__ . '/../storage/backups');
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório privado de backups.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('O diretório privado de backups não permite gravação.');
        }

        $safeSlug = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $slug) ?: 'centro';
        $dumpPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tenant_' . $safeSlug . '_' . gmdate('Ymd_His') . '.sql';
        $credentialsPath = tempnam(sys_get_temp_dir(), 'meuterreiro-db-');
        if ($credentialsPath === false) {
            throw new RuntimeException('Não foi possível preparar o backup privado.');
        }
        $credentials = "[client]\nhost=" . CENTRAL_DB_HOST . "\nuser=" . CENTRAL_DB_USER . "\npassword=" . CENTRAL_DB_PASS . "\n";
        if (file_put_contents($credentialsPath, $credentials) === false) {
            @unlink($credentialsPath);
            throw new RuntimeException('Não foi possível preparar as credenciais temporárias do backup.');
        }
        @chmod($credentialsPath, 0600);

        $command = 'mariadb-dump --defaults-extra-file=' . escapeshellarg($credentialsPath)
            . ' --single-transaction --routines --events --triggers --hex-blob --no-tablespaces '
            . escapeshellarg($dbName) . ' > ' . escapeshellarg($dumpPath) . ' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);
        @unlink($credentialsPath);
        if ($exitCode !== 0 || !is_file($dumpPath) || filesize($dumpPath) < 1) {
            @unlink($dumpPath);
            throw new RuntimeException('O dump do banco isolado falhou: ' . mb_substr(implode(' ', $output), 0, 300));
        }

        $gzipOutput = [];
        $gzipExit = 1;
        exec('gzip -f ' . escapeshellarg($dumpPath) . ' 2>&1', $gzipOutput, $gzipExit);
        if ($gzipExit !== 0 || !is_file($dumpPath . '.gz')) {
            @unlink($dumpPath);
            throw new RuntimeException('A compactação do backup falhou.');
        }
        @chmod($dumpPath . '.gz', 0600);
        return $dumpPath . '.gz';
    }

    private function dropTenantDatabase(string $dbName): void
    {
        $safeDbName = str_replace('`', '``', $dbName);
        $provisioner = ProvisionerDB::getConnection();
        $provisioner->exec('DROP DATABASE IF EXISTS `' . $safeDbName . '`');
    }

    public function listConsultationRequests(int $actorId, int $tenantId): array
    {
        if (!$this->canManageTenant($actorId, $tenantId)) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT id, nome_contato, whatsapp_contato, email_contato, disponibilidade, mensagem, status, solicitado_em
             FROM tenant_consultation_requests WHERE tenant_id = ? ORDER BY solicitado_em DESC LIMIT 100"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    private function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
