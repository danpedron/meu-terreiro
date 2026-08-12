<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/CommunityService.php';

$baseUrl = getenv('MEU_TERREIRO_PUBLIC_URL');
if (!$baseUrl) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $baseUrl = $scheme . '://' . $host . rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/sitemap.php'))), '/');
}
$baseUrl = rtrim($baseUrl, '/');

$urls = [
    $baseUrl . '/directory.php',
    $baseUrl . '/index.php?p=login',
    $baseUrl . '/index.php?p=cadastro',
];

try {
    $service = new CommunityService();
    foreach ($service->listPublicTenantSlugs() as $slug) {
        $urls[] = $baseUrl . '/terreiro.php?c=' . rawurlencode((string) $slug);
    }
} catch (Throwable $exception) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Sitemap temporariamente indisponível.');
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach (array_unique($urls) as $url) {
    echo '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
}
echo '</urlset>';
