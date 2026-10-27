<?php
use App\Core\Auth;
use App\Modules\Concursos\Concursos;
$csrf = e(Auth::csrfToken());
$base = 'concursos/' . $c['slug'];
$cuenta = array_count_values(array_column($obras, 'estado'));
$abierto = in_array($fase, ['recepcion', 'revision'], true);
$nuevasEnVotacion = $fase === 'votacion' && Concursos::recepcionAbierta($c);
?>
<?php include __DIR__ . '/_estilos.php'; ?>
<style>.cv-verso{white-space:pre-wrap;font-family:Georgia,'Times New Roman',serif;font-size:15px;line-height:1.5;margin:8px 0}.cv-obra{border-top:1px solid var(--gris-200,#ddd);padding:14px 0}</style>
<section class="tarjeta" style="margin-bottom:16px">
  <div class="tarjeta-cabecera"><h2><?= e($c['titulo']) ?></h2><a class="btn btn-mini" href="<?= url($base) ?>">Ver convocatoria</a></div>
  <p class="texto-secundario">Las obras se muestran sin el nombre de su autor. Aprueba las que cumplen las bases; a las que no, escribe el motivo: se le envía al autor sin decir quién revisó. <?= $abierto ? 'Puedes cambiar un dictamen hasta que empiece la votación.' : ($nuevasEnVotacion ? 'La votación ya empezó: puedes aprobar las obras nuevas que sigan llegando mientras la recepción esté abierta; de las ya aprobadas solo se puede retirar la aprobación.' : 'La votación ya empezó: solo se puede retirar de la votación una obra (no aprobarla).') ?></p>
  <p><strong><?= (int) ($cuenta['enviada'] ?? 0) ?></strong> por revisar · <strong><?= (int) ($cuenta['aprobada'] ?? 0) ?></strong> aprobadas · <strong><?= (int) ($cuenta['rechazada'] ?? 0) ?></strong> no aprobadas · fase: <?= e(Concursos::FASES[$fase]) ?></p>
</section>

<?php if ($conteo): ?>
<section class="tarjeta" id="resultados" style="margin-bottom:16px">
  <h2>Resultados de la votación</h2>
  <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Lugar</th><th>Obra</th><th>Votos</th></tr></thead><tbody>
    <?php foreach ($conteo as $f): ?><tr><td><?= (int) $f['lugar'] ?></td><td>N.º <?= (int) $f['numero'] ?> · <?= e($f['titulo']) ?></td><td><?= (int) $f['votos'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if ($empates && $fase === 'computo'): ?>
    <h3 style="margin-top:14px">Empates por resolver</h3>
    <p class="texto-secundario">Hay obras con los mismos votos en los tres primeros lugares. Indica su orden (1 = queda arriba) antes de la premiación.</p>
    <form method="post" action="<?= url($base . '/desempate') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <?php foreach ($empates as $g): ?>
        <fieldset style="margin-bottom:10px"><legend><?= (int) $g[0]['votos'] ?> votos</legend>
          <?php foreach ($g as $i => $f): ?>
            <label style="display:flex;gap:8px;align-items:center">Posición <input type="number" min="1" max="<?= count($g) ?>" name="orden[<?= (int) $f['id'] ?>]" value="<?= (int) ($f['desempate'] ?? $i + 1) ?>" style="width:70px"> N.º <?= (int) $f['numero'] ?> · <?= e($f['titulo']) ?></label>
          <?php endforeach; ?>
        </fieldset>
      <?php endforeach; ?>
      <button class="btn btn-primario" type="submit">Guardar orden</button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="tarjeta">
  <h2>Obras recibidas</h2>
  <?php if (!$obras): ?><div class="ux-empty">Aún no hay obras.</div><?php endif; ?>
  <?php foreach ($obras as $o): ?>
    <div class="cv-obra" id="obra-<?= (int) $o['id'] ?>">
      <h3>N.º <?= (int) $o['numero'] ?> · <?= e($o['titulo']) ?>
        <span class="tit-chip <?= $o['estado'] === 'aprobada' ? 'ok' : ($o['estado'] === 'rechazada' ? 'mal' : 'rev') ?>"><?= e(['enviada' => 'Por revisar', 'aprobada' => 'Aprobada', 'rechazada' => 'No aprobada'][$o['estado']] ?? $o['estado']) ?></span></h3>
      <small class="texto-secundario">Recibida el <?= e(fecha_corta((string) $o['creado_en'])) ?><?= $o['actualizado_en'] ? ' · corregida el ' . e(fecha_corta((string) $o['actualizado_en'])) : '' ?></small>
      <div class="cv-verso"><?= e($o['texto']) ?></div>
      <?php if ($o['estado'] === 'rechazada' && $o['motivo']): ?><p class="tit-obs"><?= e($o['motivo']) ?></p><?php endif; ?>
      <?php if ($abierto || ($fase === 'votacion' && ($o['estado'] === 'aprobada' || ($nuevasEnVotacion && $o['estado'] === 'enviada')))): ?>
        <form method="post" action="<?= url($base . '/prefiltro/' . (int) $o['id']) ?>" class="formulario" style="margin-top:8px">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <label>Motivo (si no se aprueba; lo recibe el autor) <textarea name="motivo" rows="2" maxlength="600"><?= e((string) ($o['motivo'] ?? '')) ?></textarea></label>
          <div class="acciones-formulario">
            <?php if (($abierto || ($nuevasEnVotacion && $o['estado'] === 'enviada')) && $o['estado'] !== 'aprobada'): ?><button class="btn btn-primario btn-mini" name="dictamen" value="aprobada" type="submit">Aprobar</button><?php endif; ?>
            <?php if ($o['estado'] !== 'rechazada'): ?><button class="btn btn-mini" name="dictamen" value="rechazada" type="submit">No aprobar</button><?php endif; ?>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
