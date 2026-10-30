const { chromium } = await import(process.env.BOYUAN_PLAYWRIGHT_MODULE || 'playwright')
import { mkdir } from 'node:fs/promises'
await mkdir(new URL('./evidence/', import.meta.url), { recursive: true })
const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const kinds = ['product', 'warehouse', 'staff', 'customer', 'supplier', 'method', 'account', 'location']
const labels = ['商品', '仓库', '人员', '客户', '供应商', '收付方式', '资金账户', '库位']
let checks = 0
const errors = []
try {
    for (const width of [320, 375, 430]) {
        const page = await browser.newPage({ viewport: { width, height: 812 } })
        page.on('pageerror', error => errors.push(error.message))
        const requests = []
        await page.route('**/api/boyuan/mappings?**', async route => {
            const query = new URL(route.request().url()).searchParams
            const kind = query.get('kind'), keyword = query.get('keyword') || '', next = Number(query.get('page') || 1)
            requests.push({ kind, keyword, page: next })
            if (!kinds.includes(kind)) throw new Error('mapping request missing category')
            if (keyword === '慢查询') await new Promise(resolve => setTimeout(resolve, 800))
            const total = keyword ? 1 : kind === 'product' ? 51 : 1
            const length = Math.min(50, total - (next - 1) * 50)
            const data = Array.from({ length: Math.max(0, length) }, (_, i) => ({ id: kinds.indexOf(kind) * 100 + (next - 1) * 50 + i + 1, kind, local_ref: `${kind}:${i + 1}`, local_name: `${labels[kinds.indexOf(kind)]}${keyword || '本地资料'}${(next - 1) * 50 + i + 1}`, external_id: `EXT_${kind}_${i}`, external_name: `${labels[kinds.indexOf(kind)]}博远档案`, external_code: `${kind.toUpperCase()}_CODE`, extra_json: '{"unit_code":"TAI"}', revision: 1 }))
            await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ code: 1, data: { total, data } }) })
        })
        await page.goto('http://127.0.0.1:5199/', { waitUntil: 'networkidle' })
        await page.getByRole('button', { name: '资料对应', exact: true }).click()
        await page.locator('.mapping-row').first().waitFor()
        if (await page.getByRole('tab').count() !== 4) throw new Error('only product, staff and party roles should need mapping in OpenAPI')
        if (await page.locator('.mapping-row').count() !== 50) throw new Error('product first page not isolated')
        checks++
        await page.getByRole('button', { name: '加载更多', exact: true }).click()
        await page.waitForFunction(() => document.querySelectorAll('.mapping-row').length === 51)
        checks++
        await page.getByRole('tab', { name: '人员', exact: true }).click()
        await page.waitForFunction(() => document.querySelectorAll('.mapping-row').length === 1 && document.querySelector('.mapping-row')?.textContent.includes('人员本地资料'))
        if (requests.at(-1).kind !== 'staff') throw new Error('staff not filtered on server')
        checks++
        const search = page.locator('.search input')
        await search.fill('人员筛选'); await search.press('Enter')
        await page.locator('.mapping-row').filter({ hasText: '人员筛选' }).waitFor()
        await page.getByRole('tab', { name: '商品', exact: true }).click()
        if (await page.locator('.mapping-row').count() !== 51 || await search.inputValue() !== '') throw new Error('product pagination/search not retained separately')
        await page.getByRole('tab', { name: '人员', exact: true }).click()
        if (await search.inputValue() !== '人员筛选') throw new Error('staff search lost')
        checks++
        await page.getByRole('button', { name: '新增对应', exact: true }).click()
        await page.locator('.mapping-sheet .sheet-title').filter({ hasText: '人员对应' }).waitFor()
        if (await page.locator('.mapping-sheet select').count()) throw new Error('category select still present')
        await page.getByRole('button', { name: '关闭弹窗' }).click()
        await page.locator('.mapping-row').first().click()
        await page.locator('.mapping-sheet .sheet-title').filter({ hasText: '人员对应' }).waitFor()
        checks++
        await page.getByRole('button', { name: '关闭弹窗' }).click()
        await search.fill('慢查询'); await search.press('Enter')
        await page.waitForTimeout(50)
        await search.fill('新查询'); await search.press('Enter')
        await page.locator('.mapping-row').filter({ hasText: '新查询' }).waitFor()
        await page.waitForTimeout(900)
        if (await page.locator('.mapping-row').filter({ hasText: '慢查询' }).count()) throw new Error('late search overwrote current rows')
        checks++
        for (const label of ['客户', '供应商']) await page.getByRole('tab', { name: label, exact: true }).click()
        if (await page.getByRole('tab', { name: '仓库', exact: true }).count() || await page.getByRole('tab', { name: '资金账户', exact: true }).count()) throw new Error('vendor-owned references incorrectly require manual mapping')
        checks++
        await page.getByRole('tab', { name: '人员', exact: true }).click()
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`mapping layout overflow at ${width}`)
        checks++
        const current = await page.evaluate(async () => (await (await fetch('/api/boyuan/config')).json()).data)
        current.profiles[current.mode].connection_key = 'e'.repeat(32)
        await page.route(url => url.pathname === '/api/boyuan/config', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ code: 1, data: current }) }))
        await page.getByRole('button', { name: '刷新', exact: true }).click()
        await page.waitForFunction(() => document.querySelector('.search input')?.value === '' && document.querySelector('.mapping-row')?.textContent.includes('人员本地资料'))
        if (requests.at(-1).keyword !== '') throw new Error('old connection search context leaked')
        checks++
        await page.screenshot({ path: new URL(`./evidence/mapping-tabs-${width}.png`, import.meta.url).pathname, fullPage: true })
        await page.waitForLoadState('networkidle')
        await page.close()
    }
    if (errors.length) throw new Error(errors.join('\n'))
    console.log(JSON.stringify({ passed: true, checks, pageErrors: errors, remoteWrites: 0, databaseWrites: 0 }))
} finally { await browser.close() }
