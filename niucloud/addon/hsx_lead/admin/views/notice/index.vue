<template>
    <section class="account-notice-center">
        <header class="notice-head"><div><h2>通知中心</h2><p>查看协作与账户提醒的发送结果，按场景配置通知渠道。</p></div><el-button :loading="activeTab === 'records' ? loading : settingsLoading" :disabled="busy || saving" @click="refreshCurrent">刷新</el-button></header>
        <el-result v-if="!canRead" icon="warning" title="暂无通知中心访问权限" sub-title="请联系管理员开通通知记录读取权限" />
        <el-tabs v-else v-model="activeTab">
            <el-tab-pane label="发送记录" name="records">
                <el-form class="notice-filters" label-position="top" :disabled="loading || busy" @submit.prevent="search">
                    <el-form-item label="通知对象"><el-input v-model="filters.keyword" clearable placeholder="昵称、手机号或会员编号" @keyup.enter="search" @clear="search" /></el-form-item>
                    <el-form-item label="场景"><el-select v-model="filters.event_key" clearable placeholder="全部场景"><el-option v-for="(label,key) in accountNoticeEvents" :key="key" :label="label" :value="key" /></el-select></el-form-item>
                    <el-form-item label="渠道"><el-select v-model="filters.channel" clearable placeholder="全部渠道"><el-option v-for="(label,key) in accountNoticeChannels" :key="key" :label="label" :value="key" /></el-select></el-form-item>
                    <el-form-item label="状态"><el-select v-model="filters.status" clearable placeholder="全部状态"><el-option v-for="(label,key) in accountNoticeStatuses" :key="key" :label="label" :value="key" /></el-select></el-form-item>
                    <el-form-item label="时间范围" class="notice-filters__time"><el-date-picker v-model="dateRange" type="datetimerange" start-placeholder="开始时间" end-placeholder="结束时间" range-separator="至" :clearable="true" /></el-form-item>
                    <div class="notice-filter-actions"><el-button @click="reset">重置</el-button><el-button type="primary" native-type="submit" :loading="loading">查询</el-button></div>
                </el-form>
                <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="notice-banner"><template #default><el-button link type="primary" :disabled="loading || busy" @click="loadTasks">重新读取</el-button></template></el-alert>
                <div class="notice-surface">
                    <HsxTable :columns="columns" :data="rows" :loading="loading" row-key="id" :empty-text="error ? '通知记录未能读取，请重新加载' : '暂无符合条件的通知'">
                        <template #recipient="{row}"><div class="notice-cell"><strong>{{ row.nickname || '会员' }}</strong><small>{{ row.member_no || `会员 ID：${row.recipient_member_id}` }}</small><small v-if="row.mobile">{{ row.mobile }}</small></div></template>
                        <template #event="{row}"><div class="notice-cell"><strong>{{ row.event_name || accountNoticeEvents[row.event_key] || '场景待核对' }}</strong><small v-if="String(row.event_key).startsWith('cooperation_')">{{ row.task_no || `线索 #${row.source_id || '待核对'}` }}</small><template v-else><small>{{ row.account_name || '账户类型待核对' }}</small><small v-if="row.source_id">流水 #{{ row.source_id }}</small></template></div></template>
                        <template #amount="{row}"><div v-if="String(row.event_key).startsWith('cooperation_')" class="notice-cell"><strong>{{ row.store_name || '协作提醒' }}</strong><span>{{ row.notice_remark || '查看对应线索的当前状态' }}</span><small v-if="Number(row.remaining_minutes)>0">提醒节点：剩余 {{ row.remaining_minutes }} 分钟</small></div><div v-else class="notice-cell"><strong>{{ money(row.change_amount,true) }}</strong><small>变动后 {{ money(row.account_balance) }}</small></div></template>
                        <template #channel="{row}">{{ accountNoticeChannels[row.channel] || '渠道待核对' }}</template>
                        <template #status="{row}"><div class="notice-cell"><el-tag :type="accountNoticeStatusTone(row.status)" size="small" effect="plain">{{ accountNoticeStatuses[row.status] || '状态待核对' }}</el-tag><small>已尝试 {{ row.attempt_count ?? '—' }} 次</small><small v-if="row.next_retry_at && ['retry_wait','paused'].includes(row.status)">下次核对 {{ coopTime(row.next_retry_at) }}</small></div></template>
                        <template #result="{row}"><div class="notice-cell"><span>{{ row.last_message || '尚未记录渠道说明' }}</span><small v-if="row.provider_code !== '' && row.provider_code != null">渠道返回码 {{ row.provider_code }}</small><small v-if="unverifiedIds.has(Number(row.id))">上次操作结果待核对，请先刷新。</small></div></template>
                        <template #time="{row}"><div class="notice-cell"><span>{{ coopTime(row.occurred_at || row.create_time) }}</span><small v-if="row.expires_at">有效至 {{ coopTime(row.expires_at) }}</small></div></template>
                        <template #actions="{row}"><div class="notice-row-actions"><el-button v-if="canAttempts" link type="primary" :disabled="busy" @click="openAttempts(row)">尝试明细</el-button><el-button v-if="retryAllowed(row)" link type="primary" :disabled="loading || busy" @click="beginRetry(row)">重新检查</el-button><span v-if="!canAttempts && !retryAllowed(row)" class="notice-muted">—</span></div></template>
                    </HsxTable>
                    <div class="notice-pagination"><HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading || busy" @change="changePage" /></div>
                </div>
                <p class="notice-footnote">渠道已受理不代表用户已读；提交中或结果待核实的记录不能重复发送。线索工单通知仍在对应线索详情中查看。</p>
            </el-tab-pane>
            <el-tab-pane v-if="canSettings" label="通知规则" name="settings">
                <el-skeleton v-if="settingsLoading && !settingsReady" :rows="6" animated />
                <el-alert v-if="settingsError" :title="settingsError" type="error" show-icon :closable="false" class="notice-banner"><template #default><el-button link type="primary" :disabled="settingsLoading || saving" @click="loadSettings">重新读取</el-button></template></el-alert>
                <template v-if="settingsReady">
                    <el-alert v-if="!policy.installed" title="本站未安装启用商户协作，当前不能启用通知" type="warning" :closable="false" show-icon class="notice-banner" />
                    <el-form class="notice-policy" label-position="top" :disabled="!canSave || saving || settingsLoading || !policy.installed">
                        <div class="notice-general"><el-form-item label="通知总开关"><el-switch v-model="policy.enabled" inline-prompt active-text="开启" inactive-text="关闭" /></el-form-item><el-form-item label="提醒有效期（分钟）"><el-input-number v-model="ttlMinutes" :min="5" :max="1440" :precision="0" /></el-form-item><el-form-item label="单条最多尝试次数"><el-input-number v-model="policy.max_attempts" :min="1" :max="5" :precision="0" /></el-form-item></div>
                        <div class="notice-general"><el-form-item label="夜间免打扰"><el-switch v-model="policy.quiet_hours.enabled" active-text="开启" inactive-text="即时通知" /></el-form-item><el-form-item v-if="policy.quiet_hours.enabled" label="免打扰开始（北京时间）"><el-time-select v-model="policy.quiet_hours.start" start="00:00" step="00:15" end="23:45" placeholder="开始时间" /></el-form-item><el-form-item v-if="policy.quiet_hours.enabled" label="免打扰结束（北京时间）"><el-time-select v-model="policy.quiet_hours.end" start="00:00" step="00:15" end="23:45" placeholder="结束时间" /></el-form-item></div>
                        <p v-if="policy.quiet_hours.enabled" class="notice-muted">此时段不发送新通知或重试；结束后重新核对业务与时效，已接单、已转交或过时的提醒不会补发。</p><p v-if="quietHoursError" class="notice-danger">{{ quietHoursError }}</p>
                        <el-form-item v-if="hasCooperationEvents" label="接单提醒节点（剩余分钟）"><el-select v-model="policy.response_reminder_minutes" multiple filterable allow-create default-first-option placeholder="选择或输入1—1440分钟，最多8项" class="notice-reminder-select"><el-option v-for="minute in [30,10,5,1]" :key="minute" :label="`剩余${minute}分钟`" :value="minute" /></el-select><p v-if="responseNodesError" class="notice-danger">{{ responseNodesError }}</p></el-form-item>
                        <el-collapse class="notice-help"><el-collapse-item title="启用、自动重试与渠道授权说明" name="help"><p>资金提醒仅通知启用后的新流水，不补发历史账目。提醒超时后停止发送。</p><p>接单节点提醒发给接收方；时间到但尚未接单时提醒发布方，仍保留原交付，不自动转派，也不禁止原门店接单。门店确认接单后再通知发布方。</p><p>接单节点留空仅关闭截止前的节点提醒，不影响单独开启的“门店尚未接单”和“接单成功”场景。</p><p>系统只在确认可重试时自动处理；结果待核实不会盲目重发。用户关闭偏好、拒绝订阅或未绑定渠道时，不会绕过授权。</p><p>短信可能计费，必须同时满足平台启用与用户主动开启偏好。</p></el-collapse-item></el-collapse>
                        <div v-for="event in policy.events" :key="event.event_key" class="notice-event-card">
                            <div class="notice-event-head"><div><h3>{{ event.name || accountNoticeEvents[event.event_key] }}</h3><span class="notice-muted">{{ event.event_key === 'cooperation_acceptance_reminder' ? '接单提醒节点到达时通知接收方' : event.event_key === 'cooperation_response_overdue' ? '提醒时间到后通知发布方，不自动换店' : event.event_key === 'cooperation_assignment_accepted' ? '接单成功后通知发布方' : '仅通知流水所属会员本人' }}</span></div><el-switch v-model="event.enabled" :aria-label="`启用${event.name}`" /></div>
                            <div v-for="(label,channel) in accountNoticeChannels" :key="channel" class="notice-channel-row"><div class="notice-channel-name"><strong>{{ label }}</strong><el-tag size="small" effect="plain" :type="readiness(event, String(channel)).tone">{{ readiness(event, String(channel)).label }}</el-tag></div><div class="notice-channel-actions"><el-button v-if="channel !== 'sms' && canSave" link type="primary" @click="editTemplate(event,String(channel))">{{ event[channel]?.tid || event[channel]?.temp_key ? '核对模板' : '配置模板' }}</el-button><el-button link type="primary" @click="openChannel(String(channel))">渠道设置</el-button><el-switch v-model="event.channels[channel]" :aria-label="`${event.name}${label}规则开关`" /></div></div>
                        </div>
                    </el-form>
                    <div class="notice-policy-footer"><span class="notice-muted">保存规则不代表已发送，也不会替用户授权。</span><el-button v-if="canSave" type="primary" :loading="saving" :disabled="!policy.installed || settingsLoading || !!settingsError || !!responseNodesError || !!quietHoursError" @click="saveSettings">保存通知规则</el-button></div>
                </template>
            </el-tab-pane>
        </el-tabs>
        <HsxDrawer v-model="attemptsVisible" title="通知尝试明细" :subtitle="selectedTask.member_no || `任务 #${selectedTask.id || ''}`" size="lg" :body-loading="attemptsLoading">
            <el-alert v-if="attemptsError" :title="attemptsError" type="error" show-icon :closable="false"><template #default><el-button link type="primary" @click="loadAttempts">重新读取</el-button></template></el-alert>
            <template v-else-if="attemptsReady"><div class="notice-attempt-summary"><span>{{ accountNoticeEvents[selectedTask.event_key] || '资金提醒' }} · {{ accountNoticeChannels[selectedTask.channel] }}</span><el-tag size="small" :type="accountNoticeStatusTone(selectedTask.status)">{{ accountNoticeStatuses[selectedTask.status] || '待核对' }}</el-tag></div><HsxTable :columns="attemptColumns" :data="attempts" empty-text="尚无渠道提交记录"><template #trigger="{row}"><div class="notice-cell"><span>{{ row.trigger_type === 'manual' ? '人工复查' : '自动处理' }}</span><small v-if="row.operator_uid">操作员 #{{ row.operator_uid }}</small><small v-if="row.reason">{{ row.reason }}</small></div></template><template #result="{row}"><div class="notice-cell"><strong>{{ accountNoticeStatuses[row.status] || row.status || '状态待核对' }}</strong><span>{{ row.message || '未记录说明' }}</span><small v-if="row.provider_code !== '' && row.provider_code != null">返回码 {{ row.provider_code }}</small></div></template><template #time="{row}">{{ coopTime(row.create_time) }}</template></HsxTable></template>
            <template #footer><el-button @click="attemptsVisible=false">关闭</el-button></template>
        </HsxDrawer>
        <el-dialog v-model="retryVisible" title="重新检查通知" width="min(520px,94vw)" append-to-body :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy">
            <p>{{ selectedRetry.nickname || selectedRetry.member_no || `会员 #${selectedRetry.recipient_member_id || ''}` }} · {{ accountNoticeEvents[selectedRetry.event_key] }} · {{ accountNoticeChannels[selectedRetry.channel] }}</p>
            <el-alert title="只重新核对原通知，符合条件后排队发送；不改变业务与款项。" type="warning" :closable="false" show-icon />
            <el-alert v-if="selectedRetry.channel==='sms'" title="短信提交后可能计费，请先核对接收人。" type="warning" :closable="false" show-icon class="notice-banner" />
            <el-alert v-if="retryError" :title="retryError" type="error" :closable="false" show-icon class="notice-banner" />
            <el-form label-position="top" class="notice-retry-form" :disabled="busy || retryUncertain"><el-form-item label="复查原因" required><el-input v-model="retryReason" type="textarea" :rows="3" maxlength="200" show-word-limit placeholder="如：已核对渠道设置，重新检查原提醒" /></el-form-item><el-checkbox v-model="retryConfirmed">已核对接收人与原因，确认重新检查</el-checkbox></el-form>
            <template #footer><el-button :disabled="busy" @click="retryVisible=false">取消</el-button><el-button v-if="retryUncertain" type="primary" :loading="loading" @click="refreshRetry">刷新核对原记录</el-button><el-button v-else type="primary" :loading="busy" :disabled="!retryConfirmed || !retryReason.trim() || !retryAllowed(selectedRetry)" @click="submitRetry">确认重新检查</el-button></template>
        </el-dialog>
        <HsxDrawer v-model="templateVisible" :title="`${accountNoticeChannels[templateChannel]}模板`" :subtitle="accountNoticeEvents[templateEvent]" size="lg" show-footer confirm-text="应用到规则草稿" :confirm-disabled="!!templateError" @confirm="applyTemplate">
            <el-alert title="仅使用本站实际可用的官方模板；更换映射后需保存规则，再到牛云渠道页获取模板并开启。" type="info" show-icon :closable="false" class="notice-banner" />
            <WeappNoticeTemplatePicker v-if="templateChannel==='weapp' && canLibrary" :can-edit="true" :variables="variables" :scene-name="templateEventName" :request-library="getAccountNoticeTemplateLibrary" :field-pattern="ACCOUNT_NOTICE_FIELD_PATTERN" :variable-options="templateVariableOptions" :suggest-variable="templateVariableSuggestion" @select="applyLibrary" />
            <el-collapse><el-collapse-item title="在官方后台哪里获取？" name="source"><p v-if="templateChannel==='weapp'">在微信公众平台 → 小程序 → 订阅消息中核对公共模板编号、关键词与所属类目；也可使用上方官方模板库。这里填写数字公共编号，不是已生成的长模板 ID。</p><p v-else>在微信公众平台 → 服务号 → 模板消息中核对模板库编号与关键词；保存映射后，到牛云公众号消息页获取本站模板并开启。</p><p>字段须对应真实模板，不要套用其他小程序或公众号的私有模板 ID。</p></el-collapse-item></el-collapse>
            <el-form label-position="top" class="notice-template-form"><el-form-item :label="templateChannel==='weapp' ? '公共模板编号（tid）' : '公众号模板库编号（temp_key）'"><el-input v-if="templateChannel==='weapp'" v-model="templateDraft.tid" maxlength="20" placeholder="填写本站类目下的真实数字公共编号" /><el-input v-else v-model="templateDraft.temp_key" maxlength="64" placeholder="填写官方模板库编号" /></el-form-item><el-form-item v-if="templateChannel==='weapp'" label="已核验使用场景"><el-input v-model="templateDraft.scene_desc" maxlength="100" /></el-form-item>
                <div v-for="(field,index) in templateDraft.fields" :key="index" class="notice-template-field"><el-form-item label="官方字段名"><el-input v-model="field.label" maxlength="30" placeholder="填写官方字段名称" /></el-form-item><el-form-item label="官方关键词"><el-input v-model="field.keyword" maxlength="30" placeholder="从官方模板复制，不自动编造" /></el-form-item><el-form-item label="对应通知内容"><el-select v-model="field.variable" placeholder="选择匹配的内容"><el-option v-for="item in templateVariableOptions(field.keyword,variables)" :key="item.key" :label="item.label" :value="item.key" /></el-select></el-form-item><el-button link type="danger" @click="templateDraft.fields.splice(index,1)">删除</el-button></div>
                <el-button :disabled="templateDraft.fields.length>=10" @click="templateDraft.fields.push({label:'',keyword:'',variable:''})">添加真实字段</el-button><el-checkbox v-model="templateDraft.confirmed" class="notice-template-confirm">已逐项核对本站官方模板、关键词及对应内容</el-checkbox><p v-if="templateError" class="notice-danger" role="alert">{{ templateError }}</p>
            </el-form>
            <el-button type="danger" plain @click="clearTemplate">清空当前模板草稿</el-button>
        </HsxDrawer>
    </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { useRouter } from 'vue-router';
import useUserStore from '@/stores/modules/user';
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue';
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue';
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue';
import WeappNoticeTemplatePicker from '../components/WeappNoticeTemplatePicker.vue';
import { getAccountNoticeSettings, saveAccountNoticeSettings, getAccountNoticeTasks, getAccountNoticeAttempts, retryAccountNotice, getAccountNoticeTemplateLibrary } from '../../api/notice';
import { hasLeadPagePermission } from '../../composables/navigation';
import { coopError, coopTime } from '../../composables/cooperation';
import { ACCOUNT_NOTICE_FIELD_PATTERN, accountNoticeEvents, accountNoticeChannels, accountNoticeStatuses, accountNoticeStatusTone, accountRetryAllowed, parseAccountNoticeSettings, accountTemplatePayload, accountTemplateError, accountVariableOptions, suggestAccountVariable, noticeVariablesForEvent, normalizeResponseReminderMinutes, normalizeNoticeQuietHours, type NoticeRow } from '../../composables/accountNotice';
const user = useUserStore(), router = useRouter(), allowed = (key: string) => hasLeadPagePermission(user, key);
const canRead = computed(() => allowed('hsx_lead_notice_center_read')), canSettings = computed(() => canRead.value && allowed('hsx_lead_notice_settings')), canSave = computed(() => canSettings.value && allowed('hsx_lead_notice_center_save')), canRetry = computed(() => canRead.value && allowed('hsx_lead_notice_center_retry')), canAttempts = computed(() => canRead.value && allowed('hsx_lead_notice_attempts')), canLibrary = computed(() => canSettings.value && allowed('hsx_lead_notice_library'));
const activeTab = ref('records'), filters = reactive({ keyword: '', event_key: '', channel: '', status: '' }), query = reactive({ page: 1, limit: 20, keyword: '', event_key: '', channel: '', status: '', start_time: 0, end_time: 0 }), dateRange = ref<Date[] | null>(null);
const rows = ref<NoticeRow[]>([]), total = ref(0), loading = ref(false), error = ref(''), busy = ref(false), unverifiedIds = reactive(new Set<number>());
const columns = [{ prop: 'recipient_member_id', label: '通知对象', slot: 'recipient', minWidth: 180 }, { prop: 'event_key', label: '场景 / 账户', slot: 'event', minWidth: 170 }, { prop: 'change_amount', label: '业务摘要 / 资金变动', slot: 'amount', minWidth: 190 }, { prop: 'channel', label: '渠道', slot: 'channel', width: 120 }, { prop: 'status', label: '状态', slot: 'status', minWidth: 180 }, { prop: 'last_message', label: '结果 / 说明', slot: 'result', minWidth: 240 }, { prop: 'create_time', label: '事件时间 / 时效', slot: 'time', minWidth: 180 }, { prop: 'actions', label: '操作', slot: 'actions', width: 180, fixed: 'right' as const }];
const money = (value: unknown, signed = false) => value === undefined || value === null || value === '' || !Number.isFinite(Number(value)) ? '—' : `${signed && Number(value) > 0 ? '+' : ''}${Number(value).toFixed(2)} 元`;
const retryAllowed = (row: NoticeRow) => canRetry.value && accountRetryAllowed(row) && !unverifiedIds.has(Number(row.id));
const policy = ref<NoticeRow>({}), settingsReady = ref(false), settingsLoading = ref(false), settingsError = ref(''), saving = ref(false), templateChanges = reactive(new Set<string>());
const hasCooperationEvents = computed(() => (policy.value.events || []).some((event:NoticeRow) => String(event.event_key).startsWith('cooperation_')));
const responseNodesError = computed(() => { if(!hasCooperationEvents.value)return '';try{normalizeResponseReminderMinutes(policy.value.response_reminder_minutes);return ''}catch(error){return coopError(error,'请核对接单提醒节点')} });
const quietHoursError = computed(() => { if(!settingsReady.value)return '';try{normalizeNoticeQuietHours(policy.value.quiet_hours);return ''}catch(error){return coopError(error,'请核对免打扰时段')} });
const ttlMinutes = computed({ get: () => Number(policy.value.ttl_seconds) / 60, set: value => {
        policy.value.ttl_seconds = Number(value) * 60;
    } });
let generation = 0, settingsGeneration = 0, attemptGeneration = 0, disposed = false;
const identity = () => `${user.siteInfo?.site_id || ''}:${user.token || ''}`;
const current = (actor: string) => !disposed && actor === identity() && canRead.value;
async function loadTasks() {
    if (!canRead.value)
        return false;
    const actor = identity(), turn = ++generation;
    loading.value = true;
    error.value = '';
    try {
        const result = await getAccountNoticeTasks({ ...query });
        if (turn !== generation || !current(actor))
            return false;
        const page = result?.data;
        if (!page || !Array.isArray(page.data) || !Number.isSafeInteger(Number(page.total)) || Number(page.total) < 0)
            throw Error('通知记录返回不完整，请重新读取');
        rows.value = page.data;
        total.value = Number(page.total);
        rows.value.forEach(row => unverifiedIds.delete(Number(row.id)));
        return true;
    }
    catch (cause) {
        if (turn === generation && current(actor)) {
            rows.value = [];
            total.value = 0;
            error.value = coopError(cause, '通知记录读取失败，请重新加载');
        }
        return false;
    }
    finally {
        if (turn === generation)
            loading.value = false;
    }
}
function search() {
    const start = dateRange.value?.[0]?.getTime(), end = dateRange.value?.[1]?.getTime();
    if (dateRange.value && (!Number.isFinite(start) || !Number.isFinite(end) || Number(end) < Number(start))) {
        ElMessage.warning('请核对有效的起止时间');
        return;
    }
    Object.assign(query, filters, { page: 1, start_time: start ? Math.floor(start / 1000) : 0, end_time: end ? Math.floor(end / 1000) : 0 });
    return loadTasks();
}
function reset() {
    Object.assign(filters, { keyword: '', event_key: '', channel: '', status: '' });
    dateRange.value = null;
    return search();
}
function changePage(value: {
    page: number;
    limit: number;
}) {
    if (loading.value || busy.value)
        return;
    Object.assign(query, value);
    return loadTasks();
}
async function loadSettings() {
    if (!canSettings.value || saving.value)
        return;
    const actor = identity(), turn = ++settingsGeneration;
    settingsLoading.value = true;
    settingsError.value = '';
    try {
        const result = await getAccountNoticeSettings();
        if (turn !== settingsGeneration || !current(actor) || !canSettings.value)
            return;
        policy.value = parseAccountNoticeSettings(result.data);
        settingsReady.value = true;
        templateChanges.clear();
    }
    catch (cause) {
        if (turn === settingsGeneration && current(actor)) {
            settingsReady.value = false;
            policy.value = {};
            settingsError.value = coopError(cause, '通知规则读取失败，当前不能保存');
        }
    }
    finally {
        if (turn === settingsGeneration)
            settingsLoading.value = false;
    }
}
async function saveSettings() {
    if (!canSave.value || !settingsReady.value || settingsLoading.value || saving.value || settingsError.value || responseNodesError.value || quietHoursError.value || !policy.value.installed)
        return;
    const actor = identity();
    saving.value = true;
    settingsError.value = '';
    const snapshot = { enabled: policy.value.enabled, ttl_seconds: policy.value.ttl_seconds, max_attempts: policy.value.max_attempts, quiet_hours:normalizeNoticeQuietHours(policy.value.quiet_hours), ...(hasCooperationEvents.value ? {response_reminder_minutes:normalizeResponseReminderMinutes(policy.value.response_reminder_minutes)} : {}), events: policy.value.events.map((event: NoticeRow) => {
            const row: NoticeRow = { event_key: event.event_key, enabled: event.enabled, channels: { ...event.channels } };
            for (const channel of ['weapp', 'wechat'])
                if (templateChanges.has(`${event.event_key}:${channel}`))
                    row[channel] = event[channel];
            return row;
        }) };
    try {
        const result = await saveAccountNoticeSettings(snapshot);
        if (!current(actor) || !canSettings.value)
            return;
        policy.value = parseAccountNoticeSettings(result.data);
        templateChanges.clear();
        ElMessage.success('通知规则已保存；实际发送仍需渠道与用户授权');
    }
    catch (cause) {
        if (current(actor))
            settingsError.value = coopError(cause, '保存结果未核实，请重新读取规则后核对，勿反复保存');
    }
    finally {
        saving.value = false;
    }
}
function readiness(event: NoticeRow, channel: string): {
    label: string;
    tone: 'success' | 'warning' | 'info';
} {
    if (templateChanges.has(`${event.event_key}:${channel}`))
        return { label: '模板待保存', tone: 'warning' };
    const status = event.readiness[channel]?.status;
    if (status === 'pending_configuration')
        return { label: '待配置模板', tone: 'warning' };
    if (status !== 'ready')
        return { label: '牛云渠道未开启', tone: 'info' };
    return policy.value.enabled && event.enabled && event.channels[channel] ? { label: '配置就绪', tone: 'success' } : { label: '规则未开启', tone: 'info' };
}
function openChannel(channel: string) {
    const path = policy.value.channel_routes?.[channel];
    if (typeof path === 'string' && /^\/site\/(?:channel\/weapp\/message|channel\/wechat\/message|setting\/notice\/template)$/.test(path))
        void router.push(path);
}
function refreshCurrent() {
    if (activeTab.value === 'settings')
        return loadSettings();
    return loadTasks();
}
const attemptsVisible = ref(false), attemptsLoading = ref(false), attemptsReady = ref(false), attemptsError = ref(''), attempts = ref<NoticeRow[]>([]), selectedTask = ref<NoticeRow>({});
const attemptColumns = [{ prop: 'attempt_no', label: '次数', width: 65 }, { prop: 'trigger_type', label: '处理方式 / 原因', slot: 'trigger', minWidth: 150 }, { prop: 'status', label: '渠道结果', slot: 'result', minWidth: 250 }, { prop: 'create_time', label: '尝试时间', slot: 'time', minWidth: 170 }];
function openAttempts(row: NoticeRow) {
    if (!canAttempts.value)
        return;
    selectedTask.value = { ...row };
    attemptsVisible.value = true;
    void loadAttempts();
}
async function loadAttempts() {
    const actor = identity(), id = Number(selectedTask.value.id), turn = ++attemptGeneration;
    if (!canAttempts.value || !attemptsVisible.value || id <= 0)
        return;
    attemptsLoading.value = true;
    attemptsReady.value = false;
    attemptsError.value = '';
    attempts.value = [];
    try {
        const result = await getAccountNoticeAttempts(id);
        if (turn !== attemptGeneration || !current(actor) || !canAttempts.value || !attemptsVisible.value || Number(selectedTask.value.id) !== id)
            return;
        if (Number(result.data?.id) !== id || !Array.isArray(result.data.attempts))
            throw Error('通知尝试明细返回不完整');
        selectedTask.value = { ...selectedTask.value, ...result.data };
        attempts.value = result.data.attempts;
        attemptsReady.value = true;
    }
    catch (cause) {
        if (turn === attemptGeneration && current(actor))
            attemptsError.value = coopError(cause, '尝试明细读取失败');
    }
    finally {
        if (turn === attemptGeneration)
            attemptsLoading.value = false;
    }
}
const retryVisible = ref(false), selectedRetry = ref<NoticeRow>({}), retryReason = ref(''), retryConfirmed = ref(false), retryError = ref(''), retryUncertain = ref(false), retryKey = ref('');
function beginRetry(row: NoticeRow) {
    if (busy.value || !retryAllowed(row))
        return;
    selectedRetry.value = { ...row };
    retryReason.value = '';
    retryConfirmed.value = false;
    retryError.value = '';
    retryUncertain.value = false;
    retryKey.value = `notice_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 14)}`;
    retryVisible.value = true;
}
async function submitRetry() {
    const actor = identity(), id = Number(selectedRetry.value.id), reason = retryReason.value.trim();
    if (busy.value || !retryVisible.value || retryUncertain.value || !retryConfirmed.value || !reason || reason.length > 200 || !retryAllowed(selectedRetry.value))
        return;
    busy.value = true;
    retryError.value = '';
    try {
        const result = await retryAccountNotice(id, { confirm: true, reason, request_key: retryKey.value });
        if (!current(actor))
            return;
        if (Number(result.data?.id) !== id)
            throw Error('操作结果未完整返回');
        retryVisible.value = false;
        if (result.data.status === 'pending') ElMessage.success('原通知已重新检查并排队，请查看后续结果');
        else if (result.data.status === 'accepted') ElMessage.info('渠道已受理，请勿重复发送；不代表用户已读');
        else ElMessage.info('已核对原通知，请查看当前状态');
        await loadTasks();
    }
    catch (cause) {
        if (current(actor)) {
            unverifiedIds.add(id);
            retryUncertain.value = true;
            retryConfirmed.value = false;
            retryError.value = coopError(cause, '结果待核对，请刷新原记录，不要重复提交');
        }
    }
    finally {
        busy.value = false;
    }
}
async function refreshRetry() {
    if (await loadTasks())
        retryVisible.value = false;
}
const templateVisible = ref(false), templateEvent = ref(''), templateChannel = ref('weapp'), templateDraft = reactive<NoticeRow>({ tid: '', temp_key: '', scene_desc: '', fields: [], confirmed: false });
const templateContext = computed(() => (policy.value.events || []).find((event:NoticeRow) => event.event_key === templateEvent.value));
const templateEventName = computed(() => templateContext.value?.name || accountNoticeEvents[templateEvent.value] || '业务通知');
const variables = computed(() => Object.entries(noticeVariablesForEvent(templateContext.value)).map(([key,label])=>({key,label})));
const templateVariableOptions = (keyword:unknown, options:NoticeRow[]) => accountVariableOptions(keyword,options,templateContext.value);
const templateVariableSuggestion = (field:NoticeRow, options:NoticeRow[]) => suggestAccountVariable(field,options,templateContext.value);
const templateError = computed(() => accountTemplateError(templateChannel.value, templateDraft, templateContext.value));
function editTemplate(event: NoticeRow, channel: string) {
    if (!canSave.value || saving.value)
        return;
    templateEvent.value = event.event_key;
    templateChannel.value = channel;
    Object.assign(templateDraft, { tid: '', temp_key: '', scene_desc: '', fields: [], ...JSON.parse(JSON.stringify(event[channel] || {})), confirmed: false });
    templateVisible.value = true;
}
function applyLibrary(value: NoticeRow) {
    Object.assign(templateDraft, { tid: value.tid, scene_desc: value.scene_desc, fields: (value.fields || []).map((field: NoticeRow) => ({ ...field })), confirmed: false });
}
function clearTemplate() {
    Object.assign(templateDraft, { tid: '', temp_key: '', scene_desc: '', fields: [], confirmed: false });
}
function applyTemplate() {
    if (!canSave.value || templateError.value || !templateVisible.value)
        return;
    const event = policy.value.events.find((item: NoticeRow) => item.event_key === templateEvent.value);
    if (!event)
        return;
    event[templateChannel.value] = accountTemplatePayload(templateChannel.value, templateDraft);
    templateChanges.add(`${templateEvent.value}:${templateChannel.value}`);
    templateVisible.value = false;
    ElMessage.info('已加入规则草稿，请点击“保存通知规则”后生效');
}
watch(() => JSON.stringify([templateDraft.tid, templateDraft.temp_key, templateDraft.scene_desc, templateDraft.fields]), () => {
    templateDraft.confirmed = false;
}, { flush: 'sync' });
watch(activeTab, value => {
    if (value === 'settings' && canSettings.value && !settingsReady.value)
        void loadSettings();
});
watch(() => [identity(), canRead.value, canSettings.value, canAttempts.value, canRetry.value], () => {
    generation++;
    settingsGeneration++;
    attemptGeneration++;
    loading.value = false;
    settingsLoading.value = false;
    rows.value = [];
    total.value = 0;
    error.value = '';
    settingsReady.value = false;
    policy.value = {};
    settingsError.value = '';
    templateChanges.clear();
    attemptsVisible.value = false;
    templateVisible.value = false;
    retryVisible.value = false;
    selectedTask.value = {};
    selectedRetry.value = {};
    attempts.value = [];
    attemptsReady.value = false;
    attemptsError.value = '';
    retryReason.value = '';
    retryConfirmed.value = false;
    retryError.value = '';
    clearTemplate();
    unverifiedIds.clear();
    if (!canSettings.value)
        activeTab.value = 'records';
    if (canRead.value) {
        if (activeTab.value === 'settings')
            void loadSettings();
        else
            void loadTasks();
    }
}, { immediate: true });
watch(attemptsVisible, value => {
    if (!value) {
        attemptGeneration++;
        attemptsLoading.value = false;
        attempts.value = [];
        attemptsReady.value = false;
    }
});
onBeforeUnmount(() => {
    disposed = true;
    generation++;
    settingsGeneration++;
    attemptGeneration++;
});
</script>

<style scoped>
.notice-reminder-select{width:min(540px,100%)}
.account-notice-center{min-width:0}.notice-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.notice-head h2{font-size:20px;margin:0 0 7px;color:var(--el-text-color-primary)}.notice-head p,.notice-muted,.notice-footnote{font-size:12px;line-height:1.7;color:var(--el-text-color-secondary);margin:0}.notice-filters{display:grid;grid-template-columns:minmax(190px,1.5fr) repeat(3,minmax(125px,1fr));gap:0 14px}.notice-filters :deep(.el-form-item){min-width:0;margin-bottom:16px}.notice-filters :deep(.el-select){width:100%}.notice-filters__time{grid-column:1/4}.notice-filters__time :deep(.el-date-editor){width:100%;box-sizing:border-box}.notice-filter-actions{display:flex;align-items:end;justify-content:flex-end;gap:8px;margin-bottom:16px}.notice-filter-actions .el-button+.el-button{margin-left:0}.notice-surface{padding:16px;border:1px solid var(--el-border-color-lighter);border-radius:8px;background:var(--el-bg-color)}.notice-cell{display:flex;flex-direction:column;align-items:flex-start;gap:4px;font-size:13px;line-height:1.7;overflow-wrap:anywhere}.notice-cell strong{font-weight:500}.notice-cell small{font-size:12px;color:var(--el-text-color-secondary)}.notice-row-actions{display:flex;flex-wrap:wrap;gap:8px}.notice-row-actions .el-button+.el-button{margin:0}.notice-pagination{display:flex;justify-content:flex-end;margin-top:16px}.notice-footnote{margin:12px 0}.notice-banner{margin:12px 0}.notice-general{display:flex;flex-wrap:wrap;gap:16px 40px}.notice-help{margin-bottom:18px}.notice-help p,.notice-template-form p{line-height:1.8;font-size:13px}.notice-event-card{border:1px solid var(--el-border-color-lighter);border-radius:8px;background:var(--el-bg-color);padding:18px 20px;margin-bottom:16px}.notice-event-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:10px}.notice-event-head h3{font-size:15px;margin:0 0 4px}.notice-channel-row{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:52px;border-top:1px solid var(--el-border-color-extra-light)}.notice-channel-name,.notice-channel-actions{display:flex;align-items:center;gap:14px}.notice-channel-name strong{font-size:13px;font-weight:500}.notice-channel-actions .el-button+.el-button{margin:0}.notice-policy-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:20px 0}.notice-attempt-summary{display:flex;align-items:center;gap:12px;margin-bottom:20px;font-size:14px}.notice-retry-form,.notice-template-form{margin-top:20px}.notice-template-field{display:grid;grid-template-columns:minmax(110px,1fr) minmax(130px,1fr) minmax(160px,1.2fr) auto;gap:12px;align-items:center}.notice-template-field :deep(.el-select){width:100%}.notice-template-confirm{display:flex;height:auto;white-space:normal;line-height:1.7;margin-top:18px}.notice-danger{color:var(--el-color-danger);font-size:12px;line-height:1.7}@media(max-width:800px){.notice-filters{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.notice-filters__time{grid-column:1/-1}.notice-filter-actions{grid-column:1/-1}.notice-event-card{padding:14px}.notice-channel-row{align-items:flex-start;padding:12px 0}.notice-channel-name{flex-wrap:wrap;gap:8px}.notice-channel-actions{gap:8px;flex-wrap:wrap;justify-content:flex-end}.notice-template-field{grid-template-columns:1fr 1fr}.notice-policy-footer{align-items:flex-start}.notice-policy-footer>.el-button{flex:none}.notice-surface{padding:12px}}
</style>
