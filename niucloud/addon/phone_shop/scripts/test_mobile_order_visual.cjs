#!/usr/bin/env node
// 真订单 SFC + 全 mock API/导航/uview 外壳。只启动独立临时 Chrome，不操作用户浏览器。
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'site-uniapp'), base = path.join(app, 'src/addon/phone_shop')
const { parse, compileScript, compileStyleAsync } = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(app, 'node_modules/typescript'))
const { chromium } = require('/tmp/node_modules/playwright')
const order = { order_id: 10, order_no: '20261030357962450000000001', status: 2, status_name: { name: '待发货' }, payment_mode: 'offline_credit', order_money: 11600, pay_money: 0, create_time: 1793354400, delivery_type: 'express', delivery_type_name: '物流配送', taker_name: '石家庄新华区手机回收测试门店韩老板（很长的客户名称）', taker_mobile: '13800138000', taker_full_address: '河北省石家庄市新华区太和电子城六层测试地址（仅本地模拟，不发送物流请求）', member: { nickname: '石家庄新华区手机回收测试门店韩老板（很长的客户名称）' }, order_goods: [
    { order_goods_id: 31, goods_name: '苹果 iPhone 17 Pro Max 512GB 中国大陆国行 原装无拆修', sku_name: '512GB 橙色 · 极佳', status: 1, status_name: '正常', num: 1, price: 5800, goods_money: 5800, discount_money: 0, is_enable_refund: 1, delivery_status: 'wait_delivery', delivery_status_name: '待发货', device_identity: { imei: '353938205192374' } },
    { order_goods_id: 32, goods_name: '三星 Galaxy S26 Ultra 1TB 极佳成色（第二台测试设备）', sku_name: '1TB 黑色 · 极佳', status: 1, status_name: '正常', num: 1, price: 5800, goods_money: 5800, discount_money: 0, is_enable_refund: 1, delivery_status: 'wait_delivery', delivery_status_name: '待发货', device_identity: { imei: '863963080113124' } },
], order_log: [] }
// 本轮新增的只读财务快照：使用真实字段契约，所有金额/姓名均是本地模拟资料。
const creditModes = ['credit_pending', 'credit_partial', 'credit_settled', 'credit_unavailable']
function creditFixture(mode) {
    const state = mode.slice('credit_'.length), unavailable = state === 'unavailable'
    const received = state === 'settled' ? '11600.00' : state === 'partial' ? '3000.00' : '0.00'
    const remaining = state === 'settled' ? '0.00' : state === 'partial' ? '8600.00' : '11600.00'
    const records = ['partial', 'settled'].includes(state) ? [{ settlement_id: 101, settlement_no: 'MOCK-RECEIPT-001', settlement_type: 'receipt', method: 'bank', method_name: '银行卡', capital_account_id: 7, capital_account_name: '天泰通讯公司对公收款账户（长账户名称：石家庄新华区太和电子城）', amount: '3000.00', confirmed_at: 1793354400, operator_name: '财务测试员' }] : []
    if (state === 'settled') records.push({ settlement_id: 102, settlement_no: 'MOCK-RECEIPT-002', settlement_type: 'receipt', method: 'wechat', method_name: '微信', capital_account_id: 8, capital_account_name: '门店微信收款账户', amount: '8600.00', confirmed_at: 1793358000, operator_name: '财务测试员' })
    return { ...structuredClone(order), status: 3, status_name: { name: '待收货' }, delivery_type: 'store', delivery_type_name: '门店自提', finance_snapshot: {
        provider: 'hsx_erp', available: !unavailable, matched: !unavailable, can_complete: state === 'settled', status: state,
        status_name: { pending: '待财务收款', partial: '部分收款', settled: '已结清', unavailable: '收款状态待核实' }[state],
        amount: unavailable ? null : '11600.00', settled_amount: unavailable ? null : received, cash_received_amount: unavailable ? null : received,
        remaining_amount: unavailable ? null : remaining, offset_amount: unavailable ? null : '0.00',
        payment_methods: [...new Map(records.map(row => [row.method, { key: row.method, name: row.method_name }])).values()],
        capital_accounts: records.map(row => ({ id: row.capital_account_id, name: row.capital_account_name, type: row.method })), settlements: records,
        message: unavailable ? 'ERP 收款核对服务未响应，请刷新或联系管理员；未完成订单，请勿重复收款。' : state === 'settled' ? 'ERP 应收已结清，可以核实交付后完成订单。' : 'ERP 应收尚未结清，财务确认收款前不能完成订单。'
    } }
}
const creditFixtures = Object.fromEntries(creditModes.map(mode => [mode, creditFixture(mode)]))
const api = `const source=${JSON.stringify(order)};window.queryCalls=[];window.writeCalls=[];function getOrder(){const result=structuredClone(source);if(new URLSearchParams(location.search).get('mode')==='detail')result.payment_mode='online';return result}export const getOrderDetail=async()=>({data:getOrder()});export const getOrderList=async params=>{queryCalls.push(params);const offline=structuredClone(source),online={...structuredClone(source),order_id:11,payment_mode:'online'};let rows=[online,offline];if(params.order_channel==='online')rows=[online];if(params.order_channel==='offline')rows=[offline];if(params.status!==undefined)rows=rows.filter(row=>row.status===params.status);return{data:{data:rows,total:rows.length}}};export const getDeliveryCompanies=async()=>({data:[{company_id:7,company_name:'顺丰'}]});export const getOrderDeliveryTypes=async()=>({data:{express:'物流配送',none_express:'无需物流'}});export const getOrderRefundMoney=async()=>({data:{refund_money:11600}});export const getOfflineCapitalAccounts=async()=>({data:[]});export const processOrderAction=async params=>{if(params.action==='return_preview')return{data:{preview_token:'LOCAL-PREVIEW',items:source.order_goods.map(goods=>({...goods,imei:goods.device_identity.imei,can_return:true,requires_location:false,return_amount:5800,offset_amount:5800,refund_amount:0}))}};writeCalls.push(params);throw Error('Write blocked: visual test only')};export const closeOrder=processOrderAction,finishOrder=processOrderAction,deliverOrder=processOrderAction,confirmOrderDeviceReceived=processOrderAction,setOrderRemark=processOrderAction;`
const stubs = `import{defineComponent,h,Teleport,onMounted}from'/vue.js';export const Popup=defineComponent({props:['show'],setup(props,{slots}){return()=>props.show?h(Teleport,{to:'body'},h('div',{class:'popup-shell'},h('div',{class:'popup-content'},slots.default?.()))):null}});export const Button=defineComponent({props:['customStyle','disabled','loading','text','type'],emits:['click'],setup(props,{slots,emit}){return()=>h('button',{style:props.customStyle,disabled:props.disabled||props.loading,class:props.type?'primary':'',onClick:()=>emit('click')},props.text||slots.default?.())}});export const Icon=defineComponent({props:['name','color'],setup:props=>()=>h('span',{style:{color:props.color,fontSize:'18px'}},props.name?.includes('circle')?'○':props.name==='close'?'×':'›')});export const Empty=defineComponent({props:['text'],setup:props=>()=>h('div',props.text)});export const Paging=defineComponent({props:['modelValue','pagingStyle'],emits:['query','update:modelValue'],setup(props,{slots,emit,expose}){const query=()=>emit('query',1,12);expose({reload:query,complete:rows=>{if(Array.isArray(rows))emit('update:modelValue',rows)}});onMounted(query);return()=>h('div',{class:'mock-paging',style:{...props.pagingStyle,top:parseFloat(props.pagingStyle?.top||0)*innerWidth/750+'px'}},[slots.default?.(),props.modelValue?.length?null:slots.empty?.()])}});export const ActionSheet=defineComponent({props:['show','actions'],emits:['select','close'],setup(props,{emit}){return()=>props.show?h('div',{class:'mock-actions'},props.actions?.map(action=>h('button',{onClick:()=>emit('select',action)},action.name))):null}});export default Empty;`
const transpile = source => ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext } }).outputText
async function compile(file) {
    const filename = path.join(base, file)
    // 本次视觉验收采用 H5 壳；原生胶囊/导航需真机另验。
    const source = fs.readFileSync(filename, 'utf8').replace(/<!-- #ifdef MP -->[\s\S]*?<!-- #endif -->/g, '')
    const { descriptor, errors } = parse(source, { filename }); if (errors.length) throw errors[0]
    const script = compileScript(descriptor, { id: 'order-ui-audit', inlineTemplate: true, templateOptions: { compilerOptions: { isCustomElement: tag => ['view', 'text', 'scroll-view', 'picker', 'image'].includes(tag) } } })
    let js = transpile(script.content).replace(/from ['"]vue['"]/g, "from '/vue.js'").replace(/from ['"]@\/addon\/phone_shop\/api\/order['"]/g, "from '/api.js'")
        .replace(/from ['"]@\/addon\/phone_shop\/utils\/orderWorkflow['"]/g, "from '/workflow.js'").replace(/from ['"]@\/utils\/common['"]/g, "from '/common.js'")
        .replace(/from ['"]@\/addon\/phone_shop\/utils\/orderCredit['"]/g, "from '/credit.js'")
        .replace(/from ['"]@dcloudio\/uni-app['"]/g, "from '/hooks.js'").replace(/from ['"]@\/addon\/phone_shop\/composables\/usePhoneShopListHeader['"]/g, "from '/header-hook.js'")
        .replace(/from ['"]@\/components\/upload-img\/upload-img.vue['"]/g, "from '/stubs.js'")
        .replace(/from ['"]@\/addon\/phone_shop\/components\/([^'"]+)['"]/g, (_, name) => "from '/sfc?file=components/" + name + "'")
        .replace(/from ['"]\.\/([^'"]+\.vue)['"]/g, (_, name) => "from '/sfc?file=components/" + name + "'")
    let css = ''
    for (const style of descriptor.styles) { const result = await compileStyleAsync({ filename, source: style.content, id: 'order-ui-audit', preprocessLang: style.lang }); if (result.errors.length) throw result.errors[0]; css += result.code }
    return js + '\n{const sheet=document.createElement("style");sheet.textContent=' + JSON.stringify(css) + '.replace(/(-?[\\d.]+)rpx/g,(_,n)=>"calc("+n+" * 100vw / 750)");document.head.appendChild(sheet);}'
}
const mainCode = `import{createApp,h}from'/vue.js';import List from'/sfc?file=pages/order/list.vue';import Detail from'/sfc?file=pages/order/detail.vue';import Panel from'/sfc?file=components/OrderActionPanel.vue';import*as UI from'/stubs.js';const params=new URLSearchParams(location.search),mode=params.get('mode');window.navCalls=[];window.uni={getStorageSync(key){return key==='siteId'?100005:params.get('unknown')==='1'&&key.includes('uncertain')?true:undefined},setStorageSync(){},removeStorageSync(){},showToast(){},showModal(){},navigateTo(data){navCalls.push(data.url)},setClipboardData(){},makePhoneCall(){},previewImage(){}};const app=createApp({setup(){return()=>mode==='list'?h(List):mode==='detail'?h(Detail):h(Panel,{show:true,order:${JSON.stringify(order)},action:mode})}});app.config.globalProperties.themeColor=()=>'';for(const[name,component]of Object.entries({'u-popup':UI.Popup,'u-button':UI.Button,'u-icon':UI.Icon,'u-action-sheet':UI.ActionSheet,'u-empty':UI.Empty,'z-paging':UI.Paging}))app.component(name,component);app.mount('#app');`
async function main() {
    const server = http.createServer(async (request, response) => {
        try {
            const url = new URL(request.url, 'http://localhost'); response.setHeader('Content-Type', 'application/javascript'); let body = ''
            if (url.pathname === '/vue.js') body = fs.readFileSync(path.join(app, 'node_modules/vue/dist/vue.esm-browser.js'), 'utf8')
            else if (url.pathname === '/api.js') body = `const creditFixtures=${JSON.stringify(creditFixtures)};` + api.replace('function getOrder(){', "function getOrder(){const mode=new URLSearchParams(location.search).get('mode');if(creditFixtures[mode])return structuredClone(creditFixtures[mode]);")
            else if (url.pathname === '/stubs.js') body = stubs
            else if (url.pathname === '/common.js') body = 'export const img=value=>value;'
            else if (url.pathname === '/header-hook.js') body = "import{ref,computed}from'/vue.js';export const usePhoneShopListHeader=()=>({isMp:ref(false),customNavStyle:'',listTop:computed(()=>'250rpx'),canGoBack:ref(true),handleNavAction(){}});"
            else if (url.pathname === '/hooks.js') body = "import{onMounted}from'/vue.js';export const onLoad=callback=>callback({order_id:new URLSearchParams(location.search).get('mode')==='list'?0:10});export const onShow=callback=>onMounted(callback);"
            else if (url.pathname === '/workflow.js') body = transpile(fs.readFileSync(path.join(base, 'utils/orderWorkflow.ts'), 'utf8')).replace("from './orderCredit'", "from '/credit.js'")
            else if (url.pathname === '/credit.js') body = transpile(fs.readFileSync(path.join(base, 'utils/orderCredit.ts'), 'utf8'))
            else if (url.pathname === '/sfc') body = await compile(url.searchParams.get('file'))
            else if (url.pathname === '/main.js') body = mainCode.replace("mode==='detail'?h(Detail)", "(mode==='detail'||mode.startsWith('credit_'))?h(Detail)")
            else { response.setHeader('Content-Type', 'text/html; charset=utf-8'); body = '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif;--primary-color:#2463eb;--primary-color-light:#eff5ff;--page-bg-color:#f5f7fa}view,scroll-view{display:block}text{display:inline}scroll-view{overflow-y:auto}input,textarea{font-family:inherit;border:0}button{min-width:0;height:42px;border:1px solid #dce2ec;border-radius:5px;background:white;color:#516174;font-size:14px}.primary{background:#2463eb;color:white;border-color:#2463eb}.popup-shell{position:fixed;inset:0;background:#0007;z-index:10075}.popup-content{position:absolute;bottom:0;left:0;right:0;background:white;border-radius:12px 12px 0 0;overflow:hidden}.mock-paging{position:fixed;bottom:0;left:0;right:0;overflow-y:auto}.mock-actions{position:fixed;bottom:0;left:0;right:0;background:#fff;z-index:10000;display:flex;flex-direction:column;padding:16px;gap:8px}</style><div id="app"></div><script type="module" src="/main.js"></script>' }
            response.end(body)
        } catch (error) { response.statusCode = 500; response.end(String(error.stack)) }
    })
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve)); let browser, count = 0
    try {
        browser = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' })
        const origin = `http://127.0.0.1:${server.address().port}`
        for (const [width, height] of [[320, 568], [390, 844]]) {
            const page = await browser.newPage({ viewport: { width, height }, isMobile: true }); const errors = []
            page.on('pageerror', error => errors.push(String(error)))
            await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort())
            for (const mode of ['list', 'detail', 'return', 'unknown', 'delivery', 'refund', ...creditModes]) {
                await page.goto(`${origin}/?mode=${mode === 'unknown' ? 'return&unknown=1' : mode}`); await page.waitForTimeout(500)
                assert.equal(errors.length, 0, errors.join('\n')); count++
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${mode}: horizontal overflow`); count++
                if (mode === 'list') {
                    assert.equal(await page.locator('.order-card').count(), 2); count++
                    await page.locator('.channel-filter>view').nth(1).click(); await page.waitForTimeout(100)
                    assert.equal(await page.locator('.order-card').count(), 1); count++
                    assert.equal(await page.evaluate(() => queryCalls.at(-1).order_channel), 'online'); count++
                    await page.locator('.channel-filter>view').nth(2).click(); await page.waitForTimeout(100)
                    assert.equal(await page.evaluate(() => queryCalls.at(-1).order_channel), 'offline'); count++
                    await page.locator('.tab-item').nth(1).click(); await page.waitForTimeout(100)
                    assert.equal(await page.evaluate(() => queryCalls.at(-1).status), 1); count++
                    assert.equal(await page.locator('.order-card').count(), 0); count++
                    await page.locator('.tab-item').first().click(); await page.waitForTimeout(100)
                    await page.locator('.detail-button').first().click(); assert.ok((await page.evaluate(() => navCalls.at(-1))).includes('/pages/order/detail?order_id=10')); count++
                } else if (mode === 'detail' || creditModes.includes(mode)) {
                    assert.equal(await page.locator('.device-row').count(), 2); count++
                    await page.getByText('353938205192374', { exact: false }).first().waitFor(); count++
                    await page.getByText('863963080113124', { exact: false }).first().waitFor(); count++
                    const footer = await page.locator('.detail-footer').boundingBox(); assert.ok(footer && footer.y + footer.height <= height + 1); count++
                    if (creditModes.includes(mode)) {
                        const finance = creditFixtures[mode].finance_snapshot
                        await page.locator('.credit-heading').getByText('ERP 收款 · ' + finance.status_name).waitFor(); count++
                        assert.equal(await page.locator('.detail-footer .primary-button').count(), finance.can_complete ? 1 : 0, `${mode}: finish only after authoritative settlement`); count++
                        assert.ok((await page.locator('.channel-tag').innerText()).includes('客户挂账'), 'original credit payment agreement retained'); count++
                        assert.equal(await page.locator('.credit-message').innerText(), finance.message); count++
                        if (!finance.available) {
                            assert.equal(await page.locator('.credit-amounts').count(), 0, 'unknown amounts are not shown as zero'); count++
                            assert.equal(await page.locator('.credit-toggle').count(), 0, 'no fictitious receipts'); count++
                        } else {
                            const text = await page.locator('.credit-amounts').innerText()
                            assert.ok(text.includes('已收 ¥' + finance.cash_received_amount), `${mode}: received amount`); count++
                            assert.ok(text.includes('未收 ¥' + finance.remaining_amount), `${mode}: remaining amount`); count++
                            if (finance.settlements.length) {
                                const methods = finance.payment_methods.map(row => row.name).join('、'), accounts = finance.capital_accounts.map(row => row.name).join('、')
                                assert.ok((await page.locator('.credit-summary').innerText()).includes('实际收款方式：' + methods)); count++
                                assert.ok((await page.locator('.credit-summary').innerText()).includes('实际到账账户：' + accounts)); count++
                                await page.locator('.credit-toggle').click()
                                assert.equal(await page.locator('.credit-record').count(), finance.settlements.length); count++
                                for (const record of finance.settlements) {
                                    const row = page.locator('.credit-record').filter({ hasText: record.settlement_no })
                                    assert.ok((await row.innerText()).includes(record.method_name)); count++
                                    assert.ok((await row.innerText()).includes(record.capital_account_name)); count++
                                    assert.ok((await row.innerText()).includes('¥' + record.amount)); count++
                                }
                            } else { assert.equal(await page.locator('.credit-toggle').count(), 0); count++ }
                        }
                        if (finance.can_complete) { assert.equal(await page.locator('.detail-footer .primary-button').innerText(), '确认订单完成'); count++ }
                        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${mode}: no overflow after expanding receipts`); count++
                        assert.equal(await page.evaluate(() => writeCalls.length), 0, 'receipt display does not perform financial writes'); count++
                    }
                } else {
                    const footer = await page.locator('.panel-foot').boundingBox(); assert.ok(footer && footer.y + footer.height <= height + 1); count++
                    assert.equal(await page.locator('.selection-line').count(), 2); count++
                    const buttons = await page.locator('.panel-foot').evaluateAll(nodes => nodes.flatMap(node => [...node.querySelectorAll('button')]).map(button => { const rect = button.getBoundingClientRect(); return { left: rect.left, right: rect.right, height: rect.height } })); assert.ok(buttons.every(rect => rect.left >= 0 && rect.right <= width && rect.height >= 40)); count++
                    if (mode === 'return') { await page.getByText('冲销未收 ¥11600.00').waitFor(); count++ }
                    if (mode === 'unknown') { await page.getByText('结果尚未确认，不能按失败重复提交。', { exact: false }).waitFor(); await page.locator('.panel-foot button').last().click(); assert.equal(await page.evaluate(() => writeCalls.length), 0); count++ }
                }
                await page.screenshot({ path: `/tmp/mobile-order-visual-${mode}-${width}.png`, fullPage: mode === 'detail' || creditModes.includes(mode) })
                console.log(`PASS ${mode} ${width}x${height}`)
            }
            await page.close()
        }
        console.log(`[通过] ${count} 项真实订单SFC/模拟API浏览器断言；不代表原生导航、键盘或真实资金操作已验收。`)
    } finally { await browser?.close(); await new Promise(resolve => server.close(resolve)) }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
