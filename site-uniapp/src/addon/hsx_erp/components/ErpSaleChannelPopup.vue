<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="channel-popup">
            <view class="channel-header">
                <view>
                    <text class="channel-title">选择销售渠道</text>
                    <text class="channel-subtitle">选择本次交易的销售渠道</text>
                </view>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

            <scroll-view scroll-y class="channel-list">
                <view
                    v-for="item in channels"
                    :key="item.key"
                    class="channel-item"
                    :class="{ selected: item.key === modelValue }"
                    @click="selectChannel(item)"
                >
                    <view class="channel-main">
                        <view class="channel-name-row">
                            <text class="channel-name">{{ item.name }}</text>
                            <u-tag v-if="item.channel_type" :text="item.channel_type_name" type="primary" plain plainFill size="mini" />
                        </view>
                        <text v-if="item.is_default" class="channel-source">默认渠道</text>
                    </view>
                    <u-icon v-if="item.key === modelValue" name="checkmark-circle-fill" color="#3b6ef5" size="21" />
                    <view v-else class="channel-choice-circle" />
                </view>
                <view v-if="!channels.length && !loading" class="channel-empty">暂无可用渠道，请先在 ERP 渠道配置中启用</view>
                <view v-if="loading" class="channel-loading"><u-loading-icon size="24" /></view>
            </scroll-view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useErpSaleChannels } from '@/addon/hsx_erp/hooks/useErpSaleChannels'

const props = withDefaults(defineProps<{
    show: boolean
    modelValue?: string
}>(), {
    show: false,
    modelValue: '',
})

const emit = defineEmits<{
    (e: 'update:show', value: boolean): void
    (e: 'update:modelValue', value: string): void
    (e: 'change', value: any): void
}>()

const { options: channels, loading, load } = useErpSaleChannels()

watch(() => props.show, value => {
    if (value) loadChannels()
})

async function loadChannels() {
    try {
        await load(true)
    } catch (error: any) {
        uni.showToast({ title: error?.message || '销售渠道加载失败', icon: 'none' })
    }
}

function selectChannel(item: any) {
    emit('update:modelValue', String(item.key || ''))
    emit('change', item)
    close()
}

function close() {
    emit('update:show', false)
}

</script>

<style scoped lang="scss">
.channel-popup { min-height:48vh; max-height:78vh; padding:28rpx 32rpx calc(28rpx + env(safe-area-inset-bottom)); box-sizing:border-box; display:flex; flex-direction:column; background:#fff; }
.channel-header { display:flex; align-items:flex-start; justify-content:space-between; gap:18rpx; margin-bottom:20rpx; }
.channel-title,.channel-subtitle { display:block; }
.channel-title { font-size:32rpx; font-weight:750; color:#0f172a; }
.channel-subtitle { margin-top:7rpx; font-size:21rpx; color:#64748b; }
.channel-list { flex:1; min-height:320rpx; }
.channel-item { display:flex; min-height:104rpx; align-items:center; justify-content:space-between; gap:20rpx; padding:16rpx 18rpx; border-bottom:1rpx solid #f1f5f9; box-sizing:border-box; }
.channel-item.selected { border-radius:14rpx; border-bottom-color:transparent; background:#eff6ff; }
.channel-main { min-width:0; flex:1; }
.channel-name-row { display:flex; align-items:center; flex-wrap:wrap; gap:10rpx; }
.channel-name { color:#0f172a; font-size:27rpx; font-weight:680; }
.channel-source { display:block; margin-top:7rpx; color:#94a3b8; font-size:20rpx; }
.channel-empty { padding:70rpx 20rpx; color:#94a3b8; font-size:24rpx; line-height:1.5; text-align:center; }
.channel-loading { display:flex; justify-content:center; padding:70rpx 0; }
.channel-choice-circle { width:36rpx; height:36rpx; box-sizing:border-box; border:3rpx solid #cbd5e1; border-radius:50%; flex:none; }
</style>
