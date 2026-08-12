<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/CommunityService.php';
require_once __DIR__ . '/../config/seo.php';

$baseUrl = rtrim(meu_terreiro_public_base_url(), '/');
$urls = [
    [
        'loc' => $baseUrl . '/sobre.php',
        'changefreq' => 'monthly',
        'priority' => '0.8',
    ],
    [
        'loc' => $baseUrl . '/directory.php',
        'changefreq' => 'daily',
        'priority' => '1.0',
    ],
];

try {
    $service = new CommunityService();

    foreach ($service->listPublicLocations() as $location) {
        $city = trim((string) ($location['cidade_publica'] ?? ''));
        $state = strtoupper(trim((string) ($location['estado_publico'] ?? '')));
        if ($city === '') {
            continue;
        }
        $urls[] = [
            'loc' => meu_terreiro_canonical_url('directory.php', ['cidade' => $city, 'uf' => $state ?: null]),
            'lastmod' => !empty($location['updated_at']) ? gmdate('c', strtotime((string) $location['updated_at'])) : null,
            'changefreq' => 'weekly',
            'priority' => '0.9',
        ];
    }

    foreach ($service->listPublicTenantSitemapEntries() as $tenant) {
        $urls[] = [
            'loc' => meu_terreiro_canonical_url('terreiro.php', ['c' => (string) $tenant['slug']]),
            'lastmod' => !empty($tenant['updated_at']) ? gmdate('c', strtotime((string) $tenant['updated_at'])) : null,
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];
    }
} catch (Throwable $exception) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Sitemap temporariamente indisponível.');
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($urls as $url) {
    echo '<url>';
    echo '<loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    if (!empty($url['lastmod'])) {
        echo '<lastmod>' . htmlspecialchars((string) $url['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod>';
    }
    echo '<changefreq>' . $url['changefreq'] . '</changefreq>';
    echo '<priority>' . $url['priority'] . '</priority>';
    echo '</url>';
}
echo '</urlset>';
