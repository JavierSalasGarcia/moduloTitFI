<?php use App\Core\Auth; ?>
<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:18px;align-items:start">
  <section class="tarjeta">
    <div class="tarjeta-cabecera">
      <div>
        <h2><?= icono('lista', 18) ?> Expediente <?= e($folio) ?></h2>
        <p class="texto-secundario"><?= e($oficina['nombre']) ?> · acceso restringido</p>
      </div>
      <span class="estado estado-<?= e($expediente['estado']) ?>"><?= e(str_replace('_', ' ', $expediente['estado'])) ?></span>
    </div>

    <?php if ($puedeEditar): ?>
      <form class="formulario" method="post" enctype="multipart/form-data" action="<?= url('fila/' . $oficina['ruta'] . '/reg.php?' . rawurlencode($folio)) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <label>Asunto
          <input type="text" name="asunto" maxlength="500" required value="<?= e($expediente['asunto']) ?>">
        </label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <label>Fecha
            <input type="date" name="fecha" required value="<?= e($expediente['fecha']) ?>">
          </label>
          <label>Estado
            <select name="estado" required>
              <?php foreach (['abierto'=>'Abierto','en_seguimiento'=>'En seguimiento','en_espera'=>'En espera','atendido'=>'Atendido','cerrado'=>'Cerrado'] as $valor=>$etiqueta): ?>
                <option value="<?= e($valor) ?>" <?= $expediente['estado']===$valor?'selected':'' ?>><?= e($etiqueta) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <label>Persona(s) del caso
          <textarea name="personas_caso" rows="4" maxlength="5000" placeholder="Nombres y relación con el asunto"><?= e($expediente['personas_caso']) ?></textarea>
        </label>
        <label>Personas con acceso
          <textarea name="correos_acceso" rows="5" required placeholder="correo@uaemex.mx, uno por línea"><?php foreach ($accesos as $acceso): ?><?= e($acceso['email']) . "\n" ?><?php endforeach; ?></textarea>
          <span class="ayuda-form">Solo una cuenta logueada con uno de estos correos podrá consultar el expediente y descargar sus archivos. Tu propio correo se conserva automáticamente.</span>
        </label>
        <label>Agregar archivos adjuntos
          <input type="file" name="archivos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx,.pptx,.zip">
          <span class="ayuda-form">Cada archivo puede pesar hasta <?= e((string) round(\App\Core\App::config('archivos.max_bytes') / 1048576)) ?> MB.</span>
        </label>
        <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar expediente</button></div>
      </form>
    <?php else: ?>
      <dl style="display:grid;grid-template-columns:150px 1fr;gap:10px 16px;margin:0">
        <dt><strong>Asunto</strong></dt><dd><?= e($expediente['asunto'] ?: 'Pendiente de registrar') ?></dd>
        <dt><strong>Fecha</strong></dt><dd><?= e($expediente['fecha']) ?></dd>
        <dt><strong>Estado</strong></dt><dd><?= e(str_replace('_', ' ', $expediente['estado'])) ?></dd>
        <dt><strong>Personas del caso</strong></dt><dd style="white-space:pre-wrap"><?= e($expediente['personas_caso'] ?: 'Sin registrar') ?></dd>
      </dl>
    <?php endif; ?>
  </section>

  <aside style="display:flex;flex-direction:column;gap:18px">
    <section class="tarjeta">
      <h2 style="font-size:16px">Personas con acceso</h2>
      <ul style="padding-left:18px;margin-bottom:0">
        <?php foreach ($accesos as $acceso): ?>
          <li><strong><?= e($acceso['nombre'] ?: $acceso['email']) ?></strong><?php if ($acceso['nombre']): ?><br><span class="texto-secundario"><?= e($acceso['email']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="tarjeta">
      <h2 style="font-size:16px">Archivos adjuntos</h2>
      <?php if (!$archivos): ?><p class="texto-secundario">Todavía no hay archivos.</p><?php endif; ?>
      <ul style="padding-left:18px;margin-bottom:0">
        <?php foreach ($archivos as $archivo): ?>
          <li><a href="<?= url_archivo('fila/' . $oficina['ruta'] . '/archivo/' . (int) $archivo['id']) ?>"><?= e($archivo['nombre_original']) ?></a><br><span class="texto-secundario"><?= e($archivo['autor']) ?> · <?= e(fecha_corta($archivo['creado_en'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </aside>
</div>
<style>@media(max-width:800px){#contenido-principal>div[style*="grid-template-columns"]{grid-template-columns:1fr!important}}</style>
