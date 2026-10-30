/* Synthetic WordPress form output with production CSS/JS; no real account or site. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium,webkit}=require('playwright');
const log=fs.readFileSync(process.argv[2],'utf8');
const fixtures=[...log.matchAll(/SWIMLOG_UX_([A-Z]+)_HTML_BASE64=([A-Za-z0-9+/=]+)/g)].map(m=>({name:m[1],html:Buffer.from(m[2],'base64').toString('utf8')}));
assert.ok(fixtures.length>=10,'Actual WordPress page fixtures must be supplied');
const css='body{margin:0;padding:12px;font:14px/1.5 sans-serif;background:#f0f0f1}input,select,textarea,button{font:inherit}input.regular-text{width:25em}textarea.large-text{width:100%}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:8px;vertical-align:top}.form-table th{width:200px}.button{display:inline-block;padding:6px 12px;border:1px solid #2271b1}.widefat{background:white}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(1px,1px,1px,1px)}';
async function scenario(engine,name,options){
 const browser=await engine.launch({headless:true});
 try{
  const page=await browser.newPage(options),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',r=>r.fulfill({body:'',contentType:'text/plain'}));
  for(const fixture of fixtures){
   await page.setContent('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style>'+fixture.html);
   await page.addStyleTag({path:path.resolve('assets/css/admin.css')});
   await page.addStyleTag({path:path.resolve('assets/css/structured-workout-editor.css')});
   await page.addScriptTag({path:path.resolve('assets/js/admin.js')});
   await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
   const bad=await page.locator('.swimlog-admin table.widefat').evaluateAll(tables=>tables.filter(t=>!t.closest('.swimlog-table-wrap,.swimlog-history-results')).length);
   assert.equal(bad,0,fixture.name+': every data table has a contained scrolling region');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,fixture.name+': page must fit viewport');
   for(const region of await page.locator('.swimlog-table-wrap').all()){
    assert.equal(await region.getAttribute('tabindex'),'0');
    const geometry=await region.evaluate(e=>({width:e.clientWidth,scroll:e.scrollWidth}));
    if(geometry.scroll>geometry.width){await region.focus();await page.keyboard.press('End');await region.evaluate(e=>e.scrollLeft=e.scrollWidth);assert.ok(await region.evaluate(e=>e.scrollLeft>0),'Wide table can scroll to its final columns');}
   }
   if(['PLAN','EVENT','LOCATION','STRUCTURE'].includes(fixture.name)){
    await page.evaluate(()=>document.querySelector('[data-swimlog-form-error]').focus());
    assert.equal(await page.locator('[data-swimlog-form-error]').evaluate(e=>e===document.activeElement),true);
   }
   if(fixture.name==='PLAN'){
    assert.equal(await page.locator('#swimlog-plan-duration').inputValue(),'45:99');
    assert.equal(await page.locator('#swimlog-plan-title').inputValue(),'Attempted plan title');
    assert.equal(await page.locator('#swimlog-plan-distance').inputValue(),'2000');
    assert.equal(await page.locator('#swimlog-plan-distance-unit').inputValue(),'yd');
    assert.equal(await page.locator('#swimlog-plan-pool-length').inputValue(),'25');
   }
   if(fixture.name==='STRUCTURE'){
    await page.addScriptTag({path:path.resolve('assets/js/structured-workout-editor.js')});
    assert.equal(await page.locator('#swimlog-structure-title').inputValue(),'Attempted structure title');
    assert.ok(await page.locator('#swimlog-structure-editor input').evaluateAll(nodes=>nodes.some(n=>n.value==='Attempted section')));
   }
  }
  assert.deepEqual(errors,[]);
  console.log(name+': '+fixtures.length+' admin pages passed contained tables, viewport fit, keyboard focus and rejected-field recovery.');
 }finally{await browser.close()}
}
(async()=>{await scenario(chromium,'Desktop Chromium',{viewport:{width:1440,height:1000}});await scenario(chromium,'Android-sized touch Chromium',{viewport:{width:390,height:844},isMobile:true,hasTouch:true});await scenario(webkit,'iPhone-sized touch WebKit',{viewport:{width:375,height:812},isMobile:true,hasTouch:true});})().catch(e=>{console.error(e);process.exitCode=1});
