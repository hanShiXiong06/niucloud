'use strict'
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict'), crypto = require('node:crypto')
const root = path.resolve(__dirname, '..'), deps = path.join(root, 'admin/node_modules'), base = path.join(root, 'niucloud/addon/phone_shop/admin')
const sfc = require(path.join(deps, '@vue/compiler-sfc')), esbuild = require(path.join(deps, 'esbuild'))
const { chromium } = require(process.env.PLAYWRIGHT_PACKAGE || '/Users/a123/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')
const out = '/tmp/phone-shop-return-ui'
function compile(file) {
    const {descriptor:d,errors} = sfc.parse(fs.readFileSync(file,'utf8'), {filename:file}); assert.deepEqual(errors,[])
    const id = crypto.createHash('sha256').update(file).digest('hex').slice(0,8), script = sfc.compileScript(d,{id})
    const template = sfc.compileTemplate({source:d.template.content,filename:file,id,compilerOptions:{bindingMetadata:script.bindings,expressionPlugins:['typescript']}}); assert.deepEqual(template.errors,[])
    const styles = d.styles.map(s => {const r=sfc.compileStyle({source:s.content,id:'data-v-'+id,scoped:s.scoped}); assert.deepEqual(r.errors,[]); return r.code}).join('\n')
    return script.content.replace('export default', 'const __component =')+'\n'+template.code+'\n__component.render=render;__component.__scopeId='+JSON.stringify('data-v-'+id)+';export default __component;\nconst style=document.createElement("style");style.textContent='+JSON.stringify(styles)+';document.head.appendChild(style);'
}
async function main() {
    for (const file of ['views/order/list.vue','views/order/detail.vue','views/order/components/order-detail.vue','views/order/components/order-device-identity.vue','views/order/components/order-return-guide.vue']) compile(path.join(base,file))
    compile(path.join(root,'niucloud/addon/hsx_erp/admin/views/erp/sale_return/list.vue'))
    fs.mkdirSync(out,{recursive:true})
    const bootstrap = `import {createApp,h,reactive} from 'vue';import ElementPlus from 'element-plus';import 'element-plus/dist/index.css';
        import Identity from ${JSON.stringify(path.join(base,'views/order/components/order-device-identity.vue'))};
        import Guide from ${JSON.stringify(path.join(base,'views/order/components/order-return-guide.vue'))};
        createApp({setup(){const row=reactive({order_goods_id:42,device_identity:{imei:'357465822199501',sn:'TESTSN001',source:'原订单记录'},can_confirm_received:true});
        const order=reactive({payment_mode:'offline_credit',return_handler_name:'原业务员',erp_return_context:{sale_order_id:33,receivable_remaining:0,refund_pending:1000,items:[{asset_id:50,status:'sold'}]}});window.testState.order=order;
        return()=>h('main',[h('h2','线下订单 / 订单 · 退回设备'),h('section',[h('h3','原订单 TEST-ORDER-001'),h('div','测试手机 256G'),h(Identity,{row,onComplete:()=>{row.can_confirm_received=false;row.return_state='returned';row.return_receiver='收货测试员'}}),h(Guide,{order})])])}}).use(ElementPlus).mount('#app');`
    await esbuild.build({stdin:{contents:bootstrap,resolveDir:path.join(root,'admin'),loader:'js'},bundle:true,outfile:path.join(out,'test.js'),nodePaths:[deps],platform:'browser',define:{'process.env.NODE_ENV':'"production"',__VUE_OPTIONS_API__:'true',__VUE_PROD_DEVTOOLS__:'false'},plugins:[{name:'isolated-io',setup(build){
        build.onResolve({filter:/@\/addon\/phone_shop\/api\/order/},()=>({path:'api',namespace:'fixture'}))
        build.onResolve({filter:/^vue-router$/},()=>({path:'router',namespace:'fixture'}))
        build.onLoad({filter:/.*/,namespace:'fixture'},args=>({contents:args.path==='router'?`export const useRouter=()=>({push:r=>window.testState.routes.push(r)})`:`export async function confirmOrderDeviceReceived(id){window.testState.posts.push(id);await new Promise(r=>setTimeout(r,300));if(window.testState.fail){window.testState.fail=false;throw {msg:'ERP退回尚未同步成功，请核对后重试'}}return {code:1}}`,loader:'js'}))
        build.onLoad({filter:/\.vue$/},args=>({contents:compile(args.path),loader:'ts',resolveDir:path.dirname(args.path)}))
    }}]})
    const server = http.createServer((req,res)=>{
        if (['/test.js','/test.css'].includes(req.url)) {res.setHeader('Content-Type', req.url.endsWith('.css')?'text/css':'application/javascript');fs.createReadStream(path.join(out,req.url)).pipe(res);return}
        res.setHeader('Content-Type','text/html;charset=utf-8');res.end('<html lang="zh"><meta charset="utf-8"><link rel="stylesheet" href="/test.css"><style>body{margin:0;font-family:Arial,"PingFang SC",sans-serif;background:#f5f7fa;color:#172033}main{max-width:850px;padding:24px;margin:auto}section{padding:24px;border:1px solid #e2e8f0;border-radius:10px;background:white}h2{font-size:20px}h3{font-size:15px}</style><body><div id="app"></div><script>window.testState={posts:[],routes:[],fail:false}</script><script src="/test.js"></script></body></html>')
    })
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve))
    const url='http://127.0.0.1:'+server.address().port
    assert.equal((await fetch(url)).status,200,'本地测试服务需要先就绪')
    let browser
    try {
        browser=await chromium.launch({headless:true,channel:process.env.TEST_BROWSER_CHANNEL||'chrome',args:['--no-proxy-server']})
        const page=await browser.newPage({viewport:{width:1280,height:720}}), errors=[]
        page.on('pageerror',e=>errors.push(e.message))
        await page.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort())
        await page.goto(url)
        await page.getByText('357465822199501',{exact:true}).waitFor();assert.ok(await page.getByText('TESTSN001',{exact:true}).isVisible())
        const receive=page.getByRole('button',{name:'确认设备已收回',exact:true})
        await receive.click();await page.getByRole('button',{name:'尚未收回',exact:true}).click();assert.equal(await page.evaluate(()=>testState.posts.length),0)
        await page.evaluate(()=>testState.fail=true);await receive.click();await page.getByRole('button',{name:'已核对并收回',exact:true}).click()
        await page.getByText('ERP退回尚未同步成功，请核对后重试',{exact:true}).waitFor();assert.equal(await receive.isDisabled(),false)
        await receive.click();await page.getByRole('button',{name:'已核对并收回',exact:true}).click();await page.waitForFunction(()=>testState.posts.length===2)
        await page.locator('.receipt-action .el-button.is-loading').waitFor()
        await page.getByText('已退回 · 原单记录保留',{exact:false}).waitFor();assert.equal(await receive.count(),0)
        await page.getByRole('button',{name:'退回处理',exact:true}).click();await page.getByText('先处理原单，再重新上架',{exact:true}).waitFor()
        assert.ok(await page.getByText(/原业务员：原业务员/).isVisible());assert.ok(await page.getByText(/待退给客户 ¥1000.00/).isVisible())
        await page.waitForTimeout(3500) // 等待浮层过渡及前一次错误提示消失，再做视觉检查。
        await page.screenshot({path:path.join(out,'return-guide-1280.png'),fullPage:true})
        await page.getByRole('button',{name:'到 ERP 办理原单退货',exact:true}).click()
        const route=await page.evaluate(()=>testState.routes[0]);assert.equal(route.path,'/site/hsx_erp/sale_return');assert.equal(route.query.sale_order_id,33);assert.equal(route.query.refund_mode,'payable')
        await page.evaluate(()=>testState.order.erp_return_context.items[0].status='returned')
        await page.getByText(/设备已办理退回。若仍有待退款/).waitFor()
        assert.equal(await page.getByText(/尚未找到可退回的 ERP 设备明细/).count(),0)
        for(const width of [1280,960,640]) {await page.setViewportSize({width,height:720});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'页面溢出 '+width)}
        assert.deepEqual(errors,[])
        console.log('PASS: 6 Vue components compiled; IMEI/SN, cancel without write, failure/retry, receipt confirmation, original staff, real balance hint, ERP route, 1280/960/640 layout; no runtime errors.')
    } finally {if(browser)await browser.close();await new Promise(resolve=>server.close(resolve))}
}
main().catch(e=>{console.error(e);process.exitCode=1})
