import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { loadMobileUtility } from './mobile-utils.mjs'

const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const require = createRequire(path.resolve(plugin, '../../../uni-app/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const mobile = path.join(plugin, 'uni-app'), components = path.join(mobile, 'components/cooperation')
let checks = 0
const check = (condition, label) => { assert.ok(condition, label); checks++ }
const equal = (actual, expected, label) => { assert.deepEqual(JSON.parse(JSON.stringify(actual)), expected, label); checks++ }

const selection = loadMobileUtility('catalog-selection')
const rows = selection.normalizeCatalog([
    { id: '001', title: 'iPhone 16', brand: '苹果', category_name: '手机' },
    { id: '002', title: 'iPhone 17', brand: '苹果', category_name: '手机' },
    { id: '003', title: 'Mate 70', brand: '华为', category_name: '手机' },
    { id: '004', title: 'iPad Air', brand: '苹果', category_name: '平板' },
])
equal(selection.catalogCategories(rows).map(row => [row.name, row.count]), [['手机', 3], ['平板', 1]], 'real catalog category_name establishes separate product categories')
const apple = selection.visibleModels(rows, { category: '手机', brand: '苹果' })
equal(apple.map(row => row.id), ['001', '002'], 'category and brand constraints are applied together')
equal(selection.visibleModels(rows, { keyword: 'iphone16' }).map(row => row.id), ['001'], 'search ignores whitespace and case')
equal(selection.applySelection([rows[3]], apple, 'select_all', 0).items.map(row => row.id), ['004', '001', '002'], 'zero imposes no business limit and range selection preserves another category')
equal(selection.applySelection([rows[0], rows[3]], apple, 'invert', 0).items.map(row => row.id), ['004', '002'], 'invert only touches the current visible category/brand')
equal(selection.applySelection(rows, apple, 'clear', 0).items.map(row => row.id), ['003', '004'], 'clear only touches the current range')
const many = Array.from({ length: 150 }, (_, id) => ({ id: 'catalog_' + id, title: '型号 ' + id, brand: '品牌', category_name: '手机' }))
check(selection.applySelection([], many, 'select_all', 0).items.length === 150, 'delivery capabilities have no old 100-item business cap')
const bounded = selection.applySelection([rows[3]], apple, 'select_all', 2)
check(!!bounded.error && bounded.items.length === 1 && bounded.items[0].id === '004', 'over-limit batches remain atomic when an explicit limit is supplied')
check(selection.selectionState([rows[0]], apple).indeterminate, 'partial range selection reports an indeterminate checkbox')

const demand = loadMobileUtility('demand-models')
check(demand.normalizeDemandModelLimit(undefined) === 5, 'customer demand defaults to five models')
check(demand.normalizeDemandModelLimit(0) === 0 && demand.demandModelLimitError(many, 0) === '', 'unlimited customer selection does not use a false zero cap')
check(!!demand.demandModelLimitError(rows, 3), 'configured customer demand limit is enforced separately from capabilities')
check(!!demand.toggleDemandModel(rows.slice(0, 3), rows[3], 3).error, 'adding an item above the customer limit leaves prior choices intact')
check(demand.toggleDemandModel(rows.slice(0, 3), rows[0], 3).items.length === 2, 'removal remains possible after reaching the limit')
check(demand.demandModelPreview(rows).items.length === 3 && demand.demandModelPreview(rows).hidden === 1, 'default detail display folds long customer demands')
check(demand.demandModelPreview(rows, true).items.length === 4, 'explicit expansion reveals all customer demands')

const flow = loadMobileUtility('connection-actions').connectionActionState
const initial = { connected: false, editing: false, hasPreview: false, setupStage: '', channelClosed: false, enabled: false, syncStatus: 'idle' }
for (const [changes, step, action] of [
    [{}, 'authorize', 'verify'],
    [{ hasPreview: true }, 'preview', 'activate'],
    [{ connected: true, setupStage: 'start_sync' }, 'resume', 'resume'],
    [{ connected: true, enabled: true, syncStatus: 'running' }, 'manage', 'sync'],
    [{ connected: true, enabled: true, syncStatus: 'completed' }, 'manage', 'return'],
    [{ connected: true, enabled: false, syncStatus: 'completed' }, 'manage', 'enable'],
    [{ connected: true, channelClosed: true }, 'manage', 'return'],
]) {
    const next = flow({ ...initial, ...changes })
    check(next.step === step && next.primary === action, 'ERP has one primary next action for ' + step + '/' + action)
}

const hook = fs.readFileSync(path.join(mobile, 'composables/useCooperationPage.ts'), 'utf8')
const hookJs = ts.transpileModule(hook, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
function pageFixture(options) {
    const callbacks = {}, module = { exports: {} }; let shareHooks = 0
    const lifecycle = Object.fromEntries(['onHide', 'onLoad', 'onPullDownRefresh', 'onReachBottom', 'onShow'].map(name => [name, callback => { callbacks[name] = callback }]))
    vm.runInNewContext('(function(require,module,exports){' + hookJs + '})', { Number, String, uni: { stopPullDownRefresh() {} } })(key => {
        if (key === 'vue') return Vue
        if (key === '@dcloudio/uni-app') return lifecycle
        if (key === '@/hooks/useShare') return { useShare: () => ({ onShareAppMessage() { shareHooks++ } }) }
        throw new Error('Unexpected page dependency: ' + key)
    }, module, module.exports)
    const page = module.exports.useCooperationPage(); callbacks.onLoad(options)
    return { page, shareHooks }
}
let page = pageFixture({ collaboration_id: '92', promotion_invite: '1', mid: '201' })
check(page.page.collaborationId.value === 92 && page.page.promotionRequested.value && page.page.promotionInviter.value === 201, 'invitation intent and existing collaboration deep link remain separate')
check(page.shareHooks === 1, 'Mini Program share hook is registered in real page setup')
for (const mid of ['-1', '0', '1.5', '4294967296', 'bad']) check(pageFixture({ promotion_invite: '1', mid }).page.promotionInviter.value === 0, 'invalid invitation member id is rejected: ' + mid)
page = pageFixture({ mid: '201' })
check(!page.page.promotionRequested.value, 'an ordinary framework mid is not treated as consent to plugin promotion binding')

const source = name => fs.readFileSync(path.join(components, name + '.vue'), 'utf8')
const workbench = source('CooperationWorkbench'), profile = source('CoopProfilePanel'), picker = source('ModelCatalogPicker')
check(workbench.indexOf('class="coop-delivery-controls"') < workbench.indexOf('class="coop-main-scroll"'), 'native list filter controls do not scroll away with rows')
check(workbench.includes("import FrameworkTabbar from '@/components/tabbar/tabbar.vue'") && workbench.includes("open('hsx_lead_cooperation'"), 'navigation and payment continue through framework implementations')
check(workbench.includes(':config="ui.config.value"') && source('CoopPublishForm').includes('businessSelectionLimit(business.value)'), 'publishing form consumes each server-returned business selection policy')
check(picker.includes("cooperationCatalog('', { browse: 1, business_type: props.businessType })") && profile.includes("max: 0, selection_mode: 'multiple'"), 'the shared selector uses a business directory while merchant capabilities remain multi-select independent of customer limits')
check(profile.includes('推广中心') && workbench.includes('@promotion="openPromotion"') && workbench.includes("overlay.value !== 'promotion'"), 'any logged-in promoter may open promotion without creating a merchant profile')
check(workbench.includes('await processPromotionIntent()') && workbench.includes('pendingPromotion ? { promotion_invite: 1') && !workbench.includes('relation.bind'), 'invitation survives login and is delegated to server-backed confirmation, not local relation writes')
for (const name of ['store-discovery', 'inventory', 'delivery-capability', 'reliable-wing']) check(fs.statSync(path.join(mobile, 'static/cooperation/visuals', name + '.png')).size > 100, 'original approved local artwork is bundled: ' + name)
const avatar = source('CoopMerchantAvatar')
check(avatar.includes('v-if="showHighlight" class="coop-merchant-avatar__wing') && !avatar.includes('coop-merchant-avatar__badge'), 'the pure highlighted avatar retains double wings without creating a certification badge')
for (const name of fs.readdirSync(components).filter(name => name.endsWith('.vue'))) {
    const text = fs.readFileSync(path.join(components, name), 'utf8')
    check(!/@prototype|@mobile|prototypeSession|simulatePay|readMobile|writeMobile/.test(text), name + ' has no prototype business-data dependency')
}
const pageDictionary = fs.readFileSync(path.join(plugin, 'package/uni-app-pages.php'), 'utf8')
check((pageDictionary.match(/"navigationStyle": "custom"/g) || []).length === (pageDictionary.match(/"path": "pages\//g) || []).length - 1 && pageDictionary.includes('pages/entity/index') && pageDictionary.includes('pages/membership/index') && pageDictionary.includes('pages/orders/index'), 'business page registrations disable duplicate native title bars; invitation alone uses the framework native login title')
check(pageDictionary.includes('"path": "pages/invite/index", "style": { "navigationBarTitleText": "登录注册" }, "needLogin": false') && !fs.readFileSync(path.join(mobile,'pages/invite/index.vue'),'utf8').includes('<CooperationNav'), 'public login entry has one native header and does not require login before opening')
console.log(`PASS ${checks} real mobile UI migration checks; actual plugin utilities and page lifecycle with synthetic inputs, no API/payment/database calls.`)
