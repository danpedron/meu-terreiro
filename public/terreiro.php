<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/CommunityService.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/seo.php';
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
$service = new CommunityService();
$tenant = $service->getTenantForPublicDirectory(trim((string) ($_GET['c'] ?? '')));
if (!$tenant) { http_response_code(404); exit('Esta casa não está disponível no diretório público.'); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
$publicError = $_SESSION['public_error'] ?? null; unset($_SESSION['public_error']);
$publicSuccess = $_SESSION['public_success'] ?? null; unset($_SESSION['public_success']);
$whatsappLink = $tenant['whatsapp_publico'] ? 'https://wa.me/' . preg_replace('/\D+/', '', $tenant['whatsapp_publico']) : null;
$mapEnabled = !empty($tenant['mostrar_no_mapa']) && $tenant['latitude_publica'] !== null && $tenant['longitude_publica'] !== null;
$canonicalUrl = meu_terreiro_canonical_url('terreiro.php', ['c' => $tenant['slug']]);
$locationLabel = trim((string) ($tenant['cidade_publica'] ?? ''));
if (!empty($tenant['estado_publico'])) { $locationLabel .= ($locationLabel ? ', ' : '') . $tenant['estado_publico']; }
$seoTitle = meu_terreiro_compact_text($tenant['nome_exibicao'] . ($locationLabel ? ' em ' . $locationLabel : '') . ' — Meu Terreiro', 60);
$seoDescription = meu_terreiro_compact_text(
    $tenant['descricao_publica'] ?: ('Conheça ' . $tenant['nome_exibicao'] . ($locationLabel ? ', casa pública no diretório comunitário em ' . $locationLabel : '') . '.'),
    160
);
$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type' => ['Organization', 'Place'],
    '@id' => $canonicalUrl . '#organization',
    'name' => $tenant['nome_exibicao'],
    'url' => $canonicalUrl,
    'description' => $seoDescription,
    'inLanguage' => 'pt-BR',
];
if ($locationLabel !== '') {
    $organizationSchema['address'] = array_filter([
        '@type' => 'PostalAddress',
        'addressLocality' => $tenant['cidade_publica'] ?: null,
        'addressRegion' => $tenant['estado_publico'] ?: null,
        'addressCountry' => 'BR',
    ]);
}
if ($mapEnabled) {
    $organizationSchema['geo'] = [
        '@type' => 'GeoCoordinates',
        'latitude' => (float) $tenant['latitude_publica'],
        'longitude' => (float) $tenant['longitude_publica'],
    ];
}
if (!empty($tenant['whatsapp_publico'])) { $organizationSchema['telephone'] = $tenant['whatsapp_publico']; }
if (!empty($tenant['email_publico'])) { $organizationSchema['email'] = $tenant['email_publico']; }
$publicProperties = [];
foreach ([
    'Nação ou tradição informada' => $tenant['nacao_publica'],
    'Dirigência informada' => $tenant['dirigente_publico'],
    'Horários e giras' => $tenant['horarios_publicos'],
    'Informação compartilhada' => $tenant['linha_presenca_publica'],
] as $label => $value) {
    if (!empty($value)) { $publicProperties[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => meu_terreiro_compact_text((string) $value, 500)]; }
}
if ($publicProperties) { $organizationSchema['additionalProperty'] = $publicProperties; }
$jsonLd = [
    $organizationSchema,
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonicalUrl . '#webpage',
        'url' => $canonicalUrl,
        'name' => $seoTitle,
        'description' => $seoDescription,
        'inLanguage' => 'pt-BR',
        'about' => ['@id' => $canonicalUrl . '#organization'],
        'isPartOf' => ['@id' => meu_terreiro_public_base_url() . '/#website'],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Diretório comunitário', 'item' => meu_terreiro_canonical_url('directory.php')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $tenant['nome_exibicao'], 'item' => $canonicalUrl],
        ],
    ],
];
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#5a3324">
<?php meu_terreiro_render_seo_head($seoTitle, $seoDescription, $canonicalUrl, true, $jsonLd); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"><link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" integrity="sha256-p4NxAoJBhIINfQ6Lr5UVR8a6G6N6au6M6F1eQSCV5p0A=" crossorigin=""><link href="assets/css/app.css" rel="stylesheet">
<?php meu_terreiro_analytics_head(); ?>
</head><body class="mt-public-body">
<nav class="navbar navbar-expand-lg mt-navbar navbar-dark"><div class="container"><a class="navbar-brand mt-brand" href="directory.php"><span class="mt-brand-mark"><i class="fa-solid fa-leaf"></i></span>Meu Terreiro</a><div class="ms-auto d-flex gap-2"><a class="btn btn-sm btn-outline-light" href="sobre.php">Sobre</a><a class="btn btn-sm btn-outline-light" href="directory.php">Diretório</a><a class="btn btn-sm btn-light" href="index.php?p=comunidade">Minha comunidade</a></div></div></nav>
<main class="container py-4 py-lg-5">
<?php if ($publicError): ?><div class="alert alert-danger" role="alert"><?php echo e($publicError); ?></div><?php endif; ?>
<?php if ($publicSuccess): ?><div class="alert alert-success" role="status"><?php echo e($publicSuccess); ?></div><?php endif; ?>
<section class="mt-profile-hero rounded-4 p-4 p-lg-5 mb-4"><span class="mt-eyebrow">Casa no diretório comunitário</span><h1 class="display-5 fw-bold mb-2"><?php echo e($tenant['nome_exibicao']); ?></h1><?php if ($tenant['nacao_publica']): ?><p class="lead mb-2"><i class="fa-solid fa-seedling me-2"></i><?php echo e($tenant['nacao_publica']); ?></p><?php endif; ?><p class="mb-0"><?php echo e($tenant['bairro_publico'] ?: ($tenant['cidade_publica'] ?: 'A localização detalhada é compartilhada diretamente pela casa.')); ?><?php echo $tenant['estado_publico'] ? ', ' . e($tenant['estado_publico']) : ''; ?></p></section>
<div class="row g-4"><section class="col-lg-8"><div class="card border-0 shadow-sm mb-4"><div class="card-body p-4 p-lg-5"><h2 class="h3">Sobre a casa</h2><p class="mb-0"><?php echo nl2br(e($tenant['descricao_publica'] ?: 'Esta casa ainda não adicionou uma apresentação pública.')); ?></p></div></div>
<div class="row g-4"><div class="col-md-6"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4"><i class="fa-regular fa-calendar me-2"></i>Horários e giras</h2><p class="mb-0"><?php echo nl2br(e($tenant['horarios_publicos'] ?: 'Consulte a casa para confirmar os horários.')); ?></p></div></div></div><div class="col-md-6"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4"><i class="fa-solid fa-hands-holding-circle me-2"></i>Dirigência e presença</h2><?php if ($tenant['dirigente_publico']): ?><p class="mb-2"><strong>Dirigência:</strong> <?php echo e($tenant['dirigente_publico']); ?></p><?php endif; ?><p class="mb-0"><strong>Informação compartilhada:</strong> <?php echo e($tenant['linha_presenca_publica'] ?: 'A casa não informou presença pública específica.'); ?></p></div></div></div></div>
<?php if ($mapEnabled): ?><div class="card border-0 shadow-sm mt-4"><div class="card-body p-0"><div id="tenantMap" class="mt-directory-map rounded-4" aria-label="Localização aproximada da casa"></div></div><div class="card-footer bg-white small text-muted">A precisão da localização é definida pela própria casa. © <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap contributors</a>.</div></div><?php endif; ?>
</section>
<aside class="col-lg-4"><div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h4">Contato</h2><p class="small text-muted">Utilize somente os canais que a casa escolheu tornar públicos.</p><?php if ($whatsappLink): ?><a class="btn btn-success w-100 mb-2" href="<?php echo e($whatsappLink); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp me-2"></i>Falar pelo WhatsApp</a><?php endif; ?><?php if ($tenant['email_publico']): ?><a class="btn btn-outline-primary w-100" href="mailto:<?php echo rawurlencode($tenant['email_publico']); ?>"><i class="fa-regular fa-envelope me-2"></i>Enviar e-mail</a><?php endif; ?><?php if (!$whatsappLink && !$tenant['email_publico']): ?><p class="mb-0 text-muted">A casa não disponibilizou um canal público de contato.</p><?php endif; ?></div></div>
<?php if ($tenant['aceita_solicitacoes_vinculo']): ?><div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h4">Quero fazer parte</h2><p class="small">Envie uma solicitação. A casa poderá aceitar, recusar ou pedir mais informações. Pedidos de reconhecimento como dirigente em casa sem dirigente verificado são analisados pela administração global.</p><form method="post" action="membership_request.php"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="tenant_slug" value="<?php echo e($tenant['slug']); ?>"><label class="form-label" for="papel">Como deseja participar?</label><select class="form-select mb-3" id="papel" name="papel" required><option value="Consulente">Consulente</option><option value="Assistencia">Assistência</option><option value="FilhoDeSanto">Filho de santo</option><option value="Babalorixa">Babalorixá</option><option value="Yalorixa">Yalorixá</option></select><label class="form-label" for="mensagemVinculo">Mensagem opcional</label><textarea class="form-control mb-3" id="mensagemVinculo" name="mensagem" rows="3" maxlength="1200" placeholder="Não inclua informações íntimas, de saúde ou fundamentos religiosos."></textarea><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="aceite_vinculo" value="1" id="aceiteVinculo" required><label class="form-check-label small" for="aceiteVinculo">Autorizo o envio dos meus dados básicos de conta para análise deste vínculo.</label></div><button class="btn btn-primary w-100" type="submit">Solicitar vínculo</button></form></div></div><?php endif; ?>
<?php if ($tenant['aceita_consultas']): ?><div class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4">Solicitar consulta</h2><p class="small">A casa definirá se e como responderá. Não descreva situação íntima neste formulário.</p><form method="post" action="consultation_request.php"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="tenant_slug" value="<?php echo e($tenant['slug']); ?>"><label class="form-label" for="nomeContato">Seu nome</label><input class="form-control mb-2" id="nomeContato" name="nome_contato" required maxlength="255"><label class="form-label" for="whatsappContato">WhatsApp</label><input class="form-control mb-2" id="whatsappContato" name="whatsapp_contato" maxlength="20"><label class="form-label" for="emailContato">E-mail</label><input class="form-control mb-2" id="emailContato" name="email_contato" type="email" maxlength="255"><label class="form-label" for="disponibilidade">Disponibilidade</label><input class="form-control mb-2" id="disponibilidade" name="disponibilidade" maxlength="255" placeholder="Ex.: terças à noite"><label class="form-label" for="mensagemConsulta">Mensagem breve (opcional)</label><textarea class="form-control mb-3" id="mensagemConsulta" name="mensagem" rows="3" maxlength="1500"></textarea><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="aceite_contato" value="1" id="aceiteContato" required><label class="form-check-label small" for="aceiteContato">Autorizo o uso destes dados exclusivamente para resposta a este pedido.</label></div><button class="btn btn-outline-primary w-100" type="submit">Enviar solicitação</button></form></div></div><?php endif; ?>
</aside></div></main>
<?php meu_terreiro_analytics_consent_banner(); ?>
<?php if ($mapEnabled): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script><script>const map=L.map('tenantMap').setView([<?php echo (float) $tenant['latitude_publica']; ?>,<?php echo (float) $tenant['longitude_publica']; ?>],14);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'}).addTo(map);L.marker([<?php echo (float) $tenant['latitude_publica']; ?>,<?php echo (float) $tenant['longitude_publica']; ?>]).addTo(map).bindPopup(<?php echo json_encode($tenant['nome_exibicao']); ?>);</script><?php endif; ?>
</body></html>
