import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
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
    CooperationWorkbench: ['hsx-cooperation', 'coop-delivery-controls', 'coop-main-scroll', 'coop-framework-tabbar', 'coop-framework-tabbar-mount', 'coop-work-overview'],
    CoopPublishForm: ['coop-form-section', 'coop-section-title', 'coop-form-row', 'coop-form-column', 'coop-type-switch', 'coop-search-trigger', 'coop-consent', 'coop-form-error'],
    CoopCommissionForm: ['coop-form-section', 'coop-amount-input', 'coop-amount-presets', 'coop-detail-shop', 'coop-muted', 'coop-form-error'],
    CoopProfilePanel: ['coop-profile-card', 'coop-profile-title', 'coop-profile-name', 'coop-profile-badges', 'coop-menu-row', 'coop-profile-buy', 'coop-form-section', 'coop-form-column', 'coop-picker-field', 'coop-profile-tools'],
    CoopWalletPanel: ['coop-balance-card', 'coop-balance', 'coop-withdraw-entry', 'coop-income-summary', 'coop-wallet-entry', 'coop-menu-row', 'coop-loadmore'],
    CoopTaskDetail: ['coop-form-section', 'coop-customer-contact', 'coop-state-heading', 'coop-history-item', 'coop-footer-actions', 'coop-secondary', 'coop-primary'],
    CoopConnectionPanel: ['coop-form-section', 'coop-form-row', 'coop-form-column', 'coop-detail-shop', 'coop-sync-settings', 'coop-small-actions', 'coop-captcha-row', 'coop-consent'],
    CoopTaskSheet: ['coop-sheet', 'coop-sheet__head', 'coop-sheet__body', 'coop-sheet__footer', 'coop-icon-button'],
    CoopStorePicker: ['coop-store-picker', 'coop-region-search', 'coop-customer-location', 'coop-footer-note'],
    CoopStoreInventory: ['coop-stock-browser', 'coop-stock-row', 'coop-stock-more'],
    CooperationNav: ['cooperation-nav', 'cooperation-nav__heading', 'cooperation-nav__title'],
    CooperationSearch: ['coop-search-field'],
    ModelCatalogPicker: ['catalog-picker', 'catalog-columns', 'catalog-footer'],
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
    components.push({ name, scope, wxss_bytes: Buffer.byteLength(css), required_classes: classes.length })
}
const app = JSON.parse(fs.readFileSync(path.join(output, 'app.json'), 'utf8'))
const pages = [...(app.pages || []), ...(app.subPackages || app.subpackages || []).flatMap(group => group.pages.map(page => group.root.replace(/\/$/, '') + '/' + page))]
assert.equal(pages.filter(value => value.startsWith('addon/hsx_lead/')).length, 4, 'All four merchant pages must be registered in the app or plugin subpackage'); checks++
console.log(JSON.stringify({ passed: true, checks, components, scope: 'Actual mp-weixin scoped WXSS and slot caller ownership; read-only local artifact check, not real-device visual acceptance' }))
