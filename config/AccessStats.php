<?php

declare(strict_types=1);

/**
 * Leitura agregada dos acessos públicos do Meu Terreiro.
 *
 * O leitor não retorna nem persiste IP, user-agent, URL completa ou query
 * string. Apenas páginas públicas permitidas são contabilizadas.
 */
final class AccessStats
{
    private const DEFAULT_LOG = '/var/log/nginx/saravaumbandatk/access.log';
    private const PUBLIC_PREFIX = '/meuterreiro/public';

    public function summarize(int $days = 14): array
    {
        $days = max(1, min($days, 30));
        $cutoff = time() - ($days * 86400);
        $daily = [];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = (new DateTimeImmutable('today'))->modify('-' . $offset . ' days')->format('Y-m-d');
            $daily[$date] = 0;
        }

        $total = 0;
        $successful = 0;
        $errors = 0;
        $pages = [];
        $referrers = [];

        foreach ($this->logFiles() as $file) {
            $modifiedAt = @filemtime($file);
            if ($modifiedAt !== false && $modifiedAt < ($cutoff - 172800)) {
                continue;
            }

            foreach ($this->readLines($file) as $line) {
                $entry = $this->parseLine($line);
                if ($entry === null || $entry['timestamp']->getTimestamp() < $cutoff) {
                    continue;
                }

                $page = $this->publicPageLabel($entry['method'], $entry['target']);
                if ($page === null) {
                    continue;
                }

                $total++;
                if ($entry['status'] >= 200 && $entry['status'] < 400) {
                    $successful++;
                } else {
                    $errors++;
                }

                $date = $entry['timestamp']->format('Y-m-d');
                if (array_key_exists($date, $daily)) {
                    $daily[$date]++;
                }
                $pages[$page] = ($pages[$page] ?? 0) + 1;

                $referrer = $this->referrerLabel($entry['referrer']);
                $referrers[$referrer] = ($referrers[$referrer] ?? 0) + 1;
            }
        }

        arsort($pages);
        arsort($referrers);

        $dailyRows = [];
        foreach ($daily as $date => $count) {
            $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $dailyRows[] = [
                'date' => $date,
                'label' => $dateObject ? $dateObject->format('d/m') : $date,
                'total' => $count,
            ];
        }

        return [
            'days' => $days,
            'total' => $total,
            'successful' => $successful,
            'errors' => $errors,
            'daily' => $dailyRows,
            'pages' => array_slice($pages, 0, 8, true),
            'referrers' => array_slice($referrers, 0, 8, true),
            'generated_at' => new DateTimeImmutable(),
        ];
    }

    /** @return list<string> */
    private function logFiles(): array
    {
        $base = getenv('MEUTERREIRO_NGINX_ACCESS_LOG') ?: self::DEFAULT_LOG;
        $candidates = glob($base . '*') ?: [];
        $files = [];
        foreach ($candidates as $file) {
            $name = basename($file);
            if (preg_match('/^access\.log(?:\.\d+)?(?:\.gz)?$/', $name) && is_readable($file)) {
                $files[] = $file;
            }
        }
        usort($files, static fn(string $left, string $right): int => (int) (@filemtime($right) <=> @filemtime($left)));
        return $files;
    }

    /** @return iterable<string> */
    private function readLines(string $file): iterable
    {
        if (str_ends_with($file, '.gz')) {
            $handle = @gzopen($file, 'rb');
            if ($handle === false) {
                return;
            }
            try {
                while (!gzeof($handle)) {
                    $line = gzgets($handle, 65536);
                    if ($line === false) {
                        break;
                    }
                    yield $line;
                }
            } finally {
                gzclose($handle);
            }
            return;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return;
        }
        try {
            while (($line = fgets($handle, 65536)) !== false) {
                yield $line;
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return array{timestamp: DateTimeImmutable, method: string, target: string, status: int, referrer: string}|null */
    private function parseLine(string $line): ?array
    {
        $matched = preg_match('/^\S+\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s+([^\"]+)\s+HTTP\/[^\"]+"\s+(\d{3})\s+\S+\s+"([^\"]*)"/', $line, $parts);
        if ($matched !== 1) {
            return null;
        }

        $timestamp = DateTimeImmutable::createFromFormat('d/M/Y:H:i:s O', $parts[1]);
        if ($timestamp === false) {
            return null;
        }

        return [
            'timestamp' => $timestamp,
            'method' => $parts[2],
            'target' => $parts[3],
            'status' => (int) $parts[4],
            'referrer' => $parts[5],
        ];
    }

    private function publicPageLabel(string $method, string $target): ?string
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return null;
        }

        $path = (string) parse_url($target, PHP_URL_PATH);
        $prefix = rtrim(self::PUBLIC_PREFIX, '/');
        if ($path !== $prefix && !str_starts_with($path, $prefix . '/')) {
            return null;
        }

        $relative = ltrim(substr($path, strlen($prefix)), '/');
        if ($relative === '' || $relative === 'index.php') {
            $query = [];
            parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
            return match ((string) ($query['p'] ?? '')) {
                '', 'login' => 'Entrada / login',
                'cadastro' => 'Criação de conta',
                'cadastrar-centro' => 'Cadastro público de centro',
                default => null,
            };
        }

        return match ($relative) {
            'directory.php' => 'Diretório público',
            'sobre.php' => 'Página Sobre',
            'terreiro.php' => 'Perfil público de casa',
            default => null,
        };
    }

    private function referrerLabel(string $referrer): string
    {
        if ($referrer === '' || $referrer === '-') {
            return 'Direto / não informado';
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return 'Direto / não informado';
        }
        return strtolower($host);
    }
}
