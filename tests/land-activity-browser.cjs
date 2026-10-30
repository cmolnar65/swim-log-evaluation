const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium,webkit}=require('playwright');
const log=fs.readFileSync(process.argv[2],'utf8');
const html=Buffer.from(log.match(/SWIMLOG_LAND_DETAIL_HTML_BASE64=([A-Za-z0-9+/=]+)/)[1],'base64').toString('utf8');
(async()=>{
 for(const [engine,name,viewport,touch] of [[chromium,'Desktop',{width:1440,height:1000},false],[chromium,'Android touch',{width:390,height:844},true],[webkit,'iPhone touch',{width:375,height:812},true]]){
  const browser=await engine.launch({headless:true});
  try{
   const page=await browser.newPage({viewport,isMobile:touch,hasTouch:touch});let requests=0;
   await page.route('**/*',route=>{requests++;return route.abort();});
   await page.setContent('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:12px;font:14px sans-serif}.widefat{width:100%;border-collapse:collapse}.widefat th,.widefat td{text-align:left;padding:8px;overflow-wrap:anywhere}</style>'+html);
   await page.addStyleTag({path:'assets/css/admin.css'});
   const map=page.getByRole('img',{name:'Recorded activity GPS route map'});await map.waitFor();
   assert.equal(await map.locator('polyline').count(),1);
   const box=await map.boundingBox();assert(box.width>0&&box.width<=viewport.width,'Map must scale on mobile');
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Activity detail must not overflow horizontally');
   assert.equal(requests,0,'Private map must make no external tile/GPS requests');
   assert.doesNotMatch(await page.locator('body').innerText(),/SWOLF|Personal best|Readiness|Create Post/);
   console.log(name+': private recorded run summary and shared SVG route fit the viewport with no external map requests.');
  }finally{await browser.close();}
 }
})().catch(e=>{console.error(e);process.exit(1);});
