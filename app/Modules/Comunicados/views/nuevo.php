<?php use App\Core\Auth; ?>
<section class="card" style="max-width:720px">
  <form method="post" action="<?= url('comunicados') ?>" class="formulario">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

    <label>Título <span class="req">*</span>
      <input type="text" name="titulo" required maxlength="180">
    </label>

    <label>Contenido <span class="req">*</span>
      <textarea name="contenido" required rows="6" placeholder="Texto del comunicado…"></textarea>
    </label>

    <div class="form-rejilla">
      <label>Alcance <span class="req">*</span>
        <select name="alcance" id="selAlcance" required>
          <option value="todos">Todos los alumnos</option>
          <option value="licenciatura">Solo licenciatura</option>
          <option value="posgrado">Solo posgrado</option>
          <option value="programa">Un programa específico</option>
        </select>
      </label>
      <label id="campoPrograma" style="display:none">Programa
        <select name="programa">
          <?php foreach ($programas as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= e($p['siglas']) ?> · <?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label>Vigente hasta (opcional)<input type="date" name="vence_en"><small class="ayuda-form">Al vencer pasa al archivo. Si no eliges fecha, permanece vigente.</small></label>
    <div class="acciones-formulario">
      <a class="btn" href="<?= url('comunicados') ?>">Cancelar</a>
      <button type="submit" class="btn btn-primario">Publicar comunicado</button>
    </div>
  </form>
</section>
<script>
  var alcance = document.getElementById('selAlcance');
  alcance.addEventListener('change', function () {
    document.getElementById('campoPrograma').style.display =
      alcance.value === 'programa' ? 'block' : 'none';
  });
</script>
