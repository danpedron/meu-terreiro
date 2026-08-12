<?php

declare(strict_types=1);

/**
 * Utilitários de SEO para conteúdo publicamente autorizado.
 * Não devem ser usados para dados internos, páginas autenticadas ou formulários sensíveis.
 */
function meu_terreiro_public_base_url(): string
{
    $configured = trim((string) getenv('MEU_TERREIRO_PUBLIC_URL'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $directory = rtrim(dirname($script), '/');

    return $scheme . '://' . $host . ($directory === '' || $directory === '.' ? '' : $directory);
}

function meu_terreiro_canonical_url(string $path, array $parameters = []): string
{
    $url = rtrim(meu_terreiro_public_base_url(), '/') . '/' . ltrim($path, '/');
    $clean = [];

    foreach ($parameters as $key => $value) {
        if (is_scalar($value) && (string) $value !== '') {
            $clean[(string) $key] = (string) $value;
        }
    }

    return $clean ? $url . '?' . http_build_query($clean, '', '&', PHP_QUERY_RFC3986) : $url;
}

function meu_terreiro_seo_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function meu_terreiro_compact_text(?string $value, int $limit = 160): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    return mb_strimwidth($text, 0, $limit, '…', 'UTF-8');
}

function meu_terreiro_send_noindex_header(): void
{
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow', true);
    }
}

/**
 * Renderiza metadados públicos. O JSON-LD deve representar somente texto já visível na página.
 *
 * @param array<int, array<string, mixed>> $jsonLd
 */
function meu_terreiro_render_seo_head(
    string $title,
    string $description,
    string $canonicalUrl,
    bool $indexable = true,
    array $jsonLd = []
): void {
    $robots = $indexable ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'noindex, nofollow';
    $safeTitle = meu_terreiro_seo_escape($title);
    $safeDescription = meu_terreiro_seo_escape($description);
    $safeUrl = meu_terreiro_seo_escape($canonicalUrl);

    echo '<title>' . $safeTitle . '</title>' . "\n";
    echo '<meta name="description" content="' . $safeDescription . '">' . "\n";
    echo '<meta name="robots" content="' . $robots . '">' . "\n";
    echo '<link rel="canonical" href="' . $safeUrl . '">' . "\n";
    echo '<meta property="og:locale" content="pt_BR">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="Meu Terreiro">' . "\n";
    echo '<meta property="og:title" content="' . $safeTitle . '">' . "\n";
    echo '<meta property="og:description" content="' . $safeDescription . '">' . "\n";
    echo '<meta property="og:url" content="' . $safeUrl . '">' . "\n";
    echo '<meta name="twitter:card" content="summary">' . "\n";
    echo '<meta name="twitter:title" content="' . $safeTitle . '">' . "\n";
    echo '<meta name="twitter:description" content="' . $safeDescription . '">' . "\n";

    foreach ($jsonLd as $item) {
        $encoded = json_encode(
            $item,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if ($encoded !== false) {
            echo '<script type="application/ld+json">' . $encoded . '</script>' . "\n";
        }
    }
}
