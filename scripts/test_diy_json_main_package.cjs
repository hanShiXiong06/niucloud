// node scripts/test_diy_json_main_package.cjs [微信小程序构建输出目录]
// 只读检查：不启动小程序，不调用接口，不修改构建产物。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '..')
const local = createRequire(path.join(root, 'uni-app/package.json'))
const ts = local('typescript')
let checks = 0
const check = (name, test) => { test(); checks++; console.log('PASS', name) }
function loadParser(relative) {
    const file = path.join(root, relative)
    const result = ts.transpileModule(fs.readFileSync(file, 'utf8'), {
        fileName: file, reportDiagnostics: true,
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 }
    })
    assert.deepEqual(result.diagnostics, [])
    const exports = {}
    vm.runInNewContext(result.outputText, { exports }, { filename: file })
    return exports.parseJsonValue
}
const parse = loadParser('uni-app/src/utils/json.ts')
const pluginParse = loadParser('uni-app/src/addon/phone_shop/utils/json.ts')
const fallback = { global: {}, value: [] }
const object = { global: { title: '首页' }, value: [{ componentName: 'Text' }] }
check('结构化对象不重复 JSON.parse', () => assert.equal(parse(object, fallback), object))
check('普通 JSON 字符串', () => assert.equal(JSON.stringify(parse(JSON.stringify(object), fallback)), JSON.stringify(object)))
check('重复编码的 JSON 字符串', () => assert.equal(JSON.stringify(parse(JSON.stringify(JSON.stringify(object)), fallback)), JSON.stringify(object)))
check('数组返回值', () => assert.equal(JSON.stringify(parse('[1,2]', [])), '[1,2]'))
for (const value of ['', ' ', '[object Object]', '{bad', null, undefined, 1, true, 'null', '123', '"text"']) {
    check('无效装修数据安全回退 ' + String(value), () => assert.equal(parse(value, fallback), fallback))
}
check('公共与商城解析结果一致', () => {
    for (const value of [object, JSON.stringify(object), JSON.stringify(JSON.stringify(object)), '[1,2]', '{bad', null]) {
        assert.equal(JSON.stringify(parse(value, fallback)), JSON.stringify(pluginParse(value, fallback)))
    }
})
const entries = ['hooks/useDiy.ts', 'app/stores/diy.ts', 'app/components/diy/carousel-search/index.vue']
for (const entry of entries) check('主包入口使用公共工具 ' + entry, () => {
    const source = fs.readFileSync(path.join(root, 'uni-app/src', entry), 'utf8')
    assert.match(source, /import\s*\{\s*parseJsonValue\s*\}\s*from\s*['"]@\/utils\/json['"]/)
    assert.doesNotMatch(source, /@\/addon\/phone_shop\/utils\/json/)
})
if (process.argv[2]) {
    const output = path.resolve(process.argv[2])
    const files = []
    const visit = directory => {
        for (const item of fs.readdirSync(directory, { withFileTypes: true })) {
            const file = path.join(directory, item.name)
            if (item.isDirectory()) visit(file)
            else if (item.name.endsWith('.js')) files.push(file)
        }
    }
    visit(output)
    const config = JSON.parse(fs.readFileSync(path.join(output, 'app.json'), 'utf8'))
    const packages = (config.subPackages || config.subpackages || []).map(item => item.root.replace(/\/$/, '') + '/')
    check('主包 JS 不再引用商城 JSON 分包', () => {
        for (const file of files) {
            const relative = path.relative(output, file).split(path.sep).join('/')
            if (packages.some(prefix => relative.startsWith(prefix))) continue
            const source = fs.readFileSync(file, 'utf8')
            assert.doesNotMatch(source, /phone_shop\/utils\/json(?:\.js)?/, relative)
        }
    })
    check('构建后的公共 JSON 工具可独立加载', () => {
        const file = path.join(output, 'utils/json.js')
        assert.ok(fs.existsSync(file), '缺少主包 utils/json.js')
        const module = { exports: {} }
        vm.runInNewContext(fs.readFileSync(file, 'utf8'), { module, exports: module.exports }, { filename: file })
        assert.equal(JSON.stringify(module.exports.parseJsonValue(JSON.stringify(object), fallback)), JSON.stringify(object))
    })
}
console.log('完成：' + checks + ' 项检查；未调用业务接口。')
