<template>
    <view class="mc-segmented">
        <u-subsection
            :list="options"
            keyName="label"
            :current="current"
            mode="button"
            bgColor="#eef1f5"
            activeColor="#245caa"
            inactiveColor="#68778d"
            :fontSize="13"
            :bold="true"
            :disabled="disabled"
            @change="change"
        />
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(
    defineProps<{
        modelValue: string
        options: Array<{ label: string; value: string }>
        disabled?: boolean
    }>(),
    { disabled: false }
)
const emit = defineEmits(['update:modelValue', 'change'])
const current = computed(() =>
    Math.max(
        0,
        props.options.findIndex((item) => item.value === props.modelValue)
    )
)
const change = (index: number) => {
    const option = props.options[index]
    if (!props.disabled && option && option.value !== props.modelValue) {
        emit('update:modelValue', option.value)
        emit('change', option.value)
    }
}
</script>
