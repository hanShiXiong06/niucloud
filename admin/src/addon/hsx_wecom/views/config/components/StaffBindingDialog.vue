<template>
    <el-dialog v-model="visible" title="绑定企业微信接收员工" width="min(620px, calc(100vw - 24px))" class="wecom-binding-dialog" append-to-body destroy-on-close :close-on-click-modal="false" :close-on-press-escape="!confirming" :show-close="!confirming" @close="onClosed">
        <div class="binding-summary"><strong>{{ employee.name || employee.username }}</strong><span>{{ corpName || '当前站点已授权企业' }}</span></div>
        <el-alert v-if="employee.wecom_userid" type="warning" :closable="false" show-icon title="正在重新绑定：核对并确认后才替换原接收身份；提交确认前取消不会改动原绑定。" />
        <div class="binding-body" v-loading="generating" element-loading-text="正在生成专属绑定码">
            <div v-if="confirmationUncertain || expiryNeedsVerification" class="result-panel"><el-result icon="warning" title="绑定结果待核实" :sub-title="confirmationUncertain ? '确认请求未取得明确结果，不能据此判断绑定失败。正在向服务端核对；也可关闭后刷新员工列表核对当前身份。' : '倒计时已结束，正在向服务端核对最终状态，不会仅凭电脑时间判断绑定是否成功。'" /></div>
            <template v-else-if="status === 'waiting'">
                <div class="qr-panel"><img v-if="qrImage" :src="qrImage" alt="员工专属企业微信绑定二维码" width="220" height="220"><span v-else>二维码准备中</span></div>
                <div class="binding-instruction">
                    <strong>请由 {{ employee.name || '该员工' }} 本人扫码</strong>
                    <p>使用手机企业微信“扫一扫”，切换到上方企业。不要代扫，也不要把此链接发到群里。</p>
                    <div class="expiry">剩余 {{ countdownLabel }}，扫码后还需在本页核对并确认。</div>
                    <el-button :disabled="!url || !remaining" @click="copyLink">复制本人专属链接</el-button>
                    <el-input v-if="manualCopy" :model-value="url" readonly aria-label="本人专属绑定链接" class="manual-link" />
                </div>
            </template>
            <template v-else-if="status === 'scanned'">
                <div class="identity-review">
                    <el-tag type="warning">已扫码 · 尚未绑定</el-tag>
                    <h3>请核对：扫码人是否就是 {{ employee.name || '该员工' }}？</h3>
                    <dl><dt>扫码企业</dt><dd>{{ candidate.corp_name || '未返回企业名称，请先核实' }}</dd><dt>扫码身份</dt><dd>{{ candidate.identity_label || '企业微信成员标识' }}</dd><dt>成员标识</dt><dd>{{ candidate.recipient_id || '未返回，暂不能确认' }}</dd></dl>
                    <p>平台不一定能获取姓名。请当面或单独联系本人核对，不要仅凭“扫码成功”确认。</p>
                    <el-checkbox v-model="identityConfirmed">我已核对，这是 {{ employee.name || '该员工' }} 本人的企业微信</el-checkbox>
                    <div class="expiry">剩余 {{ countdownLabel }}。确认后，该员工的通知将发给此身份。</div>
                </div>
            </template>
            <div v-else-if="status === 'bound'" class="result-panel"><el-result icon="success" title="员工绑定已确认" sub-title="请返回接收员工列表检查通知开关，再发送一次测试通知。" /></div>
            <div v-else-if="!generating" class="result-panel"><el-result :icon="status === 'expired' ? 'warning' : 'error'" :title="status === 'expired' ? '绑定码已过期' : '暂未完成绑定'" :sub-title="message || '请重新生成二维码，让员工使用正确企业身份再试。'" /></div>
        </div>
        <el-alert v-if="pollError && !generating" type="warning" :closable="false" show-icon :title="pollError"><el-button link type="primary" :loading="checking" @click="checkAgain">重新检查状态</el-button></el-alert>
        <div v-if="message && ['waiting', 'scanned'].includes(status)" class="state-message">{{ message }}</div>
        <template #footer>
            <div class="binding-footer">
                <el-button :disabled="confirming" @click="visible = false">{{ status === 'bound' ? '完成' : confirmationUncertain ? '关闭并核对员工列表' : '取消本次绑定' }}</el-button>
                <el-button v-if="status !== 'bound' && !confirmationUncertain" :loading="generating" :disabled="confirming || expiryNeedsVerification" @click="regenerate">{{ status === 'scanned' ? '不是本人，重新生成' : '重新生成二维码' }}</el-button>
                <el-button v-if="status === 'scanned' && !confirmationUncertain && !expiryNeedsVerification" type="primary" :loading="confirming" :disabled="!identityConfirmed || !candidate.recipient_id || !remaining || Boolean(pollError)" @click="confirmBinding">确认绑定此员工</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import QRCode from 'qrcode'
import { cancelWecomStaffBinding, confirmWecomStaffBinding, getWecomStaffBindStatus, getWecomStaffBindUrl } from '../../../api'
import { bindingDeadline, bindingOutcomeStillUncertain, bindingStatus, createBindingSessionGate, remainingBindingSeconds, type BindingStatus } from '../../../utils/binding-session'

defineProps<{ corpName: string }>()
const emit = defineEmits<{ (event: 'bound'): void; (event: 'refresh'): void }>()
const visible = ref(false)
const employee = reactive<any>({})
const status = ref<BindingStatus>('waiting')
const candidate = reactive({ corp_name: '', recipient_id: '', identity_label: '' })
const identityConfirmed = ref(false)
const intentId = ref(0)
const url = ref('')
const qrImage = ref('')
const deadline = ref(0)
const now = ref(performance.now())
const confirmationUncertain = ref(false)
const expiryNeedsVerification = ref(false)
const message = ref('')
const pollError = ref('')
const manualCopy = ref(false)
const generating = ref(false)
const checking = ref(false)
const confirming = ref(false)
const gate = createBindingSessionGate()
let timer: ReturnType<typeof setInterval> | undefined
let pollTimer: ReturnType<typeof setTimeout> | undefined
let pollAbort: AbortController | undefined
let boundEmitted = false
let currentTicket = 0
const remaining = computed(() => remainingBindingSeconds(deadline.value, now.value))
const countdownLabel = computed(() => `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`)
const errorMessage = (error: any, fallback: string) => String(error?.msg || error?.message || fallback)

function stopTimers() {
    if (timer) clearInterval(timer)
    if (pollTimer) clearTimeout(pollTimer)
    timer = undefined
    pollTimer = undefined
    pollAbort?.abort()
    pollAbort = undefined
    checking.value = false
}
function cancelIntent(uid: number, id: number, notifyFailure = false) {
    if (uid > 0 && id > 0) void cancelWecomStaffBinding(uid, id).catch(() => {
        if (notifyFailure) ElMessage.warning('弹窗已关闭，但未能确认旧入口是否撤销。未确认的扫码不会生效，绑定码到期自动失效。')
    })
}
function isCurrent(ticket: number) { return visible.value && gate.isCurrent(ticket) }
function markBound() {
    status.value = 'bound'
    confirmationUncertain.value = false
    expiryNeedsVerification.value = false
    identityConfirmed.value = false
    stopTimers()
    if (!boundEmitted) { boundEmitted = true; emit('bound') }
}
function onClosed(notifyFailure = true) {
    gate.invalidate()
    stopTimers()
    if (confirmationUncertain.value) {
        // The confirm request may have committed even if its response was lost. Do not cancel it.
        if (notifyFailure) { ElMessage.warning('绑定结果尚未核实，请刷新员工列表确认当前接收身份。'); emit('refresh') }
    } else if (status.value !== 'bound') cancelIntent(Number(employee.uid), intentId.value, notifyFailure)
    intentId.value = 0
    url.value = ''
    qrImage.value = ''
}
function open(row: any) {
    if (visible.value) onClosed()
    Object.assign(employee, { ...row })
    visible.value = true
    void regenerate()
}
async function regenerate() {
    const uid = Number(employee.uid)
    const previousId = intentId.value
    const ticket = gate.start()
    currentTicket = ticket
    stopTimers()
    cancelIntent(uid, previousId)
    intentId.value = 0
    url.value = ''
    qrImage.value = ''
    deadline.value = 0
    confirmationUncertain.value = false
    expiryNeedsVerification.value = false
    status.value = 'waiting'
    message.value = ''
    pollError.value = ''
    manualCopy.value = false
    identityConfirmed.value = false
    Object.assign(candidate, { corp_name: '', recipient_id: '', identity_label: '' })
    boundEmitted = false
    generating.value = true
    try {
        const res: any = await getWecomStaffBindUrl(uid)
        const data = res.data || {}
        const id = Number(data.intent_id || 0)
        if (!isCurrent(ticket)) { cancelIntent(uid, id); return }
        const nextUrl = String(data.url || '')
        if (!/^https:\/\//i.test(nextUrl) || !(id > 0) || !(Number(data.expires_in) > 0)) throw new Error('绑定信息不完整或入口已过期，请让平台确认前后端已经同步更新。')
        intentId.value = id
        url.value = nextUrl
        syncDeadline(data.expires_in)
        const qr = await QRCode.toDataURL(nextUrl, { width: 260, margin: 2, errorCorrectionLevel: 'M' })
        if (!isCurrent(ticket)) return
        qrImage.value = qr
        startTicker(ticket)
        void poll(ticket)
    } catch (error: any) {
        if (isCurrent(ticket)) { status.value = 'failed'; message.value = errorMessage(error, '生成绑定码失败，请重试或联系平台管理员。') }
    } finally { if (isCurrent(ticket)) generating.value = false }
}
function startTicker(ticket: number) {
    if (timer) clearInterval(timer)
    timer = setInterval(() => {
        if (!isCurrent(ticket)) return
        now.value = performance.now()
        if (remaining.value === 0 && status.value !== 'bound') {
            if (timer) clearInterval(timer)
            timer = undefined
            expiryNeedsVerification.value = true
            message.value = '正在核对最终状态，请勿将倒计时结束当作绑定失败。'
            // Expiry is decided by the server. A lost confirmation response may already be bound.
            void poll(ticket)
        }
    }, 1000)
}
function syncDeadline(expiresIn: unknown) {
    now.value = performance.now()
    deadline.value = bindingDeadline(expiresIn, now.value)
}
async function poll(ticket: number) {
    if (!isCurrent(ticket) || !intentId.value || ['bound', 'expired', 'failed'].includes(status.value)) return
    if (pollTimer) clearTimeout(pollTimer)
    pollAbort?.abort()
    pollAbort = new AbortController()
    const currentAbort = pollAbort
    checking.value = true
    try {
        const res: any = await getWecomStaffBindStatus(Number(employee.uid), intentId.value, currentAbort.signal)
        if (!isCurrent(ticket) || currentAbort.signal.aborted) return
        const data = res.data || {}
        if (!['waiting', 'scanned', 'bound', 'expired', 'failed'].includes(data.status)) throw new Error('服务未返回有效的绑定状态，请联系平台核对。')
        const nextStatus = bindingStatus(data.status)
        if (String(candidate.recipient_id) !== String(data.candidate?.recipient_id || '')) identityConfirmed.value = false
        Object.assign(candidate, { corp_name: '', recipient_id: '', identity_label: '' }, data.candidate || {})
        status.value = nextStatus
        confirmationUncertain.value = bindingOutcomeStillUncertain(nextStatus, confirmationUncertain.value)
        message.value = confirmationUncertain.value ? '确认结果待核实，请等待状态刷新或核对员工列表。' : String(data.message || '')
        pollError.value = ''
        if (nextStatus === 'bound') { markBound(); return }
        if (['expired', 'failed'].includes(nextStatus)) { expiryNeedsVerification.value = false; stopTimers(); return }
        syncDeadline(data.expires_in)
        expiryNeedsVerification.value = remaining.value === 0
        if (remaining.value > 0 && !timer) startTicker(ticket)
        pollTimer = setTimeout(() => void poll(ticket), 2500)
    } catch (error: any) {
        if (isCurrent(ticket) && !currentAbort.signal.aborted) pollError.value = `${errorMessage(error, '网络暂时未响应')}；${confirmationUncertain.value || expiryNeedsVerification.value ? '最终结果待核实，请重新检查状态或刷新员工列表。' : '自动检查已暂停，请重新检查状态。'}`
    } finally { if (isCurrent(ticket) && !currentAbort.signal.aborted) checking.value = false }
}
function checkAgain() { if (!checking.value) void poll(currentTicket) }
async function confirmBinding() {
    if (status.value !== 'scanned' || !identityConfirmed.value || !candidate.recipient_id || remaining.value <= 0 || confirming.value || confirmationUncertain.value || expiryNeedsVerification.value) return
    const ticket = currentTicket
    confirming.value = true
    stopTimers()
    try {
        const res: any = await confirmWecomStaffBinding(Number(employee.uid), intentId.value)
        if (!isCurrent(ticket)) return
        if (res.data?.status !== 'bound') throw new Error('服务未返回绑定成功状态，请重新检查后再试。')
        markBound()
    } catch (error: any) {
        if (isCurrent(ticket)) {
            confirmationUncertain.value = true
            pollError.value = `${errorMessage(error, '确认请求未取得明确结果')}；绑定结果待核实。`
            now.value = performance.now()
            expiryNeedsVerification.value = remaining.value === 0
            if (remaining.value > 0) startTicker(ticket)
            void poll(ticket)
        }
    } finally { if (isCurrent(ticket)) confirming.value = false }
}
async function copyLink() {
    if (!url.value || remaining.value <= 0) return
    try { await navigator.clipboard.writeText(url.value); ElMessage.success('已复制，请仅单独发送给这名员工本人') }
    catch { manualCopy.value = true; ElMessage.warning('请手动选择下方专属链接复制，仅发送给本人') }
}
onBeforeUnmount(() => onClosed(false))
defineExpose({ open })
</script>

<style scoped lang="scss">
.binding-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-bottom: 14px; }.binding-summary strong { font-size: 17px; }.binding-summary span { color: var(--el-text-color-secondary); }
.binding-body { display: flex; gap: 20px; align-items: center; min-height: 270px; padding: 16px 0; }.qr-panel { width: 220px; height: 220px; flex: 0 0 220px; display: grid; place-items: center; background: white; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }.qr-panel img { display: block; }
.binding-instruction { flex: 1; min-width: 0; }.binding-instruction strong { line-height: 1.6; }.binding-instruction p, .identity-review p { color: var(--el-text-color-secondary); font-size: 13px; line-height: 1.7; margin: 8px 0; }.expiry { font-size: 12px; line-height: 1.6; color: var(--el-text-color-secondary); margin: 12px 0; }.manual-link { margin-top: 8px; }
.identity-review { width: 100%; }.identity-review h3 { margin: 12px 0; font-size: 16px; }.identity-review dl { display: grid; grid-template-columns: 80px minmax(0, 1fr); gap: 8px 12px; padding: 14px; background: var(--el-fill-color-light); border-radius: 8px; font-size: 13px; }.identity-review dt { color: var(--el-text-color-secondary); }.identity-review dd { margin: 0; overflow-wrap: anywhere; }.identity-review :deep(.el-checkbox) { height: auto; white-space: normal; align-items: flex-start; }.identity-review :deep(.el-checkbox__label) { white-space: normal; line-height: 1.5; }.identity-review :deep(.el-checkbox__input) { margin-top: 4px; }
.result-panel { width: 100%; }.result-panel :deep(.el-result) { padding: 16px; }.binding-footer { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }.binding-footer :deep(.el-button) { margin-left: 0; }.state-message { margin-top: 8px; font-size: 13px; color: var(--el-text-color-secondary); }
@media (max-width: 640px) { .binding-body { flex-direction: column; }.binding-instruction { width: 100%; text-align: center; } }
</style>
