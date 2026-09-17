'use strict'
// 编译真实拍摄组件、以隔离模拟接口验证交互。只监听本机，绝不请求生产接口。
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '..'), deps = path.join(root, 'admin/node_modules')
const esbuild = require(path.join(deps, 'esbuild'))
const sfc = require(path.join(deps, '@vue/compiler-sfc'))
const { chromium } = require(process.env.PLAYWRIGHT_PACKAGE || '/Users/a123/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')
const component = path.join(root, 'niucloud/addon/hsx_device_asset/admin/components/AutomaticPhotoStation.vue')
const out = '/tmp/hsx-photo-ui-smoke'
const apiStub = `
export async function createPhotoTask() { window.__photoTest.tasks++; return {data:{id:2}} }
export async function saveAssetMedia(id, data) { window.__photoTest.media++; return {data:{saved_media:[{id:100+window.__photoTest.media,url:data.media[0].url}]}} }
export async function confirmAssetPhotos(id, data) {
 const t=window.__photoTest; t.confirm++; t.selection=data.media_ids;
 if(t.failConfirm) {t.failConfirm=false;throw {code:0,msg:'ERP 暂未确认接收，请重试，无需重拍'}}
 return {data:{photo_handoff:{erp_asset_id:10},message:'ERP 已接收选中图片，请继续定价'}}
}`
const bootstrap = `
import {createApp,h,ref} from 'vue'; import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css'; import Station from ${JSON.stringify(component)};
localStorage.clear();localStorage.setItem('hsx_photo_station_settings_100',JSON.stringify({url:location.origin,token:'ui-test-only',profile:'fast'}));
const simulated=new URLSearchParams(location.search).has('demo');
const t=window.__photoTest={tasks:0,media:0,uploads:0,confirm:0,steps:0,failConfirm:true,failStep:false};
let job={inspection_id:1,context:{site_id:100,asset_id:7,task_id:2},state:'idle',photos:[],simulated,capture_seconds:0};
const reply=data=>new Response(JSON.stringify({ok:true,data}),{headers:{'Content-Type':'application/json'}});
window.fetch=async(url,options={})=>{
 const p=new URL(url,location.origin).pathname;
 if(p.endsWith('/capabilities')) return reply({protocol_version:1,station_id:'ui-test',ready:true,simulated});
 if(p.endsWith('/prepare')) return reply(job);
 if(p.endsWith('/step')) {
  t.steps++;if(t.failStep){t.failStep=false;return new Response(JSON.stringify({ok:false,code:'capture_failed',error:'模拟相机断线，当前角度尚未完成'}),{status:409})}
  job.photos.push({id:job.photos.length+1,face:job.photos.length<2?'front':'back',angle:job.photos.length%2*60,has_original:true});
  job.state=job.photos.length===2?'wait_flip':job.photos.length===4?'done':job.photos.length<2?'shooting_front':'shooting_back';job.capture_seconds+=1;
  return reply(job);
 }
 if(p.endsWith('/flip')) {job.state='shooting_back';return reply(job)}
 if(p.endsWith('/abort')) {job.state='error';return reply(job)}
 if(p.endsWith('/receipt')) {job.receipt={message:'ERP 已接收选中图片，请继续定价'};job.state='done';return reply(job)}
 if(p.includes('/photos/')) return new Response(Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZl8AAAAASUVORK5CYII='),c=>c.charCodeAt(0)),{headers:{'Content-Type':'image/png'}});
 if(p.endsWith('/sys/image')) {t.uploads++;return new Response(JSON.stringify({code:1,data:{url:'upload/test/'+t.uploads+'.jpg'}}))}
 if(p.endsWith('/inspections/1')) return reply(job);
 throw new Error('禁止未知或外部测试请求：'+p);
};
createApp({setup(){const fallback=ref(false);return()=>h('main',{style:'max-width:920px;margin:20px auto;padding:16px;background:white'},[
 h('h3','隔离交互验收 · 全部为模拟数据'),h(Station,{asset:{id:7,model:'测试设备',ext_json:{erp_asset_id:10}},onFallback:()=>fallback.value=true}),
 fallback.value?h('div',{id:'fallback'},'手机补拍入口已展开'):null]);}}).use(ElementPlus).mount('#app');
`
async function main() {
 fs.mkdirSync(out, {recursive:true})
 await esbuild.build({stdin:{contents:bootstrap,resolveDir:path.join(root,'admin'),loader:'js'},bundle:true,outfile:path.join(out,'test.js'),platform:'browser',nodePaths:[deps],define:{'process.env.NODE_ENV':'"production"','import.meta.env.VITE_APP_BASE_URL':'"/adminapi/"','import.meta.env.VITE_REQUEST_HEADER_TOKEN_KEY':'"token"','import.meta.env.VITE_REQUEST_HEADER_SITEID_KEY':'"site-id"',__VUE_OPTIONS_API__:'true',__VUE_PROD_DEVTOOLS__:'false'},plugins:[{name:'actual-vue-component',setup(build){
  build.onResolve({filter:/utils\/common$/},()=>({path:'common',namespace:'stub'}))
  build.onResolve({filter:/utils\/storage$/},()=>({path:'storage',namespace:'stub'}))
  build.onResolve({filter:/api\/device_asset$/},()=>({path:'api',namespace:'stub'}))
  build.onLoad({filter:/.*/,namespace:'stub'},args=>({contents:args.path==='api'?apiStub:args.path==='storage'?'export default {get:()=>100}':'export const getToken=()=>"ui-test-token"',loader:'js'}))
  build.onLoad({filter:/\.vue$/},args=>{
   const {descriptor:d,errors}=sfc.parse(fs.readFileSync(args.path,'utf8'),{filename:args.path});assert.equal(errors.length,0)
   const script=sfc.compileScript(d,{id:'photo-test'}),template=sfc.compileTemplate({source:d.template.content,filename:args.path,id:'photo-test',compilerOptions:{bindingMetadata:script.bindings,expressionPlugins:['typescript']}});assert.equal(template.errors.length,0)
   const styles=d.styles.map(s=>sfc.compileStyle({source:s.content,id:'data-v-photo-test',scoped:s.scoped}).code).join('\n');
   const css='const style=document.createElement("style");style.textContent='+JSON.stringify(styles)+';document.head.appendChild(style);';
   return {contents:script.content.replace('export default','const __component =')+'\n'+template.code+'\n__component.render=render;__component.__scopeId="data-v-photo-test";export default __component;\n'+css,loader:'ts',resolveDir:path.dirname(args.path)};
  });
 }}]})
 const server=http.createServer((req,res)=>{
  res.setHeader('Content-Security-Policy',"default-src 'self'; connect-src 'self'; img-src 'self' blob: data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'")
  if(req.url.startsWith('/test.')){const f=path.join(out,req.url.split('?')[0]);res.setHeader('Content-Type',f.endsWith('.css')?'text/css':'application/javascript');fs.createReadStream(f).pipe(res)}
  else {res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<html lang="zh"><meta charset="utf-8"><link rel="stylesheet" href="/test.css"><body style="margin:0;background:#f4f6f8"><div id="app"></div><script src="/test.js"></script></body></html>')}
 })
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve))
 const url='http://127.0.0.1:'+server.address().port
 let browser
 try {
  browser=await chromium.launch({headless:true,channel:process.env.PHOTO_TEST_BROWSER_CHANNEL || 'chrome'})
  const page=await browser.newPage({viewport:{width:1280,height:720}}),errors=[]
  page.on('pageerror',e=>errors.push(e.message))
  await page.goto(url);await page.getByRole('button',{name:'开始自动拍摄',exact:true}).waitFor()
  await page.waitForFunction(()=>!document.querySelector('.station__actions button').disabled)
  assert.equal(await page.evaluate(()=>window.__photoTest.steps),0,'连接不能自动触发拍摄')
  await page.getByRole('button',{name:'开始自动拍摄',exact:true}).click()
  await page.getByRole('button',{name:'已翻面，继续拍摄',exact:true}).waitFor()
  assert.equal(await page.locator('.station__photos article').count(),2)
  await page.getByRole('button',{name:'已翻面，继续拍摄',exact:true}).click()
  await page.getByRole('button',{name:'采用 4 张，提交 ERP',exact:true}).waitFor()
  await page.locator('.station__photos article').last().locator('label.el-checkbox').click()
  await page.getByRole('button',{name:'采用 3 张，提交 ERP',exact:true}).click()
  await page.getByText('ERP 暂未确认接收，请重试，无需重拍',{exact:true}).waitFor()
  assert.equal(await page.evaluate(()=>window.__photoTest.uploads),3)
  await page.getByRole('button',{name:'采用 3 张，提交 ERP',exact:true}).click()
  await page.getByRole('button',{name:'拍下一台',exact:true}).waitFor()
  assert.ok(await page.locator('.station__photos article input[type=checkbox]').first().isDisabled(),'交接完成后选图应锁定，避免误以为可以更改回执')
  assert.equal(await page.evaluate(()=>window.__photoTest.uploads),3,'失败重试不应重复上传已保存图片')
  assert.equal(await page.evaluate(()=>window.__photoTest.confirm),2)
  await page.screenshot({path:path.join(out,'confirmed-1280.png'),fullPage:true})
  for(const width of [1280,960,600]) {await page.setViewportSize({width,height:720});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'不得产生横向溢出 '+width)}
  await page.goto(url+'?demo=1');await page.getByRole('button',{name:'开始自动拍摄',exact:true}).click();await page.getByRole('button',{name:'已翻面，继续拍摄',exact:true}).waitFor()
  assert.ok(await page.getByRole('button',{name:'采用 2 张，提交 ERP',exact:true}).isDisabled(),'模拟图必须禁止 ERP 提交')
  assert.equal(await page.evaluate(()=>window.__photoTest.tasks),0,'模拟拍摄不能创建 ERP 工单')
  await page.goto(url);await page.waitForFunction(()=>!document.querySelector('.station__actions button').disabled)
  await page.evaluate(()=>{window.__photoTest.failStep=true});await page.getByRole('button',{name:'开始自动拍摄',exact:true}).click()
  await page.getByText('模拟相机断线，当前角度尚未完成',{exact:true}).waitFor()
  await page.getByRole('button',{name:'继续自动拍摄',exact:true}).click();await page.getByRole('button',{name:'已翻面，继续拍摄',exact:true}).waitFor()
  await page.getByRole('button',{name:'停止自动拍摄，手机补拍',exact:true}).click()
  await page.getByRole('button',{name:'保存并手机补拍',exact:true}).click();await page.locator('#fallback').waitFor()
  assert.equal(await page.evaluate(()=>window.__photoTest.uploads),2,'转手机前保留已选照片')
  assert.equal(await page.evaluate(()=>window.__photoTest.confirm),0,'补拍未完成前不提前交接')
  assert.deepEqual(errors,[])
  console.log('PASS UI：连接不拍摄、前后翻面、选图、断线继续、上传重试不重复、模拟隔离、手机补拍保留图片；1280/960/600 无横向溢出。')
  console.log('截图：'+path.join(out,'confirmed-1280.png'))
 } finally {if(browser)await browser.close();await new Promise(resolve=>server.close(resolve))}
}
main().catch(error=>{console.error(error);process.exitCode=1})
