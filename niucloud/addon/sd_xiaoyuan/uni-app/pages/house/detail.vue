<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="detail-page" v-if="isFeatureEnabled">
        <swiper class="image-swiper" v-if="images.length > 0" indicator-dots autoplay circular>
            <swiper-item v-for="(item, index) in images" :key="index">
                <image :src="img(item)" mode="aspectFill" @click="previewImage(index)"></image>
            </swiper-item>
        </swiper>

        <view class="house-info">
            <view class="price-row">
                <text class="price">¥{{ houseInfo.price }}<text class="unit">/月</text></text>
                <text class="type-tag">{{ getTypeName(houseInfo.house_type) }}</text>
            </view>
            <text class="title">{{ houseInfo.title }}</text>
            
            <view class="info-tags">
                <text class="tag">{{ houseInfo.room_type }}</text>
                <text class="tag">{{ houseInfo.area }}㎡</text>
                <text class="tag">押金¥{{ houseInfo.deposit }}</text>
            </view>
            
            <view class="address-row">
                <text class="iconfont icon-location"></text>
                <text class="address">{{ houseInfo.address }}</text>
            </view>
        </view>

        <view class="section">
            <view class="section-title">房源描述</view>
            <text class="description">{{ houseInfo.description || '暂无描述' }}</text>
        </view>

        <view class="section">
            <view class="section-title">配套设施</view>
            <view class="facilities" v-if="facilities.length > 0">
                <text class="facility-item" v-for="item in facilities" :key="item">{{ item }}</text>
            </view>
            <text class="empty-text" v-else>暂无配套设施信息</text>
        </view>

        <view class="bottom-bar" v-if="isOwner">
            <view class="bottom-left">
                <button class="contact-btn" @click="editHouse">编辑</button>
            </view>
            <view class="bottom-right">
                <button class="order-btn" v-if="houseInfo.status === 1" @click="offlineHouse">下架</button>
            </view>
        </view>
        <view class="bottom-bar" v-else>
            <view class="bottom-left">
                <view class="price-info">
                    <text class="price-label">租金</text>
                    <text class="price-value">¥{{ houseInfo.price }}/月</text>
                </view>
            </view>
            <view class="bottom-right">
                <button class="contact-btn" @click="callPhone">
                    <u-icon name="phone" size="18" color="#333"></u-icon>
                    <text>联系房东</text>
                </button>
                <button class="order-btn" @click="showOrderPopup = true">立即下单</button>
            </view>
        </view>

        <u-popup :show="showOrderPopup" mode="bottom" round="16" @close="showOrderPopup = false">
            <view class="order-popup">
                <view class="popup-title">租房下单</view>
                <view class="popup-house">
                    <text class="popup-house-title">{{ houseInfo.title }}</text>
                    <text class="popup-house-price">¥{{ houseInfo.price }}/月</text>
                </view>
                <view class="popup-form">
                    <view class="popup-item">
                        <text class="popup-label">联系人</text>
                        <input v-model="orderForm.contact_name" placeholder="请输入您的姓名" />
                    </view>
                    <view class="popup-item">
                        <text class="popup-label">手机号</text>
                        <input v-model="orderForm.contact_mobile" type="number" placeholder="请输入手机号" maxlength="11" />
                    </view>
                    <view class="popup-item">
                        <text class="popup-label">留言</text>
                        <input v-model="orderForm.message" placeholder="选填，如入住时间等" />
                    </view>
                </view>
                <view class="popup-total">
                    <text>押金</text>
                    <text class="total-price">¥{{ houseInfo.deposit || 0 }}</text>
                </view>
                <u-button type="primary" shape="circle" @click="submitOrder">确认下单并支付</u-button>
            </view>
        </u-popup>

        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getHouseDetail, createHouseOrder, offlineHouse as offlineApi } from '../../api/xiaoyuan'
import pay from '@/components/pay/pay.vue'
import { img } from '@/utils/common'
import useMemberStore from '@/stores/member'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_house')

const memberStore = useMemberStore()
const isOwner = computed(() => houseInfo.value.member_id && memberStore.info?.member_id && houseInfo.value.member_id == memberStore.info.member_id)

const houseId = ref(0)
const houseInfo = ref<any>({})
const showOrderPopup = ref(false)
const payRef = ref<any>(null)
const orderForm = ref({
    contact_name: '',
    contact_mobile: '',
    message: ''
})

const typeMap: Record<string, string> = {
    'RENT': '整租',
    'SHARE': '合租',
    'SUBLEASE': '转租'
}

const images = computed(() => {
    if (!houseInfo.value?.images) return []
    if (typeof houseInfo.value.images === 'string') {
        try {
            const parsed = JSON.parse(houseInfo.value.images)
            if (Array.isArray(parsed)) return parsed
            return houseInfo.value.images.split(',').filter((s: string) => s)
        } catch {
            return houseInfo.value.images.split(',').filter((s: string) => s)
        }
    }
    return houseInfo.value.images
})

const facilities = computed(() => {
    if (!houseInfo.value?.facilities) return []
    if (typeof houseInfo.value.facilities === 'string') {
        return houseInfo.value.facilities.split(',').filter((s: string) => s)
    }
    return houseInfo.value.facilities
})

onLoad((options: any) => {
    if (options?.id) {
        houseId.value = parseInt(options.id)
        loadConfig()
        loadDetail()
    }
})

const loadDetail = async () => {
    try {
        const res: any = await getHouseDetail(houseId.value)
        if (res.code === 1) {
            houseInfo.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const getTypeName = (type: string) => typeMap[type] || type

const previewImage = (index: any) => {
    uni.previewImage({
        current: index,
        urls: images.value.map((u: string) => img(u))
    })
}

const editHouse = () => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/house/publish?id=${houseId.value}` })
}

const offlineHouse = () => {
    uni.showModal({
        title: '提示',
        content: '确定下架该房源？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await offlineApi({ id: houseId.value })
                if (result.code === 1) {
                    uni.showToast({ title: '下架成功', icon: 'success' })
                    loadDetail()
                }
            }
        }
    })
}

const callPhone = () => {
    if (!houseInfo.value.contact_mobile) {
        uni.showToast({ title: '暂无联系电话', icon: 'none' })
        return
    }
    uni.makePhoneCall({
        phoneNumber: houseInfo.value.contact_mobile
    })
}

const submitOrder = async () => {
    if (!orderForm.value.contact_name) {
        uni.showToast({ title: '请输入联系人', icon: 'none' })
        return
    }
    if (!orderForm.value.contact_mobile || orderForm.value.contact_mobile.length !== 11) {
        uni.showToast({ title: '请输入正确手机号', icon: 'none' })
        return
    }
    uni.showLoading({ title: '提交中...' })
    const res: any = await createHouseOrder({
        house_id: houseId.value,
        ...orderForm.value
    })
    uni.hideLoading()
    if (res.code === 1 && res.data?.id) {
        showOrderPopup.value = false
        payRef.value?.open('sd_xiaoyuan_house', res.data.id, '/addon/sd_xiaoyuan/pages/house/detail?id=' + houseId.value)
    } else {
        uni.showToast({ title: res.msg || '下单失败', icon: 'none' })
    }
}

const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    loadDetail()
}

const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.detail-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 140rpx;
}

.image-swiper {
    width: 100%;
    height: 500rpx;
    
    image {
        width: 100%;
        height: 100%;
    }
}

.house-info {
    background: #fff;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.price-row {
    display: flex;
    align-items: center;
    margin-bottom: 16rpx;
    
    .price {
        font-size: 48rpx;
        font-weight: bold;
        color: #ff6b00;
        
        .unit {
            font-size: 28rpx;
            font-weight: normal;
        }
    }
    
    .type-tag {
        margin-left: 20rpx;
        padding: 6rpx 16rpx;
        background: #f6ffed;
        color: #52c41a;
        font-size: 24rpx;
        border-radius: 4rpx;
    }
}

.title {
    font-size: 34rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 16rpx;
}

.info-tags {
    display: flex;
    gap: 16rpx;
    margin-bottom: 20rpx;
    
    .tag {
        padding: 8rpx 16rpx;
        background: #f5f5f5;
        color: #666;
        font-size: 24rpx;
        border-radius: 4rpx;
    }
}

.address-row {
    display: flex;
    align-items: center;
    
    .iconfont {
        font-size: 28rpx;
        color: #52c41a;
        margin-right: 8rpx;
    }
    
    .address {
        font-size: 26rpx;
        color: #666;
    }
}

.section {
    background: #fff;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.description {
    font-size: 28rpx;
    color: #666;
    line-height: 1.8;
}

.facilities {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.facility-item {
    padding: 12rpx 24rpx;
    background: #f5f5f5;
    color: #666;
    font-size: 26rpx;
    border-radius: 8rpx;
}

.empty-text {
    font-size: 28rpx;
    color: #999;
}

.bottom-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16rpx 24rpx;
    padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 10rpx rgba(0,0,0,0.05);

    .bottom-left {
        .price-label {
            font-size: 24rpx;
            color: #999;
        }
        .price-value {
            font-size: 36rpx;
            font-weight: bold;
            color: #ff6b00;
            margin-left: 8rpx;
        }
    }

    .bottom-right {
        display: flex;
        gap: 16rpx;
    }

    .contact-btn {
        display: flex;
        align-items: center;
        gap: 6rpx;
        height: 72rpx;
        line-height: 72rpx;
        padding: 0 28rpx;
        background: #f5f5f5;
        color: #333;
        font-size: 26rpx;
        border: none;
        border-radius: 36rpx;
        &::after { border: none; }
    }

    .order-btn {
        height: 72rpx;
        line-height: 72rpx;
        padding: 0 40rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        font-size: 28rpx;
        font-weight: bold;
        border: none;
        border-radius: 36rpx;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
        &::after { border: none; }
    }
}

.order-popup {
    padding: 30rpx;

    .popup-title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
        text-align: center;
        margin-bottom: 30rpx;
    }

    .popup-house {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8f8f8;
        padding: 20rpx;
        border-radius: 12rpx;
        margin-bottom: 24rpx;

        .popup-house-title {
            font-size: 28rpx;
            color: #333;
            flex: 1;
        }
        .popup-house-price {
            font-size: 28rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }

    .popup-form {
        .popup-item {
            display: flex;
            align-items: center;
            padding: 20rpx 0;
            border-bottom: 1rpx solid #f0f0f0;

            .popup-label {
                width: 140rpx;
                font-size: 28rpx;
                color: #333;
            }

            input {
                flex: 1;
                font-size: 28rpx;
                height: 60rpx;
            }
        }
    }

    .popup-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24rpx 0;
        margin-top: 10rpx;

        text { font-size: 28rpx; color: #333; }
        .total-price { font-size: 36rpx; color: #ff6b00; font-weight: bold; }
    }

    .popup-submit {
        width: 100%;
        height: 80rpx;
        line-height: 80rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        font-size: 30rpx;
        font-weight: bold;
        border: none;
        border-radius: 40rpx;
        margin-top: 10rpx;
        padding: 0;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
        &::after { border: none; }
    }
}
</style>
