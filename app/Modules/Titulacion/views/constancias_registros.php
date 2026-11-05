<?php
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$sec = $secciones[$s];
$qs = static function (array $extra = []) use ($s, $f): string {
    return http_build_query(array_filter(['s' => $s, 'q' => $f['q'], 'carrera' => $f['carrera'], 'tipo' => $f['tipo'], 'anio' => $f['anio'], 'revisar' => $f['revisar']] + $extra, static fn($v): bool => $v !== '' && $v !== null));
};
$paginas = max(1, (int) ceil($total / 100));
$valor = static function (string $col, mixed $v) use ($carreras): string {
    if ($v === null || $v === '') { return ''; }
    if ($col === 'carrera') { return $carreras[$v] ?? (string) $v; }
    if (str_starts_with($col, 'fecha') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)) { return date('d/m/Y', strtotime((string) $v)); }
    if ($col === 'tipo') { return str_replace('_', ' ', (string) $v); }
    return (string) $v;
};
?>
<style>
  .reg-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
  .reg-tabs a{padding:6px 12px;border:1px solid #cbd5e1;border-radius:999px;text-decoration:none;color:inherit;font-size:14px}
  .reg-tabs a.activa{background:#0f3d6e;color:#fff;border-color:#0f3d6e}
  .reg-filtros{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-bottom:10px}
  .reg-filtros label{display:flex;flex-direction:column;font-size:13px;gap:2px}
  .reg-tabla{overflow:auto;max-height:70vh}
  .reg-tabla table{border-collapse:collapse;width:100%;font-size:13px}
  .reg-tabla th{position:sticky;top:0;background:#f1f5f9;text-align:left;padding:6px 8px;white-space:nowrap}
  .reg-tabla td{padding:5px 8px;border-top:1px solid #e2e8f0;vertical-align:top}
  .reg-dudosa{background:#fef3c7}
</style>
<h1><?= e($titulo) ?></h1>
<p class="texto-secundario">Lo que el departamento llevaba en Excel (2015-2026), ya dentro de la plataforma. Es consulta: no genera constancias ni se edita aquí.</p>
<nav class="reg-tabs">
  <?php foreach ($secciones as $k => $x): ?>
    <a href="<?= url('titulacion/constancias/registros?s=' . $k) ?>" class="<?= $k === $s ? 'activa' : '' ?>"><?= e($x['titulo']) ?> (<?= (int) ($conteos[$k] ?? 0) ?>)</a>
  <?php endforeach; ?>
</nav>
<form method="get" action="<?= url('titulacion/constancias/registros') ?>" class="reg-filtros">
  <input type="hidden" name="s" value="<?= e($s) ?>">
  <label>Buscar <input name="q" value="<?= e($f['q']) ?>" maxlength="80" placeholder="Nombre, cuenta o correo"></label>
  <?php if ($sec['tiene_carrera']): ?>
    <label>Licenciatura <select name="carrera"><option value="">Todas</option><?php foreach ($carreras as $k => $n): ?><option value="<?= e($k) ?>" <?= $f['carrera'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <?php if ($tipos): ?>
    <label>Tipo <select name="tipo"><option value="">Todos</option><?php foreach ($tipos as $t): ?><option value="<?= e($t) ?>" <?= $f['tipo'] === $t ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $t)) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <label>Año <select name="anio"><option value="">Todos</option><?php foreach ($anios as $a): ?><option value="<?= (int) $a ?>" <?= $f['anio'] === (string) $a ? 'selected' : '' ?>><?= (int) $a ?></option><?php endforeach; ?></select></label>
  <?php if ($s !== 'aplazamiento'): ?><label style="flex-direction:row;align-items:center;gap:6px"><input type="checkbox" name="revisar" value="1" <?= $f['revisar'] ? 'checked' : '' ?>> Solo fechas por revisar</label><?php endif; ?>
  <button class="btn btn-primario" type="submit">Filtrar</button>
  <a class="btn" href="<?= url('titulacion/constancias/registros?s=' . $s) ?>">Limpiar</a>
</form>
<p class="texto-secundario"><?= (int) $total ?> registro(s)<?= $paginas > 1 ? ' · página ' . (int) $pagina . ' de ' . $paginas : '' ?>. Las filas en amarillo traen una fecha que el Excel no permitió decidir con certeza.</p>
<div class="reg-tabla">
  <table>
    <thead><tr><?php foreach ($sec['columnas'] as $c => $n): ?><th><?= e($n) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($filas as $r): ?>
      <tr class="<?= !empty($r['fecha_revisar']) ? 'reg-dudosa' : '' ?>">
        <?php foreach ($sec['columnas'] as $c => $n): ?><td><?= e($valor($c, $r[$c] ?? '')) ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$filas): ?><tr><td colspan="<?= count($sec['columnas']) ?>">Sin resultados.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($paginas > 1): ?>
<p>
  <?php if ($pagina > 1): ?><a class="btn" href="<?= url('titulacion/constancias/registros?' . $qs(['p' => $pagina - 1])) ?>">← Anterior</a><?php endif; ?>
  <?php if ($pagina < $paginas): ?><a class="btn" href="<?= url('titulacion/constancias/registros?' . $qs(['p' => $pagina + 1])) ?>">Siguiente →</a><?php endif; ?>
</p>
<?php endif; ?>
