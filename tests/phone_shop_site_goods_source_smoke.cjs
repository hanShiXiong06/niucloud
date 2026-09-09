'use strict'
// 管理端小程序归属筛选回归：执行页面真实脚本，隔离请求，不连接数据库或线上环境。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '..')
const dep = name => require(path.join(root, 'site-uniapp/node_modules', name))
const ts = dep('typescript')
const vue = dep('vue')
const sfc = dep('@vue/compiler-sfc')
const mp = dep('@dcloudio/uni-mp-compiler')
const { initPreContext, preJs, preHtml } = dep('@dcloudio/uni-cli-shared/dist/preprocess')
const filename = 'site-uniapp/src/addon/phone_shop/pages/goods/list.vue'
const read = file => fs.readFileSync(path.join(root, file), 'utf8')
const source = read(filename)
const plain = value => JSON.parse(JSON.stringify(value))
const flush = async () => { for (let i = 0; i < 8; i++) await Promise.resolve() }
let count = 0
const check = (name, fn) => { fn(); count++; console.log('PASS ' + name) }

function page(siteId = 100024) {
    initPreContext('mp-weixin')
    const descriptor = sfc.parse(preJs(preHtml(source)), { filename }).descriptor
    const code = ts.transpileModule(descriptor.scriptSetup.content + '\nexport { buildQuery, getListFn, filter, showSourceFilter, filterCount, sourceOptions, resetFilter, keyword, statusFilter, sortIdx, list, isMasterSite };', {
        compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }
    }).outputText
    const state = { requests: [], loads: [], reloads: 0, response: { data: { data: [], is_master_site: 0 } } }
    const mocks = {
        vue,
        '@dcloudio/uni-app': { onLoad: fn => state.loads.push(fn), onShow: () => {}, onPageScroll: () => {}, onReachBottom: () => {} },
        '@/utils/common': { img: x => x, pxToRpx: x => x * 2, redirect: () => {} },
        '@/components/mescroll/hooks/useMescroll.js': { default: () => ({ mescrollInit: () => {}, downCallback: () => {}, getMescroll: () => ({ resetUpScroll: () => state.reloads++ }) }) },
        '@/addon/phone_shop/api/goods': {
            getGoodsList: async query => { state.requests.push(plain(query)); return state.response },
            getSpecGroup: async () => ({ data: [] }), getGradeList: async () => ({ data: [] }),
            changeGoodsStatus: () => { throw new Error('Must not mutate goods') },
            deleteGoods: () => { throw new Error('Must not delete goods') }
        }
    }
    const exports = {}
    vm.runInNewContext(code, {
        exports, console,
        getCurrentPages: () => [],
        uni: {
            getStorageSync: key => key === 'siteId' ? siteId : '',
            getSystemInfoSync: () => ({ statusBarHeight: 24, windowWidth: 375 }),
            getMenuButtonBoundingClientRect: () => ({ top: 26, height: 32, left: 280 })
        },
        require: name => {
            if (name in mocks) return mocks[name]
            if (name.endsWith('.vue')) return {}
            throw new Error('Unmocked dependency: ' + name)
        }
    })
    state.vm = exports
    state.mescroll = { num: 1, size: 15, endSuccess: () => {}, endErr: () => { throw new Error('List request failed') } }
    return state
}

async function run() {
    check('归属选项绑定 proxy_type，移除固定站点编号和 source 精确筛选', () => {
        assert.match(source, /v-model="filter\.proxy_type"/)
        assert.doesNotMatch(source, /100024|100005|SELF_SOURCE|AGENT_SOURCE|q\.source\s*=|filter\.source|options\?\.source/)
    })
    for (const siteId of [100024, 100005, 200008]) {
        const state = page(siteId)
        state.loads[0]({ source: '100024' }) // URL 来源不能冒充当前登录站点或隐藏其他站点的切换入口。
        check('首屏就发送自营语义参数，不等接口返回后才生效：站点 ' + siteId, () => {
            assert.deepEqual(plain(state.vm.buildQuery(state.mescroll)), { page: 1, limit: 15, proxy_type: 'self' })
        })
    }

    const child = page(200008)
    child.vm.getListFn(child.mescroll)
    await flush()
    check('非固定站点也根据后端身份显示归属切换', () => {
        assert.equal(child.vm.showSourceFilter.value, true)
        assert.deepEqual(plain(child.vm.sourceOptions), [{ label: '全部', value: '' }, { label: '自营', value: 'self' }, { label: '代理', value: 'proxy' }])
        assert.equal(child.vm.filterCount.value, 1)
    })
    check('自营请求与电脑管理端一致', () => assert.equal(child.requests[0].proxy_type, 'self'))
    child.vm.filter.proxy_type = 'proxy'
    child.vm.getListFn(child.mescroll)
    await flush()
    check('代理发送 proxy_type=proxy，不指定某个主站 ID', () => {
        assert.deepEqual(child.requests[1], { page: 1, limit: 15, proxy_type: 'proxy' })
    })
    child.vm.filter.proxy_type = ''
    child.vm.getListFn(child.mescroll)
    await flush()
    check('全部不携带归属条件', () => assert.deepEqual(child.requests[2], { page: 1, limit: 15 }))

    child.vm.filter.proxy_type = 'self'
    child.vm.keyword.value = '357465822199406'
    child.vm.filter.goods_category = '2096'
    child.vm.filter.start_price = '100'
    child.vm.filter.end_price = '5000'
    child.vm.filter.stock_age = '0-7'
    child.vm.statusFilter.value = 0
    child.vm.sortIdx.value = 2
    check('IMEI、分类、价格、下架状态、库龄和排序仍可与自营组合', () => {
        assert.deepEqual(plain(child.vm.buildQuery(child.mescroll)), {
            page: 1, limit: 15, proxy_type: 'self', device_keywords: '357465822199406',
            goods_category: '2096', start_price: '100', end_price: '5000', status: 0,
            start_stock_age: '0', end_stock_age: '7', order: 'price', sort: 'desc'
        })
    })
    child.vm.keyword.value = 'iPhone Air'
    check('名称搜索不改回 source 参数', () => {
        const query = child.vm.buildQuery(child.mescroll)
        assert.equal(query.goods_name, 'iPhone Air')
        assert.equal(query.proxy_type, 'self')
        assert.equal(query.source, undefined)
    })
    child.vm.filter.proxy_type = 'proxy'
    child.vm.resetFilter()
    check('子站重置恢复自营，清除附加筛选并刷新', () => {
        assert.equal(child.vm.filter.proxy_type, 'self')
        assert.equal(child.vm.filter.goods_category, '')
        assert.equal(child.vm.filter.start_price, '')
        assert.equal(child.reloads, 1)
    })
    check('翻页仍然保留自营参数', () => {
        const query = child.vm.buildQuery({ num: 2, size: 15 })
        assert.equal(query.page, 2)
        assert.equal(query.proxy_type, 'self')
    })

    const master = page(300001)
    master.response = { data: { data: [], is_master_site: '1' } }
    master.vm.getListFn(master.mescroll)
    await flush()
    check('主站身份也由后端判断，不依赖前端站点编号', () => {
        assert.equal(master.vm.showSourceFilter.value, false)
        assert.equal(master.vm.filter.proxy_type, '')
        assert.equal(master.vm.filterCount.value, 0)
        assert.deepEqual(plain(master.vm.buildQuery(master.mescroll)), { page: 1, limit: 15 })
    })
    master.vm.resetFilter()
    check('主站重置不额外筛选 source 或 proxy_type', () => assert.deepEqual(plain(master.vm.buildQuery(master.mescroll)), { page: 1, limit: 15 }))

    check('请求仍调用管理端商品接口，不切换为销售端列表', () => {
        assert.match(read('site-uniapp/src/addon/phone_shop/api/goods.ts'), /request\.get\('phone_shop\/goods', params\)/)
    })
    check('现有后台契约支持 self/proxy，仍限制当前登录站点', () => {
        const backend = read('niucloud/addon/phone_shop/app/service/admin/goods/GoodsService.php')
        const pageMethod = backend.slice(backend.indexOf('public function getPage('), backend.indexOf('private function applySaleStateFilter('))
        assert.match(read('niucloud/addon/phone_shop/app/adminapi/controller/goods/Goods.php'), /\["proxy_type", ""\]/)
        assert.match(pageMethod, /\['goods\.site_id', '=', \$this->site_id\]/)
        assert.match(pageMethod, /empty\(\$where\['source'\]\) && !\$is_master/)
        assert.match(pageMethod, /\$where\['proxy_type'\] === 'self'[\s\S]*?where\('goods\.source', '<>', \(string\) \$master_site_id\)/)
        assert.match(pageMethod, /\$where\['proxy_type'\] === 'proxy'[\s\S]*?where\('goods\.source', '=', \(string\) \$master_site_id\)/)
        assert.match(pageMethod, /getMasterSiteId\(\)/)
    })
    check('自营来源为空是正常建表和新增约定，不能等同本站 ID', () => {
        assert.match(read('niucloud/addon/phone_shop/sql/install.sql'), /`source`\s+varchar\(30\)\s+NOT NULL DEFAULT ''/)
        assert.match(read('niucloud/addon/phone_shop/app/service/admin/goods/GoodsService.php'), /'source' => \(string\)\(\$data\[ 'source' \] \?\? ''\)/)
    })

    for (const platform of ['h5', 'mp-weixin']) {
        process.env.UNI_PLATFORM = platform
        process.env.UNI_INPUT_DIR = path.join(root, 'site-uniapp/src')
        initPreContext(platform)
        check(platform + ' 页面脚本和模板编译', () => {
            const parsed = sfc.parse(preJs(preHtml(source)), { filename })
            assert.deepEqual(parsed.errors, [])
            const script = sfc.compileScript(parsed.descriptor, { id: 'site-goods-source' })
            const template = sfc.compileTemplate({ source: parsed.descriptor.template.content, filename, id: 'site-goods-source', compilerOptions: { bindingMetadata: script.bindings } })
            assert.deepEqual(template.errors, [])
            assert.deepEqual(ts.transpileModule(script.content, { reportDiagnostics: true }).diagnostics || [], [])
            if (platform === 'mp-weixin') {
                const errors = [], output = []
                mp.compile(parsed.descriptor.template.content, {
                    mode: 'module', prefixIdentifiers: true, filename: path.join(root, filename),
                    bindingMetadata: script.bindings, onError: error => errors.push(error),
                    miniProgram: {
                        directive: 'wx:', class: { array: true }, event: { key: true },
                        slot: { fallbackContent: false, dynamicSlotNames: true },
                        component: { vShow: 'hidden', normalizeName: name => name }, emitFile: asset => output.push(asset.source)
                    }
                })
                assert.deepEqual(errors, [])
                assert.ok(output.length > 0)
            }
        })
    }
    console.log(`完成 ${count} 项隔离检查；未访问线上数据，未进行真机验收或完整项目打包。`)
}
run().catch(error => { console.error(error); process.exitCode = 1 })
