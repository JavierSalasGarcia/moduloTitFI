<?php
/** @var ?array $s @var array $v @var ?string $error @var array $firmantes @var bool $docx @var array $mias @var array $plantillas @var ?array $plantilla @var array $errores @var ?string $aviso @var int $masRenglones */
use App\Core\Auth;
use App\Modules\Constancias\Services\Plantillas;
use App\Modules\Constancias\Services\Solicitudes;

include __DIR__ . '/_estilos.php';
$porDepto = [];
foreach ($firmantes as $f) {
    $porDepto[$f['departamento']][] = $f;
}
$accion = $s ? 'constancias/solicitud/' . (int) $s['id'] : 'constancias/solicitar';
$modo = $docx && !$plantilla ? $v['modo'] : 'texto';
$armado = $plantilla && $v['armado'];
$paso1 = $plantilla && !$armado;
$campo = $v['campo'];
/** Un campo de la plantilla según su tipo. */
$pintarCampo = static function (array $c, array $campo, array $errores, bool $requerido, int $masRenglones): void {
    $k = $c['clave'];
    $id = 'campo-' . $k;
    $err = $errores[$k] ?? null;
    $ayuda = $c['ayuda'] !== '' ? '<span class="const-ayuda">' . e($c['ayuda']) . '</span>' : '';
    $marca = $c['obligatorio'] ? '' : ' <small class="const-opcional">(opcional)</small>';
    $errHtml = $err !== null ? '<span class="const-error-campo" id="' . e($id) . '-error">' . e($err) . '</span>' : '';
    $aria = $err !== null ? ' aria-invalid="true" aria-describedby="' . e($id) . '-error"' : '';
    $req = $requerido && $c['obligatorio'] ? ' required' : '';
    if ($c['tipo'] === 'tabla') {
        $filas = is_array($campo[$k] ?? null) ? array_values($campo[$k]) : [];
        $n = min(Plantillas::MAX_RENGLONES, max(3, count($filas)) + $masRenglones);
        echo '<fieldset class="const-campo-tabla' . ($err !== null ? ' con-error' : '') . '" data-tabla="' . e($k) . '"><legend>' . e($c['etiqueta']) . $marca . '</legend>' . $ayuda . $errHtml;
        echo '<div class="const-tabla-envoltura"><table class="const-captura"><thead><tr><th class="const-n" aria-label="Renglón">#</th>';
        foreach ($c['columnas'] as $col) {
            echo '<th scope="col">' . e($col) . '</th>';
        }
        echo '</tr></thead><tbody>';
        for ($i = 0; $i < $n; $i++) {
            echo '<tr><td class="const-n">' . ($i + 1) . '</td>';
            foreach ($c['columnas'] as $j => $col) {
                $val = is_array($filas[$i] ?? null) ? (string) ($filas[$i][$j] ?? '') : '';
                echo '<td><input type="text" name="campo[' . e($k) . '][' . $i . '][' . $j . ']" value="' . e($val) . '" maxlength="300" aria-label="' . e($col . ', renglón ' . ($i + 1)) . '"></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<button class="btn btn-mini const-mas" type="submit" name="paso" value="renglones" formnovalidate>Agregar renglones</button></fieldset>';
        return;
    }
    $val = is_string($campo[$k] ?? null) ? (string) $campo[$k] : '';
    echo '<label for="' . e($id) . '"' . ($err !== null ? ' class="con-error"' : '') . '>' . e($c['etiqueta']) . $marca;
    if ($c['tipo'] === 'texto_largo') {
        echo '<textarea id="' . e($id) . '" name="campo[' . e($k) . ']" rows="4" maxlength="4000"' . $req . $aria . '>' . e($val) . '</textarea>';
    } elseif ($c['tipo'] === 'fecha') {
        echo '<input id="' . e($id) . '" type="date" name="campo[' . e($k) . ']" value="' . e($val) . '"' . $req . $aria . '>';
    } elseif ($c['tipo'] === 'numero') {
        echo '<input id="' . e($id) . '" type="text" inputmode="decimal" name="campo[' . e($k) . ']" value="' . e($val) . '" maxlength="20"' . $req . $aria . '>';
    } else {
        echo '<input id="' . e($id) . '" type="text" name="campo[' . e($k) . ']" value="' . e($val) . '" maxlength="300"' . $req . $aria . '>';
    }
    echo $ayuda . $errHtml . '</label>';
};
?>
<div class="const-pila">
  <?php if (!$s && $plantillas): ?>
  <section class="tarjeta const-plantilla-sel">
    <form method="get" action="<?= e(url('constancias/solicitar')) ?>" class="const-sel-form" id="const-sel-form">
      <label for="const-plantilla">Plantilla
        <select id="const-plantilla" name="plantilla">
          <option value="">En blanco (escribir el texto o subir un Word)</option>
          <?php foreach ($plantillas as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $plantilla && $plantilla['id'] === $p['id'] ? ' selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit" id="const-sel-usar">Usar esta plantilla</button>
    </form>
    <p class="const-ayuda"><?= $plantilla ? e($plantilla['descripcion'] !== '' ? $plantilla['descripcion'] : 'Llena los datos que pide y revisa el texto que resulta.') : 'Elige una plantilla para llenar solo los datos que cambian, o empieza en blanco.' ?></p>
  </section>
  <?php endif; ?>
  <section class="tarjeta">
    <?php if ($s && !empty($s['plantilla_nombre'])): ?><p class="const-ayuda" style="margin-top:0">Hecha con la plantilla «<?= e($s['plantilla_nombre']) ?>». Aquí corriges el texto que resultó.</p><?php endif; ?>
    <?php if ($aviso !== null && $error === null): ?><div class="const-nota" role="status" style="margin-bottom:12px"><?= e($aviso) ?></div><?php endif; ?>
    <?php if ($s && $s['estado'] === 'devuelta' && $s['observacion'] !== ''): ?>
      <div class="const-nota" style="margin-bottom:12px"><strong><?= e($s['firmante_nombre']) ?> la devolvió con esta observación:</strong><br><?= nl2br(e($s['observacion'])) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?><div class="const-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="formulario const-form" method="post" action="<?= e(url($accion)) ?>" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <?php if ($plantilla): ?>
        <input type="hidden" name="plantilla" value="<?= (int) $plantilla['id'] ?>">
        <?php if ($armado): ?><input type="hidden" name="armado" value="1"><?php endif; ?>
        <!-- Al pulsar Enter se usa el primer botón: que sea el paso que corresponde, no «Agregar renglones». -->
        <button type="submit" name="paso" value="<?= $armado ? 'vista' : 'armar' ?>" class="const-oculto" tabindex="-1" aria-hidden="true">Continuar</button>
      <?php endif; ?>
      <?php if ($paso1): ?>
      <fieldset class="const-datos-plantilla">
        <legend>Datos de la constancia</legend>
        <p class="const-ayuda" style="margin-top:0">Lo que pide la plantilla «<?= e($plantilla['nombre']) ?>». Al continuar verás el texto completo y podrás corregirlo.</p>
        <?php foreach ($plantilla['campos'] as $c) { $pintarCampo($c, $campo, $errores, true, $masRenglones); } ?>
        <?php if (!$plantilla['campos']): ?><p class="const-ayuda">Esta plantilla no pide datos: continúa para ver el texto.</p><?php endif; ?>
      </fieldset>
      <details class="const-plantilla-texto">
        <summary>Ver el texto de la plantilla</summary>
        <p class="const-ayuda"><strong>Dirigida a:</strong> <?= nl2br(e($plantilla['dirigido_a'])) ?> · <strong>Asunto:</strong> <?= e($plantilla['asunto']) ?></p>
        <pre class="const-pre"><?= e($plantilla['cuerpo']) ?></pre>
      </details>
      <?php else: ?>
      <div class="const-doble">
        <label>Dirigida a
          <textarea name="dirigido_a" rows="2" maxlength="600" required><?= e($v['dirigido_a']) ?></textarea>
          <span class="const-ayuda">Por omisión «A QUIEN CORRESPONDA». Para una persona, escribe su nombre y en otro renglón su cargo.</span>
        </label>
        <label>Asunto
          <input type="text" name="asunto" value="<?= e($v['asunto']) ?>" maxlength="160" required>
        </label>
      </div>

      <?php if (!$plantilla): ?>
      <fieldset class="const-modo">
        <legend style="font-weight:600;margin-bottom:6px">Cuerpo de la constancia</legend>
        <label><input type="radio" name="modo" value="texto"<?= $modo === 'texto' ? ' checked' : '' ?>> Escribir el texto</label>
        <?php if ($docx): ?><label><input type="radio" name="modo" value="docx"<?= $modo === 'docx' ? ' checked' : '' ?>> Subir un archivo de Word</label><?php endif; ?>
      </fieldset>
      <?php if (!$docx): ?><p class="const-nota">Este servidor no puede leer archivos de Word por ahora; escribe o pega el texto en el campo.</p><?php endif; ?>
      <?php endif; ?>

      <div id="const-por-texto"<?= $modo === 'docx' ? ' hidden' : '' ?>>
        <label>Texto
          <textarea class="const-texto" name="texto" maxlength="20000" placeholder="Quien suscribe, … hace constar que …"><?= e($v['texto']) ?></textarea>
        </label>
        <?php if ($plantilla): ?><p class="const-ayuda">Puedes corregir el texto. {fecha_letras}, {firmante}, {cargo_firmante} y {suscribe} se llenan solos en el PDF con la fecha de expedición y los datos de quien firma.</p><?php endif; ?>
        <p class="const-ayuda">Separa los párrafos con una línea en blanco. Para <strong>negritas</strong> escribe <code>**así**</code>. Para una tabla, pega los renglones copiados de Excel o Word, o sepáralos con <code>|</code>: <code>| Nombre | Horas |</code>. Si la primera fila va en negritas o le sigue <code>|---|---|</code>, se toma como encabezado.</p>
      </div>
      <?php if ($docx): ?>
      <div id="const-por-docx"<?= $modo === 'docx' ? '' : ' hidden' ?>>
        <label>Archivo de Word (.docx, hasta 2 MB)
          <input type="file" name="archivo" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        </label>
        <p class="const-ayuda">Solo el cuerpo: sin membrete, folio ni firma, que la plataforma agrega. Se conservan párrafos, negritas, alineación y tablas; las imágenes y los estilos especiales se omiten y te avisamos.<?php if ($s && $s['origen'] === 'docx' && $s['archivo'] !== ''): ?> Si no eliges otro archivo, se conserva el de «<?= e($s['archivo']) ?>».<?php endif; ?></p>
      </div>
      <?php endif; ?>
      <?php if ($armado): ?>
      <details class="const-plantilla-texto"<?= $errores ? ' open' : '' ?>>
        <summary>Datos de la plantilla «<?= e($plantilla['nombre']) ?>»</summary>
        <?php foreach ($plantilla['campos'] as $c) { $pintarCampo($c, $campo, $errores, false, $masRenglones); } ?>
        <button class="btn" type="submit" name="paso" value="armar" onclick="return confirm('Se volverá a llenar el texto, el asunto y el destinatario con estos datos; se perderán tus correcciones. ¿Continuar?')">Volver a llenar el texto con estos datos</button>
      </details>
      <?php endif; ?>
      <?php endif; ?>

      <div class="const-doble">
        <label>Se extiende a (nombre)
          <input type="text" name="persona_nombre" value="<?= e($v['persona_nombre']) ?>" maxlength="160" autocomplete="off">
        </label>
        <label>Correo de esa persona (opcional)
          <input type="email" name="persona_email" value="<?= e($v['persona_email']) ?>" maxlength="190" autocomplete="off">
          <span class="const-ayuda">Con su correo, la constancia firmada le aparece en su cuenta y le avisamos.</span>
        </label>
      </div>

      <label>Firma
        <select name="firmante" required>
          <option value="">Elige a quien firma…</option>
          <?php foreach ($porDepto as $depto => $lista): ?>
            <optgroup label="<?= e($depto) ?>">
              <?php foreach ($lista as $f): ?><option value="<?= e($f['clave']) ?>"<?= $v['firmante'] === $f['clave'] ? ' selected' : '' ?>><?= e($f['nombre'] . ' — ' . $f['cargo']) ?></option><?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <span class="const-ayuda">Titulares y coordinaciones vigentes. La constancia queda archivada en el departamento de quien firma, con su folio.</span>
      </label>
      <?php if (!$firmantes): ?><p class="const-nota">Aún no hay titulares ni coordinaciones registrados. Pide a Administración que los capture en «Estructura académica».</p><?php endif; ?>

      <div class="const-acciones">
        <?php if ($paso1): ?>
          <button class="btn btn-primario" type="submit" name="paso" value="armar">Continuar</button>
          <a class="btn" href="<?= e(url('constancias/solicitar')) ?>">Empezar en blanco</a>
        <?php else: ?>
          <button class="btn btn-primario" type="submit"<?= $plantilla ? ' name="paso" value="vista"' : '' ?>>Ver la vista previa</button>
        <?php endif; ?>
        <?php if ($s): ?><a class="btn" href="<?= e(url('constancias/solicitud/' . (int) $s['id'])) ?>">Volver sin cambios</a><?php endif; ?>
      </div>
      <p class="const-ayuda"><?= $paso1 ? 'Después revisas el texto, ves el PDF y al final la envías a firma.' : 'Aún no se envía: primero revisas el PDF y después la envías a firma.' ?></p>
    </form>
  </section>

  <?php if ($mias): ?>
  <section class="tarjeta">
    <h2 style="margin-top:0;font-size:18px">Mis solicitudes</h2>
    <table class="const-lista">
      <thead><tr><th>Constancia</th><th>Firma</th><th>Folio</th><th>Estado</th><th><span class="sr-only">Abrir</span></th></tr></thead>
      <tbody>
      <?php foreach ($mias as $m): ?>
        <tr>
          <td><strong><?= e($m['persona_nombre'] !== '' ? $m['persona_nombre'] : $m['asunto']) ?></strong><br><small><?= e(fecha_corta((string) $m['creado_en'])) ?></small></td>
          <td data-et="Firma"><?= e($m['firmante_nombre']) ?><br><small><?= e($m['departamento']) ?></small></td>
          <td data-et="Folio"><?= e($m['folio'] ?: '—') ?></td>
          <td><span class="const-estado <?= e($m['estado']) ?>"><?= e(Solicitudes::ESTADOS[$m['estado']] ?? $m['estado']) ?></span></td>
          <td class="const-acc"><a class="btn btn-mini" href="<?= e(url('constancias/solicitud/' . (int) $m['id'])) ?>">Abrir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>
</div>
<script>
(function () {
  // Plantilla: al elegirla se recarga el formulario con sus campos.
  var sel = document.getElementById('const-plantilla');
  var usar = document.getElementById('const-sel-usar');
  if (sel && usar) {
    usar.hidden = true;
    sel.addEventListener('change', function () { document.getElementById('const-sel-form').submit(); });
  }
  // Tablas de la plantilla: agregar un renglón sin recargar.
  document.querySelectorAll('.const-campo-tabla').forEach(function (fs) {
    var btn = fs.querySelector('.const-mas');
    var cuerpo = fs.querySelector('tbody');
    if (!btn || !cuerpo) return;
    btn.textContent = 'Agregar un renglón';
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      var filas = cuerpo.querySelectorAll('tr');
      if (filas.length >= <?= Plantillas::MAX_RENGLONES ?>) return;
      var nueva = filas[filas.length - 1].cloneNode(true);
      var i = filas.length;
      nueva.querySelector('.const-n').textContent = String(i + 1);
      nueva.querySelectorAll('input').forEach(function (inp) {
        inp.value = '';
        inp.name = inp.name.replace(/\]\[\d+\]\[/, '][' + i + '][');
        inp.setAttribute('aria-label', inp.getAttribute('aria-label').replace(/\d+$/, String(i + 1)));
      });
      cuerpo.appendChild(nueva);
      nueva.querySelector('input').focus();
    });
  });
  var radios = document.querySelectorAll('.const-modo input[name="modo"]');
  var texto = document.getElementById('const-por-texto');
  var docx = document.getElementById('const-por-docx');
  radios.forEach(function (r) {
    r.addEventListener('change', function () {
      var w = r.value === 'docx' && r.checked;
      if (texto) texto.hidden = w;
      if (docx) docx.hidden = !w;
    });
  });
})();
</script>
