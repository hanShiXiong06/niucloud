<template>
    <view class="block w-full min-w-0">
        <u-button
            :type="type"
            :plain="plain"
            :disabled="disabled"
            :loading="loading"
            :size="size"
            :custom-style="buttonStyle"
            @click="emit('click')"
        >
            <view class="flex items-center justify-center gap-[10rpx] leading-none">
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
const buttonStyle = computed(() => ({
    width: '100%',
    height: props.compact ? '68rpx' : '84rpx',
    margin: '0',
    borderRadius: props.compact ? '12rpx' : '15rpx',
    fontSize: props.compact ? '25rpx' : '28rpx',
    fontWeight: '600',
    letterSpacing: '0',
}))
</script>
