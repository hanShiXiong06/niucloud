import assert from 'node:assert/strict'
import { mkdtemp, mkdir, readFile, writeFile, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import test from 'node:test'
import { compileTemplate, parse } from '@vue/compiler-sfc'
import prettier from 'prettier'
import { formatFiles } from './format.mjs'
import { formatVue, restoreTemplateWhitespace } from './vue-layout.mjs'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const options = { ...(await prettier.resolveConfig(join(root, 'admin/example.vue'))), filepath: 'example.vue' }
function render(sfc) {
    const { descriptor, errors } = parse(sfc)
    assert.deepEqual(errors, [])
    const { code, errors: templateErrors } = compileTemplate({ source: descriptor.template.content, filename: 'example.vue', id: 'example', isProd: true })
    assert.deepEqual(templateErrors, [])
    return code
}

// The error-message / retry-button footer appears in both original views.
for (const [page, prefix, fieldClass, handler] of [
    ['MerchantPanel', 'members', 'lead-error', 'loadMembers'],
    ['Reviews', 'lead', 'coop-danger-copy', 'loadLeads']
]) {
    test(`${page}: keep rendered space before retry, and preserve render output`, async () => {
        const source = `<template><el-select><template v-if="${prefix}Error" #footer><span class="${fieldClass}">{{ ${prefix}Error }}</span> <el-button link type="primary" :loading="${prefix}Loading" @click.stop="${handler}()">重新读取候选</el-button></template></el-select></template>`
        const removed = source.replace('</span> <el-button', '</span>\n    <el-button')
        assert.notEqual(render(removed), render(source))
        assert.equal(restoreTemplateWhitespace(source, removed, options.filepath), source)
        const output = await formatVue(source, options)
        assert.equal(render(output), render(source))
        assert.equal(await formatVue(output, options), output)
    })
}

test('interpolation closing layout keeps one physical line after a line comment, without touching script/style', () => {
    const source = '<template><span/> <span>{{ message // important\n   }}</span></template><script>const text="{{  }}"</script><style>/*   */</style>'
    const printed = source.replace('/> <span', '/>\n <span').replace('important\n', 'important\n\n\n')
    assert.equal(restoreTemplateWhitespace(source, printed, options.filepath), source)
})

test('multiline interpolation and multi-statement event remain intact, and a second format is identical', async () => {
    const source = `<template>
    <button @click="selectedLog=row;logVisible=true">{{ ${'field || '.repeat(22)}fallback }}</button>
</template>
<script setup>
const row={ content: 'script  spacing' }
</script>
<style scoped>
.demo  { color:   red; }
</style>
`
    const output = await formatVue(source, options)
    assert.ok(output.includes('selectedLog=row;logVisible=true'))
    assert.equal(render(output), render(source))
    assert.equal(await formatVue(output, options), output)
    const blocks = text => {
        const { descriptor } = parse(text)
        return [descriptor.scriptSetup.content, ...descriptor.styles.map(s => s.content)]
    }
    assert.deepEqual(blocks(output), blocks(source))
})

test('textarea attributes may wrap but preformatted content must not change', () => {
    const source = '<template><textarea title="x > y">  keep\n  me </textarea></template>'
    const attrsWrapped = source.replace(' title=', '\n title=')
    assert.equal(restoreTemplateWhitespace(source, attrsWrapped, options.filepath), attrsWrapped)
    assert.throws(() => restoreTemplateWhitespace(source, attrsWrapped.replace('  keep', ' keep'), options.filepath), /verbatim text changed/)
})

test('directory, quoted glob, ignores, non-mutating check, native TS/CSS, and settled write/check', async () => {
    const fixture = await mkdtemp(join(tmpdir(), 'hsx-formatter-'))
    try {
        const admin = join(fixture, 'admin')
        await mkdir(admin)
        for (const f of ['.prettierrc.json', '.prettierignore']) {
            await writeFile(join(fixture, f), await readFile(join(root, f)))
        }
        // The root resource/ is data; views/resource/ contains real admin pages.
        const page = join(admin, 'views', 'resource', 'inventory.vue')
        const asset = join(fixture, 'resource', 'inventory.vue')
        await mkdir(dirname(page), { recursive: true })
        await mkdir(dirname(asset), { recursive: true })
        for (const file of [page, asset]) await writeFile(file, '<template><i>inventory</i></template>')
        const ignore = { ignorePath: join(fixture, '.prettierignore') }
        assert.equal((await prettier.getFileInfo(page, ignore)).ignored, false)
        assert.equal((await prettier.getFileInfo(asset, ignore)).ignored, true)
        const data = { 'sample.vue': '<template><b>example</b></template>', 'sample.ts': 'const sample={a:1};', 'sample.css': '.x{ color:red; }', 'keep.min.css': '.x{color:red;}' }
        for (const [name, value] of Object.entries(data)) await writeFile(join(admin, name), value)
        const args = { root: fixture, cwd: fixture, report: () => {} }
        assert.deepEqual(await formatFiles('check', ['admin', 'resource'], args), { checked: 4, changed: 4 })
        assert.equal(await readFile(join(admin, 'sample.ts'), 'utf8'), data['sample.ts'])
        assert.deepEqual(await formatFiles('write', ['admin/**/*.{vue,ts,css}', 'admin/sample.ts'], args), { checked: 4, changed: 4 })
        for (const name of ['sample.ts', 'sample.css']) {
            const file = join(admin, name)
            assert.equal(await readFile(file, 'utf8'), await prettier.format(data[name], { ...(await prettier.resolveConfig(file)), filepath: file }))
        }
        assert.equal(await readFile(join(admin, 'keep.min.css'), 'utf8'), data['keep.min.css'])
        assert.equal(await readFile(asset, 'utf8'), '<template><i>inventory</i></template>')
        assert.deepEqual(await formatFiles('check', ['admin'], args), { checked: 4, changed: 0 })
        await assert.rejects(formatFiles('check', ['admin/no-such-*.css'], args), /No matches/)
    } finally {
        await rm(fixture, { recursive: true, force: true })
    }
})
