<template>
    <view :style="warpCss">
        <view class="diy-xiaoyuan-order-hall">
            <view class="hall-header">
                <text class="hall-title">{{ diyComponent.title || '任务大厅' }}</text>
                <view class="hall-more" v-if="diyComponent.showMore" @click="toLink(diyComponent.moreUrl)">
                    <text>{{ diyComponent.moreText || '查看更多' }}</text>
                    <u-icon name="arrow-right" size="12" color="#999"></u-icon>
                </view>
            </view>
            
            <view class="hall-tabs" v-if="tabList.length > 0">
                <view 
                    class="tab-item" 
                    :class="{ active: currentTab === idx }"
                    v-for="(tab, idx) in tabList" 
                    :key="idx"
                    @click="switchTab(idx)"
                >{{ tab.name }}</view>
            </view>
            
            <view class="hall-list">
                <view class="order-item" v-for="(order, idx) in orderList" :key="idx" @click="toOrderDetail(order)">
                    <view class="order-type">
                        <text class="type-tag" :style="{ background: getTypeColor(order.task_type) }">{{ getTypeName(order.task_type) }}</text>
                    </view>
                    <view class="order-info">
                        <text class="order-title">{{ order.title || order.content }}</text>
                        <text class="order-address">{{ order.from_address }} → {{ order.to_address }}</text>
                    </view>
                    <view class="order-price">
                        <text class="price">¥{{ order.tip_fee || order.total_amount }}</text>
                    </view>
                </view>
                <view class="empty-tip" v-if="orderList.length === 0">暂无任务</view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { redirect } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import { getOrderHall } from '@/addon/sd_xiaoyuan/api/xiaoyuan'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()

const currentTab = ref(0)
const orderList = ref<any[]>([])

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    } else {
        return props.component
    }
})

const tabList = computed(() => {
    return (diyComponent.value.tabs || []).filter((item: any) => item.isShow !== false)
})

const warpCss = computed(() => {
    let style = ''
    if (diyComponent.value.componentStartBgColor) {
        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) {
            style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`
        } else {
            style += 'background-color:' + diyComponent.value.componentStartBgColor + ';'
        }
    }
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;'
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;'
    return style
})

const loadOrders = async () => {
    if (diyStore.mode == 'decorate') {
        orderList.value = [
            { id: 1, task_type: 'EXPRESS', title: '取快递示例', from_address: '菜鸟驿站', to_address: '1号楼', tip_fee: '5.00' },
            { id: 2, task_type: 'BUY', title: '代买示例', from_address: '食堂', to_address: '2号楼', tip_fee: '8.00' }
        ]
        return
    }
    
    const tabType = tabList.value[currentTab.value]?.type || 'all'
    const params: any = { page: 1, limit: diyComponent.value.num || 10, status: 10 }
    if (tabType !== 'all') params.task_type = tabType
    
    const res: any = await getOrderHall(params)
    if (res.code === 1) {
        orderList.value = res.data?.data || res.data?.list || []
    }
}

const switchTab = (idx: any) => {
    currentTab.value = Number(idx)
    loadOrders()
}

const getTypeName = (type: string) => {
    const map: any = { EXPRESS: '取快递', BUY: '代买', CARRY: '代送', HELP: '帮帮忙', PRINT: '打印', TRASH: '垃圾代扔', CLEAN: '保洁' }
    return map[type] || type
}

const getTypeColor = (type: string) => {
    const map: any = { EXPRESS: '#ff6b00', BUY: '#1890ff', CARRY: '#52c41a', HELP: '#eb2f96', PRINT: '#722ed1', TRASH: '#13c2c2', CLEAN: '#faad14' }
    return map[type] || '#999'
}

const toLink = (url: string) => {
    if (diyStore.mode == 'decorate') return
    if (url) redirect({ url })
}

const toOrderDetail = (order: any) => {
    if (diyStore.mode == 'decorate') return
    redirect({ url: `/addon/sd_xiaoyuan/pages/order/detail?id=${order.id}` })
}

onMounted(() => {
    loadOrders()
})

watch(() => diyComponent.value, () => {
    loadOrders()
}, { deep: true })
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-order-hall {
    padding: 24rpx;
    
    .hall-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20rpx;
        
        .hall-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
        
        .hall-more {
            display: flex;
            align-items: center;
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .hall-tabs {
        display: flex;
        gap: 16rpx;
        margin-bottom: 20rpx;
        overflow-x: auto;
        
        .tab-item {
            padding: 12rpx 24rpx;
            background: #f5f5f5;
            border-radius: 30rpx;
            font-size: 26rpx;
            color: #666;
            white-space: nowrap;
            
            &.active {
                background: linear-gradient(135deg, #c0fe95, #d1ff7c);
                color: #333;
            }
        }
    }
    
    .hall-list {
        .order-item {
            display: flex;
            align-items: center;
            padding: 20rpx 0;
            border-bottom: 1rpx solid #f0f0f0;
            
            &:last-child {
                border-bottom: none;
            }
            
            .order-type {
                margin-right: 16rpx;
                
                .type-tag {
                    padding: 6rpx 12rpx;
                    border-radius: 6rpx;
                    font-size: 22rpx;
                    color: #fff;
                }
            }
            
            .order-info {
                flex: 1;
                
                .order-title {
                    display: block;
                    font-size: 28rpx;
                    color: #333;
                    margin-bottom: 8rpx;
                }
                
                .order-address {
                    font-size: 24rpx;
                    color: #999;
                }
            }
            
            .order-price {
                .price {
                    font-size: 32rpx;
                    font-weight: bold;
                    color: #ff6b00;
                }
            }
        }
        
        .empty-tip {
            text-align: center;
            padding: 40rpx;
            color: #999;
            font-size: 26rpx;
        }
    }
}
</style>
