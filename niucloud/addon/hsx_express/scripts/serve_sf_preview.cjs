'use strict'
// Serve real Vue / Element Plus components with memory-only APIs for manual CUA review.
// No Playwright, production requests, accounts, database writes or courier requests.
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), crypto = require('node:crypto'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), base = path.join(root, 'niucloud/addon/hsx_express/admin'), deps = path.join(root, 'admin/node_modules')
const sfc = require(path.join(deps, '@vue/compiler-sfc')), esbuild = require(path.join(deps, 'esbuild')), sass = require(path.join(deps, 'sass'))
const sfOptions = {
    environments: [{ value: 'sandbox', label: '沙箱测试（不能真实发货）' }, { value: 'production', label: '生产环境（真实业务）' }],
    products: [{ value: '1', label: '顺丰特快' }, { value: '2', label: '顺丰标快' }],
    pay_methods: [{ value: 1, label: '寄方付' }, { value: 2, label: '收方付（到付）' }, { value: 3, label: '第三方付（需授权）' }]
}
const config = scene => ({ enabled: scene === 'waybill' ? 1 : 0, environment: 'sandbox', client_code: scene === 'waybill' ? 'QA_WAYBILL' : 'QA_PICKUP', check_word: '******', has_check_word: true, monthly_card: '', product_code: '2', pay_method: 1, template_code: scene === 'waybill' ? 'QA_PDF_TEMPLATE' : '', callback_base_url: '', use_ack: 1, provider: 'sf_direct', scene, options: sfOptions,
    readiness: { ready: true, external_verified: false, checks: [{ key: 'client_code', label: '顺丰顾客编码', passed: true, message: '仅模拟配置' }, { key: 'check_word', label: '当前环境校验码', passed: true, message: '仅模拟配置' }, { key: 'confirmation', label: '开通确认', passed: true, message: '仅模拟配置' }] } })
const task = (id, state, environment, overrides = {}) => ({ id, task_id: id, task_no: `QA-SF-${id}`, business_no: `QA-ORDER-${id}`, business_type: 'phone_shop', business_id: `${id}:internal-package`, provider_code: 'hsx_express_sf_direct', provider_key: 'hsx_express_sf_direct', carrier: 'shunfeng', carrier_name: '顺丰速运', exp_type: '2', exp_type_name: '顺丰标快', print_type: 'PDF', state, state_name: state === 'ready' ? '原单 PDF 已生成' : state === 'unknown' ? '顺丰取号结果待核实' : state === 'cancel_unknown' ? '顺丰取消结果待核实' : '已取号，PDF 待获取', environment, can_confirm_delivery: environment === 'production' && state === 'ready', waybill_no: state === 'unknown' ? '' : `QA-SF-WAYBILL-${id}`, express_company_id: 1, can_download: state === 'ready', can_refresh: true, can_reprint: !['unknown', 'cancel_unknown'].includes(state), can_cancel: state !== 'unknown', can_recover: false, label_state: state === 'ready' ? 'ready' : 'unavailable', pdf_download_path: state === 'ready' ? `hsx_express/tasks/${id}/pdf` : '', message: state === 'unknown' ? '模拟超时：先查询原顺丰单，不得重复取号或切换渠道' : '仅本地预览任务；PDF 下载和查询均为内存模拟，不会联系顺丰', create_at: 1790899200, update_at: 1790899800, logs: [{ operation: 'sf_order_created', at: 1790899200, message: '模拟：原顺丰单受理成功' }, { operation: state === 'ready' ? 'sf_pdf_ready' : 'sf_query_unconfirmed', at: 1790899800, message: state === 'ready' ? '模拟：原单 PDF 已就绪' : '模拟：原单查询结果待核实' }], ...overrides })
const fixture = {
    sfConfigs: { waybill: config('waybill'), pickup: config('pickup') },
    tasks: [task(101, 'ready', 'production'), task(102, 'unknown', 'production'), task(103, 'ready', 'sandbox'), task(104, 'print_failed', 'production', { label_state: 'expired' }), task(105, 'cancel_unknown', 'production', { message: '模拟：原单取消结果待核实，仅允许重试原单取消，不能重下。' })],
    selectedShopTask: 103, operations: [], routes: [], messages: [], failSave: false, failConfigLoad: false, failDownload: false
}
function compile(file) {
    const { descriptor, errors } = sfc.parse(fs.readFileSync(file, 'utf8'), { filename: file }); assert.deepEqual(errors, [])
    const id = crypto.createHash('sha256').update(file).digest('hex').slice(0, 8)
    const script = sfc.compileScript(descriptor, { id })
    const template = sfc.compileTemplate({ source: descriptor.template.content, filename: file, id, scoped: descriptor.styles.some(style => style.scoped), compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } }); assert.deepEqual(template.errors, [])
    const styles = descriptor.styles.map(style => {
        const source = style.lang === 'scss' ? sass.compileString(style.content, { loadPaths: [path.dirname(file)] }).css : style.content
        const result = sfc.compileStyle({ source, id: 'data-v-' + id, scoped: style.scoped }); assert.deepEqual(result.errors, []); return result.code
    }).join('\n')
    return script.content.replace('export default', 'const __component =') + '\n' + template.code + '\n__component.render=render;__component.__scopeId=' + JSON.stringify('data-v-' + id) + ';export default __component;\nconst style=document.createElement("style");style.textContent=' + JSON.stringify(styles) + ';document.head.appendChild(style);'
}
const apiFixture = `
const state=window.qa;const copy=value=>JSON.parse(JSON.stringify(value));const wait=()=>new Promise(resolve=>setTimeout(resolve,180));
function row(id){const found=state.tasks.find(item=>item.id===Number(id));if(!found)throw new Error('模拟任务不存在');return found}
export async function getSfConfig(scene){await wait();if(state.failConfigLoad){state.failConfigLoad=false;throw {msg:'模拟读取失败，原配置未修改'}}return {data:copy(state.sfConfigs[scene])}}
export async function saveSfConfig(scene,data){await wait();if(state.failSave){state.failSave=false;throw {msg:'模拟保存失败；请重试'}}const old=state.sfConfigs[scene],changed=old.environment!==data.environment||old.client_code!==data.client_code;state.operations.push({kind:'save-config',scene,fields:Object.keys(data)});const hasSecret=data.clear_secrets?.includes('check_word')?false:!!data.check_word||(!changed&&old.has_check_word);const next={...old,...data,has_check_word:hasSecret,check_word:hasSecret?'******':''};if(changed){next.enabled=0;next.use_ack=0}delete next.clear_secrets;state.sfConfigs[scene]=next;const result=copy(next);if(changed)result.save_notice='账号或环境已变更，当前业务已关闭；请核对后重新启用。';return {data:result}}
export async function checkSfConfig(scene){await wait();const c=state.sfConfigs[scene],checks=[{key:'client_code',label:'顺丰顾客编码',passed:!!c.client_code,message:'请填写顾客编码'},{key:'check_word',label:'当前环境校验码',passed:c.has_check_word,message:'请填写校验码'},{key:'confirmation',label:'开通确认',passed:!!c.use_ack,message:'请确认本站账号已开通本产品'}];c.readiness={ready:checks.every(x=>x.passed),checks,external_verified:false};return {data:copy(c.readiness)}}
export async function getExpressTasks(params={}){await wait();const rows=state.tasks.filter(item=>(!params.state||item.state===params.state)&&(!params.keyword||JSON.stringify(item).includes(params.keyword)));return {data:{data:copy(rows),total:rows.length}}}
export async function getExpressTask(id){await wait();return {data:copy(row(id))}}
export async function refreshExpressTask(id){await wait();state.operations.push({kind:'query-original',id});return {data:copy(row(id))}}
export async function reprintExpressTask(id){await wait();const t=row(id);state.operations.push({kind:'original-pdf',id});t.label_state='ready';t.can_download=true;t.state='ready';t.state_name='原单 PDF 已生成';t.pdf_download_path='hsx_express/tasks/'+id+'/pdf';return {data:copy(t)}}
export async function cancelExpressTask(id,reason){await wait();const t=row(id);state.operations.push({kind:'cancel-original',id,reason});t.state='cancelled';t.state_name='顺丰运单已取消';t.can_download=false;t.can_cancel=false;t.can_reprint=false;t.can_confirm_delivery=false;t.can_refresh=false;return {data:copy(t)}}
export async function recoverExpressTask(){throw new Error('顺丰不支持重新创建方式恢复')}
export async function downloadExpressTaskPdf(id){await wait();state.operations.push({kind:'pdf-download',id});if(state.failDownload){state.failDownload=false;return new Blob([JSON.stringify({msg:'模拟 PDF 已过期，请重新获取原单 PDF'})],{type:'application/json'})}return new Blob([Uint8Array.from(atob(${JSON.stringify(fs.readFileSync(path.join(__dirname, 'fixtures/print-preview-pdf.base64'), 'utf8').trim())}), ch=>ch.charCodeAt(0))],{type:'application/pdf'})}
export async function electronicSheetProviderTask(payload){state.operations.push({kind:'shop-'+payload.operation});const id=state.selectedShopTask;if(payload.operation==='query'||payload.operation==='refresh')return getExpressTask(id);if(payload.operation==='reprint')return reprintExpressTask(id);if(payload.operation==='cancel')return cancelExpressTask(id,payload.reason);throw new Error('预览只提供既有任务，不创建运单')}
export async function electronicSheetProviderPdf(task){return downloadExpressTaskPdf(task.task_id||task.id)}
`
async function main() {
    const entry = `
import {createApp,h,ref,computed} from 'vue';import ElementPlus from 'element-plus';import 'element-plus/dist/index.css';
import SfConfig from ${JSON.stringify(path.join(base, 'components/sf-config-panel.vue'))};
import Tasks from ${JSON.stringify(path.join(base, 'views/tasks/index.vue'))};
import ShopPanel from ${JSON.stringify(path.join(root, 'niucloud/addon/phone_shop/admin/views/order/components/provider-waybill-panel.vue'))};
import {providerTaskCanConfirm} from ${JSON.stringify(path.join(root, 'niucloud/addon/phone_shop/admin/utils/electronic-sheet-provider.ts'))};
createApp({setup(){const tab=ref('waybill'),revision=ref(0),shopTask=ref({}),message=ref('');window.qaNotice=text=>message.value=text;const provider={key:'hsx_express_sf_direct',label:'顺丰直连',external:true,config_url:'/hsx_express/config?provider=sf_direct&scene=waybill',tasks_url:'/hsx_express/tasks'};const choose=name=>{tab.value=name;revision.value++;shopTask.value={}};return()=>h('main',[
h('div',{class:'qa-warning'},'本地隔离预览 · 全部账号/任务均为内存假数据，保存、查询、取消和下载不访问顺丰、不接触线上数据库。'),
h('nav',{},['waybill','pickup','tasks','shop'].map((name,i)=>h('button',{'data-testid':'qa-'+name,class:{active:tab.value===name},onClick:()=>choose(name)},['面单配置','上门取件配置','顺丰任务记录','商城发货面板'][i]))),
h('div',{class:'qa-tools'},[h('button',{onClick:()=>{window.qa.failSave=true;message.value='下一次保存将模拟失败'}},'模拟下次保存失败'),h('button',{onClick:()=>{window.qa.failConfigLoad=true;revision.value++;}},'模拟读取失败'),h('button',{onClick:()=>{window.qa.failDownload=true;message.value='下一次下载将模拟过期'}},'模拟下次 PDF 过期'),message.value?h('span',message.value):null]),
tab.value==='tasks'?h(Tasks,{key:revision.value}):tab.value==='shop'?h('section',{class:'qa-shop'},[
h('h2','模拟商城物流发货：仅展示真实面单面板'),
h('div',{class:'qa-tools'},[h('label','选择包裹场景：'),h('select',{value:window.qa.selectedShopTask,onChange:event=>{window.qa.selectedShopTask=Number(event.target.value);revision.value++;shopTask.value={}}},[h('option',{value:101},'生产模拟：PDF 就绪'),h('option',{value:102},'生产模拟：取号待核实'),h('option',{value:103},'沙箱模拟：PDF 就绪'),h('option',{value:104},'生产模拟：PDF 过期'),h('option',{value:105},'生产模拟：取消待核实')])]),
h(ShopPanel,{key:revision.value,orderId:window.qa.selectedShopTask,goodsIds:[1],provider,onTask:value=>shopTask.value=value}),
h('button',{class:'qa-primary',disabled:!shopTask.value.task_id||!providerTaskCanConfirm(shopTask.value),onClick:()=>message.value='仅模拟：没有调用真实确认发货接口'},'已交件，确认发货（仅模拟）'),
h('p',{class:'qa-footnote'},'生产任务仍为本地假数据。沙箱任务必须禁用确认发货；按钮状态调用实际商城同一判断函数。')
]):h(SfConfig,{scene:tab.value,key:tab.value+revision.value})
])}}).use(ElementPlus).mount('#app');`
    const result = await esbuild.build({ stdin: { contents: entry, resolveDir: path.join(root, 'admin'), loader: 'js' }, bundle: true, write: false, outfile: '/tmp/hsx-sf-preview/app.js', nodePaths: [deps], platform: 'browser', define: { 'process.env.NODE_ENV': '"production"', __VUE_OPTIONS_API__: 'true', __VUE_PROD_DEVTOOLS__: 'false' }, plugins: [{ name: 'isolated-sf', setup(build) {
        build.onResolve({ filter: /^(\.\.\/api|\.\.\/\.\.\/api)$/ }, args => args.importer.startsWith(base) ? ({ path: 'api', namespace: 'fixture' }) : null)
        build.onResolve({ filter: /^@\/addon\/phone_shop\/api\/electronic_sheet$/ }, () => ({ path: 'api', namespace: 'fixture' }))
        build.onResolve({ filter: /^vue-router$/ }, () => ({ path: 'router', namespace: 'fixture' }))
        build.onResolve({ filter: /^@\/utils\/request$/ }, () => ({ path: 'forbidden-request', namespace: 'fixture' }))
        build.onLoad({ filter: /.*/, namespace: 'fixture' }, args => ({ contents: args.path === 'api' ? apiFixture : args.path === 'router' ? `export const useRouter=()=>({push:path=>{window.qa.routes.push(path);window.qaNotice('隔离预览拦截跳转：'+path)},resolve:path=>({href:typeof path==='string'?path:path.path,matched:[{}]})});` : `throw new Error('Preview forbids application request client');`, loader: 'js' }))
        build.onResolve({ filter: /^@\/addon\/hsx_components\/core$/ }, () => ({ path: 'core', namespace: 'hsx-core' }))
        build.onLoad({ filter: /.*/, namespace: 'hsx-core' }, () => ({ contents: 'import '+JSON.stringify(path.join(root, 'admin/src/addon/hsx_components/styles/theme.scss'))+';\n'+['HsxPage','HsxDrawer','HsxNotice','HsxFold'].map(name => 'export {default as '+name+'} from '+JSON.stringify(path.join(root,'admin/src/addon/hsx_components/components',name,'index.vue'))+';').join('\n'), loader:'js',resolveDir:root }))
        build.onResolve({ filter: /^@\// }, args => { const file=path.join(root,'admin/src',args.path.slice(2));const resolved=[file,file+'.ts',file+'.js',path.join(file,'index.ts')].find(candidate=>fs.existsSync(candidate)&&fs.statSync(candidate).isFile());return resolved?{path:resolved}:null })
        build.onLoad({ filter: /\.vue$/ }, args => ({ contents: compile(args.path), loader: 'ts', resolveDir: path.dirname(args.path) }))
        build.onLoad({ filter: /\.scss$/ }, args => ({ contents: sass.compile(args.path).css, loader: 'css' }))
    } }] })
    const assets=Object.fromEntries(result.outputFiles.map(file=>['/'+path.basename(file.path),file.contents]))
    const server=http.createServer((req,res)=>{
        res.setHeader('Cache-Control','no-store')
        res.setHeader('Content-Security-Policy',"default-src 'self' blob: data:; connect-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'none'")
        if(assets[req.url]){res.setHeader('Content-Type',req.url.endsWith('.css')?'text/css':'application/javascript');res.end(assets[req.url]);return}
        if(req.url!== '/'){res.writeHead(404);res.end('Isolated preview: no application endpoints');return}
        res.setHeader('Content-Type','text/html;charset=utf-8');res.end('<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>顺丰隔离 UI 预览</title><link rel="stylesheet" href="/app.css"><style>body{margin:0;background:#f5f7fa;font-family:Arial,"PingFang SC",sans-serif;color:#1f2937}main{padding:18px;max-width:1180px;margin:auto;min-width:0}*,*:before,*:after{box-sizing:border-box}.qa-warning{padding:12px;border:1px solid #eacb74;background:#fffbeb;font-size:13px;line-height:1.7;border-radius:6px}nav,.qa-tools{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0;align-items:center}nav button,.qa-tools button,.qa-tools select,.qa-primary{padding:8px 12px;background:white;border:1px solid #d5dce5;border-radius:5px;cursor:pointer}nav button.active,.qa-primary{background:#2563eb;color:white;border-color:#2563eb}.qa-primary:disabled{opacity:.4;cursor:not-allowed}.qa-tools{font-size:12px;color:#64748b}.qa-shop{padding:18px;background:white;border:1px solid #e5e7eb;border-radius:8px}.qa-shop h2{font-size:17px}.qa-footnote{font-size:12px;color:#64748b;line-height:1.7}@media(max-width:640px){main{padding:10px}.qa-shop{padding:10px}}</style></head><body><div id="app"></div><script>window.qa='+JSON.stringify(fixture)+';window.open=function(path){qa.routes.push(path);window.qaNotice&&window.qaNotice("隔离预览拦截跳转："+path);return null};document.addEventListener("click",function(event){const a=event.target.closest&&event.target.closest("a");if(a&&/^https?:/.test(a.href)){event.preventDefault();window.qaNotice&&window.qaNotice("隔离预览不访问外网："+a.href)}})</script><script src="/app.js"></script></body></html>')
    })
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve))
    console.log('SF_UI_PREVIEW_URL=http://127.0.0.1:'+server.address().port)
    console.log('Serve only. Manual browser review; no browser automation or third-party calls. Ctrl+C stops the preview.')
    for(const signal of ['SIGINT','SIGTERM'])process.on(signal,()=>server.close(()=>process.exit(0)))
}
main().catch(error=>{console.error(error);process.exitCode=1})
