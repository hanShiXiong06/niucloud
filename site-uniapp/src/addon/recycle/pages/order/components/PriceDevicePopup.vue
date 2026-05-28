<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="price-popup">
            <!-- 头部 -->
            <view class="price-header">
                <view class="price-title">设备定价</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx]" @click="handleClose"></text>
            </view>

            <!-- 设备信息 -->
            <view class="device-info">
                <view class="device-info-row">
                    <text class="device-icon">📱</text>
                    <view class="device-main">
                        <view class="device-model">{{ device.model || '未知型号' }}</view>
                        <view class="device-meta">
                            <text v-if="device.imei">IMEI: {{ device.imei }}</text>
                        </view>
                    </view>
                    <view v-if="device.status_name" class="device-status">
                        <u-tag :text="device.status_name" type="primary" size="mini"></u-tag>
                    </view>
                </view>
            </view>

            <!-- 表单内容 -->
            <view class="price-content">
                <!-- 质检结果展示 -->
                <view v-if="device.check_result || device.check_result_seller" class="form-section">
                    <view class="section-title">
                        <text>质检结果</text>
                        <text v-if="detailLoading" class="section-loading">加载中...</text>
                    </view>
                    <view class="check-result-box">
                        {{ device.check_result_seller || device.check_result }}
                    </view>
                </view>

                <view v-if="imageGroups.length" class="form-section">
                    <view class="section-title">质检图片</view>
                    <view v-for="group in imageGroups" :key="group.label" class="image-group">
                        <view v-if="imageGroups.length > 1" class="image-group__label">{{ group.label }}</view>
                        <scroll-view scroll-x class="image-scroll">
                            <view class="image-row">
                                <image
                                    v-for="(item, imageIndex) in group.items"
                                    :key="item.url"
                                    class="preview-image"
                                    :src="item.thumb"
                                    mode="aspectFill"
                                    @click="previewImages(group.items, imageIndex)"
                                />
                            </view>
                        </scroll-view>
                    </view>
                </view>
                <view v-else-if="detailLoading" class="form-section">
                    <view class="section-title">质检图片</view>
                    <view class="image-empty">正在加载质检图片...</view>
                </view>

                <!-- 价格对比 -->
                <view class="form-section">
                    <view class="section-title">价格信息</view>
                    <view class="price-compare">
                        <view v-if="device.initial_price && Number(device.initial_price) > 0" class="price-item">
                            <text class="price-label">初始报价</text>
                            <text class="price-value price-value--initial">¥{{ device.initial_price }}</text>
                        </view>
                        <view v-if="device.before_price && device.before_price != device.initial_price" class="price-item">
                            <text class="price-label">上次定价</text>
                            <text class="price-value price-value--before">¥{{ device.before_price }}</text>
                        </view>
                    </view>
                </view>

                <!-- 最终定价 -->
                <view class="form-section">
                    <view class="section-title">
                        <text>最终定价</text>
                        <text class="text-[#e6a23c] text-[24rpx] ml-[16rpx]">*必填</text>
                    </view>
                    <view class="price-input-wrapper">
                        <text class="price-symbol">¥</text>
                        <input
                            v-model="formData.final_price"
                            type="digit"
                            placeholder="请输入回收价格"
                            class="price-input"
                        />
                    </view>
                    <view v-if="device.initial_price && Number(device.initial_price) > 0" class="text-[22rpx] text-[#999] mt-[8rpx]">
                        参考预估：¥{{ device.initial_price }}
                    </view>
                </view>

                <!-- 卖货价格 -->
                <view class="form-section">
                    <view class="section-title">卖货价格 <text class="text-[22rpx] text-[#999]">（选填，内部使用）</text></view>
                    <view class="price-input-wrapper">
                        <text class="price-symbol">¥</text>
                        <input
                            v-model="formData.sell_price"
                            type="digit"
                            placeholder="选填"
                            class="price-input"
                        />
                    </view>
                </view>

                <!-- 调价说明 -->
                <view class="form-section">
                    <view class="section-title">价格备注</view>
                    <u-textarea
                        v-model="formData.remark"
                        placeholder="请输入定价理由或扣费说明..."
                        :maxlength="200"
                        :height="120"
                        count
                    ></u-textarea>
                </view>
            </view>

            <!-- 底部按钮 -->
            <view class="price-footer">
                <u-button @click="handleClose" :customStyle="{flex: 1, marginRight: '20rpx'}">
                    取消
                </u-button>
                <u-button type="primary" @click="handleSubmit" :customStyle="{flex: 2}" :loading="submitting">
                    确认定价
                </u-button>
            </view>
        </view>
    </u-popup>

    <ImagePreviewOverlay
        v-model:visible="previewVisible"
        :urls="previewUrls"
        :current="previewCurrent"
        @change="previewCurrent = $event"
    />
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { confirmPrice, getDevice } from '@/addon/recycle/api/order'
import { img } from '@/utils/common'
import ImagePreviewOverlay from '@/addon/recycle/components/ImagePreviewOverlay.vue'

interface Props {
    visible: boolean
    deviceData: any
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'success'])

const show = ref(false)
const submitting = ref(false)
const detailLoading = ref(false)
const previewVisible = ref(false)
const previewUrls = ref<string[]>([])
const previewCurrent = ref(0)
const deviceDetail = ref<any>(null)
const device = computed(() => deviceDetail.value || props.deviceData || {})

const formData = ref({
    final_price: '',
    sell_price: '',
    remark: ''
})

type ImageItem = {
    url: string
    thumb: string
}

const imageGroups = computed(() => {
    const groups: Array<{ label: string, items: ImageItem[] }> = []
    const current = device.value || {}
    const sellerImages = buildImageItems(current.check_images_seller || current.check_images, current.check_images_seller_thumb_small || current.check_images_thumb_small)
    const buyerImages = buildImageItems(current.check_images_buyer, current.check_images_buyer_thumb_small)
    const summaryImages = buildImageItems(current.check_images, current.check_images_thumb_small)

    if (sellerImages.length) groups.push({ label: '卖家质检图片', items: sellerImages })
    if (buyerImages.length) groups.push({ label: '买家质检图片', items: buyerImages })
    if (!groups.length && summaryImages.length) groups.push({ label: '质检图片', items: summaryImages })

    return groups
})

const buildImageItems = (rawValue: any, thumbs: any) => {
    const urls = String(rawValue || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .map((item) => img(item))

    const thumbList = Array.isArray(thumbs)
        ? thumbs.map((item: string) => img(item))
        : String(thumbs || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean)
            .map((item) => img(item))

    return urls.map((url, index) => ({
        url,
        thumb: thumbList[index] || url
    }))
}

watch(() => props.visible, (val) => {
    show.value = val
    if (val && props.deviceData) {
        deviceDetail.value = props.deviceData
        const fp = props.deviceData.final_price
        formData.value = {
            final_price: (fp && Number(fp) > 0) ? fp : (props.deviceData.initial_price || ''),
            sell_price: props.deviceData.sell_price || '',
            remark: props.deviceData.remark || ''
        }
        loadDeviceDetail()
    } else if (!val) {
        deviceDetail.value = null
        detailLoading.value = false
    }
})

const loadDeviceDetail = async () => {
    const id = props.deviceData?.id
    if (!id) return
    detailLoading.value = true
    try {
        const res: any = await getDevice(id)
        const detail = res?.data || null
        if (!detail) return
        deviceDetail.value = {
            ...(props.deviceData || {}),
            ...detail
        }
        const fp = deviceDetail.value.final_price
        formData.value = {
            final_price: (fp && Number(fp) > 0) ? fp : (deviceDetail.value.initial_price || ''),
            sell_price: deviceDetail.value.sell_price || '',
            remark: deviceDetail.value.remark || ''
        }
    } catch (error) {
        // 图片只是辅助信息，加载失败不阻断定价。
    } finally {
        detailLoading.value = false
    }
}

const handleClose = () => {
    emit('update:visible', false)
}

const previewImages = (items: ImageItem[], index: number) => {
    if (!items.length) return
    previewUrls.value = items.map((item) => item.url)
    previewCurrent.value = Math.max(0, Math.min(index, items.length - 1))
    previewVisible.value = true
}

const handleSubmit = async () => {
    if (!device.value?.id) {
        uni.showToast({ title: '请选择设备', icon: 'none' })
        return
    }

    if (!formData.value.final_price || Number(formData.value.final_price) <= 0) {
        uni.showToast({ title: '请输入有效的回收价格', icon: 'none' })
        return
    }

    submitting.value = true
    try {
        await confirmPrice(device.value.id, {
            final_price: formData.value.final_price,
            sell_price: formData.value.sell_price || 0,
            remark: formData.value.remark
        })

        uni.showToast({ title: '定价成功' })
        emit('success')
        handleClose()
    } catch (error: any) {
        uni.showToast({
            title: error.message || '定价失败',
            icon: 'none'
        })
    } finally {
        submitting.value = false
    }
}
</script>

<style scoped lang="scss">
.price-popup {
    background: #fff;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.price-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 30rpx;
    border-bottom: 1rpx solid #f5f5f5;
}

.price-title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
}

.device-info {
    padding: 24rpx 30rpx;
    background: #f8f9fa;
}

.device-info-row {
    display: flex;
    align-items: center;
}

.device-icon {
    font-size: 48rpx;
    margin-right: 16rpx;
}

.device-main {
    flex: 1;
}

.device-model {
    font-size: 28rpx;
    font-weight: 500;
    color: #333;
    margin-bottom: 8rpx;
}

.device-meta {
    font-size: 24rpx;
    color: #999;
}

.device-status {
    margin-left: 16rpx;
}

.price-content {
    flex: 1;
    padding: 20rpx 30rpx;
    overflow-y: auto;
}

.form-section {
    margin-bottom: 32rpx;
}

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    font-size: 28rpx;
    font-weight: 500;
    color: #333;
    margin-bottom: 16rpx;
}

.section-loading {
    flex-shrink: 0;
    font-size: 22rpx;
    color: #909399;
    font-weight: 400;
}

.check-result-box {
    padding: 20rpx;
    background: #f8f9fa;
    border-radius: 12rpx;
    font-size: 26rpx;
    color: #666;
    line-height: 1.6;
}

.price-compare {
    display: flex;
    gap: 24rpx;
}

.price-item {
    flex: 1;
    padding: 20rpx;
    background: #f8f9fa;
    border-radius: 12rpx;
    text-align: center;
}

.price-label {
    display: block;
    font-size: 24rpx;
    color: #999;
    margin-bottom: 8rpx;
}

.price-value {
    display: block;
    font-size: 32rpx;
    font-weight: bold;
}

.price-value--initial {
    color: #909399;
}

.price-value--before {
    color: #e6a23c;
}

.price-input-wrapper {
    display: flex;
    align-items: center;
    padding: 20rpx 24rpx;
    background: #f8f9fa;
    border-radius: 12rpx;
    border: 2rpx solid #e4e7ed;
}

.price-symbol {
    font-size: 36rpx;
    font-weight: bold;
    color: #e6a23c;
    margin-right: 12rpx;
}

.price-input {
    flex: 1;
    font-size: 36rpx;
    font-weight: bold;
    color: #333;
}

.image-group + .image-group {
    margin-top: 16rpx;
}

.image-group__label {
    margin-bottom: 10rpx;
    font-size: 22rpx;
    color: #64748b;
}

.image-scroll {
    width: 100%;
    white-space: nowrap;
}

.image-row {
    display: flex;
    gap: 14rpx;
    padding-bottom: 4rpx;
}

.image-empty {
    padding: 22rpx;
    border-radius: 12rpx;
    background: #f8f9fa;
    font-size: 24rpx;
    color: #909399;
}

.preview-image {
    width: 132rpx;
    height: 132rpx;
    border-radius: 12rpx;
    flex-shrink: 0;
    background: #f1f5f9;
}

.price-footer {
    display: flex;
    padding: 20rpx 30rpx;
    border-top: 1rpx solid #f5f5f5;
    background: #fff;
}
</style>
