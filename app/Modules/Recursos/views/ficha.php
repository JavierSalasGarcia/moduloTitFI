<?php use App\Core\Auth; ?>
<?php $calendarioEscolar=(int)$r['id']===1 && $r['titulo']==='Calendario Escolar'; ?>
<?php if ($calendarioEscolar) require __DIR__.'/calendario_2026_b.php'; ?>
<div class="rejilla-detalle">
  <section class="card">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($r['titulo']) ?></h2>
        <small class="texto-suave"><?= $r['tipo'] === 'enlace' ? 'Enlace externo' : 'Documento oficial' ?></small>
      </div>
      <?php if ($r['tipo'] === 'enlace'): ?>
        <a class="btn btn-primario" href="<?= e($r['url']) ?>" target="_blank" rel="noopener">Abrir sitio ↗</a>
      <?php elseif ($r['archivo']): ?>
        <a class="btn btn-primario" href="<?= url_archivo('recursos/archivo/' . $r['id']) ?>"><?= icono('entrada', 15) ?> Descargar archivo</a>
      <?php else: ?>
        <span class="badge badge-ambar">Archivo pendiente de publicar</span>
      <?php endif; ?>
    </header>

    <?php if ($r['descripcion']): ?>
      <p class="descripcion-solicitud"><?= nl2br(e($r['descripcion'])) ?></p>
    <?php endif; ?>

    <?php if ($r['notas_admin']): ?>
      <?php if ($calendarioEscolar): ?><details><summary>Ver todas las fechas en texto</summary><?php endif; ?>
      <h3 class="subtitulo">Notas del administrador</h3>
      <div class="ruta-flujo" style="display:block"><?= nl2br(e($r['notas_admin'])) ?></div>
      <?php if ($calendarioEscolar): ?></details><?php endif; ?>
    <?php endif; ?>

    <h3 class="subtitulo">Mis notas personales</h3>
    <p class="texto-suave">Solo tú ves estas notas. Ej.: «Inscribirme el 22 de julio», «días de permiso que he tomado».</p>

    <?php if (!$notas): ?>
      <p class="texto-suave">Aún no tienes notas en este recurso.</p>
    <?php endif; ?>
    <ul class="lista-canales">
      <?php foreach ($notas as $n): ?>
        <li>
          <form method="post" action="<?= url('recursos/notas/' . $n['id'] . '/editar') ?>" class="form-en-linea" style="width:100%;gap:8px;display:flex">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="text" name="contenido" value="<?= e($n['contenido']) ?>" maxlength="500" style="flex:1;margin:0">
            <button type="submit" class="btn btn-mini" title="Guardar cambios">Guardar</button>
            <button type="submit" class="btn btn-mini btn-peligro" title="Eliminar nota"
                    formaction="<?= url('recursos/notas/' . $n['id'] . '/eliminar') ?>">✕</button>
          </form>
          <small class="texto-suave">Última edición: <?= e(fecha_corta($n['actualizado_en'])) ?></small>
        </li>
      <?php endforeach; ?>
    </ul>

    <form method="post" action="<?= url('recursos/' . $r['id'] . '/notas') ?>" class="formulario" style="margin-top:12px">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Agregar nota
        <input type="text" name="contenido" required maxlength="500" placeholder="Escribe un recordatorio o apunte personal…">
      </label>
      <div class="acciones-formulario">
        <button type="submit" class="btn btn-primario">Guardar nota</button>
      </div>
    </form>
  </section>

  <aside class="columna-lateral">
    <section class="card">
      <header class="tarjeta-cabecera"><h2>Relacionado</h2></header>
      <a class="btn btn-bloque" href="<?= url('calendario') ?>" style="margin-bottom:8px">Mi calendario</a>
      <a class="btn btn-bloque" href="<?= url('eventos') ?>">Avisos y eventos</a>
    </section>
  </aside>
</div>
