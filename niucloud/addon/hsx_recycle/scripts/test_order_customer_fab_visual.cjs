#!/usr/bin/env node
// Actual list/customer-picker SFCs. All APIs/native capabilities/uview shells are local fixtures.
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'site-uniapp'), base = path.join(app, 'src/addon/hsx_recycle')
const { parse, compileScript, compileStyleAsync } = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(app, 'node_modules/typescript'))
const { chromium } = require('/tmp/node_modules/playwright')
const customer = { member_id: 75, member_no: 'M0075', nickname: '石家庄新华区手机回收测试门店张老板（长客户名称）', username: 'zhang', mobile: '13800138000', headimg: '' }
const api = `const member=${JSON.stringify(customer)};window.queryCalls=[];window.memberCalls=[];window.writeCalls=[];const rows=Array.from({length:7},(_,i)=>({id:100+i,order_no:'R202610300000'+i,status:i%2?7:1,status_name:i%2?'已完成':'待签收',member_id:i===6?76:75,member:{...member,nickname:i===6?'李老板':member.nickname},count:1,delivery_type_name:'自送到店',create_at:'2026-10-30 18:00',devices:[{id:1000+i,model:'苹果 iPhone 17 Pro Max 512GB 橙色（测试设备）',imei:'353938205192374',initial_price:5800,status:1,status_name:'待质检'}]}));export async function getOrderList(params){queryCalls.push(structuredClone(params));const filtered=rows.filter(row=>(!params.member_id||row.member_id===Number(params.member_id))&&(!params.status||row.status===Number(params.status)));return{data:{data:filtered,status_counts:{all:filtered.length,1:filtered.filter(row=>row.status===1).length,7:filtered.filter(row=>row.status===7).length}}}}export const getOrderStatus=async()=>({data:[{status:1,name:'待签收'},{status:7,name:'已完成'}]});export const getOrderBusinessStageOptions=async()=>({data:[]});export async function searchMemberList(params){memberCalls.push(structuredClone(params));return{data:{data:!params.keyword||member.nickname.includes(params.keyword)||member.mobile.includes(params.keyword)?[member]:[],total:1,last_page:1}}}export const pushOrderNotify=async data=>{writeCalls.push(data);throw Error('Write disabled')};export const updateOrder=pushOrderNotify;`
const ui = `import{defineComponent,h,Teleport,onMounted,watch,nextTick}from'/vue.js';export const Popup=defineComponent({props:['show'],emits:['open','close'],setup(props,{slots,emit}){watch(()=>props.show,value=>{if(value)nextTick(()=>emit('open'))},{immediate:true});return()=>props.show?h(Teleport,{to:'body'},h('div',{class:'popup-shell'},h('div',{class:'popup-content'},slots.default?.()))):null}});export const Icon=defineComponent({props:['name','color','size'],setup:props=>()=>h('span',{style:{color:props.color,fontSize:(props.size||20)+'px'}},props.name==='plus'?'+':props.name?.includes('close')?'×':'›')});export const Avatar=defineComponent({setup:()=>()=>h('span',{class:'avatar-fixture'},'客')});export const Tag=defineComponent({props:['text'],setup:props=>()=>h('span',{class:'tag-fixture'},props.text)});export const Tabs=defineComponent({props:['list','current'],emits:['click'],setup(props,{emit}){return()=>h('div',{class:'tabs-fixture'},props.list?.map((tab,index)=>h('button',{class:{active:index===props.current},onClick:()=>emit('click',{index})},tab.name)))}});export const Empty=defineComponent({props:['text','title','description'],setup:props=>()=>h('div',{class:'empty-fixture'},props.text||props.title||'暂无订单')});export const Agent=defineComponent({props:['visible'],emits:['update:visible'],setup(props,{emit}){return()=>props.visible?h(Teleport,{to:'body'},h('div',{class:'popup-shell'},h('div',{class:'agent-fixture'},[h('h3','代客户下单'),h('p','此处复用原签收表单，测试仅检查入口'),h('button',{onClick:()=>emit('update:visible',false)},'取消')]))):null}});export const Filter=defineComponent({props:['visible','showMemberFilter'],emits:['update:visible'],setup(props,{emit}){return()=>props.visible?h(Teleport,{to:'body'},h('div',{class:'popup-shell'},h('div',{class:'filter-fixture','data-member-filter':String(props.showMemberFilter)},[h('h3','高级筛选'),h('button',{onClick:()=>emit('update:visible',false)},'关闭')]))):null}});export const Calendar=Empty;`
function preprocess(source, target) {
    const stack = []; let enabled = true
    return source.split('\n').map(line => {
        const marker = line.match(/(?:<!--|\/\/)\s*#(ifdef|ifndef|endif)\s*([^>]*?)(?:-->)?$/)
        if (marker) {
            if (marker[1] === 'endif') enabled = stack.pop() ?? true
            else { const expr = marker[2].trim(), match = expr.split(/\s*\|\|\s*/).some(part => target === 'mp' ? part.startsWith('MP') : part === 'H5'); stack.push(enabled); enabled = enabled && (marker[1] === 'ifdef' ? match : !match) }
            return ''
        }
        return enabled ? line : ''
    }).join('\n')
}
const transpile = source => ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext } }).outputText
function imports(source, filename, target) {
    return source.replace(/from\s+(['"])([^'"]+)\1/g, (full, quote, specifier) => {
        if (specifier === 'vue') return "from '/vue.js'"
        if (specifier === '@dcloudio/uni-app') return "from '/hooks.js'"
        if (specifier === '@/utils/common') return "from '/common.js'"
        if (specifier === '@/addon/hsx_recycle/api/order') return "from '/api.js'"
        const relative = specifier.startsWith('@/addon/hsx_recycle/') ? specifier.slice('@/addon/hsx_recycle/'.length) : specifier.startsWith('.') ? path.posix.normalize(path.posix.join(path.posix.dirname(filename), specifier)) : ''
        if (!relative) throw Error('Unexpected import: ' + specifier)
        if (relative.endsWith('SignOrderPopup.vue')) return "from '/agent.js'"
        if (relative.endsWith('OrderFilterDrawer.vue')) return "from '/filter.js'"
        if (relative.endsWith('RecycleEmptyState.vue')) return "from '/empty.js'"
        if (relative.endsWith('utils/clipboard')) return "from '/clipboard.js'"
        const kind = relative.endsWith('.vue') ? 'sfc' : 'ts'
        return `from '/${kind}?file=${encodeURIComponent(relative)}&target=${target}'`
    })
}
async function compile(file, target) {
    const filename = path.join(base, file), source = preprocess(fs.readFileSync(filename, 'utf8'), target).replace(/@tap/g, '@click').replace(':focus="searchFocused"', ':data-native-focus="searchFocused"')
    const { descriptor, errors } = parse(source, { filename }); if (errors.length) throw errors[0]
    const script = compileScript(descriptor, { id: 'recycle-fab-audit', inlineTemplate: true, templateOptions: { compilerOptions: { isCustomElement: tag => ['view','text','scroll-view','input','image'].includes(tag) } } })
    const js = imports(transpile(script.content), file, target)
    let css = ''
    for (const style of descriptor.styles) { const result = await compileStyleAsync({ filename, source: style.content, id:'recycle-fab-audit', preprocessLang:style.lang }); if(result.errors.length)throw result.errors[0];css+=result.code }
    return js + '\n{const el=document.createElement("style");el.textContent=' + JSON.stringify(css) + '.replace(/(-?[\\d.]+)rpx/g,(_,n)=>"calc("+n+" * 100vw / 750)");document.head.appendChild(el);}'
}
const html = `<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif;--hsx-primary:#2463eb;--hsx-primary-50:#eff5ff}view,scroll-view{display:block}text{display:inline}scroll-view{overflow:auto}input{font-family:inherit;min-width:0;border:0;outline:0}button{font-family:inherit;border:0;background:#edf3ff;padding:8px 14px;color:#516174;border-radius:6px}.popup-shell{position:fixed;inset:0;background:#0007;z-index:10140}.popup-content{position:absolute;bottom:0;left:0;right:0;background:white;border-radius:12px 12px 0 0;overflow:hidden}.paging-fixture{position:fixed;left:0;right:0;bottom:0;overflow:auto}.tabs-fixture{display:flex;height:calc(84*100vw/750);gap:10px;align-items:center;padding:0 10px}.tabs-fixture button{white-space:nowrap}.tabs-fixture .active{background:#2463eb;color:#fff}.avatar-fixture{width:35px;height:35px;border-radius:50%;background:#edf3ff;display:flex;align-items:center;justify-content:center;flex-shrink:0}.tag-fixture{font-size:11px;padding:3px 6px;color:#2463eb;background:#edf3ff;white-space:nowrap}.agent-fixture,.filter-fixture{position:absolute;bottom:0;left:0;right:0;height:65vh;background:#fff;padding:24px;border-radius:12px 12px 0 0}.empty-fixture{padding:40px 20px;text-align:center;color:#7b8797}.capsule-fixture{position:fixed;top:26px;right:12px;width:84px;height:32px;z-index:99999;border:1px solid #ccc;border-radius:20px;color:#333;text-align:center;line-height:30px;background:#fff}.nc-icon-sousuo-duanV6xx1:before{content:'⌕'}.nc-icon-shaixuanV6xx:before{content:'☷'}.nc-icon-zuoV6xx:before{content:'‹'}.nc-icon-guanbiV6xx1:before{content:'×'}.nc-icon-cuohaoV6xx1:before{content:'×'}</style><div id="app"></div><script type="module" src="/main.js"></script>`
async function main() {
    const server = http.createServer(async (request,response) => {try {
        const url = new URL(request.url,'http://localhost'), target=url.searchParams.get('target')||'h5';let body='';response.setHeader('Content-Type','application/javascript')
        if(url.pathname==='/vue.js')body=fs.readFileSync(path.join(app,'node_modules/vue/dist/vue.esm-browser.js'),'utf8')
        else if(url.pathname==='/api.js')body=api
        else if(url.pathname==='/ui.js')body=ui.replace("props:['show']", "props:['show','customStyle']").replace("class:'popup-content'", "class:'popup-content',style:props.customStyle")
        else if(url.pathname==='/agent.js')body="export{Agent as default}from'/ui.js';"
        else if(url.pathname==='/filter.js')body="export{Filter as default}from'/ui.js';"
        else if(url.pathname==='/empty.js')body="export{Empty as default}from'/ui.js';"
        else if(url.pathname==='/clipboard.js')body='export const copyOrderNo=()=>{},copyIMEI=()=>{};'
        else if(url.pathname==='/common.js')body="export const pxToRpx=value=>value*750/innerWidth;export const img=value=>value;export const redirect=data=>window.navCalls.push(data);"
        else if(url.pathname==='/hooks.js')body="import{onMounted}from'/vue.js';export const onLoad=fn=>onMounted(()=>fn({}));export const onShow=fn=>onMounted(fn);"
        else if(url.pathname==='/sfc')body=await compile(url.searchParams.get('file'),target)
        else if(url.pathname==='/ts'){let file=url.searchParams.get('file');if(!file.endsWith('.ts'))file+='.ts';body=imports(transpile(preprocess(fs.readFileSync(path.join(base,file),'utf8'),target)),file,target)}
        else if(url.pathname==='/main.js')body=`import{createApp,defineComponent,h,onMounted}from'/vue.js';import*as UI from'/ui.js';const target=new URLSearchParams(location.search).get('target')||'h5';window.navCalls=[];window.uni={getSystemInfoSync:()=>({windowWidth:innerWidth,windowHeight:innerHeight,statusBarHeight:24}),getMenuButtonBoundingClientRect:()=>({top:26,height:32,width:84,left:innerWidth-96}),hideKeyboard(){},showToast(){},showModal(){},setClipboardData(){},navigateBack(){},reLaunch(){}};window.getCurrentPages=()=>[{},{}];const{default:List}=await import('/sfc?file=pages/order/list.vue&target='+target);const Paging=defineComponent({props:['modelValue','pagingStyle','scrollable'],emits:['query','update:modelValue'],setup(props,{slots,emit,expose}){const query=()=>emit('query',1,15);expose({reload:query,refresh:query,complete:rows=>{if(Array.isArray(rows))emit('update:modelValue',rows)}});onMounted(query);return()=>h('div',{class:'paging-fixture',style:{top:parseFloat(props.pagingStyle?.top||0)*innerWidth/750+'px',overflowY:props.scrollable===false?'hidden':'auto'}},[slots.default?.(),props.modelValue?.length?null:slots.empty?.()])}});const app=createApp({setup:()=>()=>[h(List),target==='mp'?h('div',{class:'capsule-fixture'},'•••　◉'):null]});for(const[name,component]of Object.entries({'u-popup':UI.Popup,'u-icon':UI.Icon,'u-avatar':UI.Avatar,'u-tag':UI.Tag,'u-tabs':UI.Tabs,'u-calendar':UI.Calendar,'z-paging':Paging}))app.component(name,component);app.mount('#app');`
        else{response.setHeader('Content-Type','text/html; charset=utf-8');body=html}response.end(body)
    }catch(error){response.statusCode=500;response.end(String(error.stack))}})
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser,count=0
    try {
        browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'})
        const origin=`http://127.0.0.1:${server.address().port}`
        for(const [width,height]of[[320,568],[390,844]])for(const target of['h5','mp']){
            const page=await browser.newPage({viewport:{width,height},isMobile:true});const errors=[];page.on('pageerror',error=>errors.push(String(error)))
            await page.route('**/*',route=>new URL(route.request().url()).origin===origin?route.continue():route.abort())
            await page.goto(origin+'/?target='+target);await page.locator('.order-card').first().waitFor();await page.waitForTimeout(100)
            assert.equal(errors.length,0,errors.join('\n'));count++
            assert.equal(await page.locator('.agent-order-bar').count(),0);count++
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);count++
            const fab=await page.locator('.agent-order-fab').boundingBox();assert.ok(fab&&fab.x>=0&&fab.y>=0&&fab.x+fab.width<=width&&fab.y+fab.height<=height&&fab.width>=44);count++
            const search=page.locator(target==='mp'?'.nav-search-box':'.search-box')
            if(target==='mp'){const box=await search.boundingBox(),capsule=await page.locator('.capsule-fixture').boundingBox();assert.ok(box.x+box.width<=capsule.x-2,'customer search overlaps capsule');count++}
            await page.screenshot({path:`/tmp/recycle-customer-list-${target}-${width}.png`})
            await search.click();await page.locator('.recycle-member-picker__item').first().waitFor();assert.equal(await page.locator('.agent-order-fab').count(),0);count++
            await page.waitForTimeout(30);assert.equal(await page.locator('.recycle-member-picker__input').getAttribute('data-native-focus'),'true');count++
            const input=page.locator('.recycle-member-picker__input');await input.fill('张');await page.waitForTimeout(380)
            assert.equal(await page.evaluate(()=>memberCalls.at(-1).keyword),'张');count++
            assert.equal(await page.evaluate(()=>queryCalls.length),1);count++
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);count++
            const keyboard=height===568?280:320
            await input.evaluate((node,value)=>{node.dispatchEvent(new CustomEvent('keyboardheightchange',{detail:{height:value}}));const keyboard=document.createElement('div');keyboard.className='keyboard-fixture';keyboard.textContent='本地模拟键盘';keyboard.style.cssText='position:fixed;left:0;right:0;bottom:0;height:'+value+'px;background:#e5e7eb;z-index:20000;text-align:center;padding-top:24px;color:#64748b';document.body.appendChild(keyboard)},keyboard)
            await page.waitForTimeout(80)
            const searchBox=await input.boundingBox(),pickerList=await page.locator('.recycle-member-picker__list').boundingBox()
            assert.ok(searchBox.y>=0&&searchBox.y+searchBox.height<=height-keyboard+1,'keyboard covers customer search');count++
            assert.ok(pickerList.height>0&&pickerList.y+pickerList.height<=height-keyboard+1,'keyboard covers customer results');count++
            if(target==='mp'){
                const close=await page.locator('.recycle-member-picker__close').boundingBox(),capsule=await page.locator('.capsule-fixture').boundingBox()
                assert.ok(close.x+close.width<=capsule.x||close.x>=capsule.x+capsule.width||close.y>=capsule.y+capsule.height+2,'native capsule covers picker close control');count++
            }
            await page.screenshot({path:`/tmp/recycle-customer-picker-${target}-${width}.png`})
            await input.evaluate(node=>{node.dispatchEvent(new CustomEvent('keyboardheightchange',{detail:{height:0}}));document.querySelector('.keyboard-fixture')?.remove()})
            await page.waitForTimeout(40)
            await page.locator('.recycle-member-picker__item').first().click();await page.waitForTimeout(100)
            assert.equal(await page.locator('.recycle-member-picker').count(),0);count++
            assert.equal(await page.evaluate(()=>queryCalls.at(-1).member_id),75);count++
            assert.ok((await search.innerText()).includes('张老板'));count++
            assert.equal(await page.evaluate(()=>queryCalls.every(query=>!('keyword'in query)&&!('search'in query))),true);count++
            if(target==='mp'){const box=await search.boundingBox(),capsule=await page.locator('.capsule-fixture').boundingBox();assert.ok(box.x+box.width<=capsule.x-2);count++}
            await page.screenshot({path:`/tmp/recycle-customer-selected-${target}-${width}.png`})
            await page.locator('.tabs-fixture button').nth(1).click();await page.waitForTimeout(100);assert.equal(await page.evaluate(()=>queryCalls.at(-1).member_id),75);count++
            assert.equal(await page.evaluate(()=>String(queryCalls.at(-1).status)),'1');count++
            await page.locator('.customer-search-clear').click();await page.waitForTimeout(100);assert.equal(await page.evaluate(()=>queryCalls.at(-1).member_id),undefined);count++
            assert.equal(await page.locator('.recycle-member-picker').count(),0);count++
            const paging=page.locator('.paging-fixture');await paging.evaluate(node=>node.scrollTop=node.scrollHeight);await page.waitForTimeout(60)
            const last=await page.locator('.order-card').last().boundingBox(),float=await page.locator('.agent-order-fab').boundingBox();assert.ok(last.y+last.height<float.y,'FAB covers last row at scroll end');count++
            await page.locator('.agent-order-fab').click();await page.locator('.agent-fixture').waitFor();assert.equal(await page.locator('.agent-order-fab').count(),0);count++
            await page.screenshot({path:`/tmp/recycle-customer-fab-open-${target}-${width}.png`});await page.locator('.agent-fixture button').click();await page.locator('.agent-order-fab').waitFor()
            assert.equal(await page.evaluate(()=>writeCalls.length),0);count++
            assert.equal(errors.length,0,errors.join('\n'));count++
            await page.close();console.log(`PASS recycle customer/FAB ${target} ${width}x${height}`)
        }
        console.log(`[通过] ${count} 项回收客户筛选/FAB真实SFC模拟浏览器断言；非微信真机验收。`)
    }finally{await browser?.close();await new Promise(resolve=>server.close(resolve))}
}
main().catch(error=>{console.error(error);process.exitCode=1})
