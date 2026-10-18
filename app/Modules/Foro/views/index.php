<?php use App\Core\Auth; ?>
<div class="barra-acciones">
  <nav class="pestanas" aria-label="Tipo de publicación">
    <a class="pestana<?= $tipo === '' ? ' activa' : '' ?>" href="<?= url('foro') ?>">Todo</a>
    <a class="pestana<?= $tipo === 'publicacion' ? ' activa' : '' ?>" href="<?= url('foro?tipo=publicacion') ?>">Publicaciones</a>
    <a class="pestana<?= $tipo === 'peticion' ? ' activa' : '' ?>" href="<?= url('foro?tipo=peticion') ?>">Propuestas</a>
  </nav>
</div>

<section class="card" data-disclosure="Publicar una conversación o propuesta">
  <header class="tarjeta-cabecera"><h2>Publicar en el foro</h2></header>
  <form method="post" action="<?= url('foro') ?>" class="formulario" data-draft="foro">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="form-rejilla">
      <label>Título <span class="req">*</span>
        <input type="text" name="titulo" required maxlength="180">
      </label>
      <label>Tipo
        <select name="tipo">
          <option value="publicacion">Publicación (conversación)</option>
          <option value="peticion">Propuesta (reúne apoyos)</option>
        </select>
      </label>
    </div>
    <label>Contenido <span class="req">*</span>
      <textarea name="contenido" rows="3" required placeholder="¿Qué quieres compartir o pedir?"></textarea>
    </label>
    <p class="ayuda-form">Una petición que reúna <?= $umbral ?> apoyos pasa a seguimiento para que las autoridades formen una comisión de seguimiento.</p>
    <div class="acciones-formulario">
      <button type="submit" class="btn btn-primario">Publicar</button>
    </div>
  </form>
</section>

<?php foreach ($publicaciones as $p): ?>
  <article class="card foro-pub">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><a href="<?= url('foro/' . $p['id']) ?>"><?= e($p['titulo']) ?></a></h2>
        <small class="texto-suave"><?= e($p['autor']) ?> · <?= e(fecha_corta($p['creado_en'])) ?></small>
      </div>
      <div class="grupo-chips">
        <span class="badge <?= $p['tipo'] === 'peticion' ? 'badge-oro' : 'badge-azul badge-verde' ?>"><?= e($p['tipo']) ?></span>
        <?php if ($p['estado'] !== 'abierta'): ?>
          <span class="chip estado-<?= e($p['estado']) ?>"><?= e(str_replace('_', ' ', $p['estado'])) ?></span>
        <?php endif; ?>
      </div>
    </header>
    <p style="margin:0"><?= nl2br(e(mb_strimwidth($p['contenido'], 0, 280, '…'))) ?></p>
    <?php if ($p['tipo'] === 'peticion' && $p['estado'] === 'abierta'): ?>
      <div>
        <div class="progreso"><div class="progreso-barra" style="width:<?= min(100, (int) ($p['likes'] * 100 / max(1, (int)$p['umbral_likes']))) ?>%"></div></div>
        <small class="texto-suave"><?= (int) $p['likes'] ?> de <?= (int)$p['umbral_likes'] ?> apoyos para iniciar el seguimiento</small>
      </div>
    <?php endif; ?>
    <div class="foro-meta">
      <form method="post" action="<?= url('foro/' . $p['id'] . '/like') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <button type="submit" class="btn btn-mini btn-like<?= $p['mi_like'] ? ' activo' : '' ?>">Apoyar · <?= (int) $p['likes'] ?></button>
      </form>
      <a href="<?= url('foro/' . $p['id']) ?>"><?= (int) $p['comentarios'] ?> comentarios</a>
    </div>
  </article>
<?php endforeach; ?>
