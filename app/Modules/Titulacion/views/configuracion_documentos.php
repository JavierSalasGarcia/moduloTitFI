<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/configuracion') ?>">← Formatos</a></p>
    <p class="texto-secundario">Desmarca <strong>Se pide</strong> para retirar un documento. <strong>Físico (*)</strong> lo marca con asterisco para entregarse en Apoyo a Titulación; <strong>Original para cotejo</strong> exige que el personal lo compare y registre el cotejo con su firma electrónica antes de cerrar la etapa.</p>
  </div>
</div>

<section class="tarjeta" style="margin-bottom:18px">
  <form method="get" action="<?= url('titulacion/configuracion/documentos') ?>" class="form-en-linea" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <label>Ámbito de los cambios
      <select name="modalidad" onchange="this.form.submit()">
        <option value="*" <?= $ambito === '*' ? 'selected' : '' ?>>Todas las opciones de titulación</option>
        <?php foreach ($modalidades as $k => $m): ?><option value="<?= e($k) ?>" <?= $ambito === $k ? 'selected' : '' ?>><?= e($m['nombre']) ?> solamente</option><?php endforeach; ?>
      </select></label>
    <noscript><button class="btn" type="submit">Ver</button></noscript>
  </form>
  <p class="texto-secundario" style="font-size:13px;margin:8px 0 0">Un cambio en una opción concreta prevalece sobre el de «todas las opciones».</p>
</section>

<form method="post" action="<?= url('titulacion/configuracion/documentos') ?>">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <input type="hidden" name="modalidad" value="<?= e($ambito) ?>">
  <?php foreach ($filas as $etapaTitulo => $docs): ?>
  <section class="tarjeta" style="margin-bottom:18px">
    <h2 style="font-size:17px"><?= e($etapaTitulo) ?></h2>
    <div class="tabla-contenedor">
    <table class="tabla">
      <thead><tr><th>Documento</th><th>Se pide</th><th>Físico *</th><th>Original para cotejo</th><th>Firma del cotejo</th></tr></thead>
      <tbody>
      <?php foreach ($docs as $clave => $x): $d = $x['actual']; $n = 'd[' . $clave . ']'; ?>
        <tr id="fila-<?= (int) ($x['extra'] ?? 0) ?>" style="<?= $d['activo'] ? '' : 'opacity:.6' ?>">
          <td style="min-width:300px">
            <input type="hidden" name="<?= e($n) ?>[_]" value="1">
            <input type="text" name="<?= e($n) ?>[nombre]" maxlength="250" value="<?= e($d['nombre']) ?>" aria-label="Nombre del documento" style="width:100%;font-weight:700">
            <input type="text" name="<?= e($n) ?>[nota]" maxlength="500" value="<?= e((string) $d['nota']) ?>" placeholder="Nota o instrucción para el pasante" aria-label="Nota" style="width:100%;margin-top:4px;font-size:13px">
            <span class="texto-secundario" style="font-size:12px">En: <?= e(implode(', ', array_slice(array_unique($x['en']), 0, 3))) ?><?= count(array_unique($x['en'])) > 3 ? ' y ' . (count(array_unique($x['en'])) - 3) . ' más' : '' ?><?= !empty($x['solo_fisico']) ? ' · solo entrega presencial' : '' ?></span>
            <?php if (!empty($x['extra'])): ?>
              <button class="btn btn-mini btn-peligro" type="submit" formaction="<?= url('titulacion/configuracion/documentos/' . (int) $x['extra'] . '/eliminar') ?>" onclick="return confirm('¿Eliminar este documento añadido?')">Eliminar</button>
            <?php endif; ?>
          </td>
          <td><input type="checkbox" name="<?= e($n) ?>[activo]" value="1" <?= $d['activo'] ? 'checked' : '' ?> aria-label="Se pide"></td>
          <td><input type="checkbox" name="<?= e($n) ?>[fisico]" value="1" <?= $d['fisico'] || $d['cotejo'] ? 'checked' : '' ?> aria-label="Entrega en físico"></td>
          <td><input type="checkbox" name="<?= e($n) ?>[cotejo]" value="1" <?= $d['cotejo'] ? 'checked' : '' ?> <?= !empty($x['solo_fisico']) ? 'disabled' : '' ?> aria-label="Original para cotejo"></td>
          <td>
            <select name="<?= e($n) ?>[firma_cotejo]" aria-label="Tipo de firma del cotejo">
              <option value="sello" <?= $d['firma_cotejo'] === 'sello' ? 'selected' : '' ?>>Sello de cotejo</option>
              <option value="pades" <?= $d['firma_cotejo'] === 'pades' ? 'selected' : '' ?> <?= $pki ? '' : 'disabled' ?>>Firma PAdES<?= $pki ? '' : ' (PKI inactiva)' ?></option>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
  <?php endforeach; ?>
  <div class="acciones-formulario" style="position:sticky;bottom:0;background:var(--blanco);padding:10px 0;border-top:1px solid var(--gris-100)">
    <button class="btn btn-primario" type="submit">Guardar requisitos</button>
  </div>
</form>

<section class="tarjeta" style="margin-top:18px">
  <h2>Añadir un documento o entrega</h2>
  <form class="formulario" method="post" action="<?= url('titulacion/configuracion/documentos/nuevo') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="modalidad" value="<?= e($ambito) ?>">
    <p class="texto-secundario" style="margin:0">Se añadirá en <strong><?= $ambito === '*' ? 'todas las opciones' : e($modalidades[$ambito]['nombre']) ?></strong>.</p>
    <div class="tit-campos">
      <label>Nombre <input type="text" name="nombre" maxlength="250" required></label>
      <label>Etapa
        <select name="etapa" required><?php foreach ($etapas as $ec => $et): ?><option value="<?= e($ec) ?>"><?= e($et) ?></option><?php endforeach; ?></select></label>
      <label>Nota para el pasante <input type="text" name="nota" maxlength="500"></label>
      <label>Condición (si aplica solo a algunos) <input type="text" name="condicion" maxlength="300" placeholder="Solo si…"></label>
      <label>Formato que debe firmar
        <select name="formato"><option value="">Ninguno</option><?php foreach ($formatos as $fc => $f): ?><option value="<?= e($fc) ?>"><?= e($f['nombre']) ?></option><?php endforeach; ?></select></label>
      <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="solo_fisico" value="1"> Solo se entrega en físico (no se sube PDF)</label>
    </div>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Añadir</button></div>
  </form>
</section>
