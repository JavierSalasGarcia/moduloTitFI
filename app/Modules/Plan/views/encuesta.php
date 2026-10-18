<?php
$id = $encuesta['id'];
$catalog = $instrumentos;
$posted = $_POST ?? [];
$cerrada = ($catalog['encuestas_abiertas'] ?? true) !== true
    && !in_array($id, $catalog['encuestas_abiertas_ids'] ?? [], true);
?>
<div class="plan-contenedor plan-encuesta-pagina">
  <p class="plan-ceja">Consulta participativa · FI 360</p>
  <h1><?= e($id . ' · ' . $encuesta['titulo']) ?></h1>
  <p><strong>Dirigida a:</strong> <?= e($encuesta['destinatarios']) ?>.</p>
  <p>Este cuestionario aporta evidencia para el diagnóstico y el FODA del Plan de Desarrollo de Ingeniería. Selecciona una respuesta por pregunta. Las preguntas abiertas permiten describir necesidades y proponer mejoras.</p>
  <p class="plan-aviso-privacidad"><?= e($catalog['nota_privacidad']) ?></p>
  <?php if ($cerrada): ?>
    <div class="plan-encuesta-exito" role="status"><h2>Consulta cerrada</h2><p>El periodo de recepción de respuestas concluyó el <?= e((string)($catalog['fecha_cierre'] ?? '')) ?>. Las respuestas recibidas se conservaron para integrar el diagnóstico de la Facultad de Ingeniería.</p></div>
  <?php elseif ($enviado): ?>
    <div class="plan-encuesta-exito" role="status"><h2>Respuesta recibida</h2><p>Gracias por contribuir al diagnóstico de la Facultad de Ingeniería. El correo sólo se usa si decidiste proporcionarlo para recibir una invitación de seguimiento.</p></div>
    <script>try{localStorage.removeItem(<?= json_encode('fi-plan-encuesta-borrador-v1:' . $id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);}catch(error){}</script>
  <?php else: ?>
    <?php if ($error): ?><p class="plan-encuesta-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form class="plan-encuesta-form" method="post" action="<?= e(url('consulta-fi/' . $surveyToken)) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="plan-trampa" aria-hidden="true"><label>No llenar<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <?php if ($encuesta['segmentacion'] === 'carrera'): ?>
        <label class="plan-encuesta-segmento">Licenciatura a la que corresponde tu respuesta
          <select name="segmento" required><option value="">Selecciona una licenciatura</option>
            <?php foreach ($catalog['carreras'] as $career): ?><option value="<?= e($career['id']) ?>" <?= ($posted['segmento'] ?? '') === $career['id'] ? 'selected' : '' ?>><?= e($career['nombre']) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php elseif ($encuesta['segmentacion'] === 'programa'): ?>
        <label class="plan-encuesta-segmento">Programa de estudios avanzados
          <select name="segmento" required><option value="">Selecciona un programa</option>
            <?php foreach ($catalog['programas_posgrado'] as $program): ?><option value="<?= e($program) ?>" <?= ($posted['segmento'] ?? '') === $program ? 'selected' : '' ?>><?= e($program) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <?php foreach ($encuesta['preguntas'] as $question): ?>
        <?php if ($question['tipo'] === 'opcion'): ?>
          <fieldset class="plan-encuesta-pregunta"><legend><span><?= e($question['id']) ?></span> <?= e($question['texto']) ?></legend>
            <?php foreach ($question['opciones'] as $option): ?><label><input type="radio" name="<?= e($question['id']) ?>" value="<?= e($option) ?>" <?= ($posted[$question['id']] ?? '') === $option ? 'checked' : '' ?> required><span><?= e($option) ?></span></label><?php endforeach; ?>
          </fieldset>
        <?php elseif ($question['tipo'] === 'ranking_asignaturas'): ?>
          <fieldset class="plan-encuesta-pregunta plan-ranking-asignaturas" data-ranking-id="<?= e($question['id']) ?>">
            <legend><span><?= e($question['id']) ?></span> <?= e($question['texto']) ?></legend>
            <p class="plan-fuente">Las opciones se cargan del mapa curricular de la licenciatura seleccionada. No repitas una asignatura.</p>
            <?php $rankingPosted = is_array($posted[$question['id']] ?? null) ? $posted[$question['id']] : []; ?>
            <?php for ($position = 1; $position <= (int)($question['max_selecciones'] ?? 5); $position++): ?>
              <label><?= $position ?>.ª <?= $position === 1 ? 'Mayor dificultad' : 'posición' ?>
                <select name="<?= e($question['id']) ?>[]" data-ranking-position="<?= $position ?>" <?= $position === 1 ? 'required' : '' ?>><option value="">Selecciona una asignatura<?= $position > 1 ? ' (opcional)' : '' ?></option></select>
              </label>
            <?php endfor; ?>
            <script type="application/json" class="plan-ranking-opciones"><?= json_encode($question['opciones_por_segmento'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            <script type="application/json" class="plan-ranking-previo"><?= json_encode(array_values($rankingPosted), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
          </fieldset>
        <?php elseif ($question['tipo'] === 'correo'): ?>
          <label class="plan-encuesta-abierta"><span><strong><?= e($question['id']) ?></strong> <?= e($question['texto']) ?></span><input type="email" name="<?= e($question['id']) ?>" maxlength="190" autocomplete="email" value="<?= e((string)($posted[$question['id']] ?? '')) ?>" placeholder="correo@ejemplo.mx (opcional)"></label>
        <?php else: ?>
          <label class="plan-encuesta-abierta"><span><strong><?= e($question['id']) ?></strong> <?= e($question['texto']) ?></span><textarea name="<?= e($question['id']) ?>" rows="4" maxlength="2000" <?= !empty($question['obligatoria']) ? 'required' : '' ?> placeholder="<?= !empty($question['obligatoria']) ? 'Ejemplo: Cálculo diferencial, Álgebra lineal, Física' : 'Respuesta abierta opcional' ?>"><?= e((string)($posted[$question['id']] ?? '')) ?></textarea></label>
        <?php endif; ?>
      <?php endforeach; ?>
      <button type="submit" class="plan-btn plan-btn-primario">Enviar respuesta</button>
      <p id="plan-borrador-estado" class="plan-fuente" role="status" aria-live="polite">El avance se guarda automáticamente en este navegador.</p>
    </form>
    <script>
    (()=>{const career=document.querySelector('select[name="segmento"]'),box=document.querySelector('.plan-ranking-asignaturas');if(!career||!box)return;
      const options=JSON.parse(box.querySelector('.plan-ranking-opciones').textContent),previous=JSON.parse(box.querySelector('.plan-ranking-previo').textContent),selects=[...box.querySelectorAll('[data-ranking-position]')];
      const render=()=>{const list=options[career.value]||[];selects.forEach((select,index)=>{const selected=select.value||previous[index]||'';select.replaceChildren();const blank=document.createElement('option');blank.value='';blank.textContent=`Selecciona una asignatura${index?' (opcional)':''}`;select.append(blank);list.forEach(name=>{const option=document.createElement('option');option.value=name;option.textContent=name;option.selected=name===selected;select.append(option);});});};
      career.addEventListener('change',render);render();
      box.closest('form').addEventListener('submit',event=>{const chosen=selects.map(s=>s.value).filter(Boolean);if(new Set(chosen).size!==chosen.length){event.preventDefault();selects[0].setCustomValidity('No repitas una asignatura en el orden de dificultad.');selects[0].reportValidity();}else selects[0].setCustomValidity('');});
    })();
    </script>
    <script>
    (()=>{const form=document.querySelector('.plan-encuesta-form');if(!form)return;
      const key=<?= json_encode('fi-plan-encuesta-borrador-v1:' . $id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,status=document.getElementById('plan-borrador-estado');let timer;
      const controls=()=>[...form.elements].filter(field=>field.name&&!['_csrf','website'].includes(field.name)&&field.type!=='submit');
      const snapshot=()=>{const values={};controls().forEach(field=>{if((field.type==='radio'||field.type==='checkbox')&&!field.checked)return;const value=field.value;if(Object.hasOwn(values,field.name)){values[field.name]=Array.isArray(values[field.name])?[...values[field.name],value]:[values[field.name],value];}else values[field.name]=value;});return values;};
      const save=()=>{try{localStorage.setItem(key,JSON.stringify({guardado:new Date().toISOString(),respuestas:snapshot()}));status.textContent=navigator.onLine?'Avance guardado automáticamente en este navegador.':'Sin conexión. El avance quedó guardado en este navegador y podrás enviarlo al recuperar la conexión.';}catch(error){status.textContent='No fue posible guardar el avance en este navegador.';}};
      const restore=()=>{try{const draft=JSON.parse(localStorage.getItem(key)||'null');if(!draft?.respuestas)return;const values=draft.respuestas,segment=form.elements.segmento;if(segment&&Object.hasOwn(values,'segmento')){segment.value=values.segmento;segment.dispatchEvent(new Event('change'));}controls().forEach(field=>{if(!Object.hasOwn(values,field.name)||field.name==='segmento')return;const stored=Array.isArray(values[field.name])?values[field.name]:[values[field.name]];if(field.type==='radio'||field.type==='checkbox')field.checked=stored.includes(field.value);else{const same=controls().filter(item=>item.name===field.name);field.value=stored[Math.max(0,same.indexOf(field))]??'';}});status.textContent='Se recuperó el avance guardado en este navegador.';}catch(error){localStorage.removeItem(key);}};
      restore();form.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(save,250);});form.addEventListener('change',save);window.addEventListener('offline',save);window.addEventListener('online',()=>{status.textContent='Conexión restablecida. Tu avance está listo para enviarse.';});form.addEventListener('submit',save);
    })();
    </script>
  <?php endif; ?>
</div>
