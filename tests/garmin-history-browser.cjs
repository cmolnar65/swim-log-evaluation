const fs=require('fs');
const path=require('path');
const assert=require('assert/strict');
const {chromium,webkit}=require('playwright');
const log=fs.readFileSync(process.argv[2],'utf8');
const match=log.match(/SWIMLOG_HISTORY_HTML_BASE64=([A-Za-z0-9+/=]+)/);
assert(match,'Missing actual WordPress historical controls fixture.');
const html=Buffer.from(match[1],'base64').toString();
const css=fs.readFileSync(path.join(__dirname,'../assets/css/garmin-history.css'),'utf8');
const js=fs.readFileSync(path.join(__dirname,'../assets/js/garmin-history.js'),'utf8');
async function scenario(engine,name,options){
 const browser=await engine.launch();
 try {
  const context=await browser.newContext(options);const page=await context.newPage();let requests=0;
  await page.route('http://history.test/**',async route=>{
   if(route.request().url().includes('admin-ajax.php')){
    const body=new URLSearchParams(route.request().postData());
    assert.equal(body.get('action'),'swimlog_history_status');assert.equal(body.get('nonce'),'synthetic-status-nonce');assert(!body.has('owner'));
    requests++;
    const refreshed=await page.locator('#swimlog-history-status').evaluate(el=>{
      const copy=el.cloneNode(true);copy.dataset.active='0';copy.querySelector('[role=status]').textContent='Completed test refresh';return copy.outerHTML;
    });
    return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data:{html:refreshed}})});
   }
   return route.fulfill({contentType:'text/html',body:'<html><body></body></html>'});
  });
  await page.goto('http://history.test/admin.php');
  await page.setContent('<main>'+html+'</main>');
  await page.addStyleTag({content:css});
  const root=page.locator('#swimlog-garmin-history');
  assert.equal(await root.getByLabel('Start date',{exact:true}).count(),1);
  await root.getByLabel('Start date',{exact:true}).fill('2018-01-01');await root.getByLabel('End date',{exact:true}).fill('2018-12-31');
  await root.getByLabel('Pool swims',{exact:true}).check();await root.getByLabel('Weight',{exact:true}).check();await root.getByLabel('SpO2 provider summaries',{exact:true}).check();
  await page.evaluate(()=>{window.historySubmissions=[];document.addEventListener('submit',event=>{event.preventDefault();window.historySubmissions.push(Object.fromEntries(new FormData(event.target)));});});
  await root.getByRole('button',{name:'Start historical import',exact:true}).click();
  let sent=await page.evaluate(()=>window.historySubmissions.pop());assert.equal(sent.history_start,'2018-01-01');assert.equal(sent.history_end,'2018-12-31');assert.equal(sent.swimlog_history_action,'start');assert(sent._wpnonce);assert(!('owner' in sent));
  const pause=root.getByRole('button',{name:'Pause',exact:true});
  assert((await pause.boundingBox()).height>=44);
  await pause.click();sent=await page.evaluate(()=>window.historySubmissions.pop());assert.equal(sent.swimlog_history_action,'pause');assert(sent.history_job);
  await root.getByRole('button',{name:'Cancel — keep imported records',exact:true}).click();sent=await page.evaluate(()=>window.historySubmissions.pop());assert.equal(sent.swimlog_history_action,'cancel');
  assert.equal(await root.getByRole('link',{name:'Failed items',exact:true}).count(),1);assert.equal(await root.getByRole('link',{name:'Needs review',exact:true}).count(),1);
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),'Horizontal page overflow');
  await page.evaluate(()=>{window.swimlogHistorySettings={url:'http://history.test/admin-ajax.php',nonce:'synthetic-status-nonce'};const original=window.setTimeout;window.setTimeout=(fn,ms,...args)=>original(fn,ms===10000?40:ms,...args);});
  await pause.focus();await page.addScriptTag({content:js});await page.waitForTimeout(120);assert.equal(requests,0,'Focused controls should not be replaced');
  await pause.evaluate(el=>el.blur());await page.getByText('Completed test refresh',{exact:true}).waitFor();
  assert.equal(requests,1);assert.equal(await page.locator('#swimlog-history-status').getAttribute('data-active'),'0');
  console.log(name+': historical date/category form, nonce/job controls, touch targets, responsive layout and private focus-safe status refresh passed.');
  await context.close();
 }finally{await browser.close();}
}
(async()=>{
 await scenario(chromium,'Desktop Chromium',{viewport:{width:1440,height:1000}});
 await scenario(chromium,'Android-sized touch Chromium',{viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 await scenario(webkit,'iPhone-sized touch WebKit',{viewport:{width:375,height:812},isMobile:true,hasTouch:true});
})().catch(error=>{console.error(error);process.exit(1);});
