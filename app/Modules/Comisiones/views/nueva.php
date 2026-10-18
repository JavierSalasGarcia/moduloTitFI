<?php use App\Core\Auth; ?>
<section class="card" style="max-width:760px">
  <?php if ($publicacion): ?>
    <div class="ruta-flujo">
      Origen: petición del foro «<?= e($publicacion['titulo']) ?>» (alcanzó el umbral de apoyos)
    </div>
  <?php else: ?>
    <div class="ruta-flujo">Origen: reunión convocada / acuerdo de órgano colegiado</div>
  <?php endif; ?>

  <form method="post" action="<?= url('comisiones') ?>" class="formulario" data-draft="comision">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <?php if ($publicacion): ?>
      <input type="hidden" name="publicacion" value="<?= (int) $publicacion['id'] ?>">
    <?php endif; ?>

    <label>Título de la comisión <span class="req">*</span>
      <input type="text" name="titulo" required maxlength="180"
             value="<?= $publicacion ? 'Comisión de seguimiento: ' . e($publicacion['titulo']) : '' ?>">
    </label>

    <label>Encargo / descripción <span class="req">*</span>
      <textarea name="descripcion" required rows="4" placeholder="Objetivo, alcance y entregables esperados de la comisión…"><?= $publicacion ? e($publicacion['contenido']) : '' ?></textarea>
    </label>

    <label>Departamento responsable <span class="req">*</span>
      <select name="depto" required>
        <option value="" disabled selected>Selecciona el área a cuyas funciones corresponde el asunto…</option>
        <?php foreach ($deptos as $d): ?>
          <option value="<?= (int) $d['id'] ?>"><?= e($d['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Invitar profesores (deberán confirmar su participación)
      <select name="profesores[]" multiple size="6">
        <?php foreach ($profesores as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> · <?= e($p['depto']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="ayuda-form">Marca a las personas que deseas invitar. Los profesores también pueden postularse como voluntarios.</p>
    </label>

    <div class="acciones-formulario">
      <a class="btn" href="<?= url('comisiones') ?>">Cancelar</a>
      <button type="submit" class="btn btn-primario">Crear comisión</button>
    </div>
  </form>
</section>
