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
        if ($slug === '' || strlen($slug) > 40) {
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

    private static function slugify(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
