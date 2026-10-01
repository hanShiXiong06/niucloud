<template>
    <HsxPage title="运单与打印记录" subtitle="取号、打印、取消分别核实。这里只管理快递任务，不会自动关闭订单或退款。" class="express-tasks">
        <template #extra><el-button @click="router.push('/hsx_express/config')">接入配置</el-button><el-button :loading="loading" @click="load">刷新</el-button></template>
        <HsxNotice type="warning" title="结果待核实的任务，请勿重复取号" description="接口超时不代表失败。先核对原渠道的业务单和运单，确认结果后再处理；不要切换服务商重下。打印失败应处理原单，不重新生成运单。" />
        <div class="surface">
            <el-form class="search" @submit.prevent="search">
                <el-input v-model.trim="filters.keyword" clearable placeholder="业务单号 / 运单号 / 任务号" aria-label="搜索业务单号、运单号或任务号" />
                <el-select v-model="filters.state" clearable placeholder="全部任务状态" aria-label="任务状态"><el-option v-for="item in stateOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select>
                <el-button type="primary" native-type="submit">查询</el-button><el-button @click="reset">重置</el-button>
            </el-form>
            <HsxNotice v-if="error" type="error" title="记录加载失败" :description="error" :default-expanded="true" :closable="false" />
            <el-table v-loading="loading" :data="rows" row-key="id" empty-text="暂无任务；请从商城真实订单发起电子面单取号。">
                <el-table-column label="业务与运单" min-width="230"><template #default="{ row }"><strong>{{ row.waybill_no || '尚未取得运单号' }}</strong><div class="muted">{{ businessName(row.business_type) }} · {{ row.business_no || '订单号待读取，请查看任务详情' }}</div></template></el-table-column>
                <el-table-column label="快递 / 打印方式" min-width="170"><template #default="{ row }"><div>{{ row.carrier_name || carrierName(row.carrier) }} · {{ productName(row) }}</div><div class="muted">{{ isSfTask(row) ? '顺丰直连 · PDF面单' : row.print_type === 'CLOUD' ? '快递100 · 云打印' : '快递100 · 电脑打印' }}</div><el-tag v-if="isSandboxTask(row)" size="small" type="warning">沙箱测试 · 非真实寄件</el-tag></template></el-table-column>
                <el-table-column label="进度" min-width="220"><template #default="{ row }"><div class="progress"><el-tag :type="row.waybill_no ? 'success' : 'info'" size="small">{{ row.waybill_no ? '已取号' : '未确认取号' }}</el-tag><el-tag :type="taskState(row.state).type" size="small">{{ row.state_name || taskState(row.state).text }}</el-tag></div><p class="message">{{ row.message || taskState(row.state).next }}</p></template></el-table-column>
                <el-table-column label="更新时间" min-width="160"><template #default="{ row }"><span class="time">{{ displayTime(row.update_at || row.create_at) }}</span></template></el-table-column>
                <el-table-column label="操作" width="180" fixed="right"><template #default="{ row }"><div class="table-actions"><el-button link type="primary" @click="openDetail(row.id)">详情</el-button><el-button v-if="canDownloadSfPdf(row) || (labelLinks(row.labels || row.label).length && !['cancelled', 'cancelling', 'cancel_unknown'].includes(row.state))" link type="primary" @click="openDetail(row.id)">{{ isSfTask(row) ? 'PDF 面单' : '打开面单' }}</el-button><el-button v-if="canRecover(row)" link type="warning" :disabled="!!actingId" @click="recover(row)">恢复原申请</el-button><el-button v-if="canReprint(row)" link type="primary" :disabled="!!actingId" @click="reprint(row)">{{ isSfTask(row) ? '获取原单 PDF' : '补打原单' }}</el-button><el-button v-if="canCancel(row)" link type="danger" :disabled="!!actingId" @click="cancel(row)">{{ isSfTask(row) && row.state === 'cancel_unknown' ? '重试确认取消' : '取消运单' }}</el-button></div></template></el-table-column>
            </el-table>
            <div class="pagination"><el-pagination v-model:current-page="filters.page" v-model:page-size="filters.limit" :total="total" :page-sizes="[15, 30, 50]" layout="total, prev, pager, next, sizes" @current-change="load" @size-change="search" /></div>
        </div>
        <HsxDrawer v-model="detailVisible" title="快递任务详情" subtitle="操作针对原运单；业务订单状态请回商城核对。" :body-loading="detailLoading" size="md">
            <template v-if="detail">
                <HsxNotice v-if="isSandboxTask(detail)" type="warning" title="沙箱任务，不代表真实寄件" description="此任务只用于接口联调，不得用来确认真实商城发货。下载 PDF 也不能证明实际出纸或顺丰已揽收。" :default-expanded="true" :closable="false" />
                <HsxNotice :type="taskState(detail.state).type === 'danger' ? 'error' : taskState(detail.state).type === 'success' ? 'success' : 'warning'" :title="detail.state_name || taskState(detail.state).text" :description="detail.message || taskState(detail.state).next" :default-expanded="true" :closable="false" />
                <el-descriptions :column="1" border class="details"><el-descriptions-item label="业务来源">{{ businessName(detail.business_type) }}</el-descriptions-item><el-descriptions-item label="业务单">{{ detail.business_no || '订单号待读取，可用下方定位编号核实' }}</el-descriptions-item><el-descriptions-item label="运单号">{{ detail.waybill_no || '尚未确认' }}</el-descriptions-item><el-descriptions-item label="快递产品">{{ detail.carrier_name || carrierName(detail.carrier) }} · {{ productName(detail) }}</el-descriptions-item><el-descriptions-item label="取号结果">{{ detail.waybill_no ? '已取得运单号' : '未确认取得运单号' }}</el-descriptions-item><el-descriptions-item label="打印结果">{{ printStatus(detail) }}</el-descriptions-item><el-descriptions-item label="创建时间">{{ displayTime(detail.create_at) }}</el-descriptions-item><el-descriptions-item label="更新时间">{{ displayTime(detail.update_at) }}</el-descriptions-item></el-descriptions>
                <div v-if="detailLabels.length && !['cancelled', 'cancelling', 'cancel_unknown'].includes(detail.state)" class="label-files"><h3>原单面单文件</h3><p class="muted">打开后使用浏览器或 PDF 阅读器打印。请核对纸张与缩放，文件可能有有效期。</p><a v-for="(url, index) in detailLabels" :key="url" :href="url" target="_blank" rel="noopener noreferrer" class="file-link">打开面单 {{ detailLabels.length > 1 ? index + 1 : '' }} ↗</a></div>
                <div v-if="isSfTask(detail)" class="label-files"><h3>顺丰 PDF 面单</h3><p class="muted">{{ detail.label_state === 'expired' ? '原 PDF 已过期，请查询原单并重新获取面单；不要重新取号。' : detail.label_state === 'ready' ? '点「打印面单」打开打印窗口，无需先下载。' : 'PDF 尚不可打印；先查询原顺丰单，再根据结果获取原单面单。' }}</p><div v-if="canDownloadSfPdf(detail)" class="pdf-actions"><HsxPdfPrint :context-key="detail.id" :loader="exportPdf" :disabled="!!actingId || detailLoading || !detailVisible" type="primary" /><HsxExport button-text="下载 PDF（备用）" :disabled="!!actingId || detailLoading" :exporter="exportPdf" @error="pdfError" /></div><p class="muted">打开打印窗口不代表已经出纸；实际打印、交件和揽收需分别核实。</p></div>
                <HsxNotice v-if="detail.print_type === 'IMAGE'" title="电脑是否实际出纸，需要人工确认" description="系统能确认面单文件生成，但无法检测你的本地打印机是否出纸；不要将“面单已生成”视为“客户已收货”。" />
                <HsxNotice v-if="canRecover(detail)" type="warning" title="可使用原账号与原包裹编号恢复申请" description="这不是只读查询。服务商已成功时返回原单；原请求未受理时，可能首次真正取号并计费。恢复后不会自动确认商城发货。" :default-expanded="true" :closable="false" />
                <p v-if="detail.cancel_unavailable_reason" class="muted">{{ detail.cancel_unavailable_reason }}</p>
                <div class="drawer-actions"><el-button v-if="detail.can_refresh" :loading="actingId === detail.id" @click="refreshOriginal(detail)">查询原顺丰单</el-button><el-button v-if="canRecover(detail)" type="warning" :loading="actingId === detail.id" @click="recover(detail)">恢复原申请</el-button><el-button v-if="canReprint(detail)" type="primary" :loading="actingId === detail.id" @click="reprint(detail)">{{ isSfTask(detail) ? '重新获取原单 PDF' : '补打原单' }}</el-button><el-button v-if="canCancel(detail)" type="danger" plain :loading="actingId === detail.id" @click="cancel(detail)">{{ isSfTask(detail) && detail.state === 'cancel_unknown' ? '重试确认取消' : '取消运单' }}</el-button><el-button :loading="detailLoading" @click="openDetail(detail.id)">刷新本站记录</el-button></div>
                <HsxFold title="操作记录与定位编号" summary="供核对服务商结果使用，不展示账号密钥"><p class="muted">本地任务：{{ detail.task_no }}<br />服务商任务：{{ detail.provider_task_id || '尚未返回' }}<br />内部关联标识：{{ detail.business_id || '—' }}</p><el-empty v-if="!detail.logs?.length" description="暂无操作记录" :image-size="60" /><div v-for="(log, index) in detail.logs || []" :key="log.id || index" class="log"><span>{{ displayTime(log.create_at || log.created_at || log.at) }}</span><strong :title="log.operation || log.action || log.event">{{ log.action_name || operationLabel(log.operation || log.action || log.event) }}</strong><p>{{ log.message || log.result_message || '—' }}</p></div></HsxFold>
            </template>
            <HsxNotice v-else-if="detailError" type="error" title="任务详情读取失败" :description="detailError" :default-expanded="true" />
        </HsxDrawer>
    </HsxPage>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { HsxDrawer, HsxFold, HsxNotice, HsxPage } from '@/addon/hsx_components/core'
import HsxExport from '@/addon/hsx_components/components/HsxExport/index.vue'
import HsxPdfPrint from '@/addon/hsx_components/components/HsxPdfPrint/index.vue'
import { cancelExpressTask, downloadExpressTaskPdf, getExpressTask, getExpressTasks, recoverExpressTask, refreshExpressTask, reprintExpressTask } from '../../api'
import { canCancel, canRecover, canReprint, displayTime, labelLinks, operationLabel, requestError, stateOptions, taskState } from '../../utils/presentation'
import { canDownloadSfPdf, isSandboxTask, isSfTask, validatedPdf } from '../../utils/sf'

const router = useRouter()
const filters = reactive({ keyword: '', state: '', page: 1, limit: 15 })
const loading = ref(false), error = ref(''), rows = ref<any[]>([]), total = ref(0), actingId = ref(0)
const detailVisible = ref(false), detailLoading = ref(false), detailError = ref(''), detail = ref<any>(null)
const detailLabels = computed(() => labelLinks(detail.value?.labels || detail.value?.label))
let listRequest = 0, detailRequest = 0
const carrierName = (carrier: string) => ({ shunfeng: '顺丰', jd: '京东' } as Record<string, string>)[carrier] || carrier || '—'
const businessName = (business: string) => ({ phone_shop: '手机商城', hsx_recycle: '回收业务' } as Record<string, string>)[business] || '业务发货'
const productName = (task: Record<string, any>) => task.exp_type_name || (isSfTask(task) ? '产品见原顺丰单' : task.exp_type || '—')
function printStatus(task: Record<string, any>) {
    if (isSfTask(task)) return task.label_state === 'ready' ? 'PDF 已生成；出纸、交件需人工核对' : task.label_state === 'expired' ? 'PDF 已过期，需获取原单面单' : 'PDF 尚未就绪'
    if (task.state === 'printed') return '云打印回执成功'
    if (task.state === 'print_failed') return '打印失败，保留原运单'
    if (task.state === 'print_pending') return '等待云打印回执'
    if (task.print_type === 'IMAGE' && task.waybill_no) return '面单文件已生成，实际出纸请人工核对'
    return '尚未确认'
}
async function exportPdf() {
    if (actingId.value || detailLoading.value || !detailVisible.value || !detail.value || !canDownloadSfPdf(detail.value)) throw new Error('当前任务不可使用 PDF，请刷新后核实')
    const current = { ...detail.value }
    const blob = await validatedPdf(await downloadExpressTaskPdf(Number(current.id)))
    if (!detailVisible.value || !detail.value || current.id !== detail.value.id || !canDownloadSfPdf(detail.value)) throw new Error('任务已变化，请重新核对面单')
    return { blob, filename: `${isSandboxTask(current) ? '沙箱测试-' : ''}顺丰面单-${current.waybill_no || current.task_no}.pdf` }
}
function pdfError(error: any) { ElMessage.error(requestError(error, 'PDF 下载失败，请刷新原任务后重试')) }
async function refreshOriginal(task: Record<string, any>) {
    if (actingId.value || !task.can_refresh || !isSfTask(task)) return
    actingId.value = Number(task.id)
    try { await refreshExpressTask(Number(task.id)); await load(); await openDetail(Number(task.id)) }
    catch (error) { ElMessage.error(requestError(error, '原单查询失败，未重新取号')); await load(); await openDetail(Number(task.id)) }
    finally { actingId.value = 0 }
}
async function load() {
    const current = ++listRequest
    loading.value = true; error.value = ''
    try {
        const result = await getExpressTasks({ ...filters })
        if (current !== listRequest) return
        rows.value = result.data?.data || []; total.value = Number(result.data?.total || 0)
    } catch (err: any) { if (current === listRequest) { error.value = requestError(err, '请稍后刷新重试'); rows.value = []; total.value = 0 } }
    finally { if (current === listRequest) loading.value = false }
}
function search() { filters.page = 1; void load() }
function reset() { filters.keyword = ''; filters.state = ''; search() }
async function openDetail(id: number) {
    const current = ++detailRequest
    detailVisible.value = true; detailLoading.value = true; detailError.value = ''; detail.value = null
    try { const result = await getExpressTask(id); if (current === detailRequest) detail.value = result.data || null }
    catch (err: any) { if (current === detailRequest) detailError.value = requestError(err, '请关闭后重试') }
    finally { if (current === detailRequest) detailLoading.value = false }
}
async function reprint(task: Record<string, any>) {
    if (actingId.value || !canReprint(task)) return
    try { await ElMessageBox.confirm(isSfTask(task) ? '仅为原顺丰运单重新获取 PDF，不重新取号，不会自动发给打印机或确认商城发货。打印前请检查原面单是否已出纸，避免重复贴单。' : task.print_type === 'CLOUD' ? '请先检查打印机和原面单是否已出纸。本次会再次向原运单关联的云打印机发送打印任务，可能再次出纸，不会重新取号。确认仍需补打后再继续。' : '请先检查原面单是否已经打印。本次将重新获取原运单的面单文件，重复打印可能再次出纸，不会重新取号。官方补打有时效与次数限制，以返回结果为准。', isSfTask(task) ? '重新获取原单 PDF' : '补打原单：请先检查是否已出纸', { confirmButtonText: isSfTask(task) ? '确认获取原单 PDF' : '已检查，确认补打', cancelButtonText: '暂不处理', type: 'warning', closeOnClickModal: false }) } catch { return }
    actingId.value = Number(task.id)
    try {
        await reprintExpressTask(Number(task.id))
        ElMessage.success(isSfTask(task) ? '原单 PDF 请求已处理，请查看下载状态；未自动打印或发货' : '补打请求已处理，请查看原任务结果')
        await load(); await openDetail(Number(task.id))
    } catch (err: any) {
        ElMessage.error(requestError(err, '补打请求未能确认，请核对原任务结果，不要重复取号'))
        await load(); await openDetail(Number(task.id))
    } finally { actingId.value = 0 }
}
async function recover(task: Record<string, any>) {
    if (actingId.value || !canRecover(task)) return
    try {
        await ElMessageBox.confirm('系统将使用原账号、原包裹编号再次请求服务商。若原请求已成功，将返回原运单；若原请求未受理，可能首次真正生成运单并产生费用。这不是只读查询，也不会自动确认商城发货。确认此包裹仍需寄出，再继续。', '恢复原申请：可能取号并计费', { confirmButtonText: '确认恢复，知悉可能计费', cancelButtonText: '先人工核实', type: 'warning', closeOnClickModal: false })
    } catch { return }
    actingId.value = Number(task.id)
    try {
        await recoverExpressTask(Number(task.id))
        ElMessage.success('原申请恢复请求已处理，请核对运单结果；商城发货仍需确认')
        await load(); await openDetail(Number(task.id))
    } catch (err: any) {
        ElMessage.error(requestError(err, '恢复结果未能确认，请继续核实原任务，不要重新取号'))
        await load(); await openDetail(Number(task.id))
    } finally { actingId.value = 0 }
}
async function cancel(task: Record<string, any>) {
    if (actingId.value || !canCancel(task)) return
    let reason = ''
    try {
        const retry = isSfTask(task) && task.state === 'cancel_unknown'
        const result = await ElMessageBox.prompt(retry ? '将再次向原顺丰订单请求取消并核实结果，可能执行原单取消，不会创建新运单。未确认取消前禁止重新下单；不会关闭商城订单，也不处理退款或库存。请填写本次核对原因。' : '只取消这张快递运单，不关闭商城订单、不退款。已交件的运单可能无法取消；请填写原因。', retry ? '重试确认取消' : '取消原运单', { confirmButtonText: retry ? '确认重试原单取消' : '确认取消运单', cancelButtonText: '暂不操作', inputPlaceholder: '例如：客户修改地址，尚未交件', inputValidator: (value: string) => value?.trim().length >= 2 && value.trim().length <= 200 || '请填写 2–200 字的取消原因', type: 'warning' })
        reason = result.value.trim()
    } catch { return }
    actingId.value = Number(task.id)
    try {
        await cancelExpressTask(Number(task.id), reason)
        ElMessage.success('取消请求已处理，请查看最终结果')
        await load(); await openDetail(Number(task.id))
    } catch (err: any) {
        ElMessage.error(requestError(err, '取消请求未能确认，请核对原任务状态，不要重新开单'))
        await load(); await openDetail(Number(task.id))
    } finally { actingId.value = 0 }
}
onMounted(load)
</script>

<style scoped lang="scss">
.pdf-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.pdf-actions .el-button + .el-button{margin-left:0}
.express-tasks { color: #1f2937; }.surface { margin-top: 16px; padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }.search { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }.search > .el-input { width: 290px; }.search > .el-select { width: 190px; }.search .el-button + .el-button { margin-left: 0; }.muted { margin-top: 5px; color: #64748b; font-size: 12px; line-height: 1.65; }.subtle { margin-top: 4px; color: #94a3b8; font-size: 11px; overflow-wrap: anywhere; }.progress,.table-actions { display: flex; flex-wrap: wrap; gap: 8px; }.table-actions .el-button + .el-button { margin-left: 0; }.message { margin: 7px 0 0; color: #64748b; font-size: 12px; line-height: 1.6; }.time { white-space: nowrap; font-size: 12px; }.pagination { margin-top: 18px; overflow-x: auto; }.details { margin: 18px 0; }.label-files { padding: 14px; margin-bottom: 16px; border: 1px solid #dce2eb; border-radius: 8px; }.label-files h3 { margin: 0; font-size: 14px; }.file-link { display: inline-flex; margin: 10px 16px 0 0; color: var(--el-color-primary); font-size: 13px; }.drawer-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 18px 0; }.drawer-actions .el-button + .el-button { margin-left: 0; }.log { display: grid; grid-template-columns: 150px 1fr; gap: 6px 12px; padding: 12px 0; border-top: 1px solid #edf0f5; font-size: 12px; }.log span { color: #64748b; }.log p { grid-column: 2; margin: 0; color: #475569; line-height: 1.65; overflow-wrap: anywhere; }@media(max-width:640px) { .surface { padding: 12px; }.search > .el-input { width: 100%; }.search > .el-select { flex: 1; min-width: 140px; }.log { grid-template-columns: 1fr; }.log p { grid-column: 1; } }
</style>
