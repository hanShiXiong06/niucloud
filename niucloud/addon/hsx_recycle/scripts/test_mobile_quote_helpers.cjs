const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../../')
const ts = require(path.join(root, 'site-uniapp/node_modules/typescript'))
const file = path.join(root, 'site-uniapp/src/addon/hsx_recycle/utils/quote.ts')
const code = ts.transpileModule(fs.readFileSync(file, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const context = { exports: {}, setTimeout, clearTimeout, Date, Promise }
vm.runInNewContext(code, context, { filename: file })
const { flattenQuoteCategories, filterQuoteCategories, formatQuoteTime, withQuoteUploadTimeout } = context.exports
const tree = [
    { category_id: 1, site_id: 100005, category_name: '安卓', sort: 1, child_list: [{ category_id: 11, site_id: 100005, category_name: '小米 17', images: '', sort: 0 }] },
    { category_id: 2, site_id: 100005, category_name: '苹果', sort: 9, child_list: [
        { category_id: 21, site_id: 100005, category_name: 'iPhone 17 Pro', images: 'https://storage.example/a.png', sort: 5 },
        { category_id: 22, site_id: 0, category_name: 'iPhone Air', images: 'upload/shared.png', sort: 5 }
    ] }
]
const rows = flattenQuoteCategories(tree)
assert.equal(rows.map(row => row.category_id).join(','), '2,21,22,1,11', '后台 sort 顺序与同sort稳定顺序')
assert.equal(rows[1].parentName, '苹果')
assert.equal(rows[1].fullName, '苹果 / iPhone 17 Pro')
assert.equal(filterQuoteCategories(rows, 'IPHONE17', 'all').length, 1, '搜索忽略大小写和空格')
assert.equal(filterQuoteCategories(rows, '苹果', 'all').length, 3, '可按上级分类查子分类')
assert.equal(filterQuoteCategories(rows, '', 'ready').length, 2)
assert.equal(filterQuoteCategories(rows, '', 'empty').length, 3)
assert.equal(flattenQuoteCategories(null).length, 0)
assert.equal(rows[2].site_id, 0, '保留归属供公共分类只读判断')
assert.equal(formatQuoteTime(''), '')
assert.equal(formatQuoteTime('invalid-time'), 'invalid-time')
assert.match(formatQuoteTime(1794409200), /^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}$/)
;(async () => {
    assert.equal(await withQuoteUploadTimeout(Promise.resolve('ok'), 50), 'ok')
    await assert.rejects(withQuoteUploadTimeout(Promise.reject(new Error('上传失败')), 50), /上传失败/)
    await assert.rejects(withQuoteUploadTimeout(new Promise(() => {}), 5), /上传超时/)
    console.log('PASS mobile quote helpers: sort, search, scope metadata, date, upload timeout')
})().catch(error => { console.error(error); process.exitCode = 1 })
