const count = (n: unknown) => typeof n === 'number' && Number.isSafeInteger(n) && n >= 0 ? n : null
const text = (v: unknown) => typeof v === 'string' ? v.trim() : ''
export function wholesaleTrace(d: any) {
    if (d?.mode !== 'test' || d?.kind !== 'wholesale_outbound' || !d.side_effect || d.side_effect === 'none' || d.status === 'succeeded' || d.result?.receipt?.created === true) return null
    const head = d.request?.biz?.head, lookup = d.result?.lookup
    const manual = text(head?.manual_number), businessDate = text(head?.business_date)
    const outcome = typeof lookup?.outcome === 'string' ? lookup.outcome : ''
    const labels: Record<string,string> = { matched_candidates:'查到候选单，尚未核实', no_match:'本次查询没有匹配单据', unidentifiable:'返回资料不足，无法辨认原单', failed:'查询失败，未取得结果' }
    const label = Object.prototype.hasOwnProperty.call(labels,outcome) ? labels[outcome] : lookup != null ? '旧核对记录，未记录查询是否完整' : '尚无原单查询记录'
    const queried = count(lookup?.queried_at), date = queried && queried < 253402300800 ? new Date(queried * 1000).toLocaleString('zh-CN',{timeZone:'Asia/Shanghai',hour12:false}) : ''
    // Do not turn an absent/failed response into a completed lookup with zero matches.
    const observed = ['matched_candidates','no_match','unidentifiable'].includes(outcome)
    const found = observed ? count(lookup?.candidate_count) : null
    const missing = observed ? count(lookup?.missing_manual_number_rows) : null
    const candidates = outcome !== 'failed' && Array.isArray(lookup?.candidates) ? lookup.candidates.flatMap((row:any) => {
        const no=text(row?.external_no); return no ? [{no,id:text(row?.external_id)}] : []
    }) : []
    return {manual,businessDate,label,date,found,missing,candidates}
}
