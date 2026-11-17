<?php
use App\Core\Auth;
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Flujo;
use App\Modules\Posgrado\Services\Firmas;

include __DIR__ . '/_estilos.php';

$base = 'posgrado/tramite/' . (int) $t['id'];
$i = $t['datos']['interesado'] ?? [];
$s = $t['datos']['sinodo'] ?? [];
$paso = $ev['paso'];
$actual = (int) $ev['actual'];
$momentos = Catalogo::momentos();
$editable = $puedeEditar;
$prog = $ev['hitos']['programacion']['datos'] ?? [];
$res = $ev['hitos']['resultado']['datos'] ?? [];
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$sel = static fn(string $a, string $b): string => $a === $b ? ' selected' : '';
$chk = static fn(bool $b): string => $b ? ' checked' : '';
$maxMb = (int) $maxMb;
$cotejado = isset($ev['hitos']['cotejo']);
$firmas = $ev['firmas'];
?>
<div class="pos-cabecera">
  <div>
    <p class="pos-volver"><a href="<?= url('posgrado') ?>">← Posgrado: obtener mi grado</a></p>
    <p class="texto-secundario">Folio <strong><?= e($t['folio']) ?></strong><?= ($i['programa'] ?? '') !== '' ? ' · ' . e((string) $i['programa']) : '' ?></p>
  </div>
  <div>
    <span class="estado estado-<?= e($t['estado'] === 'concluido' ? 'concluida' : $t['estado']) ?>"><?= e(Catalogo::estados()[$t['estado']] ?? $t['estado']) ?></span>
    <?php if ($esPersonal && !$esDueno): ?> <a class="btn btn-mini" href="<?= url('posgrado/gestion/' . (int) $t['id']) ?>">Abrir en validación</a><?php endif; ?>
  </div>
</div>

<div class="pos-pila">
  <section class="pos-paso <?= e($paso['turno']) ?>" aria-labelledby="pos-paso-titulo">
    <p class="pos-paso-etiqueta"><?= $paso['turno'] === 'personal' ? 'Ahora le toca a Apoyo a Grado' : ($paso['turno'] === 'nadie' ? 'Tu trámite' : ($paso['turno'] === 'espera' ? 'Tu siguiente paso · en espera' : 'Tu siguiente paso')) ?></p>
    <h2 id="pos-paso-titulo"><?= e($paso['titulo']) ?></h2>
    <p><?= e($paso['texto']) ?></p>
    <?php if ($paso['turno'] === 'tuyo' && $paso['boton'] && $editable): ?>
      <a class="btn btn-primario" href="#<?= e((string) $paso['ancla']) ?>"><?= e($paso['boton']) ?> <?= icono('flecha-der', 16) ?></a>
    <?php elseif ($paso['turno'] === 'espera'): ?>
      <?php if (!empty($paso['reenviar']) && $editable): ?>
        <form method="post" action="<?= url($base . '/firma/' . (int) $paso['reenviar'] . '/reenviar') ?>"><?= $csrf ?><input type="hidden" name="volver" value="<?= e((string) $paso['ancla']) ?>"><button class="btn btn-primario" type="submit">Reenviar enlace</button></form>
      <?php endif; ?>
      <a class="btn" href="#<?= e((string) $paso['ancla']) ?>">Ver quién falta</a>
    <?php elseif ($paso['turno'] === 'personal'): ?>
      <p class="texto-secundario" style="margin:0">No tienes que hacer nada en la plataforma por ahora; te avisaremos en tus notificaciones y por correo.</p>
    <?php endif; ?>
  </section>

  <nav aria-label="Avance de tu trámite">
    <ol class="pos-momentos">
      <?php foreach ($momentos as $n => $m):
        $clase = $ev['hecho'][$n] ? 'hecho' : ($n === $actual ? 'actual' : 'pendiente'); ?>
        <li class="<?= $clase ?>"<?= $n === $actual ? ' aria-current="step"' : '' ?>>
          <a href="#momento-<?= (int) $n ?>"><span class="pos-num" aria-hidden="true"><?= $ev['hecho'][$n] ? '✓' : (int) $n ?></span><?= e($m['breve']) ?><span class="sr-only"> (<?= $clase === 'hecho' ? 'hecho' : ($clase === 'actual' ? 'en curso' : 'pendiente') ?>)</span></a>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <?php /* ---------------------------------------------------------------- Momento 1 */ ?>
  <details class="pos-momento <?= $actual === 1 ? 'actual' : '' ?>" id="momento-1" <?= $actual === 1 ? 'open' : '' ?>>
    <summary>1. <?= e($momentos[1]['titulo']) ?> <small><?= $ev['hecho'][1] ? '· Hecho' : '· ' . (int) $ev['listos'] . ' de ' . (int) $ev['total'] . ' listos' ?></small></summary>
    <div class="pos-momento-cuerpo">
      <?php if ($cotejado): $c = $ev['hitos']['cotejo']['datos']; ?>
        <div class="pos-ok">Apoyo a Grado cotejó tus originales el <?= e(Flujo::fechaLarga((string) ($c['fecha'] ?? ''))) ?> a las <?= e((string) ($c['hora'] ?? '')) ?> horas.
          <?php if ($ev['hecho'][1] && isset($ev['generados']['cotejo'])): ?> <a href="<?= url('posgrado/generado/' . (int) $ev['generados']['cotejo']['id']) ?>" target="_blank" rel="noopener">Ver el oficio de cotejo para tu director</a>.
          <?php else: ?> El oficio de cotejo está en firma de la Coordinación.<?php endif; ?></div>
      <?php endif; ?>

      <details id="datos" class="pos-grupo" <?= $ev['faltanDatos'] ? 'open' : '' ?>>
        <summary>Tus datos y los de tu director <span class="pos-cuenta"><?= $ev['faltanDatos'] ? count($ev['faltanDatos']) . ' por capturar' : 'Completos' ?></span></summary>
        <?php $editaDatos = $editable && !$cotejado; ?>
        <form class="formulario" method="post" action="<?= url($base . '/datos') ?>">
          <?= $csrf ?>
          <fieldset class="pos-fieldset" <?= $editaDatos ? '' : 'disabled' ?>>
            <legend>Datos del interesado</legend>
            <div class="pos-campos">
              <label class="ancho">Tu nombre tal como irá en tu grado
                <input type="text" name="c[nombre]" maxlength="120" value="<?= e((string) ($i['nombre'] ?? '')) ?>" autocomplete="name">
                <small>Con mayúsculas, minúsculas y acentos, igual que en tu CURP.</small></label>
              <div class="ancho"><span style="font-size:13px;font-weight:600">¿Tu nombre lleva acentos?</span>
                <div class="pos-radios"><label><input type="radio" name="c[acentos]" value="si"<?= $chk(($i['acentos'] ?? '') === 'si') ?>> Sí</label><label><input type="radio" name="c[acentos]" value="no"<?= $chk(($i['acentos'] ?? '') === 'no') ?>> No</label></div></div>
              <label>Número de cuenta<input type="text" name="c[cuenta]" inputmode="numeric" maxlength="12" value="<?= e((string) ($i['cuenta'] ?? '')) ?>"></label>
              <label>Nivel
                <select name="c[nivel]"><option value="">Elige…</option><?php foreach (Catalogo::niveles() as $k => $v): ?><option value="<?= e($k) ?>"<?= $sel($k, (string) ($i['nivel'] ?? '')) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
              <label class="ancho">Programa<input type="text" name="c[programa]" maxlength="150" value="<?= e((string) ($i['programa'] ?? '')) ?>"><small>Por ejemplo «Maestría en Movilidad y Transporte».</small></label>
              <label>Plan de estudios<input type="text" name="c[plan]" maxlength="120" value="<?= e((string) ($i['plan'] ?? '')) ?>"></label>
              <label>Periodo en que iniciaste tus estudios<input type="text" name="c[periodo_inicio]" maxlength="5" placeholder="2024B" value="<?= e((string) ($i['periodo_inicio'] ?? '')) ?>"></label>
              <label>Teléfono<input type="tel" name="c[telefono]" maxlength="30" value="<?= e((string) ($i['telefono'] ?? '')) ?>" autocomplete="tel"></label>
              <label>Correo electrónico<input type="email" name="c[correo]" maxlength="120" value="<?= e((string) ($i['correo'] ?? '')) ?>" autocomplete="email"></label>
              <label class="ancho">Ocupación actual<input type="text" name="c[ocupacion]" maxlength="120" value="<?= e((string) ($i['ocupacion'] ?? '')) ?>"></label>
            </div>
          </fieldset>
          <fieldset class="pos-fieldset" <?= $editaDatos ? '' : 'disabled' ?>>
            <legend>Tu trabajo de grado</legend>
            <div class="pos-campos">
              <label class="ancho">Título<textarea name="c[titulo]" rows="2" maxlength="500"><?= e((string) ($i['titulo'] ?? '')) ?></textarea><small>Igual al de tu constancia de registro de protocolo.</small></label>
              <label>Modalidad
                <select name="c[modalidad]"><option value="">Elige…</option><?php foreach (Catalogo::modalidades() as $k => $v): ?><option value="<?= e($k) ?>"<?= $sel($k, (string) ($i['modalidad'] ?? '')) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
              <label>Fecha de tu examen previo<input type="date" name="c[fecha_previo]" max="<?= e(date('Y-m-d')) ?>" value="<?= e((string) ($i['fecha_previo'] ?? '')) ?>"><small>Va en el voto aprobatorio de tu director.</small></label>
              <div class="ancho"><span style="font-size:13px;font-weight:600">¿Necesitas autorización para continuar con los trámites de obtención de grado?</span>
                <div class="pos-radios"><label><input type="radio" name="c[art53]" value="no"<?= $chk(($i['art53'] ?? '') === 'no') ?>> No</label><label><input type="radio" name="c[art53]" value="si"<?= $chk(($i['art53'] ?? '') === 'si') ?>> Sí</label></div>
                <small class="texto-secundario">Aplica si te pasaste del plazo para graduarte (art. 53 del Reglamento de Estudios Avanzados). Si no sabes, pregunta a tu coordinador.</small></div>
            </div>
          </fieldset>
          <datalist id="pos-grados"><?php foreach (Catalogo::grados() as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
          <?php foreach (['director' => 'Director(a) de tesis', 'codirector' => 'Codirector(a) (si tienes)', 'tutor' => 'Tutor(a) (si tienes)'] as $rol => $leyenda): ?>
          <fieldset class="pos-fieldset" <?= $editaDatos ? '' : 'disabled' ?>>
            <legend><?= e($leyenda) ?></legend>
            <div class="pos-campos">
              <label>Grado<input type="text" name="c[<?= $rol ?>_grado]" list="pos-grados" maxlength="20" placeholder="Dr., Dra., Mtro., Mtra." value="<?= e((string) ($i[$rol . '_grado'] ?? '')) ?>"></label>
              <label>Nombre completo<input type="text" name="c[<?= $rol ?>_nombre]" maxlength="120" value="<?= e((string) ($i[$rol . '_nombre'] ?? '')) ?>"></label>
              <label class="ancho">Correo<?= $rol === 'director' ? ' (obligatorio)' : '' ?><input type="email" name="c[<?= $rol ?>_correo]" maxlength="120" value="<?= e((string) ($i[$rol . '_correo'] ?? '')) ?>"<?= $rol === 'director' ? ' aria-describedby="ayuda-dir-correo"' : '' ?>>
                <?php if ($rol === 'director'): ?><small id="ayuda-dir-correo">A este correo le llega su enlace personal para firmar en línea el voto aprobatorio y la solicitud.</small><?php endif; ?></label>
            </div>
          </fieldset>
          <?php endforeach; ?>
          <?php if ($editaDatos): ?><div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar mis datos</button></div>
          <?php elseif ($cotejado): ?><p class="texto-secundario">Tus datos ya se cotejaron; si algo está mal, pídele a Apoyo a Grado que lo corrija.</p><?php endif; ?>
        </form>
      </details>

      <div id="requisitos">
        <h3 class="pos-sub">Tus requisitos</h3>
        <div class="pos-avance">
          <strong><?= (int) $ev['listos'] ?> de <?= (int) $ev['total'] ?> listos</strong>
          <div class="pos-barra" role="progressbar" aria-label="Requisitos listos" aria-valuemin="0" aria-valuemax="<?= (int) $ev['total'] ?>" aria-valuenow="<?= (int) $ev['listos'] ?>"><i style="width:<?= $ev['total'] > 0 ? round(100 * $ev['listos'] / $ev['total']) : 0 ?>%"></i></div>
        </div>
        <p class="texto-secundario" style="margin:0 0 8px">Sube cada documento escaneado en PDF. Los originales y copias los entregas en persona el día del cotejo. El voto aprobatorio y tu formato de datos se firman en línea.</p>
        <?php
        $porGrupo = [];
        foreach ($ev['requisitos'] as $r) {
            $porGrupo[$r['grupo']][] = $r;
        }
        foreach (Catalogo::grupos() as $g => $nombreGrupo):
            $filas = $porGrupo[$g] ?? [];
            if (!$filas) {
                continue;
            }
            $cuentan = array_filter($filas, static fn(array $r): bool => $r['cuenta'] ?? true);
            $hechos = count(array_filter($cuentan, static fn(array $r): bool => $r['listo']));
            $abrir = !$cotejado && $hechos < count($cuentan); ?>
          <details class="pos-grupo" <?= $abrir ? 'open' : '' ?>>
            <summary><?= e($nombreGrupo) ?> <span class="pos-cuenta"><?= $hechos ?> de <?= count($cuentan) ?></span></summary>
            <ul class="pos-lista">
              <?php foreach ($filas as $fila) {
                  $prefijo = 'req-';
                  $puedeSubir = $editable && !$cotejado && !$ev['faltanDatos'];
                  include __DIR__ . '/_doc.php';
              } ?>
            </ul>
          </details>
        <?php endforeach; ?>
        <?php if ($editable && $ev['faltanDatos'] && !$cotejado): ?><p class="pos-nota">Para subir documentos, primero completa <a href="#datos">tus datos</a>.</p><?php endif; ?>
      </div>
    </div>
  </details>

  <?php /* ---------------------------------------------------------------- Momento 2 */ ?>
  <details class="pos-momento <?= $actual === 2 ? 'actual' : '' ?>" id="momento-2" <?= $actual === 2 ? 'open' : '' ?>>
    <summary>2. <?= e($momentos[2]['titulo']) ?> <small><?= $ev['hecho'][2] ? '· Hecho' : ($actual < 2 ? '· Después del cotejo' : '') ?></small></summary>
    <div class="pos-momento-cuerpo" id="sinodo">
      <?php if (!empty($t['datos']['aplazamientos']) && !$ev['hecho'][2]): ?>
        <p class="pos-nota">Tu examen anterior quedó aplazado. Propón una nueva fecha y envíala a firma: tu director y tu sínodo firmarán otra vez en línea.</p>
      <?php endif; ?>
      <?php if (!$ev['hecho'][1]): ?>
        <p class="texto-secundario">Cuando Apoyo a Grado coteje tus originales y la Coordinación firme el oficio de cotejo, aquí propondrás a tu sínodo y la fecha de tu examen.</p>
      <?php else: ?>
        <?php if ($ev['hecho'][2]): ?>
          <div class="pos-ok"><strong>Examen programado:</strong> <?= e(Flujo::fechaLarga((string) ($prog['fecha'] ?? ''))) ?> a las <?= e((string) ($prog['hora'] ?? '')) ?> horas, <?= ($prog['modalidad'] ?? '') === 'virtual' ? 'modalidad virtual' : 'modalidad presencial' ?>, en <?= e((string) ($prog['lugar'] ?? '')) ?>.</div>
        <?php elseif (!$ev['propuestaEnviada']): ?>
          <p class="pos-nota">Elige una fecha a partir del <strong><?= e(Flujo::fechaLarga($fechaMinima)) ?></strong> (10 días hábiles). Te sugerimos dejar 15 días hábiles, es decir, del <strong><?= e(Flujo::fechaLarga($fechaSugerida)) ?></strong> en adelante, para que firmen con calma. Cada integrante necesita su correo: ahí recibe su enlace para firmar el visto bueno y, después, su invitación. Su firma electrónica UAEMéx debe estar vigente al menos un mes después del examen.</p>
        <?php endif; ?>
        <?php
        $miembros = $s['miembros'] ?? [];
        if (!$miembros) {
            $pre = [];
            foreach (['director', 'codirector', 'tutor'] as $rol) {
                if (trim((string) ($i[$rol . '_nombre'] ?? '')) !== '') {
                    $pre[] = ['nombre' => trim(($i[$rol . '_grado'] ?? '') . ' ' . $i[$rol . '_nombre']), 'tipo' => $rol, 'correo' => (string) ($i[$rol . '_correo'] ?? ''), 'externo' => 0];
                }
            }
            $miembros = $pre;
        }
        $editaSinodo = $editable && !$ev['hecho'][2];
        ?>
        <?php if ($ev['propuestaEnviada']): ?><details class="pos-grupo"><summary>Tu propuesta: <?= e(Flujo::fechaLarga((string) ($s['fecha'] ?? ''))) ?>, <?= e((string) ($s['hora'] ?? '')) ?> horas<?= $editaSinodo ? ' · ver o cambiar' : '' ?></summary><?php endif; ?>
        <form class="formulario" method="post" action="<?= url($base . '/sinodo') ?>">
          <?= $csrf ?>
          <fieldset class="pos-fieldset" <?= $editaSinodo ? '' : 'disabled' ?>>
            <legend>Tu sínodo: cinco propietarios y dos suplentes</legend>
            <p class="texto-secundario" style="margin:0 0 6px;font-size:13px">Escribe el nombre con su grado (por ejemplo «Dra. Ana López Pérez»). <small>Art. 65 del Reglamento de Estudios Avanzados.</small></p>
            <?php for ($n = 0; $n < 7; $n++): $m = $miembros[$n] ?? []; ?>
              <?php if ($n === 0): ?><p class="pos-n" style="margin:8px 0 0">Propietarios</p><?php elseif ($n === 5): ?><p class="pos-n" style="margin:12px 0 0">Suplentes</p><?php endif; ?>
              <div class="pos-miembro">
                <span class="pos-n" aria-hidden="true"><?= $n + 1 ?></span>
                <label>Nombre con grado<input type="text" name="m[<?= $n ?>][nombre]" maxlength="150" value="<?= e((string) ($m['nombre'] ?? '')) ?>" aria-label="<?= $n < 5 ? 'Propietario ' . ($n + 1) : 'Suplente ' . ($n - 4) ?>: nombre con grado"></label>
                <label>Tipo de miembro<select name="m[<?= $n ?>][tipo]"><option value="">Elige…</option><?php foreach (Catalogo::tiposMiembro() as $k => $v): ?><option value="<?= e($k) ?>"<?= $sel($k, (string) ($m['tipo'] ?? '')) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
                <label>Correo (obligatorio)<input type="email" name="m[<?= $n ?>][correo]" maxlength="120" value="<?= e((string) ($m['correo'] ?? '')) ?>"></label>
                <label class="pos-check"><input type="checkbox" name="m[<?= $n ?>][externo]" value="1"<?= $chk(!empty($m['externo'])) ?>> Externo a la UAEMéx</label>
              </div>
            <?php endfor; ?>
          </fieldset>
          <fieldset class="pos-fieldset" <?= $editaSinodo ? '' : 'disabled' ?>>
            <legend>Fecha y hora que propones</legend>
            <div class="pos-campos">
              <label>Fecha<input type="date" name="fecha" min="<?= e($fechaMinima) ?>" value="<?= e((string) ($s['fecha'] ?? '')) ?>"></label>
              <label>Hora<input type="time" name="hora" min="07:00" max="20:00" step="900" value="<?= e((string) ($s['hora'] ?? '')) ?>"></label>
              <div class="ancho"><span style="font-size:13px;font-weight:600">Modalidad del examen</span>
                <div class="pos-radios"><label><input type="radio" name="modalidad" value="presencial"<?= $chk(($s['modalidad'] ?? 'presencial') === 'presencial') ?>> Presencial</label><label><input type="radio" name="modalidad" value="virtual"<?= $chk(($s['modalidad'] ?? '') === 'virtual') ?>> Virtual</label></div></div>
            </div>
          </fieldset>
          <?php if ($editaSinodo): ?><div class="acciones-formulario">
            <?php if ($ev['propuestaEnviada']): ?>
              <button class="btn btn-primario" type="submit">Guardar cambios</button>
              <small class="texto-secundario" style="display:block;margin-top:6px">Si cambias nombres, fecha u hora, la solicitud y el visto bueno se regeneran y se piden de nuevo todas las firmas. Si solo corriges un correo, se envía un enlace nuevo a esa persona.</small>
            <?php else: ?>
              <button class="btn" type="submit">Guardar borrador</button>
              <button class="btn btn-primario" type="submit" name="enviar" value="1">Guardar y enviar a firma</button>
              <small class="texto-secundario" style="display:block;margin-top:6px">Al enviar, tu director recibe la solicitud y cada integrante el visto bueno, con un enlace personal para firmar en línea.</small>
            <?php endif; ?>
          </div><?php endif; ?>
        </form>
        <?php if ($ev['propuestaEnviada']): ?></details><?php endif; ?>

        <?php if ($ev['externos']): ?>
          <div class="pos-nota"><strong>Tienes sinodales externos.</strong> Apoyo a Grado verificará que estén registrados en el Sistema de Control Escolar de la UAEMéx; si no lo están, les pedirán a ti y a tu director los documentos del profesor para darlo de alta. Además, cada externo necesita su firma electrónica para firmar el acta en el SICDE.
            <br><span class="pos-estado <?= $ev['externosAlta'] ? 'pos-e-ok' : 'pos-e-pend' ?>" style="margin-top:6px"><?= $ev['externosAlta'] ? 'Externos dados de alta en Control Escolar' : 'Alta de externos: pendiente' ?></span>
            <small class="texto-secundario" style="display:block;margin-top:4px">Punto 4 de «Puntos a considerar».</small></div>
        <?php endif; ?>

        <div id="firmas-m2">
        <h3 class="pos-sub">Firmas de tu propuesta</h3>
        <?php if (!$ev['propuestaEnviada']): ?>
          <p class="texto-secundario"><?= $ev['faltanSinodo'] ? 'Cuando completes tu propuesta podrás enviarla a firma.' : 'Tu propuesta aún no se envía a firma.' ?> <?php if (!$ev['faltanSinodo']): ?><a href="<?= url($base . '/formato/solicitud') ?>" target="_blank" rel="noopener">Vista previa de la solicitud</a> · <a href="<?= url($base . '/formato/visto_bueno') ?>" target="_blank" rel="noopener">vista previa del visto bueno</a><?php endif; ?></p>
        <?php else: ?>
          <?php foreach ($ev['avisosVigencia'] as $aviso): ?><p class="pos-alerta" role="alert"><strong><?= e($aviso) ?></strong> Debe renovarla en <a href="https://fise.uaemex.mx/" target="_blank" rel="noopener noreferrer">https://fise.uaemex.mx/</a> y actualizar la fecha desde su enlace.</p><?php endforeach; ?>
          <?php foreach ($ev['respuestasNo'] as $rn): ?><p class="pos-alerta"><strong><?= e($rn['nombre']) ?></strong> contestó «No» a: <?= e(implode(', ', $rn['preguntas'])) ?>. Apoyo a Grado revisará la propuesta.</p><?php endforeach; ?>
          <?php foreach (['solicitud' => 'Solicitud de evaluación de grado (la firma tu director)', 'visto_bueno' => 'Visto bueno del sínodo (lo firma cada integrante)'] as $doc => $etq):
              $docFirmas = $firmas[$doc] ?? null; $g = $ev['generados'][$doc] ?? null; ?>
            <h4 style="margin:12px 0 4px;font-size:14px"><?= e($etq) ?> <?php if ($docFirmas): ?><span class="pos-estado <?= $docFirmas['completo'] ? 'pos-e-ok' : 'pos-e-rev' ?>"><?= $docFirmas['completo'] ? 'Firmado por todos' : 'En firma' ?></span><?php endif; ?>
              <?php if ($g): ?> <a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $g['id']) ?>" target="_blank" rel="noopener">Ver PDF</a><?php endif; ?></h4>
            <?php $gestiona = $editable && !$ev['hecho'][2]; $volver = 'firmas-m2'; $fechaExamen = $ev['fechaExamen']; include __DIR__ . '/_firmantes.php'; ?>
          <?php endforeach; ?>
        <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </details>

  <?php /* ---------------------------------------------------------------- Momento 3 */ ?>
  <details class="pos-momento <?= $actual === 3 ? 'actual' : '' ?>" id="momento-3" <?= $actual === 3 ? 'open' : '' ?>>
    <summary>3. <?= e($momentos[3]['titulo']) ?> <small><?= $ev['hecho'][3] ? '· Hecho' : '' ?></small></summary>
    <div class="pos-momento-cuerpo" id="invitaciones">
      <?php if (!$ev['hecho'][2]): ?>
        <p class="texto-secundario">Cuando Apoyo a Grado programe tu examen, aquí descargarás los oficios de invitación para tu sínodo.</p>
      <?php else: ?>
        <p>La Coordinación firma tus oficios de invitación; después cada integrante recibe el suyo por correo y acusa de recibido en línea. Tú solo entrégales tu trabajo final.</p>
        <ul class="pos-lista">
          <?php foreach (['invitaciones' => 'Una hoja por cada integrante (propietarios y suplentes).', 'notificacion' => 'Para tu director: explica lo que debe hacer antes, durante y después del examen. Descárgala y compártela con él.', 'acuse' => 'Cada integrante acusa de recibido en línea su invitación; su sello queda en su renglón.'] as $clave => $ayuda):
              $g = $ev['generados'][$clave] ?? null; $ef = Flujo::estadoFirma($firmas, $clave);
              $listo = $ef === 'firmado' || ($clave === 'acuse' && Firmas::rolCompleto($firmas, 'acuse', 'coordinacion')); ?>
            <li class="pos-doc" id="doc-<?= e($clave) ?>">
              <span class="pos-icono <?= $ef === 'firmado' ? 'pos-e-ok' : ($g ? 'pos-e-rev' : 'pos-e-pend') ?>" aria-hidden="true"><?= $ef === 'firmado' ? icono('ok', 18) : '' ?></span>
              <div><h4><?= e(Catalogo::emitibles()[$clave]['nombre']) ?>
                <?php if ($ef === 'firmado'): ?><span class="pos-estado pos-e-ok">Firmado</span><?php elseif ($g && !$listo): ?><span class="pos-estado pos-e-rev">En firma de la Coordinación</span><?php elseif ($g): ?><span class="pos-estado pos-e-rev">Esperando acuses</span><?php endif; ?></h4>
                <p class="pos-ayuda"><?= e($ayuda) ?></p></div>
              <?php if ($clave === 'acuse' && $listo): ?><div style="grid-column:2"><?php $docFirmas = $firmas['acuse'] ?? null; $gestiona = $editable; $volver = 'doc-acuse'; $fechaExamen = ''; include __DIR__ . '/_firmantes.php'; ?></div><?php endif; ?>
              <div class="pos-doc-acciones"><?= $g && $listo ? '<a class="btn btn-mini" href="' . url('posgrado/generado/' . (int) $g['id']) . '" target="_blank" rel="noopener">Descargar</a>' : '<span class="texto-secundario">' . ($g ? 'Disponible cuando lo firme la Coordinación.' : 'Apoyo a Grado lo emitirá pronto.') . '</span>' ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (isset($ev['hitos']['acuse_cierre'])): ?><p class="pos-ok">Apoyo a Grado cerró las invitaciones.</p><?php endif; ?>
      <?php endif; ?>
    </div>
  </details>

  <?php /* ---------------------------------------------------------------- Momento 4 */ ?>
  <details class="pos-momento <?= $actual >= 4 ? 'actual' : '' ?>" id="momento-4" <?= $actual >= 4 ? 'open' : '' ?>>
    <summary>4. <?= e($momentos[4]['titulo']) ?> <small><?= $ev['hecho'][4] ? '· Concluido' : '' ?></small></summary>
    <div class="pos-momento-cuerpo" id="cierre">
      <?php if (!$ev['hecho'][3]): ?>
        <p class="texto-secundario">Aquí verás el resultado de tu examen y lo que sigue para cerrar tu trámite.</p>
      <?php else: ?>
        <?php if (!$res): ?>
          <p>Tu examen: <strong><?= e(Flujo::fechaLarga((string) ($prog['fecha'] ?? ''))) ?></strong> a las <strong><?= e((string) ($prog['hora'] ?? '')) ?> horas</strong>, en <?= e((string) ($prog['lugar'] ?? '')) ?>.</p>
        <?php else: ?>
          <div class="pos-ok"><strong><?= e(Catalogo::resultados()[$res['resultado']] ?? '') ?></strong> el <?= e(Flujo::fechaLarga((string) ($res['fecha'] ?? ''))) ?>.</div>
        <?php endif; ?>
        <?php if ($ev['aprobado']): ?>
          <h3 class="pos-sub">Registra tu grado en el SICDE</h3>
          <p>Tienes <strong>48 horas</strong> después del examen para registrar tu grado electrónico en el portal de alumnos de Control Escolar. Después captura aquí el folio de seguimiento que te da el SICDE.</p>
          <form class="formulario" method="post" action="<?= url($base . '/sicde') ?>">
            <?= $csrf ?>
            <div class="pos-campos">
              <label>Folio de seguimiento del SICDE<input type="text" name="folio_sicde" maxlength="40" value="<?= e($ev['folioSicde']) ?>" <?= $editable ? 'required' : 'disabled' ?>></label>
            </div>
            <?php if ($editable): ?><div class="acciones-formulario"><button class="btn btn-primario" type="submit"><?= $ev['folioSicde'] !== '' ? 'Actualizar folio' : 'Guardar folio' ?></button></div><?php endif; ?>
          </form>
          <h3 class="pos-sub">Para cerrar</h3>
          <ul>
            <li>Todos los sinodales firman electrónicamente el acta dentro de las 24 horas siguientes al examen.</li>
            <li>Tu director debe subir tu tesis al Repositorio Institucional (RI) en los 3 días siguientes a la aprobación.</li>
          </ul>
          <?php
          $constancias = array_filter(['constancias_revisores', 'constancias_direccion', 'no_adeudo', 'integracion'], static fn(string $c): bool => isset($ev['generados'][$c]));
          if ($constancias): ?>
            <h3 class="pos-sub">Documentos emitidos</h3>
            <ul>
              <?php foreach ($constancias as $c): ?><li><?php if (Flujo::estadoFirma($firmas, $c) === 'firmado'): ?><a href="<?= url('posgrado/generado/' . (int) $ev['generados'][$c]['id']) ?>" target="_blank" rel="noopener"><?= e(Catalogo::emitibles()[$c]['nombre']) ?></a><?php else: ?><?= e(Catalogo::emitibles()[$c]['nombre']) ?> <span class="pos-estado pos-e-rev">En firma de la Coordinación</span><?php endif; ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </details>

  <details class="pos-momento">
    <summary>Historial <small>· <?= count($eventos) ?> movimientos</small></summary>
    <div class="pos-momento-cuerpo">
      <?php if (!$eventos): ?><p class="texto-secundario">Sin movimientos.</p><?php endif; ?>
      <?php foreach ($eventos as $evt): ?>
        <div class="pos-evento"><time datetime="<?= e((string) $evt['creado_en']) ?>"><?= e(fecha_corta((string) $evt['creado_en'])) ?> · <?= e((string) $evt['autor']) ?></time><?= e((string) $evt['detalle']) ?></div>
      <?php endforeach; ?>
    </div>
  </details>
</div>
<script>
// Abre los apartados plegados que contienen el destino de un enlace interno (#req-…, #datos, #sinodo…).
(function () {
  function abrir() {
    var id = decodeURIComponent(location.hash.slice(1));
    var el = id ? document.getElementById(id) : null;
    for (var n = el; n; n = n.parentElement) { if (n.tagName === 'DETAILS') { n.open = true; } }
    if (el) { el.scrollIntoView({block: 'start'}); }
  }
  window.addEventListener('hashchange', abrir);
  if (location.hash) { abrir(); }
})();
</script>
