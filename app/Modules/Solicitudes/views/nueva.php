<?php use App\Core\Auth; ?>
<section class="tarjeta" style="max-width:720px">
  <header class="tarjeta-cabecera">
    <h2>Nueva solicitud interdepartamental</h2>
  </header>

  <?php if (!$canales): ?>
    <p class="texto-suave">Tu departamento no cuenta con canales autorizados de interacción.
    Los canales se definen en la matriz de relaciones del Manual de Organización;
    solicita a la Dirección la apertura de uno nuevo.</p>
  <?php else: ?>
    <form method="post" action="<?= url('solicitudes') ?>" class="formulario" data-draft="entre-areas">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

      <label>Departamento destino (solo canales autorizados)
        <select name="destino" required id="selDestino">
          <option value="" disabled selected>Selecciona un departamento…</option>
          <?php foreach ($canales as $c): ?>
            <option value="<?= (int) $c['id'] ?>" data-desc="<?= e($c['descripcion']) ?>"><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="texto-suave" id="descCanal" style="min-height:1.2em"></p>

      <label>Asunto
        <input type="text" name="asunto" required maxlength="180" placeholder="Ej. Corrección de acta de examen — grupo 03">
      </label>

      <label>Descripción del trámite
        <textarea name="descripcion" required rows="5" placeholder="Detalla la información que compartes o requieres: números de cuenta, periodos, documentos anexos…"></textarea>
      </label>

      <label>Prioridad
        <select name="prioridad">
          <option value="baja">Baja</option>
          <option value="media" selected>Media</option>
          <option value="alta">Alta</option>
        </select>
      </label>

      <div class="acciones-formulario">
        <a class="btn" href="<?= url('solicitudes') ?>">Cancelar</a>
        <button type="submit" class="btn btn-primario"><?= icono('enviar', 15) ?> Enviar solicitud</button>
      </div>
    </form>
    <script>
      var sel = document.getElementById('selDestino');
      sel.addEventListener('change', function () {
        document.getElementById('descCanal').textContent =
          'Canal: ' + (sel.selectedOptions[0].dataset.desc || '');
      });
    </script>
  <?php endif; ?>
</section>
