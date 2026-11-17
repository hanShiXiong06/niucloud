const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../../')
const ts = require(path.join(root, 'site-uniapp/node_modules/typescript'))
const file = path.join(root, 'site-uniapp/src/addon/hsx_recycle/utils/quote.ts')
const addonFile = path.join(root, 'niucloud/addon/hsx_recycle/site-uniapp/utils/quote.ts')
assert.equal(fs.readFileSync(file, 'utf8'), fs.readFileSync(addonFile, 'utf8'), '插件 helper 副本须字节一致')
const code = ts.transpileModule(fs.readFileSync(file, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const context = { exports: {}, setTimeout, clearTimeout, Date, Promise }
vm.runInNewContext(code, context, { filename: file })
const { flattenQuoteCategories, filterQuoteCategories, visibleQuoteTreeRows, formatQuoteTime, withQuoteUploadTimeout } = context.exports
const ids = list => list.map(row => row.category_id).join(',')
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

const deepTree = [
    { category_id: 10, site_id: 100005, category_name: '手机', sort: 2, images: '', is_show: 1, child_list: [
        { category_id: 11, site_id: 100005, category_name: '安卓', sort: 1, images: '', is_show: 0, child_list: [
            { category_id: 111, site_id: 100005, category_name: '三星', sort: 0, images: 'samsung.png', is_show: 1, child_list: [
                { category_id: 1111, site_id: 100005, category_name: 'S系列', sort: 4, images: '', is_show: 0 }
            ] },
            { category_id: 112, site_id: 100005, category_name: '小米', sort: 10, images: '', is_show: 0 },
            { category_id: 113, site_id: 100005, category_name: '荣耀', sort: 10, images: 'honor.png', is_show: 1 }
        ] },
        { category_id: 12, site_id: 100005, category_name: '苹果', sort: 9, images: 'apple.png', is_show: 1 }
    ] },
    { category_id: 20, site_id: 0, category_name: '平板', sort: 3, images: 'tablet.png', is_show: 0, child_list: [
        { category_id: 21, site_id: 100005, category_name: 'iPad', sort: 99, images: '', is_show: 1 }
    ] },
    { category_id: 30, site_id: 100005, category_name: '配件', sort: 2, images: '', is_show: 1, child_list: [null, { category_id: 0 }] }
]
const originalTree = JSON.stringify(deepTree)
const deepRows = flattenQuoteCategories(deepTree)
assert.equal(ids(deepRows), '20,21,10,12,11,112,113,111,1111,30', '各层 sort 降序，同 sort 保留接口顺序，后代不得跨根重排')
assert.equal(JSON.stringify(deepTree), originalTree, '平铺不修改接口树或子节点顺序')
assert.equal(deepRows.find(row => row.category_id === 10).pid, 0, '根 pid 为 0')
assert.equal(deepRows.find(row => row.category_id === 1111).pid, 111, '任意层保留直属父节点')
assert.equal(deepRows.find(row => row.category_id === 1111).depth, 3, '深度从 0 开始且不限制层数')
assert.equal(deepRows.find(row => row.category_id === 1111).ancestorIds.join(','), '10,11,111', '按根到父保留完整祖先链')
assert.equal(deepRows.find(row => row.category_id === 1111).fullName, '手机 / 安卓 / 三星 / S系列')
assert.equal(deepRows.find(row => row.category_id === 11).hasChildren, true)
assert.equal(deepRows.find(row => row.category_id === 30).hasChildren, false, '无效子节点不产生展开箭头')
assert.equal(deepRows.find(row => row.category_id === 1111).hasChildren, false)
assert.equal(ids(visibleQuoteTreeRows(deepRows)), '20,10,30', '默认只显示各根分类')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'all', [10])), '20,10,12,11,30', '展开一层只显示直接子级')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'all', [10, 11])), '20,10,12,11,112,113,111,30', '展开第二层保留树的原顺序')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'all', [10, 11, 111])), '20,10,12,11,112,113,111,1111,30', '可展开任意深度')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'all', [11, 111])), '20,10,30', '祖先收起后隐藏整支，即使后代仍记为展开')
const repeatedExpanded = [10, 11, 11, 111, 99999]
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'all', repeatedExpanded)), '20,10,12,11,112,113,111,1111,30', '重复展开 id 或失效 id 不重复展示节点')
assert.equal(repeatedExpanded.join(','), '10,11,11,111,99999', '显示计算不修改页面展开状态')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'all', [20, 10, 11, 111])), ids(deepRows), '全部展开后与原树一致')

assert.equal(ids(filterQuoteCategories(deepRows, 'S系列', 'all')), '1111', '实际命中数量不包含上下文祖先')
assert.equal(ids(visibleQuoteTreeRows(deepRows, 'S系列')), '10,11,111,1111', '深层搜索自动保留全部祖先且无需预先展开')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '三星')), '10,11,111,1111', '上级名字通过完整路径继续匹配后代')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '手机')), '10,12,11,112,113,111,1111', '搜索父名字保留其子树，不显示其他分支')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '不存在', 'all', [20, 10, 11, 111])), '', '无搜索命中不显示已展开的不相关分支')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '   ')), '20,10,30', '纯空格搜索不强制展开')
assert.equal(ids(filterQuoteCategories(deepRows, '', 'ready')), '20,12,113,111')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'ready')), '20,10,12,11,113,111', '已上传筛选保留待上传祖先但不保留未命中后代')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '安卓', 'ready')), '10,11,113,111', '搜索与状态同时生效，祖先仅作为上下文')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'empty')), '20,21,10,11,112,111,1111,30', '待上传筛选保留已上传的上下文祖先')
assert.equal(ids(filterQuoteCategories(deepRows, '', 'hidden')), '20,11,112,1111')
assert.equal(ids(filterQuoteCategories(deepRows, '', 'visible')), '21,10,12,113,111,30')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'hidden')), '20,10,11,112,111,1111', '隐藏筛选保留显示中的祖先，不泄露无关分支')
assert.equal(ids(visibleQuoteTreeRows(deepRows, '', 'visible')), '20,21,10,12,11,113,111,30', '显示筛选保留隐藏祖先，只以自身状态计算命中')
assert.equal(ids(visibleQuoteTreeRows(deepRows, 'S系列', 'visible')), '', '搜索命中但显示状态不匹配时不显示祖先')
assert.equal(filterQuoteCategories(rows, '', 'visible').length, rows.length, '旧数据缺失 is_show 与页面约定一致，按未隐藏处理')
assert.equal(ids(filterQuoteCategories(deepRows.map(row => ({ ...row, is_show: String(row.is_show) })), '', 'hidden')), '20,11,112,1111', '接口字符串状态仍准确识别')
const originalRows = JSON.stringify(deepRows)
const contextualRows = visibleQuoteTreeRows(deepRows, 'S系列', 'all', [20, 10])
assert.equal(contextualRows[0], deepRows.find(row => row.category_id === 10), '显示列表返回原分类引用，不覆盖业务字段')
assert.equal(contextualRows[3], deepRows.find(row => row.category_id === 1111))
assert.equal(JSON.stringify(deepRows), originalRows, '搜索筛选与展开不改变树 metadata 或报价图片')
assert.equal(visibleQuoteTreeRows([]).length, 0)
assert.equal(visibleQuoteTreeRows([], '手机', 'hidden').length, 0)
assert.equal(visibleQuoteTreeRows(null).length, 0)
const legacyRows = rows.map(({ pid, depth, ancestorIds, hasChildren, ...row }) => row)
assert.equal(ids(visibleQuoteTreeRows(legacyRows)), ids(legacyRows), '旧行缺失树 metadata 时安全显示，不丢数据')

assert.equal(formatQuoteTime(''), '')
assert.equal(formatQuoteTime('invalid-time'), 'invalid-time')
assert.match(formatQuoteTime(1794409200), /^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}$/)
;(async () => {
    assert.equal(await withQuoteUploadTimeout(Promise.resolve('ok'), 50), 'ok')
    await assert.rejects(withQuoteUploadTimeout(Promise.reject(new Error('上传失败')), 50), /上传失败/)
    await assert.rejects(withQuoteUploadTimeout(new Promise(() => {}), 5), /上传超时/)
    console.log('PASS mobile quote helpers: stable tree sort, expand/collapse, search/filter ancestry, scope metadata, date, upload timeout')
})().catch(error => { console.error(error); process.exitCode = 1 })
