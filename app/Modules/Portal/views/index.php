<?php use App\Core\Auth; $usuario = Auth::usuario(); ?>
<section class="consulta-inicio" aria-labelledby="consulta-titulo">
  <nav class="consulta-notificaciones" aria-label="Notificaciones">
    <?php foreach ($notificaciones as $notificacion): ?>
      <button type="button" class="consulta-notificacion" id="notif-<?= e($notificacion['icono']) ?>" data-notification data-href="<?= url($notificacion['ruta']) ?>" data-label="<?= e($notificacion['etiqueta']) ?>" data-count="<?= (int) $notificacion['cantidad'] ?>" data-description="<?= e($notificacion['detalle']) ?>" aria-expanded="false"
         aria-label="<?= e($notificacion['etiqueta']) ?>: <?= (int) $notificacion['cantidad'] ?>"
         title="<?= e($notificacion['etiqueta']) ?>">
        <?= icono($notificacion['icono'], 19) ?>
        <?php if ($notificacion['cantidad'] > 0): ?><span class="consulta-contador"><?= (int) $notificacion['cantidad'] ?></span><?php endif; ?>
      </button>
    <?php endforeach; ?>
  </nav>

  <div class="consulta-hero">
    <p class="consulta-saludo">Hola, <?= e(explode(' ', trim($usuario['nombre']))[0] ?? $usuario['nombre']) ?></p>
    <h1 id="consulta-titulo">¿Qué necesitas resolver?</h1>
    <p class="consulta-intro">Consulta tus solicitudes, tus reuniones o encuentra el trámite que necesitas.</p>

    <form class="consulta-form" method="post" action="<?= url('consulta') ?>" data-consulta-form>
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label class="sr-only" for="consulta-texto">Escribe tu consulta</label>
      <textarea id="consulta-texto" name="consulta" rows="3" minlength="4" maxlength="1200" required
                placeholder="Por ejemplo: ¿cómo solicito una constancia?, ver mis reuniones, ¿dónde justifico una falta?"><?= e($consulta) ?></textarea>
      <div class="consulta-form-pie">
        <span>Enter para nueva línea · Ctrl + Enter para consultar</span>
        <button type="submit" class="consulta-boton">
          <span>Consultar</span><?= icono('flecha-der', 17) ?>
        </button>
      </div>
      <?php if ($iaExterna): ?>
        <label class="consulta-ia-opcion"><input type="checkbox" name="usar_ia" value="1" <?= $iaRestantes > 0 ? 'checked' : 'disabled' ?>>
          <span>Usar asistencia de IA. Mi pregunta (no mis registros) se envía a los servicios Jev y Gemini; no incluiré datos personales ni confidenciales.
          <?php if ($iaRestantes > 0): ?>Te quedan <?= (int) $iaRestantes ?> de <?= (int) $iaLimite ?> consultas asistidas hoy.<?php else: ?>Alcanzaste tu límite de <?= (int) $iaLimite ?> consultas asistidas de hoy; la consulta local sigue disponible.<?php endif; ?></span></label>
      <?php endif; ?>
    </form>
    <p class="ayuda-form">Los registros se consultan aquí, con tus permisos y sin enviar resultados a una IA externa. Consultar no guarda cambios.</p>

    <details class="consulta-ejemplos-ayuda"><summary>Ideas para empezar</summary><div class="consulta-ejemplos">
      <?php foreach (['Ver mis solicitudes','Ver mis reuniones','Necesito una constancia de estudios'] as $ejemplo): ?><button type="button" data-example="<?= e($ejemplo) ?>"><?= e($ejemplo) ?></button><?php endforeach; ?>
    </div></details>
    <?php if ($errorConsulta): ?>
      <p class="consulta-error" role="alert"><?= e($errorConsulta) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($resumen && !$consultado): include __DIR__ . '/_resumen.php'; endif; ?>

  <?php if ($resultado && ($resultado['tipo']??'')==='datos'): ?>
    <section class="consulta-respuesta" aria-labelledby="respuesta-titulo"><h2 id="respuesta-titulo"><?= e($resultado['titulo']) ?></h2><p><?= e($resultado['explicacion']) ?></p>
    <ul><?php foreach($resultado['items'] as $item): ?><li style="margin-block:16px"><a href="<?= url($item['ruta']) ?>"><?= e($item['titulo']) ?></a><br><span><?= e($item['detalle']) ?></span></li><?php endforeach; ?></ul><small>Fuente: registros autorizados de Traza FI · sin historial compartido.</small></section>
  <?php elseif ($resultado): ?>
    <section class="consulta-respuesta" aria-labelledby="respuesta-titulo">
      <div class="consulta-respuesta-cabecera">
        <span class="consulta-respuesta-icono"><?= icono('flujo', 20) ?></span>
        <div>
          <p class="consulta-sobrelinea">Formulario recomendado</p>
          <h2 id="respuesta-titulo"><?= e($resultado['titulo']) ?></h2>
        </div>
      </div>

      <p class="consulta-explicacion"><?= e($resultado['explicacion']) ?></p>
      <div class="consulta-destino">
        <strong><?= e($resultado['depto']) ?></strong>
        <span><?= e($resultado['funcion']) ?></span>
      </div>

      <form class="consulta-ticket" method="post" action="<?= url('tickets') ?>" enctype="multipart/form-data" data-draft="consulta">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="para_type[]" value="departamento">
        <details class="ux-disclosure"><summary>Elegir otra área</summary><label>Área que atenderá tu solicitud<select name="para_id[]" id="consulta-area"><?php foreach ($departamentos as $departamento): ?><option value="<?= (int) $departamento['id'] ?>" <?= (int)$departamento['id']===(int)$resultado['depto_id']?'selected':'' ?>><?= e($departamento['nombre']) ?></option><?php endforeach; ?></select></label></details>

        <label>Asunto
          <input type="text" name="asunto" required maxlength="180" value="<?= e($resultado['asunto']) ?>">
        </label>
        <label>Descripción
          <textarea name="descripcion" rows="5" required><?= e($consulta) ?></textarea>
        </label>
        <div class="consulta-ticket-fila">
          <label>Prioridad
            <select name="prioridad">
              <option value="baja">Baja</option>
              <option value="media" selected>Media</option>
              <option value="alta">Alta</option>
            </select>
          </label>
          <label>Adjuntar evidencia <span class="opcional">Opcional</span>
            <input type="file" name="archivos[]" multiple>
          </label>
        </div>
        <div class="consulta-ticket-acciones">
          <span id="consulta-area-resumen">Se enviará a <?= e($resultado['depto']) ?>.</span>
          <button type="submit" class="consulta-boton"><?= icono('enviar', 16) ?> Enviar solicitud</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($limitado): ?><p class="ayuda-form" role="status">Se alcanzó el límite de consultas asistidas; esta respuesta se resolvió con el motor local.</p><?php endif; ?>
  <?php if ($enlaces): ?>
    <section class="consulta-enlaces" aria-labelledby="enlaces-titulo">
      <h2 id="enlaces-titulo">Dónde consultarlo</h2>
      <ol>
        <?php foreach ($enlaces as $enlace): ?>
          <li>
            <span class="consulta-enlace-ruta"><?= e($enlace['modulo']) ?> › <?= e(url($enlace['ruta'])) ?></span>
            <a class="consulta-enlace-titulo" href="<?= url($enlace['ruta']) ?>"><?= e($enlace['titulo']) ?></a>
            <?php if ($enlace['descripcion'] !== ''): ?><p><?= e($enlace['descripcion']) ?></p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <small>Solo se muestran secciones a las que tu cuenta tiene acceso.</small>
    </section>
  <?php endif; ?>
</section>

<script>
(function () {
  const form = document.querySelector('[data-consulta-form]');
  const area = document.getElementById('consulta-texto');
  if (!form || !area) return;

  const resize = () => {
    area.style.height = 'auto';
    area.style.height = Math.min(area.scrollHeight, 220) + 'px';
  };
  area.addEventListener('input', resize);
  area.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault();
      form.requestSubmit();
    }
  });
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.querySelector('span').textContent = 'Analizando…';
  });
  resize();

  if (document.querySelector('.consulta-respuesta, .consulta-enlaces')) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelector('.consulta-respuesta, .consulta-enlaces').scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }
})();
</script>
