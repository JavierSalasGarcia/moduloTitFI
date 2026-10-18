<?php
$nombresMes = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = (date('Y-m') === $mes) ? (int) date('j') : 0;
?>
<div class="barra-acciones" style="flex-wrap: wrap; gap: 15px;">
  <div class="grupo-chips">
    <a class="btn btn-mini" href="<?= url('calendario?m=' . $mesAnterior) ?>">← Anterior</a>
    <strong style="font-size:16px;color:var(--verde-900);text-transform:capitalize">
      <?= e($nombresMes[$numMes - 1]) ?> <?= $anio ?>
    </strong>
    <a class="btn btn-mini" href="<?= url('calendario?m=' . $mesSiguiente) ?>">Siguiente →</a>
      <a class="enlace" href="<?= url('calendario') ?>">Hoy</a>
  </div>
  <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
    <?php if (!empty($outlookIcsUrl)): ?>
      <button type="button" class="btn" id="btn-conectar-teams" onclick="sincronizarOutlookAhora()" style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid #16a34a; color: #15803d; font-weight: 500; cursor: pointer; background: #f0fdf4; transition: all 0.2s;">
        ✓ Teams / Outlook conectado (Sincronizar)
      </button>
      <button type="button" class="btn" onclick="desvincularOutlook()" style="border-color: #ef4444; color: #b91c1c; background: #fef2f2; font-weight: 500;">
        Desvincular
      </button>
    <?php else: ?>
      <button type="button" class="btn" id="btn-conectar-teams" onclick="abrirModalConectarOutlook()" style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid #0f6cbd; color: #0f6cbd; font-weight: 500; cursor: pointer; transition: all 0.2s;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
        Conectar cuenta de Teams / Outlook
      </button>
    <?php endif; ?>
    <a class="btn btn-primario" href="<?= url('eventos?tab=eventos') ?>">Explorar eventos</a>
  </div>
</div>

<div class="card sin-relleno" data-calendar-month>
  <div class="cal-encabezados">
    <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?><span><?= $d ?></span><?php endforeach; ?>
  </div>
  <div class="cal-grid">
    <?php for ($i = 0; $i < $offset; $i++): ?><div class="cal-celda vacia"></div><?php endfor; ?>
    <?php for ($dia = 1; $dia <= $diasEnMes; $dia++): ?>
      <div class="cal-celda<?= $dia === $hoy ? ' hoy' : '' ?>">
        <span class="cal-dia"><?= $dia ?></span>
        <?php foreach ($porDia[$dia] ?? [] as $ev): ?>
          <?php if (($ev['tipo'] ?? '') === 'outlook'): ?>
            <a class="cal-evento" href="#" style="background:#e0e7ff; color:#3730a3; border-color:#c7d2fe;" onclick="fiShowDetails('Evento de Outlook', <?= e(json_encode("Microsoft Teams / Outlook - Reunión Importada\n\nAsunto: " . str_replace('👥 [Teams] ', '', $ev['titulo']) . "\nHora: " . date('H:i', strtotime($ev['inicio'])) . "\nLugar: " . $ev['lugar'] . "\nDetalles: " . ($ev['descripcion'] ?? 'Sin descripción adicional'))) ?>); return false;">
              <?= e(mb_strimwidth($ev['titulo'], 0, 26, '…')) ?>
            </a>
          <?php else: ?>
            <a class="cal-evento" href="<?= url('eventos/' . $ev['id']) ?>" title="<?= e($ev['titulo']) ?> · <?= e(fecha_corta($ev['inicio'])) ?>">
              <?= e(mb_strimwidth($ev['titulo'], 0, 26, '…')) ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<section class="card" data-calendar-agenda>
  <header class="tarjeta-cabecera"><h2>Agenda del mes</h2></header>
  <?php if (!$eventos): ?>
    <p class="texto-suave">No tienes eventos este mes. Explora los <a href="<?= url('eventos') ?>">avisos vigentes</a>
    y agrégalos a tu calendario con un clic.</p>
  <?php else: ?>
    <ul class="lista-canales">
      <?php foreach ($eventos as $ev): ?>
        <li>
          <?php if (($ev['tipo'] ?? '') === 'outlook'): ?>
            <a href="#" onclick="fiShowDetails('Evento de Outlook', <?= e(json_encode("Microsoft Teams / Outlook - Reunión Importada\n\nAsunto: " . str_replace('👥 [Teams] ', '', $ev['titulo']) . "\nHora: " . date('H:i', strtotime($ev['inicio'])) . "\nLugar: " . $ev['lugar'] . "\nDetalles: " . ($ev['descripcion'] ?? 'Sin descripción adicional'))) ?>); return false;"><strong><?= e($ev['titulo']) ?></strong></a>
          <?php else: ?>
            <a href="<?= url('eventos/' . $ev['id']) ?>"><strong><?= e($ev['titulo']) ?></strong></a>
          <?php endif; ?>
          <small class="texto-suave">
            <?= e(fecha_corta($ev['inicio'])) ?><?= $ev['lugar'] ? ' · ' . e($ev['lugar']) : '' ?> · <?= e($ev['depto']) ?>
          </small>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?><!-- Modal para Conectar Outlook iCal -->
<div id="modal-conectar-outlook" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
  <div style="background: #fff; max-width: 650px; width: 100%; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); display: flex; flex-direction: column; max-height: 90vh;">
    <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
      <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; text-align: left;">Vincular Calendario de Microsoft Outlook / Teams</h3>
      <button type="button" onclick="document.getElementById('modal-conectar-outlook').style.display = 'none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8;">&times;</button>
    </div>
    <div style="padding: 20px; overflow-y: auto; font-size: 13px; color: #475569; line-height: 1.6; text-align: left;">
      <p style="margin-top: 0; font-weight: 600; color: #0f172a;">Sigue estos pasos en tu correo institucional para obtener tu enlace iCal:</p>
      <ol style="padding-left: 20px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
        <li>Entra a tu correo de la UAEMEX desde la web: <a href="https://outlook.office.com" target="_blank" style="color: #0f6cbd; text-decoration: underline; font-weight: 500;">Outlook Web ↗</a></li>
        <li>Ve a <strong>Configuración</strong> (icono de engranaje en la esquina superior derecha).</li>
        <li>Navega a <strong>Calendario</strong> ➔ <strong>Calendarios compartidos</strong>.</li>
        <li>En la sección <strong>Publicar un calendario</strong>, selecciona tu calendario de trabajo, elige el permiso <em>"Puede ver todos los detalles"</em> y haz clic en <strong>Publicar</strong>.</li>
        <li>Copia la dirección del enlace que termina en <strong>.ics</strong> (el enlace HTML no servirá, copia el enlace **ICS**).</li>
      </ol>
      
      <div style="margin-top: 15px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px;">
        <label style="font-weight: 600; display: block; margin-bottom: 6px; color: #0f172a;">Pega tu enlace iCal (URL .ics) de Outlook:</label>
        <input type="url" id="input-outlook-ics" placeholder="https://outlook.office356.com/owa/calendar/..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12.5px;" value="<?= e($outlookIcsUrl) ?>">
      </div>
    </div>
    <div style="padding: 15px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px;">
      <button type="button" class="btn" onclick="document.getElementById('modal-conectar-outlook').style.display = 'none'" style="border-color: #cbd5e1;">Cancelar</button>
      <button type="button" class="btn btn-primario" onclick="guardarEnlaceOutlook()" id="btn-guardar-outlook">Vincular y Sincronizar</button>
    </div>
  </div>
</div>

<script>
function abrirModalConectarOutlook() {
  document.getElementById('modal-conectar-outlook').style.display = 'flex';
  document.getElementById('input-outlook-ics').focus();
}

async function guardarEnlaceOutlook() {
  const url = document.getElementById('input-outlook-ics').value.trim();
  const btn = document.getElementById('btn-guardar-outlook');
  const originalText = btn.textContent;

  btn.disabled = true;
  btn.textContent = 'Vinculando...';

  try {
    const form = new FormData();
    form.append('_csrf', '<?= e(\App\Core\Auth::csrfToken()) ?>');
    form.append('outlook_ics_url', url);

    const res = await fetch('<?= url("calendario/vincular-ics") ?>', {
      method: 'POST',
      body: form
    });
    const result = await res.json();

    if (result.exito) {
      alert(result.mensaje);
      window.location.reload();
    } else {
      alert('Error: ' + result.error);
    }
  } catch (err) {
    alert('Error al conectar: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

async function sincronizarOutlookAhora() {
  const btn = document.getElementById('btn-conectar-teams');
  const originalText = btn.innerHTML;

  btn.disabled = true;
  btn.innerHTML = `<span style="display:inline-block; width:12px; height:12px; border:2px solid currentColor; border-top-color:transparent; border-radius:50%; animation:spin_loader 0.8s linear infinite; margin-right:6px;"></span> Sincronizando...`;

  try {
    const form = new FormData();
    form.append('_csrf', '<?= e(\App\Core\Auth::csrfToken()) ?>');

    const res = await fetch('<?= url("calendario/sincronizar") ?>', {
      method: 'POST',
      body: form
    });
    const result = await res.json();

    if (result.exito) {
      alert(result.mensaje);
      window.location.reload();
    } else {
      alert('Error de sincronización: ' + result.error);
    }
  } catch (err) {
    alert('Error al sincronizar: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalText;
  }
}

async function desvincularOutlook() {
  if (!confirm('¿Seguro que deseas desvincular tu calendario de Outlook? Esto eliminará todos los eventos importados de la plataforma.')) {
    return;
  }

  try {
    const form = new FormData();
    form.append('_csrf', '<?= e(\App\Core\Auth::csrfToken()) ?>');
    form.append('outlook_ics_url', ''); // Mandar vacío para desvincular

    const res = await fetch('<?= url("calendario/vincular-ics") ?>', {
      method: 'POST',
      body: form
    });
    const result = await res.json();

    if (result.exito) {
      alert(result.mensaje);
      window.location.reload();
    } else {
      alert('Error: ' + result.error);
    }
  } catch (err) {
    alert('Error al desvincular: ' + err.message);
  }
}
</script>

<style>
@keyframes spin_loader {
  to { transform: rotate(360deg); }
}
</style>
</section>
