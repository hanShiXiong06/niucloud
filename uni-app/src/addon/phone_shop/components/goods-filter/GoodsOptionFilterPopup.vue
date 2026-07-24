<template>
    <u-popup :show="show" mode="bottom" :round="18" :close-on-click-overlay="true" @close="close">
        <view class="filter-popup" :style="themeColor()">
            <view class="filter-popup__head">
                <view class="filter-popup__title">{{ title }}</view>
                <view class="filter-popup__close" @click="close">
                    <u-icon name="close" size="20" color="#64748b" />
                </view>
            </view>
            <scroll-view scroll-y class="filter-popup__body">
                <view v-for="group in groups" :key="group.key || group.title" class="option-group">
                    <view v-if="group.title" class="option-group__title">{{ group.title }}</view>
                    <view class="option-grid">
                        <view
                            v-for="item in group.items"
                            :key="String(item.value)"
                            class="option-chip"
                            :class="{ 'option-chip--active': selected.includes(String(item.value)) }"
                            @click="toggle(item.value)"
                        >
                            <text class="option-chip__label">{{ item.label }}</text>
                            <text v-if="item.desc" class="option-chip__desc">{{ item.desc }}</text>
                            <view v-if="selected.includes(String(item.value))" class="option-chip__check">
                                <u-icon name="checkmark" size="12" color="#fff" />
                            </view>
                        </view>
                    </view>
                </view>
                <u-empty v-if="!groups.length" text="暂无可选项" mode="list" />
            </scroll-view>
            <view class="filter-popup__footer">
                <view class="filter-button filter-button--plain" @click="reset">重置</view>
                <view class="filter-button filter-button--primary" @click="confirm">
                    确定<text v-if="selected.length">（{{ selected.length }}）</text>
                </view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

interface OptionItem {
    label: string
    value: string | number
    desc?: string
}

interface OptionGroup {
    key?: string | number
    title?: string
    items: OptionItem[]
}

const props = withDefaults(defineProps<{
    show: boolean
    title: string
    modelValue: Array<string | number>
    groups: OptionGroup[]
    multiple?: boolean
}>(), {
    multiple: true
})

const emit = defineEmits(['update:show', 'confirm'])
const selected = ref<string[]>([])

watch(() => props.show, (value) => {
    if (value) selected.value = (props.modelValue || []).map(String)
}, { immediate: true })

const close = () => emit('update:show', false)

const toggle = (value: string | number) => {
    const normalized = String(value)
    if (!props.multiple) {
        selected.value = selected.value.includes(normalized) ? [] : [normalized]
        return
    }
    selected.value = selected.value.includes(normalized)
        ? selected.value.filter(item => item !== normalized)
        : [...selected.value, normalized]
}

const reset = () => {
    selected.value = []
}

const confirm = () => {
    emit('confirm', [...selected.value])
    close()
}
</script>

<style lang="scss" scoped>
.filter-popup {
    height: min(72vh, 960rpx);
    display: flex;
    flex-direction: column;
    background: #fff;
}

.filter-popup__head {
    height: 112rpx;
    padding: 0 30rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1rpx solid #eef2f7;
}

.filter-popup__title {
    color: #0f172a;
    font-size: 32rpx;
    font-weight: 600;
}

.filter-popup__close {
    width: 60rpx;
    height: 60rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f5f7fa;
}

.filter-popup__body {
    flex: 1;
    min-height: 0;
    box-sizing: border-box;
    padding: 8rpx 30rpx 30rpx;
}

.option-group {
    padding-top: 30rpx;
}

.option-group__title {
    margin-bottom: 20rpx;
    color: #334155;
    font-size: 27rpx;
    font-weight: 600;
}

.option-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18rpx;
}

.option-chip {
    position: relative;
    min-height: 70rpx;
    padding: 14rpx 16rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    overflow: hidden;
    border: 2rpx solid transparent;
    border-radius: 14rpx;
    background: #f5f7fa;
    color: #334155;
}

.option-chip--active {
    color: var(--primary-color);
    border-color: var(--primary-color);
    background: var(--primary-color-light);
}

.option-chip__label {
    max-width: 100%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 25rpx;
}

.option-chip__desc {
    margin-top: 4rpx;
    color: #94a3b8;
    font-size: 20rpx;
}

.option-chip__check {
    position: absolute;
    right: -1rpx;
    bottom: -1rpx;
    width: 34rpx;
    height: 30rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14rpx 0 10rpx 0;
    background: var(--primary-color);
}

.filter-popup__footer {
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    display: grid;
    grid-template-columns: 220rpx minmax(0, 1fr);
    gap: 20rpx;
    border-top: 1rpx solid #eef2f7;
    background: #fff;
}

.filter-button {
    height: 82rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 42rpx;
    font-size: 28rpx;
    font-weight: 600;
}

.filter-button--plain {
    border: 1rpx solid #dbe1ea;
    color: #475569;
}

.filter-button--primary {
    color: #fff;
    background: var(--primary-color);
}
</style>
