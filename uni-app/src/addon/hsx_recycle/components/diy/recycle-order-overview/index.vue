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
                <!-- 待签收 (订单状态1) -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('1')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.pending_sign }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ pendingSignText }}</text>
                </view>

                <!-- 质检中 (订单状态2+3) -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('3')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.checking }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ checkingText }}</text>
                </view>

                <!-- 待确认 (订单状态5) -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('5')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.pending_confirm }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ pendingConfirmText }}</text>
                </view>

                <!-- 待打款 (订单状态6) -->
                <view class="status-item flex-1 flex flex-col items-center" @click="handleStatusClick('6')">
                    <text class="status-count font-bold" :style="{ color: numberColor }">{{ orderData.pending_payment }}</text>
                    <text class="status-name" :style="{ color: labelColor }">{{ pendingPaymentText }}</text>
                </view>
            </view>

            <view
                v-if="showConsignmentEntry"
                class="consignment-entry mx-4 mb-3"
                @click="handleConsignmentClick"
            >
                <view>
                    <text class="consignment-title" :style="{ color: titleColor }">{{ consignmentTitle }}</text>
                    <text class="consignment-desc" :style="{ color: labelColor }">{{ consignmentDesc }}</text>
                </view>
                <view class="consignment-count">
                    <text class="count" :style="{ color: numberColor }">{{ consignmentTotal }}</text>
                    <text class="label" :style="{ color: labelColor }">单</text>
                    <text class="iconfont iconarrow-right ml-1" :style="{ color: viewAllColor }"></text>
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
import { getOrderStatusCount } from '@/addon/hsx_recycle/api/order';
import { getConsignmentStatusCount } from '@/addon/hsx_recycle/api/consignment';
import { useLogin } from "@/hooks/useLogin";


// 定义订单数据接口
interface OrderData {
    pending_sign: number;     // 待签收 (状态1)
    checking: number;         // 质检中 (状态2+3)
    pending_confirm: number;  // 待确认 (状态5)
    pending_payment: number;  // 待打款 (状态6)
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
    pending_sign: 0,
    checking: 0,
    pending_confirm: 0,
    pending_payment: 0
});
const consignmentEnabled = ref(false);
const consignmentTotal = ref(0);
const consignmentServerTitle = ref('');
const consignmentServerDesc = ref('');

const decorateOrderData: OrderData = {
    pending_sign: 3,
    checking: 5,
    pending_confirm: 2,
    pending_payment: 1
};

// 组件配置
const title = computed(() => props.component.title || '回收订单');
const viewAllText = computed(() => props.component.viewAllText || '全部');
const pendingSignText = computed(() => props.component.pendingSignText || '待签收');
const checkingText = computed(() => props.component.checkingText || '质检中');
const pendingConfirmText = computed(() => props.component.pendingConfirmText || '待确认');
const pendingPaymentText = computed(() => props.component.pendingPaymentText || '待打款');
const consignmentTitle = computed(() => props.component.consignmentText || consignmentServerTitle.value || '代卖订单');
const consignmentDesc = computed(() => props.component.consignmentDesc || consignmentServerDesc.value || '查看代卖进度、成交与结算结果');
const showConsignmentEntry = computed(() => {
    if (props.component.showConsignment === 0 || props.component.showConsignment === false) return false;
    if (diyStore.mode === 'decorate') return true;
    return consignmentEnabled.value;
});

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
    useLogin().setLoginBack({ url:'/addon/hsx_recycle/pages/order/list' })
   }
   redirect({url:'/addon/hsx_recycle/pages/order/list'})
};

// 处理点击订单状态
const handleStatusClick = (status: string) => {
    // 装修模式下不触发跳转
    if (diyStore.mode === 'decorate') {
        return;
    }
    
    if(!getToken()){
    useLogin().setLoginBack({ url:'/addon/hsx_recycle/pages/order/list?status='+status })
   }
   redirect({url:'/addon/hsx_recycle/pages/order/list?status='+status})
};

const handleConsignmentClick = () => {
    if (diyStore.mode === 'decorate') {
        return;
    }

    if (!getToken()) {
        useLogin().setLoginBack({ url: '/addon/hsx_recycle/pages/consignment/list' });
    }
    redirect({ url: '/addon/hsx_recycle/pages/consignment/list' });
};

// 获取订单状态统计数据
const getOrderDataCount = async () => {
    if (diyStore.mode === 'decorate') {
        orderData.value = decorateOrderData;
        return;
    }

    try {
        const res: any = await getOrderStatusCount();
        if (res.code === 1 && res.data && res.data.list) {
            // 从接口返回的 list 中按 key 提取各状态数量
            const statusMap: Record<string, number> = {};
            res.data.list.forEach((item: any) => {
                statusMap[item.key] = item.count || 0;
            });

            orderData.value = {
                pending_sign: statusMap['1'] || 0,       // 待签收(状态1)
                checking: (statusMap['2'] || 0) + (statusMap['3'] || 0), // 质检中(状态2已签收+状态3质检中)
                pending_confirm: statusMap['5'] || 0,    // 待确认(状态5)
                pending_payment: statusMap['6'] || 0     // 待打款(状态6)
            };
        }
    } catch (error) {
        console.error('获取订单状态数量失败:', error);
    }
};

const getConsignmentDataCount = async () => {
    if (props.component.showConsignment === 0 || props.component.showConsignment === false) {
        consignmentEnabled.value = false;
        return;
    }

    if (diyStore.mode === 'decorate') {
        consignmentEnabled.value = true;
        consignmentTotal.value = 2;
        return;
    }

    try {
        const res: any = await getConsignmentStatusCount();
        if (res.code === 1 && res.data?.enabled) {
            consignmentEnabled.value = true;
            consignmentTotal.value = Number(res.data.total || 0);
            consignmentServerTitle.value = res.data.title || '';
            consignmentServerDesc.value = res.data.desc || '';
            return;
        }
        consignmentEnabled.value = false;
        consignmentTotal.value = 0;
    } catch (error) {
        consignmentEnabled.value = false;
        console.error('获取代卖订单数量失败:', error);
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
    getOrderDataCount();
    getConsignmentDataCount();
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

    .consignment-entry {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20rpx;
        padding: 22rpx 24rpx;
        border-radius: 16rpx;
        background: #f8fafc;
    }

    .consignment-title,
    .consignment-desc {
        display: block;
    }

    .consignment-title {
        font-size: 28rpx;
        font-weight: 600;
    }

    .consignment-desc {
        margin-top: 6rpx;
        font-size: 22rpx;
        line-height: 1.4;
    }

    .consignment-count {
        display: flex;
        align-items: baseline;
        flex-shrink: 0;
    }

    .consignment-count .count {
        font-size: 34rpx;
        font-weight: 700;
    }

    .consignment-count .label {
        margin-left: 4rpx;
        font-size: 22rpx;
    }
}
</style>
