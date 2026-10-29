<template>
    <view class="bridge">
        <view class="toolbar">
            <view class="heading"><text class="title">博远对接</text><text class="mode" :class="config.mode">{{ modeName(config.mode) }}</text></view>
            <view class="tools"><button class="icon-button" aria-label="刷新" :disabled="loading" @click="refresh"><u-icon name="reload" size="21" /></button><button class="icon-button" aria-label="连接设置" @click="openConfig"><u-icon name="setting" size="21" /></button></view>
        </view>
        <view class="connection" :class="{ paused: !config.enabled || config.paused || workerStale }">
            <u-icon :name="workerStale && !config.paused ? 'error-circle' : config.enabled && !config.paused ? 'checkmark-circle' : 'pause-circle'" size="17" />
            <text>{{ !config.enabled ? '尚未启用' : config.paused ? '投递已暂停，待处理记录保留' : workerStale ? '后台处理服务长时间未运行，请管理员检查' : config.mode === 'mock' ? '模拟运行，不向博远写入数据' : '自动同步已开启' }}</text>
        </view>
        <view class="summary"><view><text class="metric">{{ pendingCount }}</text><text class="muted">处理中</text></view><view><text class="metric warning">{{ attentionCount }}</text><text class="muted">需要处理</text></view><view><text class="metric">{{ completedCount }}</text><text class="muted">{{ config.mode === 'mock' ? '模拟完成' : '已同步' }}</text></view></view>
        <view class="nav"><button v-for="item in sections" :key="item.key" :class="{ selected: section === item.key }" @click="changeSection(item.key)">{{ item.name }}</button></view>

        <view v-if="error" class="error-state"><u-icon name="error-circle" size="24" color="#c2410c" /><text>{{ error }}</text><u-button size="small" @click="refresh">重新加载</u-button></view>
        <template v-else-if="section === 'tasks'">
            <view class="search"><u-search v-model="keyword" placeholder="搜索业务单号" :show-action="false" @search="loadTasks(true)" @clear="loadTasks(true)" /></view>
            <scroll-view scroll-x class="filter-scroll"><view class="filters"><button v-for="filter in filters" :key="filter.value" :class="{ active: status === filter.value }" @click="selectStatus(filter.value)">{{ filter.label }}</button></view></scroll-view>
            <view v-if="loading && !tasks.length" class="loading"><u-loading-icon text="加载中" /></view>
            <view v-else-if="!tasks.length" class="empty"><u-empty mode="list" text="暂无对接任务" /><text class="muted">启用后，新业务将自动进入这里</text></view>
            <view class="task-list"><view v-for="task in tasks" :key="task.id" class="task" @click="openTask(task.id)">
                <view class="row"><text class="task-title">{{ kindName(task.kind) }}</text><text class="state" :class="task.status">{{ stateName(task.status) }}</text></view>
                <text class="number">{{ task.business_no }}</text>
                <text v-if="task.reason" class="reason">{{ task.reason }}</text>
                <view class="row foot"><text>{{ modeName(task.mode) }} · {{ time(task.update_at) }}</text><u-icon name="arrow-right" size="13" /></view>
            </view></view>
            <view class="load-more"><u-button v-if="tasks.length < total" size="small" :loading="loading" @click="loadTasks(false)">加载更多</u-button><text v-else-if="tasks.length" class="muted">共 {{ total }} 条记录</text></view>
        </template>
        <template v-else-if="section === 'mappings'">
            <view class="section-head"><text>当前{{ modeName(config.mode) }}资料对应</text><u-button size="small" type="primary" @click="editMapping()">新增对应</u-button></view>
            <view class="search"><u-search v-model="mappingKeyword" placeholder="搜索名称 / 博远编码" :show-action="false" @search="loadMappings(true)" @clear="loadMappings(true)" /></view>
            <view v-if="!mappings.length" class="empty"><u-empty mode="list" text="暂无资料对应" /></view>
            <view v-for="map in mappings" :key="map.id" class="mapping-row" @click="editMapping(map)"><view><text class="task-title">{{ map.local_name || map.local_ref }}</text><text class="muted break">{{ mappingNames[map.kind] }} · {{ map.external_name || map.external_code }}</text></view><u-icon name="edit-pen" size="19" /></view>
            <view class="load-more"><u-button v-if="mappings.length < mappingTotal" size="small" @click="loadMappings(false)">加载更多</u-button><text v-else class="muted">共 {{ mappingTotal }} 项对应</text></view>
        </template>
        <template v-else>
            <view v-if="!logs.length" class="empty"><u-empty mode="list" text="暂无操作记录" /></view>
            <view v-for="log in logs" :key="log.id" class="log-row"><text class="log-dot" /><view><text>{{ log.message }}</text><text class="muted">{{ time(log.create_at) }} · {{ log.outcome }}</text></view></view>
        </template>

        <u-popup :show="!!detail || detailLoading" mode="bottom" :round="8" closeable :close-on-click-overlay="!busy" @close="closeDetail">
            <view class="sheet">
                <view class="sheet-header"><text class="sheet-title">任务详情</text><text v-if="detail" class="muted break">{{ detail.business_no }}</text></view>
                <scroll-view scroll-y class="sheet-body">
                    <view v-if="detailLoading" class="loading"><u-loading-icon /></view>
                    <template v-else-if="detail">
                        <view class="detail-state"><text class="state" :class="detail.status">{{ stateName(detail.status) }}</text><text>{{ detail.reason || (detail.mode === 'mock' ? '模拟回执已核验，未访问博远' : '查看下方处理记录') }}</text></view>
                        <text v-if="!detail.active_connection" class="notice">这是其他环境或旧连接的任务，不会发送到当前账套。</text>
                        <view class="detail-meta"><view class="row"><text>操作编号</text><text class="break">{{ detail.operation_id }}</text></view><view class="row"><text>博远单号</text><text class="break">{{ detail.external_no || '尚未取得' }}</text></view></view>
                        <view v-for="line in detail.snapshot?.lines || []" :key="line.source_line_id" class="device"><text class="task-title">{{ line.product_name }}</text><view class="row"><text class="muted break">{{ line.imei || line.sn || '缺少串号' }}</text><text class="amount">¥{{ line.amount }}</text></view></view>
                        <view v-if="detail.status === 'blocked'" class="quick-maps"><text class="subheading">资料对应</text><button v-for="item in requiredMaps" :key="item.kind + item.ref" class="mapping-choice" @click="mapFromTask(item)"><text>{{ mappingNames[item.kind] }} · {{ item.name }}</text><u-icon name="arrow-right" size="13" /></button></view>
                        <view class="timeline"><text class="subheading">处理记录</text><view v-for="log in detail.logs" :key="log.id" class="log-row"><text class="log-dot" /><view><text>{{ log.message }}</text><text class="muted">{{ time(log.create_at) }}</text></view></view></view>
                        <button class="text-button" @click="showPayload = !showPayload">{{ showPayload ? '收起技术回执' : '查看技术回执' }}</button><text v-if="showPayload" class="payload">{{ JSON.stringify(detail.result, null, 2) }}</text>
                        <template v-if="manualForm"><view class="form-field"><text>博远人工单号</text><u-input v-model="manualNo" placeholder="填写实际单号" /></view><view class="form-field"><text>处理说明</text><u-textarea v-model="manualNote" placeholder="填写已处理内容和核对依据，至少5字" :maxlength="1000" /></view><text class="notice">仅记录人工处理，不会标记为同步成功。</text></template>
                    </template>
                </scroll-view>
                <view v-if="detail" class="sheet-footer">
                    <u-button v-if="manualForm" :loading="busy" type="primary" @click="act('record_manual')">保存人工记录</u-button>
                    <template v-else-if="!['simulated', 'succeeded', 'processing'].includes(detail.status)">
                        <u-button v-if="detail.side_effect === 'none' && detail.active_connection && detail.kind !== 'manual_review'" type="primary" :loading="busy" @click="act('retry')">补齐后重试</u-button>
                        <u-button v-else-if="detail.request?.operation_id && detail.active_connection" type="primary" :loading="busy" @click="act('query')">查询并核验结果</u-button>
                        <u-button :disabled="busy" @click="manualForm = true">记录人工处理</u-button>
                    </template>
                    <template v-else><u-button v-if="['simulated', 'succeeded'].includes(detail.status) && detail.active_connection" :loading="busy" @click="act('query')">核对博远状态</u-button><u-button @click="closeDetail">关闭</u-button></template>
                </view>
            </view>
        </u-popup>

        <u-popup :show="configVisible" mode="bottom" :round="8" closeable :close-on-click-overlay="false" @close="closeConfig">
            <view class="sheet"><view class="sheet-header"><text class="sheet-title">连接设置</text></view><scroll-view scroll-y class="sheet-body">
                <view class="mode-tabs"><button v-for="mode in modes" :key="mode" :class="{ selected: draft.mode === mode }" @click="changeDraftMode(mode)">{{ modeName(mode) }}</button></view>
                <view class="form-field inline"><text>接收新业务</text><u-switch v-model="draft.enabled" /></view><view class="form-field inline"><text>暂停外部投递</text><u-switch v-model="draft.paused" /></view>
                <view v-if="draft.mode === 'mock'" class="form-field"><text>模拟场景</text><picker :range="scenarios" range-key="label" @change="profile.scenario = scenarios[Number($event.detail.value)].value"><view class="select-input">{{ scenarios.find(s => s.value === profile.scenario)?.label }}</view></picker></view>
                <template v-else><view class="form-field"><text>博远服务地址</text><u-input v-model="profile.endpoint" placeholder="https://博远提供的域名/应用路径" /></view><view class="form-field"><text>目标账套编号</text><u-input v-model="profile.account_id" placeholder="博远授权对应的账套" /></view><view class="form-field"><text>接口授权</text><u-input v-model="profile.token" type="password" :placeholder="profile.token_configured ? '已加密保存，留空保留原授权' : '填写博远提供的接口Token'" /></view><view class="form-field inline"><text>接口契约已双方确认</text><u-switch v-model="profile.contract_confirmed" /></view><view v-if="draft.mode === 'production'" class="form-field"><text>真实测试验收单号</text><u-input v-model="profile.acceptance_ref" placeholder="填双方验收记录编号" /></view><view class="form-field"><text>启用写入确认</text><u-input v-model="confirmAccount" placeholder="启用时重新输入目标账套编号" /></view><u-button :loading="busy" @click="checkConnection">核验已保存账套</u-button></template>
                <view class="form-field inline"><text>第二期财务同步</text><u-switch v-model="draft.finance_enabled" /></view>
                <view class="form-field"><text>异常责任人</text><picker :range="staffOptions" range-key="label" @change="draft.assignee_uid = staffOptions[Number($event.detail.value)].uid"><view class="select-input">{{ staffOptions.find(s => s.uid === draft.assignee_uid)?.label || '未设置' }}</view></picker></view>
                <text class="notice">切换模式不会迁移已有任务。暂停不能撤回已发出的请求。</text>
            </scroll-view><view class="sheet-footer"><u-button :loading="busy" type="primary" @click="persistConfig">保存设置</u-button></view></view>
        </u-popup>

        <u-popup :show="mappingVisible" mode="bottom" :round="8" closeable :close-on-click-overlay="false" @close="closeMapping">
            <view class="sheet mapping-sheet"><view class="sheet-header"><text class="sheet-title">资料对应</text></view><scroll-view scroll-y class="sheet-body">
                <view class="form-field"><text>资料类型</text><picker :range="mapKinds" range-key="label" @change="mappingDraft.kind = mapKinds[Number($event.detail.value)].value"><view class="select-input">{{ mappingNames[mappingDraft.kind] }}</view></picker></view>
                <view class="form-field"><text>来源名称</text><u-input v-model="mappingDraft.local_name" /></view><button class="text-button" @click="showSourceRef = !showSourceRef">{{ showSourceRef ? '收起来源编号' : '查看来源编号' }}</button><view v-if="showSourceRef || !mappingDraft.local_ref" class="form-field"><text>来源编号</text><u-input v-model="mappingDraft.local_ref" placeholder="可从任务详情选择，避免手输" /></view>
                <view v-if="referenceResource" class="reference-lookup"><u-search v-model="referenceKeyword" placeholder="搜索博远档案" :show-action="false" @search="loadReferences" /><u-button size="small" :loading="referenceLoading" @click="loadReferences">选择博远档案</u-button><button v-for="item in referenceRows" :key="item.id" class="mapping-choice" @click="selectReference(item)"><text>{{ item.name }} · {{ item.code }}</text><u-icon name="arrow-right" size="13" /></button></view>
                <view class="form-field"><text>博远内部ID</text><u-input v-model="mappingDraft.external_id" /></view><view class="form-field"><text>博远档案编码</text><u-input v-model="mappingDraft.external_code" /></view><view class="form-field"><text>博远名称</text><u-input v-model="mappingDraft.external_name" /></view><view v-if="mappingDraft.kind === 'product'" class="form-field"><text>博远计量单位编码</text><u-input v-model="mappingDraft.extra.unit_code" placeholder="如博远实际单位编码" /></view>
                <text class="notice">对应关系仅属于当前环境。修改不会覆盖已发送的原始请求。</text>
            </scroll-view><view class="sheet-footer"><u-button :loading="busy" type="primary" @click="persistMapping">保存对应</u-button></view></view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, onUnmounted } from 'vue'
import { onShow, onHide } from '@dcloudio/uni-app'
import * as api from '../../api/index'
const modes = ['mock', 'test', 'production']
const modeName = (mode: string) => ({ mock: '模拟模式', test: '测试模式', production: '正式模式' }[mode] || mode)
const states: Record<string, string> = { pending: '待同步', processing: '处理中', waiting: '等待原单', confirming: '结果待确认', blocked: '资料待补充', rejected: '博远已拒绝', partial: '部分成功', attention: '需人工核验', manual_pending: '人工处理待核验', simulated: '模拟完成', succeeded: '已同步', retry: '等待重试' }
const stateName = (state: string) => states[state] || state
const kindName = (kind: string) => ({ purchase: '采购入库', sale: '销售出库', purchase_return: '采购退货', sale_return: '销售退货', party_resolve: '往来单位对应', financial_item_ensure: '应收应付关联', settlement_register: '收付款登记', manual_review: '原单变更待核对' }[kind] || kind)
const mappingNames: Record<string, string> = { product: '商品', warehouse: '仓库', location: '库位', supplier: '供应商', customer: '客户', staff: '业务员', method: '收付方式', account: '资金账户' }
const mapKinds = Object.entries(mappingNames).map(([value, label]) => ({ value, label }))
const scenarios = [{ value: 'success', label: '正常成功' }, { value: 'rejected', label: '明确拒绝' }, { value: 'timeout_after_commit', label: '处理成功但响应丢失' }, { value: 'partial', label: '部分成功' }, { value: 'malformed', label: '回执不完整' }]
const sections = [{ key: 'tasks', name: '同步任务' }, { key: 'mappings', name: '资料对应' }, { key: 'logs', name: '操作记录' }]
const filters = [{ value: '', label: '全部' }, { value: 'blocked', label: '资料待补充' }, { value: 'confirming', label: '结果待确认' }, { value: 'partial', label: '部分成功' }, { value: 'rejected', label: '已拒绝' }, { value: 'attention', label: '需人工核验' }, { value: 'manual_pending', label: '人工待核验' }, { value: 'simulated', label: '模拟完成' }, { value: 'succeeded', label: '已同步' }]
const config = ref<any>({ mode: 'mock', enabled: false, paused: true, profiles: {} })
const tasks = ref<any[]>([]), mappings = ref<any[]>([]), logs = ref<any[]>([]), counts = ref<Record<string, number>>({})
const loading = ref(false), error = ref(''), busy = ref(false), detailLoading = ref(false), detail = ref<any>(null)
const keyword = ref(''), status = ref(''), section = ref('tasks'), page = ref(1), total = ref(0)
const configVisible = ref(false), draft = ref<any>({ profiles: {} }), confirmAccount = ref(''), savedDraft = ref('')
const profile = computed<any>(() => draft.value.profiles[draft.value.mode] || {})
const staffOptions = ref<any[]>([{ uid: 0, label: '不发送员工提醒' }])
const mappingVisible = ref(false), mappingDraft = ref<any>({ kind: 'product', extra: {} })
const mappingPage = ref(1), mappingTotal = ref(0), mappingKeyword = ref('')
const showSourceRef = ref(false), referenceKeyword = ref(''), referenceLoading = ref(false), referenceRows = ref<any[]>([])
const referenceResource = computed(() => ({ product: 'products', warehouse: 'warehouses', staff: 'staff', method: 'payment_methods', account: 'capital_accounts' }[mappingDraft.value.kind] || ''))
const manualForm = ref(false), manualNo = ref(''), manualNote = ref(''), showPayload = ref(false)
const pendingCount = computed(() => ['pending', 'processing', 'waiting', 'confirming', 'retry'].reduce((sum, key) => sum + (counts.value[key] || 0), 0))
const attentionCount = computed(() => ['blocked', 'rejected', 'partial', 'attention', 'manual_pending'].reduce((sum, key) => sum + (counts.value[key] || 0), 0))
const completedCount = computed(() => (counts.value.simulated || 0) + (counts.value.succeeded || 0))
const workerStale = computed(() => config.value.enabled && Date.now() / 1000 - Number(config.value.worker_last_at || config.value.started_at) > 180)
const time = (timestamp: number) => timestamp ? new Date(timestamp * 1000).toLocaleString('zh-CN', { hour12: false }) : '-'
const message = (err: any) => String(err?.msg || err?.message || '请求未完成，请重试').slice(0, 240)
const toast = (title: string) => uni.showToast({ title, icon: 'none', duration: 2400 })
let timer: ReturnType<typeof setInterval> | undefined
let active = false
let pendingReload = false
let returnTaskId = 0
async function loadTasks(reset = true) {
    if (loading.value) { if (reset) pendingReload = true; return }
    loading.value = true
    try {
        const nextPage = reset ? 1 : page.value + 1
        const res = await api.getTasks({ page: nextPage, limit: 15, keyword: keyword.value, status: status.value, mode: config.value.mode })
        tasks.value = reset ? res.data.data : [...tasks.value, ...res.data.data]
        total.value = res.data.total; page.value = nextPage
        counts.value = Object.fromEntries(res.data.counts.map((item: any) => [item.status, Number(item.total)])); error.value = ''
    } catch (err) { error.value = message(err) } finally { loading.value = false; if (pendingReload) { pendingReload = false; loadTasks(true) } }
}
async function refresh() {
    try {
        config.value = (await api.getConfig()).data
        if (section.value === 'tasks') await loadTasks(true)
        else if (section.value === 'mappings') { await loadMappings(true); error.value = '' }
        else { logs.value = (await api.getLogs()).data; error.value = '' }
    } catch (err) { error.value = message(err) }
}
function selectStatus(value: string) { status.value = value; loadTasks(true) }
function changeSection(value: string) { section.value = value; refresh() }
async function openTask(id: number) {
    detailLoading.value = true; manualForm.value = false; showPayload.value = false; manualNote.value = ''; manualNo.value = ''
    try { detail.value = (await api.getTask(id)).data } catch (err) { toast(message(err)) } finally { detailLoading.value = false }
}
function closeDetail() {
    if (busy.value) return
    if (manualForm.value && (manualNote.value.trim() || manualNo.value.trim())) uni.showModal({ title: '放弃未保存的人工记录？', success: result => { if (result.confirm) detail.value = null } })
    else detail.value = null
}
async function act(action: string) {
    if (busy.value || !detail.value) return
    if (action === 'record_manual' && manualNote.value.trim().length < 5) return toast('请填写至少5字处理说明')
    busy.value = true
    try {
        const id = detail.value.id
        await api.taskAction(id, { action, note: manualNote.value.trim(), external_no: manualNo.value.trim() })
        manualForm.value = false; detail.value = (await api.getTask(id)).data; await loadTasks(true); toast('处理请求已记录')
    } catch (err) { toast(message(err)) } finally { busy.value = false }
}
async function openConfig() {
    draft.value = JSON.parse(JSON.stringify(config.value)); confirmAccount.value = ''; savedDraft.value = JSON.stringify(draft.value); configVisible.value = true
    try { staffOptions.value = [{ uid: 0, label: '不发送员工提醒' }, ...(await api.getStaff()).data.map((row: any) => ({ uid: row.uid, label: row.real_name || row.username }))] } catch (err) { toast(message(err)) }
}
function changeDraftMode(mode: string) { draft.value.mode = mode; draft.value.paused = true; confirmAccount.value = '' }
function closeConfig() {
    if (busy.value) return
    if (JSON.stringify(draft.value) !== savedDraft.value) uni.showModal({ title: '放弃未保存的修改？', success: result => { if (result.confirm) configVisible.value = false } })
    else configVisible.value = false
}
async function persistConfig() {
    if (busy.value) return
    busy.value = true
    try {
        config.value = (await api.saveConfig({ revision: config.value.revision, mode: draft.value.mode, enabled: draft.value.enabled, paused: draft.value.paused, finance_enabled: draft.value.finance_enabled, assignee_uid: draft.value.assignee_uid, profile: profile.value, confirm_account: confirmAccount.value })).data
        configVisible.value = false; await refresh(); toast('连接设置已保存')
    } catch (err) { toast(message(err)) } finally { busy.value = false }
}
async function checkConnection() {
    if (JSON.stringify(draft.value) !== savedDraft.value) return toast('请先保存配置，再核验已保存的账套')
    busy.value = true
    try { await api.probe(); config.value = (await api.getConfig()).data; draft.value = JSON.parse(JSON.stringify(config.value)); savedDraft.value = JSON.stringify(draft.value); toast('账套身份核验通过，未写单') } catch (err) { toast(message(err)) } finally { busy.value = false }
}
const requiredMaps = computed(() => {
    const snapshot = detail.value?.snapshot || {}, result: any[] = []
    const push = (kind: string, ref: string, name: string) => { if (ref && !result.some(item => item.kind === kind && item.ref === ref)) result.push({ kind, ref, name }) }
    for (const line of snapshot.lines || []) { push('product', line.product_ref, line.product_name); push('warehouse', line.warehouse_ref, line.warehouse_ref) }
    if (snapshot.party) push(['purchase', 'purchase_return'].includes(snapshot.kind) ? 'supplier' : 'customer', snapshot.party.ref, snapshot.party.name)
    if (snapshot.party_ref) push(snapshot.party_role, snapshot.party_ref, snapshot.party_ref)
    for (const item of snapshot.items || []) push(item.party_role, item.party_ref, item.party_ref)
    push('staff', snapshot.salesperson_ref, snapshot.salesperson_name); push('method', snapshot.payment_method_ref, snapshot.payment_method_ref)
    push('account', snapshot.account_ref, snapshot.account_ref); push('method', snapshot.method_ref, snapshot.method_ref)
    return result
})
async function mapFromTask(item: any) {
    try { const res = await api.getMappings({ kind: item.kind, local_ref: item.ref, limit: 1 }); returnTaskId = detail.value.id; detail.value = null; editMapping(res.data.data[0] || { kind: item.kind, local_ref: item.ref, local_name: item.name }) } catch (err) { toast(message(err)) }
}
async function loadMappings(reset = true) { const next = reset ? 1 : mappingPage.value + 1; const res = await api.getMappings({ page: next, limit: 50, keyword: mappingKeyword.value }); mappings.value = reset ? res.data.data : [...mappings.value, ...res.data.data]; mappingPage.value = next; mappingTotal.value = res.data.total }
function closeMapping() { if (busy.value) return; mappingVisible.value = false; if (returnTaskId) { const id = returnTaskId; returnTaskId = 0; openTask(id) } }
function editMapping(map?: any) { mappingDraft.value = { kind: 'product', local_ref: '', local_name: '', external_id: '', external_code: '', external_name: '', revision: 0, ...map, extra: map?.extra_json ? JSON.parse(map.extra_json) : { unit_code: '' } }; showSourceRef.value = false; referenceRows.value = []; referenceKeyword.value = ''; mappingVisible.value = true }
async function loadReferences() {
    referenceLoading.value = true
    try { const res = await api.getReferences({ resource: referenceResource.value, keyword: referenceKeyword.value, limit: 25 }); referenceRows.value = (res.data.data?.items || []).filter((item: any) => item.enabled); if (!referenceRows.value.length) toast('没有找到可用档案') } catch (err) { toast(message(err)) } finally { referenceLoading.value = false }
}
function selectReference(item: any) { mappingDraft.value.external_id = item.id; mappingDraft.value.external_code = item.code; mappingDraft.value.external_name = item.name; if (item.unit_code) mappingDraft.value.extra.unit_code = item.unit_code; referenceRows.value = [] }
async function persistMapping() {
    if (!mappingDraft.value.external_id || !mappingDraft.value.external_code || !mappingDraft.value.local_ref) return toast('请填写来源编号和博远ID、编码')
    busy.value = true
    try { await api.saveMapping(mappingDraft.value); await loadMappings(true); busy.value = false; closeMapping(); toast('对应已保存，可返回原任务重试') } catch (err) { toast(message(err)) } finally { busy.value = false }
}
onShow(() => { active = true; refresh(); if (!timer) timer = setInterval(async () => {
    if (!active || configVisible.value || mappingVisible.value || busy.value) return
    try {
        config.value = (await api.getConfig()).data
        if (section.value === 'tasks' && page.value === 1) await loadTasks(true)
        if (detail.value) { const id = detail.value.id; const res = await api.getTask(id); if (detail.value?.id === id) detail.value = res.data }
    } catch (err) { error.value = message(err) }
}, 10000) })
onHide(() => { active = false })
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<style scoped lang="scss">
.bridge { --brand: #2563eb; color: #20252d; background: #f5f6f8; min-height: 100vh; padding-bottom: calc(24px + env(safe-area-inset-bottom)); font-size: 14px; letter-spacing: 0; }
button { margin: 0; padding: 0; font-size: inherit; line-height: 1.4; border: 0; background: transparent; color: inherit; &::after { border: 0; } }
.toolbar { display: flex; align-items: center; justify-content: space-between; padding: 16px; background: #fff; }
.heading, .tools, .row, .inline, .section-head { display: flex; align-items: center; gap: 8px; }.row { justify-content: space-between; }.heading { min-width: 0; }.title { font-size: 20px; font-weight: 700; }.mode { font-size: 11px; padding: 3px 7px; background: #eef2ff; color: #4558a2; border-radius: 4px; white-space: nowrap; }.mode.production { color: #b45309; background: #fff5df; }.mode.test { color: #0f766e; background: #e9f6f1; }
.icon-button { width: 36px; height: 36px; display: flex; justify-content: center; align-items: center; }.connection { display: flex; gap: 7px; align-items: center; padding: 10px 16px; background: #eef6f3; color: #246651; font-size: 12px; }.connection.paused { background: #fff5e9; color: #a36318; }
.summary { display: grid; grid-template-columns: repeat(3, 1fr); padding: 20px 12px; background: #fff; }.summary > view { display: flex; flex-direction: column; align-items: center; gap: 5px; }.metric { font-size: 27px; font-weight: 650; }.metric.warning { color: #c26a18; }.muted { display: block; font-size: 12px; color: #7a828e; line-height: 1.6; }.nav { background: #fff; display: flex; border-bottom: 1px solid #e9ebef; padding: 0 16px; gap: 28px; }.nav button { padding: 13px 0; white-space: nowrap; position: relative; color: #7a828e; }.nav .selected { color: #20252d; font-weight: 650; &::before { content: ''; width: 24px; height: 3px; border-radius: 2px; background: var(--brand); position: absolute; bottom: 0; left: calc(50% - 12px); } }
.search { padding: 14px 16px 8px; background: #fff; }.filters { display: flex; gap: 8px; padding: 4px 16px 14px; width: max-content; }.filter-scroll { background: #fff; white-space: nowrap; }.filters button { padding: 6px 10px; font-size: 12px; color: #67717f; background: #f4f5f7; border-radius: 4px; white-space: nowrap; }.filters button.active { color: var(--brand); background: #edf3ff; font-weight: 600; }
.task-list { padding: 8px 12px 0; }.task { background: #fff; padding: 15px; margin-bottom: 8px; border-radius: 6px; }.task-title { display: block; font-weight: 600; font-size: 15px; overflow-wrap: anywhere; }.number { display: block; font-size: 13px; color: #67717f; margin-top: 7px; overflow-wrap: anywhere; }.state { font-size: 11px; color: #657183; white-space: nowrap; padding: 3px 6px; border-radius: 3px; background: #f1f3f6; }.state.succeeded { background: #eaf7f0; color: #218356; }.state.simulated { background: #edf2ff; color: #4b62a8; }.state.blocked, .state.rejected, .state.attention, .state.partial, .state.manual_pending { color: #b15a18; background: #fff3e5; }.reason { display: block; padding-top: 10px; color: #a35d26; font-size: 12px; line-height: 1.7; overflow-wrap: anywhere; }.foot { border-top: 1px solid #f0f1f4; margin-top: 12px; padding-top: 10px; font-size: 11px; color: #949ba6; }.empty, .loading { padding: 56px 16px; text-align: center; }.empty .muted { margin-top: 18px; }.error-state { padding: 35px 20px; display: flex; align-items: center; gap: 18px; flex-direction: column; }.load-more { padding: 16px; text-align: center; }
.section-head { padding: 16px; justify-content: space-between; font-size: 13px; }.section-head :deep(.u-button) { width: auto; margin: 0; }.mapping-row { padding: 16px; margin: 0 12px; background: #fff; border-bottom: 1px solid #eef0f3; display: flex; justify-content: space-between; gap: 12px; }.mapping-row > view { min-width: 0; }.break { overflow-wrap: anywhere; word-break: break-all; }.log-row { padding: 14px 16px; display: flex; align-items: flex-start; gap: 10px; line-height: 1.7; border-bottom: 1px solid #eef0f3; }.log-dot { display: block; width: 6px; height: 6px; flex: 0 0 6px; border-radius: 50%; background: #a3b4ce; margin-top: 8px; }.log-row > view { min-width: 0; }
.sheet { background: #fff; padding-top: 8px; max-height: 88vh; display: flex; flex-direction: column; border-radius: 8px 8px 0 0; }.sheet-header { padding: 14px 48px 14px 18px; border-bottom: 1px solid #eef0f3; flex-shrink: 0; }.sheet-title { display: block; font-size: 18px; font-weight: 650; }.sheet-body { height: 60vh; box-sizing: border-box; padding: 0 18px; }.sheet-footer { display: flex; flex-shrink: 0; gap: 10px; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); border-top: 1px solid #eef0f3; }.detail-state { padding: 18px 0; display: flex; align-items: flex-start; gap: 10px; line-height: 1.7; }.detail-state > text:last-child { min-width: 0; }.detail-meta { padding: 12px 0; border-block: 1px solid #eef0f3; font-size: 12px; color: #7a828e; }.detail-meta .row { align-items: flex-start; margin: 8px 0; }.detail-meta .row > text:first-child { flex-shrink: 0; }.detail-meta .row > text:last-child { text-align: right; max-width: 75%; }.device { padding: 16px 0; border-bottom: 1px solid #eef0f3; }.device .row { margin-top: 8px; }.amount { font-weight: 600; }.subheading { display: block; font-size: 14px; font-weight: 600; padding: 18px 0 3px; }.timeline .log-row { padding: 12px 0; }.notice { display: block; background: #f6f7f9; color: #7a828e; font-size: 12px; line-height: 1.7; padding: 10px 12px; margin: 12px 0; }.text-button { color: var(--brand); padding: 16px 0; text-align: left; font-size: 12px; }.payload { display: block; font-family: monospace; white-space: pre-wrap; font-size: 11px; overflow-wrap: anywhere; color: #67717f; padding-bottom: 16px; }.mapping-choice { display: flex; width: 100%; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #eef0f3; font-size: 13px; }.mapping-choice text { min-width: 0; text-align: left; overflow-wrap: anywhere; }
.mode-tabs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; padding: 16px 0; }.mode-tabs button { border: 1px solid #e5e8ee; border-radius: 4px; height: 38px; font-size: 13px; }.mode-tabs .selected { border-color: var(--brand); color: var(--brand); background: #f4f7ff; }.form-field { padding: 12px 0; border-bottom: 1px solid #f0f1f4; }.form-field > text { display: block; font-size: 13px; margin-bottom: 10px; }.form-field.inline { justify-content: space-between; min-height: 44px; }.form-field.inline > text { margin: 0; }.select-input { border: 1px solid #dadde3; padding: 10px 12px; border-radius: 4px; font-size: 14px; }
.reference-lookup { padding: 10px 0; }.reference-lookup :deep(.u-button) { margin-top: 10px; }
@media (max-width: 350px) { .toolbar { padding: 12px; }.title { font-size: 18px; }.nav { gap: 22px; }.task { padding: 12px; }.state { font-size: 10px; }.sheet-body { padding: 0 12px; }.sheet-footer { padding-left: 12px; padding-right: 12px; } }
</style>
