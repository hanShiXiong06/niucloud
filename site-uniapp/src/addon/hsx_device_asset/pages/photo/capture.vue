<template>
    <view class="asset-capture">
        <view class="top-card">
            <view class="top-card__main">
                <view class="asset-title">{{ asset.model || asset.asset_no || '商品图拍摄' }}</view>
                <view class="asset-meta">资产 {{ asset.asset_no || '-' }}</view>
                <view class="asset-meta">IMEI {{ asset.imei || '-' }}</view>
            </view>
            <u-tag :text="asset.photo_status_name || asset.photo_status || '待拍照'" type="primary" size="mini"></u-tag>
        </view>

        <view class="flow-card">
            <view v-for="item in flowSteps" :key="item.key" class="flow-step" :class="{ done: item.done, active: item.active }">
                <view class="flow-step__dot">{{ item.index }}</view>
                <view class="flow-step__title">{{ item.title }}</view>
            </view>
        </view>

        <view class="section-card">
            <view class="section-head">
                <view>
                    <view class="section-title">标准机位</view>
                    <view class="section-desc">点击机位卡片进行拍照、补图、预览或重拍。</view>
                </view>
                <view class="text-action" @click="checkPopupVisible = true">查看质检</view>
            </view>

            <view class="scene-grid">
                <view
                    v-for="scene in shotScenes"
                    :key="scene.key"
                    class="scene-card"
                    :class="{ done: sceneDone(scene.key) }"
                    @click="openScene(scene.key)"
                >
                    <view class="scene-card__icon">
                        <u-icon :name="scene.icon" size="26" :color="sceneDone(scene.key) ? '#16a34a' : 'var(--hsx-primary)'"></u-icon>
                    </view>
                    <view class="scene-card__name">{{ scene.label }}</view>
                    <view class="scene-card__desc">{{ sceneHint(scene.key) }}</view>
                    <view v-if="sceneFirstImage(scene.key)" class="scene-card__thumb">
                        <image :src="sceneFirstImage(scene.key)" mode="aspectFill" />
                    </view>
                </view>
            </view>
        </view>

        <view class="section-card">
            <view class="section-head">
                <view>
                    <view class="section-title">商品图</view>
                    <view class="section-desc">已回传 {{ uploadedImages.length }} 张，待保存 {{ pendingImages.length }} 张。</view>
                </view>
                <view class="text-action" @click="loadInfo">刷新</view>
            </view>

            <scroll-view v-if="galleryImages.length" scroll-x class="gallery-scroll">
                <view class="gallery-row">
                    <view v-for="(item, index) in galleryImages" :key="`${item.key}-${index}`" class="gallery-item" @click="previewGallery(index)">
                        <image :src="item.url" mode="aspectFill" />
                        <view class="gallery-item__label">{{ sceneLabel(item.scene) }}</view>
                        <view v-if="item.status === 'done' && item.pending" class="gallery-item__badge">待回传</view>
                        <view v-if="item.status === 'uploading'" class="tile-mask" @click.stop>
                            <u-loading-icon mode="circle" color="#ffffff" size="22"></u-loading-icon>
                            <text class="tile-mask__txt">上传中</text>
                        </view>
                        <view v-else-if="item.status === 'failed'" class="tile-mask tile-mask--fail" @click.stop="retryGallery(item)">
                            <u-icon name="reload" color="#ffffff" size="22"></u-icon>
                            <text class="tile-mask__txt">点击重试</text>
                        </view>
                        <view class="gallery-item__del" @click.stop="removeGalleryItem(item)">
                            <u-icon name="close" color="#ffffff" size="13"></u-icon>
                        </view>
                    </view>
                </view>
            </scroll-view>
            <u-empty v-else text="暂无商品图，先拍正面图" mode="list"></u-empty>
        </view>

        <view class="footer-safe"></view>
        <view class="footer-actions">
            <u-button @click="openScene(nextSceneKey)" :customStyle="{ flex: 1 }">继续拍</u-button>
            <u-button type="primary" :loading="saveLoading || reviewLoading" :disabled="primaryDisabled" @click="handlePrimary" :customStyle="{ flex: 2 }">
                {{ primaryText }}
            </u-button>
        </view>

        <u-popup :show="scenePopupVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="scenePopupVisible = false">
            <view class="action-popup">
                <view class="popup-head">
                    <view>
                        <view class="popup-title">{{ activeSceneConfig.label }}</view>
                        <view class="popup-subtitle">{{ activeSceneImages.length ? `已有 ${activeSceneImages.length} 张图片` : '拍摄后会出现在对应机位' }}</view>
                    </view>
                    <text class="nc-iconfont nc-icon-guanbiV6xx1 popup-close" @click="scenePopupVisible = false"></text>
                </view>

                <view v-if="activeSceneImages.length" class="popup-images">
                    <view v-for="(item, index) in activeSceneImages" :key="`${item.key}-${index}`" class="popup-img">
                        <image :src="item.url" mode="aspectFill" @click="previewScene(index)" />
                        <view v-if="item.status === 'uploading'" class="tile-mask" @click.stop>
                            <u-loading-icon mode="circle" color="#ffffff" size="20"></u-loading-icon>
                        </view>
                        <view v-else-if="item.status === 'failed'" class="tile-mask tile-mask--fail" @click.stop="retryGallery(item)">
                            <u-icon name="reload" color="#ffffff" size="20"></u-icon>
                        </view>
                        <view v-if="item.pending" class="popup-img__del" @click.stop="removeGalleryItem(item)">
                            <u-icon name="close" color="#ffffff" size="12"></u-icon>
                        </view>
                    </view>
                </view>

                <view class="popup-actions">
                    <u-button type="primary" icon="camera-fill" @click="startBurst" :customStyle="{ flex: 1 }">连拍</u-button>
                    <u-button icon="photo" @click="chooseAlbum" :customStyle="{ flex: 1 }">相册</u-button>
                </view>
                <view class="popup-tip">拍一张自动接着拍下一张，拍完返回即可；图片在后台上传，红色可点击重试。</view>
                <view v-if="activeScenePending.length" class="danger-action" @click="clearActiveScenePending">清空本机位待回传图片</view>
            </view>
        </u-popup>

        <u-popup :show="checkPopupVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="checkPopupVisible = false">
            <view class="check-popup">
                <view class="popup-head">
                    <view>
                        <view class="popup-title">回收质检信息</view>
                        <view class="popup-subtitle">用于判断商品图重点和定价依据</view>
                    </view>
                    <text class="nc-iconfont nc-icon-guanbiV6xx1 popup-close" @click="checkPopupVisible = false"></text>
                </view>
                <scroll-view scroll-y class="check-popup__body">
                    <view v-if="checkEntries.length" class="check-list">
                        <view v-for="item in checkEntries" :key="item.key" class="check-row">
                            <text>{{ item.key }}</text>
                            <text>{{ item.value }}</text>
                        </view>
                    </view>
                    <u-empty v-else text="暂无质检信息" mode="list"></u-empty>
                </scroll-view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { img } from '@/utils/common'
import { uploadImage } from '@/app/api/system'
import { confirmAssetPhotos, createPhotoTask, getAssetInfo, reviewAssetMedia, saveAssetMedia } from '@/addon/hsx_device_asset/api/device_asset'

type SceneKey = 'front' | 'back' | 'side' | 'flaw'

const assetId = ref('')
const asset = ref<any>({})
interface PendingShot { key: string; scene: SceneKey; status: 'uploading' | 'done' | 'failed'; localUrl: string; path: string }
const pendingImages = ref<PendingShot[]>([])
const loading = ref(false)
const saveLoading = ref(false)
const reviewLoading = ref(false)
const scenePopupVisible = ref(false)
const checkPopupVisible = ref(false)
const activeScene = ref<SceneKey>('front')
const uid = () => `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
const anyUploading = computed(() => pendingImages.value.some(p => p.status === 'uploading'))

const shotScenes: Array<{ key: SceneKey; label: string; icon: string }> = [
    { key: 'front', label: '正面', icon: 'camera' },
    { key: 'back', label: '背面', icon: 'photo' },
    { key: 'side', label: '边框', icon: 'grid' },
    { key: 'flaw', label: '瑕疵', icon: 'error-circle' }
]

onLoad((options: any) => {
    assetId.value = String(options?.id || '')
    if (!assetId.value) {
        uni.showToast({ title: '缺少资产ID', icon: 'none' })
        return
    }
    loadInfo()
    handleCreateTask()
})

const uploadedImages = computed(() => {
    return (asset.value.media || [])
        .filter((item: any) => item.media_type !== 'video' && item.status !== 'rejected')
        .map((item: any) => ({ id: Number(item.id), key: `u${item.id}`, url: img(item.url), rawUrl: item.url, scene: normalizeScene(item.scene), pending: false, status: 'done' }))
})

const galleryImages = computed(() => [
    ...uploadedImages.value,
    ...pendingImages.value.map(item => ({ id: 0, key: item.key, url: item.localUrl, rawUrl: item.path, scene: item.scene, pending: true, status: item.status }))
])

const flowSteps = computed(() => {
    const hasMedia = uploadedImages.value.length + pendingImages.value.length > 0
    const returned = uploadedImages.value.length > 0 && !pendingImages.value.length
    const done = asset.value.photo_status === 'approved'
    return [
        { key: 'capture', index: 1, title: '拍摄', done: hasMedia, active: !hasMedia },
        { key: 'return', index: 2, title: '回传', done: returned || done, active: hasMedia && !returned && !done },
        { key: 'done', index: 3, title: '完成', done: done, active: returned && !done }
    ]
})

const activeSceneConfig = computed(() => shotScenes.find(item => item.key === activeScene.value) || shotScenes[0])
const activeSceneImages = computed(() => galleryImages.value.filter(item => item.scene === activeScene.value))
const activeScenePending = computed(() => pendingImages.value.filter(item => item.scene === activeScene.value))
const nextSceneKey = computed<SceneKey>(() => shotScenes.find(item => !sceneDone(item.key))?.key || 'front')

const checkEntries = computed(() => {
    const device = asset.value?.recycle_device || asset.value?.recycleDevice || {}
    const data = {
        ...normalizeCheckResult(device.check_result_seller || '', '卖家质检'),
        ...normalizeCheckResult(device.check_result_buyer || '', '买家质检'),
        ...normalizeObject(asset.value?.check_summary || {})
    }
    if (!data['容量'] && (asset.value?.ext_json?.capacity || device.capacity)) data['容量'] = asset.value?.ext_json?.capacity || device.capacity
    if (!data['颜色'] && (asset.value?.ext_json?.color || device.color)) data['颜色'] = asset.value?.ext_json?.color || device.color
    return Object.entries(data)
        .filter(([, value]) => value !== '' && value !== null && value !== undefined)
        .map(([key, value]) => ({
            key,
            value: typeof value === 'object' ? JSON.stringify(value) : String(value)
        }))
})

// 拍照员当场删糊图、留好图，传完一键「拍照完成」直接进待定价（不需要单独复检审核）
const isDone = computed(() => asset.value.photo_status === 'approved')
const primaryText = computed(() => {
    if (pendingImages.value.length) return '保存并回传'
    if (isDone.value) return '已完成拍照'
    return '拍照完成'
})
const primaryDisabled = computed(() => {
    if (pendingImages.value.length) return anyUploading.value
    if (isDone.value) return true
    return uploadedImages.value.length === 0
})
const handlePrimary = () => {
    if (pendingImages.value.length) return handleSave()
    return handleFinishPhoto()
}
const handleFinishPhoto = async () => {
    if (!uploadedImages.value.length) {
        uni.showToast({ title: '请先拍照并回传', icon: 'none' })
        return
    }
    reviewLoading.value = true
    try {
        await confirmAssetPhotos(assetId.value)
        uni.showToast({ title: '拍照完成，进入待定价', icon: 'none' })
        const pages = getCurrentPages()
        setTimeout(() => {
            if (pages.length > 1) {
                uni.navigateBack()
            } else {
                uni.redirectTo({ url: '/addon/hsx_device_asset/pages/task/list?tab=price' })
            }
        }, 600)
    } finally {
        reviewLoading.value = false
    }
}

// 拍照员自己删坏图/糊图：未回传的直接从本地移除；已回传的标记删除(不会用于上架)
const removeGalleryItem = (item: any) => {
    if (item.pending) {
        const i = pendingImages.value.findIndex(p => p.key === item.key)
        if (i >= 0) pendingImages.value.splice(i, 1)
        return
    }
    if (!item.id) return
    uni.showModal({
        title: '删除图片',
        content: '这张不清晰/不要了？删除后不会用于上架。',
        confirmText: '删除',
        confirmColor: '#ef4444',
        success: async (r) => {
            if (!r.confirm) return
            await reviewAssetMedia(item.id, { status: 'rejected', reject_reason: '拍照员删除' })
            await loadInfo()
        }
    })
}

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
    try {
        await createPhotoTask(assetId.value, {
            source: 'mobile',
            station_id: '',
            remark: '移动端扫码拍摄商品图'
        })
    } catch {
        // 已有任务或网络异常不阻断拍照，保存媒体时仍可回传。
    }
}

const openScene = (scene: SceneKey) => {
    activeScene.value = scene
    scenePopupVisible.value = true
}

// 拍一张 → 本地缩略图立刻占位（角标转圈）→ 后台上传，不阻塞下一张
const addAndUpload = (localPath: string, scene: SceneKey) => {
    const key = uid()
    pendingImages.value.push({ key, scene, status: 'uploading', localUrl: localPath, path: '' })
    uploadOne(key, localPath)
}

const uploadOne = async (key: string, localPath: string) => {
    try {
        const res: any = await uploadImage({ filePath: localPath, name: 'file' })
        const path = res?.data?.url || res?.data?.path || ''
        const item = pendingImages.value.find(p => p.key === key)
        if (!item) return
        if (path) { item.path = path; item.status = 'done' }
        else { item.status = 'failed' }
    } catch {
        const item = pendingImages.value.find(p => p.key === key)
        if (item) item.status = 'failed'
    }
}

const retryGallery = (item: any) => {
    const target = pendingImages.value.find(p => p.key === item.key)
    if (!target) return
    target.status = 'uploading'
    uploadOne(target.key, target.localUrl)
}

// 拍一张相机会自动返回；这里拍完立刻再开相机，达到连拍效果，用户取消即结束
const chooseOnePhoto = () => new Promise<string>((resolve, reject) => {
    uni.chooseImage({
        count: 1,
        sourceType: ['camera'],
        sizeType: ['compressed', 'original'],
        success: (res: any) => {
            const p = res?.tempFiles?.[0]?.path || res?.tempFilePaths?.[0] || ''
            p ? resolve(p) : reject(new Error('empty'))
        },
        fail: () => reject(new Error('cancel'))
    })
})

const startBurst = async () => {
    // eslint-disable-next-line no-constant-condition
    while (true) {
        if (pendingImages.value.length >= 18) {
            uni.showToast({ title: '最多保留18张待回传图片', icon: 'none' })
            break
        }
        let localPath = ''
        try {
            localPath = await chooseOnePhoto()
        } catch {
            break // 用户取消相机 → 结束连拍
        }
        if (!localPath) break
        addAndUpload(localPath, activeScene.value)
    }
}

const chooseAlbum = () => {
    const remain = 18 - pendingImages.value.length
    if (remain <= 0) {
        uni.showToast({ title: '最多保留18张待回传图片', icon: 'none' })
        return
    }
    uni.chooseImage({
        count: Math.min(remain, 9),
        sourceType: ['album'],
        sizeType: ['compressed', 'original'],
        success: (res: any) => {
            const files = Array.isArray(res.tempFiles) ? res.tempFiles : []
            for (const file of files) {
                const filePath = file?.path || ''
                if (filePath) addAndUpload(filePath, activeScene.value)
            }
        },
        fail: (error: any) => {
            if (error?.errMsg && !String(error.errMsg).includes('cancel')) {
                uni.showToast({ title: error.errMsg, icon: 'none' })
            }
        }
    })
}

const handleSave = async () => {
    if (anyUploading.value) {
        uni.showToast({ title: '图片上传中，请稍候', icon: 'none' })
        return
    }
    const ready = pendingImages.value.filter(p => p.status === 'done' && p.path)
    if (!ready.length) {
        uni.showToast({ title: '请先拍照', icon: 'none' })
        return
    }
    saveLoading.value = true
    try {
        await saveAssetMedia(assetId.value, {
            media: ready.map((item, index) => ({
                url: item.path,
                media_type: 'image',
                scene: item.scene,
                source: 'mobile',
                sort: uploadedImages.value.length + index + 1
            }))
        })
        // 已回传的清掉；失败的保留，便于重试
        pendingImages.value = pendingImages.value.filter(p => p.status !== 'done')
        scenePopupVisible.value = false
        uni.showToast({ title: '已回传到资产', icon: 'none' })
        await loadInfo()
    } finally {
        saveLoading.value = false
    }
}

const previewGallery = (index: number) => {
    const urls = galleryImages.value.map(item => item.url)
    uni.previewImage({ urls, current: urls[index] })
}

const previewScene = (index: number) => {
    const urls = activeSceneImages.value.map(item => item.url)
    uni.previewImage({ urls, current: urls[index] })
}

const clearActiveScenePending = () => {
    pendingImages.value = pendingImages.value.filter(item => item.scene !== activeScene.value)
}

const sceneDone = (scene: SceneKey) => galleryImages.value.some(item => item.scene === scene)
const sceneFirstImage = (scene: SceneKey) => galleryImages.value.find(item => item.scene === scene)?.url || ''
const sceneHint = (scene: SceneKey) => sceneDone(scene) ? '已拍摄' : (scene === 'flaw' ? '有瑕疵再拍' : '待拍摄')
const sceneLabel = (scene: string) => shotScenes.find(item => item.key === scene)?.label || '商品图'
const normalizeScene = (scene = ''): SceneKey => {
    return ['front', 'back', 'side', 'flaw'].includes(scene) ? scene as SceneKey : 'front'
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

const normalizeCheckResult = (value: any, label: string) => {
    if (!value) return {}
    if (typeof value === 'object') return value
    try {
        const parsed = JSON.parse(value)
        return typeof parsed === 'object' && parsed ? parsed : { [label]: value }
    } catch {
        return { [label]: value }
    }
}
</script>

<style lang="scss" scoped>
.asset-capture {
    min-height: 100vh;
    padding: 24rpx 24rpx 0;
    background: #f6f7fb;
    box-sizing: border-box;
}

.top-card,
.section-card,
.flow-card {
    margin-bottom: 20rpx;
    padding: 24rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.top-card {
    display: flex;
    justify-content: space-between;
    gap: 18rpx;
}

.asset-title {
    color: #111827;
    font-size: 34rpx;
    font-weight: 700;
    line-height: 1.35;
}

.asset-meta,
.section-desc,
.popup-subtitle {
    color: #64748b;
    font-size: 24rpx;
    line-height: 1.6;
}

.flow-card {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12rpx;
}

.flow-step {
    display: flex;
    align-items: center;
    gap: 10rpx;
    color: #94a3b8;
}

.flow-step__dot {
    width: 38rpx;
    height: 38rpx;
    line-height: 38rpx;
    border-radius: 50%;
    text-align: center;
    background: #e5e7eb;
    font-size: 22rpx;
    font-weight: 700;
}

.flow-step__title {
    font-size: 24rpx;
    font-weight: 650;
}

.flow-step.active {
    color: var(--hsx-primary);
}

.flow-step.done {
    color: #16a34a;
}

.flow-step.active .flow-step__dot {
    color: #fff;
    background: var(--hsx-primary);
}

.flow-step.done .flow-step__dot {
    color: #fff;
    background: #16a34a;
}

.section-head,
.popup-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18rpx;
    margin-bottom: 20rpx;
}

.section-title,
.popup-title {
    color: #111827;
    font-size: 30rpx;
    font-weight: 700;
}

.text-action {
    flex: 0 0 auto;
    color: var(--hsx-primary);
    font-size: 25rpx;
    line-height: 44rpx;
}

.scene-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16rpx;
}

.scene-card {
    position: relative;
    min-height: 210rpx;
    padding: 20rpx;
    border: 2rpx solid #e5e7eb;
    border-radius: 18rpx;
    background: #f8fafc;
    overflow: hidden;
}

.scene-card.done {
    border-color: #86efac;
    background: #f0fdf4;
}

.scene-card__icon {
    width: 58rpx;
    height: 58rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 18rpx;
    background: #fff;
}

.scene-card__name {
    margin-top: 16rpx;
    color: #111827;
    font-size: 30rpx;
    font-weight: 700;
}

.scene-card__desc {
    margin-top: 6rpx;
    color: #64748b;
    font-size: 23rpx;
}

.scene-card__thumb {
    position: absolute;
    right: 14rpx;
    bottom: 14rpx;
    width: 86rpx;
    height: 86rpx;
    border-radius: 14rpx;
    overflow: hidden;
}

.scene-card__thumb image,
.gallery-item image,
.popup-images image {
    width: 100%;
    height: 100%;
}

.gallery-scroll {
    width: 100%;
    white-space: nowrap;
}

.gallery-row {
    display: flex;
    gap: 14rpx;
}

.gallery-item {
    position: relative;
    width: 188rpx;
    height: 188rpx;
    flex: 0 0 188rpx;
    border-radius: 18rpx;
    overflow: hidden;
    background: #e5e7eb;
}

.gallery-item__label,
.gallery-item__badge {
    position: absolute;
    left: 10rpx;
    bottom: 10rpx;
    padding: 4rpx 10rpx;
    border-radius: 999rpx;
    color: #fff;
    background: rgba(15, 23, 42, 0.72);
    font-size: 21rpx;
}

.gallery-item__badge {
    left: auto;
    right: 10rpx;
    background: rgba(37, 99, 235, 0.86);
}

.gallery-item__del {
    position: absolute;
    top: 6rpx;
    right: 6rpx;
    width: 40rpx;
    height: 40rpx;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.55);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20rpx;
    z-index: 2;
}

.footer-safe {
    height: 128rpx;
}

.footer-actions {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    gap: 16rpx;
    padding: 18rpx 24rpx calc(18rpx + env(safe-area-inset-bottom));
    background: rgba(246, 247, 251, 0.96);
    box-shadow: 0 -8rpx 24rpx rgba(15, 23, 42, 0.06);
}

.action-popup,
.check-popup {
    padding: 28rpx 28rpx calc(28rpx + env(safe-area-inset-bottom));
    background: #fff;
}

.popup-close {
    color: #64748b;
    font-size: 34rpx;
}

.popup-images {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12rpx;
    margin-bottom: 18rpx;
}

.popup-img {
    position: relative;
    height: 160rpx;
    border-radius: 14rpx;
    overflow: hidden;
    background: #f1f5f9;
}

.popup-img image {
    width: 100%;
    height: 100%;
}

.popup-img__del {
    position: absolute;
    top: 6rpx;
    right: 6rpx;
    width: 36rpx;
    height: 36rpx;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
}

.popup-tip {
    margin-bottom: 22rpx;
    color: #94a3b8;
    font-size: 22rpx;
    line-height: 1.5;
}

/* 图块上传中 / 失败 蒙层 */
.tile-mask {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8rpx;
    background: rgba(15, 23, 42, 0.5);
    z-index: 1;
}

.tile-mask--fail {
    background: rgba(220, 38, 38, 0.6);
}

.tile-mask__txt {
    color: #fff;
    font-size: 20rpx;
}

.popup-actions {
    display: flex;
    gap: 16rpx;
}

.danger-action {
    margin-top: 22rpx;
    color: #dc2626;
    text-align: center;
    font-size: 25rpx;
}

.check-popup__body {
    max-height: 720rpx;
}

.check-list {
    display: grid;
    gap: 12rpx;
}

.check-row {
    padding: 18rpx;
    border-radius: 16rpx;
    background: #f8fafc;
    font-size: 25rpx;
    line-height: 1.55;
}

.check-row text:first-child {
    display: block;
    margin-bottom: 6rpx;
    color: #64748b;
}

.check-row text:last-child {
    color: #111827;
    font-weight: 600;
}
</style>
