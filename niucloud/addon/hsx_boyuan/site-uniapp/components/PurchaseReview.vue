<template>
    <u-popup :show="taskId > 0" mode="bottom" :round="8" closeable :close-on-click-overlay="false" @close="close">
        <view class="purchase-sheet">
            <view class="sheet-head"><text class="title">{{ lookup ? '选择博远' + lookup.name : '采购入库核对' }}</text><text class="muted">{{ view?.task?.business_no || '正在读取任务' }}</text><picker v-if="!lookup && (taskIds || []).length > 1" :disabled="interactionLocked" :range="batchOptions" range-key="label" @change="switchBatch(Number($event.detail.value))"><text class="batch-link">入库批次 {{ (taskIds || []).indexOf(taskId) + 1 }} / {{ taskIds?.length }} · 切换核对 ›</text></picker></view>
            <scroll-view scroll-y class="sheet-body">
                <view v-if="loading && !view" class="empty"><u-loading-icon text="读取采购任务" /></view>
                <template v-if="lookup">
                    <text class="notice">只选择博远已有的启用资料，不自动创建档案。</text>
                    <u-search v-model="lookupKeyword" bg-color="var(--boyuan-soft)" color="var(--boyuan-text)" placeholder-color="var(--boyuan-muted)" placeholder="搜索名称 / 编码" :show-action="false" @search="loadChoices(false)" @clear="loadChoices(false)" />
                    <view v-if="lookupError" class="issue"><text>{{ lookupError }}</text><button class="text-button" @click="loadChoices(false)">重新读取</button></view>
                    <button v-for="item in choices" :key="item.id" class="choice" :disabled="interactionLocked || !connectionMatches" @click="selectChoice(item)"><view><text class="choice-name">{{ item.name }}</text><text class="muted">{{ item.code }}<template v-if="item.extra?.company_name"> · {{ item.extra.company_name }}</template></text><text v-if="item.extra?.memory || item.extra?.color" class="muted">{{ item.extra.memory || '' }} {{ item.extra.color || '' }} {{ item.extra.version_type_name || '' }}</text></view><u-icon :name="selectedChoiceId === String(item.id) ? 'checkmark-circle' : 'arrow-right'" size="18" /></button>
                    <view v-if="choiceLoading" class="empty"><u-loading-icon text="正在读取" /></view>
                    <text v-else-if="!choices.length && !lookupError" class="notice">没有匹配的启用资料。可调整搜索条件，或请管理员更新基础资料后再选。</text>
                    <u-button v-if="choiceCursor" size="small" :loading="choiceLoading" @click="loadChoices(true)">加载更多资料</u-button>
                </template>
                <template v-else-if="view">
                    <view class="state" :class="nextStep.tone"><view class="row"><SyncState :status="view.task.status" :label="stateLabel" /><text class="muted">{{ sourceLines.length }} 台</text></view><text class="next-title">{{ nextStep.title }}</text><text class="next-detail">{{ nextStep.detail }}</text><text v-if="view.task.external_no" class="muted">博远原单号 {{ view.task.external_no }}</text></view>
                    <text v-if="view.can_operate === false" class="notice">当前账号仅可查看采购核对结果，不能保存、提交或核验；请联系有采购核对权限的员工处理。</text>
                    <text v-if="view.task.reason" class="issue">{{ view.task.reason }}</text>
                    <text v-if="!connectionMatches" class="issue">当前账套已变化。此任务不能在新账套修改或发送，请关闭后核对连接。</text>
                    <view class="closure-panel"><view class="row"><view><text class="section-title">{{ closure.complete ? '六环节已核对' : '采购记录核对 ≠ 全闭环' }}</text><text class="muted">{{ closure.stages.length ? '本批次已核对 ' + verifiedStages + ' / 6 环节' : '闭环状态尚未提供' }}</text></view><button v-if="closure.stages.length" class="text-button" :aria-expanded="closureExpanded" @click="closureExpanded = !closureExpanded">{{ closureExpanded ? '收起' : '查看阶段' }}</button></view><text v-if="remainingStage" class="closure-pending">{{ remainingStage.label }} · {{ remainingStage.reason }}</text><template v-if="closureExpanded"><view v-for="stage in closure.stages" :key="stage.key" class="closure-stage" :class="stage.status"><view class="row"><text>{{ stage.label }}</text><text class="stage-status">{{ purchaseClosureStatus(stage.status) }}</text></view><text class="muted">{{ stage.reason }}</text><text v-if="stage.amount != null" class="muted">金额 {{ purchaseMoney(stage.amount) }}</text><text v-if="stage.paid_amount != null || stage.unpaid_amount != null" class="muted">已付 {{ purchaseMoney(stage.paid_amount) }} · 未付 {{ purchaseMoney(stage.unpaid_amount) }}</text></view><view class="closure-totals"><text>本批采购 {{ purchaseMoney(closure.totals.amount) }}</text><text>本站已确认付款 {{ purchaseMoney(closure.totals.paid_amount) }}</text><text>本站未付 {{ purchaseMoney(closure.totals.unpaid_amount) }}</text></view></template><text v-for="(warning, index) in closure.warnings" :key="index" class="closure-pending">{{ warning }}</text></view>
                    <view class="local-summary"><view class="row"><text class="section-title">本站采购 · 只读</text><text class="muted">{{ sourceLines.length }} 台</text></view><text class="muted">供应商 {{ view.source?.party?.name || '未提供' }} · 采购员 {{ view.source?.operator?.name || '未提供' }}</text><text class="muted">串号、数量和成本取自已入库记录，本页不能修改。</text></view>
                    <view class="association-head"><text class="section-title">博远入库资料 <text v-if="editable" class="muted">{{ missingFields.length ? '还需补齐 ' + missingFields.length + ' 项' : '必填项已选齐' }}</text></text><text class="muted">{{ editable ? '逐项核对后再提交；同一采购请选择同一个博远仓库。' : '本次关联只读，不会重复建单' }}</text></view>
                    <view v-for="field in topFields" :key="field.key"><button class="association" :disabled="!editable || interactionLocked" @click="openLookup(field)"><text>{{ field.name }}{{ field.key !== 'buyer_id' ? ' *' : '' }}</text><view><text :class="{ placeholder: !hasSelection(field.key) }">{{ labelFor(field.key) }}</text><u-icon v-if="editable" name="arrow-right" size="13" /></view></button><button v-if="field.key === 'buyer_id' && selection.buyer_id && editable" class="text-button" :disabled="interactionLocked" @click="clearBuyer">清除选填业务员</button></view>
                    <view v-for="(line, index) in sourceLines" :key="line.source_line_id" class="device">
                        <view class="row"><text class="section-title">{{ index + 1 }}. {{ line.product_name || '采购设备' }}</text><text class="amount">¥{{ line.amount }}</text></view><text class="serial">{{ line.imei || line.sn || '缺少串号' }}</text><text class="muted">数量 {{ line.quantity || 1 }} · 本站仓库 {{ line.warehouse_name || '名称未提供' }}</text>
                        <button v-for="field in lineFields" :key="field.key" class="association" :disabled="!editable || interactionLocked" @click="openLookup(field, line.source_line_id)"><text>{{ field.name }} *</text><view><text :class="{ placeholder: !lineSelection(line.source_line_id)?.[field.key] }">{{ labelFor(field.key, line.source_line_id) }}</text><u-icon v-if="editable" name="arrow-right" size="13" /></view></button>
                    </view>
                    <view v-if="view.issues?.length" class="issue"><text>上次服务端核对提示，修改后保存可重新核验：</text><text v-for="(issue, index) in view.issues" :key="index">{{ issue }}</text></view>
                    <view v-if="editable" class="review-note"><u-checkbox v-if="view.can_remember === true" :used-alone="true" :checked="remember" :disabled="interactionLocked" label="记住本次资料对应，供后续采购使用" :label-size="12" @change="remember = !remember" /><text class="muted">默认只用于本单。保存核对不会发送；确认提交后才进入后台建单。</text><text v-if="!config.enabled || !config.purchase_enabled" class="issue">业务总闸或采购投递尚未启用，可先保存核对，请管理员确认连接后再开放提交。</text><text v-else-if="config.paused" class="issue">外部投递已暂停，可先保存核对，不会立即发送。</text></view>
                    <view v-if="!editable" class="receipt"><text class="section-title">采购回执</text><text class="muted">{{ stateMessage }}</text></view>
                    <view v-if="canVerify" class="verify"><text class="section-title">核验已有博远单据</text><u-input v-model="externalNo" color="var(--boyuan-text)" disabled-color="var(--boyuan-soft)" placeholder-style="color:var(--boyuan-muted)" placeholder="已知博远单号，可核对后填写" :disabled="interactionLocked || !!view.task.external_no" confirm-type="done" @confirm="verify" /><text class="muted">填写单号不等于成功，只核验原单，不新建采购或付款。</text></view>
                    <button class="details-toggle" :aria-expanded="detailsExpanded" @click="detailsExpanded = !detailsExpanded">原单与对接详情 <text>{{ detailsExpanded ? '收起' : '展开' }}</text></button><view v-if="detailsExpanded" class="technical-details"><text>本站采购单 {{ view.source?.source_document_no || view.task.business_no }}</text><text>入库批次 {{ view.source?.batch_ref || '未提供' }}</text><text>任务编号 / 模式 {{ view.task.id }} / {{ view.task.mode }}</text><text>博远原单号 {{ view.task.external_no || '尚未取得' }}</text><text class="muted">详细诊断统一记录到框架日志，本页不读取数据库日志。</text></view>
                </template>
                <view v-if="error" class="issue"><text>{{ error }}</text><button v-if="!view" class="text-button" @click="reload">重新读取</button></view>
            </scroll-view>
            <view class="sheet-footer">
                <u-button v-if="lookup" @click="exitLookup">返回核对</u-button>
                <template v-else-if="editable"><u-button :loading="busy" :disabled="interactionLocked" @click="saveDraft">保存（不发送）</u-button><u-button type="primary" :loading="busy" :disabled="!canSubmit || interactionLocked" @click="confirmSubmit">确认并提交</u-button></template>
                <template v-else><u-button :disabled="interactionLocked || loading" :loading="loading" @click="reload">刷新状态</u-button><u-button v-if="canVerify" type="primary" :loading="busy" :disabled="!externalNo.trim() || interactionLocked" @click="verify">核验原单</u-button><u-button v-else :disabled="interactionLocked" @click="close">关闭</u-button></template>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue'
import { onShow, onHide } from '@dcloudio/uni-app'
import * as api from '../api/index'
import SyncState from './SyncState.vue'
import { purchaseSelection, purchaseSelectionComplete, purchaseStateMessage, purchaseMissingFields, purchaseNextStep, purchaseClosure, purchaseClosureStatus, purchaseMoney, type PurchaseSelection } from '../utils/purchase-form'

const props = defineProps<{ taskId: number, taskIds?: number[], config: any }>()
const emit = defineEmits<{ (event: 'close'): void, (event: 'changed'): void, (event: 'switch', taskId: number): void }>()
type LookupField = { key: string, name: string, resource: string, role?: string, sourceLineId?: string }
const topFields: LookupField[] = [{ key: 'company_id', name: '公司', resource: 'companies' }, { key: 'supplier_id', name: '供应商', resource: 'parties', role: 'supplier' }, { key: 'creator_id', name: '博远制单人', resource: 'staff' }, { key: 'buyer_id', name: '业务员（选填）', resource: 'staff', role: 'buyer' }]
const lineFields: LookupField[] = [{ key: 'product_id', name: '商品', resource: 'products' }, { key: 'warehouse_id', name: '仓库', resource: 'warehouses' }]
const view = ref<any>(null), loading = ref(false), busy = ref(false), error = ref(''), remember = ref(false), externalNo = ref('')
const confirmingSubmit = ref(false), detailsExpanded = ref(false), closureExpanded = ref(false)
const interactionLocked = computed(() => busy.value || confirmingSubmit.value)
const selection = ref<PurchaseSelection>(purchaseSelection()), labels = ref<Record<string, any>>({})
const sourceLines = computed<any[]>(() => view.value?.source?.lines || [])
const batchOptions = computed(() => (props.taskIds || []).map((id, index) => ({ id, label: '第 ' + (index + 1) + ' 个入库批次' })))
const connectionMatches = computed(() => !!view.value && view.value.task.mode === props.config.mode && view.value.task.connection_key === props.config.profiles?.[props.config.mode]?.connection_key)
const editable = computed(() => !!view.value?.editable && view.value?.can_operate !== false && connectionMatches.value)
const canVerify = computed(() => !!view.value?.can_verify && view.value?.can_operate !== false && connectionMatches.value && view.value?.task?.status !== 'succeeded')
const canSubmit = computed(() => editable.value && !!props.config.enabled && !!props.config.purchase_enabled && !props.config.paused && !!props.config.profiles?.[props.config.mode]?.probe_at && purchaseSelectionComplete(selection.value))
const writeEnabled = computed(() => !!props.config.enabled && !!props.config.purchase_enabled && !props.config.paused && !!props.config.profiles?.[props.config.mode]?.probe_at)
const stateMessage = computed(() => purchaseStateMessage(view.value?.task, view.value?.result))
const missingFields = computed(() => purchaseMissingFields(selection.value))
const nextStep = computed(() => purchaseNextStep(view.value?.task, { editable: editable.value, canVerify: canVerify.value, complete: purchaseSelectionComplete(selection.value), enabled: writeEnabled.value, canOperate: view.value?.can_operate }))
const closure = computed(() => purchaseClosure(view.value?.closure))
const verifiedStages = computed(() => closure.value.stages.filter((stage: any) => stage.status === 'verified').length)
const remainingStage = computed(() => closure.value.stages.find((stage: any) => stage.status !== 'verified'))
const purchaseStates: Record<string, string> = { awaiting_confirmation: '待核对', created_review: '已建单待核验', needs_review: '需人工核验' }
const stateLabel = computed(() => purchaseStates[view.value?.task?.status])
const lookup = ref<LookupField | null>(null), lookupKeyword = ref(''), choices = ref<any[]>([]), choiceCursor = ref(''), choiceLoading = ref(false), lookupError = ref('')
const selectedChoiceId = computed(() => lookup.value?.sourceLineId ? lineSelection(lookup.value.sourceLineId)?.[lookup.value.key] : (selection.value as any)[lookup.value?.key || ''])
let request = 0, choiceRequest = 0, savedSelection = '', active = true
const issueMessage = (err: any) => String(err?.msg || err?.message || '操作未完成，请刷新任务核对最新状态').slice(0, 400)
const labelKey = (key: string, lineId = '') => lineId + ':' + key
function lineSelection(id: string): any { return selection.value.lines.find(line => line.source_line_id === String(id)) }
function hasSelection(key: string) { return !!(selection.value as any)[key] }
function labelFor(key: string, lineId = '') { const name = labels.value[labelKey(key, String(lineId))]?.name; if (name) return name; const selected = lineId ? lineSelection(lineId)?.[key] : (selection.value as any)[key]; return selected ? editable.value ? '已选择，请点开核对资料' : '档案名称暂不可用' : editable.value ? '请选择博远' + [...topFields, ...lineFields].find(field => field.key === key)?.name : '本次未指定' }
function applyView(value: any, preserveExternal = false) {
    const knownInput = preserveExternal && view.value?.task?.id === value.task.id && !value.task.external_no ? externalNo.value : ''
    view.value = value; selection.value = purchaseSelection(value.selection, value.source); externalNo.value = value.task.external_no || knownInput; remember.value = false
    const nextLabels: Record<string, any> = {}
    for (const [key, name] of [['company_id', 'company'], ['supplier_id', 'supplier'], ['creator_id', 'creator'], ['buyer_id', 'buyer']]) nextLabels[labelKey(key)] = value.labels?.[name]
    for (const line of value.labels?.lines || []) { nextLabels[labelKey('product_id', String(line.source_line_id))] = line.product; nextLabels[labelKey('warehouse_id', String(line.source_line_id))] = line.warehouse }
    labels.value = nextLabels; savedSelection = JSON.stringify(selection.value)
}
async function reload(silent = false) {
    if (!props.taskId || interactionLocked.value || (silent && (lookup.value || JSON.stringify(selection.value) !== savedSelection || remember.value || externalNo.value !== String(view.value?.task?.external_no || '')))) return
    const id = props.taskId, token = ++request
    if (!silent) loading.value = true
    try { const res: any = await api.getPurchase(id); if (token === request && id === props.taskId) { applyView(res.data, true); error.value = '' } }
    catch (err) { if (token === request) error.value = issueMessage(err) }
    finally { if (token === request) loading.value = false }
}
function exitLookup() { lookup.value = null; choiceRequest++; choiceLoading.value = false }
function openLookup(field: LookupField, sourceLineId?: string) {
    if (!editable.value || interactionLocked.value) return
    lookup.value = { ...field, sourceLineId: sourceLineId === undefined ? undefined : String(sourceLineId) }; lookupKeyword.value = ''; choices.value = []; choiceCursor.value = ''; loadChoices(false)
}
async function loadChoices(append = false) {
    if (!lookup.value || !editable.value || (append && (choiceLoading.value || !choiceCursor.value))) return
    const field = { ...lookup.value }, token = ++choiceRequest, id = props.taskId, connection = view.value.task.connection_key
    const cursor = append ? choiceCursor.value : '1'
    choiceLoading.value = true; lookupError.value = ''
    if (!append) { choices.value = []; choiceCursor.value = '' }
    try {
        const res: any = await api.getReferences({ resource: field.resource, role: field.role || '', keyword: lookupKeyword.value.trim(), cursor, limit: 50, enabled_only: true })
        if (token !== choiceRequest || props.taskId !== id || !lookup.value || view.value?.task.connection_key !== connection || !connectionMatches.value) return
        if (res.data?.code && res.data.code !== 'OK') throw new Error(res.data.message || '基础资料读取未完成')
        const data = res.data?.data || {}, rows = (data.items || []).filter((item: any) => !!item.enabled && !item.stale && (field.role !== 'supplier' || !!item.extra?.is_supplier) && (field.role !== 'buyer' || !!item.extra?.is_buyer))
        const previous = append ? choices.value : []
        choices.value = [...previous, ...rows.filter((item: any) => !previous.some(old => String(old.id) === String(item.id)))]
        choiceCursor.value = data.next_cursor && String(data.next_cursor) !== String(cursor) ? String(data.next_cursor) : ''
    } catch (err) { if (token === choiceRequest) lookupError.value = issueMessage(err) }
    finally { if (token === choiceRequest) choiceLoading.value = false }
}
function selectChoice(item: any) {
    if (!lookup.value || !editable.value || interactionLocked.value || !item.enabled || item.stale) return
    const field = lookup.value
    if (field.sourceLineId) { const line = lineSelection(field.sourceLineId); if (!line) return; line[field.key] = String(item.id) }
    else (selection.value as any)[field.key] = String(item.id)
    labels.value[labelKey(field.key, field.sourceLineId)] = { id: String(item.id), name: item.name, code: item.code }; error.value = ''; exitLookup()
}
function clearBuyer() { if (editable.value && !interactionLocked.value) { selection.value.buyer_id = ''; delete labels.value[labelKey('buyer_id')] } }
function input() { return { revision: view.value.task.revision, mode: view.value.task.mode, connection_key: view.value.task.connection_key, selection: purchaseSelection(selection.value, view.value.source), remember: remember.value && view.value.can_remember === true } }
async function saveDraft() {
    if (!editable.value || interactionLocked.value) return
    busy.value = true; error.value = ''
    try { const res: any = await api.savePurchaseDraft(props.taskId, input()); applyView(res.data); emit('changed'); uni.showToast({ title: '核对已保存，尚未发送', icon: 'none' }) }
    catch (err) { error.value = issueMessage(err) }
    finally { busy.value = false }
}
function confirmSubmit() {
    if (!canSubmit.value || interactionLocked.value) return
    confirmingSubmit.value = true
    uni.showModal({ title: '确认向博远提交采购？', content: `${view.value.task.business_no} · ${sourceLines.value.length} 台。提交后关联锁定；结果未知只核验原单，不重复建单。`, success: async result => { try { if (result.confirm) await submit() } finally { confirmingSubmit.value = false } }, fail: () => { confirmingSubmit.value = false } })
}
async function submit() {
    if (!canSubmit.value || busy.value) return
    busy.value = true; error.value = ''
    try { const res: any = await api.submitPurchase(props.taskId, input()); applyView(res.data); emit('changed'); uni.showToast({ title: '已交后台处理，请等待核验', icon: 'none' }) }
    catch (err) { error.value = '提交结果尚未确认，请刷新任务状态后处理，勿重复提交。' + issueMessage(err); request++; view.value.editable = false; view.value.can_submit = false }
    finally { busy.value = false }
}
async function verify() {
    if (!canVerify.value || interactionLocked.value || !externalNo.value.trim()) return
    busy.value = true; error.value = ''
    try { const res: any = await api.verifyPurchase(props.taskId, { revision: view.value.task.revision, mode: view.value.task.mode, connection_key: view.value.task.connection_key, external_no: externalNo.value.trim() }); applyView(res.data); emit('changed'); uni.showToast({ title: '已请求核验，尚未确认成功', icon: 'none' }) }
    catch (err) { error.value = issueMessage(err) }
    finally { busy.value = false }
}
function close() {
    if (interactionLocked.value) return
    if (lookup.value) return exitLookup()
    if (editable.value && (JSON.stringify(selection.value) !== savedSelection || remember.value)) uni.showModal({ title: '放弃未保存的核对？', success: result => { if (result.confirm) emit('close') } })
    else emit('close')
}
function switchBatch(index: number) {
    if (interactionLocked.value || loading.value || !batchOptions.value[index] || batchOptions.value[index].id === props.taskId) return
    const proceed = () => emit('switch', batchOptions.value[index].id)
    if (editable.value && (JSON.stringify(selection.value) !== savedSelection || remember.value)) uni.showModal({ title: '切换批次将放弃未保存的核对？', success: result => { if (result.confirm) proceed() } })
    else proceed()
}
watch(() => props.taskId, id => { request++; choiceRequest++; loading.value = false; view.value = null; error.value = ''; lookup.value = null; remember.value = false; if (id) reload() }, { immediate: true })
const timer = setInterval(() => { if (active && props.taskId && view.value && !editable.value) reload(true) }, 6000)
onShow(() => { active = true })
onHide(() => { active = false })
onUnmounted(() => { clearInterval(timer); request++; choiceRequest++ })
</script>

<style scoped lang="scss">
.purchase-sheet { --boyuan-surface: var(--el-bg-color, #fff); --boyuan-soft: var(--el-fill-color-light, #f4f6f8); --boyuan-text: var(--el-text-color-primary, #20252d); --boyuan-muted: var(--el-text-color-regular, #626d7c); --boyuan-line: var(--el-border-color-lighter, #e7ebf0); --boyuan-brand: var(--primary-color, #2563eb); --boyuan-warning: #93511d; --boyuan-warning-bg: #fff4e7; color: var(--boyuan-text); background: var(--boyuan-surface); border-radius: 8px 8px 0 0; font-size: 14px; max-height: 90vh; display: flex; flex-direction: column; }
button { margin: 0; padding: 0; color: inherit; font-size: inherit; background: transparent; line-height: 1.5; &::after { border: 0; } }
.sheet-head { padding: 20px 48px 14px 18px; border-bottom: 1px solid var(--boyuan-line); flex-shrink: 0; }.title { font-size: 18px; font-weight: 650; }.muted { display: block; color: var(--boyuan-muted); font-size: 12px; line-height: 1.7; }.batch-link { display: block; padding-top: 8px; font-size: 12px; color: var(--boyuan-brand); }.sheet-body { height: 63vh; min-height: 0; box-sizing: border-box; padding: 0 18px 12px; overscroll-behavior: contain; }.sheet-footer { padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); display: flex; gap: 10px; border-top: 1px solid var(--boyuan-line); background: var(--boyuan-surface); flex-shrink: 0; }.sheet-footer :deep(.u-button) { min-height: 44px; }
.state { padding: 18px 0 14px; line-height: 1.7; }.state .row { align-items: center; }.next-title { display: block; font-size: 19px; font-weight: 650; line-height: 1.5; margin-top: 12px; }.next-detail { display: block; margin-top: 6px; color: var(--boyuan-muted); font-size: 13px; }.state > .muted { margin-top: 6px; }.issue { display: block; margin: 12px 0; padding: 10px 12px; color: var(--boyuan-warning); font-size: 12px; line-height: 1.8; background: var(--boyuan-warning-bg); overflow-wrap: anywhere; }.issue > text { display: block; }.notice { display: block; margin: 14px 0; color: var(--boyuan-muted); background: var(--boyuan-soft); padding: 10px 12px; font-size: 12px; line-height: 1.7; }.row { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }.section-title { font-size: 14px; font-weight: 600; }.local-summary { padding: 12px 0; border-bottom: 1px solid var(--boyuan-line); }.local-summary .row { margin-bottom: 4px; }.association-head { padding: 20px 0 5px; }.association-head .section-title .muted { display: inline; margin-left: 8px; font-weight: 400; }.association { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-height: 48px; padding: 14px 0; border-bottom: 1px solid var(--boyuan-line); text-align: left; }.association > text { flex-shrink: 0; color: var(--boyuan-muted); font-size: 12px; }.association > view { display: flex; align-items: center; justify-content: flex-end; gap: 8px; min-width: 0; text-align: right; }.association > view > text { overflow-wrap: anywhere; }.placeholder { color: var(--boyuan-muted); }.device { padding: 18px 0 4px; border-bottom: 1px solid var(--boyuan-line); }.device .association:last-child { border: 0; }.serial { display: block; font-family: monospace; padding: 8px 0 3px; overflow-wrap: anywhere; }.amount { font-weight: 600; white-space: nowrap; }.review-note { padding: 16px 0; }.review-note .muted { margin-top: 9px; }.receipt, .verify { padding: 18px 0; }.receipt .muted { margin-top: 8px; }.verify .section-title { display: block; margin-bottom: 12px; }.verify .muted { margin-top: 10px; }.verify :deep(.u-input) { background: var(--boyuan-surface); border-color: var(--boyuan-line); }.break { word-break: break-all; text-align: right; }.choice { display: flex; justify-content: space-between; align-items: center; gap: 12px; width: 100%; min-height: 50px; padding: 15px 0; border-bottom: 1px solid var(--boyuan-line); text-align: left; }.choice > view { min-width: 0; }.choice-name { display: block; font-size: 14px; overflow-wrap: anywhere; }.empty { padding: 30px 12px; }.text-button { color: var(--boyuan-brand); font-size: 12px; min-height: 40px; display: inline-flex; align-items: center; }.closure-panel { border-block: 1px solid var(--boyuan-line); padding: 12px 0; }.closure-panel .row { align-items: center; }.closure-panel .text-button { flex-shrink: 0; }.closure-pending { display: block; margin-top: 8px; color: var(--boyuan-warning); font-size: 12px; line-height: 1.7; overflow-wrap: anywhere; }.closure-stage { padding: 12px 0; border-top: 1px solid var(--boyuan-line); font-size: 12px; }.closure-stage:first-of-type { margin-top: 12px; }.stage-status { color: var(--boyuan-warning); white-space: nowrap; }.closure-stage.verified .stage-status { color: #20835b; }.closure-totals { padding-top: 12px; border-top: 1px solid var(--boyuan-line); }.closure-totals > text { display: block; margin-bottom: 7px; color: var(--boyuan-muted); font-size: 12px; }.details-toggle { display: flex; align-items: center; justify-content: space-between; min-height: 48px; width: 100%; border-top: 1px solid var(--boyuan-line); font-size: 13px; text-align: left; }.details-toggle > text { color: var(--boyuan-brand); font-size: 12px; }.technical-details { padding-bottom: 16px; }.technical-details > text { display: block; margin: 8px 0; color: var(--boyuan-muted); font-size: 12px; overflow-wrap: anywhere; }
@media (max-height: 640px) { .sheet-body { height: 55vh; }.sheet-head { padding-top: 14px; padding-bottom: 10px; }.sheet-footer { padding-top: 9px; padding-bottom: calc(9px + env(safe-area-inset-bottom)); } }
@media (prefers-color-scheme: dark) { .purchase-sheet { --boyuan-surface: var(--el-bg-color, #191d24); --boyuan-soft: var(--el-fill-color-light, #252b35); --boyuan-text: var(--el-text-color-primary, #edf0f5); --boyuan-muted: var(--el-text-color-regular, #bac4d1); --boyuan-line: var(--el-border-color-lighter, #39414d); --boyuan-warning: #f1ba80; --boyuan-warning-bg: #36291c; }.closure-stage.verified .stage-status { color: #7bd9ad; } }
</style>
