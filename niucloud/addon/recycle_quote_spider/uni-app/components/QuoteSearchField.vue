<template>
    <view class="quote-search-field">
        <view class="search-input-wrap">
            <up-icon name="search" size="20" color="#7b8493" />
            <input class="search-input" :value="modelValue" :placeholder="placeholder" :disabled="disabled"
                :maxlength="80" confirm-type="search" @input="onInput" @confirm="submit" />
            <button v-if="modelValue && !disabled" class="clear-button" aria-label="清空搜索" @click="clear">
                <up-icon name="close-circle-fill" size="17" color="#a1a7b0" />
            </button>
        </view>
        <button class="search-button" :style="{ backgroundColor: buttonColor }" :disabled="disabled" @click="submit">搜索</button>
    </view>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{ modelValue: string; placeholder?: string; disabled?: boolean; buttonColor?: string }>(), {
    placeholder: '搜索型号，如 iPhone 17 Pro Max', disabled: false, buttonColor: '#2563eb'
})
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void; (event: 'search', value: string): void; (event: 'clear'): void }>()
const onInput = (event: any) => emit('update:modelValue', event.detail.value)
const submit = () => { if (!props.disabled) emit('search', props.modelValue) }
const clear = () => { emit('update:modelValue', ''); emit('clear') }
</script>

<style scoped lang="scss">
.quote-search-field { display: flex; align-items: center; gap: 16rpx; width: 100%; min-width: 0; }
.search-input-wrap { display: flex; flex: 1; min-width: 0; align-items: center; height: 80rpx; padding: 0 20rpx; background: #f3f5f7; border-radius: 12rpx; }
.search-input { flex: 1; min-width: 0; height: 80rpx; margin-left: 12rpx; font-size: 28rpx; color: #20252e; }
.clear-button, .search-button { display: flex; align-items: center; justify-content: center; margin: 0; padding: 0; line-height: 1; border: 0; }
.clear-button::after, .search-button::after { border: 0; }
.clear-button { width: 48rpx; height: 64rpx; background: transparent; flex-shrink: 0; }
.search-button { width: 112rpx; height: 80rpx; flex-shrink: 0; border-radius: 12rpx; color: #fff; font-size: 28rpx; }
</style>
