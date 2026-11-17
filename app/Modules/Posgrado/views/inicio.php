<?php
use App\Core\Auth;
use App\Modules\Posgrado\Catalogo;

include __DIR__ . '/_estilos.php';
$momentos = Catalogo::momentos();
?>
<div class="pos-pila">
  <section class="tarjeta">
    <h2>Tu examen de grado, en cuatro momentos</h2>
    <p>Tú capturas tus datos, tu sínodo y la fecha; la plataforma llena los formatos para que tu director y tu sínodo solo los firmen. El personal de Apoyo a Grado de la Coordinación de Estudios Avanzados revisa tus documentos y emite los oficios. Siempre verás un solo siguiente paso.</p>
    <div class="pos-tarjetas">
      <?php foreach ($momentos as $n => $m): ?>
        <div class="pos-momento-info">
          <span class="pos-num" aria-hidden="true"><?= (int) $n ?></span>
          <div><h3><?= e($m['titulo']) ?></h3><p><?= e($m['texto']) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($mis): ?>
  <section class="tarjeta">
    <h2>Mis trámites</h2>
    <div class="tabla-contenedor">
      <table class="tabla">
        <thead><tr><th>Folio</th><th>Trabajo</th><th>Momento</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($mis as $m): ?>
          <tr>
            <td><a href="<?= url('posgrado/tramite/' . (int) $m['id']) ?>"><?= e($m['folio']) ?></a></td>
            <td><?= e($m['titulo'] ? mb_strimwidth((string) $m['titulo'], 0, 90, '…') : 'Sin título capturado') ?></td>
            <td><?= $m['estado'] === 'abierto' ? e((int) $m['momento'] . '. ' . ($momentos[(int) $m['momento']]['titulo'] ?? '')) : '—' ?></td>
            <td><span class="estado estado-<?= e($m['estado'] === 'concluido' ? 'concluida' : $m['estado']) ?>"><?= e(Catalogo::estados()[$m['estado']] ?? $m['estado']) ?></span><?= $m['resultado'] === 'aplazado' && $m['estado'] === 'abierto' ? ' <small class="texto-secundario">(examen aplazado)</small>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($abierto): ?><p style="margin-top:12px"><a class="btn btn-primario" href="<?= url('posgrado/tramite/' . (int) $abierto['id']) ?>">Continuar mi trámite</a></p><?php endif; ?>
  </section>
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
