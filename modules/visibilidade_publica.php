<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/CommunityService.php';
$community = new CommunityService();
$tenantId = (int) $_SESSION['tenant_id'];
if (!$community->canManageTenant((int) $_SESSION['user_id'], $tenantId)) {
    http_response_code(403);
    echo '<div class="alert alert-warning">Somente a dirigência ou a administração global pode alterar a visibilidade pública da casa.</div>';
    return;
}
$db = $community->getConnection();
$stmt = $db->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch();
if (!$tenant) { echo '<div class="alert alert-danger">A casa não foi localizada.</div>'; return; }
$error = null;
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sua sessão expirou. Tente novamente.';
    } else {
        $locationLevel = (string) ($_POST['localizacao_publica'] ?? 'Nenhuma');
        $allowedLevels = ['Nenhuma','Bairro','Aproximada','Endereco'];
        if (!in_array($locationLevel, $allowedLevels, true)) { $locationLevel = 'Nenhuma'; }
        $latRaw = str_replace(',', '.', trim((string) ($_POST['latitude_publica'] ?? '')));
        $lngRaw = str_replace(',', '.', trim((string) ($_POST['longitude_publica'] ?? '')));
        $latitude = $latRaw !== '' && is_numeric($latRaw) ? (float) $latRaw : null;
        $longitude = $lngRaw !== '' && is_numeric($lngRaw) ? (float) $lngRaw : null;
        if (($latitude !== null && ($latitude < -90 || $latitude > 90)) || ($longitude !== null && ($longitude < -180 || $longitude > 180))) {
            $error = 'Informe coordenadas válidas ou deixe os campos vazios.';
        } elseif (in_array($locationLevel, ['Aproximada','Endereco'], true) && ($latitude === null || $longitude === null)) {
            $error = 'Para exibir no mapa, informe latitude e longitude ou use o botão de localização.';
        } else {
            $listed = !empty($_POST['listar_publicamente']) ? 1 : 0;
            $showMap = $listed && in_array($locationLevel, ['Aproximada','Endereco'], true) ? 1 : 0;
            $clean = static fn(string $key, int $length = 255): ?string => ($value = trim(mb_substr((string) ($_POST[$key] ?? ''), 0, $length))) !== '' ? $value : null;
            $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp_publico'] ?? '')) ?? '';
            if ($whatsapp !== '' && (strlen($whatsapp) < 10 || strlen($whatsapp) > 15)) { $error = 'Informe um WhatsApp público válido ou deixe o campo vazio.'; }
            $email = mb_strtolower(trim((string) ($_POST['email_publico'] ?? '')));
            if (!$error && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Informe um e-mail público válido ou deixe o campo vazio.'; }
            if (!$error) {
                $update = $db->prepare(
                    "UPDATE tenants SET listar_publicamente = ?, mostrar_no_mapa = ?, localizacao_publica = ?, endereco_publico = ?, bairro_publico = ?, cidade_publica = ?, estado_publico = ?, latitude_publica = ?, longitude_publica = ?, descricao_publica = ?, nacao_publica = ?, dirigente_publico = ?, linha_presenca_publica = ?, horarios_publicos = ?, whatsapp_publico = ?, email_publico = ?, aceita_consultas = ?, aceita_solicitacoes_vinculo = ? WHERE id = ?"
                );
                $state = strtoupper((string) ($clean('estado_publico', 2) ?? ''));
                if ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) { $error = 'Use a sigla de duas letras para o estado.'; }
                if (!$error) {
                    $update->execute([$listed, $showMap, $locationLevel, $clean('endereco_publico'), $clean('bairro_publico', 120), $clean('cidade_publica', 120), $state ?: null, $latitude, $longitude, $clean('descricao_publica', 3000), $clean('nacao_publica', 120), $clean('dirigente_publico'), $clean('linha_presenca_publica'), $clean('horarios_publicos', 3000), $whatsapp ?: null, $email ?: null, !empty($_POST['aceita_consultas']) ? 1 : 0, !empty($_POST['aceita_solicitacoes_vinculo']) ? 1 : 0, $tenantId]);
                    $community->log((int) $_SESSION['user_id'], $tenantId, 'perfil_publico_atualizado', 'tenants', $tenantId, 'Configuração de diretório atualizada.');
                    $success = 'As informações públicas foram atualizadas.';
                    $stmt->execute([$tenantId]);
                    $tenant = $stmt->fetch();
                }
            }
        }
    }
}
?>
<section class="mb-4"><span class="mt-eyebrow">Diretório comunitário</span><h1 class="mt-page-title">Visibilidade pública da casa</h1><p class="mt-subtitle">A casa decide o que deseja compartilhar. Localização, contatos, horários e presença pública são opcionais e podem ser removidos a qualquer momento.</p></section>
<?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
<form method="post" class="vstack gap-4"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4">Participação no diretório</h2><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="listar_publicamente" name="listar_publicamente" value="1" <?php echo !empty($tenant['listar_publicamente']) ? 'checked' : ''; ?>><label class="form-check-label" for="listar_publicamente">Exibir a casa no diretório público</label></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="aceita_solicitacoes_vinculo" name="aceita_solicitacoes_vinculo" value="1" <?php echo !empty($tenant['aceita_solicitacoes_vinculo']) ? 'checked' : ''; ?>><label class="form-check-label" for="aceita_solicitacoes_vinculo">Receber solicitações de vínculo</label></div><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" id="aceita_consultas" name="aceita_consultas" value="1" <?php echo !empty($tenant['aceita_consultas']) ? 'checked' : ''; ?>><label class="form-check-label" for="aceita_consultas">Receber pedidos de consulta pelo sistema</label></div></div></section>
<section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4">Apresentação pública</h2><div class="row g-3"><div class="col-12"><label class="form-label" for="descricao_publica">Sobre a casa</label><textarea class="form-control" id="descricao_publica" name="descricao_publica" rows="4" maxlength="3000" placeholder="Apresentação escolhida pela própria casa."><?php echo e($tenant['descricao_publica']); ?></textarea></div><div class="col-md-6"><label class="form-label" for="nacao_publica">Nação ou tradição</label><input class="form-control" id="nacao_publica" name="nacao_publica" value="<?php echo e($tenant['nacao_publica']); ?>" maxlength="120"></div><div class="col-md-6"><label class="form-label" for="dirigente_publico">Dirigência a ser exibida</label><input class="form-control" id="dirigente_publico" name="dirigente_publico" value="<?php echo e($tenant['dirigente_publico']); ?>" maxlength="255"></div><div class="col-12"><label class="form-label" for="linha_presenca_publica">Informação pública sobre presença ou linha</label><input class="form-control" id="linha_presenca_publica" name="linha_presenca_publica" value="<?php echo e($tenant['linha_presenca_publica']); ?>" maxlength="255" placeholder="Somente se a casa desejar compartilhar"></div><div class="col-12"><label class="form-label" for="horarios_publicos">Horários de gira e orientações de acolhimento</label><textarea class="form-control" id="horarios_publicos" name="horarios_publicos" rows="3" maxlength="3000"><?php echo e($tenant['horarios_publicos']); ?></textarea></div></div></div></section>
<section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h4">Localização e contato</h2><p class="small text-muted">Não informe local de oferenda, fundamentos, lugares de prática ou qualquer detalhe que a casa não queira tornar público.</p><div class="row g-3"><div class="col-md-4"><label class="form-label" for="localizacao_publica">Precisão da localização</label><select class="form-select" id="localizacao_publica" name="localizacao_publica"><option value="Nenhuma" <?php echo $tenant['localizacao_publica'] === 'Nenhuma' ? 'selected' : ''; ?>>Não exibir localização</option><option value="Bairro" <?php echo $tenant['localizacao_publica'] === 'Bairro' ? 'selected' : ''; ?>>Somente bairro/cidade</option><option value="Aproximada" <?php echo $tenant['localizacao_publica'] === 'Aproximada' ? 'selected' : ''; ?>>Ponto aproximado no mapa</option><option value="Endereco" <?php echo $tenant['localizacao_publica'] === 'Endereco' ? 'selected' : ''; ?>>Endereço e mapa</option></select></div><div class="col-md-4"><label class="form-label" for="bairro_publico">Bairro</label><input class="form-control" id="bairro_publico" name="bairro_publico" value="<?php echo e($tenant['bairro_publico']); ?>" maxlength="120"></div><div class="col-md-3"><label class="form-label" for="cidade_publica">Cidade</label><input class="form-control" id="cidade_publica" name="cidade_publica" value="<?php echo e($tenant['cidade_publica']); ?>" maxlength="120"></div><div class="col-md-1"><label class="form-label" for="estado_publico">UF</label><input class="form-control" id="estado_publico" name="estado_publico" value="<?php echo e($tenant['estado_publico']); ?>" maxlength="2"></div><div class="col-12"><label class="form-label" for="endereco_publico">Endereço público (somente se escolher exibir endereço)</label><input class="form-control" id="endereco_publico" name="endereco_publico" value="<?php echo e($tenant['endereco_publico']); ?>" maxlength="255"></div><div class="col-md-5"><label class="form-label" for="latitude_publica">Latitude</label><input class="form-control" id="latitude_publica" name="latitude_publica" value="<?php echo e($tenant['latitude_publica'] !== null ? (string) $tenant['latitude_publica'] : ''); ?>" inputmode="decimal"></div><div class="col-md-5"><label class="form-label" for="longitude_publica">Longitude</label><input class="form-control" id="longitude_publica" name="longitude_publica" value="<?php echo e($tenant['longitude_publica'] !== null ? (string) $tenant['longitude_publica'] : ''); ?>" inputmode="decimal"></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100" id="captureLocation" type="button">Usar local</button></div><div class="col-md-6"><label class="form-label" for="whatsapp_publico">WhatsApp público</label><input class="form-control" id="whatsapp_publico" name="whatsapp_publico" value="<?php echo e($tenant['whatsapp_publico']); ?>" maxlength="20"></div><div class="col-md-6"><label class="form-label" for="email_publico">E-mail público</label><input class="form-control" id="email_publico" type="email" name="email_publico" value="<?php echo e($tenant['email_publico']); ?>" maxlength="255"></div></div></div></section><div><button class="btn btn-primary btn-lg" type="submit">Salvar visibilidade pública</button></div></form>
<script>document.getElementById('captureLocation')?.addEventListener('click',()=>{if(!navigator.geolocation){alert('Seu navegador não oferece geolocalização.');return;}navigator.geolocation.getCurrentPosition(p=>{document.getElementById('latitude_publica').value=p.coords.latitude.toFixed(6);document.getElementById('longitude_publica').value=p.coords.longitude.toFixed(6);},()=>alert('Não foi possível obter a localização. Você pode informar as coordenadas manualmente.'),{enableHighAccuracy:false,timeout:10000,maximumAge:300000});});</script>
