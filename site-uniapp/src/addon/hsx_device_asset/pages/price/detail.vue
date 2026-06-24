<template>
    <view class="price-page">
        <!-- 资产信息 -->
        <view class="asset-card">
            <view class="asset-card__head">
                <view class="asset-card__main">
                    <view class="asset-title">{{ asset.model || asset.asset_no || '资产定价' }}</view>
                    <view class="asset-meta">资产 {{ asset.asset_no || '-' }} · IMEI {{ asset.imei || '-' }}</view>
                    <view v-if="isPriceCompleted" class="asset-done-tip">定价已完成，资产已进入待导出链路。</view>
                </view>
                <u-tag :text="asset.price_status_name || asset.price_status || '待定价'" type="primary" size="mini" plain plainFill></u-tag>
            </view>
        </view>

        <!-- 商品图 -->
        <view class="section-card">
            <view class="section-head">
                <text class="section-title">商品图</text>
                <text class="section-sub">{{ allImages.length }} 张 · 点击全屏</text>
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

        <!-- 质检结论：内联组件，异常常驻高亮，正常折叠 -->
        <view class="section-card">
            <AssetCheckSummary
                :text="checkText"
                :items="checkText ? undefined : checkItems"
                empty-text="暂无质检摘要"
            ></AssetCheckSummary>
            <!-- 质检员手填备注：模板覆盖不到的补充信息，重要，单独突出 -->
            <view v-if="checkRemark" class="check-remark">
                <view class="check-remark__head">
                    <u-icon name="edit-pen-fill" color="#fa5c1e" size="14"></u-icon>
                    <text class="check-remark__label">质检备注</text>
                </view>
                <text class="check-remark__text">{{ checkRemark }}</text>
            </view>
        </view>

        <!-- 定价提醒 -->
        <view v-if="priceWarnings.length" class="warning-card">
            <view class="warning-card__head">
                <u-icon name="error-circle-fill" color="#c2410c" size="16"></u-icon>
                <text class="warning-card__title">定价提醒</text>
            </view>
            <view v-for="item in priceWarnings" :key="item" class="warning-card__row">{{ item }}</view>
        </view>

        <!-- 价格设置：输入框直接铺在页面上，一页搞定，不弹窗 -->
        <view class="section-card">
            <view class="section-head">
                <text class="section-title">价格设置</text>
                <view class="cost-chip">回收成本 ¥{{ money(asset.recycle_final_price) }}</view>
            </view>

            <view class="pf">
                <view class="pf__item">
                    <text class="pf__label">销售价<text class="pf__req">*</text></text>
                    <view class="pf__field">
                        <text class="rmb">¥</text>
                        <u-input
                            v-model="form.sale_price"
                            type="digit"
                            placeholder="请输入销售价"
                            border="none"
                            :customStyle="fieldInputStyle"
                        ></u-input>
                    </view>
                </view>

                <view class="pf__item">
                    <text class="pf__label">同行价</text>
                    <view class="pf__field">
                        <text class="rmb">¥</text>
                        <u-input
                            v-model="form.peer_price"
                            type="digit"
                            placeholder="选填"
                            border="none"
                            :customStyle="fieldInputStyle"
                        ></u-input>
                    </view>
                </view>

                <view class="pf__item pf__item--profit" :class="{ danger: grossProfit < 0 }">
                    <text class="pf__label">预估毛利</text>
                    <text class="pf__profit">¥{{ money(grossProfit) }}</text>
                </view>

                <view class="pf__item pf__item--col">
                    <text class="pf__label">定价备注</text>
                    <u-textarea
                        v-model="form.remark"
                        placeholder="成色、渠道、底价说明等（选填）"
                        :height="110"
                        :maxlength="300"
                        count
                    ></u-textarea>
                </view>
            </view>
        </view>

        <view class="footer-safe"></view>

        <!-- 浮动确认条：毛利常驻 + 一键确认定价（直接提交，不弹窗） -->
        <view class="pricing-bar">
            <view class="pricing-bar__info" :class="{ danger: grossProfit < 0 }">
                <text class="pricing-bar__k">预估毛利</text>
                <text class="pricing-bar__v">¥{{ money(grossProfit) }}</text>
            </view>
            <u-button
                type="primary"
                :loading="submitting"
                :custom-style="{ flex: 1, height: '88rpx', marginLeft: '24rpx' }"
                @click="handleConfirm"
            >{{ pricePrimaryText }}</u-button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { img } from '@/utils/common'
import { completeAssetPrice, getAssetInfo } from '@/addon/hsx_device_asset/api/device_asset'
import AssetCheckSummary from '@/addon/hsx_device_asset/components/AssetCheckSummary.vue'

const assetId = ref('')
const asset = ref<any>({})
const loading = ref(false)
const submitting = ref(false)
const activeImageIndex = ref(0)
const fieldInputStyle = { padding: '0', background: 'transparent', fontSize: '32rpx', fontWeight: '700' }

const form = reactive({ sale_price: '', peer_price: '', remark: '' })

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
        form.remark = asset.value.price_remark || ''
        activeImageIndex.value = 0
    } finally {
        loading.value = false
    }
}

// 质检结论文本：优先入库快照，其次回收质检结果
const checkText = computed(() => {
    const device = asset.value?.recycle_device || asset.value?.recycleDevice || {}
    const snap = toObject(asset.value?.check_snapshot)
    return String(snap.check_result || device.check_result_seller || device.check_result || device.check_result_buyer || '')
})
// 质检员手填补充备注：优先入库快照透传的 check_remark，其次回收设备 remark
const checkRemark = computed(() => {
    const device = asset.value?.recycle_device || asset.value?.recycleDevice || {}
    const snap = toObject(asset.value?.check_snapshot)
    return String(snap.check_remark || device.remark || asset.value?.check_remark || '').trim()
})
const checkItems = computed(() => {
    const obj = toObject(asset.value?.check_summary)
    return Object.entries(obj)
        .filter(([, value]) => value !== '' && value !== null && value !== undefined)
        .map(([key, value]) => ({ label: key, value: typeof value === 'object' ? JSON.stringify(value) : String(value) }))
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
    return form.sale_price ? '确认定价' : '填写销售价'
})
const grossProfit = computed(() => Number(form.sale_price || 0) - Number(asset.value?.recycle_final_price || 0))
const priceWarnings = computed(() => {
    const warnings: string[] = []
    const salePrice = Number(form.sale_price || 0)
    const costPrice = Number(asset.value?.recycle_final_price || 0)
    if (salePrice > 0 && costPrice > 0 && salePrice < costPrice) warnings.push('销售价低于回收成本，请确认是否亏损出货')
    if (allImages.value.length < 4) warnings.push('商品图少于4张，建议补齐正面、背面、边框、瑕疵')
    return warnings
})

const handleConfirm = async () => {
    if (!form.sale_price || Number(form.sale_price) <= 0) {
        uni.showToast({ title: '请输入销售价', icon: 'none' })
        return
    }
    submitting.value = true
    try {
        await completeAssetPrice(assetId.value, {
            sale_price: Number(form.sale_price || 0),
            peer_price: Number(form.peer_price || 0),
            min_price: 0,
            remark: form.remark || ''
        })
        uni.showToast({ title: '定价已保存，继续下一台', icon: 'none' })
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

const toObject = (value: any): Record<string, any> => {
    if (!value) return {}
    if (typeof value === 'object') return value
    try {
        const parsed = JSON.parse(value)
        return typeof parsed === 'object' && parsed ? parsed : {}
    } catch {
        return {}
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
.warning-card {
    margin-bottom: 20rpx;
    padding: 24rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.asset-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18rpx;
}
.asset-card__main { flex: 1; min-width: 0; }
.asset-title {
    color: #0f172a;
    font-size: 34rpx;
    font-weight: 700;
    line-height: 1.35;
}
.asset-meta {
    margin-top: 6rpx;
    color: #64748b;
    font-size: 24rpx;
}
.asset-done-tip {
    margin-top: 8rpx;
    color: #16a34a;
    font-size: 24rpx;
}

.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16rpx;
}
.section-title {
    color: #0f172a;
    font-size: 30rpx;
    font-weight: 700;
}
.section-sub {
    color: #94a3b8;
    font-size: 24rpx;
}
.cost-chip {
    padding: 6rpx 16rpx;
    border-radius: 999rpx;
    background: #f1f5f9;
    color: #475569;
    font-size: 22rpx;
}

/* 质检员补充备注：橙色弱底，强调它是人工补充的关键信息 */
.check-remark {
    margin-top: 18rpx;
    padding: 16rpx 18rpx;
    border-radius: 14rpx;
    background: #fff7f2;
    border: 1rpx solid #ffe0cf;
}
.check-remark__head {
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin-bottom: 8rpx;
}
.check-remark__label {
    color: #fa5c1e;
    font-size: 23rpx;
    font-weight: 700;
}
.check-remark__text {
    display: block;
    color: #5b4636;
    font-size: 24rpx;
    line-height: 1.55;
    word-break: break-word;
}

.main-image {
    width: 100%;
    height: 520rpx;
    border-radius: 18rpx;
    overflow: hidden;
    background: #e5e7eb;
}
.main-image image { width: 100%; height: 100%; }

.thumb-scroll {
    width: 100%;
    margin-top: 16rpx;
    white-space: nowrap;
}
.thumb-row { display: flex; gap: 12rpx; }
.thumb-item {
    width: 112rpx;
    height: 112rpx;
    flex: 0 0 112rpx;
    border: 4rpx solid transparent;
    border-radius: 16rpx;
    overflow: hidden;
    background: #e5e7eb;
}
.thumb-item.active { border-color: var(--hsx-primary); }
.thumb-item image { width: 100%; height: 100%; }

.warning-card { background: #fff7ed; }
.warning-card__head {
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin-bottom: 10rpx;
}
.warning-card__title {
    color: #c2410c;
    font-size: 27rpx;
    font-weight: 700;
}
.warning-card__row {
    margin-top: 6rpx;
    color: #c2410c;
    font-size: 24rpx;
    line-height: 1.5;
}

/* 价格设置：内联输入 */
.pf {
    display: flex;
    flex-direction: column;
    gap: 14rpx;
}
.pf__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18rpx;
    min-height: 92rpx;
    padding: 0 22rpx;
    border-radius: 16rpx;
    background: #f8fafc;
}
.pf__item--col {
    flex-direction: column;
    align-items: stretch;
    gap: 14rpx;
    padding: 22rpx;
}
.pf__label {
    flex: 0 0 auto;
    color: #334155;
    font-size: 26rpx;
    font-weight: 600;
}
.pf__req {
    margin-left: 4rpx;
    color: #dc2626;
}
.pf__field {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 8rpx;
}
.rmb {
    color: #0f172a;
    font-size: 30rpx;
    font-weight: 700;
}
.pf__item--profit .pf__profit {
    color: #16a34a;
    font-size: 32rpx;
    font-weight: 800;
}
.pf__item--profit.danger .pf__profit { color: #dc2626; }

.footer-safe { height: 168rpx; }

/* 浮动确认条 */
.pricing-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    padding: 16rpx 24rpx calc(16rpx + env(safe-area-inset-bottom));
    background: #ffffff;
    box-shadow: 0 -8rpx 28rpx rgba(15, 23, 42, 0.1);
}
.pricing-bar__info {
    flex: 0 0 auto;
}
.pricing-bar__k {
    display: block;
    color: #94a3b8;
    font-size: 21rpx;
}
.pricing-bar__v {
    display: block;
    margin-top: 4rpx;
    color: #16a34a;
    font-size: 36rpx;
    font-weight: 800;
    line-height: 1.1;
}
.pricing-bar__info.danger .pricing-bar__v { color: #dc2626; }
</style>
