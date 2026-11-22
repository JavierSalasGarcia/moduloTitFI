<?php use App\Core\Auth; ?>
<section class="card" style="max-width:720px">
  <form method="post" action="<?= url('tickets') ?>" class="formulario" enctype="multipart/form-data" data-draft="solicitud">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

    <?php if (!empty($carpetaVinculada)): ?>
      <div class="ruta-flujo" style="margin-bottom: 15px; background: #e8f5e9; border: 1px solid #d1fae5; color: var(--verde-900); padding: 12px; border-radius: var(--radio);">
        <div style="display: flex; align-items: center; gap: 6px; font-weight: bold;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
          Carpeta de Teams vinculada:
        </div>
        <span style="font-size: 13px; display: block; margin-top: 4px; font-weight: bold;"><?= e(basename($carpetaVinculada['ruta_relativa'])) ?></span>
        <span style="font-size: 11px; display: block; word-break: break-all; color: var(--gris-600); margin-top: 2px;"><?= e($carpetaVinculada['ruta_relativa']) ?></span>
        <input type="hidden" name="teams_carpeta_id" value="<?= $carpetaVinculada['id'] ?>">
      </div>
    <?php endif; ?>

    <label>Para <span class="req">*</span></label>
    <?php $campo = 'para_id[]'; $campoTipo = 'para_type[]'; $listaVar = 'opcionesTickets'; $url = '';
          $seleccion = $deptoDirecto ? [['tipo' => 'departamento', 'id' => (int) $deptoDirecto['id'], 'nombre' => $deptoDirecto['nombre']]] : [];
          $id = 'sp-para'; $max = 0; $libre = false; $valor = 'id'; $placeholder = 'Escribe el nombre de una persona, departamento o coordinación…'; $etiqueta = 'Para';
          include dirname(__DIR__, 3) . '/Views/selector_personas.php'; ?>
    <p class="ayuda-form">Elige uno o varios destinatarios (personas, coordinaciones o departamentos). El ticket se canalizará a las áreas destino elegidas.</p>

    <label>CC (Con copia)</label>
    <?php $campo = 'cc_id[]'; $campoTipo = 'cc_type[]'; $seleccion = []; $id = 'sp-cc'; $placeholder = 'Escribe personas o departamentos para enviar copia…'; $etiqueta = 'CC';
          include dirname(__DIR__, 3) . '/Views/selector_personas.php'; unset($campoTipo, $listaVar); ?>
    <p class="ayuda-form">Miembros o departamentos que recibirán copia informativa de este ticket.</p>

    <?= \App\Core\RecipientGroups::selector('grupos_para','Añadir grupos a Para') ?>
    <?= \App\Core\RecipientGroups::selector('grupos_cc','Añadir grupos a CC') ?>
    <p class="ayuda-form">Si el grupo no tiene personas adscritas a un área, añade también el departamento que atenderá tu solicitud.</p>
    <label>Asunto <span class="req">*</span>
      <input type="text" name="asunto" required maxlength="180" placeholder="Ej. Constancia de estudios con promedio">
    </label>

    <label>Descripción <span class="req">*</span>
      <textarea name="descripcion" required rows="5" placeholder="Describe tu trámite o problema: número de cuenta, grupo, fechas, equipo afectado…"><?= e($ref ?? '') ?></textarea>
    </label>

    <label>Prioridad
      <select name="prioridad">
        <option value="baja">Baja</option>
        <option value="media" selected>Media</option>
        <option value="alta">Alta</option>
      </select>
    </label>

    <label>Archivos Adjuntos (Uno o varios)
      <input type="file" name="archivos[]" multiple style="margin-top: 6px; font-size: 13px; cursor: pointer; width: 100%;">
      <p class="ayuda-form">Puedes subir múltiples imágenes, PDFs o documentos comprimidos como soporte.</p>
    </label>

    <div class="acciones-formulario">
      <a class="btn" href="<?= url('tickets') ?>">Cancelar</a>
      <button type="submit" class="btn btn-primario">Enviar solicitud</button>
    </div>
  </form>

<?php
$opcionesList = [];
foreach ($departamentos as $d) {
    $opcionesList[] = ['tipo' => 'departamento', 'id' => (int) $d['id'], 'nombre' => $d['nombre'], 'sub' => 'Departamento / Coordinación'];
}
foreach ($usuarios as $u) {
    $opcionesList[] = ['tipo' => 'usuario', 'id' => (int) $u['id'], 'nombre' => $u['nombre'], 'email' => (string) $u['email'], 'sub' => 'Usuario · ' . ucfirst($u['rol']) . ($u['email'] ? ' (' . $u['email'] . ')' : '')];
}
?>
<script>
// Lista local para el selector de destinatarios (SelectorPersonas); se define antes de que el componente se inicie.
window.opcionesTickets = <?= json_encode($opcionesList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.formulario');
  // Al recuperar un borrador, Para y CC se vuelven a llenar con lo guardado.
  form.addEventListener('ux:draft-restored', (event) => {
    [['sp-para', 'para'], ['sp-cc', 'cc']].forEach(([id, campo]) => {
      const sel = document.getElementById(id); SelectorPersonas.limpiar(sel);
      const tipos = event.detail.filter(v => v.name === campo + '_type[]'), ids = event.detail.filter(v => v.name === campo + '_id[]');
      ids.forEach((v, i) => { const o = window.opcionesTickets.find(x => String(x.id) === String(v.value) && x.tipo === tipos[i]?.value); if (o) SelectorPersonas.agregar(sel, o); });
    });
  });
  form.addEventListener('submit', function (e) {
    const paras = document.querySelectorAll('input[name="para_id[]"]');
    if (paras.length === 0 && !document.querySelector('[name="grupos_para[]"]')?.selectedOptions.length) {
      e.preventDefault();
      alert('Debes agregar al menos un destinatario en el campo "Para".');
    }
  });
});
</script>
</section>
