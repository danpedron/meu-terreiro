<?php

declare(strict_types=1);

/**
 * Medição pública do Meu Terreiro.
 *
 * O ID de medição é público por natureza. A coleta só é iniciada depois que
 * a pessoa visitante autoriza cookies de medição no aviso apresentado na tela.
 */
const MEU_TERREIRO_GA4_MEASUREMENT_ID = 'G-R7DBMCYP8B';

function meu_terreiro_analytics_consent(): string
{
    $value = (string) ($_COOKIE['mt_analytics_consent'] ?? '');
    return in_array($value, ['accepted', 'rejected'], true) ? $value : '';
}

function meu_terreiro_analytics_head(): void
{
    if (MEU_TERREIRO_GA4_MEASUREMENT_ID === '' || meu_terreiro_analytics_consent() !== 'accepted') {
        return;
    }

    $measurementId = htmlspecialchars(MEU_TERREIRO_GA4_MEASUREMENT_ID, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!-- Google Analytics 4: ativado somente após consentimento de medição -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$measurementId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$measurementId}', {send_page_view: true});
</script>
HTML;
}

function meu_terreiro_analytics_consent_banner(): void
{
    if (meu_terreiro_analytics_consent() !== '') {
        return;
    }
    ?>
    <aside class="mt-analytics-consent" id="mt-analytics-consent" aria-label="Consentimento para medição de audiência">
        <div class="mt-analytics-consent__text">
            <strong>Ajude a melhorar o Meu Terreiro</strong>
            <span>Podemos medir visitas e uso da página para melhorar a comunidade. Não enviamos dados de contas, mensagens ou formulários ao Google Analytics.</span>
        </div>
        <div class="mt-analytics-consent__actions">
            <button class="btn btn-primary btn-sm" type="button" onclick="window.meuTerreiroAnalyticsConsent('accepted')">Aceitar medição</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.meuTerreiroAnalyticsConsent('rejected')">Continuar sem medição</button>
        </div>
    </aside>
    <script>
    window.meuTerreiroAnalyticsConsent = function (value) {
        if (value !== 'accepted' && value !== 'rejected') return;
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = 'mt_analytics_consent=' + value + '; Max-Age=31536000; Path=/; SameSite=Lax' + secure;
        var banner = document.getElementById('mt-analytics-consent');
        if (banner) banner.remove();
        if (value === 'accepted') window.location.reload();
    };
    </script>
    <?php
}
