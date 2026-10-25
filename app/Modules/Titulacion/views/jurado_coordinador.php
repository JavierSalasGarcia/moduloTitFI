<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$base ??= 'titulacion/jurado/t/' . $token;
$borrador = $_SESSION['_jurado_borrador'][$a['id']] ?? $propuesta;
$alumno = (string) ($t['datos']['datos']['nombre'] ?? '');
[$numProp, $numSup] = $integrantes;
$etq = [];
for ($i = 1; $i <= $numProp; $i++) { $etq[] = 'Propietario · ' . \App\Modules\Titulacion\Services\AgendaExamen::FUNCIONES[\App\Modules\Titulacion\Services\AgendaExamen::funcion($i, $numProp)]; }
for ($i = 1; $i <= $numSup; $i++) { $etq[] = 'Suplente' . ($numSup > 1 ? ' ' . $i : ''); }
include __DIR__ . '/_banner_enlace.php';
?>
<section class="tarjeta" style="max-width:760px;margin:20px auto">
  <h2>Asignación de sinodales · <?= e($t['folio']) ?></h2>
  <p><strong><?= e($alumno) ?></strong> · <?= e(\App\Modules\Titulacion\Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? '') ?><?php if ($t['titulo']): ?> · «<?= e($t['titulo']) ?>»<?php endif; ?></p>
  <p>El pasante solicitó su <strong><?= e($examen) ?></strong> para el <strong><?= e(\App\Modules\Titulacion\Services\Oficios::fecha($a['fecha'])) ?></strong>, <?= e($a['hora']) ?> h, en <?= e($a['lugar']) ?> (el espacio ya está apartado).</p>
  <?php if ($trabajo): ?><p><a class="btn btn-mini" href="<?= url(str_contains($base, '/t/') ? $base . '/trabajo' : \App\Modules\Titulacion\Services\Archivos::url('trabajo_coordinador', (int) $a['id'])) ?>" target="_blank" rel="noopener">Ver el trabajo escrito (PDF)</a></p><?php endif; ?>

  <?php if ($a['estado'] === 'firmada'): ?>
    <p class="tit-ok">Firmaste esta asignación el <?= e(fecha_corta((string) $a['firmada_en'])) ?>. Los oficios ya se generaron.</p>
    <ul class="tit-lista-simple"><?php foreach ($sinodales as $s): ?><li><?= e($s['calidad'] === 'suplente' ? 'Suplente' : 'Propietario · ' . (\App\Modules\Titulacion\Services\AgendaExamen::FUNCIONES[$s['funcion'] ?? ''] ?? '')) ?>: <?= e($s['nombre']) ?> (<?= e($s['email']) ?>)</li><?php endforeach; ?></ul>
  <?php else: ?>
    <form method="post" action="<?= url($base . '/firmar') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <p class="texto-secundario"><?= $propuesta ? 'Este es el sínodo que propuso el pasante. Ratifícalo o cambia a quien consideres; ' : '' ?>El orden de los propietarios define su función en el jurado (presidente, secretario y vocal). Busca por nombre o por correo: si el profesor está registrado se completan los datos. Si no, escríbelos.</p>
      <?php foreach ($etq as $i => $et): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px">
          <label style="flex:1 1 240px"><?= e($et) ?> · nombre
            <input type="text" name="s[<?= $i ?>][nombre]" maxlength="160" required value="<?= e((string) ($borrador[$i]['nombre'] ?? '')) ?>" data-prof="s<?= $i ?>" list="dl-prof-s<?= $i ?>" autocomplete="off"></label>
          <label style="flex:0 1 110px">Grado
            <input type="text" name="s[<?= $i ?>][grado]" maxlength="30" value="<?= e((string) ($borrador[$i]['grado'] ?? '')) ?>" data-prof-grado="s<?= $i ?>" placeholder="Dr., Mtra."></label>
          <label style="flex:0 1 130px">Género
            <select name="s[<?= $i ?>][genero]" required data-prof-genero="s<?= $i ?>"><option value="">Elige…</option><option value="masculino" <?= ($borrador[$i]['genero'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option><option value="femenino" <?= ($borrador[$i]['genero'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option></select></label>
          <label style="flex:1 1 240px">Correo
            <input type="email" name="s[<?= $i ?>][email]" maxlength="120" required value="<?= e((string) ($borrador[$i]['email'] ?? '')) ?>" data-prof-correo="s<?= $i ?>" list="dl-prof-s<?= $i ?>-c" autocomplete="off"></label>
          <datalist id="dl-prof-s<?= $i ?>"></datalist><datalist id="dl-prof-s<?= $i ?>-c"></datalist>
        </div>
      <?php endforeach; ?>
      <?php if ($puedeFirmar): ?>
        <button class="btn btn-primario" type="submit">Firmar asignación</button>
      <?php else: ?>
        <button class="btn btn-primario" type="submit">Guardar y continuar para firmar</button>
        <p class="texto-secundario">Para firmar necesitas sesión con <b><?= e($a['coord_email']) ?></b>; tus selecciones se conservan.</p>
      <?php endif; ?>
    </form>
    <script src="<?= url('assets/js/titulacion-profesores.js') ?>" data-url="<?= url('titulacion/profesores') ?>" defer></script>
  <?php endif; ?>
</section>
