<?php
require_once __DIR__ . '/db_config.php';

class TenantManager {
    private $centralConn;

    public function __construct() {
        $this->centralConn = CentralDB::getInstance()->getConnection();
    }

    /**
     * Conecta ao banco de dados específico de um terreiro
     */
    public function getTenantConnection($slug) {
        $stmt = $this->centralConn->prepare("SELECT db_name FROM tenants WHERE slug = ? AND status = 'Ativo'");
        $stmt->execute([$slug]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            return null;
        }

        try {
            $dsn = "mysql:host=" . CENTRAL_DB_HOST . ";dbname=" . $tenant['db_name'] . ";charset=" . CENTRAL_DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            return new PDO($dsn, CENTRAL_DB_USER, CENTRAL_DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Erro na conexão Tenant ($slug): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria um novo terreiro e seu banco de dados isolado
     */
    public function createTenant($slug, $nomeExibicao) {
        $dbName = "meuterreiro_" . preg_replace('/[^a-z0-9_]/', '', strtolower($slug));

        try {
            $this->centralConn->beginTransaction();

            // 1. Registrar no central
            $stmt = $this->centralConn->prepare("INSERT INTO tenants (slug, db_name, nome_exibicao) VALUES (?, ?, ?)");
            $stmt->execute([$slug, $dbName, $nomeExibicao]);
            $tenantId = $this->centralConn->lastInsertId();

            // 2. Criar banco de dados
            $this->centralConn->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 3. Aplicar esquema
            $schemaPath = __DIR__ . '/../database/terreiro_schema.sql';
            if (!file_exists($schemaPath)) {
                throw new Exception("Esquema SQL não encontrado em: $schemaPath");
            }
            $schema = file_get_contents($schemaPath);

            $tenantConn = new PDO("mysql:host=" . CENTRAL_DB_HOST . ";dbname=$dbName", CENTRAL_DB_USER, CENTRAL_DB_PASS);
            $tenantConn->exec($schema);

            $this->centralConn->commit();
            return $tenantId;
        } catch (Exception $e) {
            if ($this->centralConn->inTransaction()) {
                $this->centralConn->rollBack();
            }
            error_log("Erro ao criar Tenant: " . $e->getMessage());
            echo "ERRO: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
?>
