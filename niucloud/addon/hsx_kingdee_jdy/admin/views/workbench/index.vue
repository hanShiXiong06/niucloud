<template>
    <main class="jdy-workbench">
        <header class="page-heading">
            <div class="title"><el-icon :size="24"><Connection /></el-icon><h1>精斗云对接</h1><el-tag type="info" effect="plain">云进销存</el-tag></div>
            <el-tooltip content="刷新当前视图"><el-button :icon="Refresh" circle :loading="loading" :disabled="busy" aria-label="刷新" @click="refresh" /></el-tooltip>
        </header>
        <el-alert title="待授权账套验收 · 正式写单未开放" type="warning" :closable="false" show-icon />
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="error-banner" />
        <el-tabs v-model="tab" @tab-change="changeTab">
            <el-tab-pane label="任务工作台" name="tasks">
                <div class="metrics"><div><span>待处理</span><strong>{{ count('blocked') }}</strong></div><div><span>等待预检</span><strong>{{ count('pending') + count('retry') }}</strong></div><div><span>已预检，未写单</span><strong>{{ count('prepared') }}</strong></div><div><span>人工结案</span><strong>{{ count('manual_closed') }}</strong></div></div>
                <div class="toolbar">
                    <el-select v-model="filter.status" clearable placeholder="全部状态" @change="search"><el-option v-for="(label, value) in statuses" :key="value" :value="value" :label="label" /></el-select>
                    <el-input v-model="filter.keyword" clearable :prefix-icon="Search" placeholder="业务单号" class="search" @keyup.enter="search" @clear="search" /><el-button :icon="Search" @click="search">查询</el-button>
                </div>
                <el-table v-loading="loading" :data="tasks" row-key="id" empty-text="暂无任务，开启本地预检后接收新业务" @row-dblclick="openTask">
                    <el-table-column prop="business_no" label="ERP业务单号" min-width="180" show-overflow-tooltip />
                    <el-table-column label="来源" width="100"><template #default="{ row }">{{ kinds[row.kind] || row.kind }}</template></el-table-column>
                    <el-table-column label="状态" width="160"><template #default="{ row }"><el-tag :type="row.status === 'blocked' ? 'warning' : 'info'">{{ statuses[row.status] || row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="reason" label="待处理事项" min-width="230" show-overflow-tooltip />
                    <el-table-column label="员工提醒" width="130"><template #default="{ row }">{{ notices[row.notice_status] || row.notice_status }}</template></el-table-column>
                    <el-table-column label="更新时间" width="170"><template #default="{ row }">{{ time(row.update_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="85" fixed="right"><template #default="{ row }"><el-button link type="primary" @click="openTask(row)">处理</el-button></template></el-table-column>
                </el-table>
                <el-pagination v-model:current-page="filter.page" :total="total" :page-size="20" layout="total, prev, pager, next" @current-change="loadTasks" />
            </el-tab-pane>
            <el-tab-pane label="资料映射" name="mappings">
                <div class="toolbar"><el-select v-model="mappingKind" clearable placeholder="全部类型" @change="mappingPage = 1; loadMappings()"><el-option v-for="(label, value) in mappingKinds" :key="value" :label="label" :value="value" /></el-select><el-button type="primary" :icon="Plus" @click="editMapping()">新增映射</el-button></div>
                <el-table v-loading="loading" :data="mappings" empty-text="暂无映射">
                    <el-table-column label="类型" width="130"><template #default="{ row }">{{ mappingKinds[row.kind] }}</template></el-table-column>
                    <el-table-column prop="local_id" label="ERP记录ID" width="130" />
                    <el-table-column prop="external_number" label="精斗云编码" min-width="180" />
                    <el-table-column prop="unit_id" label="计量单位ID" width="140" />
                    <el-table-column label="核验状态" width="130"><el-tag type="info" effect="plain">待远端核验</el-tag></el-table-column>
                    <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
                    <el-table-column width="85" label="操作"><template #default="{ row }"><el-button link type="primary" @click="editMapping(row)">编辑</el-button></template></el-table-column>
                </el-table>
                <el-pagination v-model:current-page="mappingPage" :total="mappingTotal" :page-size="20" layout="total, prev, pager, next" @current-change="loadMappings" />
            </el-tab-pane>
            <el-tab-pane label="授权配置" name="config">
                <el-form v-loading="loading" :model="config" label-width="150px" class="config-form" :disabled="busy || !configLoaded">
                    <section><h2><el-icon><Key /></el-icon>应用身份</h2>
                        <el-form-item label="应用ID · clientId"><el-input v-model.trim="config.client_id" maxlength="100" autocomplete="off" /></el-form-item>
                        <el-form-item label="应用密钥"><div class="secret-field"><el-input v-model="config.client_secret" type="password" show-password autocomplete="new-password" :placeholder="config.client_secret_configured ? '留空保留已保存密钥' : 'clientSecret'" maxlength="2000" /><el-tag :type="config.client_secret_configured ? 'success' : 'info'">{{ config.client_secret_configured ? '已加密保存' : '未配置' }}</el-tag></div></el-form-item>
                    </section>
                    <section><h2><el-icon><OfficeBuilding /></el-icon>账套授权</h2>
                        <el-form-item label="授权Key · app_key"><el-input v-model.trim="config.app_key" maxlength="255" autocomplete="off" /></el-form-item>
                        <el-form-item label="授权密钥"><div class="secret-field"><el-input v-model="config.app_secret" type="password" show-password autocomplete="new-password" :placeholder="config.app_secret_configured ? '留空保留已保存密钥' : 'app_secret'" maxlength="2000" /><el-tag :type="config.app_secret_configured ? 'success' : 'info'">{{ config.app_secret_configured ? '已加密保存' : '未配置' }}</el-tag></div></el-form-item>
                        <el-form-item label="账套ID · accountId"><el-input v-model.trim="config.account_id" maxlength="100" placeholder="精斗云账套数字ID" /></el-form-item>
                        <el-form-item label="IDC域名 · domain"><el-input v-model.trim="config.domain" maxlength="255" placeholder="授权返回的域名，例如 https://vip1-gd.jdy.com" /></el-form-item>
                    </section>
                    <section><h2><el-icon><Setting /></el-icon>业务接收</h2>
                        <el-form-item label="运行模式"><el-radio-group v-model="config.mode"><el-radio label="disabled">关闭</el-radio><el-radio label="preview">本地预检</el-radio></el-radio-group></el-form-item>
                        <el-form-item label="异常责任人"><el-select v-model="config.assignee_uid" filterable><el-option label="暂不发送员工提醒" :value="0" /><el-option v-for="person in staff" :key="person.uid" :value="Number(person.uid)" :label="person.real_name || person.username" /></el-select></el-form-item>
                        <el-form-item label="连接状态"><el-tag :type="verified ? 'success' : 'info'">{{ verified ? '只读查询已验证' : '尚未验证' }}</el-tag><span v-if="config.probe_at" class="timestamp">{{ time(config.probe_at) }}</span></el-form-item>
                    </section>
                    <el-form-item><el-button type="primary" :icon="Check" :loading="saving" @click="submitConfig">保存配置</el-button><el-button :icon="Connection" :loading="probing" :disabled="dirty || !authComplete" @click="probe">检查已保存授权</el-button><el-tag v-if="dirty" type="warning">有未保存修改</el-tag></el-form-item>
                </el-form>
                <el-alert v-if="probeMessage" :title="probeMessage" type="success" show-icon :closable="false" />
            </el-tab-pane>
            <el-tab-pane label="操作日志" name="logs">
                <el-table v-loading="loading" :data="logs" empty-text="暂无操作记录"><el-table-column label="时间" width="180"><template #default="{ row }">{{ time(row.create_at) }}</template></el-table-column><el-table-column prop="task_id" label="任务ID" width="100" /><el-table-column prop="action" label="动作" width="130" /><el-table-column prop="outcome" label="结果" width="160" /><el-table-column prop="message" label="记录" min-width="320" /><el-table-column prop="operator_uid" label="操作人UID" width="120" /></el-table>
                <el-pagination v-model:current-page="logPage" :total="logTotal" :page-size="20" layout="total, prev, pager, next" @current-change="loadLogs" />
            </el-tab-pane>
        </el-tabs>
        <el-drawer v-model="drawer" title="任务处理" size="min(640px, 96vw)" destroy-on-close>
            <div v-loading="detailLoading" class="task-detail">
                <el-alert v-if="detailError" :title="detailError" type="error" :closable="false" />
                <template v-if="detail.id && !detailLoading && !detailError">
                    <div class="detail-heading"><h2>{{ detail.plan?.label || '等待预检' }}</h2><el-tag type="info">{{ statuses[detail.status] }}</el-tag></div>
                    <el-descriptions :column="1" border><el-descriptions-item label="ERP单号">{{ detail.business_no || '未记录' }}</el-descriptions-item><el-descriptions-item label="拟用精斗云单号">{{ detail.plan?.proposed_number || '待生成' }}</el-descriptions-item><el-descriptions-item label="远端写入">未执行</el-descriptions-item><el-descriptions-item label="员工提醒">{{ notices[detail.notice_status] }}</el-descriptions-item></el-descriptions>
                    <section v-if="detail.plan?.requirements?.length"><h3>需要处理</h3><ul><li v-for="item in detail.plan.requirements" :key="item">{{ item }}</li></ul></section>
                    <section v-if="detail.plan?.references?.length"><h3>资料对应</h3><el-table :data="detail.plan.references"><el-table-column prop="label" label="类型" /><el-table-column prop="local_id" label="ERP记录ID" /><el-table-column label="精斗云编码"><template #default="{ row }">{{ row.external_number || '待映射' }}</template></el-table-column></el-table><el-button link type="primary" @click="drawer = false; tab = 'mappings'; loadMappings()">维护映射</el-button></section>
                    <section v-if="detail.status === 'prepared'"><h3>账套验收待确认</h3><ul><li v-for="item in detail.plan?.remote_acceptance" :key="item">{{ item }}</li></ul></section>
                    <section v-if="detail.status === 'manual_closed'"><h3>人工处理记录</h3><p>{{ detail.resolution }}</p><p>外部单号：{{ detail.external_no || '未填写' }}</p></section>
                    <section><h3>处理记录</h3><el-timeline><el-timeline-item v-for="log in detail.logs" :key="log.id" :timestamp="time(log.create_at)">{{ log.message }}</el-timeline-item></el-timeline></section>
                </template>
            </div>
            <template #footer><div class="drawer-actions"><el-button @click="drawer = false">关闭</el-button><el-button v-if="canAct && detail.status === 'blocked'" :icon="Bell" :disabled="busy" @click="act('notice')">提醒责任人</el-button><el-button v-if="canAct" :icon="Refresh" :loading="acting" @click="act('retry')">重新预检</el-button><el-button v-if="canClose" type="primary" :disabled="busy" @click="openResolve">人工结案</el-button></div></template>
        </el-drawer>
        <el-dialog v-model="mappingDialog" title="资料映射" width="min(540px, 94vw)" :close-on-click-modal="false">
            <el-form label-width="120px" :disabled="saving"><el-form-item label="资料类型"><el-select v-model="mapping.kind" :disabled="mappingEditing"><el-option v-for="(label, value) in mappingKinds" :key="value" :label="label" :value="value" /></el-select></el-form-item><el-form-item label="ERP记录ID"><el-input-number v-model="mapping.local_id" :min="1" :precision="0" :disabled="mappingEditing" /></el-form-item><el-form-item label="精斗云编码" required><el-input v-model.trim="mapping.external_number" maxlength="100" /></el-form-item><el-form-item v-if="isMaterial" label="计量单位ID" required><el-input v-model.trim="mapping.unit_id" maxlength="64" /></el-form-item><el-form-item label="备注"><el-input v-model="mapping.remark" maxlength="255" /></el-form-item></el-form>
            <template #footer><el-button :disabled="saving" @click="mappingDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="!mapping.external_number || (isMaterial && !mapping.unit_id)" @click="submitMapping">保存映射</el-button></template>
        </el-dialog>
        <el-dialog v-model="resolveDialog" title="人工结案" width="min(540px, 94vw)" :close-on-click-modal="false">
            <el-alert title="结案后不再自动重试；不代表精斗云已经同步成功。" type="warning" :closable="false" show-icon />
            <el-form label-position="top" class="resolve-form" :disabled="acting"><el-form-item label="处理说明" required><el-input v-model="resolution.note" type="textarea" :rows="4" maxlength="1000" show-word-limit /></el-form-item><el-form-item label="精斗云单号（可选）"><el-input v-model.trim="resolution.external_no" maxlength="100" /></el-form-item></el-form>
            <template #footer><el-button :disabled="acting" @click="resolveDialog = false">取消</el-button><el-button type="primary" :loading="acting" :disabled="resolution.note.trim().length < 5" @click="submitResolve">确认结案</el-button></template>
        </el-dialog>
    </main>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Bell, Check, Connection, Key, OfficeBuilding, Plus, Refresh, Search, Setting } from '@element-plus/icons-vue'
import * as api from '../../api'

const route = useRoute()
const tab = ref('tasks'), loading = ref(false), saving = ref(false), probing = ref(false), acting = ref(false), error = ref('')
const busy = computed(() => saving.value || probing.value || acting.value)
const statuses: Record<string, string> = { pending: '等待预检', processing: '预检中', retry: '等待重试', blocked: '待处理', prepared: '已预检，未写单', manual_closed: '人工结案' }
const notices: Record<string, string> = { pending: '待提醒', retry: '提醒待重试', queued: '消息已入队', existing: '已有提醒记录', unavailable: '提醒未入队' }
const kinds: Record<string, string> = { asset: '设备流水', quantity: '标品流水', account: '往来账', money: '资金流水' }
const mappingKinds: Record<string, string> = { supplier: '供应商', customer: '客户', asset_material: '设备商品', product: '标品商品', warehouse: '仓库' }
const tasks = ref<any[]>([]), counts = ref<any[]>([]), total = ref(0)
const filter = reactive({ page: 1, limit: 20, status: '', keyword: '' })
const mappings = ref<any[]>([]), mappingTotal = ref(0), mappingPage = ref(1), mappingKind = ref('')
const logs = ref<any[]>([]), logPage = ref(1), logTotal = ref(0), staff = ref<any[]>([])
const config = reactive<Record<string, any>>({ client_id: '', client_secret: '', app_key: '', app_secret: '', account_id: '', domain: '', mode: 'disabled', assignee_uid: 0, revision: 0 })
const configLoaded = ref(false), savedStamp = ref(''), probeMessage = ref('')
const dirty = computed(() => configLoaded.value && JSON.stringify(config) !== savedStamp.value)
const verified = computed(() => !dirty.value && config.probe_at > 0 && config.probe_revision === config.revision)
const authComplete = computed(() => config.client_id && config.app_key && config.account_id && config.domain && config.client_secret_configured && config.app_secret_configured)
watch(() => JSON.stringify(config), () => { probeMessage.value = '' })
const drawer = ref(false), detail = ref<any>({}), detailLoading = ref(false), detailError = ref('')
const canAct = computed(() => detail.value.id && !detailLoading.value && !detailError.value && ['blocked', 'prepared', 'retry'].includes(detail.value.status))
const canClose = computed(() => canAct.value && detail.value.status !== 'retry')
const resolveDialog = ref(false), resolution = reactive({ note: '', external_no: '' })
const mappingDialog = ref(false), mappingEditing = ref(false)
const mapping = reactive({ kind: 'supplier', local_id: 1, external_number: '', unit_id: '', remark: '' })
const isMaterial = computed(() => ['asset_material', 'product'].includes(mapping.kind))
const count = (status: string) => Number(counts.value.find(row => row.status === status)?.total || 0)
const time = (value: number) => value ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }) : '-'
let loadId = 0, detailId = 0

async function load(run: () => Promise<void>) {
    loading.value = true; error.value = ''
    try { await run() } catch { error.value = '加载未完成，请检查插件安装、访问权限后刷新。' } finally { loading.value = false }
}
async function loadTasks() {
    const id = ++loadId
    await load(async () => { const result = await api.getTasks({ ...filter }); if (id !== loadId) return; tasks.value = result.data.data; total.value = result.data.total; counts.value = result.data.counts })
}
async function loadMappings() {
    const id = ++loadId
    await load(async () => { const result = await api.getMappings({ page: mappingPage.value, limit: 20, kind: mappingKind.value }); if (id !== loadId) return; mappings.value = result.data.data; mappingTotal.value = result.data.total })
}
async function loadLogs() {
    const id = ++loadId
    await load(async () => { const result = await api.getLogs({ page: logPage.value, limit: 20 }); if (id !== loadId) return; logs.value = result.data.data; logTotal.value = result.data.total })
}
function assignConfig(data: any) { Object.assign(config, data, { client_secret: '', app_secret: '' }); savedStamp.value = JSON.stringify(config); configLoaded.value = true }
async function loadConfig() {
    const id = ++loadId
    configLoaded.value = false
    await load(async () => { const result = await api.getConfig(); if (id !== loadId) return; assignConfig(result.data); try { const people = await api.getStaff(); if (id === loadId) staff.value = people.data } catch { error.value = '员工列表加载失败，请检查查看员工权限。' } })
}
const search = () => { filter.page = 1; loadTasks() }
async function changeTab() {
    if (tab.value === 'config' && dirty.value) return
    await ({ tasks: loadTasks, mappings: loadMappings, config: loadConfig, logs: loadLogs }[tab.value] || loadTasks)()
}
async function refresh() {
    if (tab.value === 'config' && dirty.value) {
        try { await ElMessageBox.confirm('刷新会丢弃未保存的授权修改，继续吗？', '刷新配置', { type: 'warning' }) } catch { return }
    }
    await ({ tasks: loadTasks, mappings: loadMappings, config: loadConfig, logs: loadLogs }[tab.value] || loadTasks)()
}
async function submitConfig() {
    saving.value = true
    try { const result = await api.saveConfig({ ...config }); assignConfig(result.data); ElMessage.success('配置已保存') } catch { /* Request layer displays the server validation message. */ } finally { saving.value = false }
}
async function probe() {
    if (dirty.value || !authComplete.value) return
    try { await ElMessageBox.confirm('使用已保存授权向精斗云查询一条购货单，仅核对访问权限，不导入库存、不写单。', '只读授权检查', { type: 'info' }) } catch { return }
    probing.value = true
    try { const result = await api.probe(config.revision); await loadConfig(); probeMessage.value = result.data.message } catch { /* Request layer displays the error. */ } finally { probing.value = false }
}
async function openTask(row: any) {
    const id = ++detailId
    drawer.value = true; detailLoading.value = true; detailError.value = ''; detail.value = {}
    try { const result = await api.getTask(Number(row.id)); if (id === detailId) detail.value = result.data } catch { if (id === detailId) detailError.value = '任务加载失败或已无访问权限。' } finally { if (id === detailId) detailLoading.value = false }
}
watch(drawer, value => { if (!value) { detailId++; detailLoading.value = false; resolveDialog.value = false } })
async function act(action: 'notice' | 'retry') {
    acting.value = true
    const id = detail.value.id
    try { await api[action](id); await openTask({ id }); await loadTasks(); ElMessage.success(action === 'retry' ? '已重新预检，未写单' : '已请求提醒，请查看投递状态') } catch { /* Keep the task open on failure. */ } finally { acting.value = false }
}
function openResolve() { Object.assign(resolution, { note: '', external_no: '' }); resolveDialog.value = true }
async function submitResolve() {
    acting.value = true
    const id = detail.value.id
    try { await api.resolve(id, { ...resolution }); resolveDialog.value = false; await openTask({ id }); await loadTasks(); ElMessage.success('已记录人工结案') } catch { /* Preserve entered resolution on failure. */ } finally { acting.value = false }
}
function editMapping(row?: any) { mappingEditing.value = !!row; Object.assign(mapping, { kind: 'supplier', local_id: 1, external_number: '', unit_id: '', remark: '' }, row || {}); mappingDialog.value = true }
async function submitMapping() {
    saving.value = true
    try { await api.saveMapping({ ...mapping }); mappingDialog.value = false; await loadMappings(); ElMessage.success('映射已保存，请重新预检相关任务') } catch { /* Preserve mapping on validation failure. */ } finally { saving.value = false }
}
onMounted(async () => { await loadTasks(); if (/^\d+$/.test(String(route.query.task_id || ''))) await openTask({ id: Number(route.query.task_id) }) })
</script>

<style scoped lang="scss">
.jdy-workbench { padding: 24px; background: #fff; min-height: 660px; color: var(--el-text-color-primary); letter-spacing: 0; }
.page-heading, .title, .toolbar, .detail-heading, .drawer-actions, .secret-field { display: flex; align-items: center; gap: 12px; }
.page-heading { justify-content: space-between; margin-bottom: 20px; }
.title { flex-wrap: wrap; } h1 { font-size: 22px; margin: 0; } h2 { font-size: 16px; margin: 0 0 20px; display: flex; align-items: center; gap: 8px; } h3 { font-size: 15px; margin: 0 0 16px; }
.el-tabs { margin-top: 18px; } .error-banner { margin-top: 12px; }
.metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 24px; padding: 12px 0 24px; border-bottom: 1px solid var(--el-border-color-lighter); }
.metrics div { display: flex; flex-direction: column; gap: 10px; } .metrics span { font-size: 13px; color: var(--el-text-color-secondary); } .metrics strong { font-size: 26px; font-weight: 600; }
.toolbar { flex-wrap: wrap; padding: 20px 0; } .toolbar .el-select { width: 185px; } .search { width: 260px; }
.el-pagination { justify-content: flex-end; margin-top: 20px; max-width: 100%; overflow-x: auto; }
.config-form { max-width: 780px; padding-top: 8px; } .config-form section { padding: 20px 0 10px; border-bottom: 1px solid var(--el-border-color-lighter); margin-bottom: 22px; }
.secret-field { width: 100%; } .secret-field .el-input { flex: 1; } .secret-field .el-tag { flex-shrink: 0; }
.timestamp { font-size: 12px; margin-left: 12px; color: var(--el-text-color-secondary); }
.task-detail { min-height: 120px; } .task-detail section { margin-top: 28px; } .detail-heading { justify-content: space-between; margin-bottom: 20px; } .detail-heading h2 { margin: 0; }
ul { padding-left: 20px; margin: 0; } li { line-height: 1.8; margin-bottom: 8px; } p { overflow-wrap: anywhere; line-height: 1.7; }
.drawer-actions { justify-content: flex-end; flex-wrap: wrap; } .resolve-form { margin-top: 20px; }
@media (max-width: 720px) { .jdy-workbench { padding: 16px; } .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; } .secret-field { flex-wrap: wrap; } .secret-field .el-input { flex-basis: 100%; } .search { flex: 1; min-width: 160px; } .toolbar .el-select { width: 100%; } }
</style>
