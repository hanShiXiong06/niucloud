const { chromium } = await import(process.env.BOYUAN_PLAYWRIGHT_MODULE || 'playwright')
import { mkdir } from 'node:fs/promises'
const out = new URL('./evidence/', import.meta.url)
await mkdir(out, { recursive: true })
const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const errors = []
let checks = 0
try {
    for (const [width, height] of [[320, 640], [375, 812], [430, 932], [1024, 768]]) {
        const page = await browser.newPage({ viewport: { width, height } })
        page.on('pageerror', error => errors.push(error.message))
        await page.goto('http://127.0.0.1:5199/', { waitUntil: 'networkidle' })
        await page.getByText('博远对接', { exact: true }).waitFor()
        await page.locator('.task').first().waitFor()
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`horizontal overflow ${width}`)
        checks++
        await page.screenshot({ path: new URL(`list-${width}.png`, out).pathname, fullPage: true })
        await page.locator('.task').first().click()
        await page.getByText('任务详情', { exact: true }).waitFor()
        await page.locator('.detail-state').waitFor()
        await page.screenshot({ path: new URL(`detail-${width}.png`, out).pathname })
        const footer = await page.locator('.sheet-footer').boundingBox()
        if (!footer || footer.y + footer.height > height + 1) throw new Error(`footer occluded ${width}`)
        checks++
        await page.getByRole('button', { name: '关闭弹窗' }).click()
        await page.getByRole('button', { name: '连接设置' }).click()
        await page.getByText('连接设置', { exact: true }).waitFor()
        await page.screenshot({ path: new URL(`config-${width}.png`, out).pathname })
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`config overflow ${width}`)
        checks++
        await page.close()
    }
    for (const fixture of [{ scenario: 'success', mapped: false }, { scenario: 'partial', mapped: true }]) {
        const response = await fetch('http://127.0.0.1:5199/api/demo/purchase', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(fixture) })
        const body = await response.json()
        if (body.code !== 1) throw new Error('cannot arrange isolated UI fixture: ' + body.msg)
    }
    await new Promise(resolve => setTimeout(resolve, 6000))
    const page = await browser.newPage({ viewport: { width: 375, height: 812 } })
    page.on('pageerror', error => errors.push(error.message))
    await page.goto('http://127.0.0.1:5199/', { waitUntil: 'networkidle' })
    await page.locator('.task').filter({ hasText: '商品对应' }).first().click()
    await page.locator('.detail-state').waitFor()
    await page.locator('.quick-maps .mapping-choice').filter({ hasText: '商品' }).first().click()
    await page.getByText('来源名称', { exact: true }).waitFor()
    if (await page.locator('.popup-overlay').count() !== 1) throw new Error('nested mapping popup')
    checks++
    await page.getByRole('button', { name: '选择博远档案', exact: true }).click()
    await page.locator('.reference-lookup .mapping-choice').first().click()
    await page.getByRole('button', { name: '保存对应', exact: true }).click()
    await page.getByText('任务详情', { exact: true }).waitFor()
    await page.getByRole('button', { name: '补齐后重试', exact: true }).click()
    await page.waitForFunction(() => document.querySelector('.detail-state .state')?.textContent?.includes('模拟完成'), undefined, { timeout: 30000 })
    checks++
    await page.screenshot({ path: new URL('mapping-compensation-375.png', out).pathname })
    await page.getByRole('button', { name: '关闭弹窗' }).click()
    await page.getByRole('button', { name: '刷新', exact: true }).click()
    await page.locator('.task').filter({ hasText: '部分成功' }).first().click()
    await page.getByRole('button', { name: '记录人工处理', exact: true }).click()
    await page.locator('.u-textarea').fill('已在隔离演示账套核对人工处理，保留待核验状态')
    await page.getByRole('button', { name: '保存人工记录', exact: true }).click()
    await page.locator('.detail-state').getByText('人工处理待核验', { exact: true }).waitFor()
    checks++
    await page.locator('.preview-toast').waitFor({ state: 'detached' })
    await page.locator('.sheet-body').evaluate(element => { element.scrollTop = 0 })
    await page.screenshot({ path: new URL('manual-pending-375.png', out).pathname })
    await page.close()
    if (errors.length) throw new Error(errors.join('\n'))
    console.log(JSON.stringify({ passed: true, checks, viewports: [320, 375, 430, 1024], pageErrors: errors, externalWrites: 0 }))
} finally { await browser.close() }
