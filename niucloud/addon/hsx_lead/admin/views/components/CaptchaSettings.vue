<template>
    <HsxDrawer
        v-model="visible"
        title="自动登录设置"
        subtitle="本站共享 · 仅用于本次交互式登录"
        size="sm"
        :body-loading="loading"
        :confirm-loading="saving"
    >
        <div class="lead-captcha-settings">
            <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" />
            <el-alert v-if="config.notice" :title="config.notice" type="info" :closable="false" />
            <el-form label-position="top" :disabled="loading || saving">
                <el-form-item label="自动识别验证码"
                    ><el-switch v-model="form.enabled" /><span class="setting-caption"
                        >默认关闭；未启用时仍可手动填写验证码。</span
                    ></el-form-item
                >
                <el-form-item label="2captcha API Key" :required="form.enabled && !config.configured">
                    <el-input
                        v-model="form.api_key"
                        type="password"
                        show-password
                        autocomplete="new-password"
                        :maxlength="32"
                        :placeholder="config.configured ? '已保存；留空保留，仅输入新值时替换' : '填写本站 32 位 2captcha API Key'"
                    />
                    <p class="setting-caption">{{ keyStatus }}</p>
                    <el-checkbox
                        v-if="config.configured"
                        v-model="form.clear_key"
                        @change="value => { if (value) form.enabled = false }"
                        >清除密钥并关闭自动识别</el-checkbox
                    >
                </el-form-item>
                <el-form-item label="每日提交次数上限"
                    ><el-input-number
                        v-input-number-a11y
                        v-model="form.daily_limit"
                        :min="1"
                        :max="1000"
                        :precision="0"
                    /><p class="setting-caption"
                        >今日已用 {{ config.used_today ?? '—' }} 次，剩余
                        {{ config.remaining_today ?? '—' }} 次。包含失败提交，不代表实际扣费金额。</p
                    ></el-form-item
                >
                <div class="setting-consent"
                    ><el-checkbox v-model="consent">我同意启用第三方验证码识别</el-checkbox
                    ><p
                        >仅把验证码图片发送给 2captcha，可能由人工识别，按识别次数扣费。ERP
                        密码不发送给识别服务，只在本次登录任务中短期加密保存；关闭或停止任务不退还已提交的识别费用。</p
                    ></div
                >
            </el-form>
            <div class="setting-balance"
                ><el-button
                    :loading="checking"
                    :disabled="!config.configured || saving || loading || !!form.api_key || form.clear_key"
                    @click="balance"
                    >查询已保存账号余额</el-button
                ><span v-if="balanceText">{{ balanceText }}</span
                ><p class="setting-caption">余额查询不提交验证码、不触发识别。修改密钥后请先保存。</p></div
            >
            <el-collapse
                ><el-collapse-item title="接入与费用说明"
                    ><p
                        >在 2captcha 后台获取 API
                        Key，并自行确认计费和隐私规则。本站使用异步查询，不需要填写公网回调地址。验证码识别成功不代表
                        ERP 登录成功，也不会自动启用商户接入。</p
                    ><a href="https://2captcha.com/api-docs/normal-captcha" target="_blank" rel="noopener noreferrer"
                        >查看 2captcha 官方说明 ↗</a
                    ></el-collapse-item
                ></el-collapse
            >
        </div>
        <template #footer
            ><el-button :disabled="saving" @click="visible = false">取消</el-button
            ><el-button type="primary" :loading="saving" :disabled="loading" @click="save"
                >保存设置</el-button
            ></template
        >
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import * as api from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
import { vInputNumberA11y } from '../../directives/inputNumberA11y'
const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void, (e: 'changed', value: Row): void }>()
const visible = computed({ get: () => props.modelValue, set: value => { if (!value) reset(); emit('update:modelValue', value) } })
const loading = ref(false), saving = ref(false), checking = ref(false), error = ref(''), consent = ref(false), config = ref<Row>({}), balanceText = ref('')
const form = reactive({ enabled: false, api_key: '', clear_key: false, daily_limit: 20 })
const keyStatus = computed(() => config.value.configured ? `${String(config.value.key_hint || '').startsWith('已配置') ? config.value.key_hint : `已配置 ${config.value.key_hint || '••••••••'}`}，不会回显完整密钥。` : '尚未配置识别服务密钥。')
let sequence = 0
function reset() { sequence++; form.api_key = ''; form.clear_key = false; consent.value = false; error.value = ''; balanceText.value = ''; loading.value = false; checking.value = false; saving.value = false }
function accept(payload: Row) {
    // Explicit public fields only; never retain a plaintext key returned by a misconfigured endpoint.
    config.value = Object.fromEntries(['provider', 'enabled', 'configured', 'key_hint', 'daily_limit', 'used_today', 'remaining_today', 'revision', 'notice'].map(key => [key, payload[key]]))
    form.enabled = payload.enabled === true; form.daily_limit = Number(payload.daily_limit || 20); form.api_key = ''; form.clear_key = false
}
async function load() { reset(); const token = sequence; loading.value = true; try { const result = await api.getCaptchaConfig(); if (token === sequence && visible.value) accept(result.data || {}) } catch (cause) { if (token === sequence) error.value = errorText(cause, '无权读取或设置暂不可用；可关闭后使用手动验证码。') } finally { if (token === sequence) loading.value = false } }
async function save() {
    if (saving.value || loading.value) return
    if (form.enabled && !consent.value) { error.value = '请先确认第三方识别、费用及隐私说明。'; return }
    if (form.api_key && !/^[a-f0-9]{32}$/i.test(form.api_key)) { error.value = 'API Key 应为 32 位十六进制字符。'; return }
    if (form.clear_key && form.api_key) { error.value = '清除密钥与替换新密钥不能同时进行。'; return }
    if (form.enabled && (form.clear_key && !form.api_key || !config.value.configured && !form.api_key)) { error.value = '启用前请填写识别服务 API Key。'; return }
    if (!Number.isInteger(form.daily_limit) || form.daily_limit < 1 || form.daily_limit > 1000) { error.value = '每日上限请填写 1–1000 的整数。'; return }
    const token = sequence; saving.value = true; error.value = ''
    try { const result = await api.saveCaptchaConfig({ ...form, consent: consent.value, ...(typeof config.value.revision === 'string' ? { revision: config.value.revision } : {}) }); if (token !== sequence || !visible.value) return; accept(result.data || {}); consent.value = false; emit('changed', { ...config.value }); visible.value = false }
    catch (cause) { if (token === sequence) { form.api_key = ''; error.value = errorText(cause, '设置未保存，请核对权限与输入。') } }
    finally { if (token === sequence) saving.value = false }
}
async function balance() {
    if (checking.value || !config.value.configured || form.api_key || form.clear_key) return
    const token = sequence; checking.value = true; error.value = ''; balanceText.value = ''
    try { const result = await api.checkCaptchaBalance(); if (token !== sequence || !visible.value) return; const data = result.data || {}, note = data.notice || data.message; balanceText.value = data.balance !== undefined && data.balance !== null ? `余额 ${String(data.balance)}${data.currency ? ` ${String(data.currency)}` : ''}${note ? ` · ${note}` : ''}` : '服务商未返回余额' }
    catch (cause) { if (token === sequence) error.value = errorText(cause, '余额查询失败，不影响手动登录。') }
    finally { if (token === sequence) checking.value = false }
}
watch(() => props.modelValue, value => { if (value) void load(); else reset() }, { immediate: true, flush: 'sync' })
onBeforeUnmount(reset)
</script>
<style scoped>
.lead-captcha-settings { display: grid; gap: 16px; color: #303133; background: #fff; }
.lead-captcha-settings :deep(.el-form-item) { margin-bottom: 18px; }
.setting-caption { margin: 6px 0 0; color: #737b89; font-size: 12px; line-height: 1.7; }
span.setting-caption { margin-left: 10px; }
.setting-consent { padding: 12px; border: 1px solid #e5e9ef; border-radius: 6px; background: #f8fafc; }
.setting-consent p, .lead-captcha-settings :deep(.el-collapse-item__content) { margin: 6px 0 0; font-size: 12px; line-height: 1.8; color: #687284; }
.setting-balance > span { display: block; margin-top: 8px; font-size: 13px; }
</style>
