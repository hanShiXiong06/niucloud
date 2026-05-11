<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="mall-page" v-if="isFeatureEnabled">
        <view class="page-header">
            <view class="header-bg"></view>
            <view class="header-content">
                <text class="title">积分商城</text>
                <view class="my-points-info">
                    <text class="pts-label">我的积分</text>
                    <text class="pts-value">{{ myPoints }}</text>
                </view>
            </view>
            <view class="header-actions">
                <view class="orders-btn" @click="goToMyOrders">
                    <u-icon name="list" size="18" color="#fff"></u-icon>
                    <text>兑换记录</text>
                </view>
            </view>
        </view>

        <!-- 商品列表 -->
        <view class="goods-list">
            <view class="goods-item" v-for="item in goodsList" :key="item.id" @click="showExchange(item)">
                <image class="goods-image" :src="item.image ? img(item.image) : '/static/images/default_goods.png'" mode="aspectFill"></image>
                <view class="goods-info">
                    <text class="goods-name">{{ item.name }}</text>
                    <view class="goods-bottom">
                        <view class="price-row">
                            <text class="points-price">{{ item.points_price }}</text>
                            <text class="pts-unit">积分</text>
                        </view>
                        <text class="exchange-count">库存{{ item.stock || 0 }}件</text>
                    </view>
                </view>
            </view>
            <view class="empty" v-if="goodsList.length === 0 && !loading">
                <u-icon name="shopping-cart" size="60" color="#ccc"></u-icon>
                <text>暂无商品</text>
            </view>
        </view>

        <!-- 兑换弹窗 -->
        <u-popup :show="showPopup" mode="bottom" @close="showPopup = false" round="16">
            <view class="exchange-popup">
                <view class="popup-header">
                    <text class="popup-title">兑换商品</text>
                    <u-icon name="close" size="24" @click="showPopup = false"></u-icon>
                </view>

                <view class="popup-goods" v-if="selectedGoods">
                    <image class="popup-img" :src="selectedGoods.image ? img(selectedGoods.image) : '/static/images/default_goods.png'" mode="aspectFill"></image>
                    <view class="popup-goods-info">
                        <text class="popup-goods-name">{{ selectedGoods.name }}</text>
                        <text class="popup-goods-price">{{ selectedGoods.points_price }} 积分</text>
                    </view>
                </view>

                <view class="form-section">
                    <view class="form-item" @click="selectAddress">
                        <text class="label">收货地址</text>
                        <view class="addr-select">
                            <text class="addr-text" :class="{ placeholder: !exchangeForm.receiver_address }">
                                {{ exchangeForm.receiver_address || '请选择收货地址' }}
                            </text>
                            <u-icon name="arrow-right" size="14" color="#999"></u-icon>
                        </view>
                    </view>
                    <view class="form-item">
                        <text class="label">收货人</text>
                        <input class="input" v-model="exchangeForm.receiver_name" placeholder="请输入收货人姓名" />
                    </view>
                    <view class="form-item">
                        <text class="label">手机号</text>
                        <input class="input" v-model="exchangeForm.receiver_phone" placeholder="请输入手机号" type="tel" />
                    </view>
                    <view class="form-item">
                        <text class="label">备注</text>
                        <input class="input" v-model="exchangeForm.remark" placeholder="选填" />
                    </view>
                </view>

                <button class="confirm-btn" @click="confirmExchange" :disabled="exchangeLoading">
                    {{ exchangeLoading ? '兑换中...' : '确认兑换' }}
                </button>
            </view>
        </u-popup>

        <!-- 地址选择弹窗 -->
        <u-popup :show="showAddrPopup" mode="bottom" @close="showAddrPopup = false" round="16">
            <view class="addr-popup">
                <view class="popup-header">
                    <text class="popup-title">选择收货地址</text>
                    <u-icon name="close" size="24" @click="showAddrPopup = false"></u-icon>
                </view>
                <scroll-view scroll-y class="addr-list">
                    <view 
                        class="addr-item" 
                        v-for="addr in addressList" 
                        :key="addr.id"
                        @click="onAddrSelect(addr)"
                    >
                        <view class="addr-info">
                            <text class="addr-name">{{ addr.name }} {{ addr.phone }}</text>
                            <text class="addr-detail">{{ addr.full_address || addr.address }}</text>
                        </view>
                        <u-icon name="checkmark" size="20" color="#52c41a" v-if="exchangeForm.receiver_address === (addr.full_address || addr.address)"></u-icon>
                    </view>
                    <view class="empty" v-if="addressList.length === 0">
                        <text>暂无地址，请手动输入</text>
                    </view>
                </scroll-view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPointsGoodsList, exchangePointsGoods, getAddressList } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import useMemberStore from '@/stores/member'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_points_mall')

const memberStore = useMemberStore()
const loading = ref(false)
const goodsList = ref<any[]>([])
const myPoints = ref(0)
const showPopup = ref(false)
const showAddrPopup = ref(false)
const selectedGoods = ref<any>(null)
const exchangeLoading = ref(false)
const addressList = ref<any[]>([])

const exchangeForm = ref({
    receiver_name: '',
    receiver_phone: '',
    receiver_address: '',
    remark: ''
})

onMounted(() => {
    loadConfig()
    loadGoods()
    loadAddresses()
    // 从会员信息获取积分
    if (memberStore.info) {
        myPoints.value = memberStore.info.point || 0
    }
})

const loadGoods = async () => {
    loading.value = true
    try {
        const res: any = await getPointsGoodsList({ page: 1, limit: 50 })
        if (res.code === 1) {
            goodsList.value = res.data.list || []
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const loadAddresses = async () => {
    try {
        const res: any = await getAddressList()
        if (res.code === 1) {
            addressList.value = res.data?.list || res.data || []
        }
    } catch (e) {
        console.error(e)
    }
}

const showExchange = (item: any) => {
    selectedGoods.value = item
    exchangeForm.value = {
        receiver_name: '',
        receiver_phone: '',
        receiver_address: '',
        remark: ''
    }
    showPopup.value = true
}

const selectAddress = () => {
    showAddrPopup.value = true
}

const onAddrSelect = (addr: any) => {
    exchangeForm.value.receiver_name = addr.name || ''
    exchangeForm.value.receiver_phone = addr.phone || ''
    exchangeForm.value.receiver_address = addr.full_address || addr.address || ''
    showAddrPopup.value = false
}

const confirmExchange = async () => {
    if (!selectedGoods.value) return
    if (!exchangeForm.value.receiver_name) {
        uni.showToast({ title: '请输入收货人', icon: 'none' }); return
    }
    if (!exchangeForm.value.receiver_phone) {
        uni.showToast({ title: '请输入手机号', icon: 'none' }); return
    }
    if (!exchangeForm.value.receiver_address) {
        uni.showToast({ title: '请填写收货地址', icon: 'none' }); return
    }

    exchangeLoading.value = true
    try {
        const res: any = await exchangePointsGoods({
            goods_id: selectedGoods.value.id,
            ...exchangeForm.value
        })
        if (res.code === 1) {
            uni.showToast({ title: '兑换成功！', icon: 'success' })
            showPopup.value = false
            // 刷新积分
            myPoints.value = Math.max(0, myPoints.value - selectedGoods.value.points_price)
            // 跳转到订单列表页
            setTimeout(() => {
                uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/points/orders' })
            }, 1500)
        } else {
            uni.showToast({ title: res.msg || '兑换失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.showToast({ title: e.message || '兑换失败', icon: 'none' })
    } finally {
        exchangeLoading.value = false
    }
}

const goToMyOrders = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/points/orders' })
}
</script>

<style lang="scss" scoped>
.mall-page {
    min-height: 100vh;
    background: #f7f7f7;
    padding-bottom: 40rpx;
}

.page-header {
    position: relative;
    height: 280rpx;

    .header-bg {
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 100%;
        background: linear-gradient(135deg, #c0fe95, #f7f7f7);
    }

    .header-content {
        position: relative;
        padding: 80rpx 30rpx 30rpx;
        display: flex;
        justify-content: space-between;
        align-items: center;

        .title {
            font-size: 48rpx;
            font-weight: bold;
            color: #333;
        }

        .my-points-info {
            display: flex;
            align-items: baseline;
            gap: 8rpx;

            .pts-label {
                font-size: 24rpx;
                color: #666;
            }
            .pts-value {
                font-size: 48rpx;
                font-weight: bold;
                color: #ff6b00;
            }
        }
    }
    
    .header-actions {
        position: relative;
        padding: 0 30rpx 20rpx;
        
        .orders-btn {
            display: inline-flex;
            align-items: center;
            gap: 8rpx;
            padding: 12rpx 24rpx;
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            border-radius: 30rpx;
            box-shadow: 0 4rpx 12rpx rgba(24, 144, 255, 0.3);
            
            text {
                font-size: 26rpx;
                color: #fff;
                font-weight: 500;
            }
        }
    }
}

.goods-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    padding: 0 20rpx;
    margin-top: 30rpx;
    position: relative;
}

.goods-item {
    width: calc(50% - 8rpx);
    background: #fff;
    border-radius: 16rpx;
    overflow: hidden;

    .goods-image {
        width: 100%;
        height: 300rpx;
    }

    .goods-info {
        padding: 16rpx;

        .goods-name {
            font-size: 26rpx;
            color: #333;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 12rpx;
        }

        .goods-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;

            .price-row {
                display: flex;
                align-items: baseline;
                gap: 4rpx;

                .points-price {
                    font-size: 36rpx;
                    font-weight: bold;
                    color: #ff6b00;
                }
                .pts-unit {
                    font-size: 22rpx;
                    color: #ff6b00;
                }
            }

            .exchange-count {
                font-size: 22rpx;
                color: #999;
            }
        }
    }
}

.empty {
    width: 100%;
    text-align: center;
    padding: 100rpx 0;
    color: #999;
    font-size: 28rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16rpx;
}

.exchange-popup {
    padding: 30rpx;
    padding-bottom: calc(30rpx + env(safe-area-inset-bottom));
}

.popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24rpx;

    .popup-title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
}

.popup-goods {
    display: flex;
    gap: 20rpx;
    padding: 20rpx;
    background: #f9f9f9;
    border-radius: 12rpx;
    margin-bottom: 24rpx;

    .popup-img {
        width: 120rpx;
        height: 120rpx;
        border-radius: 8rpx;
    }

    .popup-goods-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;

        .popup-goods-name {
            font-size: 28rpx;
            color: #333;
            margin-bottom: 8rpx;
        }
        .popup-goods-price {
            font-size: 32rpx;
            font-weight: bold;
            color: #ff6b00;
        }
    }
}

.form-section {
    .form-item {
        display: flex;
        align-items: center;
        padding: 20rpx 0;
        border-bottom: 1rpx solid #f0f0f0;

        .label {
            width: 120rpx;
            font-size: 26rpx;
            color: #333;
            flex-shrink: 0;
        }

        .input {
            flex: 1;
            font-size: 26rpx;
        }

        .addr-select {
            flex: 1;
            display: flex;
            align-items: center;

            .addr-text {
                flex: 1;
                font-size: 26rpx;
                color: #333;

                &.placeholder {
                    color: #999;
                }
            }
        }
    }
}

.confirm-btn {
    width: 100%;
    height: 80rpx;
    line-height: 80rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000;
    border: none;
    border-radius: 40rpx;
    font-size: 28rpx;
    font-weight: bold;
    margin-top: 30rpx;

    &[disabled] {
        background: #e5e5e5;
        color: #999;
    }
}

.addr-popup {
    padding: 30rpx;
    padding-bottom: calc(30rpx + env(safe-area-inset-bottom));
    max-height: 60vh;
}

.addr-list {
    max-height: 50vh;
}

.addr-item {
    display: flex;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;

    .addr-info {
        flex: 1;

        .addr-name {
            display: block;
            font-size: 28rpx;
            color: #333;
            margin-bottom: 6rpx;
        }
        .addr-detail {
            font-size: 24rpx;
            color: #666;
        }
    }
}
</style>
