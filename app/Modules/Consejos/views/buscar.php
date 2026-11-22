<?php
/** @var string $q @var string $modo @var string $campo @var ?int $reunionSel @var string $grupo @var array $reuniones @var array $res */
use App\Modules\Consejos\Services\Html;
include __DIR__ . '/_estilos.php';
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab"><div><h1>Buscar en las actas</h1><p class="cns-sub">Busca en las solicitudes, antecedentes y acuerdos de todas las reuniones. <strong>Comodines:</strong> <code>*</code> sustituye cualquier cantidad de letras (<code>sabátic*</code>) y <code>?</code> una sola letra (<code>Garc?a</code>). No importan mayúsculas ni acentos.</p></div></div>
    <form class="cns-form" method="get" action="<?= e(url('consejos/buscar')) ?>" role="search" style="margin-top:12px">
      <label>Palabra o frase<input type="search" name="q" value="<?= e($q) ?>" maxlength="200" placeholder="año sabático   ·   comit* curricular   ·   no se aprueba" autofocus></label>
      <div class="cns-fila">
        <label>Cómo buscar<select name="modo"><option value="frase"<?= $modo === 'frase' ? ' selected' : '' ?>>La frase tal cual</option><option value="palabras"<?= $modo === 'palabras' ? ' selected' : '' ?>>Todas las palabras (en cualquier orden)</option></select></label>
        <label>Dónde<select name="campo"><option value="todo"<?= $campo === 'todo' ? ' selected' : '' ?>>Solicitudes y acuerdos</option><option value="solicitud"<?= $campo === 'solicitud' ? ' selected' : '' ?>>Solo solicitudes y antecedentes</option><option value="acuerdos"<?= $campo === 'acuerdos' ? ' selected' : '' ?>>Solo acuerdos</option></select></label>
        <label>Reunión<select name="reunion"><option value="">Todas</option><?php foreach ($reuniones as $r): ?><option value="<?= (int) $r['id'] ?>"<?= $reunionSel === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['etiqueta']) ?></option><?php endforeach; ?></select></label>
        <label>Tipo de caso<select name="grupo"><option value="">Todos</option><option value="profesor"<?= $grupo === 'profesor' ? ' selected' : '' ?>>Profesores</option><option value="alumno"<?= $grupo === 'alumno' ? ' selected' : '' ?>>Alumnos</option><option value="general"<?= $grupo === 'general' ? ' selected' : '' ?>>Asuntos generales</option></select></label>
      </div>
      <div class="cns-acciones"><button class="btn btn-primario" type="submit"><?= icono('buscar', 16) ?> Buscar</button></div>
    </form>
  </section>

  <?php if ($q !== ''): ?>
    <p class="cns-sub" role="status"><?= $res['total'] ? $res['total'] . ' caso' . ($res['total'] > 1 ? 's' : '') . ' encontrado' . ($res['total'] > 1 ? 's' : '') . ($res['recortado'] ? ' (se muestran los primeros ' . $res['total'] . '; afina la búsqueda)' : '') . '.' : 'No se encontró nada con esa búsqueda. Prueba con menos palabras o con un comodín (*).' ?></p>
    <?php foreach ($res['filas'] as $f): ?>
      <article class="cns-res">
        <div class="cns-cab" style="gap:6px">
          <strong><a href="<?= e(url('consejos/reunion/' . $f['reunion_id'])) ?>"><?= e($f['etiqueta']) ?></a> · <span class="cns-clave"><?= e($f['clave']) ?></span></strong>
          <small><?= $f['gobierno_fecha'] ? e(date('d/m/Y', strtotime($f['gobierno_fecha']))) : '' ?></small>
        </div>
        <?php foreach ($f['fragmentos'] as $fr): ?>
          <p><small><?= e($fr['campo']) ?>:</small> <?= $fr['html'] /* ya escapado y con <mark> */ ?></p>
        <?php endforeach; ?>
        <p><a class="btn btn-mini" href="<?= e(url('consejos/asunto/' . $f['id'] . '/editar')) ?>"><?= !empty($f['cerrada']) ? 'Ver el caso (cerrado)' : 'Abrir el caso' ?></a></p>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
