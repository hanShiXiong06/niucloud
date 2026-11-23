/** Only exact original task and call identities can be displayed, not a serial/amount/time match. */
export function evidenceQuery(r:any):{trace:string;call:string}|null {
    const site=Number(r?.site_id), task=Number(r?.task_id), call=String(r?.call_id??'')
    if(r?.source!=='boyuan' || !Number.isSafeInteger(site) || site<1 || !Number.isSafeInteger(task) || task<1 || (call && !/^[a-f0-9]{16}$/.test(call)))return null
    return {trace:'T'+site+'-'+task,call}
}
export function appendEvidence(existing:any[], page:any[], query:{trace:string;call:string}) {
    const result=new Map(existing.map(r=>[r.call_id,r]))
    for(const r of page){
        if(!r || r.trace_id!==query.trace || !/^[a-f0-9]{16}$/.test(String(r.call_id)) || (query.call && r.call_id!==query.call))continue
        if(!result.has(r.call_id) || r.stage==='result')result.set(r.call_id,r)
    }
    return [...result.values()]
}
