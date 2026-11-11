import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { spawnSync } from 'node:child_process'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Read actual uni-app output, not an SSR mock. Never opens WeChat or calls APIs.
// Usage: node tests/check-mobile-build.mjs --dir /absolute/path/to/mp-weixin
const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const workspace = path.resolve(plugin, '../../..')
const require = createRequire(path.join(workspace, 'uni-app/package.json'))
const postcss = require('postcss')
const selectorParser = require('postcss-selector-parser')
const args = process.argv.slice(2)
assert.ok(args.length === 2 && args[0] === '--dir', 'Pass --dir pointing to an existing mp-weixin build')
const output = path.resolve(args[1]), directory = path.join(output, 'addon/hsx_lead/components/cooperation')
const expectations = {
    CooperationWorkbench: ['hsx-cooperation', 'coop-delivery-controls', 'coop-main-scroll', 'coop-main-paging', 'coop-framework-tabbar', 'coop-framework-tabbar-mount', 'coop-work-overview'],
    CoopPublishForm: ['coop-form-section', 'coop-section-title', 'coop-form-row', 'coop-form-column', 'coop-business-choice', 'coop-business-choice__value', 'coop-search-trigger', 'coop-consent', 'coop-inline-error'],
    CoopBusinessField: ['coop-business-field', 'coop-business-field__label', 'coop-business-field__select', 'coop-business-field__address', 'coop-business-field__count'],
    CoopCommissionForm: ['coop-form-section', 'coop-amount-input', 'coop-amount-presets', 'coop-detail-shop', 'coop-muted', 'coop-form-error'],
    CoopProfilePanel: ['coop-profile-theme', 'coop-profile-card', 'coop-profile-title', 'coop-profile-name', 'coop-profile-badges', 'coop-form-section', 'coop-form-column', 'coop-picker-field', 'coop-profile-tools', 'coop-profile-tool', 'coop-profile-tool__label', 'coop-profile-promotion', 'coop-inline-error', 'coop-profile-field-error', 'coop-profile-erp'],
    CoopMembershipEntry: ['entity-membership-entry', 'entity-membership-entry__body', 'entity-membership-entry__title', 'entity-membership-entry__hint', 'entity-membership-entry__bottom'],
    CoopInvitePoster: ['coop-poster', 'coop-poster__header', 'coop-poster__title', 'coop-poster__preview', 'coop-poster__image', 'coop-poster__status', 'coop-poster__retry', 'coop-poster__actions', 'coop-poster__cancel', 'coop-poster__primary', 'coop-poster__error', 'coop-poster__settings'],
    CoopLevelBadge: ['coop-level-badge', 'coop-level-badge__surface', 'coop-level-badge__mark', 'coop-level-badge__caption', 'coop-level-badge__state', 'coop-level-badge__edge'],
    CoopBusinessIcon: ['coop-business-icon'],
    CoopBusinessNavigation: ['lead-business', 'lead-business-heading', 'lead-business-subtitle', 'lead-business-items', 'lead-business-message', 'lead-business-error'],
    CoopRoleEntryCard: ['coop-role-card', 'coop-role-card__mask', 'coop-role-card__content', 'coop-role-card__copy', 'coop-role-card__title', 'coop-role-card__subtitle', 'coop-role-card__button', 'coop-role-card__art'],
    CoopExperienceProgress: ['coop-experience', 'coop-experience__heading', 'coop-experience__value', 'coop-experience__target', 'coop-experience__hint'],
    CoopMerchantAvatar: ['coop-merchant-avatar', 'coop-merchant-avatar__wing', 'coop-merchant-avatar__halo', 'coop-merchant-avatar__photo', 'coop-merchant-avatar__image', 'coop-merchant-avatar__fallback', 'coop-merchant-avatar__glint'],
    CoopBusinessLicenseUpload: ['coop-license-upload', 'coop-license-upload__tile', 'coop-license-upload__more', 'coop-license-upload__busy', 'coop-license-upload__error'],
    CoopStorefrontUpload: ['coop-storefront-upload', 'coop-storefront-upload__row', 'coop-storefront-upload__title', 'coop-storefront-upload__controls', 'coop-storefront-upload__tile', 'coop-storefront-upload__choose', 'coop-storefront-upload__preview', 'coop-storefront-upload__more', 'coop-storefront-upload__busy', 'coop-storefront-upload__error'],
    CoopPaymentSummary: ['coop-payment-summary', 'coop-payment-purchase', 'coop-payment-kind', 'coop-payment-symbol', 'coop-payment-name', 'coop-payment-caption', 'coop-payment-total', 'coop-payment-amount', 'coop-payment-loading', 'coop-payment-feedback', 'coop-payment-next'],
    CoopPaymentHelp: ['coop-payment-help', 'coop-payment-help__entry', 'coop-payment-help__title', 'coop-payment-help__mount', 'coop-payment-help__sheet', 'coop-payment-help__head', 'coop-payment-help__body', 'coop-payment-help__content', 'coop-payment-help__qr', 'coop-payment-help__detail', 'coop-payment-help__safe-end'],
    CoopProfileDiySection: ['lead-profile-section', 'lead-profile-section__heading', 'lead-profile-section__demo', 'lead-profile-section__mask', 'lead-profile-section__content'],
    CoopWalletPanel: ['coop-balance-card', 'coop-balance', 'coop-withdraw-entry', 'coop-income-summary', 'coop-wallet-entry', 'coop-menu-row', 'coop-loadmore'],
    CoopTaskDetail: ['coop-form-section', 'coop-customer-contact', 'coop-state-heading', 'coop-history-item', 'coop-footer-actions', 'coop-secondary', 'coop-primary'],
    CoopResponseDeadline: ['coop-response-deadline', 'coop-response-deadline__line', 'coop-response-deadline__digits', 'coop-response-deadline__hint'],
    CoopConnectionPanel: ['coop-form-section', 'coop-form-row', 'coop-form-column', 'coop-detail-shop', 'coop-small-actions', 'coop-captcha-row', 'coop-sms-input', 'coop-consent', 'coop-connection-overview', 'coop-connection-status', 'coop-connection-history', 'coop-sync-log__toggle', 'coop-sync-log__detail', 'coop-connection-settings'],
    CoopRegionFields: ['region-result', 'region-result__content', 'region-result__tail', 'region-level'],
    CoopTaskSheet: ['coop-sheet', 'coop-sheet__head', 'coop-sheet__body', 'coop-sheet__footer', 'coop-icon-button', 'coop-popup-mount'],
    CoopStorePicker: ['coop-store-picker', 'coop-region-search', 'coop-customer-location', 'coop-footer-note'],
    CoopStoreInventory: ['coop-stock-browser', 'coop-stock-row', 'coop-stock-paging', 'coop-stock-empty'],
    CooperationNav: ['cooperation-nav', 'cooperation-nav__heading', 'cooperation-nav__title'],
    CooperationSearch: ['coop-search-field'],
    ModelCatalogPicker: ['catalog-picker', 'catalog-columns', 'catalog-save-hint'],
    DemandModels: ['demand-models', 'demand-models__items', 'demand-models__tag']
}
let checks = 0
const components = []
for (const [name, classes] of Object.entries(expectations)) {
    const css = fs.readFileSync(path.join(directory, name + '.wxss'), 'utf8')
    const script = fs.readFileSync(path.join(directory, name + '.js'), 'utf8')
    const template = fs.readFileSync(path.join(directory, name + '.wxml'), 'utf8')
    const scope = script.match(/"__scopeId","(data-v-[a-f\d]+)"/)?.[1]
    assert.ok(scope, `${name} must have its own compiled scoped style`); checks++
    assert.ok(css.length > 0 && !/:deep\s*\(/.test(css), `${name} must emit real WXSS, not empty or raw deep selectors`); checks++
    const selectors = []
    postcss.parse(css).walkRules(rule => selectors.push(...rule.selectors))
    for (const className of classes) {
        assert.ok(template.includes(className), `Stale expectation: ${name} no longer uses ${className}`); checks++
        let owned = false
        for (const selector of selectors) selectorParser(root => root.walkClasses(node => {
            if (node.value !== className) return
            const siblings = node.parent.nodes, index = siblings.indexOf(node)
            let first = index, last = index
            while (first > 0 && siblings[first - 1].type !== 'combinator') first--
            while (last + 1 < siblings.length && siblings[last + 1].type !== 'combinator') last++
            owned ||= siblings.slice(first, last + 1).some(part => part.type === 'class' && part.value === scope)
        })).processSync(selector)
        assert.ok(owned, `${name}: ${className} must belong to its own WXSS scope, not its parent component`); checks++
    }
    const options = JSON.parse(fs.readFileSync(path.join(directory, name + '.json'), 'utf8'))
    assert.ok(options.styleIsolation !== 'shared', `${name} must not disable isolation to hide a missing local stylesheet`); checks++
    if (['CooperationWorkbench', 'CoopStoreInventory'].includes(name)) { assert.ok(options.usingComponents?.['z-paging'], `${name} must compile the actual installed z-paging component`); checks++ }
    components.push({ name, scope, wxss_bytes: Buffer.byteLength(css), required_classes: classes.length })
}
const app = JSON.parse(fs.readFileSync(path.join(output, 'app.json'), 'utf8'))
const retiredModules = ['components/cooperation/CoopPlanComparison', 'components/cooperation/CoopPlansEntryCard', 'utils/cooperation-plans']
for (const retired of retiredModules) {
    for (const extension of retired.startsWith('utils/') ? ['.js'] : ['.js', '.json', '.wxml', '.wxss']) {
        assert.ok(!fs.existsSync(path.join(output, 'addon/hsx_lead', retired + extension)), `Retired module must not survive in the actual native package: ${retired + extension}`); checks++
    }
}
function nativeFiles(root) { return fs.readdirSync(root, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? nativeFiles(path.join(root, entry.name)) : [path.join(root, entry.name)]) }
const artifactFiles = nativeFiles(output)
for (const file of artifactFiles.filter(file => /\.(?:js|json|wxml|wxss)$/.test(file))) {
    const content = fs.readFileSync(file, 'utf8')
    assert.ok(!retiredModules.some(retired => content.includes('hsx_lead/' + retired) || content.includes(retired + '.js')) && !/coop-plan-comparison|coop-plans-entry-card/.test(content), `Current package must not reference retired source: ${path.relative(output, file)}`)
}
checks++
for (const rank of [1, 2, 3, 4]) { assert.ok(!fs.existsSync(path.join(output, `addon/hsx_lead/static/cooperation/levels/level-v${rank}.png`)), `Retired metallic V${rank} artwork is absent from the native package`); checks++ }
const nativeBadgeCss = fs.readFileSync(path.join(directory, 'CoopLevelBadge.wxss'), 'utf8'), nativeBadgeScript = fs.readFileSync(path.join(directory, 'CoopLevelBadge.js'), 'utf8')
assert.ok(!/text-shadow|infinite|url\(/.test(nativeBadgeCss) && !/level-v[1-4]\.png/.test(nativeBadgeScript) && nativeBadgeCss.includes('--lead-member-gradient') && nativeBadgeCss.includes('--lead-member-text'), 'Native badge consumes configured membership gradient/text variables without metallic artwork or looping effects'); checks++
assert.ok(/(?:1400ms|1\.4s)/.test(nativeBadgeCss) && nativeBadgeCss.includes('prefers-reduced-motion'), 'Native high-tier opt-in edge retains a bounded duration and reduced-motion alternative'); checks++
// Exercise the actual generated membership helpers and WXML-gated effects.
// A styling object or selected tier must not manufacture paid membership.
function nativeSkinUtility(name) {
    const module = { exports: {} }
    vm.runInNewContext(fs.readFileSync(path.join(output, 'addon/hsx_lead/utils', name + '.js'), 'utf8'), { exports: module.exports, module }, { timeout: 1000 })
    return module.exports
}
const nativeSkin = nativeSkinUtility('membership-skin'), nativeVisual = nativeSkinUtility('membership-visual')
const nativeBundledImage = nativeSkinUtility('bundled-image')
assert.equal(nativeBundledImage.bundledImage('/static/owned.png'), '/static/owned.png', 'Mini Program bundled asset adapter preserves its native path without browser location'); checks++
assert.equal(typeof nativeSkin.membershipSkinVars, 'function', 'Native bundle exports the compiled controlled skin variable helper'); checks++
const visualSideEffects = ['utils/pages.js', 'app/stores/diy.js', 'hooks/useLogin.js', 'app/api/auth.js', 'utils/request.js', 'stores/member.js', 'app/api/member.js', 'locale/index.js', 'locale/i18n.js', 'locale/language.js', 'stores/system.js', 'app/api/system.js', 'stores/config.js']
const visualVendor = {
    defineComponent: value => value, computed: getter => ({ get value() { return getter() } }),
    ref: value => ({ value }), watch() {}, unref: value => value && typeof value === 'object' && 'value' in value ? value.value : value,
    e: (...values) => Object.assign({}, ...values), n: value => value, s: value => value,
    t: value => String(value ?? ''), p: value => value, o: callback => callback, _export_sfc: value => value,
}
function nativeVisualComponent(name) {
    let component
    vm.runInNewContext(fs.readFileSync(path.join(directory, name + '.js'), 'utf8'), {
        require: dependency => {
            if (dependency.endsWith('/common/vendor.js')) return visualVendor
            if (dependency.endsWith('/common/assets.js')) return { highlightWing: '/local-wing.png' }
            if (dependency.endsWith('/utils/membership-skin.js')) return nativeSkin
            if (dependency.endsWith('/utils/membership-visual.js')) return nativeVisual
            if (dependency.endsWith('/utils/bundled-image.js')) return nativeBundledImage
            if (dependency.endsWith('/utils/common.js')) return { img: value => value }
            if (visualSideEffects.some(relative => dependency.endsWith('/' + relative))) return {}
            throw Error('Unexpected native visual dependency: ' + dependency)
        }, wx: { createComponent: value => { component = value } },
    }, { timeout: 1000 })
    assert.ok(component?.setup, 'Native ' + name + ' has an executable setup'); checks++
    return component
}
function nativeVisualCondition(name, className) {
    const template = fs.readFileSync(path.join(directory, name + '.wxml'), 'utf8')
    const tag = template.match(new RegExp('<view\\b[^>]*class="[^"]*\\b' + className + '\\b[^>]*>'))?.[0] || ''
    const key = tag.match(/wx:if="\{\{([\w$]+)\}\}"/)?.[1]
    assert.ok(key, 'Native WXML retains conditional ' + className); checks++
    return key
}
const avatarComponent = nativeVisualComponent('CoopMerchantAvatar'), badgeComponent = nativeVisualComponent('CoopLevelBadge')
const haloKey = nativeVisualCondition('CoopMerchantAvatar', 'coop-merchant-avatar__halo'), wingKey = nativeVisualCondition('CoopMerchantAvatar', 'coop-merchant-avatar__wing--left'), edgeKey = nativeVisualCondition('CoopLevelBadge', 'coop-level-badge__edge')
const configuredSkin = { preset: 'custom', start_color: '#123456', end_color: '#456789', text_color: '#fff', accent_color: '#217590', angle: 82, avatar_effect: true }
for (const active of [false, 1, 'true', undefined]) {
    const frame = avatarComponent.setup({ image: '', name: '未开通', size: 'default', active, rank: 8, highlighted: true, appearance: configuredSkin })({}, {})
    assert.ok(!frame[haloKey] && !frame[wingKey] && !JSON.stringify(frame).includes('--lead-member-gradient'), 'Native inactive avatar cannot acquire paid effects from a target rank or skin'); checks++
}
let activeAvatar = avatarComponent.setup({ image: '', name: '已开通', size: 'default', active: true, rank: 8, highlighted: true, appearance: configuredSkin })({}, {})
assert.ok(activeAvatar[haloKey] && activeAvatar[wingKey] && JSON.stringify(activeAvatar).includes('linear-gradient(82deg,#123456,#456789)'), 'Native paid avatar receives the exact configured palette and optional wings'); checks++
activeAvatar = avatarComponent.setup({ image: '', name: '已开通', size: 'default', active: true, rank: 8, highlighted: true, appearance: { ...configuredSkin, avatar_effect: false } })({}, {})
assert.ok(!activeAvatar[haloKey] && !activeAvatar[wingKey] && JSON.stringify(activeAvatar).includes('--lead-member-gradient'), 'Native effect=false keeps a static paid border with no mounted looping layer'); checks++
for (const state of ['locked', 'expired']) {
    const frame = badgeComponent.setup({ level: 18, label: '旗舰门店', state, size: 'small', variant: 'inline', animated: true, showLabel: false, appearance: configuredSkin })({}, {})
    assert.ok(!frame[edgeKey] && JSON.stringify(frame).includes(state === 'locked' ? '未开通' : '已到期'), 'Native ' + state + ' badge preserves state and cannot animate'); checks++
}
const noEffectBadge = badgeComponent.setup({ level: 18, label: '旗舰门店', state: 'active', size: 'small', variant: 'inline', animated: true, showLabel: false, appearance: { ...configuredSkin, avatar_effect: false } })({}, {})
assert.ok(!noEffectBadge[edgeKey] && JSON.stringify(noEffectBadge).includes('旗舰门店') && !JSON.stringify(noEffectBadge).includes('V18'), 'Native badge keeps arbitrary real tier names and respects the effect switch'); checks++
const nativeAvatarCss = fs.readFileSync(path.join(directory, 'CoopMerchantAvatar.wxss'), 'utf8')
assert.ok(nativeAvatarCss.includes('--lead-member-gradient') && nativeAvatarCss.includes('--lead-member-accent') && nativeAvatarCss.includes('prefers-reduced-motion'), 'Native avatar uses controlled skin variables and retains reduced-motion treatment'); checks++
const nativeIconOptions = JSON.parse(fs.readFileSync(path.join(directory, 'CoopBusinessIcon.json'), 'utf8'))
assert.ok(nativeIconOptions.usingComponents?.['u-icon']?.endsWith('/u-icon/u-icon'), 'Shared business symbols resolve the installed standard uview icon component'); checks++
const nativeIconTemplate = fs.readFileSync(path.join(directory, 'CoopBusinessIcon.wxml'), 'utf8')
assert.equal((nativeIconTemplate.match(/<u-icon\b/g) || []).length, 1, 'Native operation icon is one standard outline, not doubled fill/line layers'); checks++
assert.ok(!/coop-business-icon__(?:fill|line)/.test(nativeIconTemplate), 'Retired doubled icon layers do not survive native compilation'); checks++
const nativeIconCss = fs.readFileSync(path.join(directory, 'CoopBusinessIcon.wxss'), 'utf8')
assert.ok(!/gradient\(|box-shadow|infinite/.test(nativeIconCss) && /(?:120ms|\.12s|0\.12s)/.test(nativeIconCss) && nativeIconCss.includes('prefers-reduced-motion'), 'Native business symbols retain neutral short-press styling without continuous decoration'); checks++
const nativeInventory = fs.readFileSync(path.join(directory, 'CoopStoreInventory.js'), 'utf8')
assert.match(nativeInventory, /auto:\s*(?:!0|true)\b/, 'Native stock pager must retain mounted automatic loading rather than rely on an early parent ref'); checks++
const fieldTemplate = fs.readFileSync(path.join(directory, 'CoopBusinessField.wxml'), 'utf8')
assert.match(fieldTemplate, /<textarea\b[^>]*\bvalue="\{\{[\w$]+\}\}"[^>]*\bmaxlength="\{\{300\}\}"[^>]*\bbindinput="\{\{[\w$]+\}\}"/, 'Native address keeps a bound value/input event and the 300-character limit'); checks++
assert.ok(!/Array\.from|\.length\s*\}\}/.test(fieldTemplate), 'Unicode address count is computed in script, not a global expression in WXML'); checks++
const topbarScript = fs.readFileSync(path.join(output, 'components/top-tabbar/top-tabbar.js'), 'utf8')
for (const option of ['titleFontSize', 'titleFontWeight', 'titleLineHeight']) { assert.ok(topbarScript.includes(option), `Native DIY head retains optional ${option} support`); checks++ }
// Execute the callback actually bound in generated WXML. Source/setup tests
// alone miss uni-app dropping a duplicate v-model + update listener.
const catalogDirectory = path.join(output, 'addon/hsx_lead/pages/catalog')
const catalogWxml = fs.readFileSync(path.join(catalogDirectory, 'index.wxml'), 'utf8')
const pickerTag = catalogWxml.match(/<model-catalog-picker\b[^>]*>/)?.[0] || ''
const updateBindings = [...pickerTag.matchAll(/bind(?::)?updateModelValue="\{\{([\w$]+)\}\}"/g)]
assert.equal(updateBindings.length, 1, 'Native catalog must bind exactly one update handler'); checks++
const returned = [], transitions = []
let nativeCatalogPage, loadCatalog, initializeCatalog
const eventChannel = { on(event, callback) { if (event === 'initialize') initializeCatalog = callback }, emit(event, payload) { returned.push([event, payload]); transitions.push(event) } }
const vendor = {
    defineComponent: component => component, getCurrentInstance: () => ({ proxy: { getOpenerEventChannel: () => eventChannel } }),
    ref: value => ({ value }), onLoad: callback => { loadCatalog = callback },
    e: (...values) => Object.assign({}, ...values), o: callback => callback, p: value => value, s: value => value, unref: value => value,
    _export_sfc: component => component,
}
vm.runInNewContext(fs.readFileSync(path.join(catalogDirectory, 'index.js'), 'utf8'), {
    require: dependency => {
        if (dependency.endsWith('/common/vendor.js')) return vendor
        if (dependency.endsWith('/utils/cooperation-navigation.js')) return { backCooperationTask: () => transitions.push('back') }
        throw Error('Unexpected native catalog dependency: ' + dependency)
    },
    wx: { createPage: component => { nativeCatalogPage = component } },
}, { timeout: 1000 })
const renderCatalog = nativeCatalogPage.setup({}, { expose() {} })
loadCatalog()
assert.equal(typeof initializeCatalog, 'function', 'Native page receives its opener channel'); checks++
initializeCatalog({ business_type: 'purchase', max: 5, items: [{ id: 'previous', title: '原选择' }] })
const nativeFrame = renderCatalog({ themeColor: () => '' }, {})
const nativeUpdate = nativeFrame[updateBindings[0][1]]
assert.equal(typeof nativeUpdate, 'function', 'WXML update binding resolves to an executable callback'); checks++
const selectedRows = [{ id: 'model_a', title: '合成型号甲' }, { id: 'model_b', title: '合成型号乙' }]
nativeUpdate(selectedRows)
assert.equal(JSON.stringify(returned), JSON.stringify([['selected', selectedRows]]), 'Bound native update must return selected models to the publishing form, not only update local v-model'); checks++
const closeBinding = pickerTag.match(/bindclose="\{\{([\w$]+)\}\}"/)?.[1]
assert.equal(typeof nativeFrame[closeBinding], 'function', 'Native close binding retains page-stack return'); checks++
nativeFrame[closeBinding]()
assert.deepEqual(transitions, ['selected', 'back'], 'Choices reach the opener before navigating back'); checks++
const beforeCancel = returned.length
nativeFrame[closeBinding]()
assert.equal(returned.length, beforeCancel, 'Cancelling/returning alone must not overwrite the original choices'); checks++
nativeUpdate([])
assert.equal(JSON.stringify(returned.at(-1)), JSON.stringify(['selected', []]), 'Explicitly clearing all choices returns an empty selection for required validation'); checks++
const pages = [...(app.pages || []), ...(app.subPackages || app.subpackages || []).flatMap(group => group.pages.map(page => group.root.replace(/\/$/, '') + '/' + page))]
const originalMerchantPages=['pages/index','pages/workbench/index','pages/stores/index','pages/wallet/index','pages/profile/index','pages/invite/index','pages/task/index','pages/catalog/index','pages/entity/index','pages/membership/index','pages/orders/index']
assert.deepEqual(pages.filter(value=>value.startsWith('addon/hsx_lead/')).sort(),[...originalMerchantPages,'pages/notifications/index'].map(route=>'addon/hsx_lead/'+route).sort(),'All original eleven merchant/public invitation pages plus the new notification page must be registered exactly once');checks++
for (const route of ['entity', 'membership', 'orders', 'invite', 'notifications']) {
    assert.ok(pages.includes(`addon/hsx_lead/pages/${route}/index`), `Native ${route} is a real navigable page`); checks++
    assert.ok(fs.readFileSync(path.join(output, `addon/hsx_lead/pages/${route}/index.wxss`), 'utf8').length > 0, `Native ${route} emits page-local styles`); checks++
}
const nativeRoutes={exports:{}}
vm.runInNewContext(fs.readFileSync(path.join(output,'utils/pages.js'),'utf8'),{exports:nativeRoutes.exports,module:nativeRoutes},{timeout:1000})
const protectedRoutes=nativeRoutes.exports.getNeedLoginPages().filter(route=>route.startsWith('/addon/hsx_lead/'))
assert.deepEqual([...protectedRoutes].sort(),['task','catalog','entity','membership','orders','wallet','notifications'].map(route=>'/addon/hsx_lead/pages/'+route+'/index').sort(),'Actual framework login registry preserves every original protected merchant page and protects notification preferences');checks++
for(const route of ['index','invite/index','workbench/index','stores/index','profile/index']){assert.ok(!protectedRoutes.includes('/addon/hsx_lead/pages/'+route),'Public merchant entry remains outside forced authentication: '+route);checks++}
const notificationPath=path.join(output,'addon/hsx_lead/pages/notifications/index'),notificationCss=fs.readFileSync(notificationPath+'.wxss','utf8'),notificationWxml=fs.readFileSync(notificationPath+'.wxml','utf8'),notificationScript=fs.readFileSync(notificationPath+'.js','utf8'),notificationConfig=JSON.parse(fs.readFileSync(notificationPath+'.json','utf8'))
assert.equal(notificationConfig.navigationBarTitleText,'通知偏好','Notification page has its own real native title');checks++
assert.equal(notificationConfig.navigationStyle,'custom','Notification page reuses the custom native header');checks++
for(const tag of ['cooperation-nav','coop-state','u-switch','u-button']){assert.ok(notificationConfig.usingComponents?.[tag],'Notification preferences resolve actual registered '+tag);checks++}
const notificationScope=notificationScript.match(/"__scopeId","(data-v-[a-f\d]+)"/)?.[1]
assert.ok(notificationScope,'Notification preferences retain their own compiled scoped stylesheet');checks++
for(const className of ['account-notice-page','account-notice-scroll','account-notice-content','account-notice-heading','account-notice-card','account-notice-channel','account-notice-error','account-notice-footer']){assert.ok(notificationWxml.includes(className)&&notificationCss.includes('.'+className+'.'+notificationScope),'Native notification page renders and locally styles '+className);checks++}
assert.match(notificationWxml,/<scroll-view\b[^>]*scroll-y[^>]*class="account-notice-scroll/,'Notification preferences retain native body scrolling below the fixed header');checks++
assert.match(notificationWxml,/<button\b[^>]*bindtap="\{\{event\.[\w$]+\}\}"[^>]*>订阅此项<\/button>/,'Each native notification scene has its own user-initiated subscribe binding');checks++
for(const name of ['CoopTaskDetail','CoopLeadCard']){const config=JSON.parse(fs.readFileSync(path.join(directory,name+'.json'),'utf8'));assert.ok(config.usingComponents?.['coop-response-deadline']?.endsWith('/CoopResponseDeadline')||config.usingComponents?.['coop-response-deadline']==='./CoopResponseDeadline','Native '+name+' reuses the same deadline component');checks++}
for(const relative of ['components/cooperation/CoopCommissionForm','pages/membership/index','pages/orders/index']){
 const base=path.join(output,'addon/hsx_lead',relative),config=JSON.parse(fs.readFileSync(base+'.json','utf8')),wxml=fs.readFileSync(base+'.wxml','utf8')
 assert.ok(config.usingComponents?.['coop-payment-help']?.endsWith('/CoopPaymentHelp'),'Native '+relative+' resolves the shared payment help component');checks++
 assert.equal((wxml.match(/<coop-payment-help\b/g)||[]).length,1,'Native '+relative+' mounts help once, outside repeated payment rows');checks++
}
const paymentHelpTemplate=fs.readFileSync(path.join(directory,'CoopPaymentHelp.wxml'),'utf8'),paymentHelpOptions=JSON.parse(fs.readFileSync(path.join(directory,'CoopPaymentHelp.json'),'utf8'))
assert.match(paymentHelpTemplate,/<scroll-view\b[^>]*scroll-y[^>]*class="coop-payment-help__body/,'Native help retains an independent scroll body');checks++
assert.ok(paymentHelpOptions.usingComponents?.['u-popup']?.endsWith('/u-popup/u-popup'),'Native help uses the installed popup, not a replacement cashier');checks++
// Parse actual generated WXSS with WeChat's own compiler, not just PostCSS.
const wxssCompiler=process.env.WXSS_COMPILER||'/Applications/wechatwebdevtools.app/Contents/Resources/app.asar.unpacked/node_modules/wcc-exec/wcsc'
assert.ok(fs.existsSync(wxssCompiler),'A real local WeChat WXSS compiler is required; native grammar validation cannot silently skip');checks++
// Every emitted plugin stylesheet (including imported CSS and app.wxss) must pass.
// A small hand-picked subset missed a bare :first-child added to the SMS row.
const wxssResult=spawnSync(process.execPath,[path.join(plugin,'scripts/check-wxss.mjs'),'--dir',output],{
 encoding:'utf8',timeout:300000,maxBuffer:1024*1024,env:{...process.env,WXSS_COMPILER:wxssCompiler}
})
assert.ok(!wxssResult.error&&wxssResult.status===0,'Real WeChat WXSS grammar accepts every plugin stylesheet: '+(wxssResult.error?.message||wxssResult.stderr||wxssResult.stdout||'').slice(0,5000));checks++
const wxssReport=JSON.parse(wxssResult.stdout.trim())
assert.ok(wxssReport.passed===true&&wxssReport.wxss_files>Object.keys(expectations).length,'Native grammar gate covers more than the selected key components');checks++
const nativeNoticeCalls=[],nativeNoticeReports=[],nativeNoticeEvents=['account_balance_changed','account_money_changed','account_commission_changed','cooperation_acceptance_reminder','cooperation_response_overdue','cooperation_assignment_accepted'].map((event_key,index)=>({event_key,name:'合成通知'+index,channels:['weapp','wechat','sms'].map(channel=>({channel,enabled:true,configured:true,authorization_required:channel==='weapp',...(channel==='weapp'?{template_id:'SYNTHETIC_NATIVE_TEMPLATE_'+index}:{})}))}))
const actualVue=require('vue'),noticeVendor={...actualVue,index:{getStorageSync:()=>'',requestSubscribeMessage:options=>nativeNoticeCalls.push(options)}},nativeNoticeHook={exports:{}}
vm.runInNewContext(fs.readFileSync(path.join(output,'addon/hsx_lead/composables/useAccountNoticePreferences.js'),'utf8'),{exports:nativeNoticeHook.exports,module:nativeNoticeHook,require:dependency=>{if(dependency.endsWith('/common/vendor.js'))return noticeVendor;if(dependency.endsWith('/utils/common.js'))return{getSiteId:()=>100005,getToken:()=> 'synthetic-native-member'};if(dependency.endsWith('/api/notice.js'))return{getAccountNoticePreferences:async()=>({data:{events:nativeNoticeEvents}}),reportAccountNoticeAuthorization:async keys=>nativeNoticeReports.push([...keys])};if(dependency.endsWith('/api/cooperation.js'))return{coopError:(e,f)=>e?.message||f};throw Error('Unexpected native notice dependency '+dependency)}},{timeout:1000})
const compiledPreferences=nativeNoticeHook.exports.useAccountNoticePreferences();await compiledPreferences.load()
assert.equal(compiledPreferences.events.value.length,6,'Actual compiled preference controller retains all six scenes rather than a fixed three-scene whitelist');checks++
compiledPreferences.subscribe(nativeNoticeEvents[5].event_key);assert.deepEqual([...nativeNoticeCalls[0].tmplIds],['SYNTHETIC_NATIVE_TEMPLATE_5'],'Actual compiled controller requests only the explicitly selected last scene');checks++
await nativeNoticeCalls[0].success({SYNTHETIC_NATIVE_TEMPLATE_5:'accept'});assert.equal(JSON.stringify(nativeNoticeReports),JSON.stringify([[nativeNoticeEvents[5].event_key]]),'Only accepted native template wakes its specific event');checks++
const nativeNoticeStart=nativeNoticeCalls.length;compiledPreferences.subscribe();await nativeNoticeCalls.at(-1).success(Object.fromEntries(nativeNoticeCalls.at(-1).tmplIds.map(id=>[id,'reject'])));compiledPreferences.subscribe();assert.equal(nativeNoticeCalls.length,nativeNoticeStart+2,'Native grouped subscription advances only on a second explicit user action');checks++
assert.deepEqual([...nativeNoticeCalls.at(-1).tmplIds],['SYNTHETIC_NATIVE_TEMPLATE_3','SYNTHETIC_NATIVE_TEMPLATE_4','SYNTHETIC_NATIVE_TEMPLATE_5'],'Actual compiled grouped subscription reaches later cooperation scenes');checks++
assert.ok(nativeNoticeCalls.every(call=>call.tmplIds.length<=3),'Every actual compiled native consent call has at most three template IDs');checks++
compiledPreferences.dispose()
for (const route of ['workbench', 'stores']) {
    const config = JSON.parse(fs.readFileSync(path.join(output, `addon/hsx_lead/pages/${route}/index.json`), 'utf8'))
    assert.equal(config.enablePullDownRefresh, false, `${route}: z-paging owns refresh, not a second native page handler`); checks++
    assert.equal(config.navigationBarTitleText, route === 'stores' ? '找门店' : '找客户', `${route}: native title must identify this bottom-navigation role`); checks++
}
const group = JSON.parse(fs.readFileSync(path.join(output, 'addon/components/diy/group/index.json'), 'utf8'))
assert.ok(group.usingComponents?.['diy-lead-business-navigation'], 'Actual framework DIY group discovers the independently configurable business navigation'); checks++
for (const tag of ['diy-lead-role-entry', 'diy-lead-cooperation-hero']) {
    assert.ok(group.usingComponents?.[tag], 'Actual framework DIY group discovers the independently configurable ' + tag); checks++
}
const roleWrapper = JSON.parse(fs.readFileSync(path.join(output, 'addon/hsx_lead/components/diy/lead-role-entry/index.json'), 'utf8'))
assert.ok(roleWrapper.usingComponents?.['coop-role-entry-card'], 'Independent role wrapper reuses the same renderer as the optional combined block'); checks++
const heroCss = fs.readFileSync(path.join(output, 'addon/hsx_lead/components/diy/lead-cooperation-hero/index.wxss'), 'utf8')
assert.ok(heroCss.includes('lead-cooperation-hero__accent') && heroCss.includes('--lead-diy-title'), 'Independent hero emits configurable heading and accent styles'); checks++
const nativeBusinessWrapper = JSON.parse(fs.readFileSync(path.join(output, 'addon/hsx_lead/components/diy/lead-business-navigation/index.json'), 'utf8'))
assert.ok(nativeBusinessWrapper.usingComponents?.['coop-business-navigation'], 'Business decoration is a thin wrapper over the shared runtime navigation'); checks++
assert.ok(fs.readFileSync(path.join(output, 'addon/hsx_lead/components/diy/lead-business-navigation/index.wxml'), 'utf8').includes('<coop-business-navigation'), 'The new business wrapper emits an actual native component tag, not only a registration'); checks++
const nativeBusinessCache = { exports: {} }
const nativeBusinessCacheScript = fs.readFileSync(path.join(output, 'addon/hsx_lead/utils/cooperation-business-cache.js'), 'utf8')
assert.ok(!/\btype\s+(?:BusinessEntry|BusinessReader)\s*=/.test(nativeBusinessCacheScript), 'Business cache type aliases are compiled away, never emitted into native JavaScript'); checks++
vm.runInNewContext(nativeBusinessCacheScript, { exports: nativeBusinessCache.exports, module: nativeBusinessCache }, { timeout: 1000 })
const { readCooperationBusinessEntries: readNativeBusinesses, clearCooperationBusinessCache: clearNativeBusinesses } = nativeBusinessCache.exports
assert.equal(typeof readNativeBusinesses, 'function', 'The actual package exports the named shared business reader'); checks++
assert.equal(typeof clearNativeBusinesses, 'function', 'The actual package exports explicit identity-cache invalidation'); checks++
let businessReads = 0
const businessReader = () => { businessReads++; return Promise.resolve({ data: { config: { businesses: [{ key: 'purchase', name: '二手机', enabled: true }, { key: 'hidden', name: '停用业务', enabled: false }] } } }) }
const [nativeBusinessesA, nativeBusinessesB] = await Promise.all([readNativeBusinesses('synthetic-site:member-a', businessReader), readNativeBusinesses('synthetic-site:member-a', businessReader)])
assert.equal(businessReads, 1, 'Two native homepage blocks coalesce the same identity request'); checks++
assert.equal(JSON.stringify(nativeBusinessesA), JSON.stringify([{ key: 'purchase', name: '二手机' }]), 'Native projection exposes only actual enabled business names and stable keys'); checks++
nativeBusinessesA[0].name = 'Mutated local copy'
assert.equal(nativeBusinessesB[0].name, '二手机', 'Native consumers do not mutate one another through cached row references'); checks++
clearNativeBusinesses()
const nativeBusinessesOther = await readNativeBusinesses('synthetic-site:member-b', () => Promise.resolve({ data: { config: { businesses: [] } } }))
assert.equal(nativeBusinessesOther.length, 0, 'A different identity cannot see a previous merchant business cache'); checks++
for (const name of ['identity', 'tools', 'benefits', 'promotion']) {
    assert.ok(group.usingComponents?.['diy-lead-profile-' + name], `framework group registers personal-center ${name}`); checks++
    assert.ok(fs.existsSync(path.join(output, 'addon/hsx_lead/components/diy/lead-profile-' + name, 'index.wxml')), `personal-center ${name} renderer is compiled`); checks++
}
const memberPage = JSON.parse(fs.readFileSync(path.join(output, 'addon/hsx_lead/pages/profile/index.json'), 'utf8'))
assert.ok(memberPage.usingComponents?.['diy-group'], 'personal center uses the framework DIY group'); checks++
// Exercise the callback bound by the actual native membership entry. The old
// ordinary/reliable marketing card is intentionally tree-shaken, not kept alive
// for an obsolete style expectation.
const entryWxml = fs.readFileSync(path.join(directory, 'CoopMembershipEntry.wxml'), 'utf8')
const entryClick = entryWxml.match(/<view\b[^>]*\bclass="[^"]*entity-membership-entry[^>]*\bbindtap="\{\{([\w$]+)\}\}"/)?.[1]
assert.ok(entryClick, 'Native entity membership card exposes one bound entry action'); checks++
let nativeEntry
const entryEvents = [], entryVendor = {
    defineComponent: value => value, computed: getter => ({ get value() { return getter() } }),
    unref: value => value && typeof value === 'object' && 'value' in value ? value.value : value,
    e: (...values) => Object.assign({}, ...values), s: value => value,
    t: value => String(value ?? ''), p: value => value, o: callback => callback, _export_sfc: value => value,
}
const entrySideEffects = ['utils/pages.js', 'app/stores/diy.js', 'hooks/useLogin.js', 'app/api/auth.js', 'utils/request.js', 'stores/member.js', 'app/api/member.js', 'locale/index.js', 'locale/i18n.js', 'locale/language.js', 'stores/system.js', 'app/api/system.js', 'stores/config.js']
const nativeAppearance = { exports: {} }
vm.runInNewContext(fs.readFileSync(path.join(output, 'addon/hsx_lead/utils/cooperation-diy-style.js'), 'utf8'), { exports: nativeAppearance.exports, module: nativeAppearance }, { timeout: 1000 })
assert.equal(typeof nativeAppearance.exports.cooperationDiyVars, 'function', 'Native membership entry uses the compiled shared presentation helper'); checks++
vm.runInNewContext(fs.readFileSync(path.join(directory, 'CoopMembershipEntry.js'), 'utf8'), {
    require: dependency => {
        if (dependency.endsWith('/common/vendor.js')) return entryVendor
        if (dependency.endsWith('/utils/entity-membership.js')) return {
            entityExpiry: () => '2030/1/1', membershipState: value => value.active ? 'active' : 'locked', entityAccounts: value => `${value.account_limit} 人`, membershipBadgeRank: value => Number(value.display_rank) || 0,
        }
        if (dependency.endsWith('/utils/common.js')) return { img: value => value }
        if (dependency.endsWith('/utils/cooperation-diy-style.js')) return nativeAppearance.exports
        // The framework common URL helper has these generated side-effect
        // registrations. Never execute its HTTP/auth modules in an artifact check.
        if (entrySideEffects.some(relative => dependency.endsWith('/' + relative))) return {}
        throw Error('Unexpected native membership entry dependency: ' + dependency)
    },
    wx: { createComponent: component => { nativeEntry = component } },
}, { timeout: 1000 })
const entryRender = nativeEntry.setup({ profile: { membership: { level_code: 'ML702', level_name: '等级2', display_rank: 2, active: true, expires_at: 1900000000, account_limit: 2, used_accounts: 1, can_manage: true } }, embedded: false, appearance: {} })
const entryFrame = entryRender({ $emit: (...args) => entryEvents.push(args) }, {})
assert.equal(typeof entryFrame[entryClick], 'function', 'Native entry WXML points to an executable compiled handler'); checks++
entryFrame[entryClick]({})
assert.deepEqual(entryEvents, [['open']], 'Native card only opens the membership page; it does not purchase or change rights'); checks++
const nativeProfile = JSON.parse(fs.readFileSync(path.join(directory, 'CoopProfilePanel.json'), 'utf8'))
assert.ok(nativeProfile.usingComponents?.['coop-membership-entry'], 'The actual profile renderer mounts the new entity membership entry'); checks++
const nativeMemberConfig = JSON.parse(fs.readFileSync(path.join(output, 'addon/hsx_lead/pages/membership/index.json'), 'utf8'))
assert.ok(nativeMemberConfig.usingComponents?.pay && nativeMemberConfig.usingComponents?.['coop-task-sheet'] && nativeMemberConfig.usingComponents?.['coop-level-badge'], 'Membership uses the framework cashier and existing plugin page/badge components'); checks++
assert.ok(nativeMemberConfig.usingComponents.pay.endsWith('/components/pay/pay'), 'The native pay tag resolves to the real framework cashier, never a same-named setup action'); checks++
assert.ok(nativeMemberConfig.usingComponents?.['coop-experience-progress'], 'Native membership mounts the shared subject-experience presentation'); checks++
const nativeExperience = JSON.parse(fs.readFileSync(path.join(directory, 'CoopExperienceProgress.json'), 'utf8'))
assert.ok(nativeExperience.usingComponents?.['u-line-progress']?.endsWith('/u-line-progress/u-line-progress'), 'Growth progress resolves the installed uview progress bar'); checks++
const nativeMemberWxml = fs.readFileSync(path.join(output, 'addon/hsx_lead/pages/membership/index.wxml'), 'utf8')
// H5 transparent component roots do not imply native transparency. Capture the
// actual compiled component options, without executing setup/auth or requests.
const viewportComponents=['CoopTaskSheet','CoopPublishForm','CoopStorePicker','CoopTaskDetail','CoopCommissionForm','CoopConnectionPanel','CoopPromotionPanel','ModelCatalogPicker','CoopStoreInventory','CoopProfilePanel']
let nativeTaskSheet
for(const name of viewportComponents){let captured;vm.runInNewContext(fs.readFileSync(path.join(directory,name+'.js'),'utf8'),{require:dependency=>dependency.endsWith('/common/vendor.js')?{defineComponent:value=>value,_export_sfc:value=>value}: {},wx:{createComponent:value=>{captured=value}},exports:{}},{timeout:1000});assert.equal(captured?.options?.virtualHost,true,name+': actual WeChat component removes its extra auto-height native host');checks++;if(name==='CoopTaskSheet')nativeTaskSheet=captured}
const nativeSheetWxml=fs.readFileSync(path.join(directory,'CoopTaskSheet.wxml'),'utf8')
assert.ok(nativeSheetWxml.includes('<slot />')&&nativeSheetWxml.includes('<slot name="footer" />'),'Native shared sheet preserves default content and named footer slots; layout must not be “fixed” by removing either');checks++
assert.ok(nativeMemberWxml.includes('membership-current')&&nativeMemberWxml.includes('membership-content')&&nativeMemberWxml.includes('slot="footer"'),'Native membership retains its real ready-state body and footer together');checks++
assert.ok(/<swiper\b[^>]*\bbindchange="\{\{[\w$]+\}\}"/.test(nativeMemberWxml), 'Member level cards retain their native swipe handler'); checks++
assert.ok(/<pay\b[^>]*\bbindconfirm="\{\{[\w$]+\}\}"/.test(nativeMemberWxml), 'Cashier completion retains a native result-verification binding'); checks++
const nativeTeamWxml = fs.readFileSync(path.join(output, 'addon/hsx_lead/pages/entity/index.wxml'), 'utf8')
assert.ok(/<button\b[^>]*class="[^"]*entity-copy[^>]*bindtap="\{\{[\w$]+\}\}"/.test(nativeTeamWxml), 'Copy subject code is an actionable native button'); checks++
assert.ok(/<button\b[^>]*class="[^"]*entity-share[^>]*open-type="share"/.test(nativeTeamWxml), 'Staff invitation uses native share instead of an inert simulated link'); checks++
const nativePosterConfig = JSON.parse(fs.readFileSync(path.join(directory, 'CoopInvitePoster.json'), 'utf8'))
for (const tag of ['u-popup', 'u-loading-icon', 'u-button', 'u-icon']) {
    assert.ok(nativePosterConfig.usingComponents?.[tag], `Poster resolves the real framework ${tag} component`); checks++
}
const nativePosterWxml = fs.readFileSync(path.join(directory, 'CoopInvitePoster.wxml'), 'utf8')
assert.ok(/<u-popup\b[^>]*bindclose="\{\{[\w$]+\}\}"/.test(nativePosterWxml), 'Native poster overlay close resolves to a bound handler'); checks++
assert.ok(/<image\b[^>]*data-source="\{\{[\w$]+\}\}"[^>]*bindload="\{\{[\w$]+\}\}"[^>]*binderror="\{\{[\w$]+\}\}"/.test(nativePosterWxml), 'Native poster image keeps its source-bound load/error events'); checks++
assert.ok(/<button\b[^>]*open-type="openSetting"[^>]*bindopensetting="\{\{[\w$]+\}\}"/.test(nativePosterWxml), 'Native album permission recovery uses the official openSetting button'); checks++
assert.ok(nativePosterWxml.includes('保存到相册') || fs.readFileSync(path.join(directory, 'CoopInvitePoster.js'), 'utf8').includes('保存到相册'), 'Native poster has its album-save primary action'); checks++
const nativePosterController = fs.readFileSync(path.join(output, 'addon/hsx_lead/composables/useCooperationPoster.js'), 'utf8')
for (const method of ['downloadFile', 'saveImageToPhotosAlbum', 'previewImage']) {
    assert.ok(nativePosterController.includes(method), `Native poster controller retains official ${method} API`); checks++
}
for (const route of ['entity', 'profile']) {
    const pageJs = fs.readFileSync(path.join(output, `addon/hsx_lead/pages/${route}/index.js`), 'utf8')
    assert.ok(pageJs.includes('utils/cooperation-invite.js'), `Native ${route} page includes the framework QR-scene bridge`); checks++
}
const nativeInvite = fs.readFileSync(path.join(output, 'addon/hsx_lead/utils/cooperation-invite.js'), 'utf8')
assert.ok(nativeInvite.includes('handleOnloadParams') && nativeInvite.includes('promotion_invite'), 'Native QR bridge retains framework parsing and the short promotion flag mapping'); checks++
console.log(JSON.stringify({ passed: true, checks, components, wxss_compiler:'WeChat wcsc (required, not skipped)', scope: 'Actual mp-weixin output, scoped style/slot ownership, real WeChat WXSS grammar and generated preference controller with synthetic callbacks. No live APIs/consent prompts; temporary compiler output only, not real-device visual acceptance.' }))
