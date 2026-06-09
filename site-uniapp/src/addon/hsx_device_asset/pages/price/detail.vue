<template>
    <view class="price-page">
        <view class="asset-card">
            <view class="asset-title">{{ asset.model || asset.asset_no || '资产定价' }}</view>
            <view class="asset-meta">资产编号：{{ asset.asset_no || '-' }}</view>
            <view class="asset-meta">IMEI：{{ asset.imei || '-' }}</view>
            <view class="asset-meta">回收成本：¥{{ money(asset.recycle_final_price) }}</view>
            <view class="status-row">
                <text>{{ asset.photo_status_name || asset.photo_status || '-' }}</text>
                <text>{{ asset.price_status_name || asset.price_status || '-' }}</text>
            </view>
        </view>

        <view class="section">
            <view class="section-title">质检摘要</view>
            <view v-if="checkEntries.length" class="summary-list">
                <view v-for="item in checkEntries" :key="item.key" class="summary-row">
                    <text>{{ item.key }}</text>
                    <text>{{ item.value }}</text>
                </view>
            </view>
            <view v-else class="empty">暂无质检摘要</view>
        </view>

        <view class="section">
            <view class="section-title">图片参考</view>
            <view v-if="allImages.length" class="image-grid">
                <image
                    v-for="(url, index) in allImages"
                    :key="`${ url }-${ index }`"
                    :src="img(url)"
                    class="image-thumb"
                    mode="aspectFill"
                    @click="preview(index)"
                />
            </view>
            <view v-else class="empty">暂无图片</view>
        </view>

        <view class="section">
            <view class="section-title">销售定价</view>
            <view class="form-row">
                <text>销售价</text>
                <input v-model="form.sale_price" type="digit" placeholder="请输入销售价" />
            </view>
            <view class="form-row">
                <text>同行价</text>
                <input v-model="form.peer_price" type="digit" placeholder="可选" />
            </view>
            <view class="form-row">
                <text>最低价</text>
                <input v-model="form.min_price" type="digit" placeholder="可选" />
            </view>
            <view class="remark-box">
                <textarea v-model="form.remark" placeholder="定价备注：成色、渠道、底价说明等" />
            </view>
        </view>

        <view class="footer-actions">
            <button class="secondary-btn" :disabled="loading" @click="loadInfo">刷新</button>
            <button class="primary-btn" :disabled="submitting" @click="submitPrice">保存定价</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { img } from '@/utils/common'
import { completeAssetPrice, getAssetInfo } from '@/addon/hsx_device_asset/api/device_asset'

const assetId = ref('')
const asset = ref<any>({})
const loading = ref(false)
const submitting = ref(false)
const form = reactive({
    sale_price: '',
    peer_price: '',
    min_price: '',
    remark: ''
})

onLoad((options: any) => {
    assetId.value = String(options?.id || '')
    if (!assetId.value) {
        uni.showToast({ title: '缺少资产ID', icon: 'none' })
        return
    }
    loadInfo()
})

const loadInfo = async () => {
    if (!assetId.value) return
    loading.value = true
    try {
        const res: any = await getAssetInfo(assetId.value)
        asset.value = res.data || {}
        form.sale_price = asset.value.sale_price && Number(asset.value.sale_price) > 0 ? String(asset.value.sale_price) : ''
        form.peer_price = asset.value.peer_price && Number(asset.value.peer_price) > 0 ? String(asset.value.peer_price) : ''
        form.min_price = asset.value.min_price && Number(asset.value.min_price) > 0 ? String(asset.value.min_price) : ''
        form.remark = asset.value.price_remark || ''
    } finally {
        loading.value = false
    }
}

const checkEntries = computed(() => {
    const data = normalizeObject(asset.value?.check_summary || asset.value?.recycle_device?.check_result_buyer || {})
    return Object.entries(data)
        .filter(([, value]) => value !== '' && value !== null && value !== undefined)
        .map(([key, value]) => ({
            key,
            value: typeof value === 'object' ? JSON.stringify(value) : String(value)
        }))
})

const allImages = computed(() => {
    const checkImages = recycleCheckImages(asset.value)
    const assetImages = (asset.value?.media || [])
        .filter((item: any) => item.media_type !== 'video' && item.status !== 'rejected')
        .map((item: any) => item.url)
        .filter(Boolean)
    return [...checkImages, ...assetImages].filter((url, index, arr) => url && arr.indexOf(url) === index)
})

const submitPrice = async () => {
    if (!form.sale_price || Number(form.sale_price) <= 0) {
        uni.showToast({ title: '请输入销售价', icon: 'none' })
        return
    }
    submitting.value = true
    try {
        await completeAssetPrice(assetId.value, {
            sale_price: Number(form.sale_price || 0),
            peer_price: Number(form.peer_price || 0),
            min_price: Number(form.min_price || 0),
            remark: form.remark || ''
        })
        uni.showToast({ title: '定价已保存', icon: 'none' })
        await loadInfo()
    } finally {
        submitting.value = false
    }
}

const preview = (index: number) => {
    const urls = allImages.value.map((url: string) => img(url))
    uni.previewImage({
        urls,
        current: urls[index]
    })
}

const money = (value: any) => Number(value || 0).toFixed(2)

const recycleCheckImages = (value: any) => {
    const device = value?.recycle_device || value?.recycleDevice || {}
    return [
        ...splitImages(device.check_images_buyer),
        ...splitImages(device.check_images_seller),
        ...splitImages(device.check_images)
    ].filter((url, index, arr) => url && arr.indexOf(url) === index)
}

const splitImages = (value: any) => {
    if (Array.isArray(value)) return value.filter(Boolean)
    return String(value || '').split(',').map(item => item.trim()).filter(Boolean)
}

const normalizeObject = (value: any) => {
    if (!value) return {}
    if (typeof value === 'object') return value
    try {
        const parsed = JSON.parse(value)
        return typeof parsed === 'object' && parsed ? parsed : { 原始质检: value }
    } catch {
        return { 原始质检: value }
    }
}
</script>

<style lang="scss" scoped>
.price-page {
    min-height: 100vh;
    padding: 24rpx;
    background: #f6f7fb;
    box-sizing: border-box;
}

.asset-card,
.section {
    margin-bottom: 20rpx;
    padding: 24rpx;
    border-radius: 18rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.asset-title {
    color: #111827;
    font-size: 34rpx;
    font-weight: 700;
    margin-bottom: 12rpx;
}

.asset-meta {
    color: #6b7280;
    font-size: 24rpx;
    line-height: 1.8;
}

.status-row {
    display: flex;
    gap: 12rpx;
    margin-top: 14rpx;

    text {
        padding: 8rpx 16rpx;
        border-radius: 999rpx;
        color: #2563eb;
        background: #dbeafe;
        font-size: 22rpx;
    }
}

.section-title {
    margin-bottom: 18rpx;
    color: #111827;
    font-size: 30rpx;
    font-weight: 650;
}

.summary-list {
    display: grid;
    gap: 10rpx;
}

.summary-row,
.form-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
    min-height: 72rpx;
    padding: 0 18rpx;
    border-radius: 14rpx;
    background: #f8fafc;
    color: #6b7280;
    font-size: 25rpx;

    text:last-child {
        color: #111827;
        font-weight: 600;
        text-align: right;
    }
}

.image-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12rpx;
}

.image-thumb {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 12rpx;
    background: #f3f4f6;
}

.form-row {
    margin-bottom: 12rpx;

    input {
        flex: 1;
        text-align: right;
        color: #111827;
    }
}

.remark-box {
    margin-top: 12rpx;
    padding: 16rpx;
    border-radius: 14rpx;
    background: #f8fafc;

    textarea {
        width: 100%;
        min-height: 150rpx;
        color: #111827;
        font-size: 26rpx;
    }
}

.empty {
    padding: 30rpx 0;
    text-align: center;
    color: #9ca3af;
    font-size: 25rpx;
}

.footer-actions {
    position: sticky;
    bottom: 0;
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 16rpx;
    padding: 18rpx 0 env(safe-area-inset-bottom);
    background: #f6f7fb;
}

.primary-btn,
.secondary-btn {
    height: 84rpx;
    line-height: 84rpx;
    border-radius: 42rpx;
    font-size: 28rpx;
}

.primary-btn {
    color: #fff;
    background: #2563eb;
}

.secondary-btn {
    color: #2563eb;
    background: #dbeafe;
}
</style>
