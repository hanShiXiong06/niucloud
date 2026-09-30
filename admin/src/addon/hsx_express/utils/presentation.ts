export const configDefaults = () => ({
    provider: 'kuaidi100', enabled: 0, scene: 'waybill_web', key: '', secret: '',
    carrier: '', exp_type: '', partner_id: '', partner_key: '', partner_secret: '',
    partner_name: '', net: '', code: '', check_man: '', pay_type: 'MONTHLY', template_id: '', device_id: '', callback_base_url: '', use_ack: 0
})

export const secretFields = ['key', 'secret', 'partner_key', 'partner_secret'] as const

export function applyCarrierSelection(form: Record<string, any>, carrier?: Record<string, any>) {
    // 接口账号及打印机可以共用，快递账号、产品和模板不能串用。
    for (const key of ['partner_id', 'partner_key', 'partner_secret', 'partner_name', 'net', 'code', 'check_man', 'template_id']) form[key] = ''
    form.exp_type = carrier?.products?.length === 1 ? carrier.products[0].value : ''
    form.pay_type = 'MONTHLY'
    form.use_ack = 0
    form.enabled = 0
}

export function requestError(error: any, fallback: string) {
    return typeof error?.msg === 'string' && error.msg.trim() ? error.msg : typeof error?.message === 'string' && error.message.trim() ? error.message : fallback
}

// GET 中的脱敏标记不回填到输入框；留空保存由后端保留原凭据。
export function configForEditing(data: Record<string, any>) {
    const result: Record<string, any> = configDefaults()
    for (const key of Object.keys(result)) if (data[key] !== undefined) result[key] = data[key]
    for (const key of secretFields) result[key] = ''
    result.enabled = Number(result.enabled) === 1 ? 1 : 0
    result.use_ack = Number(result.use_ack) === 1 ? 1 : 0
    return result
}

const states: Record<string, { text: string; type: 'info' | 'warning' | 'success' | 'danger'; next: string }> = {
    creating: { text: '正在取号', type: 'info', next: '请求处理中，请刷新查看结果，不要重复发起。' },
    unknown: { text: '取号结果待核实', type: 'warning', next: '可能已经生成运单。先到快递100核实原任务，勿重新取号或更换服务商下单。' },
    failed: { text: '取号失败', type: 'danger', next: '根据失败原因修正配置或订单资料，再从商城原订单操作。' },
    ready: { text: '面单已生成', type: 'success', next: '打开面单并打印；确认贴单后，在商城完成发货。生成面单不等于已发货。' },
    print_pending: { text: '等待打印回执', type: 'warning', next: '先检查打印机和纸张，并刷新状态。不要因为等待回执重新申请运单。' },
    printed: { text: '打印成功', type: 'success', next: '核对面单并贴单；实际交件后按商城流程处理发货。' },
    print_failed: { text: '打印失败', type: 'danger', next: '运单可能已生成。修复打印机后补打原单，不要重新取号。' },
    cancelling: { text: '取消处理中', type: 'warning', next: '等待快递100取消结果；未确认取消前不要另开运单。' },
    cancel_unknown: { text: '取消结果待核实', type: 'warning', next: '先向快递100确认原运单是否取消，不能把超时当成取消成功。' },
    cancelled: { text: '运单已取消', type: 'info', next: '仅取消快递运单，不会关闭商城订单或自动退款。' }
}

export const stateOptions = Object.entries(states).map(([value, meta]) => ({ value, label: meta.text }))
export function taskState(state: string) {
    return states[state] || { text: '状态待确认', type: 'info' as const, next: '请刷新任务并联系管理员核实，不要重复下单。' }
}

export function canReprint(task: Record<string, any>) {
    return !!task.can_reprint && ['ready', 'printed', 'print_failed', 'print_pending'].includes(task.state)
}

export function operationLabel(operation: string) {
    return ({ create: '申请面单', reprint_requested: '请求补打', reprint: '补打原单', reprint_unknown: '补打结果待核实',
        print_callback: '打印回调', recover_requested: '请求恢复原申请', recover: '恢复原申请', recover_unknown: '恢复结果待核实',
        recover_conflict: '恢复结果需人工核实', cancel_requested: '请求取消运单', cancel: '取消运单', cancel_unknown: '取消结果待核实'
    } as Record<string, string>)[operation] || '处理记录'
}

// 原申请的恢复期限和资格由服务端判断，客户端不根据本机时钟推断。
export function canRecover(task: Record<string, any>) {
    return !!task.can_recover && ['unknown', 'creating'].includes(task.state)
}

export function canCancel(task: Record<string, any>) {
    return !!task.can_cancel && ['ready', 'printed', 'print_pending', 'print_failed'].includes(task.state)
}

// 仅允许官方面单域名的 HTTP(S) 文件；官方部分短链仍为 HTTP，绝不内嵌执行 HTML。
export function labelLinks(value: unknown): string[] {
    let labels: unknown = value
    if (typeof value === 'string') {
        try { labels = JSON.parse(value) } catch { labels = value }
    }
    const values = Array.isArray(labels) ? labels : [labels]
    return [...new Set(values.filter((item): item is string => {
        if (typeof item !== 'string') return false
        try {
            const url = new URL(item)
            const official = ['kuaidi100.com', 'ckd.im'].some(host => url.hostname === host || url.hostname.endsWith(`.${host}`))
            return ['http:', 'https:'].includes(url.protocol) && official && !url.username && !url.password
        } catch { return false }
    }))]
}

export function displayTime(value: string | number) {
    if (!value) return '—'
    if (typeof value === 'number' || /^\d+$/.test(String(value))) {
        const timestamp = Number(value)
        return new Date(timestamp < 1e12 ? timestamp * 1000 : timestamp).toLocaleString('zh-CN', { hour12: false })
    }
    return String(value)
}
