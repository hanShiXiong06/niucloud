import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Render the actual preview template with harmless UI stubs; no app/server/network calls.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue')
const { compile } = require('@vue/compiler-dom')
const { renderToString } = require('@vue/server-renderer')
const source = fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin/views/components/ConnectionWizard.vue'), 'utf8')
let checks = 0
const check = (condition, description) => { assert.ok(condition, description); checks++ }
check(!/wanji|soujike|jixiaoer|aiguanji/.test(source), 'notice logic remains generic rather than provider-specific')
const previewTemplate = source.match(/<template v-if="step === 2">([\s\S]+?)\n            <\/template>/)?.[1]
check(!!previewTemplate, 'actual preview section is available for rendered branch checks')
const render = new Function('Vue', compile(previewTemplate, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
const passthrough = { setup: (_, { slots }) => () => Vue.h('div', slots.default?.()) }
const alert = { props: ['title', 'type'], setup: (props) => () => Vue.h('section', { 'data-alert-type': props.type }, props.title) }
async function previewHtml(preview) {
    const app = Vue.createSSRApp({
        render,
        setup: () => ({
            schema: { name: '合成 ERP' }, preview, storeChanged: false, impact: {},
            needsStoreProbe: false, confirmed: false, original: {}, selectedStore: '',
            verificationBasis: '认证账号资料与首屏库存接口', probeExpires: 0, time: () => '合成时间'
        })
    })
    app.config.warnHandler = () => {}
    for (const component of ['el-descriptions', 'el-descriptions-item', 'el-table', 'el-table-column',
        'el-collapse', 'el-collapse-item', 'el-form', 'el-form-item', 'el-select', 'el-option', 'el-checkbox']) {
        app.component(component, passthrough)
    }
    app.component('el-alert', alert)
    return renderToString(app)
}

const verified = await previewHtml({ empty_inventory: true, can_activate: true, first_page_count: 0,
    identity: { name: '合成公司', basis: 'authenticated_profile_and_first_page' },
    note: '已核验身份，当前查询范围没有库存。' })
check(verified.includes('data-alert-type="info"') && verified.includes('来源身份已核验'), 'verified empty profile has a plain informational notice')
check(verified.includes('可以先启用接入') && !verified.includes('因此暂不能启用'), 'verified empty list is not falsely blocked by UI copy')
check(verified.includes('已核验身份，当前查询范围没有库存。') && verified.includes('核验依据'), 'server scope note and human-readable basis are retained')
check(!verified.includes('authenticated_profile_and_first_page'), 'raw technical basis key is not shown to users')

const unverified = await previewHtml({ empty_inventory: true, can_activate: false, first_page_count: 0,
    identity: { basis: 'first_page_read_only_list' } })
check(unverified.includes('data-alert-type="warning"') && unverified.includes('因此暂不能启用'), 'unverified empty list still explains why activation is unavailable')
check(!unverified.includes('可以先启用接入') && !unverified.includes('我已核对 ERP'), 'unverified result does not show the affirmative empty notice or activation confirmation')

const populated = await previewHtml({ empty_inventory: false, can_activate: true, first_page_count: 1,
    identity: { name: '合成公司' }, items: [{ title: '合成设备', store_id: '42' }] })
check(!populated.includes('来源身份已核验，当前查询范围暂无商品资料') && !populated.includes('因此暂不能启用'), 'nonempty list does not show either empty-state notice')
const collapse = previewTemplate.match(/<el-collapse\b([^>]*)>/)?.[1] || ''
check(collapse.includes('preview.note || preview.identity?.basis') && !/v-model|model-value/.test(collapse), 'optional scope explanation starts collapsed rather than forcing expanded content')

const ruleStage = await previewHtml({ empty_inventory: false, can_activate: true, first_page_count: 0, has_more: true,
    identity: { name: '合成门店', basis: 'authenticated_profile_and_first_page' } })
check(ruleStage.includes('正在核对上架范围') && !ruleStage.includes('暂无商品资料'), 'preflight rule phase is not shown as zero inventory')
const unverifiedStage = await previewHtml({ empty_inventory: false, can_activate: false, first_page_count: 0, has_more: true,
    identity: { basis: 'first_page_read_only_list' } })
check(!unverifiedStage.includes('来源身份已核验') && !unverifiedStage.includes('我已核对 ERP'), 'unverified intermediate result never claims authenticated identity or allows confirmation')

console.log(`PASS ${checks} connection-empty UI checks; actual Vue template rendered with local stubs, no network/database or visual verification`)
