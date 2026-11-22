<?php
/** @var array $reuniones */
include __DIR__ . '/_estilos.php';
$estados = ['preparacion' => 'En preparación', 'academico' => 'Consejo Académico', 'gobierno' => 'Consejo de Gobierno', 'cerrada' => 'Concluida'];
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <h1>Consejos</h1>
        <p class="cns-sub">Captura los casos del mes, conduce la sesión del Consejo Académico y la del Consejo de Gobierno, y genera las actas y los dictámenes en Word.</p>
      </div>
      <div class="cns-acciones">
        <a class="btn" href="<?= e(url('consejos/buscar')) ?>"><?= icono('buscar', 16) ?> Buscar en las actas</a>
        <a class="btn btn-primario" href="<?= e(url('consejos/nueva')) ?>">Nueva reunión</a>
      </div>
    </div>
  </section>

  <?php if (!$reuniones): ?>
    <section class="tarjeta" style="text-align:center;padding:28px 16px">
      <h2 style="margin:0 0 6px;font-size:18px">Aún no hay reuniones</h2>
      <p class="cns-sub">Crea la primera: se parte de los datos de la mesa (presidente, secretario y consejeros) y después agregas los casos.</p>
    </section>
  <?php else: ?>
    <section class="tarjeta">
      <table class="cns-tabla">
        <thead><tr><th>Reunión</th><th>Consejo Académico</th><th>Consejo de Gobierno</th><th>Casos</th><th>Oficios</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($reuniones as $r): ?>
          <tr>
            <td><a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>"><strong><?= e($r['etiqueta']) ?></strong></a><br><small><?= e(ucfirst($r['tipo_sesion'])) ?></small></td>
            <td><?= $r['academico_fecha'] ? e(date('d/m/Y', strtotime($r['academico_fecha']))) : '—' ?></td>
            <td><?= $r['gobierno_fecha'] ? e(date('d/m/Y', strtotime($r['gobierno_fecha']))) : '—' ?></td>
            <td><?= (int) $r['asuntos'] ?></td>
            <td><?= (int) $r['oficios'] ?></td>
            <td><span class="cns-etq <?= $r['estado'] === 'cerrada' ? 'ok' : '' ?>"><?= e($estados[$r['estado']] ?? $r['estado']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</div>
