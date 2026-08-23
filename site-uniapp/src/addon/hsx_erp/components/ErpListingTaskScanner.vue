<template>
    <view class="listing-scanner">
        <view class="listing-scanner__entry" @click="scanCode">
            <view class="listing-scanner__icon">
                <u-icon name="scan" color="#ffffff" size="22" />
            </view>
            <view class="listing-scanner__copy">
                <view class="listing-scanner__title-row">
                    <text class="listing-scanner__title">扫码拍摄定价</text>
                    <text class="listing-scanner__badge">我的任务</text>
                </view>
                <text class="listing-scanner__desc">扫描 IMEI / SN，直接打开分配给我的处理表单</text>
            </view>
            <u-loading-icon v-if="loading" color="#2563eb" size="18" />
            <u-icon v-else name="arrow-right" color="#94a3b8" size="15" />
        </view>
        <view class="listing-scanner__manual" @click="openManual">
            <u-icon name="edit-pen" color="#64748b" size="14" />
            <text>无法扫码？手动输入串号</text>
        </view>

        <u-popup :show="manualVisible" mode="bottom" round="20" :safe-area-inset-bottom="true" @close="manualVisible = false">
            <view class="listing-scanner-popup">
                <view class="listing-scanner-popup__head">
                    <view>
                        <text class="listing-scanner-popup__title">输入设备串号</text>
                        <text class="listing-scanner-popup__desc">支持 IMEI、SN 或 ERP 资产号</text>
                    </view>
                    <u-icon name="close" color="#94a3b8" size="20" @click="manualVisible = false" />
                </view>
                <view class="listing-scanner-popup__input">
                    <u-input v-model="manualCode" placeholder="请输入或粘贴串号" border="none" clearable />
                </view>
                <view class="listing-scanner-popup__button">
                    <u-button type="primary" :loading="loading" text="查找并处理" @click="submitManual" />
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'

defineProps<{ loading?: boolean }>()
const emit = defineEmits<{ resolve: [code: string] }>()

const manualVisible = ref(false)
const manualCode = ref('')

function normalizeCode(raw: unknown): string {
    const text = String(raw || '').trim()
    if (!text) return ''
    try {
        const parsed = JSON.parse(text)
        if (parsed && typeof parsed === 'object') {
            for (const key of ['imei', 'sn', 'asset_no', 'code']) {
                const value = String((parsed as Record<string, any>)[key] || '').trim()
                if (value) return value
            }
        }
    } catch (_) {}
    const queryValue = text.match(/[?&#](?:imei|sn|asset_no|code)=([^&#]+)/i)?.[1]
    if (queryValue) {
        try { return decodeURIComponent(queryValue).trim() } catch (_) { return queryValue.trim() }
    }
    return text
}

function scanCode() {
    uni.scanCode({
        scanType: ['barCode', 'qrCode'],
        success: result => {
            const code = normalizeCode(result.result)
            if (!code) return uni.showToast({ title: '未识别到有效串号', icon: 'none' })
            emit('resolve', code)
        },
        fail: error => {
            if (!String(error?.errMsg || '').includes('cancel')) openManual()
        },
    })
}

function openManual() {
    manualCode.value = ''
    manualVisible.value = true
}

function submitManual() {
    const code = normalizeCode(manualCode.value)
    if (!code) return uni.showToast({ title: '请输入设备串号', icon: 'none' })
    manualVisible.value = false
    emit('resolve', code)
}
</script>

<style scoped lang="scss">
.listing-scanner {
    margin: 20rpx 24rpx 0;
    overflow: hidden;
    border: 2rpx solid #dbeafe;
    border-radius: 22rpx;
    background: #fff;
    box-shadow: 0 10rpx 30rpx rgba(37, 99, 235, .07);
}
.listing-scanner__entry {
    display: flex;
    align-items: center;
    gap: 20rpx;
    padding: 24rpx;
}
.listing-scanner__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 76rpx;
    height: 76rpx;
    flex: none;
    border-radius: 20rpx;
    background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
    box-shadow: 0 8rpx 18rpx rgba(37, 99, 235, .22);
}
.listing-scanner__copy { min-width: 0; flex: 1; }
.listing-scanner__title-row { display: flex; align-items: center; gap: 12rpx; }
.listing-scanner__title { color: #0f172a; font-size: 29rpx; font-weight: 700; }
.listing-scanner__badge {
    padding: 4rpx 10rpx;
    border-radius: 999rpx;
    color: #2563eb;
    font-size: 20rpx;
    background: #eff6ff;
}
.listing-scanner__desc {
    display: block;
    margin-top: 7rpx;
    color: #64748b;
    font-size: 23rpx;
    line-height: 1.45;
}
.listing-scanner__manual {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8rpx;
    padding: 16rpx 24rpx;
    border-top: 2rpx solid #f1f5f9;
    color: #64748b;
    font-size: 22rpx;
    background: #f8fafc;
}
.listing-scanner-popup { padding: 30rpx 30rpx calc(28rpx + env(safe-area-inset-bottom)); }
.listing-scanner-popup__head { display: flex; justify-content: space-between; align-items: flex-start; }
.listing-scanner-popup__title,
.listing-scanner-popup__desc { display: block; }
.listing-scanner-popup__title { color: #0f172a; font-size: 34rpx; font-weight: 700; }
.listing-scanner-popup__desc { margin-top: 8rpx; color: #94a3b8; font-size: 23rpx; }
.listing-scanner-popup__input {
    margin-top: 28rpx;
    padding: 8rpx 22rpx;
    border: 2rpx solid #dbe3ef;
    border-radius: 16rpx;
    background: #f8fafc;
}
.listing-scanner-popup__button { margin-top: 24rpx; }
</style>
