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
    for (const file of ['views/order/config.vue','views/order/list.vue','views/order/detail.vue','views/order/components/order-detail.vue','views/order/components/order-device-identity.vue','views/order/components/order-return-guide.vue','views/order/components/order-offline-batch.vue','views/order/components/delivery-action.vue']) compile(path.join(base,file))
    compile(path.join(root,'niucloud/addon/hsx_erp/admin/views/erp/sale_return/list.vue'))
    fs.mkdirSync(out,{recursive:true})
    const bootstrap = `import {createApp,h,reactive} from 'vue';import ElementPlus from 'element-plus';import 'element-plus/dist/index.css';
        import Identity from ${JSON.stringify(path.join(base,'views/order/components/order-device-identity.vue'))};
        import Guide from ${JSON.stringify(path.join(base,'views/order/components/order-return-guide.vue'))};
        import Batch from ${JSON.stringify(path.join(base,'views/order/components/order-offline-batch.vue'))};
        import ${JSON.stringify(path.join(root,'admin/src/addon/hsx_components/styles/theme.scss'))};
        createApp({setup(){const row=reactive({order_goods_id:42,device_identity:{imei:'357465822199501',sn:'TESTSN001',source:'原订单记录'},can_confirm_received:true});
        const order=reactive({order_id:77,order_no:'TEST-ORDER-001',payment_mode:'offline_credit',return_handler_name:'原业务员'});window.testState.order=order;
        let batch;
        return()=>h('main',[h('h2','线下订单 / 订单 · 退回设备'),h('section',[h('h3','原订单 TEST-ORDER-001'),h('div','测试手机 256G'),h(Identity,{row,onComplete:()=>{row.can_confirm_received=false;row.return_state='returned';row.return_receiver='收货测试员'}}),h(Guide,{order,onComplete:()=>window.testState.completed++}),
            h('button',{onClick:()=>batch.open([{order_id:1,order_no:'TEST-001',delivery_type:'express'},{order_id:2,order_no:'TEST-002',delivery_type:'store'}],'batch_delivery')},'打开批量发货'),
            h(Batch,{ref:el=>batch=el,onComplete:()=>window.testState.batchCompleted++})])])}}).use(ElementPlus).mount('#app');`
    await esbuild.build({stdin:{contents:bootstrap,resolveDir:path.join(root,'admin'),loader:'js'},bundle:true,outfile:path.join(out,'test.js'),nodePaths:[deps],platform:'browser',define:{'process.env.NODE_ENV':'"production"',__VUE_OPTIONS_API__:'true',__VUE_PROD_DEVTOOLS__:'false'},plugins:[{name:'isolated-io',setup(build){
        build.onResolve({filter:/@\/addon\/phone_shop\/api\/order/},()=>({path:'api',namespace:'fixture'}))
        build.onResolve({filter:/^vue-router$/},()=>({path:'router',namespace:'fixture'}))
        build.onResolve({filter:/@\/addon\/phone_shop\/api\/delivery/},()=>({path:'delivery',namespace:'fixture'}))
        build.onResolve({filter:/@\/addon\/hsx_components\/core/},()=>({path:'components',namespace:'fixture'}))
        build.onLoad({filter:/.*/,namespace:'fixture'},args=>{
            let contents
            if(args.path==='router') contents=`export const useRouter=()=>({push:r=>window.testState.routes.push(r)})`
            else if(args.path==='delivery') contents=`export const getCompanyList=async()=>({data:[{company_id:1,company_name:'测试中通'}]})`
            else if(args.path==='components') contents=['HsxDrawer','HsxNotice'].map(name=>'export { default as '+name+' } from '+JSON.stringify(path.join(root,'admin/src/addon/hsx_components/components',name,'index.vue'))).join(';')
            else contents=`export async function confirmOrderDeviceReceived(id){window.testState.posts.push(id);await new Promise(r=>setTimeout(r,300));if(window.testState.fail){window.testState.fail=false;throw {msg:'ERP退回尚未同步成功，请核对后重试'}}return {code:1}}
            export async function processOfflineOrderAction(params){
                const state=window.testState; await new Promise(r=>setTimeout(r,150));
                if(params.action==='return_preview'){
                    state.previews++;
                    if(state.previewFail){state.previewFail=false;throw {msg:'ERP退货服务未就绪，未关单、未冲账'}}
                    return {data:{order_no:'TEST-ORDER-001',preview_token:'confirmed-plan',items:[
                        {order_goods_id:101,asset_id:0,can_return:true,goods_name:'商城原生测试手机',sku_name:'256G 黑色',imei:'357465822199502',return_amount:5000,offset_amount:5000,refund_amount:0},
                        {order_goods_id:102,asset_id:50,can_return:true,goods_name:'ERP设备',imei:'357465822199503',return_amount:5000,offset_amount:0,refund_amount:5000}
                    ]}}
                }
                if(params.action==='return_received'){
                    state.returnPosts.push(params);await new Promise(r=>setTimeout(r,500));
                    if(state.returnFail){state.returnFail=false;throw {msg:'收款状态已变化，请重新核对金额'}}
                    return {data:{offset_amount:5000,refund_amount:5000}}
                }
                state.batchPosts.push(params);
                return {data:{items:params.items.map(row=>({order_id:row.order_id,success:row.order_id===1||state.batchPosts.length>1,message:row.order_id===1||state.batchPosts.length>1?'已处理':'该订单尚未完成收款或挂账'}))}}
            }`
            return {contents,loader:'js',resolveDir:path.join(root,'admin')}
        })
        build.onLoad({filter:/\.scss$/},args=>({contents:require(path.join(deps,'sass')).compile(args.path,{logger:{warn(){},debug(){}}}).css,loader:'css'}))
        build.onLoad({filter:/\.vue$/},args=>({contents:compile(args.path),loader:'ts',resolveDir:path.dirname(args.path)}))
    }}]})
    const server = http.createServer((req,res)=>{
        if (['/test.js','/test.css'].includes(req.url)) {res.setHeader('Content-Type', req.url.endsWith('.css')?'text/css':'application/javascript');fs.createReadStream(path.join(out,req.url)).pipe(res);return}
        res.setHeader('Content-Type','text/html;charset=utf-8');res.end('<html lang="zh"><meta charset="utf-8"><link rel="stylesheet" href="/test.css"><style>body{margin:0;font-family:Arial,"PingFang SC",sans-serif;background:#f5f7fa;color:#172033}main{max-width:850px;padding:24px;margin:auto}main>section{padding:24px;border:1px solid #e2e8f0;border-radius:10px;background:white}h2{font-size:20px}h3{font-size:15px}</style><body><div id="app"></div><script>window.testState={posts:[],routes:[],fail:false,previews:0,returnPosts:[],completed:0,batchPosts:[],batchCompleted:0}</script><script src="/test.js"></script></body></html>')
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
        await receive.click();assert.ok(await page.getByText(/确认后恢复一台库存并自动上架/).isVisible());await page.getByRole('button',{name:'尚未收回',exact:true}).click();assert.equal(await page.evaluate(()=>testState.posts.length),0)
        await page.evaluate(()=>testState.fail=true);await receive.click();await page.getByRole('button',{name:'已核对并收回',exact:true}).click()
        await page.getByText('ERP退回尚未同步成功，请核对后重试',{exact:true}).waitFor();assert.equal(await receive.isDisabled(),false)
        await receive.click();await page.getByRole('button',{name:'已核对并收回',exact:true}).click();await page.waitForFunction(()=>testState.posts.length===2)
        await page.locator('.receipt-action .el-button.is-loading').waitFor()
        await page.getByText('已退回 · 原单记录保留',{exact:false}).waitFor();assert.equal(await receive.count(),0)
        await page.evaluate(()=>testState.previewFail=true)
        await page.getByRole('button',{name:'退回处理',exact:true}).click()
        await page.getByText('ERP退货服务未就绪，未关单、未冲账',{exact:true}).waitFor()
        const submit=page.getByRole('button',{name:'确认收回并处理原账',exact:true})
        assert.ok(await submit.isDisabled());assert.equal(await page.evaluate(()=>testState.returnPosts.length),0)
        await page.getByRole('button',{name:'重新核对原账',exact:true}).click()
        await page.getByText('商城原生测试手机',{exact:true}).waitFor()
        assert.ok(await page.getByText('确认收回后自动恢复上架，请先核对实物；不会自动退款',{exact:true}).isVisible())
        assert.ok(await page.getByText(/原业务员：原业务员/).isVisible())
        assert.ok(await submit.isDisabled(),'未填原因、未确认实物不能提交')
        await page.getByPlaceholder('例如：客户未售出退回，已核对原设备').fill('客户未售出，已收回原机')
        assert.ok(await submit.isDisabled())
        await page.getByText('我已核对所选设备串号，并实际收回设备',{exact:true}).click()
        assert.equal(await submit.isDisabled(),false)
        await page.locator('.el-message').waitFor({state:'hidden'})
        for(const width of [1280,960,640,390]) {
            await page.setViewportSize({width,height:780});await page.waitForTimeout(700)
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'页面溢出 '+width)
            const box = await page.getByRole('dialog',{name:'收回设备并处理原账',exact:true}).boundingBox()
            assert.ok(box.x >= -1 && box.x + box.width <= width + 1, '抽屉越界 '+JSON.stringify({width,box}))
            await page.screenshot({path:path.join(out,'return-drawer-'+width+'.png'),fullPage:true})
        }
        await page.setViewportSize({width:1280,height:780})
        await page.evaluate(()=>testState.returnFail=true)
        await submit.click()
        await page.locator('.el-drawer__footer .el-button.is-loading').waitFor()
        await page.getByText('收款状态已变化，请重新核对金额',{exact:true}).waitFor()
        assert.ok(await submit.isDisabled());assert.equal(await page.evaluate(()=>testState.completed),0)
        await page.getByRole('button',{name:'重新核对原账',exact:true}).click()
        await page.getByText('商城原生测试手机',{exact:true}).waitFor()
        await page.getByText('我已核对所选设备串号，并实际收回设备',{exact:true}).click()
        await submit.click();await page.waitForFunction(()=>testState.completed===1)
        const post=await page.evaluate(()=>testState.returnPosts[1])
        assert.deepEqual(post.order_goods_ids,[101,102]);assert.equal(post.received,true);assert.equal(post.preview_token,'confirmed-plan')
        await page.getByRole('button',{name:'退回处理',exact:true}).click()
        await page.getByText('商城原生测试手机',{exact:true}).waitFor()
        assert.ok(await submit.isDisabled(),'重新打开必须重新确认，不能沿用上次确认')
        await page.getByRole('button',{name:'取消',exact:true}).click()
        await page.getByRole('button',{name:'打开批量发货',exact:true}).click()
        await page.locator('.batch-delivery .el-select').click()
        await page.getByText('测试中通',{exact:true}).click()
        await page.getByPlaceholder('填写实际运单号').fill('ZT123456789')
        await page.getByText('我已实际交件 / 当面交付，所填运单号真实正确',{exact:true}).click()
        await page.getByRole('button',{name:'确认处理所列订单',exact:true}).click()
        await page.getByText(/成功 1 笔 · 未成功 1 笔/).waitFor()
        assert.ok(await page.getByPlaceholder('填写实际运单号').isDisabled(),'成功的订单不再可提交')
        await page.getByText('我已实际交件 / 当面交付，所填运单号真实正确',{exact:true}).click()
        await page.getByRole('button',{name:'重试未成功订单',exact:true}).click()
        await page.getByText(/成功 2 笔 · 未成功 0 笔/).waitFor()
        assert.deepEqual(await page.evaluate(()=>testState.batchPosts[1].items.map(r=>r.order_id)),[2])
        assert.deepEqual(errors,[])
        console.log('PASS: 9 page/components plus shared Drawer/Notice compiled; IMEI, original staff, ERP failure/retry, native-item selection, receipt guard, auto-relist notice, stale plan, submit lock, refresh, batch partial success/retry; 1280/960/640/390 layout; no runtime errors.')
    } finally {if(browser)await browser.close();await new Promise(resolve=>server.close(resolve))}
}
main().catch(e=>{console.error(e);process.exitCode=1})
