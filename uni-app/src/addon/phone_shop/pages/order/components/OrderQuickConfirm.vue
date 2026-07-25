<template>
    <view class="quick-confirm">
        <view
            v-if="hasRealGoods && deliveryTypes.length"
            class="legacy-delivery"
            :style="{
                backgroundImage: `url(${img('addon/phone_shop/payment/head_bg.png')})`,
                backgroundSize: '100%',
                backgroundRepeat: 'no-repeat',
                backgroundPosition: 'bottom'
            }"
        >
            <view v-if="deliveryTypes.length > 1" class="legacy-delivery__tabs">
                <view
                    v-for="(item, index) in deliveryTypes"
                    :key="item.key"
                    class="legacy-delivery__tab"
                    :class="{ active: deliveryType === item.key }"
                    @click="chooseDelivery(item, index)"
                >
                    <text class="legacy-delivery__tab-text">{{ item.name }}</text>
                    <image
                        v-if="deliveryType === item.key && deliveryTypes.length === 3"
                        class="legacy-delivery__tab-image"
                        :class="`legacy-delivery__tab-image--${index}`"
                        :src="img(`addon/phone_shop/payment/tab_${index}.png`)"
                        mode="aspectFit"
                    />
                    <image
                        v-else-if="deliveryType === item.key && deliveryTypes.length === 2"
                        class="legacy-delivery__tab-image legacy-delivery__tab-image--double"
                        :src="img(`addon/phone_shop/payment/tabstyle_${index}.png`)"
                        mode="aspectFit"
                    />
                </view>
            </view>
            <view v-else class="legacy-delivery__single">{{ deliveryTypes[0].name }}</view>

            <view class="legacy-delivery__body">
                <view v-if="isAddressDelivery" class="legacy-delivery__content">
                    <view @click="$emit('select-address')">
                        <view v-if="takeAddress" class="legacy-address">
                            <image class="legacy-address__icon" :src="img('addon/phone_shop/payment/position_01.png')" mode="aspectFit" />
                            <view class="legacy-address__main">
                                <text class="legacy-address__area">{{ addressArea }}</text>
                                <text class="legacy-address__detail">{{ takeAddress.address }}</text>
                                <view class="legacy-address__contact">
                                    <text>{{ takeAddress.name }}</text>
                                    <text>{{ mobileHide(takeAddress.mobile) }}</text>
                                </view>
                                <text v-if="orderData.delivery?.error" class="legacy-address__error">{{ orderData.delivery.error }}</text>
                            </view>
                            <text class="nc-iconfont nc-icon-youV6xx legacy-arrow" />
                        </view>
                        <view v-else class="legacy-empty">
                            <image class="legacy-empty__icon" :src="img('addon/phone_shop/payment/position_02.png')" mode="aspectFit" />
                            <text>添加收货地址</text>
                            <text class="nc-iconfont nc-icon-youV6xx legacy-arrow" />
                        </view>
                    </view>
                    <view v-if="deliveryType === 'local_delivery'" class="legacy-delivery__extra">
                        <view class="legacy-extra-row" @click="$emit('select-time')">
                            <view class="legacy-extra-row__label">
                                <image :src="img('addon/phone_shop/payment/time.png')" mode="aspectFit" />
                                <text>配送时间</text>
                            </view>
                            <view class="legacy-extra-row__value">
                                <text>{{ createData.delivery.buyer_ask_delivery_time || '立即配送' }}</text>
                                <text class="nc-iconfont nc-icon-youV6xx" />
                            </view>
                        </view>
                        <view
                            v-if="orderData.config?.local_delivery?.is_local_delivery_store_select"
                            class="legacy-extra-row"
                            @click="$emit('select-store')"
                        >
                            <view class="legacy-extra-row__label">
                                <image :src="img('addon/phone_shop/payment/store.png')" mode="aspectFit" />
                                <text>配送门店</text>
                            </view>
                            <view class="legacy-extra-row__value">
                                <text>{{ takeStore?.store_name || '请选择配送门店' }}</text>
                                <text class="nc-iconfont nc-icon-youV6xx" />
                            </view>
                        </view>
                    </view>
                </view>

                <view v-else-if="deliveryType === 'store'" class="legacy-delivery__content">
                    <view class="legacy-store" @click="$emit('select-store')">
                        <template v-if="takeStore && takeStore.store_id">
                            <view class="legacy-store__main">
                                <text class="legacy-store__name">{{ takeStore.store_name }}</text>
                                <text>门店地址：{{ takeStore.full_address }}</text>
                                <text>联系电话：{{ takeStore.store_mobile }}</text>
                                <text>营业时间：{{ takeStore.trade_time }}</text>
                            </view>
                            <text class="nc-iconfont nc-icon-youV6xx legacy-arrow" />
                        </template>
                        <template v-else>
                            <image class="legacy-empty__icon" :src="img('addon/phone_shop/payment/position_02.png')" mode="aspectFit" />
                            <text>请选择自提点</text>
                            <text class="nc-iconfont nc-icon-youV6xx legacy-arrow" />
                        </template>
                    </view>
                    <view class="legacy-receiver">
                        <view class="legacy-receiver__row">
                            <text>姓名</text>
                            <input v-model="createData.delivery.taker_name" maxlength="20" placeholder="请输入" />
                        </view>
                        <view class="legacy-receiver__row">
                            <text>预留手机</text>
                            <input v-model="createData.delivery.taker_mobile" maxlength="11" type="number" placeholder="请输入" />
                        </view>
                        <view class="legacy-receiver__row" @click="$emit('select-time')">
                            <text>提货时间</text>
                            <view>
                                <text>{{ createData.delivery.buyer_ask_delivery_time || '选择提货时间' }}</text>
                                <text class="nc-iconfont nc-icon-youV6xx" />
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </view>
        <view v-else-if="hasRealGoods" class="confirm-card delivery-empty">商家尚未配置配送方式</view>

        <view class="confirm-card goods-card">
            <view class="card-head">
                <view class="head-title">
                    <u-icon name="bag-fill" color="var(--primary-color)" size="20" />
                    <text>商品信息</text>
                </view>
                <text class="head-count">共 {{ goodsList.length }} 件</text>
            </view>
            <view v-for="item in goodsList" :key="item.sku_id" class="goods-row">
                <up-image width="128rpx" height="128rpx" radius="12rpx" :src="img(item.sku_image || item.goods?.goods_cover || '')" mode="aspectFill">
                    <template #error>
                        <image class="w-[128rpx] h-[128rpx] rounded-[12rpx]" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill" />
                    </template>
                </up-image>
                <view class="goods-info">
                    <text class="goods-name">{{ item.goods?.goods_name || item.goods_name }}</text>
                    <PhoneGoodsMeta :subtitle="item.goods?.sub_title || item.sub_title" :imei="item.sku_no || item.goodsSku?.sku_no" compact />
                    <view class="goods-bottom">
                        <text class="goods-price">￥{{ Number(item.price || 0).toFixed(2) }}</text>
                        <text class="goods-num">×{{ item.num || 1 }}</text>
                    </view>
                </view>
            </view>
        </view>

        <view class="confirm-card settings-card">
            <view class="setting-row" @click="$emit('edit-message')">
                <text class="setting-label">买家留言</text>
                <text class="setting-value">{{ createData.member_remark || '无留言' }}</text>
                <u-icon name="arrow-right" color="#cbd5e1" size="15" />
            </view>
            <view v-if="couponList.length" class="setting-row" @click="$emit('select-coupon')">
                <text class="setting-label">优惠券</text>
                <text class="setting-value active">{{ couponName || `有 ${couponList.length} 张可用` }}</text>
                <u-icon name="arrow-right" color="#cbd5e1" size="15" />
            </view>
            <view v-if="invoiceEnabled" class="setting-row" @click="$emit('select-invoice')">
                <text class="setting-label">发票信息</text>
                <text class="setting-value">{{ createData.invoice?.header_name || '不需要发票' }}</text>
                <u-icon name="arrow-right" color="#cbd5e1" size="15" />
            </view>
        </view>

        <view v-if="tradeModes.length" class="confirm-card payment-card" @click="tradeModes.length > 1 && (paymentPopup = true)">
            <view class="card-head !mb-0">
                <view class="head-title">
                    <u-icon :name="currentTrade?.key === 'offline_pending' ? 'account-fill' : 'weixin-fill'" color="var(--primary-color)" size="20" />
                    <view>
                        <view class="flex items-center">
                            <text>支付方式</text>
                            <text class="payment-name">{{ currentTrade?.name || '请选择' }}</text>
                        </view>
                        <text class="payment-desc">{{ currentTrade?.desc || '' }}</text>
                    </view>
                </view>
                <view v-if="tradeModes.length > 1" class="edit-action">
                    <text>更换</text>
                    <u-icon name="arrow-right" color="#94a3b8" size="15" />
                </view>
            </view>
        </view>

        <view class="confirm-card price-card">
            <view class="card-head"><view class="head-title"><text>金额明细</text></view></view>
            <view class="price-row"><text>商品金额</text><text>￥{{ money(orderData.basic.goods_money) }}</text></view>
            <view v-if="Number(orderData.basic.delivery_money)" class="price-row"><text>配送费用</text><text>￥{{ money(orderData.basic.delivery_money) }}</text></view>
            <view v-if="Number(orderData.basic.coupon_money)" class="price-row discount"><text>优惠券</text><text>-￥{{ money(orderData.basic.coupon_money) }}</text></view>
            <view v-if="Number(orderData.basic.manjian_discount_money)" class="price-row discount"><text>满减优惠</text><text>-￥{{ money(orderData.basic.manjian_discount_money) }}</text></view>
            <view class="price-row total"><text>应付金额</text><text>￥{{ money(orderData.basic.order_money) }}</text></view>
        </view>

        <u-popup :show="paymentPopup" mode="bottom" :round="16" @close="paymentPopup = false">
            <view class="popup-panel">
                <view class="popup-head">
                    <text>选择支付方式</text>
                    <u-icon name="close" color="#64748b" size="20" @click="paymentPopup = false" />
                </view>
                <view
                    v-for="item in tradeModes"
                    :key="item.key"
                    class="option-row"
                    :class="{ selected: createData.payment_mode === item.key }"
                    @click="choosePayment(item.key)"
                >
                    <view>
                        <view class="flex items-center">
                            <text class="option-title">{{ item.name }}</text>
                            <text v-if="item.recommended" class="recommend-tag">推荐</text>
                        </view>
                        <text class="option-desc">{{ item.desc }}</text>
                    </view>
                    <u-icon :name="createData.payment_mode === item.key ? 'checkmark-circle-fill' : 'circle'" :color="createData.payment_mode === item.key ? 'var(--primary-color)' : '#cbd5e1'" size="21" />
                </view>
            </view>
        </u-popup>

        <u-popup :show="receiverPopup" mode="bottom" :round="16" @close="receiverPopup = false">
            <view class="popup-panel">
                <view class="popup-head">
                    <text>修改提货人</text>
                    <u-icon name="close" color="#64748b" size="20" @click="receiverPopup = false" />
                </view>
                <view class="input-row">
                    <text>姓名</text>
                    <input v-model="receiver.name" maxlength="20" placeholder="请输入提货人姓名" />
                </view>
                <view class="input-row">
                    <text>手机号</text>
                    <input v-model="receiver.mobile" maxlength="11" type="number" placeholder="请输入手机号" />
                </view>
                <view class="popup-submit" @click="confirmReceiver">保存</view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { img, mobileHide } from '@/utils/common'
import PhoneGoodsMeta from '@/addon/phone_shop/components/PhoneGoodsMeta.vue'

const props = defineProps({
    orderData: { type: Object, required: true },
    createData: { type: Object, required: true },
    deliveryTypes: { type: Array, default: () => [] },
    tradeModes: { type: Array, default: () => [] },
    couponList: { type: Array, default: () => [] },
    invoiceEnabled: { type: Boolean, default: false }
})

const emit = defineEmits([
    'change-delivery', 'change-payment', 'select-address', 'select-store',
    'select-time', 'edit-message', 'select-coupon', 'select-invoice', 'update-receiver'
])

const paymentPopup = ref(false)
const receiverPopup = ref(false)
const receiver = reactive({ name: '', mobile: '' })

const hasRealGoods = computed(() => props.orderData.basic?.has_goods_types?.includes('real'))
const deliveryType = computed(() => props.createData.delivery?.delivery_type || '')
const isAddressDelivery = computed(() => ['express', 'local_delivery'].includes(deliveryType.value))
const takeAddress = computed(() => props.orderData.delivery?.take_address || null)
const takeStore = computed(() => props.orderData.delivery?.take_store || null)
const addressArea = computed(() => {
    const fullAddress = String(takeAddress.value?.full_address || '')
    const detailAddress = String(takeAddress.value?.address || '')
    if (!detailAddress) return fullAddress
    const index = fullAddress.lastIndexOf(detailAddress)
    return index > -1 ? fullAddress.substring(0, index) : fullAddress
})
const goodsList = computed<any[]>(() => props.orderData.goods || [])
const currentTrade = computed<any>(() => (props.tradeModes as any[]).find(item => item.key === props.createData.payment_mode))
const couponName = computed(() => props.orderData.discount?.coupon?.title || '')

const money = (value: any) => Number(value || 0).toFixed(2)
const chooseDelivery = (item: any, index: number) => {
    if (deliveryType.value !== item.key) emit('change-delivery', { key: item.key, index })
}

const choosePayment = (key: string) => {
    paymentPopup.value = false
    if (props.createData.payment_mode !== key) emit('change-payment', key)
}

const openReceiver = () => {
    receiver.name = props.createData.delivery?.taker_name || ''
    receiver.mobile = props.createData.delivery?.taker_mobile || ''
    receiverPopup.value = true
}

const confirmReceiver = () => {
    if (!receiver.name || !receiver.mobile) {
        uni.showToast({ title: '请完整填写姓名和手机号', icon: 'none' })
        return
    }
    emit('update-receiver', { ...receiver })
    receiverPopup.value = false
}
</script>

<style lang="scss" scoped>
.quick-confirm { padding: 22rpx 24rpx 150rpx; }
.quick-confirm.has-extra { padding-bottom: 22rpx; }
.confirm-card { margin-bottom: 20rpx; padding: 26rpx; border-radius: 22rpx; background: #fff; box-shadow: 0 8rpx 28rpx rgba(15, 23, 42, .035); }
.card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22rpx; }
.legacy-delivery { margin-bottom: 20rpx; overflow: hidden; border-radius: var(--rounded-big); background-color: #fff; }
.legacy-delivery__tabs { display: flex; align-items: center; width: 100%; background: var(--shop-payment-header-tab-color); }
.legacy-delivery__tab { position: relative; flex: 1; height: 74rpx; color: #303133; font-size: 28rpx; line-height: 74rpx; text-align: center; }
.legacy-delivery__tab-text { position: relative; z-index: 2; }
.legacy-delivery__tab.active { color: var(--primary-color); font-weight: 600; }
.legacy-delivery__tab-image { position: absolute; z-index: 1; bottom: -2rpx; left: 50%; width: 240rpx; height: 94rpx; transform: translateX(-50%); }
.legacy-delivery__tab-image--1 { width: 312rpx; }
.legacy-delivery__tab-image--double { width: 354rpx; height: 95rpx; }
.legacy-delivery__single { height: 74rpx; padding: 0 30rpx; color: var(--primary-color); background: var(--shop-payment-header-tab-color); font-size: 28rpx; font-weight: 600; line-height: 74rpx; }
.legacy-delivery__body { display: flex; align-items: center; min-height: 140rpx; padding: 0 30rpx; }
.legacy-delivery__content { width: 100%; }
.legacy-address { display: flex; align-items: center; padding: 20rpx 0 30rpx; }
.legacy-address__icon { width: 60rpx; height: 60rpx; margin-right: 20rpx; flex-shrink: 0; }
.legacy-address__main { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.legacy-address__area { max-width: 536rpx; margin-top: 16rpx; overflow: hidden; color: var(--text-color-light9); font-size: 26rpx; text-overflow: ellipsis; white-space: nowrap; }
.legacy-address__detail { max-width: 536rpx; margin-top: 14rpx; overflow: hidden; color: #333; font-size: 30rpx; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
.legacy-address__contact { display: flex; align-items: center; gap: 16rpx; margin-top: 16rpx; color: var(--text-color-light6); font-size: 26rpx; }
.legacy-address__error { margin-top: 16rpx; color: var(--primary-color); font-size: 24rpx; }
.legacy-arrow { margin-left: auto; color: var(--text-color-light9); font-size: 26rpx; }
.legacy-empty { display: flex; align-items: center; min-height: 140rpx; font-size: 28rpx; }
.legacy-empty__icon { width: 26rpx; height: 30rpx; margin-right: 10rpx; flex-shrink: 0; }
.legacy-delivery__extra { padding-top: 22rpx; border-top: 1rpx solid #f2f2f2; }
.legacy-extra-row { display: flex; align-items: center; justify-content: space-between; min-height: 74rpx; color: #303133; font-size: 26rpx; }
.legacy-extra-row__label, .legacy-extra-row__value { display: flex; align-items: center; }
.legacy-extra-row__label image { width: 30rpx; height: 30rpx; margin-right: 12rpx; }
.legacy-extra-row__value { max-width: 390rpx; text-align: right; }
.legacy-extra-row__value > text:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.legacy-store { display: flex; align-items: center; min-height: 140rpx; padding: 30rpx 0; color: #303133; font-size: 28rpx; }
.legacy-store__main { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 14rpx; color: var(--text-color-light6); font-size: 24rpx; line-height: 1.4; }
.legacy-store__name { margin-bottom: 4rpx; color: #303133; font-size: 30rpx; font-weight: 500; }
.legacy-receiver { padding: 4rpx 0 14rpx; border-top: 1rpx solid #f2f2f2; }
.legacy-receiver__row { display: flex; align-items: center; justify-content: space-between; min-height: 58rpx; color: #303133; font-size: 26rpx; }
.legacy-receiver__row input { flex: 1; text-align: right; }
.legacy-receiver__row > view { color: var(--text-color-light6); }
.delivery-empty { color: var(--primary-color); font-size: 28rpx; }
.head-title { display: flex; align-items: center; gap: 12rpx; color: #172033; font-size: 29rpx; font-weight: 600; }
.head-count { color: #94a3b8; font-size: 23rpx; }
.edit-action { display: flex; align-items: center; gap: 4rpx; color: var(--primary-color); font-size: 24rpx; }
.summary-block { display: flex; align-items: center; min-height: 86rpx; padding: 20rpx; border-radius: 16rpx; background: #f8fafc; }
.summary-main { display: flex; flex: 1; min-width: 0; flex-direction: column; }
.summary-title { color: #1e293b; font-size: 27rpx; font-weight: 600; }
.summary-desc { margin-top: 8rpx; overflow: hidden; color: #64748b; font-size: 23rpx; text-overflow: ellipsis; white-space: nowrap; }
.empty-text { flex: 1; color: #64748b; font-size: 26rpx; }
.receiver-row { display: flex; align-items: center; justify-content: space-between; margin-top: 16rpx; padding: 18rpx 2rpx 0; border-top: 1rpx solid #f1f5f9; }
.receiver-label { margin-right: 22rpx; color: #64748b; font-size: 24rpx; }
.receiver-value { color: #334155; font-size: 24rpx; }
.goods-row { display: flex; padding: 18rpx 0; border-top: 1rpx solid #f1f5f9; }
.goods-row:first-of-type { border-top: 0; }
.goods-info { display: flex; flex: 1; min-width: 0; flex-direction: column; margin-left: 20rpx; }
.goods-name { overflow: hidden; color: #1e293b; font-size: 27rpx; font-weight: 600; line-height: 38rpx; text-overflow: ellipsis; white-space: nowrap; }
.goods-bottom { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
.goods-price { color: var(--price-text-color); font-size: 29rpx; font-weight: 600; }
.goods-num { color: #94a3b8; font-size: 23rpx; }
.settings-card { padding-top: 4rpx; padding-bottom: 4rpx; }
.setting-row { display: flex; align-items: center; min-height: 88rpx; border-bottom: 1rpx solid #f1f5f9; }
.setting-row:last-child { border-bottom: 0; }
.setting-label { width: 150rpx; color: #334155; font-size: 26rpx; }
.setting-value { flex: 1; min-width: 0; overflow: hidden; color: #94a3b8; font-size: 24rpx; text-align: right; text-overflow: ellipsis; white-space: nowrap; }
.setting-value.active { color: var(--primary-color); }
.payment-name { margin-left: 18rpx; color: var(--primary-color); font-size: 25rpx; }
.payment-desc { display: block; margin-top: 8rpx; color: #94a3b8; font-size: 22rpx; font-weight: 400; }
.price-row { display: flex; justify-content: space-between; padding: 13rpx 0; color: #64748b; font-size: 25rpx; }
.price-row.discount text:last-child { color: var(--price-text-color); }
.price-row.total { margin-top: 10rpx; padding-top: 20rpx; border-top: 1rpx solid #f1f5f9; color: #172033; font-weight: 600; }
.price-row.total text:last-child { color: var(--price-text-color); font-size: 32rpx; }
.popup-panel { padding: 28rpx 28rpx calc(34rpx + env(safe-area-inset-bottom)); background: #fff; }
.popup-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 26rpx; color: #172033; font-size: 31rpx; font-weight: 600; }
.option-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16rpx; padding: 24rpx; border: 2rpx solid #eef2f7; border-radius: 18rpx; background: #f8fafc; }
.option-row.selected { border-color: var(--primary-color); background: var(--primary-color-light); }
.option-title { display: block; color: #1e293b; font-size: 27rpx; font-weight: 600; }
.option-desc { display: block; margin-top: 7rpx; color: #718096; font-size: 22rpx; }
.recommend-tag { margin-left: 10rpx; padding: 3rpx 9rpx; border-radius: 7rpx; color: var(--primary-color); background: #fff; font-size: 19rpx; }
.input-row { display: flex; align-items: center; height: 94rpx; border-bottom: 1rpx solid #eef2f7; color: #334155; font-size: 26rpx; }
.input-row text { width: 120rpx; }
.input-row input { flex: 1; text-align: right; }
.popup-submit { display: flex; align-items: center; justify-content: center; height: 84rpx; margin-top: 30rpx; border-radius: 42rpx; color: #fff; background: var(--primary-color); font-size: 28rpx; font-weight: 600; }
</style>
