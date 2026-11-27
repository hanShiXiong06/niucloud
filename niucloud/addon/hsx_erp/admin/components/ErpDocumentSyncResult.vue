<template>
    <section v-if="rows.length || panel" class="document-sync" :class="{ 'document-sync--operator': operatorView }" aria-label="外部单据同步结果">
        <component v-for="group in groups" :is="group.folded ? 'details' : 'div'" :key="group.folded ? 'details' : 'primary'" :class="{'document-sync__technical':group.folded}">
        <summary v-if="group.folded">{{ operatorView==='sale'?'已完成的出库同步记录':'查看同步明细' }}（{{ group.items.length }}）</summary>
        <div v-for="row in group.items" :key="row.key + ':' + row.task_id" class="document-sync__row">
            <div class="document-sync__summary">
                <strong>{{ row.provider_label }}</strong>
                <el-tag :type="row.tone" size="small" effect="plain">{{ row.status_label }}</el-tag>
                <span v-if="row.external_no" class="document-sync__number">单号 <b>{{ row.external_no }}</b></span>
            </div>
            <p v-if="operatorView==='payable' && row.pc_next_step">{{ row.pc_next_step }}</p>
            <p v-else-if="row.next_step">{{ row.next_step }}</p>
            <details v-if="row.receipt_details?.length || row.issue_code" class="document-sync__evidence"><summary>{{ row.issue_code ? '查看核对信息' : '查看回执字段' }}</summary>
                <dl><template v-for="field in row.receipt_details" :key="field.label"><dt>{{ field.label }}</dt><dd>{{ field.value }}</dd></template><template v-if="row.issue_code"><dt>问题代码</dt><dd>{{ row.issue_code }}</dd></template></dl>
                <small v-if="row.receipt_details?.length">创建时返回，不代表当前余额或实际收付款。</small>
            </details>
            <el-button v-if="row.extension" class="document-sync__action" link type="primary" @click="openExtension(row)">{{ actionCaption(row, group.folded) }}</el-button>
            <span v-else-if="row.query?.detail_extension && row.hasTask" class="document-sync__number">核对面板未安装，请联系管理员。</span>
            <el-button v-else-if="row.target" link type="primary" @click="router.push(row.target)">查看核对记录</el-button>
        </div>
        </component>
        <component :is="panel" v-if="panel" ref="panelRef" @updated="updateRow" @finished="emit('refresh')" />
    </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onErrorCaptured, ref, shallowRef, watch, type Component } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import { resultTarget } from '../hooks/useSourceDocument'

const props = defineProps<{ results?: unknown; operatorView?: 'purchase'|'payable'|'sale'|'receivable'; originalSaleId?:number; hasSerialEvidence?:boolean }>()
const emit = defineEmits<{ (event:'refresh'):void }>()
const router = useRouter()
// Optional local addon components only. Never import a path supplied by an API response.
const extensions = import.meta.glob<{ default: Component }>('/src/addon/*/extensions/erp-document-result.vue')
// Resolve an adapter to its locally installed addon. Zero or multiple registrations are ambiguous.
const adapterExtensions = Object.keys(import.meta.glob('/src/addon/*/extensions/erp-bridge-*.vue'))
const adapterOwners = new Map<string,string | null>()
for (const file of adapterExtensions) {
    const m=/^\/src\/addon\/([a-z][a-z0-9_]*)\/extensions\/erp-bridge-([a-z][a-z0-9_]*)\.vue$/.exec(file)
    if (m) adapterOwners.set(m[2],adapterOwners.has(m[2]) ? null : m[1])
}
function extension(code:unknown) {
    if (typeof code !== 'string' || !/^[a-z][a-z0-9_]{0,63}$/.test(code)) return undefined
    return extensions['/src/addon/' + code + '/extensions/erp-document-result.vue']
}
const panel = shallowRef<Component>(), panelRef = ref<{ open:(id:number,context:Record<string,any>)=>void }>()
const activeKey = ref(''), updates = ref<Record<string,Record<string,string>>>({})
let revision = 0
watch(() => props.results, () => { ++revision; panel.value = undefined; activeKey.value = ''; updates.value = {} })
const rows = computed(() => (Array.isArray(props.results) ? props.results : [])
    .filter(item => item && typeof item.key === 'string' && typeof item.provider_label === 'string' && item.provider_label && typeof item.status_label === 'string' && item.status_label)
    .map(item => {
        const r = {...item, ...updates.value[item.key]}, hasTask = Number.isInteger(r.task_id) && r.task_id > 0
        const localSource=Number(r.query?.original_source_id), canLink=r.task_id===0 && Number.isSafeInteger(localSource) && localSource>0
        return {...r, hasTask, action_label:r.action_label || (canLink?r.query?.action_label:undefined), tone: ['info','warning','success','danger'].includes(r.tone) ? r.tone : 'info',
            extension: hasTask || canLink ? extension(r.query?.detail_extension) : undefined,
            target: hasTask ? resultTarget(r.pc_route, r.query) : null }
    }))
const positiveId=(v:unknown)=>/^[1-9]\d*$/.test(String(v)) && Number.isSafeInteger(Number(v)) ? Number(v) : 0
// Only verified documents may open in receipt-only mode. A returned number alone is not proof.
function confirmedReceipt(row:any) {
    return !!props.operatorView && !!row.external_no && row.tone==='success' && !row.issue_code &&
        ['purchase','payment','receipt'].includes(row.query?.kind) && ['succeeded','registered','registered_review'].includes(row.status)
}
function actionCaption(row:any, technical:boolean) {
    if(row.query?.kind==='receipt') {
        if(confirmedReceipt(row))return '查看收款回执'
        if(row.action_label)return row.action_label
        return row.status==='awaiting_confirmation'?'核对已有收款凭证':'查看凭证状态'
    }
    if (props.operatorView && confirmedReceipt(row)) return technical?'查看记录':row.query.kind==='payment'?'查看付款回执':'查看采购回执'
    if (row.action_label) return row.action_label
    if (!props.operatorView) return '在本单核对任务'
    if (['processing','pending','confirming','linked'].includes(row.status)) return '查看进度'
    if (row.query?.kind==='payment') return '核对付款凭证'
    if (row.query?.kind==='purchase') return row.status==='awaiting_confirmation'?'核对采购资料':'核对原采购'
    return '查看详情'
}
function sameVerifiedReceipt(bridge:any,document:any) {
    const b=bridge.query||{}, d=document.query||{}, owner=adapterOwners.get(b.receipt_adapter)
    if (!owner || bridge===document || !positiveId(b.receipt_vendor_task_id) || d.detail_extension!==owner || b.kind!==d.kind || !['purchase','payment'].includes(b.kind)) return false
    if (bridge.status!=='succeeded' || bridge.tone!=='success' || document.tone!=='success' || !['succeeded','registered'].includes(document.status)
        || bridge.issue_code || document.issue_code || !bridge.external_no || bridge.external_no!==document.external_no) return false
    if (b.kind==='payment' && (!positiveId(b.settlement_id) || positiveId(b.settlement_id)!==positiveId(d.settlement_id))) return false
    return positiveId(b.receipt_vendor_task_id)===positiveId(d[b.kind+'_task_id'])
}
const groups=computed(()=>{
    const primary:any[]=[], technical:any[]=[]
    for (const row of rows.value) {
        if(props.operatorView==='sale') {
            const q=row.query||{},sale=positiveId(props.originalSaleId)
            const complete=row.status==='succeeded' && row.tone==='success' && !!row.external_no && !row.issue_code && row.hasTask
            // These are independent records, never merged by a shared bill number.
            const technicalSale=complete && sale>0 && positiveId(q.original_sale_id)===sale && q.kind!=='receipt' &&
                ((q.kind==='sale' && q.sale_record_role==='bridge_delivery') ||
                (props.hasSerialEvidence && q.sale_record_role==='displayed_serial_receipt' && positiveId(q.displayed_task_id)===row.task_id))
            ;(technicalSale?technical:primary).push(row)
            continue
        }
        const matched=row.query?.receipt_adapter && rows.value.filter(other=>sameVerifiedReceipt(row,other)).length===1
        ;(matched?technical:primary).push(row)
    }
    if(props.operatorView==='sale') primary.sort((a,b)=>Number(b.query?.kind==='receipt')-Number(a.query?.kind==='receipt'))
    return [{folded:false,items:primary},{folded:true,items:technical}].filter(g=>g.items.length)
})
async function openExtension(row:any) {
    const version = ++revision
    try {
        const mod = await row.extension()
        if (version !== revision) return
        panel.value = mod.default; activeKey.value = row.key
        await nextTick()
        if (version === revision) panelRef.value?.open(row.task_id, confirmedReceipt(row) ? { ...row.query, readOnly:true } : row.query || {})
    } catch { if (version === revision) ElMessage.warning('暂时无法打开此核对记录，原单可继续查看。') }
}
function openAction(action:any, sourceId:number) {
    const loader = extension(action?.query?.detail_extension)
    if (!loader || !Number.isSafeInteger(sourceId) || sourceId <= 0) {
        ElMessage.warning('本单核对面板不可用，请联系管理员。'); return
    }
    void openExtension({key:action.key,task_id:0,extension:loader,query:{...action.query,purchase_source_id:sourceId}})
}
defineExpose({ openAction })
function updateRow(id:number, patch:Record<string,string>) {
    const own = rows.value.find(r => r.key === activeKey.value && r.task_id === id)
    if (!own || !patch) return
    const next:Record<string,string> = {}
    for (const key of ['status_label','tone','external_no','next_step']) if (typeof patch[key] === 'string') next[key] = patch[key]
    updates.value[own.key] = next
}
onErrorCaptured((_e, instance) => {
    // Isolate only the optional panel's subtree; leave unrelated Vue errors to the usual handler.
    let child = instance?.$
    while (child) {
        if (panel.value && child.type === panel.value) {
            panel.value = undefined
            ElMessage.warning('此核对组件暂时不可用，原单可继续查看。')
            return false
        }
        child = child.parent ?? undefined
    }
})
</script>

<style scoped>
.document-sync { margin: 0 0 14px; border: 1px solid var(--el-border-color-lighter); border-radius: var(--el-border-radius-base); }
.document-sync__row { padding: 10px 14px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px 14px; }
.document-sync__row + .document-sync__row { border-top: 1px solid var(--el-border-color-lighter); }
.document-sync__summary { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; }
.document-sync__number { color: var(--el-text-color-secondary); }
.document-sync__number b { color: var(--el-text-color-primary); font-variant-numeric: tabular-nums; user-select: all; overflow-wrap: anywhere; }
.document-sync__row p { margin: 0; flex: 1 1 180px; font-size: 13px; color: var(--el-text-color-regular); overflow-wrap: anywhere; }
.document-sync__evidence { font-size: 12px; color: var(--el-text-color-regular); }
.document-sync__evidence summary { cursor: pointer; }
.document-sync__evidence dl { display:grid; grid-template-columns:auto 1fr; gap:3px 12px; }
.document-sync__evidence dd { margin:0; font-variant-numeric:tabular-nums; }
.document-sync__technical { border-top:1px solid var(--el-border-color-lighter); }
.document-sync__technical > summary { padding:8px 14px; font-size:13px; color:var(--el-text-color-regular); cursor:pointer; }
.document-sync--operator { container-type:inline-size; }
.document-sync--operator .document-sync__row { display:grid; grid-template-columns:minmax(180px,.85fr) minmax(160px,1.2fr) auto; column-gap:18px; row-gap:4px; align-items:center; padding:12px 16px; }
.document-sync--operator .document-sync__summary { grid-column:1; grid-row:1; }
.document-sync--operator .document-sync__row p { grid-column:2; grid-row:1; line-height:1.6; }
.document-sync--operator .document-sync__action { grid-column:3; grid-row:1; justify-self:end; white-space:nowrap; }
.document-sync--operator .document-sync__evidence { grid-column:1 / -1; }
.document-sync--operator summary:focus-visible { outline:2px solid var(--el-color-primary); outline-offset:-2px; }
@container (max-width:620px) {
    .document-sync--operator .document-sync__row { grid-template-columns:minmax(0,1fr) auto; }
    .document-sync--operator .document-sync__row p,.document-sync--operator .document-sync__evidence { grid-column:1 / -1; grid-row:auto; }
    .document-sync--operator .document-sync__action { grid-column:2; }
}
</style>
