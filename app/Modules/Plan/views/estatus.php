<?php
$overview = $estado;
$projects = $overview['proyectos'];
?>
<div class="plan-contenedor plan-estatus">
  <div class="plan-bloque-cabeza">
    <div><p class="plan-ceja">Planeación FI 360 · seguimiento</p><h1>Estatus de los proyectos</h1>
      <p>Consulta los responsables, los insumos pendientes y los hitos de redacción de cada proyecto.</p></div>
    <a class="plan-btn plan-btn-claro" href="<?= e(url('plan')) ?>">Volver al editor</a>
  </div>
  <?php if (isset($_GET['error'])): ?><p class="plan-encuesta-error" role="alert"><?= e((string)$_GET['error']) ?></p><?php endif; ?>
  <?php if (isset($_GET['guardado'])): ?><p class="plan-encuesta-exito" role="status">Estatus guardado como nueva revisión del proyecto.</p><?php endif; ?>
  <div class="plan-estatus-resumen"><strong><?= e((string)$overview['completados']) ?> / <?= e((string)$overview['total']) ?></strong><span>proyectos completados</span>
    <div class="plan-encuesta-barra"><span style="width:<?= $overview['total'] ? round(100*$overview['completados']/$overview['total']) : 0 ?>%"></span></div>
  </div>
  <p class="plan-fuente"><?= e($overview['nota_autoria']) ?> Cada marca guardada funciona como checkpoint de la revisión de esa persona. Para completar el proyecto se requieren seis: FODA propio, FODA elegido para el diagnóstico, FODA enviado a revisión metodológica, diagnóstico editado, insumos declarados listos y plantilla terminada. Cada checkpoint aporta la misma proporción al porcentaje. Marcar una casilla no ejecuta ni sustituye la revisión metodológica.</p>
  <?php if (!$puedeEditar): ?><p class="plan-aviso-privacidad">Para marcar encuestas listas o una plantilla terminada, <a href="<?= e(url('login')) ?>">inicia sesión con una cuenta de personal</a>.</p><?php endif; ?>
  <div class="plan-estatus-lista">
  <?php foreach ($projects as $project): ?>
    <section class="plan-estatus-proyecto" id="proyecto-<?= (int)$project['numero'] ?>">
      <div class="plan-estatus-cabecera">
        <div><p class="plan-ceja">Pilar <?= e($project['pilar']) ?> · Proyecto <?= (int)$project['numero'] ?></p>
          <h2><?= e($project['nombre']) ?></h2>
          <p><?= $project['completado'] ? 'Completado' : ($project['responsables'] ? 'En elaboración' : 'Sin revisiones') ?> · avance mayor de los responsables: <?= (int)$project['avance'] ?> %</p>
        </div>
        <a class="plan-btn plan-btn-claro" href="<?= e(url('plan') . '?proyecto=' . (int)$project['numero'] . '&tab=matriz&seccion=foda') ?>">Abrir proyecto</a>
      </div>
      <div class="plan-encuesta-barra"><span style="width:<?= (int)$project['avance'] ?>%"></span></div>
      <details>
        <summary>Responsables, encuestas y reunión · <?= count($project['responsables']) ?> responsable(s)</summary>
        <div class="plan-estatus-interior">
          <h3>Responsables y hitos</h3>
          <?php if (!$project['responsables']): ?><p>Aún no hay una revisión guardada por una persona responsable.</p><?php else: ?>
          <div class="plan-tabla-scroll"><table class="plan-estatus-tabla"><thead><tr><th>Responsable</th><th>Última revisión</th><th>FODA propio</th><th>Su FODA para diagnóstico</th><th>FODA listo para revisión</th><th>Diagnóstico editado</th><th>Insumos listos</th><th>Plantilla</th><th>Avance</th></tr></thead><tbody>
          <?php foreach ($project['responsables'] as $person): ?><tr>
            <th><?= e($person['nombre']) ?><small><?= $person['identidad'] === 'cuenta' ? 'Cuenta registrada' : 'Nombre declarado' ?></small></th>
            <td><?= e($person['fecha']) ?></td>
            <?php foreach (['foda_propio','foda_elegido','foda_revision_solicitada','diagnostico_editado','insumos_listos','plantilla_completada'] as $step): ?><td><?= $person['hitos'][$step] ? '✓' : 'Pendiente' ?></td><?php endforeach; ?>
            <td><?= (int)$person['avance'] ?> %</td>
          </tr><?php endforeach; ?></tbody></table></div>
          <?php endif; ?>
          <h3>Encuestas necesarias para este FODA</h3>
          <?php if (!$project['encuestas']): ?><p>Este proyecto no tiene cuestionarios pendientes citados en el borrador.</p><?php else: ?>
            <?php foreach ($project['encuestas'] as $survey): ?>
              <div class="plan-estatus-insumo"><div><strong><?= e($survey['id'] . ' · ' . $survey['titulo']) ?></strong>
                <p><?= (int)$survey['respondidas'] ?> / <?= $survey['meta'] === null ? 'meta por definir' : (int)$survey['meta'] ?> respuestas · <?= $survey['porcentaje'] === null ? 'avance por definir' : e((string)$survey['porcentaje']) . ' %' ?></p>
                <p><?= e($survey['resumen']) ?></p>
                <small>Preguntas citadas: <?= e(implode(', ', array_map(static fn($x) => '{' . $x . '}', $survey['preguntas']))) ?>. Procesamiento: <?= $survey['procesada'] ? 'procesado y documentado' : 'pendiente' ?>.</small>
                <p>Responsables que marcaron datos listos: <?= $survey['responsables_listos'] ? e(implode(', ', $survey['responsables_listos'])) : 'ninguno todavía' ?>.</p></div>
                <?php if ($survey['datos_disponibles']): ?>
                  <form method="post" action="<?= e(url('plan/estatus')) ?>" class="plan-estatus-marcar">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="tipo" value="encuesta"><input type="hidden" name="proyecto" value="<?= (int)$project['numero'] ?>"><input type="hidden" name="cuestionario" value="<?= e($survey['id']) ?>"><input type="hidden" name="marcar" value="0">
                    <label><input type="checkbox" name="marcar" value="1" <?= in_array($survey['id'], $project['mis_encuestas_listas'], true) ? 'checked' : '' ?> <?= (!$puedeEditar || !$project['mi_revision']) ? 'disabled' : '' ?>> Revisé los datos de esta encuesta y están listos para la interpretación</label>
                    <small><strong>Checkpoint en Status:</strong> guarda tu confirmación. El checkpoint «Insumos listos» se completa cuando confirmas todas las encuestas requeridas y registras los resultados de reunión solicitados. No significa que la interpretación ya esté elaborada.</small>
                    <button type="submit" class="plan-btn plan-btn-claro" <?= (!$puedeEditar || !$project['mi_revision']) ? 'disabled' : '' ?>>Guardar checkpoint</button>
                    <?php if ($puedeEditar && !$project['mi_revision']): ?><small>Guarda primero una revisión del proyecto con tu cuenta.</small><?php endif; ?>
                  </form>
                <?php else: ?><p class="plan-fuente">La casilla aparecerá al recibir datos suficientes.</p><?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <h3>Reunión de coordinaciones</h3>
          <p>Asuntos requeridos: <?= $project['agenda'] ? e(implode(', ', array_map(static fn($x) => '{' . $x . '}', $project['agenda']))) : 'Ninguno citado' ?>.</p>
          <?php $meetings = array_values(array_filter($project['responsables'], static fn($p) => $p['reunion_resultados'] !== '')); ?>
          <?php if (!$meetings): ?><p>Sin resultados de reunión registrados para este proyecto.</p><?php else: foreach ($meetings as $person): ?>
            <div class="plan-estatus-reunion"><strong><?= e($person['nombre']) ?><?= $person['reunion_fecha'] ? ' · ' . e($person['reunion_fecha']) : '' ?></strong><p><?= nl2br(e($person['reunion_resultados'])) ?></p></div>
          <?php endforeach; endif; ?>
          <p>Interpretación FODA: <?= $project['interpretacion_foda'] ? 'elaborada y disponible en el proyecto' : 'pendiente de procesamiento de encuestas y revisión de reunión' ?>.</p>
          <h3>Archivo de la plantilla</h3>
          <form method="post" action="<?= e(url('plan/estatus')) ?>" class="plan-estatus-plantilla">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="tipo" value="plantilla"><input type="hidden" name="proyecto" value="<?= (int)$project['numero'] ?>"><input type="hidden" name="marcar" value="0">
            <label><input type="checkbox" name="marcar" value="1" <?= $project['mi_plantilla_completada'] ? 'checked' : '' ?> <?= (!$puedeEditar || !$project['mi_revision']) ? 'disabled' : '' ?>> Ya terminé el archivo de la plantilla</label>
            <small><strong>Checkpoint en Status:</strong> se completa únicamente al marcar la casilla, escribir el nombre o enlace del archivo y pulsar «Guardar checkpoint».</small>
            <label>Nombre o enlace del archivo<input type="text" name="referencia" maxlength="500" value="<?= e((string)$project['mi_plantilla_referencia']) ?>" <?= (!$puedeEditar || !$project['mi_revision']) ? 'disabled' : '' ?>></label>
            <button type="submit" class="plan-btn plan-btn-claro" <?= (!$puedeEditar || !$project['mi_revision']) ? 'disabled' : '' ?>>Guardar checkpoint</button>
          </form>
        </div>
      </details>
    </section>
  <?php endforeach; ?>
  </div>
</div>
