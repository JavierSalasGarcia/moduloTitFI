<?php use App\Core\Auth; ?>
<p><a class="enlace" href="<?= url('foro') ?>"><?= icono('flecha-izq', 14) ?> Volver al foro</a></p>

<div class="rejilla-detalle">
  <section class="card foro-pub">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($p['titulo']) ?></h2>
        <small class="texto-suave"><?= e($p['autor']) ?> · <?= e(fecha_corta($p['creado_en'])) ?></small>
      </div>
      <div class="grupo-chips">
        <span class="badge badge-oro"><?= e($p['tipo']) ?></span>
        <span class="chip estado-<?= e($p['estado']) ?>"><?= e(str_replace('_', ' ', $p['estado'])) ?></span>
      </div>
    </header>

    <p class="descripcion-solicitud"><?= nl2br(e($p['contenido'])) ?></p>

    <?php if ($p['tipo'] === 'peticion' && $p['estado'] === 'abierta'): ?>
      <div class="progreso"><div class="progreso-barra" style="width:<?= min(100, (int) ($p['likes'] * 100 / max(1, (int)$p['umbral_likes']))) ?>%"></div></div>
      <small class="texto-suave"><?= (int) $p['likes'] ?> de <?= (int)$p['umbral_likes'] ?> apoyos para pasar a trazabilidad</small>
    <?php endif; ?>

    <div class="foro-meta" style="margin-top:10px">
      <form method="post" action="<?= url('foro/' . $p['id'] . '/like') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <button type="submit" class="btn btn-mini btn-like<?= $p['mi_like'] ? ' activo' : '' ?>">👍 <?= (int) $p['likes'] ?></button>
      </form>
    </div>

    <h3 class="subtitulo">Comentarios (los más apoyados primero)</h3>
    <?php if (!$comentarios): ?>
      <p class="texto-suave">Sé quien inicie la conversación.</p>
    <?php endif; ?>
    <?php foreach ($comentarios as $c): ?>
      <div class="comentario">
        <p class="evento-meta">
          <strong><?= e($c['autor']) ?></strong>
          <small class="texto-suave">· <?= e(fecha_corta($c['creado_en'])) ?></small>
        </p>
        <p><?= nl2br(e($c['contenido'])) ?></p>
        <form method="post" action="<?= url('foro/comentario/' . $c['id'] . '/like') ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button type="submit" class="btn btn-mini btn-like<?= $c['mi_like'] ? ' activo' : '' ?>">👍 <?= (int) $c['likes'] ?></button>
        </form>
      </div>
    <?php endforeach; ?>

    <form method="post" action="<?= url('foro/' . $p['id'] . '/comentar') ?>" class="formulario" style="margin-top:12px">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Comentar
        <textarea name="contenido" rows="2" required placeholder="Escribe tu comentario…"></textarea>
      </label>
      <div class="acciones-formulario">
        <button type="submit" class="btn btn-primario">Comentar</button>
      </div>
    </form>
  </section>

  <aside class="columna-lateral">
    <?php if ($comision): ?>
      <section class="card">
        <header class="tarjeta-cabecera"><h2>Comisión asignada</h2></header>
        <p><strong><?= e($comision['titulo']) ?></strong></p>
        <p><span class="chip estado-<?= e($comision['estado']) ?>"><?= e(str_replace('_', ' ', $comision['estado'])) ?></span></p>
        <a class="btn btn-bloque" href="<?= url('comisiones/' . $comision['id']) ?>">Ver seguimiento</a>
      </section>
    <?php elseif ($p['estado'] === 'en_trazabilidad'): ?>
      <section class="card">
        <header class="tarjeta-cabecera"><h2>Etapa de trazabilidad</h2></header>
        <p class="texto-suave">Esta petición alcanzó el apoyo suficiente. Corresponde a la Dirección,
        a las Subdirecciones o al titular competente formar una comisión de seguimiento.</p>
        <?php if ($puedeComision): ?>
          <a class="btn btn-primario btn-bloque" href="<?= url('comisiones/nueva?publicacion=' . $p['id']) ?>">Formar comisión</a>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <?php if ($p['tipo'] === 'peticion' && Auth::tieneRol('admin')): ?>
      <section class="card">
        <header class="tarjeta-cabecera"><h2>Configurar umbral</h2></header>
        <form method="post" action="<?= url('foro/' . $p['id'] . '/umbral') ?>" class="formulario">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <label>Umbral de apoyos
            <input type="number" name="umbral_likes" value="<?= (int) $p['umbral_likes'] ?>" min="1" required>
          </label>
          <div class="acciones-formulario">
            <button type="submit" class="btn btn-primario btn-bloque">Actualizar umbral</button>
          </div>
        </form>
      </section>
    <?php endif; ?>
  </aside>
</div>
