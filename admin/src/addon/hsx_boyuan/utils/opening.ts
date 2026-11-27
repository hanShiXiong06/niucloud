export const openingResources = ['companies', 'categories', 'brands', 'products', 'warehouses', 'staff', 'parties', 'members', 'payment_methods', 'capital_accounts'] as const

// Both PC entry points advance the same scoped read-only job; a receipt from another account or job is never shown.
export async function advanceReference(row:any, here:()=>boolean, read:(id:number,resource:string,key:string,mode:string,cursor:string)=>Promise<any>, progress:(x:any)=>void) {
    const id=Number(row?.job?.id), resource=row?.resource, key=row?.connection_key, mode=row?.mode
    let cursor=String(row?.job?.cursor || '1')
    if (!Number.isSafeInteger(id) || id<1 || !key || !['test','production'].includes(mode) || !/^[1-9]\d{0,5}$/.test(cursor)) return
    for(let i=0;i<25 && here();i++){
        const x=(await read(id,resource,key,mode,cursor))?.data
        if(!here())return
        if(!x || x.id!==id || x.resource!==resource || x.connection_key!==key || x.mode!==mode || !Number.isInteger(x.pages) || !Number.isInteger(x.processed))
            throw Error('未取得当前资料的完整回执，请刷新查看。')
        progress(x)
        if(x.status!=='queued' || !/^[1-9]\d{0,5}$/.test(x.cursor) || x.wait_seconds>2 || (x.cursor===cursor && !x.wait_seconds))break
        cursor=x.cursor
        await new Promise(resolve=>setTimeout(resolve,Math.max(1,x.wait_seconds || 1)*1000))
    }
}

export function openingState(job: any): 'unstarted'|'working'|'completed'|'failed'|'review'|'unknown' {
    if (job === null) return 'unstarted'
    if (!job || typeof job !== 'object') return 'unknown'
    switch (job.status) {
        case 'completed': return 'completed'
        case 'failed': return 'failed'
        case 'needs_review': return 'review'
        case 'queued': case 'running': case 'retry': return 'working'
        default: return 'unknown'
    }
}

// Count the latest run, never an older success or the number of cached rows.
export function openingProgress(rows: unknown) {
    const keys = new Set<string>(openingResources)
    const seen = new Set<string>(), states = { unstarted: 0, working: 0, completed: 0, failed: 0, review: 0, unknown: 0 }
    const initialResources: string[] = []
    let valid = Array.isArray(rows) && rows.length === keys.size
    if (Array.isArray(rows)) for (const row of rows) {
        const key = row?.resource
        if (!keys.has(key) || seen.has(key)) { valid = false; continue }
        seen.add(key)
        const state = openingState(row.job)
        states[state]++
        if (state === 'unstarted') initialResources.push(key)
        if (state === 'unknown') valid = false
    }
    valid = valid && seen.size === keys.size
    const ready = valid && states.completed === keys.size
    const title = !valid ? '尚未取得完整的同步状态' : ready ? '博远档案已同步' : states.working ? '正在同步博远档案' : states.failed || states.review ? '部分档案需要处理' : '准备博远基础资料'
    const detail = !valid ? '请刷新状态，暂不提交任务。' : ready ? '档案已下载到本站；建入 ERP 需另行确认。' : '已同步 ' + states.completed + ' / ' + keys.size + ' 类。'
    return { ...states, total: keys.size, valid, ready, title, detail, initialResources: valid ? initialResources : [] }
}
