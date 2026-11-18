<?php
use App\Core\Auth;
use App\Modules\Constancias\Services\Plantillas;
use App\Modules\Titulacion\Services\Constancias;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
?>
<style>
/* Plantillas para «Solicitar una constancia» (prefijo pl-). Móvil primero. */
.pl-cab{display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;justify-content:space-between;margin-bottom:6px}
.pl-cab h2{margin:0;font-size:18px;color:var(--verde-900)}
.pl-lista{width:100%;border-collapse:collapse;font-size:14px}
.pl-lista th{text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--gris-600);padding:8px 6px;border-bottom:1px solid var(--gris-100)}
.pl-lista td{padding:10px 6px;border-bottom:1px dashed var(--gris-100);vertical-align:top}
.pl-lista small{color:var(--gris-600)}
.pl-lista tr.inactiva td:first-child strong{color:var(--gris-600)}
.pl-acc{display:flex;flex-wrap:wrap;gap:6px}
.pl-acc form{margin:0}
.pl-ayuda{font-size:13px;color:var(--gris-600);line-height:1.5;margin:4px 0 0}
.pl-ayuda code,.pl-marc code{background:var(--gris-bg);padding:0 4px;border-radius:4px}
.pl-doble{display:grid;grid-template-columns:1fr;gap:0 16px}
.formulario textarea.pl-cuerpo{min-height:300px;font-family:inherit;line-height:1.5}
.pl-marcs{display:flex;flex-direction:column;gap:10px;margin:8px 0 14px}
.pl-marc{border:1px solid var(--gris-100);border-radius:var(--radio);padding:10px 12px;display:grid;grid-template-columns:1fr;gap:6px 12px;background:#fff}
.pl-marc>header{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-weight:700}
.pl-marc label{margin:0}
.pl-marc .pl-ob{display:flex;gap:8px;align-items:center;font-weight:600}
.pl-auto{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 0}
.pl-auto span{font-size:12px;background:var(--verde-050);color:var(--verde-900);border-radius:999px;padding:2px 10px}
.pl-error{border-left:4px solid var(--error);background:#fdecec;padding:10px 14px;border-radius:var(--radio);margin-bottom:12px}
.pl-aviso{border-left:4px solid var(--oro);background:var(--oro-050);padding:10px 14px;border-radius:var(--radio);margin-bottom:12px;font-size:14px}
.pl-invalido{color:var(--error);font-size:13px;font-weight:600;margin:6px 0 0}
.pl-hist{list-style:none;margin:0;padding:0;font-size:13px;color:var(--gris-600)}
.pl-hist li{padding:4px 0;border-top:1px dashed var(--gris-100)}
@media (min-width:720px){
  .pl-doble{grid-template-columns:1fr 1fr}
  .pl-marc{grid-template-columns:repeat(2,minmax(0,1fr))}
  .pl-marc>header,.pl-marc .pl-ancho{grid-column:1/-1}
}
@media (max-width:719px){
  .pl-lista thead{display:none}
  .pl-lista,.pl-lista tbody,.pl-lista tr,.pl-lista td{display:block;width:100%}
  .pl-lista tr{padding:10px 0;border-bottom:1px dashed var(--gris-100)}
  .pl-lista td{border:0;padding:2px 0}
  .pl-lista td[data-et]::before{content:attr(data-et) ': ';color:var(--gris-600);font-size:12px}
  .pl-lista td.pl-td-acc{padding-top:8px}
}
</style>
<?php if (isset($editor)):
    /** @var array $editor @var ?array $plantillaActual @var ?string $error @var ?string $aviso @var array $historialPlantilla */
    $d = $editor;
    $porClave = [];
    foreach ($d['campos'] as $c) {
        $porClave[$c['clave']] = $c;
    }
    $todos = Plantillas::marcadores($d['asunto'], $d['dirigido_a'], $d['cuerpo']);
    $autos = array_values(array_filter($todos, static fn(string $m): bool => isset(Plantillas::AUTOMATICOS[$m])));
    $invalidos = array_values(array_filter($todos, static fn(string $m): bool => !preg_match('/^[a-z0-9_]{2,40}$/D', $m)));
    $accion = $plantillaActual ? 'titulacion/constancias/plantillas/' . (int) $plantillaActual['id'] : 'titulacion/constancias/plantillas';
    $filaMarcador = static function (array $c): string {
        $k = $c['clave'];
        $n = 'def[' . $k . ']';
        $op = '';
        foreach (Plantillas::TIPOS as $t => $et) {
            $op .= '<option value="' . e($t) . '"' . ($c['tipo'] === $t ? ' selected' : '') . '>' . e($et) . '</option>';
        }
        return '<div class="pl-marc" data-clave="' . e($k) . '"><header><code>{' . e($k) . '}</code></header>'
            . '<input type="hidden" name="' . e($n) . '[_]" value="1">'
            . '<label>Pregunta que verá quien solicita <input type="text" name="' . e($n) . '[etiqueta]" maxlength="120" value="' . e($c['etiqueta']) . '"></label>'
            . '<label>Tipo de dato <select name="' . e($n) . '[tipo]" class="pl-tipo">' . $op . '</select></label>'
            . '<label class="pl-ancho">Ayuda (opcional) <input type="text" name="' . e($n) . '[ayuda]" maxlength="200" value="' . e($c['ayuda']) . '"></label>'
            . '<label class="pl-ancho pl-cols"' . ($c['tipo'] === 'tabla' ? '' : ' hidden') . '>Columnas de la tabla, separadas por | <input type="text" name="' . e($n) . '[columnas]" maxlength="500" value="' . e(implode(' | ', $c['columnas'])) . '" placeholder="Curso | Periodo | Horas"></label>'
            . '<label class="pl-ob"><input type="checkbox" name="' . e($n) . '[obligatorio]" value="1"' . ($c['obligatorio'] ? ' checked' : '') . '> Obligatorio</label></div>';
    };
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario"><a href="<?= url('titulacion/constancias/textos#plantillas') ?>">← Volver a las plantillas</a></p>
  <p class="texto-secundario">Plantilla para «Solicitar una constancia». Quien solicita la elige, llena los datos que piden los marcadores <code>{así}</code> y revisa el texto que resulta antes de enviarlo a firma.</p>
</div></div>
<section class="tarjeta">
  <?php if ($error !== null): ?><div class="pl-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if ($aviso !== null && $error === null): ?><div class="pl-aviso" role="status"><?= e($aviso) ?></div><?php endif; ?>
  <form class="formulario" method="post" action="<?= url($accion) ?>" id="pl-form">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <?php if ($plantillaActual): ?><input type="hidden" name="plantilla_id" value="<?= (int) $plantillaActual['id'] ?>"><?php endif; ?>
    <div class="pl-doble">
      <label>Nombre de la plantilla <input type="text" name="nombre" maxlength="120" required value="<?= e($d['nombre']) ?>" placeholder="Impartición de curso"></label>
      <label>Descripción breve <input type="text" name="descripcion" maxlength="255" value="<?= e($d['descripcion']) ?>" placeholder="Para quién o para qué sirve"></label>
      <label>Dirigida a <textarea name="dirigido_a" rows="2" maxlength="600"><?= e($d['dirigido_a']) ?></textarea>
        <span class="pl-ayuda">Por omisión «A QUIEN CORRESPONDA». Puede llevar marcadores, por ejemplo <code>{destinatario}</code>.</span></label>
      <label>Asunto <input type="text" name="asunto" maxlength="160" value="<?= e($d['asunto']) ?>"></label>
    </div>
    <label>Cuerpo
      <textarea class="pl-cuerpo" name="cuerpo" rows="14" id="pl-cuerpo" maxlength="20000" required placeholder="{suscribe}, {firmante}, {cargo_firmante} …, hace constar que **{nombre_persona}** …"><?= e($d['cuerpo']) ?></textarea>
    </label>
    <p class="pl-ayuda">Mismo formato que al solicitar: párrafos separados por una línea en blanco, <code>**negritas**</code> y tablas fijas con <code>|</code>. Escribe entre llaves lo que cambia en cada constancia, con minúsculas, números y guion bajo: <code>{nombre_curso}</code>. Una tabla que llena quien solicita va como un marcador solo en su renglón, de tipo «Tabla».</p>
    <p class="pl-ayuda">Se llenan solos (no se piden): <?php foreach (Plantillas::AUTOMATICOS as $m => $dsc): ?><code>{<?= e($m) ?>}</code> <?= e(mb_strtolower(mb_substr($dsc, 0, 1)) . mb_substr($dsc, 1)) ?>; <?php endforeach; ?></p>

    <h3 style="font-size:16px;margin:16px 0 4px;color:var(--verde-900)">Datos que se piden</h3>
    <p class="pl-ayuda" id="pl-resumen"><?= $d['campos'] ? 'Marcadores encontrados en el texto. Define cómo se piden.' : 'Aún no hay marcadores por llenar en el texto.' ?></p>
    <p class="pl-invalido" id="pl-invalidos"<?= $invalidos ? '' : ' hidden' ?>><?= $invalidos ? 'Nombre no válido: ' . e(implode(', ', array_map(static fn(string $m): string => '{' . $m . '}', $invalidos))) : '' ?></p>
    <div class="pl-auto" id="pl-autos"><?php foreach ($autos as $a): ?><span>{<?= e($a) ?>} se llena solo</span><?php endforeach; ?></div>
    <div class="pl-marcs" id="pl-marcs">
      <?php foreach ($d['campos'] as $c) { echo $filaMarcador($c); } ?>
    </div>
    <template id="pl-tpl"><?= $filaMarcador(['clave' => 'zz_clave', 'etiqueta' => '', 'tipo' => 'texto', 'obligatorio' => true, 'ayuda' => '', 'columnas' => []]) ?></template>
    <button class="btn btn-mini" type="submit" name="accion" value="detectar" formnovalidate id="pl-detectar">Actualizar la lista de marcadores</button>

    <div class="acciones-formulario" style="margin-top:16px">
      <button class="btn btn-primario" type="submit" name="accion" value="guardar">Guardar plantilla</button>
      <button class="btn" type="submit" formaction="<?= url('titulacion/constancias/plantillas/muestra') ?>" formtarget="_blank">Vista previa (PDF con datos de ejemplo)</button>
      <a class="btn" href="<?= url('titulacion/constancias/textos#plantillas') ?>">Cancelar</a>
    </div>
  </form>
</section>
<?php if ($historialPlantilla): $acc = ['creada' => 'Creada', 'editada' => 'Editada', 'duplicada' => 'Creada como copia', 'desactivada' => 'Desactivada', 'activada' => 'Activada']; ?>
<section class="tarjeta" style="margin-top:14px">
  <h2 style="margin-top:0;font-size:16px">Cambios recientes</h2>
  <ul class="pl-hist"><?php foreach ($historialPlantilla as $h): ?><li><strong><?= e($acc[$h['accion']] ?? $h['accion']) ?></strong> · <?= e(fecha_corta((string) $h['creado_en'])) ?><?= $h['nombre'] ? ' · ' . e($h['nombre']) : '' ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<script>
(function () {
  var form = document.getElementById('pl-form');
  var cont = document.getElementById('pl-marcs');
  var tpl = document.getElementById('pl-tpl');
  if (!form || !cont || !tpl) return;
  var autos = <?= json_encode(array_keys(Plantillas::AUTOMATICOS)) ?>;
  var valido = /^[a-z0-9_]{2,40}$/;
  function tipos(raiz) {
    raiz.querySelectorAll('.pl-tipo').forEach(function (s) {
      var cols = s.closest('.pl-marc').querySelector('.pl-cols');
      var ver = function () { cols.hidden = s.value !== 'tabla'; };
      s.addEventListener('change', ver); ver();
    });
  }
  tipos(cont);
  var guardadas = {};
  function detectar() {
    var t = ['asunto', 'dirigido_a', 'cuerpo'].map(function (n) { return form.elements[n].value; }).join('\n');
    var vistos = [], malos = [], auto = [], m, re = /\{([^{}\n]{1,60})\}/g;
    while ((m = re.exec(t))) {
      var k = m[1];
      if (autos.indexOf(k) >= 0) { if (auto.indexOf(k) < 0) auto.push(k); }
      else if (!valido.test(k)) { if (malos.indexOf(k) < 0) malos.push(k); }
      else if (vistos.indexOf(k) < 0) vistos.push(k);
    }
    // Conserva lo definido de cada marcador aunque desaparezca un momento del texto.
    cont.querySelectorAll('.pl-marc').forEach(function (f) { guardadas[f.dataset.clave] = f; f.remove(); });
    vistos.forEach(function (k) {
      var f = guardadas[k];
      if (!f) {
        var html = tpl.innerHTML.split('zz_clave').join(k);
        var d = document.createElement('div'); d.innerHTML = html; f = d.firstElementChild;
        f.querySelector('input[name$="[etiqueta]"]').value = k.charAt(0).toUpperCase() + k.slice(1).replace(/_/g, ' ');
        tipos(f); guardadas[k] = f;
      }
      cont.appendChild(f);
    });
    var inv = document.getElementById('pl-invalidos');
    inv.hidden = malos.length === 0;
    inv.textContent = malos.length ? 'Nombre no válido: ' + malos.map(function (x) { return '{' + x + '}'; }).join(', ') + '. Usa minúsculas sin acentos, números o guion bajo (2 a 40).' : '';
    document.getElementById('pl-autos').innerHTML = auto.map(function (x) { return '<span>{' + x + '} se llena solo</span>'; }).join('');
    document.getElementById('pl-resumen').textContent = vistos.length ? 'Marcadores encontrados en el texto. Define cómo se piden.' : 'Aún no hay marcadores por llenar en el texto.';
  }
  var espera;
  ['asunto', 'dirigido_a', 'cuerpo'].forEach(function (n) {
    form.elements[n].addEventListener('input', function () { clearTimeout(espera); espera = setTimeout(detectar, 300); });
  });
  document.getElementById('pl-detectar').hidden = true;
})();
</script>
<?php return; endif; ?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Redacción de cada constancia. Los marcadores entre llaves se sustituyen al generarla con los datos del profesor, del pasante y del trabajo. Separa párrafos con una línea en blanco. Los cambios valen para las constancias que se generen después de guardarlos; las ya generadas no cambian. Más abajo están las <a href="#plantillas">plantillas para «Solicitar una constancia»</a>.</p>
</div></div>

<details class="tarjeta" style="margin-bottom:14px">
  <summary><strong>Marcadores disponibles</strong></summary>
  <div class="tabla-contenedor"><table class="tabla">
    <?php foreach (Constancias::MARCADORES as $m => $d): ?><tr><td><code><?= e($m) ?></code></td><td><?= e($d) ?></td></tr><?php endforeach; ?>
  </table></div>
</details>

<?php foreach ($textos as $tipo => $t): ?>
<details class="tarjeta" id="<?= e($tipo) ?>" style="margin-bottom:12px" <?= $abierto === $tipo ? 'open' : '' ?>>
  <summary><strong><?= e(Constancias::TIPOS[$tipo][0]) ?></strong> · firma <?= e(Constancias::EMISORES[Constancias::TIPOS[$tipo][2]]) ?><?= $t['editado'] ? ' · <span class="tit-chip rev">editado</span>' : '' ?></summary>
  <form class="formulario" method="post" action="<?= url('titulacion/constancias/textos/' . $tipo) ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Asunto (a la derecha, bajo el número de oficio; vacío para omitirlo) <input type="text" name="asunto" maxlength="80" value="<?= e($t['asunto']) ?>"></label>
    <label>Introducción (antes del nombre del profesor) <textarea name="intro" rows="3" maxlength="2000" required><?= e($t['intro']) ?></textarea></label>
    <label>Cuerpo (después del nombre del profesor) <textarea name="cuerpo" rows="5" maxlength="4000" required><?= e($t['cuerpo']) ?></textarea></label>
    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="tabla" value="1" <?= $t['tabla'] ? 'checked' : '' ?>> Incluir la tabla del trabajo (pasante, modalidad y clave, fecha, título)</label>
    <label>Cierre <textarea name="cierre" rows="3" maxlength="2000"><?= e($t['cierre']) ?></textarea></label>
    <div class="acciones-formulario">
      <button class="btn btn-primario" type="submit">Guardar</button>
      <button class="btn" type="submit" formaction="<?= url('titulacion/constancias/textos/' . $tipo . '/muestra') ?>" formtarget="_blank">Vista previa (PDF)</button>
      <?php if ($t['editado']): ?><button class="btn" type="submit" formaction="<?= url('titulacion/constancias/textos/' . $tipo . '/restaurar') ?>" formnovalidate onclick="return confirm('¿Restaurar el texto original de esta constancia?')">Restaurar original</button><?php endif; ?>
    </div>
  </form>
</details>
<?php endforeach; ?>

<?php /** @var array $plantillas */ ?>
<section class="tarjeta" id="plantillas" style="margin-top:22px">
  <div class="pl-cab">
    <h2>Plantillas para solicitar constancias</h2>
    <a class="btn btn-primario" href="<?= url('titulacion/constancias/plantillas/nueva') ?>">Nueva plantilla</a>
  </div>
  <p class="pl-ayuda" style="margin-bottom:10px">Las activas aparecen a todo el personal en «Constancias → Solicitar una constancia»: eligen una, llenan los datos que pide y revisan el texto antes de enviarlo a firma.</p>
  <?php if (!$plantillas): ?>
    <p>Aún no hay plantillas.</p>
  <?php else: ?>
  <table class="pl-lista">
    <thead><tr><th>Plantilla</th><th>Última modificación</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
    <tbody>
    <?php foreach ($plantillas as $p): ?>
      <tr class="<?= $p['activa'] ? '' : 'inactiva' ?>">
        <td><strong><?= e($p['nombre']) ?></strong><?php if ($p['descripcion'] !== ''): ?><br><small><?= e($p['descripcion']) ?></small><?php endif; ?></td>
        <td data-et="Modificada"><?= e(fecha_corta((string) $p['actualizado_en'])) ?><?php if (!empty($p['actualizada_por_nombre'])): ?><br><small><?= e($p['actualizada_por_nombre']) ?></small><?php endif; ?></td>
        <td><span class="tit-chip <?= $p['activa'] ? 'ok' : 'pend' ?>"><?= $p['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
        <td class="pl-td-acc"><div class="pl-acc">
          <a class="btn btn-mini" href="<?= url('titulacion/constancias/plantillas/' . (int) $p['id']) ?>">Editar</a>
          <form method="post" action="<?= url('titulacion/constancias/plantillas/' . (int) $p['id'] . '/duplicar') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Duplicar</button></form>
          <form method="post" action="<?= url('titulacion/constancias/plantillas/' . (int) $p['id'] . '/estado') ?>"<?= $p['activa'] ? ' onsubmit="return confirm(\'¿Desactivar esta plantilla? Ya no aparecerá al solicitar constancias.\')"' : '' ?>>
            <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="activa" value="<?= $p['activa'] ? '0' : '1' ?>">
            <button class="btn btn-mini" type="submit"><?= $p['activa'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
