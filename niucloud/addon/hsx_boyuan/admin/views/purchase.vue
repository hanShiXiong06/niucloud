<template>
    <div class="main-container boyuan-purchase" v-loading="loading">
        <header>
            <div><h2>{{ paymentTarget ? '财务付款凭证' : embedded ? '采购挂账与付款凭证' : '博远采购同步' }}</h2><p v-if="view">{{ view.source?.party?.name || '采购入库' }} · {{ sourceLines.length }} 台<span v-if="sourceLines.length === 1"> · 串号 {{ sourceLines[0].imei || sourceLines[0].sn || '未提供' }}</span></p></div>
            <div class="header-actions">
                <el-select v-if="taskIds.length > 1" aria-label="选择入库批次" :model-value="taskId" :disabled="interactionLocked" class="batch-select" @change="switchTask"><el-option v-for="(id, index) in taskIds" :key="id" :value="id" :label="'第 ' + (index + 1) + ' 个入库批次'" /></el-select>
                <el-button v-if="view" :disabled="interactionLocked || loading || dirty" :loading="loading" @click="refresh">刷新状态</el-button>
            </div>
        </header>
        <section v-if="originalReview && !view" class="closure-panel">
            <h3>这张采购在启用同步之前已入库</h3>
            <p>{{ originalReview.purchase_no }} · 串号 {{ originalReview.imei }} · ¥{{ originalReview.amount }}</p>
            <p class="secondary">{{ originalReview.mode === 'test' ? '测试验收' : '正式同步' }} · 博远账套 {{ originalReview.account_id }}。只准备这一台，下一步核对商品和仓库。不会重开本站采购或付款。</p>
            <el-button type="primary" :loading="busy" :disabled="interactionLocked || loading" @click="prepareOriginal">准备这张原采购单</el-button>
        </section>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="notice"><el-button v-if="!view" link type="primary" :disabled="interactionLocked || loading" @click="refresh">重新读取采购任务</el-button></el-alert>
        <template v-if="view">
            <el-alert v-if="isPilot" title="正式试单 · 仅此设备，1 元挂账，不付款" type="info" :closable="false" show-icon class="notice" />
            <el-alert v-if="!connectionMatches" title="当前账套与原采购单不一致。请切回原账套，再继续处理。" type="warning" :closable="false" show-icon class="notice" />
            <p v-if="pricing?.required && editable" :class="pricing.status === 'known' ? 'secondary' : 'reason'" aria-live="polite">
                <template v-if="pricing.status === 'known'">回收定价人：<strong>{{ localPricer }}</strong> · {{ buyerDisplay ? '博远采购业务员：' + buyerDisplay : '请选择这位定价人在博远的员工档案。' }}</template>
                <template v-else>{{ pricing.message || '无法确认本批设备的实际定价人，暂不能提交。' }}</template>
            </p>
            <section class="status-panel" :class="nextStep.tone" aria-live="polite">
                <h3 class="next-title">{{ nextStep.title }}</h3>
                <p>{{ nextStep.detail }}</p>
                <p v-if="showTaskReason" class="reason">{{ view.task.reason }}</p>
                <div v-if="view.task.external_no" class="external-no">博远采购单号 <strong>{{ view.task.external_no }}</strong></div>
                <p v-if="pricing?.required && !editable && view.task.external_no" class="secondary">采购业务员：{{ buyerReceiptLabel }}</p>
                <div v-if="canOpenPayable" class="status-actions"><el-button type="primary" :disabled="interactionLocked || loading" @click="openPayable">处理应付款</el-button></div>
                <el-button v-if="canReadPayable && !canOpenPayable" link type="primary" @click="viewPayable">查看关联应付</el-button>
                <div v-if="currentAction" class="status-actions"><el-button type="primary" :loading="busy" :disabled="interactionLocked || loading" @click="processCurrent">{{ currentAction === 'submit' ? '立即处理本单' : '继续核对原单' }}</el-button></div>
                <div v-if="canVerify" class="verify">
                    <el-input v-if="!view.task.external_no" v-model.trim="externalNo" aria-label="已知博远采购单号" placeholder="输入已知的博远采购单号" :disabled="interactionLocked" maxlength="64" @keyup.enter="verify" />
                    <el-button type="primary" :disabled="!externalNo || interactionLocked" :loading="busy" @click="verify">核对博远原单</el-button>
                </div>
                <p v-if="view.can_operate === false" class="secondary">采购资料只读，当前账号不能提交或修改这张采购单。</p>
            </section>
            <el-alert v-if="inventory.status === 'failed'" title="本批设备的库存查询发现异常" type="warning" show-icon :closable="false" class="notice"><el-button link @click="detailsExpanded = true">查看库存结果</el-button></el-alert>
            <el-alert v-if="view.issues?.length && editable" title="请先核对以下资料" type="warning" :closable="false" class="notice"><p v-for="(issue, index) in view.issues" :key="index">{{ issue }}</p></el-alert>
            <PurchasePaymentReview v-if="!isPilot && view.task.status === 'succeeded' && connectionMatches" ref="paymentForm" :key="view.task.id" :purchase="view" :config="config" :target="paymentTarget" :read-only="readOnly" @changed="refresh(true)" @config-changed="config = $event; refresh(true)" />
            <button v-if="!editable" type="button" class="details-toggle" :aria-expanded="detailsExpanded" @click="detailsExpanded = !detailsExpanded">{{ detailsExpanded ? '收起单据详情' : '查看单据详情' }} <span>{{ detailsExpanded ? '收起' : '设备、库存和同步记录' }}</span></button>
            <div v-if="editable" class="mapped-archives">
                <button type="button" class="details-toggle" :aria-expanded="matchedExpanded" @click="matchedExpanded = !matchedExpanded">{{ missingFields.length ? "待补 " + missingFields.length + " 项资料" : "入库资料已齐全" }} <span>{{ matchedExpanded ? "收起已带入资料" : "查看或调整对应关系" }}</span></button>
                <dl v-if="!matchedExpanded && mappedSummary.length" class="mapped-summary"><div v-for="field in mappedSummary" :key="field.key"><dt>{{ field.name }}</dt><dd>{{ field.value }}</dd></div></dl>
            </div>
            <section v-if="editable ? visibleTopFields.length : detailsExpanded">
                <div class="section-heading"><h3>入库资料</h3><span>本站供应商：{{ view.source?.party?.name || '未提供' }}</span></div>
                <el-form label-position="top" class="association-form">
                    <el-form-item v-for="field in visibleTopFields" :key="field.key" :label="field.name" :required="field.key !== 'buyer_id' || !!pricing?.required">
                        <el-button class="pick-button" :disabled="!editable || interactionLocked || (field.key === 'buyer_id' && buyerLocked)" @click="openLookup(field)">{{ labelFor(field.key) }}</el-button>
                        <el-button v-if="field.key === 'buyer_id' && !pricing?.required && selection.buyer_id && editable" link :disabled="interactionLocked" @click="clearBuyer">清除</el-button>
                    </el-form-item>
                </el-form>
            </section>
            <section v-if="editable ? visibleSourceLines.length : detailsExpanded"><div class="section-heading"><h3>入库设备 · {{ sourceLines.length }} 台</h3><span>同批设备请选择同一个博远仓库。</span></div><el-table :data="visibleSourceLines" row-key="source_line_id" border><el-table-column label="本站设备" min-width="210"><template #default="{ row }"><div>{{ sourceLines.indexOf(row) + 1 }}. {{ row.product_name }}</div><code>{{ row.imei || row.sn || '缺少串号' }}</code></template></el-table-column><el-table-column label="数量" width="70"><template #default="{ row }">{{ row.quantity ?? '—' }}</template></el-table-column><el-table-column label="成本" width="110" align="right"><template #default="{ row }">¥{{ row.amount }}</template></el-table-column><el-table-column v-for="field in lineFields" :key="field.key" :label="'博远' + field.name" min-width="220"><template #default="{ row }"><span v-if="!matchedExpanded && lineSelection(String(row.source_line_id))?.[field.key]">{{ labelFor(field.key, row.source_line_id) }}</span><el-button v-else class="pick-button" :disabled="!editable || interactionLocked" @click="openLookup(field, row.source_line_id)">{{ labelFor(field.key, row.source_line_id) }}</el-button></template></el-table-column></el-table></section>
            <footer v-if="editable">
                <div>
                    <el-checkbox v-if="!isPilot && view.can_remember === true && (dirty || missingFields.length || matchedExpanded)" v-model="remember" :disabled="interactionLocked">下次沿用这组对应关系</el-checkbox>
                    <p v-if="remember && view.source?.purchase_source === 'ordinary_customer'">只有能唯一核对的回收规格才会记住；规格不全或有重名歧义时，商品只用于本单。</p>
                    <p v-if="missingFields.length">还需选择：{{ missingFields.slice(0, 3).join('、') }}{{ missingFields.length > 3 ? '等' : '' }}。</p>
                    <p v-if="!writeEnabled" class="reason">当前只能存草稿；请管理员检查账套连接和采购同步开关。</p>
                </div>
                <div class="header-actions">
                    <el-button :loading="busy" :disabled="interactionLocked" @click="saveDraft">存草稿</el-button>
                    <el-button v-if="writeEnabled" type="primary" :loading="busy" :disabled="!canSubmit || interactionLocked" @click="confirmSubmit">同步采购挂账</el-button>
                </div>
            </footer>
            <button v-if="editable" type="button" class="details-toggle" :aria-expanded="detailsExpanded" @click="detailsExpanded = !detailsExpanded">{{ detailsExpanded ? '收起单据详情' : '查看单据详情' }} <span>单号和同步记录</span></button>
            <template v-if="detailsExpanded">
                <section class="technical-details">
                    <h3>单据与同步记录</h3>
                    <dl><dt>本站采购单</dt><dd>{{ view.source?.source_document_no || view.task.business_no }}</dd><dt>入库批次</dt><dd>{{ view.source?.batch_ref || '未提供' }}</dd><dt>任务编号</dt><dd>{{ view.task.id }}</dd><dt>博远采购单号</dt><dd>{{ view.task.external_no || '尚未取得' }}</dd><dt>核对结果</dt><dd>{{ stateMessage }}</dd></dl>
                </section>
                <section v-if="view.task.status === 'succeeded' || inventory.task_id != null" class="inventory-panel">
                    <div class="section-heading"><h3>本批设备当前库存</h3><el-tag :type="inventory.status === 'failed' ? 'warning' : 'info'">{{ inventoryLabel }}</el-tag></div>
                    <p v-if="inventory.reason">{{ inventory.reason }}</p>
                    <p v-if="inventory.total_count != null && inventory.in_stock_count != null">当前在库 <strong>{{ inventory.in_stock_count }}</strong> / 本批 <strong>{{ inventory.total_count }}</strong> 台</p>
                    <p v-if="inventory.checked_at" class="secondary">核对时间 {{ purchaseCheckedAt(inventory.checked_at) }}<template v-if="inventory.query_date"> · 查询日期 {{ inventory.query_date }}</template></p>
                    <el-button v-if="canStartInventory" :loading="busy" :disabled="interactionLocked" @click="checkInventory">{{ inventoryActionLabel }}</el-button>
                </section>
                <section class="closure-panel">
                    <h3>处理进度与金额</h3>
                    <p v-if="!closure.stages.length" class="secondary">尚未取得本批处理进度。</p>
                    <div v-else class="closure-stages"><div v-for="stage in closure.stages" :key="stage.key" class="closure-stage" :class="stage.status"><div class="closure-stage-heading"><span>{{ stage.label }}</span><strong>{{ purchaseClosureStatus(stage.status) }}</strong></div><p>{{ stage.reason }}</p><p v-if="stage.key === 'remote_settlement' && stage.count != null">已登记 {{ stage.count }} 笔 · {{ purchaseMoney(stage.amount) }}</p><p v-else-if="stage.amount != null">金额 {{ purchaseMoney(stage.amount) }}</p><p v-if="stage.paid_amount != null || stage.unpaid_amount != null">已付 {{ purchaseMoney(stage.paid_amount) }} · 未付 {{ purchaseMoney(stage.unpaid_amount) }}</p></div></div>
                    <p v-for="(warning, index) in closure.warnings" :key="index" class="reason">{{ warning }}</p>
                </section>
            </template>
        </template>
        <el-empty v-else-if="!loading && !error && !originalReview" description="请从本站已入库采购详情进入博远核对" />
        <el-dialog v-model="lookupVisible" :title="'选择博远' + (lookup?.name || '资料').replace(/^博远/, '')" width="min(760px, 92vw)" top="6vh" append-to-body :close-on-click-modal="false" @closed="closeLookup"><p class="secondary">{{ productPickerHint || '仅选择当前账套已有的启用档案，不自动创建资料。' }}</p><el-input v-model="lookupKeyword" aria-label="搜索博远档案名称或编码" clearable placeholder="搜索名称 / 编码，按 Enter 查询" @keyup.enter="loadChoices(false)" @clear="loadChoices(false)"><template #append><el-button :loading="choiceLoading" @click="loadChoices(false)">搜索</el-button></template></el-input><el-alert v-if="lookupError" :title="lookupError" type="error" :closable="false" class="notice"><el-button link type="primary" @click="loadChoices(false)">重新读取</el-button></el-alert><el-table :data="choices" v-loading="choiceLoading" max-height="42vh" :empty-text="lookupError ? '读取失败，尚未确认可用档案' : choiceLoading ? '正在读取档案' : lookupDevice ? '没有匹配商品。请调整搜索词，或在博远维护对应的单串号商品后同步。' : '没有匹配的启用档案，请调整搜索条件'" class="choice-table"><el-table-column prop="name" label="名称" min-width="190" /><el-table-column prop="code" label="编码" min-width="130" /><el-table-column label="规格 / 所属公司" min-width="180"><template #default="{ row }">{{ [row.extra?.memory, row.extra?.color, row.extra?.version_type_name, row.extra?.company_name].filter(Boolean).join(' · ') }}</template></el-table-column><el-table-column width="80"><template #default="{ row }"><el-button link type="primary" :disabled="!connectionMatches || !editable || interactionLocked" @click="selectChoice(row)">选择</el-button></template></el-table-column></el-table><div class="dialog-footer"><span>{{ lookupError ? '未完成读取，已保留搜索条件' : '已读取 ' + choices.length + ' 条启用资料' }}</span><el-button v-if="choiceCursor" :loading="choiceLoading" @click="loadChoices(true)">加载下一页</el-button><el-button @click="lookupVisible = false">返回核对</el-button></div></el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import useUserStore from '@/stores/modules/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import * as api from '../api/purchase'
import { checkTaskReceipt, processCurrentAction, taskReceiptNotice } from '../utils/manual-task-receipt'
import PurchasePaymentReview from '../components/PurchasePaymentReview.vue'
import { purchaseSelection, purchaseSelectionProblem, purchaseSelectionComplete, purchaseStateMessage, purchaseRouteId, purchaseMissingFields, purchaseNextStep, purchaseClosure, purchaseClosureStatus, purchaseMoney, purchaseInventory, purchaseInventoryLabel, purchaseCheckedAt, type PurchaseSelection } from '../utils/purchase-form'

type LookupField = { key: string, name: string, resource: string, role?: string, sourceLineId?: string }
const props = defineProps<{ embedded?:boolean, originalTaskId?:number, originalSourceId?:number, paymentTarget?:{settlementId:number,taskId:number}, readOnly?:boolean }>()
const paymentForm = ref<InstanceType<typeof PurchasePaymentReview>>()
const route = useRoute()
const router = useRouter()
const user = useUserStore()
const config = ref<any>({}), view = ref<any>(null), taskId = ref(0), taskIds = ref<number[]>([]), loading = ref(false), busy = ref(false), error = ref('')
const selection = ref<PurchaseSelection>(purchaseSelection()), labels = ref<Record<string, any>>({}), remember = ref(false), externalNo = ref('')
const confirmingSubmit = ref(false), detailsExpanded = ref(false)
const originalReview = ref<any>(null)
const uncertainPurchaseKeys = ref<Set<string>>(new Set())
const purchaseKey = (task: any) => JSON.stringify([task?.mode, task?.connection_key, task?.id])
const awaitingReceipt = computed(() => view.value?.task?.status === 'awaiting_confirmation' && uncertainPurchaseKeys.value.has(purchaseKey(view.value.task)))
const interactionLocked = computed(() => busy.value || confirmingSubmit.value)
const topFields: LookupField[] = [{ key: 'company_id', name: '公司', resource: 'companies' }, { key: 'supplier_id', name: '供应商', resource: 'parties', role: 'supplier' }, { key: 'creator_id', name: '博远制单人', resource: 'staff' }, { key: 'buyer_id', name: '采购业务员', resource: 'staff', role: 'buyer' }]
const lineFields: LookupField[] = [{ key: 'product_id', name: '商品', resource: 'products' }, { key: 'warehouse_id', name: '仓库', resource: 'warehouses' }]
const sourceLines = computed<any[]>(() => view.value?.source?.lines || [])
const connectionMatches = computed(() => view.value?.active_connection === true)
const editable = computed(() => !props.readOnly && !!view.value?.editable && view.value?.can_operate !== false && connectionMatches.value && !awaitingReceipt.value)
const currentAction = computed(() => processCurrentAction(view.value?.task, !props.readOnly && view.value?.can_process_current === true && view.value?.write_enabled === true && connectionMatches.value && view.value?.can_operate !== false && !error.value))
const canVerify = computed(() => !props.readOnly && !!view.value?.can_verify && !currentAction.value && view.value?.can_operate !== false && connectionMatches.value && !['pending', 'processing', 'succeeded'].includes(view.value?.task?.status))
const inventory = computed(() => purchaseInventory(view.value?.inventory))
const inventoryLabel = computed(() => purchaseInventoryLabel(inventory.value))
const inventoryWorking = computed(() => ['pending', 'processing'].includes(inventory.value.status))
const canCheckInventory = computed(() => !props.readOnly && view.value?.can_check_inventory === true && view.value?.can_operate !== false && connectionMatches.value && view.value?.task?.status === 'succeeded')
const canContinueInventory = computed(() => canCheckInventory.value && view.value?.task?.mode === 'test' && view.value?.inventory?.can_process_current === true && inventory.value.status === 'pending')
const canStartInventory = computed(() => canCheckInventory.value && !error.value && ((!inventoryWorking.value && inventory.value.status !== 'succeeded') || canContinueInventory.value))
const inventoryActionLabel = computed(() => canContinueInventory.value ? '继续核对本批库存' : inventory.value.status === 'failed' ? '重新核对当前在库' : inventory.value.status === 'stale' ? '核对今天当前在库' : '核对当前在库')
const writeEnabled = computed(() => connectionMatches.value && view.value?.write_enabled === true)
const isPilot = computed(() => view.value?.production_pilot === true)
const pricing = computed(() => view.value?.pricing_buyer)
const buyerLocked = computed(() => !!pricing.value?.required && (pricing.value.status !== 'known' || !!pricing.value.mapped_id))
const buyerDisplay = computed(() => String(labels.value[':buyer_id']?.name || '').trim())
const localPricer = computed(() => String(pricing.value?.name || pricing.value?.local_ref || '未记录'))
const buyerReceiptLabel = computed(() => {
    const p = pricing.value
    if (p?.receipt_status === 'matched') {
        const name = p.returned_name || buyerDisplay.value || '员工编号已核对'
        return p.receipt_source === 'purchase_details' ? name + '（已核对博远采购明细）' : name
    }
    if (p?.receipt_status === 'not_supplied') return '原单未传采购业务员'
    if (p?.receipt_status === 'mismatch') return '博远回执与提交人员不一致，请核对原单，勿重发'
    return '博远未返回可核对的人员信息'
})
const canSubmit = computed(() => editable.value && writeEnabled.value && purchaseSelectionComplete(selection.value, pricing.value)
    && (!pricing.value?.required || !!buyerDisplay.value))
const stateMessage = computed(() => purchaseStateMessage(view.value?.task, view.value?.result, view.value?.inventory))
const missingFields = computed(() => purchaseMissingFields(selection.value, pricing.value))
const matchedExpanded = ref(false)
const showAllChoices = computed(() => !editable.value || matchedExpanded.value)
const visibleTopFields = computed(() => topFields.filter(f => showAllChoices.value || ((f.key !== 'buyer_id' || (pricing.value?.required && !buyerLocked.value)) && !(selection.value as any)[f.key])))
const visibleSourceLines = computed(() => sourceLines.value.filter(r => showAllChoices.value || lineFields.some(f => !lineSelection(String(r.source_line_id))?.[f.key])))
const mappedSummary = computed(() => {
    const items = topFields.filter(f => (selection.value as any)[f.key] && labels.value[':'+f.key]?.name)
        .map(f => ({ key:f.key, name:f.name, value:labels.value[':'+f.key].name }))
    if (sourceLines.value.length === 1) {
        const id = String(sourceLines.value[0].source_line_id)
        for (const f of lineFields) if (lineSelection(id)?.[f.key] && labels.value[id+':'+f.key]?.name)
            items.push({ key:f.key, name:f.name, value:labels.value[id+':'+f.key].name })
    }
    return items
})
const closure = computed(() => purchaseClosure(view.value?.closure))
const nextStep = computed(() => isPilot.value && view.value?.task?.status === 'succeeded' && view.value?.result?.verified === true
    ? { title: '正式采购已核对', detail: '在博远按下方采购单号或串号查看。此单为挂账，不登记付款。', tone: 'success', action: undefined }
    : awaitingReceipt.value
    ? { title: '尚未收到入库回执', detail: '请刷新原任务或联系管理员核对，不要再次提交。', tone: 'warning', action: undefined }
    : currentAction.value ? { title: currentAction.value === 'submit' ? '原采购单等待处理' : '原采购单等待核对', detail: currentAction.value === 'submit' ? '可立即处理已确认的这张单，不必重新录入。' : '只查询已提交的这张单，不会再次入库。', tone: 'progress', action: undefined }
    : purchaseNextStep(view.value?.task, { editable: editable.value, canVerify: canVerify.value, complete: purchaseSelectionComplete(selection.value, pricing.value), enabled: writeEnabled.value, canOperate: view.value?.can_operate, operatorComplete: closure.value.operatorComplete, canCheckInventory: canCheckInventory.value, inventory: inventory.value, closure: closure.value, payment: view.value?.payment }))
const showTaskReason = computed(() => !!view.value?.task?.reason && ['confirming', 'attention', 'needs_review', 'blocked', 'failed'].includes(view.value.task.status) && view.value.task.reason !== nextStep.value.detail)
const sourceNo = computed(() => String(view.value?.source?.source_document_no || view.value?.task?.business_no || '').trim())
// The route exists only if installed and granted. This is navigation, never an ERP payment command.
const canOpenPayable = computed(() => !isPilot.value && !props.embedded && nextStep.value.action === 'payable' && connectionMatches.value && !!sourceNo.value
    && closure.value.available && !closure.value.stages.some((s: any) => s.status === 'permission_denied')
    && router.hasRoute('hsx_erp_payable') && user.rules.includes('hsx_erp_payable_items_payment'))
const canReadPayable = computed(() => !props.embedded && connectionMatches.value && !!sourceNo.value && router.hasRoute('hsx_erp_payable') && user.rules.includes('hsx_erp_payable_party_items'))
function payableLocation() {
    const imei = sourceLines.value.length === 1 ? String(sourceLines.value[0]?.imei || '') : ''
    return { name: 'hsx_erp_payable', query: { source_no: sourceNo.value, ...(imei ? {imei} : {}) } }
}
function viewPayable() {
    if(canReadPayable.value) return router.push(payableLocation())
}
function openPayable() {
    if (!canOpenPayable.value || interactionLocked.value || loading.value) return
    return router.push(payableLocation())
}
const dirty = computed(() => editable.value && (JSON.stringify(selection.value) !== savedSelection || remember.value))
const lookupVisible = ref(false), lookup = ref<LookupField | null>(null), lookupKeyword = ref(''), choices = ref<any[]>([]), choiceCursor = ref(''), choiceLoading = ref(false), lookupError = ref('')
let request = 0, choiceRequest = 0, savedSelection = '', mounted = true
const message = (err: any) => String(err?.msg || err?.message || '操作未完成，请刷新任务核对最新状态').slice(0, 400)
const labelKey = (key: string, lineId = '') => lineId + ':' + key
function lineSelection(id: string): any { return selection.value.lines.find(line => line.source_line_id === String(id)) }
function labelFor(key: string, lineId = '') { const name = labels.value[labelKey(key, String(lineId))]?.name; if (name) return name; const selected = lineId ? lineSelection(lineId)?.[key] : (selection.value as any)[key]; return selected ? editable.value ? '已选择，请点开核对资料' : '档案名称暂不可用' : editable.value ? '请选择博远' + ([...topFields, ...lineFields].find(field => field.key === key)?.name || '资料').replace(/^博远/, '') : '本次未指定' }
function applyView(value: any, preserveExternal = false) {
    if (view.value?.task?.id !== value.task.id) matchedExpanded.value = false
    if (view.value?.task?.id !== value.task.id) detailsExpanded.value = false
    if (value.task.status !== 'awaiting_confirmation') uncertainPurchaseKeys.value.delete(purchaseKey(value.task))
    const knownInput = preserveExternal && view.value?.task?.id === value.task.id && !value.task.external_no ? externalNo.value : ''
    view.value = value; selection.value = purchaseSelection(value.selection, value.source); externalNo.value = value.task.external_no || knownInput; remember.value = false
    const nextLabels: Record<string, any> = {}
    for (const [key, name] of [['company_id', 'company'], ['supplier_id', 'supplier'], ['creator_id', 'creator'], ['buyer_id', 'buyer']]) nextLabels[labelKey(key)] = value.labels?.[name]
    for (const line of value.labels?.lines || []) { nextLabels[labelKey('product_id', String(line.source_line_id))] = line.product; nextLabels[labelKey('warehouse_id', String(line.source_line_id))] = line.warehouse }
    labels.value = nextLabels; savedSelection = JSON.stringify(selection.value)
}
async function refresh(silent: boolean | any = false) {
    if (interactionLocked.value || (silent === true && (dirty.value || lookupVisible.value || externalNo.value !== String(view.value?.task?.external_no || '')))) return
    const token = ++request
    if (silent !== true) loading.value = true
    try {
        // Config is only for the optional "enable payment experiment" management dialog.
        // Viewing and reviewing a document use its server-computed capabilities, not global settings.
        let settings = {}
        if (user.rules.includes('hsx_boyuan_config') && user.rules.includes('hsx_boyuan_save_config')) {
            try { settings = (await api.getConfig()).data || {} } catch { /* settings must never block an order read */ }
        }
        if (!mounted || token !== request) return
        config.value = settings
        if (!taskId.value) {
            const routeTaskId = purchaseRouteId(props.embedded ? props.originalTaskId : route.query.taskid || route.query.task_id)
            if (routeTaskId) taskId.value = routeTaskId
            else {
                const sourceId = purchaseRouteId(props.embedded ? props.originalSourceId : route.query.purchase_id)
                if (!sourceId) return
                const captured: any = await api.capturePurchaseSource(sourceId)
                if (!mounted || token !== request) return
                originalReview.value=captured.data?.original_review || null
                if(originalReview.value){error.value='';return}
                const ids: number[] = (captured.data.task_ids || [captured.data.task_id]).map(purchaseRouteId).filter(Boolean)
                taskIds.value = [...new Set(ids)]; taskId.value = purchaseRouteId(captured.data.task_id)
            }
        }
        if (!taskId.value) throw new Error('尚未取得采购任务，请从采购详情重新进入')
        if (!taskIds.value.length) taskIds.value = [taskId.value]
        const result: any = await api.getPurchase(taskId.value)
        if (mounted && token === request) { if (purchaseRouteId(result?.data?.task?.id) !== taskId.value) throw new Error('采购回执不属于这张原单，未打开其他单据。'); applyView(result.data, true); error.value = '' }
    } catch (err) { if (mounted && token === request) error.value = message(err) }
    finally { if (token === request) loading.value = false }
}
async function prepareOriginal(){
    const sourceId = () => purchaseRouteId(props.embedded ? props.originalSourceId : route.query.purchase_id)
    const scope=originalReview.value, id=sourceId()
    if(!scope || !id || interactionLocked.value || loading.value)return
    const token=++request;busy.value=true
    try{
        const result=(await api.preparePurchaseOriginal(id,scope))?.data
        if(!mounted || token!==request || id!==sourceId())return
        const task=purchaseRouteId(result?.task_id)
        if(!task)throw Error('未取得原单核对任务，请刷新查询，不要另开采购。')
        taskId.value=task;taskIds.value=[task];originalReview.value=null;error.value=''
    }catch(e){if(mounted && token===request){error.value=message(e);originalReview.value=null}}
    finally{if(token===request)busy.value=false}
    if(mounted && token===request && taskId.value)await refresh()
}
async function switchTask(id: number) {
    if (interactionLocked.value || id === taskId.value) return
    if (dirty.value) { try { await ElMessageBox.confirm('切换批次将放弃未保存的核对，是否继续？', '切换入库批次', { type: 'warning' }) } catch { return } }
    taskId.value = id; view.value = null; await refresh()
}
function closeLookup() { choiceRequest++; lookup.value = null; choiceLoading.value = false }
const lookupDevice = computed(() => lookup.value?.key === 'product_id' ? sourceLines.value.find(line => String(line.source_line_id) === lookup.value?.sourceLineId) : null)
const productPickerHint = computed(() => lookupDevice.value ? `本机：${lookupDevice.value.product_name || '采购设备'} · ${String(lookupDevice.value.imei || '').length} 位串号。只列出串号规则匹配的商品，请再核对型号和规格。` : '')
function openLookup(field: LookupField, sourceLineId?: string) {
    if (!editable.value || interactionLocked.value) return
    if (field.key === 'buyer_id' && buyerLocked.value) return
    lookup.value = { ...field, sourceLineId: sourceLineId === undefined ? undefined : String(sourceLineId) }
    lookupKeyword.value = field.key === 'buyer_id' && pricing.value?.required ? String(pricing.value.name || '') : String(lookupDevice.value?.product_name || '')
    choices.value = []; choiceCursor.value = ''; lookupVisible.value = true; loadChoices(false)
}
async function loadChoices(append = false) {
    if (!lookup.value || !editable.value || (append && (choiceLoading.value || !choiceCursor.value))) return
    const field = { ...lookup.value }, token = ++choiceRequest, id = taskId.value, key = view.value.task.connection_key, cursor = append ? choiceCursor.value : '1'
    choiceLoading.value = true; lookupError.value = ''
    if (!append) { choices.value = []; choiceCursor.value = '' }
    try {
        const result: any = await api.getReferences({ resource: field.resource, role: field.key === 'product_id' ? 'purchase_product' : (field.role || ''), ...(field.key === 'product_id' ? { serial_length: String(lookupDevice.value?.imei || '').length } : {}), keyword: lookupKeyword.value.trim(), cursor, limit: 50, enabled_only: true, expected_connection_key: view.value.task.connection_key, ...(['staff','warehouses'].includes(field.resource) && selection.value.company_id ? { company_id: selection.value.company_id } : {}) })
        if (token !== choiceRequest || taskId.value !== id || !lookupVisible.value || view.value?.task.connection_key !== key || !connectionMatches.value) return
        if (result.data?.code && result.data.code !== 'OK') throw new Error(result.data.message || '资料读取未完成')
        const data = result.data?.data || {}, rows = (data.items || []).filter((item: any) => !!item.enabled && !item.stale && (field.role !== 'supplier' || !!item.extra?.is_supplier) && (field.role !== 'buyer' || !!item.extra?.is_buyer))
        const previous = append ? choices.value : []
        choices.value = [...previous, ...rows.filter((item: any) => !previous.some(old => String(old.id) === String(item.id)))]
        choiceCursor.value = data.next_cursor && String(data.next_cursor) !== String(cursor) ? String(data.next_cursor) : ''
    } catch (err) { if (token === choiceRequest) lookupError.value = message(err) }
    finally { if (token === choiceRequest) choiceLoading.value = false }
}
function selectChoice(item: any) {
    if (!lookup.value || !editable.value || interactionLocked.value || !item.enabled || item.stale) return
    const field = lookup.value
    if (field.key === 'company_id' && selection.value.company_id && selection.value.company_id !== String(item.id)) {
        selection.value.creator_id = ''; delete labels.value[labelKey('creator_id')]
        if (!pricing.value?.mapped_id) { selection.value.buyer_id = ''; delete labels.value[labelKey('buyer_id')] }
        selection.value.lines.forEach(line => { line.warehouse_id = ''; delete labels.value[labelKey('warehouse_id', line.source_line_id)] })
    }
    if (field.key === 'warehouse_id' && !selection.value.company_id && item.company?.id && item.extra?.company_id === item.company.id) {
        selection.value.company_id = item.company.id; labels.value[labelKey('company_id')] = item.company
    }
    if (field.sourceLineId) { const line = lineSelection(field.sourceLineId); if (!line) return; line[field.key] = String(item.id) }
    else (selection.value as any)[field.key] = String(item.id)
    labels.value[labelKey(field.key, field.sourceLineId)] = { id: String(item.id), name: item.name, code: item.code }; error.value = ''; lookupVisible.value = false; closeLookup()
}
function clearBuyer() { if (editable.value && !interactionLocked.value) { selection.value.buyer_id = ''; delete labels.value[labelKey('buyer_id')] } }
function input() { return { revision: view.value.task.revision, mode: view.value.task.mode, connection_key: view.value.task.connection_key, selection: purchaseSelection(selection.value, view.value.source), remember: remember.value && view.value.can_remember === true } }
function acceptReceipt(data: any, original: any) {
    const task = checkTaskReceipt(data?.task, Number(original.id), Number(original.revision))
    if (purchaseKey(task) !== purchaseKey(original)) throw new Error('回执不属于原账套，请刷新原单核对。')
    applyView(data); const notice = taskReceiptNotice(task, '入库'); ElMessage[notice.type](notice.text)
}
async function saveDraft() {
    if (!editable.value || interactionLocked.value) return
    busy.value = true; error.value = ''
    try { const result: any = await api.savePurchaseDraft(taskId.value, input()); applyView(result.data); ElMessage.success('核对已保存，尚未发送') }
    catch (err) { error.value = message(err) }
    finally { busy.value = false }
}
async function confirmSubmit() {
    if (!canSubmit.value || interactionLocked.value) return
    confirmingSubmit.value = true
    try {
        const pilot = isPilot.value
        const text = pilot
            ? `正式账套 89096：串号 ${sourceLines.value[0]?.imei}，1 台，1.00 元。本操作只登记一次挂账采购，不付款。未收到回执时只核对原单，不重复发送。`
            : `采购单 ${view.value.task.business_no}，共 ${sourceLines.value.length} 台。提交后将在博远创建入库单，不能再改对应关系。`
        const confirmBuyer = pricing.value?.required === true && !pricing.value.mapped_id
        const buyerNote = pricing.value?.required ? ` 本站定价人「${localPricer.value}」对应博远采购业务员「${buyerDisplay.value}」。` : ''
        try { await ElMessageBox.confirm(text + buyerNote, pilot ? '提交这张 1 元挂账采购单？' : '确认提交这张采购入库单？', { type: 'warning', confirmButtonText: confirmBuyer ? '确认人员对应并入库' : '确认入库', cancelButtonText: '返回核对', closeOnClickModal: false }) } catch { return }
        if (!canSubmit.value || busy.value) return
        busy.value = true; error.value = ''
        const original = view.value.task; ++request; loading.value = false
        try { const result: any = await api.submitPurchase(taskId.value, { ...input(), ...(confirmBuyer ? { confirm_pricing_buyer: true } : {}), ...(pilot ? { confirm_pilot: true } : {}) }); acceptReceipt(result.data, original) }
        catch (err) {
            const selectionIssue = purchaseSelectionProblem(err)
            if (selectionIssue) { error.value = '未提交：' + selectionIssue; return }
            uncertainPurchaseKeys.value.add(purchaseKey(view.value.task)); error.value = '未收到提交回执，请核对原任务。' + message(err)
            view.value.editable = false; view.value.can_submit = false
        }
        finally { busy.value = false }
    } finally { confirmingSubmit.value = false }
}
async function verify() {
    if (!canVerify.value || !externalNo.value.trim() || interactionLocked.value) return
    busy.value = true; error.value = ''
    const original = view.value.task; ++request; loading.value = false
    try { const result: any = await api.verifyPurchase(taskId.value, { revision: original.revision, mode: original.mode, connection_key: original.connection_key, external_no: externalNo.value.trim() }); acceptReceipt(result.data, original) }
    catch (err) { error.value = message(err) }
    finally { busy.value = false }
}
async function processCurrent() {
    const action = currentAction.value, original = view.value?.task
    if (!action || interactionLocked.value || loading.value || !original) return
    busy.value = true; ++request
    try {
        if (action === 'submit') { try { await ElMessageBox.confirm('将处理本张已确认的采购入库单，不会新建本站任务。', '立即处理本单？', { type: 'warning', confirmButtonText: '处理本单', cancelButtonText: '返回' }) } catch { return } }
        if (view.value?.task !== original || currentAction.value !== action) return
        const body = { revision: original.revision, mode: original.mode, connection_key: original.connection_key, intent: 'process_current' }
        const res: any = await (action === 'submit' ? api.submitPurchase(taskId.value, body) : api.verifyPurchase(taskId.value, body))
        acceptReceipt(res.data, original)
    } catch (err) { error.value = '未取得本次处理结果，请刷新原单核对，不要重复提交。' + message(err) }
    finally { busy.value = false }
}
async function checkInventory() {
    if (!canStartInventory.value || interactionLocked.value) return
    busy.value = true; error.value = ''
    const original = view.value.task; ++request; loading.value = false
    try {
        const result: any = await api.checkPurchaseInventory(taskId.value, { revision: original.revision, mode: original.mode, connection_key: original.connection_key })
        const task = checkTaskReceipt(result.data?.task, Number(original.id), Number(original.revision))
        if (purchaseKey(task) !== purchaseKey(original)) throw new Error('回执不属于原账套。')
        applyView(result.data, true)
        if (inventory.value.status === 'succeeded') ElMessage.success('本批 ' + inventory.value.in_stock_count + ' 台设备已核对在库，未修改库存。')
        else if (canContinueInventory.value) ElMessage.info('本批尚未核对完，可继续核对下一批串号。')
        else if (inventoryWorking.value) ElMessage.info('在库核对尚未完成，页面会刷新结果。')
        else ElMessage.warning(inventory.value.reason || '尚未确认全部在库，请查看核对结果。')
    }
    catch (err) { error.value = '未取得本次库存核对回执，请刷新原单查看结果。' + message(err) }
    finally { busy.value = false }
}
async function mayClose() {
    if (interactionLocked.value || loading.value || paymentForm.value?.isOpen()) return false
    if (!dirty.value) return true
    try { await ElMessageBox.confirm('离开会放弃未保存的采购核对，是否继续？', '离开核对', { type: 'warning' }); return true } catch { return false }
}
if (!props.embedded) onBeforeRouteLeave(mayClose)
defineExpose({ mayClose })
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
    refresh()
    // Opening a verified original receipt is a read, not an ongoing job. Manual refresh remains available.
    if (props.embedded && props.readOnly) return
    timer = setInterval(() => { if (view.value && !editable.value) refresh(true) }, 6000)
})
onUnmounted(() => { mounted = false; request++; choiceRequest++; if (timer) clearInterval(timer) })
</script>

<style scoped lang="scss">
.mapped-archives { margin-bottom:12px; padding:0 16px; border:1px solid var(--el-border-color-lighter); border-radius:6px; background:var(--el-fill-color-lighter); }
.mapped-archives .mapped-summary { display:flex; flex-wrap:wrap; gap:8px 24px; padding:0 0 12px; }
.mapped-summary div { display:flex; gap:6px; align-items:baseline; overflow-wrap:anywhere; }
.mapped-summary dt { color:var(--el-text-color-regular); }
.boyuan-purchase { max-width: 1280px; margin: 0 auto; color: var(--el-text-color-primary); padding-bottom: 28px; }header { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; padding: 8px 0 20px; }h2 { margin: 0; font-size: 22px; }h3 { margin: 0; font-size: 16px; }p { margin: 8px 0; line-height: 1.7; font-size: 13px; }header p, .secondary, .section-heading span, footer p { color: var(--el-text-color-secondary); font-size: 12px; }section { background: var(--el-bg-color); border: 1px solid var(--el-border-color-lighter); border-radius: 8px; padding: 20px; margin-bottom: 16px; }.status-panel { background: var(--el-fill-color-lighter); }.status-heading, .section-heading { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }.section-heading { justify-content: space-between; margin-bottom: 18px; }.reason { color: var(--el-color-warning-dark-2); }.notice { margin: 14px 0; }.association-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }.association-form :deep(.el-form-item) { margin-bottom: 0; }.pick-button { max-width: 100%; min-height: 34px; height: auto; white-space: normal; text-align: left; justify-content: flex-start; }.pick-button :deep(span) { overflow-wrap: anywhere; line-height: 1.5; }code { display: block; margin-top: 4px; font-size: 12px; color: var(--el-text-color-secondary); }footer { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px 20px; border: 1px solid var(--el-border-color-lighter); background: var(--el-bg-color); border-radius: 8px; }.actions { flex-shrink: 0; }.verify { margin-top: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }.verify .el-input { width: 280px; }.verify span { color: var(--el-text-color-secondary); font-size: 12px; }.choice-table { margin-top: 16px; }.dialog-footer { display: flex; gap: 12px; align-items: center; justify-content: flex-end; margin-top: 16px; }.dialog-footer span { margin-right: auto; color: var(--el-text-color-secondary); font-size: 12px; }.batch-select { width: 190px; }@media (max-width: 900px) { .association-form { grid-template-columns: 1fr; }footer { align-items: flex-start; flex-direction: column; }.section-heading { align-items: flex-start; flex-direction: column; } }
header { position: sticky; top: 0; z-index: 8; padding: 14px 0; margin-bottom: 8px; background: var(--el-bg-color-page); align-items: center; }header p { margin-bottom: 0; }.header-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }.header-actions :deep(.el-button + .el-button) { margin-left: 0; }.status-panel { border-left: 3px solid var(--el-color-primary); padding: 16px 20px; }.status-panel.warning { border-left-color: var(--el-color-warning); }.status-panel.success { border-left-color: var(--el-color-success); }.status-heading > strong { overflow-wrap: anywhere; }.status-heading > span { font-size: 12px; color: var(--el-text-color-regular); }.next-title { margin-top: 14px; font-size: 20px; line-height: 1.45; }.external-no { display: flex; gap: 10px; align-items: baseline; font-size: 12px; color: var(--el-text-color-regular); }.external-no strong { overflow-wrap: anywhere; color: var(--el-text-color-primary); }.closure-panel { padding: 14px 20px; }.closure-panel .section-heading { margin-bottom: 4px; }.closure-panel .secondary { margin-bottom: 0; }.closure-panel .reason { margin-bottom: 0; }.closure-stages { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 20px; margin-top: 16px; }.closure-stage { padding: 14px 0; border-top: 1px solid var(--el-border-color-lighter); min-width: 0; }.closure-stage-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; }.closure-stage-heading strong { color: var(--el-color-warning); white-space: nowrap; }.closure-stage.verified .closure-stage-heading strong { color: var(--el-color-success); }.closure-stage.unavailable .closure-stage-heading strong, .closure-stage.permission_denied .closure-stage-heading strong { color: var(--el-text-color-regular); }.closure-stage p { color: var(--el-text-color-regular); font-size: 12px; overflow-wrap: anywhere; }.closure-totals { display: flex; gap: 12px 28px; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid var(--el-border-color-lighter); font-size: 12px; color: var(--el-text-color-regular); }.closure-totals strong { margin-left: 6px; color: var(--el-text-color-primary); font-variant-numeric: tabular-nums; }.association-form { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 24px; }.section-heading .completion-caption { font-weight: 400; margin-left: 8px; }.technical-details { padding-top: 0; padding-bottom: 0; }.details-toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; min-height: 46px; padding: 0; border: 0; color: var(--el-text-color-regular); background: transparent; font: inherit; font-size: 13px; text-align: left; cursor: pointer; }.details-toggle span { font-size: 12px; color: var(--el-color-primary); }.details-toggle:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 3px; }dl { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 10px 18px; padding: 4px 0 16px; margin: 0; font-size: 12px; color: var(--el-text-color-regular); }dt, dd { margin: 0; overflow-wrap: anywhere; }dd { color: var(--el-text-color-primary); }.dialog-footer { align-items: center; }.dialog-footer span { min-width: 0; }footer { margin-bottom: 16px; }.reason { color: var(--el-text-color-regular); }.status-panel.warning .reason, .closure-panel > .reason { color: var(--el-color-warning-dark-2); }
@media (max-width: 900px) { header { align-items: flex-start; flex-direction: column; gap: 12px; }.header-actions { justify-content: flex-start; }.association-form { grid-template-columns: 1fr; }.closure-stages { grid-template-columns: repeat(2, minmax(0, 1fr)); }.closure-panel .section-heading { align-items: flex-start; }.dialog-footer { flex-wrap: wrap; }.dialog-footer span { flex-basis: 100%; } }
@media (max-height: 740px) { header { padding-top: 8px; padding-bottom: 10px; }section { padding-top: 16px; padding-bottom: 16px; margin-bottom: 12px; }.status-panel { padding-top: 14px; padding-bottom: 14px; }.next-title { margin-top: 10px; }.section-heading { margin-bottom: 14px; } }
.inventory-panel { padding: 14px 20px; }.inventory-panel .section-heading { margin-bottom: 4px; }.inventory-panel p { margin: 6px 0; }.inventory-panel strong { font-variant-numeric: tabular-nums; }.inventory-panel .secondary { margin-bottom: 0; }
.status-panel .next-title { margin-top: 0; }.status-panel .external-no, .status-panel .status-actions { margin-top: 12px; }.details-toggle { gap: 12px; font-weight: 600; color: var(--el-text-color-primary); }.details-toggle span { color: var(--el-text-color-secondary); font-weight: 400; }.technical-details { padding: 16px 20px; }.technical-details h3 { margin-bottom: 10px; }.technical-details dl { padding: 0; }.status-panel .verify { margin-top: 12px; }.status-panel .reason { overflow-wrap: anywhere; }
</style>
