// 本地离线验证：仅执行 SFC 编译与模拟接口，不连接数据库、不访问网络、不发送通知。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const { execFileSync } = require('node:child_process')
const root = path.resolve(__dirname, '../../../..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const sass = require(path.join(root, 'admin/node_modules/sass'))
let checks = 0
const check = (condition, label) => { assert.ok(condition, label); checks++ }
const clone = value => JSON.parse(JSON.stringify(value))

function compileSfc(relative, id) {
    const file = path.join(__dirname, '../admin', relative)
    const source = fs.readFileSync(file, 'utf8')
    const parsed = compiler.parse(source, { filename: file })
    check(parsed.errors.length === 0, `${relative}: SFC parses`)
    compiler.compileScript(parsed.descriptor, { id })
    check(compiler.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id, compilerOptions: { expressionPlugins: ['typescript'] } }).errors.length === 0, `${relative}: template compiles`)
    for (const style of parsed.descriptor.styles) if (style.lang === 'scss') sass.compileString(style.content)
    check(source === fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle', relative), 'utf8'), `${relative}: admin mirror is exact`)
    return { source, parsed }
}
const { source, parsed } = compileSfc('views/third_party/express_order.vue', 'pickup-config')
const guideSfc = compileSfc('components/PickupSetupGuide.vue', 'pickup-setup-guide')
check(!/v-model\s*=\s*["']noticeForm\[[^"']+\.enabled/.test(source), 'notification enable flag has no second editable UI switch')
check(source.includes('系统开关已开启') && source.includes('/setting/notice/template'), 'read-only notice status links to system message management')
check(!source.includes('setPickupNoticeConfig') && !source.includes('saveNotice') && !source.includes('noticeForm') && !source.includes('mapping-row'), 'pickup page has no independent notification template editor or save flow')
check(source.includes('/channel/weapp/message') && source.includes('/channel/wechat/message'), 'channel template acquisition uses native framework pages')
check(source.includes('公共模板尚待开发方核实') && source.includes('catalog_ready'), 'unverified public templates are shown as unavailable rather than ready')
const noticeApi = fs.readFileSync(path.join(__dirname, '../admin/api/pickup_notice.ts'), 'utf8')
check(!noticeApi.includes('request.put') && !noticeApi.includes('setPickupNoticeConfig'), 'pickup notification API module is read-only')
check(noticeApi === fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle/api/pickup_notice.ts'), 'utf8'), 'notice API mirror is exact')
check(guideSfc.source.includes('官方目录支持 ≠ 本站账号已开通'), 'guide distinguishes official support from account permissions')

// 从正在交付的 PHP 纯类生成产品字典，通知使用只读状态契约。
const fixtures = JSON.parse(execFileSync('php', ['-r',
    'require $argv[1]; echo json_encode(["guide" => \\addon\\hsx_recycle\\app\\service\\core\\express\\provider\\Kuaidi100ProductCatalog::guide()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);',
    path.join(__dirname, '../app/service/core/express/provider/Kuaidi100ProductCatalog.php'),
], { encoding: 'utf8' }))
check(fixtures.guide.source_type === 'official_reference_catalog', 'API mock uses actual backend catalog')
const script = parsed.descriptor.scriptSetup.content.replace(/^import .*$/gm, '') + '\n;({form, configLoaded, configDirty, configBusy, savedProviderLabel, refreshConfig, saveConfig, checkSavedConfig, carrierChanged, modeChanged, configValidationError, catalog, catalogLoaded, catalogLoading, catalogError, loadCatalog, carrierOptions, productOptions, selectionIssue, noticeReadiness, noticeLoaded, noticeLoading, noticeError, loadNoticeOnce, refreshNotice})'
const executable = ts.transpileModule(script, { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText

function harness() {
    const state = {
        messages: [], saves: [], tests: 0, reads: 0, catalogReads: 0, noticeReads: 0, confirmations: 0,
        discard: false, loadError: null, saveError: null, testError: null, catalogLoadError: null,
        noticeLoadError: null, noticeResponseOverride: null, readAfterSaveError: false,
        testResult: { success: true, message: '可使用' }, catalogResult: clone(fixtures.guide),
        notices: { weapp: { enabled: 0, catalog_ready: false, template_ready: false, ready: false, missing: ['尚未在消息管理启用此渠道', '公共模板待开发方核实'], authorization_note: '请客户主动订阅' }, wechat: { enabled: 0, catalog_ready: false, template_ready: false, ready: false, missing: ['公共模板待开发方核实'], authorization_note: '请关注并绑定公众号' } }
    }
    const noticeResponse = () => {
        const data = { notice_key: 'hsx_recycle_pickup_update', catalog_sync_message: '公共模板待开发方核实', switch_managed_by_notice: true }
        for (const channel of ['weapp', 'wechat']) {
            data[channel] = clone(state.notices[channel])
        }
        return { data }
    }
    const ref = value => ({ value })
    const context = {
        URL, Set, ref, reactive: value => value, computed: getter => ({ get value() { return getter() } }),
        onMounted: fn => { state.mount = fn }, onBeforeRouteLeave: fn => { state.leave = fn }, useRouter: () => ({ push() {} }),
        ElMessage: Object.fromEntries(['success', 'warning', 'error', 'info'].map(type => [type, message => state.messages.push({ type, message })])),
        ElMessageBox: { confirm: async () => { state.confirmations++; if (!state.discard) throw new Error('cancel') } },
        useThirdPartyServiceConfig: (_, defaults) => {
            state.saved = clone(defaults)
            state.saved.yisu.appid = 'saved-app'
            state.saved.yisu.app_secret = '******'
            state.saved.kuaidi100 = { ...state.saved.kuaidi100, api_key: '******', secret: '******', callback_salt: '******', service_type: '顺丰标快', callback_url: 'https://pickup.example.com/api/recycle/express/kuaidi100_push' }
            const form = clone(defaults), loading = ref(false), saving = ref(false), testing = ref(false)
            return { form, loading, saving, testing, capabilityInfo: ref({}), load: async () => {
                state.reads++; loading.value = true
                try {
                    if (state.loadError || (state.readAfterSaveError && state.saves.length)) throw state.loadError || new Error('read failed')
                    Object.keys(form).forEach(key => delete form[key]); Object.assign(form, clone(state.saved))
                } finally { loading.value = false }
            } }
        },
        getPickupProductGuide: async () => { state.catalogReads++; if (state.catalogLoadError) throw state.catalogLoadError; return { data: clone(state.catalogResult) } },
        apiThirdPartyConfigSave: async data => { state.saves.push(clone(data)); if (state.saveError) throw state.saveError; state.saved = clone(data.express_order); return { data: true } },
        apiThirdPartyConfigTest: async capability => { check(capability === 'express_order', 'check is limited to pickup'); state.tests++; if (state.testError) throw state.testError; return { data: state.testResult } },
        getPickupNoticeConfig: async () => {
            state.noticeReads++
            if (state.noticeLoadError) throw state.noticeLoadError
            return state.noticeResponseOverride || noticeResponse()
        }
    }
    return { state, page: vm.runInNewContext(executable, context) }
}

async function testDraftAndCheck() {
    const { state, page } = harness()
    await page.saveConfig()
    check(state.saves.length === 0, 'initial unread defaults never overwrite saved config')
    await state.mount()
    check(page.configLoaded.value && !page.configDirty.value, 'successful load establishes saved snapshot')
    check(page.catalogLoaded.value && state.catalogReads === 1, 'mount loads official reference dictionary')
    page.form.provider = 'kuaidi100'
    check(page.configDirty.value && page.savedProviderLabel.value.includes('易速'), 'draft provider does not relabel saved status')
    await page.checkSavedConfig()
    check(state.tests === 0 && page.form.provider === 'kuaidi100', 'unsaved check blocked and draft preserved')
    await page.refreshConfig()
    check(state.confirmations === 1 && state.reads === 1 && page.configDirty.value, 'cancel refresh preserves draft')
    check(await state.leave() === false, 'cancel route leave preserves draft')
    state.discard = true
    await page.refreshConfig()
    check(state.reads === 2 && page.form.provider === 'yisu' && !page.configDirty.value, 'confirmed refresh reloads saved config')
    await page.checkSavedConfig()
    check(state.tests === 1 && state.reads === 2, 'check never reloads the editing form')
    check(state.messages.at(-1).message.includes('未验证账号权限') && !state.messages.at(-1).message.includes('可使用'), 'check success does not repeat provider activation claim')
    state.testError = { msg: '指定渠道检查失败' }
    await page.checkSavedConfig()
    check(state.messages.at(-1).message === '指定渠道检查失败' && !page.configBusy.value, 'check handles API msg and resets busy state')

    page.form.kuaidi100.carrier_code = 'jd' // el-select 的 v-model 先变更，再派发 change。
    page.form.kuaidi100.service_type = '顺丰标快'
    page.form.kuaidi100.payment = 'CONSIGNEE'
    page.carrierChanged('jd')
    const jd = fixtures.guide.modes.find(mode => mode.value === 'online').carriers.find(carrier => carrier.code === 'jd')
    check(page.form.kuaidi100.service_type === '' && page.form.kuaidi100.carrier_name === jd.name, 'carrier switch invalidates old product and uses canonical backend name')
    check(page.form.kuaidi100.payment === 'CONSIGNEE', 'carrier switch never silently changes payment responsibility')
    page.form.kuaidi100.mode = 'offline'; page.form.kuaidi100.service_type = '特惠送'; page.modeChanged()
    check(page.form.kuaidi100.carrier_code === 'jd' && page.form.kuaidi100.service_type === '', 'mode switch keeps supported carrier and clears previous product')
    check(page.form.kuaidi100.payment === 'CONSIGNEE', 'mode switch preserves chosen payment')
    page.form.kuaidi100.mode = 'online'; page.form.kuaidi100.carrier_code = 'kuayue'; page.form.kuaidi100.service_type = '标准快递'; page.modeChanged()
    check(page.form.kuaidi100.carrier_code === '' && page.form.kuaidi100.carrier_name === '' && page.form.kuaidi100.service_type === '', 'unsupported carrier on mode switch is cleared rather than replaced')
    check(page.form.kuaidi100.payment === 'CONSIGNEE', 'online switch does not silently convert consignee to shipper')
    page.form.kuaidi100.carrier_code = 'yimidida'; page.form.kuaidi100.service_type = '标准快递'; page.modeChanged()
    check(page.form.kuaidi100.carrier_code === '' && page.form.kuaidi100.service_type === '', 'mode switch never keeps disabled adapter')
}

async function testValidationAndSave() {
    const { state, page } = harness()
    await state.mount()
    const valid = clone(state.saved); valid.provider = 'kuaidi100'
    check(page.configValidationError(valid) === '', 'complete masked configuration remains valid')
    for (const field of ['api_key', 'secret', 'carrier_code', 'carrier_name', 'service_type', 'callback_url']) {
        const invalid = clone(valid); invalid.kuaidi100[field] = ' '
        check(page.configValidationError(invalid) !== '', `missing ${field} is rejected`)
    }
    for (const [field, value] of [['timeout', null], ['timeout', 2], ['timeout', 61], ['timeout', 3.5], ['mode', 'other'], ['environment', 'other'], ['carrier_code', '顺丰'], ['payment', 'CONSIGNEE']]) {
        const invalid = clone(valid); invalid.kuaidi100[field] = value
        check(page.configValidationError(invalid) !== '', `invalid ${field}:${value} is rejected`)
    }
    const unsupported = clone(valid); Object.assign(unsupported.kuaidi100, { mode: 'offline', payment: 'CONSIGNEE', carrier_code: 'zhongtong', service_type: '标准快递' })
    check(page.configValidationError(unsupported).includes('不支持到付'), 'offline carrier payment restriction explained')
    for (const mutation of [
        { carrier_code: 'jd', service_type: '顺丰标快' },
        { mode: 'offline', carrier_code: 'shunfeng', service_type: '顺丰标快' },
        { mode: 'offline', carrier_code: 'jtexpress', service_type: '标准快递' },
        { mode: 'online', carrier_code: 'kuayue', service_type: '标准快递' },
        { carrier_code: 'yimidida', service_type: '标准快递' },
        { carrier_code: 'zhongtongkuaiyun', service_type: '标准快递' },
        { carrier_code: 'kuaidi100zhixuan', service_type: '标准快递' },
        { service_type: '任意商务手填产品' }
    ]) {
        const invalid = clone(valid); Object.assign(invalid.kuaidi100, mutation)
        check(page.configValidationError(invalid) !== '', `invalid catalog combination rejected: ${JSON.stringify(mutation)}`)
        Object.assign(page.form, invalid)
        await page.saveConfig()
        check(state.saves.length === 0, 'invalid catalog combination never reaches save API')
    }
    const callbackPath = '/api/recycle/express/kuaidi100_push'
    for (const url of [`http://pickup.example.com${callbackPath}`, `https://localhost${callbackPath}`, `https://127.0.0.1${callbackPath}`, `https://192.168.1.1${callbackPath}`, `https://172.16.1.1${callbackPath}`, `https://user:pass@pickup.example.com${callbackPath}`, `https://pickup.example.com${callbackPath}#fragment`, 'https://pickup.example.com/wrong', `https://pickup.example.com${callbackPath}?record_id=1`, 'bad-url']) {
        const invalid = clone(valid); invalid.kuaidi100.callback_url = url
        check(page.configValidationError(invalid) !== '', `invalid callback rejected: ${url}`)
    }
    const disabled = clone(valid); disabled.enabled = 0; disabled.kuaidi100 = {}
    check(page.configValidationError(disabled) === '', 'disabling never requires complete active credentials')
    Object.assign(page.form, clone(valid)); page.form.kuaidi100.carrier_name = ' 任意伪造公司名 '
    await page.saveConfig()
    check(state.saves.length === 1 && state.saves[0].express_order.kuaidi100.carrier_name === '顺丰速运', 'save derives carrier name from catalog instead of trusting editable input')
    check(state.saves[0].express_order.kuaidi100.api_key === '******', 'save preserves masked credentials')
    check(!page.configDirty.value && page.savedProviderLabel.value.includes('快递100'), 'successful save reloads and updates saved provider')
    check(state.messages.some(item => item.type === 'success' && item.message.includes('未发起真实寄件')), 'save success explicitly excludes real booking')
    page.form.kuaidi100.service_type = '顺丰特快'
    state.saveError = { msg: '配置保存被拒绝' }
    await page.saveConfig()
    check(page.configDirty.value && !page.configBusy.value && state.messages.at(-1).message === '配置保存被拒绝', 'backend-rejected valid selection preserves draft and backend reason')
    check(page.form.kuaidi100.service_type === '顺丰特快', 'backend rejection never restores stale product over draft')
    state.saveError = null; state.readAfterSaveError = true
    await page.saveConfig()
    check(!page.configLoaded.value && state.messages.at(-1).message.includes('保存请求已成功'), 'read-after-save failure is not misreported as write failure')
    const saves = state.saves.length
    await page.saveConfig()
    check(state.saves.length === saves, 'unconfirmed re-read blocks blind duplicate save')
    const failed = harness(); failed.state.loadError = { msg: '配置读取失败' }
    await failed.state.mount(); await failed.page.saveConfig()
    check(!failed.page.configLoaded.value && failed.state.saves.length === 0, 'initial read failure cannot save defaults')
}

async function testCatalogFailureAndDraft() {
    const { state, page } = harness()
    state.catalogLoadError = { msg: '官方参考目录读取失败' }
    await state.mount()
    page.form.provider = 'kuaidi100'
    await page.saveConfig()
    check(page.configLoaded.value && !page.catalogLoaded.value && state.saves.length === 0, 'catalog read failure blocks enabling even when account config loaded')
    check(page.catalogError.value === '官方参考目录读取失败' && !page.catalogLoading.value, 'catalog failure retains actionable reason and clears busy state')
    check(page.configValidationError(page.form).includes('参考目录'), 'catalog loading requirement explained')
    page.form.enabled = 0; page.form.kuaidi100.service_type = '尚未确认的旧草稿'
    await page.saveConfig()
    check(state.saves.length === 1 && state.saved.enabled === 0 && state.saved.kuaidi100.service_type === '尚未确认的旧草稿', 'catalog unavailable still allows disabled draft save without rewriting product')
    state.catalogLoadError = null; state.catalogResult = { modes: [] }
    await page.loadCatalog()
    check(!page.catalogLoaded.value && page.catalogError.value.includes('不完整'), 'empty catalog response is rejected')
    state.catalogResult = clone(fixtures.guide)
    const draftBefore = clone(page.form)
    await page.loadCatalog()
    check(page.catalogLoaded.value && !page.catalogError.value && JSON.stringify(page.form) === JSON.stringify(draftBefore), 'catalog retry succeeds without modifying draft')
    check(page.selectionIssue.value.includes('不匹配'), 'legacy unsupported draft remains visible with mismatch warning')
    page.form.enabled = 1
    await page.saveConfig()
    check(state.saves.length === 1, 'retrieved catalog does not authorize legacy arbitrary product')
    page.form.kuaidi100.service_type = '顺丰特快'
    const chosenDraft = clone(page.form)
    state.catalogLoadError = new Error('目录暂不可用')
    await page.loadCatalog(); await page.saveConfig()
    check(!page.catalogLoaded.value && state.saves.length === 1 && JSON.stringify(page.form) === JSON.stringify(chosenDraft), 'failed reload invalidates readiness but preserves selected draft')
}

async function testNotice() {
    const { state, page } = harness()
    await page.loadNoticeOnce([])
    check(state.noticeReads === 0, 'collapsed notice section does not fetch')
    state.noticeLoadError = { msg: '模板读取失败' }
    await page.loadNoticeOnce(['notice'])
    check(!page.noticeLoaded.value && page.noticeError.value === '模板读取失败' && state.messages.at(-1).message === '模板读取失败', 'read failure is visible and retryable')
    state.noticeLoadError = null
    await page.loadNoticeOnce(['notice']); await page.loadNoticeOnce(['notice'])
    check(state.noticeReads === 2 && page.noticeLoaded.value, 'read-only notice status loads once when section is expanded')
    check(page.noticeReadiness.value.weapp.template_ready === false && page.noticeReadiness.value.weapp.missing.length === 2, 'backend readiness and reasons are preserved without fabricating success')
    check(page.noticeReadiness.value.weapp.catalog_ready === false && page.noticeReadiness.value.weapp.catalog_sync_message.includes('开发方核实'), 'unverified templates remain unavailable')
    check(page.noticeReadiness.value.weapp.authorization_note === '请客户主动订阅', 'authorization requirements come from backend status')
    check(!Object.hasOwn(page.noticeReadiness.value.weapp, 'template_id') && !Object.hasOwn(page.noticeReadiness.value.weapp, 'content'), 'read model excludes template IDs and editable mapping')
    check(!page.noticeError.value, 'successful retry clears stale error')

    state.notices.weapp = { enabled: 1, catalog_ready: true, template_ready: true, ready: true, missing: [], authorization_note: '仍需客户订阅', catalog_sync_message: '插件已提供固定模板定义，微信获取结果为准' }
    await page.refreshNotice()
    check(page.noticeReadiness.value.weapp.enabled === true && page.noticeReadiness.value.weapp.ready === true && page.noticeReadiness.value.weapp.catalog_ready === true, 'refresh reflects framework status changes without any write')
    check(page.noticeReadiness.value.weapp.catalog_sync_message === state.notices.weapp.catalog_sync_message, 'channel-specific acquisition guidance overrides global fallback')
    check(page.noticeReadiness.value.weapp.ready && !page.noticeReadiness.value.wechat.catalog_ready, 'UI independently displays ready mini-program and unverified official-account channel')
    check(state.saves.length === 0, 'reading notice status does not save courier or notification configuration')
    const readsBefore = state.noticeReads
    page.noticeLoading.value = true; await page.refreshNotice(); page.noticeLoading.value = false
    check(state.noticeReads === readsBefore, 'concurrent notice refresh is prevented')
    check(await state.leave() === true, 'read-only notice section creates no unsaved-draft warning')

    state.noticeResponseOverride = { data: { weapp: {}, wechat: null } }
    await page.refreshNotice()
    check(!page.noticeLoaded.value && page.noticeError.value.includes('返回不完整') && Object.keys(page.noticeReadiness.value).length === 0, 'malformed status invalidates prior readiness')
    state.noticeResponseOverride = null
    state.notices.weapp = { enabled: '0', catalog_ready: 'true', template_ready: 'true', ready: 'true', missing: ['真实原因', null, {}], authorization_note: {} }
    await page.refreshNotice()
    check(page.noticeReadiness.value.weapp.enabled === false && page.noticeReadiness.value.weapp.catalog_ready === false && page.noticeReadiness.value.weapp.template_ready === false, 'string truthy values cannot fabricate enabled or template-ready status')
    check(page.noticeReadiness.value.weapp.missing.length === 1 && page.noticeReadiness.value.weapp.authorization_note === '', 'malformed descriptions are removed safely')
    state.noticeLoadError = new Error('网络中断')
    await page.refreshNotice()
    check(!page.noticeLoaded.value && !page.noticeLoading.value && Object.keys(page.noticeReadiness.value).length === 0 && page.noticeError.value === '网络中断', 'failed refresh clears stale ready status and resets loading')
}

;(async () => {
    await testDraftAndCheck()
    await testValidationAndSave()
    await testCatalogFailureAndDraft()
    await testNotice()
    console.log(`PASS ${checks} pickup configuration checks (2 SFCs + backend catalog + offline interaction mocks, no DB/network/notifications)`)
})().catch(error => { console.error(error); process.exitCode = 1 })
