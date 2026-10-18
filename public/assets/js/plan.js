(() => {
  'use strict';
  const menuButton=document.getElementById('plan-menu-movil');
  const sideMenu=document.getElementById('plan-menu-lateral');
  const menuScrim=document.querySelector('.plan-menu-scrim');
  const mobileMenu=window.matchMedia('(max-width: 900px)');
  const setMenuOpen=open=>{
    if(!menuButton||!sideMenu||!menuScrim)return;
    const expanded=Boolean(open&&mobileMenu.matches);
    sideMenu.classList.toggle('abierto',expanded);
    menuButton.setAttribute('aria-expanded',String(expanded));
    menuButton.setAttribute('aria-label',expanded?'Cerrar menú':'Abrir menú');
    menuScrim.hidden=!expanded;
    document.body.classList.toggle('plan-menu-abierto',expanded);
  };
  menuButton?.addEventListener('click',()=>setMenuOpen(!sideMenu.classList.contains('abierto')));
  menuScrim?.addEventListener('click',()=>setMenuOpen(false));
  sideMenu?.querySelectorAll('.plan-menu-seccion-titulo').forEach(button=>button.addEventListener('click',()=>{
    const section=button.closest('.plan-menu-seccion');
    const collapsed=section.classList.toggle('colapsada');
    button.setAttribute('aria-expanded',String(!collapsed));
  }));
  sideMenu?.querySelectorAll('.plan-menu-item').forEach(link=>link.addEventListener('click',()=>setMenuOpen(false)));
  document.addEventListener('keydown',event=>{if(event.key==='Escape')setMenuOpen(false);});
  mobileMenu.addEventListener?.('change',()=>setMenuOpen(false));
  const app = document.getElementById('plan-app');
  if (!app) return;
  const base = app.dataset.base.replace(/\/$/, '');
  const canEdit = app.dataset.canEdit === '1';
  const catalog = JSON.parse(document.getElementById('plan-catalogo').textContent);
  const instrumentsNode=document.getElementById('plan-instrumentos');
  const instruments=instrumentsNode?JSON.parse(instrumentsNode.textContent):{encuestas:[],agenda:[]};
  const $ = id => document.getElementById(id);
  const query=new URLSearchParams(location.search);
  const requestedTabRaw=query.get('tab');
  const requestedTab=requestedTabRaw==='foda'?'matriz':requestedTabRaw;
  const routeTargets={foda:'plan-seccion-foda',diagnostico:'plan-seccion-diagnostico','objetivo-general':'plan-seccion-objetivo-general',politicas:'plan-form-politicas','objetivos-especificos':'plan-form-especificos'};
  const targetRoutes=Object.fromEntries(Object.entries(routeTargets).map(([route,target])=>[target,route]));
  const requestedRoute=requestedTabRaw==='foda'?'foda':query.get('seccion');
  const allowedTabs=['matriz','evidencia','historial',...(canEdit?['encuestas']:[])];
  const initialTab=allowedTabs.includes(requestedTab)?requestedTab:'matriz';
  const initialTarget=initialTab==='evidencia'?'plan-tab-evidencia':initialTab==='encuestas'?'plan-tab-encuestas':initialTab==='historial'?'plan-tab-historial':routeTargets[requestedRoute]||'plan-seccion-foda';
  let pendingSection=requestedRoute&&routeTargets[requestedRoute]?initialTarget:null;
  const state = { bundle: null, category: null, section: null, indicator: null, metric: null,
    tab: initialTab, routeTarget:initialTarget };
  let mainEditor=null,crossEditor=null;
  const labels = {fortalezas:'Fortalezas', debilidades:'Debilidades', oportunidades:'Oportunidades', amenazas:'Amenazas'};
  const text = (parent, tag, value, className) => { const el=document.createElement(tag); el.textContent=value ?? ''; if(className)el.className=className; parent.appendChild(el); return el; };
  const format = n => typeof n==='number' ? new Intl.NumberFormat('es-MX',{maximumFractionDigits:2}).format(n) : String(n??'');
  const annualYears=['2027','2028','2029','2030'];
  const normalizedKey=value=>String(value??'').toUpperCase().replace(/\s+/g,'');
  const scopeName = section => section.nivel==='facultad'?'Facultad':section.nombre;

  function goalReference(goal) {
    const wanted=normalizedKey(goal?.indicador);
    return (state.bundle?.propuesta?.referencia_base_2026?.metas||[]).find(item=>normalizedKey(item.clave)===wanted)||null;
  }
  function savedProgram(goal,variable,index) {
    const saved=Array.isArray(goal?.programacion_variables)?goal.programacion_variables:[];
    return saved.find(item=>String(item?.nombre??'').trim()===String(variable?.nombre??'').trim())||saved[index]||null;
  }
  function variableSourceText(variable) {
    const source=variable?.fuente_programacion_2027;
    if(!source)return 'Base de la sugerencia de IA para 2027: estimación metodológica pendiente de documentar.';
    const files=[...(Array.isArray(source.archivos)?source.archivos:[]),source.archivo].filter(Boolean);
    const labels={tablero_uaemex:'Tablero UAEMéx',calculo_con_tablero:'Cálculo con Tablero UAEMéx',poa_2026:'POA 2026',estimacion:'Estimación'};
    const parts=[`Base de la sugerencia de IA para 2027: ${labels[source.tipo]||'Fuente documentada'}`];
    if(files.length)parts.push(files.join(', '));
    if(source.pagina)parts.push(`p. ${source.pagina}`);
    if(source.metrica)parts.push(`métrica «${source.metrica}»`);
    if(source.periodo)parts.push(`periodo ${source.periodo}`);
    if(source.valor_base!=null)parts.push(`valor base ${format(source.valor_base)}`);
    if(source.formula)parts.push(`Cálculo: ${source.formula}`);
    if(source.criterio_coincidencia)parts.push(source.criterio_coincidencia);
    return parts.join(' · ');
  }
  function renderVariableDetails(parent,goal,editable=false) {
    const reference=goalReference(goal);
    if(!reference?.variables?.length)return;
    const details=text(parent,'details','',editable?'plan-variables plan-variables-editables':'plan-variables');
    text(details,'summary','Valor de las variables y programación anual');
    text(details,'p',editable
      ?'Sugerencia generada por IA a partir de la memoria de cálculo y las series disponibles. Puedes ajustar 2027–2030; Ref. 2026 se conserva sin cambios.'
      :'Sugerencia generada por IA. Ref. 2026 es sólo lectura y la programación cubre 2027–2030.','plan-fuente');
    const scroll=text(details,'div','', 'plan-tabla-scroll'),table=text(scroll,'table','', 'plan-variables-tabla');
    const head=text(table,'thead',''),header=text(head,'tr','');
    ['Variable','Ref. 2026',...annualYears].forEach(label=>text(header,'th',label));
    const body=text(table,'tbody','');
    reference.variables.forEach((variable,index)=>{
      const row=text(body,'tr','');row.dataset.variableIndex=String(index);row.dataset.variableName=String(variable.nombre||'');
      const variableName=text(row,'th','');variableName.scope='row';
      text(variableName,'span',variable.nombre||`Variable ${index+1}`,'plan-variable-nombre');
      text(variableName,'small',variableSourceText(variable),'plan-variable-origen');
      const referenceCell=text(row,'td',variable.valor_ref_2026==null?'n.d.':format(variable.valor_ref_2026),'plan-variable-ref');referenceCell.dataset.label='Ref. 2026';
      const saved=savedProgram(goal,variable,index);
      annualYears.forEach(year=>{
        const cell=text(row,'td','');cell.dataset.label=year;const suggested=variable.programacion?.[year];const value=saved?.programacion?.[year]??suggested;
        if(editable){const input=document.createElement('input');input.type='number';input.step='any';input.min='0';input.required=true;input.dataset.year=year;input.value=value==null?'':String(value);input.setAttribute('aria-label',`${variable.nombre||'Variable'} ${year}`);cell.append(input);}
        else text(cell,'span',value==null?'n.d.':format(value));
      });
    });
    text(details,'small',reference.criterio_programacion||goal.metodo||'', 'plan-fuente');
  }

  function selectOption(select, value, label) {
    const option=document.createElement('option'); option.value=String(value); option.textContent=label; select.append(option);
  }
  function populatePillars() {
    const selector=$('plan-pilar'); selector.replaceChildren();
    catalog.forEach(p=>selectOption(selector,p.numero_romano,`Pilar ${p.numero_romano} · ${p.nombre}`));
    const requested=Number(new URLSearchParams(location.search).get('proyecto'));
    const pillar=catalog.find(p=>p.proyectos.some(x=>x.numero===requested));
    if(pillar)selector.value=pillar.numero_romano;
    populateProjects(requested);
  }
  function populateProjects(requested) {
    const pillar=catalog.find(p=>p.numero_romano===$('plan-pilar').value) || catalog[0];
    const selector=$('plan-proyecto'); selector.replaceChildren();
    pillar.proyectos.forEach(p=>selectOption(selector,p.numero,`${p.numero}. ${p.nombre}`));
    if(pillar.proyectos.some(p=>p.numero===requested))selector.value=String(requested);
    loadProject();
  }
  async function loadProject() {
    const id=$('plan-proyecto').value;
    $('plan-estado').textContent='Cargando proyecto y evidencia…';
    $('plan-detalle').hidden=true;
    try {
      const response=await fetch(`${base}/api/${id}`,{credentials:'same-origin'});
      if(!response.ok)throw new Error('No se pudieron abrir los datos del proyecto.');
      state.bundle=await response.json();
      updateLocation(id);
      renderProject(); $('plan-detalle').hidden=false;
      if(pendingSection){const target=$(pendingSection);pendingSection=null;requestAnimationFrame(()=>target?.scrollIntoView({block:'start'}));}
      $('plan-estado').textContent=`Proyecto ${id} listo · ${state.bundle.evidencia.length} categorías de evidencia`;
    } catch(error) {$('plan-estado').textContent=error.message;}
  }
  function updateLocation(id=$('plan-proyecto').value) {
    const suffix=state.tab==='matriz'?`&seccion=${targetRoutes[state.routeTarget]||'foda'}`:`&tab=${state.tab}`;
    history.replaceState(null,'',`${base}?proyecto=${id}${suffix}`);
  }
  function originalText(value) {return typeof value==='string'?value:(value?.texto_prdi||'No consta en el PRDI.');}
  function renderPrdi() {
    const source=state.bundle.prdi;
    $('plan-prdi-objetivo').textContent=originalText(source.objetivo_general);
    $('plan-prdi-diagnostico').textContent=source.diagnostico?.resumen_institucional_prdi || 'Sin diagnóstico en la fuente.';
    const detail=$('plan-prdi-detalle');detail.replaceChildren();
    (source.objetivos_especificos||[]).forEach((item,i)=>{
      const box=text(detail,'div','', 'plan-prdi-item');
      text(box,'strong',`Objetivo específico ${i+1}`);text(box,'p',originalText(item));
      (item.politicas||[]).forEach(p=>{text(box,'strong','Política');text(box,'p',originalText(p));});
      (item.estrategias||[]).forEach(s=>{text(box,'strong','Estrategia');text(box,'p',originalText(s));});
    });
    text(detail,'p',`Metas del proyecto en el PRDI: ${source.metas_totales_proyecto??'sin dato'}.`);
  }
  function renderProposal() {
    const p=state.bundle.propuesta,box=$('plan-propuesta-contenido');box.replaceChildren();
    text(box,'h4','Diagnóstico');text(box,'p',p.diagnostico);
    if(p.referencia_base_2026){const memory=text(box,'div','', 'plan-meta');text(memory,'strong','Referencia base Ref. 2026');
      const valid=p.referencia_base_2026.referencias_validas||[],zeros=p.referencia_base_2026.ceros_por_validar||[];
      text(memory,'span',`${p.referencia_base_2026.estado.replaceAll('_',' ')} · ${valid.length} valores positivos verificables · ${zeros.length} ceros por confirmar.`);
      valid.forEach(item=>text(memory,'small',`${item.clave}: ${format(item.valor)} · ${item.descripcion}.`));
      text(memory,'small',`Fuente: ${p.referencia_base_2026.referencia_apa||'Sistema de Planeación y Desarrollo Institucional. (2026a). Memoria de cálculo NS 2026–2030 (versión 4) [Hoja de cálculo no publicada]. Universidad Autónoma del Estado de México.'}`);}
    if(p.alineacion_fi360){text(box,'h4','Referencia de trabajo');const link=text(box,'a',`{Planeación FI360, ${p.alineacion_fi360.referencia}}`);link.href=p.alineacion_fi360.fuente;link.target='_blank';link.rel='noopener noreferrer';}
    text(box,'h4','Objetivo general');text(box,'p',p.objetivo_general);
    p.objetivos_especificos.forEach((item,i)=>{
      text(box,'h4',`${item.id||`OE${i+1}`} · Objetivo específico`);text(box,'p',item.texto);
      (p.politicas||[]).filter(policy=>policy.objetivos[0]===item.id).forEach(policy=>{text(box,'h4',`${policy.id} · Política`);text(box,'p',policy.texto);});
      (item.estrategias||[]).forEach(strategy=>{
        text(box,'h4',`${strategy.id} · Estrategia`);text(box,'p',strategy.texto);
        (strategy.metas||[]).forEach(goal=>{const meta=text(box,'div','', 'plan-meta');text(meta,'strong','Meta preliminar');text(meta,'span',goal.descripcion);
          if(goal.valor_base!=null)text(meta,'small',`Línea base: ${format(goal.valor_base)} (${goal.periodo_base}). Meta 2030: ${format(goal.valor_meta_2030)}. Método: ${goal.metodo}`);
          else text(meta,'small',goal.metodo);renderVariableDetails(meta,goal);});
      });
    });
    if(p.referencias_apa?.length){text(box,'h4','Referencias');const list=text(box,'ul','', 'plan-referencias-apa');p.referencias_apa.forEach(reference=>text(list,'li',reference));}
  }
  function factorText(factor) {
    if(typeof factor==='string')return factor;
    if(factor.texto)return `${factor.texto} Fuente: ${factor.fuente||'por verificar'}. Alcance: ${factor.limite||'inferencia contextual'}.`;
    const a=factor.periodo_inicial,b=factor.periodo_final;
    const category=state.bundle.evidencia.find(item=>String(item.id_categoria)===String(factor.categoria));
    const metric=(factor.metrica||'Indicador').replaceAll('_',' ');
    const name=/^(Programa educativo|Espacio académico)$/i.test(metric)&&category?`${category.categoria} · ${metric}`:metric;
    return `${name}: ${format(factor.valor_inicial)} (${a}) → ${format(factor.valor_final)} (${b}). Categoría ${factor.categoria||'sin identificar'}.`;
  }
  function pendingLabel(tag) {
    if(String(tag).startsWith('AG-')){
      const agenda=(instruments.agenda||[]).find(item=>item.id===tag);
      return agenda?`${agenda.tema}: ${agenda.orientacion}`:String(tag);
    }
    for(const survey of instruments.encuestas||[]){
      const question=(survey.preguntas||[]).find(item=>item.id===tag);
      if(question)return question.texto;
    }
    return String(tag);
  }
  function editableContent() {
    const revisions=state.bundle.revisiones;
    return revisions.length?revisions[revisions.length-1].contenido:state.bundle.propuesta;
  }
  function matrixContent(content) {
    if(content.politicas&&content.objetivos_especificos?.every(item=>Array.isArray(item.estrategias)))return content;
    const proposal=state.bundle.propuesta,first=content.objetivos_especificos?.[0];
    const policies=structuredClone(proposal.politicas);
    if(first?.politica)policies[0].texto=first.politica;
    const objectives=structuredClone(proposal.objetivos_especificos);
    if(first){objectives[0].texto=first.texto||objectives[0].texto;
      if(first.estrategia){const suffix=` [${objectives[0].estrategias[0].criterios.join(', ')}]`;
        objectives[0].estrategias[0].texto=/\[(?:C|P|I)(?:,\s*(?:C|P|I))*\]$/.test(first.estrategia.trim())?first.estrategia:first.estrategia.trimEnd()+suffix;}
      if(first.meta)objectives[0].estrategias[0].metas[0]=first.meta;}
    return {...content,politicas:policies,objetivos_especificos:objectives};
  }
  function renderEditor() {
    const content=matrixContent(editableContent()),form=$('plan-form');
    form.elements.diagnostico.value=content.diagnostico||'';
    $('plan-usar-mi-foda').checked=content.diagnostico_fuente==='foda_usuario';
    $('plan-reunion-fecha').value=content.reunion_fecha||'';
    $('plan-reunion-resultados').value=content.reunion_resultados||'';
    form.elements.objetivo_general.value=content.objetivo_general||'';
    const policyBox=$('plan-form-politicas');policyBox.replaceChildren();text(policyBox,'h4','Políticas de cada objetivo particular');
    (content.politicas||[]).forEach(item=>{const block=text(policyBox,'div','', 'plan-especifico');block.dataset.id=item.id;
      const label=text(block,'label',`${item.id} · Política`);const input=text(label,'textarea','');input.rows=3;input.value=item.texto;input.required=true;input.dataset.field='texto';
      const owner=text(block,'label','Objetivo particular al que pertenece');const select=document.createElement('select');select.dataset.field='objetivo';
      (content.objetivos_especificos||[]).forEach((objective,i)=>selectOption(select,objective.id||`OE${i+1}`,objective.id||`OE${i+1}`));
      select.value=item.objetivos[0];owner.append(select);});
    const specific=$('plan-form-especificos');specific.replaceChildren();
    (content.objetivos_especificos||[]).forEach((item,i)=>{
      const block=text(specific,'div','', 'plan-especifico');block.dataset.index=String(i);block.dataset.id=item.id||`OE${i+1}`;
      text(block,'h4',`${block.dataset.id} · Objetivo específico`);
      const label=text(block,'label','Redacción del objetivo');const input=text(label,'textarea','');input.rows=4;input.dataset.field='texto';input.value=item.texto;input.required=true;
      (item.estrategias||[]).forEach((strategy,j)=>{const area=text(block,'div','', 'plan-estrategia');area.dataset.index=String(j);area.dataset.id=strategy.id;
        const sl=text(area,'label',`${strategy.id} · Estrategia (con [C], [P] o [I] al final)`);const si=text(sl,'textarea','');si.rows=3;si.dataset.field='estrategia';si.value=strategy.texto;si.required=true;
        (strategy.metas||[]).forEach((goal,k)=>{const mb=text(area,'div','', 'plan-meta-editor');mb.dataset.index=String(k);
           const ml=text(mb,'label','Meta y plazo');const mi=text(ml,'textarea','');mi.rows=3;mi.dataset.field='meta';mi.value=goal.descripcion;mi.required=true;
           if(goal.valor_meta_2030!=null){const nl=text(mb,'label',`Valor numérico de la meta 2030 (${goal.unidad||'unidad'})`);const ni=document.createElement('input');ni.type='number';ni.step='any';ni.min=String(goal.valor_base??0);ni.required=true;ni.dataset.field='valor_meta_2030';ni.value=String(goal.valor_meta_2030);nl.append(ni);}
          text(mb,'small',goal.metodo||'', 'plan-fuente');renderVariableDetails(mb,goal,true);});});
    });
    const last=state.bundle.revisiones.at(-1);
    if(canEdit){form.elements.autor_nombre.value=app.dataset.authorName||'';form.elements.autor_correo.value=app.dataset.authorEmail||'';}
    else if(last){form.elements.autor_nombre.value=last.autor.nombre;form.elements.autor_correo.value=last.autor.correo;}
    $('plan-version-label').textContent=last?`Revisión ${last.version}`:'Igual a la propuesta';
    $('plan-revision-badge').textContent=last?`Tu revisión ${last.version}`:'Propuesta inicial';
  }
  function renderFodaMatrix(container, factors, editable=false, limit=0) {
    container.replaceChildren();
    const table=text(container,'table','', 'plan-foda-matriz');
    const head=text(table,'thead',''),headers=text(head,'tr','');
    ['Origen','Favorable','Desfavorable'].forEach(label=>text(headers,'th',label));
    const body=text(table,'tbody','');
    [['Interno','fortalezas','debilidades'],['Externo','oportunidades','amenazas']].forEach(([origin,positive,negative])=>{
      const row=text(body,'tr','');text(row,'th',origin);
      [positive,negative].forEach(key=>{
        const cell=text(row,'td','');text(cell,'h5',labels[key]);
        const values=factors[key]||[];
        if(editable){
          const area=text(cell,'textarea','');area.dataset.foda=key;
          area.setAttribute('aria-label',`${labels[key]} editables`);area.rows=8;
          area.value=values.map(factorText).join('\n');
        } else if(values.length){
          const list=text(cell,'ul','');
          values.slice(0,limit||values.length).forEach(factor=>{
            const item=text(list,'li',factorText(factor));
            if(factor.url){const link=text(item,'a',' Consultar fuente oficial');link.href=factor.url;link.target='_blank';link.rel='noopener noreferrer';}
          });
          if(limit&&values.length>limit)text(cell,'small',`+ ${values.length-limit} factores; consulta la matriz completa.`);
        } else if(origin!=='Externo') text(cell,'p','Sin factor sustentado en las series consultadas.');
        if(origin==='Externo'&&!editable){
          const allTags=state.bundle.propuesta.pendientes_foda||[];
          const tags=limit?allTags.slice(0,limit):allTags;
          if(tags.length)text(cell,'p',`Contraste local pendiente: ${tags.map(pendingLabel).join('; ')}.`,'plan-fuente');
        }
      });
    });
  }
  function renderFoda() {
    const proposal=state.bundle.propuesta,edited=editableContent();
    renderFodaMatrix($('plan-foda-propuesta'),proposal.foda);
    renderFodaMatrix($('plan-foda-editor'),edited.foda||proposal.foda,true);
    $('plan-foda-prosa-propuesta').textContent=proposal.prosa_foda||'';
    $('plan-foda-prosa-editor').value=edited.prosa_foda??proposal.prosa_foda??'';
    $('plan-foda-bandera').checked=Boolean(edited.solicitar_integracion_foda);
    $('plan-foda-bandera-estado').textContent=$('plan-foda-bandera').checked?'Bandera guardada en esta versión.':'';
    updateFodaMode();
  }
  function updateFodaMode() {
    const flagged=$('plan-foda-bandera').checked,interpreted=$('plan-foda-interpretacion');
    $('plan-foda-etiquetas').hidden=flagged;
    interpreted.hidden=!flagged;interpreted.replaceChildren();
    if(!flagged)return;
    const result=state.bundle?.interpretacion_foda;
    if(result?.foda&&result?.prosa){
      text(interpreted,'h4','FODA interpretado con respuestas verificadas');
      const matrix=text(interpreted,'div','');renderFodaMatrix(matrix,result.foda);
      text(interpreted,'p',result.prosa,'plan-foda-prosa');
      text(interpreted,'small',`Elaborado: ${result.fecha||'fecha por confirmar'}.`);
    } else {
      text(interpreted,'h4','Interpretación pendiente de revisión');
      text(interpreted,'p','La casilla registra la solicitud. La interpretación de cuestionarios y agenda se elaborará en una sesión posterior, cuando haya datos suficientes y se revisen sus fuentes. Vuelve a la vista con etiquetas para consultar o editar el borrador.');
    }
  }
  function renderHistory() {
    const list=$('plan-revisiones');list.replaceChildren();
    if(!state.bundle.revisiones.length){text(list,'li','Todavía no hay revisiones guardadas.');return;}
    [...state.bundle.revisiones].reverse().forEach(r=>{
      text(list,'li',`Versión ${r.version} · ${new Intl.DateTimeFormat('es-MX',{dateStyle:'medium',timeStyle:'short',timeZone:'America/Mexico_City'}).format(new Date(r.fecha))} · ${r.autor.nombre} (${r.autor.correo}) · ${r.autor.identidad==='cuenta'?'cuenta de Traza FI':'identidad declarada'}`);
    });
  }
  function renderEvidence() {
    const select=$('plan-categoria');select.replaceChildren();
    state.bundle.evidencia.forEach((d,i)=>selectOption(select,i,`${d.id_categoria} · ${d.categoria}`));
    const empty=!state.bundle.evidencia.length;
    $('plan-sin-datos').hidden=!empty;
    ['plan-categoria','plan-ambito','plan-indicador','plan-metrica','plan-vista','plan-jpg'].forEach(id=>$(id).disabled=empty);
    $('plan-grafica-nota').value='';$('plan-copiar-pie').disabled=empty;$('plan-copiar-estado').textContent='';$('plan-conciliacion').textContent='';
    if(empty){const canvas=$('plan-canvas');canvas.getContext('2d').clearRect(0,0,canvas.width,canvas.height);$('plan-tabla').hidden=true;return;}
    $('plan-tabla').hidden=false;selectCategory();
  }
  function selectCategory() {
    state.category=state.bundle.evidencia[Number($('plan-categoria').value)];
    const select=$('plan-ambito');select.replaceChildren();selectOption(select,'facultad','Facultad de Ingeniería');
    Object.keys(state.category.carreras).forEach(name=>selectOption(select,name,name));
    selectSection();
  }
  function selectSection() {
    const value=$('plan-ambito').value;
    state.section=value==='facultad'?state.category.facultad:state.category.carreras[value];
    const select=$('plan-indicador');select.replaceChildren();
    state.availableIndicators=state.section.indicadores.filter(ind=>chartableMetrics(ind).length);
    state.availableIndicators.forEach((ind,i)=>selectOption(select,i,`${ind.metricas.join(' · ')} (${ind.numero_registros})`));
    const empty=!state.availableIndicators.length;$('plan-sin-datos').hidden=!empty;
    $('plan-sin-datos').textContent=empty?'No hay una serie temporal con al menos dos periodos comparables para este ámbito. Los indicadores de un solo punto no se ofrecen como gráfica.':' ';
    $('plan-copiar-pie').disabled=empty;$('plan-jpg').disabled=empty;$('plan-metrica').disabled=empty;$('plan-vista').disabled=empty;
    if(empty)$('plan-ambito').disabled=false;
    if(!empty)selectIndicator();else {const canvas=$('plan-canvas');$('plan-tabla').hidden=true;$('plan-grafica-nota').value='';$('plan-analisis-comparativo').textContent='';canvas.getContext('2d').clearRect(0,0,canvas.width,canvas.height);}
  }
  function selectIndicator() {
    state.indicator=state.availableIndicators[Number($('plan-indicador').value)];
    const select=$('plan-metrica');select.replaceChildren();
    const fields=chartableMetrics(state.indicator);
    fields.forEach(key=>selectOption(select,key,key));
    state.metric=state.section.nivel==='facultad'&&fields.includes('Espacio académico')?'Espacio académico':
      state.section.nivel!=='facultad'&&fields.includes('Programa educativo')?'Programa educativo':fields[0]||null;
    select.value=state.metric||'';updateViewAvailability();renderTable();drawChart();
  }
  function renderTable() {
    const rows=state.indicator.registros,head=$('plan-tabla').querySelector('thead'),body=$('plan-tabla').querySelector('tbody');
    head.replaceChildren();body.replaceChildren();
    if($('plan-vista').value==='extremos'){
      const h=text(head,'tr','');['Periodo','Facultad','Mínimo','Carrera con mínimo','Máximo','Carrera con máximo'].forEach(name=>text(h,'th',name));
      comparisonData().forEach(point=>{const tr=text(body,'tr','');[point.label,format(point.faculty),format(point.min),point.minName,format(point.max),point.maxName].forEach(value=>text(tr,'td',value));});
      $('plan-tabla').querySelector('caption').textContent='Tabla 1. Facultad y extremos de las licenciaturas con datos comparables';return;
    }
    $('plan-tabla').querySelector('caption').textContent='Tabla 1. Datos publicados para el indicador seleccionado';
    const fields=[...new Set(rows.flatMap(x=>Object.keys(x).filter(k=>k!=='proyectos')))];
    const tr=text(head,'tr','');fields.forEach(f=>text(tr,'th',f));
    rows.forEach(row=>{const tr=text(body,'tr','');fields.forEach(f=>text(tr,'td',Array.isArray(row[f])?row[f].join(', '):format(row[f])));});
    const rec=state.category.conciliacion_ultimo_periodo;
    $('plan-conciliacion').textContent=rec&&state.section.nivel==='facultad'&&rec.diferencia_sin_programa_identificable
      ?`Conciliación ${rec.periodo}: total Facultad ${format(rec.total_facultad)}, suma de programas ${format(rec.suma_programas_publicados)}; ${format(rec.diferencia_sin_programa_identificable)} sin programa identificable.`:'';
  }
  function temporalPoints(rows,metric) {
    if(!metric)return [];
    const field=['Ciclo escolar','Año','ciclo_escolar'].find(k=>rows.some(r=>r[k]!=null));
    if(!field)return [];
    const groups=new Map();
    rows.forEach((row,i)=>{
      const raw=row[metric];if(typeof raw!=='number'||!Number.isFinite(raw))return;const v=raw*metricScale(metric);
      const label=field?String(row[field]):String(i+1);
      if(!groups.has(label))groups.set(label,[]);groups.get(label).push(v);
    });
    const average=/índice|indice|tasa|promedio|eficiencia|abandono|reprobaci[oó]n|titulaci[oó]n|por alumno|crecimiento|pct/i.test(metric);
    return [...groups].sort((a,b)=>a[0].localeCompare(b[0])).map(([label,values])=>({label,value:values.reduce((a,b)=>a+b,0)/(average?values.length:1)})).slice(-16);
  }
  function metricScale(metric) {return /^(%\s*)|TCMA|Crecimiento Acumulado|Crecimiento %/i.test(metric||'')?100:1;}
  function chartableMetrics(indicator) {
    const numeric=[...new Set(indicator.registros.flatMap(row=>Object.keys(row).filter(key=>typeof row[key]==='number'&&key!=='Año')))];
    return numeric.filter(metric=>temporalPoints(indicator.registros,metric).length>=2);
  }
  function chartData() {return temporalPoints(state.indicator.registros,state.metric);}
  function unitName(metric=state.metric) {
    const own=`${metric||''}`;const context=`${own} ${state.category?.categoria||''}`;
    if(/paridad/i.test(own))return 'razón';
    if(/solicitud|alumno|beca|personal|matrícula|matricula|ingreso|egresado|tutor|snii|servicio.social|participante/i.test(own)&&!/%|tasa|índice|indice|eficiencia|abandono|reprobaci[oó]n|titulaci[oó]n|crecimiento|promedio|por alumno/i.test(own))return 'personas';
    if(/tasa|índice|indice|eficiencia|abandono|reprobaci[oó]n|titulaci[oó]n|crecimiento|porcentaje|%|pct/i.test(context))return '%';
    return 'unidades reportadas';
  }
  function signature(indicator,metric) {
    return (indicator?.metricas||[]).map(key=>key===metric?'$valor':key.replace(/Espacio acad[eé]mico|Programa educativo/gi,'$ámbito').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()).sort().join('|');
  }
  function periodValues(section,metric,sourceIndicator=null,sourceMetric=null) {
    const wanted=sourceIndicator?signature(sourceIndicator,sourceMetric):null;
    const indicator=section?.indicadores?.find(ind=>(!wanted||signature(ind,metric)===wanted)&&ind.registros.some(row=>typeof row[metric]==='number'));
    if(!indicator)return new Map();
    const map=new Map();indicator.registros.forEach((row,i)=>{const raw=row[metric];if(typeof raw!=='number'||!Number.isFinite(raw))return;const value=raw*metricScale(metric);
      const period=String(row['Ciclo escolar']??row['Año']??row.ciclo_escolar??i+1);
      if(!map.has(period))map.set(period,[]);map.get(period).push(value);});
    const average=/índice|indice|tasa|promedio|eficiencia|abandono|reprobaci[oó]n|titulaci[oó]n|crecimiento|pct/i.test(metric);
    return new Map([...map].map(([period,values])=>[period,values.reduce((a,b)=>a+b,0)/(average?values.length:1)]));
  }
  function comparisonData() {
    if(!state.category||!state.metric)return [];
    const facultyMetric=state.metric==='Programa educativo'?'Espacio académico':state.metric;
    const careerMetric=state.metric==='Espacio académico'?'Programa educativo':state.metric;
    const faculty=periodValues(state.category.facultad,facultyMetric,state.indicator,state.metric);
    const careers=Object.entries(state.category.carreras).filter(([name])=>/^Licenciatura\b/i.test(name)).map(([name,section])=>[name,periodValues(section,careerMetric,state.indicator,state.metric)]);
    return [...faculty].sort((a,b)=>a[0].localeCompare(b[0])).map(([label,value])=>{
      const available=careers.map(([name,map])=>({name,value:map.get(label)})).filter(x=>Number.isFinite(x.value));
      if(available.length<2)return null;const min=available.reduce((a,b)=>a.value<=b.value?a:b),max=available.reduce((a,b)=>a.value>=b.value?a:b);
      return {label,faculty:value,min:min.value,minName:min.name,max:max.value,maxName:max.name,n:available.length};
    }).filter(Boolean).slice(-16);
  }
  function comparableMetric(metric=state.metric) {
    return /porcentaje|%|tasa|índice|indice|eficiencia|abandono|reprobaci[oó]n|titulaci[oó]n|promedio|paridad|por alumno|crecimiento/i.test(`${metric||''} ${state.category?.categoria||''}`);
  }
  function updateViewAvailability() {
    const option=[...$('plan-vista').options].find(item=>item.value==='extremos');
    const available=comparableMetric()&&comparisonData().length>=2;
    option.disabled=!available;
    option.title=available?'Compara magnitudes equivalentes de la Facultad y las licenciaturas.':'Disponible sólo para tasas, índices o promedios con al menos dos periodos y dos licenciaturas comparables.';
    if(!available&&$('plan-vista').value==='extremos')$('plan-vista').value='historico';
    $('plan-ambito').disabled=$('plan-vista').value==='extremos';
  }
  function plotSource(ctx,canvas,ids,editor=mainEditor) {
    const position=editor?editor.point('source',88,canvas.height-24):{x:88,y:canvas.height-24};
    ctx.fillStyle='#526055';ctx.font='12px Inter, Arial';ctx.textAlign='left';
    const label=`Fuente: Sistema de Planeación y Desarrollo Institucional (SPDI, 2026b), tableros ${ids.join(', ')}.`;
    ctx.fillText(label,position.x,position.y);
    editor?.hit('source','Fuente',position.x-8,position.y-18,Math.min(760,ctx.measureText(label).width+16),28);
  }
  function axisTitles(ctx,canvas,yLabel,editor=mainEditor,xLabel='Periodo (año o ciclo escolar)') {
    const xp=editor?editor.point('x-title',canvas.width/2,515):{x:canvas.width/2,y:515};
    const yp=editor?editor.point('y-title',28,canvas.height/2-25):{x:28,y:canvas.height/2-25};
    ctx.fillStyle='#334539';ctx.font='600 14px Inter, Arial';ctx.textAlign='center';ctx.fillText(xLabel,xp.x,xp.y);
    ctx.save();ctx.translate(yp.x,yp.y);ctx.rotate(-Math.PI/2);ctx.fillText(yLabel,0,0,canvas.height-125);ctx.restore();ctx.textAlign='left';
    editor?.hit('x-title','Título del eje horizontal',xp.x-190,xp.y-22,380,34);
    editor?.hit('y-title','Título del eje vertical',yp.x-18,70,38,canvas.height-165);
  }
  function drawExtremes() {
    const canvas=$('plan-canvas'),ctx=canvas.getContext('2d'),points=comparisonData();ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);
    mainEditor?.begin();
    $('plan-jpg').disabled=!points.length;$('plan-copiar-pie').disabled=!points.length;
    if(!points.length){$('plan-analisis-comparativo').textContent='No hay carreras con datos comparables para este campo y periodo.';return;}
    const unit=unitName(),all=points.flatMap(p=>[p.faculty,p.min,p.max]),lo=Math.min(...all),hi=Math.max(...all),pad=(hi-lo||1)*.15;
    const left=150,right=1125,top=48,bottom=420,y=v=>bottom-(v-(lo-pad))/(hi-lo+2*pad)*(bottom-top),x=i=>points.length===1?(left+right)/2:left+(right-left)*i/(points.length-1);
    const scale=mainEditor?mainEditor.point('y-scale',0,0):{x:0,y:0};
    ctx.strokeStyle='#dce3da';ctx.fillStyle='#526055';ctx.font='13px Inter, Arial';ctx.textAlign='right';for(let i=0;i<=4;i++){const yy=bottom-(bottom-top)*i/4;ctx.beginPath();ctx.moveTo(left,yy);ctx.lineTo(right,yy);ctx.stroke();ctx.fillText(format(lo-pad+(hi-lo+2*pad)*i/4),left-14+scale.x,yy+4+scale.y);}ctx.textAlign='left';
    mainEditor?.hit('y-scale','Escala vertical',35+scale.x,top-12+scale.y,left-45,bottom-top+28);
    [['min','#b78a3b'],['faculty','#486b55'],['max','#8b5454']].forEach(([key,color])=>{ctx.strokeStyle=color;ctx.lineWidth=3;ctx.beginPath();points.forEach((p,i)=>i?ctx.lineTo(x(i),y(p[key])):ctx.moveTo(x(i),y(p[key])));ctx.stroke();points.forEach((p,i)=>{ctx.fillStyle=color;ctx.beginPath();ctx.arc(x(i),y(p[key]),4,0,Math.PI*2);ctx.fill();});});
    const periods=mainEditor?mainEditor.point('periods',0,0):{x:0,y:0};
    ctx.fillStyle='#364b3c';ctx.font='13px Inter, Arial';ctx.textAlign='center';points.forEach((p,i)=>{if(points.length<=8||i===0||i===points.length-1||i%Math.ceil(points.length/7)===0)ctx.fillText(p.label,x(i)+periods.x,bottom+24+periods.y);});ctx.textAlign='left';
    mainEditor?.hit('periods','Periodos del eje horizontal',left-20+periods.x,bottom+5+periods.y,right-left+40,42);
    const legend=mainEditor?mainEditor.point('legend',0,0):{x:0,y:0};
    [['Mínimo entre licenciaturas','#b78a3b'],['Resultado de la Facultad','#486b55'],['Máximo entre licenciaturas','#8b5454']].forEach(([name,color],i)=>{const start=120+i*355+legend.x;ctx.fillStyle=color;ctx.fillRect(start,555+legend.y,17,4);ctx.fillStyle='#334539';ctx.fillText(name,start+25,561+legend.y);});
    mainEditor?.hit('legend','Leyenda',105+legend.x,535+legend.y,1090,48);
    axisTitles(ctx,canvas,`${state.category.categoria}: ${state.metric.replaceAll('_',' ')} (${unit})`);plotSource(ctx,canvas,[state.category.id_categoria]);
    const last=points.at(-1),range=last.max-last.min;
    const careerMetric=state.metric==='Espacio académico'?'Programa educativo':state.metric;
    const observations=Object.entries(state.category.carreras).filter(([name])=>/^Licenciatura\b/i.test(name)).map(([name,section])=>({name,value:periodValues(section,careerMetric,state.indicator,state.metric).get(last.label)})).filter(x=>Number.isFinite(x.value)).sort((a,b)=>b.value-a.value);
    const middle=observations.map(x=>x.value).sort((a,b)=>a-b),median=middle.length%2?middle[(middle.length-1)/2]:(middle[middle.length/2-1]+middle[middle.length/2])/2;
    const names=observations.map(x=>`${x.name.replace('Licenciatura de Ingeniería en ','').replace('Licenciatura de Ingeniería ','').replace('Licenciatura en Ingeniería en ','')}: ${format(x.value)} ${unit}`).join('; ');
    const facultyChange=points.length>1?` Entre ${points[0].label} y ${last.label}, la serie de Facultad cambió ${format(last.faculty-points[0].faculty)} ${unit}.`:'';
    const crossHint=state.bundle.propuesta.cruces_indicadores?.length&&['4014','4015','4016'].includes(String(state.category.id_categoria))?' El cruce de reprobación, abandono y eficiencia situado abajo ayuda a contrastar este patrón con otras tasas.':'';
    $('plan-analisis-comparativo').textContent=`Comparación de ${last.label}: la Facultad registra ${format(last.faculty)} ${unit}. Las ${observations.length} licenciaturas con dato comparable, de mayor a menor, son ${names}. La mediana entre ellas es ${format(median)} ${unit}; la brecha entre extremos alcanza ${format(range)} ${unit}.${facultyChange}${crossHint} La posición extrema no demuestra por sí sola una causa y los programas sin dato publicado no se tratan como cero.`;
    $('plan-grafica-nota').value=`${state.category.categoria}: Facultad y extremos de licenciaturas (${points[0].label}–${last.label}). ${state.category.referencia_apa||`Sistema de Planeación y Desarrollo Institucional. (2026b). Tablero ${state.category.id_categoria}. ${state.category.url_categoria}`} La selección del mínimo y máximo se hace en cada periodo entre licenciaturas con dato publicado; no se imputan faltantes. ${$('plan-analisis-comparativo').textContent}`;
  }
  function drawChart() {
    if($('plan-vista').value==='extremos'){drawExtremes();renderTable();return;}
    const canvas=$('plan-canvas'),ctx=canvas.getContext('2d'),W=canvas.width,H=canvas.height;
    ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);
    mainEditor?.begin();
    $('plan-analisis-comparativo').textContent='';
    canvas.setAttribute('aria-label',`Gráfica de ${state.metric||'indicador sin valores'}: ${state.category.categoria}, ${scopeName(state.section)}`);
    const points=chartData();
    $('plan-jpg').disabled=!points.length;
    $('plan-copiar-pie').disabled=!points.length;
    const first=points[0]?.label,last=points.at(-1)?.label;
    const period=first?(first===last?first:`${first} a ${last}`):'periodo sin datos';
    const average=/índice|indice|tasa|promedio|eficiencia|abandono|reprobaci[oó]n|titulaci[oó]n|por alumno|crecimiento|pct/i.test(state.metric||'');
    const reconciliation=$('plan-conciliacion').textContent;
    const delta=points.length>1?points.at(-1).value-points[0].value:null;
    const relative=delta!==null&&points[0].value!==0?delta/Math.abs(points[0].value)*100:null;
    const change=delta===null?'':` Entre el primer y el último periodo la variación es ${delta>=0?'+':''}${format(delta)}${relative===null?'':` (${relative>=0?'+':''}${format(relative)} %)`}.`;
    $('plan-grafica-nota').value=`${state.category.categoria}: ${(state.metric||'indicador sin valores').replaceAll('_',' ')} en ${scopeName(state.section)} (${period}). ${state.category.referencia_apa||`Sistema de Planeación y Desarrollo Institucional. (2026b). Tablero ${state.category.id_categoria}. ${state.category.url_categoria}`} La línea verde muestra el valor observado y la línea dorada discontinua su promedio móvil de hasta tres periodos; la línea horizontal marca el primer periodo.${change} Si hay varios registros en un periodo se presenta su ${average?'promedio':'suma'}; los valores originales están en la tabla.${reconciliation?' '+reconciliation:''}`;
    $('plan-copiar-estado').textContent='';
    if(!points.length){ctx.fillStyle='#6c786b';ctx.font='18px Inter, Arial';ctx.fillText('No hay valores para graficar.',65,220);return;}
    const left=150,right=1130,top=55,bottom=420,plotH=bottom-top;
    const values=points.map(p=>p.value),moving=values.map((_,i)=>values.slice(Math.max(0,i-2),i+1).reduce((a,b)=>a+b,0)/Math.min(i+1,3));
    const rawMin=Math.min(...values,...moving),rawMax=Math.max(...values,...moving);
    const padding=(rawMax-rawMin||Math.max(Math.abs(rawMax)*.1,1))*.14;
    const min=rawMin-padding,max=rawMax+padding,range=max-min;
    const xAt=i=>points.length===1?(left+right)/2:left+(right-left)*i/(points.length-1);
    const yAt=value=>bottom-(value-min)/range*plotH;
    const scale=mainEditor?mainEditor.point('y-scale',0,0):{x:0,y:0};
    ctx.strokeStyle='#dce3da';ctx.lineWidth=1;ctx.fillStyle='#526055';ctx.font='15px Inter, Arial';ctx.textAlign='right';
    for(let i=0;i<=4;i++){const y=bottom-i*plotH/4;ctx.beginPath();ctx.moveTo(left,y);ctx.lineTo(right,y);ctx.stroke();ctx.fillText(format(min+range*i/4),left-14+scale.x,y+5+scale.y);}ctx.textAlign='left';
    mainEditor?.hit('y-scale','Escala vertical',30+scale.x,top-14+scale.y,left-40,bottom-top+30);
    ctx.setLineDash([5,6]);ctx.strokeStyle='#a7b6a5';ctx.lineWidth=1.5;ctx.beginPath();ctx.moveTo(left,yAt(values[0]));ctx.lineTo(right,yAt(values[0]));ctx.stroke();ctx.setLineDash([]);
    if(points.length>2){ctx.setLineDash([8,6]);ctx.strokeStyle='#b78a3b';ctx.lineWidth=3;ctx.beginPath();moving.forEach((v,i)=>i?ctx.lineTo(xAt(i),yAt(v)):ctx.moveTo(xAt(i),yAt(v)));ctx.stroke();ctx.setLineDash([]);}
    ctx.strokeStyle='#486b55';ctx.lineWidth=4;ctx.beginPath();points.forEach((p,i)=>i?ctx.lineTo(xAt(i),yAt(p.value)):ctx.moveTo(xAt(i),yAt(p.value)));ctx.stroke();
    const periods=mainEditor?mainEditor.point('periods',0,0):{x:0,y:0};
    points.forEach((p,i)=>{
      const x=xAt(i),y=yAt(p.value);
      ctx.fillStyle=i===points.length-1?'#b78a3b':'#486b55';ctx.beginPath();ctx.arc(x,y,i===points.length-1?6:4,0,2*Math.PI);ctx.fill();
      if(i===0||i===points.length-1||points.length<=5){ctx.fillStyle='#384033';ctx.font='700 13px "DM Sans", Arial';ctx.textAlign='center';ctx.fillText(format(p.value),x,Math.max(22,y-12));}
      if(points.length<=8||i===0||i===points.length-1||i%Math.ceil(points.length/7)===0){ctx.save();ctx.translate(x+periods.x,bottom+15+periods.y);ctx.rotate(-Math.PI/5);ctx.font='13px Inter, Arial';ctx.textAlign='right';ctx.fillText(p.label,0,0);ctx.restore();}
    });ctx.textAlign='left';
    mainEditor?.hit('periods','Periodos del eje horizontal',left-25+periods.x,bottom+2+periods.y,right-left+50,78);
    axisTitles(ctx,canvas,`${state.category.categoria}: ${(state.metric||'Indicador').replaceAll('_',' ')} (${unitName()})`);
    const legend=mainEditor?mainEditor.point('legend',0,0):{x:0,y:0};
    ctx.fillStyle='#486b55';ctx.fillRect(370+legend.x,555+legend.y,18,4);ctx.fillStyle='#334539';ctx.font='13px Inter, Arial';ctx.fillText('Valor observado',394+legend.x,561+legend.y);
    ctx.fillStyle='#b78a3b';ctx.fillRect(580+legend.x,555+legend.y,18,4);ctx.fillStyle='#334539';ctx.fillText('Promedio móvil',604+legend.x,561+legend.y);
    mainEditor?.hit('legend','Leyenda',350+legend.x,535+legend.y,420,48);
    plotSource(ctx,canvas,[state.category.id_categoria]);
  }
  function academicData() {
    const cats=Object.fromEntries(state.bundle.evidencia.filter(item=>['4014','4015','4016'].includes(String(item.id_categoria))).map(item=>[String(item.id_categoria),item]));
    if(Object.keys(cats).length!==3)return null;
    const names=Object.keys(cats['4014'].carreras).filter(name=>cats['4015'].carreras[name]&&cats['4016'].carreras[name]);
    const faculty=Object.fromEntries(Object.entries(cats).map(([id,cat])=>[id,periodValues(cat.facultad,'Espacio académico')]));
    const careers=names.map(name=>({name,values:Object.fromEntries(Object.entries(cats).map(([id,cat])=>[id,periodValues(cat.carreras[name],'Programa educativo')]))}));
    const latest=state.bundle.propuesta.cruces_indicadores?.[0]?.periodo;
    const scatter=careers.map(c=>({name:c.name,eff:c.values['4014'].get(latest),abandon:c.values['4015'].get(latest),fail:c.values['4016'].get(latest)}))
      .filter(p=>[p.eff,p.abandon,p.fail].every(Number.isFinite));
    const periods=[...faculty['4014'].keys()].filter(period=>faculty['4015'].has(period)&&faculty['4016'].has(period)).sort();
    return {scatter,faculty,periods,latest};
  }
  function drawAcademicCross() {
    const section=$('plan-cruce-seccion'),defined=state.bundle.propuesta.cruces_indicadores||[];
    section.hidden=defined.length===0;if(section.hidden)return;
    const data=academicData(),canvas=$('plan-cruce-canvas'),ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);crossEditor?.begin();
    if(!data){$('plan-cruce-jpg').disabled=true;return;}
    $('plan-cruce-analisis').textContent=state.bundle.propuesta.diagnostico.split('\n\n').find(p=>p.startsWith('Lectura conjunta de trayectorias.'))||'';
    const type=$('plan-cruce-tipo').value,colors={'4014':'#486b55','4015':'#b78a3b','4016':'#8b5454'};
    if(type==='dispersion'){
      if(!data.scatter.length){$('plan-cruce-jpg').disabled=true;return;}
      const points=data.scatter,faculty={fail:data.faculty['4016'].get(data.latest),abandon:data.faculty['4015'].get(data.latest),eff:data.faculty['4014'].get(data.latest)};
      const left=115,right=1070,top=55,bottom=465,maxX=Math.max(20,...points.map(p=>p.fail),faculty.fail||0)*1.1,maxY=Math.max(15,...points.map(p=>p.abandon),faculty.abandon||0)*1.1;
      const x=v=>left+(right-left)*v/maxX,y=v=>bottom-(bottom-top)*v/maxY;
      const scale=crossEditor?crossEditor.point('scale',0,0):{x:0,y:0};
      ctx.strokeStyle='#dce3da';ctx.fillStyle='#526055';ctx.font='13px Inter, Arial';
      for(let i=0;i<=5;i++){const xx=left+(right-left)*i/5,yy=bottom-(bottom-top)*i/5;ctx.beginPath();ctx.moveTo(xx,top);ctx.lineTo(xx,bottom);ctx.moveTo(left,yy);ctx.lineTo(right,yy);ctx.stroke();ctx.fillText(format(maxX*i/5),xx-10+scale.x,bottom+22+scale.y);ctx.fillText(format(maxY*i/5),60+scale.x,yy+4+scale.y);}
      crossEditor?.hit('scale','Escalas de los ejes',42+scale.x,top-12+scale.y,right-left+90,bottom-top+55);
      points.forEach(p=>{const radius=7+p.eff/14;ctx.fillStyle='rgba(72,107,85,.55)';ctx.strokeStyle='#355a42';ctx.lineWidth=2;ctx.beginPath();ctx.arc(x(p.fail),y(p.abandon),radius,0,Math.PI*2);ctx.fill();ctx.stroke();ctx.fillStyle='#283a2d';ctx.font='600 13px Inter, Arial';ctx.fillText(p.name.replace('Licenciatura de Ingeniería en ','').replace('Licenciatura de Ingeniería ','').replace('Licenciatura en Ingeniería en ',''),x(p.fail)+radius+5,y(p.abandon)-5);});
      if([faculty.fail,faculty.abandon].every(Number.isFinite)){ctx.fillStyle='#b78a3b';ctx.fillRect(x(faculty.fail)-7,y(faculty.abandon)-7,14,14);ctx.fillStyle='#283a2d';ctx.fillText('Facultad',x(faculty.fail)+12,y(faculty.abandon)+4);}
      const xp=crossEditor?crossEditor.point('x-title',600,525):{x:600,y:525},yp=crossEditor?crossEditor.point('y-title',24,260):{x:24,y:260};
      ctx.fillStyle='#334539';ctx.font='600 15px Inter, Arial';ctx.textAlign='center';ctx.fillText('Reprobación por programa (%)',xp.x,xp.y);ctx.save();ctx.translate(yp.x,yp.y);ctx.rotate(-Math.PI/2);ctx.fillText('Abandono por programa (%)',0,0);ctx.restore();ctx.textAlign='left';
      crossEditor?.hit('x-title','Título del eje horizontal',xp.x-210,xp.y-22,420,34);crossEditor?.hit('y-title','Título del eje vertical',yp.x-18,70,38,390);
      const note=crossEditor?crossEditor.point('legend',115,565):{x:115,y:565};ctx.font='13px Inter, Arial';ctx.fillText('Tamaño del círculo: eficiencia terminal global (%). Cuadrado: Facultad.',note.x,note.y);
      crossEditor?.hit('legend','Leyenda',note.x-10,note.y-22,650,34);
      canvas.setAttribute('aria-label','Cruce por carrera de reprobación, abandono y eficiencia terminal; Facultad señalada con cuadrado.');
    }else{
      const periods=data.periods.slice(-10);if(!periods.length){$('plan-cruce-jpg').disabled=true;return;}
      const left=110,right=1090,top=55,bottom=455,all=periods.flatMap(period=>['4014','4015','4016'].map(id=>data.faculty[id].get(period))),min=Math.min(...all),max=Math.max(...all),pad=(max-min||1)*.1;
      const x=i=>periods.length===1?600:left+(right-left)*i/(periods.length-1),y=v=>bottom-(v-(min-pad))/(max-min+2*pad)*(bottom-top);
      const scale=crossEditor?crossEditor.point('scale',0,0):{x:0,y:0};
      ctx.strokeStyle='#dce3da';ctx.fillStyle='#526055';ctx.font='13px Inter, Arial';for(let i=0;i<=5;i++){const yy=bottom-(bottom-top)*i/5;ctx.beginPath();ctx.moveTo(left,yy);ctx.lineTo(right,yy);ctx.stroke();ctx.fillText(format(min-pad+(max-min+2*pad)*i/5),45+scale.x,yy+4+scale.y);}crossEditor?.hit('scale','Escala vertical',30+scale.x,top-12+scale.y,70,bottom-top+28);
      const legend=crossEditor?crossEditor.point('legend',0,0):{x:0,y:0};
      [['4014','Eficiencia terminal'],['4015','Abandono'],['4016','Reprobación']].forEach(([id,name],j)=>{ctx.strokeStyle=colors[id];ctx.lineWidth=3;ctx.beginPath();periods.forEach((period,i)=>i?ctx.lineTo(x(i),y(data.faculty[id].get(period))):ctx.moveTo(x(i),y(data.faculty[id].get(period))));ctx.stroke();ctx.fillStyle=colors[id];ctx.fillRect(270+j*250+legend.x,550+legend.y,18,4);ctx.fillStyle='#334539';ctx.fillText(name,295+j*250+legend.x,556+legend.y);});crossEditor?.hit('legend','Leyenda',250+legend.x,530+legend.y,750,45);
      const periodOffset=crossEditor?crossEditor.point('periods',0,0):{x:0,y:0};
      ctx.fillStyle='#334539';ctx.textAlign='center';periods.forEach((period,i)=>{if(periods.length<=7||i===0||i===periods.length-1||i%2===0)ctx.fillText(period,x(i)+periodOffset.x,bottom+23+periodOffset.y);});crossEditor?.hit('periods','Periodos del eje horizontal',left-18+periodOffset.x,bottom+5+periodOffset.y,right-left+36,38);
      const xp=crossEditor?crossEditor.point('x-title',600,515):{x:600,y:515},yp=crossEditor?crossEditor.point('y-title',24,260):{x:24,y:260};ctx.font='600 15px Inter, Arial';ctx.fillText('Ciclo escolar',xp.x,xp.y);ctx.save();ctx.translate(yp.x,yp.y);ctx.rotate(-Math.PI/2);ctx.fillText('Tasas de Facultad (%)',0,0);ctx.restore();ctx.textAlign='left';crossEditor?.hit('x-title','Título del eje horizontal',xp.x-150,xp.y-22,300,34);crossEditor?.hit('y-title','Título del eje vertical',yp.x-18,70,38,390);
      canvas.setAttribute('aria-label','Tendencia comparada de eficiencia terminal, abandono y reprobación de la Facultad.');
    }
    const previous=state.category;state.category={fecha_extraccion:'2026-09-21'};plotSource(ctx,canvas,['4014','4015','4016'],crossEditor);state.category=previous;
    $('plan-cruce-fuente').textContent=`Fuente: Sistema de Planeación y Desarrollo Institucional (SPDI, 2026b), tableros 4014, 4015 y 4016. ${type==='dispersion'?'Cada punto usa el último periodo común por carrera; el tamaño del círculo muestra eficiencia terminal.':'Las tres tasas conservan su propia definición y denominador; comparar tendencias no implica relación causal.'}`;
    $('plan-cruce-jpg').disabled=false;
  }
  function renderProject() {
    const b=state.bundle,p=b.propuesta;
    $('plan-nombre').textContent=`${p.numero}. ${p.nombre}`;
    $('plan-pilar-etiqueta').textContent=`Pilar ${p.pilar} · Facultad de Ingeniería`;
    renderPrdi();renderProposal();renderEditor();renderFoda();renderHistory();renderEvidence();drawAcademicCross();openTab(state.tab,state.routeTarget);lockEditor();
  }
  function lockEditor() {
    if(canEdit)return;
    document.querySelectorAll('#plan-form input, #plan-form textarea, #plan-form button, #plan-foda-editor textarea, #plan-foda-prosa-editor, #plan-usar-mi-foda, #plan-foda-insertar-diagnostico, #plan-reunion-fecha, #plan-reunion-resultados, #plan-foda-bandera, #plan-foda-ir-guardar').forEach(node=>node.disabled=true);
  }
  function openTab(name,target) {
    state.tab=name;
    state.routeTarget=target||(name==='matriz'?'plan-seccion-foda':`plan-tab-${name}`);
    document.querySelectorAll('.plan-ruta button').forEach(button=>{
      const active=button.dataset.target===state.routeTarget;
      button.classList.toggle('activo',active);button.setAttribute('aria-current',active?'step':'false');
    });
    document.querySelectorAll('.plan-tab').forEach(panel=>panel.hidden=panel.id!==`plan-tab-${name}`);
  }
  function navigateTo(name,target) {
    openTab(name,target);updateLocation();
    requestAnimationFrame(()=>$(target)?.scrollIntoView({behavior:'smooth',block:'start'}));
  }
  function collectContent() {
    const proposal=state.bundle.propuesta;
    const previous=matrixContent(editableContent());
    const policies=[...document.querySelectorAll('#plan-form-politicas .plan-especifico')].map(block=>({id:block.dataset.id,
      texto:block.querySelector('[data-field="texto"]').value.trim(),objetivos:[block.querySelector('[data-field="objetivo"]').value]}));
    const specifics=[...document.querySelectorAll('#plan-form-especificos .plan-especifico')].map((block,i)=>({id:block.dataset.id,
      texto:block.querySelector(':scope > label [data-field="texto"]').value.trim(),
      estrategias:[...block.querySelectorAll(':scope > .plan-estrategia')].map((area,j)=>({id:area.dataset.id,
        texto:area.querySelector('[data-field="estrategia"]').value.trim(),
        metas:[...area.querySelectorAll('.plan-meta-editor')].map((mb,k)=>{const old=previous.objetivos_especificos[i].estrategias[j].metas[k];
          const meta={...old,descripcion:mb.querySelector('[data-field="meta"]').value.trim()};const target=mb.querySelector('[data-field="valor_meta_2030"]');
          if(target)meta.valor_meta_2030=Number(target.value);
          const rows=[...mb.querySelectorAll('.plan-variables-editables tbody tr')];
          if(rows.length)meta.programacion_variables=rows.map(row=>({nombre:row.dataset.variableName,
            programacion:Object.fromEntries(annualYears.map(year=>[year,Number(row.querySelector(`[data-year="${year}"]`).value)]))}));
          return meta;})}))}));
    const foda={};document.querySelectorAll('[data-foda]').forEach(area=>foda[area.dataset.foda]=area.value.split(/\r?\n/).map(s=>s.trim()).filter(Boolean));
    const original=Object.fromEntries(Object.keys(labels).map(key=>[key,(proposal.foda[key]||[]).map(factorText)]));
    const fodaChanged=Object.keys(labels).some(key=>JSON.stringify(foda[key])!==JSON.stringify(original[key]));
    const diagnosis=$('plan-form').elements.diagnostico.value.trim();
    return {diagnostico:diagnosis,diagnostico_modificado:diagnosis!==proposal.diagnostico.trim(),
      diagnostico_fuente:$('plan-usar-mi-foda').checked?'foda_usuario':'propuesta_fi',
      objetivo_general:$('plan-form').elements.objetivo_general.value.trim(),politicas:policies,objetivos_especificos:specifics,foda,foda_modificada:fodaChanged,
      prosa_foda:$('plan-foda-prosa-editor').value.trim(),solicitar_integracion_foda:$('plan-foda-bandera').checked,
      reunion_fecha:$('plan-reunion-fecha').value,reunion_resultados:$('plan-reunion-resultados').value.trim(),
      encuestas_listas:previous.encuestas_listas||{},plantilla_completada:Boolean(previous.plantilla_completada),
      plantilla_referencia:previous.plantilla_referencia||'',base_propuesta:proposal.clave};
  }
  async function save(event) {
    event.preventDefault();if(!canEdit)return;const form=event.currentTarget;if(!form.reportValidity())return;
    const button=form.querySelector('[type="submit"]'),fodaStatus=$('plan-foda-guardar-estado');button.disabled=true;$('plan-estado').textContent='Guardando revisión…';if(fodaStatus)fodaStatus.textContent='Guardando revisión…';
    try{
      const data=new FormData();data.set('_csrf',app.dataset.csrf);data.set('proyecto',String(state.bundle.proyecto));data.set('version',String(state.bundle.version));
      data.set('autor_nombre',form.elements.autor_nombre.value.trim());data.set('autor_correo',form.elements.autor_correo.value.trim());
      data.set('contenido',JSON.stringify(collectContent()));
      const response=await fetch(`${base}/guardar`,{method:'POST',body:data,credentials:'same-origin'});
      const result=await response.json();if(!response.ok)throw new Error(result.error||'No se pudo guardar.');
      await loadProject();const message=`Revisión ${result.version} guardada por ${result.revision.autor.nombre}.`;$('plan-estado').textContent=message;if(fodaStatus)fodaStatus.textContent=message;
    }catch(error){$('plan-estado').textContent=error.message;if(fodaStatus)fodaStatus.textContent=error.message;}finally{button.disabled=false;}
  }
  mainEditor=window.PlanChartEditor({canvas:'plan-canvas',toggle:'plan-ajustar',reset:'plan-restablecer',status:'plan-ajuste-estado',storagePrefix:'plan-chart-layout-v1',
    key:()=>`${state.bundle?.proyecto||'sin-proyecto'}:${state.category?.id_categoria||'sin-categoria'}:${$('plan-ambito').value||'facultad'}:${state.metric||'sin-metrica'}:${$('plan-vista').value}`,
    redraw:()=>{if(state.bundle&&state.category)drawChart();}});
  crossEditor=window.PlanChartEditor({canvas:'plan-cruce-canvas',toggle:'plan-cruce-ajustar',reset:'plan-cruce-restablecer',status:'plan-cruce-ajuste-estado',storagePrefix:'plan-cross-layout-v1',
    key:()=>`${state.bundle?.proyecto||'sin-proyecto'}:${$('plan-cruce-tipo').value}`,
    redraw:()=>{if(state.bundle)drawAcademicCross();}});
  $('plan-pilar').addEventListener('change',()=>populateProjects());
  $('plan-proyecto').addEventListener('change',loadProject);
  $('plan-form').addEventListener('submit',save);
  document.querySelectorAll('.plan-ruta button').forEach(button=>button.addEventListener('click',()=>navigateTo(button.dataset.tab,button.dataset.target)));
  $('plan-foda-ir-guardar').addEventListener('click',()=>{
    const form=$('plan-form');
    if(!form.reportValidity()){navigateTo('matriz','plan-seccion-diagnostico');form.reportValidity();return;}
    form.requestSubmit();
  });
  $('plan-foda-insertar-diagnostico').addEventListener('click',()=>{
    const field=$('plan-form').elements.diagnostico;
    if(field.value.includes('Interpretación de mi matriz FODA:')){$('plan-foda-insertar-estado').textContent='La lectura ya está en el diagnóstico; puedes editarla.';return;}
    const sections=Object.keys(labels).map(key=>{
      const items=(document.querySelector(`[data-foda="${key}"]`)?.value||'').split(/\r?\n/).map(s=>s.trim()).filter(Boolean).slice(0,3).map(s=>s.slice(0,320));
      return `${labels[key]}: ${items.join('; ')||'sin factor propio registrado'}.`;
    });
    field.value=`${field.value.trim()}\n\nInterpretación de mi matriz FODA: ${sections.join(' ')} Estos factores deben contrastarse con las series, las respuestas de los cuestionarios y los acuerdos de reunión antes de cerrar la redacción.`;
    $('plan-usar-mi-foda').checked=true;navigateTo('matriz','plan-seccion-diagnostico');field.focus();$('plan-foda-insertar-estado').textContent='Lectura añadida; revísala y guarda tu versión.';
  });
  $('plan-foda-bandera').addEventListener('change',()=>{updateFodaMode();$('plan-foda-bandera-estado').textContent='Bandera sin guardar. Guarda una nueva revisión para conservarla.';});
  $('plan-categoria').addEventListener('change',selectCategory);
  $('plan-ambito').addEventListener('change',selectSection);
  $('plan-indicador').addEventListener('change',selectIndicator);
  $('plan-metrica').addEventListener('change',()=>{state.metric=$('plan-metrica').value;updateViewAvailability();renderTable();drawChart();});
  $('plan-vista').addEventListener('change',()=>{updateViewAvailability();renderTable();drawChart();});
  $('plan-cruce-tipo').addEventListener('change',drawAcademicCross);
  $('plan-cruce-jpg').addEventListener('click',()=>{const chart=state.bundle?.propuesta?.cruces_indicadores?.find(item=>item.tipo===$('plan-cruce-tipo').value);if(!chart)return;drawAcademicCross();const link=document.createElement('a');link.download=chart.archivo;link.href=$('plan-cruce-canvas').toDataURL('image/jpeg',.94);link.click();});
  $('plan-copiar-pie').addEventListener('click',async()=>{
    const field=$('plan-grafica-nota');if(!field.value)return;
    try {
      if(navigator.clipboard?.writeText)await navigator.clipboard.writeText(field.value);
      else {field.select();if(!document.execCommand('copy'))throw new Error('No se pudo copiar.');}
      $('plan-copiar-estado').textContent='Texto copiado.';
    } catch(error) {field.select();$('plan-copiar-estado').textContent='Seleccionado; usa Ctrl+C para copiar.';}
  });
  $('plan-jpg').addEventListener('click',()=>{
    if(!state.bundle)return;drawChart();const link=document.createElement('a');
    const metric=state.metric.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,45);
    link.download=`grafica-fi-proyecto-${state.bundle.proyecto}-${state.category.id_categoria}-${metric}${$('plan-vista').value==='extremos'?'-facultad-extremos':''}.jpg`;
    link.href=$('plan-canvas').toDataURL('image/jpeg',.94);link.click();
  });
  populatePillars();
})();

