'use strict'
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const os = require('node:os')
const {execFileSync, spawnSync} = require('node:child_process')
const root = path.resolve(__dirname, '..')
const sfc = require(path.join(root, 'uni-app/node_modules/@vue/compiler-sfc'))
const {repair} = require(path.join(root, 'uni-app/scripts/repair-phone-shop-diy.cjs'))
const manifest = require(path.join(root, 'uni-app/src/addon/phone_shop/components/diy/registration.json'))
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'phone-shop-diy-test-'))
let count = 0
function check(name, fn) { fn(); count++; console.log('PASS ' + name) }
function compile(source) {
    const parsed = sfc.parse(source, {filename:'index.vue'})
    assert.deepEqual(parsed.errors, [])
    const script = sfc.compileScript(parsed.descriptor, {id:'diy-group'})
    const template = sfc.compileTemplate({source:parsed.descriptor.template.content, filename:'index.vue', id:'diy-group', compilerOptions: {bindingMetadata:script.bindings}})
    assert.deepEqual(template.errors, [])
}
function put(file, content) {fs.mkdirSync(path.dirname(file), {recursive:true}); fs.writeFileSync(file, content)}
function name(slug) {return slug.split('-').map(part => part[0].toUpperCase() + part.slice(1)).join('')}
function block(slug) {return `    <template v-if="component.componentName == '${name(slug)}'">\n        <diy-${slug} ref="diy${name(slug)}Ref" :component="component" />\n    </template>\n`}
function imported(addon, slug) {return `import diy${name(slug)} from '@/addon/${addon}/components/diy/${slug}/index.vue';\n`}
const fixture = '<template>\n<view>\n' + block('shop-exchange-goods') + block('other-card') + block('phone-shop-exchange-goods') + block('shop-exchange-goods') + block('phone-shop-member-barcode') + block('shop-member-barcode') + '</view>\n</template>\n<script setup lang="ts">\n' + imported('shop', 'shop-exchange-goods') + imported('example', 'other-card') + imported('phone_shop', 'phone-shop-exchange-goods') + imported('phone_shop', 'shop-exchange-goods') + imported('phone_shop', 'phone-shop-member-barcode') + imported('phone_shop', 'shop-member-barcode') + '</script>\n'
try {
    check('原冲突可复现', () => assert.throws(() => compile(fixture), /already been declared/))
    const fixed = repair(fixture, manifest)
    check('修复后 Vue 脚本与模板均可编译', () => compile(fixed.source))
    check('只移除 phone_shop 旧目录注册，保留原商城和其他插件', () => {
        assert.deepEqual(fixed.removed, ['shop-exchange-goods', 'shop-member-barcode'])
        assert.ok(fixed.source.includes(imported('shop', 'shop-exchange-goods')))
        assert.ok(fixed.source.includes(imported('example', 'other-card')))
        assert.equal(fixed.source.split(block('shop-exchange-goods')).length - 1, 1)
        assert.equal(fixed.source.split(block('shop-member-barcode')).length - 1, 0)
    })
    check('重复修复不再产生变更', () => assert.equal(repair(fixed.source, manifest).source, fixed.source))
    check('CRLF 文件仍保留原换行', () => assert.equal(repair(fixture.replace(/\n/g, '\r\n'), manifest).source, fixed.source.replace(/\n/g, '\r\n')))
    check('新组件未上传拒绝修改', () => assert.throws(() => repair(fixture, manifest, () => false), /更新不完整/))
    check('新组件未注册拒绝修改', () => assert.throws(() => repair(fixture.replace(imported('phone_shop', 'phone-shop-exchange-goods'), ''), manifest), /更新不完整/))
    check('模板被人工改成不同结构时拒绝盲删', () => assert.throws(() => repair(fixture.replace(block('shop-exchange-goods'), ''), manifest), /数量与导入不一致/))
    check('未知重复注册拒绝修改', () => assert.throws(() => repair(fixture.replace('</script>', imported('third', 'other-card') + '</script>'), manifest), /仍有组件重名/))
    check('无效注册配置拒绝修改', () => assert.throws(() => repair(fixture, {version:1, replaced_directories:22}), /格式错误/))

    const artifact = path.join(root, 'docs/hotfix/2026-09-07-phone-shop-diy/group/index.vue')
    const artifactSource = fs.readFileSync(artifact, 'utf8')
    check('按用户线上插件集合修复的完整文件可编译', () => compile(artifactSource))
    for (const addon of ['shop', 'tk_jhkd', 'sd_xiaoyuan', 'hsx_recycle', 'recycle_daheng_quote', 'recycle_quote_spider', 'hsx_ai']) {
        check('线上文件保留插件 ' + addon, () => assert.ok(artifactSource.includes("@/addon/" + addon + '/')))
    }
    if (process.argv[2]) {
        const original = fs.readFileSync(process.argv[2], 'utf8')
        check('用户原始错误精确复现', () => assert.throws(() => compile(original), /diyShopExchangeGoods.*already been declared/))
        check('交付文件等于用户原文件的定向修复结果', () => assert.equal(artifactSource.trimEnd(), repair(original, manifest).source.trimEnd()))
        check('原文件其他插件导入全部原样保留', () => {
            const others = original.match(/^.*import .*from ['"]@\/addon\/(?!phone_shop\/).*$/gm)
            for (const line of others) assert.ok(artifactSource.includes(line))
        })
    }
    check('发布包和运行目录使用相同注册声明', () => assert.equal(fs.readFileSync(path.join(root, 'niucloud/addon/phone_shop/uni-app/components/diy/registration.json'), 'utf8'), fs.readFileSync(path.join(root, 'uni-app/src/addon/phone_shop/components/diy/registration.json'), 'utf8')))
    check('全部替代目标都是真实组件，且与字典一致', () => {
        const dictionary = fs.readFileSync(path.join(root, 'niucloud/addon/phone_shop/app/dict/diy/components.php'), 'utf8')
        for (const replacement of Object.values(manifest.replaced_directories)) {
            assert.ok(fs.existsSync(path.join(root, 'uni-app/src/addon/phone_shop/components/diy', replacement, 'index.vue')))
            assert.ok(dictionary.includes("'" + name(replacement) + "'"))
        }
    })

    const cliRoot = path.join(temp, 'cli')
    const cli = path.join(cliRoot, 'scripts/repair-phone-shop-diy.cjs')
    const target = path.join(cliRoot, 'src/addon/components/diy/group/index.vue')
    put(cli, fs.readFileSync(path.join(root, 'uni-app/scripts/repair-phone-shop-diy.cjs')))
    put(target, fixture)
    put(path.join(cliRoot, 'src/addon/phone_shop/components/diy/registration.json'), JSON.stringify(manifest))
    for (const slug of ['phone-shop-exchange-goods', 'phone-shop-member-barcode']) put(path.join(cliRoot, 'src/addon/phone_shop/components/diy', slug, 'index.vue'), '<template><view /></template>')
    check('命令默认只预览，不写文件', () => {assert.equal(spawnSync(process.execPath, [cli], {encoding:'utf8'}).status, 2); assert.equal(fs.readFileSync(target, 'utf8'), fixture)})
    check('确认写入后原文件有独立备份，且修复幂等', () => {
        const result = spawnSync(process.execPath, [cli, '--write'], {encoding:'utf8'})
        assert.equal(result.status, 0, result.stderr)
        assert.equal(fs.readFileSync(target, 'utf8'), fixed.source)
        const backup = fs.readdirSync(path.join(cliRoot, '.diy-backups'))
        assert.equal(backup.length, 1)
        assert.equal(fs.readFileSync(path.join(cliRoot, '.diy-backups', backup[0]), 'utf8'), fixture)
        assert.equal(spawnSync(process.execPath, [cli, '--write']).status, 0)
        assert.equal(fs.readdirSync(path.join(cliRoot, '.diy-backups')).length, 1)
    })

    const php = process.env.PHP_BIN || 'php'
    const generated = JSON.parse(execFileSync(php, [path.join(__dirname, 'diy_component_registration_smoke.php'), temp], {encoding:'utf8'}))
    for (const label of generated.cases) check('后端生成：' + label, () => {})
    for (const file of generated.outputs) check('后端真实生成文件通过 Vue 编译 ' + path.basename(path.dirname(path.dirname(path.dirname(path.dirname(file))))), () => compile(fs.readFileSync(file, 'utf8')))
    console.log(`全部通过：${count} 项；未连接数据库，未修改业务数据。`)
} finally {
    fs.rmSync(temp, {recursive:true, force:true})
}
