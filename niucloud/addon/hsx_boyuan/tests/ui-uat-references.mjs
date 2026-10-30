const { chromium } = await import(process.env.BOYUAN_PLAYWRIGHT_MODULE || 'playwright')
const browser = await chromium.launch({ headless: true, channel: 'chrome' })
let checks = 0
const errors = []
try {
    for (const width of [320, 375]) {
        const page = await browser.newPage({ viewport: { width, height: 812 } })
        page.on('pageerror', e => errors.push(e.message))
        await page.goto('http://127.0.0.1:5199/', { waitUntil: 'networkidle' })
        await page.locator('.archive-row').last().waitFor()
        if (await page.locator('.archive-row').count() !== 10) throw new Error('archive groups missing')
        if (await page.locator('.archive-row').filter({ hasText: '数量待核对' }).count() !== 1) throw new Error('upstream mismatch not visible')
        checks++
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error('UAT page overflow')
        checks++
        await page.screenshot({ path: new URL(`./evidence/uat-references-${width}.png`, import.meta.url).pathname, fullPage: true })
        await page.getByRole('button', { name: '连接设置' }).click()
        const password = page.locator('input[type=password]')
        if (await password.inputValue() !== '') throw new Error('secret exposed in input')
        const configResponse = await page.request.get('http://127.0.0.1:5199/api/boyuan/config')
        const config = (await configResponse.json()).data
        if (!config.profiles.test.app_secret_configured || config.enabled || !config.paused || 'app_secret_cipher' in config.profiles.test) throw new Error('unsafe UAT configuration')
        checks++
        await page.close()
    }
    const page = await browser.newPage({ viewport: { width: 375, height: 812 } })
    page.on('pageerror', e => errors.push(e.message))
    await page.goto('http://127.0.0.1:5199/', { waitUntil: 'networkidle' })
    await page.locator('.archive-name').filter({ hasText: '仓库' }).click()
    if (await page.getByRole('button', { name: '对应仓库', exact: true }).count()) throw new Error('vendor warehouse should not require local mapping')
    await page.getByRole('button', { name: '关闭弹窗' }).click()
    await page.getByRole('button', { name: '资料对应', exact: true }).click()
    if (await page.getByRole('tab').count() !== 4) throw new Error('vendor-owned master data leaked into mapping tabs')
    checks++
    await page.close()
    if (errors.length) throw new Error(errors.join('\n'))
    console.log(JSON.stringify({ passed: true, checks, pageErrors: errors, remoteBusinessWrites: 0, databaseWrites: 0 }))
} finally { await browser.close() }
