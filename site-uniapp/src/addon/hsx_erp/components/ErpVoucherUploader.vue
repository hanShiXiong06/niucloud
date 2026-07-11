<template>
    <view class="erp-voucher">
        <view class="erp-voucher__head">
            <view>
                <text class="erp-voucher__title">{{ title }}</text>
                <text v-if="hint" class="erp-voucher__hint">{{ hint }}</text>
            </view>
            <text v-if="!readonly" class="erp-voucher__count">{{ paths.length }}/{{ maxCount }}</text>
        </view>
        <view v-if="paths.length || !readonly" class="erp-voucher__images">
            <view v-for="(url, index) in urls" :key="paths[index]" class="erp-voucher__item">
                <image class="erp-voucher__image" :src="url" mode="aspectFill" @click="preview(index)" />
                <view v-if="!readonly" class="erp-voucher__remove" @click.stop="removeAt(index)">×</view>
            </view>
            <view v-if="!readonly && paths.length < maxCount" class="erp-voucher__add" @click="choose">
                <u-loading-icon v-if="uploading" size="20" />
                <template v-else>
                    <text class="erp-voucher__plus">＋</text>
                    <text class="erp-voucher__add-text">上传凭证</text>
                </template>
            </view>
        </view>
        <text v-else class="erp-voucher__empty">{{ emptyText }}</text>
    </view>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useErpVoucher } from '@/addon/hsx_erp/hooks/useErpVoucher'

const props = withDefaults(defineProps<{
    modelValue?: string | string[]
    title?: string
    hint?: string
    emptyText?: string
    maxCount?: number
    readonly?: boolean
}>(), {
    modelValue: '', title: '收付款凭证', hint: '可上传转账截图、回单或其他资金证据', emptyText: '未上传凭证', maxCount: 3, readonly: false,
})
const emit = defineEmits(['update:modelValue', 'change', 'uploading'])
const { paths, urls, value, uploading, reset, preview, remove, chooseAndUpload } = useErpVoucher(props.modelValue, props.maxCount)

watch(() => props.modelValue, next => {
    if (String(next || '') !== value.value) reset(next)
})
watch(value, next => { emit('update:modelValue', next); emit('change', next) })
watch(uploading, next => emit('uploading', next))

async function choose() { await chooseAndUpload() }
function removeAt(index: number) { remove(index) }
defineExpose({ uploading, getValue: () => value.value })
</script>

<style scoped lang="scss">
.erp-voucher { padding:16rpx; border-radius:12rpx; background:#f8fafc; }
.erp-voucher__head { display:flex; align-items:flex-start; justify-content:space-between; gap:16rpx; }
.erp-voucher__title,.erp-voucher__hint { display:block; }
.erp-voucher__title { color:#334155; font-size:24rpx; font-weight:650; }
.erp-voucher__hint { margin-top:4rpx; color:#94a3b8; font-size:20rpx; line-height:1.4; }
.erp-voucher__count { color:#94a3b8; font-size:20rpx; }
.erp-voucher__images { display:flex; flex-wrap:wrap; gap:14rpx; margin-top:14rpx; }
.erp-voucher__item,.erp-voucher__add { position:relative; width:112rpx; height:112rpx; border-radius:10rpx; overflow:hidden; }
.erp-voucher__image { width:100%; height:100%; background:#e2e8f0; }
.erp-voucher__remove { position:absolute; top:4rpx; right:4rpx; width:32rpx; height:32rpx; border-radius:50%; color:#fff; background:rgba(15,23,42,.72); text-align:center; line-height:30rpx; }
.erp-voucher__add { box-sizing:border-box; border:2rpx dashed #cbd5e1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#64748b; }
.erp-voucher__plus { font-size:34rpx; line-height:1; }.erp-voucher__add-text { margin-top:5rpx; font-size:19rpx; }
.erp-voucher__empty { display:block; margin-top:10rpx; color:#94a3b8; font-size:22rpx; }
</style>
