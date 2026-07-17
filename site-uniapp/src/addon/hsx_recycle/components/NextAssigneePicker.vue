<template>
    <view class="next-assignee">
        <view class="next-assignee__copy">
            <text class="next-assignee__label">{{ label }}</text>
            <text class="next-assignee__hint">提交后自动创建待办并通知本人</text>
        </view>
        <picker v-if="users.length" :range="users" range-key="name" :value="selectedIndex" @change="handleChange">
            <view class="next-assignee__picker">
                <text>{{ selectedName || '请选择负责人' }}</text>
                <text class="nc-iconfont nc-icon-youV6xx1"></text>
            </view>
        </picker>
        <view v-else class="next-assignee__empty">{{ loading ? '正在匹配岗位人员…' : '暂无对应岗位员工，将使用系统兜底规则' }}</view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { getAssignableUsers } from '@/addon/hsx_recycle/api/task'

const props = withDefaults(defineProps<{
    modelValue: number
    stageKey: string
    label?: string
}>(), { label: '下一环节负责人' })
const emit = defineEmits<{ (event: 'update:modelValue', value: number): void }>()
const users = ref<any[]>([])
const loading = ref(false)
const storageKey = () => `hsx_recycle:last_assignee:${props.stageKey}`
const selectedIndex = computed(() => Math.max(0, users.value.findIndex(user => Number(user.uid) === Number(props.modelValue))))
const selectedName = computed(() => users.value.find(user => Number(user.uid) === Number(props.modelValue))?.name || '')

const chooseInitial = () => {
    if (!users.value.length) return
    if (users.value.some(user => Number(user.uid) === Number(props.modelValue))) return
    const cached = Number(uni.getStorageSync(storageKey()) || 0)
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

const handleChange = (event: any) => {
    const user = users.value[Number(event?.detail?.value || 0)]
    if (!user) return
    const uid = Number(user.uid)
    emit('update:modelValue', uid)
    uni.setStorageSync(storageKey(), uid)
}

watch(() => props.stageKey, loadUsers, { immediate: true })
watch(() => props.modelValue, chooseInitial)
</script>

<style scoped lang="scss">
.next-assignee { margin: 20rpx 24rpx; padding: 22rpx 24rpx; background: #f7f8fa; border: 1rpx solid #edf0f5; border-radius: 12rpx; }
.next-assignee__copy { display: flex; justify-content: space-between; align-items: center; gap: 20rpx; margin-bottom: 16rpx; }
.next-assignee__label { color: #202124; font-size: 28rpx; font-weight: 600; }
.next-assignee__hint { color: #8a94a6; font-size: 22rpx; }
.next-assignee__picker { min-height: 72rpx; padding: 0 20rpx; display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1rpx solid #dfe4ec; border-radius: 8rpx; color: #303133; font-size: 28rpx; }
.next-assignee__empty { color: #909399; font-size: 24rpx; }
</style>
