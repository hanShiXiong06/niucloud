import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Compile each real mobile component independently. H5 descendant selectors can
// conceal missing component-local rules that do not cross WeChat isolation.
// No installed frontend, browser, API, database or package build is required.
const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const directory = path.join(plugin, 'uni-app/components/cooperation')
const require = createRequire(path.resolve(plugin, '../../../uni-app/package.json'))
const dependencyStyles = path.resolve(plugin, '../../../uni-app/node_modules')
const compiler = require('@vue/compiler-sfc'), dom = require('@vue/compiler-dom')
const sass = require('sass'), postcss = require('postcss'), selectors = require('postcss-selector-parser')
const failures = []
let checks = 0, components = 0, styles = 0, hooks = 0
function check(condition, message) { checks++; if (!condition) failures.push(message) }
const businessClass = name => name.startsWith('coop-') || name === 'hsx-cooperation'

function templateHooks(template) {
    const classes = new Set()
    function visit(node) {
        if (node.type === 1) {
            for (const prop of node.props) {
                if (prop.type === 6 && prop.name === 'class') {
                    for (const name of (prop.value?.content || '').split(/\s+/)) if (businessClass(name)) classes.add(name)
                }
            }
        }
        for (const child of node.children || []) visit(child)
    }
    visit(dom.baseParse(template))
    return classes
}

function styleHooks(css, scope) {
    const all = new Set(), own = new Set()
    postcss.parse(css).walkRules(rule => {
        // Keyframes have no class selectors, and declarations in a different
        // nested rule cannot establish this rule's component ownership.
        if (!rule.nodes?.some(node => node.type === 'decl')) return
        selectors(root => root.walkClasses(node => {
            if (!businessClass(node.value)) return
            all.add(node.value)
            const siblings = node.parent.nodes, index = siblings.indexOf(node)
            let start = index, end = index
            while (start > 0 && siblings[start - 1].type !== 'combinator') start--
            while (end + 1 < siblings.length && siblings[end + 1].type !== 'combinator') end++
            if (siblings.slice(start, end + 1).some(part => part.type === 'attribute' && part.attribute === scope)) own.add(node.value)
        })).processSync(rule.selector)
    })
    return { all, own }
}

function compile(source, scope, filename = 'isolated.vue', scoped = true) {
    const result = compiler.compileStyle({ source, filename, id: scope, scoped })
    assert.deepEqual(result.errors, [], filename)
    return result.code
}

// Validate the detector itself, including the exact old false positive. A
// parent's scoped descendant rule is valid CSS but is not the child's style.
const fixtureScope = 'data-v-style-fixture'
check(!styleHooks(compile(':deep(.coop-form-section) { padding: 30rpx; }', fixtureScope), fixtureScope).own.has('coop-form-section'), 'detector rejects a parent scoped deep selector as child-local styling')
check(styleHooks(compile('.coop-form-section { padding: 30rpx; }', fixtureScope), fixtureScope).own.has('coop-form-section'), 'detector accepts a local selector scoped to the owning component')
check(!styleHooks(compile('.coop-form-section { padding: 30rpx; }', fixtureScope, 'global.vue', false), fixtureScope).own.has('coop-form-section'), 'detector does not accept global CSS as component-local ownership')
check(!styleHooks(compile('.coop-parent .coop-form-section { padding: 30rpx; }', fixtureScope), fixtureScope).own.has('coop-parent'), 'ancestor class is not mistaken for the actual declaration target')
check(!styleHooks(compile('.coop-form-section { padding: 30rpx; }', fixtureScope), 'data-v-other-component').own.has('coop-form-section'), 'a different component scope cannot satisfy local ownership')
check(templateHooks('<CoopTaskSheet><view class="coop-form-section"/><template #footer><view class="coop-footer-actions"/></template></CoopTaskSheet>').has('coop-footer-actions'), 'named-slot content is audited in its calling component, not the sheet component')

for (const name of fs.readdirSync(directory).filter(name => name.endsWith('.vue')).sort()) {
    const filename = path.join(directory, name), source = fs.readFileSync(filename, 'utf8')
    const parsed = compiler.parse(source, { filename }), descriptor = parsed.descriptor
    check(parsed.errors.length === 0, name + ': SFC parses successfully')
    const template = descriptor.template?.content || '', required = templateHooks(template)
    const scope = 'data-v-coop-style-' + path.basename(name, '.vue').toLowerCase()
    const css = []
    let validScopes = true, noBusinessDeep = true
    for (const style of descriptor.styles) {
        // uni-app prepends the project's uni.scss before every SFC. In this
        // project that imports uview's theme; a standalone @use can pass the
        // source check yet fail both real platform builds after this injection.
        const raw = style.lang === 'scss' ? sass.compileString("@import 'uview-plus/theme.scss';\n" + style.content, { loadPaths: [directory, dependencyStyles], logger: sass.Logger.silent }).css : style.content
        validScopes &&= style.scoped === true
        noBusinessDeep &&= !/:deep\s*\([^)]*\.(?:coop-|hsx-cooperation)/.test(raw)
        css.push(compile(raw, scope, filename, style.scoped))
        styles++
    }
    const rules = styleHooks(css.join('\n'), scope)
    const missing = [...required].filter(name => !rules.own.has(name))
    const foreign = [...rules.all].filter(name => !template.includes(name))
    check(validScopes && (required.size === 0 || descriptor.styles.length > 0), name + ': template styling must be declared in a local scoped style block')
    check(noBusinessDeep, name + ': business component classes must not rely on parent :deep selectors')
    check(missing.length === 0, name + ': missing owned rules for ' + missing.join(', '))
    check(foreign.length === 0, name + ': contains another component\'s styles or unused bulk CSS: ' + foreign.join(', '))
    check(!/styleIsolation\s*:\s*['"](?:shared|apply-shared)['"]|addGlobalClass\s*:\s*true/.test(descriptor.scriptSetup?.content || descriptor.script?.content || ''), name + ': do not bypass mini-program component isolation to conceal missing local styles')
    if (name === 'CooperationWorkbench.vue') {
        const mount = new Map()
        postcss.parse(css.join('\n')).walkRules(rule => { if (rule.selector.includes('.coop-framework-tabbar-mount')) for (const declaration of rule.nodes.filter(node => node.type === 'decl')) mount.set(declaration.prop, declaration.value) })
        check(mount.get('position') === 'fixed' && mount.get('height') === '0', 'framework internal duplicate spacers are mounted outside document flow')
        check(!mount.has('transform') && !mount.has('contain') && mount.get('overflow') !== 'hidden', 'framework fixed navigation is not clipped or reparented by a new containing block')
    }
    if (name === 'CoopProfilePanel.vue') {
        const actions = []
        function findActions(node) {
            if (node.type === 1 && node.props.some(prop => prop.type === 6 && prop.name === 'class' && prop.value?.content.split(/\s+/).includes('coop-profile-buy'))) actions.push(node)
            for (const child of node.children || []) findActions(child)
        }
        findActions(dom.baseParse(template))
        check(actions.length === 0 && /<CoopMembershipEntry\s/.test(template), 'profile replaces competing purchase rows with one component-owned entity membership entry')
        check(template.includes("shown('showMembership')") && template.includes(':profile="profile"'), 'consolidated entity entry respects existing DIY visibility flags and uses real subject rights')
        check(template.includes('@open="$emit(\'membership\')"') && !template.includes("$emit('purchase'"), 'profile entry is read-only native navigation, not a competing instant-purchase action')
    }
    hooks += required.size; components++
}
if (failures.length) {
    console.error(failures.map(message => 'FAIL ' + message).join('\n'))
    console.error(JSON.stringify({ passed: false, checks, failed: failures.length, components, styles, template_style_hooks: hooks }))
    process.exitCode = 1
} else {
    console.log(`PASS ${checks} mobile style ownership checks; ${components} independently compiled SFCs, ${styles} style blocks, ${hooks} template hooks. Component-local CSS and named-slot ownership, no copied parent stylesheet or live requests.`)
}
