import QRCode from 'qrcode'

/** A4 回收单：只输出标签摘要，不输出长质检、原始 info、备注或付款账户。 */
export const escapePrintText = (value: unknown): string => String(value ?? '')
  .replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char] as string))

export const formatPrintTime = (value: unknown): string => {
  if (!value || value === '0') return '—'
  const text = String(value).trim()
  if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(text)) return text.replace('T', ' ').slice(0, 19)
  const numeric = Number(text)
  const date = new Date(Number.isFinite(numeric) ? (numeric < 1e12 ? numeric * 1000 : numeric) : text)
  if (Number.isNaN(date.getTime())) return '—'
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

const deviceState = (device: Record<string, any>): string => {
  if (Number(device.status) === 6 || Number(device.confirm_status) === 2) return '拒绝 / 退回处理'
  if (Number(device.status) === 9 || device.dispose_type === 'consign') return '已转代卖'
  if (Number(device.pay_status) === 1) return '已打款'
  if (Number(device.status) === 5 || Number(device.confirm_status) === 1) {
    return Number(device.pay_status) === 2 ? '已确认 · 部分打款' : '已确认 · 待打款'
  }
  return device.status_name || '待处理'
}

/** 二维码在浏览器本地生成，不调用外部服务、不上传客户信息、不占服务器存储。 */
export const prepareRecycleOrderPrint = async (order: Record<string, any>): Promise<Record<string, any>> => ({
  ...order,
  devices: await Promise.all((Array.isArray(order.devices) ? order.devices : []).map(async (device: Record<string, any>) => {
    if (!device.print_summary || typeof device.print_summary !== 'object') throw new Error('未取得设备打印摘要，请更新回收插件后端后重试')
    const url = String(device.print_device_url || '')
    if (!/^https?:\/\//i.test(url)) throw new Error('未取得设备二维码链接，请更新回收插件后端后重试')
    const print_qrcode = await QRCode.toDataURL(url, { errorCorrectionLevel: 'M', margin: 4, scale: 8 })
    return { ...device, print_qrcode }
  })),
})

const deviceSummary = (device: Record<string, any>): string => {
  const summary = device.print_summary || {}
  const item = (key: string, label: string): string => {
    let value = String(summary[key] ?? '')
    // 不把缺少字典的纯编号当成容量/颜色/包装打印；不猜测它对应哪个选项。
    if (['capacity', 'color', 'package_type', 'condition_grade'].includes(key) && /^-?\d+(?:[,、]\d+)*$/.test(value)) value = '待核对'
    if (!value || value === '-') value = '未录入'
    if (key === 'battery' && /^\d+(?:\.\d+)?$/.test(value)) value += '%'
    if (key === 'battery' && /^\d+$/.test(String(summary.battery_cycle ?? ''))) value += ` / ${summary.battery_cycle}次`
    return `<span><span class="spec-label">${label}</span> ${escapePrintText(value)}</span>`
  }
  return `<div class="spec-line">${item('capacity', '容量')}${item('color', '颜色')}${item('battery', '电池')}</div>
    <div class="spec-line">${item('warranty_info', '保修')}${item('package_type', '包装')}</div>`
}

export const buildRecycleOrderPrintHtml = (order: Record<string, any>, printedAt = new Date()): string => {
  const text = (value: unknown) => escapePrintText(value === '' || value == null ? '—' : value)
  const devices = Array.isArray(order.devices) ? order.devices : []
  const pageCount = Math.max(1, Math.ceil(devices.length / 20))
  const renderDevice = (device: Record<string, any> | undefined, index: number): string => {
    if (!device) return '<td class="empty-cell"></td>'
    const quote = Number(device.final_price)
    const identifiers = [
      ['IMEI', device.imei], ['SN', device.sn], ['自编号', device.user_sn],
    ].filter(([, value]) => value)
    const qrcode = /^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(String(device.print_qrcode || ''))
      ? `<img class="device-qr" src="${device.print_qrcode}" alt="第 ${index + 1} 台设备二维码"><div class="qr-caption">扫码查看设备</div>`
      : '<span class="qr-caption">二维码未生成</span>'
    return `<td><div class="device-entry"><div class="device-info">
      <div class="model"><span class="row-number">${index + 1}.</span> <strong>${text(device.model)}</strong></div>
      <div class="identities">${identifiers.length ? identifiers.map(([label, value]) => `<span class="identity"><span>${label}</span> ${text(value)}</span>`).join('') : '标识未录入'}</div>
      <div class="device-specs">${deviceSummary(device)}</div>
      <div class="device-bottom"><span>${text(deviceState(device))}</span><span class="quote">回收报价（元）<span class="amount">${Number.isFinite(quote) && quote > 0 ? quote.toFixed(2) : ''}</span></span></div>
      </div><div class="qr-cell">${qrcode}</div></div></td>`
  }
  const pages = Array.from({ length: pageCount }, (_, pageIndex) => {
    const offset = pageIndex * 20
    const chunk = devices.slice(offset, offset + 20)
    // 每页按左栏1–10、右栏11–20排列；不用把二维码压到难扫的单栏行高。
    const rowCount = Math.min(10, chunk.length)
    const rows = Array.from({ length: rowCount }, (_, row) =>
      `<tr>${renderDevice(chunk[row], offset + row)}${renderDevice(chunk[row + 10], offset + row + 10)}</tr>`
    ).join('')
    const fact = (label: string, value: unknown) => `<span><span class="fact-label">${label}</span>${text(value)}</span>`
    return `<section class="print-page"><header><div class="heading"><h1>回收单</h1><strong>订单号：${text(order.order_no)}</strong><span>当前状态：${text(order.status_name)}</span></div>
      <div class="facts">${fact('客户', order.customer_name || order.recycleUserAddress?.name || order.member?.nickname)}${fact('电话', order.customer_phone || order.recycleUserAddress?.mobile || order.member?.mobile)}${fact('送达', order.delivery_type_name)}${fact('来源', order.order_source === 'agent' ? ['员工代下单', order.agent_name].filter(Boolean).join(' · ') : '客户下单')}</div>
      <div class="facts time">${fact('创建', formatPrintTime(order.create_at))}${fact('签收', formatPrintTime(order.sign_at))}${order.complete_at ? fact('完成', formatPrintTime(order.complete_at)) : ''}${order.pay_time ? fact('打款', formatPrintTime(order.pay_time)) : ''}</div>
      <div class="section-caption"><span>设备基础清单 · 二维码供工作人员登录管理端查看</span><span>本页 ${chunk.length} 台 · 共 ${devices.length} 台${Number(order.count) > 0 ? ` / 申报 ${Number(order.count)} 台` : ''}</span></div></header>
      <table aria-label="设备基础清单"><colgroup><col style="width:50%"><col style="width:50%"></colgroup><tbody>${rows || '<tr><td colspan="2">尚未录入设备</td></tr>'}</tbody></table>
      <footer><span>本单为业务记录，不作为收付款或退货签收凭证；拒绝出售仍需按退货流程交还。</span><span>第 ${pageIndex + 1} / ${pageCount} 页</span><span class="printed-time">打印时间：${text(formatPrintTime(printedAt.getTime()))}</span></footer>
      </section>`
  }).join('')
  return `<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">
    <title>回收单-${text(order.order_no)}</title>
    <style>
      @page { size: A4 portrait; margin: 10mm 8mm; }
      * { box-sizing: border-box; }
      body { margin: 0; color: #111; font: 8.5pt/1.35 Arial, "Microsoft YaHei", "PingFang SC", sans-serif; }
      .toolbar { position: sticky; top: 0; padding: 12px 20px; background: #f3f5f7; border-bottom: 1px solid #ddd; font-size: 13px; display: flex; align-items: center; gap: 12px; }
      .toolbar button { padding: 7px 16px; cursor: pointer; border: 1px solid #bbb; border-radius: 4px; background: #fff; }
      main { max-width: 194mm; margin: 12px auto; }
      .print-page { margin-bottom: 8mm; }
      h1 { font-size: 17pt; margin: 0; letter-spacing: 1pt; white-space: nowrap; }
      .heading { display: flex; align-items: center; justify-content: space-between; gap: 4mm; border-bottom: 1pt solid #222; padding-bottom: 1.5mm; font-size: 9pt; }
      .heading strong { overflow-wrap: anywhere; }
      .facts { display: flex; flex-wrap: wrap; gap: 1mm 5mm; margin-top: 1.5mm; }
      .facts > span { overflow-wrap: anywhere; } .fact-label { color: #555; margin-right: 1.5mm; }
      .time { font-variant-numeric: tabular-nums; font-size: 8pt; } .time > span { white-space: nowrap; }
      .section-caption { display: flex; justify-content: space-between; gap: 3mm; margin: 2mm 0 1mm; font-size: 7.5pt; color: #555; }
      table { width: 100%; border-collapse: collapse; table-layout: fixed; }
      td { border: .6pt solid #999; padding: 1.4mm 1.5mm; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
      tr { break-inside: avoid; page-break-inside: avoid; } .empty-cell { border: none; }
      .device-entry { display: grid; grid-template-columns: minmax(0, 1fr) 16mm; gap: 1mm; min-height: 19mm; }
      .device-info { min-width: 0; } .model { font-size: 9pt; line-height: 1.3; margin-bottom: .4mm; }
      .row-number { white-space: nowrap; font-variant-numeric: tabular-nums; }
      .identities, .spec-line { display: flex; flex-wrap: wrap; gap: 0 2mm; }
      .identity { font-size: 8pt; font-variant-numeric: tabular-nums; word-break: break-all; }
      .identity span, .spec-label { color: #555; }
      .device-specs { font-size: 8pt; line-height: 1.35; margin-top: .4mm; }
      .spec-line > span { max-width: 100%; }
      .device-bottom { display: flex; justify-content: space-between; gap: 1mm; font-size: 8pt; margin-top: .4mm; }
      .quote { white-space: nowrap; } .amount { display: inline-block; min-width: 12mm; margin-left: 1mm; font-weight: 600; font-variant-numeric: tabular-nums; }
      .qr-cell { text-align: center; align-self: center; } .device-qr { display: block; width: 16mm; height: 16mm; margin: 0; }
      .qr-caption { display: block; font-size: 6.5pt; line-height: 1.2; color: #555; white-space: nowrap; }
      footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5mm 2mm; margin-top: 2mm; padding-top: 1mm; border-top: .6pt solid #aaa; font-size: 7pt; color: #555; }
      .printed-time { width: 100%; }
      @media print { .toolbar { display: none !important; } main { width: 100%; max-width: none; margin: 0; } .print-page { margin: 0; break-after: page; page-break-after: always; } .print-page:last-child { break-after: auto; page-break-after: auto; } }
      @media screen and (max-width: 740px) { main { margin: 12px; } }
    </style></head><body>
    <div class="toolbar"><button id="print-order" type="button">打印 / 保存 PDF</button><span>建议选择 A4 纵向、缩放 100%，关闭浏览器页眉页脚。</span></div>
    <main>${pages}</main></body></html>`
}
