'use strict'
// 检查真正的双端构建产物，防止“编译成功但页面没样式”。只读文件，不发布、不请求业务接口。
// node tests/member_card_mobile_styles_build.cjs <mp-weixin 构建目录> <H5 构建目录>
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '..')
const source = path.join(root, 'site-uniapp/src/addon/hsx_member_card')
const release = path.join(root, 'niucloud/addon/hsx_member_card/site-uniapp')
const mpDir = process.argv[2]
const h5Dir = process.argv[3]
if (!mpDir || !h5Dir) throw new Error('请传入独立的小程序和 H5 构建目录，先构建，再检查')
const walk = dir => fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
    const file = path.join(dir, entry.name)
    return entry.isDirectory() ? walk(file) : [file]
})
let count = 0
const check = (title, run) => { run(); count++; console.log('PASS ' + title) }
const verifyCommon = css => {
    for (const name of ['mc-page', 'mc-content', 'mc-card', 'mc-field', 'mc-sheet']) {
        assert.match(css, new RegExp('\\.' + name + '(?:[,\\s]*\\{|,)'), name + ' 缺失或被页面 scoped 限制')
    }
    assert.match(css, /\.mc-actionbar__inner\s*\{[^}]*display:\s*flex/)
    assert.doesNotMatch(css, /\.mc-actionbar__inner(?:\[data-v-|\.data-v-)/)
}
for (const file of walk(path.join(source, 'pages')).filter(file => file.endsWith('.vue'))) {
    const relative = path.relative(source, file).replace(/\.vue$/, '.wxss')
    check('小程序页面独立携带公共样式 ' + relative, () => {
        verifyCommon(fs.readFileSync(path.join(mpDir, 'addon/hsx_member_card', relative), 'utf8'))
    })
}
check('H5 编辑页同时保留公共组件样式与自身滚动布局', () => {
    const allStyles = walk(path.join(h5Dir, 'assets')).filter(file => file.endsWith('.css'))
        .map(file => fs.readFileSync(file, 'utf8'))
    const styles = allStyles.filter(css => css.includes('.mc-product-edit__scroll'))
    assert.equal(styles.length, 1, '没有找到唯一的编辑页样式，确认构建目录是否已经清理重建')
    const shared = allStyles.filter(css => css.includes('.mc-actionbar__inner'))
    assert.ok(shared.length, 'H5 共享样式未构建')
    shared.forEach(verifyCommon)
    assert.match(styles[0], /\.mc-product-edit__scroll(?:\[data-v-[^\]]+\])?\s*\{[^}]*height:\s*100%/)
})
check('插件发布源与运行源逐文件完全一致，升级不会还原 UI 修复', () => {
    const runtimeFiles = walk(source).map(file => path.relative(source, file)).sort()
    const releaseFiles = walk(release).map(file => path.relative(release, file)).sort()
    assert.deepEqual(releaseFiles, runtimeFiles)
    for (const file of runtimeFiles) assert.equal(fs.readFileSync(path.join(source, file), 'utf8'), fs.readFileSync(path.join(release, file), 'utf8'), file)
})
console.log('\n' + count + ' PASS — 构建产物及发布源检查；无部署或数据写入')
