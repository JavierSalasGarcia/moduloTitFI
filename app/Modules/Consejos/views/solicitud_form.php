<?php
/** @var array $s @var array $profesores @var array $coordinaciones @var array $plantillas */
use App\Core\Auth;
use App\Modules\Consejos\Services\Solicitudes;
include __DIR__ . '/_estilos.php';
$nueva = (int) $s['id'] === 0;
$accion = $nueva ? 'consejos/solicitudes' : 'consejos/solicitudes/' . $s['id'];
$apartados = ['profesor' => 'Casos de profesores', 'alumno' => 'Trámites de alumnos', 'general' => 'Asuntos generales y comisiones'];
$coordActual = '';
foreach ($coordinaciones as $c) {
    if ((int) $c['usuario_id'] === (int) $s['coordinador_id'] && $c['coordinacion'] === $s['coordinacion']) {
        $coordActual = $c['clave'];
    }
}
?>
<div class="cns-pila">
  <form class="tarjeta cns-form" method="post" action="<?= e(url($accion)) ?>" id="form-asunto">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="plantilla" id="plantilla-clave" value="<?= e((string) ($s['plantilla'] ?? '')) ?>">
    <div class="cns-cab" style="margin-bottom:12px">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/solicitudes')) ?>">← Mis solicitudes</a> · <a href="<?= e(url('consejos/manual')) ?>">Manual de captura</a></p>
        <h1><?= $nueva ? 'Nueva solicitud a los Consejos' : 'Editar solicitud ' . e((string) $s['folio']) ?></h1>
        <p class="cns-sub">Redacta tu solicitud con la redacción de las actas. Se envía a la coordinación para su Vo. Bo. y, con él, pasa al acta de la siguiente reunión.</p>
      </div>
    </div>
    <?php if (!empty($s['comentario'])): ?>
      <p class="cns-aviso" style="background:#fffbeb;border:1px solid #f1e1a6;border-radius:var(--radio);padding:10px 12px"><strong>La coordinación hizo observaciones:</strong> <?= e((string) $s['comentario']) ?></p>
    <?php endif; ?>

    <div class="cns-fila">
      <label>Tipo de trámite
        <select id="plantilla"><option value="">— Escribir libremente —</option>
          <?php foreach ($apartados as $g => $titulo): ?>
            <optgroup label="<?= e($titulo) ?>"><?php foreach ($plantillas as $k => $p): if ($p['grupo'] === $g): ?><option value="<?= (int) $k ?>"<?= ($s['plantilla'] ?? '') === $p['clave'] ? ' data-previa="1"' : '' ?>><?= e($p['nombre']) ?></option><?php endif; endforeach; ?></optgroup>
          <?php endforeach; ?>
        </select>
        <span class="cns-ayuda">Rellena la solicitud con la redacción oficial. Sustituye lo que está entre [corchetes].</span>
      </label>
      <label>Apartado del acta
        <select name="grupo" id="grupo-sel"><?php foreach ($apartados as $g => $titulo): ?><option value="<?= e($g) ?>"<?= $s['grupo'] === $g ? ' selected' : '' ?>><?= e($titulo) ?></option><?php endforeach; ?></select>
      </label>
    </div>

    <label>Solicitud <span class="req">*</span>
      <span class="cns-ayuda">Sujeto y calidad + «solicita a los H.H. Consejos Académico y de Gobierno» + lo que se pide + su finalidad o fundamento.</span>
    </label>
    <?php include __DIR__ . '/_herramientas.php'; ?>
    <div class="cns-lienzo" id="ed-solicitud" contenteditable="true" data-vacio="Escribe aquí la solicitud o elige un tipo de trámite…" data-herr="1"><?= $s['solicitud_html'] ?></div>
    <input type="hidden" name="solicitud_html" id="in-solicitud">

    <label style="margin-top:16px">Documentos y/o antecedentes
      <span class="cns-ayuda">Lista de los documentos que anexas («Oficio de solicitud»). Las tablas de Word se pegan con Ctrl+C / Ctrl+V.</span>
    </label>
    <?php include __DIR__ . '/_herramientas.php'; ?>
    <div class="cns-lienzo" id="ed-antecedentes" contenteditable="true" data-vacio="Lista de antecedentes…" data-herr="1"><?= $s['antecedentes_html'] ?></div>
    <input type="hidden" name="antecedentes_html" id="in-antecedentes">

    <label style="margin-top:16px">Vo. Bo. de la coordinación <span class="req">*</span>
      <span class="cns-ayuda">Por acuerdo del H. Consejo de Gobierno del <?= e(Solicitudes::ACUERDO_FECHA) ?> (<?= e(Solicitudes::ACUERDO_CLAVE) ?>), toda solicitud lleva el Vo. Bo. o de enterado de la Coordinación de Docencia respectiva o de la de Estudios Avanzados. Elige a quién se la envías.</span>
      <select name="coordinacion" required>
        <option value="">— Elige la coordinación —</option>
        <?php foreach ($coordinaciones as $c): ?><option value="<?= e($c['clave']) ?>"<?= $coordActual === $c['clave'] ? ' selected' : '' ?>><?= e($c['coordinacion']) ?> — <?= e($c['nombre']) ?></option><?php endforeach; ?>
      </select>
    </label>

    <label style="margin-top:16px">Profesores mencionados (opcional)
      <span class="cns-ayuda">Escribe <strong>@</strong> y parte del nombre en la solicitud, o búscalos aquí; así el caso les aparece en <strong>Comisiones</strong>. Nunca se deducen por el nombre.</span>
    </label>
    <?php
    $campo = 'profesores[]'; $url = url('consejos/solicitudes/personas'); $seleccion = array_map(static fn($p) => ['id' => (int) $p['id'], 'nombre' => $p['nombre'], 'email' => $p['email']], $profesores);
    $id = 'personas'; $max = 0; $libre = false; $valor = 'id'; $placeholder = 'Escribe el nombre o correo del profesor…'; $etiqueta = 'Profesores mencionados en el caso';
    include dirname(__DIR__, 3) . '/Views/selector_personas.php';
    ?>

    <?php if (($s['estado'] ?? '') === 'devuelta'): ?>
    <label style="margin-top:16px">Qué corregiste (opcional)<input type="text" name="respuesta" maxlength="1000" placeholder="Queda en el historial de observaciones y la coordinación lo ve al revisar"></label>
    <?php endif; ?>
    <div class="cns-acciones" style="margin-top:18px">
      <button class="btn btn-primario" type="submit" name="accion" value="enviar"><?= ($s['estado'] ?? '') === 'devuelta' ? 'Guardar y reenviar a la coordinación' : 'Guardar y enviar a la coordinación' ?></button>
      <button class="btn" type="submit" name="accion" value="borrador">Guardar borrador</button>
      <a class="btn" href="<?= e(url('consejos/solicitudes')) ?>">Cancelar</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/_editor_js.php'; ?>
