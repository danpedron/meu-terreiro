<?php
/**
 * Configuração de banco de dados.
 *
 * Nunca coloque senhas ou tokens neste arquivo. Use variáveis de ambiente ou
 * config/db_config.local.php, que é ignorado pelo Git.
 */
$localConfig = __DIR__ . '/db_config.local.php';
if (is_readable($localConfig)) {
    require_once $localConfig;
}

define('CENTRAL_DB_HOST', getenv('MEUTERREIRO_DB_HOST') ?: '127.0.0.1');
define('CENTRAL_DB_NAME', getenv('MEUTERREIRO_DB_NAME') ?: 'meuterreiro_admin');
define('CENTRAL_DB_USER', getenv('MEUTERREIRO_DB_USER') ?: 'meuterreiro_app');
define('CENTRAL_DB_PASS', getenv('MEUTERREIRO_DB_PASS') ?: (defined('MEUTERREIRO_LOCAL_DB_PASS') ? MEUTERREIRO_LOCAL_DB_PASS : ''));
define('CENTRAL_DB_CHARSET', 'utf8mb4');

/**
 * Esta conta é usada exclusivamente no onboarding para criar o banco isolado
 * de um novo terreiro. Ela não é compartilhada pelo restante da aplicação.
 */
define('PROVISIONER_DB_USER', getenv('MEUTERREIRO_PROVISIONER_DB_USER') ?: (defined('MEUTERREIRO_PROVISIONER_LOCAL_DB_USER') ? MEUTERREIRO_PROVISIONER_LOCAL_DB_USER : 'meuterreiro_provisioner'));
define('PROVISIONER_DB_PASS', getenv('MEUTERREIRO_PROVISIONER_DB_PASS') ?: (defined('MEUTERREIRO_PROVISIONER_LOCAL_DB_PASS') ? MEUTERREIRO_PROVISIONER_LOCAL_DB_PASS : ''));

function database_connection(string $database, string $username, string $password): PDO
{
    if ($password === '') {
        throw new RuntimeException('Credencial de banco não configurada.');
    }
    return new PDO(
        'mysql:host=' . CENTRAL_DB_HOST . ';dbname=' . $database . ';charset=' . CENTRAL_DB_CHARSET,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

final class CentralDB
{
    private static ?self $instance = null;
    private PDO $conn;

    private function __construct()
    {
        try {
            $this->conn = database_connection(CENTRAL_DB_NAME, CENTRAL_DB_USER, CENTRAL_DB_PASS);
        } catch (Throwable $e) {
            error_log('Erro na conexão central: ' . $e->getMessage());
            die('Erro técnico. Não foi possível conectar ao banco de dados.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->conn;
    }
}

final class ProvisionerDB
{
    public static function getConnection(): PDO
    {
        return database_connection('mysql', PROVISIONER_DB_USER, PROVISIONER_DB_PASS);
    }
}
