<?php
/**
 * Configuração do banco central.
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

class CentralDB {
    private static $instance = null;
    private PDO $conn;

    private function __construct() {
        if (CENTRAL_DB_PASS === '') {
            error_log('Credencial do banco central não configurada.');
            die('Erro técnico. Configure as credenciais do banco no ambiente.');
        }

        try {
            $dsn = 'mysql:host=' . CENTRAL_DB_HOST . ';dbname=' . CENTRAL_DB_NAME . ';charset=' . CENTRAL_DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, CENTRAL_DB_USER, CENTRAL_DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Erro na conexão Central: ' . $e->getMessage());
            die('Erro técnico. Não foi possível conectar ao banco de dados.');
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->conn;
    }
}
?>
