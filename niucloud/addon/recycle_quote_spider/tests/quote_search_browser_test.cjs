'use strict'
// Runs the real H5 page against isolated API fixtures; no real token or business writes.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const { chromium } = require('playwright')
const base = process.env.QUOTE_SEARCH_H5_URL || 'http://127.0.0.1:5187/wap'
const output = process.env.QUOTE_SEARCH_SCREENSHOTS || '/tmp/quote-search-ui'
process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY = '1'
fs.mkdirSync(output, { recursive: true })
const results = Array.from({ length: 14 }, (_, index) => ({
    id: index + 1, item_id: index < 7 ? 101 : 102, item_name: index < 7 ? '苹果靓机报价' : '苹果问题机报价',
    model_name: 'iPhone 17 Pro Max', brand: '苹果', tab: '苹果', capacity: index % 2 ? '512GB' : '256GB',
    source_name: '回收报价', price_date: '2026-09-16', update_at_text: '2026-09-16 10:30',
    columns: ['充新', '靓机', '小花'], final_prices: { '充新': 5500 + index, '靓机': 5200, '小花': 4900 },
    remark: '屏幕更换需另议；蓝色减 100 元。电池低于 85% 另扣，最终以到店质检为准。外观有磕碰请提前确认。'
}))

async function main() {
    const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--no-proxy-server'] })
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } })
        const errors = []
        const searches = []
        let failNext = false
        page.on('pageerror', error => { errors.push(error.message); if (process.env.QUOTE_SEARCH_DEBUG) console.log('Runtime:', error.message) })
        await page.addInitScript(() => {
            localStorage.setItem('wapToken', JSON.stringify({ type: 'string', data: 'isolated-ui-fixture' }))
            localStorage.setItem('wap_member_id', JSON.stringify({ type: 'number', data: 900000916 }))
        })
        await page.route(url => url.pathname.startsWith('/api/'), async route => {
            const url = new URL(route.request().url())
            const endpoint = url.pathname.replace(/^\/api\//, '')
            const send = (data, code = 1, msg = '') => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ code, data, msg }), headers: { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': '*', 'Access-Control-Allow-Methods': 'GET,POST,OPTIONS' } })
            if (route.request().method() === 'OPTIONS') return send({})
            if (endpoint === 'init') return send({
                tabbar_list: [], map_config: { is_open: 0, valid_time: 0, map_type: 'tencent', key: '' }, theme_list: [],
                site_info: { site_id: 100005, app: [], site_addons: [{ key: 'recycle_quote_spider' }], site_name: '搜索测试' },
                copyright: {}, member_level: [], member_exist: 1,
                login_config: { is_username: 1, is_mobile: 0, is_auth_register: 0, is_force_access_user_info: 0, is_bind_mobile: 0, agreement_show: 0 }
            })
            if (endpoint === 'member/member') return send({ member_id: 900000916, nickname: '测试会员', mobile: '13800000000' })
            if (endpoint === 'recycle_quote_spider/search') {
                const keyword = url.searchParams.get('keyword') || ''
                const current = Number(url.searchParams.get('page') || 1)
                searches.push({ keyword, current })
                if (failNext) { failNext = false; return send({}, 0, '网络暂不可用') }
                const matched = keyword === '不存在的型号' ? [] : results
                await new Promise(resolve => setTimeout(resolve, 150))
                return send({ data: matched.slice((current - 1) * 12, current * 12), total: matched.length, last_page: Math.ceil(matched.length / 12), current_page: current })
            }
            if (/recycle_quote_spider\/item\/\d+/.test(endpoint)) return send({
                id: 101, source_id: 1, category_id: 1, name: '苹果靓机报价', title: '苹果靓机报价', category_path: '苹果',
                is_image_quote: 0, rows: [results[0], { ...results[1], id: 99, model_name: 'iPhone 16 Pro' }], price_date: '2026-09-16'
            })
            return send([])
        })
        await page.goto(base + '/addon/recycle_quote_spider/pages/price/search', { waitUntil: 'domcontentloaded', timeout: 60000 })
        await page.locator('.quote-search-page').waitFor({ timeout: 60000 })
        if (process.env.QUOTE_SEARCH_DEBUG) {
            console.log('Initial URL:', page.url(), 'Body:', (await page.locator('body').innerText()).slice(0, 1500), 'Errors:', errors)
            await page.screenshot({ path: path.join(output, 'initial.png'), fullPage: true })
        }
        await page.locator('.empty-title').filter({ hasText: '搜型号' }).waitFor()
        assert.equal(searches.length, 0)
        console.log('PASS Empty search opens without querying the whole price catalog')
        const input = page.locator('.search-input input')
        await input.fill('17 Pro Max')
        await page.locator('.search-button').click()
        await page.locator('.quote-result').first().waitFor()
        assert.equal(await page.locator('.quote-result').count(), 12)
        assert.ok((await page.locator('.result-count').innerText()).includes('14'))
        assert.ok((await page.locator('.quote-result').last().innerText()).includes('苹果问题机报价'))
        console.log('PASS Model search displays prices from multiple quotation sheets')
        await page.screenshot({ path: path.join(output, 'mobile-results.png'), fullPage: false })
        for (const width of [320, 390, 768, 1280]) {
            await page.setViewportSize({ width, height: 844 })
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'Horizontal overflow at ' + width)
            await page.screenshot({ path: path.join(output, 'width-' + width + '.png'), fullPage: false })
        }
        console.log('PASS No horizontal page overflow at 320/390/768/1280px')
        await page.setViewportSize({ width: 390, height: 844 })
        await page.locator('.load-more').click()
        await page.locator('.end-note').waitFor()
        assert.equal(await page.locator('.quote-result').count(), 14)
        console.log('PASS Pagination loads remaining matches')
        await page.locator('.clear-button').click()
        await page.locator('.history-item').first().waitFor()
        assert.ok((await page.locator('.history-item').innerText()).includes('上次 14 条'))
        await page.reload({ waitUntil: 'domcontentloaded' })
        await page.locator('.history-item').first().waitFor()
        const before = searches.length
        await page.locator('.history-item').first().click()
        await page.locator('.quote-result').first().waitFor()
        assert.ok(searches.length > before)
        console.log('PASS History survives page reload and performs a fresh query')
        await input.fill('不存在的型号')
        await page.locator('.search-button').click()
        await page.locator('.empty-title').filter({ hasText: '没有找到' }).waitFor()
        console.log('PASS No-match state is explicit')
        failNext = true
        await input.fill('17 Pro Max')
        await input.press('Enter')
        await page.locator('.error-state').waitFor()
        await page.locator('.error-state .secondary-button').click()
        await page.locator('.quote-result').first().waitFor()
        console.log('PASS Keyboard search and failure retry work')
        await page.locator('.detail-button').first().click()
        await page.waitForURL(/show_price/)
        console.log('Detail URL:', page.url())
        assert.equal(decodeURIComponent(new URL(page.url()).searchParams.get('model_name') || ''), 'iPhone 17 Pro Max')
        await page.locator('.price-content .model-name').first().waitFor()
        assert.ok((await page.locator('.price-content .model-name').allInnerTexts()).every(name => name.includes('17 Pro Max')))
        console.log('PASS Quote detail receives and filters to the selected model')
        await page.goBack({ waitUntil: 'domcontentloaded' })
        await page.locator('.clear-button').click()
        await page.locator('.section-heading .icon-button').click()
        await page.getByText('确定', { exact: true }).click()
        await page.locator('.empty-note').filter({ hasText: '还没有搜索记录' }).waitFor()
        console.log('PASS Search history can be cleared after confirmation')
        assert.deepEqual(errors, [])
        console.log('PASS No page runtime errors')
        console.log('Screenshots: ' + output)
    } finally { await browser.close() }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
