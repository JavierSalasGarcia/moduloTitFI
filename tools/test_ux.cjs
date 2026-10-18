const {chromium}=require('C:/Users/edigi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');
const base=process.env.FI_TEST_URL||'http://127.0.0.1:8765/public/';
const local=base.startsWith('http://127.0.0.1:8765/');
const out=path.join(process.env.TEMP,'fi-ux-verification');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});
 const page=await context.newPage();let errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 const login=async(email,password)=>{await page.goto(base+'login');await page.locator('#email-login').fill(email);await page.locator('#btn-login-siguiente').click();await page.locator('#clave-login').fill(password);await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login')),page.locator('#btn-login-directo').click()]);};
 try {
  await login(local?'direccion@fi.uaemex.mx':process.env.FI_TEST_EMAIL,local?'Ux-local-test-2026':process.env.FI_TEST_PASSWORD);
  const routes=['','tickets','tickets/nuevo','reuniones','reuniones/nueva','reuniones?tab=disponibilidad','calendario','firmas','firmas/nuevo','firmas/grupos','teams','eventos','eventos?avisos=archivo','eventos/biblioteca','directorio','funciones','comisiones','comisiones/nueva','encuestas','encuestas/nueva','foro','solicitudes','solicitudes/nueva','recursos','ajustes','ajustes/usuarios','seguridad','alumnos'];
  for(const route of process.env.FI_TEST_FLOWS?[]:routes){
   const response=await page.goto(base+route,{waitUntil:'networkidle'});const body=await page.locator('body').innerText();
   assert(response.status()===200,route+' HTTP '+response.status());assert(!/Fatal error|Warning:|Uncaught|Parse error/.test(body),route+' SERVER ERROR '+body.slice(0,400));
   for(const width of [360,390,768,1024,1440]) {await page.setViewportSize({width,height:900});await page.emulateMedia({reducedMotion:'reduce'});const sizes=await page.evaluate(()=>({w:innerWidth,s:document.documentElement.scrollWidth}));assert(sizes.s<=sizes.w+2,route+' overflow '+width+': '+sizes.s);}
   if(['','reuniones/nueva','ajustes/usuarios','calendario','firmas/nuevo','teams','ajustes'].includes(route)){await page.screenshot({path:path.join(out,(route||'inicio').replaceAll('/','-')+'-pc.png'),fullPage:true,animations:'disabled'});await page.setViewportSize({width:390,height:844});await page.screenshot({path:path.join(out,(route||'inicio').replaceAll('/','-')+'-mobile.png'),fullPage:true,animations:'disabled'});}
   if(errors.length)throw Error(route+' JS '+errors.join('; '));console.log('PASS',route||'inicio');
  }
  await page.goto(base+'ajustes/usuarios');assert(await page.locator('.menu-item[aria-current=page]').count()===1,'one selected menu');
  await page.setViewportSize({width:390,height:844});await page.locator('#btn-menu-movil').click();assert(await page.locator('#btn-menu-movil').getAttribute('aria-expanded')==='true');await page.keyboard.press('Escape');assert(await page.locator('#btn-menu-movil').getAttribute('aria-expanded')==='false');
  console.log('PASS navigation keyboard');
  if(local){
   await page.goto(base+'');await page.locator('#consulta-texto').fill('Necesito corregir una calificación que aparece mal en mi acta');await page.getByRole('button',{name:'Consultar',exact:true}).click();await page.locator('.consulta-respuesta').waitFor();
   await page.locator('.consulta-ticket button[type=submit]').click();await page.waitForURL(/tickets\/\d+/);assert((await page.locator('main').innerText()).includes('Solicitud'));console.log('PASS query -> submit -> tracking');
   await page.goto(base+'reuniones/nueva');await page.locator('[name=asunto]').fill('Prueba local de interfaz');await page.getByRole('button',{name:'Continuar',exact:true}).click();await page.locator('#rango_inicio').fill('2026-09-15');await page.locator('#rango_fin').fill('2026-09-15');await page.locator('#rango_fin').dispatchEvent('change');await page.locator('#calendario-grid button').first().click();await page.getByRole('button',{name:'Continuar',exact:true}).click();await page.locator('#buscador-invitados').fill('hugo');await page.locator('#sugerencias-contenedor > div').first().click();await page.getByRole('button',{name:'Continuar',exact:true}).click();await page.locator('.ux-wizard-actions button[type=submit]').click();await page.waitForURL(/reuniones\/\d+/);console.log('PASS meeting wizard -> create');
   await page.goto(base+'encuestas/nueva');await page.locator('[name=titulo]').fill('Prueba local de opciones');const opts=page.locator('.ux-option-row input');await opts.nth(0).fill('Mañana');await opts.nth(1).fill('Tarde');await page.getByRole('button',{name:'Abrir encuesta',exact:true}).click();await page.waitForURL(/encuestas\/\d+/);console.log('PASS survey creation');
  }
  await page.goto(base+'logout');console.log('COMPLETE',out);
 } finally {await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
