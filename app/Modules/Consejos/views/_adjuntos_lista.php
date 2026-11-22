<?php
/** Lista de adjuntos con su descripción. Variables: $lista (filas de consejos_adjuntos), $quitar (bool: botón para retirar; no usar dentro de otro formulario). */
use App\Core\Auth;
$quitar = !empty($quitar);
if (!$lista) {
    return;
}
?>
<ul style="list-style:none;margin:6px 0 10px;padding:0;display:grid;gap:6px">
<?php foreach ($lista as $ad): ?>
  <li style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;border:1px solid var(--gris-100);border-radius:var(--radio);padding:6px 10px;background:#fff">
    <span class="cns-etq <?= $ad['tipo'] === 'oficio' ? 'ok' : '' ?>"><?= $ad['tipo'] === 'oficio' ? 'Oficio original' : 'Anexo' ?></span>
    <span style="flex:1;min-width:180px"><strong><?= e((string) $ad['descripcion']) ?></strong><br><small class="cns-ayuda"><?= e((string) $ad['nombre']) ?> · <?= e(number_format(((int) $ad['bytes']) / 1048576, 2)) ?> MB</small></span>
    <a class="btn btn-mini" href="<?= e(url('consejos/adjunto/' . $ad['id'])) ?>" target="_blank" rel="noopener">Abrir</a>
    <?php if ($quitar): ?>
    <form method="post" action="<?= e(url('consejos/adjunto/' . $ad['id'] . '/quitar')) ?>" style="display:inline" onsubmit="return confirm('¿Quitar este documento?')"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Quitar</button></form>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
