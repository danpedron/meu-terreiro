<?php
require_once __DIR__ . '/db_config.php';

final class TenantManager
{
    private PDO $centralConn;

    public function __construct()
    {
        $this->centralConn = CentralDB::getInstance()->getConnection();
    }

    public function getTenantConnection(string $slug, ?int $tenantId = null): ?PDO
    {
        $sql = "SELECT id, db_name FROM tenants WHERE slug = ? AND status = 'Ativo'";
        $params = [$slug];
        if ($tenantId !== null) {
            $sql .= ' AND id = ?';
            $params[] = $tenantId;
        }

        $stmt = $this->centralConn->prepare($sql);
        $stmt->execute($params);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            return null;
        }

        try {
            return database_connection($tenant['db_name'], CENTRAL_DB_USER, CENTRAL_DB_PASS);
        } catch (PDOException $e) {
            error_log('Erro de conexão do tenant: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria um tenant, seu banco isolado e o primeiro usuário dirigente.
     * Não registra ou exibe credenciais do banco em hipótese alguma.
     *
     * @return array{tenant_id:int,slug:string}|array{error:string}
     */
    public function createTenantWithAdmin(
        string $nomeTerreiro,
        string $nacao,
        ?string $fundacao,
        string $nomeResponsavel,
        string $email,
        string $password,
        bool $aceitouTermos
    ): array {
        $nomeTerreiro = trim($nomeTerreiro);
        $nacao = trim($nacao);
        $nomeResponsavel = trim($nomeResponsavel);
        $email = mb_strtolower(trim($email));
        $slug = self::slugify($nomeTerreiro);

        if (mb_strlen($nomeTerreiro) < 3 || mb_strlen($nomeTerreiro) > 255) {
            return ['error' => 'Informe o nome do terreiro com pelo menos 3 caracteres.'];
        }
        if (mb_strlen($nomeResponsavel) < 3 || mb_strlen($nomeResponsavel) > 255) {
            return ['error' => 'Informe o nome da pessoa responsável.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Informe um e-mail válido.'];
        }
        if (strlen($password) < 12) {
            return ['error' => 'Crie uma senha com pelo menos 12 caracteres.'];
        }
        if (!$aceitouTermos) {
            return ['error' => 'É necessário confirmar a responsabilidade pelo uso e a proteção dos dados.'];
        }
        if ($slug === '' || strlen($slug) > 50) {
            return ['error' => 'Não foi possível criar um identificador seguro para este terreiro.'];
        }

        $dbName = 'meuterreiro_' . $slug;
        $existing = $this->centralConn->prepare('SELECT id FROM tenants WHERE slug = ? OR db_name = ? LIMIT 1');
        $existing->execute([$slug, $dbName]);
        if ($existing->fetch()) {
            return ['error' => 'Já existe um terreiro com esse identificador. Ajuste o nome informado.'];
        }

        $existingUser = $this->centralConn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $existingUser->execute([$email]);
        if ($existingUser->fetch()) {
            return ['error' => 'Este e-mail já possui uma conta cadastrada.'];
        }

        $schemaPath = __DIR__ . '/../database/terreiro_schema.sql';
        if (!is_readable($schemaPath)) {
            error_log('Schema do tenant não encontrado: ' . $schemaPath);
            return ['error' => 'Não foi possível preparar a estrutura do novo terreiro.'];
        }

        $databaseCreated = false;
        $provisionerConn = null;
        try {
            // dbName vem exclusivamente do slug normalizado; nunca de texto livre.
            $provisionerConn = ProvisionerDB::getConnection();
            $provisionerConn->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $databaseCreated = true;

            $provisionedTenantConn = database_connection($dbName, PROVISIONER_DB_USER, PROVISIONER_DB_PASS);
            $provisionedTenantConn->exec((string) file_get_contents($schemaPath));
            $tenantConn = database_connection($dbName, CENTRAL_DB_USER, CENTRAL_DB_PASS);

            $this->centralConn->beginTransaction();
            $stmt = $this->centralConn->prepare(
                "INSERT INTO tenants (slug, db_name, nome_exibicao, email_responsavel, status, onboarding_status, termos_aceitos_em)
                 VALUES (?, ?, ?, ?, 'Ativo', 'Em configuração', NOW())"
            );
            $stmt->execute([$slug, $dbName, $nomeTerreiro, $email]);
            $tenantId = (int) $this->centralConn->lastInsertId();

            $userStmt = $this->centralConn->prepare(
                "INSERT INTO users (id_tenant, nome, email, password_hash, role, status)
                 VALUES (?, ?, ?, ?, 'Regente', 'Ativo')"
            );
            $userStmt->execute([$tenantId, $nomeResponsavel, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $this->centralConn->commit();

            $infoStmt = $tenantConn->prepare(
                'INSERT INTO terreiro_info (nome, fundacao, nacao, babalorixa, yalorixa) VALUES (?, ?, ?, ?, ?)'
            );
            $infoStmt->execute([$nomeTerreiro, $fundacao ?: null, $nacao ?: null, null, null]);
            $detalhesStmt = $tenantConn->prepare('INSERT INTO terreiro_detalhes (email_contato) VALUES (?)');
            $detalhesStmt->execute([$email]);

            return ['tenant_id' => $tenantId, 'slug' => $slug];
        } catch (Throwable $e) {
            if ($this->centralConn->inTransaction()) {
                $this->centralConn->rollBack();
            }
            if ($databaseCreated) {
                try {
                    if ($provisionerConn instanceof PDO) {
                        $provisionerConn->exec("DROP DATABASE IF EXISTS `$dbName`");
                    }
                } catch (Throwable $cleanupError) {
                    error_log('Falha ao limpar banco de tenant: ' . $cleanupError->getMessage());
                }
            }
            error_log('Falha ao criar tenant: ' . $e->getMessage());
            return ['error' => 'Não foi possível concluir o cadastro agora. Tente novamente ou contate a administração.'];
        }
    }

    /**
     * Cria uma casa para uma conta global já autenticada. A criação da casa não
     * concede liderança automaticamente: pedidos de Babalorixá/Yalorixá ficam
     * pendentes para análise do administrador global.
     *
     * @return array{tenant_id:int,slug:string,leadership_pending:bool}|array{error:string}
     */
    public function createTenantForUser(
        int $userId,
        string $nomeTerreiro,
        string $nacao,
        ?string $fundacao,
        string $roleRequested,
        bool $aceitouTermos,
        array $publicProfile = []
    ): array {
        $nomeTerreiro = trim($nomeTerreiro);
        $nacao = trim($nacao);
        $roleRequested = in_array($roleRequested, ['Babalorixa', 'Yalorixa'], true) ? $roleRequested : 'Colaborador';
        $slug = self::slugify($nomeTerreiro);

        if ($userId <= 0 || mb_strlen($nomeTerreiro) < 3 || mb_strlen($nomeTerreiro) > 255) {
            return ['error' => 'Informe o nome da casa com pelo menos 3 caracteres.'];
        }
        if (!$aceitouTermos) {
            return ['error' => 'Confirme que possui autorização para cadastrar a casa e proteger os dados informados.'];
        }
        if ($slug === '' || strlen($slug) > 50) {
            return ['error' => 'Não foi possível criar um identificador seguro para esta casa.'];
        }
        $userStmt = $this->centralConn->prepare("SELECT id, nome, email FROM users WHERE id = ? AND status = 'Ativo' LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user) {
            return ['error' => 'Sua conta não está disponível para cadastrar uma casa.'];
        }
        $profile = $this->normalizePublicProfile($publicProfile, $nacao);
        if (isset($profile['error'])) {
            return ['error' => $profile['error']];
        }

        $dbName = 'meuterreiro_' . $slug;
        $existing = $this->centralConn->prepare('SELECT id FROM tenants WHERE slug = ? OR db_name = ? LIMIT 1');
        $existing->execute([$slug, $dbName]);
        if ($existing->fetch()) {
            return ['error' => 'Já existe uma casa com esse identificador. Ajuste o nome informado.'];
        }
        $schemaPath = __DIR__ . '/../database/terreiro_schema.sql';
        if (!is_readable($schemaPath)) {
            error_log('Schema do tenant não encontrado: ' . $schemaPath);
            return ['error' => 'Não foi possível preparar a estrutura da nova casa.'];
        }

        $databaseCreated = false;
        $provisionerConn = null;
        try {
            $provisionerConn = ProvisionerDB::getConnection();
            $provisionerConn->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $databaseCreated = true;
            $provisionedTenantConn = database_connection($dbName, PROVISIONER_DB_USER, PROVISIONER_DB_PASS);
            $provisionedTenantConn->exec((string) file_get_contents($schemaPath));
            $tenantConn = database_connection($dbName, CENTRAL_DB_USER, CENTRAL_DB_PASS);

            $this->centralConn->beginTransaction();
            $tenantStmt = $this->centralConn->prepare(
                "INSERT INTO tenants (slug, db_name, nome_exibicao, email_responsavel, status, onboarding_status, termos_aceitos_em, dirigente_status, listar_publicamente, mostrar_no_mapa, localizacao_publica, cidade_publica, estado_publico, latitude_publica, longitude_publica, descricao_publica, nacao_publica, horarios_publicos, aceita_solicitacoes_vinculo)
                 VALUES (?, ?, ?, ?, 'Ativo', 'Em configuração', NOW(), 'Sem dirigente', 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            $tenantStmt->execute([$slug, $dbName, $nomeTerreiro, $user['email'], $profile['mostrar_no_mapa'], $profile['localizacao_publica'], $profile['cidade_publica'], $profile['estado_publico'], $profile['latitude_publica'], $profile['longitude_publica'], $profile['descricao_publica'], $profile['nacao_publica'], $profile['horarios_publicos']]);
            $tenantId = (int) $this->centralConn->lastInsertId();
            $membershipStatus = $roleRequested === 'Colaborador' ? 'Ativo' : 'PendenteAdminGlobal';
            $membershipStmt = $this->centralConn->prepare(
                "INSERT INTO tenant_memberships (tenant_id, user_id, papel, status, solicitacao, consentimento_em, solicitado_em, aprovado_em, aprovado_por_user_id)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)"
            );
            $membershipStmt->execute([
                $tenantId,
                $userId,
                $roleRequested,
                $membershipStatus,
                $roleRequested === 'Colaborador' ? 'Pessoa criadora da casa; aguardando indicação de dirigente.' : 'Pedido de verificação de dirigente feito durante a criação da casa.',
                $membershipStatus === 'Ativo' ? date('Y-m-d H:i:s') : null,
                $membershipStatus === 'Ativo' ? $userId : null,
            ]);
            $this->centralConn->commit();

            $infoStmt = $tenantConn->prepare('INSERT INTO terreiro_info (nome, fundacao, nacao, babalorixa, yalorixa) VALUES (?, ?, ?, ?, ?)');
            $infoStmt->execute([$nomeTerreiro, $fundacao ?: null, $nacao ?: null, null, null]);
            $detailStmt = $tenantConn->prepare('INSERT INTO terreiro_detalhes (descricao, cidade, estado, email_contato) VALUES (?, ?, ?, ?)');
            $detailStmt->execute([$profile['descricao_publica'], $profile['cidade_publica'], $profile['estado_publico'], $user['email']]);
            return ['tenant_id' => $tenantId, 'slug' => $slug, 'leadership_pending' => $membershipStatus === 'PendenteAdminGlobal'];
        } catch (Throwable $e) {
            if ($this->centralConn->inTransaction()) {
                $this->centralConn->rollBack();
            }
            if ($databaseCreated && $provisionerConn instanceof PDO) {
                try {
                    $provisionerConn->exec("DROP DATABASE IF EXISTS `$dbName`");
                } catch (Throwable $cleanupError) {
                    error_log('Falha ao limpar banco de tenant: ' . $cleanupError->getMessage());
                }
            }
            error_log('Falha ao criar tenant por conta global: ' . $e->getMessage());
            return ['error' => 'Não foi possível concluir o cadastro agora. Tente novamente ou contate a administração.'];
        }
    }

    /**
     * Recebe somente informações que o centro aceita tornar públicas após aprovação.
     * Endereço e contatos não são presumidos nem copiados automaticamente.
     */
    private function normalizePublicProfile(array $input, string $fallbackNation = ''): array
    {
        $clean = static fn(string $key, int $length = 3000): ?string => ($value = trim(mb_substr((string) ($input[$key] ?? ''), 0, $length))) !== '' ? $value : null;
        $city = $clean('cidade_publica', 120);
        $state = strtoupper((string) ($clean('estado_publico', 2) ?? ''));
        if ($city === null) {
            return ['error' => 'Informe a cidade para que a casa possa ser localizada no diretório.'];
        }
        if (!preg_match('/^[A-Z]{2}$/', $state)) {
            return ['error' => 'Informe a UF com duas letras para a localização pública.'];
        }
        $latRaw = str_replace(',', '.', trim((string) ($input['latitude_publica'] ?? '')));
        $lngRaw = str_replace(',', '.', trim((string) ($input['longitude_publica'] ?? '')));
        $latitude = $latRaw !== '' && is_numeric($latRaw) ? (float) $latRaw : null;
        $longitude = $lngRaw !== '' && is_numeric($lngRaw) ? (float) $lngRaw : null;
        if (($latitude === null) !== ($longitude === null) || ($latitude !== null && ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180))) {
            return ['error' => 'Informe latitude e longitude válidas juntas, ou deixe ambas vazias.'];
        }
        return [
            'cidade_publica' => $city,
            'estado_publico' => $state,
            'latitude_publica' => $latitude,
            'longitude_publica' => $longitude,
            'mostrar_no_mapa' => $latitude !== null ? 1 : 0,
            'localizacao_publica' => $latitude !== null ? 'Aproximada' : 'Bairro',
            'descricao_publica' => $clean('descricao_publica'),
            'nacao_publica' => $clean('nacao_publica', 120) ?: (trim($fallbackNation) ?: null),
            'horarios_publicos' => $clean('horarios_publicos'),
        ];
    }

    /**
     * Permite iniciar um cadastro de centro sem criar conta. O registro fica
     * suspenso e invisível até uma decisão explícita da administração global.
     */
    public function createPublicTenantSubmission(string $contactName, string $contactEmail, string $nomeTerreiro, string $nacao, ?string $fundacao, array $publicProfile, bool $aceitouTermos): array
    {
        $contactName = trim($contactName);
        $contactEmail = mb_strtolower(trim($contactEmail));
        $nomeTerreiro = trim($nomeTerreiro);
        $nacao = trim($nacao);
        $slug = self::slugify($nomeTerreiro);
        if (mb_strlen($contactName) < 3 || mb_strlen($contactName) > 255) {
            return ['error' => 'Informe o nome da pessoa responsável pelo cadastro.'];
        }
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Informe um e-mail válido para retorno da administração global.'];
        }
        if (mb_strlen($nomeTerreiro) < 3 || mb_strlen($nomeTerreiro) > 255 || $slug === '' || strlen($slug) > 50) {
            return ['error' => 'Informe o nome do centro com pelo menos 3 caracteres.'];
        }
        if (!$aceitouTermos) {
            return ['error' => 'Confirme que possui autorização para cadastrar o centro e que as informações públicas estão corretas.'];
        }
        $profile = $this->normalizePublicProfile($publicProfile, $nacao);
        if (isset($profile['error'])) {
            return ['error' => $profile['error']];
        }
        $dbName = 'meuterreiro_' . $slug;
        $existing = $this->centralConn->prepare('SELECT id FROM tenants WHERE slug = ? OR db_name = ? LIMIT 1');
        $existing->execute([$slug, $dbName]);
        if ($existing->fetch()) {
            return ['error' => 'Já existe um centro com esse identificador. Se a casa já existir, peça uma vinculação pelo diretório.'];
        }
        $schemaPath = __DIR__ . '/../database/terreiro_schema.sql';
        if (!is_readable($schemaPath)) {
            error_log('Schema do tenant não encontrado: ' . $schemaPath);
            return ['error' => 'Não foi possível preparar a estrutura do centro agora.'];
        }
        $databaseCreated = false;
        $provisionerConn = null;
        try {
            $provisionerConn = ProvisionerDB::getConnection();
            $provisionerConn->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $databaseCreated = true;
            $provisionedTenantConn = database_connection($dbName, PROVISIONER_DB_USER, PROVISIONER_DB_PASS);
            $provisionedTenantConn->exec((string) file_get_contents($schemaPath));
            $tenantConn = database_connection($dbName, CENTRAL_DB_USER, CENTRAL_DB_PASS);

            $this->centralConn->beginTransaction();
            $tenantStmt = $this->centralConn->prepare(
                "INSERT INTO tenants (slug, db_name, nome_exibicao, email_responsavel, status, onboarding_status, termos_aceitos_em, listar_publicamente, mostrar_no_mapa, localizacao_publica, cadastro_publico_pendente, solicitante_cadastro_nome, cidade_publica, estado_publico, latitude_publica, longitude_publica, descricao_publica, nacao_publica, horarios_publicos, aceita_solicitacoes_vinculo)
                 VALUES (?, ?, ?, ?, 'Suspenso', 'Em configuração', NOW(), 1, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            $tenantStmt->execute([$slug, $dbName, $nomeTerreiro, $contactEmail, $profile['mostrar_no_mapa'], $profile['localizacao_publica'], $contactName, $profile['cidade_publica'], $profile['estado_publico'], $profile['latitude_publica'], $profile['longitude_publica'], $profile['descricao_publica'], $profile['nacao_publica'], $profile['horarios_publicos']]);
            $tenantId = (int) $this->centralConn->lastInsertId();
            $this->log(null, $tenantId, 'cadastro_publico_centro_recebido', 'tenants', $tenantId, 'Cadastro sem login aguardando análise global.');
            $this->centralConn->commit();

            $infoStmt = $tenantConn->prepare('INSERT INTO terreiro_info (nome, fundacao, nacao, babalorixa, yalorixa) VALUES (?, ?, ?, ?, ?)');
            $infoStmt->execute([$nomeTerreiro, $fundacao ?: null, $nacao ?: null, null, null]);
            $detailStmt = $tenantConn->prepare('INSERT INTO terreiro_detalhes (descricao, cidade, estado, email_contato) VALUES (?, ?, ?, ?)');
            $detailStmt->execute([$profile['descricao_publica'], $profile['cidade_publica'], $profile['estado_publico'], $contactEmail]);
            return ['tenant_id' => $tenantId, 'slug' => $slug];
        } catch (Throwable $e) {
            if ($this->centralConn->inTransaction()) {
                $this->centralConn->rollBack();
            }
            if ($databaseCreated && $provisionerConn instanceof PDO) {
                try { $provisionerConn->exec("DROP DATABASE IF EXISTS `$dbName`"); } catch (Throwable $cleanupError) { error_log('Falha ao limpar banco de cadastro público: ' . $cleanupError->getMessage()); }
            }
            error_log('Falha ao cadastrar centro sem login: ' . $e->getMessage());
            return ['error' => 'Não foi possível concluir o cadastro agora. Tente novamente mais tarde.'];
        }
    }

    private static function slugify(string $value): string
    {
        $original = trim($value);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $original);
        $value = strtolower($transliterated !== false ? $transliterated : $original);
        $slug = trim((string) (preg_replace('/[^a-z0-9]+/', '-', $value) ?? ''), '-');

        // O banco aceita 50 caracteres. Nomes longos recebem um sufixo curto
        // derivado do nome original para preservar estabilidade e reduzir colisões.
        if (strlen($slug) > 50) {
            $slug = rtrim(substr($slug, 0, 43), '-') . '-' . substr(hash('sha256', $original), 0, 6);
        }
        if ($slug === '') {
            $slug = 'centro-' . substr(hash('sha256', $original), 0, 12);
        }
        return substr($slug, 0, 50);
    }
}
