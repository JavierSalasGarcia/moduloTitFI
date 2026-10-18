<?php use App\Core\Auth; ?>
<div class="barra-acciones">
  <nav class="pestanas" aria-label="Filtro de alumnos">
    <a class="pestana<?= $filtro === 'todos' ? ' activa' : '' ?>" href="<?= url('alumnos') ?>">Todos</a>
    <a class="pestana<?= $filtro === 'licenciatura' ? ' activa' : '' ?>" href="<?= url('alumnos?programa=licenciatura') ?>">Licenciatura</a>
    <a class="pestana<?= $filtro === 'posgrado' ? ' activa' : '' ?>" href="<?= url('alumnos?programa=posgrado') ?>">Posgrado</a>
  </nav>
  <form method="get" action="<?= url('alumnos') ?>" class="buscador">
    <?= icono('buscar', 16) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o correo…">
    <?php if ($filtro !== 'todos'): ?><input type="hidden" name="programa" value="<?= e($filtro) ?>"><?php endif; ?>
  </form>
</div>

<div class="card">
  <header class="tarjeta-cabecera"><h2>Programas educativos</h2></header>
  <div class="grupo-chips">
    <?php $nivelAnterior = ''; foreach ($programas as $p): ?>
      <?php if ($p['nivel'] !== $nivelAnterior): $nivelAnterior = $p['nivel']; ?>
        <span class="texto-suave" style="width:100%;text-transform:uppercase;font-weight:700;font-size:11px;margin-top:4px"><?= e($p['nivel']) ?></span>
      <?php endif; ?>
      <a class="chip <?= $filtro === $p['siglas'] ? 'estado-resuelta' : 'badge-oro' ?>"
         href="<?= url('alumnos?programa=' . $p['siglas']) ?>"
         title="<?= e($p['nombre']) ?>">
        <?= e($p['nombre']) ?> (<?= e($p['siglas']) ?>) · <?= (int) $p['alumnos'] ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card sin-relleno tabla-contenedor">
  <table class="tabla">
    <thead>
      <tr><th>Programa</th><th>Nombre</th><th class="oculta-movil">Correo</th><th class="oculta-movil">Nivel</th></tr>
    </thead>
    <tbody>
      <?php if (!$alumnos): ?>
        <tr><td colspan="4" class="texto-suave">No hay alumnos con este filtro.</td></tr>
      <?php endif; ?>
      <?php foreach ($alumnos as $a): ?>
        <tr>
          <td><span class="badge badge-verde"><?= e($a['siglas']) ?></span></td>
          <td><?= e($a['nombre']) ?></td>
          <td class="oculta-movil"><?= e($a['email']) ?></td>
          <td class="oculta-movil texto-suave"><?= e($a['nivel']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($puedeAlta): ?>
  <section class="card" style="max-width:720px" data-disclosure="Registrar un alumno">
    <header class="tarjeta-cabecera"><h2>Alta de alumno</h2></header>
    <form method="post" action="<?= url('alumnos') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <div class="form-rejilla">
        <label>Nombre completo <span class="req">*</span>
          <input type="text" name="nombre" required maxlength="120">
        </label>
        <label>Correo institucional <span class="req">*</span>
          <input type="email" name="email" required pattern=".*[uU][aA][eE][mM][eE][xX]\.[mM][xX].*" title="El correo debe contener uaemex.mx" placeholder="cuenta@alumno.uaemex.mx">
        </label>
        <label>Programa <span class="req">*</span>
          <select name="programa" required>
            <?php foreach ($programas as $p): ?>
              <option value="<?= (int) $p['id'] ?>"><?= e($p['siglas']) ?> · <?= e($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="acciones-formulario">
        <button type="submit" class="btn btn-primario">Registrar alumno</button>
      </div>
    </form>
  </section>
<?php endif; ?>
