<template>
    <div class="next-assignee" :class="{ 'is-compact': compact }">
        <div class="next-assignee__copy">
            <span class="next-assignee__label">{{ label }}</span>
            <span v-if="!compact" class="next-assignee__hint">提交后自动生成对方待办并发送企业微信通知</span>
        </div>
        <el-select
            :model-value="modelValue"
            :loading="loading"
            :disabled="disabled || loading || users.length === 0"
            placeholder="按岗位自动选择"
            class="next-assignee__select"
            @update:model-value="handleChange"
        >
            <el-option v-for="user in users" :key="user.uid" :label="user.name" :value="Number(user.uid)">
                <div class="next-assignee__option">
                    <span>{{ user.name }}</span>
                    <el-tag v-if="user.is_default" size="small" type="info" effect="plain">默认</el-tag>
                </div>
            </el-option>
        </el-select>
        <span v-if="!loading && users.length === 0" class="next-assignee__empty">暂无对应岗位员工，将使用系统兜底规则</span>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getAssignableUsers } from '@/addon/hsx_recycle/api/task'

const props = withDefaults(defineProps<{
    modelValue: number
    stageKey: string
    label?: string
    compact?: boolean
    disabled?: boolean
}>(), {
    label: '下一环节负责人',
    compact: false,
    disabled: false
})

const emit = defineEmits<{ (event: 'update:modelValue', value: number): void }>()
const users = ref<any[]>([])
const loading = ref(false)
const storageKey = () => `hsx_recycle:last_assignee:${props.stageKey}`

const chooseInitial = () => {
    if (!users.value.length) return
    const current = Number(props.modelValue || 0)
    if (users.value.some(user => Number(user.uid) === current)) return
    const cached = Number(window.localStorage.getItem(storageKey()) || 0)
    const selected = users.value.find(user => Number(user.uid) === cached)
        || users.value.find(user => Number(user.is_default) === 1)
        || users.value[0]
    emit('update:modelValue', Number(selected.uid))
}

const loadUsers = async () => {
    if (!props.stageKey) return
    loading.value = true
    try {
        const response: any = await getAssignableUsers(props.stageKey)
        users.value = Array.isArray(response?.data) ? response.data : []
        chooseInitial()
    } catch {
        users.value = []
    } finally {
        loading.value = false
    }
}

const handleChange = (value: number) => {
    const uid = Number(value || 0)
    emit('update:modelValue', uid)
    if (uid > 0) window.localStorage.setItem(storageKey(), String(uid))
}

watch(() => props.stageKey, loadUsers, { immediate: true })
watch(() => props.modelValue, chooseInitial)
</script>

<style scoped lang="scss">
.next-assignee {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border: 1px solid var(--el-border-color-lighter);
    background: var(--el-fill-color-extra-light);
    border-radius: 6px;
}
.next-assignee__copy { min-width: 190px; display: flex; flex-direction: column; gap: 2px; }
.next-assignee__label { color: var(--el-text-color-primary); font-weight: 600; }
.next-assignee__hint, .next-assignee__empty { color: var(--el-text-color-secondary); font-size: 12px; }
.next-assignee__select { width: 220px; }
.next-assignee__option { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.next-assignee.is-compact { padding: 0; border: 0; background: transparent; }
.next-assignee.is-compact .next-assignee__copy { min-width: auto; }
@media (max-width: 768px) {
    .next-assignee { align-items: stretch; flex-direction: column; gap: 8px; }
    .next-assignee__copy, .next-assignee__select { width: 100%; min-width: 0; }
}
</style>
