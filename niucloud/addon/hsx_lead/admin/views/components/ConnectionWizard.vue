<template>
    <HsxDrawer
        v-model="visible"
        title="ERP 接入向导"
        :subtitle="merchantName"
        size="md"
        :body-loading="loading"
        :confirm-loading="busy"
    >
        <el-steps :active="step" finish-status="success" simple class="lead-banner"
            ><el-step title="选 ERP" /><el-step title="填授权" /><el-step title="验证预览" /><el-step title="启用同步"
        /></el-steps>
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="lead-error-banner" />
        <el-alert
            v-if="original.channel_enabled === false"
            title="原 ERP 渠道已关闭，采集暂停"
            :description="original.channel_message || '账号与历史数据仍保留。若需更换接入，请从下方选择开放渠道并重新验证；不会自动切换原连接。'"
            type="warning"
            show-icon
            :closable="false"
            class="lead-banner"
        />
        <el-alert
            v-else-if="step < 2 && syncAttentionFor(original)"
            :title="syncAttentionFor(original)?.action === 'reauthorize' ? '原账号需要重新登录' : '原连接同步未完成'"
            :description="syncAttentionFor(original)?.message"
            type="warning"
            show-icon
            :closable="false"
            class="lead-banner"
        />
        <template v-if="!loading">
            <template v-if="step === 0"
                ><p class="lead-muted">先验证新授权，验证失败不会影响原来的接入。当前每个商户保留一个启用连接。</p
                ><div
                    v-for="item in providers"
                    :key="item.key"
                    class="lead-candidate"
                    :class="{ 'is-selected': provider === item.key }"
                    role="radio"
                    :aria-checked="provider === item.key"
                    tabindex="0"
                    @click="choose(item.key)"
                    @keydown.enter.prevent="choose(item.key)"
                    ><h4>{{ item.name }}</h4
                    ><p
                        class="lead-muted"
                        >{{ Array.isArray(item.notes) ? item.notes.join('；') : item.notes || '只读获取库存资料，不修改 ERP 的商品与库存。' }}</p
                    ></div
                ><el-empty v-if="!providers.length" description="暂无可用 ERP，请联系管理员确认插件配置"
            /></template>
            <template v-if="step === 1">
                <h3>{{ schema?.name }} · 授权信息</h3>
                <el-radio-group
                    v-if="supportsInteractive && !smsMode"
                    v-model="authMode"
                    :disabled="busy || challengeLoading"
                    class="lead-auth-methods"
                    aria-label="授权方式"
                >
                    <el-radio-button value="interactive" label="interactive">账号密码登录</el-radio-button>
                    <el-radio-button value="token" label="token">已有授权 Token</el-radio-button>
                </el-radio-group>
                <el-radio-group
                    v-else-if="authModes.length > 1"
                    v-model="authMode"
                    :disabled="busy"
                    class="lead-auth-methods"
                    aria-label="授权方式"
                >
                    <el-radio-button
                        v-for="mode in authModes"
                        :key="mode.key"
                        :value="mode.key"
                        :label="mode.key"
                        >{{ mode.label }}</el-radio-button
                    >
                </el-radio-group>
                <p
                    v-if="!supportsInteractive && selectedAuthMode?.notes?.length"
                    class="lead-muted"
                    >{{ selectedAuthMode.notes.join('；') }}</p
                >
                <el-alert
                    v-if="schema"
                    :title="renewalHint.title"
                    :description="renewalHint.message"
                    :type="renewalHint.type"
                    :closable="false"
                    show-icon
                    class="lead-auth-renewal"
                />
                <div
                    v-if="interactiveMode && automaticCapability && !companies.length"
                    class="lead-auth-automatic-heading"
                >
                    <div
                        ><el-tag v-if="automaticReady" size="small" type="success">自动登录已就绪</el-tag
                        ><span
                            v-else
                            class="lead-muted"
                            >{{ captchaConfigLoading ? '正在检查自动登录设置…' : '自动识别未启用或不可用，仍可手动登录。' }}</span
                        ></div
                    >
                    <el-button
                        link
                        type="primary"
                        :disabled="busy || automatic.active.value"
                        @click="captchaSettingsVisible = true"
                        >自动登录设置</el-button
                    >
                    <el-radio-group
                        v-if="automaticReady"
                        v-model="recognitionMode"
                        size="small"
                        aria-label="验证码处理方式"
                        ><el-radio-button value="automatic" label="automatic">自动识别</el-radio-button
                        ><el-radio-button value="manual" label="manual">手动验证码</el-radio-button></el-radio-group
                    >
                </div>
                <div
                    v-if="interactiveMode && !smsMode && !companies.length && automatic.status.value !== 'idle'"
                    class="lead-auth-progress"
                    role="status"
                    aria-live="polite"
                >
                    <div
                        ><span
                            v-if="automatic.working.value"
                            class="lead-auth-spinner"
                        /><strong
                            >{{ automatic.stage.value === 'logging_in' ? '正在登录 ERP' : automatic.status.value === 'paused' ? '查询暂时中断' : automatic.status.value === 'failed' || automatic.status.value === 'expired' ? '本次自动登录未完成' : '正在自动登录' }}</strong
                        ></div
                    >
                    <p>{{ automatic.message.value }}</p
                    ><p class="lead-muted">不需填写公网回调。停止任务不会退还已提交的识别费用；不会自动启用接入。</p>
                    <el-button
                        v-if="automatic.status.value === 'paused'"
                        size="small"
                        type="primary"
                        @click="automatic.resume"
                        >继续查询本次申请</el-button
                    ><el-button v-if="automatic.active.value" size="small" @click="stopAutomatic"
                        >停止本次登录</el-button
                    ><el-button v-if="!automatic.working.value" size="small" link @click="recognitionMode = 'manual'"
                        >改用手动验证码</el-button
                    >
                </div>
                <el-form label-position="top" class="lead-form" :disabled="busy || automatic.active.value">
                    <el-form-item
                        v-for="field in activeFields"
                        :key="field.key"
                        :label="field.label"
                        :required="field.required"
                    >
                        <el-input
                            v-model="credentials[field.key]"
                            :type="field.secret ? 'password' : 'text'"
                            :show-password="!!field.secret"
                            :maxlength="field.max_length || 2000"
                            :autocomplete="field.secret ? 'new-password' : 'off'"
                            :placeholder="canReuseSecret(field) ? '已保存；身份未变时留空保留' : (field.placeholder || `填写${field.label}`)"
                        />
                        <p v-if="field.help" class="lead-muted">{{ field.help }}</p>
                    </el-form-item>
                    <template v-if="interactiveMode">
                        <template v-if="!companies.length">
                            <el-form-item
                                v-for="field in interactiveFields"
                                :key="field.key"
                                :label="field.label"
                                :required="field.required"
                            >
                                <el-input
                                    v-model="authorizationInput[field.key]"
                                    :type="field.secret ? 'password' : 'text'"
                                    :show-password="!!field.secret"
                                    :maxlength="field.max_length || 4096"
                                    :autocomplete="field.secret ? 'new-password' : 'off'"
                                    :placeholder="field.placeholder || `填写${field.label}`"
                                />
                                <p v-if="field.help" class="lead-muted">{{ field.help }}</p>
                            </el-form-item>
                            <el-form-item v-if="smsMode" label="短信验证码" required>
                                <div class="lead-auth-captcha">
                                    <el-input
                                        v-model="authorizationInput.code"
                                        aria-label="短信验证码"
                                        :placeholder="`填写 ${codeLength} 位数字验证码`"
                                        :maxlength="codeLength"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        :disabled="!challengeToken || challengeLoading"
                                    />
                                    <el-button
                                        :loading="challengeLoading"
                                        :disabled="busy || smsRetrySeconds > 0 || !validSmsMobile"
                                        @click="refreshChallenge"
                                        >{{ smsRetrySeconds > 0 ? smsRetrySeconds + 's 后重发' : challengeToken ? '重新获取' : '获取验证码' }}</el-button
                                    >
                                </div>
                                <p v-if="challengeToken" class="lead-muted" role="status"
                                    >验证码已发送至 {{ smsMobileMask }}，请在
                                    {{ time(challengeExpires) }} 前完成验证。</p
                                >
                                <p v-else-if="smsRetrySeconds > 0" class="lead-muted" role="status"
                                    >请求已提交，如收到短信可稍后重新验证；请勿连续发送。</p
                                >
                            </el-form-item>
                            <el-form-item v-else-if="!automaticMode" label="图片验证码" required>
                                <div class="lead-auth-captcha">
                                    <el-input
                                        v-model="authorizationInput.code"
                                        aria-label="图片验证码"
                                        placeholder="填写图片中的 4 位验证码"
                                        :maxlength="4"
                                        autocomplete="off"
                                        :disabled="!challengeToken || challengeLoading"
                                    />
                                    <button
                                        v-if="captchaImage"
                                        type="button"
                                        class="lead-auth-image"
                                        :disabled="busy || challengeLoading"
                                        aria-label="刷新登录验证码"
                                        @click="refreshChallenge"
                                        ><img :src="captchaImage" alt="登录验证码" /><span>换一张</span></button
                                    >
                                    <el-button
                                        v-else
                                        :loading="challengeLoading"
                                        :disabled="busy"
                                        @click="refreshChallenge"
                                        >获取验证码</el-button
                                    >
                                </div>
                                <p v-if="challengeToken" class="lead-muted"
                                    >验证码有效至 {{ time(challengeExpires) }}；刷新后请使用新图片。</p
                                >
                            </el-form-item>
                        </template>
                        <div v-else class="lead-auth-company">
                            <el-alert
                                title="账号属于多个公司，请选择本商户对应的公司后继续。不会自动选择或切换其他公司。"
                                type="info"
                                :closable="false"
                            />
                            <el-form-item label="来源公司" required>
                                <el-select
                                    v-model="selectedCompany"
                                    filterable
                                    no-match-text="没有匹配的公司，请核对名称或编号"
                                    aria-label="搜索授权来源公司"
                                    placeholder="搜索并选择本次授权返回的公司"
                                    style="width: 100%"
                                    ><el-option
                                        v-for="company in companies"
                                        :key="company.id"
                                        :value="company.id"
                                        :label="`${company.name} · ${company.id}`"
                                /></el-select>
                            </el-form-item>
                            <el-button link :disabled="busy || challengeLoading" @click="refreshChallenge"
                                >放弃本次选择，重新获取验证码</el-button
                            >
                        </div>
                        <p
                            class="lead-muted"
                            >{{ smsMode ? '验证码仅用于本次授权。接入后自动同步库存，授权到期时需重新完成短信验证。' : automaticMode ? '点击一次后异步识别并登录；可能由第三方人工识别验证码，按次扣费。只发送验证码图片，不向识别服务发送 ERP 密码。' : '密码和验证码仅用于本次登录；接入后只保存加密授权，失效时需要重新登录。' }}</p
                        >
                    </template>
                    <el-form-item
                        v-for="field in activeScopeFields.filter(item => item.required)"
                        :key="field.key"
                        :label="field.label"
                        required
                        ><el-input
                            v-model="scope[field.key]"
                            :maxlength="field.max_length || 100"
                            :placeholder="field.placeholder || '按接口要求填写来源范围'"
                    /></el-form-item>
                    <el-collapse v-if="activeScopeFields.some(item => !item.required)"
                        ><el-collapse-item title="来源范围（按接口要求填写；可自动识别的项目可留空）"
                            ><el-form-item
                                v-for="field in activeScopeFields.filter(item => !item.required)"
                                :key="field.key"
                                :label="field.label"
                                :required="field.required"
                                ><el-input
                                    v-model="scope[field.key]"
                                    :maxlength="field.max_length || 100"
                                    :placeholder="field.placeholder || '按 ERP 来源信息填写'"
                                /><p v-if="field.help" class="lead-muted">{{ field.help }}</p></el-form-item
                            ></el-collapse-item
                        ></el-collapse
                    ></el-form
                >
                <el-alert
                    title="凭据只发送给本插件的安全验证服务，不会下发给商户会员，也不会在页面回显已保存的密钥。"
                    type="info"
                    :closable="false"
                />
            </template>
            <template v-if="step === 2">
                <h3>只读验证结果</h3>
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="ERP">{{ schema?.name }}</el-descriptions-item>
                    <el-descriptions-item
                        label="来源身份"
                        >{{ preview.identity?.name || preview.identity?.tenant_id || preview.identity?.store_id || '已核对授权身份' }}</el-descriptions-item
                    >
                    <el-descriptions-item label="首屏商品"
                        >{{ preview.first_page_count ?? 0 }}
                        条{{ preview.has_more ? ' · 还有后续分页' : ' · 首屏分页已结束' }}</el-descriptions-item
                    >
                    <el-descriptions-item label="数据边界"
                        >首屏条数不是库存总数，更不是已确认可售数量</el-descriptions-item
                    >
                </el-descriptions>
                <el-table v-if="preview.items?.length" :data="preview.items" size="small" style="margin-top: 16px">
                    <el-table-column prop="title" label="首屏商品预览" min-width="180" /><el-table-column
                        prop="brand"
                        label="品牌"
                        width="100"
                    /><el-table-column
                        prop="price"
                        label="快照价格"
                        :formatter="(_row, _column, value) => previewPrice(value)"
                        width="100"
                    /><el-table-column prop="store_id" label="来源门店 ID" min-width="120" />
                </el-table>
                <el-alert
                    v-if="preview.empty_inventory && preview.can_activate !== false"
                    title="来源身份已核验，当前查询范围暂无商品资料。可以先启用接入，后续同步会按实际库存更新。"
                    type="info"
                    :closable="false"
                    style="margin-top: 16px"
                />
                <el-alert
                    v-else-if="preview.empty_inventory"
                    title="授权可读取，但没有商品可用于核对来源门店，因此暂不能启用。商户资料已保留，待有库存后重新验证。"
                    type="warning"
                    :closable="false"
                    style="margin-top: 16px"
                />
                <el-alert
                    v-else-if="preview.can_activate === true && preview.first_page_count === 0 && preview.has_more"
                    title="来源身份已核验，正在核对上架范围；启用并完成完整同步后才能查看设备清单。"
                    type="info"
                    :closable="false"
                    style="margin-top: 16px"
                />
                <el-collapse v-if="preview.note || preview.identity?.basis" style="margin-top: 16px">
                    <el-collapse-item title="验证范围与数据说明" name="scope">
                        <p v-if="preview.identity?.basis" class="lead-muted">核验依据：{{ verificationBasis }}</p>
                        <p v-if="preview.note" class="lead-muted">{{ preview.note }}</p>
                    </el-collapse-item>
                </el-collapse>
                <el-form v-if="preview.stores?.length" label-position="top" style="margin-top: 20px"
                    ><el-form-item label="确认来源门店"
                        ><el-select
                            v-model="selectedStore"
                            filterable
                            no-match-text="没有匹配的门店，请核对名称或编号"
                            aria-label="搜索授权来源门店"
                            placeholder="搜索并选择本商户对应的来源门店"
                            style="width: 100%"
                            ><el-option
                                v-for="store in preview.stores"
                                :key="store.store_id"
                                :label="`${store.name || '来源门店'} · ${store.store_id}`"
                                :value="String(store.store_id)" /></el-select></el-form-item
                ></el-form>
                <el-alert
                    v-if="preview.requires_store_selection || storeChanged"
                    title="需确认门店范围。选择后会重新进行只读验证；不能跳过范围校验直接启用。"
                    type="warning"
                    :closable="false"
                />
                <el-alert
                    v-if="impact.message"
                    :title="impact.message"
                    :type="impact.switching || impact.published_snapshot_will_be_hidden ? 'warning' : 'info'"
                    :closable="false"
                    style="margin-top: 16px"
                />
                <el-checkbox
                    v-if="!needsStoreProbe && preview.can_activate !== false"
                    v-model="confirmed"
                    style="margin-top: 18px"
                    >我已核对 ERP 及来源门店，确认启用{{ original.connected ? '并替换当前接入' : '' }}</el-checkbox
                >
                <p class="lead-muted">验证有效期至 {{ time(probeExpires) }}；启用时不再次传送凭据。</p>
            </template>
            <template v-if="step === 3"
                ><el-result
                    icon="success"
                    title="ERP 接入已启用"
                    sub-title="下一步完成首次同步，完整发布库存后才参与库存匹配。"
                /><el-alert
                    :title="renewalHint.title"
                    :description="renewalHint.message"
                    :type="renewalHint.type"
                    :closable="false"
                    show-icon
                    class="lead-auth-renewal"
                /><el-form label-position="top" class="lead-form"
                    ><el-form-item label="自动同步"><el-switch v-model="settings.enabled" /></el-form-item
                    ><el-form-item label="同步间隔（分钟）"
                        ><el-input-number v-model="settings.interval_minutes" :min="15" :max="1440" :precision="0" /><p
                            class="lead-muted"
                            >默认 60 分钟；修改周期不会替你开启牛云服务器计划任务。</p
                        ></el-form-item
                    ></el-form
                ></template
            >
        </template>
        <template #footer
            ><div class="lead-actions" style="justify-content: flex-end"
                ><el-button :disabled="busy" @click="visible = false">关闭</el-button
                ><el-button v-if="step > 0 && step < 3" :disabled="busy" @click="previous">上一步</el-button
                ><el-button v-if="step === 0" type="primary" :disabled="!provider || loading" @click="step = 1"
                    >填写授权</el-button
                ><el-button
                    v-if="step === 1"
                    type="primary"
                    :disabled="challengeLoading || automatic.active.value"
                    :loading="busy || automatic.working.value"
                    @click="probe"
                    >{{ companies.length ? '确认公司并验证' : automaticMode ? '自动登录并预览' : smsMode ? '验证并预览' : '只读验证与预览' }}</el-button
                ><el-button
                    v-if="step === 2 && needsStoreProbe"
                    type="primary"
                    :disabled="!selectedStore"
                    :loading="busy"
                    @click="reprobeStore"
                    >确认门店并重新验证</el-button
                ><el-button
                    v-if="step === 2 && !needsStoreProbe"
                    type="primary"
                    :disabled="!confirmed || !probeToken || preview.can_activate === false"
                    :loading="busy"
                    @click="activate"
                    >确认启用接入</el-button
                ><el-button v-if="step === 3" type="primary" :loading="busy" @click="initialSync"
                    >保存周期并开始首次同步</el-button
                ></div
            ></template
        >
    </HsxDrawer>
    <CaptchaSettings v-model="captchaSettingsVisible" @changed="captchaConfigChanged" />
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import CaptchaSettings from './CaptchaSettings.vue'
import * as api from '../../api'
import { errorText, time, type Row } from '../../composables/useLeadUi'
import { useAutomaticAuthorization } from '../../composables/useAutomaticAuthorization'
import { authRenewalLabel, authRenewalPolicy, syncAttentionFor } from '../../composables/authRenewal'
const props = defineProps<{ modelValue: boolean, merchantId: number, merchantName?: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void, (e: 'changed'): void, (e: 'sync', merchantId: number): void }>()
const visible = computed({ get: () => props.modelValue, set: value => { if (!value) invalidateRequests(); emit('update:modelValue', value) } })
const step = ref(0), loading = ref(false), busy = ref(false), error = ref(''), providers = ref<Row[]>([]), original = ref<Row>({}), provider = ref(''), credentials = reactive<Row>({}), scope = reactive<Row>({}), preview = ref<Row>({}), impact = ref<Row>({}), selectedStore = ref(''), probeToken = ref(''), probeExpires = ref(0), confirmed = ref(false), verifiedScope = ref('')
const settings = reactive({ enabled: true, interval_minutes: 60 })
const schema = computed(() => providers.value.find(item => item.key === provider.value))
const authMode = ref('token'), authorizationInput = reactive<Row>({}), challengeToken = ref(''), captchaImage = ref(''), challengeExpires = ref(0), challengeLoading = ref(false), companies = ref<Row[]>([]), selectedCompany = ref('')
const smsMode = computed(() => schema.value?.interactive_auth?.type === 'sms_code')
const supportsInteractive = computed(() => ['image_captcha', 'sms_code'].includes(schema.value?.interactive_auth?.type))
const interactiveMode = computed(() => supportsInteractive.value && (smsMode.value || authMode.value === 'interactive'))
const codeLength = computed(() => Number(schema.value?.interactive_auth?.code_length || 4))
const validSmsMobile = computed(() => /^1[3-9][0-9]{9}$/.test(String(credentials.username || '')))
const smsRetryAt = ref(0), smsNow = ref(Date.now()), smsMobileMask = ref('')
const smsRetrySeconds = computed(() => Math.max(0, Math.ceil((smsRetryAt.value - smsNow.value) / 1000)))
let smsTimer: ReturnType<typeof setTimeout> | undefined
function stopSmsCountdown(reset = true) { if (smsTimer !== undefined) clearTimeout(smsTimer); smsTimer = undefined; if (reset) { smsRetryAt.value = 0; smsNow.value = Date.now() } }
function tickSmsCountdown() { stopSmsCountdown(false); smsNow.value = Date.now(); if (smsRetryAt.value > smsNow.value && visible.value) smsTimer = setTimeout(tickSmsCountdown, 1000) }
function startSmsCountdown(seconds = 60) { smsRetryAt.value = Date.now() + seconds * 1000; tickSmsCountdown() }
const interactiveCredentialFields = computed(() => (schema.value?.fields || []).filter((field: Row) => !field.secret && (schema.value?.interactive_auth?.credential_fields || []).includes(field.key)))
const interactiveFields = computed(() => schema.value?.interactive_auth?.fields || [])
const authModes = computed<Row[]>(() => !supportsInteractive.value && Array.isArray(schema.value?.auth_modes) ? schema.value.auth_modes : [])
const selectedAuthMode = computed(() => authModes.value.find(mode => mode.key === authMode.value))
const activatedRenewal = ref<Row>({})
const renewalHint = computed(() => step.value === 3 && activatedRenewal.value.message
    ? { title: authRenewalLabel(activatedRenewal.value), message: String(activatedRenewal.value.message), type: activatedRenewal.value.automatic === true && activatedRenewal.value.ready === true ? 'success' as const : 'warning' as const }
    : authRenewalPolicy(schema.value || {}, authMode.value))
const activeFields = computed<Row[]>(() => interactiveMode.value ? interactiveCredentialFields.value : selectedAuthMode.value?.fields || schema.value?.fields || [])
const activeScopeFields = computed<Row[]>(() => selectedAuthMode.value?.scope_fields || schema.value?.scope_fields || [])
const captchaConfig = ref<Row>({}), captchaConfigLoading = ref(false), captchaSettingsVisible = ref(false), recognitionChoice = ref('')
const automaticCapability = computed(() => schema.value?.interactive_auth?.automatic_recognition === true)
const automaticReady = computed(() => captchaConfig.value.enabled === true && captchaConfig.value.configured === true && Number(captchaConfig.value.remaining_today) > 0)
const automaticMode = computed(() => interactiveMode.value && automaticCapability.value && automaticReady.value && recognitionChoice.value !== 'manual')
const recognitionMode = computed({ get: () => automaticMode.value ? 'automatic' : 'manual', set: value => { invalidateRequests(); recognitionChoice.value = value; error.value = '' } })
const automatic = useAutomaticAuthorization({ start: api.startAutomaticAuthorization, advance: api.advanceAutomaticAuthorization, cancel: api.cancelAutomaticAuthorization,
    errorText, complete: payload => { try { acceptProbePayload(payload) } catch (cause) { clearTransient(); error.value = errorText(cause, '自动登录结果不完整，请改用手动验证码。') } } })
const verificationBasis = computed(() => preview.value.identity?.basis === 'authenticated_profile_and_first_page' ? '认证账号资料与首屏库存接口' : preview.value.identity?.basis === 'first_page_read_only_list' ? '首屏商品的来源门店与租户' : '来源接口返回的身份资料')
const storeChanged = computed(() => !!selectedStore.value && selectedStore.value !== String(verifiedScope.value || ''))
const needsStoreProbe = computed(() => !!preview.value.requires_store_selection || storeChanged.value)
let sequence = 0, configSequence = 0, restoring = false
function previewPrice(value: unknown) {
    const raw = typeof value === 'string' ? value.trim() : typeof value === 'number' && Number.isFinite(value) ? String(value) : ''
    if (!/^[0-9]{1,14}(?:\.[0-9]{1,2})?$/.test(raw)) return '未提供'
    const [whole, fraction = ''] = raw.split('.')
    return `¥${whole.replace(/^0+(?=\d)/, '')}.${fraction.padEnd(2, '0')}`
}
function clearTransient(keepSmsCooldown = false) {
    Object.keys(authorizationInput).forEach(key => delete authorizationInput[key])
    challengeToken.value = ''; captchaImage.value = ''; challengeExpires.value = 0
    companies.value = []; selectedCompany.value = ''; smsMobileMask.value = ''
    if (!keepSmsCooldown) stopSmsCountdown()
}
function clearSecrets() { Object.keys(credentials).forEach(key => delete credentials[key]); clearTransient() }
function invalidateRequests() { sequence++; automatic.stop(); busy.value = false; challengeLoading.value = false; probeToken.value = ''; confirmed.value = false; clearTransient() }
function stopAutomatic() { invalidateRequests(); error.value = '本次自动登录已停止；已提交的识别费用不退还。可以重新填写密码或改用手动验证码。' }
function captchaConfigChanged(payload: Row, resetChoice = true) { captchaConfig.value = { enabled: payload.enabled === true, configured: payload.configured === true, remaining_today: payload.remaining_today }; if (resetChoice) recognitionChoice.value = '' }
async function loadCaptchaConfig() {
    if (typeof api.getCaptchaConfig !== 'function') return
    const token = ++configSequence, merchantId = props.merchantId
    const current = () => token === configSequence && visible.value && merchantId === props.merchantId
    captchaConfigLoading.value = true
    try { const result = await api.getCaptchaConfig(); if (current()) captchaConfigChanged(result.data || {}, false) }
    catch { if (current()) captchaConfig.value = {} }
    finally { if (current()) captchaConfigLoading.value = false }
}
function applyProvider(key: string) {
    restoring = true
    try {
        clearSecrets(); activatedRenewal.value = {}; Object.keys(scope).forEach(name => delete scope[name]); provider.value = key
        if (original.value.provider === key) { Object.assign(credentials, original.value.credentials || {}); Object.assign(scope, original.value.scope || {}) }
        const savedMode = original.value.provider === key ? original.value.credentials?.auth_mode : ''
        authMode.value = supportsInteractive.value ? (schema.value?.interactive_auth?.default ? 'interactive' : 'token')
            : authModes.value.some(mode => mode.key === savedMode) ? savedMode
                : authModes.value.find(mode => mode.key === schema.value?.auth_mode)?.key || authModes.value[0]?.key || schema.value?.auth_mode || 'token'
        recognitionChoice.value = ''
    } finally { restoring = false }
}
function choose(key: string) { if (provider.value === key || !providers.value.some(item => item.key === key && item.enabled !== false)) return; invalidateRequests(); error.value = ''; applyProvider(key) }
const identityKey = computed(() => JSON.stringify([provider.value, ...activeFields.value.filter((field: Row) => !field.secret && field.identity).map((field: Row) => [field.key, String(credentials[field.key] || '')])]))
function invalidateAuthorizationIdentity(clearScope: boolean) {
    invalidateRequests()
    if (clearScope) Object.keys(scope).forEach(key => delete scope[key])
    for (const field of [...(schema.value?.fields || []), ...authModes.value.flatMap(mode => mode.fields || [])]) if (field.secret) delete credentials[field.key]
    error.value = ''
}
watch(identityKey, () => { if (!restoring) invalidateAuthorizationIdentity(true) }, { flush: 'sync' })
watch(authMode, () => {
    if (restoring) return
    if (!authModes.value.length) { invalidateAuthorizationIdentity(false); return }
    restoring = true
    try {
        invalidateRequests(); clearSecrets(); Object.keys(scope).forEach(key => delete scope[key]); error.value = ''
        if (original.value.provider === provider.value && original.value.credentials?.auth_mode === authMode.value) {
            Object.assign(credentials, original.value.credentials || {}); Object.assign(scope, original.value.scope || {})
        }
    } finally { restoring = false }
}, { flush: 'sync' })
function isCurrent(token: number) { return token === sequence && visible.value }
function credentialPayload() {
    const values = Object.fromEntries(activeFields.value.filter((field: Row) => credentials[field.key] !== undefined).map((field: Row) => [field.key, credentials[field.key]]))
    return authModes.value.length ? { ...values, auth_mode: authMode.value } : values
}
function canReuseSecret(field: Row) {
    return field.secret && original.value.provider === provider.value && original.value.configured_fields?.includes(field.key)
        && (!authModes.value.length || original.value.credentials?.auth_mode === authMode.value)
        && activeFields.value.filter(item => !item.secret && item.identity).every(item => String(credentials[item.key] || '') === String(original.value.credentials?.[item.key] || ''))
}
function validateFields(fields: Row[], values: Row, allowSaved = false) {
    for (const field of fields) {
        const configured = allowSaved && canReuseSecret(field)
        if (field.required && !values[field.key] && !configured) return `请填写${field.label}`
    }
    return ''
}
function challengeResponse(payload: Row) {
    const token = payload.challenge_token, expires = Number(payload.expires_at), image = payload.captcha_image
    if (typeof token !== 'string' || !token || token.length > 256 || !Number.isFinite(expires) || expires * 1000 <= Date.now() || expires * 1000 > Date.now() + 600000) throw new Error('验证码授权返回不完整，请重新获取。')
    if (smsMode.value) {
        const seconds = Number(payload.retry_after), mobile = String(credentials.username || ''), mask = mobile.slice(0, 3) + '****' + mobile.slice(-4)
        if (payload.challenge_type !== 'sms_code' || !Number.isSafeInteger(seconds) || seconds < 1 || seconds > 300 || payload.mobile_mask !== mask) throw new Error('短信验证码返回不完整，请稍后重新获取。')
        return { token, expires, image: '', retry: seconds, mask }
    }
    if (typeof image !== 'string' || image.length > 200000 || !/^data:image\/(?:jpeg|png|gif);base64,[A-Za-z0-9+/]+={0,2}$/.test(image)) throw new Error('验证码图片格式不正确，请重新获取。')
    return { token, expires, image, retry: 0, mask: '' }
}
async function refreshChallenge() {
    if (!interactiveMode.value || busy.value || (smsMode.value && (challengeLoading.value || smsRetrySeconds.value > 0))) return
    if (smsMode.value && (!validSmsMobile.value || !Number.isInteger(codeLength.value) || codeLength.value < 4 || codeLength.value > 8)) { error.value = '请填写有效的登录手机号。'; return }
    recognitionChoice.value = 'manual'
    const invalid = validateFields(interactiveCredentialFields.value, credentials)
    if (invalid) { error.value = invalid; return }
    const keptInput = { ...authorizationInput }; delete keptInput.code
    invalidateRequests(); Object.assign(authorizationInput, keptInput)
    const token = sequence; challengeLoading.value = true; error.value = ''
    if (smsMode.value) startSmsCountdown()
    try {
        const result = await api.getAuthorizationChallenge(props.merchantId, { provider: provider.value, credentials: credentialPayload() })
        if (!isCurrent(token)) return
        const challenge = challengeResponse(result.data || {})
        challengeToken.value = challenge.token; challengeExpires.value = challenge.expires; captchaImage.value = challenge.image
        if (smsMode.value) { smsMobileMask.value = challenge.mask; startSmsCountdown(challenge.retry) }
    } catch (cause) { if (isCurrent(token)) { clearTransient(smsMode.value); error.value = errorText(cause, smsMode.value ? '发送结果暂未确认，请稍后重新获取，系统不会自动重发。' : '验证码获取失败，请重试。') } }
    finally { if (isCurrent(token)) challengeLoading.value = false }
}
function acceptProbePayload(payload: Row) {
    if (smsMode.value && (payload.authorization_required === true || payload.status === 'choose_company')) throw new Error('短信登录尚未完成，请重新获取验证码并验证。')
    if (payload.authorization_required === true || payload.status === 'choose_company') { acceptCompanySelection(payload); return }
    if (typeof payload.probe_token !== 'string' || !payload.probe_token) throw new Error('验证结果不完整，请重新验证。')
    probeToken.value = payload.probe_token; probeExpires.value = payload.expires_at; preview.value = payload.preview || {}; impact.value = payload.impact || {}
    verifiedScope.value = String(scope.store_id || preview.value.identity?.store_id || (preview.value.stores?.length === 1 ? preview.value.stores[0].store_id : ''))
    selectedStore.value = verifiedScope.value || (preview.value.stores?.length === 1 ? String(preview.value.stores[0].store_id) : '')
    clearTransient(smsMode.value); step.value = 2
}
function acceptCompanySelection(payload: Row) {
    const list = payload.companies || payload.public?.companies, token = payload.challenge_token, expires = Number(payload.expires_at)
    if (!Array.isArray(list) || list.length < 1 || list.length > 100 || typeof token !== 'string' || !token || token.length > 256 || !Number.isFinite(expires) || expires * 1000 <= Date.now() || expires * 1000 > Date.now() + 600000) throw new Error('公司选择授权已失效，请重新登录。')
    const parsed = list.map((item: Row) => {
        if (!item || !['string', 'number'].includes(typeof item.id) || !String(item.id) || typeof item.name !== 'string' || !item.name.trim()) throw new Error('公司列表格式变化，请重新登录。')
        return { id: String(item.id), name: item.name }
    })
    if (new Set(parsed.map((item: Row) => item.id)).size !== parsed.length) throw new Error('公司列表存在歧义，请重新登录。')
    clearTransient(); challengeToken.value = token; challengeExpires.value = expires; companies.value = parsed
}
async function load() {
    configSequence++; automatic.stop(); captchaConfig.value = {}; captchaConfigLoading.value = false; captchaSettingsVisible.value = false
    const token = ++sequence; restoring = true
    step.value = 0; loading.value = true; error.value = ''; clearSecrets(); provider.value = ''; original.value = {}; restoring = false
    try {
        const [list, connection] = await Promise.all([api.getProviders(), api.getConnection(props.merchantId)])
        if (!isCurrent(token)) return
        providers.value = list.data.items || []; original.value = connection.data || {}
        applyProvider(providers.value.some(item => item.key === original.value.provider) ? original.value.provider : providers.value[0]?.key || '')
        void loadCaptchaConfig()
        settings.enabled = original.value.connected ? !!original.value.enabled : true; settings.interval_minutes = Number(original.value.interval_minutes || 60)
    } catch (cause) { if (isCurrent(token)) error.value = errorText(cause, '接入信息读取失败，请关闭后重试。') }
    finally { if (isCurrent(token)) loading.value = false }
}
async function probe() {
    if (busy.value || challengeLoading.value || automatic.active.value || !schema.value) return
    if (authModes.value.length && !selectedAuthMode.value) { error.value = '请选择可用的授权方式。'; return }
    const invalid = validateFields(activeFields.value, credentials, !interactiveMode.value)
        || validateFields(activeScopeFields.value, scope)
    if (invalid) { error.value = invalid; return }
    if (automaticMode.value && !companies.value.length) {
        const invalidInput = validateFields(interactiveFields.value, authorizationInput)
        if (invalidInput) { error.value = invalidInput; return }
        error.value = ''; probeToken.value = ''; confirmed.value = false
        const pending = automatic.start(props.merchantId, { provider: provider.value, credentials: credentialPayload(), scope: { ...scope }, password: authorizationInput.password })
        clearTransient() // Do not keep the password in the editable form while the task runs.
        await pending; return
    }
    let authorization: Row | undefined
    if (interactiveMode.value) {
        if (!challengeToken.value || challengeExpires.value * 1000 <= Date.now()) { clearTransient(); error.value = '请先获取有效验证码，再完成登录验证。'; return }
        if (companies.value.length) {
            if (!companies.value.some(item => item.id === selectedCompany.value)) { error.value = '请选择本次授权返回的公司。'; return }
            authorization = { challenge_token: challengeToken.value, company_id: selectedCompany.value }
        } else {
            const invalidInput = validateFields(interactiveFields.value, authorizationInput)
            if (invalidInput) { error.value = invalidInput; return }
            if (smsMode.value ? !new RegExp('^[0-9]{' + codeLength.value + '}$').test(String(authorizationInput.code || '')) : !/^[A-Za-z0-9]{4}$/.test(String(authorizationInput.code || ''))) { error.value = smsMode.value ? '请填写 ' + codeLength.value + ' 位数字验证码。' : '请填写图片中的 4 位验证码。'; return }
            authorization = Object.fromEntries(interactiveFields.value.map((field: Row) => [field.key, authorizationInput[field.key]]))
            authorization.challenge_token = challengeToken.value; authorization.code = String(authorizationInput.code)
        }
    }
    busy.value = true; error.value = ''; probeToken.value = ''; confirmed.value = false
    const token = ++sequence
    try {
        const result = await api.probeConnection(props.merchantId, { provider: provider.value, credentials: credentialPayload(), scope: { ...scope }, ...(authorization ? { authorization } : {}) })
        if (!isCurrent(token)) return
        const payload = result.data || {}
        acceptProbePayload(payload)
    } catch (cause) { if (isCurrent(token)) { if (interactiveMode.value) clearTransient(smsMode.value); error.value = errorText(cause, '验证失败，原接入未更改；请重新获取验证码。') } }
    finally { if (isCurrent(token)) busy.value = false }
}
async function reprobeStore() {
    if (!selectedStore.value) return
    scope.store_id = selectedStore.value
    if (interactiveMode.value) { invalidateRequests(); step.value = 1; error.value = '来源范围已选择，请重新完成登录验证。'; return }
    await probe()
}
function previous() { invalidateRequests(); error.value = ''; step.value-- }
async function activate() {
    if (!confirmed.value || !probeToken.value || needsStoreProbe.value || preview.value.can_activate === false || busy.value) return
    const token = ++sequence; busy.value = true; error.value = ''
    try {
        const result = await api.activateConnection(props.merchantId, { probe_token: probeToken.value, ...(scope.store_id ? { store_id: scope.store_id } : {}), confirm_switch: true })
        if (!isCurrent(token)) return
        activatedRenewal.value = result.data?.auth_renewal || {}
        clearSecrets(); probeToken.value = ''; step.value = 3; emit('changed')
    } catch (cause) { if (isCurrent(token)) error.value = errorText(cause, '启用未完成，验证可能已过期，请重新验证。') }
    finally { if (isCurrent(token)) busy.value = false }
}
async function initialSync() {
    const token = ++sequence; busy.value = true; error.value = ''
    try { await api.saveSyncSettings(props.merchantId, { ...settings }); if (!isCurrent(token)) return; visible.value = false; emit('sync', props.merchantId) }
    catch (cause) { if (isCurrent(token)) error.value = errorText(cause, '周期未保存，接入已启用，可稍后在同步设置中处理。') }
    finally { if (isCurrent(token)) busy.value = false }
}
watch(() => [props.modelValue, props.merchantId], () => { if (visible.value && props.merchantId) void load(); else { configSequence++; invalidateRequests(); clearSecrets(); loading.value = false; captchaSettingsVisible.value = false } }, { immediate: true, flush: 'sync' })
onBeforeUnmount(() => { configSequence++; invalidateRequests(); clearSecrets() })
</script>
<style scoped>
.lead-auth-methods { margin-bottom: 20px; }
.lead-auth-renewal { margin-bottom: 16px; }
.lead-auth-automatic-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-bottom: 16px; }
.lead-auth-automatic-heading > :first-child { flex: 1; }
.lead-auth-automatic-heading :deep(.el-radio-group) { flex-basis: 100%; }
.lead-auth-progress { padding: 12px 14px; margin-bottom: 18px; border: 1px solid #dce8f8; border-radius: 6px; background: #f5f9ff; font-size: 13px; line-height: 1.7; }
.lead-auth-progress p { margin: 6px 0; }
.lead-auth-spinner { display: inline-block; width: 12px; height: 12px; margin-right: 8px; border: 2px solid #ccdff9; border-top-color: #409eff; border-radius: 50%; animation: lead-auth-spin .8s linear infinite; vertical-align: -2px; }
@keyframes lead-auth-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .lead-auth-spinner { animation: none; } }
.lead-auth-captcha { display: flex; align-items: stretch; gap: 12px; width: 100%; }
.lead-auth-captcha > :first-child { flex: 1; min-width: 0; }
.lead-auth-image { flex: 0 0 148px; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 10px; border: 1px solid var(--el-border-color); border-radius: var(--el-border-radius-base, 4px); background: var(--el-fill-color-blank, #fff); color: var(--el-color-primary); cursor: pointer; }
.lead-auth-image img { width: 88px; height: 32px; object-fit: contain; }
.lead-auth-image:disabled { cursor: wait; opacity: .65; }
.lead-auth-company { display: grid; gap: 16px; margin-bottom: 20px; }
@media (max-width: 480px) { .lead-auth-captcha { gap: 8px; } .lead-auth-image { flex-basis: 128px; padding: 4px 6px; } }
</style>
