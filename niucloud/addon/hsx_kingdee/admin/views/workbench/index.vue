<template>
    <main class="kingdee-workbench">
        <header class="page-header">
            <div class="page-title"><el-icon :size="23"><Connection /></el-icon><h1>金蝶对接</h1><el-tag type="info" effect="plain">本地预览版</el-tag></div>
            <el-tooltip content="刷新当前视图"><el-button :icon="Refresh" :loading="loading" circle aria-label="刷新" @click="refresh" /></el-tooltip>
        </header>
        <el-alert title="正式同步未开放：当前仅接收新业务、生成预检和人工待办，不向金蝶账套写入。" type="warning" :closable="false" show-icon />
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="load-error"><template #default><el-button link @click="refresh">重新加载</el-button></template></el-alert>
        <el-tabs v-model="tab" @tab-change="changeTab">
            <el-tab-pane label="任务工作台" name="tasks">
                <div class="summary-row"><span>待处理 <strong>{{ count('blocked') }}</strong></span><span>等待预检 <strong>{{ count('pending') + count('retry') }}</strong></span><span>人工结案 <strong>{{ count('manual_closed') }}</strong></span></div>
                <div class="toolbar">
                    <el-radio-group v-model="filter.status" @change="search"><el-radio-button label="">全部</el-radio-button><el-radio-button label="blocked">待处理</el-radio-button><el-radio-button label="manual_closed">人工结案</el-radio-button></el-radio-group>
                    <el-input v-model="filter.keyword" clearable :prefix-icon="Search" placeholder="业务单号" class="search-input" @keyup.enter="search" @clear="search" /><el-button :icon="Search" @click="search">查询</el-button>
                </div>
                <el-table v-loading="loading" :data="tasks" row-key="id" stripe empty-text="暂无任务；开启预览后仅接收新业务，不回填历史记录。" @row-dblclick="openTask">
                    <el-table-column prop="business_no" label="业务单号" min-width="200" show-overflow-tooltip />
                    <el-table-column label="来源" width="105"><template #default="{ row }">{{ kindLabels[row.kind] || row.kind }}</template></el-table-column>
                    <el-table-column label="处理状态" width="115"><template #default="{ row }"><el-tag :type="statusType(row.status)" effect="light">{{ statusLabels[row.status] || row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="reason" label="待处理事项" min-width="240" show-overflow-tooltip />
                    <el-table-column label="员工提醒" width="135"><template #default="{ row }">{{ noticeLabels[row.notice_status] || row.notice_status }}</template></el-table-column>
                    <el-table-column label="更新时间" width="165"><template #default="{ row }">{{ time(row.update_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="95" fixed="right"><template #default="{ row }"><el-button link type="primary" @click="openTask(row)">查看处理</el-button></template></el-table-column>
                </el-table>
                <el-pagination v-model:current-page="filter.page" :page-size="20" :total="total" layout="total, prev, pager, next" @current-change="loadTasks" />
            </el-tab-pane>
            <el-tab-pane label="主数据映射" name="mappings">
                <div class="toolbar"><el-select v-model="mappingKind" clearable placeholder="全部类型" @change="mappingPage = 1; loadMappings()"><el-option v-for="(label, key) in mappingLabels" :key="key" :label="label" :value="key" /></el-select><el-button type="primary" :icon="Plus" @click="newMapping">新增映射</el-button></div>
                <el-table v-loading="loading" :data="mappings" empty-text="暂无映射。连接、站点和组织之间不会共用映射。">
                    <el-table-column label="类型" width="150"><template #default="{ row }">{{ mappingLabels[row.kind] }}</template></el-table-column>
                    <el-table-column prop="local_id" label="ERP 记录 ID" min-width="120" />
                    <el-table-column prop="external_number" label="金蝶编码（待远端验证）" min-width="240" />
                    <el-table-column prop="remark" label="备注" min-width="200" show-overflow-tooltip />
                    <el-table-column label="操作" width="80"><template #default="{ row }"><el-button link type="primary" @click="editMapping(row)">编辑</el-button></template></el-table-column>
                </el-table>
                <el-pagination v-model:current-page="mappingPage" :page-size="20" :total="mappingTotal" layout="total, prev, pager, next" @current-change="loadMappings" />
            </el-tab-pane>
            <el-tab-pane label="接入配置" name="config">
                <el-form v-loading="loading" :model="config" label-width="130px" class="connection-form" :disabled="saving || probing || !configLoaded">
                    <h2>运行状态</h2>
                    <el-form-item label="接收新业务"><el-radio-group v-model="config.mode"><el-radio label="disabled">关闭</el-radio><el-radio label="preview">开启预览</el-radio></el-radio-group></el-form-item>
                    <el-form-item label="正式同步"><el-tag type="info">未开放，需授权测试账套完成验收</el-tag></el-form-item>
                    <h2>测试账套</h2>
                    <el-form-item label="服务地址"><el-input v-model.trim="config.endpoint" placeholder="https://example.com/k3cloud/" maxlength="255" /></el-form-item>
                    <el-form-item label="账套 ID"><el-input v-model.trim="config.account_id" maxlength="255" /></el-form-item>
                    <el-form-item label="组织编码"><el-input v-model.trim="config.organization" maxlength="255" /></el-form-item>
                    <el-form-item label="集成用户"><el-input v-model.trim="config.username" autocomplete="off" maxlength="255" /></el-form-item>
                    <el-form-item label="应用 ID"><el-input v-model.trim="config.app_id" maxlength="255" /></el-form-item>
                    <el-form-item label="应用密钥"><el-input v-model="config.app_secret" type="password" show-password autocomplete="new-password" :placeholder="config.secret_configured ? '已加密保存；留空保持不变' : '尚未配置'" /></el-form-item>
                    <el-form-item v-if="config.secret_configured" label="清除密钥"><el-checkbox v-model="config.clear_secret">保存时清除现有密钥</el-checkbox></el-form-item>
                    <h2>异常责任人</h2>
                    <el-form-item label="本站员工"><el-select v-model="config.assignee_uid" filterable><el-option :value="0" label="暂不发送员工提醒" /><el-option v-for="person in staff" :key="person.uid" :value="Number(person.uid)" :label="person.real_name || person.username" /></el-select></el-form-item>
                    <el-form-item><el-button type="primary" :icon="Check" :loading="saving" @click="submitConfig">保存配置</el-button></el-form-item>
                </el-form>
                <section class="probe-section">
                    <h2>联调检查</h2>
                    <div class="probe-actions"><el-button :icon="Connection" :loading="probing" :disabled="!configLoaded || configDirty || saving" @click="probeConnection">检查已保存连接</el-button><el-tag v-if="configDirty" type="warning">配置未保存</el-tag><el-tag v-else type="info" effect="plain">仅查询，不写单</el-tag></div>
                    <el-alert v-if="probeResult" :type="probeResult.status === 'passed' ? 'success' : 'warning'" :title="probeResult.message" :closable="false" show-icon class="load-error" />
                </section>
            </el-tab-pane>
            <el-tab-pane label="审计日志" name="logs">
                <el-table v-loading="loading" :data="auditLogs" empty-text="暂无日志">
                    <el-table-column label="时间" width="180"><template #default="{ row }">{{ time(row.create_at) }}</template></el-table-column>
                    <el-table-column prop="task_id" label="任务 ID" width="110" />
                    <el-table-column prop="action" label="动作" width="130" />
                    <el-table-column prop="outcome" label="结果" width="130" />
                    <el-table-column prop="message" label="处理记录" min-width="300" />
                    <el-table-column prop="operator_uid" label="操作人 UID" width="120" />
                </el-table>
                <el-pagination v-model:current-page="logPage" :page-size="20" :total="logTotal" layout="total, prev, pager, next" @current-change="loadLogs" />
            </el-tab-pane>
        </el-tabs>
        <el-drawer v-model="drawer" title="任务处理" :size="drawerSize" destroy-on-close>
            <div v-loading="detailLoading" class="task-detail">
                <el-alert v-if="detailError" :title="detailError" type="error" :closable="false" show-icon />
                <template v-if="detail.id && !detailLoading && !detailError">
                    <div class="detail-heading"><h2>{{ detail.plan?.label || '待预检' }}</h2><el-tag :type="statusType(detail.status)">{{ statusLabels[detail.status] }}</el-tag></div>
                    <el-descriptions :column="1" border><el-descriptions-item label="业务单号">{{ detail.business_no || '无' }}</el-descriptions-item><el-descriptions-item label="金蝶单据">{{ detail.plan?.form_id || '待确认' }}</el-descriptions-item><el-descriptions-item label="金额含义">{{ detail.plan?.amount_role || '待确认' }}</el-descriptions-item><el-descriptions-item label="外部写入">未执行</el-descriptions-item><el-descriptions-item label="员工提醒">{{ noticeLabels[detail.notice_status] || detail.notice_status }}</el-descriptions-item></el-descriptions>
                    <section v-if="detail.plan?.requirements?.length" class="detail-section"><h3>需要处理</h3><ul><li v-for="item in detail.plan.requirements" :key="item">{{ item }}</li></ul></section>
                    <section v-if="detail.plan?.references?.length" class="detail-section"><h3>关联信息</h3><el-table :data="detail.plan.references"><el-table-column prop="label" label="类型" /><el-table-column prop="local_id" label="ERP ID" /><el-table-column label="金蝶编码"><template #default="{ row }">{{ row.external_number || '待映射' }}</template></el-table-column></el-table><el-button link type="primary" @click="drawer = false; tab = 'mappings'; loadMappings()">维护映射</el-button></section>
                    <section v-if="detail.status === 'manual_closed'" class="detail-section"><h3>人工结案</h3><p>{{ detail.resolution }}</p><p>外部单号：{{ detail.external_no || '未填写' }}</p><el-tag type="info">人工记录，不代表系统已核验金蝶</el-tag></section>
                    <section class="detail-section"><h3>处理日志</h3><el-timeline><el-timeline-item v-for="log in detail.logs" :key="log.id" :timestamp="time(log.create_at)">{{ log.message }}<span v-if="log.operator_uid" class="field-note">操作人 #{{ log.operator_uid }}</span></el-timeline-item></el-timeline></section>
                </template>
            </div>
            <template #footer><div class="drawer-actions"><el-button @click="drawer = false">关闭</el-button><el-button v-if="canAct && detail.status === 'blocked'" :icon="Bell" :disabled="acting" @click="notify">提醒责任人</el-button><el-button v-if="canAct" :icon="Refresh" :loading="acting" @click="recheck">重新预检</el-button><el-button v-if="canClose" type="primary" :disabled="acting" @click="closeDialog = true">人工结案</el-button></div></template>
        </el-drawer>
        <el-dialog v-model="closeDialog" title="人工结案" width="min(520px, 94vw)" :close-on-click-modal="false">
            <el-alert title="结案后不再自动重试。此操作不代表金蝶已完成同步。" type="warning" :closable="false" show-icon />
            <el-form label-position="top" class="dialog-form"><el-form-item label="处理说明" required><el-input v-model="resolution.note" type="textarea" :rows="4" maxlength="1000" show-word-limit /></el-form-item><el-form-item label="金蝶单号（可选）"><el-input v-model.trim="resolution.external_no" maxlength="100" /></el-form-item></el-form>
            <template #footer><el-button @click="closeDialog = false">取消</el-button><el-button type="primary" :loading="acting" :disabled="resolution.note.trim().length < 5" @click="closeTask">确认结案</el-button></template>
        </el-dialog>
        <el-dialog v-model="mappingDialog" title="主数据映射" width="min(520px, 94vw)" :close-on-click-modal="false">
            <el-form label-width="120px"><el-form-item label="类型"><el-select v-model="mapping.kind" :disabled="mappingEditing"><el-option v-for="(label, key) in mappingLabels" :key="key" :label="label" :value="key" /></el-select></el-form-item><el-form-item label="ERP 记录 ID"><el-input-number v-model="mapping.local_id" :min="1" :precision="0" :disabled="mappingEditing" /></el-form-item><el-form-item label="金蝶编码" required><el-input v-model.trim="mapping.external_number" maxlength="100" /></el-form-item><el-form-item label="备注"><el-input v-model="mapping.remark" maxlength="255" /></el-form-item></el-form>
            <template #footer><el-button @click="mappingDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="!mapping.external_number" @click="submitMapping">保存映射</el-button></template>
        </el-dialog>
    </main>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Bell, Check, Connection, Plus, Refresh, Search } from '@element-plus/icons-vue'
import * as api from '../../api'

const route = useRoute()
const tab = ref('tasks'), loading = ref(false), saving = ref(false), acting = ref(false), error = ref('')
const configLoaded = ref(false)
const probing = ref(false), probeResult = ref<any>(null), savedConfigStamp = ref('')
const config = reactive<Record<string, any>>({ mode: 'disabled', endpoint: '', account_id: '', username: '', app_id: '', app_secret: '', organization: '', assignee_uid: 0, revision: 0, clear_secret: false })
const configStamp = computed(() => JSON.stringify(config))
const configDirty = computed(() => configLoaded.value && configStamp.value !== savedConfigStamp.value)
watch(configStamp, () => { probeResult.value = null })
const filter = reactive({ page: 1, limit: 20, status: '', keyword: '' })
const tasks = ref<any[]>([]), counts = ref<any[]>([]), total = ref(0)
const mappings = ref<any[]>([]), mappingKind = ref(''), mappingPage = ref(1), mappingTotal = ref(0)
const staff = ref<any[]>([]), auditLogs = ref<any[]>([]), logPage = ref(1), logTotal = ref(0)
const drawer = ref(false), detailLoading = ref(false), detailError = ref(''), detail = ref<any>({}), closeDialog = ref(false)
const resolution = reactive({ note: '', external_no: '' })
const mappingDialog = ref(false), mappingEditing = ref(false)
const mapping = reactive({ kind: 'supplier', local_id: 1, external_number: '', remark: '' })
const kindLabels: Record<string, string> = { asset: '设备流水', quantity: '标品流水', account: '往来账', money: '资金流水' }
const mappingLabels: Record<string, string> = { supplier: '供应商', customer: '客户', asset_material: '设备物料', product: '标品物料', warehouse: '仓库' }
const statusLabels: Record<string, string> = { pending: '等待预检', processing: '预检中', retry: '等待重试', blocked: '待处理', preview: '仅预览', manual_closed: '人工结案', unknown: '结果待核实', partial: '部分成功' }
const noticeLabels: Record<string, string> = { pending: '待提醒', queued: '消息已入队', existing: '已有通知记录', unavailable: '提醒未入队', retry: '提醒待重试' }
const drawerSize = 'min(640px, 96vw)'
const canAct = computed(() => !detailLoading.value && !detailError.value && ['blocked', 'preview', 'retry'].includes(detail.value.status))
const canClose = computed(() => canAct.value && detail.value.status !== 'retry')
const statusType = (status: string) => status === 'blocked' || status === 'unknown' ? 'warning' : 'info'
const count = (status: string) => Number(counts.value.find(item => item.status === status)?.total || 0)
const time = (value: number) => value ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }) : '-'
let listRequest = 0, detailRequest = 0

async function loadTasks() {
    const request = ++listRequest
    loading.value = true; error.value = ''
    try {
        const result = await api.getTasks({ ...filter })
        if (request !== listRequest) return
        tasks.value = result.data.data; total.value = result.data.total; counts.value = result.data.counts
    } catch { if (request === listRequest) error.value = '任务加载失败，当前列表可能不是最新状态。' }
    finally { if (request === listRequest) loading.value = false }
}
async function loadMappings() {
    const request = ++listRequest
    loading.value = true; error.value = ''
    try {
        const result = await api.getMappings({ page: mappingPage.value, limit: 20, kind: mappingKind.value })
        if (request !== listRequest) return
        mappings.value = result.data.data; mappingTotal.value = result.data.total
    } catch { if (request === listRequest) error.value = '映射加载失败，请检查权限或重试。' }
    finally { if (request === listRequest) loading.value = false }
}
async function loadConfig() {
    const request = ++listRequest
    loading.value = true; error.value = ''; configLoaded.value = false
    try {
        const result = await api.getConfig()
        const people = await api.getStaff()
        if (request !== listRequest) return
        staff.value = people.data
        Object.assign(config, result.data, { app_secret: '', clear_secret: false }); savedConfigStamp.value = configStamp.value; probeResult.value = null; configLoaded.value = true
    } catch { if (request === listRequest) error.value = '配置加载失败，暂不能保存。' }
    finally { if (request === listRequest) loading.value = false }
}
async function loadLogs() {
    const request = ++listRequest
    loading.value = true; error.value = ''
    try {
        const result = await api.getLogs({ page: logPage.value, limit: 20 })
        if (request !== listRequest) return
        auditLogs.value = result.data.data; logTotal.value = result.data.total
    } catch { if (request === listRequest) error.value = '日志加载失败，请检查权限或重试。' }
    finally { if (request === listRequest) loading.value = false }
}
function refresh() { return tab.value === 'config' ? loadConfig() : tab.value === 'mappings' ? loadMappings() : tab.value === 'logs' ? loadLogs() : loadTasks() }
function changeTab() { refresh() }
function search() { filter.page = 1; loadTasks() }
async function openTask(row: { id: number }) {
    const request = ++detailRequest
    drawer.value = true; detailLoading.value = true; detailError.value = ''; detail.value = {}
    resolution.note = ''; resolution.external_no = ''
    try { const result = await api.getTask(row.id); if (request === detailRequest) detail.value = result.data }
    catch { if (request === detailRequest) detailError.value = '详情加载失败，请关闭后重试。' }
    finally { if (request === detailRequest) detailLoading.value = false }
}
async function recheck() {
    acting.value = true
    try { await api.retryTask(detail.value.id); await openTask({ id: detail.value.id }); await loadTasks() }
    catch { /* The shared request client displays the server's error. */ }
    finally { acting.value = false }
}
async function notify() {
    acting.value = true
    try { await api.requestNotice(detail.value.id); await openTask({ id: detail.value.id }) }
    catch { /* Keep the task available when notification is unavailable. */ }
    finally { acting.value = false }
}
async function closeTask() {
    acting.value = true
    try { await api.resolveTask(detail.value.id, { ...resolution }); closeDialog.value = false; await openTask({ id: detail.value.id }); await loadTasks(); ElMessage.success('已人工结案') }
    catch { /* Keep the form open for correction or retry. */ }
    finally { acting.value = false }
}
async function submitConfig() {
    if (!configLoaded.value) return
    try { await ElMessageBox.confirm('仅接收启用之后的新业务。关闭不删除已有任务；再次开启不回填停用期间的记录。确认保存？', '保存接入配置', { type: 'warning', confirmButtonText: '保存', cancelButtonText: '取消' }) }
    catch { return }
    saving.value = true
    try { const result = await api.saveConfig({ ...config }); Object.assign(config, result.data, { app_secret: '', clear_secret: false }); savedConfigStamp.value = configStamp.value; ElMessage.success('配置已保存') }
    catch { /* Preserve unsaved input for correction. */ }
    finally { saving.value = false }
}
async function probeConnection() {
    if (!configLoaded.value || configDirty.value || probing.value) return
    try { await ElMessageBox.confirm('将使用已保存的授权向金蝶服务发送一次只读查询，不创建、修改或审核单据。确认检查？', '只读连接检查', { confirmButtonText: '开始检查', cancelButtonText: '取消' }) }
    catch { return }
    const revision = config.revision, stamp = configStamp.value
    probing.value = true; probeResult.value = null
    try {
        const result = await api.probeConnection(revision)
        if (stamp === configStamp.value && configLoaded.value) probeResult.value = result.data
    } catch { if (stamp === configStamp.value) probeResult.value = { status: 'failed', message: '连接检查未完成，请根据错误提示处理后重试；未执行写单。' } }
    finally { probing.value = false }
}
function newMapping() { mappingEditing.value = false; Object.assign(mapping, { kind: 'supplier', local_id: 1, external_number: '', remark: '' }); mappingDialog.value = true }
function editMapping(row: any) { mappingEditing.value = true; Object.assign(mapping, row); mappingDialog.value = true }
async function submitMapping() {
    saving.value = true
    try { await api.saveMapping({ ...mapping }); mappingDialog.value = false; await loadMappings(); ElMessage.success('映射已保存，金蝶编码仍需远端验证') }
    catch { /* Keep the mapping editor open on failure. */ }
    finally { saving.value = false }
}
onMounted(async () => {
    await loadTasks()
    const id = Number(route.query.task_id)
    if (Number.isSafeInteger(id) && id > 0) await openTask({ id })
})
</script>

<style scoped lang="scss">
.kingdee-workbench { padding: 20px 24px; background: var(--el-bg-color); color: var(--el-text-color-primary); min-height: 70vh; letter-spacing: 0; }
.page-header, .page-title, .toolbar, .summary-row, .detail-heading, .drawer-actions { display: flex; align-items: center; gap: 12px; }
.page-header, .detail-heading { justify-content: space-between; margin-bottom: 18px; }
.page-title { flex-wrap: wrap; } h1 { font-size: 20px; margin: 0; font-weight: 600; } h2 { font-size: 17px; font-weight: 600; margin: 22px 0 18px; } h3 { font-size: 15px; margin: 0 0 16px; }
.load-error { margin-top: 12px; } :deep(.el-tabs__header) { margin-top: 18px; }
.summary-row { gap: 28px; padding: 10px 0 20px; color: var(--el-text-color-secondary); flex-wrap: wrap; } .summary-row strong { margin-left: 8px; font-size: 20px; color: var(--el-text-color-primary); }
.toolbar { margin-bottom: 16px; flex-wrap: wrap; } .search-input { width: 240px; max-width: 100%; } .toolbar :deep(.el-select) { width: 200px; }
.el-pagination { justify-content: flex-end; margin-top: 20px; max-width: 100%; overflow-x: auto; }
.connection-form { max-width: 740px; } .connection-form h2 { padding-top: 14px; border-top: 1px solid var(--el-border-color-light); } .connection-form h2:first-child { border: 0; }
.probe-section { max-width: 740px; border-top: 1px solid var(--el-border-color-light); padding-bottom: 20px; } .probe-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.field-note { color: var(--el-text-color-secondary); font-size: 12px; margin-left: 12px; } .task-detail { min-height: 140px; } .detail-heading h2 { margin: 0; }
.detail-section { padding-top: 24px; margin-top: 24px; border-top: 1px solid var(--el-border-color-light); } .detail-section li { line-height: 1.65; margin-bottom: 12px; } .detail-section ul { padding-left: 20px; } .detail-section p { overflow-wrap: anywhere; line-height: 1.7; }
.drawer-actions { justify-content: flex-end; flex-wrap: wrap; } .dialog-form { margin-top: 20px; } :deep(.el-descriptions__content) { overflow-wrap: anywhere; } :deep(.el-table .cell) { word-break: normal; }
@media (max-width: 900px) { .kingdee-workbench { padding: 16px; } .toolbar { gap: 10px; } .summary-row { gap: 18px; } }
</style>
