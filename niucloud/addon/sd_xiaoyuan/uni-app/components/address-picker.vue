<template>
    <view class="address-picker">
        <view class="picker-header">
            <text class="title">选择地址</text>
            <text class="close" @click="close">×</text>
        </view>
        
        <view class="picker-content">
            <!-- 常用地址 -->
            <view class="address-section" v-if="addressList.length > 0">
                <view class="section-title">常用地址</view>
                <view 
                    class="address-item" 
                    v-for="item in addressList" 
                    :key="item.id"
                    @click="selectAddress(item)"
                >
                    <view class="address-icon">
                        <text class="iconfont icon-location"></text>
                    </view>
                    <view class="address-info">
                        <view class="info-header">
                            <text class="name">{{ item.name }}</text>
                            <text class="mobile">{{ item.mobile }}</text>
                            <text class="type-tag" v-if="item.address_type !== 'OTHER'">{{ getTypeName(item.address_type) }}</text>
                        </view>
                        <text class="address">{{ item.address }}</text>
                    </view>
                </view>
            </view>

            <!-- 选择位置 -->
            <view class="action-section">
                <view class="action-item" @click="chooseLocation">
                    <text class="iconfont icon-map"></text>
                    <text>从地图选择</text>
                </view>
                <view class="action-item" @click="goToAddAddress">
                    <text class="iconfont icon-plus"></text>
                    <text>新增地址</text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getAddressList } from '../api/xiaoyuan'

const emit = defineEmits(['select', 'close'])

const addressList = ref<any[]>([])

const typeMap: Record<string, string> = {
    'DORM': '宿舍',
    'TEACHING': '教学楼',
    'EXPRESS': '快递点',
    'OTHER': '其他'
}

onMounted(() => {
    loadAddressList()
})

const loadAddressList = async () => {
    try {
        const res: any = await getAddressList()
        if (res.code === 1) {
            addressList.value = (res.data.list || res.data).slice(0, 5)
        }
    } catch (e) {
        console.error(e)
    }
}

const getTypeName = (type: string) => typeMap[type] || type

const selectAddress = (item: any) => {
    emit('select', {
        name: item.name,
        mobile: item.mobile,
        address: item.address,
        lng: item.lng,
        lat: item.lat
    })
}

const chooseLocation = () => {
    uni.chooseLocation({
        success: (res) => {
            emit('select', {
                name: '',
                mobile: '',
                address: res.name || res.address,
                lng: res.longitude,
                lat: res.latitude
            })
        }
    })
}

const goToAddAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/edit'
    })
}

const close = () => {
    emit('close')
}
</script>

<style lang="scss" scoped>
.address-picker {
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30rpx;
    border-bottom: 1rpx solid #f0f0f0;
    
    .title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
    
    .close {
        font-size: 40rpx;
        color: #999;
    }
}

.picker-content {
    flex: 1;
    overflow-y: auto;
    padding: 20rpx;
}

.address-section {
    margin-bottom: 30rpx;
}

.section-title {
    font-size: 26rpx;
    color: #999;
    margin-bottom: 16rpx;
}

.address-item {
    display: flex;
    padding: 24rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    margin-bottom: 16rpx;
    
    .address-icon {
        width: 50rpx;
        height: 50rpx;
        background: #f6ffed;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20rpx;
        flex-shrink: 0;
        
        .iconfont {
            font-size: 28rpx;
            color: #52c41a;
        }
    }
    
    .address-info {
        flex: 1;
        
        .info-header {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12rpx;
            margin-bottom: 8rpx;
            
            .name {
                font-size: 28rpx;
                font-weight: bold;
                color: #333;
            }
            
            .mobile {
                font-size: 26rpx;
                color: #666;
            }
            
            .type-tag {
                font-size: 22rpx;
                color: #52c41a;
                background: #f6ffed;
                padding: 4rpx 12rpx;
                border-radius: 4rpx;
            }
        }
        
        .address {
            font-size: 26rpx;
            color: #999;
        }
    }
}

.action-section {
    display: flex;
    gap: 20rpx;
}

.action-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 30rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    
    .iconfont {
        font-size: 48rpx;
        color: #52c41a;
        margin-bottom: 12rpx;
    }
    
    text {
        font-size: 26rpx;
        color: #333;
    }
}
</style>
