<template>
    <view class="bridge" :class="{ 'has-batch-bar': section === 'tasks' && selectionMode }">
        <view class="toolbar">
            <view class="heading"><view><text class="title">博远对接</text><text class="account-caption">{{ configLoaded ? '账套 ' + (config.profiles[config.mode]?.account_id || '本地模拟') : '正在读取连接' }}</text></view><text v-if="configLoaded" class="mode" :class="config.mode">{{ modeName(config.mode) }}</text></view>
            <view class="tools"><button class="icon-button" aria-label="刷新" :disabled="loading || !configLoaded" @click="refresh"><u-icon name="reload" size="21" /></button><button class="icon-button" aria-label="连接设置" :disabled="!configLoaded" @click="openConfig"><u-icon name="setting" size="21" /></button></view>
        </view>
        <view v-if="configLoaded" class="connection" :class="{ paused: !config.enabled || config.paused || workerStale }">
            <u-icon :name="workerStale && !config.paused ? 'error-circle' : config.enabled && !config.paused ? 'checkmark-circle' : 'pause-circle'" size="17" />
            <text>{{ isOpenApi ? '基础资料只读同步 · 单据投递未开启' : !config.enabled ? '尚未启用' : config.paused ? '投递已暂停，待处理记录保留' : workerStale ? '后台处理服务长时间未运行，请管理员检查' : config.mode === 'mock' ? '模拟运行，不向博远写入数据' : '自动同步已开启' }}</text>
        </view>
        <view v-if="configLoaded && (section === 'tasks' || section === 'archives')" class="summary"><button v-for="metric in summaryMetrics" :key="metric.key" :class="{ 'metric-alert': metric.alert }" @click="focusMetric(metric.key)"><view><text class="metric">{{ sectionLoaded ? metric.count : '—' }}</text><text class="metric-unit">{{ section === 'archives' ? '类' : '笔' }}</text></view><text class="muted">{{ metric.label }} <text aria-hidden="true">›</text></text></button></view>
        <view v-if="configLoaded" class="nav"><button v-for="item in sections" :key="item.key" :class="{ selected: section === item.key }" @click="changeSection(item.key)">{{ item.name }}</button></view>

        <view v-if="error" class="inline-alert"><u-icon name="error-circle" size="18" /><view><text>更新未完成，已保留当前内容</text><text class="muted">{{ error }}</text></view><button class="inline-action" @click="refresh">重试</button></view>
        <view v-if="!configLoaded && !error" class="loading"><u-loading-icon text="正在读取账套" /></view>
        <view v-if="configLoaded && section === 'tasks'" class="scope-notice" :class="{ 'notice-warning': isOpenApi || workerStale }"><u-icon name="error-circle" size="17" /><view><text>{{ isOpenApi ? '当前只开放基础资料读取' : '本站业务通过当前账套同步' }}</text><text class="muted">{{ isOpenApi ? '博远单据接口未开放，不能在此提交业务；未执行旧ERP数据停用。' : '提交不等于完成。结果未知时先核验，不重复建单。' }}</text></view></view>
        <template v-if="configLoaded && section === 'tasks'">
            <view class="search"><u-search v-model="keyword" placeholder="搜索业务单号" :show-action="false" @search="loadTasks(true)" @clear="loadTasks(true)" /></view>
            <scroll-view scroll-x class="filter-scroll"><view class="filters"><button v-for="filter in filters" :key="filter.value" :class="{ active: status === filter.value }" @click="selectStatus(filter.value)">{{ filter.label }}</button></view></scroll-view>
            <view class="list-toolbar"><text class="muted">{{ sectionLoaded ? total + ' 笔业务' : '读取业务中' }}</text><view class="toolbar-actions"><button v-if="batchResults.length" class="inline-action" @click="batchVisible = true">上次批次</button><button class="inline-action" :disabled="isOpenApi || !tasks.length" @click="toggleSelection">{{ selectionMode ? '退出多选' : '批量处理' }}</button></view></view>
            <view v-if="loading && !tasks.length" class="loading"><u-loading-icon text="加载中" /></view>
            <view v-else-if="sectionLoaded && !tasks.length && !error" class="empty"><u-empty mode="list" :text="status || keyword ? '没有符合条件的业务' : '暂无对接任务'" /><button v-if="status || keyword" class="inline-action" @click="clearTaskFilter">清除筛选</button><text v-else class="muted">{{ isOpenApi ? '可先更新基础资料，等待博远开放单据接口。' : '接收的新业务会保留在这里，处理结果可持续查看。' }}</text></view>
            <view class="task-list"><view v-for="task in tasks" :key="task.id" class="task" :class="{ 'task-selected': selectedIds.includes(task.id) }" @click="selectionMode ? toggleTask(task) : openTask(task.id)">
                <view v-if="selectionMode" class="task-check" @click.stop><u-checkbox :used-alone="true" :checked="selectedIds.includes(task.id)" :disabled="!taskSelectable(task) || busy" :label="'选择 ' + task.business_no" :label-size="12" @change="toggleTask(task)" /></view>
                <view class="row"><text class="task-title">{{ kindName(task.kind) }}</text><SyncState :status="task.status" /></view>
                <text class="number">{{ task.business_no }}</text>
                <text v-if="task.reason" class="reason">{{ task.reason }}</text>
                <text v-if="noticeText(task.notice_status) && needsAttention(task.status)" class="notice-caption">{{ noticeText(task.notice_status) }}</text>
                <view class="row foot"><text>{{ modeName(task.mode) }} · {{ time(task.update_at) }}</text><u-icon name="arrow-right" size="13" /></view>
            </view></view>
            <view class="load-more"><u-button v-if="tasks.length < total" size="small" :loading="loading" @click="loadTasks(false)">加载更多</u-button><text v-else-if="tasks.length" class="muted">共 {{ total }} 条记录</text></view>
        </template>
        <template v-else-if="section === 'archives'">
            <view class="section-head"><view><text class="task-title">基础资料</text><text class="muted">博远资料只读更新，不导入历史库存和单据</text></view><u-button size="small" type="primary" :loading="busy" @click="startSync()">更新全部</u-button></view>
            <view class="archive-filters"><button :class="{ active: archiveFilter === '' }" @click="archiveFilter = ''">全部资料</button><button :class="{ active: archiveFilter === 'attention' }" @click="archiveFilter = 'attention'">待处理 {{ archiveStats.attention }}</button></view>
            <view v-if="sectionLoaded && !visibleArchives.length" class="empty"><u-empty mode="list" text="暂无需要处理的资料" /><button class="inline-action" @click="archiveFilter = ''">查看全部资料</button></view>
            <view v-for="archive in visibleArchives" :key="archive.resource" class="archive-row">
                <view class="row"><button class="archive-name" @click="openArchive(archive)">{{ archive.name }}<text class="muted">{{ archive.count }} 条 · {{ archive.last_success_at ? '最近更新 ' + time(archive.last_success_at) : archive.job?.status === 'needs_review' ? '完整性待核对' : '尚未完成首次更新' }}</text></button><button class="icon-button" :aria-label="'同步' + archive.name" :disabled="busy" @click="startSync(archive.resource)"><u-icon name="reload" size="20" /></button></view>
                <view class="row archive-top"><text class="owner-tag" :class="ownership(archive.resource).tone">{{ ownership(archive.resource).label }}</text><SyncState v-if="archive.job" :status="archive.job.status" /></view>
                <view v-if="archive.job && ['queued', 'running', 'retry', 'failed'].includes(archive.job.status)" class="row archive-progress"><text>{{ syncState(archive.job.status) }} · 已读取 {{ archive.job.processed }} 条</text><u-button v-if="archive.job.status === 'failed'" size="small" :loading="busy" @click="resumeSync(archive.job.id)">继续更新</u-button></view>
                <view v-if="archive.job?.reason" class="archive-issue"><text class="reason">{{ archive.job.reason }}</text><button class="inline-action" @click="openArchive(archive)">查看资料 <text aria-hidden="true">›</text></button></view>
                <text v-if="archive.job?.warning" class="muted archive-warning">{{ archive.job.warning }}</text>
                <text v-if="archive.resource === 'members' && archive.job" class="muted">注册日期 {{ archive.job.scope.since }} 至 {{ archive.job.scope.until }}</text>
            </view>
        </template>
        <template v-else-if="section === 'mappings'">
            <view class="scope-notice"><u-icon name="checkmark-circle" size="17" /><view><text>只处理需要跨系统关联的资料</text><text class="muted">仓库、收付款账户在基础资料中查看，无需手工对应。实际业务来源切换尚未启用。</text></view></view>
            <view class="mapping-tabs"><u-tabs :list="visibleMappingTabs" :current="mappingTabIndex" :scrollable="true" lineColor="#2563eb" lineWidth="22" lineHeight="3" :activeStyle="{ color: '#20252d', fontWeight: '600', fontSize: '14px' }" :inactiveStyle="{ color: '#7a828e', fontSize: '14px' }" :itemStyle="{ height: '46px', padding: '0 16px' }" @change="changeMappingTab" /></view>
            <view class="section-head"><view><text class="task-title">{{ mappingNames[mappingKind] }}对应</text><text class="muted">{{ modeName(config.mode) }} · {{ mappingTotal }} 项</text></view><u-button size="small" type="primary" :disabled="mappingUnavailable" @click="editMapping()">新增对应</u-button></view>
            <view v-if="mappingUnavailable" class="mapping-notice">博远当前未提供库位查询接口，暂不能新增库位对应。</view>
            <view class="search"><u-search v-model="mappingKeyword" :placeholder="'搜索' + mappingNames[mappingKind] + '名称 / 博远编码'" :show-action="false" @search="loadMappings(true)" @clear="loadMappings(true)" /></view>
            <view v-if="mappingView.error" class="mapping-error"><text>{{ mappingView.error }}</text><u-button size="small" :loading="mappingView.loading" @click="loadMappings(true)">重试</u-button></view>
            <view v-if="mappingView.loading && !mappings.length" class="loading"><u-loading-icon text="加载中" /></view>
            <view v-else-if="!mappings.length && !mappingView.error" class="empty"><u-empty mode="list" :text="'暂无' + mappingNames[mappingKind] + '对应'" /></view>
            <button v-for="map in mappings" :key="map.id" class="mapping-row" :aria-label="'编辑' + (map.local_name || map.local_ref) + '对应'" @click="editMapping(map)"><view><text class="task-title">{{ map.local_name || map.local_ref }}</text><view class="mapping-target"><u-icon name="arrow-right" size="12" /><text class="muted break">{{ map.external_name || map.external_code }}</text></view><text v-if="map.external_name && map.external_code" class="muted break">{{ map.external_code }}</text></view><u-icon name="edit-pen" size="19" /></button>
            <view class="load-more"><u-button v-if="mappings.length < mappingTotal" size="small" :loading="mappingView.loading" @click="loadMappings(false)">加载更多</u-button><text v-else-if="mappings.length" class="muted">共 {{ mappingTotal }} 项{{ mappingNames[mappingKind] }}对应</text></view>
        </template>
        <template v-else-if="section === 'logs'">
            <view class="section-head"><view><text class="task-title">操作记录</text><text class="muted">保留处理过程，提醒入队不代表已送达</text></view></view>
            <view v-if="!logs.length" class="empty"><u-empty mode="list" text="暂无操作记录" /></view>
            <view v-for="log in logs" :key="log.id" class="log-row"><text class="log-dot" /><view><text>{{ log.message }}</text><text class="muted">{{ time(log.create_at) }}</text><button v-if="log.task_id" class="inline-action" @click="openTask(log.task_id)">查看相关业务</button></view></view>
        </template>

        <view v-if="section === 'tasks' && selectionMode" class="batch-bar"><view class="batch-select-all"><u-checkbox :used-alone="true" :checked="allVisibleSelected" label="本页可处理项" :label-size="12" :disabled="busy || !selectableTasks.length" @change="toggleAll" /><text class="muted">已选 {{ selectedIds.length }} 笔</text></view><u-button type="primary" :disabled="!selectedIds.length || busy" @click="openBatch">同步到博远</u-button></view>

        <u-popup :show="batchVisible" mode="bottom" :round="8" closeable :close-on-click-overlay="false" @close="closeBatch">
            <view class="sheet batch-sheet">
                <view class="sheet-header"><text class="sheet-title">{{ batchResults.length ? '批次处理结果' : '确认批量处理' }}</text><text class="muted">{{ modeName(config.mode) }} · 账套 {{ config.profiles[config.mode]?.account_id || '本地模拟' }}</text></view>
                <scroll-view scroll-y class="sheet-body">
                    <template v-if="!batchResults.length">
                        <view class="batch-overview"><text class="task-title">本次选择 {{ selectedIds.length }} 笔业务</text><text class="muted">未发送的继续同步；结果未知的只查询原操作，不重新建单。</text></view>
                        <view v-for="task in selectedTasks" :key="task.id" class="batch-item"><view><text class="task-title">{{ task.business_no }}</text><text class="muted">{{ kindName(task.kind) }}</text></view><SyncState :status="task.status" /></view>
                    </template>
                    <template v-else>
                        <view class="batch-overview"><text class="task-title">{{ batchSummary }}</text><text class="muted">排队不是同步成功，结果自动更新；关闭后仍可从业务列表查看。</text><button class="inline-action" :disabled="batchPolling" @click="refreshBatchResults">{{ batchPolling ? '正在更新结果' : '刷新处理结果' }}</button></view>
                        <view v-for="result in batchResults" :key="result.id" class="batch-result"><view class="row"><text class="task-title">{{ result.business_no || '业务 #' + result.id }}</text><SyncState :status="result.status" /></view><text class="muted">{{ result.message }}</text><button v-if="!['queued', 'pending', 'processing', 'waiting', 'retry'].includes(result.status)" class="inline-action" @click="openBatchIssue(result.id)">{{ needsAttention(result.status) ? '查看并处理' : '查看业务' }} <text aria-hidden="true">›</text></button></view>
                    </template>
                    <view v-if="batchError" class="mapping-error">{{ batchError }}</view>
                </scroll-view>
                <view class="sheet-footer"><u-button v-if="!batchResults.length" type="primary" :loading="busy" @click="submitBatch">确认提交 {{ selectedIds.length }} 笔</u-button><u-button v-else type="primary" @click="closeBatch">返回业务列表</u-button></view>
            </view>
        </u-popup>

        <u-popup :show="!!detail || detailLoading" mode="bottom" :round="8" closeable :close-on-click-overlay="!busy" @close="closeDetail">
            <view class="sheet">
                <view class="sheet-header"><text class="sheet-title">任务详情</text><text v-if="detail" class="muted break">{{ detail.business_no }}</text></view>
                <scroll-view scroll-y class="sheet-body">
                    <view v-if="detailLoading" class="loading"><u-loading-icon /></view>
                    <template v-else-if="detail">
                        <view class="detail-state"><SyncState :status="detail.status" /><text>{{ detailMessage }}</text></view>
                        <view v-if="needsAttention(detail.status)" class="responsibility"><text>处理责任人：{{ assigneeName }}</text><text class="muted">{{ noticeText(detail.notice_status) || '待办持续保留，不依赖短暂消息提示' }}</text><button v-if="!config.assignee_uid" class="inline-action" @click="detail = null; openConfig()">设置责任人</button></view>
                        <text v-if="!detail.active_connection" class="notice">这是其他环境或旧连接的任务，不会发送到当前账套。</text>
                        <view class="detail-meta"><view class="row"><text>操作编号</text><text class="break">{{ detail.operation_id }}</text></view><view class="row"><text>博远单号</text><text class="break">{{ detail.external_no || '尚未取得' }}</text></view></view>
                        <view v-for="line in detail.snapshot?.lines || []" :key="line.source_line_id" class="device"><text class="task-title">{{ line.product_name }}</text><view class="row"><text class="muted break">{{ line.imei || line.sn || '缺少串号' }}</text><text class="amount">¥{{ line.amount }}</text></view></view>
                        <view v-if="detail.status === 'blocked' && requiredMaps.length" class="quick-maps"><text class="subheading">需要补充的资料</text><button v-for="item in requiredMaps" :key="item.kind + item.ref" class="mapping-choice" @click="mapFromTask(item)"><text>{{ mappingNames[item.kind] }} · {{ item.name }}</text><u-icon name="arrow-right" size="13" /></button></view>
                        <view class="timeline"><text class="subheading">处理记录</text><view v-for="log in detail.logs" :key="log.id" class="log-row"><text class="log-dot" /><view><text>{{ log.message }}</text><text class="muted">{{ time(log.create_at) }}</text></view></view></view>
                        <button class="text-button" @click="showPayload = !showPayload">{{ showPayload ? '收起技术回执' : '查看技术回执' }}</button><text v-if="showPayload" class="payload">{{ JSON.stringify(detail.result, null, 2) }}</text>
                        <template v-if="manualForm"><view class="form-field"><text>博远人工单号</text><u-input v-model="manualNo" placeholder="填写实际单号" /></view><view class="form-field"><text>处理说明</text><u-textarea v-model="manualNote" placeholder="填写已处理内容和核对依据，至少5字" :maxlength="1000" /></view><text class="notice">仅记录人工处理，不会标记为同步成功。</text></template>
                    </template>
                </scroll-view>
                <view v-if="detail" class="sheet-footer">
                    <u-button v-if="manualForm" :loading="busy" type="primary" @click="act('record_manual')">保存人工记录</u-button>
                    <u-button v-else-if="['pending', 'retry', 'waiting', 'processing'].includes(detail.status)" disabled>等待后台处理</u-button>
                    <template v-else-if="!['simulated', 'succeeded'].includes(detail.status)">
                        <u-button v-if="!isOpenApi && detail.side_effect === 'none' && detail.active_connection && detail.kind !== 'manual_review'" type="primary" :loading="busy" @click="act('retry')">补齐后重试</u-button>
                        <u-button v-else-if="!isOpenApi && detail.request?.operation_id && detail.active_connection" type="primary" :loading="busy" @click="act('query')">查询并核验结果</u-button>
                        <u-button :disabled="busy" @click="manualForm = true">记录人工处理</u-button>
                    </template>
                    <template v-else><u-button v-if="['simulated', 'succeeded'].includes(detail.status) && detail.active_connection" :loading="busy" @click="act('query')">核对博远状态</u-button><u-button @click="closeDetail">关闭</u-button></template>
                </view>
            </view>
        </u-popup>

        <u-popup :show="configVisible" mode="bottom" :round="8" closeable :close-on-click-overlay="false" @close="closeConfig">
            <view class="sheet"><view class="sheet-header"><text class="sheet-title">连接设置</text></view><scroll-view scroll-y class="sheet-body">
                <view class="mode-tabs"><button v-for="mode in modes" :key="mode" :class="{ selected: draft.mode === mode }" @click="changeDraftMode(mode)">{{ modeName(mode) }}</button></view>
                <template v-if="draft.mode === 'mock' || profile.protocol !== 'openapi_v153'"><view class="form-field inline"><text>接收新业务</text><u-switch v-model="draft.enabled" /></view><view class="form-field inline"><text>暂停外部投递</text><u-switch v-model="draft.paused" /></view></template>
                <view v-if="draft.mode === 'mock'" class="form-field"><text>模拟场景</text><picker :range="scenarios" range-key="label" @change="profile.scenario = scenarios[Number($event.detail.value)].value"><view class="select-input">{{ scenarios.find(s => s.value === profile.scenario)?.label }}</view></picker></view>
                <template v-else>
                    <view class="form-field"><text>接入协议</text><picker :range="protocols" range-key="label" @change="changeProtocol(protocols[Number($event.detail.value)].value)"><view class="select-input">{{ protocols.find(p => p.value === profile.protocol)?.label }}</view></picker></view>
                    <view class="form-field"><text>服务基础地址</text><u-input v-model="profile.endpoint" placeholder="https://博远域名/api" /></view>
                    <template v-if="profile.protocol === 'openapi_v153'">
                        <view class="form-field"><text>网关路径</text><u-input v-model="profile.gateway_path" placeholder="/gateway.do" /></view>
                        <view class="form-field"><text>AppID</text><u-input v-model="profile.app_id" placeholder="博远分配的应用ID" /></view>
                        <view class="form-field"><text>AppSecret</text><u-input v-model="profile.app_secret" type="password" :placeholder="profile.app_secret_configured ? '已加密保存，留空不修改' : '博远提供的32位密钥'" /></view>
                        <view class="form-field"><text>账套代码 big_gsdm</text><u-input v-model="profile.account_id" placeholder="账套公司代码，不是门店UUID" /></view>
                        <view class="form-field"><text>会员档案起始日期</text><picker mode="date" :value="profile.member_since" @change="profile.member_since = $event.detail.value"><view class="select-input">{{ profile.member_since }}</view></picker></view>
                        <view class="notice">接口版本 1.0 · MD5 · UTF-8<text class="muted">仅同步基础资料，不创建单据或收付款。</text></view>
                    </template>
                    <template v-else><view class="form-field"><text>目标账套编号</text><u-input v-model="profile.account_id" placeholder="博远授权对应的账套" /></view><view class="form-field"><text>接口授权</text><u-input v-model="profile.token" type="password" :placeholder="profile.token_configured ? '已加密保存，留空保留原授权' : '填写约定桥接服务Token'" /></view><view class="form-field inline"><text>接口契约已双方确认</text><u-switch v-model="profile.contract_confirmed" /></view><view v-if="draft.mode === 'production'" class="form-field"><text>真实测试验收单号</text><u-input v-model="profile.acceptance_ref" placeholder="填双方验收记录编号" /></view><view class="form-field"><text>启用写入确认</text><u-input v-model="confirmAccount" placeholder="启用时重新输入目标账套编号" /></view></template>
                    <u-button :loading="busy" @click="checkConnection">核验已保存账套</u-button><text class="muted">{{ profile.probe_at ? '最近核验：' + time(profile.probe_at) : '尚未核验' }}</text>
                </template>
                <view v-if="draft.mode === 'mock' || profile.protocol !== 'openapi_v153'" class="form-field inline"><text>第二期财务同步</text><u-switch v-model="draft.finance_enabled" /></view>
                <view class="form-field"><text>异常责任人</text><picker :range="staffOptions" range-key="label" @change="draft.assignee_uid = staffOptions[Number($event.detail.value)].uid"><view class="select-input">{{ staffOptions.find(s => s.uid === draft.assignee_uid)?.label || '未设置' }}</view></picker></view>
                <text class="notice">切换模式不会迁移已有任务。暂停不能撤回已发出的请求。</text>
            </scroll-view><view class="sheet-footer"><u-button :loading="busy" type="primary" @click="persistConfig">保存设置</u-button></view></view>
        </u-popup>

        <u-popup :show="mappingVisible" mode="bottom" :round="8" closeable :close-on-click-overlay="false" @close="closeMapping">
            <view class="sheet mapping-sheet"><view class="sheet-header"><text class="sheet-title">{{ mappingNames[mappingDraft.kind] }}对应</text></view><scroll-view scroll-y class="sheet-body">
                <template v-if="!lookupMode"><view class="binding-step"><view class="row"><text class="step-label">1 · 本站{{ mappingNames[mappingDraft.kind] }}</text><button class="inline-action" :disabled="busy" @click="openLookup('local')">{{ mappingDraft.local_ref ? '更换' : '选择本地资料' }}</button></view><text class="binding-name">{{ mappingDraft.local_name || '请选择需要关联的本站资料' }}</text></view>
                <view class="binding-step"><view class="row"><text class="step-label">2 · 博远{{ mappingNames[mappingDraft.kind] }}</text><button class="inline-action" :disabled="busy" @click="openLookup('remote')">{{ mappingDraft.external_id ? '更换' : '选择博远档案' }}</button></view><text class="binding-name">{{ mappingDraft.external_name || '请选择同一对象在博远的资料' }}</text><text v-if="mappingDraft.external_code" class="muted">档案编码 {{ mappingDraft.external_code }}</text></view></template>
                <view v-else class="binding-context"><text class="muted">{{ lookupMode === 'local' ? '选择本站' + mappingNames[mappingDraft.kind] : '为 ' + (mappingDraft.local_name || '本站资料') + ' 选择博远资料' }}</text></view>
                <view v-if="lookupMode === 'local'" class="reference-lookup"><text class="subheading">选择本站资料</text><u-search v-model="sourceKeyword" placeholder="搜索本地ERP资料" :show-action="false" @search="loadSources(false)" @clear="loadSources(false)" /><view v-if="sourceLoading" class="lookup-loading"><u-loading-icon /></view><button v-for="item in sourceRows" :key="item.ref" class="mapping-choice" @click="selectSource(item)"><view><text>{{ item.name }}</text><text v-if="item.phone_masked" class="muted">{{ item.phone_masked }}</text></view><u-icon name="arrow-right" size="16" /></button><text v-if="!sourceLoading && !sourceRows.length" class="muted lookup-empty">没有匹配的本站资料，可调整搜索条件</text><u-button v-if="sourceMore" size="small" :loading="sourceLoading" @click="loadSources(true)">加载更多本地资料</u-button></view>
                <view v-if="lookupMode === 'remote' && referenceResource" class="reference-lookup">
                    <view class="row"><text class="subheading">{{ candidateLabel || '选择博远资料' }}</text><button v-if="canMatchPhone" class="inline-action" :disabled="referenceLoading" @click="loadCandidates">按手机号匹配</button></view>
                    <text v-if="candidateHint" class="candidate-hint">{{ candidateHint }}</text>
                    <u-search v-model="referenceKeyword" placeholder="搜索名称 / 编码 / 完整手机号" :show-action="false" @search="loadReferences()" @clear="loadReferences()" />
                    <view v-if="referenceLoading" class="lookup-loading"><u-loading-icon /></view>
                    <button v-for="item in referenceRows" :key="item.id" class="mapping-choice candidate-choice" @click="selectReference(item)">
                        <view><text class="task-title">{{ item.name }}</text><text class="muted">{{ item.phone_masked || item.code }}</text><text v-if="item.extra?.company_name" class="muted">{{ item.extra.company_name }}</text>
                            <text v-if="item.extra?.memory || item.extra?.color" class="muted">内存 {{ item.extra.memory || '未提供' }} · 颜色 {{ item.extra.color || '未提供' }}</text>
                            <text v-if="item.extra?.version_type_name" class="muted">版本 {{ item.extra.version_type_name }}</text>
                            <text v-if="item.extra?.is_cust || item.extra?.is_supplier" class="muted">{{ item.extra?.is_cust ? '客户 ' : '' }}{{ item.extra?.is_supplier ? '供应商' : '' }}</text>
                        </view><u-icon :name="mappingDraft.external_id === item.id ? 'checkmark-circle' : 'arrow-right'" size="18" />
                    </button>
                    <text v-if="!referenceLoading && !referenceRows.length" class="muted lookup-empty">没有可选资料，可搜索其他条件或先更新基础资料</text>
                    <u-button v-if="referenceCursor" size="small" :loading="referenceLoading" @click="loadReferences(true)">加载更多</u-button>
                </view>
                <view v-if="mappingError" class="mapping-error">{{ mappingError }}</view>
                <template v-if="!lookupMode"><text class="notice">仅保存当前账套中的对应关系，不合并登录账号，不修改历史单据。保存后可在这里更正。</text>
                <button class="text-button" @click="showSourceRef = !showSourceRef">{{ showSourceRef ? '收起技术编号' : '查看技术编号' }}</button><view v-if="showSourceRef" class="technical-ids"><text>本站编号：{{ mappingDraft.local_ref || '未选择' }}</text><text>博远编号：{{ mappingDraft.external_id || '未选择' }}</text></view></template>
            </scroll-view><view class="sheet-footer"><u-button v-if="lookupMode" @click="exitLookup">返回核对</u-button><u-button v-else :loading="busy" :disabled="!mappingDraft.local_ref || !mappingDraft.external_id" type="primary" @click="persistMapping">{{ returnTaskId ? '保存绑定并返回业务' : '保存对应' }}</u-button></view></view>
        </u-popup>
        <u-popup :show="archiveVisible" mode="bottom" :round="8" closeable @close="archiveVisible = false">
            <view class="sheet"><view class="sheet-header"><text class="sheet-title">{{ selectedArchive?.name }}</text><text class="muted">{{ ownership(selectedArchive?.resource).label }} · {{ ownership(selectedArchive?.resource).hint }}</text></view><scroll-view scroll-y class="sheet-body">
                <view class="search"><u-search v-model="archiveKeyword" placeholder="搜索名称 / 编码 / 完整手机号" :show-action="false" @search="loadArchive(false)" @clear="loadArchive(false)" /></view>
                <view v-if="!archiveRows.length && !archiveLoading" class="empty"><u-empty mode="list" text="暂无已同步资料" /></view>
                <view v-for="item in archiveRows" :key="item.id" class="archive-item"><view class="row"><text class="task-title">{{ item.name }}</text><text class="state">{{ item.stale ? '待核对' : item.extra?.status_known === false ? '状态未提供' : item.enabled ? '启用' : '停用' }}</text></view><text class="muted break">{{ item.code }}</text><text v-if="item.phone_masked" class="muted">{{ item.phone_masked }}</text><text v-if="item.extra?.memory || item.extra?.color" class="muted">{{ item.extra.memory }} {{ item.extra.color }}</text>
                    <view v-if="item.enabled && !item.stale" class="row"><button v-for="kind in mappingKindsFor(item)" :key="kind" class="text-button" @click="mapArchive(item, kind)">对应{{ mappingNames[kind] }}</button></view>
                </view>
            </scroll-view><view class="sheet-footer"><u-button v-if="archiveCursor" :loading="archiveLoading" @click="loadArchive(true)">加载更多</u-button><u-button v-else @click="archiveVisible = false">关闭</u-button></view></view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, onUnmounted } from 'vue'
import { onShow, onHide } from '@dcloudio/uni-app'
import * as api from '../../api/index'
import SyncState from '../../components/SyncState.vue'
const modes = ['mock', 'test', 'production']
const protocols = [{ value: 'openapi_v153', label: '博远开放平台 v1.5.3' }, { value: 'bridge_v1', label: '约定桥接接口 v1.0' }]
const modeName = (mode: string) => ({ mock: '模拟模式', test: '测试模式', production: '正式模式' }[mode] || mode)
const kindName = (kind: string) => ({ purchase: '采购入库', sale: '销售出库', purchase_return: '采购退货', sale_return: '销售退货', party_resolve: '往来单位对应', financial_item_ensure: '应收应付关联', settlement_register: '收付款登记', manual_review: '原单变更待核对' }[kind] || kind)
const mappingNames: Record<string, string> = { product: '商品', warehouse: '仓库', location: '库位', supplier: '供应商', customer: '客户', staff: '人员', method: '收付方式', account: '资金账户' }
const mappingTabs = [{ kind: 'product', name: '商品' }, { kind: 'warehouse', name: '仓库' }, { kind: 'staff', name: '人员' }, { kind: 'customer', name: '客户' }, { kind: 'supplier', name: '供应商' }, { kind: 'method', name: '收付方式' }, { kind: 'account', name: '资金账户' }, { kind: 'location', name: '库位' }]
const visibleMappingTabs = computed(() => isOpenApi.value ? mappingTabs.filter(item => ['product', 'staff', 'customer', 'supplier'].includes(item.kind)) : mappingTabs)
const scenarios = [{ value: 'success', label: '正常成功' }, { value: 'rejected', label: '明确拒绝' }, { value: 'timeout_after_commit', label: '处理成功但响应丢失' }, { value: 'partial', label: '部分成功' }, { value: 'malformed', label: '回执不完整' }]
const sections = [{ key: 'tasks', name: '同步任务' }, { key: 'archives', name: '基础资料' }, { key: 'mappings', name: '资料对应' }, { key: 'logs', name: '操作记录' }]
const filters = [{ value: '', label: '全部' }, { value: 'ready', label: '待同步' }, { value: 'working', label: '处理中' }, { value: 'action_required', label: '需要处理' }, { value: 'completed', label: '已完成' }]
const config = ref<any>({ mode: 'mock', enabled: false, paused: true, profiles: {} })
const isOpenApi = computed(() => config.value.mode !== 'mock' && config.value.profiles[config.value.mode]?.protocol === 'openapi_v153')
const configLoaded = ref(false), sectionLoaded = ref(false), archiveFilter = ref('')
const visibleArchives = computed(() => archiveJobs.value.filter(item => !archiveFilter.value || (archiveFilter.value === 'attention' ? ['failed', 'needs_review'].includes(item.job?.status) : archiveFilter.value === 'working' ? ['queued', 'running', 'retry'].includes(item.job?.status) : item.job?.status === 'completed')))
const ownership = (resource: string) => ({
    products: { label: '本站维护 · 结构化匹配', tone: 'local', hint: '按型号、内存、颜色及版本核对；当前只读取博远已有商品，建档接口待确认。' },
    warehouses: { label: '博远提供 · 无需对应', tone: 'external', hint: '直接使用博远仓库身份，无需人工绑定；本站业务来源切换尚未启用。' },
    capital_accounts: { label: '博远提供 · 无需对应', tone: 'external', hint: '博远收付款账户资料，不读取历史余额和流水；业务切换尚未启用。' },
    payment_methods: { label: '博远提供', tone: 'external', hint: '收银方式与资金账户分开管理，不代表已收款。' },
    staff: { label: '人员身份绑定', tone: 'binding', hint: '手机号匹配后确认同一人，不创建或合并登录账号。' },
    parties: { label: '往来单位 · 可兼多角色', tone: 'binding', hint: '客户、供应商共用往来主体。按手机号查找或创建的接口待博远确认。' },
}[resource] || { label: '博远参考资料', tone: 'reference', hint: '仅供本站业务参考，不覆盖本地主档和历史记录。' })
const archiveJobs = ref<any[]>([]), archiveVisible = ref(false), selectedArchive = ref<any>(null), archiveRows = ref<any[]>([]), archiveKeyword = ref(''), archiveCursor = ref(''), archiveLoading = ref(false)
const archiveStats = computed(() => ({ pending: archiveJobs.value.filter(a => ['queued', 'running', 'retry'].includes(a.job?.status)).length, attention: archiveJobs.value.filter(a => ['failed', 'needs_review'].includes(a.job?.status)).length, completed: archiveJobs.value.filter(a => a.job?.status === 'completed').length }))
const archiveMappingKinds = computed<string[]>(() => {
    const kinds = ({ products: ['product'], warehouses: ['warehouse'], staff: ['staff'], parties: ['customer', 'supplier'], payment_methods: ['method'], capital_accounts: ['account'] }[selectedArchive.value?.resource] || [])
    return isOpenApi.value ? kinds.filter(kind => ['product', 'staff', 'customer', 'supplier'].includes(kind)) : kinds
})
const syncState = (status: string) => ({ queued: '待同步', running: '读取中', retry: '等待重试', failed: '同步中断', needs_review: '数量待核对', completed: '本轮完成' }[status] || status)
const tasks = ref<any[]>([]), logs = ref<any[]>([]), counts = ref<Record<string, number>>({})
const loading = ref(false), error = ref(''), busy = ref(false), detailLoading = ref(false), detail = ref<any>(null)
const keyword = ref(''), status = ref(''), section = ref('tasks'), page = ref(1), total = ref(0)
const configVisible = ref(false), draft = ref<any>({ profiles: {} }), confirmAccount = ref(''), savedDraft = ref('')
const profile = computed<any>(() => draft.value.profiles[draft.value.mode] || {})
const staffOptions = ref<any[]>([{ uid: 0, label: '不发送员工提醒' }])
const assigneeName = computed(() => !config.value.assignee_uid ? '未指定' : staffOptions.value.find(item => item.uid === config.value.assignee_uid)?.label || '已指定员工')
const needsAttention = (value: string) => ['blocked', 'rejected', 'partial', 'attention', 'manual_pending', 'confirming'].includes(value)
const noticeText = (value: string) => ({ queued: '提醒已入队，送达待确认', unavailable: '提醒未送出，请检查责任人和消息配置', retry: '提醒发送异常，待办仍保留', simulated: '模拟提醒，不发送真实消息' }[value] || '')
const selectionMode = ref(false), selectedIds = ref<number[]>([]), batchVisible = ref(false), batchResults = ref<any[]>([]), batchError = ref('')
const selectedTasks = computed(() => tasks.value.filter(task => selectedIds.value.includes(task.id)))
const taskSelectable = (task: any) => !isOpenApi.value && !!task.batch_action && task.mode === config.value.mode && task.connection_key === config.value.profiles[config.value.mode]?.connection_key
const selectableTasks = computed(() => tasks.value.filter(taskSelectable))
const allVisibleSelected = computed(() => !!selectableTasks.value.length && selectableTasks.value.every(task => selectedIds.value.includes(task.id)))
const batchPolling = ref(false)
const batchSummary = computed(() => {
    const count = (values: string[]) => batchResults.value.filter(row => values.includes(row.status)).length
    return [[count(['succeeded']), '笔已同步'], [count(['simulated']), '笔模拟完成'], [count(['queued', 'pending', 'processing', 'waiting', 'retry']), '笔处理中'], [count(['confirming']), '笔结果待确认'], [count(['blocked', 'rejected', 'partial', 'attention', 'manual_pending']), '笔待处理'], [count(['skipped']), '笔未重复提交']].filter(([n]) => n).map(([n, label]) => n + ' ' + label).join('，')
})
let batchConnection = '', batchMode = ''
const mappingVisible = ref(false), mappingDraft = ref<any>({ kind: 'product', extra: {} })
const sourceKeyword = ref(''), sourceRows = ref<any[]>([]), sourceLoading = ref(false), sourceMore = ref(false), sourcePage = ref(1)
type MappingView = { items: any[], keyword: string, page: number, total: number, loading: boolean, loaded: boolean, error: string, connection: string, request: number }
const mappingKind = ref('product')
const mappingViews = reactive<Record<string, MappingView>>(Object.fromEntries(mappingTabs.map(tab => [tab.kind, { items: [], keyword: '', page: 1, total: 0, loading: false, loaded: false, error: '', connection: '', request: 0 }])))
const mappingView = computed(() => mappingViews[mappingKind.value])
const mappings = computed(() => mappingView.value.items)
const mappingTotal = computed(() => mappingView.value.total)
const mappingKeyword = computed({ get: () => mappingView.value.keyword, set: value => { mappingView.value.keyword = value } })
const mappingTabIndex = computed(() => Math.max(0, visibleMappingTabs.value.findIndex(tab => tab.kind === mappingKind.value)))
const mappingUnavailable = computed(() => mappingKind.value === 'location')
const mappingConnection = () => config.value.mode + ':' + (config.value.profiles[config.value.mode]?.connection_key || '')
watch(mappingConnection, (connection, previous) => {
    if (previous.endsWith(':')) return
    selectedIds.value = []; selectionMode.value = false; batchResults.value = []; batchVisible.value = false
    if (!visibleMappingTabs.value.some(tab => tab.kind === mappingKind.value)) mappingKind.value = 'product'
    for (const view of Object.values(mappingViews)) { view.request++; Object.assign(view, { items: [], keyword: '', page: 1, total: 0, loading: false, loaded: false, error: '', connection }) }
    if (section.value === 'mappings') loadMappings(true)
})
const showSourceRef = ref(false), referenceKeyword = ref(''), referenceLoading = ref(false), referenceRows = ref<any[]>([]), referenceCursor = ref('')
const lookupMode = ref(''), mappingError = ref(''), candidateLabel = ref(''), candidateHint = ref('')
const canMatchPhone = computed(() => isOpenApi.value && !!mappingDraft.value.local_ref && ['staff', 'customer', 'supplier'].includes(mappingDraft.value.kind))
let savedMapping = '', referenceRequest = 0, sourceRequest = 0
const referenceResource = computed(() => ({ product: 'products', warehouse: 'warehouses', staff: 'staff', method: 'payment_methods', account: 'capital_accounts', customer: 'parties', supplier: 'parties' }[mappingDraft.value.kind] || ''))
const manualForm = ref(false), manualNo = ref(''), manualNote = ref(''), showPayload = ref(false)
const detailMessage = computed(() => detail.value?.reason || ({ simulated: '模拟回执已核验，未向博远写入', succeeded: '博远已确认业务生效', pending: '处理请求已保存，等待后台执行', retry: '等待后台按原任务继续处理', waiting: '原业务完成后将继续处理', processing: '后台正在核验，请勿重复提交', confirming: '结果尚未确认，将核验原操作，不重新建单' }[detail.value?.status] || '查看下方处理记录'))
const pendingCount = computed(() => ['pending', 'processing', 'waiting', 'confirming', 'retry'].reduce((sum, key) => sum + (counts.value[key] || 0), 0))
const attentionCount = computed(() => ['blocked', 'rejected', 'partial', 'attention', 'manual_pending'].reduce((sum, key) => sum + (counts.value[key] || 0), 0))
const completedCount = computed(() => (counts.value.simulated || 0) + (counts.value.succeeded || 0))
const summaryMetrics = computed(() => section.value === 'archives' ? [{ key: 'working', count: archiveStats.value.pending, label: '更新中', alert: false }, { key: 'attention', count: archiveStats.value.attention, label: '待核对', alert: true }, { key: 'completed', count: archiveStats.value.completed, label: '已更新', alert: false }] : [{ key: 'working', count: pendingCount.value, label: '处理中', alert: false }, { key: 'action_required', count: attentionCount.value, label: '需要处理', alert: true }, { key: 'completed', count: completedCount.value, label: config.value.mode === 'mock' ? '模拟完成' : '已同步', alert: false }])
const workerStale = computed(() => config.value.enabled && Date.now() / 1000 - Number(config.value.worker_last_at || config.value.started_at) > 180)
const time = (timestamp: number) => timestamp ? new Date(timestamp * 1000).toLocaleString('zh-CN', { hour12: false }) : '-'
const message = (err: any) => String(err?.msg || err?.message || '请求未完成，请重试').slice(0, 240)
const toast = (title: string) => uni.showToast({ title, icon: 'none', duration: 2400 })
let timer: ReturnType<typeof setInterval> | undefined
let batchTimer: ReturnType<typeof setInterval> | undefined
let active = false
let pendingReload = false
let returnTaskId = 0
let mappingDraftConnection = ''
let initialized = false
async function loadTasks(reset = true) {
    if (loading.value) { if (reset) pendingReload = true; return }
    loading.value = true
    try {
        const nextPage = reset ? 1 : page.value + 1, connection = mappingConnection()
        const res = await api.getTasks({ page: nextPage, limit: 15, keyword: keyword.value, status: status.value, mode: config.value.mode })
        if (mappingConnection() !== connection) { pendingReload = true; return }
        tasks.value = reset ? res.data.data : [...tasks.value, ...res.data.data]
        total.value = res.data.total; page.value = nextPage
        sectionLoaded.value = true
        selectedIds.value = selectedIds.value.filter(id => tasks.value.some(task => task.id === id && taskSelectable(task)))
        counts.value = Object.fromEntries(res.data.counts.map((item: any) => [item.status, Number(item.total)])); error.value = ''
    } catch (err) { error.value = message(err) } finally { loading.value = false; if (pendingReload) { pendingReload = false; loadTasks(true) } }
}
async function refresh() {
    try {
        config.value = (await api.getConfig()).data
        configLoaded.value = true
        if (!initialized) { initialized = true; if (isOpenApi.value) section.value = 'archives' }
        if (section.value === 'tasks') await loadTasks(true)
        else if (section.value === 'archives') { archiveJobs.value = (await api.getReferenceJobs()).data; sectionLoaded.value = true; error.value = '' }
        else if (section.value === 'mappings') { await loadMappings(true); error.value = '' }
        else { logs.value = (await api.getLogs()).data; error.value = '' }
    } catch (err) { error.value = message(err) }
}
function selectStatus(value: string) { if (busy.value) return; selectedIds.value = []; status.value = value; loadTasks(true) }
function clearTaskFilter() { keyword.value = ''; selectStatus('') }
function changeSection(value: string) { if (busy.value) return; selectedIds.value = []; selectionMode.value = false; sectionLoaded.value = false; section.value = value; refresh() }
function focusMetric(key: string) { if (section.value === 'archives') archiveFilter.value = key; else selectStatus(key) }
function toggleSelection() { if (busy.value || isOpenApi.value) return; selectionMode.value = !selectionMode.value; selectedIds.value = [] }
function toggleTask(task: any) {
    if (busy.value || !taskSelectable(task)) return
    if (selectedIds.value.includes(task.id)) selectedIds.value = selectedIds.value.filter(id => id !== task.id)
    else if (selectedIds.value.length >= 50) toast('每次最多选择50笔业务')
    else selectedIds.value.push(task.id)
}
function toggleAll() { if (busy.value) return; selectedIds.value = allVisibleSelected.value ? [] : selectableTasks.value.slice(0, 50).map(task => task.id) }
function openBatch() {
    if (busy.value || !selectedIds.value.length || isOpenApi.value) return
    batchMode = config.value.mode; batchConnection = config.value.profiles[config.value.mode]?.connection_key || ''
    batchResults.value = []; batchError.value = ''; batchVisible.value = true
}
function closeBatch() { if (busy.value) return; batchVisible.value = false; if (batchResults.value.length) { selectionMode.value = false; selectedIds.value = []; loadTasks(true) } }
function openBatchIssue(id: number) { if (busy.value) return; batchVisible.value = false; selectionMode.value = false; selectedIds.value = []; openTask(id); loadTasks(true) }
async function submitBatch() {
    if (busy.value || !selectedIds.value.length || isOpenApi.value || batchResults.value.length) return
    if (config.value.mode !== batchMode || config.value.profiles[batchMode]?.connection_key !== batchConnection) { batchError.value = '账套已变化，请关闭后重新选择'; return }
    busy.value = true
    const captured = selectedTasks.value.map(task => ({ id: task.id, business_no: task.business_no }))
    try {
        const res = await api.submitTaskBatch({ ids: captured.map(task => task.id), mode: batchMode, connection_key: batchConnection, request_id: 'BATCH_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10) })
        batchResults.value = res.data.results.map((row: any) => ({ ...row, business_no: row.business_no || captured.find(task => task.id === row.id)?.business_no }))
        selectedIds.value = selectedIds.value.filter(id => !batchResults.value.some(row => row.id === id && ['queued', 'skipped'].includes(row.status)))
        await loadTasks(true)
    } catch (err) {
        batchError.value = '批次结果尚未确认，请先刷新业务状态，不要重复提交。' + message(err)
        batchResults.value = captured.map(task => ({ ...task, status: 'confirming', uncertain: true, message: '提交结果待确认，请进入业务核对原操作' }))
    } finally { busy.value = false }
}
async function refreshBatchResults() {
    if (!active || busy.value || batchPolling.value || !batchVisible.value || !batchResults.value.length) return
    const current = batchResults.value
    const pending = current.filter(row => ['queued', 'pending', 'processing', 'waiting', 'retry', 'confirming'].includes(row.status))
    if (!pending.length) return
    batchPolling.value = true
    try {
        const rows = (await api.getTaskBatchStatus({ ids: pending.map(row => row.id), mode: batchMode, connection_key: batchConnection })).data.items
        if (batchResults.value !== current || !batchVisible.value) return
        for (const row of pending) {
            const task = rows.find((item: any) => item.id === row.id)
            if (!task) { Object.assign(row, { status: 'blocked', message: '业务不在当前账套，请返回列表核对，未重新提交' }); continue }
            if (row.uncertain && ['pending', 'retry'].includes(task.status)) continue
            Object.assign(row, { status: task.status, uncertain: false, message: task.reason || (task.status === 'succeeded' ? '博远已确认业务生效' : task.status === 'simulated' ? '模拟回执已核验，没有向博远写入' : '后台处理中，请等待实际结果') })
        }
        if (!current.some(row => row.uncertain)) batchError.value = ''
    } catch { batchError.value = '最新结果暂时读取失败，已保留上次状态。可稍后刷新，不要重复提交。' }
    finally { batchPolling.value = false }
}
watch(batchVisible, visible => { if (batchTimer) clearInterval(batchTimer); if (visible) { refreshBatchResults(); batchTimer = setInterval(refreshBatchResults, 2500) } })
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
function changeDraftMode(mode: string) { draft.value.mode = mode; draft.value.paused = true; confirmAccount.value = ''; if (profile.value.protocol === 'openapi_v153') { draft.value.enabled = false; draft.value.finance_enabled = false; if (!profile.value.endpoint) profile.value.endpoint = mode === 'test' ? 'https://open-api-uat.139erp.com/api' : 'https://open-api.139erp.com/api' } }
function changeProtocol(protocol: string) { profile.value.protocol = protocol; draft.value.enabled = false; draft.value.paused = true; draft.value.finance_enabled = false }
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
        draft.value = JSON.parse(JSON.stringify(config.value))
        if (isOpenApi.value) section.value = 'archives'
        configVisible.value = false; await refresh(); toast('连接设置已保存')
    } catch (err) { toast(message(err)) } finally { busy.value = false }
}
async function checkConnection() {
    if (JSON.stringify(draft.value) !== savedDraft.value) return toast('请先保存配置，再核验已保存的账套')
    busy.value = true
    try { await api.probe(); config.value = (await api.getConfig()).data; draft.value = JSON.parse(JSON.stringify(config.value)); savedDraft.value = JSON.stringify(draft.value); toast('账套身份核验通过，未写单') } catch (err) { toast(message(err)) } finally { busy.value = false }
}
const requiredMaps = computed(() => {
    const result = detail.value?.missing_mappings || []
    return isOpenApi.value ? result.filter(item => ['product', 'staff', 'customer', 'supplier'].includes(item.kind)) : result
})
async function mapFromTask(item: any) {
    try { const res = await api.getMappings({ kind: item.kind, local_ref: item.ref, limit: 1 }); returnTaskId = detail.value.id; detail.value = null; editMapping(res.data.data[0] || { kind: item.kind, local_ref: item.ref, local_name: item.name }) } catch (err) { toast(message(err)) }
}
async function loadMappings(reset = true) {
    const kind = mappingKind.value, view = mappingViews[kind], connection = mappingConnection()
    if (view.connection !== connection) { view.items = []; view.keyword = ''; view.page = 1; view.total = 0; view.loaded = false; view.connection = connection }
    if (view.loading && !reset) return
    const request = ++view.request, next = reset ? 1 : view.page + 1, keyword = view.keyword
    view.loading = true; view.error = ''
    if (reset) { view.items = []; view.total = 0 }
    try {
        const res = await api.getMappings({ kind, page: next, limit: 50, keyword })
        if (view.request !== request || view.connection !== connection || mappingConnection() !== connection) return
        view.items = reset ? res.data.data : [...view.items, ...res.data.data]; view.page = next; view.total = res.data.total; view.loaded = true
    } catch (err) { if (view.request === request && view.connection === connection) view.error = message(err) }
    finally { if (view.request === request) view.loading = false }
}
function changeMappingTab(tab: { index: number }) {
    const target = visibleMappingTabs.value[tab.index]
    if (!target) return
    mappingKind.value = target.kind
    if (!mappingView.value.loaded || mappingView.value.connection !== mappingConnection()) loadMappings(true)
}
function finishMapping() { mappingVisible.value = false; referenceRequest++; sourceRequest++; if (returnTaskId) { const id = returnTaskId; returnTaskId = 0; openTask(id) } }
function closeMapping() { if (busy.value) return; if (JSON.stringify(mappingDraft.value) !== savedMapping) uni.showModal({ title: '放弃未保存的对应修改？', success: result => { if (result.confirm) finishMapping() } }); else finishMapping() }
function editMapping(map?: any) {
    const kind = map?.kind || mappingKind.value
    if (kind === 'location') return toast('当前协议未提供库位查询，保留已有对应，暂不能新建或修改')
    if (isOpenApi.value && !['product', 'staff', 'customer', 'supplier'].includes(kind)) return toast('该类资料由博远提供，无需手工对应')
    let extra = { unit_code: '' }
    try { if (map?.extra_json) extra = JSON.parse(map.extra_json) } catch { return toast('对应资料格式异常，请联系管理员核对') }
    mappingKind.value = kind; mappingDraftConnection = mappingConnection()
    mappingDraft.value = { kind, local_ref: '', local_name: '', external_id: '', external_code: '', external_name: '', revision: 0, ...map, extra }
    savedMapping = JSON.stringify(mappingDraft.value); referenceRequest++; sourceRequest++; sourceLoading.value = false; referenceLoading.value = false
    showSourceRef.value = false; lookupMode.value = ''; mappingError.value = ''; candidateHint.value = ''; candidateLabel.value = ''; referenceRows.value = []; referenceCursor.value = ''; referenceKeyword.value = ''; sourceRows.value = []; sourceKeyword.value = ''; sourceMore.value = false; mappingVisible.value = true
}
function openLookup(target: string) { lookupMode.value = target; mappingError.value = ''; if (target === 'local') loadSources(false); else if (canMatchPhone.value) loadCandidates(); else loadReferences() }
function exitLookup() { lookupMode.value = ''; referenceRequest++; referenceLoading.value = false }
async function loadSources(append: boolean) {
    if (append && sourceLoading.value) return
    const request = ++sourceRequest, kind = mappingDraft.value.kind, connection = mappingConnection(), next = append ? sourcePage.value + 1 : 1
    sourceLoading.value = true; mappingError.value = ''
    if (!append) { sourceRows.value = []; sourceMore.value = false }
    try { const res = await api.getMappingSources({ kind, keyword: sourceKeyword.value, page: next }); if (request !== sourceRequest || kind !== mappingDraft.value.kind || connection !== mappingConnection() || !mappingVisible.value) return; sourceRows.value = append ? [...sourceRows.value, ...res.data.items] : res.data.items; sourceMore.value = res.data.has_more; sourcePage.value = next } catch (err) { if (request === sourceRequest) mappingError.value = message(err) } finally { if (request === sourceRequest) sourceLoading.value = false }
}
async function selectSource(item: any) {
    if (busy.value) return
    busy.value = true
    const kind = mappingDraft.value.kind
    mappingDraft.value.local_ref = item.ref; mappingDraft.value.local_name = item.name; mappingDraft.value.revision = 0; mappingDraft.value.external_id = ''; mappingDraft.value.external_code = ''; mappingDraft.value.external_name = ''; sourceRows.value = []; sourceMore.value = false; lookupMode.value = ''
    try { const res = await api.getMappings({ kind, local_ref: item.ref, limit: 1 }); if (mappingDraft.value.kind === kind && mappingDraft.value.local_ref === item.ref) { const old = res.data.data[0]; if (old) { mappingDraft.value.revision = old.revision; mappingDraft.value.external_id = old.external_id; mappingDraft.value.external_code = old.external_code; mappingDraft.value.external_name = old.external_name; mappingDraft.value.extra = old.extra_json ? JSON.parse(old.extra_json) : { unit_code: '' } } } } catch (err) { mappingError.value = message(err) } finally { busy.value = false }
    if (canMatchPhone.value && !mappingDraft.value.external_id) { lookupMode.value = 'remote'; loadCandidates() }
}
async function loadCandidates() {
    if (!canMatchPhone.value) return
    const request = ++referenceRequest, connection = mappingConnection()
    referenceLoading.value = true; mappingError.value = ''; referenceRows.value = []; referenceCursor.value = ''
    try {
        const res = await api.getMappingCandidates({ kind: mappingDraft.value.kind, local_ref: mappingDraft.value.local_ref })
        if (request !== referenceRequest || connection !== mappingConnection() || !mappingVisible.value) return
        referenceRows.value = res.data.items
        candidateLabel.value = res.data.match === 'ambiguous' ? '同手机号找到 ' + res.data.total + ' 项' : res.data.match === 'unique' ? '找到1项手机号匹配' : '选择博远资料'
        candidateHint.value = ({ ambiguous: '请选择同一对象，保存后下次直接使用绑定。', unique: '请核对姓名、单位和角色后确认，不会自动合并账号。', missing_phone: '本站未填写有效手机号，可手动搜索并选择。', sync_required: '请先完整更新人员或往来单位资料，再进行手机号匹配。', not_found: '未找到同手机号的启用资料，可手动搜索。不会在博远自动创建账号。' } as Record<string, string>)[res.data.match] || ''
    } catch (err) { if (request === referenceRequest) mappingError.value = message(err) } finally { if (request === referenceRequest) referenceLoading.value = false }
}
async function loadReferences(append = false) {
    if (append && referenceLoading.value) return
    const request = ++referenceRequest, connection = mappingConnection()
    referenceLoading.value = true; mappingError.value = ''; candidateLabel.value = ''; candidateHint.value = ''
    try { const res = await api.getReferences({ resource: referenceResource.value, keyword: referenceKeyword.value, cursor: append ? referenceCursor.value : '1', role: mappingDraft.value.kind, enabled_only: true, limit: 25 }); if (request !== referenceRequest || connection !== mappingConnection() || !mappingVisible.value) return; const rows = (res.data.data?.items || []).filter((item: any) => item.enabled && !item.stale); referenceRows.value = append ? [...referenceRows.value, ...rows] : rows; referenceCursor.value = res.data.data?.next_cursor || '' } catch (err) { if (request === referenceRequest) mappingError.value = message(err) } finally { if (request === referenceRequest) referenceLoading.value = false }
}
async function startSync(resource?: string) {
    if (busy.value) return
    busy.value = true
    try { await api.syncReferences(resource ? [resource] : archiveJobs.value.map(item => item.resource)); archiveJobs.value = (await api.getReferenceJobs()).data; toast('资料同步已排队，可离开页面') } catch (err) { toast(message(err)) } finally { busy.value = false }
}
async function resumeSync(id: number) { if (busy.value) return; busy.value = true; try { await api.retryReference(id); archiveJobs.value = (await api.getReferenceJobs()).data; toast('已从失败分页继续') } catch (err) { toast(message(err)) } finally { busy.value = false } }
async function openArchive(archive: any) { selectedArchive.value = archive; archiveKeyword.value = ''; archiveRows.value = []; archiveVisible.value = true; await loadArchive(false) }
async function loadArchive(append: boolean) {
    if (archiveLoading.value) return
    archiveLoading.value = true
    try { const res = await api.getReferences({ resource: selectedArchive.value.resource, cached: true, keyword: archiveKeyword.value, cursor: append ? archiveCursor.value : '1', limit: 25 }); archiveRows.value = append ? [...archiveRows.value, ...res.data.data.items] : res.data.data.items; archiveCursor.value = res.data.data.next_cursor || '' } catch (err) { toast(message(err)) } finally { archiveLoading.value = false }
}
function mapArchive(item: any, kind: string) { archiveVisible.value = false; editMapping({ kind, external_id: item.id, external_code: item.code, external_name: item.name }) }
function mappingKindsFor(item: any) { return archiveMappingKinds.value.filter(kind => kind !== 'customer' && kind !== 'supplier' || !!item.extra?.[kind === 'customer' ? 'is_cust' : 'is_supplier']) }
function selectReference(item: any) { mappingDraft.value.external_id = item.id; mappingDraft.value.external_code = item.code; mappingDraft.value.external_name = item.name; if (item.unit_code) mappingDraft.value.extra.unit_code = item.unit_code; referenceRows.value = []; lookupMode.value = ''; mappingError.value = ''; referenceRequest++; referenceLoading.value = false }
async function persistMapping() {
    if (mappingDraftConnection !== mappingConnection()) return toast('连接已变化，请关闭后重新选择资料')
    if (!mappingDraft.value.external_id || !mappingDraft.value.external_code || !mappingDraft.value.local_ref) { mappingError.value = '请选择本站资料和对应的博远资料'; return }
    busy.value = true
    try { await api.saveMapping({ ...mappingDraft.value, mode: config.value.mode, connection_key: config.value.profiles[config.value.mode]?.connection_key }); await loadMappings(true); busy.value = false; const fromTask = !!returnTaskId; finishMapping(); toast(fromTask ? '绑定已保存，请继续处理原业务' : '对应已保存') } catch (err) { mappingError.value = message(err) } finally { busy.value = false }
}
onShow(() => { active = true; refresh(); if (!timer) timer = setInterval(async () => {
    if (!active || configVisible.value || mappingVisible.value || archiveVisible.value || batchVisible.value || selectionMode.value || busy.value) return
    try {
        config.value = (await api.getConfig()).data
        if (section.value === 'tasks' && page.value === 1) await loadTasks(true)
        if (section.value === 'archives') { archiveJobs.value = (await api.getReferenceJobs()).data; sectionLoaded.value = true }
        if (detail.value) { const id = detail.value.id; const res = await api.getTask(id); if (detail.value?.id === id) detail.value = res.data }
    } catch (err) { error.value = message(err) }
}, 10000) })
onHide(() => { active = false })
onUnmounted(() => { if (timer) clearInterval(timer); if (batchTimer) clearInterval(batchTimer) })
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
.archive-row { background: #fff; padding: 14px 16px; border-bottom: 1px solid #eef0f3; }.archive-name { text-align: left; min-width: 0; flex: 1; font-weight: 600; }.archive-progress { color: #7a828e; font-size: 12px; padding-top: 8px; }.archive-progress :deep(.u-button) { flex: initial; width: auto; margin: 0; }.archive-item { padding: 14px 0; border-bottom: 1px solid #eef0f3; }.archive-item .row .text-button { padding: 10px 0 0; }.nav { gap: 16px; justify-content: space-between; }
.archive-name .muted { font-weight: 400; }
.archive-warning { padding-top: 6px; }
.mapping-tabs { background: #fff; border-bottom: 1px solid #eef0f3; }.mapping-target { display: flex; align-items: flex-start; gap: 6px; margin-top: 6px; }.mapping-target :deep(.u-icon) { margin-top: 4px; flex-shrink: 0; }.mapping-row { width: calc(100% - 24px); text-align: left; align-items: center; }.mapping-row > view { flex: 1; }.mapping-notice, .mapping-error { margin: 0 12px 10px; padding: 12px; font-size: 12px; line-height: 1.7; background: #fff5e9; color: #a36318; }.mapping-error { display: flex; align-items: center; gap: 12px; }.mapping-error > text { flex: 1; min-width: 0; }
.mapping-error :deep(.u-button) { flex: 0 0 auto; width: auto; margin: 0; }
.account-caption { display: block; font-size: 12px; line-height: 1.5; color: #747d89; margin-top: 4px; overflow-wrap: anywhere; }.icon-button { width: 44px; height: 44px; flex: 0 0 44px; }.tools { gap: 0; }
.summary { padding: 16px 12px; }.summary button { min-height: 68px; text-align: center; }.metric-unit { font-size: 11px; color: #747d89; margin-left: 4px; }.metric-alert .metric { color: #ae601d; }.summary .muted { margin-top: 5px; }
.scope-notice, .inline-alert { display: flex; align-items: flex-start; gap: 9px; padding: 13px 16px; background: #eef5fc; color: #34577a; font-size: 13px; line-height: 1.65; }.scope-notice > view, .inline-alert > view { min-width: 0; flex: 1; }.scope-notice :deep(.u-icon), .inline-alert :deep(.u-icon) { margin-top: 3px; flex-shrink: 0; }.scope-notice .muted { color: #62758a; margin-top: 3px; }.notice-warning, .inline-alert { background: #fff6e9; color: #996019; }.notice-warning .muted { color: #8b7659; }
.list-toolbar, .toolbar-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }.list-toolbar { padding: 0 16px; background: #fff; min-height: 46px; }.inline-action { min-height: 44px; padding: 8px 0; color: #245fbe; font-size: 13px; font-weight: 500; text-align: left; }.inline-action[disabled] { color: #9aa2ad; }.task-selected { box-shadow: inset 0 0 0 1px #3976d2; background: #f5f9ff; }.task-check { padding: 0 0 8px; min-height: 32px; }.notice-caption { display: block; margin-top: 9px; color: #895d2b; font-size: 12px; line-height: 1.6; }
.archive-filters { display: flex; gap: 20px; padding: 0 16px; background: #fff; border-bottom: 1px solid #eef0f3; }.archive-filters button { min-height: 44px; font-size: 13px; color: #75808e; }.archive-filters .active { color: #235ab1; font-weight: 600; border-bottom: 2px solid #2d6bc8; }.archive-top { padding-top: 10px; }.owner-tag { display: inline-flex; font-size: 12px; line-height: 1.5; color: #687484; }.owner-tag.external { color: #25755b; }.owner-tag.local { color: #315fb3; }.owner-tag.binding { color: #8b6534; }.archive-issue { margin-top: 8px; padding-left: 10px; border-left: 2px solid #d69c4b; }.archive-issue .reason { padding-top: 0; }.archive-issue .inline-action { min-height: 36px; }.archive-progress { align-items: flex-start; }
.has-batch-bar { padding-bottom: calc(100px + env(safe-area-inset-bottom)); }.batch-bar { position: fixed; z-index: 30; left: 50%; bottom: 0; transform: translateX(-50%); width: 100%; max-width: 480px; box-sizing: border-box; background: #fff; border-top: 1px solid #e5e9ef; display: flex; align-items: center; gap: 14px; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); }.batch-select-all { flex: 1; min-width: 0; }.batch-bar :deep(.u-button) { flex: 0 0 auto; width: auto; margin: 0; min-width: 120px; }.batch-overview { padding: 18px 0; border-bottom: 1px solid #eef0f3; }.batch-overview .muted { margin-top: 7px; }.batch-item { display: flex; align-items: center; gap: 12px; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #eef0f3; }.batch-item > view:first-child { min-width: 0; }.batch-result { padding: 16px 0; border-bottom: 1px solid #eef0f3; }.batch-result .row { align-items: flex-start; }.batch-result .task-title { min-width: 0; flex: 1; }.batch-result .muted { margin-top: 8px; }
.responsibility { padding: 12px 0; border-top: 1px solid #eef0f3; border-bottom: 1px solid #eef0f3; font-size: 13px; line-height: 1.7; }.responsibility .muted { margin-top: 4px; }.binding-step { padding: 18px 0; border-bottom: 1px solid #e9edf3; }.step-label { font-size: 12px; color: #758092; }.binding-step .inline-action { padding: 0; min-height: 30px; }.binding-name { display: block; font-weight: 600; font-size: 15px; line-height: 1.65; overflow-wrap: anywhere; margin-top: 7px; }.binding-step .muted { margin-top: 5px; }.technical-ids { display: flex; flex-direction: column; gap: 8px; color: #7a828e; font-size: 12px; line-height: 1.6; overflow-wrap: anywhere; padding-bottom: 18px; }.candidate-hint { display: block; font-size: 12px; line-height: 1.7; color: #8b6534; margin: 4px 0 12px; }.reference-lookup .subheading { padding: 12px 0; }.lookup-loading, .lookup-empty { padding: 18px 0; }.candidate-choice { align-items: flex-start; gap: 12px; min-height: 60px; }.candidate-choice > view { min-width: 0; }.candidate-choice :deep(.u-icon) { flex-shrink: 0; margin-top: 3px; }.candidate-choice .muted { margin-top: 5px; }.sheet-body { min-height: 0; flex-shrink: 1; }.sheet-footer :deep(.u-button) { min-height: 44px; }
.nav { position: sticky; top: 0; z-index: 20; }
.batch-result .task-title, .batch-item .task-title { font-size: 13px; font-weight: 500; }
.binding-context { padding-top: 16px; }
@media (prefers-reduced-motion: reduce) { .bridge *, .bridge *::before { transition: none !important; animation: none !important; } }
@media (max-width: 350px) { .toolbar { padding: 12px; }.title { font-size: 18px; }.nav { gap: 22px; }.task { padding: 12px; }.state { font-size: 10px; }.sheet-body { padding: 0 12px; }.sheet-footer { padding-left: 12px; padding-right: 12px; } }
</style>
