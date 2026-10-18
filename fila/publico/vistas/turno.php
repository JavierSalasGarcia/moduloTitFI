<?php
$esCita=($datos['tipo']??($_GET['tipo']??''))==='cita';
$horaElegida=(string)($datos['hora']??'');
$horaDisponible=false;
$correoRecordado=(string)($correo_recordado??'');
$identidadRecordada=$identidad_recordada??null;
$usuarioCorreo=u_email_local($datos['email']??'',(string)$dep['dominio_correo']);
foreach ($huecos as $dia) { foreach ($dia['opciones'] as $opcion) {
    if ($opcion->format('Y-m-d H:i')===$horaElegida) $horaDisponible=true;
} }
?>
<section class="tarjeta angosta acceso-fila">
  <p class="sobrelinea"><?= u_escapar($dep['nombre']) ?></p>

  <?php if (!$esCita): ?>
    <h1><?= !empty($identidadRecordada['registrado'])?'Hola, '.u_escapar(u_nombre_corto((string)$identidadRecordada['nombre_real'])):'Toma un turno' ?></h1>
    <?php if ($estado['abierta']): ?>
      <p class="resumen-fila">Disponible<?= $estado['proximo_hueco'] ? ' · aprox. '.u_hora($estado['proximo_hueco']) : '' ?></p>
    <?php else: ?>
      <p class="estado aviso"><?= u_escapar($estado['motivo_cierre']) ?></p>
    <?php endif; ?>
    <?php if ($error): ?><p class="error" role="alert"><?= u_escapar($error) ?></p><?php endif; ?>

    <?php if ($estado['abierta']): ?>
    <form method="post" action="<?= ruta($dep['clave'].'/turno') ?>" class="formulario formulario-turno" data-envio-unico>
      <?= campo_csrf() ?>
      <input type="hidden" name="tipo" value="turno">
      <?php if ($correoRecordado!==''): ?>
      <div class="usuario-recordado">
        <?php if (empty($identidadRecordada['registrado'])): ?><strong><?= u_escapar($correoRecordado) ?></strong><?php endif; ?>
        <?php if (!empty($identidadRecordada['registrado'])): ?><span><?= u_escapar($correoRecordado) ?></span><?php endif; ?>
        <a href="<?= ruta($dep['clave'].'/turno?olvidar=1') ?>">No soy yo</a>
      </div>
      <input name="email" type="hidden" value="<?= u_escapar($correoRecordado) ?>">
      <?php else: ?>
      <label>Correo institucional <span class="meta">(sin @<?= u_escapar($dep['dominio_correo']) ?>)</span>
        <span class="correo-institucional">
          <input name="email" type="text" inputmode="email" autocomplete="username" enterkeyhint="go"
                 data-correo-local data-dominio="<?= u_escapar($dep['dominio_correo']) ?>"
                 required autofocus placeholder="usuario" value="<?= u_escapar($usuarioCorreo) ?>">
          <strong>@<?= u_escapar($dep['dominio_correo']) ?></strong>
        </span>
      </label>
      <?php endif; ?>
      <button class="grande boton-bloque">Obtener turno</button>
    </form>
    <?php endif; ?>

    <div class="acciones-minimas">
      <a class="boton secundario boton-bloque" href="<?= ruta($dep['clave'].'/turno?tipo=cita') ?>">Agendar reunión</a>
      <a href="<?= ruta($dep['clave'].'/fila') ?>">Ver fila</a>
    </div>
  <?php else: ?>
    <a class="volver" href="<?= ruta($dep['clave'].'/turno') ?>">← Turno rápido</a>
    <h1>Solicitar reunión</h1>
    <p class="meta">Completa los datos. Confirmarás por correo.</p>
    <?php if ($error): ?><p class="error" role="alert"><?= u_escapar($error) ?></p><?php endif; ?>

    <form method="post" action="<?= ruta($dep['clave'].'/confirmar') ?>" enctype="multipart/form-data"
          class="formulario" id="forma-cita" data-identidad-url="<?= u_escapar(ruta($dep['clave'].'/identidad')) ?>"
          data-identidad-csrf="<?= u_escapar(csrf()) ?>" data-envio-unico>
      <?= campo_csrf() ?>
      <input type="hidden" name="tipo" id="tipo" value="cita">

      <label>Correo institucional
        <input name="email" type="email" autocomplete="email" inputmode="email" required
               placeholder="usuario@<?= u_escapar($dep['dominio_correo']) ?>"
               value="<?= u_escapar($datos['email']??'') ?>">
      </label>
      <label>Nombre
        <input name="nombre" autocomplete="name" maxlength="120" required
               value="<?= u_escapar($datos['nombre']??'') ?>">
      </label>
      <p class="meta" data-identidad-estado role="status">Usaremos el nombre asociado a tu correo.</p>
      <label>Asunto
        <input name="asunto" required maxlength="200" value="<?= u_escapar($datos['asunto']??'') ?>">
      </label>

      <?php require __DIR__.'/modalidad_form.php'; ?>
      <div class="en-linea">
        <label>Duración
          <select name="minutos_cita" id="minutos_cita">
            <?php foreach ($minutos_cita as $opcion): ?>
            <option value="<?= $opcion ?>" <?= (int)($datos['minutos_cita']??$minutos_cita[0])===$opcion?'selected':'' ?>><?= $opcion ?> min</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Día y hora
          <select name="hora" id="hora" required>
            <?php if ($horaElegida!==''&&!$horaDisponible): ?>
            <option value="" selected disabled>Elige otra hora</option>
            <?php endif; ?>
            <?php foreach ($huecos as $dia): ?>
            <optgroup label="<?= u_escapar(u_fecha_larga($dia['dia']->setTime(0,0))) ?>">
              <?php foreach ($dia['opciones'] as $opcion): ?>
              <option value="<?= $opcion->format('Y-m-d H:i') ?>" <?= $opcion->format('Y-m-d H:i')===$horaElegida?'selected':'' ?>><?= u_hora($opcion) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Descripción
        <textarea name="descripcion" rows="5" maxlength="4000" required><?= u_escapar($datos['descripcion']??'') ?></textarea>
      </label>
      <label>Archivos <span class="meta">(opcional, máximo 5)</span>
        <input type="file" name="archivos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt">
      </label>
      <button class="grande boton-bloque">Continuar</button>
    </form>
  <?php endif; ?>
</section>

<script>
document.querySelectorAll('[data-correo-local]').forEach(input => {
  const suffix='@'+input.dataset.dominio.toLowerCase();
  const strip=()=>{if(input.value.toLowerCase().endsWith(suffix)) input.value=input.value.slice(0,-suffix.length);};
  strip(); input.addEventListener('input',strip); input.addEventListener('change',strip);
});
document.querySelectorAll('[data-envio-unico]').forEach(form => form.addEventListener('submit', function () {
  const button=this.querySelector('button[type="submit"],button:not([type])');
  if (!button || button.disabled) return;
  button.disabled=true; button.setAttribute('aria-busy','true'); button.textContent='Enviando…';
}));
</script>
<?php if ($esCita): ?>
<script>
document.getElementById('minutos_cita').addEventListener('change', async evento => {
  const respuesta=await fetch(location.pathname+'?tipo=cita&huecos='+evento.target.value,{headers:{Accept:'application/json'}});
  if (!respuesta.ok) return;
  const datos=await respuesta.json();
  document.getElementById('hora').innerHTML=datos.dias.map(d=>`<optgroup label="${d.etiqueta}">${d.opciones.map(o=>`<option value="${o.valor}">${o.hora}</option>`).join('')}</optgroup>`).join('');
});
</script>
<script src="<?= u_escapar(ruta('estatico/identidad.js')) ?>?v=<?= filemtime(__DIR__.'/../estatico/identidad.js') ?>"></script>
<?php endif; ?>
