<?php use App\Core\Auth; $s = $solicitud; ?>
<p><a class="enlace" href="<?= url('solicitudes') ?>"><?= icono('flecha-izq', 14) ?> Volver a solicitudes</a></p>

<div class="rejilla-detalle">
  <section class="tarjeta">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($s['asunto']) ?></h2>
        <small class="texto-suave monoespacio"><?= e($s['folio']) ?> · levantada por <?= e($s['solicitante']) ?> el <?= e(fecha_corta($s['creado_en'])) ?></small>
      </div>
      <div class="grupo-chips">
        <span class="chip prioridad-<?= e($s['prioridad']) ?>">prioridad <?= e($s['prioridad']) ?></span>
        <span class="chip estado-<?= e($s['estado']) ?>"><?= e(str_replace('_', ' ', $s['estado'])) ?></span>
      </div>
    </header>

    <div class="ruta-flujo">
      <span><?= icono('edificio', 15) ?> <?= e($s['origen']) ?></span>
      <?= icono('flecha-der', 16) ?>
      <span><?= icono('edificio', 15) ?> <?= e($s['destino']) ?></span>
    </div>

    <p class="descripcion-solicitud"><?= nl2br(e($s['descripcion'])) ?></p>

    <h3 class="subtitulo">Bitácora</h3>
    <?php if (!$eventos): ?>
      <p class="texto-suave">Sin actividad registrada aún.</p>
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

    <form method="post" action="<?= url('solicitudes/' . $s['id'] . '/comentar') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Agregar comentario
        <textarea name="contenido" rows="2" required placeholder="Escribe una actualización para la contraparte…"></textarea>
      </label>
      <div class="acciones-formulario">
        <button type="submit" class="btn btn-primario"><?= icono('enviar', 14) ?> Comentar</button>
      </div>
    </form>
  </section>

  <?php if ($esDestino): ?>
    <aside class="tarjeta">
      <header class="tarjeta-cabecera"><h2><?= icono('engrane', 16) ?> Atender solicitud</h2></header>
      <p class="texto-suave">Tu departamento es el destinatario: puedes actualizar el estado del trámite.</p>
      <form method="post" action="<?= url('solicitudes/' . $s['id'] . '/estado') ?>" class="formulario">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label>Nuevo estado
          <select name="estado">
            <?php foreach ($estados as $est): ?>
              <option value="<?= e($est) ?>" <?= $est === $s['estado'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $est)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Nota (visible en la bitácora)
          <textarea name="nota" rows="2" placeholder="Motivo o detalle del cambio…"></textarea>
        </label>
        <button type="submit" class="btn btn-primario btn-bloque">Actualizar estado</button>
      </form>
    </aside>
  <?php endif; ?>
</div>
