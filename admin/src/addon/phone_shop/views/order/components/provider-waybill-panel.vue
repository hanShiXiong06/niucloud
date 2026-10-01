<template>
    <section class="provider-waybill" v-loading="busy">
        <div class="provider-waybill__head">
            <strong>{{ provider.label }} · 先取号，再交件</strong>
            <el-button v-if="provider.config_url" link type="primary" @click="openPage(provider.config_url)">账号与打印配置</el-button>
        </div>
        <p class="provider-waybill__hint">先在下方勾选本次包裹商品，默认发货地址来自商城地址库。取号或打印不会改变订单状态；包裹实际交给快递员后，再点击底部「确认发货」。</p>
        <el-alert v-if="provider.environment === 'sandbox' || task.environment === 'sandbox'" title="顺丰沙箱测试：不是实际寄件，不可确认真实商城发货" type="warning" :closable="false" />
        <p v-if="provider.key === 'hsx_express_sf_direct'" class="provider-waybill__hint">顺丰电子面单不等于预约上门取件。取号后点「打印面单」，无需先下载；实际出纸和交件请现场核实。</p>
        <div class="provider-waybill__actions">
            <span>重量</span><el-input-number v-model="weight" :min="0.01" :max="100" :precision="2" :step="0.1" :disabled="hasActiveTask" size="small"/><span>kg</span>
            <el-button type="primary" :disabled="!goodsIds.length || hasActiveTask || failedToLoad" @click="createTask">{{ task.task_id ? '重新申请面单' : '申请面单' }}</el-button>
            <el-button :disabled="!goodsIds.length" @click="operate('query')">刷新任务</el-button>
        </div>
        <el-alert v-if="!goodsIds.length" title="请先勾选下方需要装入同一包裹的商品" type="info" :closable="false" />
        <el-alert v-if="failedToLoad" title="任务读取失败，请刷新任务确认是否已取号。为避免重复取号，暂不允许再次申请。" type="warning" :closable="false" />
        <div v-if="task.task_id" class="provider-waybill__result">
            <div><strong>{{ task.waybill_no || '尚未取得运单号' }}</strong><el-tag size="small" class="ml-[8px]">{{ task.state_name || task.state }}</el-tag></div>
            <p>{{ task.message || '请核实任务状态及纸质面单，再确认实际交件。' }}</p>
            <p v-if="task.cancel_unavailable_reason">{{ task.cancel_unavailable_reason }}</p>
            <p v-if="task.can_confirm_delivery === false" class="provider-waybill__hint">{{ task.environment === 'sandbox' ? '这是沙箱任务，不能用于真实订单发货。' : '此任务当前不能确认发货，请核对原单状态及提示。' }}</p>
            <p v-if="task.waybill_no && !task.express_company_id">未唯一匹配商城快递公司，暂不能确认发货。请在商城「物流公司」中检查{{ isSfWaybillTask(task) ? '顺丰公司及其承运编码' : '与本运单承运商一致且唯一的快递100编码' }}，再刷新任务；仅选择上方公司不能代替编码关联。</p>
            <div class="provider-waybill__actions">
                <a v-for="(url, index) in labelUrls" :key="url" :href="url" target="_blank" rel="noopener noreferrer" class="provider-waybill__link">打开面单{{ labelUrls.length > 1 ? index + 1 : '' }}打印</a>
                <HsxPdfPrint v-if="canDownloadProviderPdf(task)" :context-key="packageIdentity + ':' + task.task_id" :loader="exportPdf" :disabled="busy || failedToLoad" type="primary" size="small" />
                <HsxExport v-if="canDownloadProviderPdf(task)" size="small" button-text="下载 PDF（备用）" :disabled="busy || failedToLoad" :exporter="exportPdf" @error="pdfError" />
                <el-button v-if="task.can_refresh" size="small" @click="operate('refresh')">查询原顺丰单</el-button>
                <el-button v-if="canReprint" size="small" @click="reprintTask">{{ isSfWaybillTask(task) ? '重新获取原单 PDF' : task.print_type === 'CLOUD' ? '原单云补打' : '重新获取原单面单' }}</el-button>
                <el-button v-if="canCancel" size="small" type="danger" plain @click="cancelTask">{{ isSfWaybillTask(task) && task.state === 'cancel_unknown' ? '重试确认取消' : '取消本运单' }}</el-button>
                <el-button v-if="provider.tasks_url" size="small" link @click="openPage(provider.tasks_url)">查看任务日志</el-button>
            </div>
            <p v-if="task.print_type === 'CLOUD'" class="provider-waybill__hint">云打印已提交不代表已出纸；请等待打印回调或现场确认，打印失败补打原单，不要重新申请运单。</p>
            <p v-if="task.print_type === 'PDF'" class="provider-waybill__hint">{{ task.label_state === 'expired' ? 'PDF 已过期，请查询原单并重新获取 PDF，不要重新取号。' : 'PDF 生成、下载都不代表打印机已出纸、包裹已交件或快递已揽收。' }}</p>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import HsxExport from '@/addon/hsx_components/components/HsxExport/index.vue'
import HsxPdfPrint from '@/addon/hsx_components/components/HsxPdfPrint/index.vue'
import { electronicSheetProviderPdf, electronicSheetProviderTask } from '@/addon/phone_shop/api/electronic_sheet'
import { canDownloadProviderPdf, isSfWaybillTask, providerPdfBlob, providerTaskKey, SF_WAYBILL_PDF_CONFIRM, trustedWaybillLabels, WAYBILL_CREATE_CONFIRM, WAYBILL_REPRINT_CONFIRM } from '@/addon/phone_shop/utils/electronic-sheet-provider'

const props = defineProps<{ orderId: number | string; goodsIds: number[]; provider: Record<string, any> }>()
const emit = defineEmits(['task', 'busy'])
const router = useRouter()
const busy = ref(false)
const failedToLoad = ref(false)
const task = ref<Record<string, any>>({})
const weight = ref(1)
let sequence = 0
const packageIdentity = computed(() => `${props.orderId}:${[...props.goodsIds].sort((a, b) => a - b).join(',')}:${props.provider.key}`)
const hasActiveTask = computed(() => !!task.value.task_id && !['cancelled', 'failed'].includes(task.value.state))
const labelUrls = computed(() => trustedWaybillLabels(Array.isArray(task.value.labels) ? task.value.labels : task.value.label))
const canReprint = computed(() => !failedToLoad.value && task.value.can_reprint === true)
const canCancel = computed(() => !failedToLoad.value && task.value.can_cancel === true)
const openPage = (path: string) => window.open(router.resolve(path).href, '_blank', 'noopener')
const exportPdf = async () => {
    if (busy.value || failedToLoad.value || !canDownloadProviderPdf(task.value)) throw new Error('PDF 当前不可用，请刷新原任务')
    const current = { ...task.value }
    const identity = packageIdentity.value
    const blob = await providerPdfBlob(await electronicSheetProviderPdf(current))
    if (identity !== packageIdentity.value || current.task_id !== task.value.task_id || !canDownloadProviderPdf(task.value)) throw new Error('包裹或任务已变化，请重新核对面单')
    return { blob, filename: `${current.environment === 'sandbox' ? '沙箱测试-' : ''}顺丰面单-${current.waybill_no || current.task_id}.pdf` }
}
const pdfError = (error: any) => ElMessage.error(error?.msg || error?.message || 'PDF 下载失败，请刷新原任务后重试')

const operate = async (operation: string, reason = '', confirm = 0) => {
    if (busy.value || !props.goodsIds.length) return
    const token = ++sequence
    busy.value = true
    emit('busy', true)
    try {
        const providerKey = ['create', 'query'].includes(operation) ? props.provider.key : providerTaskKey(task.value, props.provider.key)
        const response = await electronicSheetProviderTask({ operation, reason, confirm, provider_key: providerKey, order_id: props.orderId, order_goods_ids: [...props.goodsIds], weight: weight.value })
        if (token !== sequence) return
        task.value = response.data || {}
        failedToLoad.value = false
        emit('task', task.value)
    } catch {
        if (token === sequence) { failedToLoad.value = true; emit('task', {}) }
    } finally {
        if (token === sequence) { busy.value = false; emit('busy', false) }
    }
}
const confirmOperation = async (operation: 'create' | 'reprint') => {
    if (busy.value || !props.goodsIds.length) return
    if (operation === 'create' && (hasActiveTask.value || failedToLoad.value)) return
    if (operation === 'reprint' && !canReprint.value) return
    const identity = packageIdentity.value
    const originalTaskId = task.value.task_id
    try {
        await ElMessageBox.confirm(operation === 'create' ? WAYBILL_CREATE_CONFIRM : isSfWaybillTask(task.value) ? SF_WAYBILL_PDF_CONFIRM : WAYBILL_REPRINT_CONFIRM, operation === 'create' ? '确认申请面单：可能计费' : isSfWaybillTask(task.value) ? '重新获取原单 PDF' : '确认补打原单', {
            type: 'warning', confirmButtonText: operation === 'create' ? '核对无误，申请面单' : isSfWaybillTask(task.value) ? '确认获取原单 PDF' : '已核实，补打原单', cancelButtonText: '暂不操作', closeOnClickModal: false
        })
    } catch { return }
    if (identity !== packageIdentity.value || originalTaskId !== task.value.task_id) {
        ElMessage.warning('包裹或任务已变化，请重新核对后操作')
        return
    }
    await operate(operation, '', operation === 'reprint' ? 1 : 0)
}
const createTask = () => confirmOperation('create')
const reprintTask = () => confirmOperation('reprint')
const cancelTask = async () => {
    if (busy.value || !canCancel.value) return
    let reason = ''
    try {
        const retry = isSfWaybillTask(task.value) && task.value.state === 'cancel_unknown'
        const result = await ElMessageBox.prompt(retry ? '再次向原顺丰订单请求取消并核实结果，可能执行原单取消，不会创建新单。未确认取消前禁止重下；商城订单、退款和库存均不变。' : '仅取消运单，不取消订单或退款。请确认包裹尚未交给快递员，并填写取消原因。', retry ? '重试确认取消' : '取消运单', {
            type: 'warning', confirmButtonText: retry ? '确认重试原单取消' : '确认未交件，取消运单', inputPlaceholder: '例如：收件地址需要修改',
            inputValidator: (value: string) => (value?.trim().length > 0 && value.trim().length <= 200) || '请填写 1 至 200 字的取消原因'
        })
        reason = result.value.trim()
    } catch { return }
    await operate('cancel', reason)
}
watch(packageIdentity, async () => {
    sequence++
    busy.value = false
    failedToLoad.value = false
    task.value = {}
    emit('task', {})
    emit('busy', false)
    await operate('query')
}, { immediate: true })
</script>

<style scoped>
.provider-waybill{padding:16px;margin:8px 0 16px;border:1px solid var(--el-border-color-light);border-radius:8px;background:var(--el-fill-color-light)}
.provider-waybill__head,.provider-waybill__actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.provider-waybill__head{justify-content:space-between}
.provider-waybill__hint{font-size:12px;line-height:1.7;color:var(--el-text-color-secondary);margin:8px 0 12px}.provider-waybill__result{margin-top:14px;padding-top:12px;border-top:1px solid var(--el-border-color-light)}
.provider-waybill__result p{font-size:12px;line-height:1.6;margin:8px 0}.provider-waybill__link{color:var(--el-color-primary);font-size:13px}.provider-waybill__actions{margin:8px 0}
</style>
