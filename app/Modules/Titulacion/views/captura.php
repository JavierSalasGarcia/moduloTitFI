<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\Captura;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$siglas = array_map(static fn(array $c): string => $c['siglas'][0], Catalogo::carreras());
$modalidades = array_map(static fn(array $m): string => $m['nombre'], Catalogo::modalidades());
$e = $editar ?? [];
$v = static fn(string $k): string => e((string) ($e[$k] ?? ''));
$fecha = static fn(?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '';
?>
<style>
.cap-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.cap-form .ancho{grid-column:1/-1}
.cap-form label{display:flex;flex-direction:column;gap:4px}
.cap-form label.cap-check{flex-direction:row;align-items:center;gap:8px}
.cap-form input[type=text]{width:100%}
.cap-form textarea{min-height:58px}
.cap-falta{display:inline-block;margin:1px 2px;padding:0 6px;border-radius:9px;background:#FDE2E2;color:#991B1B;font-size:11px;white-space:nowrap}
.cap-tabla td{vertical-align:top;font-size:13px}
.cap-tabla .titulo{max-width:340px}
.cap-resumen{display:flex;gap:18px;flex-wrap:wrap;align-items:center}
.cap-resumen strong{font-size:22px}
</style>

<div class="tit-cabecera"><div>
  <p class="texto-secundario">Fichas de los trámites de titulación que se atienden en papel hasta que se inaugure la plataforma: temas aprobados, prórrogas, afinidades para Créditos en Estudios Avanzados y trabajos escritos en revisión. Una ficha está completa cuando tiene nombre, número de cuenta, licenciatura y modalidad; con trabajo escrito, además, título, asesor y fecha de aprobación, dictamen o examen; por créditos, el posgrado afín y la fecha de afinidad. Las marcadas «por confirmar» tienen datos contradictorios en los oficios (se explican en Observaciones) y cuentan como incompletas. Cuando el alumno presente examen, «Pasar al historial» lo lleva al libro de actas, donde se generan las constancias. Al inaugurar la plataforma, la captura se cierra (solo lectura) y cada ficha se vincula desde el expediente del alumno por su número de cuenta.</p>
</div></div>

<section class="tarjeta" style="margin-bottom:14px">
  <div class="cap-resumen">
    <span><strong><?= (int) $resumen['total'] ?></strong> fichas</span>
    <span><strong style="color:#B91C1C"><?= (int) $resumen['incompletas'] ?></strong> incompletas</span>
    <span><strong><?= (int) ($resumen['total'] - $resumen['incompletas']) ?></strong> completas</span>
    <?php if ($cerrada): ?><span class="tit-chip mal">Captura cerrada (solo lectura)</span><?php endif; ?>
    <span style="flex:1"></span>
    <a class="btn" href="<?= url('titulacion/captura/exportar') ?>">Exportar CSV</a>
    <?php if ($puedeCerrar): ?>
      <form method="post" action="<?= url('titulacion/captura/cerrar') ?>" onsubmit="return confirm(<?= e(json_encode($cerrada ? '¿Reabrir la captura transitoria?' : '¿Cerrar la captura? Úsalo al inaugurar la plataforma: las fichas quedan en solo lectura y se vinculan desde el expediente de cada alumno.')) ?>)">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="cerrar" value="<?= $cerrada ? '0' : '1' ?>">
        <button class="btn" type="submit"><?= $cerrada ? 'Reabrir captura' : 'Cerrar captura (inauguración)' ?></button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if (!$cerrada || $editar): ?>
<section class="tarjeta" style="margin-bottom:14px" <?= $editar ? 'id="ficha"' : 'data-disclosure="Agregar una ficha nueva"' ?>>
  <?php if ($editar): ?><h2 style="margin-top:0"><?= $cerrada ? 'Ficha' : 'Editar ficha' ?> #<?= (int) $editar['id'] ?></h2><?php endif; ?>
  <form method="post" action="<?= url('titulacion/captura/guardar') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= $v('id') ?>">
    <fieldset class="cap-form" style="border:0;padding:0;margin:0" <?= $cerrada ? 'disabled' : '' ?>>
    <label>Estado del trámite <select name="estado" required><?php foreach (Captura::ESTADOS as $k => $n): ?><option value="<?= e($k) ?>" <?= ($e['estado'] ?? 'tema_aprobado') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Licenciatura <select name="carrera"><option value="">—</option><?php foreach ($carreras as $k => $n): ?><option value="<?= e($k) ?>" <?= ($e['carrera'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Plantel <select name="sistema"><?php foreach (Catalogo::sistemas() as $k => $n): ?><option value="<?= e($k) ?>" <?= ($e['sistema'] ?? 'fi') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Modalidad <select name="modalidad"><option value="">—</option><?php foreach ($modalidades as $k => $n): ?><option value="<?= e($k) ?>" <?= ($e['modalidad'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Nombre completo del alumno <input type="text" name="alumno" maxlength="160" required value="<?= $v('alumno') ?>"></label>
    <label>Número de cuenta <input type="text" name="cuenta" maxlength="10" inputmode="numeric" pattern="[0-9]{6,10}" value="<?= $v('cuenta') ?>"></label>
    <label>Clave de registro <input type="text" name="clave_registro" maxlength="30" value="<?= $v('clave_registro') ?>" placeholder="IEL-202504281"></label>
    <label class="ancho">Título del trabajo <textarea name="titulo" maxlength="500"><?= $v('titulo') ?></textarea></label>
    <label>Asesor o director <input type="text" name="asesor" maxlength="160" value="<?= $v('asesor') ?>" placeholder="Grado y nombre"></label>
    <label>Coasesor <input type="text" name="coasesor" maxlength="160" value="<?= $v('coasesor') ?>"></label>
    <label>Revisores del protocolo (uno por renglón) <textarea name="revisores_protocolo" maxlength="600"><?= $v('revisores_protocolo') ?></textarea></label>
    <label>Revisores del trabajo escrito (uno por renglón) <textarea name="revisores_trabajo" maxlength="600"><?= $v('revisores_trabajo') ?></textarea></label>
    <label class="ancho">Posgrado afín (solo Créditos en Estudios Avanzados) <input type="text" name="programa_cea" maxlength="300" value="<?= $v('programa_cea') ?>" placeholder="Programa e institución"></label>
    <?php foreach (['fecha_tema', 'fecha_prorroga', 'fecha_afinidad', 'fecha_dictamen', 'fecha_examen'] as $k): ?>
      <label><?= e(Captura::CAMPOS[$k][0]) ?> <input type="date" name="<?= e($k) ?>" value="<?= $v($k) ?>"></label>
    <?php endforeach; ?>
    <label class="ancho">Observaciones <textarea name="observaciones" maxlength="4000"><?= $v('observaciones') ?></textarea></label>
    <label class="ancho">Documento de origen <input type="text" name="fuente" maxlength="300" value="<?= $v('fuente') ?>" placeholder="Oficio, constancia o fecha de recepción"></label>
    <label class="ancho cap-check"><input type="checkbox" name="revisar" value="1" <?= !empty($e['revisar']) ? 'checked' : '' ?>> Por confirmar (hay datos contradictorios; explícalo en Observaciones)</label>
    <div class="ancho acciones-formulario">
      <?php if (!$cerrada): ?><button class="btn btn-primario" type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar ficha' ?></button><?php endif; ?>
      <?php if ($editar): ?><a class="btn" href="<?= url('titulacion/captura') ?>">Cancelar</a><?php endif; ?>
    </div>
    </fieldset>
  </form>
  <?php if ($editar && !$cerrada): ?>
    <div class="acciones-formulario">
      <?php if (!$editar['historico_id']): ?>
        <form method="post" action="<?= url('titulacion/captura/' . (int) $editar['id'] . '/historial') ?>" onsubmit="return confirm('¿Pasar esta ficha al libro de actas? Guarda antes los cambios. Si el alumno ya está en el libro se enlaza y solo se llenan sus datos vacíos.')">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn" type="submit">Pasar al historial (ya presentó examen)</button>
        </form>
      <?php endif; ?>
      <?php if (!$editar['tramite_id']): ?>
        <form method="post" action="<?= url('titulacion/captura/' . (int) $editar['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar esta ficha?')">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn" type="submit">Eliminar ficha</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="tarjeta">
  <form method="get" action="<?= url('titulacion/captura') ?>" class="tit-campos" style="margin-bottom:10px">
    <label>Buscar <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Alumno, cuenta, título, profesor o clave"></label>
    <label>Licenciatura <select name="carrera"><option value="">Todas</option><?php foreach ($carreras as $k => $n): ?><option value="<?= e($k) ?>" <?= $f['carrera'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Estado <select name="estado"><option value="">Todos</option><?php foreach (Captura::ESTADOS as $k => $n): ?><option value="<?= e($k) ?>" <?= $f['estado'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Datos <select name="faltantes"><option value="">Todas las fichas</option><option value="incompletos" <?= $f['faltantes'] === 'incompletos' ? 'selected' : '' ?>>Incompletas</option><option value="completos" <?= $f['faltantes'] === 'completos' ? 'selected' : '' ?>>Completas</option></select></label>
    <div><button class="btn btn-primario" type="submit">Filtrar</button></div>
  </form>
  <p class="texto-secundario"><?= (int) $total ?> fichas con este filtro</p>
  <?php if (!$filas): ?><div class="ux-empty">Sin fichas<?= array_filter($f) ? ' con ese filtro' : '' ?>.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla cap-tabla">
    <thead><tr><th>Alumno</th><th>Lic.</th><th>Modalidad</th><th>Estado</th><th class="titulo">Título / posgrado</th><th>Asesor</th><th>Fechas</th><th>Falta</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($filas as $c): ?>
      <tr>
        <td><?= e($c['alumno'] ?: '(sin nombre)') ?><br><span class="texto-secundario"><?= e($c['cuenta']) ?></span></td>
        <td><?= e($siglas[$c['carrera']] ?? '—') ?><?= $c['sistema'] !== 'fi' ? '<br><span class="texto-secundario">' . e(Catalogo::sistemas()[$c['sistema']] ?? '') . '</span>' : '' ?></td>
        <td><?= e($modalidades[$c['modalidad']] ?? '—') ?></td>
        <td><?= e(Captura::ESTADOS[$c['estado']] ?? $c['estado']) ?><?php if ($c['historico_id']): ?><br><span class="tit-chip ok">En el libro de actas</span><?php endif; ?><?php if ($c['tramite_id']): ?><br><a class="tit-chip ok" href="<?= url('titulacion/gestion/' . (int) $c['tramite_id']) ?>">Vinculada</a><?php endif; ?></td>
        <td class="titulo"><?= e($c['modalidad'] === 'creditos' ? $c['programa_cea'] : $c['titulo']) ?><?php if ($c['observaciones']): ?><br><span class="texto-secundario" title="<?= e($c['observaciones']) ?>">Obs.: <?= e(mb_strimwidth($c['observaciones'], 0, 110, '…')) ?></span><?php endif; ?></td>
        <td><?= e($c['asesor']) ?><?= $c['coasesor'] ? '<br><span class="texto-secundario">Coasesor: ' . e($c['coasesor']) . '</span>' : '' ?></td>
        <td style="white-space:nowrap"><?php foreach (['fecha_tema' => 'Tema', 'fecha_prorroga' => 'Prórroga', 'fecha_afinidad' => 'Afinidad', 'fecha_dictamen' => 'Dictamen', 'fecha_examen' => 'Examen'] as $k => $et): ?><?= $c[$k] ? e($et) . ' ' . e($fecha($c[$k])) . '<br>' : '' ?><?php endforeach; ?></td>
        <td><?php foreach ($c['faltan'] as $k): ?><span class="cap-falta"><?= e($k === 'revisar' ? 'Por confirmar' : Captura::CAMPOS[$k][0]) ?></span><?php endforeach; ?></td>
        <td style="white-space:nowrap"><a class="btn btn-mini" href="<?= url('titulacion/captura?editar=' . (int) $c['id']) ?>#ficha"><?= $cerrada ? 'Ver' : 'Editar' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
