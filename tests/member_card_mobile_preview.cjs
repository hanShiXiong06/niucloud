'use strict'
// UI 验收专用：只绑定本机，所有业务数据在内存里，不连接数据库，不代理任何线上接口。
// 先把 H5 编译到 /tmp/member-card-ui-h5-20260909，再运行本脚本。
const http = require('node:http'), fs = require('node:fs'), path = require('node:path')
const dir = path.resolve(process.env.MEMBER_CARD_PREVIEW_DIR || '/tmp/member-card-ui-h5-20260909')
if (!fs.existsSync(path.join(dir, 'index.html'))) throw new Error('请先完成隔离 H5 构建')
const now = Math.floor(Date.now() / 1000)
const site = { site_id: 1, site_name: '会员卡本地验收 · 模拟数据', site_addons: [], app: [] }
const account = { id: 1, name: '门店微信', type: 'wechat', type_name: '微信', status: 1, is_default: 1 }
let config = { finance_provider: 'local', default_capital_account_id: 1, allow_receivable: 0, capital_account_options: [account, { id: 2, name: '门店支付宝', type: 'alipay', status: 1 }, { id: 3, name: '对公银行账户', type: 'bank', status: 1 }, { id: 4, name: '备用现金账户', type: 'cash', status: 0 }], inventory_available: 0, inventory_mode: 'none', inventory_mode_effective: 'none', inventory_warning: '未接入 ERP，仅记录服务，不扣耗材库存。', inventory_warehouses: [] }
const item = { id: 1, card_id: 1, item_id: 1, item_name: '高清贴膜服务', usage_mode: 'limited', total_times: 10, used_times: 2, remaining_times: 8, binding_mode: 'member', daily_limit: 1, standard_consumable_qty: 1, consumable_name: '高清膜', consumable_unit: '张', bound_imei: '', bound_model: '' }
const product = { id: 1, product_name: '门店贴膜服务卡', sale_price: '99.00', market_price: '129.00', status: 'enabled', status_text: '已启用', effective_mode: 'immediate', validity_mode: 'duration', duration_value: 12, duration_unit: 'month', validity_text: '12个月有效', item, item_name: item.item_name, total_times: 10, usage_mode: 'limited', binding_mode: 'member' }
const card = { id: 1, ...item, items: [item], product_name: product.product_name, status: 'active', status_text: '有效', validity_text: '有效期至 2027-09-09', card_no: 'MC-TEST-001', issuer_name: '测试店员', create_at: now - 86400, order_id: 1, available: true }
const member = { member_id: 1, display_name: '测试客户', nickname: '测试客户', real_name: '测试客户', mobile: '13800000001', mobile_masked: '138****0001', member_no: 'TEST001', card_count: 1, available_card_count: 1, holder_name: '测试客户', holder_mobile: '13800000001', cards: [card], avatar: '' }
const orders = [
    { id: 1, product_name: product.product_name, holder_name: '测试客户', holder_mobile: '13800000001', order_amount: '99.00', paid_amount: '99.00', finance_status: 'settled', business_status: 'active', issuer_name: '测试店员', capital_account_id: 1, capital_account_name: '门店微信', settlement_mode: 'immediate', order_no: 'MO-TEST-001', create_at: now, card, card_items: [item], finance_links: [], operation_logs: [], remark: '' },
    { id: 2, product_name: '终身贴膜卡', holder_name: '待处理客户', holder_mobile: '13800000002', order_amount: '199.00', paid_amount: '0.00', finance_status: 'failed', business_status: 'failed', issuer_name: '测试店员', capital_account_id: 1, capital_account_name: '', settlement_mode: 'immediate', order_no: 'MO-TEST-002', create_at: now - 1800, card_items: [], last_error: '所选收款账户已停用，请核对实际到账账户后重试。' }
]
const redemption = { id: 1, card_id: 1, redeem_no: 'MR-TEST-001', holder_name: '测试客户', holder_mobile: '13800000001', product_name: product.product_name, item_name: item.item_name, redeem_times: 1, before_times: 9, after_times: 8, inventory_status: 'skipped', inventory_mode: 'none', status: 'success', operator_name: '测试店员', create_at: now, recognized_amount: '9.90', actual_consumable_qty: 1, consumable_unit: '张' }
const list = (rows, url) => { const page = Number(url.searchParams.get('page') || 1), limit = Number(url.searchParams.get('limit') || 15); return { total: rows.length, data: rows.slice((page - 1) * limit, page * limit), current_page: page, per_page: limit } }
async function route(req, url, body) {
    const p = url.pathname.replace('/adminapi/', '')
    if (p === 'login/config') return { is_site_captcha: 0 }
    if (p === 'login/site' || p === 'login/mobile/site') {
        if (p === 'login/site' && url.searchParams.get('username') !== 'mock') return { __error: '验收账号请填写 mock（仅本机模拟）' }
        return { token: 'member-card-ui-preview-only', site_id: 1, userinfo: { uid: 1, username: 'mock' }, site_info: site }
    }
    if (['auth/site', 'site'].includes(p)) return site
    if (p === 'sys/web/website') return { site_name: site.site_name, title: site.site_name }
    if (/nav|menu/.test(p)) return []
    if (p === 'member_card/dashboard') return { card_sale_amount: '298.00', actual_received_amount: '99.00', pending_receivable_amount: '199.00', active_card_count: 28, redemption_count: 12, recognized_amount: '118.80', refund_amount: '0.00' }
    if (p === 'member_card/config/capital_account') {
        const id = Number(body.id) || Math.max(0, ...config.capital_account_options.map(a => a.id)) + 1
        const entry = { ...body, id, status: Number(body.status), is_default: Number(body.is_default) }
        if (entry.is_default) { config.default_capital_account_id = id; config.capital_account_options.forEach(a => a.is_default = 0) }
        config.capital_account_options = [...config.capital_account_options.filter(a => a.id !== id), entry]
        return config
    }
    if (p.startsWith('member_card/config/capital_account/') && req.method === 'DELETE') {
        config.capital_account_options = config.capital_account_options.filter(a => a.id !== Number(p.split('/').pop()))
        if (!config.capital_account_options.some(a => a.id === config.default_capital_account_id)) config.default_capital_account_id = 0
        return config
    }
    if (p === 'member_card/config') { if (req.method === 'POST') config = { ...config, ...body }; return config }
    if (p === 'member_card/product/lists') return list([product], url)
    if (p === 'member_card/product/options') return [product]
    if (p === 'member_card/product/1') return product
    if (p === 'member_card/member/options') return [member]
    if (p === 'member_card/member/lists') return list([member], url)
    if (p === 'member_card/member/1/info') return { member, cards: [card], redemptions: [{ ...redemption, inventory_status: 'not_managed', occurred_at: now }] }
    if (p === 'member_card/member/1/cards') return [card]
    if (p === 'member_card/order/lists') return list(orders, url)
    if (/^member_card\/order\/\d+$/.test(p)) return orders.find(o => o.id === Number(p.split('/').pop())) || orders[0]
    if (p === 'member_card/card/search') return { candidates: [member] }
    if (p === 'member_card/redemption/lists') return list([redemption], url)
    if (p === 'member_card/card/1') return { ...card, member }
    if (p.startsWith('member_card/')) return { __error: '本地视觉验收暂不执行此业务操作，请使用自动化脚本测试。' }
    return {}
}
const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1:18779')
    // 仅放行当前已安装 uview-plus 的官方图标字体，业务连接仍全部限制在本机。
    res.setHeader('Content-Security-Policy', "default-src 'self'; connect-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; font-src 'self' data: https://at.alicdn.com; media-src 'self' blob:")
    res.setHeader('Cache-Control', 'no-store')
    if (url.pathname.startsWith('/adminapi/')) {
        let raw = ''; for await (const chunk of req) raw += chunk
        let body = {}; try { body = JSON.parse(raw) } catch (_) { body = Object.fromEntries(new URLSearchParams(raw)) }
        let data
        try { data = await route(req, url, body) } catch (_) { data = { __error: '模拟接口处理失败' } }
        res.setHeader('Content-Type', 'application/json; charset=utf-8')
        res.end(JSON.stringify({ code: data?.__error ? 0 : 1, msg: data?.__error || '操作成功', data: data?.__error ? null : data }))
        console.log(req.method, url.pathname) // 不记录密码、手机号或请求体。
        return
    }
    const filename = path.resolve(dir, '.' + url.pathname.replace(/^\/adminapp/, ''))
    if (!filename.startsWith(dir + path.sep) && filename !== dir) { res.writeHead(403); res.end(); return }
    const extension = path.extname(filename)
    const target = fs.existsSync(filename) && fs.statSync(filename).isFile() ? filename : !extension ? path.join(dir, 'index.html') : null
    if (!target) { res.writeHead(404); res.end(); return }
    const mime = { '.html': 'text/html', '.js': 'application/javascript', '.css': 'text/css', '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.ttf': 'font/ttf' }
    res.setHeader('Content-Type', mime[path.extname(target)] || 'application/octet-stream')
    fs.createReadStream(target).pipe(res)
})
server.listen(18779, '127.0.0.1', () => console.log('本地模拟验收：http://127.0.0.1:18779/adminapp/addon/hsx_member_card/pages/dashboard/index'))
