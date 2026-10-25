<?php use App\Core\Auth; ?>
<p><a class="enlace" href="<?= url('comisiones') ?>"><?= icono('flecha-izq', 14) ?> Volver a comisiones</a></p>

<div class="rejilla-detalle">
  <section class="card">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($c['titulo']) ?></h2>
        <small class="texto-suave">
          Responsable: <?= e($c['depto']) ?> · creada por <?= e($c['creador']) ?> el <?= e(fecha_corta($c['creado_en'])) ?>
        </small>
      </div>
      <span class="chip estado-<?= e($c['estado']) ?>"><?= e(str_replace('_', ' ', $c['estado'])) ?></span>
    </header>

    <div class="ruta-flujo">
      Origen: <?= $c['origen'] === 'peticion' ? 'petición del foro' : 'reunión convocada' ?>
      <?php if ($c['publicacion_id']): ?>
        · <a href="<?= url('foro/' . $c['publicacion_id']) ?>">«<?= e($c['peticion_titulo']) ?>»</a>
      <?php endif; ?>
    </div>

    <p class="descripcion-solicitud"><?= nl2br(e($c['descripcion'])) ?></p>

    <?php if ($miEstado === 'invitado'): ?>
      <div class="flash flash-alerta">
        Recibiste una asignación a esta comisión. Debes confirmarla para participar en el seguimiento.
        <form method="post" action="<?= url('comisiones/' . $c['id'] . '/responder') ?>" class="grupo-chips" style="margin-top:8px">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button type="submit" name="respuesta" value="aceptar" class="btn btn-mini btn-primario">Aceptar asignación</button>
          <button type="submit" name="respuesta" value="rechazar" class="btn btn-mini btn-peligro">Declinar</button>
        </form>
      </div>
    <?php endif; ?>

    <ol class="ux-progress-steps" aria-label="Avance de la comisión"><?php foreach (['formacion'=>'En formación','en_seguimiento'=>'En seguimiento','concluida'=>'Concluida'] as $estado=>$etiqueta): ?><li <?= $c['estado']===$estado?'aria-current="step"':'' ?>><?= $etiqueta ?></li><?php endforeach; ?></ol>
    <h3 class="subtitulo">Acuerdos y avances</h3>
    <section class="ux-next-step" aria-label="Próximo compromiso">
      <h3>Próximo compromiso</h3>
      <?php if ($compromiso): ?>
        <p><?= e($compromiso['texto']) ?></p><p>Fecha: <time datetime="<?= e($compromiso['fecha']) ?>"><?= e($compromiso['fecha']) ?></time></p>
      <?php else: ?><p>Aún no se ha definido el próximo compromiso.</p><?php endif; ?>
      <?php if ($esGestor && $c['estado'] !== 'concluida'): ?>
        <details><summary>Definir o actualizar compromiso</summary>
          <form method="post" action="<?= url('comisiones/' . $c['id'] . '/compromiso') ?>" class="formulario">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <label>Qué se hará<textarea name="compromiso" maxlength="500" required><?= e($compromiso['texto'] ?? '') ?></textarea></label>
            <label>Para cuándo<input type="date" name="fecha" value="<?= e($compromiso['fecha'] ?? '') ?>" required></label>
            <button class="btn btn-primario">Guardar compromiso</button>
          </form>
        </details>
      <?php endif; ?>
    </section>
    <?php if (!$bitacora): ?>
      <p class="texto-suave">Aún no hay avances registrados.</p>
    <?php else: ?>
      <ul class="bitacora">
        <?php foreach ($bitacora as $b): ?>
          <li class="evento">
            <span class="evento-punto"></span>
            <div>
              <p class="evento-meta">
                <strong><?= e($b['autor']) ?></strong>
                <small class="texto-suave">· <?= e(fecha_corta($b['creado_en'])) ?></small>
              </p>
              <p><?= nl2br(e($b['contenido'])) ?></p>
              <?php if ($b['archivo']): ?>
                <p><a class="enlace" href="<?= url_archivo('comisiones/archivo/' . $b['id']) ?>">📎 Descargar evidencia adjunta</a></p>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($puedeBitacora && $c['estado'] !== 'concluida'): ?>
      <form method="post" action="<?= url('comisiones/' . $c['id'] . '/bitacora') ?>" class="formulario" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label>Registrar avance <span class="req">*</span>
          <textarea name="contenido" rows="3" required placeholder="Acción realizada, acuerdos, hallazgos…"></textarea>
        </label>
        <label>Evidencia (opcional: pdf, imagen, office, zip · máx. 10 MB)
          <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx,.pptx,.zip">
        </label>
        <div class="acciones-formulario">
          <button type="submit" class="btn btn-primario">Registrar en bitácora</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <aside class="columna-lateral">
    <section class="card">
      <header class="tarjeta-cabecera"><h2>Integrantes</h2></header>
      <?php if (!$miembros): ?><p class="texto-suave">Sin integrantes todavía.</p><?php endif; ?>
      <ul class="lista-canales">
        <?php foreach ($miembros as $m): ?>
          <li>
            <strong><?= e($m['nombre']) ?></strong>
            <small class="texto-suave"><?= e($m['depto']) ?></small>
            <div class="grupo-chips" style="margin-top:4px">
              <span class="chip estado-<?= e($m['estado']) ?>"><?= e($m['estado']) ?></span>
              <?php if ($m['es_voluntario']): ?><span class="badge badge-verde">voluntario</span><?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if (Auth::rol() === 'profesor' && !$miEstado && $c['estado'] !== 'concluida'): ?>
        <form method="post" action="<?= url('comisiones/' . $c['id'] . '/postular') ?>" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button type="submit" class="btn btn-oro btn-bloque">Postularme como voluntario</button>
        </form>
      <?php endif; ?>
    </section>

    <?php if ($esGestor): ?>
      <section class="card">
        <header class="tarjeta-cabecera"><h2>Gestionar</h2></header>
        <?php if ($profesores && $c['estado'] !== 'concluida'): ?>
          <form method="post" action="<?= url('comisiones/' . $c['id'] . '/invitar') ?>" class="formulario">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <label>Invitar profesor
              <select name="profesor">
                <?php foreach ($profesores as $p): ?>
                  <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button type="submit" class="btn btn-bloque">Enviar invitación</button>
          </form>
          <hr style="border:none;border-top:1px solid var(--gris-100);margin:14px 0">
        <?php endif; ?>
        <form method="post" action="<?= url('comisiones/' . $c['id'] . '/estado') ?>" class="formulario" data-confirm="¿Actualizar el estado de la comisión?">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <label>Estado
            <select name="estado">
              <?php foreach (['formacion', 'en_seguimiento', 'concluida'] as $est): ?>
                <option value="<?= $est ?>" <?= $est === $c['estado'] ? 'selected' : '' ?>><?= str_replace('_', ' ', $est) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button type="submit" class="btn btn-primario btn-bloque">Actualizar estado</button>
        </form>
      </section>
    <?php endif; ?>
  </aside>
</div>
