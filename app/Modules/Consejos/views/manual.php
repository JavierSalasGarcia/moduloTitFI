<?php
/** @var array $plantillas */
include __DIR__ . '/_estilos.php';
$apartados = ['profesor' => ['Casos de profesores', 'Trámites que presenta o solicita una persona de la planta académica.'],
    'alumno' => ['Casos de alumnos', 'Trámites de estudiantes, egresados y pasantes; siempre con número de cuenta.'],
    'general' => ['Asuntos generales', 'Comisiones, calendarios, informes, distinciones y asuntos propios de los Consejos.']];
// El texto de la plantilla con los datos por llenar resaltados.
$resaltar = static fn(string $t): string => preg_replace('/\[([^\[\]]+)\]/u', '<mark>[$1]</mark>', e($t));
?>
<style>
  .man h2{margin:0 0 6px;font-size:20px;color:var(--verde-900)}
  .man h3{margin:0 0 4px;font-size:16px}
  .man .pasos{counter-reset:p;list-style:none;margin:10px 0 0;padding:0;display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(230px,1fr))}
  .man .pasos li{counter-increment:p;border:1px solid var(--gris-100);border-radius:var(--radio);padding:12px 14px 12px 52px;position:relative;background:#fff}
  .man .pasos li::before{content:counter(p);position:absolute;left:12px;top:12px;width:28px;height:28px;border-radius:50%;background:var(--verde-900);color:#fff;font-weight:800;display:grid;place-items:center}
  .man .formula{display:grid;gap:6px;margin:10px 0 0}
  .man .formula div{border-left:4px solid var(--verde-900);background:var(--verde-050);padding:8px 12px;border-radius:0 var(--radio) var(--radio) 0}
  .man details{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;margin:8px 0}
  .man summary{cursor:pointer;padding:10px 14px;font-weight:700;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .man details[open] summary{border-bottom:1px solid var(--gris-100)}
  .man .cuerpo{padding:10px 14px 14px;display:grid;gap:10px}
  .man .ejemplo{background:#f8faf8;border:1px dashed var(--gris-100);border-radius:var(--radio);padding:10px 12px;line-height:1.55}
  .man mark{background:#fff2b3;padding:0 2px;border-radius:3px}
  .man .etq-g{font-size:12px;font-weight:700;background:#fde8e8;color:#8a1f1f;border-radius:999px;padding:2px 9px}
  .man dl{margin:0;display:grid;gap:4px}
  .man dt{font-weight:700;font-size:13px;color:var(--verde-900)}
  .man dd{margin:0}
  .man ul.datos{margin:4px 0 0;padding-left:20px}
  .man .avisos{display:grid;gap:8px;margin:8px 0 0;padding:0;list-style:none}
  .man .avisos li{padding:8px 12px;border-radius:var(--radio);background:#fffbeb;border:1px solid #f1e1a6}
  @media print{
    .man details{break-inside:avoid}
    .man details:not([open]) > *:not(summary){display:block}
    .cns-solo-pantalla{display:none !important}
  }
</style>
<div class="cns-pila man">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos')) ?>">← Consejos</a></p>
        <h1>Manual de captura de casos</h1>
        <p class="cns-sub">Guía para los departamentos: cómo registrar un caso para el H. Consejo Académico y el H. Consejo de Gobierno, con la redacción que usan las actas de 2026.</p>
      </div>
      <div class="cns-acciones cns-solo-pantalla">
        <a class="btn btn-primario" href="<?= e(url('consejos/solicitudes/nueva')) ?>">Redactar una solicitud</a>
        <button class="btn" type="button" id="abrir-todo">Abrir todos los trámites</button>
        <button class="btn" type="button" onclick="window.print()">Imprimir</button>
      </div>
    </div>
  </section>

  <section class="tarjeta">
    <h2>Si eres profesor o coordinador: redacta tu solicitud en la plataforma</h2>
    <p class="cns-sub">En <strong>Constancias → Solicitudes a Consejos</strong> puedes redactar tu solicitud con el asistente de plantillas, sin esperar a que alguien más la capture.</p>
    <ol class="pasos">
      <li><h3>Redacta</h3>Elige el tipo de trámite, llena lo resaltado y anota los antecedentes. Puedes guardar un borrador.</li>
      <li><h3>Elige la coordinación</h3>Por acuerdo del H. Consejo de Gobierno del 19 de noviembre de 2026 (AG28), toda solicitud lleva el Vo. Bo. o de enterado de la Coordinación de Docencia respectiva o de la de Estudios Avanzados.</li>
      <li><h3>La coordinación revisa</h3>Recibe un aviso. Si hay algo que corregir, hace observaciones y te la regresa; la corriges, anotas qué cambiaste y la reenvías. Puede haber varias rondas, y todo queda en el historial.</li>
      <li><h3>Pasa a la cartera</h3>Cuando está bien, la coordinación da su Vo. Bo. o de enterado y la solicitud pasa a la cartera de casos para el Consejo; el secretario académico y su asistente reciben el aviso y la llevan a una reunión. El sistema genera además el oficio en hoja membretada.</li>
      <li><h3>Firma</h3>Si la firma es autógrafa, imprime el oficio, que firmen tú y la coordinación, y sube el escaneado. Si está habilitada la firma interna, se firma dentro de la plataforma.</li>
    </ol>
  </section>

  <section class="tarjeta">
    <h2>1. El recorrido de un caso</h2>
    <ol class="pasos">
      <li><h3>Se captura</h3>Antes de la sesión, desde la reunión del mes: eliges el apartado (profesor, alumno o general) y el tipo de trámite.</li>
      <li><h3>Se analiza en el Consejo Académico</h3>Se proyecta el caso; el Consejo decide si avala, avala con condición, o no avala, y el acuerdo se redacta en pantalla.</li>
      <li><h3>Se aprueba en el Consejo de Gobierno</h3>Son los mismos casos: Gobierno aprueba el acuerdo del Académico. Los asuntos propios de Gobierno se marcan en su plantilla.</li>
      <li><h3>Se firma el acta y se notifica</h3>Con el acta firmada el caso se cierra y ya no se edita. Después se puede notificar el dictamen a los interesados.</li>
    </ol>
  </section>

  <section class="tarjeta">
    <h2>2. Cómo capturar un caso, paso a paso</h2>
    <ol class="pasos">
      <li><h3>Entra a la reunión</h3>Consejos → la reunión del mes → los botones «+ Profesor», «+ Alumno» o «+ General» (o «+ Agregar» dentro de cada apartado).</li>
      <li><h3>Elige el tipo de trámite</h3>El menú «Tipo de trámite» rellena la solicitud con la redacción oficial. Si no hay uno parecido, usa «Otro» o escribe libremente.</li>
      <li><h3>Llena lo resaltado</h3>Sustituye cada dato entre <mark>[corchetes]</mark> (nombre, cuenta, periodo…). Borra las opciones que no apliquen, por ejemplo «[Examen Especial / Examen por Competencias]».</li>
      <li><h3>Completa los antecedentes</h3>Es la lista de documentos que se anexan («Oficio de solicitud»). Las tablas de Word se pegan con Ctrl+C / Ctrl+V.</li>
      <li><h3>Menciona a los profesores</h3>Escribe <strong>@</strong> y parte del nombre; así el caso aparece en Comisiones. Nunca se deduce por el nombre.</li>
      <li><h3>Guarda</h3>Si queda un dato entre corchetes sin llenar, el sistema te avisa antes de guardar.</li>
    </ol>
  </section>

  <section class="tarjeta">
    <h2>3. Cómo se redacta una solicitud</h2>
    <p class="cns-sub">Toda solicitud tiene cinco partes, en este orden:</p>
    <div class="formula">
      <div><strong>1. Quién solicita y con qué calidad.</strong> «La Mtra. …, Coordinadora de Docencia de …» · «El C. …, con número de cuenta …, alumno del Plan de Estudios …»</div>
      <div><strong>2. Verbo.</strong> «solicita a los H.H. Consejos Académico y de Gobierno» · «presenta a los H.H. Consejos … para su análisis y aprobación»</div>
      <div><strong>3. Qué se pide.</strong> «el aval a…» · «el aval para…» · «autorización para…» · «la aprobación de…» · «el reconocimiento de…»</div>
      <div><strong>4. Sobre qué.</strong> La materia, el proyecto, la comisión, el periodo o la distinción, completos y sin abreviar.</div>
      <div><strong>5. Para qué o con qué fundamento.</strong> «con la finalidad de…» · «con fundamento en el Artículo …» · «en virtud de que…»</div>
    </div>
    <p style="margin:12px 0 0"><strong>Por qué importa el orden:</strong> el acuerdo del Consejo Académico repite lo que se pide a partir de la parte 3 («El H. Consejo Académico otorga el aval a / para …»). Una solicitud bien redactada produce un acuerdo correcto sin escribirlo de nuevo.</p>
  </section>

  <section class="tarjeta">
    <h2>4. Trámites con plantilla</h2>
    <p class="cns-sub">Toca un trámite para ver qué datos debes llenar y cómo queda la solicitud. Lo <mark>resaltado</mark> es lo que cambia en cada caso.</p>
    <?php foreach ($apartados as $clave => [$titulo, $desc]): ?>
      <h3 style="margin:18px 0 2px"><?= e($titulo) ?></h3>
      <p class="cns-sub" style="margin:0 0 6px"><?= e($desc) ?></p>
      <?php foreach ($plantillas as $p): if ($p['grupo'] !== $clave) { continue; } ?>
        <details>
          <summary><?= e($p['nombre']) ?><?php if ($p['gobierno'] && !str_contains($p['nombre'], 'solo Consejo')): ?><span class="etq-g">Solo Consejo de Gobierno</span><?php endif; ?></summary>
          <div class="cuerpo">
            <div>
              <strong>Datos que debes llenar</strong>
              <?php if ($p['datos']): ?><ul class="datos"><?php foreach ($p['datos'] as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul><?php else: ?><p style="margin:4px 0 0">Ninguno: la plantilla está completa; solo ajusta el texto a tu caso.</p><?php endif; ?>
            </div>
            <div><strong>Así queda la solicitud</strong><div class="ejemplo"><?= $resaltar($p['solicitud']) ?></div></div>
            <dl>
              <dt>Documentos y/o antecedentes</dt><dd><?= e($p['antecedentes']) ?></dd>
              <?php if ($p['tabla']): ?><dt>Tabla que se arma sola</dt><dd>Columnas: <?= e(implode(' · ', $p['tabla'])) ?>. Agrega una fila por cada persona o asunto.</dd><?php endif; ?>
              <?php if ($p['gobierno']): ?><dt>Dónde se presenta</dt><dd>Solo en el Consejo de Gobierno: se captura como caso nuevo en esa sesión.</dd><?php endif; ?>
            </dl>
          </div>
        </details>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </section>

  <section class="tarjeta">
    <h2>5. Cuidados frecuentes</h2>
    <ul class="avisos">
      <li><strong>No dejes corchetes.</strong> Si aparece «[nombre]» en el acta, quedó un dato sin llenar.</li>
      <li><strong>Número de cuenta siempre en los trámites de alumnos.</strong> Es lo que identifica a la persona; no basta el nombre.</li>
      <li><strong>Un caso, un trámite.</strong> Si hay varios alumnos o materias del mismo trámite, van en la tabla del mismo caso, no en casos separados.</li>
      <li><strong>Anexa el oficio.</strong> Escribe en antecedentes los documentos que acompañan la solicitud; las tablas de Word se pegan tal cual.</li>
      <li><strong>Un caso cerrado no se edita.</strong> Una vez subida el acta firmada solo se puede consultar. Corrige antes de la sesión o por Fe de erratas.</li>
      <li><strong>Altas durante la sesión son la excepción.</strong> Los casos se capturan al preparar la reunión; en la sesión del Consejo de Gobierno solo se agregan asuntos propios o urgentes.</li>
    </ul>
  </section>

  <section class="tarjeta">
    <h2>6. Qué pasa después con el acuerdo</h2>
    <dl>
      <dt>Consejo Académico</dt><dd>Se avala («otorga el aval a / para …»), se avala con condición o se turna («solicita que …»), o no se avala («no otorga el aval … en virtud de que …»). En los informes «para conocimiento» se usa «se da por enterado».</dd>
      <dt>Consejo de Gobierno</dt><dd>Lo habitual es «Se aprueba el acuerdo del H. Consejo Académico.». Los asuntos propios de Gobierno llevan su propia fórmula («El H. Consejo de Gobierno aprueba …»).</dd>
      <dt>Dictamen</dt><dd>Con el acta firmada, la Subdirección puede notificar el dictamen a los interesados con un enlace que pide iniciar sesión. A quién se notifica lo decide quien captura; las actas no lo fijan.</dd>
    </dl>
  </section>
</div>
<script>
  document.getElementById('abrir-todo')?.addEventListener('click', e => {
    const ds = document.querySelectorAll('.man details'), abrir = [...ds].some(d => !d.open);
    ds.forEach(d => d.open = abrir); e.target.textContent = abrir ? 'Cerrar todos los trámites' : 'Abrir todos los trámites';
  });
  window.addEventListener('beforeprint', () => document.querySelectorAll('.man details').forEach(d => d.open = true));
</script>
