<template>
    <view class="mc-button" :class="{ 'mc-button--compact': compact }">
        <u-button
            :type="type"
            :plain="plain"
            :disabled="disabled"
            :loading="loading"
            :size="size"
            :custom-style="buttonStyle"
            @click="emit('click')"
        >
            <view class="mc-button__content">
                <u-icon v-if="icon" :name="icon" :size="compact ? 15 : 17" :color="iconColor" />
                <text>{{ text }}</text>
            </view>
        </u-button>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    text: string
    type?: string
    size?: string
    icon?: string
    plain?: boolean
    disabled?: boolean
    loading?: boolean
    compact?: boolean
}>(), {
    type: 'default',
    size: 'normal',
    icon: '',
    plain: false,
    disabled: false,
    loading: false,
    compact: false,
})

const emit = defineEmits(['click'])
const solidTypes = ['primary', 'success', 'warning', 'error']
const iconColor = computed(() => {
    if (!props.plain && solidTypes.includes(props.type)) return '#ffffff'
    if (props.type === 'primary') return '#2563eb'
    if (props.type === 'success') return '#16a34a'
    if (props.type === 'warning') return '#d97706'
    if (props.type === 'error') return '#dc2626'
    return '#475569'
})
const buttonStyle = computed(() => ({ width: '100%', margin: '0' }))
</script>

<style scoped lang="scss">
.mc-button {
    display: block;
    width: 100%;
    min-width: 0;
}

.mc-button :deep(.u-button) {
    height: 82rpx;
    margin: 0 !important;
    border-radius: 14rpx;
    font-size: 27rpx;
    font-weight: 650;
    letter-spacing: 0;
}

.mc-button--compact :deep(.u-button) {
    height: 66rpx;
    border-radius: 12rpx;
    font-size: 24rpx;
}

.mc-button__content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9rpx;
    line-height: 1;
}
</style>
