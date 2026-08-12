<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/CommunityService.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/seo.php';

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function directory_query_url(array $params): string {
    $params = array_filter($params, static fn($value): bool => $value !== null && $value !== '');
    return 'directory.php' . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
}

$service = new CommunityService();
$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
$city = trim((string) ($_GET['cidade'] ?? ''));
$state = strtoupper(trim((string) ($_GET['uf'] ?? '')));
if (!preg_match('/^[A-Z]{2}$/', $state)) { $state = ''; }
$radius = filter_input(INPUT_GET, 'raio', FILTER_VALIDATE_INT) ?: 25;
$hasCoordinates = $lat !== false && $lat !== null && $lng !== false && $lng !== null;
$view = (string) ($_GET['visualizacao'] ?? 'mapa');
if (!in_array($view, ['mapa', 'lista', 'cartoes'], true)) { $view = 'mapa'; }
$results = $service->findPublicTenants($hasCoordinates ? (float) $lat : null, $hasCoordinates ? (float) $lng : null, $city ?: null, $radius, $state ?: null);
$publicLocations = $service->listPublicLocations();

// Buscas por GPS são pessoais e efêmeras; apenas páginas de cidade com resultados reais são indexáveis.
$isLocalLandingPage = !$hasCoordinates && $city !== '' && count($results) > 0;
$isIndexable = !$hasCoordinates && ($city === '' || $isLocalLandingPage);
if (!$isIndexable) { meu_terreiro_send_noindex_header(); }
$locationLabel = trim($city . ($state ? ', ' . $state : ''));
$seoTitle = $isLocalLandingPage
    ? 'Terreiros e casas de axé em ' . $locationLabel . ' — Meu Terreiro'
    : 'Encontrar terreiros e casas de axé — Meu Terreiro';
$seoDescription = $isLocalLandingPage
    ? 'Encontre casas de axé em ' . $locationLabel . ' que escolheram se apresentar publicamente, com informações compartilhadas pela própria comunidade.'
    : 'Pesquise terreiros e casas de axé que escolheram se apresentar publicamente. Busque por cidade ou use sua localização somente nesta consulta.';
$canonicalUrl = meu_terreiro_canonical_url('directory.php', $isLocalLandingPage ? ['cidade' => $city, 'uf' => $state ?: null] : []);
$jsonLd = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    '@id' => $canonicalUrl . '#webpage',
    'url' => $canonicalUrl,
    'name' => $seoTitle,
    'description' => $seoDescription,
    'inLanguage' => 'pt-BR',
    'isPartOf' => ['@id' => meu_terreiro_public_base_url() . '/#website'],
]];
if ($results) {
    $items = [];
    foreach ($results as $position => $result) {
        $items[] = ['@type' => 'ListItem', 'position' => $position + 1, 'name' => $result['nome_exibicao'], 'url' => meu_terreiro_canonical_url('terreiro.php', ['c' => $result['slug']])];
    }
    $jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $isLocalLandingPage ? 'Casas públicas em ' . $locationLabel : 'Casas públicas no diretório Meu Terreiro', 'numberOfItems' => count($items), 'itemListElement' => $items];
}

$markers = [];
foreach ($results as $result) {
    if (!empty($result['mostrar_no_mapa']) && $result['latitude_publica'] !== null && $result['longitude_publica'] !== null) {
        $markers[] = ['lat' => (float) $result['latitude_publica'], 'lng' => (float) $result['longitude_publica'], 'name' => $result['nome_exibicao'], 'city' => trim((string) ($result['cidade_publica'] ?? '')), 'url' => 'terreiro.php?c=' . rawurlencode($result['slug'])];
    }
}
$baseSearchParams = [
    'lat' => $hasCoordinates ? (string) $lat : null,
    'lng' => $hasCoordinates ? (string) $lng : null,
    'cidade' => $city ?: null,
    'uf' => $state ?: null,
    'raio' => $radius !== 25 ? (string) $radius : null,
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#5a3324">
    <?php meu_terreiro_render_seo_head($seoTitle, $seoDescription, $canonicalUrl, $isIndexable, $jsonLd); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" integrity="sha256-p4NxAoJBhIINfQ6Lr5UVR8a6G6N6au6M6F1eQSCV5p0A=" crossorigin="">
    <link href="assets/css/app.css" rel="stylesheet">
<?php meu_terreiro_analytics_head(); ?>
</head>
<body class="mt-public-body">
<nav class="navbar navbar-expand-lg mt-navbar navbar-dark">
    <div class="container"><a class="navbar-brand mt-brand" href="index.php"><span class="mt-brand-mark"><i class="fa-solid fa-leaf"></i></span>Meu Terreiro</a><div class="ms-auto d-flex gap-2"><a class="btn btn-sm btn-outline-light d-none d-sm-inline-block" href="sobre.php">Sobre</a><a class="btn btn-sm btn-outline-light" href="index.php?p=cadastrar-centro"><i class="fa-solid fa-house-circle-check me-1"></i>Cadastrar centro</a><a class="btn btn-sm btn-light" href="index.php?p=login">Entrar</a></div></div>
</nav>
<main class="container py-4 py-lg-5">
    <section class="mt-directory-hero rounded-4 p-4 p-lg-5 mb-4">
        <span class="mt-eyebrow">Diretório comunitário</span>
        <h1 class="display-6 fw-bold">Encontre uma casa com informações compartilhadas pela própria comunidade.</h1>
        <p class="lead mb-3">O mapa é o modo padrão. Você também pode alternar para lista ou cartões, sem perder a busca atual.</p>
        <a class="btn btn-light" href="index.php?p=cadastrar-centro"><i class="fa-solid fa-plus me-2"></i>Cadastrar um centro, mesmo sem conta</a>
    </section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-body p-3 p-lg-4">
        <form id="directorySearch" class="row g-3" method="get" action="directory.php">
            <input type="hidden" id="geoLat" name="lat" value="<?php echo $hasCoordinates ? e((string) $lat) : ''; ?>">
            <input type="hidden" id="geoLng" name="lng" value="<?php echo $hasCoordinates ? e((string) $lng) : ''; ?>">
            <input type="hidden" name="visualizacao" value="<?php echo e($view); ?>">
            <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="cidade">Cidade ou região</label><input class="form-control form-control-lg" id="cidade" name="cidade" value="<?php echo e($city); ?>" maxlength="120" placeholder="Ex.: Jaraguá do Sul"></div>
            <div class="col-4 col-md-2"><label class="form-label fw-semibold" for="uf">UF</label><input class="form-control form-control-lg" id="uf" name="uf" value="<?php echo e($state); ?>" maxlength="2" placeholder="SC"></div>
            <div class="col-7 col-md-2"><label class="form-label fw-semibold" for="raio">Raio máximo</label><select class="form-select form-select-lg" id="raio" name="raio"><option value="10" <?php echo $radius === 10 ? 'selected' : ''; ?>>10 km</option><option value="25" <?php echo $radius === 25 ? 'selected' : ''; ?>>25 km</option><option value="50" <?php echo $radius === 50 ? 'selected' : ''; ?>>50 km</option></select></div>
            <div class="col-5 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-lg w-100" type="submit"><i class="fa-solid fa-magnifying-glass me-2"></i>Buscar</button></div>
            <div class="col-12"><button class="btn btn-outline-primary" id="useLocation" type="button"><i class="fa-solid fa-location-crosshairs me-2"></i>Usar minha localização nesta busca</button><span class="small text-muted ms-2" id="geoStatus" aria-live="polite"></span></div>
        </form>
    </div></section>

    <section aria-label="Resultados da busca">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div><h2 class="h3 mb-1"><?php echo count($results); ?> casa(s) encontrada(s)</h2><p class="small text-muted mb-0">Centros aprovados são exibidos por padrão; cada casa pode remover seu perfil público quando desejar.</p></div>
            <div class="btn-group mt-view-toggle" role="group" aria-label="Escolher formato de exibição">
                <a class="btn btn-outline-primary <?php echo $view === 'mapa' ? 'active' : ''; ?>" href="<?php echo e(directory_query_url([...$baseSearchParams, 'visualizacao' => 'mapa'])); ?>"<?php echo $view === 'mapa' ? ' aria-current="page"' : ''; ?>><i class="fa-solid fa-map me-1"></i>Mapa</a>
                <a class="btn btn-outline-primary <?php echo $view === 'lista' ? 'active' : ''; ?>" href="<?php echo e(directory_query_url([...$baseSearchParams, 'visualizacao' => 'lista'])); ?>"<?php echo $view === 'lista' ? ' aria-current="page"' : ''; ?>><i class="fa-solid fa-list me-1"></i>Lista</a>
                <a class="btn btn-outline-primary <?php echo $view === 'cartoes' ? 'active' : ''; ?>" href="<?php echo e(directory_query_url([...$baseSearchParams, 'visualizacao' => 'cartoes'])); ?>"<?php echo $view === 'cartoes' ? ' aria-current="page"' : ''; ?>><i class="fa-solid fa-table-cells-large me-1"></i>Cartões</a>
            </div>
        </div>

        <?php if ($view === 'mapa'): ?>
            <section class="card border-0 shadow-sm mb-3" aria-label="Mapa de casas encontradas"><div class="card-body p-0">
                <?php if ($markers): ?><div id="directoryMap" class="mt-directory-map rounded-4" aria-label="Mapa interativo de casas encontradas"></div><p class="small text-muted px-3 pt-2 mb-3" id="mapStatus" aria-live="polite">Use os marcadores para abrir o perfil público de cada casa.</p>
                <?php else: ?><div class="p-4"><div class="alert alert-light border mb-0"><strong>Esta busca não tem posições públicas no mapa.</strong> As casas encontradas podem ter escolhido compartilhar somente cidade/bairro. Escolha <a href="<?php echo e(directory_query_url([...$baseSearchParams, 'visualizacao' => 'lista'])); ?>">Lista</a> ou <a href="<?php echo e(directory_query_url([...$baseSearchParams, 'visualizacao' => 'cartoes'])); ?>">Cartões</a> para ver todas.</div></div><?php endif; ?>
            </div></section>
        <?php elseif ($view === 'lista'): ?>
            <div class="card border-0 shadow-sm"><div class="list-group list-group-flush mt-directory-list">
            <?php foreach ($results as $item): ?><a class="list-group-item list-group-item-action p-3 p-lg-4" href="terreiro.php?c=<?php echo rawurlencode($item['slug']); ?>"><div class="d-flex flex-column flex-md-row justify-content-between gap-2"><div><span class="badge text-bg-light mb-2"><?php echo e($item['cidade_publica'] ?: 'Localidade compartilhada pela casa'); ?><?php echo $item['estado_publico'] ? ', ' . e($item['estado_publico']) : ''; ?></span><h3 class="h5 mb-1"><?php echo e($item['nome_exibicao']); ?></h3><?php if ($item['horarios_publicos']): ?><p class="small text-muted mb-0"><?php echo e(mb_strimwidth($item['horarios_publicos'], 0, 150, '…')); ?></p><?php endif; ?></div><?php if ($item['distancia_km'] !== null): ?><p class="fw-semibold mb-0"><i class="fa-solid fa-route me-2"></i><?php echo number_format((float) $item['distancia_km'], 1, ',', '.'); ?> km</p><?php endif; ?></div></a><?php endforeach; ?>
            <?php if (!$results): ?><div class="p-4 text-muted">Nenhuma casa pública foi encontrada com estes critérios. Busque por cidade, amplie o raio ou cadastre um centro para análise.</div><?php endif; ?>
            </div></div>
        <?php else: ?>
            <div class="row g-3">
            <?php foreach ($results as $item): ?><div class="col-12 col-md-6 col-xl-4"><article class="card h-100 border-0 shadow-sm mt-directory-card"><div class="card-body p-4"><span class="badge text-bg-light mb-2"><?php echo e($item['cidade_publica'] ?: 'Localidade compartilhada pela casa'); ?><?php echo $item['estado_publico'] ? ', ' . e($item['estado_publico']) : ''; ?></span><h3 class="h4"><a class="stretched-link text-decoration-none" href="terreiro.php?c=<?php echo rawurlencode($item['slug']); ?>"><?php echo e($item['nome_exibicao']); ?></a></h3><?php if ($item['nacao_publica']): ?><p class="mb-2"><i class="fa-solid fa-seedling text-success me-2"></i><?php echo e($item['nacao_publica']); ?></p><?php endif; ?><?php if ($item['dirigente_publico']): ?><p class="small text-muted mb-2">Dirigência informada: <?php echo e($item['dirigente_publico']); ?></p><?php endif; ?><?php if ($item['horarios_publicos']): ?><p class="small mb-2"><i class="fa-regular fa-calendar me-2"></i><?php echo e(mb_strimwidth($item['horarios_publicos'], 0, 110, '…')); ?></p><?php endif; ?><?php if ($item['distancia_km'] !== null): ?><p class="fw-semibold mb-0"><i class="fa-solid fa-route me-2"></i><?php echo number_format((float) $item['distancia_km'], 1, ',', '.'); ?> km de distância aproximada</p><?php endif; ?></div></article></div><?php endforeach; ?>
            </div>
            <?php if (!$results): ?><div class="alert alert-light border mt-3">Nenhuma casa pública foi encontrada com estes critérios. Você pode buscar por cidade, ampliar o raio ou <a href="index.php?p=cadastrar-centro">cadastrar um centro para análise</a>.</div><?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($publicLocations): ?>
        <section class="card border-0 shadow-sm mt-4" aria-label="Explorar casas por cidade"><div class="card-body p-4"><h2 class="h3">Explorar casas por cidade</h2><p class="text-muted">Estas localidades possuem pelo menos uma casa aprovada e pública no diretório.</p><div class="d-flex flex-wrap gap-2"><?php foreach (array_slice($publicLocations, 0, 40) as $location): $locationCity = trim((string) $location['cidade_publica']); $locationState = strtoupper(trim((string) ($location['estado_publico'] ?? ''))); if ($locationCity === '') { continue; } ?><a class="btn btn-outline-primary btn-sm" href="<?php echo e(directory_query_url(['cidade' => $locationCity, 'uf' => $locationState ?: null, 'visualizacao' => 'mapa'])); ?>"><?php echo e($locationCity . ($locationState ? ', ' . $locationState : '')); ?> <span class="visually-hidden">— <?php echo (int) $location['total_casas']; ?> casa(s) pública(s)</span></a><?php endforeach; ?></div></div></section>
    <?php endif; ?>
</main>
<footer class="border-top bg-white py-4"><div class="container small text-muted">Centros aprovados são públicos por padrão e podem ser removidos do diretório pela própria casa. Mapa: © <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap contributors</a>. <a href="sobre.php">Sobre o Meu Terreiro</a>.</div></footer>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<?php meu_terreiro_analytics_consent_banner(); ?>
<script>
const useLocation = document.getElementById('useLocation');
const geoStatus = document.getElementById('geoStatus');
useLocation?.addEventListener('click', () => {
    if (!navigator.geolocation) { geoStatus.textContent = 'Seu navegador não oferece geolocalização. Busque por cidade.'; return; }
    geoStatus.textContent = 'Solicitando sua permissão…';
    navigator.geolocation.getCurrentPosition((position) => {
        document.getElementById('geoLat').value = position.coords.latitude.toFixed(5);
        document.getElementById('geoLng').value = position.coords.longitude.toFixed(5);
        geoStatus.textContent = 'Localização aplicada somente a esta busca.';
        document.getElementById('directorySearch').submit();
    }, () => { geoStatus.textContent = 'Não foi possível usar a localização. Você pode buscar por cidade.'; }, {enableHighAccuracy: false, timeout: 10000, maximumAge: 300000});
});
<?php if ($view === 'mapa' && $markers): ?>
const markers = <?php echo json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
const initialCenter = markers.length ? [markers[0].lat, markers[0].lng] : [-14.235, -51.9253];
const map = L.map('directoryMap', {scrollWheelZoom: false}).setView(initialCenter, markers.length === 1 ? 13 : 5);
const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'}).addTo(map);
const bounds = [];
markers.forEach((marker) => { L.marker([marker.lat, marker.lng]).addTo(map).bindPopup('<a href="' + marker.url + '">' + escapeHtml(marker.name) + '</a>' + (marker.city ? '<br><span class="small">' + escapeHtml(marker.city) + '</span>' : '')); bounds.push([marker.lat, marker.lng]); });
<?php if ($hasCoordinates): ?>
L.circleMarker([<?php echo (float) $lat; ?>, <?php echo (float) $lng; ?>], {radius: 7, color: '#1f5b4b', fillColor: '#1f5b4b', fillOpacity: 0.8}).addTo(map).bindTooltip('Sua busca');
bounds.push([<?php echo (float) $lat; ?>, <?php echo (float) $lng; ?>]);
<?php endif; ?>
if (bounds.length > 1) { map.fitBounds(bounds, {padding: [32, 32], maxZoom: 14}); }
window.requestAnimationFrame(() => map.invalidateSize());
tiles.on('tileerror', () => { const status = document.getElementById('mapStatus'); if (status) { status.textContent = 'Algumas imagens do mapa não foram carregadas. Você ainda pode abrir os perfis pelos marcadores ou escolher Lista.'; } });
<?php endif; ?>
</script>
</body></html>
