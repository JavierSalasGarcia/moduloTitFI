<?php
/** @var array $r @var array $a @var array $profesores @var array $grupos */
use App\Core\Auth;
use App\Modules\Consejos\Services\Texto;
include __DIR__ . '/_estilos.php';
$nuevo = (int) $a['id'] === 0;
$clave = Texto::clave($a['grupo'], (int) $a['numero']);
$accion = $nuevo ? 'consejos/reunion/' . $r['id'] . '/asunto' : 'consejos/asunto/' . $a['id'];
?>
<div class="cns-pila">
  <form class="tarjeta cns-form" method="post" action="<?= e(url($accion)) ?>" id="form-asunto" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="grupo" value="<?= e($a['grupo']) ?>">
    <?php $desde = (string) ($desde ?? ''); $rutaVolver = $desde !== '' ? 'consejos/reunion/' . $r['id'] . '/sesion/' . substr($desde, 7) : 'consejos/reunion/' . $r['id']; ?>
    <?php if ($desde !== ''): ?><input type="hidden" name="desde" value="<?= e($desde) ?>"><?php endif; ?>
    <div class="cns-cab" style="margin-bottom:12px">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">← <?= e($r['etiqueta']) ?></a></p>
        <h1><?= $nuevo ? 'Nuevo ' . e(mb_strtolower($grupos[$a['grupo']])) . ' (' . e($clave) . ')' : 'Caso ' . e($clave) ?></h1>
      </div>
      <?php if (!$nuevo): ?><label style="margin:0;max-width:130px">Número<input type="text" name="numero" inputmode="numeric" pattern="\d{1,3}" value="<?= (int) $a['numero'] ?>"></label><?php endif; ?>
    </div>

    <?php if (!empty($a['solicitud_id'])): ?>
    <p class="cns-ayuda" style="background:#f3f8f3;border:1px solid var(--gris-100);border-radius:var(--radio);padding:8px 12px">Este caso viene de una solicitud con Vo. Bo. de la coordinación. Aquí puedes editarlo con el mismo lienzo (tablas incluidas); el oficio original no cambia: <a href="<?= e(url('consejos/solicitudes/' . (int) $a['solicitud_id'])) ?>">ver la solicitud y su oficio</a>.</p>
    <?php endif; ?>
    <?php $plantillas = \App\Modules\Consejos\Services\Plantillas::paraGrupo((string) $a['grupo']); ?>
    <?php if ($plantillas): ?>
    <label>Tipo de trámite (plantilla opcional)
      <select id="plantilla"><option value="">— Escribir libremente —</option>
        <optgroup label="De este apartado del acta"><?php foreach ($plantillas as $k => $p): if ($p['propia']): ?><option value="<?= (int) $k ?>"><?= e($p['nombre']) ?></option><?php endif; endforeach; ?></optgroup>
        <optgroup label="De otros apartados"><?php foreach ($plantillas as $k => $p): if (!$p['propia']): ?><option value="<?= (int) $k ?>"><?= e($p['nombre']) ?></option><?php endif; endforeach; ?></optgroup></select>
      <span class="cns-ayuda">Rellena la solicitud con la redacción que usan las actas (sujeto + «solicita a los H.H. Consejos…» + lo que se pide + su finalidad) y, si el trámite lleva tabla, la deja armada. Sustituye lo que está entre [corchetes]; el sistema avisa si queda alguno sin llenar.</span>
    </label>
    <?php endif; ?>
    <label style="margin-top:12px">Solicitud <span class="req">*</span>
      <span class="cns-ayuda">Escribe o pega el texto tal como irá en el acta («El Dr. …, profesor de esta Facultad, solicita a los H.H. Consejos…»).</span>
    </label>
    <?php include __DIR__ . '/_herramientas.php'; ?>
    <div class="cns-lienzo" id="ed-solicitud" contenteditable="true" data-vacio="Escribe o pega aquí la solicitud…" data-herr="1"><?= $a['solicitud_html'] ?></div>
    <input type="hidden" name="solicitud_html" id="in-solicitud">

    <label style="margin-top:16px">Documentos y/o antecedentes
      <span class="cns-ayuda"><strong>Para poner una tabla: cópiala en Word (Ctrl+C) y pégala aquí (Ctrl+V).</strong> Se conservan las filas, columnas y celdas combinadas. También puedes escribir listas como «Oficio de solicitud».</span>
    </label>
    <?php include __DIR__ . '/_herramientas.php'; ?>
    <div class="cns-lienzo" id="ed-antecedentes" contenteditable="true" data-vacio="Pega aquí tablas y documentos de Word, o escribe la lista de antecedentes…" data-herr="1"><?= $a['antecedentes_html'] ?></div>
    <input type="hidden" name="antecedentes_html" id="in-antecedentes">

    <?php if ($a['grupo'] !== 'alumno'): ?>
    <label style="margin-top:16px">Profesores mencionados en el caso
      <span class="cns-ayuda">A quienes elijas aquí les aparecerá el caso (y su dictamen escaneado) en <strong>Comisiones</strong>. Si en la solicitud escribes <strong>@</strong> y parte del nombre, la persona se agrega aquí sola; quitarla de esta lista no la borra del texto. El sistema nunca los deduce por el nombre.</span>
    </label>
    <?php else: ?>
    <label style="margin-top:16px">Profesores mencionados (opcional)
      <span class="cns-ayuda">Solo si el caso incluye comités o sínodos de profesores que deban verlo en <strong>Comisiones</strong>.</span>
    </label>
    <?php endif; ?>
    <?php
    $campo = 'profesores[]'; $url = url('consejos/personas'); $seleccion = array_map(static fn($p) => ['id' => (int) $p['id'], 'nombre' => $p['nombre'], 'email' => $p['email']], $profesores);
    $id = 'personas'; $max = 0; $libre = false; $valor = 'id'; $placeholder = 'Escribe el nombre o correo del profesor…'; $etiqueta = 'Profesores mencionados en el caso';
    include dirname(__DIR__, 3) . '/Views/selector_personas.php';
    ?>

    <label style="margin-top:16px">Oficio original escaneado y anexos (opcional)
      <span class="cns-ayuda">Si el caso llegó sin pasar por la plataforma, sube aquí el <strong>oficio escaneado</strong> (elige «Oficio original escaneado»): en la proyección del caso se abre con el botón «Oficio y anexos». Cada archivo lleva una breve descripción de qué es.</span>
    </label>
    <?php $admiteOficio = true; include __DIR__ . '/_adjuntos_campos.php'; ?>

    <label style="margin-top:16px">Observación de la Subdirección (opcional)
      <span class="cns-ayuda">No es parte del acuerdo: en el acta en Word sale como <strong>comentario</strong> junto a la clave del caso, con tu nombre como autor.</span>
      <textarea name="observacion" rows="3" maxlength="3000"><?= e((string) ($a['observacion'] ?? '')) ?></textarea>
    </label>

    <div class="cns-acciones" style="margin-top:18px">
      <button class="btn btn-primario" type="submit">Guardar</button>
      <?php if ($nuevo): ?><button class="btn" type="submit" name="seguir" value="1">Guardar y agregar otro</button><?php endif; ?>
      <a class="btn" href="<?= e(url($rutaVolver)) ?>">Cancelar</a>
    </div>
  </form>
  <?php if (!empty($adjuntos)): ?>
  <section class="tarjeta">
    <h2 style="margin:0 0 4px;font-size:18px">Documentos del caso</h2>
    <?php $lista = $adjuntos; $quitar = true; include __DIR__ . '/_adjuntos_lista.php'; ?>
  </section>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/_editor_js.php'; ?>
