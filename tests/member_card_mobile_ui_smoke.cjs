'use strict'
// 执行插件真实脚本和双端模板编译；所有业务请求使用内存模拟，禁止访问线上。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '..')
const base = path.join(root, 'site-uniapp/src/addon/hsx_member_card')
const dep = name => require(path.join(root, 'site-uniapp/node_modules', name))
const vue = dep('vue'), ts = dep('typescript'), sfc = dep('@vue/compiler-sfc')
const mp = dep('@dcloudio/uni-mp-compiler')
const { initPreContext, preJs, preHtml } = dep('@dcloudio/uni-cli-shared/dist/preprocess')
const read = file => fs.readFileSync(path.join(base, file), 'utf8')
const plain = v => JSON.parse(JSON.stringify(v))
const flush = async () => { for (let i = 0; i < 15; i++) await Promise.resolve() }
const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { promise, resolve, reject } }
let count = 0
const check = async (name, fn) => { await fn(); count++; console.log('PASS ' + name) }
const walk = dir => fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)])

function harness(file, names, api = {}, props = {}) {
    const events = { load: [], show: [], unload: [], unmount: [], emits: [], toasts: [], modal: [], navigations: [], requests: [] }
    const source = read(file)
    const script = file.endsWith('.vue') ? sfc.parse(source).descriptor.scriptSetup.content : source
    const code = ts.transpileModule(script + '\nexport { ' + names.join(', ') + ' };', { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } }).outputText
    const exports = {}
    const context = {
        exports, console, setTimeout, clearTimeout, Promise,
        defineProps: () => props,
        withDefaults: (value, defaults) => Object.assign({}, defaults, value),
        defineEmits: () => (...args) => events.emits.push(args),
        uni: {
            showToast: value => events.toasts.push(value),
            showModal: async value => { events.modal.push(value); return { confirm: true } },
            navigateTo: value => events.navigations.push(value), redirectTo: value => events.navigations.push(value), navigateBack: () => {},
            pageScrollTo: () => {}, setNavigationBarTitle: () => {}, setClipboardData: () => {}, scanCode: () => {}, makePhoneCall: () => {}
        },
        require: name => {
            if (name === 'vue') return { ...vue, onBeforeUnmount: fn => events.unmount.push(fn) }
            if (name === '@dcloudio/uni-app') return { onLoad: fn => events.load.push(fn), onShow: fn => events.show.push(fn), onUnload: fn => events.unload.push(fn) }
            if (name.endsWith('/api')) return { memberCardRequestId: p => p + ':' + Math.random(), ...api }
            if (name.endsWith('/utils/presentation')) return presentation
            if (name.endsWith('.vue') || name.endsWith('.scss')) return {}
            if (name === '@/app/api/system') return api
            if (name === '@/utils/common') return { img: s => s }
            throw new Error('禁止未模拟依赖：' + name)
        }
    }
    vm.runInNewContext(code, context, { filename: file })
    return { ...events, v: exports, context }
}
let presentation

async function run() {
    for (const platform of ['h5', 'mp-weixin']) {
        process.env.UNI_PLATFORM = platform
        process.env.UNI_INPUT_DIR = path.join(root, 'site-uniapp/src')
        initPreContext(platform)
        for (const file of walk(base).filter(f => f.endsWith('.vue'))) {
            await check(platform + ' 编译 ' + path.relative(base, file), () => {
                const parsed = sfc.parse(preJs(preHtml(fs.readFileSync(file, 'utf8'))), { filename: file })
                assert.deepEqual(parsed.errors, [])
                const script = sfc.compileScript(parsed.descriptor, { id: 'member-ui' })
                const template = sfc.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: 'member-ui', compilerOptions: { bindingMetadata: script.bindings } })
                assert.deepEqual(template.errors, [])
                assert.deepEqual(ts.transpileModule(script.content, { reportDiagnostics: true }).diagnostics || [], [])
                if (platform === 'mp-weixin') {
                    const errors = [], assets = []
                    mp.compile(parsed.descriptor.template.content, {
                        mode: 'module', prefixIdentifiers: true, filename: file, bindingMetadata: script.bindings,
                        onError: e => errors.push(e), miniProgram: {
                            directive: 'wx:', class: { array: true }, event: { key: true }, slot: { fallbackContent: false, dynamicSlotNames: true },
                            component: { vShow: 'hidden', normalizeName: n => n }, emitFile: asset => assets.push(asset.source)
                        }
                    })
                    assert.deepEqual(errors, []); assert.ok(assets.length)
                }
            })
        }
    }
    presentation = harness('utils/presentation.ts', []).v
    await check('公共样式按端加载：H5 不受页面作用域限制，小程序样式不丢失', () => {
        const pages = walk(path.join(base, 'pages')).filter(file => file.endsWith('.vue'))
        assert.equal(pages.length, 10)
        for (const file of pages) {
            const source = fs.readFileSync(file, 'utf8')
            for (const platform of ['h5', 'mp-weixin']) {
                initPreContext(platform)
                const descriptor = sfc.parse(preJs(preHtml(source)), { filename: file }).descriptor
                if (platform === 'h5') {
                    assert.match(descriptor.scriptSetup.content, /import\s+['"][^'"]+mobile\.scss['"]/)
                    assert.ok(descriptor.styles.every(style => !style.content.includes('@import')), file)
                } else {
                    assert.doesNotMatch(descriptor.scriptSetup.content, /import\s+['"][^'"]+\.scss['"]/)
                    assert.ok(descriptor.styles.some(style => style.lang === 'scss' && style.content.includes("@import '../../styles/mobile.scss'")), file)
                }
            }
        }
        const edit = sfc.parse(read('pages/product/edit.vue')).descriptor
        assert.equal(edit.styles.length, 1)
        assert.ok(edit.styles[0].content.includes('.mc-product-edit__scroll'))
    })
    await check('uview 分段选择按业务值映射，禁用时不能修改', () => {
        const options = [{ label: '独立', value: 'local' }, { label: 'ERP', value: 'erp' }]
        const h = harness('components/MemberCardSegmented.vue', ['current', 'change'], {}, { modelValue: 'erp', options })
        assert.equal(h.v.current.value, 1); h.v.change(0)
        assert.deepEqual(plain(h.emits), [['update:modelValue', 'local'], ['change', 'local']])
        const disabled = harness('components/MemberCardSegmented.vue', ['change'], {}, { modelValue: 'local', options, disabled: true })
        disabled.v.change(1); assert.equal(disabled.emits.length, 0)
    })
    await check('uview 列表搜索与状态标签仍使用原筛选参数，清空会重查', () => {
        const h = harness('components/MemberCardListHeader.vue', ['currentTab', 'tabChanged', 'localKeyword', 'search', 'clear'], {}, {
            modelValue: '', activeTab: 'pending', tabs: [{ label: '全部', value: '' }, { label: '待收款', value: 'pending' }]
        })
        assert.equal(h.v.currentTab.value, 1); h.v.tabChanged({ index: 0 })
        assert.deepEqual(plain(h.emits.slice(0, 2)), [['update:activeTab', ''], ['tab-change', '']])
        h.v.localKeyword.value = ' 测试 '; h.v.search(); assert.deepEqual(plain(h.emits.at(-1)), ['search', '测试'])
        h.v.clear(); assert.deepEqual(plain(h.emits.at(-1)), ['search', ''])
    })
    await check('核销复选控件受业务忙碌状态保护', () => {
        const h = harness('pages/card/search.vue', ['verificationChanged', 'confirmed', 'redeeming'])
        h.v.verificationChanged(['verified']); assert.equal(h.v.confirmed.value, true)
        h.v.redeeming.value = true; h.v.verificationChanged([]); assert.equal(h.v.confirmed.value, true)
    })
    await check('指定生效和失效日期的卡种不会被错误标成默认选项', () => {
        const h = harness('pages/product/edit.vue', ['form', 'effectiveOptions', 'validityOptions'])
        h.v.form.fixed_start_at = 1700000000; h.v.form.fixed_end_at = 1800000000
        assert.ok(h.v.effectiveOptions.value.some(option => option.value === 'fixed'))
        assert.ok(h.v.validityOptions.value.some(option => option.value === 'fixed'))
    })
    await check('金额缺失不冒充 0，数量精度与 ERP / 本地账户契约', () => {
        assert.equal(presentation.money(undefined), '—'); assert.equal(presentation.money(null), '—'); assert.equal(presentation.money(0), '0.00')
        assert.equal(presentation.quantity(10), '10'); assert.equal(presentation.quantity(1.125), '1.125')
        assert.equal(presentation.accountOptions({ finance_provider: 'erp', capital_account_options: [{ id: 1 }] }).length, 1)
        assert.deepEqual(plain(presentation.accountOptions({ finance_provider: 'local', capital_account_options: [{ id: 1, status: 0 }, { id: 2, status: 1 }] }).map(a => a.id)), [2])
    })
    await check('四类列表搜索置顶、底部不遮挡、失败与空数据区分', () => {
        for (const file of ['member', 'product', 'order', 'redemption']) {
            const source = read('pages/' + file + '/list.vue')
            assert.match(source, /#top/); assert.match(source, /:error="listError"/); assert.doesNotMatch(source, /pagingStyle|158rpx|onShow\(reload\)/)
        }
        assert.match(read('components/MemberCardButton.vue'), /:loadingText=/)
        assert.match(read('components/MemberCardSheet.vue'), /:closeOnClickOverlay="!busy"/)
        assert.match(read('styles/mobile.scss'), /env\(safe-area-inset-bottom\)/)
    })
    await check('保位刷新超过 100 条仍完整，普通返回不重刷，写操作后才刷新', async () => {
        const h = harness('hooks/useMemberCardList.ts', [])
        const dataset = Array.from({ length: 351 }, (_, id) => ({ id }))
        const requests = [], completed = []
        const list = h.v.useMemberCardList(async p => { requests.push(p); return { data: { total: dataset.length, data: dataset.slice((p.page - 1) * p.limit, p.page * p.limit) } } }, () => ({ keyword: '客' }))
        let refreshed = 0
        list.paging.value = { complete: d => completed.push(d), refresh: () => { refreshed++ } }
        await list.query(1, 150); assert.deepEqual(plain(completed[0]), dataset.slice(0, 150)); assert.ok(requests.every(p => p.limit <= 100))
        await list.query(2, 150); assert.deepEqual(plain(completed[1]), dataset.slice(150, 300))
        h.show[0](); assert.equal(refreshed, 0); presentation.markMemberCardChanged(); h.show[0](); assert.equal(refreshed, 1)
    })
    await check('列表失败保留明确原因并通知分页失败', async () => {
        const h = harness('hooks/useMemberCardList.ts', [])
        const list = h.v.useMemberCardList(async () => { throw { msg: '没有查询权限' } }, () => ({})); let completed
        list.paging.value = { complete: v => { completed = v } }; await list.query(1, 15)
        assert.equal(completed, false); assert.equal(list.listError.value, '没有查询权限')
    })
    await check('工作台快速切日期：旧请求不能覆盖新结果，自定义取消不改变当前周期', async () => {
        const a = deferred(), b = deferred(); let request = 0
        const h = harness('pages/dashboard/index.vue', ['load', 'switchPeriod', 'overview', 'period', 'loading'], { getMemberCardDashboard: () => (++request === 1 ? a.promise : b.promise) })
        const first = h.v.load(); h.v.switchPeriod('month'); b.resolve({ data: { card_sale_amount: 200 } }); await flush(); a.resolve({ data: { card_sale_amount: 100 } }); await first
        assert.equal(h.v.overview.value.card_sale_amount, 200); assert.equal(h.v.loading.value, false)
        h.v.switchPeriod('custom'); assert.equal(h.v.period.value, 'month')
    })
    await check('开卡过滤停用账户，上传阻止提交，连点不重复，保留原请求重试', async () => {
        let calls = [], pending = deferred()
        const h = harness('pages/order/create.vue', ['load', 'submit', 'form', 'member', 'uploading', 'result', 'availableAccounts'], {
            getCardProductOptions: async () => ({ data: [{ id: 3, product_name: '贴膜卡', sale_price: '99.00', item: { binding_mode: 'member' } }] }),
            getMemberCardConfig: async () => ({ data: { finance_provider: 'local', default_capital_account_id: 1, allow_receivable: 0, capital_account_options: [{ id: 1, name: '已停用', status: 0 }, { id: 2, name: '微信', status: 1 }] } }),
            createCardOrder: p => { calls.push(plain(p)); return pending.promise }
        })
        await h.v.load(); assert.equal(h.v.form.capital_account_id, 2); assert.equal(h.v.availableAccounts.value.length, 1)
        h.v.member.value = { member_id: 8 }; h.v.form.product_id = 3
        h.v.uploading.value = true; await h.v.submit(); assert.equal(calls.length, 0); h.v.uploading.value = false
        const first = h.v.submit(); await h.v.submit(); assert.equal(calls.length, 1)
        pending.reject({}); await first; pending = deferred(); const second = h.v.submit(); assert.equal(calls[0].request_id, calls[1].request_id)
        pending.resolve({ data: { order_id: 9, amount: '99.00', success: false } }); await second
        assert.equal(h.v.result.value.order_id, 9); await h.v.submit(); assert.equal(calls.length, 2)
    })
    await check('核销单抽屉只确认一次，IMEI / 客户核验不绕过，重试使用原请求', async () => {
        const calls = []; let pending = deferred()
        const h = harness('pages/card/search.vue', ['openConfirm', 'redeem', 'confirmed', 'serviceImei', 'redeemError', 'outcome', 'mobile'], { redeemMemberCard: (id, p) => { calls.push(p); return pending.promise }, searchMemberCards: async () => ({ data: { candidates: [] } }) })
        h.v.mobile.value = '13800000001'; await flush()
        h.v.openConfirm({ holder_name: '测试' }, { available: true, card_id: 5, item_id: 6, binding_mode: 'imei' })
        await h.v.redeem(); assert.equal(calls.length, 0); h.v.confirmed.value = true; await h.v.redeem(); assert.equal(calls.length, 0)
        h.v.serviceImei.value = '357465822199406'; const first = h.v.redeem(); await h.v.redeem(); assert.equal(calls.length, 1); pending.reject({}); await first
        pending = deferred(); const second = h.v.redeem(); assert.equal(calls[0].request_id, calls[1].request_id); assert.equal(calls[1].verification_confirmed, 1)
        pending.resolve({ data: { redeem_no: 'RD1', inventory_status: 'failed' } }); await second
        assert.equal(h.v.outcome.value.warning, true); assert.equal(h.modal.length, 0)
    })
    await check('核销搜索输入变化清除旧客户，失败不冒充空结果', async () => {
        const pending = deferred()
        const h = harness('pages/card/search.vue', ['mobile', 'search', 'candidates', 'error'], { searchMemberCards: () => pending.promise })
        h.v.mobile.value = '0001'; await flush(); const first = h.v.search(); h.v.mobile.value = '0002'; await flush(); pending.resolve({ data: { candidates: [{ member_id: 1 }] } }); await first
        assert.equal(h.v.candidates.value.length, 0)
    })
    await check('新卡种部分完成后只补剩余步骤，不重建卡种、不重复期初库存', async () => {
        let saves = 0, stocks = 0, enables = 0
        const h = harness('pages/product/edit.vue', ['load', 'form', 'initialStock', 'submit', 'activationPending', 'id'], {
            getMemberCardConfig: async () => ({ data: { inventory_available: 1 } }),
            saveCardProduct: async () => { saves++; return { data: { id: 18 } } },
            adjustCardProductStock: async () => { stocks++; return { data: {} } },
            enableCardProduct: async () => { enables++; if (enables === 1) throw { msg: '暂不可启用' }; return { data: {} } }
        })
        await h.v.load(); h.v.form.product_name = '测试卡'; h.v.form.sale_price = '9.90'; h.v.initialStock.value = '20'
        await h.v.submit(); assert.equal(h.v.activationPending.value, true); assert.equal(h.v.id.value, 18)
        await h.v.submit(); assert.equal(saves, 1); assert.equal(stocks, 1); assert.equal(enables, 2); assert.equal(h.v.activationPending.value, false)
    })
    await check('盘点不预填 0，必须数量与原因，允许明确把库存调整为 0', async () => {
        const calls = []
        const h = harness('pages/product/edit.vue', ['id', 'stockTarget', 'stockRemark', 'adjustStock'], { adjustCardProductStock: async (id, p) => { calls.push(p); return { data: { stock_after: 0 } } } })
        h.v.id.value = 8; await h.v.adjustStock(); assert.equal(calls.length, 0)
        h.v.stockTarget.value = '0'; await h.v.adjustStock(); assert.equal(calls.length, 0)
        h.v.stockRemark.value = '实盘为零'; await h.v.adjustStock(); assert.equal(calls.length, 1); assert.equal(calls[0].target_quantity, 0); assert.match(h.modal[0].content, /不是追加/)
    })
    await check('配置账户更新不丢失未保存的耗材选择，换仓库重新选库位', () => {
        const h = harness('pages/config/payment.vue', ['applyConfig', 'inventoryForm', 'inventoryDirty', 'picker', 'chooseLocation', 'editor', 'accountStatusChanged'])
        h.v.applyConfig({ inventory_mode: 'none', inventory_warehouse_id: 1, inventory_location_id: 3 }, true)
        h.v.inventoryForm.mode = 'strict'; h.v.applyConfig({ inventory_mode: 'none' }); assert.equal(h.v.inventoryForm.mode, 'strict'); assert.equal(h.v.inventoryDirty.value, true)
        h.v.picker.value = 'warehouse'; h.v.chooseLocation({ id: 2 }); assert.equal(h.v.inventoryForm.locationId, 0)
        h.v.editor.status = 0; h.v.editor.is_default = 1; h.v.accountStatusChanged(); assert.equal(h.v.editor.is_default, 0)
    })
    await check('订单重试传递已选择 ERP 账户，不以新的开卡请求代替', async () => {
        const calls = []
        const h = harness('components/MemberCardOrderDetail.vue', ['order', 'loadAccounts', 'accountId', 'submit', 'mode', 'canCancel', 'canRetry'], {
            getMemberCardConfig: async () => ({ data: { finance_provider: 'erp', capital_account_options: [{ id: 7, name: '账户甲' }] } }),
            retryCardOrderFinance: async (id, p) => { calls.push([id, p]); return { data: { success: true } } },
            getCardOrder: async () => ({ data: { id: 11, business_status: 'active', paid_amount: 100, finance_status: 'settled' } })
        }, { show: false, orderId: 11 })
        h.v.order.value = { id: 11, capital_account_id: 7, settlement_mode: 'immediate', business_status: 'failed', finance_status: 'failed', paid_amount: 0 }
        assert.equal(h.v.canRetry.value, true); await h.v.loadAccounts(); assert.equal(h.v.accountId.value, 7)
        h.v.mode.value = 'retry'; await h.v.submit(); assert.equal(calls[0][0], 11); assert.equal(calls[0][1].capital_account_id, 7); assert.equal(h.v.canCancel.value, false)
        h.v.order.value = { business_status: 'cancelled', paid_amount: 0, finance_status: 'failed' }; assert.equal(h.v.canRetry.value, false); assert.equal(h.v.canCancel.value, false)
    })
    await check('会员详情注册登录路由，全部修改保持在插件及必要页面注册内', () => {
        const routes = fs.readFileSync(path.join(root, 'site-uniapp/src/pages.json'), 'utf8')
        const json = dep('json5').parse(routes)
        const group = json.subPackages.find(x => x.root === 'addon/hsx_member_card')
        assert.ok(group.pages.some(x => x.path === 'pages/member/detail' && x.needLogin === true))
        assert.equal(group.pages.length, 10)
        for (const file of walk(base).filter(f => f.endsWith('.vue'))) assert.doesNotMatch(fs.readFileSync(file, 'utf8'), /https:\/\/(gl|sass)\.hsxbk\.top/)
    })
    console.log('完成 ' + count + ' 项检查；未访问生产接口、数据库或执行真实收款。')
}
run().catch(e => { console.error(e); process.exitCode = 1 })
