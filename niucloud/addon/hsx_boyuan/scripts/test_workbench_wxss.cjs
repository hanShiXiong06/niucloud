#!/usr/bin/env node
// 源码条件编译 + 真实 SFC/SCSS 编译；可额外传完整 mp-weixin 构建目录。
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const { initPreContext, preCss } = require(path.join(app, 'node_modules/@dcloudio/uni-cli-shared/dist/preprocess'))
const postcss = require(path.join(app, 'node_modules/postcss'))
const selectors = require(path.join(app, 'node_modules/postcss-selector-parser'))
const filename = path.join(app, 'src/addon/hsx_boyuan/pages/workbench/index.vue')
const source = fs.readFileSync(filename, 'utf8')
let count = 0
const check = (condition, message) => { assert.ok(condition, message); count++ }
const universalSelectors = css => {
    const found = []
    postcss.parse(css).walkRules(rule => {
        selectors(ast => ast.walkUniversals(() => found.push(rule.selector))).processSync(rule.selector)
    })
    return found
}
const withoutMotion = css => {
    const ast = postcss.parse(css)
    ast.walkComments(comment => comment.remove())
    ast.walkAtRules('media', rule => { if (rule.params.includes('prefers-reduced-motion')) rule.remove() })
    return ast.toString().replace(/\s+/g, '')
}
async function main() {
    const { descriptor, errors } = compiler.parse(source, { filename })
    check(errors.length === 0, 'SFC 必须能解析')
    check(descriptor.styles.length === 1 && descriptor.styles[0].lang === 'scss', '检查真实页面SCSS')
    check(/\/\* #ifndef MP-WEIXIN \*\/[\s\S]*?prefers-reduced-motion[\s\S]*?\/\* #endif \*\//.test(source), '仅微信排除原通配规则')
    const raw = descriptor.styles[0].content
    check(universalSelectors(raw).length === 2, '负例应能发现原两处通配选择器')
    check(universalSelectors('.bridge{width:calc(100% - 2 * 3px)}').length === 0, '不误删声明里的乘号')
    check(universalSelectors('.bridge[data-value="*"]{display:block}').length === 0, '不误判属性字符串星号')
    check(universalSelectors('.bridge *::before{transition:none}').length === 1, '伪元素前的通配也被识别')
    const outputs = {}
    for (const platform of ['mp-weixin', 'h5']) {
        initPreContext(platform)
        const css = preCss(raw)
        const result = await compiler.compileStyleAsync({ source: css, filename, id: 'data-v-workbench-test', scoped: true, preprocessLang: 'scss' })
        check(result.errors.length === 0, platform + ' 样式编译无错误')
        outputs[platform] = result.code
    }
    check(universalSelectors(outputs['mp-weixin']).length === 0, '微信编译结果不能含非法通配选择器')
    check(!outputs['mp-weixin'].includes('prefers-reduced-motion'), '微信不能遗留该排除规则')
    check(universalSelectors(outputs.h5).length === 2 && outputs.h5.includes('prefers-reduced-motion'), 'H5减少动画行为保留')
    check(withoutMotion(outputs['mp-weixin']) === withoutMotion(outputs.h5), '其余平台样式不变')
    check(outputs['mp-weixin'].includes('max-width: 350px'), '原小屏适配不被删除')
    if (process.argv[2]) {
        const wxss = path.join(path.resolve(process.argv[2]), 'addon/hsx_boyuan/pages/workbench/index.wxss')
        check(fs.existsSync(wxss), '完整构建必须存在博远工作台WXSS')
        check(universalSelectors(fs.readFileSync(wxss, 'utf8')).length === 0, '完整构建产物不能含通配选择器')
    }
    console.log(`[通过] ${count} 项博远工作台微信样式回归；不改业务、不连接接口。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
