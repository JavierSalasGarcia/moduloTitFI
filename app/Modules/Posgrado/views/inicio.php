<?php
use App\Core\Auth;
use App\Modules\Posgrado\Catalogo;

include __DIR__ . '/_estilos.php';
$momentos = Catalogo::momentos();
// Los trámites en pausa van aparte, en «Abandonadas».
$abandonadas = array_values(array_filter($mis, static fn(array $m): bool => $m['estado'] === 'abandonado'));
$abiertos = array_values(array_filter($mis, static fn(array $m): bool => $m['estado'] === 'abierto'));
$mis = array_values(array_filter($mis, static fn(array $m): bool => $m['estado'] !== 'abandonado'));
$nombreMod = static fn(array $m): string => ($m['modalidad'] ?? '') !== '' ? (Catalogo::modalidades()[$m['modalidad']] ?? (string) $m['modalidad']) : 'modalidad sin elegir';
?>
<div class="pos-pila">
  <section class="tarjeta">
    <h2>Tu examen de grado, en cuatro momentos</h2>
    <p>Tú capturas tus datos, tu sínodo y la fecha; la plataforma llena los formatos y tu director, tu sínodo y la Coordinación los firman en línea, sin imprimir nada. El personal de Apoyo a Grado de la Coordinación de Estudios Avanzados revisa tus documentos y emite los oficios. Siempre verás un solo siguiente paso.</p>
    <div class="pos-tarjetas">
      <?php foreach ($momentos as $n => $m): ?>
        <div class="pos-momento-info">
          <span class="pos-num" aria-hidden="true"><?= (int) $n ?></span>
          <div><h3><?= e($m['titulo']) ?></h3><p><?= e($m['texto']) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
    <h3 class="pos-sub">Modalidades: conoce cada una antes de empezar</h3>
    <ul class="pos-lista pos-modalidades">
      <?php foreach (Catalogo::modalidades() as $clave => $nombre): ?>
        <li style="padding:3px 0"><a href="<?= url('posgrado/modalidad/' . $clave) ?>"><?= e($nombre) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <p class="texto-secundario" style="margin:6px 0 0;font-size:13px"><a href="<?= url('posgrado/manuales') ?>">Compara las modalidades: descarga sus manuales</a><?php foreach (\App\Modules\Posgrado\Services\Manuales::deLaSesion() as $manA => $manT): ?> · <a href="<?= url(\App\Modules\Posgrado\Services\Manuales::url($manA, 'consolidado')) ?>" download><?= e($manT) ?> (PDF)</a><?php endforeach; ?></p>
  </section>

  <?php if (count($abiertos) > 1): ?>
  <section class="pos-nota" aria-labelledby="pos-dos-t">
    <h2 id="pos-dos-t" style="margin:0 0 4px;font-size:18px">Tienes más de un trámite abierto</h2>
    <p style="margin:0 0 6px">Ahora solo puede seguir uno. Elige cuál dejas; el que quede será el que continúes. Si nadie más intervino en el que dejas, se borrará; si no, pasará a «Abandonadas».</p>
    <?php foreach ($abiertos as $m): ?>
      <div class="pos-aband">
        <div><strong><?= e((string) $m['folio']) ?></strong> · <?= e($nombreMod($m)) ?></div>
        <span><a class="btn btn-mini" href="<?= url('posgrado/tramite/' . (int) $m['id']) ?>">Abrir</a> <a class="btn btn-mini btn-peligro" href="<?= url('posgrado/tramite/' . (int) $m['id'] . '/dejar') ?>">Dejar este</a></span>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if ($mis): ?>
  <section class="tarjeta">
    <h2>Mis trámites</h2>
    <div class="tabla-contenedor">
      <table class="tabla">
        <thead><tr><th>Folio</th><th>Trabajo</th><th>Modalidad</th><th>Momento</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($mis as $m): ?>
          <tr>
            <td><a href="<?= url('posgrado/tramite/' . (int) $m['id']) ?>"><?= e($m['folio']) ?></a></td>
            <td><?= e($m['titulo'] ? mb_strimwidth((string) $m['titulo'], 0, 90, '…') : 'Sin título capturado') ?></td>
            <td><?= e($nombreMod($m)) ?></td>
            <td><?= $m['estado'] === 'abierto' ? e((int) $m['momento'] . '. ' . ($momentos[(int) $m['momento']]['titulo'] ?? '')) : '—' ?></td>
            <td style="white-space:nowrap"><span class="estado estado-<?= e($m['estado'] === 'concluido' ? 'concluida' : $m['estado']) ?>"><?= e(Catalogo::estados()[$m['estado']] ?? $m['estado']) ?></span><?= $m['resultado'] === 'aplazado' && $m['estado'] === 'abierto' ? ' <small class="texto-secundario">(examen aplazado)</small>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($abierto): ?><p style="margin-top:12px"><a class="btn btn-primario" href="<?= url('posgrado/tramite/' . (int) $abierto['id']) ?>">Continuar mi trámite</a></p><?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($abandonadas): ?>
  <details class="pos-momento" id="abandonadas">
    <summary>Abandonadas</summary>
    <div class="pos-momento-cuerpo">
      <p class="texto-secundario" style="margin:0 0 6px">Trámites que dejaste en pausa. Se conserva todo lo que llevabas y lo que se generó; al retomar uno sigues justo donde se quedó.</p>
      <?php foreach ($abandonadas as $m): ?>
        <div class="pos-aband">
          <div>
            <strong><?= e((string) $m['folio']) ?></strong> · <?= e($nombreMod($m)) ?>
            <p>En pausa<?= !empty($m['abandonado_en']) ? ' desde el ' . e(fecha_corta((string) $m['abandonado_en'])) : '' ?> · se quedó en «<?= e((string) ($momentos[(int) $m['momento']]['titulo'] ?? '')) ?>» · <a href="<?= url('posgrado/tramite/' . (int) $m['id']) ?>">Ver lo que llevas</a></p>
          </div>
          <?php if ($puedeIniciar): ?>
          <form method="post" action="<?= url('posgrado/tramite/' . (int) $m['id'] . '/retomar') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button class="btn btn-mini" type="submit">Retomar</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
  <?php endif; ?>

  <?php if ($puedeIniciar && !$abierto): ?>
  <section class="tarjeta" id="comenzar">
    <h2>Comenzar</h2>
    <p>Antes de empezar ten a la mano: la constancia de registro de tu protocolo (el título de tu trabajo debe coincidir), los datos de tu director de tesis y la fecha de tu examen previo. Puedes guardar tu avance y volver cuando quieras.</p>
    <form method="post" action="<?= url('posgrado/iniciar') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label class="pos-check"><input type="checkbox" name="acepto" value="1" required> Leí el <a href="<?= url('titulacion/licenciatura/blanco/privacidad') ?>" target="_blank" rel="noopener">aviso de privacidad</a> y acepto que mis datos se usen para este trámite.</label>
      <p style="margin-top:12px"><button class="btn btn-primario" type="submit">Comenzar mi trámite de grado</button></p>
    </form>
  </section>
  <?php elseif ($puedeIniciar && $abierto): ?>
  <details class="pos-dejar">
    <summary>¿Iniciaste con la modalidad equivocada? Comenzar un trámite nuevo</summary>
    <p>Solo puede haber un trámite activo. Si comienzas uno nuevo, antes te diremos qué pasará con el que tienes (se borra si nadie más intervino; si no, queda en pausa) y te pediremos confirmarlo.</p>
    <form method="post" action="<?= url('posgrado/iniciar') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label class="pos-check"><input type="checkbox" name="acepto" value="1" required> Leí el <a href="<?= url('titulacion/licenciatura/blanco/privacidad') ?>" target="_blank" rel="noopener">aviso de privacidad</a> y acepto que mis datos se usen para este trámite.</label>
      <p style="margin-top:10px"><button class="btn btn-mini" type="submit">Comenzar un trámite nuevo</button></p>
    </form>
  </details>
  <?php elseif (!$puedeIniciar): ?>
  <section class="tarjeta">
    <p>Este trámite lo inician los alumnos y egresados de los programas de posgrado de la Facultad con su cuenta institucional.</p>
    <?php if ($esPersonal): ?><p><a class="btn btn-primario" href="<?= url('posgrado/gestion') ?>">Ir a validar trámites</a> <a class="btn" href="<?= url('posgrado/configuracion') ?>">Configuración</a></p><?php endif; ?>
  </section>
  <?php endif; ?>
  <?php if ($puedeIniciar && $esPersonal): ?>
    <p class="texto-secundario"><a href="<?= url('posgrado/gestion') ?>">Ir a validar trámites</a> · <a href="<?= url('posgrado/configuracion') ?>">Configuración</a></p>
  <?php endif; ?>
</div>
