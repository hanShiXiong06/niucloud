<template>
    <view class="address-list">
        <!-- 地址列表 -->
        <view class="list-section">
            <view 
                class="address-item" 
                v-for="item in addressList" 
                :key="item.id"
                @click="selectAddress(item)"
            >
                <view class="address-content">
                    <view class="address-header">
                        <text class="name">{{ item.name }}</text>
                        <text class="mobile">{{ item.mobile }}</text>
                        <text class="type-tag" v-if="item.address_type !== 'OTHER'">{{ getTypeName(item.address_type) }}</text>
                        <text class="default-tag" v-if="item.is_default">默认</text>
                    </view>
                    <text class="address">{{ item.address }}</text>
                    <text class="building" v-if="item.building || item.room">{{ item.building }} {{ item.room }}</text>
                </view>
                <view class="address-actions">
                    <view class="action-btn" @click.stop="editAddress(item)">
                        <u-icon name="edit-pen" size="20" color="#666"></u-icon>
                    </view>
                    <view class="action-btn del" @click.stop="deleteAddress(item.id)">
                        <u-icon name="trash" size="20" color="#ff4d4f"></u-icon>
                    </view>
                </view>
            </view>

            <view class="empty" v-if="addressList.length === 0">
                <u-icon name="map" size="120" color="#ccc"></u-icon>
                <text>暂无地址，请添加</text>
            </view>
        </view>

        <!-- 添加地址按钮 -->
        <view class="add-section">
            <button class="add-btn" @click="goToAdd">
                <u-icon name="plus" size="18" color="#000"></u-icon>
                <text>添加新地址</text>
            </button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getAddressList, deleteAddress as deleteAddressApi } from '../../api/xiaoyuan'

const addressList = ref<any[]>([])
const selectType = ref('')

const typeMap: Record<string, string> = {
    'DORM': '宿舍',
    'TEACHING': '教学楼',
    'EXPRESS': '快递点',
    'OTHER': '其他'
}

onLoad((options: any) => {
    selectType.value = options?.type || ''
})

onShow(() => {
    loadAddressList()
})

const loadAddressList = async () => {
    try {
        const res: any = await getAddressList()
        if (res.code === 1) {
            addressList.value = res.data.list || res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const getTypeName = (type: string) => {
    return typeMap[type] || type
}

const selectAddress = (item: any) => {
    if (selectType.value) {
        const eventChannel = (uni as any).getOpenerEventChannel()
        eventChannel.emit('selectAddress', item)
        uni.navigateBack()
    }
}

const editAddress = (item: any) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/address/edit?id=${item.id}`
    })
}

const deleteAddress = (id: number) => {
    uni.showModal({
        title: '提示',
        content: '确定要删除这个地址吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const result: any = await deleteAddressApi({ id })
                    if (result.code === 1) {
                        uni.showToast({ title: '删除成功', icon: 'success' })
                        loadAddressList()
                    } else {
                        uni.showToast({ title: result.msg || '删除失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '网络错误', icon: 'none' })
                }
            }
        }
    })
}

const goToAdd = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/edit'
    })
}
</script>

<style lang="scss" scoped>
.address-list {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 150rpx;
}

.list-section {
    padding: 20rpx;
}

.address-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.address-content {
    flex: 1;
    
    .address-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12rpx;
        margin-bottom: 12rpx;
        
        .name {
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
        }
        
        .mobile {
            font-size: 28rpx;
            color: #666;
        }
        
        .type-tag {
            font-size: 22rpx;
            color: #333;
            background: #c0fe95;
            padding: 4rpx 12rpx;
            border-radius: 4rpx;
            font-weight: bold;
        }
        
        .default-tag {
            font-size: 22rpx;
            color: #000000;
            background: #e6f7ff;
            padding: 4rpx 12rpx;
            border-radius: 4rpx;
        }
    }
    
    .address {
        display: block;
        font-size: 28rpx;
        color: #333;
        line-height: 1.5;
    }
    
    .building {
        display: block;
        font-size: 26rpx;
        color: #999;
        margin-top: 8rpx;
    }
}

.address-actions {
    display: flex;
    flex-direction: column;
    gap: 16rpx;
    margin-left: 20rpx;
    
    .action-btn {
        width: 56rpx;
        height: 56rpx;
        border-radius: 50%;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;

        &.del {
            background: #fff0f0;
        }
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    text {
        font-size: 28rpx;
        color: #999;
        margin-top: 20rpx;
    }
}

.add-section {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05);
}

.add-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10rpx;
    height: 80rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 30rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
}
</style>
