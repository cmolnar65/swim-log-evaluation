/* Real WordPress HTML + production JS/CSS; synthetic REST/AJAX fixtures only. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium, webkit } = require('playwright');
const log = fs.readFileSync(process.argv[2], 'utf8');
const fixture = key => Buffer.from(log.match(new RegExp(key+'=([A-Za-z0-9+/=]+)'))[1], 'base64').toString('utf8');
const html = fixture('SWIMLOG_DAY_CALENDAR_HTML_BASE64');
const data = JSON.parse(fixture('SWIMLOG_DAY_CALENDAR_DATA_BASE64'));
async function scenario(engine, name, options) {
 const browser = await engine.launch({headless:true});
 try {
  const context = await browser.newContext({...options,locale:'en-US',timezoneId:'America/Denver'});
  const page = await context.newPage(); const errors = [], requests = [];
  page.on('pageerror', e=>errors.push(e.message));
  let failDay = false, delayDay = false, saveCount = 0;
  await page.route('**/*', async route=>{
   const request=route.request(), url=new URL(request.url()); requests.push({url:request.url(),method:request.method(),headers:request.headers(),body:request.postData()});
   if(request.method()==='POST') {
    const body=new URLSearchParams(request.postData());
    if(body.get('action')==='swimlog_calendar_event_result') return route.fulfill({json:{success:true,data:{result_time:'1:20.00',placement:2,notes:'Synthetic result note'}}});
    saveCount++; return route.fulfill({body:JSON.stringify({success:true,data:{id:321}}),contentType:'application/json'});
   }
   if(url.pathname.includes('/calendar')) {
    const start=url.searchParams.get('start'), end=url.searchParams.get('end');
    if(start===end && failDay) return route.fulfill({status:403,json:{message:'Denied synthetic request'}});
    if(start===end && delayDay) await new Promise(resolve=>setTimeout(resolve,150));
    const calendar=structuredClone(data.calendar); calendar.range.start=start;calendar.range.end=end;
    calendar.workouts=calendar.workouts.filter(w=>w.workout_start.slice(0,10)>=start && w.workout_start.slice(0,10)<=end);
    calendar.planned_workouts=calendar.planned_workouts.filter(w=>w.planned_date>=start&&w.planned_date<=end);
    calendar.events=calendar.events.filter(w=>w.event_date>=start&&w.event_date<=end);
    return route.fulfill({json:calendar});
   }
   return route.fulfill({body:'<!doctype html><title>Synthetic calendar test</title>',contentType:'text/html'});
  });
  await page.goto('https://calendar.test/');
  await page.setContent('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:12px;font:16px sans-serif}header{height:250px}footer{height:900px}</style><header>Test calendar host</header>'+html+'<footer>End of synthetic host</footer>');
  await page.evaluate(()=>{document.querySelectorAll('[data-swimlog-calendar]').forEach(el=>{el.dataset.restUrl=location.origin+'/calendar';});window.wpApiSettings={nonce:'synthetic-rest-nonce'};window.swimlogCalendarSettings={ajaxUrl:'https://calendar.test/ajax',planNonce:'synthetic-plan-nonce',eventNonce:'synthetic-event-nonce'};});
  await page.addStyleTag({path:path.resolve('assets/css/calendar.css')});
  await page.addScriptTag({path:path.resolve('blocks/calendar/view.js')});
  await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  const root=page.locator('[data-swimlog-calendar="1"]');
  await page.waitForFunction(()=>!document.querySelector('[data-swimlog-calendar]').classList.contains('is-loading'));
  const mobile=options.viewport.width<=700;
  const dayArea=()=>root.locator(mobile?'[data-calendar-agenda]':'.swimlog-calendar-grid');
  const dayButton=(date)=>dayArea().locator(`[data-calendar-day="${date}"]`);
  const open=async(date)=>{await dayButton(date).click();await page.getByRole('dialog').waitFor();};
  const close=async()=>{await page.getByRole('button',{name:'Close day details',exact:true}).click();await page.getByRole('dialog').waitFor({state:'detached'});};
  const state=await root.evaluate(el=>({view:el.dataset.view,date:el.dataset.focusDate,user:el.dataset.user}));
  await open(data.date);
  let dialog=page.getByRole('dialog');
  assert.equal(await dialog.locator('[data-calendar-workout]').count(),2);
  assert.equal(await dialog.locator('[data-calendar-planned-workout]').count(),12);
  assert.equal(await dialog.locator('[data-calendar-event]').count(),1);
  assert.match(await dialog.locator('.swimlog-calendar-day-summary').innerText(),/1,800 m recorded/);
  const bounds=await dialog.boundingBox(); assert(bounds.x>=0&&bounds.y>=0&&bounds.x+bounds.width<=options.viewport.width+1&&bounds.y+bounds.height<=options.viewport.height+1,'Dialog exceeds viewport');
  assert(await dialog.locator('.swimlog-calendar-day-body').evaluate(el=>el.scrollHeight>el.clientHeight),'Busy day must scroll internally');
  assert(await dialog.evaluate(el=>el.scrollWidth<=el.clientWidth+1),'Horizontal dialog overflow');
  assert(await dialog.locator('.swimlog-calendar-day-close').evaluate(el=>el.getBoundingClientRect().height>=44),'Close touch target too small');
  console.log('SWIMLOG_CALENDAR_QA_'+name.split(' ')[0].toUpperCase().replace(/[^A-Z]/g,'')+'_BASE64='+(await page.screenshot()).toString('base64'));
  if (mobile) {
   const landscape={width:options.viewport.height,height:options.viewport.width};
   await page.setViewportSize(landscape);
   const rotated=await dialog.boundingBox(); assert(rotated.x>=0&&rotated.y>=0&&rotated.x+rotated.width<=landscape.width+1&&rotated.y+rotated.height<=landscape.height+1,'Rotated dialog exceeds viewport');
   await page.setViewportSize(options.viewport);
  }
  // Tab remains within the native modal; Escape closes it and restores the date trigger.
  for(let i=0;i<20;i++) {await page.keyboard.press('Tab');assert(await dialog.evaluate(el=>el.contains(document.activeElement)),'Focus escaped modal');}
  await page.keyboard.press('Escape'); await dialog.waitFor({state:'detached'});
  assert(await dayButton(data.date).evaluate(el=>el===document.activeElement),'Focus not restored');
  assert.deepEqual(await root.evaluate(el=>({view:el.dataset.view,date:el.dataset.focusDate,user:el.dataset.user})),state);
  // Existing direct-item behavior remains inline and does not open a day modal.
  await dayArea().locator('[data-calendar-workout]').first().click();
  assert.equal(await page.getByRole('dialog').count(),0);
  await root.locator('[data-calendar-detail-close]').click();
  await open(data.date); dialog=page.getByRole('dialog');
  await dialog.locator('[data-calendar-workout]').first().click();
  assert.equal(await dialog.locator('[data-calendar-detail] a').getAttribute('target'),'_blank');
  await dialog.locator('[data-calendar-detail-close]').click();
  await dialog.locator('[data-calendar-event]').click();
  await dialog.locator('[name="result_time"]').waitFor();
  await page.waitForFunction(()=>document.querySelector('dialog [name="result_time"]').value==='1:20.00');
  await dialog.locator('[data-calendar-detail-close]').click();
  await dialog.locator('[data-calendar-planned-workout]').first().click();
  await dialog.getByRole('button',{name:'Edit',exact:true}).click();
  await dialog.locator('[name="title"]').fill('Edited inside expanded day');
  await dialog.getByRole('button',{name:'Update Planned Workout',exact:true}).click();
  await page.waitForFunction(()=>!document.querySelector('dialog')&&!document.querySelector('[data-swimlog-calendar]').classList.contains('is-loading'));
  assert.equal(saveCount,1,'Existing plan action did not submit');
  await open(data.empty_date); assert.match(await page.getByRole('dialog').innerText(),/No workouts or events for this day/); await close();
  // Blank day space opens the same view without changing navigation.
  const cell=dayArea().locator(`[data-date="${data.empty_date}"]`);
  await cell.evaluate(el=>el.click()); await page.getByRole('dialog').waitFor(); await close();
  // Outside tap/click closes while an inside click stays open; verify scroll restoration.
  await dayButton(data.date).scrollIntoViewIfNeeded(); const scroll=await page.evaluate(()=>({x:scrollX,y:scrollY}));
  await open(data.date); dialog=page.getByRole('dialog');
  await dialog.locator('h2').click(); assert.equal(await page.getByRole('dialog').count(),1);
  if(options.hasTouch) await page.touchscreen.tap(1,1); else await page.mouse.click(1,1);
  await dialog.waitFor({state:'detached'});
  assert.deepEqual(await page.evaluate(()=>({x:scrollX,y:scrollY})),scroll,'Closing changed host scroll');
  // Week view uses the same date dialog, including the mobile agenda.
  await root.locator('[data-calendar-view="week"]').click();
  await page.waitForFunction(()=>!document.querySelector('[data-swimlog-calendar]').classList.contains('is-loading'));
  await root.locator('[data-calendar-go-date]').fill(data.date); await root.locator('[data-calendar-go]').click();
  await page.waitForFunction(date=>document.querySelector('[data-swimlog-calendar]').dataset.focusDate===date&&!document.querySelector('[data-swimlog-calendar]').classList.contains('is-loading'),data.date);
  await open(data.date); assert.equal(await page.getByRole('dialog').locator('[data-calendar-planned-workout]').count(),12); await close();
  assert.equal(await root.getAttribute('data-view'),'week');
  // Adjacent-month dates use the existing authorized range endpoint; failures stay explicit.
  await root.locator('[data-calendar-view="month"]').click();
  await page.waitForFunction(()=>document.querySelector('[data-swimlog-calendar]').dataset.view==='month'&&!document.querySelector('[data-swimlog-calendar]').classList.contains('is-loading'));
  const outside=await root.locator('.swimlog-calendar-grid .is-outside-month').first().getAttribute('data-date');
  failDay=true;
  // On phones the adjacent grid is hidden; invoke the same date trigger programmatically.
  await root.locator(`.swimlog-calendar-grid [data-calendar-day="${outside}"]`).evaluate(el=>el.click());
  await page.getByRole('dialog').getByRole('button',{name:'Retry',exact:true}).waitFor();
  assert.match(await page.getByRole('dialog').innerText(),/Unable to load this day/);
  const request=requests.filter(r=>r.url.includes('start='+outside)).pop();
  assert(request.url.includes('user='+state.user));assert.equal(request.headers['x-wp-nonce'],'synthetic-rest-nonce');
  failDay=false; await page.getByRole('button',{name:'Retry',exact:true}).click();
  await page.getByRole('dialog').getByText('No workouts or events for this day.',{exact:true}).waitFor(); await close();
  delayDay=true; await root.locator(`.swimlog-calendar-grid [data-calendar-day="${outside}"]`).evaluate(el=>el.click()); await close();
  await page.waitForTimeout(200); assert.equal(await page.getByRole('dialog').count(),0);assert(!await page.locator('body').evaluate(el=>el.classList.contains('swimlog-calendar-modal-open')));
  assert.equal(errors.length,0,errors.join('\n'));
  console.log(name+': month/week, responsive agenda, scroll/focus, dismissal, empty/busy days, existing actions and authorized adjacent-day requests passed.');
  await context.close();
 } finally {await browser.close();}
}
(async()=>{
 await scenario(chromium,'Desktop Chromium',{viewport:{width:1440,height:1000}});
 await scenario(chromium,'Android-sized touch Chromium',{viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2});
 await scenario(webkit,'iPhone-sized touch WebKit',{viewport:{width:375,height:812},isMobile:true,hasTouch:true,deviceScaleFactor:2});
})().catch(error=>{console.error(error);process.exit(1);});
