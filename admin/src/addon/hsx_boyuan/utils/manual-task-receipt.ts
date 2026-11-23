export type ManualAction = 'submit' | 'verify'
type ReceiptNotice = { type: 'success' | 'info' | 'warning', text: string }

export function checkTaskReceipt(value: any, id: number, revision: number) {
    if (!value || Number(value.id) !== id || !Number.isSafeInteger(Number(value.revision)) ||
        Number(value.revision) < revision || typeof value.status !== 'string' || !value.status) {
        throw new Error('未取得这张原单的有效回执，请刷新原单核对，不要重复提交。')
    }
    return value
}

export function processCurrentAction(task: any, allowed: boolean): ManualAction | '' {
    if (!allowed || !task || task.mode !== 'test') return ''
    if (task.status === 'pending' && task.side_effect === 'none') return 'submit'
    return task.status === 'confirming' ? 'verify' : ''
}

// Only a verified original with its number is a success. A POST returning HTTP 200 is not.
export function taskReceiptNotice(task: any, movement: '入库' | '出库'): ReceiptNotice {
    const no = String(task?.external_no || '').trim()
    switch (task?.status) {
        case 'succeeded':
            return no ? { type: 'success', text: '已核实' + movement + '，博远单号：' + no }
                : { type: 'warning', text: '回执未提供博远单号，请刷新原单核对。' }
        case 'created_review':
            return { type: 'warning', text: no ? '博远已建单：' + no + '。请核对原单明细。' : '博远建单结果待核对，请刷新原单。' }
        case 'pending': return { type: 'info', text: '本单仍待处理，尚未确认博远' + movement + '。' }
        case 'processing': case 'retry': return { type: 'info', text: '本单正在处理，请等待结果，不要重复提交。' }
        case 'confirming': return { type: 'info', text: '正在核对原单，不会重复' + movement + '。' }
        case 'blocked': case 'failed':
            return { type: 'warning', text: task.reason || '本单未完成，请查看原因。' }
        default:
            return { type: 'warning', text: '结果待核实，请查看原单，勿重复提交。' }
    }
}
