<template>
    <HsxDrawer
        v-model="visible"
        title="支付帮助"
        subtitle="在会员开通、协作付佣和付款单中显示本站客服信息"
        size="md"
        :body-loading="loading"
        :show-footer="true"
        :confirm-loading="saving"
        :confirm-disabled="!ready || loading || readRequired || !canEdit"
        @confirm="save"
    >
        <el-alert
            v-if="error"
            :title="error"
            :type="readRequired ? 'warning' : 'error'"
            :closable="false"
            show-icon
            class="payment-help-alert"
        >
            <template #default
                ><el-button
                    link
                    type="primary"
                    :disabled="saving || loading"
                    @click="readRequired ? reconcile() : reload()"
                    >{{ readRequired ? '核对已保存配置' : '重新读取' }}</el-button
                ></template
            >
        </el-alert>
        <el-alert
            v-if="verifiedMessage"
            :title="verifiedMessage"
            type="info"
            :closable="false"
            show-icon
            class="payment-help-alert"
        />
        <template v-if="ready">
            <el-alert
                v-if="draft.enabled && !draft.qr_image"
                title="当前二维码不可用，请重新选择本站图片，或关闭支付帮助。"
                type="warning"
                :closable="false"
                show-icon
                class="payment-help-alert"
            />
            <el-alert
                v-if="!canEdit"
                title="当前账号可查看，不能修改支付帮助"
                type="info"
                :closable="false"
                show-icon
                class="payment-help-alert"
            />
            <el-form
                ref="formRef"
                :model="draft"
                label-position="top"
                :disabled="saving || loading || readRequired || !canEdit"
                @submit.prevent
            >
                <el-form-item label="显示支付帮助"
                    ><el-switch v-model="draft.enabled" active-text="开启" inactive-text="关闭"
                /></el-form-item>
                <el-form-item label="标题" :error="fieldError.title"
                    ><el-input v-model="draft.title" maxlength="40" show-word-limit placeholder="支付帮助"
                /></el-form-item>
                <el-form-item label="提醒内容" :required="draft.enabled" :error="fieldError.content"
                    ><el-input
                        v-model="draft.content"
                        type="textarea"
                        :rows="4"
                        maxlength="600"
                        show-word-limit
                        placeholder="填写需要向用户展示的信息"
                /></el-form-item>
                <el-form-item label="客服二维码" :required="draft.enabled" :error="fieldError.qr_image">
                    <UploadImage
                        v-if="canEdit && !saving && !loading && !readRequired"
                        v-model="draft.qr_image"
                        :limit="1"
                        width="112px"
                        height="112px"
                        image-text="选择二维码"
                    />
                    <el-image
                        v-else-if="draft.qr_image"
                        :src="img(draft.qr_image)"
                        :preview-src-list="[img(draft.qr_image)]"
                        fit="contain"
                        style="width:112px;height:112px"
                    />
                    <span v-else class="payment-help-note">尚未设置二维码</span>
                    <p class="payment-help-note">从本站图片库选择或上传，用户可点开查看大图。</p>
                </el-form-item>
                <el-form-item label="添加好友备注" :error="fieldError.remark"
                    ><el-input
                        v-model="draft.remark"
                        type="textarea"
                        :rows="2"
                        maxlength="120"
                        show-word-limit
                        placeholder="选填，用户可复制这段备注"
                /></el-form-item>
            </el-form>
            <el-collapse class="payment-help-explanation"
                ><el-collapse-item title="查看显示范围与操作说明" name="help"
                    ><p class="payment-help-note"
                        >关闭后，商户端不再显示此入口，已保存的内容保留。这里只展示客服信息，不会改变付款单、开通权益或确认收款；标题、内容和备注按纯文本显示。</p
                    ></el-collapse-item
                ></el-collapse
            >
        </template>
        <template #footer
            ><el-button :disabled="saving" @click="visible=false">关闭</el-button
            ><el-button
                v-if="canEdit"
                type="primary"
                :disabled="!ready || loading || readRequired"
                :loading="saving"
                @click="save"
                >保存支付帮助</el-button
            ></template
        >
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import UploadImage from '@/components/upload-image/index.vue'
import { img } from '@/utils/common'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import { getPaymentHelpSettings, savePaymentHelpSettings, type PaymentHelpSettings as HelpSettings } from '../../api/paymentHelp'
import { coopError } from '../../composables/cooperation'
const props = withDefaults(defineProps<{ modelValue: boolean; canEdit?: boolean }>(), { canEdit: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const user = useUserStore(), loading = ref(false), saving = ref(false), ready = ref(false), readRequired = ref(false), error = ref(''), verifiedMessage = ref(''), formRef = ref<any>(null)
const empty = (): HelpSettings => ({ enabled: false, title: '支付帮助', content: '', qr_image: '', remark: '' })
const draft = reactive<HelpSettings>(empty()), fieldError = reactive({ title: '', content: '', qr_image: '', remark: '' })
const visible = computed({ get: () => props.modelValue, set: value => { if (!saving.value) emit('update:modelValue', value) } })
const identity = () => `${user.siteInfo?.site_id || ''}:${user.token || ''}`
let sequence = 0, disposed = false, actor = identity(), baseline = '', attempted = ''
const current = (who: string, turn: number) => !disposed && props.modelValue && who === identity() && actor === who && turn === sequence
const serialize = (data: HelpSettings) => JSON.stringify({ enabled: data.enabled, title: data.title, content: data.content, qr_image: data.qr_image, remark: data.remark })
function normalize(data: any): HelpSettings {
    if (!data || typeof data.enabled !== 'boolean' || ['title', 'content', 'qr_image', 'remark'].some(key => typeof data[key] !== 'string')) throw Error('支付帮助返回不完整，请核对配套后端后重新读取')
    return { enabled: data.enabled, title: data.title, content: data.content, qr_image: data.qr_image, remark: data.remark }
}
function clearFieldErrors() { Object.keys(fieldError).forEach(key => fieldError[key as keyof typeof fieldError] = '') }
async function read(verify = false) {
    if (loading.value || saving.value || !props.modelValue) return
    const turn = ++sequence, who = identity(); loading.value = true; error.value = ''; verifiedMessage.value = ''
    try {
        const response = await getPaymentHelpSettings()
        if (!current(who, turn)) return
        const saved = normalize(response.data), savedText = serialize(saved)
        baseline = savedText; ready.value = true; readRequired.value = false
        if (verify && savedText !== attempted) verifiedMessage.value = '已核对服务器配置；当前草稿已保留，请确认后再保存。'
        else { Object.assign(draft, saved); if (verify) verifiedMessage.value = '已核对，服务器已保存本次配置。' }
        attempted = ''; clearFieldErrors()
    } catch (cause) { if (current(who, turn)) error.value = coopError(cause, '支付帮助读取失败，请重试') }
    finally { if (turn === sequence && !disposed) loading.value = false }
}
async function reload() {
    if (loading.value || saving.value) return
    const who = identity(), turn = sequence
    if (ready.value && serialize(draft) !== baseline) {
        try { await ElMessageBox.confirm('重新读取将替换当前未保存的支付帮助内容。', '保留草稿或重新读取', { confirmButtonText: '重新读取', cancelButtonText: '继续编辑', type: 'warning' }) } catch { return }
    }
    if (!current(who, turn)) return
    await read()
}
async function reconcile() { await read(true) }
function validation() {
    clearFieldErrors()
    for (const [key, max, label] of [['title', 40, '标题'], ['content', 600, '提醒内容'], ['qr_image', 1000, '二维码'], ['remark', 120, '添加好友备注']] as const) if ([...draft[key]].length > max) fieldError[key] = `${label}不能超过${max}个字符`
    if (draft.enabled && !draft.content.trim()) fieldError.content = '开启后请填写提醒内容'
    if (draft.enabled && !draft.qr_image.trim()) fieldError.qr_image = '开启后请选择本站客服二维码'
    return !Object.values(fieldError).some(Boolean)
}
async function save() {
    if (loading.value || saving.value || !ready.value || readRequired.value || !props.canEdit || !props.modelValue || !validation()) return
    const who = identity(), turn = sequence, payload: HelpSettings = { enabled: draft.enabled, title: draft.title.trim() || '支付帮助', content: draft.content.trim(), qr_image: draft.qr_image.trim(), remark: draft.remark.trim() }
    saving.value = true; error.value = ''; verifiedMessage.value = ''; attempted = serialize(payload)
    try {
        const response = await savePaymentHelpSettings(payload)
        if (!current(who, turn)) return
        const saved = normalize(response.data); Object.assign(draft, saved); baseline = serialize(saved); attempted = ''; ElMessage.success('支付帮助已保存')
    } catch (cause) {
        if (current(who, turn)) { readRequired.value = true; error.value = coopError(cause, '保存结果尚未确认，草稿已保留，请先核对已保存配置') }
    } finally { if (turn === sequence && !disposed) saving.value = false }
}
watch(() => [props.modelValue, identity()], () => {
    sequence++; loading.value = false; saving.value = false
    if (actor !== identity()) { actor = identity(); Object.assign(draft, empty()); ready.value = false; readRequired.value = false; baseline = ''; attempted = ''; error.value = ''; verifiedMessage.value = ''; clearFieldErrors() }
    if (props.modelValue && !ready.value) void read()
}, { immediate: true })
onBeforeUnmount(() => { disposed = true; sequence++ })
</script>
<style scoped>
.payment-help-alert{margin-bottom:16px}.payment-help-note{width:100%;margin:8px 0 0;font-size:12px;line-height:1.8;color:var(--el-text-color-secondary)}.payment-help-upload.is-readonly{pointer-events:none;opacity:.7}.payment-help-explanation{margin-top:8px}
</style>
