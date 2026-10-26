<?php
use App\Core\Auth;
$f = static fn(?string $v): string => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
?>
<?php include __DIR__ . '/_estilos.php'; ?>
<section class="tarjeta">
  <form method="post" action="<?= url('concursos/gestion') ?>" class="formulario">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= e((string) ($c['id'] ?? '')) ?>">
    <div class="tit-campos" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px">
      <label>Título <input name="titulo" maxlength="200" required value="<?= e($c['titulo']) ?>"></label>
      <label>Dirección (concursos/…) <input name="slug" maxlength="80" pattern="[a-z0-9-]{3,80}" required value="<?= e($c['slug']) ?>"></label>
      <label>Estado <select name="estado"><?php foreach (['borrador' => 'Borrador (solo gestión)', 'publicado' => 'Publicado', 'cancelado' => 'Cancelado'] as $k => $n): ?><option value="<?= $k ?>" <?= $c['estado'] === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
      <label>Temática <input name="tematica" maxlength="200" value="<?= e($c['tematica']) ?>"></label>
      <label>Publicación de la convocatoria <input type="datetime-local" name="publicacion" required value="<?= $f($c['publicacion']) ?>"></label>
      <label>Fin de la recepción <input type="datetime-local" name="recepcion_fin" required value="<?= $f($c['recepcion_fin']) ?>"></label>
      <label>Inicio de la votación <input type="datetime-local" name="votacion_inicio" required value="<?= $f($c['votacion_inicio']) ?>"></label>
      <label>Fin de la votación <input type="datetime-local" name="votacion_fin" required value="<?= $f($c['votacion_fin']) ?>"></label>
      <label>Premiación y resultados (fecha y hora) <input type="datetime-local" name="premiacion" required value="<?= $f($c['premiacion']) ?>"></label>
      <label>Premio 1.er lugar (MXN; vacío = por anunciar) <input name="premio_1" inputmode="decimal" value="<?= e((string) ($c['premio_1'] ?? '')) ?>"></label>
      <label>Premio 2.º lugar <input name="premio_2" inputmode="decimal" value="<?= e((string) ($c['premio_2'] ?? '')) ?>"></label>
      <label>Premio 3.er lugar <input name="premio_3" inputmode="decimal" value="<?= e((string) ($c['premio_3'] ?? '')) ?>"></label>
      <label>Obras por persona <input type="number" name="max_por_persona" min="1" max="10" value="<?= (int) $c['max_por_persona'] ?>"></label>
      <label>Máximo de caracteres por obra <input type="number" name="max_caracteres" min="200" max="10000" step="50" value="<?= (int) $c['max_caracteres'] ?>"></label>
    </div>
    <label>Resumen (aparece en la lista de concursos) <textarea name="resumen" rows="2" maxlength="400"><?= e($c['resumen']) ?></textarea></label>
    <label>Bases <textarea name="bases" rows="22" required><?= e($c['bases']) ?></textarea></label>
    <p class="texto-secundario" style="font-size:13px">Formato: «## » al inicio de un renglón para un título, «- » para una viñeta y una línea en blanco entre párrafos. Marcadores que se llenan solos: {tematica}, {max_por_persona}, {max_caracteres}, {recepcion_fin}, {votacion_inicio}, {votacion_fin}, {premiacion}, {premio_1}, {premio_2}, {premio_3}.</p>
    <div class="acciones-formulario">
      <button class="btn btn-primario" type="submit">Guardar</button>
      <?php if (!empty($c['slug']) && !empty($c['id'])): ?><a class="btn" href="<?= url('concursos/' . $c['slug']) ?>" target="_blank" rel="noopener">Ver convocatoria</a><?php endif; ?>
      <a class="btn" href="<?= url('concursos/gestion') ?>">Volver</a>
    </div>
  </form>
</section>
