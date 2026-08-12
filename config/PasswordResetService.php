<?php
declare(strict_types=1);

final class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 30;
    private const MAX_REQUESTS_PER_IP = 5;
    private const RATE_WINDOW_MINUTES = 15;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Sempre retorna uma resposta genérica ao chamador para não revelar se o e-mail existe.
     */
    public function request(string $email, string $ipAddress, string $resetUrl): bool
    {
        $email = mb_strtolower(trim($email));
        $ipAddress = trim($ipAddress);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $this->cleanup();
        if ($ipAddress !== '') {
            $rateStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM password_reset_tokens WHERE request_ip = ? AND requested_at >= (NOW() - INTERVAL ' . self::RATE_WINDOW_MINUTES . ' MINUTE)'
            );
            $rateStmt->execute([mb_substr($ipAddress, 0, 45)]);
            if ((int) $rateStmt->fetchColumn() >= self::MAX_REQUESTS_PER_IP) {
                return false;
            }
        }

        $userStmt = $this->db->prepare("SELECT id, nome, email FROM users WHERE email = ? AND status = 'Ativo' LIMIT 1");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        $this->db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL')->execute([(int) $user['id']]);
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $insert = $this->db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, request_ip) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . self::TOKEN_TTL_MINUTES . ' MINUTE), ?)'
        );
        $insert->execute([(int) $user['id'], $tokenHash, $ipAddress !== '' ? mb_substr($ipAddress, 0, 45) : null]);

        $link = $resetUrl . (str_contains($resetUrl, '?') ? '&' : '?') . 'token=' . rawurlencode($token);
        $subject = 'Recuperacao de senha - Meu Terreiro';
        $body = "Olá, " . ($user['nome'] ?: 'pessoa usuária') . ",\n\n" .
            "Recebemos uma solicitação para criar uma nova senha para sua conta no Meu Terreiro.\n\n" .
            "Acesse este link em até " . self::TOKEN_TTL_MINUTES . " minutos:\n" . $link . "\n\n" .
            "Se você não fez esta solicitação, ignore esta mensagem. A senha atual continuará válida.\n\n" .
            "Com cuidado,\nMeu Terreiro";
        $headers = [
            'From: ' . self::mailFrom(),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: Meu Terreiro',
        ];

        $sent = @mail($user['email'], $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            error_log('Meu Terreiro: falha ao enviar e-mail de recuperação de senha.');
        }
        return $sent;
    }

    public function reset(string $token, string $newPassword): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/i', $token) || strlen($newPassword) < 12) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT t.id, t.user_id FROM password_reset_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.status = 'Ativo'
                 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$tokenHash]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record) {
                $this->db->rollBack();
                return false;
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateUser = $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $updateUser->execute([$passwordHash, (int) $record['user_id']]);
            $this->db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([(int) $record['user_id']]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Meu Terreiro: falha ao redefinir senha.');
            return false;
        }
    }

    private function cleanup(): void
    {
        $this->db->exec('DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');
    }

    private static function mailFrom(): string
    {
        $configured = trim((string) getenv('MEUTERREIRO_MAIL_FROM'));
        return filter_var($configured, FILTER_VALIDATE_EMAIL) ? $configured : 'no-reply@pedron.com.br';
    }
}
