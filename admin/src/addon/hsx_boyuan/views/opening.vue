<template>
    <main class="opening-page" aria-labelledby="opening-title">
        <header><div><h1 id="opening-title">期初建账</h1><p>先准备回收入库、同行挂账出库要用的档案，再到原单确认同步。不必一次取回所有资料。</p></div>
            <div><el-button v-if="$router.hasRoute('hsx_boyuan_connection_page') && !writable" type="primary" plain @click="$router.push({name:'hsx_boyuan_connection_page'})">去连接设置</el-button><el-button :loading="loading" :disabled="busy" @click="refresh()">刷新状态</el-button></div>
        </header>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon />
        <ManualDelivery v-if="contextKey && !stale && rows[0]?.mode === 'test' && rows[0]?.capabilities?.manual_delivery === true" :key="'delivery-'+contextKey" :connection-key="contextKey" />
        <section v-if="loaded" aria-labelledby="archive-title">
            <div class="row">
                <div><span class="eyebrow">第一步 · 博远资料</span><h2 id="archive-title">{{ statusTitle }}</h2>
                    <p aria-live="polite">{{ statusText }}</p>
                    <span v-if="contextKey && rows[0]?.mode !== 'mock'" class="meta">{{ rows[0]?.mode === 'production' ? '博远正式环境' : '博远测试环境（UAT）' }} · 仅取回资料，不向博远制单</span>
                    <small v-if="contextKey && rows[0]?.mode === 'mock'">当前为演示数据，不可用于本站 ERP 建档。</small>
                </div>
                <el-button v-if="canSearch" type="primary" @click="openCatalog">打开基础资料</el-button>
            </div>
            <div v-for="group in groups" :key="group.key" class="group">
                <div v-if="group.key === 'core'" class="row"><h3>挂账入库、出库常用资料</h3><span class="meta">{{ group.done }} / {{ group.items.length }} 类已取回</span></div>
                <button v-else class="link" type="button" :aria-expanded="extraOpen" @click="extraOpen = !extraOpen">{{ extraOpen ? '收起' : '展开' }}其他资料（{{ group.items.length }} 类）<span v-if="group.attention"> · {{ group.attention }} 类待处理</span></button>
                <el-table v-if="group.key === 'core' || extraOpen" :data="group.items" row-key="resource" class="archives">
                <el-table-column label="资料 / 用途" min-width="200"><template #default="{ row }"><strong>{{ row.name }}</strong><div class="meta">{{ usage[row.resource] }}</div></template></el-table-column>
                <el-table-column label="本轮进度" min-width="160"><template #default="{ row }">
                    <strong>{{ readingResource === row.resource ? '正在取回' : stateName(row.job) }}</strong><div v-if="row.job" class="meta">{{ Number(row.job.pages) === 0 && row.job.status === 'queued' ? '等待开始，尚未读取第一页' : '已读取 '+row.job.processed+' 条 · '+row.job.pages+' 页' }}</div>
                    <div v-if="row.job?.reason || row.job?.warning" class="meta">{{ row.job.reason || row.job.warning }}</div>
                </template></el-table-column>
                <el-table-column label="档案库" width="90"><template #default="{ row }">{{ row.count }} 条</template></el-table-column>
                <el-table-column label="操作" min-width="160"><template #default="{ row }">
                    <el-button v-if="canStart && state(row) === 'unstarted'" link type="primary" :disabled="locked" @click="startOne(row)">取回此项</el-button>
                    <el-button v-if="canStart && foreground(row) && row.job?.status === 'queued'" link type="primary" :disabled="locked" @click="readQueued(row)">现在取回</el-button>
                    <el-button v-if="canSearch" link type="primary" @click="openRows(row)">查看档案</el-button>
                    <el-button v-if="canRetry && state(row) === 'failed'" link type="primary" :disabled="locked" @click="resume(row)">从中断处继续</el-button>
                    <el-button v-if="canStart && ['completed','review'].includes(state(row))" link :disabled="locked" @click="restart(row)">重新读取</el-button>
                </template></el-table-column>
            </el-table>
            </div>
        </section>
        <p v-else-if="loading" role="status">正在读取当前账套的同步进度…</p>
        <OpeningPost v-if="contextKey && !stale && rows[0]?.mode !== 'mock'" :key="contextKey" :connection-key="contextKey" :rows="rows" />
    </main>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, onDeactivated, onActivated } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import * as api from '../api/opening-archives'
import { advanceReference, openingProgress, openingState } from '../utils/opening'
import OpeningPost from '../components/OpeningPost.vue'
import { useRouter } from 'vue-router'
const router=useRouter()
import ManualDelivery from '../components/ManualDelivery.vue'

const rows = ref<any[]>([]), loaded = ref(false), stale = ref(true), loading = ref(false), busy = ref(false), error = ref('')
const extraOpen = ref(false)
const readingResource=ref('')
const foreground=(r:any)=>r?.capabilities?.read_now===true && ['test','production'].includes(r.mode)
const progress = computed(() => openingProgress(rows.value))
const sections = { core:['companies','warehouses','parties','products','staff'], extra:['capital_accounts','categories','brands','members','payment_methods'] }
const usage: Record<string,string> = {
    companies:'确认单据所属的公司或门店', warehouses:'匹配入库仓、出库仓；需新建本站仓时在第二步确认',
    parties:'关联回收供应商、购机客户', products:'关联设备的博远商品型号', staff:'选择博远制单人或经办人，不创建员工账号',
    categories:'商品档案未带分类名称时补充', brands:'商品档案未带品牌名称时补充',
    payment_methods:'博远的收银规则，仅供查阅；本站不用另选', capital_accounts:'实际收付款用哪个账户，在第二步选择；仅挂账可跳过',
    members:'按需查阅博远会员；同行挂账使用客户供应商档案'
}
const groups = computed(() => {
    if (!contextKey.value || stale.value) return []
    const byKey = new Map(rows.value.map(row => [row.resource,row] as const))
    return Object.entries(sections).map(([key, keys]) => {
        const items=keys.map(k=>byKey.get(k))
        return {key, items, done:items.filter(r=>state(r)==='completed').length, attention:items.filter(r=>['failed','review'].includes(state(r))).length}
    })
})
const contextKey = computed(() => {
    const first = rows.value[0]
    return progress.value.valid && typeof first?.connection_key === 'string' && rows.value.every(i => i.connection_key === first.connection_key && i.mode === first.mode) ? first.connection_key : ''
})
const locked = computed(() => stale.value || loading.value || busy.value || !contextKey.value)
const writable = computed(() => !stale.value && !!contextKey.value && rows.value[0]?.ready === true)
const canStart = computed(() => writable.value && rows.value[0]?.capabilities?.start === true)
const canRetry = computed(() => writable.value && rows.value[0]?.capabilities?.retry === true)
const canSearch = computed(() => !stale.value && !!contextKey.value && rows.value[0]?.capabilities?.search === true)
const statusTitle = computed(() => !progress.value.valid ? '暂时不能确定同步进度' : !writable.value && !stale.value ? '先核验博远账套' : '逐项取回基础资料')
const statusText = computed(() => {
    if (stale.value) return '当前状态尚未核实，请刷新后再操作。'
    if (!progress.value.valid) return '返回的资料类别不完整，请刷新或联系管理员。'
    if (!contextKey.value && rows.value.some(r=>r.connection_key || r.mode!==rows.value[0]?.mode)) return '资料进度与当前账套不一致，请刷新后再操作。'
    if (!contextKey.value) return '先在「连接设置」填写博远提供的资料，核验账套后再同步。'
    if (!writable.value) return (rows.value[0]?.blocking_reason || '当前连接未就绪。') + ' 请管理员在「博远对接 → 连接设置」中保存资料并核验账套。'
    if (!canStart.value && progress.value.initialResources.length) return '当前账号仅可查看进度，请有同步权限的员工取回资料。'
    if (progress.value.working) return '已排队不等于已取回。可以点「现在取回」在当前页读取；离开后由已启用的后台调度接续。'
    if (progress.value.failed || progress.value.review) return '请查看对应资料的提示。中断的可继续，需要重新读取的只重读该项。'
    return '每次只取回选中的一类。已取回不等于已建入本站 ERP，也不会自动开启采购或销售同步。'
})
const state = (r: any) => openingState(r.job)
const stateName = (job: any) => ({ unstarted:'未取回', working:job?.status==='queued'?'已排队':job?.status==='retry'?'等待重试':'取回中', completed:'已取回', failed:'取回中断', review:'待核对', unknown:'状态待核实' }[openingState(job)])
function openRows(row:any) {
    if (!canSearch.value || !rows.value.includes(row)) return
    if (!router.hasRoute('hsx_boyuan_catalog_page')) { ElMessage.info('请管理员开启「基础资料」菜单权限。'); return }
    return router.push({name:'hsx_boyuan_catalog_page',query:{resource:row.resource}})
}
function openCatalog(){const first=rows.value.find(x=>x.resource==='parties') || rows.value[0]; if(first)return openRows(first)}
let mounted = true, viewSeq = 0, tick: ReturnType<typeof setTimeout> | undefined
let activeScreen=true, screenEpoch=0
const msg = (e: any, fallback: string) => typeof e?.msg === 'string' ? e.msg : typeof e?.message === 'string' ? e.message : fallback
function nextPoll() {
    clearTimeout(tick)
    if (mounted && activeScreen && !stale.value && progress.value.working) tick = setTimeout(() => { if (busy.value) nextPoll(); else refresh() }, 6000)
}
async function refresh() {
    const seq = ++viewSeq
    clearTimeout(tick); loading.value = true
    try {
        const data = (await api.readOpeningJobs()).data
        if (!mounted || seq !== viewSeq) return
        if (!Array.isArray(data)) throw new Error('没有取得有效的资料进度，请重新读取。')
        const prevKey = contextKey.value
        rows.value = data; stale.value = false; loaded.value = true; error.value = ''
        if (prevKey !== contextKey.value) extraOpen.value = false
    } catch (e) {
        if (!mounted || seq !== viewSeq) return
        stale.value = true; error.value = msg(e,'读取同步进度失败。请核对权限并刷新。')
    } finally { if (mounted && seq === viewSeq) { loading.value = false; nextPoll() } }
}
async function perform(resource: string, action: () => Promise<any>) {
    if (locked.value) return
    const key=contextKey.value, epoch=screenEpoch
    busy.value = true
    try {
        const data=(await action()).data
        if (!mounted || key!==contextKey.value || stale.value) return
        const inline = data?.queued===true && Array.isArray(data.ids) && data.ids.length===1 && Number.isSafeInteger(Number(data.ids[0])) && canStart.value && foreground(rows.value.find(r=>r.resource===resource))
        if (data?.queued === true) { if(!inline) ElMessage.success('此项已排队，可在清单查看取回进度。') }
        else if (data?.skipped?.[resource]) ElMessage.info('此项状态已变化，没有新建任务。')
        else throw new Error('未确认是否受理，请先刷新此项进度。')
        if (mounted) await refresh()
        const row=rows.value.find(r=>r.resource===resource)
        if(inline && activeScreen && epoch===screenEpoch && !stale.value && key===contextKey.value && Number(row?.job?.id)===Number(data.ids[0])) await advance(row,key)
    } catch (e) {
        if (mounted && key===contextKey.value) { error.value = msg(e,'未确认是否受理，请先刷新状态。'); stale.value = true }
    } finally { busy.value = false; nextPoll() }
}
async function advance(row:any,key:string) {
    const id=Number(row?.job?.id), mode=row?.mode, resource=row?.resource, epoch=screenEpoch
    const here=()=>mounted && activeScreen && epoch===screenEpoch && !stale.value && key===contextKey.value && canStart.value
    let cursor=String(row?.job?.cursor || '1')
    if(!here() || !foreground(row) || !Number.isSafeInteger(id) || id<1 || !/^\d+$/.test(cursor))return
    readingResource.value=resource
    try {
        const current=()=>rows.value.find(r=>r.resource===resource)?.job
        await advanceReference(row,()=>here() && Number(current()?.id)===id,api.readJobPage,x=>Object.assign(current(),{status:x.status,pages:x.pages,processed:x.processed,cursor:x.cursor}))
        if(!here())return
        await refresh()
        if(!here())return
        const j=rows.value.find(r=>r.resource===resource)?.job
        if(Number(j?.id)!==id)return
        if(j.status==='completed')ElMessage.success(row.name+'已取回 '+j.processed+' 条。')
        else if(j.status==='queued')ElMessage.info('本次读取进度已保存，可继续取回。')
    } finally { readingResource.value='' }
}
async function readQueued(row:any) {
    if(locked.value || !rows.value.includes(row) || row.job?.status!=='queued' || !foreground(row) || !canStart.value)return
    const key=contextKey.value; busy.value=true
    try{await advance(row,key)}catch(e){if(mounted && key===contextKey.value){stale.value=true;error.value=msg(e,'读取结果未确认，请刷新。')}}
    finally{busy.value=false;nextPoll()}
}
function startOne(row: any) {
    if (!canStart.value || locked.value || !rows.value.includes(row) || state(row)!=='unstarted') return
    const key=contextKey.value
    return perform(row.resource, () => api.queueOpening([row.resource], key, 'opening'))
}
function resume(row: any) {
    if (!canRetry.value || !rows.value.includes(row) || state(row) !== 'failed' || !Number.isSafeInteger(Number(row.job?.id)) || Number(row.job?.id) <= 0) return
    return perform(row.resource, () => api.resumeOpening(Number(row.job.id), contextKey.value))
}
async function restart(row: any) {
    if (!canStart.value || locked.value || !rows.value.includes(row) || !['completed','review'].includes(state(row))) return
    const key = contextKey.value, id = row.job?.id
    busy.value = true
    try { await ElMessageBox.confirm('重新从第一页读取「'+row.name+'」？只更新博远档案库，不覆盖本站 ERP 资料。','重新读取资料',{confirmButtonText:'重新读取', cancelButtonText:'取消'}) }
    catch { return } finally { busy.value = false; nextPoll() }
    if (!mounted || !canStart.value || key !== contextKey.value || !rows.value.some(i => i.resource === row.resource && i.job?.id === id && ['completed','review'].includes(state(i)))) return
    return perform(row.resource, () => api.queueOpening([row.resource],key,'refresh'))
}
onMounted(() => refresh())
onDeactivated(()=>{activeScreen=false;screenEpoch++;clearTimeout(tick)})
onActivated(()=>{if(!activeScreen){activeScreen=true;void refresh()}})
onUnmounted(() => { mounted=false; viewSeq++; clearTimeout(tick) })
</script>

<style scoped>
.opening-page { max-width:1120px; padding:24px; margin:auto; color:var(--el-text-color-primary); }
header, .row { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; }
h1, h2, p { margin:0; }
h1 { font-size:23px; }
h2 { font-size:18px; margin-top:5px; }
p, small, .meta, .eyebrow, .meter span { font-size:13px; line-height:1.6; color:var(--el-text-color-regular); }
small { color:var(--el-color-warning-dark-2); }
header p, .row p { margin-top:6px; }
section { margin:18px 0; padding:20px; background:var(--el-bg-color); border:1px solid var(--el-border-color-light); border-radius:8px; }
.eyebrow { color:var(--el-color-primary); }
.group { margin-top:16px; }.group h3 { margin:0; font-size:15px; }.archives { margin-top:8px; }
.link { background:transparent; border:0; padding:3px 0; color:var(--el-color-primary); font:inherit; cursor:pointer; }
.meta { font-size:12px; }.alert,.el-pagination {margin-top:12px;}
@media (max-width: 720px) { .opening-page { padding:12px; } section { padding:12px; } }
</style>
