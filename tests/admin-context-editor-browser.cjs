/* Real synthetic WordPress markup and production editor/navigation assets. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium,webkit}=require('playwright');
const log=fs.readFileSync(process.argv[2],'utf8');
const fixture=name=>Buffer.from(log.match(new RegExp('SWIMLOG_CONTEXT_'+name+'_HTML_BASE64=([A-Za-z0-9+/=]+)'))[1],'base64').toString('utf8');
async function scenario(engine,name,options){const browser=await engine.launch({headless:true});try{
 const page=await browser.newPage(options),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>route.fulfill({body:'<!doctype html><title>Synthetic host</title>',contentType:'text/html'}));await page.goto('https://example.test/wp-admin/admin.php');
 const mount=async(html,editor=false)=>{
  await page.setContent('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:12px;font:14px/1.5 sans-serif}input,select,button{font:inherit}.button{display:inline-block;padding:6px 10px;border:1px solid #2271b1}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(1px,1px,1px,1px)}</style>'+html);
  await page.addStyleTag({path:path.resolve('assets/css/admin.css')});await page.addStyleTag({path:path.resolve('assets/css/structured-workout-editor.css')});await page.addScriptTag({path:path.resolve('assets/js/admin.js')});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  if(editor)await page.addScriptTag({path:path.resolve('assets/js/structured-workout-editor.js')});
 };
 const menu='<nav id="toplevel_page_swimlog-dashboard"><a href="admin.php?page=swimlog-workouts">Menu workouts</a><a href="admin.php?page=swimlog-garmin">Menu Garmin</a><a href="admin.php?page=swimlog-privacy">Menu preferences</a></nav>';
 await mount(menu+fixture('DASHBOARD'));
 const selected=await page.locator('[data-swimlog-context-owner]').getAttribute('data-swimlog-context-owner');
 const menuUrl=new URL(await page.getByRole('link',{name:'Menu workouts',exact:true}).getAttribute('href'));
 assert.equal(menuUrl.searchParams.get('swimmer_id'),selected);assert.ok(menuUrl.searchParams.get('_swimlog_swimmer_nonce'));
 for(const label of ['Menu Garmin','Menu preferences'])assert.equal(new URL(await page.getByRole('link',{name:label,exact:true}).getAttribute('href'),'https://example.test/wp-admin/admin.php').searchParams.has('swimmer_id'),false);
 for(const section of ['DASHBOARD','WORKOUTS','BESTS','TRENDS','ANALYTICS','MANUAL']){
  await mount(fixture(section));
  for(const form of await page.locator('form[method=get]').all()){
   const fields=await form.evaluate(e=>Object.fromEntries(new FormData(e)));
   assert.equal(fields.swimmer_id,selected,section+' selected swimmer');assert.ok(fields._swimlog_swimmer_nonce,section+' nonce');
  }
 }
 await mount(fixture('EDITOR'),true);const root=page.locator('#swimlog-structure-editor');
 const stroke=root.getByLabel('Stroke',{exact:true}).first();await stroke.waitFor({state:'visible'});assert.deepEqual(await stroke.locator('option').allTextContents(),['Freestyle','Breaststroke','Backstroke','Butterfly','Mixed']);
 await stroke.focus();await stroke.selectOption('BR');assert.equal(await stroke.evaluate(e=>e===document.activeElement),true);
 const unit=root.getByLabel('Distance unit',{exact:true});await unit.focus();await unit.selectOption('yd');assert.equal(await unit.evaluate(e=>e===document.activeElement),true);
 const equipment=root.locator('.swimlog-equipment-disclosure');assert.equal(await equipment.nth(0).getAttribute('open'),null);assert.notEqual(await equipment.nth(1).getAttribute('open'),null);
 await equipment.nth(0).locator('summary').click();await equipment.nth(0).getByLabel('Paddles',{exact:true}).check();await equipment.nth(0).getByLabel('Other equipment (comma separated)',{exact:true}).fill('Synthetic custom');
 const stepPanels=()=>root.locator('.swimlog-structure-panel').filter({has:page.locator('legend')});
 const firstStep=root.locator('.swimlog-structure-panel').nth(1);
 await firstStep.getByRole('button',{name:'Move down',exact:true}).click();
 assert.equal(await page.evaluate(()=>document.activeElement.tagName),'LEGEND');assert.equal(await page.evaluate(()=>document.activeElement.textContent),'Step 2');
 let documentValue=JSON.parse(await page.locator('#swimlog-structure-document').inputValue());
 assert.deepEqual(documentValue.sections[0].children.map(x=>x.distance),[50,100]);assert.ok(documentValue.sections[0].children[1].equipment.includes('Paddles'));assert.ok(documentValue.sections[0].children[1].equipment.includes('Synthetic custom'));
 const section=root.locator('.swimlog-structure-panel').first();await section.getByRole('button',{name:'+ Step',exact:true}).click();
 assert.equal(await page.evaluate(()=>document.activeElement.textContent),'Step 3');
 const added=root.locator('.swimlog-structure-panel').last();await added.getByRole('button',{name:'Remove',exact:true}).click();
 assert.equal(await page.evaluate(()=>document.activeElement.textContent),'Step 2');
 const last=root.locator('.swimlog-structure-panel').last();await last.getByRole('button',{name:'Move up',exact:true}).click();assert.equal(await page.evaluate(()=>document.activeElement.textContent),'Step 1');
 documentValue=JSON.parse(await page.locator('#swimlog-structure-document').inputValue());assert.equal(documentValue.unit,'yd');assert.equal(documentValue.sections[0].children[0].stroke,'BR');
 assert.ok(documentValue.sections[0].children[1].equipment.includes('Custom buoy'));assert.equal(documentValue.sections[0].children[1].rest,'0');assert.equal(Object.hasOwn(documentValue.sections[0],'nodeKey'),false);
 assert.equal(await section.getByRole('button',{name:'Remove',exact:true}).first().isEnabled(),false);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
 await mount(fixture('LOCKED'));assert.equal(await page.locator('#swimlog-structure-editor').count(),0);assert.match(await page.locator('.swimlog-prescription-preview').textContent(),/Freestyle/);
 await mount(fixture('REGEN'));await page.getByText('Review complete proposed workout',{exact:true}).click();assert.match(await page.locator('.swimlog-prescription-preview').textContent(),/Breaststroke/);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
 assert.deepEqual(errors,[]);console.log(name+': signed swimmer navigation, owner-only menu exclusion, full stroke names, stable selector/reorder/add/remove focus, equipment preservation and readable locked/regeneration previews passed.');
}finally{await browser.close()}}
(async()=>{await scenario(chromium,'Desktop Chromium',{viewport:{width:1440,height:1000}});await scenario(chromium,'Android-sized touch Chromium',{viewport:{width:390,height:844},isMobile:true,hasTouch:true});await scenario(webkit,'iPhone-sized touch WebKit',{viewport:{width:375,height:812},isMobile:true,hasTouch:true});})().catch(e=>{console.error(e);process.exitCode=1});
