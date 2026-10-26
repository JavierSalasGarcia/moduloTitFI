<?php
use App\Core\Auth;
use App\Modules\Concursos\Concursos;
$csrf = e(Auth::csrfToken());
$base = 'concursos/' . $c['slug'];
$borrador = $_SESSION['concurso_borrador'] ?? null;
unset($_SESSION['concurso_borrador']);
$usuario = Auth::usuario();
$vigentes = count(array_filter($misObras, static fn(array $o): bool => in_array($o['estado'], ['enviada', 'aprobada'], true)));
$estados = ['enviada' => 'En prefiltro', 'aprobada' => 'Aprobada: participa en la votación', 'rechazada' => 'No aprobada'];
?>
<?php include __DIR__ . '/_estilos.php'; ?>
<style>
.cv-verso{white-space:pre-wrap;font-family:Georgia,'Times New Roman',serif;font-size:15.5px;line-height:1.55;margin:8px 0 0}
.cv-fases{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 0;padding:0;list-style:none;font-size:13px}
.cv-fases li{padding:4px 10px;border-radius:999px;background:var(--gris-100,#f1f1f1);color:var(--gris-600,#555)}
.cv-fases li.actual{background:var(--verde,#2c5234);color:#fff;font-weight:600}
.cv-obra{border-top:1px solid var(--gris-200,#ddd);padding:14px 0}
.cv-recibo{border:2px dashed var(--oro,#b8860b);border-radius:10px;padding:12px 14px;margin:0 0 14px}
.cv-recibo code{font-size:20px;letter-spacing:1px}
</style>

<section class="tarjeta" style="margin-bottom:16px">
  <div class="tarjeta-cabecera">
    <h2><?= e($c['titulo']) ?></h2>
    <?php if ($gestiona): ?><a class="btn btn-mini" href="<?= url('concursos/gestion/' . (int) $c['id']) ?>">Configurar</a> <a class="btn btn-mini" href="<?= url($base . '/prefiltro') ?>">Prefiltro</a><?php endif; ?>
  </div>
  <?php if ($c['estado'] === 'borrador'): ?><p class="tit-aviso">Borrador: solo lo ve quien gestiona los concursos. Publícalo desde «Configurar».</p><?php endif; ?>
  <p style="font-size:17px"><?= e($c['resumen']) ?></p>
  <ol class="cv-fases" aria-label="Fases del concurso">
    <?php foreach (['recepcion' => 'Recepción', 'revision' => 'Prefiltro', 'votacion' => 'Votación', 'computo' => 'Cómputo', 'resultados' => 'Resultados'] as $k => $n): ?>
      <li class="<?= $fase === $k ? 'actual' : '' ?>"><?= e($n) ?></li>
    <?php endforeach; ?>
  </ol>
</section>

<?php if ($fase === 'resultados'): ?>
<section class="tarjeta" id="resultados" style="margin-bottom:16px">
  <h2>Resultados</h2>
  <?php if (!$conteo): ?><p class="texto-secundario">No hubo obras en la votación.</p><?php endif; ?>
  <?php foreach (array_slice($conteo, 0, 3) as $f): ?>
    <div class="cv-obra">
      <h3><?= ['', '🥇 Primer lugar', '🥈 Segundo lugar', '🥉 Tercer lugar'][$f['lugar']] ?> · <?= e($f['titulo']) ?></h3>
      <p><strong><?= e($f['autor']) ?></strong> · <?= (int) $f['votos'] ?> voto(s) · premio: <?= e(Concursos::monto($c['premio_' . $f['lugar']])) ?></p>
      <div class="cv-verso"><?= e($f['texto']) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (count($conteo) > 3): ?>
    <details style="margin-top:10px"><summary>Todas las obras de la votación</summary>
      <?php foreach (array_slice($conteo, 3) as $f): ?>
        <div class="cv-obra"><h4>N.º <?= (int) $f['numero'] ?> · <?= e($f['titulo']) ?> <span class="texto-secundario">· <?= (int) $f['votos'] ?> voto(s)</span></h4><div class="cv-verso"><?= e($f['texto']) ?></div></div>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>
  <p style="margin-top:12px"><a href="<?= url($base . '/recibos') ?>">Verifica tu voto con tu recibo</a></p>
</section>
<?php endif; ?>

<?php if ($fase === 'votacion'): ?>
<section class="tarjeta" id="votacion" style="margin-bottom:16px">
  <h2>Votación</h2>
  <?php if ($recibo): ?>
    <div class="cv-recibo" role="status">
      <strong>Tu voto quedó registrado por la obra N.º <?= (int) $recibo['numero'] ?>.</strong> Este es tu recibo; guárdalo, no se vuelve a mostrar:
      <div><code><?= e($recibo['recibo']) ?></code></div>
      <small class="texto-secundario">Con él puedes comprobar tu voto en la <a href="<?= url($base . '/recibos?recibo=' . rawurlencode($recibo['recibo'])) ?>">página de verificación</a>. El recibo no está ligado a tu cuenta.</small>
    </div>
  <?php endif; ?>
  <p class="texto-secundario">Lee las obras y vota por tu favorita. Tienes un voto y es anónimo: la plataforma registra que ya votaste, pero no por cuál obra. Las obras se muestran sin el nombre de su autor, en orden aleatorio.</p>
  <?php if (!$usuario): ?><p class="tit-aviso"><a href="<?= url('login') ?>">Inicia sesión</a> en Traza FI para votar.</p>
  <?php elseif ($yaVoto && !$recibo): ?><p class="tit-ok">Ya votaste en este concurso. Gracias.</p><?php endif; ?>
  <?php $orden = $conteo; shuffle($orden); $propias = array_column($misObras, 'numero'); ?>
  <?php foreach ($orden as $f): ?>
    <div class="cv-obra" id="obra-<?= (int) $f['numero'] ?>">
      <h3>N.º <?= (int) $f['numero'] ?> · <?= e($f['titulo']) ?></h3>
      <div class="cv-verso"><?= e($f['texto']) ?></div>
      <?php if ($usuario && !$yaVoto && !in_array((int) $f['numero'], array_map('intval', $propias), true)): ?>
        <form method="post" action="<?= url($base . '/votar') ?>" style="margin-top:8px" onsubmit="return confirm('¿Votar por la obra N.º <?= (int) $f['numero'] ?>? Solo tienes un voto.')">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="numero" value="<?= (int) $f['numero'] ?>">
          <button class="btn btn-primario btn-mini" type="submit">Votar por esta</button>
        </form>
      <?php elseif (in_array((int) $f['numero'], array_map('intval', $propias), true)): ?><p class="texto-secundario" style="margin-top:6px">Es tu obra.</p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$conteo): ?><p class="texto-secundario">No hay obras en la votación.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($fase === 'computo'): ?>
  <section class="tarjeta" style="margin-bottom:16px"><p>La votación cerró. Los resultados se publican el <strong><?= e(Concursos::fechaHora($c['premiacion'])) ?></strong>. Mientras tanto puedes <a href="<?= url($base . '/recibos') ?>">verificar tu voto</a>.</p></section>
<?php endif; ?>

<?php if ($usuario && $fase === 'recepcion' && $c['estado'] === 'publicado'): ?>
<section class="tarjeta" id="participar" style="margin-bottom:16px">
  <h2>Participa</h2>
  <?php if ($vigentes >= (int) $c['max_por_persona']): ?>
    <p class="texto-secundario">Ya enviaste <?= $vigentes ?> obra(s), el máximo por persona. Puedes corregir o retirar las que aún no se revisan.</p>
  <?php else: ?>
    <form method="post" action="<?= url($base . '/obra') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <label>Título <input name="titulo" maxlength="120" required value="<?= e((string) ($borrador['titulo'] ?? '')) ?>"></label>
      <label>Tu calaverita (un verso por renglón y una línea en blanco entre estrofas; máximo <?= number_format((int) $c['max_caracteres']) ?> caracteres)
        <textarea name="texto" rows="14" maxlength="<?= (int) $c['max_caracteres'] ?>" required data-cuenta><?= e((string) ($borrador['texto'] ?? '')) ?></textarea></label>
      <small class="texto-secundario" data-contador></small>
      <p class="texto-secundario" style="font-size:13px">Al enviar tu obra aceptas las bases y el uso de tus datos para este concurso, según el aviso de privacidad de Traza FI: tu nombre solo se publica si quedas en los tres primeros lugares.</p>
      <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Enviar obra</button></div>
    </form>
  <?php endif; ?>
</section>
<?php elseif (!$usuario && $fase === 'recepcion'): ?>
  <section class="tarjeta" style="margin-bottom:16px"><p><strong>Para participar necesitas una cuenta en Traza FI.</strong> <a class="btn btn-primario btn-mini" href="<?= url('login') ?>">Iniciar sesión</a> <a class="btn btn-mini" href="<?= url('registro') ?>">Registrarme</a></p></section>
<?php endif; ?>

<?php if ($misObras): ?>
<section class="tarjeta" id="mis-obras" style="margin-bottom:16px">
  <h2>Mis obras</h2>
  <?php foreach ($misObras as $o): ?>
    <div class="cv-obra">
      <h3>N.º <?= (int) $o['numero'] ?> · <?= e($o['titulo']) ?> <span class="tit-chip <?= $o['estado'] === 'aprobada' ? 'ok' : ($o['estado'] === 'rechazada' ? 'mal' : 'rev') ?>"><?= e($estados[$o['estado']] ?? $o['estado']) ?></span></h3>
      <?php if ($o['estado'] === 'rechazada' && $o['motivo']): ?><p class="tit-obs"><?= e($o['motivo']) ?></p><?php endif; ?>
      <?php if ($o['estado'] === 'enviada' && $fase === 'recepcion'): ?>
        <details><summary>Corregir</summary>
          <form method="post" action="<?= url($base . '/obra') ?>" class="formulario">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="obra" value="<?= (int) $o['id'] ?>">
            <label>Título <input name="titulo" maxlength="120" required value="<?= e($o['titulo']) ?>"></label>
            <label>Texto <textarea name="texto" rows="12" maxlength="<?= (int) $c['max_caracteres'] ?>" required data-cuenta><?= e($o['texto']) ?></textarea></label>
            <small class="texto-secundario" data-contador></small>
            <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar corrección</button></div>
          </form>
          <form method="post" action="<?= url($base . '/obra/' . (int) $o['id'] . '/retirar') ?>" onsubmit="return confirm('¿Retirar esta obra del concurso?')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Retirar obra</button></form>
        </details>
      <?php else: ?><div class="cv-verso"><?= e($o['texto']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="tarjeta" id="bases">
  <h2>Bases</h2>
  <?= Concursos::basesHtml($c) ?>
</section>
<script>
document.querySelectorAll('[data-cuenta]').forEach(function (t) {
  var c = t.closest('form').querySelector('[data-contador]');
  function pintar() { c.textContent = t.value.length + ' de ' + t.maxLength + ' caracteres'; }
  t.addEventListener('input', pintar); pintar();
});
</script>
