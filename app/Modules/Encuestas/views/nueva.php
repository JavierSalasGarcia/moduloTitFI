<?php use App\Core\Auth; ?>
<section class="card" style="max-width:720px">
  <form method="post" action="<?= url('encuestas') ?>" class="formulario" data-draft="encuesta">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

    <label>Pregunta / título <span class="req">*</span>
      <input type="text" name="titulo" required maxlength="180" placeholder="Ej. ¿Qué horario prefieres para los talleres?">
    </label>

    <label>Descripción (opcional)
      <textarea name="descripcion" rows="3" placeholder="Contexto de la votación…"></textarea>
    </label>

    <label>Opciones de respuesta (entre 2 y 10) <span class="req">*</span>
      <textarea name="opciones" rows="5" required data-options-editor placeholder="Una opción por línea"></textarea>
    </label>

    <p class="ayuda-form">El voto es anónimo: el sistema solo registra que ya votaste, no qué votaste.
    Cada voto genera un token público con el que su emisor puede verificarlo en el tablero.</p>

    <div class="acciones-formulario">
      <a class="btn" href="<?= url('encuestas') ?>">Cancelar</a>
      <button type="submit" class="btn btn-primario">Abrir encuesta</button>
    </div>
  </form>
</section>
