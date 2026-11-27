<template>
    <u-button
        :type="type"
        :plain="plain"
        :disabled="disabled || loading"
        :loading="loading"
        :loadingText="loadingText || '处理中…'"
        :size="size"
        :custom-style="buttonStyle"
        @click="click"
    >
        <view class="mc-button-label">
            <u-icon v-if="icon && !loading" :name="icon" :size="compact ? 15 : 17" :color="iconColor" />
            <text>{{ loading ? loadingText || '处理中…' : text }}</text>
        </view>
    </u-button>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(
    defineProps<{
        text: string
        type?: string
        size?: string
        icon?: string
        plain?: boolean
        disabled?: boolean
        loading?: boolean
        compact?: boolean
        loadingText?: string
    }>(),
    {
        type: 'default',
        size: 'normal',
        icon: '',
        plain: false,
        disabled: false,
        loading: false,
        compact: false,
        loadingText: ''
    }
)
const emit = defineEmits(['click'])
const colors: Record<string, string> = {
    primary: 'var(--mc-primary, #2563eb)',
    success: '#287951',
    warning: '#94651d',
    error: '#bb3e3e'
}
const color = computed(() => colors[props.type] || '#46566e')
const solid = computed(() => props.type !== 'default' && !props.plain)
const iconColor = computed(() => (solid.value ? '#fff' : color.value))
const buttonStyle = computed(() => ({
    width: '100%',
    height: props.compact ? '34px' : '44px',
    margin: '0',
    borderRadius: '8px',
    fontSize: props.compact ? '13px' : '15px',
    fontWeight: '600',
    background: solid.value ? color.value : '#fff',
    color: solid.value ? '#fff' : color.value,
    border: '1rpx solid ' + (props.type === 'default' ? '#dce3ed' : color.value),
    opacity: props.disabled ? '.5' : '1'
}))
const click = () => {
    if (!props.loading && !props.disabled) emit('click')
}
</script>
<style scoped>
.mc-button-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10rpx;
}
</style>
