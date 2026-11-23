// A private viewer for the two generated, synthetic acceptance artifacts. No database or business routes.
import { createServer } from 'node:http'
import { existsSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const port = 5287
const root = new URL('../../../../deliverables/boyuan-local-cycle-20261118/', import.meta.url)
const snapshot = JSON.parse(readFileSync(new URL('local-cycle.json', root), 'utf8'))
if (snapshot.passed !== true || snapshot.external_requests !== 0 || !snapshot.steps?.sale?.sale_no) throw new Error('请先重新生成并校验本地快照')
const html = readFileSync(fileURLToPath(new URL('本地验收结果.html', root)))
const json = JSON.stringify(snapshot, null, 2)
const persistentPath = new URL('site-100003.json', root)
const live = existsSync(persistentPath) ? JSON.parse(readFileSync(persistentPath,'utf8')) : null
if (live && !(live.verified && live.site_id === 100003 && !live.connector_enabled && live.connector_paused && live.mock_tasks.every(x => x.mode === 'mock' && x.status === 'simulated'))) throw new Error('站点验收资料未经验证')
const esc = str => String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))
const persistentHtml = live && `<!doctype html><html lang="zh-CN"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>测试 2 · 本站单据验收</title>
<style>body{font:16px/1.65 system-ui,sans-serif;color:#233547;background:#f4f6f9;padding:20px;margin:auto;max-width:760px}header,section{background:white;padding:18px 22px;border:1px solid #dce4eb;border-radius:12px;margin-bottom:10px}header{background:#123e50;color:white}h1{margin:3px 0;font-size:26px}h2{font-size:18px;margin:0 0 7px}p{margin:5px 0}b{color:#16594c}code{word-break:break-all}a{color:#135286}</style>
<header><small>只读快照 · 本机站点 100003（测试 2）</small><h1>同一台设备，从入库到出库</h1><p>以下 ERP 单据保留在本站后台，不需要再开一单。复核时间：${esc(live.verified_at)}</p></header>
<section><h2>① ERP 采购入库</h2><p><b>采购单号</b> <code>${esc(live.purchase.no)}</code> · #${esc(live.purchase.id)}</p><p>供应商：${esc(live.purchase.supplier)}；串号：<code>${esc(live.imei)}</code>。</p><p>去向：${esc(live.warehouse)} / ${esc(live.location)}。</p></section>
<section><h2>② ERP 同行销售出库</h2><p><b>销售单号</b> <code>${esc(live.sale.no)}</code> · #${esc(live.sale.id)}</p><p>同一台设备售给 ${esc(live.sale.customer)}；库存流水：入库 → 已售。</p></section>
<section><h2>③ 核销采购应付、销售应收</h2><p>应付 ¥${esc(live.purchase.amount)}，已付 ¥${esc(live.purchase.settled)}。应收 ¥${esc(live.sale.amount)}，已收 ¥${esc(live.sale.settled)}。</p><p>两笔均为本机验收记账，不发生真实转账。</p></section>
<section><h2>④ 博远插件的模拟回执</h2>${live.mock_tasks.map(t=>`<p>任务 #${esc(t.id)} · ${t.kind==='purchase'?'采购入库':'批发出库'} · <code>${esc(t.external_no)}</code> · 模拟完成</p>`).join('')}<p>没有连接真实博远。模拟投递已关闭并暂停，本站业务单仍可查看。</p></section><p><a href="/site-100003.json">查看本站核对数据</a> · <a href="/">查看另外一次隔离接口验收</a></p></html>`
const assets = new Map([['/', ['text/html',html]], ['/local-cycle.json',['application/json',json]]])
if(live){ assets.set('/site-100003',['text/html',persistentHtml]); assets.set('/site-100003.json',['application/json',JSON.stringify(live,null,2)]) }
const server = createServer((req, res) => {
    res.setHeader('Cache-Control', 'no-store')
    res.setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
    res.setHeader('X-Content-Type-Options', 'nosniff')
    res.setHeader('Referrer-Policy', 'no-referrer')
    if (req.headers.host !== '127.0.0.1:' + port) return res.writeHead(403).end()
    if (!['GET', 'HEAD'].includes(req.method)) return res.writeHead(405, { Allow: 'GET, HEAD' }).end()
    const path = req.url
    if (!assets.has(path)) return res.writeHead(404).end()
    const [mime,body] = assets.get(path)
    res.writeHead(200, { 'Content-Type': mime + '; charset=utf-8' })
    res.end(req.method === 'HEAD' ? undefined : body)
})
server.on('error', e => { console.error('本机快照服务未启动：' + e.code); process.exit(1) })
server.listen(port, '127.0.0.1', () => console.log('只读隔离验收快照：http://127.0.0.1:' + port + '/'))
