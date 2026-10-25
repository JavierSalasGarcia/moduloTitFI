<?php use App\Core\Auth; ?>
<p><a class="enlace" href="<?= url('firmas') ?>"><?= icono('flecha-izq', 14) ?> Volver a firmas</a></p>

<div class="rejilla-detalle">
  <section class="card">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($s['titulo']) ?></h2>
        <small class="texto-suave monoespacio"><?= e($s['folio']) ?> · solicitud <?= e($s['modo']) ?> de <?= e($s['creador']) ?> el <?= e(fecha_corta($s['creado_en'])) ?></small>
      </div>
      <span class="chip estado-<?= $s['estado'] === 'en_ruta' ? 'en_atencion' : ($s['estado'] === 'completada' ? 'resuelto' : 'cerrado') ?>"><?= e(str_replace('_', ' ', $s['estado'])) ?></span>
    </header>

    <?php if ($s['mensaje']): ?>
      <p class="descripcion-solicitud"><?= nl2br(e($s['mensaje'])) ?></p>
    <?php endif; ?>

    <?php if ($s['modo'] === 'secuencial'): ?>
      <div class="ruta-flujo">
        <?php $ultimoOrden = null; foreach ($firmantes as $i => $f): ?>
          <?php if ($ultimoOrden !== null && $f['orden'] !== $ultimoOrden): ?><?= icono('flecha-der', 16) ?><?php endif; ?>
          <span title="Paso <?= (int) $f['orden'] ?>">
            <?= $f['estado'] === 'firmado' ? icono('ok', 15) : ($f['estado'] === 'rechazado' ? icono('alerta', 15) : icono('usuario', 15)) ?>
            <?= e($f['nombre']) ?>
          </span>
          <?php $ultimoOrden = $f['orden']; endforeach; ?>
      </div>
    <?php endif; ?>

    <h3 class="subtitulo">Documentos</h3>
    <div class="tabla-contenedor">
      <table class="tabla">
        <thead><tr><th>Documento</th><th class="oculta-movil">Tipo</th><th>Descargar</th></tr></thead>
        <tbody>
          <?php foreach ($documentos as $d): ?>
            <tr>
              <td>
                <?= e($d['nombre']) ?>
                <?php if ($d['tipo'] !== 'hoja_firmas' && !empty($d['sha256_actual'])): ?>
                  <small class="texto-suave monoespacio" title="Huella del documento vigente">· SHA-256 <?= e(substr($d['sha256_actual'], 0, 16)) ?>…</small>
                <?php endif; ?>
              </td>
              <td class="oculta-movil texto-suave"><?= e(str_replace('_', ' ', $d['tipo'])) ?> · subió <?= e($d['subido_por']) ?></td>
              <td>
                <a class="enlace" href="<?= url_archivo('firmas/documento/' . $d['id']) ?>">original</a>
                <?php if ($d['archivo_firmado']): ?>
                  · <a class="enlace" href="<?= url_archivo('firmas/documento/' . $d['id']) . '?v=firmado' ?>"><strong>firmado</strong></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="subtitulo">Firmantes</h3>
    <div class="tabla-contenedor">
      <table class="tabla">
        <thead><tr><th>Paso</th><th>Firmante</th><th>Estado</th><th class="oculta-movil">Respuesta</th></tr></thead>
        <tbody>
          <?php foreach ($firmantes as $f): ?>
            <tr>
              <td class="monoespacio"><?= (int) $f['orden'] ?></td>
              <td><?= e($f['nombre']) ?> <small class="texto-suave oculta-movil"><?= e($f['email']) ?></small></td>
              <td>
                <span class="chip estado-<?= $f['estado'] === 'firmado' ? 'resuelto' : ($f['estado'] === 'rechazado' ? 'cerrado' : 'pendiente') ?>"><?= e($f['estado']) ?></span>
                <?php if ($f['estado'] === 'rechazado' && $f['motivo_rechazo']): ?>
                  <small class="texto-suave">· <?= e($f['motivo_rechazo']) ?></small>
                <?php endif; ?>
              </td>
              <td class="oculta-movil texto-suave"><?= $f['respondido_en'] ? e(fecha_corta($f['respondido_en'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="subtitulo">Bitácora</h3>
    <ul class="bitacora">
      <?php foreach ($eventos as $ev): ?>
        <li class="evento evento-<?= e($ev['tipo']) ?>">
          <span class="evento-punto"></span>
          <div>
            <p class="evento-meta">
              <strong><?= e($ev['autor']) ?></strong>
              <small class="texto-suave">· <?= e(str_replace('_', ' ', $ev['tipo'])) ?> · <?= e(fecha_corta($ev['creado_en'])) ?></small>
            </p>
            <?php if ($ev['contenido']): ?><p><?= nl2br(e($ev['contenido'])) ?></p><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <aside class="card">
    <?php if ($meToca): ?>
      <p class="texto-suave">Revisa los documentos y su huella del Algoritmo de Hash Seguro de 256 bits (SHA-256), que permite detectar cambios, antes de autorizar la firma.</p>
      <?php foreach ($documentos as $vd): if ($vd['tipo'] !== 'hoja_firmas'): ?>
        <div style="position:relative;height:240px;margin:10px 0;border:1px solid var(--gris-200);overflow:hidden;background:#f3f4f6">
          <iframe src="<?= url_archivo('firmas/documento/' . $vd['id']) ?>#toolbar=0" title="Visor PDF" style="width:100%;height:100%;border:0"></iframe>
          <div class="zona-firma" style="position:absolute;right:12%;bottom:12%;width:150px;height:55px;border:2px dashed #b08b1e;background:rgba(255,248,210,.65);cursor:move"></div>
        </div>
      <?php endif; endforeach; ?>
      <header class="tarjeta-cabecera"><h2>Firmar digitalmente</h2></header>
      <p class="texto-suave">Se aplicará PDF Advanced Electronic Signatures (PAdES), estándar que incorpora una firma verificable en cada documento, mediante la infraestructura de llave pública (PKI) interna de la Facultad.</p>
      <?php if (!$pkiLista): ?>
        <p class="alerta"><?= icono('alerta', 14) ?> La infraestructura criptográfica aún no está lista. No es posible firmar hasta que la administración complete la instalación.</p>
      <?php elseif (!$tieneTotp): ?>
        <p class="alerta"><?= icono('alerta', 14) ?> Debes activar la contraseña de un solo uso basada en tiempo como segundo factor en <a class="enlace" href="<?= url('seguridad') ?>">Seguridad</a>.</p>
      <?php endif; ?>
      <?php if ($certificado): ?>
        <p class="texto-suave">Certificado individual: serie <span class="monoespacio"><?= e($certificado['serie']) ?></span> · vence <?= e(fecha_corta($certificado['valido_hasta'])) ?>.</p>
      <?php else: ?>
        <p class="texto-suave">Tu certificado individual se emitirá dentro del servicio protegido al realizar la primera firma.</p>
      <?php endif; ?>
      <?php if ($pkiLista): ?><p><a class="enlace" href="<?= url('firmas/certificado') ?>">Descargar certificado público raíz para Adobe Acrobat o Foxit</a></p><?php endif; ?>
      <form method="post" action="<?= url('firmas/' . $s['id'] . '/firmar') ?>" class="formulario" data-confirm="Se aplicará tu firma digital a todos los documentos vigentes de la solicitud. ¿Confirmas?">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label>Página del sello
          <select name="pagina">
            <?php foreach ($paginas as $p): ?>
              <option value="<?= e($p) ?>" <?= $p === 'ultima' ? 'selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Posición
          <select name="posicion">
            <?php foreach ($posiciones as $p): ?>
              <option value="<?= e($p) ?>" <?= $p === 'abajo_derecha' ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $p)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Código de segundo factor
          <input type="text" name="codigo_2fa" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required placeholder="000000">
          <small class="ayuda-form">Código temporal de seis dígitos de tu aplicación autenticadora. Se consume una sola vez.</small>
        </label>
        <button type="submit" class="btn btn-primario btn-bloque" <?= (!$pkiLista || !$tieneTotp) ? 'disabled' : '' ?>>Firmar con certificado individual</button>
      </form>

      <form method="post" action="<?= url('firmas/' . $s['id'] . '/rechazar') ?>" class="formulario" style="margin-top:14px">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label>Rechazar con motivo
          <textarea name="motivo" rows="2" required placeholder="Motivo del rechazo…"></textarea>
        </label>
        <button type="submit" class="btn btn-bloque">Rechazar</button>
      </form>
    <?php elseif ($s['estado'] === 'en_ruta'): ?>
      <header class="tarjeta-cabecera"><h2>En ruta</h2></header>
      <p class="texto-suave"><?= $s['modo'] === 'secuencial' ? 'La solicitud va en el paso ' . (int) $s['paso_actual'] . '.' : 'Esperando las firmas pendientes.' ?></p>
    <?php else: ?>
      <header class="tarjeta-cabecera"><h2>Solicitud <?= e(str_replace('_', ' ', $s['estado'])) ?></h2></header>
      <p class="texto-suave">Consulta los documentos y la hoja de firmas.</p>
    <?php endif; ?>

    <?php if ($puedeAnexar): ?>
      <form method="post" action="<?= url('firmas/' . $s['id'] . '/anexar') ?>" enctype="multipart/form-data" class="formulario" style="margin-top:14px">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label>Añadir anexos (PDF)
          <input type="file" name="documentos[]" accept="application/pdf" multiple required>
          <small class="ayuda-form">Los anexos acompañan la solicitud hacia el destinatario final.</small>
        </label>
        <button type="submit" class="btn btn-bloque">Anexar</button>
      </form>
    <?php endif; ?>

    <?php if ($puedeCancelar): ?>
      <form method="post" action="<?= url('firmas/' . $s['id'] . '/cancelar') ?>" data-confirm="¿Cancelar esta solicitud de firma?" style="margin-top:14px">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <button type="submit" class="btn btn-bloque">Cancelar solicitud</button>
      </form>
    <?php endif; ?>
  </aside>
</div>
<?php if ($meToca): ?><script>(function(){document.querySelectorAll('.zona-firma').forEach(function(z){let d=false,sx=0,sy=0,ox=0,oy=0;z.onpointerdown=function(e){d=true;sx=e.clientX;sy=e.clientY;ox=z.offsetLeft;oy=z.offsetTop;z.setPointerCapture(e.pointerId)};z.onpointermove=function(e){if(!d)return;z.style.left=(ox+e.clientX-sx)+'px';z.style.top=(oy+e.clientY-sy)+'px';z.style.right='auto';z.style.bottom='auto'};z.onpointerup=function(){d=false}})})();</script><?php endif; ?>
