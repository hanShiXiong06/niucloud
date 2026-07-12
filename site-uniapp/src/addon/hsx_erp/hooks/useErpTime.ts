export const formatErpTime = (value: any) => {
    const ts = Number(value || 0)
    if (!ts) return '-'
    const date = new Date(ts * 1000)
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export const formatErpDate = (value: any) => {
    const formatted = formatErpTime(value)
    return formatted === '-' ? '-' : formatted.slice(0, 10)
}

export const erpPrimaryTime = (row: any, fields: string[] = []) => {
    for (const field of fields) {
        const ts = Number(row?.[field] || 0)
        if (ts) return { label: erpTimeLabel(field), value: ts }
    }
    const created = Number(row?.create_at || 0)
    if (created) return { label: '创建时间', value: created }
    return { label: '创建时间', value: 0 }
}

export const erpTimeLine = (row: any, fields: string[] = []) => {
    const primary = erpPrimaryTime(row, fields)
    const parts = [`${primary.label}：${formatErpTime(primary.value)}`]
    const updateAt = Number(row?.update_at || 0)
    if (updateAt && updateAt !== primary.value) {
        parts.push(`更新：${formatErpTime(updateAt)}`)
    }
    return parts.join(' · ')
}

const erpTimeLabel = (field: string) => ({
    stock_in_at: '入库时间',
    sale_at: '销售时间',
    sold_at: '销售时间',
    paid_at: '付款时间',
    pay_at: '付款时间',
    received_at: '收款时间',
    receipt_at: '收款时间',
    returned_at: '退货时间',
    return_at: '退货时间',
    confirmed_at: '确认时间',
    occurred_at: '发生时间',
    create_at: '创建时间',
    update_at: '更新时间',
    first_at:"创建时间",
    latest_at:"最后更新"
}[field] || '时间')
