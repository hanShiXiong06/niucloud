<template>
    <view class="fixed left-0 right-0 top-0 z-50 box-border border-b border-[#edf1f6] bg-[rgba(255,255,255,.98)] px-[20rpx] pt-[14rpx]">
        <view class="flex h-[70rpx] box-border items-center gap-[11rpx] rounded-[36rpx] border border-[#e7ecf3] bg-[#f5f7fa] px-[20rpx]">
            <u-icon name="search" color="#94a3b8" size="18" />
            <input
                v-model="localKeyword"
                class="h-[68rpx] min-w-0 flex-1 text-[25rpx] text-[#475569]"
                :placeholder="placeholder"
                placeholder-class="text-[#94a3b8]"
                confirm-type="search"
                @confirm="search"
            />
            <view v-if="localKeyword" class="flex items-center justify-center p-[8rpx]" @click="clear">
                <u-icon name="close-circle-fill" color="#cbd5e1" size="17" />
            </view>
            <slot name="search-action" />
        </view>
        <scroll-view v-if="tabs.length" scroll-x class="h-[80rpx] w-full whitespace-nowrap" :show-scrollbar="false">
            <view class="inline-flex h-[80rpx] items-center gap-[8rpx] pr-[20rpx]">
                <view
                    v-for="tab in tabs"
                    :key="tab.value"
                    class="inline-flex h-[52rpx] box-border items-center gap-[7rpx] rounded-[27rpx] px-[23rpx] text-[24rpx]"
                    :class="activeTab === tab.value ? 'bg-[#eff6ff] font-semibold text-[#2563eb]' : 'text-[#64748b]'"
                    @click="selectTab(tab.value)"
                >
                    <text>{{ tab.label }}</text>
                    <text
                        v-if="tab.count !== undefined"
                        class="min-w-[28rpx] rounded-[13rpx] px-[7rpx] py-[1rpx] text-center text-[20rpx]"
                        :class="activeTab === tab.value ? 'bg-[rgba(37,99,235,.1)]' : 'bg-[rgba(148,163,184,.13)]'"
                    >{{ tab.count }}</text>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

const props = withDefaults(defineProps<{
    modelValue?: string
    activeTab?: string
    placeholder?: string
    tabs?: Array<{ label: string; value: string; count?: number }>
}>(), {
    modelValue: '',
    activeTab: '',
    placeholder: '搜索',
    tabs: () => [],
})

const emit = defineEmits(['update:modelValue', 'update:activeTab', 'search', 'tab-change'])
const localKeyword = ref(props.modelValue)

watch(() => props.modelValue, value => { localKeyword.value = value || '' })
watch(localKeyword, value => emit('update:modelValue', value))

const search = () => emit('search', localKeyword.value.trim())
const clear = () => {
    localKeyword.value = ''
    emit('update:modelValue', '')
    emit('search', '')
}
const selectTab = (value: string) => {
    emit('update:activeTab', value)
    emit('tab-change', value)
}
</script>
