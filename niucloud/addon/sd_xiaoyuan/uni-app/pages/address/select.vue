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
            >
                <view class="address-row" @click="selectAddress(item)">
                    <view class="address-info">
                        <view class="address-top">
                            <text class="name">{{ item.name }}</text>
                            <text class="mobile">{{ item.mobile }}</text>
                            <view class="default-tag" v-if="item.is_default === 1 || item.is_default === true">默认</view>
                            <text v-if="item.address_type && item.address_type !== 'OTHER'" class="type-tag">{{ typeName(item.address_type) }}</text>
                        </view>
                        <view class="address-detail">{{ item.address }}</view>
                        <view class="address-building" v-if="buildingRoomLine(item)">
                            <text>{{ buildingRoomLine(item) }}</text>
                        </view>
                    </view>
                    <view class="address-check" v-if="selectedId === item.id">
                        <u-icon name="checkmark-circle-fill" size="24" color="#52c41a"></u-icon>
                    </view>
                </view>
                <view class="address-actions" @click.stop>
                    <text class="action-edit" @click="goToEdit(item)">修改</text>
                    <text class="action-delete" @click="handleDelete(item)">删除</text>
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
import { getAddressList, deleteAddress } from '../../api/xiaoyuan'

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

const typeMap: Record<string, string> = {
    DORM: '宿舍',
    TEACHING: '教学楼',
    EXPRESS: '快递点',
    OTHER: '其他'
}

const typeName = (k: string) => (k && typeMap[k]) || k || '其他'

const buildingRoomLine = (item: any) => {
    const b = String(item?.building || '').trim()
    const r = String(item?.room || '').trim()
    const s = [b, r].filter(Boolean).join(' ')
    return s || ''
}

/** 地图选点 + 楼栋 + 房间 + [地址类型]，合并为一条，用于下单 pickup/receive_address */
const buildFullAddressForOrder = (item: any) => {
    const base = (item?.address || '').trim()
    const building = (item?.building || '').trim()
    const room = (item?.room || '').trim()
    const typeKey = String(item?.address_type || 'OTHER')

    let body = base
    if (building && !body.includes(building)) {
        body = body ? `${body} ${building}` : building
    }
    if (room && !body.includes(room)) {
        body = body ? `${body} ${room}` : room
    }
    body = body.trim()

    if (typeKey && typeKey !== 'OTHER') {
        const label = typeName(typeKey)
        return `[${label}] ${body}`.trim()
    }
    return body
}

const loadAddressList = async () => {
    loading.value = true
    try {
        const res: any = await getAddressList()
        if (res.code === 1) {
            addressList.value = res.data.list || res.data || []
            // 默认选中默认地址
            const defaultAddr = addressList.value.find((item: any) => item.is_default === 1 || item.is_default === true)
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
        const full = buildFullAddressForOrder(item)
        const selected = {
            ...item,
            full_address: full,
            address: full,
            type: addressType.value
        }
        uni.$emit('onAddressSelect', selected)
        
        // 同时尝试 eventChannel 方式
        const pages = getCurrentPages()
        const currentPage = pages[pages.length - 1] as any
        const eventChannel = currentPage.getOpenerEventChannel?.()
        if (eventChannel) {
            try {
                eventChannel.emit('selectAddress', selected)
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

const goToEdit = (item: any) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/address/edit?id=${item.id}`
    })
}

const handleDelete = (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定删除该地址吗？',
        success: async (res) => {
            if (!res.confirm) return
            try {
                uni.showLoading({ title: '删除中...' })
                const r: any = await deleteAddress({ id: item.id })
                uni.hideLoading()
                if (r.code === 1) {
                    uni.showToast({ title: '已删除', icon: 'success' })
                    if (selectedId.value === item.id) {
                        selectedId.value = 0
                    }
                    loadAddressList()
                } else {
                    uni.showToast({ title: r.msg || '删除失败', icon: 'none' })
                }
            } catch (e) {
                uni.hideLoading()
                uni.showToast({ title: '删除失败', icon: 'none' })
            }
        }
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
    background: #fff;
    padding: 0;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    overflow: hidden;
}

.address-row {
    display: flex;
    align-items: center;
    padding: 30rpx;
    
    &:active {
        background: #f9f9f9;
    }
}

.address-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 32rpx;
    padding: 0 30rpx 24rpx;
    border-top: 1rpx solid #f0f0f0;
    
    .action-edit {
        font-size: 26rpx;
        color: #1890ff;
    }
    
    .action-delete {
        font-size: 26rpx;
        color: #ff4d4f;
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
        
        .type-tag {
            margin-left: 12rpx;
            padding: 4rpx 12rpx;
            background: #e6f7ff;
            color: #1890ff;
            font-size: 22rpx;
            border-radius: 6rpx;
        }
    }
    
    .address-detail {
        font-size: 26rpx;
        color: #666;
        line-height: 1.5;
    }
    
    .address-building {
        margin-top: 8rpx;
        font-size: 26rpx;
        color: #999;
        line-height: 1.4;
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
