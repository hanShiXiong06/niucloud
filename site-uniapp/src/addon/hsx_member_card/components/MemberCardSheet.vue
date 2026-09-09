<template>
    <u-popup
        :show="show"
        mode="bottom"
        :safe-area-inset-bottom="false"
        round="18"
        :closeOnClickOverlay="!busy"
        @close="close"
    >
        <view class="mc-sheet" :style="{ height }">
            <view class="mc-sheet__handle" />
            <view class="mc-sheet__head">
                <view class="mc-grow">
                    <text class="mc-title">{{ title }}</text>
                    <view v-if="subtitle" class="mc-sub">{{ subtitle }}</view>
                </view>
                <view class="mc-sheet__close" :style="{ opacity: busy ? 0.4 : 1 }" @click="close">关闭</view>
            </view>
            <scroll-view scroll-y class="mc-sheet__body">
                <view class="mc-sheet__content"><slot /></view>
            </scroll-view>
            <view v-if="$slots.footer" class="mc-sheet__foot">
                <slot name="footer" />
            </view>
        </view>
    </u-popup>
</template>
<script setup lang="ts">
const props = withDefaults(
    defineProps<{ show: boolean; title: string; subtitle?: string; height?: string; busy?: boolean }>(),
    { subtitle: '', height: '66vh', busy: false }
)
const emit = defineEmits(['update:show', 'close'])
const close = () => {
    if (!props.busy) {
        emit('update:show', false)
        emit('close')
    }
}
</script>
