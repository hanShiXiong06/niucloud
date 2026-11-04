<template>
<CoopTaskSheet :show="show" title="ERP 库存同步" :subtitle="flow.subtitle" :busy="busy && !syncing" full @close="close">
<view v-if="flow.step !== 'authorize' && flow.step !== 'preview'" class="coop-form-section">
<view class="coop-section-title">当前接入<text>{{ connection.provider_name || schema?.name || '尚未接入' }}</text>
</view>
<view v-if="connection.last_sync_message" class="coop-muted">{{ connection.last_sync_message }}</view>
<view class="coop-detail-shop">
<text>最近同步</text>
<text>{{ coopDate(connection.last_sync_at) }}</text>
</view>
<view class="coop-detail-shop">
<text>{{ connection.enabled ? '同步状态' : '上次同步结果' }}</text>
<text>{{ syncStateLabel }}</text>
</view>
<view v-if="run.id" class="coop-sync-progress" :class="{ complete: run.complete || run.status === 'completed' }"><view><u-icon :name="run.complete || run.status === 'completed' ? 'checkmark-circle' : 'reload'" size="23" :color="run.complete || run.status === 'completed' ? '#4d8769' : '#6d85a2'" /><text>{{ progressText }}</text></view><text>{{ run.message || (run.status === 'completed' ? '库存快照已更新，仅供交付匹配参考' : '完整同步后更新门店货盘') }}</text><text v-if="run.status === 'running' || run.status === 'pending'">可先返回；已读资料会保留，稍后可继续同步。</text></view>
<view v-if="channelClosed" class="coop-inline-error">{{ connection.channel_message || '该 ERP 渠道已关闭，授权与手动、自动采集均暂停。账号和历史资料保留，请联系平台或选择其他开放渠道。' }}</view>
<view v-if="connection.connected && renewal.message" class="coop-task-rule">{{ renewal.message }}</view>
<view v-if="tokenOnly && !channelClosed" class="coop-inline-error">当前只保存了临时 Token，到期后无法自动续登。<text @tap="editAuthorization">补齐账号密码 ›</text>
</view>
<view v-if="setupStage" class="coop-task-rule">授权已保存，但同步设置还没完成。继续处理即可，不必重复验证账号。
</view>
<view v-if="connection.connected && !setupStage" class="coop-sync-settings">
<view class="coop-form-row">
<text>同步间隔</text>
<picker :range="intervals" range-key="label" :value="intervalIndex" :disabled="busy" @change="interval = intervals[Number($event.detail.value)].value">
<view>{{ intervals[intervalIndex].label }} ›</view>
</picker>
</view>
<view v-if="connection.enabled" class="coop-sync-toggle"><u-button size="small" :type="connection.enabled ? 'default' : 'primary'" :text="connection.enabled ? '关闭库存同步' : '启用库存同步'" :loading="busy" :disabled="busy || (channelClosed && !connection.enabled)" @click="toggleSync" /></view>
<view v-if="channelClosed" class="coop-muted">保留你的原同步开关；渠道恢复前不会采集。你仍可关闭自己的自动同步。</view>
</view>
<view class="coop-small-actions">
<view><u-button size="small" text="刷新状态" :disabled="busy" @click="refresh" /></view>
<view v-if="connection.enabled && !channelClosed && currentSyncStatus === 'completed'"><u-button size="small" text="重新同步库存" :loading="busy" :disabled="busy" @click="sync" /></view>
</view>
</view>
<view v-if="flow.step === 'manage'" class="coop-form-section"><view class="coop-section-title">授权账号<text @tap="editAuthorization">{{ channelClosed ? '更换 ERP 渠道' : tokenOnly ? '补齐账号密码' : '更换 / 重新授权' }}</text></view><view class="coop-muted">{{ connection.provider_name || '当前来源' }}</view></view>
<view v-if="flow.step === 'authorize'" class="coop-form-section">
<view class="coop-section-title">填写 ERP 账号</view>
<template v-if="flow.step === 'authorize'">
<view v-if="!selectedChannelAvailable" class="coop-task-rule">{{ providers.length ? '当前渠道已关闭，请选择其他开放的 ERP。原账号和历史数据不会因此删除。' : '本站当前没有开放 ERP 接入，请联系平台管理员。已有账号及历史数据保留。' }}</view>
<view class="coop-form-column">
<text>ERP 来源</text>
<picker :range="providers" range-key="name" :value="providerIndex" :disabled="busy || !providers.length" @change="changeProvider(providers[Number($event.detail.value)]?.key || '')">
<view class="coop-picker-field">{{ schema?.name || (channelClosed && provider === connection.provider ? (connection.provider_name || '原渠道') + '（已关闭）' : '选择 ERP') }} ›</view>
</picker>
</view>
<template v-if="selectedChannelAvailable">
<view v-for="field in credentialFields" :key="field.key" class="coop-form-column">
<text>{{ field.label }}</text>
<u-input v-model="credentials[field.key]" :password="!!field.secret" :disabled="busy" :maxlength="field.max_length || 512" :placeholder="`填写${field.label}`" />
</view>
<template v-if="interactive">
<view v-for="field in schema?.interactive_auth?.fields || []" :key="field.key" class="coop-form-column">
<text>{{ field.label }}</text>
<u-input v-model="authorization[field.key]" :password="!!field.secret" :disabled="busy" :maxlength="field.max_length || 4096" :placeholder="`填写${field.label}`" />
</view>
<view class="coop-captcha-row">
<image v-if="captchaImage" :src="captchaImage" mode="aspectFit" @tap="challenge" />
<view class="coop-captcha-action"><u-button size="small" :disabled="busy" :text="captchaImage ? '刷新验证码' : '获取验证码'" @click="challenge" /></view>
</view>
<view class="coop-form-column">
<text>图片验证码</text>
<u-input v-model="authorization.code" maxlength="4" placeholder="填写图片中的验证码" :disabled="busy" />
</view>
<picker v-if="companies.length" :range="companies" range-key="name" :value="companyIndex" :disabled="busy" @change="companyId = String(companies[Number($event.detail.value)]?.id || '')">
<view class="coop-picker-field">{{ companies[companyIndex]?.name || '选择所属公司' }} ›</view>
</picker>
</template>
<view v-if="scopeFields.length" class="coop-fold-title" @tap="scopeOpen = !scopeOpen">来源范围（通常自动识别）<u-icon :name="scopeOpen ? 'arrow-up' : 'arrow-down'" size="12" color="#89939f" />
</view>
<template v-if="scopeOpen">
<view v-for="field in scopeFields" :key="field.key" class="coop-form-column">
<text>{{ field.label }}</text>
<u-input v-model="scope[field.key]" :disabled="busy" :placeholder="field.required ? '必填' : '可留空自动核对'" />
</view>
</template>

</template>
</template>
</view>
<view v-if="preview" class="coop-form-section">
<view class="coop-section-title">核对本次来源</view>
<view class="coop-muted">验证成功，尚未保存授权。请确认下面的来源门店。</view>
<view class="coop-detail-shop">
<text>来源门店</text>
<text>{{ preview.identity?.name || preview.identity?.store_id || '尚未核对' }}</text>
</view>
<view class="coop-detail-shop">
<text>验证样本</text>
<text>{{ preview.first_page_count ?? '—' }} 条，启用后同步完整库存</text>
</view>
<picker v-if="preview.requires_store_selection" :range="preview.stores || []" range-key="name" :value="storeIndex" :disabled="busy" @change="scope.store_id = String(preview.stores[Number($event.detail.value)]?.store_id || '')">
<view class="coop-picker-field">{{ scope.store_id || '选择一个来源门店后重新验证' }} ›</view>
</picker>
<view class="coop-consent" @tap="!busy && (confirmed = !confirmed)">
<CoopSelectionMark :selected="confirmed" />
<text>我已核对来源门店，同意保存授权并开启自动库存同步。</text>
</view>
<view class="coop-muted">确认后会保存本次授权并开始首次同步；不会修改 ERP 库存。更换账号将影响后续库存匹配。</view>
</view>
<view v-if="error" class="coop-inline-error coop-form-error">{{ error }}</view>
<view v-if="flow.step === 'authorize' || flow.step === 'preview'" class="coop-connection-help"><u-collapse :border="false"><u-collapse-item title="授权与同步说明" name="authorization" icon="info-circle"><view class="coop-connection-help-body"><view>账号授权仅用于读取库存。请使用门店授权的账号；启用同步不会修改 ERP 价格或库存。</view><view>{{ renewalNote }}</view><view>验证失败不会更换原接入。需要扫码或子账号时，请使用已获授权的账号。</view></view></u-collapse-item></u-collapse></view>
<template #footer>
<view class="coop-connection-actions"><view v-if="flow.showSecondary" class="coop-connection-secondary"><u-button shape="circle" :text="flow.secondaryLabel" :disabled="busy && !syncing" @click="secondaryAction" /></view><view class="coop-connection-primary"><u-button shape="circle" type="primary" :text="flow.primaryLabel" :loading="busy" :disabled="primaryDisabled" @click="primaryAction" /></view></view>
</template>
</CoopTaskSheet>
</template>
<script setup lang="ts">
import { connectionActionState } from '../../utils/connection-actions'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { cooperationConnection, cooperationChallenge, cooperationProbe, cooperationActivate, cooperationSync, cooperationSyncInfo, cooperationSyncAdvance, cooperationConnectionSettings, coopDate, coopError, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
const props = defineProps<{ show: boolean; profile: CoopRow; providers: CoopRow[] }>()
const emit = defineEmits<{ (event: 'close' | 'changed'): void }>()
const intervals = [{ value: 15, label: '每15分钟' }, { value: 30, label: '每30分钟' }, { value: 60, label: '每小时（默认）' }, { value: 120, label: '每2小时' }, { value: 360, label: '每6小时' }, { value: 1440, label: '每天' }], interval = ref(60), intervalIndex = computed(() => Math.max(0, intervals.findIndex(item => item.value === interval.value)))
const connection = ref<CoopRow>({}), run = ref<CoopRow>({}), provider = ref(''), credentials = reactive<CoopRow>({}), authorization = reactive<CoopRow>({}), scope = reactive<CoopRow>({}), busy = ref(false), error = ref(''), editing = ref(false), scopeOpen = ref(false), captchaImage = ref(''), challengeToken = ref(''), challengeExpires = ref(0), companies = ref<CoopRow[]>([]), companyId = ref(''), preview = ref<CoopRow | null>(null), probeToken = ref(''), confirmed = ref(false)
const schema = computed(() => props.providers.find(item => item.key === provider.value)), providerIndex = computed(() => Math.max(0, props.providers.findIndex(item => item.key === provider.value))), interactive = computed(() => schema.value?.interactive_auth?.type === 'image_captcha')
const defaultMode = computed(() => schema.value?.auth_modes?.find((item: CoopRow) => item.key === 'password') || schema.value?.auth_modes?.[0]), credentialFields = computed<CoopRow[]>(() => interactive.value ? (schema.value?.fields || []).filter((field: CoopRow) => (schema.value?.interactive_auth?.credential_fields || []).includes(field.key)) : defaultMode.value?.fields || schema.value?.fields || []), scopeFields = computed<CoopRow[]>(() => defaultMode.value?.scope_fields || schema.value?.scope_fields || [])
const companyIndex = computed(() => Math.max(0, companies.value.findIndex(item => String(item.id) === companyId.value))), storeIndex = computed(() => Math.max(0, (preview.value?.stores || []).findIndex((item: CoopRow) => String(item.store_id) === String(scope.store_id))))
const setupStage = ref(''), setupPublishedBaseline = ref(0), renewal = computed(() => connection.value.auth_renewal || {}), renewalPolicy = computed(() => defaultMode.value?.renewal || schema.value?.renewal || {})
const syncing = ref(false)
const tokenOnly = computed(() => renewal.value.status === 'manual_token' || connection.value.credentials?.auth_mode === 'token')
const channelClosed = computed(() => connection.value.channel_enabled === false)
const selectedChannelAvailable = computed(() => !!schema.value && schema.value.enabled !== false && !(channelClosed.value && provider.value === connection.value.provider))
const syncStateLabel = computed(() => channelClosed.value || run.value.status === 'paused' || run.value.channel_enabled === false ? '渠道已关闭 · 采集暂停' : ({ idle: '尚未同步', pending: '等待同步', running: '正在同步', completed: '同步完成', failed: '同步未完成', cancelled: '任务已取消', interrupted: '同步已中断，可重新同步' } as Record<string, string>)[run.value.status || connection.value.last_sync_status] || '尚未同步')
const progressText = computed(() => run.value.complete || run.value.status === 'completed' ? `完整同步 ${Number(run.value.record_count || 0)} 条库存` : run.value.progress_known && run.value.total_records != null ? `已同步 ${Number(run.value.record_count || 0)} / ${Number(run.value.total_records)} 条` : `已同步 ${Number(run.value.record_count || 0)} 条，继续读取中`)
const currentSyncStatus = computed(() => run.value.status || connection.value.last_sync_status || 'idle')
const flow = computed(() => connectionActionState({ connected: !!connection.value.connected, editing: editing.value, hasPreview: !!preview.value, setupStage: setupStage.value, channelClosed: channelClosed.value, enabled: !!connection.value.enabled, syncStatus: currentSyncStatus.value }))
const primaryDisabled = computed(() => busy.value || (flow.value.primary === 'verify' && (!selectedChannelAvailable.value || credentialFields.value.some(field => field.required && !String(credentials[field.key] || '').trim()))) || (flow.value.primary === 'activate' && (!selectedChannelAvailable.value || !confirmed.value || !probeToken.value || preview.value?.can_activate === false || !!preview.value?.requires_store_selection)) || (flow.value.primary === 'resume' && channelClosed.value))
function secondaryAction() { if (busy.value && !syncing.value) return; if (flow.value.step === 'preview') { preview.value = null; probeToken.value = ''; confirmed.value = false; editing.value = true; error.value = '' } else close() }
async function primaryAction() { if (primaryDisabled.value) return; if (flow.value.primary === 'verify') await probe(); else if (flow.value.primary === 'activate') await activate(); else if (flow.value.primary === 'resume') await resumeSetup(); else if (flow.value.primary === 'sync') await sync(); else if (flow.value.primary === 'enable') await toggleSync(); else close() }
const renewalNote = computed(() => interactive.value ? '本渠道需验证码登录；本次授权不等于后台自动续登，到期后需重新验证。' : renewalPolicy.value.automatic === true ? '账号密码仅在服务器加密保存。授权过期后自动重新登录并继续同步，无需你手动填写 Token。' : '请先核对本渠道的登录方式；仅开启同步不代表可以自动续登。')
let sequence = 0, captchaFile = '', driving = false, pollTimer: ReturnType<typeof setTimeout> | undefined
function stopPolling() { if (pollTimer && typeof clearTimeout === 'function') clearTimeout(pollTimer); pollTimer = undefined }
function schedulePoll() { stopPolling(); if (!props.show || !connection.value.connected || typeof setTimeout !== 'function') return; pollTimer = setTimeout(() => { if (!props.show) return; if (!busy.value) { if (driving && ['pending', 'running'].includes(run.value.status)) void guard(advancePages); else void pollState() } else schedulePoll() }, error.value ? 10000 : 2500) }
async function pollState() { const token = sequence; try { await readState() } catch (cause) { if (props.show && token === sequence && !error.value) error.value = coopError(cause, '同步状态刷新失败，可点击刷新重试') } finally { schedulePoll() } }
function clearCaptchaFile() {
    // #ifdef MP-WEIXIN
    if (captchaFile) { try { uni.getFileSystemManager().unlinkSync(captchaFile) } catch (_) {} }; captchaFile = ''
    // #endif
}
function displayCaptcha(value: string) {
    clearCaptchaFile()
    // #ifdef MP-WEIXIN
    const match = /^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$/.exec(value)
    if (match) { const runtime = (globalThis as any).wx; if (!runtime?.env?.USER_DATA_PATH) throw new Error('当前环境无法展示验证码，请重新进入小程序'); captchaFile = runtime.env.USER_DATA_PATH + '/hsx-lead-captcha-' + Date.now() + '.' + match[1]; uni.getFileSystemManager().writeFileSync(captchaFile, match[2], 'base64'); return captchaFile }
    // #endif
    return value
}
function clearSecret() { Object.keys(credentials).forEach(key => delete credentials[key]); Object.keys(authorization).forEach(key => delete authorization[key]); challengeToken.value = ''; clearCaptchaFile(); captchaImage.value = ''; challengeExpires.value = 0; companies.value = []; companyId.value = '' }
function changeProvider(value: string) { if (busy.value || !props.providers.some(item => item.key === value && item.enabled !== false)) return; provider.value = value; clearSecret(); Object.keys(scope).forEach(key => delete scope[key]); preview.value = null; probeToken.value = ''; confirmed.value = false; setupStage.value = ''; error.value = '' }
function editAuthorization() { if (busy.value) return; editing.value = true; provider.value = connection.value.provider || provider.value; clearSecret(); Object.keys(scope).forEach(key => delete scope[key]); Object.assign(credentials, connection.value.credentials || {}); delete credentials.auth_mode; delete credentials.access_token; delete credentials.password; preview.value = null; probeToken.value = ''; confirmed.value = false; setupStage.value = ''; error.value = '' }
async function guard(work: () => Promise<void>) { if (busy.value) return; busy.value = true; error.value = ''; const token = ++sequence; stopPolling(); try { await work() } catch (cause) { if (token === sequence && props.show) { driving = false; error.value = coopError(cause, '处理未完成，请刷新核对') } } finally { if (token === sequence) { busy.value = false; syncing.value = false; schedulePoll() } } }
async function readState() { const token = sequence, result: any = await cooperationConnection(); if (!props.show || token !== sequence) return; connection.value = result.data || {}; interval.value = Number(connection.value.interval_minutes || 60); const progress = connection.value.sync_progress; if (progress?.run_id) run.value = { ...progress, id: Number(progress.run_id) }; else if (Number(connection.value.active_run_id)) { const info: any = await cooperationSyncInfo(Number(connection.value.active_run_id)); if (props.show && token === sequence) run.value = info.data || {} } else run.value = {}; if (!['running', 'pending'].includes(run.value.status)) driving = false }
async function refresh() { await guard(readState) }
async function advancePages() {
    const token = sequence; syncing.value = true
    for (let page = 0; page < 8 && props.show && token === sequence && ['pending', 'running'].includes(run.value.status) && run.value.can_advance !== false; page++) {
        assertCurrentChannel(); const before = Number(run.value.page_count || 0), result: any = await cooperationSyncAdvance(Number(run.value.id))
        if (!props.show || token !== sequence) return
        run.value = result.data?.run || result.data || {}
        if (Number(run.value.page_count || 0) === before && ['pending', 'running'].includes(run.value.status)) break
    }
    if (!props.show || token !== sequence) return
    await readState(); emit('changed')
}
function payload() { return { provider: provider.value, credentials: { ...credentials, ...(defaultMode.value ? { auth_mode: defaultMode.value.key } : {}) }, scope: { ...scope } } }
function assertSelectedChannel() { if (!selectedChannelAvailable.value) throw new Error('当前 ERP 渠道未开放，请选择其他开放渠道或联系平台') }
function assertCurrentChannel() { if (channelClosed.value) throw new Error(connection.value.channel_message || 'ERP渠道已关闭，采集暂停；账号与历史数据保留') }
async function challenge() { await guard(async () => { assertSelectedChannel(); const result: any = await cooperationChallenge(payload()); if (!props.show) return; challengeToken.value = result.data?.challenge_token || ''; captchaImage.value = displayCaptcha(result.data?.captcha_image || ''); challengeExpires.value = Number(result.data?.expires_at || 0); authorization.code = ''; companies.value = [] }) }
async function probe() { await guard(async () => { assertSelectedChannel(); for (const field of credentialFields.value) if (field.required && !String(credentials[field.key] || '').trim()) throw new Error(`请填写${field.label}`); const input: CoopRow = payload(); if (interactive.value) { if (!challengeToken.value || challengeExpires.value * 1000 <= Date.now()) throw new Error('请重新获取有效验证码'); input.authorization = companies.value.length ? { challenge_token: challengeToken.value, company_id: companyId.value } : { ...authorization, challenge_token: challengeToken.value } }; const result: any = await cooperationProbe(input); if (!props.show) return; const data = result.data || {}; if (data.status === 'choose_company' || data.authorization_required) { companies.value = data.companies || data.public?.companies || []; challengeToken.value = data.challenge_token || data.public?.challenge_token || ''; companyId.value = String(companies.value[0]?.id || ''); return }; preview.value = data.preview || null; probeToken.value = data.probe_token || ''; confirmed.value = false; authorization.password = ''; authorization.code = '' }) }
async function continueSetupWork() {
    assertCurrentChannel()
    if (setupStage.value === 'enable_sync') {
        try { const result: any = await cooperationConnectionSettings({ enabled: true, interval_minutes: interval.value }); if (!props.show) return; connection.value = result.data || {}; setupStage.value = 'start_sync'; emit('changed') }
        catch (cause) { throw new Error('授权已保存，自动同步开启结果未确认。可刷新核对或点击“继续开启自动同步”，无需重复授权。' + coopError(cause, '')) }
    }
    if (setupStage.value === 'start_sync') {
        try {
            const current: any = await cooperationConnection(); if (!props.show) return; connection.value = current.data || {}; assertCurrentChannel()
            const active = Number(connection.value.active_run_id || 0), published = Number(connection.value.published_run_id || 0)
            const existing = active || (published > 0 && published !== setupPublishedBaseline.value ? published : 0)
            const result: any = existing ? await cooperationSyncInfo(existing) : await cooperationSync()
            if (!props.show) return; const data = result.data || {}; run.value = data.run || data
            if (!run.value.id && Number(data.run_id)) { const info: any = await cooperationSyncInfo(Number(data.run_id)); if (!props.show) return; run.value = info.data || {} }
            setupStage.value = ''; driving = true; await advancePages(); emit('changed'); uni.showToast({ title: run.value.status === 'completed' ? '库存已完整同步' : '同步已开始，可查看当前进度', icon: 'none' })
        } catch (cause) { throw new Error('授权和自动同步设置已保存，首次采集结果待确认。点击“继续首次同步”会先核对已有任务，不重复授权。' + coopError(cause, '')) }
    }
}
async function resumeSetup() { await guard(continueSetupWork) }
async function activate() {
    if (busy.value || !confirmed.value || !probeToken.value) return
    if (!selectedChannelAvailable.value) { error.value = '当前 ERP 渠道已关闭，未提交授权，请刷新或更换开放渠道'; return }
    const token = probeToken.value, store = scope.store_id || ''; probeToken.value = ''; preview.value = null; confirmed.value = false
    await guard(async () => {
        let result: any
        try { result = await cooperationActivate({ probe_token: token, confirm_switch: true, store_id: store }) }
        catch (_) { clearSecret(); editing.value = true; throw new Error('授权保存结果未确认，请先刷新状态；如需重新提交，请重新验证。不会重复使用本次授权凭证。') }
        if (!props.show) return; connection.value = result.data || {}; setupPublishedBaseline.value = Number(connection.value.published_run_id || 0); clearSecret(); editing.value = false; setupStage.value = 'enable_sync'; emit('changed')
        await continueSetupWork()
    })
}
async function toggleSync() { const next = !connection.value.enabled; if (!next) { const confirmation: any = await new Promise(resolve => uni.showModal({ title: '暂停库存同步？', content: '暂停后不再自动更新 ERP 库存，旧快照将不参与后续匹配；账号和历史资料仍保留。', confirmText: '确认暂停', cancelText: '继续同步', success: resolve, fail: () => resolve({ confirm: false }) })); if (!confirmation.confirm || !props.show) return }; await guard(async () => { if (next) assertCurrentChannel(); const result: any = await cooperationConnectionSettings({ enabled: next, interval_minutes: interval.value }); if (!props.show) return; connection.value = result.data || {}; emit('changed'); uni.showToast({ title: next ? '自动同步已启用' : '库存同步已关闭，快照不再参与匹配', icon: 'none' }) }) }
async function sync() { await guard(async () => { assertCurrentChannel(); await readState(); if (!props.show) return; if (!run.value.id || !['running', 'pending'].includes(run.value.status)) { const result: any = await cooperationSync(); if (!props.show) return; run.value = result.data?.run || result.data || {} }; driving = true; await advancePages() }) }
function close() { if (busy.value && !syncing.value) return; sequence++; driving = false; stopPolling(); clearSecret(); preview.value = null; emit('close') }
watch(() => props.show, show => { if (show) { provider.value = props.profile.connection?.provider || props.profile.provider_requested || props.providers[0]?.key || ''; connection.value = props.profile.connection || {}; preview.value = null; run.value = {}; editing.value = !connection.value.connected; void refresh() } else { sequence++; driving = false; stopPolling(); busy.value = false; syncing.value = false; run.value = {}; clearSecret() } })
watch(() => JSON.stringify(credentials), () => { preview.value = null; probeToken.value = ''; confirmed.value = false })
watch(() => JSON.stringify(scope), () => { preview.value = null; probeToken.value = ''; confirmed.value = false })
onBeforeUnmount(() => { sequence++; driving = false; stopPolling(); clearSecret() })
</script>
<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-muted;
@include hsx-coop-detail-shop;
@include hsx-coop-form-error;
@include hsx-coop-task-rule;
@include hsx-coop-form-row;
@include hsx-coop-form-column;
@include hsx-coop-picker-field;
@include hsx-coop-fold-title;
@include hsx-coop-consent;
.coop-sync-settings { border-top: 1rpx solid #edf0f3; margin-top: 20rpx; padding-top: 10rpx; }
.coop-small-actions { display: flex; gap: 20rpx; margin-top: 25rpx; }
.coop-captcha-row { display: flex; align-items: center; gap: 22rpx; margin: 20rpx 0; }
.coop-captcha-row>image { width: 220rpx; height: 90rpx; background: #f2f5f8; flex: none; }
.coop-sync-toggle { margin-top: 18rpx; }
.coop-small-actions>view { flex: 1; min-width: 0; }
.coop-captcha-action { flex: 1; min-width: 0; }
.coop-sync-progress { background: #edf3fa; border-radius: 10rpx; padding: 22rpx; margin-top: 20rpx; color: #637a95; font-size: 23rpx; line-height: 1.8; }.coop-sync-progress.complete { color: #52765e; background: #edf7ef; }.coop-sync-progress>view { display: flex; align-items: center; gap: 12rpx; font-size: 28rpx; font-weight: 600; margin-bottom: 8rpx; }.coop-sync-progress>text { display: block; }
.coop-connection-actions { display: flex; align-items: center; gap: 20rpx; width: 100%; }.coop-connection-secondary { flex: 0 0 28%; min-width: 0; }.coop-connection-primary { flex: 1; min-width: 0; }.coop-connection-help { margin: 20rpx 24rpx; background: #fff; border-radius: 12rpx; }.coop-connection-help-body { font-size: 23rpx; color: #8490a0; line-height: 1.8; padding: 0 8rpx 18rpx; }.coop-connection-help-body>view+view { margin-top: 12rpx; }</style>
