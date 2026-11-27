<template>
    <HsxDialog :confirm-loading="saving" :model-value="modelValue" :title="`客户信用设置 · ${party?.party_name || party?.name || ''}`" width="560px" append-to-body destroy-on-close @close="close">
        <div v-loading="loading">
            <HsxNotice default-expanded
                v-if="profile.has_outstanding"
                class="mb-4"
                type="warning"
                :closable="false"
                show-icon
                :title="`${profile.outstanding_count} 笔未结应收，共 ¥${money(profile.outstanding_amount)}，最早 ${profile.oldest_days} 天`"
            />
            <HsxNotice default-expanded v-else class="mb-4" type="success" :closable="false" show-icon title="当前没有未结应收" />
            <el-form label-width="92px">
                <el-form-item label="交易策略">
                    <el-radio-group v-model="form.credit_policy" class="credit-policy-list">
                        <el-radio label="inherit">跟随系统规则</el-radio>
                        <el-radio label="normal">正常交易，不提醒</el-radio>
                        <el-radio label="remind">存在欠款时提醒</el-radio>
                        <el-radio label="cash_only">仅允许全额现结</el-radio>
                        <el-radio label="blocked">暂停交易</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="信用额度">
                    <el-input-number v-model="form.credit_limit" :min="0" :precision="2" :controls="false" class="!w-[220px]" />
                    <span class="ml-3 text-sm text-gray-500">0 表示不限额</span>
                </el-form-item>
                <el-form-item label="设置原因">
                    <el-input v-model.trim="form.credit_remark" type="textarea" :rows="3" maxlength="255" show-word-limit placeholder="例如历史欠款、只接受现结等" />
                </el-form-item>
            </el-form>
            <div v-if="profile.credit_update_at" class="text-xs text-gray-400">
                最近调整：{{ profile.credit_update_name || '-' }} · {{ formatTime(profile.credit_update_at) }}
            </div>
        </div>
        <template #footer>
            <el-button :disabled="saving" @click="close">取消</el-button>
            <el-button :disabled="saving" type="primary" :loading="saving" @click="save">保存信用策略</el-button>
        </template>
    </HsxDialog>
</template>

<script setup lang="ts">
import { HsxDialog, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { reactive, ref, watch } from 'vue'

import { getErpPartyCredit, updateErpPartyCredit } from '@/addon/hsx_erp/api/counterparty'
const hsxFeedback = useFeedback()


const props = defineProps<{ modelValue: boolean; party?: any }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void; (e: 'saved', profile: any): void }>()
const loading = ref(false)
const saving = ref(false)
const profile = ref<any>({})
const form = reactive({ credit_policy: 'inherit', credit_limit: 0, credit_remark: '' })

watch(() => props.modelValue, visible => { if (visible) load() })

async function load() {
    const partyId = Number(props.party?.party_id || props.party?.id || 0)
    if (!partyId) return
    loading.value = true
    try {
        const res: any = await getErpPartyCredit(partyId)
        applyProfile(res?.data || {})
    } finally { loading.value = false }
}

function applyProfile(data: any) {
    profile.value = data
    form.credit_policy = data.configured_policy || 'inherit'
    form.credit_limit = Number(data.credit_limit || 0)
    form.credit_remark = data.credit_remark || ''
}

async function save() {
    const partyId = Number(props.party?.party_id || props.party?.id || 0)
    if (!partyId) return hsxFeedback.warning('请先选择客户')
    saving.value = true
    try {
        const res: any = await updateErpPartyCredit(partyId, { ...form })
        applyProfile(res?.data || {})
        emit('saved', profile.value)
        hsxFeedback.success('客户信用策略已保存')
        close()
    } finally { saving.value = false }
}

function close() { emit('update:modelValue', false) }
const money = (value: any) => Number(value || 0).toFixed(2)
const formatTime = (value: any) => Number(value || 0) > 0 ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '-'
</script>

<style scoped>
.credit-policy-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 18px; width: 100%; }
</style>
