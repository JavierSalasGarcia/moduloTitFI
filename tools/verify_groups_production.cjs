const {chromium}=require('C:/Users/edigi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('assert');
const base='https://fingenieria.mx/public/';
(async()=>{const browser=await chromium.launch({headless:true,channel:'msedge'});try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'login');await page.locator('#email-login').fill(process.env.FI_TEST_EMAIL);await page.locator('#btn-login-siguiente').click();await page.locator('#clave-login').fill(process.env.FI_TEST_PASSWORD);await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login')),page.locator('#btn-login-directo').click()]);
 await page.goto(base+'grupos-destinatarios');const activate=page.getByRole('button',{name:'Activar grupos de destinatarios',exact:true});if(await activate.count()){await activate.click();await page.waitForLoadState('networkidle');console.log('ACTIVATED additive group schema');}
 assert(await page.getByRole('link',{name:'Crear grupo',exact:true}).isVisible());
 for(const route of ['grupos-destinatarios','grupos-destinatarios/nuevo','tickets/nuevo','reuniones/nueva','firmas/nuevo','firmas/grupos','']){
  const response=await page.goto(base+route);assert.equal(response.status(),200,route);assert(!/Fatal error|Warning:|Parse error/.test(await page.locator('body').innerText()),route);
  for(const width of [360,390,768,1440]){await page.setViewportSize({width,height:900});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),route+' overflow '+width);}console.log('PASS production '+(route||'inicio'));
 }
 await page.goto(base+'grupos-destinatarios/nuevo');await page.locator('#grupo-visibilidad').selectOption('publico');assert.equal(await page.locator('[name="roles[]"]').count(),4);const search=page.locator('[data-field=miembros] input[type=search]');await search.fill('admin');await page.waitForResponse(r=>r.url().includes('grupos-destinatarios/personas')&&r.status()===200);await page.waitForTimeout(300);assert(await page.locator('[data-field=miembros] [data-picker-results] button').count()>0);await page.locator('[data-field=miembros] [data-picker-results] button').first().click();assert.equal(await page.locator('[name="miembros[]"]').count(),1);
 await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));await page.screenshot({path:process.env.TEMP+'/fi-grupos-produccion-mobile.png',fullPage:true});console.log('PASS production search, selection, category controls and mobile; form not saved');
 await page.goto(base+'logout');for(const route of ['grupos-destinatarios','grupos-destinatarios/personas?q=admin']){await page.goto(base+route);assert(new URL(page.url()).pathname.endsWith('/login'));}assert.deepEqual(errors,[]);console.log('COMPLETE no groups, messages or invitations created in production');
}finally{await browser.close();}})().catch(e=>{console.error(e.stack);process.exitCode=1;});
