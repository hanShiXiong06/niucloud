<template>
    <view class="asset-capture">
        <view class="asset-card">
            <view class="asset-title">{{ asset.model || asset.asset_no || '设备拍照' }}</view>
            <view class="asset-meta">资产编号：{{ asset.asset_no || '-' }}</view>
            <view class="asset-meta">IMEI：{{ asset.imei || '-' }}</view>
            <view class="asset-meta">状态：{{ asset.photo_status_name || asset.photo_status || '-' }}</view>
        </view>

        <view class="section">
            <view class="section-head">
                <view>
                    <view class="section-title">拍摄图片</view>
                    <view class="section-desc">建议按正面、背面、边框、瑕疵顺序拍摄，上传后 PC 端刷新即可看到。</view>
                </view>
                <button class="plain-btn" :disabled="loading" @click="loadInfo">刷新</button>
            </view>

            <view class="capture-actions">
                <button class="camera-btn" :disabled="uploading" @click="chooseImages('camera')">直接拍照</button>
                <button class="album-btn" :disabled="uploading" @click="chooseImages('album')">从相册选择</button>
            </view>

            <view v-if="pendingImages.length" class="pending-block">
                <view class="pending-head">
                    <view class="pending-title">待保存图片 {{ pendingImages.length }} 张</view>
                    <view class="pending-clear" @click="clearPending">清空</view>
                </view>
                <view class="media-list">
                    <view v-for="(item, index) in pendingImages" :key="`${ item.path }-${ index }`" class="pending-item">
                        <image class="media-thumb" :src="item.url" mode="aspectFill" @click="previewPending(index)" />
                        <view class="pending-remove" @click.stop="removePending(index)">×</view>
                    </view>
                </view>
            </view>

            <view v-if="uploading" class="uploading-tip">图片上传中，请稍候...</view>
        </view>

        <view v-if="asset.media?.length" class="section">
            <view class="section-title">已上传图片</view>
            <view class="media-list">
                <image
                    v-for="item in asset.media"
                    :key="item.id"
                    v-show="item.media_type !== 'video' && item.status !== 'rejected'"
                    class="media-thumb"
                    :src="img(item.url)"
                    mode="aspectFill"
                    @click="preview(item.url)"
                />
            </view>
        </view>

        <view class="footer-actions">
            <button class="secondary-btn" :disabled="loading || taskLoading" @click="handleCreateTask">创建拍照任务</button>
            <button class="primary-btn" :disabled="loading || uploading || saveLoading || !pendingImages.length" @click="handleSave">保存到资产</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { img } from '@/utils/common'
import { uploadImage } from '@/app/api/system'
import { createPhotoTask, getAssetInfo, saveAssetMedia } from '@/addon/hsx_device_asset/api/device_asset'

const assetId = ref('')
const asset = ref<any>({})
const pendingImages = ref<Array<{ url: string; path: string }>>([])
const loading = ref(false)
const uploading = ref(false)
const saveLoading = ref(false)
const taskLoading = ref(false)

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
    } finally {
        loading.value = false
    }
}

const handleCreateTask = async () => {
    if (!assetId.value) return
    taskLoading.value = true
    try {
        await createPhotoTask(assetId.value, {
            source: 'mobile',
            station_id: 'mobile',
            remark: '移动端扫码拍照'
        })
        uni.showToast({ title: '拍照任务已创建', icon: 'none' })
        await loadInfo()
    } finally {
        taskLoading.value = false
    }
}

const chooseImages = (source: 'camera' | 'album') => {
    if (pendingImages.value.length >= 18) {
        uni.showToast({ title: '最多保留18张待保存图片', icon: 'none' })
        return
    }

    uni.chooseImage({
        count: source === 'camera' ? 1 : Math.min(18 - pendingImages.value.length, 9),
        sourceType: [source],
        sizeType: ['compressed', 'original'],
        success: async (res) => {
            const files = Array.isArray(res.tempFiles) ? res.tempFiles : []
            if (!files.length) return
            uploading.value = true
            try {
                for (const file of files) {
                    const filePath = file?.path || ''
                    if (filePath) await uploadOne(filePath)
                }
            } finally {
                uploading.value = false
            }
        },
        fail: (error: any) => {
            if (error?.errMsg && !String(error.errMsg).includes('cancel')) {
                uni.showToast({ title: error.errMsg, icon: 'none' })
            }
        }
    })
}

const uploadOne = async (filePath: string) => {
    if (pendingImages.value.length >= 18) {
        uni.showToast({ title: '最多保留18张待保存图片', icon: 'none' })
        return
    }

    const res: any = await uploadImage({ filePath, name: 'file' })
    const path = res?.data?.url || res?.data?.path || ''
    if (!path) {
        uni.showToast({ title: '图片上传失败', icon: 'none' })
        return
    }
    pendingImages.value.push({
        path,
        url: img(path)
    })
}

const handleSave = async () => {
    const images = pendingImages.value.map(item => item.path).filter(Boolean)
    if (!images.length) {
        uni.showToast({ title: '请先拍照上传', icon: 'none' })
        return
    }
    saveLoading.value = true
    try {
        await saveAssetMedia(assetId.value, {
            media: images.map((url, index) => ({
                url,
                media_type: 'image',
                scene: 'mobile',
                source: 'mobile',
                sort: index + 1
            }))
        })
        pendingImages.value = []
        uni.showToast({ title: '已保存，PC端可刷新查看', icon: 'none' })
        await loadInfo()
    } finally {
        saveLoading.value = false
    }
}

const preview = (url: string) => {
    const urls = (asset.value.media || [])
        .filter((item: any) => item.media_type !== 'video' && item.status !== 'rejected')
        .map((item: any) => img(item.url))
    uni.previewImage({
        urls,
        current: img(url)
    })
}

const previewPending = (index: number) => {
    const urls = pendingImages.value.map(item => item.url)
    uni.previewImage({
        urls,
        current: urls[index]
    })
}

const removePending = (index: number) => {
    pendingImages.value.splice(index, 1)
}

const clearPending = () => {
    pendingImages.value = []
}
</script>

<style lang="scss" scoped>
.asset-capture {
    min-height: 100vh;
    padding: 24rpx;
    background: #f6f7fb;
    box-sizing: border-box;
}

.asset-card,
.section {
    background: #fff;
    border-radius: 18rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.asset-title {
    font-size: 34rpx;
    font-weight: 700;
    color: #111827;
    margin-bottom: 12rpx;
}

.asset-meta {
    font-size: 24rpx;
    color: #6b7280;
    line-height: 1.8;
}

.section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
    margin-bottom: 18rpx;
}

.section-title {
    font-size: 30rpx;
    font-weight: 650;
    color: #111827;
}

.section-desc {
    margin-top: 8rpx;
    font-size: 24rpx;
    color: #6b7280;
    line-height: 1.5;
}

.plain-btn {
    height: 56rpx;
    line-height: 56rpx;
    padding: 0 22rpx;
    border-radius: 28rpx;
    background: #eef2ff;
    color: #2563eb;
    font-size: 24rpx;
}

.media-list {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12rpx;
    margin-top: 18rpx;
}

.media-thumb {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 12rpx;
    background: #f3f4f6;
}

.capture-actions {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 16rpx;
}

.camera-btn,
.album-btn {
    height: 96rpx;
    line-height: 96rpx;
    border-radius: 20rpx;
    font-size: 30rpx;
}

.camera-btn {
    color: #fff;
    background: #2563eb;
}

.album-btn {
    color: #2563eb;
    background: #dbeafe;
}

.pending-block {
    margin-top: 24rpx;
}

.pending-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pending-title {
    color: #111827;
    font-size: 26rpx;
    font-weight: 650;
}

.pending-clear {
    color: #ef4444;
    font-size: 24rpx;
}

.pending-item {
    position: relative;
}

.pending-remove {
    position: absolute;
    right: -8rpx;
    top: -8rpx;
    width: 36rpx;
    height: 36rpx;
    line-height: 34rpx;
    text-align: center;
    border-radius: 50%;
    color: #fff;
    background: rgba(15, 23, 42, 0.75);
    font-size: 30rpx;
}

.uploading-tip {
    margin-top: 18rpx;
    color: #2563eb;
    font-size: 24rpx;
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
    background: #e0e7ff;
}
</style>
