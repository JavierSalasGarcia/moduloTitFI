<?php use App\Core\Auth; $t = $ticket; ?>
<p><a class="enlace" href="<?= url('tickets') ?>"><?= icono('flecha-izq', 14) ?> Volver a tickets</a></p>

<div class="rejilla-detalle">
  <section class="card">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($t['asunto']) ?></h2>
        <small class="texto-suave monoespacio"><?= e($t['folio']) ?> · <?= e($t['categoria']) ?> · levantado por <?= e($t['solicitante']) ?> el <?= e(fecha_corta($t['creado_en'])) ?></small>
      </div>
      <div class="grupo-chips">
        <span class="chip prioridad-<?= e($t['prioridad']) ?>">prioridad <?= e($t['prioridad']) ?></span>
        <span class="chip estado-<?= e($t['estado']) ?>"><?= e(str_replace('_', ' ', $t['estado'])) ?></span>
      </div>
    </header>

    <ol class="ux-progress-steps" aria-label="Avance de la solicitud"><?php foreach (['abierto'=>'Recibida','en_atencion'=>'En atención','resuelto'=>'Resuelta','cerrado'=>'Finalizada'] as $estado=>$etiqueta): ?><li <?= $t['estado']===$estado?'aria-current="step"':'' ?>><?= $etiqueta ?></li><?php endforeach; ?></ol>
    <p class="ux-helper"><?= match($t['estado']) {'abierto'=>'El área recibió tu solicitud. Aquí podrás seguir su atención.', 'en_atencion'=>'El área está atendiendo tu solicitud. Revisa los mensajes por si necesita información.', 'resuelto'=>'El área indicó una solución. Revisa la respuesta.', 'rechazado'=>'Revisa el motivo y la información que necesita corregirse.', default=>'La solicitud ha finalizado. Puedes consultar su historial.'} ?></p>
    <div class="ruta-flujo">
      <span><?= icono('usuario', 15) ?> <?= e($t['solicitante']) ?></span>
      <?= icono('flecha-der', 16) ?>
      <span><?= icono('edificio', 15) ?> <?= e($t['destino']) ?></span>
    </div>

    <?php if (!empty($carpeta)): 
      $urlEncodedPath = implode('/', array_map('rawurlencode', explode('/', $carpeta['ruta_relativa'])));
      $urlCarpetaFinal = str_starts_with($carpeta['ruta_relativa'], 'http') ? $carpeta['ruta_relativa'] : ($carpeta['url_base'] . '/' . $urlEncodedPath);
    ?>
      <div class="ruta-flujo" style="margin: 15px 0; background: #e8f5e9; border: 1px solid #d1fae5; color: var(--verde-900); padding: 12px; border-radius: var(--radio);">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
          <div style="min-width: 200px; flex: 1;">
            <div style="font-weight: bold; display: flex; align-items: center; gap: 6px; font-size: 13px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
              Carpeta de Teams Vinculada:
            </div>
            <span style="font-size: 14px; display: block; margin-top: 4px; font-weight: bold;"><?= e(basename($carpeta['ruta_relativa'])) ?></span>
            <span style="font-size: 11px; display: block; word-break: break-all; color: var(--gris-600); margin-top: 2px; font-weight: normal;"><?= e($carpeta['ruta_relativa']) ?></span>
          </div>
          <a href="<?= $urlCarpetaFinal ?>" target="_blank" class="btn btn-mini btn-primario" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-weight: bold; height: 32px;">
            Abrir Carpeta ↗
          </a>
        </div>
      </div>
    <?php endif; ?>

    <p class="descripcion-solicitud"><?= nl2br(e($t['descripcion'])) ?></p>

    <?php 
    $paras = array_filter($destinatarios, fn($d) => $d['tipo'] === 'para');
    $ccs = array_filter($destinatarios, fn($d) => $d['tipo'] === 'cc');
    ?>
    <?php if (!empty($paras)): ?>
      <div style="margin-top: 15px; border-top: 1px solid var(--gris-100); padding-top: 10px; margin-bottom: 10px;">
        <strong style="font-size: 12px; color: var(--texto); display: block; margin-bottom: 4px;">👥 Para:</strong>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
          <?php foreach ($paras as $d): ?>
            <span style="font-size: 11px; background: var(--verde-050); color: var(--verde-900); padding: 3px 8px; border-radius: 20px; border: 1px solid #d1fae5; font-weight: 500;">
              <?= $d['destinatario_type'] === 'departamento' ? '🏢' : '👤' ?> <?= e($d['destinatario_nombre']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($ccs)): ?>
      <div style="margin-top: 10px; margin-bottom: 10px;">
        <strong style="font-size: 12px; color: var(--texto); display: block; margin-bottom: 4px;">👥 CC (Con copia):</strong>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
          <?php foreach ($ccs as $d): ?>
            <span style="font-size: 11px; background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 20px; border: 1px solid #e2e8f0; font-weight: 500;">
              <?= $d['destinatario_type'] === 'departamento' ? '🏢' : '👤' ?> <?= e($d['destinatario_nombre']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($adjuntos)): ?>
      <div style="margin-top: 15px; border-top: 1px solid var(--gris-100); padding-top: 10px; margin-bottom: 20px;">
        <strong style="font-size: 12px; color: var(--texto); display: block; margin-bottom: 6px;">📎 Archivos Adjuntos:</strong>
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px;">
          <?php foreach ($adjuntos as $adj): ?>
            <li style="font-size: 12.5px; display: flex; align-items: center; justify-content: space-between; background: #fafafa; padding: 6px 10px; border-radius: 6px; border: 1px solid var(--gris-100);">
              <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%; font-weight: 500; color: var(--texto);">
                <?= e($adj['nombre_original']) ?>
              </span>
              <a href="<?= url('tickets/archivo/' . $adj['id']) ?>" class="btn btn-mini" style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px;" title="Descargar Archivo">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Descargar
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= url('tickets/' . $t['id'] . '/clasificar') ?>" class="mover-cuadrante">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <input type="hidden" name="volver" value="detalle">
      <label class="texto-suave" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        Mi clasificación (personal):
        <select name="cuadrante">
          <option value="">— sin clasificar —</option>
          <?php foreach ($cuadrantes as $c): ?>
            <option value="<?= e($c) ?>" <?= $miCuadrante === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Guardar</button>
      </label>
    </form>

    <h3 class="subtitulo">Seguimiento</h3>
    <?php if (!$eventos): ?>
      <p class="texto-suave">Aún no hay actividad en este ticket.</p>
    <?php else: ?>
      <ul class="bitacora">
        <?php foreach ($eventos as $ev): ?>
          <li class="evento evento-<?= e($ev['tipo']) ?>">
            <span class="evento-punto"></span>
            <div>
              <p class="evento-meta">
                <strong><?= e($ev['autor']) ?></strong>
                <small class="texto-suave">· <?= e($ev['depto_autor']) ?> · <?= e(fecha_corta($ev['creado_en'])) ?></small>
                <?php if ($ev['estado_nuevo']): ?>
                  <span class="chip estado-<?= e($ev['estado_nuevo']) ?>"><?= e(str_replace('_', ' ', $ev['estado_nuevo'])) ?></span>
                <?php endif; ?>
              </p>
              <p><?= nl2br(e($ev['contenido'])) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" action="<?= url('tickets/' . $t['id'] . '/comentar') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Agregar comentario
        <textarea name="contenido" rows="2" required placeholder="Escribe una actualización…"></textarea>
      </label>
      <div class="acciones-formulario">
        <button type="submit" class="btn btn-primario">Comentar</button>
      </div>
    </form>
  </section>

  <?php if ($esDestino): ?>
    <aside class="card">
      <?php $cerrado = in_array($t['estado'], ['cerrado', 'rechazado'], true); ?>
      <header class="tarjeta-cabecera"><h2>Atender ticket</h2></header>
      <?php if ($cerrado): ?>
        <p class="texto-suave">Este ticket ya está <?= e($t['estado']) ?>; no admite más acciones.</p>
      <?php else: ?>
        <p class="texto-suave">Tu departamento atiende este asunto: puedes actualizar su estado.</p>
        <form method="post" action="<?= url('tickets/' . $t['id'] . '/estado') ?>" class="formulario">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <label>Nuevo estado
            <select name="estado">
              <?php foreach ($estados as $est): ?>
                <option value="<?= e($est) ?>" <?= $est === $t['estado'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $est)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Nota para el solicitante
            <textarea name="nota" rows="2" placeholder="Motivo o detalle del cambio…"></textarea>
          </label>
          <button type="submit" class="btn btn-primario btn-bloque">Actualizar estado</button>
        </form>

        <h3 class="subtitulo">Redirigir a otra área</h3>
        <p class="texto-suave">Si el asunto no corresponde a tu departamento, canalízalo al correcto: regresará a estado abierto.</p>
        <form method="post" action="<?= url('tickets/' . $t['id'] . '/redirigir') ?>" class="formulario">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <label>Departamento que atiende
            <select name="depto_id" required>
              <option value="">— Elegir —</option>
              <?php foreach ($departamentos as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= e($d['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Motivo (opcional)
            <input type="text" name="motivo" maxlength="255" placeholder="Por qué se redirige…">
          </label>
          <button type="submit" class="btn btn-bloque">Redirigir</button>
        </form>

        <h3 class="subtitulo">Rechazar ticket</h3>
        <form method="post" action="<?= url('tickets/' . $t['id'] . '/rechazar') ?>" class="formulario" data-confirm="¿Rechazar este ticket? El solicitante verá el motivo.">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <label>Motivo del rechazo <span class="req">*</span>
            <textarea name="motivo" rows="2" required placeholder="Por qué no procede…"></textarea>
          </label>
          <button type="submit" class="btn btn-bloque">Rechazar</button>
        </form>
      <?php endif; ?>
    </aside>
  <?php endif; ?>
</div>
