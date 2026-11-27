<template>
    <CoopTaskSheet
        :page="page"
        :show="show"
        title="ERP 库存同步"
        :subtitle="flow.subtitle"
        :busy="busy && !syncing && !smsMode"
        full
        @close="close"
    >
        <template v-if="flow.step !== 'authorize' && flow.step !== 'preview'">
            <view class="coop-connection-overview" :class="'is-' + summaryTone">
                <view class="coop-connection-provider"
                    ><view class="coop-connection-provider__icon"><u-icon name="grid" size="23" color="#557fb4" /></view
                    ><view
                        ><text>{{ connectionProviderName }}</text
                        ><text>{{ authorizationLabel }}</text></view
                    ><text
                        class="coop-connection-auto"
                        :class="{ 'is-paused': channelClosed || !connection.enabled }"
                        >{{ channelClosed ? '渠道暂停' : connection.enabled ? '自动同步已开启' : '自动同步已关闭' }}</text
                    ></view
                >
                <view class="coop-connection-status"
                    ><u-icon :name="statusIcon" size="25" :color="statusColor" /><text>{{ syncStateLabel }}</text></view
                >
                <view v-if="!syncAttention" class="coop-connection-summary">{{ statusDescription }}</view>
                <view
                    v-if="run.id && !channelClosed && !syncAttention && !connection.requires_sync_restart"
                    class="coop-sync-progress"
                    :class="{ complete: currentSyncStatus === 'completed' }"
                    ><view
                        ><text>{{ progressText }}</text
                        ><text v-if="knownProgressPercent !== null">{{ knownProgressPercent }}%</text></view
                    ><view v-if="knownProgressPercent !== null" class="coop-connection-progress-track"
                        ><view :style="{ width: knownProgressPercent + '%' }" /></view
                ></view>
                <view class="coop-connection-metrics"
                    ><view
                        ><text>最近更新货盘</text><text>{{ coopDate(connection.last_sync_at) }}</text></view
                    ><view
                        ><text>同步频率</text><text>{{ savedIntervalLabel }}</text></view
                    ></view
                >
                <view v-if="channelClosed" class="coop-connection-attention"
                    >该渠道已被平台暂停，暂时不能同步；原账号和历史记录保留。</view
                >
                <view v-else-if="syncAttention" class="coop-connection-attention" role="alert"
                    ><text>{{ syncAttention.message }}</text></view
                >
                <view
                    v-else-if="tokenOnly && !['pending','running'].includes(currentSyncStatus)"
                    class="coop-connection-attention"
                    ><text>临时授权到期后不能自动续登，请补齐账号密码。</text
                    ><text class="coop-connection-link" @tap="editAuthorization">补齐账号密码 ›</text></view
                >
                <view v-if="setupStage && !syncAttention && !channelClosed" class="coop-connection-attention"
                    >授权已保存，按底部按钮继续完成同步设置，无需重新填写账号。</view
                >
                <view class="coop-small-actions"
                    ><view
                        ><u-button
                            :custom-style="cooperationButtonStyle"
                            text="刷新状态"
                            :disabled="busy"
                            @click="refresh" /></view
                    ><view
                        v-if="connection.enabled && !channelClosed && !syncAttention && currentSyncStatus === 'completed'"
                        ><u-button
                            :custom-style="cooperationButtonStyle"
                            text="重新同步"
                            :loading="busy"
                            :disabled="busy"
                            @click="sync" /></view
                ></view>
            </view>

            <view class="coop-connection-history">
                <view class="coop-connection-heading"
                    ><text>同步记录</text><text>{{ historyCaption }}</text></view
                >
                <view v-if="!stateLoaded && busy" class="coop-connection-empty"
                    ><u-loading-icon size="21" /><text>正在读取同步记录</text></view
                >
                <template v-else-if="historyRows.length">
                    <view v-for="item in historyRows" :key="item.id" class="coop-sync-log">
                        <button
                            class="coop-sync-log__toggle"
                            :aria-expanded="expandedRunId === Number(item.id)"
                            @tap="expandedRunId = expandedRunId === Number(item.id) ? 0 : Number(item.id)"
                            ><view class="coop-sync-log__dot" :class="'is-' + logTone(item)" /><view
                                class="coop-sync-log__main"
                                ><view
                                    ><text>{{ logStatus(item) }}</text
                                    ><text v-if="item.is_current_snapshot" class="coop-sync-log__current"
                                        >当前货盘</text
                                    ></view
                                ><text>{{ coopDate(item.started_at || item.create_time) }}</text></view
                            ><view class="coop-sync-log__count"
                                ><text>{{ inventoryCount(item) }}</text
                                ><text>{{ item.trigger_label || triggerLabel(item.trigger_type) }}</text></view
                            ><u-icon
                                :name="expandedRunId === Number(item.id) ? 'arrow-up' : 'arrow-down'"
                                size="12"
                                color="#8b99aa"
                        /></button>
                        <view
                            v-if="['failed','interrupted','cancelled','paused'].includes(String(item.status)) && expandedRunId !== Number(item.id)"
                            class="coop-sync-log__issue"
                            >{{ item.message || item.error_message || '本次同步未完成，展开查看详情' }}</view
                        >
                        <view v-if="expandedRunId === Number(item.id)" class="coop-sync-log__detail"
                            ><view
                                ><text>开始时间</text
                                ><text>{{ coopDate(item.started_at || item.create_time) }}</text></view
                            ><view
                                ><text>结束时间</text
                                ><text
                                    >{{ item.finished_at ? coopDate(item.finished_at) : ['pending','running','paused'].includes(String(item.status)) ? '尚未结束' : '尚未记录' }}</text
                                ></view
                            ><view
                                ><text>整理库存</text
                                ><text
                                    >{{ inventoryCount(item)
                                    }}<text v-if="item.page_count != null">
                                        · {{ Number(item.page_count) }} 页</text
                                    ></text
                                ></view
                            ><view
                                class="coop-sync-log__message"
                                >{{ item.message || item.error_message || '这轮同步没有补充说明。' }}</view
                            ></view
                        >
                    </view>
                </template>
                <view v-else class="coop-connection-empty"
                    ><u-icon
                        name="clock"
                        size="25"
                        color="#a0aec0"
                    /><text>{{ historyAvailable ? '还没有同步记录' : '同步记录暂不可用，请刷新后重试' }}</text></view
                >
                <button
                    v-if="recentRunRows.length > 3"
                    class="coop-sync-log__more"
                    @tap="historyExpanded = !historyExpanded"
                    >{{ historyExpanded ? '收起记录' : '展开其余 ' + (recentRunRows.length - 3) + ' 次记录'
                    }}<u-icon :name="historyExpanded ? 'arrow-up' : 'arrow-down'" size="12" color="#7890ad"
                /></button>
            </view>

            <view class="coop-connection-settings"
                ><u-collapse :border="false"
                    ><u-collapse-item title="同步设置" name="sync-settings" icon="setting"
                        ><view class="coop-connection-settings__body"
                            ><view class="coop-form-row"
                                ><text>同步间隔</text
                                ><picker
                                    :range="intervals"
                                    range-key="label"
                                    :value="intervalIndex"
                                    :disabled="busy"
                                    @change="selectInterval(Number($event.detail.value))"
                                    ><view>{{ intervals[intervalIndex].label }} ›</view></picker
                                ></view
                            ><view v-if="intervalDirty" class="coop-sync-toggle"
                                ><u-button
                                    :custom-style="cooperationButtonStyle"
                                    text="保存设置"
                                    :disabled="busy"
                                    :loading="busy"
                                    @click="saveSettings" /></view
                            ><view class="coop-detail-shop"
                                ><text>自动同步</text
                                ><text
                                    >{{ channelClosed ? '平台已暂停渠道' : connection.enabled ? '已开启' : '已关闭' }}</text
                                ></view
                            ><view
                                v-if="connection.enabled && connection.next_sync_at && !channelClosed"
                                class="coop-detail-shop"
                                ><text>计划同步时间</text><text>{{ coopDate(connection.next_sync_at) }}</text></view
                            ><view v-if="connection.enabled && !setupStage" class="coop-sync-toggle"
                                ><u-button
                                    :custom-style="cooperationButtonStyle"
                                    text="暂停自动同步"
                                    :loading="busy"
                                    :disabled="busy"
                                    @click="toggleSync" /></view
                            ><view v-if="channelClosed" class="coop-muted"
                                >平台恢复渠道后才能继续读取库存；也可以保留账号并关闭本站自动同步。</view
                            ></view
                        ></u-collapse-item
                    ><u-collapse-item title="授权账号" name="authorization-settings" icon="account"
                        ><view class="coop-connection-settings__body"
                            ><view class="coop-detail-shop"
                                ><text>账号授权</text><text>{{ authorizationLabel }}</text></view
                            ><view v-if="renewal.message" class="coop-muted">{{ renewal.message }}</view
                            ><view class="coop-sync-toggle"
                                ><u-button
                                    :custom-style="cooperationButtonStyle"
                                    :text="channelClosed ? '更换 ERP 渠道' : tokenOnly ? '补齐账号密码' : '更换或重新验证账号'"
                                    :disabled="busy"
                                    @click="editAuthorization" /></view></view></u-collapse-item
                    ><u-collapse-item title="同步说明" name="sync-help" icon="info-circle"
                        ><view class="coop-connection-help-body"
                            ><view>只读取 ERP 库存，不修改来源系统的价格或库存。</view
                            ><view>一轮完整同步结束后才更新货盘；未完成时保留上一批完整记录。</view
                            ><view>记录中的数量是本轮整理的库存资料，不等于当前可售设备数。</view></view
                        ></u-collapse-item
                    ></u-collapse
                ></view
            >
        </template>
        <view v-if="flow.step === 'authorize'" class="coop-form-section">
            <view
                class="coop-section-title"
                >{{ selectedChannelAvailable ? '填写' + selectedProviderName + '账号' : '填写 ERP 账号' }}</view
            >
            <template v-if="flow.step === 'authorize'">
                <view
                    v-if="!selectedChannelAvailable"
                    class="coop-task-rule"
                    >{{ providers.length ? '当前渠道已关闭，请选择其他开放的 ERP。原账号和历史数据不会因此删除。' : '本站当前没有开放 ERP 接入，请联系平台管理员。已有账号及历史数据保留。' }}</view
                >
                <view class="coop-form-column">
                    <text>ERP 来源</text>
                    <picker
                        :range="providerOptions"
                        range-key="name"
                        :value="providerIndex"
                        :disabled="busy || !providers.length"
                        @change="changeProvider(providerOptions[Number($event.detail.value)]?.key || '')"
                    >
                        <view class="coop-picker-field"
                            >{{ schema ? selectedProviderName : (channelClosed && provider === connection.provider ? connectionProviderName + '（已关闭）' : '选择 ERP') }}
                            ›</view
                        >
                    </picker>
                </view>
                <template v-if="selectedChannelAvailable">
                    <view v-for="field in credentialFields" :key="field.key" class="coop-form-column">
                        <text>{{ field.label }}</text>
                        <u-input
                            v-model="credentials[field.key]"
                            :custom-style="cooperationInputStyle"
                            :password="!!field.secret"
                            :disabled="busy"
                            :maxlength="field.max_length || 512"
                            :placeholder="`填写${field.label}`"
                        />
                    </view>
                    <template v-if="interactive">
                        <view
                            v-for="field in schema?.interactive_auth?.fields || []"
                            :key="field.key"
                            class="coop-form-column"
                        >
                            <text>{{ field.label }}</text>
                            <u-input
                                v-model="authorization[field.key]"
                                :custom-style="cooperationInputStyle"
                                :password="!!field.secret"
                                :disabled="busy"
                                :maxlength="field.max_length || 4096"
                                :placeholder="`填写${field.label}`"
                            />
                        </view>
                        <view v-if="smsMode" class="coop-form-column">
                            <text>短信验证码</text>
                            <view class="coop-captcha-row coop-sms-row">
                                <view class="coop-sms-input"
                                    ><u-input
                                        v-model="authorization.code"
                                        :custom-style="cooperationInputStyle"
                                        type="number"
                                        :maxlength="codeLength"
                                        :placeholder="`填写 ${codeLength} 位数字验证码`"
                                        :disabled="busy || !challengeToken"
                                /></view>
                                <view class="coop-sms-action"
                                    ><u-button
                                        :custom-style="cooperationButtonStyle"
                                        :disabled="busy || smsRetrySeconds > 0 || !validSmsMobile"
                                        :loading="challengeSending"
                                        :text="smsRetrySeconds > 0 ? smsRetrySeconds + 's 后重发' : challengeToken ? '重新获取' : '获取验证码'"
                                        @click="challenge"
                                /></view>
                            </view>
                            <view v-if="challengeToken" class="coop-muted"
                                >验证码已发送至 {{ smsMobileMask }}，请在有效期内完成验证。</view
                            >
                            <view v-else-if="smsRetrySeconds > 0" class="coop-muted"
                                >请求已提交，请稍后再试；系统不会自动重发短信。</view
                            >
                        </view>
                        <template v-else>
                            <view class="coop-captcha-row">
                                <image v-if="captchaImage" :src="captchaImage" mode="aspectFit" @tap="challenge" />
                                <view class="coop-captcha-action"
                                    ><u-button
                                        :custom-style="cooperationButtonStyle"
                                        :disabled="busy"
                                        :text="captchaImage ? '刷新验证码' : '获取验证码'"
                                        @click="challenge"
                                /></view>
                            </view>
                            <view class="coop-form-column">
                                <text>图片验证码</text>
                                <u-input
                                    v-model="authorization.code"
                                    :custom-style="cooperationInputStyle"
                                    maxlength="4"
                                    placeholder="填写图片中的验证码"
                                    :disabled="busy"
                                />
                            </view>
                        </template>
                        <picker
                            v-if="companies.length"
                            :range="companies"
                            range-key="name"
                            :value="companyIndex"
                            :disabled="busy"
                            @change="companyId = String(companies[Number($event.detail.value)]?.id || '')"
                        >
                            <view class="coop-picker-field"
                                >{{ companies[companyIndex]?.name || '选择所属公司' }} ›</view
                            >
                        </picker>
                    </template>
                    <view v-if="scopeFields.length" class="coop-fold-title" @tap="scopeOpen = !scopeOpen"
                        >来源范围（通常自动识别）<u-icon
                            :name="scopeOpen ? 'arrow-up' : 'arrow-down'"
                            size="12"
                            color="#89939f"
                        />
                    </view>
                    <template v-if="scopeOpen">
                        <view v-for="field in scopeFields" :key="field.key" class="coop-form-column">
                            <text>{{ field.label }}</text>
                            <u-input
                                v-model="scope[field.key]"
                                :custom-style="cooperationInputStyle"
                                :disabled="busy"
                                :placeholder="field.required ? '必填' : '可留空自动核对'"
                            />
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
            <picker
                v-if="preview.requires_store_selection"
                :range="preview.stores || []"
                range-key="name"
                :value="storeIndex"
                :disabled="busy"
                @change="scope.store_id = String(preview.stores[Number($event.detail.value)]?.store_id || '')"
            >
                <view class="coop-picker-field">{{ scope.store_id || '选择一个来源门店后重新验证' }} ›</view>
            </picker>
            <view class="coop-consent" @tap="!busy && (confirmed = !confirmed)">
                <CoopSelectionMark :selected="confirmed" />
                <text>我已核对{{ selectedProviderName }}的来源门店，同意保存授权并开启自动库存同步。</text>
            </view>
            <view class="coop-muted"
                >确认后会保存本次授权并开始首次同步；不会修改 ERP 库存。更换账号将影响后续库存匹配。</view
            >
        </view>
        <view v-if="error" class="coop-inline-error coop-form-error">{{ error }}</view>
        <view v-if="flow.step === 'authorize' || flow.step === 'preview'" class="coop-connection-help"
            ><u-collapse :border="false"
                ><u-collapse-item title="授权与同步说明" name="authorization" icon="info-circle"
                    ><view class="coop-connection-help-body"
                        ><view>账号授权仅用于读取库存。请使用门店授权的账号；启用同步不会修改 ERP 价格或库存。</view
                        ><view>{{ renewalNote }}</view
                        ><view>验证失败不会更换原接入。需要扫码或子账号时，请使用已获授权的账号。</view></view
                    ></u-collapse-item
                ></u-collapse
            ></view
        >
        <template #footer>
            <view class="coop-connection-actions"
                ><view v-if="flow.showSecondary" class="coop-connection-secondary"
                    ><u-button
                        :custom-style="cooperationButtonStyle"
                        shape="circle"
                        :text="flow.secondaryLabel"
                        :disabled="busy && !syncing"
                        @click="secondaryAction" /></view
                ><view class="coop-connection-primary"
                    ><u-button
                        :custom-style="cooperationButtonStyle"
                        shape="circle"
                        type="primary"
                        :text="smsMode && flow.primary === 'verify' ? '验证并预览' : flow.primaryLabel"
                        :loading="busy && !challengeSending"
                        :disabled="primaryDisabled"
                        @click="primaryAction" /></view
            ></view>
        </template>
    </CoopTaskSheet>
</template>
<script lang="ts">
// Match Vue's transparent layout in native Mini Programs: an extra custom
// component host otherwise breaks the page's definite-height flex chain.
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import { connectionActionState, connectionSyncAttention } from '../../utils/connection-actions'
import { erpProviderDisplayName } from '../../utils/erp-provider-display'
import { cooperationButtonStyle, cooperationInputStyle } from '../../utils/mobile-ui'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { cooperationConnection, cooperationChallenge, cooperationProbe, cooperationActivate, cooperationSync, cooperationSyncInfo, cooperationSyncAdvance, cooperationConnectionSettings, coopDate, coopError, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
const props = defineProps<{ show: boolean; page?: boolean; profile: CoopRow; providers: CoopRow[] }>()
const emit = defineEmits<{ (event: 'close' | 'changed'): void }>()
const intervals = [{ value: 15, label: '每15分钟' }, { value: 30, label: '每30分钟' }, { value: 60, label: '每小时（默认）' }, { value: 120, label: '每2小时' }, { value: 360, label: '每6小时' }, { value: 1440, label: '每天' }], interval = ref(60), intervalIndex = computed(() => Math.max(0, intervals.findIndex(item => item.value === interval.value))), intervalDirty = ref(false)
const connection = ref<CoopRow>({}), run = ref<CoopRow>({}), provider = ref(''), credentials = reactive<CoopRow>({}), authorization = reactive<CoopRow>({}), scope = reactive<CoopRow>({}), busy = ref(false), error = ref(''), editing = ref(false), scopeOpen = ref(false), captchaImage = ref(''), challengeToken = ref(''), challengeExpires = ref(0), companies = ref<CoopRow[]>([]), companyId = ref(''), preview = ref<CoopRow | null>(null), probeToken = ref(''), confirmed = ref(false)
const schema = computed(() => props.providers.find(item => item.key === provider.value)), providerIndex = computed(() => Math.max(0, props.providers.findIndex(item => item.key === provider.value))), smsMode = computed(() => schema.value?.interactive_auth?.type === 'sms_code'), interactive = computed(() => ['image_captcha', 'sms_code'].includes(schema.value?.interactive_auth?.type))
const codeLength = computed(() => Number(schema.value?.interactive_auth?.code_length || 4)), validSmsMobile = computed(() => /^1[3-9][0-9]{9}$/.test(String(credentials.username || '')))
const smsRetryAt = ref(0), smsNow = ref(Date.now()), smsMobileMask = ref(''), challengeSending = ref(false)
const smsRetrySeconds = computed(() => Math.max(0, Math.ceil((smsRetryAt.value - smsNow.value) / 1000)))
let smsTimer: ReturnType<typeof setTimeout> | undefined
function stopSmsCountdown(reset = true) { if (smsTimer !== undefined) clearTimeout(smsTimer); smsTimer = undefined; if (reset) { smsRetryAt.value = 0; smsNow.value = Date.now() } }
function tickSmsCountdown() { stopSmsCountdown(false); smsNow.value = Date.now(); if (smsRetryAt.value > smsNow.value && props.show) smsTimer = setTimeout(tickSmsCountdown, 1000) }
function startSmsCountdown(seconds = 60) { smsRetryAt.value = Date.now() + seconds * 1000; tickSmsCountdown() }
const providerOptions = computed(() => props.providers.map(item => ({ ...item, name: erpProviderDisplayName(item.key, props.providers) })))
const selectedProviderName = computed(() => erpProviderDisplayName(provider.value, props.providers, provider.value === connection.value.provider ? connection.value.provider_name : ''))
const connectionProviderName = computed(() => erpProviderDisplayName(connection.value.provider || provider.value, props.providers, connection.value.provider_name))
const defaultMode = computed(() => schema.value?.auth_modes?.find((item: CoopRow) => item.key === 'password') || schema.value?.auth_modes?.[0]), credentialFields = computed<CoopRow[]>(() => interactive.value ? (schema.value?.fields || []).filter((field: CoopRow) => (schema.value?.interactive_auth?.credential_fields || []).includes(field.key)) : defaultMode.value?.fields || schema.value?.fields || []), scopeFields = computed<CoopRow[]>(() => defaultMode.value?.scope_fields || schema.value?.scope_fields || [])
const companyIndex = computed(() => Math.max(0, companies.value.findIndex(item => String(item.id) === companyId.value))), storeIndex = computed(() => Math.max(0, (preview.value?.stores || []).findIndex((item: CoopRow) => String(item.store_id) === String(scope.store_id))))
const setupStage = ref(''), setupPublishedBaseline = ref(0), renewal = computed(() => connection.value.auth_renewal || {}), renewalPolicy = computed(() => defaultMode.value?.renewal || schema.value?.renewal || {})
const syncing = ref(false)
const stateLoaded = ref(false), expandedRunId = ref(0), historyExpanded = ref(false)
const tokenOnly = computed(() => renewal.value.status === 'manual_token' || connection.value.credentials?.auth_mode === 'token')
const channelClosed = computed(() => connection.value.channel_enabled === false)
const selectedChannelAvailable = computed(() => !!schema.value && schema.value.enabled !== false && !(channelClosed.value && provider.value === connection.value.provider))
const publishedRun = computed(() => run.value.published === true || Number(run.value.id || run.value.run_id || 0) > 0 && Number(run.value.id || run.value.run_id) === Number(connection.value.published_run_id))
const currentSyncStatus = computed(() => {
    if (channelClosed.value || run.value.status === 'paused' || run.value.channel_enabled === false) return 'paused'
    if (connection.value.requires_sync_restart || connection.value.last_sync_status === 'interrupted') return 'interrupted'
    const status = String(run.value.status || connection.value.last_sync_status || 'idle')
    if (status === 'completed' && !publishedRun.value && !(Number(connection.value.published_run_id) > 0 && !run.value.id)) return 'interrupted'
    return status
})
const syncAttention = computed(() => connectionSyncAttention(connection.value, currentSyncStatus.value))
const syncStateLabel = computed(() => syncAttention.value?.action === 'reauthorize' ? '授权已失效，需要重新登录' : syncAttention.value?.action === 'contact_support' ? '同步需要平台协助' : ({ idle:'还没有同步库存', pending:'等待同步', running:'正在同步库存', completed:'货盘已更新', failed:'本次同步未完成', cancelled:'本次同步已取消', interrupted:'需要重新同步', paused:channelClosed.value ? '该 ERP 渠道已暂停' : '同步任务已暂停' } as Record<string,string>)[currentSyncStatus.value] || '同步状态待核对')
const progressText = computed(() => currentSyncStatus.value === 'completed' ? `本轮整理 ${Number(run.value.record_count || 0)} 条库存资料` : run.value.progress_known && run.value.total_records != null ? `已整理 ${Number(run.value.record_count || 0)} 条 · 来源共 ${Number(run.value.total_records)} 条` : `已整理 ${Number(run.value.record_count || 0)} 条库存资料`)
const knownProgressPercent = computed(() => {
    if (!['pending','running','completed'].includes(currentSyncStatus.value) || !run.value.progress_known || run.value.progress_percent == null || !Number.isFinite(Number(run.value.progress_percent))) return null
    return Math.max(0,Math.min(currentSyncStatus.value === 'completed' ? 100 : 99,Number(run.value.progress_percent)))
})
const summaryTone = computed(() => syncAttention.value || ['failed','interrupted'].includes(currentSyncStatus.value) ? 'warning' : currentSyncStatus.value === 'completed' ? 'success' : ['paused','cancelled'].includes(currentSyncStatus.value) ? 'muted' : 'active')
const statusIcon = computed(() => summaryTone.value === 'success' ? 'checkmark-circle' : summaryTone.value === 'warning' ? 'error-circle' : summaryTone.value === 'muted' ? 'pause-circle' : 'reload')
const statusColor = computed(() => ({ success:'#4d8769',warning:'#b36c4b',muted:'#8491a1',active:'#5a82b3' }[summaryTone.value]))
const statusDescription = computed(() => channelClosed.value ? '原账号与历史库存记录仍保留。' : currentSyncStatus.value === 'interrupted' ? String(connection.value.last_sync_message || '上一轮尚未完整更新货盘，请重新同步；已有完整数据仍保留。') : currentSyncStatus.value === 'failed' ? String(run.value.error_message || connection.value.last_sync_message || '本轮未更新货盘，请重试；上一次完整数据仍保留。') : ['pending','running'].includes(currentSyncStatus.value) ? '整轮完成后才更新货盘。可以先返回，稍后查看结果。' : currentSyncStatus.value === 'completed' ? '最近一轮完整库存已更新，可在门店库存中查看。' : currentSyncStatus.value === 'cancelled' ? '本轮没有更新货盘，已有完整数据仍保留。' : '账号授权已保存，按底部提示开始同步库存。')
const savedIntervalLabel = computed(() => intervals.find(item => item.value === Number(connection.value.interval_minutes || 60))?.label.replace('（默认）','') || '待确认')
const authorizationLabel = computed(() => syncAttention.value?.action === 'reauthorize' ? '账号需要重新登录' : syncAttention.value?.action === 'contact_support' && connection.value.authorization_attention === true ? '授权资料待核对' : tokenOnly.value ? '临时授权，不能自动续登' : renewal.value.ready === true && renewal.value.automatic === true ? '已配置自动续登' : renewal.value.status === 'interactive_required' ? '授权到期后需重新验证' : connection.value.connected ? '账号授权已保存' : '尚未连接')
const historyAvailable = computed(() => Array.isArray(connection.value.recent_runs))
const recentRunRows = computed<CoopRow[]>(() => historyAvailable.value ? connection.value.recent_runs.slice(0,10) : [])
const historyRows = computed<CoopRow[]>(() => recentRunRows.value.slice(0,historyExpanded.value ? 10 : 3))
const historyCaption = computed(() => !historyAvailable.value ? '' : connection.value.recent_runs_has_more ? `最近 ${recentRunRows.value.length} 次` : `共 ${recentRunRows.value.length} 次`)
function logStatus(item: CoopRow) { return item.status_label || ({ completed:'同步完成',failed:'同步未完成',running:'同步中',pending:'等待同步',cancelled:'已取消',paused:'已暂停',interrupted:'已中断' } as Record<string,string>)[String(item.status)] || '状态待核对' }
function logTone(item: CoopRow) { return item.status === 'completed' ? 'success' : ['failed','interrupted'].includes(String(item.status)) ? 'warning' : ['running','pending'].includes(String(item.status)) ? 'active' : 'muted' }
function triggerLabel(value: unknown) { return ({ merchant:'手动同步',admin:'平台同步',schedule:'自动同步',scheduler:'自动同步',scheduled:'自动同步' } as Record<string,string>)[String(value)] || '库存同步' }
function inventoryCount(item: CoopRow) { return item.record_count != null && Number.isFinite(Number(item.record_count)) ? Math.max(0,Number(item.record_count)) + ' 条资料' : '数量待确认' }
const flow = computed(() => connectionActionState({ connected: !!connection.value.connected, editing: editing.value, hasPreview: !!preview.value, setupStage: setupStage.value, channelClosed: channelClosed.value, enabled: !!connection.value.enabled, syncStatus: currentSyncStatus.value, attention: syncAttention.value }))
const primaryDisabled = computed(() => busy.value || (flow.value.primary === 'verify' && (!selectedChannelAvailable.value || credentialFields.value.some(field => field.required && !String(credentials[field.key] || '').trim()))) || (flow.value.primary === 'activate' && (!selectedChannelAvailable.value || !confirmed.value || !probeToken.value || preview.value?.can_activate === false || !!preview.value?.requires_store_selection)) || (flow.value.primary === 'resume' && channelClosed.value))
function secondaryAction() { if (busy.value && !syncing.value) return; if (flow.value.step === 'preview') { preview.value = null; probeToken.value = ''; confirmed.value = false; editing.value = true; error.value = '' } else close() }
async function primaryAction() { if (primaryDisabled.value) return; if (flow.value.primary === 'verify') await probe(); else if (flow.value.primary === 'activate') await activate(); else if (flow.value.primary === 'resume') await resumeSetup(); else if (flow.value.primary === 'reauthorize') editAuthorization(); else if (flow.value.primary === 'sync') await sync(); else if (flow.value.primary === 'enable') await toggleSync(); else close() }
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
function clearChallenge(keepSmsCooldown = false) { Object.keys(authorization).forEach(key => delete authorization[key]); challengeToken.value = ''; clearCaptchaFile(); captchaImage.value = ''; challengeExpires.value = 0; companies.value = []; companyId.value = ''; smsMobileMask.value = ''; challengeSending.value = false; if (!keepSmsCooldown) stopSmsCountdown() }
function clearSecret() { Object.keys(credentials).forEach(key => delete credentials[key]); clearChallenge() }
function changeProvider(value: string) { if (busy.value || !props.providers.some(item => item.key === value && item.enabled !== false)) return; provider.value = value; clearSecret(); Object.keys(scope).forEach(key => delete scope[key]); preview.value = null; probeToken.value = ''; confirmed.value = false; setupStage.value = ''; error.value = '' }
function editAuthorization() { if (busy.value) return; editing.value = true; provider.value = connection.value.provider || provider.value; clearSecret(); Object.keys(scope).forEach(key => delete scope[key]); Object.assign(credentials, connection.value.credentials || {}); delete credentials.auth_mode; delete credentials.access_token; delete credentials.password; preview.value = null; probeToken.value = ''; confirmed.value = false; setupStage.value = ''; error.value = '' }
async function guard(work: () => Promise<void>) { if (busy.value) return; busy.value = true; error.value = ''; const token = ++sequence; stopPolling(); try { await work() } catch (cause) { if (token === sequence && props.show) { driving = false; error.value = coopError(cause, '处理未完成，请刷新核对') } } finally { if (token === sequence) { busy.value = false; syncing.value = false; schedulePoll() } } }
async function readState() { const token = sequence, result: any = await cooperationConnection(); if (!props.show || token !== sequence) return; connection.value = result.data || {}; stateLoaded.value = true; if (!intervalDirty.value) interval.value = Number(connection.value.interval_minutes || 60); const progress = connection.value.sync_progress; if (progress?.run_id) run.value = { ...progress, id: Number(progress.run_id) }; else if (Number(connection.value.active_run_id)) { const info: any = await cooperationSyncInfo(Number(connection.value.active_run_id)); if (props.show && token === sequence) run.value = info.data || {} } else run.value = {}; if (!['running', 'pending'].includes(currentSyncStatus.value)) driving = false }
async function refresh() { await guard(readState) }
function selectInterval(index: number) { if (busy.value || !intervals[index]) return; interval.value = intervals[index].value; intervalDirty.value = interval.value !== Number(connection.value.interval_minutes || 60) }
async function saveSettings() {
    if (busy.value || !intervalDirty.value) return
    const enabled = !!connection.value.enabled, selectedInterval = interval.value
    await guard(async () => {
        if (enabled) assertCurrentChannel()
        const result: any = await cooperationConnectionSettings({ enabled, interval_minutes: selectedInterval })
        if (!props.show) return
        connection.value = result.data || {}; intervalDirty.value = false; interval.value = Number(connection.value.interval_minutes || selectedInterval); emit('changed'); uni.showToast({ title:'同步设置已保存',icon:'none' })
    })
}
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
function challengeResponse(data: CoopRow) {
    const token = data.challenge_token, expires = Number(data.expires_at), image = data.captcha_image
    if (typeof token !== 'string' || !token || token.length > 256 || !Number.isFinite(expires) || expires * 1000 <= Date.now() || expires * 1000 > Date.now() + 600000) throw new Error('验证码授权返回不完整，请重新获取')
    if (smsMode.value) {
        const retry = Number(data.retry_after), mobile = String(credentials.username || ''), mask = mobile.slice(0, 3) + '****' + mobile.slice(-4)
        if (data.challenge_type !== 'sms_code' || !Number.isSafeInteger(retry) || retry < 1 || retry > 300 || data.mobile_mask !== mask) throw new Error('短信验证码返回不完整，请稍后重新获取')
        return { token, expires, image: '', retry, mask }
    }
    if (typeof image !== 'string' || image.length > 200000 || !/^data:image\/(?:jpeg|png|gif);base64,[A-Za-z0-9+/]+={0,2}$/.test(image)) throw new Error('验证码图片格式不正确，请重新获取')
    return { token, expires, image, retry: 0, mask: '' }
}
async function challenge() {
    if (busy.value || (smsMode.value && smsRetrySeconds.value > 0)) return
    await guard(async () => {
        assertSelectedChannel()
        for (const field of credentialFields.value) if (field.required && !String(credentials[field.key] || '').trim()) throw new Error(`请填写${field.label}`)
        if (smsMode.value && (!validSmsMobile.value || !Number.isInteger(codeLength.value) || codeLength.value < 4 || codeLength.value > 8)) throw new Error('请填写有效的登录手机号')
        const token = sequence
        clearChallenge()
        if (smsMode.value) startSmsCountdown()
        challengeSending.value = smsMode.value
        try {
            const result: any = await cooperationChallenge(payload())
            if (!props.show || token !== sequence) return
            const data = challengeResponse(result.data || {})
            challengeToken.value = data.token; captchaImage.value = data.image ? displayCaptcha(data.image) : ''; challengeExpires.value = data.expires; authorization.code = ''; companies.value = []
            if (smsMode.value) { smsMobileMask.value = data.mask; startSmsCountdown(data.retry) }
        } finally { if (token === sequence) challengeSending.value = false }
    })
}
async function probe() {
    await guard(async () => {
        assertSelectedChannel()
        for (const field of credentialFields.value) if (field.required && !String(credentials[field.key] || '').trim()) throw new Error(`请填写${field.label}`)
        const token = sequence, input: CoopRow = payload()
        if (interactive.value) {
            if (!challengeToken.value || challengeExpires.value * 1000 <= Date.now()) throw new Error('请重新获取有效验证码')
            if (smsMode.value && !new RegExp('^[0-9]{' + codeLength.value + '}$').test(String(authorization.code || ''))) throw new Error('请填写 ' + codeLength.value + ' 位数字验证码')
            input.authorization = companies.value.length ? { challenge_token: challengeToken.value, company_id: companyId.value } : { ...authorization, challenge_token: challengeToken.value }
        }
        let result: any
        try { result = await cooperationProbe(input) } catch (cause) { if (props.show && token === sequence && interactive.value) clearChallenge(smsMode.value); throw cause }
        if (!props.show || token !== sequence) return
        const data = result.data || {}
        if (smsMode.value && (data.authorization_required || data.status === 'choose_company' || typeof data.probe_token !== 'string' || !data.probe_token || !data.preview || typeof data.preview !== 'object' || Array.isArray(data.preview))) { clearChallenge(true); throw new Error('短信登录结果不完整，请重新获取验证码并验证') }
        if (data.status === 'choose_company' || data.authorization_required) { companies.value = data.companies || data.public?.companies || []; challengeToken.value = data.challenge_token || data.public?.challenge_token || ''; companyId.value = String(companies.value[0]?.id || ''); return }
        preview.value = data.preview || null; probeToken.value = data.probe_token || ''; confirmed.value = false; clearChallenge(smsMode.value)
    })
}
async function continueSetupWork() {
    assertSyncReady()
    if (setupStage.value === 'enable_sync') {
        try { const result: any = await cooperationConnectionSettings({ enabled: true, interval_minutes: interval.value }); if (!props.show) return; connection.value = result.data || {}; setupStage.value = 'start_sync'; emit('changed') }
        catch (cause) { throw new Error('授权已保存，自动同步开启结果未确认。可刷新核对或点击“继续开启自动同步”，无需重复授权。' + coopError(cause, '')) }
    }
    if (setupStage.value === 'start_sync') {
        try {
            const current: any = await cooperationConnection(); if (!props.show) return; connection.value = current.data || {}; assertSyncReady()
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
async function toggleSync() { const next = !connection.value.enabled; if (!next) { const confirmation: any = await new Promise(resolve => uni.showModal({ title: '暂停库存同步？', content: '暂停后不再自动更新 ERP 库存，旧快照将不参与后续匹配；账号和历史资料仍保留。', confirmText: '确认暂停', cancelText: '继续同步', success: resolve, fail: () => resolve({ confirm: false }) })); if (!confirmation.confirm || !props.show) return }; await guard(async () => { if (next) assertSyncReady(); const result: any = await cooperationConnectionSettings({ enabled: next, interval_minutes: interval.value }); if (!props.show) return; connection.value = result.data || {}; intervalDirty.value = false; emit('changed'); uni.showToast({ title: next ? '自动同步已启用' : '库存同步已关闭，快照不再参与匹配', icon: 'none' }) }) }
function assertSyncReady() { assertCurrentChannel(); if (syncAttention.value && syncAttention.value.action !== 'retry') throw new Error(syncAttention.value.message) }
async function sync() { await guard(async () => { assertCurrentChannel(); await readState(); if (!props.show) return; assertSyncReady(); if (connection.value.requires_sync_restart || !run.value.id || !['running', 'pending'].includes(run.value.status)) { const result: any = await cooperationSync(); if (!props.show) return; run.value = result.data?.run || result.data || {} }; driving = true; await advancePages() }) }
function close() { if (busy.value && !syncing.value && !smsMode.value) return; sequence++; driving = false; stopPolling(); clearSecret(); preview.value = null; emit('close') }
watch(() => props.show, show => { if (show) { intervalDirty.value = false; stateLoaded.value = false; expandedRunId.value = 0; historyExpanded.value = false; provider.value = props.profile.connection?.provider || props.profile.provider_requested || props.providers[0]?.key || ''; connection.value = props.profile.connection || {}; preview.value = null; run.value = {}; editing.value = !connection.value.connected; void refresh() } else { sequence++; driving = false; stopPolling(); busy.value = false; syncing.value = false; run.value = {}; clearSecret() } })
watch(() => JSON.stringify(credentials), () => { preview.value = null; probeToken.value = ''; confirmed.value = false })
watch(() => String(credentials.username || ''), () => { if (!smsMode.value) return; sequence++; busy.value = false; clearChallenge(); preview.value = null; probeToken.value = ''; confirmed.value = false; error.value = '' }, { flush: 'sync' })
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
.coop-small-actions { display: flex; gap: 20rpx; margin-top: 25rpx; }
.coop-captcha-row { display: flex; align-items: center; gap: 22rpx; margin: 20rpx 0; }
.coop-captcha-row>image { width: 220rpx; height: 90rpx; background: #f2f5f8; flex: none; }
.coop-sync-toggle { margin-top: 18rpx; }
.coop-small-actions>view { flex: 1; min-width: 0; }
.coop-captcha-action { flex: 1; min-width: 0; }
.coop-sms-row { gap: 16rpx; margin: 0; width: 100%; }
.coop-sms-input { flex: 1; min-width: 0; }
.coop-sms-action { width: 214rpx; flex: none; }
.coop-sync-progress { background: #edf3fa; border-radius: 10rpx; padding: 22rpx; margin-top: 20rpx; color: #637a95; font-size: 23rpx; line-height: 1.8; }.coop-sync-progress.complete { color: #52765e; background: #edf7ef; }.coop-sync-progress>view { display: flex; align-items: center; gap: 12rpx; font-size: 28rpx; font-weight: 600; margin-bottom: 8rpx; }.coop-sync-progress>text { display: block; }
.coop-connection-actions { display: flex; align-items: center; gap: 20rpx; width: 100%; }.coop-connection-secondary { flex: 0 0 28%; min-width: 0; }.coop-connection-primary { flex: 1; min-width: 0; }.coop-connection-help { margin: 20rpx 24rpx; background: #fff; border-radius: 12rpx; }.coop-connection-help-body { font-size: 23rpx; color: #8490a0; line-height: 1.8; padding: 0 8rpx 18rpx; }.coop-connection-help-body>view+view { margin-top: 12rpx; }
</style>
<style scoped lang="scss">
.coop-connection-overview,.coop-connection-history,.coop-connection-settings { margin:20rpx 24rpx; background:#fff; border-radius:12rpx; overflow:hidden; }
.coop-connection-overview { padding:28rpx; border-top:4rpx solid #d7e5f6; }
.coop-connection-overview.is-success { border-top-color:#bddacb; }
.coop-connection-overview.is-warning { border-top-color:#ebccb9; }
.coop-connection-overview.is-muted { border-top-color:#dfe5ec; }
.coop-connection-provider { display:flex; align-items:center; gap:16rpx; min-width:0; }
.coop-connection-provider__icon { display:flex; align-items:center; justify-content:center; width:76rpx; height:76rpx; border-radius:16rpx; background:#edf3fb; flex:none; }
.coop-connection-provider>view:nth-child(2) { display:flex; flex:1; min-width:0; flex-direction:column; gap:5rpx; }
.coop-connection-provider>view:nth-child(2)>text:first-child { color:#2d4058; font-size:28rpx; font-weight:600; line-height:38rpx; overflow-wrap:anywhere; }
.coop-connection-provider>view:nth-child(2)>text:last-child { color:#7f8d9f; font-size:22rpx; line-height:32rpx; }
.coop-connection-auto { font-size:21rpx; line-height:30rpx; padding:5rpx 10rpx; background:#eef6f1; color:#587c68; border-radius:6rpx; flex:none; }
.coop-connection-auto.is-paused { color:#7b8797; background:#f1f3f6; }
.coop-connection-status { display:flex; align-items:center; gap:14rpx; margin-top:30rpx; color:#253a54; font-size:36rpx; font-weight:600; line-height:50rpx; }
.coop-connection-status>text { min-width:0; }
.coop-connection-summary { font-size:25rpx; color:#718096; line-height:39rpx; margin-top:12rpx; overflow-wrap:anywhere; }
.coop-connection-overview .coop-sync-progress { padding:16rpx 0 0; margin-top:12rpx; background:transparent; }
.coop-connection-overview .coop-sync-progress>view:first-child { justify-content:space-between; font-size:23rpx; font-weight:400; line-height:34rpx; margin-bottom:10rpx; }
.coop-connection-progress-track { height:8rpx; border-radius:8rpx; background:#e9eef5; overflow:hidden; }
.coop-connection-progress-track>view { height:100%; background:#7295c4; border-radius:8rpx; }
.complete .coop-connection-progress-track>view { background:#7aaa91; }
.coop-connection-metrics { display:flex; gap:24rpx; border-top:1rpx solid #edf1f6; margin-top:24rpx; padding-top:22rpx; }
.coop-connection-metrics>view { flex:1; min-width:0; display:flex; flex-direction:column; gap:8rpx; }
.coop-connection-metrics>view>text:first-child { color:#8995a5; font-size:22rpx; line-height:32rpx; }
.coop-connection-metrics>view>text:last-child { color:#52677f; font-size:25rpx; line-height:37rpx; overflow-wrap:anywhere; }
.coop-connection-attention { background:#fff6eb; color:#9d714b; padding:18rpx 20rpx; border-radius:8rpx; margin-top:20rpx; font-size:24rpx; line-height:38rpx; }
.coop-connection-link { display:block; margin-top:8rpx; color:#547fae; font-weight:500; }
.coop-connection-history { padding:0 26rpx; }
.coop-connection-heading { display:flex; justify-content:space-between; align-items:center; gap:16rpx; padding:26rpx 0 18rpx; }
.coop-connection-heading>text:first-child { font-size:28rpx; font-weight:600; color:#2e4159; }
.coop-connection-heading>text:last-child { font-size:22rpx; color:#8b97a7; }
.coop-connection-empty { display:flex; align-items:center; justify-content:center; flex-direction:column; gap:14rpx; padding:30rpx 10rpx 36rpx; color:#8a98aa; font-size:24rpx; line-height:38rpx; text-align:center; }
.coop-sync-log { border-top:1rpx solid #edf1f6; }
.coop-sync-log__toggle { display:flex; align-items:center; gap:16rpx; width:100%; margin:0; padding:24rpx 0; border-radius:0; background:transparent; color:#354a63; text-align:left; line-height:1.5; }
.coop-sync-log__toggle::after { border:0; }
.coop-sync-log__dot { width:12rpx; height:12rpx; border-radius:50%; background:#a3afbd; flex:none; }
.coop-sync-log__dot.is-success { background:#7eaa90; }.coop-sync-log__dot.is-warning { background:#c38b63; }.coop-sync-log__dot.is-active { background:#7597c6; }
.coop-sync-log__main { flex:1; min-width:0; }
.coop-sync-log__main>view { display:flex; align-items:center; flex-wrap:wrap; gap:8rpx; font-size:26rpx; color:#3c506a; line-height:38rpx; }
.coop-sync-log__main>text { display:block; color:#91a0b2; font-size:22rpx; line-height:34rpx; margin-top:5rpx; }
.coop-sync-log__current { color:#6987ae; background:#eef4fb; font-size:20rpx; padding:1rpx 8rpx; border-radius:4rpx; }
.coop-sync-log__count { flex:none; max-width:175rpx; text-align:right; font-size:24rpx; line-height:36rpx; overflow-wrap:anywhere; }
.coop-sync-log__count>text { display:block; }.coop-sync-log__count>text:last-child { color:#96a1b1; font-size:21rpx; }
.coop-sync-log__issue { color:#a57a58; font-size:23rpx; line-height:36rpx; padding:0 0 20rpx 28rpx; overflow-wrap:anywhere; }
.coop-sync-log__detail { margin-bottom:24rpx; padding:18rpx 20rpx; border-radius:8rpx; background:#f6f8fb; }
.coop-sync-log__detail>view:not(.coop-sync-log__message) { display:flex; justify-content:space-between; gap:20rpx; font-size:22rpx; line-height:36rpx; color:#637891; }
.coop-sync-log__detail>view>text:first-child { flex:none; color:#8b99ac; }
.coop-sync-log__detail>view>text:last-child { text-align:right; min-width:0; }
.coop-sync-log__message { color:#71839a; font-size:23rpx; line-height:37rpx; margin-top:12rpx; overflow-wrap:anywhere; }
.coop-sync-log__more { display:flex;align-items:center;justify-content:center;gap:12rpx;width:100%;margin:0;padding:22rpx 0;background:transparent;border-radius:0;border-top:1rpx solid #edf1f6;color:#7089a8;font-size:24rpx;line-height:40rpx; }
.coop-sync-log__more::after { border:0; }
.coop-connection-settings__body { padding:2rpx 8rpx 24rpx; }
.coop-connection-settings .coop-connection-help-body { padding:0 8rpx 20rpx; }
.coop-connection-overview,.coop-connection-history,.coop-connection-settings { margin: 12px; border-radius: 8px; }
.coop-connection-overview { padding: 16px; }
.coop-connection-provider { flex-wrap: wrap; gap: 8px; }
.coop-connection-provider>view:nth-child(2) { min-width: 100px; }
.coop-connection-auto { max-width: 100%; font-size:12px; line-height: 18px; }
.coop-connection-status { font-size: 19px; line-height: 28px; margin-top: 16px; }
.coop-connection-summary { font-size: 13px; line-height: 21px; margin-top: 8px; }
.coop-connection-metrics { gap: 12px; margin-top: 12px; padding-top: 12px; }
.coop-small-actions { gap: 12px; margin-top: 16px; }
.coop-connection-link { display: flex; align-items: center; min-height: 44px; margin-top: 4px; font-size: 14px; }
.coop-connection-actions { gap: 12px; }
.coop-connection-secondary { flex-basis: 32%; }
.coop-sync-log__toggle,.coop-sync-log__more { min-height: 44px; box-sizing: border-box; }
</style>
