#!/usr/bin/env node
// Actual local H5 build/uview smoke. Every external request is fulfilled or aborted locally.
const fs = require('node:fs')
const path = require('node:path')
const http = require('node:http')
const assert = require('node:assert/strict')
const { chromium } = require('/tmp/node_modules/playwright')
const output = path.resolve(process.argv[2] || '/tmp/recycle-mobile-quote-h5-20261111')
const contentTypes = { '.js': 'application/javascript', '.css': 'text/css', '.html': 'text/html; charset=utf-8', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.png': 'image/png', '.svg': 'image/svg+xml' }
const categories = [{ category_id: 1, site_id: 100005, category_name: '苹果报价', category_full_name: '苹果报价', pid: 0, sort: 100, is_show: 1, images: '', child_list: [
    { category_id: 2, site_id: 100005, category_name: 'iPhone 17 Pro Max', category_full_name: '苹果报价/iPhone 17 Pro Max', pid: 1, sort: 90, is_show: 1, images: 'https://quote-fixture.invalid/old.jpg', child_list: [] }
] }, { category_id: 3, site_id: 100005, category_name: '三星报价', category_full_name: '三星报价', pid: 0, sort: 80, is_show: 1, images: '', child_list: [] }]
const quoteSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="1300"><rect width="400" height="1300" fill="white"/><rect width="400" height="70" fill="#e8f1ff"/><text x="20" y="44" font-size="22" fill="#1557b7">LOCAL QUOTE FIXTURE</text>${Array.from({ length: 20 }, (_, i) => `<rect y="${85 + i * 56}" width="400" height="54" fill="${i % 2 ? '#f8fafc' : '#eef2f7'}"/><text x="18" y="${119 + i * 56}" font-size="17" fill="#334155">iPhone ${17 - i % 4} / 256GB / ${6800 - i * 110}</text>`).join('')}</svg>`

async function main() {
    assert.ok(fs.existsSync(path.join(output, 'index.html')), '先构建 H5 到独立目录，不运行 publish 脚本')
    const server = http.createServer((request, response) => {
        const url = new URL(request.url, 'http://localhost')
        const relative = decodeURIComponent(url.pathname).replace(/^\/adminapp\/?/, '')
        const file = path.resolve(output, relative)
        if (!file.startsWith(output + path.sep) && file !== output) { response.statusCode = 403; return response.end() }
        const served = fs.existsSync(file) && fs.statSync(file).isFile() ? file : path.join(output, 'index.html')
        response.setHeader('Content-Type', contentTypes[path.extname(served)] || 'application/octet-stream')
        response.end(fs.readFileSync(served))
    })
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    let browser
    try {
        browser = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' })
        const origin = `http://127.0.0.1:${server.address().port}`
        for (const width of [320, 390]) {
            const context = await browser.newContext({ viewport: { width, height: 844 }, isMobile: true })
            await context.addInitScript(() => {
                localStorage.setItem('wapToken', 'local-fixture-token-not-a-real-credential')
                localStorage.setItem('siteId', JSON.stringify({ type: 'number', data: 100005 }))
                localStorage.setItem('site_openid', 'local-fixture-openid')
            })
            const page = await context.newPage()
            const errors = [], requests = [], writes = []
            page.on('pageerror', error => errors.push(String(error)))
            await page.route('**/*', async route => {
                const request = route.request(), url = new URL(request.url())
                if (url.origin === origin) return route.continue()
                if (url.hostname === 'quote-fixture.invalid') return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: quoteSvg })
                if (!url.pathname.startsWith('/adminapi/')) return route.abort()
                requests.push({ method: request.method(), pathname: url.pathname })
                let data = {}
                if (url.pathname.endsWith('/quote-options')) data = { categories, site_id: 100005, can_edit: true }
                else if (url.pathname.endsWith('/auth/site')) data = { site_id: 100005, uid: 1, username: '测试管理员', is_admin: 1 }
                else if (url.pathname.endsWith('/adminapp/site/navs')) data = []
                else if (url.pathname.endsWith('/sys/web/website')) data = { site_name: '本地报价回归', site_status: 1, name: '本地报价回归' }
                else if (url.pathname.endsWith('/sys/image')) data = { url: 'https://quote-fixture.invalid/new.png' }
                else if (url.pathname.endsWith('/quote-image')) { writes.push(JSON.parse(request.postData() || '{}')); data = { category_id: 2, images: 'https://quote-fixture.invalid/new.png', changed: true, update_time: 1794384000 } }
                else if (url.pathname.endsWith('/quote_history')) data = { data: [], total: 0, last_page: 1 }
                return route.fulfill({ status: 200, contentType: 'application/json', headers: { 'access-control-allow-origin': '*', 'access-control-allow-headers': '*', 'access-control-allow-methods': '*' }, body: JSON.stringify({ code: 1, msg: '操作成功', data }) })
            })
            await page.goto(origin + '/adminapp/addon/hsx_recycle/pages/quote/index')
            await page.locator('.quote-category-name').filter({ hasText: 'iPhone 17 Pro Max' }).waitFor()
            const search = page.locator('.quote-toolbar input').first()
            assert.equal(await search.count(), 1, '真实 u-search 必须渲染原生 input')
            await search.fill('三星')
            await page.waitForFunction(() => document.querySelectorAll('.quote-category').length === 1)
            assert.match(await page.locator('.quote-category-name').innerText(), /三星/)
            await search.fill('')
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true)
            await page.screenshot({ path: `/tmp/recycle-mobile-quote-real-h5-list-${width}.png` })
            await page.locator('.quote-category-name').filter({ hasText: 'iPhone 17 Pro Max' }).click()
            const button = page.locator('.quote-footer .u-button')
            await button.waitFor()
            const style = await button.evaluate(node => ({ color: getComputedStyle(node).backgroundColor, width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height }))
            assert.ok(style.width > width * 0.8 && style.height >= 40, '真实上传主按钮应满宽且可触控')
            assert.notEqual(style.color, 'rgba(0, 0, 0, 0)', '真实 u-button 不能透明')
            await page.screenshot({ path: `/tmp/recycle-mobile-quote-real-h5-detail-${width}.png` })
            const chooser = page.waitForEvent('filechooser')
            await button.click()
            await (await chooser).setFiles({ name: 'quote-fixture.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aEYsAAAAASUVORK5CYII=', 'base64') })
            await page.getByText('图片已上传，确认更新后才会替换当前报价', { exact: true }).waitFor()
            assert.equal(writes.length, 0, '真实文件上传完成也不得自动发布')
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true)
            await page.screenshot({ path: `/tmp/recycle-mobile-quote-real-h5-draft-${width}.png` })
            await page.locator('.quote-footer .u-button').filter({ hasText: '确认更新报价' }).click()
            await page.locator('.uni-modal__btn_primary').click()
            await page.waitForFunction(() => !document.querySelector('.quote-draft-card'))
            assert.equal(writes.length, 1)
            assert.equal(writes[0].expected_images, 'https://quote-fixture.invalid/old.jpg')
            assert.ok(requests.some(item => item.pathname.endsWith('/sys/image')), '真实图片上传路径必须走系统上传接口')
            assert.deepEqual(errors, [])
            console.log(`PASS real H5/uview ${width}px: search, full-width button, file upload fixture, confirm modal, no overflow`)
            await context.close()
        }
        console.log('[通过] 真实 H5 构建/uview 冒烟；全部远程请求本地拦截，未访问生产、未真实上传、未发布。')
    } finally { await browser?.close(); await new Promise(resolve => server.close(resolve)) }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
