// Exercise real Axios URL serialization: mocks of page methods alone do not catch a missing "params".
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm')
const assert = require('node:assert/strict'), { createRequire } = require('node:module')
const repo = path.resolve(__dirname, '../../../..')
const req = createRequire(path.join(repo, 'admin/package.json'))
const axios = req('axios'), ts = req('typescript')
const get = (url, config) => new URL(axios.getUri({ url, ...config }), 'http://localhost')
function load(name) {
    const relative = 'api/' + name + '.ts'
    const code = fs.readFileSync(path.join(repo, 'niucloud/addon/hsx_boyuan/admin', relative), 'utf8')
    assert.equal(code, fs.readFileSync(path.join(repo, 'admin/src/addon/hsx_boyuan', relative), 'utf8'))
    const module = {exports:{}}
    const js = ts.transpileModule(code, {compilerOptions:{module:ts.ModuleKind.CommonJS}}).outputText
    vm.runInNewContext(js, { module, exports:module.exports, require:()=>({default:{get}}) })
    return module.exports
}
const sale = load('wholesale')
function query(url, expected) { assert.deepEqual(Object.fromEntries(url.searchParams),expected) }
query(sale.listOutbound(2, 'SO-本机', 'simulated'),{type:'wholesale_review',page:'2',limit:'15',keyword:'SO-本机',status:'simulated'})
query(sale.getOutbound(9),{type:'wholesale_review'})
query(load('opening-post').readOpeningCheck('acct-X'),{connection_key:'acct-X'})
query(load('purchase').getPurchasePaymentCandidates(9,{page:2}),{page:'2'})
console.log('PASS: four PC GET operations serialize their filters to the URL. No HTTP sent.')
