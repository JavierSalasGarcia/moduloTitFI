(() => {
  'use strict';
  const app=document.getElementById('plan-app'),dataNode=document.getElementById('plan-instrumentos');
  if(!app||!dataNode)return;
  const base=app.dataset.base.replace(/\/$/,''),catalog=JSON.parse(dataNode.textContent),$=id=>document.getElementById(id);
  const state={status:{},cutoff:null,survey:null,result:null};
  let chartEditor=null,progressEditor=null;
  const colors={green:'#526855',dark:'#384033',gold:'#CA9E52',ink:'#243026',muted:'#526055',grid:'#dce3da',pale:'#edf2ec',cream:'#f7f4ec'};
  const cdmxNow=()=>new Date();
  const cdmxDate=value=>new Intl.DateTimeFormat('es-MX',{dateStyle:'short',timeZone:'America/Mexico_City'}).format(value);
  const cdmxYear=value=>new Intl.DateTimeFormat('es-MX',{year:'numeric',timeZone:'America/Mexico_City'}).format(value);
  const text=(parent,tag,value,className)=>{const node=document.createElement(tag);node.textContent=value??'';if(className)node.className=className;parent.append(node);return node;};
  const fmt=n=>new Intl.NumberFormat('es-MX',{maximumFractionDigits:1}).format(n);
  const short=(value,max=42)=>String(value).length<=max?String(value):`${String(value).slice(0,max-1).trim()}…`;
  function option(select,value,label){const item=document.createElement('option');item.value=value;item.textContent=label;select.append(item);}
  async function copy(value,status){
    try{if(navigator.clipboard?.writeText)await navigator.clipboard.writeText(value);else{const field=document.createElement('textarea');field.value=value;field.style.position='fixed';field.style.left='-9999px';document.body.append(field);field.select();const copied=document.execCommand('copy');field.remove();if(!copied)throw new Error('Copia no disponible');}status.textContent='Copiado al portapapeles.';}
    catch(error){status.textContent='Selecciona el texto y usa Ctrl+C.';}
  }
  function renderList(){
    const box=$('plan-encuestas-lista');box.replaceChildren();
    catalog.encuestas.forEach(survey=>{
      const info=state.status[survey.id]||{respondidas:0,meta:survey.meta_respuestas,porcentaje:0,segmentos:{}};
      const row=text(box,'article','', 'plan-encuesta-ficha');
      const heading=text(row,'div','', 'plan-encuesta-ficha-titulo');
      text(heading,'strong',`${survey.id} · ${survey.titulo}`);
      const url=new URL(survey.enlace,location.origin).href;
      const surveyOpen=catalog.encuestas_abiertas!==false||(catalog.encuestas_abiertas_ids||[]).includes(survey.id);
      const link=text(heading,'a',surveyOpen?'Responder encuesta':'Encuesta cerrada','plan-btn plan-btn-claro');
      if(!surveyOpen){link.setAttribute('aria-disabled','true');link.removeAttribute('href');}
      else link.href=url;
      const invite=text(heading,'button','📋','plan-copiar-icono');invite.type='button';invite.title=`Copiar invitación de ${survey.id} para WhatsApp`;invite.setAttribute('aria-label',invite.title);
      const context=['C16','C17'].includes(survey.id)?'':` Tus respuestas ayudarán a contrastar el diagnóstico y construir un FODA sustentado. En C01–C04 el correo es opcional si deseas participar en una reunión de seguimiento; las respuestas abiertas no se publican.`;
      const message=`Hola. Te invitamos a responder el cuestionario ${survey.id} del Plan de Desarrollo de la Facultad de Ingeniería, dirigido a ${survey.destinatarios.toLowerCase()}.${context} Responde aquí: ${url}`;
      const copied=text(row,'small','');invite.addEventListener('click',()=>copy(message,copied));
      text(row,'p',survey.destinatarios);
      if(app.dataset.canManageContacts==='1'&&['C01','C02','C03','C04','C16','C17'].includes(survey.id)){
        const contactLink=text(row,'a','Descargar correos voluntarios y propuestas (CSV)','plan-btn plan-btn-claro');
        contactLink.href=`${base}/encuestas/contactos/${survey.id}`;
      }
      const progress=text(row,'div','', 'plan-encuesta-progreso');
      text(progress,'span',`${fmt(info.respondidas)} / ${info.meta==null?'meta por definir':fmt(info.meta)} respuestas`);
      text(progress,'strong',info.porcentaje==null?'—':`${fmt(info.porcentaje)} %`);
      const bar=text(row,'div','', 'plan-encuesta-barra');const fill=text(bar,'span','');fill.style.width=`${Math.min(100,info.porcentaje||0)}%`;
      if(survey.segmentacion==='coordinacion')text(row,'small',info.respondidas>0?'Estado: respondida; el cargo puede ser reconocible.':'Estado: pendiente de respuesta.');
      else text(row,'small',info.base_meta);
      const frozen=state.cutoff?.cuestionarios?.[survey.id];
      if(frozen)text(row,'small',`Corte guardado: ${fmt(frozen.respondidas)} respuestas. En vivo: ${fmt(info.respondidas)}.`,'plan-encuesta-corte');
      if(['carrera','programa'].includes(survey.segmentacion)){
        const isCareer=survey.segmentacion==='carrera',items=isCareer?catalog.carreras.map(item=>({id:item.id,nombre:item.nombre,base:item.nuevo_ingreso_2025_2026})):catalog.programas_posgrado.map(item=>({id:item,nombre:item,base:null}));
        const details=text(row,'details','');details.open=survey.id==='C01';text(details,'summary',isCareer?'Avance por licenciatura':'Avance por programa');
        const table=text(details,'table','', 'plan-encuesta-segmentos');const head=text(table,'thead','');const hrow=text(head,'tr','');
        [isCareer?'Licenciatura':'Programa','Respuestas','Meta','Avance'].forEach(label=>text(hrow,'th',label));
        const body=text(table,'tbody','');items.forEach(item=>{
          const tr=text(body,'tr','');const n=info.segmentos[item.id]||0;
          const target=survey.id==='C01'?item.base:survey.id==='C16'?60:survey.id==='C17'?10:null;
          [item.nombre,fmt(n),target==null?'Por definir':fmt(target),target==null?'—':`${fmt(100*n/target)} %`].forEach(value=>text(tr,'td',value));
        });
      }
    });
  }
  async function loadStatus(){
    try{const response=await fetch(`${base}/encuestas/api`,{credentials:'same-origin'});if(!response.ok)throw new Error('No se pudo cargar el avance.');const payload=await response.json();state.status=payload.avance;state.cutoff=payload.corte_estadistico;
      $('plan-encuestas-corte').textContent=state.cutoff?`Corte estadístico conservado: ${cdmxDate(new Date(state.cutoff.corte))}. Los formularios siguen abiertos; el avance en vivo incluye respuestas posteriores.`:'Todavía no existe un corte estadístico guardado.';
      renderList();drawProgressChart();}
    catch(error){$('plan-encuestas-lista').textContent=error.message;}
  }
  function initSelect(){
    const selector=$('plan-encuesta-seleccion');selector.replaceChildren();
    catalog.encuestas.forEach(survey=>option(selector,survey.id,`${survey.id} · ${survey.titulo}`));
    selectSurvey();
  }
  function selectSurvey(){
    state.survey=catalog.encuestas.find(item=>item.id===$('plan-encuesta-seleccion').value)||catalog.encuestas[0];
    const segment=$('plan-encuesta-segmento');segment.replaceChildren();option(segment,'','Todos los segmentos');
    if(state.survey.segmentacion==='carrera')catalog.carreras.forEach(c=>option(segment,c.id,c.nombre));
    else if(state.survey.segmentacion==='programa')catalog.programas_posgrado.forEach(p=>option(segment,p,p));
    segment.disabled=!['carrera','programa'].includes(state.survey.segmentacion);
    const cross=$('plan-encuesta-cruce');cross.replaceChildren();
    const options=state.survey.segmentacion==='coordinacion'?[state.survey.cruces[0]]:state.survey.cruces;
    options.forEach((item,i)=>option(cross,String(i),item.titulo));
    loadChart();
  }
  function clearChart(message){
    const canvas=$('plan-encuesta-canvas'),ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);
    $('plan-encuesta-grafica-estado').textContent=message;$('plan-encuesta-jpg').disabled=true;$('plan-encuesta-copiar-pie').disabled=true;$('plan-encuesta-copiar-lectura').disabled=true;$('plan-encuesta-insertar-lectura').disabled=true;$('plan-encuesta-pie').value='';$('plan-encuesta-lectura').value='';
  }
  async function loadChart(){
    const id=state.survey.id,cross=$('plan-encuesta-cruce').value||'0',segment=$('plan-encuesta-segmento').value;
    clearChart('Cargando cruce de respuestas…');
    try{
      const query=new URLSearchParams({cruce:cross});if(segment)query.set('segmento',segment);
      const response=await fetch(`${base}/encuestas/resultados/${id}?${query}`,{credentials:'same-origin'});
      if(!response.ok)throw new Error('No se pudo cargar el cruce.');
      state.result=await response.json();
      if(!state.result.disponible){clearChart(`Aún no hay suficientes respuestas para mostrar un cruce anónimo (${state.result.respondidas}/${state.result.minimo} mínimo).`);return;}
      drawHeatmap();
    }catch(error){clearChart(error.message);}
  }
  function wrap(ctx,value,x,y,maxWidth,lineHeight=18){
    const words=String(value).split(' ');let line='',lineNo=0;
    words.forEach(word=>{const next=line?line+' '+word:word;if(ctx.measureText(next).width>maxWidth&&line){ctx.fillText(line,x,y+lineNo*lineHeight);line=word;lineNo++;}else line=next;});
    if(line)ctx.fillText(line,x,y+lineNo*lineHeight);
  }
  function drawProgressChart(){
    const canvas=$('plan-encuesta-avance-canvas'),ctx=canvas.getContext('2d'),W=canvas.width,H=canvas.height,now=cdmxNow();
    ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);progressEditor?.begin();
    const rows=catalog.encuestas.map(survey=>({survey,info:state.status[survey.id]||{respondidas:0,meta:survey.meta_respuestas,porcentaje:null}}));
    const max=Math.max(1,...rows.flatMap(({info})=>[Number(info.respondidas)||0,Number(info.meta)||0]));
    const left=335,right=1115,top=125,bottom=top+rows.length*38,barH=19,x=value=>left+(right-left)*value/max;
    const title=progressEditor?progressEditor.point('title',60,42):{x:60,y:42};
    ctx.textAlign='left';ctx.fillStyle=colors.dark;ctx.font='800 24px "DM Sans", Arial';ctx.fillText('Participación en los cuestionarios del Plan de Desarrollo',title.x,title.y);
    ctx.fillStyle=colors.muted;ctx.font='14px Inter, Arial';ctx.fillText('Respuestas recibidas frente a la meta de participación de cada instrumento',title.x,title.y+27);
    progressEditor?.hit('title','Título y subtítulo',title.x-10,title.y-30,1000,72);
    ctx.strokeStyle=colors.grid;ctx.lineWidth=1;ctx.fillStyle=colors.muted;ctx.font='12px Inter, Arial';ctx.textAlign='center';
    for(let i=0;i<=4;i++){const value=max*i/4,xx=x(value);ctx.beginPath();ctx.moveTo(xx,top-14);ctx.lineTo(xx,bottom+8);ctx.stroke();ctx.fillText(fmt(value),xx,top-23);}
    rows.forEach(({survey,info},i)=>{
      const y=top+i*38,n=Number(info.respondidas)||0,target=Number(info.meta)||0;
      ctx.textAlign='right';ctx.fillStyle=colors.ink;ctx.font='600 12px "DM Sans", Arial';ctx.fillText(`${survey.id} · ${short(survey.titulo,39)}`,left-14,y+14);
      ctx.fillStyle=colors.pale;ctx.fillRect(left,y,right-left,barH);
      ctx.fillStyle=colors.green;ctx.fillRect(left,y,Math.max(n?3:0,x(n)-left),barH);
      if(target){ctx.strokeStyle=colors.gold;ctx.lineWidth=3;ctx.beginPath();ctx.moveTo(x(target),y-3);ctx.lineTo(x(target),y+barH+3);ctx.stroke();}
      ctx.textAlign='left';ctx.fillStyle=colors.dark;ctx.font='700 12px "DM Sans", Arial';ctx.fillText(target?`${fmt(n)} de ${fmt(target)} · ${fmt(100*n/target)} %`:`${fmt(n)} · meta por definir`,Math.min(right-150,x(n)+8),y+14);
    });
    const legend=progressEditor?progressEditor.point('legend',left,bottom+42):{x:left,y:bottom+42};
    ctx.fillStyle=colors.green;ctx.fillRect(legend.x,legend.y,24,8);ctx.fillStyle=colors.ink;ctx.font='13px Inter, Arial';ctx.textAlign='left';ctx.fillText('Respuestas recibidas',legend.x+34,legend.y+9);
    ctx.strokeStyle=colors.gold;ctx.lineWidth=3;ctx.beginPath();ctx.moveTo(legend.x+220,legend.y-4);ctx.lineTo(legend.x+220,legend.y+14);ctx.stroke();ctx.fillText('Meta de participación',legend.x+232,legend.y+9);
    progressEditor?.hit('legend','Leyenda',legend.x-8,legend.y-18,500,42);
    const source=progressEditor?progressEditor.point('source',60,H-64):{x:60,y:H-64};
    ctx.fillStyle=colors.muted;ctx.font='12px Inter, Arial';ctx.fillText(`Fuente: Facultad de Ingeniería, UAEMéx (${cdmxYear(now)}). Cuestionarios del Plan de Desarrollo FI [datos de participación no publicados].`,source.x,source.y);
    ctx.fillText(`Corte: ${cdmxDate(now)}. Nota: el avance mide cobertura de consulta; no demuestra representatividad estadística.`,source.x,source.y+20);
    progressEditor?.hit('source','Fuente y nota metodológica',source.x-8,source.y-19,1080,52);
    canvas.setAttribute('aria-label','Respuestas recibidas y metas de participación por cuestionario del Plan de Desarrollo de la Facultad de Ingeniería.');
    $('plan-encuesta-avance-pie').value=`Participación en los cuestionarios del Plan de Desarrollo de la Facultad de Ingeniería. Fuente: Facultad de Ingeniería, Universidad Autónoma del Estado de México (${cdmxYear(now)}), cuestionarios del Plan de Desarrollo FI [datos no publicados]. Corte ${cdmxDate(now)}. Nota: las barras muestran respuestas recibidas y la marca dorada indica la meta; el avance describe cobertura de consulta y no garantiza representatividad estadística.`;
    $('plan-encuesta-avance-jpg').disabled=false;$('plan-encuesta-avance-copiar').disabled=false;
  }
  function diagnosticReading(data){
    let best={n:-1,row:'',col:'',pct:0};
    data.valores.forEach((values,i)=>{const total=values.reduce((sum,value)=>sum+value,0);values.forEach((n,j)=>{if(n>best.n)best={n,row:data.filas[i],col:data.columnas[j],pct:total?100*n/total:0};});});
    const segment=data.segmento?` para el segmento ${data.segmento}`:'';
    return `En el cuestionario ${data.fuente}${segment}, el cruce de “${data.preguntas[0]}” y “${data.preguntas[1]}” reunió ${data.respondidas} respuestas. La combinación observada con mayor frecuencia fue “${best.row}” y “${best.col}”, con ${best.n} respuestas (${fmt(best.pct)} % dentro de esa categoría de fila). El resultado es descriptivo, debe leerse junto con la cobertura de participación y no permite establecer causalidad ni generalizar por sí solo a toda la comunidad.`;
  }
  function drawHeatmap(){
    const data=state.result,canvas=$('plan-encuesta-canvas'),ctx=canvas.getContext('2d');canvas.height=820;const W=canvas.width,H=canvas.height;
    ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);
    chartEditor?.begin();
    const rows=data.filas,cols=data.columnas,values=data.valores;
    const left=270,top=175,right=1160,bottom=620,cellW=(right-left)/cols.length,cellH=(bottom-top)/rows.length;
    const heading=chartEditor?chartEditor.point('heading',55,38):{x:55,y:38};ctx.textAlign='left';ctx.fillStyle=colors.dark;ctx.font='800 23px "DM Sans", Arial';ctx.fillText(data.titulo,heading.x,heading.y,1080);ctx.fillStyle=colors.muted;ctx.font='14px Inter, Arial';ctx.fillText(`${data.fuente}${data.segmento?' · '+data.segmento:''} · ${data.respondidas} respuestas`,heading.x,heading.y+27);chartEditor?.hit('heading','Título y muestra',heading.x-8,heading.y-29,1080,70);
    const columnLabels=chartEditor?chartEditor.point('column-labels',0,0):{x:0,y:0};
    ctx.textAlign='center';ctx.font='600 13px "DM Sans", Arial';ctx.fillStyle='#384033';
    cols.forEach((col,j)=>wrap(ctx,col,left+(j+.5)*cellW+columnLabels.x,106+columnLabels.y,cellW-8,16));
    chartEditor?.hit('column-labels','Categorías superiores',left-10+columnLabels.x,98+columnLabels.y,right-left+20,68);
    const rowLabels=chartEditor?chartEditor.point('row-labels',0,0):{x:0,y:0};
    const maxPct=Math.max(0,...values.flatMap(row=>{const total=row.reduce((a,b)=>a+b,0);return row.map(n=>total?100*n/total:0);}));
    rows.forEach((row,i)=>{
      ctx.textAlign='right';ctx.fillStyle='#384033';ctx.font='600 13px "DM Sans", Arial';
      wrap(ctx,row,left-13+rowLabels.x,top+(i+.5)*cellH-8+rowLabels.y,245,16);
      const sum=values[i].reduce((a,b)=>a+b,0);
      cols.forEach((col,j)=>{
        const n=values[i][j],pct=sum?100*n/sum:0,x=left+j*cellW,y=top+i*cellH;
        const alpha=.08+.73*pct/100;
        ctx.fillStyle=`rgba(82,104,85,${alpha})`;ctx.fillRect(x+2,y+2,cellW-4,cellH-4);if(pct===maxPct&&pct>0){ctx.strokeStyle=colors.gold;ctx.lineWidth=4;ctx.strokeRect(x+3,y+3,cellW-6,cellH-6);}
        ctx.textAlign='center';ctx.fillStyle=pct>55?'#fff':'#243026';ctx.font='700 16px "DM Sans", Arial';
        ctx.fillText(`${fmt(pct)} %`,x+cellW/2,y+cellH/2-3);
        ctx.font='12px Inter, Arial';ctx.fillText(`n=${n}`,x+cellW/2,y+cellH/2+17);
      });
    });
    chartEditor?.hit('row-labels','Categorías laterales',5+rowLabels.x,top-10+rowLabels.y,left-20,bottom-top+20);
    const xp=chartEditor?chartEditor.point('x-title',(left+right)/2,660):{x:(left+right)/2,y:660},yp=chartEditor?chartEditor.point('y-title',25,top+(bottom-top)/2):{x:25,y:top+(bottom-top)/2};
    ctx.fillStyle='#334539';ctx.font='600 14px Inter, Arial';ctx.textAlign='center';ctx.fillText(`Respuesta a ${data.preguntas[1]} (categorías)`,xp.x,xp.y,1050);
    ctx.save();ctx.translate(yp.x,yp.y);ctx.rotate(-Math.PI/2);ctx.fillText(`Respuesta a ${data.preguntas[0]} (categorías)`,0,0,475);ctx.restore();
    chartEditor?.hit('x-title','Título del eje horizontal',xp.x-430,xp.y-24,860,38);chartEditor?.hit('y-title','Título del eje vertical',yp.x-18,150,38,470);
    const now=cdmxNow();
    const legend=chartEditor?chartEditor.point('legend',390,700):{x:390,y:700};const gradient=ctx.createLinearGradient(legend.x,0,legend.x+240,0);gradient.addColorStop(0,'rgba(82,104,85,.08)');gradient.addColorStop(1,'rgba(82,104,85,.81)');ctx.fillStyle=gradient;ctx.fillRect(legend.x,legend.y,240,13);ctx.strokeStyle=colors.gold;ctx.lineWidth=2;ctx.strokeRect(legend.x+246,legend.y-2,18,17);ctx.fillStyle=colors.ink;ctx.font='12px Inter, Arial';ctx.textAlign='left';ctx.fillText('0 %',legend.x-34,legend.y+12);ctx.fillText('100 %',legend.x+270,legend.y+12);ctx.fillText('Dorado: combinación más frecuente',legend.x+350,legend.y+12);chartEditor?.hit('legend','Escala de intensidad',legend.x-42,legend.y-18,690,48);
    const source=chartEditor?chartEditor.point('source',55,772):{x:55,y:772};ctx.textAlign='left';ctx.font='12px Inter, Arial';ctx.fillStyle=colors.muted;ctx.fillText(`Fuente: Facultad de Ingeniería, UAEMéx (${cdmxYear(now)}), cuestionario ${data.fuente} del Plan de Desarrollo FI [datos no publicados]. Corte: ${cdmxDate(now)}; n=${data.respondidas}.`,source.x,source.y);ctx.fillText('Nota: porcentaje calculado dentro de cada fila; las respuestas abiertas no se incluyen. Asociación descriptiva, no causal.',source.x,source.y+20);
    chartEditor?.hit('source','Fuente y nota metodológica',source.x-8,source.y-20,1090,50);
    canvas.setAttribute('aria-label',`${data.titulo}; cruce de ${data.preguntas[0]} y ${data.preguntas[1]}`);
    $('plan-encuesta-grafica-estado').textContent=`Cruce disponible: ${data.respondidas} respuestas. Cada celda muestra el porcentaje dentro de su fila y el número de respuestas.`;
    $('plan-encuesta-jpg').disabled=false;$('plan-encuesta-copiar-pie').disabled=false;$('plan-encuesta-copiar-lectura').disabled=false;$('plan-encuesta-insertar-lectura').disabled=false;
    $('plan-encuesta-pie').value=`${data.titulo}${data.segmento?' · '+data.segmento:''}. Cruce de “${data.preguntas[0]}” y “${data.preguntas[1]}”. Facultad de Ingeniería, Universidad Autónoma del Estado de México. (${cdmxYear(now)}). Cuestionario ${data.fuente} del Plan de Desarrollo de la Facultad de Ingeniería [Conjunto de datos no publicado]. Corte ${cdmxDate(now)}, n=${data.respondidas}. Nota: cada celda expresa porcentaje por fila; las respuestas abiertas no forman parte de esta gráfica.`;
    $('plan-encuesta-lectura').value=diagnosticReading(data);
  }
  chartEditor=window.PlanChartEditor({canvas:'plan-encuesta-canvas',toggle:'plan-encuesta-ajustar',reset:'plan-encuesta-restablecer',status:'plan-encuesta-ajuste-estado',storagePrefix:'plan-survey-layout-v1',
    key:()=>`${state.survey?.id||'sin-encuesta'}:${$('plan-encuesta-segmento').value||'todos'}:${$('plan-encuesta-cruce').value||'0'}`,
    redraw:()=>{if(state.result?.disponible)drawHeatmap();}});
  progressEditor=window.PlanChartEditor({canvas:'plan-encuesta-avance-canvas',toggle:'plan-encuesta-avance-ajustar',reset:'plan-encuesta-avance-restablecer',status:'plan-encuesta-avance-ajuste-estado',storagePrefix:'plan-survey-progress-layout-v1',key:()=>`cuestionarios:${cdmxYear(cdmxNow())}`,redraw:()=>{if(Object.keys(state.status).length)drawProgressChart();}});
  $('plan-encuesta-seleccion').addEventListener('change',selectSurvey);
  $('plan-encuesta-segmento').addEventListener('change',loadChart);
  $('plan-encuesta-cruce').addEventListener('change',loadChart);
  $('plan-encuesta-copiar-pie').addEventListener('click',()=>copy($('plan-encuesta-pie').value,$('plan-encuesta-copiar-estado')));
  $('plan-encuesta-copiar-lectura').addEventListener('click',()=>copy($('plan-encuesta-lectura').value,$('plan-encuesta-lectura-estado')));
  $('plan-encuesta-insertar-lectura').addEventListener('click',()=>{
    const reading=$('plan-encuesta-lectura').value.trim(),field=$('plan-form')?.elements?.diagnostico;if(!reading||!field)return;
    if(field.value.includes(reading)){$('plan-encuesta-lectura-estado').textContent='Esta lectura ya está incluida en el diagnóstico.';return;}
    field.value=`${field.value.trim()}\n\nResultados de la consulta: ${reading}`;document.querySelector('.plan-ruta [data-target="plan-seccion-diagnostico"]')?.click();field.focus();$('plan-encuesta-lectura-estado').textContent='Lectura añadida. Revísala y guarda una nueva versión.';
  });
  $('plan-encuesta-avance-copiar').addEventListener('click',()=>copy($('plan-encuesta-avance-pie').value,$('plan-encuesta-avance-copiar-estado')));
  $('plan-encuesta-avance-jpg').addEventListener('click',()=>{drawProgressChart();const link=document.createElement('a');link.download='participacion-cuestionarios-plan-fi.jpg';link.href=$('plan-encuesta-avance-canvas').toDataURL('image/jpeg',.94);link.click();});
  $('plan-agenda-copiar').addEventListener('click',()=>{
    const url=`${location.origin}${base}/agenda`;
    const topics=catalog.agenda.map(item=>`${item.id} ${item.tema}`).join('; ');
    copy(`Coordinaciones de las seis licenciaturas: les compartimos con anticipación la agenda de la reunión de consulta FI 360 para el Plan de Desarrollo de Ingeniería. Les pedimos reunir datos, ejemplos y propuestas sobre: ${topics}. Agenda completa: ${url}`,$('plan-agenda-copiar-estado'));
  });
  $('plan-encuesta-jpg').addEventListener('click',()=>{
    if(!state.result?.disponible)return;
    drawHeatmap();
    const link=document.createElement('a');link.download=`cruce-${state.result.fuente.toLowerCase()}-${$('plan-encuesta-cruce').value}${state.result.segmento?'-'+state.result.segmento:''}.jpg`;
    link.href=$('plan-encuesta-canvas').toDataURL('image/jpeg',.94);link.click();
  });
  initSelect();loadStatus();
})();
