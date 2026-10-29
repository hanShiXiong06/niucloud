<template>
    <view class="quote-picker">
        <view class="quote-picker__label">报价员 <text>选填</text></view>
        <picker :disabled="loading || failed" :range="choices" range-key="label" :value="selectedIndex" @change="select">
            <view class="quote-picker__value">{{ loading ? '加载人员…' : selectedName }} <text>⌄</text></view>
        </picker>
        <view v-if="failed" class="quote-picker__hint" @click="load">人员加载失败，点击重试（原选择保留）</view>
        <view v-else class="quote-picker__hint">{{ assignToQuoteUser ? '选定后由此人负责后续定价；留空按原流程分配' : '仅记录谁报的价，定价仍由业务员指定' }}</view>
    </view>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import request from '@/utils/request'
const props = defineProps<{ modelValue: number; name?: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: number): void; (e: 'update:name', value: string): void }>()
const users = ref<Array<{ uid: number; name: string; disabled?: boolean }>>([])
const loading = ref(false)
const failed = ref(false)
const assignToQuoteUser = ref(false)
const choices = computed(() => [{ uid: 0, name: '', label: '不指定' }, ...users.value.filter(user => !user.disabled).map(user => ({ ...user, label: user.name }))])
const selectedIndex = computed(() => Math.max(0, choices.value.findIndex(user => user.uid === Number(props.modelValue))))
const selectedName = computed(() => users.value.find(user => user.uid === Number(props.modelValue))?.name || props.name || (props.modelValue ? '原报价员（待核对）' : '选择谁给客户报的价'))
const select = (event: any) => {
    const user = choices.value[Number(event.detail.value)]
    if (!user) return
    emit('update:modelValue', user.uid)
    emit('update:name', user.name)
}
const load = async () => {
    loading.value = true
    failed.value = false
    try {
        const res: any = await request.get('recycle/quote_user_options')
        users.value = res.data?.users || []
        assignToQuoteUser.value = Boolean(res.data?.assign_to_quote_user)
    } catch { failed.value = true }
    finally { loading.value = false }
}
onMounted(load)
</script>
<style scoped>
.quote-picker { margin: 24rpx 0; }
.quote-picker__label { font-size: 28rpx; margin-bottom: 12rpx; }
.quote-picker__label text, .quote-picker__hint { color: #7b8493; font-size: 24rpx; }
.quote-picker__value { padding: 20rpx; background: #f6f7fa; border-radius: 12rpx; display: flex; justify-content: space-between; font-size: 28rpx; }
.quote-picker__hint { margin-top: 10rpx; line-height: 1.5; }
</style>
