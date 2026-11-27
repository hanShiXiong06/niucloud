const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const source = fs.readFileSync(path.join(root, 'uni-app/src/addon/hsx_recycle/pages/price.vue'), 'utf8')
assert.equal(source, fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/uni-app/pages/price.vue'), 'utf8'))
assert.ok(!source.includes('getImgUrl() + images'), 'Must use the framework image URL helper')
const body = source.match(/const previewImages = \(images\) => \{[\s\S]*?\n\};/)[0]
let calls = []
const context = { img: value => /^https?:\/\//.test(value) ? value : `https://store.test/${value.replace(/^\//, '')}`,
    uni: { previewImage: value => calls.push(JSON.parse(JSON.stringify(value))) } }
vm.createContext(context)
vm.runInContext(body + '\nthis.preview = previewImages', context)
context.preview('upload/quote.png')
assert.deepEqual(calls.pop().urls, ['https://store.test/upload/quote.png'])
context.preview('https://cloud.test/quote.png')
assert.deepEqual(calls.pop().urls, ['https://cloud.test/quote.png'])
context.preview(['upload/quote.png', 'https://cloud.test/quote.png'])
assert.deepEqual(calls.pop().urls, ['https://store.test/upload/quote.png', 'https://cloud.test/quote.png'])
context.preview([])
context.preview('')
assert.equal(calls.length, 0)
console.log('PASS 报价图片预览：发布镜像、框架地址处理、相对地址、云地址、数组及空值，共6项。')
