<template>
    <view class="price-page">
        <view class="asset-card">
            <view class="asset-card__head">
                <view>
                    <view class="asset-title">{{ asset.model || asset.asset_no || '资产定价' }}</view>
                    <view class="asset-meta">资产 {{ asset.asset_no || '-' }} / IMEI {{ asset.imei || '-' }}</view>
                    <view v-if="isPriceCompleted" class="asset-done-tip">定价已完成，当前资产已进入待导出链路。</view>
                </view>
                <u-tag :text="asset.price_status_name || asset.price_status || '待定价'" type="primary" size="mini"></u-tag>
            </view>
            <view class="asset-actions">
                <view @click="checkPopupVisible = true">质检信息</view>
                <view @click="loadInfo">刷新</view>
            </view>
        </view>

        <view class="price-board">
            <view class="price-board__item">
                <text>回收成本</text>
                <strong>¥{{ money(asset.recycle_final_price) }}</strong>
            </view>
            <view class="price-board__item primary" @click="openPriceEditor">
                <text>销售价</text>
                <strong>{{ form.sale_price ? `¥${money(form.sale_price)}` : '未设置' }}</strong>
            </view>
            <view class="price-board__item" :class="{ danger: grossProfit < 0 }">
                <text>预估毛利</text>
                <strong>¥{{ money(grossProfit) }}</strong>
            </view>
        </view>

        <view v-if="priceWarnings.length" class="warning-card" @click="warningPopupVisible = true">
            <view>
                <view class="warning-card__title">定价提醒</view>
                <view class="warning-card__desc">{{ priceWarnings[0] }}</view>
            </view>
            <u-icon name="arrow-right" color="#c2410c" size="16"></u-icon>
        </view>

        <view class="section-card">
            <view class="section-head">
                <view>
                    <view class="section-title">商品图</view>
                    <view class="section-desc">{{ allImages.length }} 张参考图，点击可全屏预览。</view>
                </view>
                <view class="text-action" @click="preview(0)">预览</view>
            </view>
            <view v-if="mainImage" class="main-image" @click="preview(activeImageIndex)">
                <image :src="img(mainImage)" mode="aspectFill" />
            </view>
            <scroll-view v-if="allImages.length" scroll-x class="thumb-scroll">
                <view class="thumb-row">
                    <view
                        v-for="(url, index) in allImages"
                        :key="`${url}-${index}`"
                        class="thumb-item"
                        :class="{ active: activeImageIndex === index }"
                        @click="activeImageIndex = index"
                    >
                        <image :src="img(url)" mode="aspectFill" />
                    </view>
                </view>
            </scroll-view>
            <u-empty v-else text="暂无商品图，请先完成拍照回传" mode="list"></u-empty>
        </view>

        <view class="section-card">
            <view class="section-head">
                <view>
                    <view class="section-title">价格设置</view>
                <view class="section-desc">点击任意价格项打开定价弹窗，一次完成价格和备注。</view>
                </view>
            </view>
            <view class="setting-list">
                <view class="setting-row" @click="openPriceEditor">
                    <text>销售价</text>
                    <strong>{{ form.sale_price ? `¥${money(form.sale_price)}` : '点击设置' }}</strong>
                </view>
                <view class="setting-row" @click="openPriceEditor">
                    <text>同行价</text>
                    <strong>{{ form.peer_price ? `¥${money(form.peer_price)}` : '选填' }}</strong>
                </view>
                <view class="setting-row" @click="openPriceEditor">
                    <text>最低价</text>
                    <strong>{{ form.min_price ? `¥${money(form.min_price)}` : '选填' }}</strong>
                </view>
                <view class="setting-row" @click="openPriceEditor">
                    <text>备注</text>
                    <strong>{{ form.remark ? '已填写' : '点击填写' }}</strong>
                </view>
            </view>
        </view>

        <view class="footer-safe"></view>
        <view class="footer-actions">
            <u-button v-if="!isPriceCompleted" @click="checkPopupVisible = true" :customStyle="{ flex: 1 }">质检</u-button>
            <u-button type="primary" :loading="submitting" @click="openPriceEditor" :customStyle="{ flex: 2 }">
                {{ pricePrimaryText }}
            </u-button>
        </view>

        <u-popup :show="pricePopupVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="pricePopupVisible = false">
            <view class="edit-popup price-edit-popup">
                <view class="popup-head">
                    <view>
                        <view class="popup-title">定价确认</view>
                        <view class="popup-subtitle">一次填写销售价、同行价、最低价和备注，确认后直接保存。</view>
                    </view>
                    <text class="nc-iconfont nc-icon-guanbiV6xx1 popup-close" @click="pricePopupVisible = false"></text>
                </view>

                <view class="price-popup-summary">
                    <view>
                        <text>回收成本</text>
                        <strong>¥{{ money(asset.recycle_final_price) }}</strong>
                    </view>
                    <view :class="{ danger: editGrossProfit < 0 }">
                        <text>预估毛利</text>
                        <strong>¥{{ money(editGrossProfit) }}</strong>
                    </view>
                </view>

                <view class="price-form-list">
                    <view class="price-form-row required">
                        <text>销售价</text>
                        <view class="price-form-input">
                            <text>¥</text>
                            <input v-model="priceDraft.sale_price" type="digit" placeholder="请输入销售价" focus />
                        </view>
                    </view>
                    <view class="price-form-row">
                        <text>同行价</text>
                        <view class="price-form-input">
                            <text>¥</text>
                            <input v-model="priceDraft.peer_price" type="digit" placeholder="选填" />
                        </view>
                    </view>
                    <view class="price-form-row">
                        <text>最低价</text>
                        <view class="price-form-input">
                            <text>¥</text>
                            <input v-model="priceDraft.min_price" type="digit" placeholder="选填" />
                        </view>
                    </view>
                </view>

                <view v-if="draftWarnings.length" class="draft-warning-list">
                    <view v-for="item in draftWarnings" :key="item">{{ item }}</view>
                </view>

                <u-textarea v-model="priceDraft.remark" placeholder="定价备注：成色、渠道、底价说明等" :height="130" :maxlength="300" count></u-textarea>

                <view class="popup-actions sticky-popup-actions">
                    <u-button @click="pricePopupVisible = false" :customStyle="{ flex: 1 }">取消</u-button>
                    <u-button type="primary" :loading="submitting" @click="confirmAndSubmitPrice" :customStyle="{ flex: 2 }">确认定价</u-button>
                </view>
            </view>
        </u-popup>

        <u-popup :show="checkPopupVisible" mode="center" round="18" :safeAreaInsetBottom="false" @close="checkPopupVisible = false">
            <view class="check-popup center-popup">
                <view class="popup-head">
                    <view>
                        <view class="popup-title">回收质检信息</view>
                        <view class="popup-subtitle">定价前重点看质检结论和瑕疵描述。</view>
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
                    <u-empty v-else text="暂无质检摘要" mode="list"></u-empty>
                </scroll-view>
            </view>
        </u-popup>

        <u-popup :show="warningPopupVisible" mode="center" round="18" :safeAreaInsetBottom="false" @close="warningPopupVisible = false">
            <view class="edit-popup center-popup">
                <view class="popup-head">
                    <view>
                        <view class="popup-title">定价提醒</view>
                        <view class="popup-subtitle">保存前建议处理这些风险。</view>
                    </view>
                    <text class="nc-iconfont nc-icon-guanbiV6xx1 popup-close" @click="warningPopupVisible = false"></text>
                </view>
                <view class="warning-list">
                    <view v-for="item in priceWarnings" :key="item">{{ item }}</view>
                </view>
                <view class="popup-actions">
                    <u-button type="primary" @click="warningPopupVisible = false" :customStyle="{ flex: 1 }">知道了</u-button>
                </view>
            </view>
        </u-popup>
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
const activeImageIndex = ref(0)
const pricePopupVisible = ref(false)
const checkPopupVisible = ref(false)
const warningPopupVisible = ref(false)
const form = reactive({
    sale_price: '',
    peer_price: '',
    min_price: '',
    remark: ''
})
const priceDraft = reactive({
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
        activeImageIndex.value = 0
    } finally {
        loading.value = false
    }
}

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

const allImages = computed(() => {
    const checkImages = recycleCheckImages(asset.value)
    const assetImages = (asset.value?.media || [])
        .filter((item: any) => item.media_type !== 'video' && item.status !== 'rejected')
        .map((item: any) => item.url)
        .filter(Boolean)
    return [...assetImages, ...checkImages].filter((url, index, arr) => url && arr.indexOf(url) === index)
})

const mainImage = computed(() => allImages.value[activeImageIndex.value] || allImages.value[0] || '')
const isPriceCompleted = computed(() => asset.value?.price_status === 'completed' || Number(asset.value?.priced_at || 0) > 0)
const pricePrimaryText = computed(() => {
    if (isPriceCompleted.value) return '重新定价'
    return form.sale_price ? '确认定价' : '填写定价'
})
const grossProfit = computed(() => Number(form.sale_price || 0) - Number(asset.value?.recycle_final_price || 0))
const editGrossProfit = computed(() => Number(priceDraft.sale_price || 0) - Number(asset.value?.recycle_final_price || 0))
const priceWarnings = computed(() => {
    const warnings: string[] = []
    const salePrice = Number(form.sale_price || 0)
    const minPrice = Number(form.min_price || 0)
    const costPrice = Number(asset.value?.recycle_final_price || 0)
    if (salePrice > 0 && costPrice > 0 && salePrice < costPrice) warnings.push('销售价低于回收成本，请确认是否亏损出货')
    if (salePrice > 0 && minPrice > salePrice) warnings.push('最低价不能高于销售价')
    if (allImages.value.length < 4) warnings.push('商品图少于4张，建议补齐正面、背面、边框、瑕疵')
    return warnings
})
const draftWarnings = computed(() => {
    const warnings: string[] = []
    const salePrice = Number(priceDraft.sale_price || 0)
    const minPrice = Number(priceDraft.min_price || 0)
    const costPrice = Number(asset.value?.recycle_final_price || 0)
    if (salePrice > 0 && costPrice > 0 && salePrice < costPrice) warnings.push('销售价低于回收成本')
    if (salePrice > 0 && minPrice > salePrice) warnings.push('最低价不能高于销售价')
    if (salePrice > 0 && editGrossProfit.value < 0 && !priceDraft.remark) warnings.push('亏损定价建议填写备注')
    return warnings
})

const openPriceEditor = () => {
    priceDraft.sale_price = form.sale_price
    priceDraft.peer_price = form.peer_price
    priceDraft.min_price = form.min_price
    priceDraft.remark = form.remark
    pricePopupVisible.value = true
}

const confirmAndSubmitPrice = async () => {
    if (!priceDraft.sale_price || Number(priceDraft.sale_price) <= 0) {
        uni.showToast({ title: '请输入销售价', icon: 'none' })
        return
    }
    if (Number(priceDraft.min_price || 0) > Number(priceDraft.sale_price || 0)) {
        uni.showToast({ title: '最低价不能高于销售价', icon: 'none' })
        return
    }
    form.sale_price = priceDraft.sale_price
    form.peer_price = priceDraft.peer_price
    form.min_price = priceDraft.min_price
    form.remark = priceDraft.remark
    await submitPrice(false)
}

const submitPrice = async (autoOpen = true) => {
    if (!form.sale_price || Number(form.sale_price) <= 0) {
        uni.showToast({ title: '请先设置销售价', icon: 'none' })
        if (autoOpen) openPriceEditor()
        return
    }
    if (Number(form.min_price || 0) > Number(form.sale_price || 0)) {
        uni.showToast({ title: '最低价不能高于销售价', icon: 'none' })
        if (autoOpen) openPriceEditor()
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
        uni.showToast({ title: '定价已保存，继续下一台', icon: 'none' })
        pricePopupVisible.value = false
        const pages = getCurrentPages()
        setTimeout(() => {
            if (pages.length > 1) {
                uni.navigateBack()
            } else {
                uni.redirectTo({ url: '/addon/hsx_device_asset/pages/task/list?tab=price' })
            }
        }, 500)
    } finally {
        submitting.value = false
    }
}

const preview = (index: number) => {
    if (!allImages.value.length) return
    const urls = allImages.value.map((url: string) => img(url))
    uni.previewImage({ urls, current: urls[index] || urls[0] })
}

const money = (value: any) => Number(value || 0).toFixed(2)

const recycleCheckImages = (value: any) => {
    const device = value?.recycle_device || value?.recycleDevice || {}
    return [
        ...splitImages(value?.ext_json?.check_images_buyer),
        ...splitImages(value?.ext_json?.check_images_seller),
        ...splitImages(value?.ext_json?.check_images),
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
.price-page {
    min-height: 100vh;
    padding: 24rpx 24rpx 0;
    background: #f6f7fb;
    box-sizing: border-box;
}

.asset-card,
.section-card,
.price-board,
.warning-card {
    margin-bottom: 20rpx;
    padding: 24rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.asset-card__head,
.section-head,
.warning-card,
.popup-head {
    display: flex;
    align-items: flex-start;
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

.asset-done-tip {
    margin-top: 8rpx;
    color: #16a34a;
    font-size: 24rpx;
    line-height: 1.5;
}

.asset-actions {
    display: flex;
    gap: 26rpx;
    margin-top: 18rpx;
    color: var(--hsx-primary);
    font-size: 25rpx;
}

.price-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12rpx;
}

.price-board__item {
    min-height: 128rpx;
    padding: 18rpx;
    border-radius: 18rpx;
    background: #f8fafc;
}

.price-board__item text {
    display: block;
    color: #64748b;
    font-size: 23rpx;
}

.price-board__item strong {
    display: block;
    margin-top: 12rpx;
    color: #111827;
    font-size: 30rpx;
    line-height: 1.2;
}

.price-board__item.primary {
    background: var(--hsx-primary-50);
}

.price-board__item.primary strong {
    color: var(--hsx-primary);
}

.price-board__item.danger strong {
    color: #dc2626;
}

.warning-card {
    align-items: center;
    background: #fff7ed;
    color: #c2410c;
}

.warning-card__title {
    font-size: 27rpx;
    font-weight: 700;
}

.warning-card__desc {
    margin-top: 6rpx;
    font-size: 24rpx;
    line-height: 1.45;
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

.main-image {
    width: 100%;
    height: 520rpx;
    margin-top: 18rpx;
    border-radius: 18rpx;
    overflow: hidden;
    background: #e5e7eb;
}

.main-image image {
    width: 100%;
    height: 100%;
}

.thumb-scroll {
    width: 100%;
    margin-top: 16rpx;
    white-space: nowrap;
}

.thumb-row {
    display: flex;
    gap: 12rpx;
}

.thumb-item {
    width: 112rpx;
    height: 112rpx;
    flex: 0 0 112rpx;
    border: 4rpx solid transparent;
    border-radius: 16rpx;
    overflow: hidden;
    background: #e5e7eb;
}

.thumb-item.active {
    border-color: var(--hsx-primary);
}

.thumb-item image {
    width: 100%;
    height: 100%;
}

.setting-list {
    display: grid;
    gap: 12rpx;
    margin-top: 16rpx;
}

.setting-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18rpx;
    min-height: 84rpx;
    padding: 0 20rpx;
    border-radius: 16rpx;
    background: #f8fafc;
    color: #64748b;
    font-size: 25rpx;
}

.setting-row strong {
    color: #111827;
    font-size: 27rpx;
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

.edit-popup,
.check-popup {
    padding: 28rpx 28rpx calc(28rpx + env(safe-area-inset-bottom));
    background: #fff;
}

.price-edit-popup {
    max-height: 86vh;
    overflow: hidden;
}

.center-popup {
    width: 650rpx;
    max-height: 78vh;
    border-radius: 18rpx;
    box-sizing: border-box;
}

.popup-head {
    margin-bottom: 22rpx;
}

.popup-close {
    color: #64748b;
    font-size: 34rpx;
}

.price-popup-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14rpx;
    margin-bottom: 18rpx;
}

.price-popup-summary view {
    padding: 18rpx;
    border-radius: 16rpx;
    background: #f8fafc;
}

.price-popup-summary text {
    display: block;
    color: #64748b;
    font-size: 23rpx;
}

.price-popup-summary strong {
    display: block;
    margin-top: 8rpx;
    color: #111827;
    font-size: 30rpx;
}

.price-popup-summary .danger strong {
    color: #dc2626;
}

.price-form-list {
    display: grid;
    gap: 12rpx;
    margin-bottom: 16rpx;
}

.price-form-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18rpx;
    min-height: 92rpx;
    padding: 0 20rpx;
    border-radius: 16rpx;
    background: #f8fafc;
}

.price-form-row > text {
    color: #334155;
    font-size: 26rpx;
    font-weight: 650;
}

.price-form-row.required > text::after {
    content: '*';
    margin-left: 4rpx;
    color: #dc2626;
}

.price-form-input {
    display: flex;
    align-items: center;
    gap: 8rpx;
    flex: 1;
}

.price-form-input text {
    color: #111827;
    font-size: 30rpx;
    font-weight: 700;
}

.price-form-input input {
    flex: 1;
    color: #111827;
    font-size: 30rpx;
    text-align: right;
}

.draft-warning-list {
    display: grid;
    gap: 8rpx;
    margin-bottom: 16rpx;
}

.draft-warning-list view {
    padding: 12rpx 16rpx;
    border-radius: 14rpx;
    background: #fff7ed;
    color: #c2410c;
    font-size: 23rpx;
    line-height: 1.45;
}

.popup-actions {
    display: flex;
    gap: 16rpx;
    margin-top: 24rpx;
}

.sticky-popup-actions {
    padding-top: 4rpx;
}

.check-popup__body {
    max-height: 720rpx;
}

.check-list,
.warning-list {
    display: grid;
    gap: 12rpx;
}

.check-row,
.warning-list view {
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

.warning-list view {
    color: #c2410c;
    background: #fff7ed;
}
</style>
