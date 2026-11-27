#!/usr/bin/env node
// Real quote-page SFC, isolated APIs/upload/uni fixtures. Never contacts a business server.
const fs = require('node:fs')
const path = require('node:path')
const http = require('node:http')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const base = path.join(app, 'src/addon/hsx_recycle')
const sfc = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(app, 'node_modules/typescript'))
const { chromium } = require('/tmp/node_modules/playwright')
const transpile = source => ts.transpileModule(source, {
    compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext }
}).outputText

function resolveImports(source, file) {
    return source.replace(/from\s+(['"])([^'"]+)\1/g, (full, quote, specifier) => {
        if (specifier === 'vue') return "from '/vue.js'"
        if (specifier === '@dcloudio/uni-app') return "from '/hooks.js'"
        if (specifier === '@/utils/common') return "from '/common.js'"
        if (specifier === '@/app/api/system') return "from '/upload.js'"
        if (specifier === '@/addon/hsx_recycle/api/quote') return "from '/api.js'"
        const local = specifier.startsWith('@/addon/hsx_recycle/')
            ? specifier.slice('@/addon/hsx_recycle/'.length)
            : specifier.startsWith('.') ? path.posix.normalize(path.posix.join(path.posix.dirname(file), specifier)) : ''
        if (!local) throw Error('Unmocked import: ' + specifier)
        return `from '/${local.endsWith('.vue') ? 'sfc' : 'ts'}?file=${encodeURIComponent(local)}'`
    })
}

async function compile(file) {
    const filename = path.join(base, file)
    const source = fs.readFileSync(filename, 'utf8').replace(/@tap/g, '@click')
    const { descriptor, errors } = sfc.parse(source, { filename })
    if (errors.length) throw errors[0]
    const script = sfc.compileScript(descriptor, {
        id: 'quote-mobile-visual', inlineTemplate: true,
        templateOptions: { compilerOptions: { isCustomElement: tag => ['view', 'text', 'scroll-view', 'input', 'image', 'button', 'textarea'].includes(tag) } }
    })
    let css = ''
    for (const style of descriptor.styles) {
        const output = await sfc.compileStyleAsync({ filename, source: style.content, id: 'quote-mobile-visual', preprocessLang: style.lang })
        if (output.errors.length) throw output.errors[0]
        css += output.code
    }
    return resolveImports(transpile(script.content), file) + '\n{const style=document.createElement("style");style.textContent=' + JSON.stringify(css) + '.replace(/(-?[\\d.]+)rpx/g,(_,n)=>"calc("+n+" * 100vw / 750)");document.head.appendChild(style)}'
}

const fixture = `
window.quoteCalls=[];window.uploadCalls=[];window.modalCalls=[];window.toasts=[];window.saveMode='ok';window.uploadMode='ok';
window.quoteCategories=[
 {category_id:1,site_id:100005,category_name:'苹果',category_full_name:'苹果',pid:0,sort:100,is_show:1,images:'',child_list:[
  {category_id:2,site_id:100005,category_name:'iPhone 17 Pro Max 报价单',category_full_name:'苹果/iPhone 17 Pro Max 报价单',pid:1,sort:90,is_show:1,images:'/quote-old.jpg',child_list:[]}]},
 {category_id:3,site_id:100005,category_name:'安卓',category_full_name:'安卓',pid:0,sort:80,is_show:1,images:'',child_list:[
  {category_id:4,site_id:100005,category_name:'三星 Galaxy S26 Ultra',category_full_name:'安卓/三星 Galaxy S26 Ultra',pid:3,sort:50,is_show:1,images:'',child_list:[]}]},
 {category_id:8,site_id:0,category_name:'公共报价',category_full_name:'公共报价',pid:0,sort:10,is_show:1,images:'/quote-public.jpg',child_list:[]}
];
export async function getQuoteOptions(){return {code:1,data:{categories:structuredClone(window.quoteCategories),site_id:100005,can_edit:new URLSearchParams(location.search).get('readonly')!=='1'}}}
export async function updateQuoteImage(id,data){quoteCalls.push({id,...structuredClone(data)});if(window.saveMode==='fail')throw {msg:'模拟保存失败，原报价保留'};const rows=quoteCategories.flatMap(item=>[item,...(item.child_list||[])]);const row=rows.find(item=>item.category_id===Number(id));row.images=data.images;return {code:1,data:{category_id:Number(id),images:data.images,update_time:1794384000,changed:true}}}
export async function getQuoteHistory(){return {code:1,data:{data:[{id:9,images:'/quote-old.jpg',operator_name:'测试员工',create_time:1794384000,remark:'测试快照'}],total:1,last_page:1,current_page:1}}}
export const getCategoryQuoteOptions=getQuoteOptions;export const updateCategoryQuoteImage=updateQuoteImage;export const getCategoryQuoteHistory=getQuoteHistory;
`
const ui = `import{defineComponent,h,Teleport}from'/vue.js';export const Popup=defineComponent({props:['show'],emits:['close'],setup(p,{slots}){return()=>p.show?h(Teleport,{to:'body'},h('div',{class:'popup-fixture'},slots.default?.())):null}});export const Icon=defineComponent({props:['name'],setup:p=>()=>h('span',p.name?.includes('close')?'×':p.name?.includes('search')?'⌕':'›')});export const Button=defineComponent({props:['text','disabled','loading'],emits:['click'],setup(p,{slots,emit}){return()=>h('button',{class:'u-button-fixture',disabled:p.disabled||p.loading,onClick:e=>emit('click',e)},slots.default?.()||p.text)}});export const Search=defineComponent({props:['modelValue','placeholder'],emits:['update:modelValue'],setup(p,{emit}){return()=>h('input',{class:'search-fixture',placeholder:p.placeholder,value:p.modelValue,onInput:e=>emit('update:modelValue',e.target.value)})}});`
const html = `<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif;--hsx-primary:#2463eb;--hsx-primary-50:#eff5ff;--hsx-text-strong:#172033;--hsx-text-secondary:#64748b}view,scroll-view{display:block}text{display:inline}scroll-view{overflow:auto}input,textarea,button{font-family:inherit}input,textarea{min-width:0}button{cursor:pointer}.popup-fixture{position:fixed;inset:0;background:#fff;z-index:9999;overflow:auto}image{display:block;overflow:hidden}.nc-iconfont{font-family:sans-serif}</style><div id="app"></div><script type="module" src="/main.js"></script>`

async function main() {
    const server = http.createServer(async (request, response) => {
        try {
            const url = new URL(request.url, 'http://localhost')
            let body = ''
            response.setHeader('Content-Type', 'application/javascript')
            if (url.pathname === '/vue.js') body = fs.readFileSync(path.join(app, 'node_modules/vue/dist/vue.esm-browser.js'), 'utf8')
            else if (url.pathname === '/api.js') body = fixture
            else if (url.pathname === '/ui.js') body = ui
            else if (url.pathname === '/common.js') body = "export const img=value=>value;export const redirect=()=>{};export const timeFormat=value=>String(value);"
            else if (url.pathname === '/hooks.js') body = "import{onMounted,onUnmounted}from'/vue.js';export const onLoad=fn=>onMounted(()=>fn({}));export const onShow=fn=>onMounted(fn);export const onUnload=fn=>onUnmounted(fn);export const onPullDownRefresh=()=>{};export const onBackPress=()=>{};"
            else if (url.pathname === '/upload.js') body = "export async function uploadImage(data){uploadCalls.push(data);if(uploadMode==='fail')throw {msg:'模拟上传失败'};return {code:1,data:{url:'/quote-new.jpg'}}}"
            else if (url.pathname === '/sfc') body = await compile(url.searchParams.get('file'))
            else if (url.pathname === '/ts') { let file = url.searchParams.get('file'); if (!file.endsWith('.ts')) file += '.ts'; body = resolveImports(transpile(fs.readFileSync(path.join(base, file), 'utf8')), file) }
            else if (url.pathname === '/main.js') body = `import{createApp}from'/vue.js';import*as UI from'/ui.js';window.uni={getStorageSync:key=>key==='siteId'?100005:null,setStorageSync(){},getSystemInfoSync:()=>({windowWidth:innerWidth,windowHeight:innerHeight,statusBarHeight:24}),pageScrollTo:()=>scrollTo(0,0),stopPullDownRefresh(){},showToast:data=>toasts.push(data),hideKeyboard(){},showLoading(){},hideLoading(){},previewImage:data=>window.previewCall=data,chooseImage:options=>{window.chooseOptions=options;options.success({tempFilePaths:['/local-picked.jpg'],tempFiles:[{path:'/local-picked.jpg',size:50000}]})},showModal:options=>{modalCalls.push(options);options.success?.({confirm:true,cancel:false});return Promise.resolve({confirm:true,cancel:false})},navigateBack(){}};window.getCurrentPages=()=>[{},{}];const{default:Page}=await import('/sfc?file=pages/quote/index.vue');const app=createApp(Page);for(const[name,component]of Object.entries({'u-popup':UI.Popup,'u-icon':UI.Icon,'u-button':UI.Button,'u-search':UI.Search}))app.component(name,component);app.mount('#app');`
            else { response.setHeader('Content-Type', 'text/html; charset=utf-8'); body = html }
            response.end(body)
        } catch (error) { response.statusCode = 500; response.end(String(error.stack)) }
    })
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    let browser
    try {
        browser = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' })
        const origin = `http://127.0.0.1:${server.address().port}`
        for (const width of [320, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 844 }, isMobile: true })
            const errors = []
            page.on('pageerror', error => errors.push(String(error)))
            await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort())
            await page.goto(origin)
            await page.getByText('iPhone 17 Pro Max 报价单', { exact: true }).first().waitFor()
            assert.deepEqual(errors, [])
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, '列表不能横向溢出')
            await page.screenshot({ path: `/tmp/recycle-mobile-quote-list-${width}.png` })
            // Interaction selectors are kept explicit below, against the actual page markup.
            await runWorkflow(page, width)
            assert.deepEqual(errors, [])
            await page.close()
            const readonly = await browser.newPage({ viewport: { width, height: 844 }, isMobile: true })
            await readonly.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort())
            await readonly.goto(origin + '/?readonly=1')
            await readonly.getByText('iPhone 17 Pro Max 报价单', { exact: true }).first().click()
            assert.equal(await readonly.locator('.quote-footer').count(), 0, '无编辑权限不得出现上传操作')
            assert.match(await readonly.locator('.quote-note').innerText(), /没有报价编辑权限/)
            assert.equal(await readonly.evaluate(() => quoteCalls.length + uploadCalls.length), 0)
            await readonly.close()
        }
        console.log('[通过] 移动报价真实 SFC 隔离浏览器交互回归；未调用业务接口、未上传云存储、非微信真机验收。')
    } finally { await browser?.close(); await new Promise(resolve => server.close(resolve)) }
}

async function runWorkflow(page, width) {
    await page.locator('.search-fixture').fill('三星')
    await page.waitForFunction(() => document.querySelectorAll('.quote-category').length === 1)
    assert.match(await page.locator('.quote-category-name').innerText(), /三星/)
    await page.locator('.search-fixture').fill('')
    await page.getByText('已有报价', { exact: true }).click()
    await page.waitForFunction(() => document.querySelectorAll('.quote-category').length === 2)
    await page.getByText('全部', { exact: true }).click()
    await page.getByText('iPhone 17 Pro Max 报价单', { exact: true }).first().click()
    const currentImage = () => page.locator('.quote-card').first().locator('image')
    assert.equal(await currentImage().getAttribute('src'), '/quote-old.jpg')
    await page.getByRole('button', { name: '上传最新报价图', exact: true }).click()
    await page.getByText('图片已上传，确认更新后才会替换当前报价', { exact: true }).waitFor()
    assert.deepEqual(await page.evaluate(() => chooseOptions.sizeType), ['original'], '长图不能先压缩')
    assert.equal(await page.evaluate(() => uploadCalls.length), 1)
    assert.equal(await page.evaluate(() => quoteCalls.length), 0, '选图和上传不应自动发布')
    assert.equal(await currentImage().getAttribute('src'), '/quote-old.jpg')
    assert.equal(await page.locator('.quote-draft-card image').getAttribute('src'), '/quote-new.jpg')
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, '草稿详情不能横向溢出')
    await page.screenshot({ path: `/tmp/recycle-mobile-quote-draft-${width}.png` })

    await page.evaluate(() => { saveMode = 'fail' })
    await page.getByRole('button', { name: '确认更新报价', exact: true }).click()
    await page.locator('.quote-save-error').waitFor()
    assert.match(await page.locator('.quote-save-error').innerText(), /模拟保存失败/)
    assert.equal(await currentImage().getAttribute('src'), '/quote-old.jpg', '保存失败应保留原图')
    assert.equal(await page.locator('.quote-draft-card').count(), 1, '保存失败应保留新图以便重试')
    assert.equal(await page.evaluate(() => quoteCalls[0].expected_images), '/quote-old.jpg')
    await page.evaluate(() => { saveMode = 'ok' })
    await page.getByRole('button', { name: '确认更新报价', exact: true }).click()
    await page.waitForFunction(() => document.querySelector('.quote-card image')?.getAttribute('src') === '/quote-new.jpg')
    assert.equal(await page.locator('.quote-draft-card').count(), 0)
    assert.equal(await page.evaluate(() => quoteCalls.length), 2)
    assert.equal(await page.evaluate(() => quoteCalls[1].expected_images), '/quote-old.jpg')
    assert.equal(await page.evaluate(() => toasts.at(-1).title), '报价已更新')
    await page.locator('.quote-card').first().locator('.quote-preview').click()
    assert.deepEqual(await page.evaluate(() => previewCall.urls), ['/quote-new.jpg'])
    await page.locator('.quote-history-toggle').click()
    await page.locator('.quote-history-row').waitFor()
    assert.match(await page.locator('.quote-history-row').innerText(), /测试员工/)
    await page.screenshot({ path: `/tmp/recycle-mobile-quote-saved-${width}.png` })

    await page.evaluate(() => { uploadMode = 'fail' })
    await page.getByRole('button', { name: '上传最新报价图', exact: true }).click()
    await page.getByRole('button', { name: '重试上传', exact: true }).waitFor()
    assert.equal(await currentImage().getAttribute('src'), '/quote-new.jpg')
    assert.equal(await page.getByRole('button', { name: '确认更新报价', exact: true }).isDisabled(), true, '上传失败不能提交空地址')
    await page.evaluate(() => { uploadMode = 'ok' })
    await page.getByRole('button', { name: '重试上传', exact: true }).click()
    await page.getByText('图片已上传，确认更新后才会替换当前报价', { exact: true }).waitFor()
    assert.equal(await page.evaluate(() => quoteCalls.length), 2, '重试上传不应发布')
    await page.locator('.quote-back').click()
    await page.locator('.quote-toolbar').waitFor()
    assert.match(await page.evaluate(() => modalCalls.at(-1).title), /放弃/)
    await page.getByText('公共报价', { exact: true }).first().click()
    assert.equal(await page.locator('.quote-footer').count(), 0, '公共分类不可由站点发布')
    assert.match(await page.locator('.quote-note').innerText(), /公共分类仅可查看/)
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true)
    console.log(`PASS mobile quote workflow ${width}px: search, draft, save/retry, history, public readonly`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
