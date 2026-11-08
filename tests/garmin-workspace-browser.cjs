/* Real rendered synthetic owner markup and production assets; no live site or Garmin access. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium, webkit} = require('playwright');
const log = fs.readFileSync(process.argv[2], 'utf8');
const fixture = name => Buffer.from(log.match(new RegExp('SWIMLOG_GARMIN_'+name+'_HTML_BASE64=([A-Za-z0-9+/=]+)'))[1], 'base64').toString('utf8');
async function scenario(engine, name, options) {
 const browser = await engine.launch({headless:true});
 try {
  const page = await browser.newPage(options); const errors=[];
  page.on('pageerror', e=>errors.push(e.message));
  await page.route('**/*', route=>route.fulfill({body:'',contentType:'text/plain'}));
  const mount = async state => {
   await page.setContent('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:12px;background:#f0f0f1;font:14px/1.5 sans-serif}input,select,button{font:inherit}input[type=password]{width:25em}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:8px}.form-table th{width:200px}.button{display:inline-block;padding:6px 12px;border:1px solid #2271b1;color:#135e96;text-decoration:none}h1{font-size:24px}h2{font-size:20px}h3{font-size:16px}</style>'+fixture(state));
   await page.addStyleTag({path:path.resolve('assets/css/admin.css')});
   await page.addStyleTag({path:path.resolve('assets/css/garmin-history.css')});
   await page.addScriptTag({path:path.resolve('assets/js/admin.js')});
   await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  };
  for(const state of ['SETUP','SAVED']) {
   await mount(state);
   assert.equal(await page.locator('.swimlog-garmin-section').count(),1);
   assert.equal(await page.locator('#swimlog-garmin-settings').getAttribute('open'),'');
   assert.equal(await page.locator('#swimlog-garmin-api-key-submit').isDisabled(),true);
   await page.locator('#swimlog-garmin-api-key').fill('synthetic-only-input');
   assert.equal(await page.locator('#swimlog-garmin-api-key-submit').isEnabled(),true);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,JSON.stringify(await page.evaluate(()=>({innerWidth,scrollWidth:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll('*')].filter(e=>e.getBoundingClientRect().right>innerWidth).map(e=>({tag:e.tagName,id:e.id,right:e.getBoundingClientRect().right}))}))));
  }
  await mount('READY');
  const ids=await page.locator('.swimlog-garmin > .swimlog-garmin-section').evaluateAll(nodes=>nodes.map(n=>n.id));
  assert.deepEqual(ids,['swimlog-garmin-activities','swimlog-garmin-measurements','swimlog-garmin-sync','swimlog-garmin-history','swimlog-garmin-settings']);
  assert.equal(await page.locator('#swimlog-garmin-settings').getAttribute('open'),null);
  assert.equal(await page.locator('#swimlog-garmin-sync input[name="garmin_sync_types[]"]').count(),4);
  assert.equal(await page.locator('#swimlog-garmin-sync input[name="scheduled_categories[]"]').count(),3);
  await page.locator('.swimlog-garmin-nav a[href="#swimlog-garmin-settings"]').click();
  assert.equal(await page.locator('#swimlog-garmin-settings').getAttribute('open'),'');
  assert.equal(await page.locator('#swimlog-garmin-api-key').inputValue(),'');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,JSON.stringify(await page.evaluate(()=>({innerWidth,scrollWidth:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll('*')].filter(e=>e.getBoundingClientRect().right>innerWidth).map(e=>({tag:e.tagName,id:e.id,right:e.getBoundingClientRect().right}))}))));
  assert.deepEqual(errors,[]);
  console.log(name+': Garmin setup/saved/ready layout, section order, independent selections, settings navigation and no page overflow passed.');
 } finally {await browser.close();}
}
(async()=>{
 await scenario(chromium,'Desktop Chromium',{viewport:{width:1440,height:1000}});
 await scenario(chromium,'Android-sized touch Chromium',{viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 await scenario(webkit,'iPhone-sized touch WebKit',{viewport:{width:375,height:812},isMobile:true,hasTouch:true});
})().catch(e=>{console.error(e);process.exitCode=1;});
