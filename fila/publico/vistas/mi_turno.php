<section class="tarjeta angosta">
  <p class="meta"><?= u_escapar($dep['nombre']) ?></p>
  <h1>Turno <?= u_escapar(folio_publico($dep,$turno)) ?></h1>
  <?php $advertencia=in_array($turno['estado'],['espera','llamado'],true) ? advertencia_otras_filas((int)$dep['id'],$turno['email'],$turno['estado']==='llamado' ? a_momento($turno['llamado']) : ($turno['estimado']??null)) : ''; ?>
  <?php if ($advertencia): ?><p class="estado aviso" role="alert"><?= u_escapar($advertencia) ?></p><?php endif; ?>

  <?php if ($turno['estado'] === 'cancelado'): ?>
    <p class="estado aviso">Este turno quedó cancelado.</p>
    <p><a class="boton" href="<?= ruta($dep['clave'].'/turno') ?>">Tomar otro turno</a></p>
  <?php elseif ($turno['estado'] === 'atendido'): ?>
    <p class="estado bien">Atendido. Gracias por venir.</p>
  <?php elseif ($turno['estado'] === 'ausente'): ?>
    <p class="estado aviso">Se te llamó y no estabas. Puedes tomar otro turno.</p>
    <p><a class="boton" href="<?= ruta($dep['clave'].'/turno') ?>">Tomar otro turno</a></p>
  <?php elseif ($turno['estado'] === 'llamado'): ?>
    <p class="estado bien grande"><?= !empty($turno['videoconferencia'])?'Es tu turno. Prepárate para la atención por Teams.':'Es tu turno. Pasa a la oficina.' ?></p>
  <?php else: ?>
    <p class="hora-grande">
      <?= isset($turno['estimado']) && $turno['estimado'] ? u_hora($turno['estimado']) : 'sin hora todavía' ?>
    </p>
    <p>Hora aproximada de atención,
      <?= u_escapar(u_espera_en_palabras($turno['espera'] ?? null)) ?>.
      <?php if ($adelante > 0): ?>Hay <?= (int) $adelante ?> persona(s) antes que tú.<?php endif; ?>
    </p>
    <p class="meta">La hora se mueve si una reunión se alarga; te avisamos por correo si cambia mucho.</p>
    <p class="aviso">Si no estás presente cuando te toque, se atenderá a quien siga y
      tendrás que formarte de nuevo.</p>
    <form method="post" class="formulario">
      <?= campo_csrf() ?>
      <input type="hidden" name="accion" value="cancelar">
      <button class="secundario">No podré llegar, cancelar mi turno</button>
    </form>
  <?php endif; ?>

  <p class="meta">Asunto: <?= u_escapar($turno['asunto']) ?> · <?= (int) $turno['minutos'] ?> minutos</p>
  <div class="acciones-minimas">
    <a class="boton boton-bloque" href="<?= ruta($dep['clave'] . '/fila') ?>">Ver la fila completa</a>
  </div>
</section>
<?php if (in_array($turno['estado'], ['espera', 'llamado'], true)): ?>
<script>setTimeout(() => location.reload(), 30000);</script>
<?php endif; ?>
