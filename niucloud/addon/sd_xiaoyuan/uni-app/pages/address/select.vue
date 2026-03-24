<template>
    <view class="address-select-page">
        <!-- 使用uni-app标准导航栏，兼容小程序 -->
        <u-navbar 
            title="选择地址" 
            :safeAreaInsetTop="true"
            :placeholder="true"
            bgColor="#c0fe95"
            :autoBack="true"
            leftIcon="arrow-left"
            @clickLeft="goBack"
        >
        </u-navbar>

        <scroll-view scroll-y class="address-list">
            <view 
                class="address-item"  
                v-for="item in addressList" 
                :key="item.id"
                @click="selectAddress(item)"
            >
                <view class="address-info">
                    <view class="address-top">
                        <text class="name">{{ item.name }}</text>
                        <text class="mobile">{{ item.mobile }}</text>
                        <view class="default-tag" v-if="item.is_default">默认</view>
                    </view>
                    <view class="address-detail">{{ item.address }}</view>
                </view>
                <view class="address-check" v-if="selectedId === item.id">
                    <u-icon name="checkmark-circle-fill" size="24" color="#52c41a"></u-icon>
                </view>
            </view>

            <view class="empty" v-if="addressList.length === 0 && !loading">
                <u-icon name="map" size="80" color="#ccc"></u-icon>
                <text>暂无地址，请添加</text>
            </view>
            
            <!-- 底部占位 -->
            <view style="height: 120rpx;"></view>
        </scroll-view>

        <!-- 底部添加按钮 -->
        <view class="bottom-bar">
            <button class="add-address-btn" @click="goToAdd">
                <u-icon name="plus-circle" size="18" color="#333"></u-icon>
                <text>添加新地址</text>
            </button>
        </view>

    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getAddressList } from '../../api/xiaoyuan'

const loading = ref(false)
const addressList = ref<any[]>([])
const selectedId = ref(0)
const addressType = ref('receive')

onLoad((options: any) => {
    if (options?.type) {
        addressType.value = options.type
    }
})

onShow(() => {
    loadAddressList()
})

const loadAddressList = async () => {
    loading.value = true
    try {
        const res: any = await getAddressList()
        if (res.code === 1) {
            addressList.value = res.data.list || res.data || []
            // 默认选中默认地址
            const defaultAddr = addressList.value.find((item: any) => item.is_default === 1)
            if (defaultAddr) {
                selectedId.value = defaultAddr.id
            } else if (addressList.value.length > 0) {
                selectedId.value = addressList.value[0].id
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const selectAddress = (item: any) => {
   // 使用 uni.$emit 全局事件，兼容性更好
   // 添加地址类型，以便区分取货地址和送货地址
        uni.$emit('onAddressSelect', { ...item, type: addressType.value })
        
        // 同时尝试 eventChannel 方式
        const pages = getCurrentPages()
        const currentPage = pages[pages.length - 1] as any
        const eventChannel = currentPage.getOpenerEventChannel?.()
        if (eventChannel) {
            try {
                eventChannel.emit('selectAddress', { ...item, type: addressType.value })
            } catch (e) {
                console.log('eventChannel emit error', e)
            }
        }
        
        uni.navigateBack()
}



const goToAdd = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/edit'
    })
}

const goBack = () => {
    uni.navigateBack()
}
</script>

<style lang="scss" scoped>
.address-select-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.address-list {
    height: calc(100vh - 200rpx);
    width: auto;
    padding: 20rpx;
}

.address-item {
    display: flex;
    align-items: center;
    background: #fff;
    padding: 30rpx;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    
    &:active {
        background: #f9f9f9;
    }
}

.address-info {
    flex: 1;
    
    .address-top {
        display: flex;
        align-items: center;
        margin-bottom: 12rpx;
        
        .name {
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
            margin-right: 20rpx;
        }
        
        .mobile {
            font-size: 28rpx;
            color: #666;
        }
        
        .default-tag {
            margin-left: 16rpx;
            padding: 4rpx 12rpx;
            background: #52c41a;
            color: #fff;
            font-size: 20rpx;
            border-radius: 6rpx;
        }
    }
    
    .address-detail {
        font-size: 26rpx;
        color: #999;
        line-height: 1.5;
    }
}

.address-check {
    margin-left: 20rpx;
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 200rpx;
    
    text {
        margin-top: 20rpx;
        font-size: 28rpx;
        color: #999;
    }
}

.bottom-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    z-index: 10;
    
    .add-address-btn {
        width: 100%;
        height: 88rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #333;
        font-size: 30rpx;
        font-weight: bold;
        border-radius: 44rpx;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12rpx;
    }
}
</style>
