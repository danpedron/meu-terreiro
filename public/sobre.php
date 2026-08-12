<?php

declare(strict_types=1);

$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/seo.php';

$canonicalUrl = meu_terreiro_canonical_url('sobre.php');
$title = 'Meu Terreiro: comunidade, diretório e gestão para casas de axé';
$description = 'Conheça o Meu Terreiro, plataforma comunitária para encontrar casas que optaram por se apresentar publicamente e apoiar sua organização com autonomia e privacidade.';
$jsonLd = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => meu_terreiro_public_base_url() . '/#website',
        'name' => 'Meu Terreiro',
        'url' => meu_terreiro_public_base_url() . '/',
        'inLanguage' => 'pt-BR',
        'description' => $description,
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Meu Terreiro',
            'url' => meu_terreiro_public_base_url() . '/',
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        '@id' => $canonicalUrl . '#webpage',
        'url' => $canonicalUrl,
        'name' => $title,
        'description' => $description,
        'inLanguage' => 'pt-BR',
        'isPartOf' => ['@id' => meu_terreiro_public_base_url() . '/#website'],
    ],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#5a3324">
    <?php meu_terreiro_render_seo_head($title, $description, $canonicalUrl, true, $jsonLd); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <?php meu_terreiro_analytics_head(); ?>
</head>
<body class="mt-public-body">
<nav class="navbar navbar-expand-lg mt-navbar navbar-dark"><div class="container"><a class="navbar-brand mt-brand" href="index.php"><span class="mt-brand-mark" aria-hidden="true"><i class="fa-solid fa-leaf"></i></span>Meu Terreiro</a><div class="ms-auto d-flex gap-2"><a class="btn btn-sm btn-outline-light" href="directory.php">Encontrar uma casa</a><a class="btn btn-sm btn-light" href="index.php?p=login">Entrar</a></div></div></nav>
<main class="container py-4 py-lg-5">
    <section class="mt-directory-hero rounded-4 p-4 p-lg-5 mb-4">
        <span class="mt-eyebrow">Sobre o Meu Terreiro</span>
        <h1 class="display-6 fw-bold">Uma plataforma para fortalecer a organização das casas e facilitar encontros respeitosos na comunidade.</h1>
        <p class="lead mb-0">O Meu Terreiro reúne um diretório público consentido e ferramentas de gestão para apoiar a rotina de terreiros de Umbanda e de outras tradições afro-brasileiras que escolherem utilizar a plataforma.</p>
    </section>

    <section class="row g-4 mb-4" aria-label="Como a plataforma funciona">
        <article class="col-md-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><i class="fa-solid fa-compass fa-xl text-success mb-3" aria-hidden="true"></i><h2 class="h4">Encontrar uma casa</h2><p class="mb-0">Pessoas podem buscar casas por cidade ou, se desejarem, usar a localização do próprio dispositivo somente para ordenar uma busca pontual.</p></div></div></article>
        <article class="col-md-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><i class="fa-solid fa-handshake fa-xl text-success mb-3" aria-hidden="true"></i><h2 class="h4">Solicitar aproximação</h2><p class="mb-0">Cada pessoa pode criar uma conta, conhecer informações públicas de uma casa e solicitar vínculo. A decisão é sempre da própria casa, conforme suas regras.</p></div></div></article>
        <article class="col-md-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><i class="fa-solid fa-shield-halved fa-xl text-success mb-3" aria-hidden="true"></i><h2 class="h4">Organizar com autonomia</h2><p class="mb-0">Cada casa mantém sua operação em ambiente separado, com controles de acesso para agenda, pessoas, obrigações, estoque, finanças e acervo.</p></div></div></article>
    </section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4 p-lg-5">
        <h2 class="h3">O que aparece no diretório público?</h2>
        <p>Somente casas ativas que escolhem aparecer no diretório são exibidas. A própria casa define quais informações compartilhar: apresentação, cidade ou bairro, linha ou nação informada, horários, referência de dirigência, canais públicos de contato, pedidos de consulta e solicitação de vínculo.</p>
        <p class="mb-0">Localização exata, informações administrativas, dados de participantes, fundamentos religiosos e mensagens enviadas por formulários não são conteúdo do diretório e não devem ser usados para divulgação pública. A plataforma prioriza a decisão de cada casa sobre a própria visibilidade.</p>
    </div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4 p-lg-5">
        <h2 class="h3">Como começar</h2>
        <p>Quem procura uma casa pode visitar o <a href="directory.php">diretório comunitário de casas</a> e pesquisar por cidade. Quem deseja participar da plataforma pode <a href="index.php?p=cadastro">criar uma conta</a>, solicitar vínculo a uma casa que esteja recebendo pedidos ou cadastrar uma nova casa. Para manter as relações de respeito e cuidado, a participação depende das regras e da aprovação de cada comunidade.</p>
        <p class="mb-0">O Meu Terreiro é uma ferramenta de apoio comunitário e administrativo. Ele não substitui a orientação direta de dirigentes, os cuidados da comunidade nem decisões tomadas no contexto de cada casa.</p>
    </div></section>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="contribua-heading"><div class="card-body p-4 p-lg-5"><div class="row g-4 align-items-center"><div class="col-lg-8"><span class="mt-eyebrow">Projeto voluntário</span><h2 class="h3" id="contribua-heading">Quer contribuir com o Meu Terreiro?</h2><p class="mb-0">O Meu Terreiro é um projeto voluntário, construído para apoiar casas e comunidades afro-brasileiras. Sugestões de melhorias, ideias e contribuições são bem-vindas. Se quiser conversar sobre a evolução do projeto, entre em contato diretamente com <strong>Dan Pedron</strong>.</p></div><div class="col-lg-4"><div class="d-grid gap-2"><a class="btn btn-success btn-lg" href="https://wa.me/5547936182693" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp me-2" aria-hidden="true"></i>Conversar pelo WhatsApp</a><a class="btn btn-outline-primary btn-lg" href="mailto:danpedron@gmail.com"><i class="fa-solid fa-envelope me-2" aria-hidden="true"></i>Enviar e-mail</a><p class="small text-muted mb-0 text-center">+55 47 93618-2693<br><span>danpedron@gmail.com</span></p></div></div></div></div></section>

    <section class="d-flex flex-column flex-sm-row gap-2"><a class="btn btn-primary btn-lg" href="directory.php"><i class="fa-solid fa-map-location-dot me-2"></i>Explorar o diretório</a><a class="btn btn-outline-primary btn-lg" href="index.php?p=cadastro"><i class="fa-solid fa-user-plus me-2"></i>Criar minha conta</a></section>
</main>
<footer class="border-top bg-white py-4"><div class="container small text-muted">Meu Terreiro. Informações públicas são definidas e revisadas por cada casa.</div></footer>
<?php meu_terreiro_analytics_consent_banner(); ?>
</body>
</html>
