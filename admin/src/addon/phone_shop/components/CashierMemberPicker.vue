<template>
    <div class="cashier-member-picker">
        <div v-if="!selected" class="member-entry">
            <div class="mode-switch" aria-label="会员识别方式">
                <button type="button" :class="['mode-switch__item', { 'is-active': mode === 'search' }]" @click="switchMode('search')">
                    <el-icon><Search /></el-icon><span>搜索</span>
                </button>
                <button type="button" :class="['mode-switch__item', { 'is-active': mode === 'scan' }]" @click="switchMode('scan')">
                    <el-icon><FullScreen /></el-icon><span>扫码</span>
                </button>
            </div>

            <el-select
                v-if="mode === 'search'"
                v-model="picked"
                filterable
                remote
                clearable
                :remote-method="searchMembers"
                :loading="loading"
                placeholder="昵称 / 手机号 / 会员号"
                class="picker-control"
                @change="onSearchPick"
                @visible-change="visible => visible && !options.length && searchMembers('')"
            >
                <el-option v-for="item in options" :key="item.member_id" :value="Number(item.member_id)" :label="memberLabel(item)">
                    <div class="option-row">
                        <span class="option-name">{{ memberName(item) }}</span>
                        <span class="option-mobile">{{ item.mobile || '未留手机号' }}</span>
                        <span class="option-id">ID {{ item.member_id }}</span>
                    </div>
                </el-option>
            </el-select>

            <el-input
                v-else
                ref="scanInputRef"
                v-model="scanValue"
                clearable
                inputmode="numeric"
                autocomplete="off"
                placeholder="扫描或输入完整会员 ID"
                class="picker-control"
                @input="onScanInput"
                @keyup.enter="resolveExactMember"
                @clear="clearMember"
            >
                <template #append>
                    <el-button :icon="Search" :loading="loading" title="精确查询" @click="resolveExactMember" />
                </template>
            </el-input>
        </div>

        <div v-if="scanMessage && !selected" class="picker-message is-error">{{ scanMessage }}</div>

        <div v-if="selected" class="member-confirm">
            <div class="member-avatar">
                <el-avatar v-if="selected.headimg" :src="img(selected.headimg)" :size="36" />
                <el-icon v-else><User /></el-icon>
            </div>
            <div class="member-main">
                <div class="member-title">
                    <strong>{{ memberName(selected) }}</strong>
                    <span class="member-verified"><el-icon><CircleCheckFilled /></el-icon>已核验</span>
                </div>
                <div class="member-meta">
                    <span>{{ selected.mobile || '未留手机号' }}</span>
                    <span>ID {{ selected.member_id }}</span>
                    <span>{{ levelName(selected) || '普通用户' }}</span>
                </div>
            </div>
            <el-button link type="primary" class="member-change" @click="clearMember">更换</el-button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { CircleCheckFilled, FullScreen, Search, User } from '@element-plus/icons-vue'
import { getMemberInfo, getMemberList } from '@/app/api/member'
import { img } from '@/utils/common'

const props = withDefaults(defineProps<{
    modelValue?: number
}>(), {
    modelValue: 0
})

const emit = defineEmits(['update:modelValue', 'change'])

const mode = ref<'search' | 'scan'>('search')
const picked = ref<number | undefined>(props.modelValue || undefined)
const selected = ref<any>(null)
const options = ref<any[]>([])
const scanValue = ref('')
const scanMessage = ref('')
const loading = ref(false)
const scanInputRef = ref<any>()

const memberName = (item: any) => item?.nickname || item?.username || item?.mobile || `会员#${item?.member_id || ''}`
const levelName = (item: any) => {
    return String(item?.member_level_name || item?.member_level_name_bind?.level_name || '').trim()
}
const memberLabel = (item: any) => [memberName(item), item?.mobile, `ID ${item?.member_id}`].filter(Boolean).join(' · ')

async function searchMembers (keyword: string) {
    loading.value = true
    try {
        const response: any = await getMemberList({ keyword: String(keyword || '').trim(), page: 1, limit: 20 })
        options.value = response?.data?.data || response?.data?.list || []
    } catch (error: any) {
        options.value = []
        ElMessage.error(error?.msg || error?.message || '会员查询失败')
    } finally {
        loading.value = false
    }
}

function commitMember (member: any) {
    const memberId = Number(member?.member_id || 0)
    if (!memberId) return clearMember()
    selected.value = member
    picked.value = memberId
    scanValue.value = String(memberId)
    scanMessage.value = ''
    if (!options.value.some(item => Number(item.member_id) === memberId)) options.value.unshift(member)
    emit('update:modelValue', memberId)
    emit('change', member)
    if (mode.value === 'scan') nextTick(() => scanInputRef.value?.select?.())
}

function onSearchPick (memberId?: number) {
    if (!memberId) return clearMember()
    const member = options.value.find(item => Number(item.member_id) === Number(memberId))
    if (member) commitMember(member)
}

function invalidateMember (message = '') {
    picked.value = undefined
    selected.value = null
    scanMessage.value = message
    emit('update:modelValue', undefined)
    emit('change', null)
}

function onScanInput (value: string) {
    scanMessage.value = ''
    if (selected.value && String(selected.value.member_id) !== String(value || '').trim()) {
        invalidateMember()
    }
}

async function resolveExactMember () {
    const raw = scanValue.value.trim()
    scanMessage.value = ''
    if (!/^\d+$/.test(raw) || Number(raw) <= 0) {
        invalidateMember('请输入完整的数字会员 ID')
        return
    }
    // 不接受 001 这类非标准写法，避免操作员误以为是其他业务编号。
    if (String(Number(raw)) !== raw) {
        invalidateMember('会员 ID 格式不正确，请重新扫描或完整输入')
        return
    }
    loading.value = true
    try {
        const response: any = await getMemberInfo(Number(raw))
        const member = response?.data || null
        if (!member || String(member.member_id) !== raw) {
            invalidateMember(`未找到会员 ID ${raw}，请核对后重试`)
            return
        }
        commitMember(member)
    } catch (error: any) {
        invalidateMember(error?.msg || error?.message || `未找到会员 ID ${raw}`)
    } finally {
        loading.value = false
    }
}

function clearMember () {
    picked.value = undefined
    selected.value = null
    scanValue.value = ''
    scanMessage.value = ''
    emit('update:modelValue', undefined)
    emit('change', null)
    if (mode.value === 'scan') nextTick(() => scanInputRef.value?.focus?.())
}

function switchMode (value: 'search' | 'scan') {
    mode.value = value
    scanMessage.value = ''
    if (mode.value === 'scan') {
        scanValue.value = selected.value ? String(selected.value.member_id) : ''
        nextTick(() => {
            scanInputRef.value?.focus?.()
            scanInputRef.value?.select?.()
        })
    }
}

watch(() => props.modelValue, async value => {
    const memberId = Number(value || 0)
    if (!memberId) {
        picked.value = undefined
        selected.value = null
        return
    }
    if (Number(selected.value?.member_id || 0) === memberId) return
    try {
        const response: any = await getMemberInfo(memberId)
        if (response?.data) commitMember(response.data)
    } catch (_) { /* 外部值无效时交由父组件处理 */ }
}, { immediate: true })
</script>

<style scoped lang="scss">
.cashier-member-picker { width: 100%; min-width: 0; }
.member-entry { display: flex; align-items: stretch; width: 100%; }
.mode-switch { display: flex; flex: none; padding: 3px; border: 1px solid var(--el-border-color); border-right: 0; border-radius: 7px 0 0 7px; background: var(--el-fill-color-light); }
.mode-switch__item { display: inline-flex; align-items: center; justify-content: center; gap: 3px; min-width: 48px; padding: 0 7px; border: 0; border-radius: 5px; color: var(--el-text-color-secondary); background: transparent; font-size: 11px; cursor: pointer; transition: .15s ease; }
.mode-switch__item:hover { color: var(--el-color-primary); }
.mode-switch__item.is-active { color: var(--el-color-primary); background: #fff; box-shadow: 0 1px 4px rgba(15, 23, 42, .08); font-weight: 600; }
.picker-control { flex: 1; width: 0; }
.picker-control :deep(.el-input__wrapper) { min-height: 38px; border-radius: 0 7px 7px 0; box-shadow: 0 0 0 1px var(--el-border-color) inset; }
.picker-control :deep(.el-input-group__append) { border-radius: 0 7px 7px 0; }
.option-row { display: flex; align-items: center; gap: 8px; min-width: 0; }
.option-name { color: var(--el-text-color-primary); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.option-mobile { color: var(--el-text-color-secondary); }
.option-id { margin-left: auto; color: var(--el-text-color-placeholder); font-size: 12px; }
.picker-message { margin-top: 5px; padding-left: 4px; color: var(--el-text-color-placeholder); font-size: 11px; line-height: 1.45; }
.picker-message.is-error { color: var(--el-color-danger); }
.member-confirm { display: flex; align-items: center; gap: 9px; min-height: 52px; padding: 7px 9px; border: 1px solid var(--el-color-success-light-7); border-radius: 8px; background: linear-gradient(90deg, var(--el-color-success-light-9), #fff); }
.member-avatar { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: none; border-radius: 50%; color: var(--el-color-primary); background: #fff; box-shadow: 0 0 0 1px var(--el-border-color-lighter); font-size: 19px; }
.member-main { flex: 1; min-width: 0; }
.member-title { display: flex; align-items: center; gap: 6px; min-width: 0; }
.member-title strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
.member-verified { display: inline-flex; align-items: center; gap: 2px; flex: none; color: var(--el-color-success); font-size: 10px; font-weight: 500; }
.member-meta { display: flex; align-items: center; gap: 0; margin-top: 4px; color: var(--el-text-color-secondary); font-size: 11px; white-space: nowrap; overflow: hidden; }
.member-meta span { overflow: hidden; text-overflow: ellipsis; }
.member-meta span + span::before { content: '·'; padding: 0 5px; color: var(--el-border-color-darker); }
.member-change { flex: none; padding: 4px; font-size: 11px; }
</style>
