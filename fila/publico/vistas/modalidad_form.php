<fieldset data-modalidad><legend>Modalidad de atención</legend>
<label><input type="checkbox" name="videoconferencia" value="1" <?= !empty($datos['videoconferencia'])?'checked':'' ?>> Videoconferencia por Teams</label>
<p data-modalidad-estado role="status" aria-live="polite"><strong><?= !empty($datos['videoconferencia'])?'La reunión será por videoconferencia en Teams.':'La reunión será presencial.' ?></strong></p>
<div data-modalidad-enlace <?= empty($datos['videoconferencia'])?'hidden':'' ?>>
<label>Enlace de la reunión de Teams (opcional)<input type="url" name="enlace_teams" maxlength="2048" placeholder="https://teams.microsoft.com/…" value="<?= u_escapar($datos['enlace_teams']??'') ?>"></label>
<p class="meta">Si no hay enlace, el titular te llamará por Teams al llegar tu turno, usando tu correo institucional. Mantén Teams disponible.</p>
</div></fieldset>
<script>(()=>{const bloque=document.currentScript.previousElementSibling;const casilla=bloque.querySelector('[name="videoconferencia"]');function actualizar(){bloque.querySelector('[data-modalidad-enlace]').hidden=!casilla.checked;bloque.querySelector('[name="enlace_teams"]').disabled=!casilla.checked;bloque.querySelector('[data-modalidad-estado] strong').textContent=casilla.checked?'La reunión será por videoconferencia en Teams.':'La reunión será presencial.';}casilla.addEventListener('change',actualizar);actualizar();})();</script>
