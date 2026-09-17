'use strict'
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../../../..')
const frontend = path.join(root, 'uni-app/src/addon/recycle_quote_spider')
const ts = require(path.join(root, 'uni-app/node_modules/typescript'))
const vue = require(path.join(root, 'uni-app/node_modules/vue'))
const sfc = require(path.join(root, 'uni-app/node_modules/@vue/compiler-sfc'))
let request
let checks = 0
function check(name, callback) { callback(); checks++; console.log('PASS ' + name) }
const modules = new Map()
function load(file) {
    if (modules.has(file)) return modules.get(file)
    const result = { exports: {} }
    const output = ts.transpileModule(fs.readFileSync(file, 'utf8'), {
        compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }, fileName: file
    }).outputText
    const requireLocal = name => {
        if (name === 'vue') return vue
        if (name === '@/addon/recycle_quote_spider/api/quotation') return { searchQuoteSpiderModels: params => request(params) }
        if (name.startsWith('@/addon/recycle_quote_spider/')) return load(path.join(frontend, name.split('@/addon/recycle_quote_spider/')[1] + '.ts'))
        if (name.startsWith('.')) return load(path.resolve(path.dirname(file), name + '.ts'))
        throw new Error('Unexpected import ' + name)
    }
    new Function('require', 'module', 'exports', output)(requireLocal, result, result.exports)
    modules.set(file, result.exports)
    return result.exports
}
const helpers = load(path.join(frontend, 'utils/quoteSearch.ts'))
const { normalizeSpiderPrices } = load(path.join(frontend, 'pages/price/utils/spiderQuote.ts'))
const { useQuoteSearch } = load(path.join(frontend, 'pages/price/composables/useQuoteSearch.ts'))
const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { promise, resolve, reject } }
const response = (ids, total = ids.length, lastPage = 1) => ({ data: { data: ids.map(id => ({ id })), total, last_page: lastPage } })

async function main() {
    check('Search keyword normalization and bounded length', () => {
        assert.equal(helpers.normalizeSearchKeyword('  iPhone  17\n Pro Max  '), 'iPhone 17 Pro Max')
        assert.equal(Array.from(helpers.normalizeSearchKeyword('苹'.repeat(90))).length, 80)
    })
    check('URL decoding handles malformed escape sequences', () => {
        assert.equal(helpers.decodeSearchKeyword('%E8%8B%B9%E6%9E%9C%2017'), '苹果 17')
        assert.equal(helpers.decodeSearchKeyword('17%XX'), '17%XX')
    })
    check('History deduplicates, bounds size, preserves no-match searches', () => {
        let history = []
        for (let index = 0; index < 20; index++) history = helpers.recordSearchHistory(history, 'model' + index, index)
        assert.equal(history.length, 12)
        history = helpers.recordSearchHistory(history, 'MODEL 19', 0, 12)
        assert.equal(history.length, 12)
        assert.deepEqual(history[0], { keyword: 'MODEL 19', total: 0, searchedAt: 12 })
    })
    check('Corrupt local history is rejected', () => {
        assert.deepEqual(helpers.readSearchHistory('bad'), [])
        assert.deepEqual(helpers.readSearchHistory([null, {}, { keyword: 'a', total: -1 }, { keyword: 'b', total: Infinity }]), [])
    })
    check('History separated by site, member and quote source', () => {
        assert.equal(new Set([[1, 1, 1], [2, 1, 1], [1, 2, 1], [1, 1, 2]].map(args => helpers.searchHistoryKey(...args))).size, 4)
    })
    check('Price normalization preserves zero, missing values and labels', () => {
        assert.deepEqual(normalizeSpiderPrices([0, null, '暂停'], ['A', 'B', 'C']), {
            A: { price: 0, final: 0 }, B: { price: null, final: null }, C: { price: '暂停', final: '暂停' }
        })
        assert.deepEqual(normalizeSpiderPrices({ 0: 100 }, ['靓机']), { '靓机': { price: 100, final: 100 } })
    })

    const saved = []
    const state = useQuoteSearch((keyword, total) => saved.push({ keyword, total }))
    let a = deferred(), b = deferred()
    request = ({ keyword }) => keyword === 'A' ? a.promise : b.promise
    const pendingA = state.search('A')
    const pendingB = state.search('B')
    b.resolve(response([2])); await pendingB
    a.resolve(response([1])); await pendingA
    check('A slow older request cannot overwrite the latest keyword or its history', () => {
        assert.equal(state.keyword.value, 'B')
        assert.deepEqual(state.rows.value.map(row => row.id), [2])
        assert.deepEqual(saved, [{ keyword: 'B', total: 1 }])
        assert.equal(state.loading.value, false)
    })
    request = () => Promise.resolve(response([1, 2], 4, 2))
    await state.search('paged')
    request = () => Promise.reject({ msg: '网络暂不可用' })
    await state.loadMore()
    check('Load-more error retains previously loaded prices', () => {
        assert.equal(state.rows.value.length, 2)
        assert.equal(state.error.value, '网络暂不可用')
    })
    let requestedPage = 0
    request = params => { requestedPage = params.page; return Promise.resolve(response([2, 3, 4], 4, 2)) }
    await state.retry()
    check('Retry retries the same page and removes duplicate rows', () => {
        assert.equal(requestedPage, 2)
        assert.deepEqual(state.rows.value.map(row => row.id), [1, 2, 3, 4])
        assert.equal(state.hasMore.value, false)
    })
    a = deferred(); request = () => a.promise
    const pending = state.search('cancelled')
    state.reset(); a.resolve(response([9])); await pending
    check('Clearing or leaving page cancels result and history updates', () => {
        assert.equal(state.keyword.value, '')
        assert.deepEqual(state.rows.value, [])
        assert.ok(!saved.some(item => item.keyword === 'cancelled'))
    })
    request = () => { throw new Error('Must not request') }
    await state.search('   ')
    check('Empty keyword does not request data', () => assert.equal(state.loading.value, false))
    request = () => Promise.resolve(response([], 0))
    await state.search('missing')
    check('No-match search is saved and not endlessly paginated', () => {
        assert.deepEqual(saved.at(-1), { keyword: 'missing', total: 0 })
        assert.equal(state.hasMore.value, false)
    })

    const files = [
        'uni-app/src/addon/recycle_quote_spider/components/QuoteSearchField.vue',
        'uni-app/src/addon/recycle_quote_spider/components/QuoteSearchResult.vue',
        'uni-app/src/addon/recycle_quote_spider/components/diy/recycle-spider-search/index.vue',
        'uni-app/src/addon/recycle_quote_spider/pages/price/search.vue',
        'uni-app/src/addon/recycle_quote_spider/pages/price/show_price.vue',
        'uni-app/src/addon/components/diy/group/index.vue',
        'admin/src/addon/recycle_quote_spider/views/diy/components/edit-recycle-spider-search.vue'
    ]
    for (const file of files) {
        check('Vue compile ' + path.basename(path.dirname(file)) + '/' + path.basename(file), () => {
            const parsed = sfc.parse(fs.readFileSync(path.join(root, file), 'utf8'), { filename: file })
            assert.deepEqual(parsed.errors, [])
            const script = sfc.compileScript(parsed.descriptor, { id: 'search-test' })
            const compiled = sfc.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: 'search-test', compilerOptions: { bindingMetadata: script.bindings } })
            assert.deepEqual(compiled.errors, [])
            for (const style of parsed.descriptor.styles) {
                const source = style.lang === 'scss' ? '$u-primary: #2563eb;\n' + style.content : style.content
                const result = sfc.compileStyle({ source, filename: file, id: 'search-test', preprocessLang: style.lang })
                assert.deepEqual(result.errors, [])
            }
        })
    }
    const group = fs.readFileSync(path.join(root, 'uni-app/src/addon/components/diy/group/index.vue'), 'utf8')
    const pages = fs.readFileSync(path.join(root, 'uni-app/src/pages.json'), 'utf8')
    check('DIY entry and page are registered', () => {
        assert.ok(group.includes("component.componentName == 'RecycleSpiderSearch'"))
        assert.ok(group.includes("import diyRecycleSpiderSearch from '@/addon/recycle_quote_spider/components/diy/recycle-spider-search/index.vue'"))
        assert.ok(pages.includes('"path": "pages/price/search"'))
    })
    console.log(`${checks} frontend checks passed`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
