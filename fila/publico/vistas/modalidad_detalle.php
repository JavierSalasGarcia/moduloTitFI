<p><strong><?= !empty($atencion['videoconferencia'])?'Videoconferencia por Teams':'Atención presencial' ?></strong></p>
<?php if (!empty($atencion['videoconferencia'])): ?>
<?php if (!empty($atencion['enlace_teams'])): ?><p><a class="boton btn" href="<?= u_escapar($atencion['enlace_teams']) ?>" target="_blank" rel="noopener noreferrer">Unirse a la reunión de Teams</a></p>
<?php else: ?><p>Sin enlace de reunión: el titular de la oficina llamará por Teams a <strong><?= u_escapar($atencion['email']??'tu correo institucional') ?></strong> cuando llegue el turno. Mantén Teams disponible.</p><?php endif; ?>
<?php endif; ?>
