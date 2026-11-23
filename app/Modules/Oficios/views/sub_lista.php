<?php
/** @var array $filas @var string $estado @var string $q @var bool $puedeFirmar @var int $siguiente */
use App\Modules\Oficios\OficiosAccess;
use App\Modules\Oficios\Services\OficiosSub;
?>
<div class="barra-acciones" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
  <nav class="grupo-chips" aria-label="Estado">
    <a class="chip<?= $estado === '' ? ' estado-en_atencion' : '' ?>" href="<?= e(url('oficios/subdireccion')) ?>">Todos</a>
    <?php foreach (OficiosSub::ESTADOS as $k => $t): ?><a class="chip<?= $estado === $k ? ' estado-en_atencion' : '' ?>" href="<?= e(url('oficios/subdireccion?estado=' . $k)) ?>"><?= e($t) ?></a><?php endforeach; ?>
  </nav>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (OficiosAccess::puedeConfigurar()): ?><a class="btn" href="<?= e(url('oficios/subdireccion/configuracion')) ?>">Configuración</a><?php endif; ?>
    <a class="btn" href="<?= e(url('oficios/confianza')) ?>">Kit de confianza del SAT</a>
    <a class="btn btn-primario" href="<?= e(url('oficios/subdireccion/nuevo')) ?>">+ Nuevo oficio</a>
  </div>
</div>

<form method="get" action="<?= e(url('oficios/subdireccion')) ?>" role="search" style="margin:12px 0">
  <?php if ($estado !== ''): ?><input type="hidden" name="estado" value="<?= e($estado) ?>"><?php endif; ?>
  <label class="sr-only" for="of-q">Buscar oficios</label>
  <input id="of-q" type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Buscar por número, asunto o destinatario…" style="width:100%;max-width:520px">
</form>
<p class="texto-suave" style="margin:0 0 10px">El siguiente número disponible es <strong>ACAD/FI/<?= str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT) ?>/<?= date('Y') ?></strong>. Se asigna al enviar el oficio a firma.</p>

<?php if (!$filas): ?>
  <div class="card"><p class="texto-suave" style="margin:0">No hay oficios con ese filtro. Crea el primero con «Nuevo oficio».</p></div>
<?php else: ?>
<div class="card sin-relleno tabla-contenedor">
  <table class="tabla" data-no-cards>
    <thead><tr><th>Número</th><th>Fecha</th><th>Asunto</th><th>Destinatarios</th><th>Estado</th><th>Redactó</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): $dest = json_decode((string) $f['destinatarios'], true) ?: []; ?>
      <tr>
        <td class="monoespacio"><a href="<?= e(url('oficios/subdireccion/' . (int) $f['id'])) ?>"><?= e($f['numero_texto'] ?: 'Sin número') ?></a></td>
        <td><?= $f['fecha'] ? e(date('d/m/Y', strtotime((string) $f['fecha']))) : '—' ?></td>
        <td><?= e($f['asunto']) ?></td>
        <td><?= e(implode('; ', array_map(static fn($d) => (string) $d['nombre'], array_slice($dest, 0, 3)))) ?><?= count($dest) > 3 ? ' (+' . (count($dest) - 3) . ')' : '' ?></td>
        <td><span class="chip"><?= e(OficiosSub::ESTADOS[$f['estado']] ?? $f['estado']) ?></span></td>
        <td><?= e($f['autor']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
