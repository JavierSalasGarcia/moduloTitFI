<?php use App\Core\Auth; ?>
<p><a class="enlace" href="<?= url('firmas') ?>"><?= icono('flecha-izq', 14) ?> Volver a firmas</a></p>

<div class="barra-acciones">
  <p class="texto-suave">Grupos que puedes administrar. Todos los grupos institucionales (NAB, CAP, CC, Cuerpos Académicos) están disponibles al crear una solicitud.</p>
</div>

<section class="card" style="max-width:760px" data-disclosure="Crear grupo">
  <header class="tarjeta-cabecera"><h2>Crear grupo</h2></header>
  <form method="post" action="<?= url('firmas/grupos') ?>" class="formulario">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <label>Nombre del grupo <span class="req">*</span>
      <input type="text" name="nombre" required maxlength="160" placeholder='Ej. "Comité evaluación Jessica, DCTBC"'>
    </label>
    <?php if ($puedeCrearCuerpo || Auth::rol() === 'admin'): ?>
      <label>Tipo
        <select name="tipo">
          <option value="personal">Personal (solo tú lo usas y editas)</option>
          <option value="cuerpo_academico">Cuerpo Académico (institucional)</option>
        </select>
      </label>
    <?php endif; ?>
    <div class="acciones-formulario">
      <button type="submit" class="btn btn-primario">Crear grupo</button>
    </div>
  </form>
</section>

<?php if (!$grupos): ?>
  <div class="card"><p class="texto-suave">No administras ningún grupo todavía.</p></div>
<?php endif; ?>

<div data-filter-cards>
<?php foreach ($grupos as $g): ?>
  <section class="card" style="max-width:760px" data-ux-card data-disclosure="<?= e($g['nombre']) ?>">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($g['nombre']) ?></h2>
        <small class="texto-suave">
          <?= e(strtoupper($g['tipo'] === 'cuerpo_academico' ? 'Cuerpo Académico' : $g['tipo'])) ?>
          <?php if ($g['programa']): ?> · <?= e($g['programa']) ?><?php endif; ?>
          · <?= (int) $g['total'] ?> integrante(s)
        </small>
      </div>
      <?php if (in_array($g['tipo'], ['personal', 'cuerpo_academico'], true)): ?>
        <form method="post" action="<?= url('firmas/grupos/' . $g['id'] . '/eliminar') ?>" data-confirm="¿Eliminar el grupo «<?= e($g['nombre']) ?>»?">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button type="submit" class="btn">Eliminar</button>
        </form>
      <?php endif; ?>
    </header>

    <?php if (!empty($miembros[$g['id']])): ?>
      <ul class="lista-simple">
        <?php foreach ($miembros[$g['id']] as $m): ?>
          <li style="display:flex;justify-content:space-between;align-items:center;gap:8px">
            <span><?= e($m['nombre']) ?> <small class="texto-suave"><?= e($m['email']) ?></small></span>
            <form method="post" action="<?= url('firmas/grupos/' . $g['id'] . '/quitar') ?>">
              <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
              <input type="hidden" name="usuario_id" value="<?= (int) $m['id'] ?>">
              <button type="submit" class="btn" title="Quitar del grupo"><?= icono('cerrar', 14) ?></button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="texto-suave">Sin integrantes todavía.</p>
    <?php endif; ?>

    <form method="post" action="<?= url('firmas/grupos/' . $g['id'] . '/miembros') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Agregar integrante
        <input list="dl-usuarios" name="usuario" autocomplete="off" required placeholder="Nombre o email institucional…">
      </label>
      <div class="acciones-formulario">
        <button type="submit" class="btn btn-primario">Agregar</button>
      </div>
    </form>
  </section>
<?php endforeach; ?>

</div>
<datalist id="dl-usuarios">
  <?php foreach ($usuarios as $u): ?>
    <option value="<?= e($u['nombre']) ?> — <?= e($u['email']) ?>"></option>
  <?php endforeach; ?>
</datalist>
