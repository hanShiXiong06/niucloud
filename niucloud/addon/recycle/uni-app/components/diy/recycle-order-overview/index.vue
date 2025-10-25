<template>
    <view :style="warpCss">
        <view :style="maskLayer"></view>
        <view class="diy-recycle-order-overview relative">
            <view class="overview-title flex justify-between items-center py-3 px-4">
                <text class="title-text font-medium" :style="{ color: titleColor }">{{ title }}</text>
                <view class="flex items-center" @click="handleViewAll">
                    <text class="view-all-text" :style="{ color: viewAllColor }">{{ viewAllText }}</text>
                    <text class="iconfont iconarrow-right ml-1" :style="{ color: viewAllColor }"></text>
                </view>
            </view>

            <view class="overview-content flex justify-between items-center py-3 px-4">
                <!-- 待质检 -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('pending_inspection')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.pending_inspection }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ pendingReceiptText }}</text>
                </view>

                <!-- 处理中 -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('processing')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.processing }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ processingText }}</text>
                </view>

                <!-- 已质检 -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('inspected')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.inspected }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ shippedText }}</text>
                </view>

                <!-- 待确认 -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('pending_confirm')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.pending_confirm }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ pendingConfirmText }}</text>
                </view>
            </view>
        </view>
    </view>
</template>

<script lang="ts" setup>
// 回收订单概况组件
import { ref, computed, onMounted } from 'vue';
import { img , getToken, redirect } from '@/utils/common';
import { useRouter } from 'vue-router';
import useDiyStore from '@/app/stores/diy';
import { getDeviceStatusCount } from '@/addon/recycle/api/recycle';
import { useLogin } from "@/hooks/useLogin";




// 定义订单数据接口
interface OrderData {
    pending_inspection: number; // 待质检
    processing: number;      // 处理中
    inspected: number;         // 已质检
    pending_confirm: number; // 待确认
}

// 定义API响应接口
interface ApiResponse {
    code: number;
    message: string;
    data: OrderData;
}

const props = defineProps({
    component: {
        type: Object,
        default: () => ({})
    },
    index: {
        type: Number,
        default: 0
    }
});

const router = useRouter();
const diyStore = useDiyStore();

// 初始化数据
const orderData = ref<OrderData>({
    pending_inspection: 0,
    processing: 0,
    inspected: 0,
    pending_confirm: 0
});

// 组件配置
const title = computed(() => props.component.title || '发货订单');
const viewAllText = computed(() => props.component.viewAllText || '全部');
const pendingReceiptText = computed(() => props.component.pendingReceiptText || '待质检');
const processingText = computed(() => props.component.processingText || '处理中');
const shippedText = computed(() => props.component.shippedText || '已质检');
const pendingConfirmText = computed(() => props.component.pendingConfirmText || '待确认');

// 颜色设置
const titleColor = computed(() => props.component.titleColor || '#333333');
const viewAllColor = computed(() => props.component.viewAllColor || '#999999');
const numberColor = computed(() => props.component.numberColor || '#FF6B00');
const labelColor = computed(() => props.component.labelColor || '#666666');

// 处理点击查看全部
const handleViewAll = () => {
    // 装修模式下不触发跳转
    if (diyStore.mode === 'decorate') {
        return;
    }
   if(!getToken()){
    useLogin().setLoginBack({ url:'/addon/recycle/pages/order/list' })
   }
   redirect({url:'/addon/recycle/pages/order/list'})
};

// 处理点击订单状态
const handleStatusClick = (status: string) => {
    // 装修模式下不触发跳转
    if (diyStore.mode === 'decorate') {
        return;
    }
    
    if(!getToken()){
    useLogin().setLoginBack({ url:'/addon/recycle/pages/order/list?status='+status })
   }
   redirect({url:'/addon/recycle/pages/order/list?status='+status})
};

// 获取设备统计数据
const getOrderDataCount = async () => {
    try {
        const res = await getDeviceStatusCount() as ApiResponse;
        console.log('获取设备状态数量:', res);
        if (res.code === 1 && res.data) {
            orderData.value = res.data;
        }
    } catch (error) {
        console.error('获取设备状态数量失败:', error);
    }
};

// 组件样式
const warpCss = computed(() => {
    let style = '';
    style += 'position:relative;';
    
    // 背景色设置
    if (props.component.componentStartBgColor) {
        if (props.component.componentStartBgColor && props.component.componentEndBgColor) {
            style += `background:linear-gradient(${props.component.componentGradientAngle || 'to bottom'},${props.component.componentStartBgColor},${props.component.componentEndBgColor});`;
        } else {
            style += 'background-color:' + props.component.componentStartBgColor + ';';
        }
    } else {
        style += 'background-color: #ffffff;';
    }
    
    // 背景图设置
    if (props.component.componentBgUrl) {
        style += `background-image:url('${img(props.component.componentBgUrl)}');`;
        style += 'background-size: cover;background-repeat: no-repeat;';
    }
    
    // 圆角设置
    if (props.component.topRounded) {
        style += 'border-top-left-radius:' + props.component.topRounded * 2 + 'rpx;';
        style += 'border-top-right-radius:' + props.component.topRounded * 2 + 'rpx;';
    } else {
        style += 'border-top-left-radius: 8rpx;';
        style += 'border-top-right-radius: 8rpx;';
    }
    
    if (props.component.bottomRounded) {
        style += 'border-bottom-left-radius:' + props.component.bottomRounded * 2 + 'rpx;';
        style += 'border-bottom-right-radius:' + props.component.bottomRounded * 2 + 'rpx;';
    } else {
        style += 'border-bottom-left-radius: 8rpx;';
        style += 'border-bottom-right-radius: 8rpx;';
    }
    
    // 边距设置
    const margin = props.component.margin || { top: 10, bottom: 10, both: 10 };
    style += `margin: ${margin.top * 2}rpx ${margin.both * 2}rpx ${margin.bottom * 2}rpx;`;
    
    return style;
});

// 背景遮罩层
const maskLayer = computed(() => {
    let style = '';
    if (props.component.componentBgUrl) {
        style += 'position:absolute;top:0;width:100%;';
        style += `background: rgba(0,0,0,${props.component.componentBgAlpha / 10});`;
        style += 'height:100%;';
        
        // 圆角同步设置
        if (props.component.topRounded) {
            style += 'border-top-left-radius:' + props.component.topRounded * 2 + 'rpx;';
            style += 'border-top-right-radius:' + props.component.topRounded * 2 + 'rpx;';
        } else {
            style += 'border-top-left-radius: 8rpx;';
            style += 'border-top-right-radius: 8rpx;';
        }
        
        if (props.component.bottomRounded) {
            style += 'border-bottom-left-radius:' + props.component.bottomRounded * 2 + 'rpx;';
            style += 'border-bottom-right-radius:' + props.component.bottomRounded * 2 + 'rpx;';
        } else {
            style += 'border-bottom-left-radius: 8rpx;';
            style += 'border-bottom-right-radius: 8rpx;';
        }
    }
    return style;
});

onMounted(() => {
    // 获取真实数据
    getOrderDataCount();
    console.log('回收订单概况组件已挂载');
});
</script>

<style lang="scss" scoped>
.diy-recycle-order-overview {
    box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
    
    .overview-title {
        border-bottom: 1rpx solid #f5f5f5;
        
        .title-text {
            font-size: 30rpx;
        }
        
        .view-all-text {
            font-size: 24rpx;
        }
    }
    
    .overview-content {
        .status-item {
            position: relative;
            
            .status-count {
                font-size: 36rpx;
                margin-bottom: 6rpx;
            }
            
            .status-name {
                font-size: 24rpx;
            }
            
            &:not(:last-child)::after {
                content: '';
                position: absolute;
                right: 0;
                top: 20%;
                height: 60%;
                width: 1rpx;
                background-color: #f0f0f0;
            }
        }
    }
}
</style> 