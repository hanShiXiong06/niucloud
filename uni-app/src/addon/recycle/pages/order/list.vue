<template>
    <view class="page">
        <!-- 顶部状态切换 -->
        <view class="header-section">
            <view class="nav-header">

                <scroll-view class="status-nav" scroll-x>
                    <view class="nav-items">
                        <view v-for="(item, index) in statusList" :key="index" class="nav-item"
                            :class="{ active: currentStatus === item.key }" @click="switchStatus(item.key)">
                            {{ item.text }}
                            <text class="count" v-if="item.count&& item.key!='all'">{{ item.count }}</text>
                        </view>
                    </view>
                </scroll-view>

            </view>


            <!-- 搜索 -->
            <view class="search-section">
                <view class="search-bar">
                    <view class="search-input">
                        <up-icon size="22" name="search"></up-icon>
                        <up-input type="text" border="none" v-model="searchKeyword" clearable placeholder="搜索订单号/IMEI"
                            confirm-type="search" @cancel="clearSearch" @confirm="handleSearch" />
                        <text class="iconfont icon-close" v-if="searchKeyword" @click="clearSearch"></text>
                    </view>
                    <text class="cancel-search" @click="handleSearch">搜索</text>
                </view>
            </view>

            <!-- 配送方式筛选 -->
            <view class="delivery-filter">
                <view class="filter-options">
                    <view class="filter-item" :class="{ active: currentDeliveryType === 'all' }"
                        @click="switchDeliveryFilter('all')">
                        全部配送
                    </view>
                    <view class="filter-item" :class="{ active: currentDeliveryType === '1' }"
                        @click="switchDeliveryFilter('1')">
                        邮寄配送
                    </view>
                    <view class="filter-item" :class="{ active: currentDeliveryType === '2' }"
                        @click="switchDeliveryFilter('2')">
                        自行配送
                    </view>
                </view>
            </view>
        </view>



        <!-- 订单列表 -->
        <scroll-view scroll-y class="order-list" @scrolltolower="loadMore" :refresher-enabled="true"
            :refresher-triggered="isRefreshing" @refresherrefresh="onRefresh" :class="{ 'has-search': showSearch }">
            <view class="order-item" v-for="(item, index) in filteredOrderList" :key="index">
                <!-- 订单头部 -->
                <view class="item-index">{{ item.order_no }}</view>

                <view class="order-header" @click="goDetail(item)">
                    <view class="header-left mt-3 ">

                        <text class="time">创建时间: {{ item.create_at }}</text>
                    </view>
                    <view class="header-right">

                        <view>
                            <text class="order-status" :class="getStatusClass(item.status)"> {{
                                item.status_name || '已删除' }}</text>

                        </view>

                        <view class="delivery-type-tag">

                            <text>{{ item.delivery_type == '1' ? '邮寄' : '自送' }}</text>
                            <text v-if="item.delivery_type == '1'" class="express-id"> | {{ item.express_no ||
                                '暂无单号'
                            }}</text>
                        </view>

                    </view>
                </view>

                <!-- 订单内容 -->
                <view class="order-content" @click="goDetail(item)">
                    <!-- 设备信息 -->
                    <view class="device-info">
                        <view class="device-list" v-if='item.devices.length > 0'>
                            <view class="device-item" v-for="(device, dIndex) in showAllDevices[item.id] ? item.devices : item.devices.slice(0, 3)" :key="dIndex">
                                <view>
                                    <view class="model">{{ device.model || '待识别' }}</view>
                                    <view class="imei text-sm">IMEI: {{ device.imei }}</view>
                                </view>
                                <view class="">
                                    <text class="price" v-if="device.final_price !== '0.00'">
                                        ¥{{ device.final_price }}
                                    </text>
                                    <text class="price pending" v-if="device.status == 1">
                                        待质检
                                    </text>
                                    <text class="price checking" v-else-if="device.status == 2">
                                        质检中
                                    </text>
                                    <text class="price checked" v-else-if="device.status == 3">
                                        已质检
                                    </text>
                                    <text class="price text-[#d3483e] bg-[#f3dddb] " v-else-if="device.status == 4">
                                        待确认
                                    </text>
                                    <text class="price completed" v-else-if="device.status == 5">
                                        已确认
                                    </text>
                                    <text class="price completed" v-else-if="device.status == 6">
                                        已完成
                                    </text>
                                    <text class="price returned" v-else>
                                        已退回
                                    </text>
                                </view>
                            </view>
                            
                            <!-- 展开/折叠按钮 -->
                            <view class="expand-toggle" v-if="item.devices.length > 3" @click.stop="toggleDeviceList(item.id)">
                                <text>{{ showAllDevices[item.id] ? '收起' : `展开更多(${item.devices.length-3})` }}</text>
                                <up-icon :name="showAllDevices[item.id] ? 'arrow-up' : 'arrow-down'" size="14" color="#666"></up-icon>
                            </view>
                        </view>
                        
                    </view>
                </view>
                <!-- 个人信息 -->

                <!-- 订单底部 -->
                <view class="order-footer" v-if="item.devices.length !== 0 && item.status != '1'">
                    <!-- <view class="total-info order-footer flex "> -->
                        <text>共<text class='device-count'>{{ item.devices.length }}</text>台设备</text>
                        <text class="total-label flex ml-2 ">
                            <text class="text-xs mr-1">总价</text>                        
                            <text class="total-price">¥{{ calculateTotalPrice(item) }}</text>
                        </text>

                    <!-- </view> -->
                    <!-- <view class="action-buttons">

                        <button v-for="(btn, index) in renderActionButtons(item)" :key="index"
                            :class="['btn', btn.class]" @click.stop="updateStatus(item, btn.type)">
                            {{ btn.text }}
                        </button>
                    </view> -->
                </view>
                <view class="order-footer" v-else>
                    
                    <text class="mr-1 text-xs">共<text class='device-count'>{{ item.count }}</text>台设备</text>
                    <view class="mr-1 flex">
                        <up-icon  class="mr-1" name="info-circle" size="16" color="#FFD700" /> 
                       <text class="text-xs">最终信息以商家确认为准 </text>
                     </view>
                </view>
            </view>

            <!-- 空状态 -->
            <view class="empty-state" v-if="orderList.length === 0">
                <up-icon name="order" size="64" color="#CCCCCC"></up-icon>
                <text>暂无订单息</text>
            </view>

            <!-- 加载更多 -->

            <view class="loading-more" v-if="orderList.length > 0">
                <text v-if="hasMore">加载中...</text>
                <text v-else>没有更多了</text>
            </view>
        </scroll-view>

        <!-- tabbar -->
        <tabbar addon="recycle" />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue';
import { getOrderList, getOrderStatus, updateOrderStatus } from '@/addon/recycle/api/order';
import { redirect } from '@/utils/common';
import { onShow, onLoad } from '@dcloudio/uni-app';
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage';

// 类型定义
type OrderStatus = '1' | '2' | '3' | '4' | '5' | '6' | '7' | '8' | '9';
type ActionType = 'cancel' | 'reject' | 'confirm' | 'complete' | 'delete' | 'update_delivery' | 'update_express' | 'pay' | 'link';

interface StatusItem {
    key: string;
    text: string;
    count: number;
    actions?: Record<string, any>;
}

interface OrderDevice {
    model: string;
    imei: string;
    initial_price: string;
    final_price: string;
    status: number;
}

interface Order {
    id: number;
    order_no: string;
    status: OrderStatus;
    status_name: string;
    create_at: string;
    devices: OrderDevice[];
    delivery_type: '1' | '2';
    express_no: string;
    send_username: string;
    telphone: string;
    order_id?: number;
}

interface ActionButton {
    text: string;
    type: ActionType;
    class: string;
}

interface ApiResponse<T = any> {
    code: number;
    data: T;
    msg?: string;
}

interface OrderListResponse {
    data: Order[];
    current_page: number;
    last_page: number;
    status_count: Record<string, number>;
}

interface StatusListResponse {
    list: StatusItem[];
    status_count: Record<string, number>;
}

// 订单列表和状态计数
const orderList = ref<Order[]>([]); // 当前显示的订单列表
const statusList = ref<StatusItem[]>([]);
const currentStatus = ref('');
const page = ref(1);

const pageSize = ref(10);
const hasMore = ref(true);
const isRefreshing = ref(false);
const statusCountMap = ref<Record<string, number>>({}); // 存储状态计数

// 配送方式筛选
const currentDeliveryType = ref<'all' | '1' | '2'>('all');
const filteredOrderList = computed(() => {
    // console.log(currentDeliveryType.value);

    if (currentDeliveryType.value === 'all') {
        return orderList.value;
    }
    return orderList.value.filter(order => order.delivery_type === currentDeliveryType.value);
});

// 切换配送方式筛选
const switchDeliveryFilter = async (type: 'all' | '1' | '2') => {
    currentDeliveryType.value = type;
    await loadData();
};

// 切换状态
const switchStatus = async (status: string) => {
    currentStatus.value = status;
    page.value = 1;
    orderList.value = [];
    hasMore.value = true;
    await loadData();
};

// 加载数据
const loadData = async () => {
    try {
        const params = {
            page: page.value,
            page_size: pageSize.value,
            status: currentStatus.value || undefined,

            delivery_type: currentDeliveryType.value === 'all' ? undefined : currentDeliveryType.value,
            search: searchKeyword.value.trim() || undefined
        };
        const res = await getOrderList(params) as ApiResponse<OrderListResponse>;

        if (res.code === 1) {
            // 更新订单列表
            if (page.value === 1) {
                orderList.value = res.data.data;
            } else {
                orderList.value = [...orderList.value, ...res.data.data];
            }

            // 更新分页信息
            hasMore.value = res.data.current_page < res.data.last_page;

            // 更新状态计数（如果返回了计数信息）
            if (res.data.status_count) {
                statusCountMap.value = res.data.status_count;
            }
        }
    } catch (error) {
        console.error('获取订单列表失败:', error);
    }
};

// 加载更多
const loadMore = async () => {
    if (hasMore.value) {
        console.log(22);
        page.value++;
        await loadData();
    }
};

// 下拉刷新
const onRefresh = async () => {
    isRefreshing.value = true;
    page.value = 1;
    await loadData();
    isRefreshing.value = false;
};

// 获取状态列表和计数
const loadStatusList = async () => {
    try {
        const res = await getOrderStatus() as ApiResponse<StatusListResponse>;
        if (res.code === 1) {
            statusList.value = res.data.list;
            statusCountMap.value = res.data.status_count;

        } 
    } catch (error) {
        console.error('获取状态列表失败:', error);
    }
};



// 更新订单状态
const updateStatus = async (order: Order, action: ActionType, extraData: any = {}): Promise<ApiResponse | undefined> => {
    const actionTextMap: Record<ActionType, string> = {
        cancel: '取消',
        reject: '退回',
        confirm: '确认',
        complete: '出售',
        delete: '删除',
        update_delivery: '更新配送方式',
        update_express: '更新快递单号',
        pay: '确认打款',
        link: '跳转'
    };

    if (action === 'link') {
        redirect({ url: `/addon/recycle/pages/order/detail?id=${order.id}`, mode: 'navigateTo' });
        return;
    }

    // 如果是更新配送方式或快递单号，直接执行不需要确认
    if (action === 'update_delivery' || action === 'update_express') {
        try {
            const result = await updateOrderStatus({
                order_id: order.id,
                status: order.status,
                action,
                ...extraData
            }) as ApiResponse;

            if (result.code === 1) {
                uni.showToast({
                    title: `${actionTextMap[action]}成功`,
                    icon: 'success'
                });
                return result;
            }
        } catch (error) {
            console.error(`${actionTextMap[action]}失败:`, error);
        }
        return undefined;
    }

    // 其他操作需要确认
    return new Promise<ApiResponse | undefined>((resolve) => {
        uni.showModal({
            title: '提示',
            content: `确定要${actionTextMap[action]} \n该订单吗？`,
            success: async (res) => {
                if (res.confirm) {
                    try {
                        const result = await updateOrderStatus({
                            order_id: order.id,
                            status: order.status,
                            action,
                            ...extraData
                        }) as ApiResponse;

                        if (result.code === 1) {
                            uni.showToast({
                                title: `${actionTextMap[action]}成功`,
                                icon: 'success'
                            });
                            // 重新加载数据
                            await loadData();
                            // 重新加载状态列表
                            await loadStatusList();
                            resolve(result);

                            resolve(result);
                        } else {
                            resolve(undefined);
                        }
                    } catch (error) {
                        console.error(`${actionTextMap[action]}失败:`, error);
                        resolve(undefined);
                    }
                } else {
                    resolve(undefined);
                }
            }
        });
    });
};

// 订单操作按钮
const renderActionButtons = (order: Order): ActionButton[] => {
    const buttons: ActionButton[] = [];

    if (order.status === '1') { // 待确认
        buttons.push({
            text: '取消订单',
            type: 'cancel',
            class: 'btn-cancel'
        });
    } else if (order.status === '4') { // 已质检
        buttons.push({
            text: '待确认',
            type: 'link',
            class: 'btn-primary'
        });
    } else if (order.status === '5') { // 已打款
        buttons.push({
            text: '待确认',
            type: 'link',
            class: 'btn-primary'
        });
    } else if (order.status === '7') { // 已取消
        buttons.push({
            text: '删除订单',
            type: 'delete',
            class: 'btn-danger'
        });
    }

    return buttons;
};

// 跳转详情
const goDetail = (order: any) => {
    redirect({ url: `/addon/recycle/pages/order/detail?id=${order.id}`, mode: 'navigateTo' });
};

// 状态样式映射
const statusClassMap: Record<OrderStatus, string> = {
    '1': 'pending',      // 待确认
    '2': 'checking',     // 质检中
    '3': 'checked',      // 已质检
    '4': 'paying',       // 待打款
    '5': 'payed',        // 已打款
    '6': 'completed',    // 已完成
    '7': 'cancelled',    // 已取消
    '8': 'returning',    // 退回中
    '9': 'returned'      // 已退回
};

// 状态文本映射
const statusTextMap: Record<OrderStatus, string> = {
    '1': '待确认',      // PENDING_CONFIRM
    '2': '质检中',      // CHECKING
    '3': '已质检',      // CHECKED
    '4': '待打款',      // PAYING
    '5': '已打款',      // PAYED
    '6': '已完成',      // COMPLETED
    '7': '已取消',      // CANCELLED
    '8': '退回中',      // RETURNING
    '9': '已退回'       // RETURNED
};

// 获取状态样式类
const getStatusClass = (status: OrderStatus): string => {


    return statusClassMap[status] || 'pending';
};





// 计算订单总价
const calculateTotalPrice = (order: Order): string => {
    return order.devices.reduce((total, device) => {
        return total + (parseFloat(device.final_price) || 0);
    }, 0).toFixed(2);
};

// 取消订单
const cancelOrder = (order: Order) => {
    updateStatus(order, 'cancel');
};

// 退回订单
const rejectOrder = (order: Order) => {
    updateStatus(order, 'reject');
};

// 确认订单
const confirmOrder = (order: Order) => {
    redirect({ url: `/addon/recycle/pages/order/order?id=${order.id}`, mode: 'navigateTo' });
    // updateStatus(order, 'confirm');
};

// 确认完成
const completeOrder = (order: Order) => {
    redirect({ url: `/addon/recycle/pages/order/order?id=${order.id}`, mode: 'navigateTo' });
    // updateStatus(order, 'complete');
};

// 切换配送方式
const toggleDeliveryType = async (order: Order, type: '1' | '2') => {
    if (order.delivery_type === type) return;

    const result = await updateStatus(order, 'update_delivery', { delivery_type: type });
    if (result?.code === 1) {
        order.delivery_type = type;
        // 重新加载数据以更新状态
        await loadData();
        // 重新加载状态列表
        await loadStatusList();
    }
};

// 搜索相关
const showSearch = ref(false);
const searchKeyword = ref('');

// 切换搜索框显示状态
const toggleSearch = () => {
    showSearch.value = !showSearch.value;
};

// 清空搜索内容
const clearSearch = () => {
    searchKeyword.value = '';
    loadData();
};

// 取消搜索
const cancelSearch = () => {
    searchKeyword.value = '';
    showSearch.value = false;
    // 重置搜索结果
    loadData();
};

// 处理搜索
const handleSearch = () => {
    if (!searchKeyword.value.trim()) return;
    // 重置页码并重新加载数据
    page.value = 1;
    loadData();
};

// 控制设备列表的展开/折叠状态
const showAllDevices = reactive<Record<number, boolean>>({});

// 切换设备列表展开/折叠
const toggleDeviceList = (orderId: number) => {
    showAllDevices[orderId] = !showAllDevices[orderId];
};

// 获取URL参数并设置初始状态
onLoad((options: any) => {
    console.log('Page loaded with options:', options,options.status === 'pending_inspection');
     if (options.status === 'pending_inspection') {
        // 待质检状态
        currentStatus.value = '1';
        
    } else if (options.status === 'processing') {
        // 质检中状态
        currentStatus.value = '2';
    } else if (options.status === 'inspected') {
        // 已质检状态
        currentStatus.value = '3';
    } else if (options.status === 'pending_confirm') {
        // 待确认状态
        currentStatus.value = '5';
    }else{
      
        // 如果URL中有status参数，则设置为当前状态
        currentStatus.value = options.status;
    }
    
});

onMounted(() => {
    loadStatusList().then(() => {
        loadData(); // 在状态列表加载完成后再加载订单数据
    });
});
onShow(async () => {
    // 请求订阅相关消息通知
    await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay');
    
    // 原有逻辑
    loadStatusList();
    loadData();
});
</script>

<style lang="scss" scoped>
.page {
    background: linear-gradient(to bottom, #f8f9fa, #f1f3f5);
    min-height: 100vh;
}

.header-section {
    // 吸顶     
    position: sticky;
    top: 0;
    z-index: 100;
}



.order-list {
    flex: 1;
    background: #f5f5f5;
    overflow: hidden;
    height: calc(100vh - 300rpx);

    .order-item {
        background: #fff;
        margin-bottom: 12rpx;
        border-radius: 10px;
        margin: 10rpx;
        box-shadow: 0 2rpx 10rpx rgba(44, 43, 43, 0.05);
        position: relative;
        overflow: hidden;

        .item-index {
            position: absolute;
            top: 0;
            left: 0;
            background: #f5f7fa;
            color: #4e82c7;
            font-size: 20rpx;
            font-weight: 500;
            padding: 4rpx 8rpx;
            border-radius: 12rpx 0 12rpx 0;
            font-family: 'DIN';
            z-index: 1;
        }

        .order-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 20rpx;
            background: linear-gradient(to right, #fff, #f8f9fa);
            border-bottom: 1rpx solid #f5f5f5;

            .header-left {
                flex: 1;

                .order-no {
                    font-size: 26rpx;
                    color: #333;
                    margin-bottom: 8rpx;
                }

                .time {
                    font-size: 24rpx;
                    color: #999;
                }
            }

            .header-right {
                text-align: right;

                .order-status {
                    font-size: 26rpx;
                    font-weight: 500;
                    padding: 4rpx 16rpx;
                    border-radius: 24rpx;
                    display: inline-block;
                    margin-bottom: 8rpx;

                    &.pending {
                        background: linear-gradient(135deg, #fff8e1, #ffe0b2);
                        color: #f57c00;
                    }

                    &.checking {
                        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
                        color: #1976d2;
                    }

                    &.checked {
                        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
                        color: #71ba75cb;
                    }

                    &.completed {
                        background: linear-gradient(135deg, #e3f2fd, #90caf9);
                        color: #1565c0;
                    }

                    &.returned {
                        background: linear-gradient(135deg, #ffebee, #ffcdd2);
                        color: #d32f2f;
                    }
                }

                .delivery-type-tag {
                    font-size: 24rpx;
                    color: #666;
                    margin-bottom: 8rpx;

                    .express-id {
                        color: #999;
                    }
                }
            }
        }

        .order-content {
            padding: 20rpx;

            .device-info {
                .device-item {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 12rpx 0;
                    border-bottom: 2rpx solid #f5f6f7;

                    .model {
                        flex: 1;
                        font-size: 26rpx;
                        color: #333;
                        max-width: 450rpx;
                    }

                    .imei {
                        font-size: 20rpx;
                        color: #666;
                        margin-right: 20rpx;
                        background: #f5f6f7;
                        padding: 4rpx 12rpx;
                        border-radius: 12rpx;
                    }

                    .price {
                        font-size: 22rpx;
                        font-weight: 500;
                        padding: 4rpx 16rpx;
                        border-radius: 24rpx;

                        &.pending {
                            background: linear-gradient(135deg, #fff8e1, #ffe0b2);
                            color: #f57c00;
                        }

                        &.checking {
                            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
                            color: #1976d2;
                        }

                        &.checked {
                            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
                            color: #388e3c;
                        }

                        &.completed {
                            background: linear-gradient(135deg, #e3f2fd, #90caf9);
                            color: #1565c0;
                        }

                        &.returned {
                            background: linear-gradient(135deg, #ffebee, #ffcdd2);
                            color: #d32f2f;
                        }
                    }
                }

                .device-item:last-child {
                    border-bottom: none;
                }
            }
        }

        .order-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16rpx 20rpx;
            background: linear-gradient(to right, #fff, #f8f9fa);
            border-top: 1rpx solid #f5f5f5;

            .total-info {
                font-size: 26rpx;
                color: #666;
                justify-content: space-between;
                

                .text-lg {
                    color: #333;
                    font-weight: 500;
                }

                .total-price {
                    font-size: 30rpx;
                    font-weight: 600;
                    background: linear-gradient(135deg, #2979ff, #1565c0);
                    -webkit-background-clip: text;
                    color: transparent;
                }
            }

            .action-buttons {
                display: flex;
                gap: 12rpx;
                justify-content: flex-end;
                flex-wrap: wrap;

                .btn {
                    padding: 6rpx 20rpx;
                    font-size: 20rpx;
                    border-radius: 20rpx;
                    border: none;
                    min-width: 120rpx;
                    position: relative;
                    overflow: hidden;
                    transition: all 0.3s;

                    &::after {
                        border: none;
                    }

                    &:active {
                        transform: scale(0.95);
                        opacity: 0.9;
                    }

                    &.btn-primary {
                        background: linear-gradient(135deg, #4481eb, #04befe);
                        color: #ffffff;
                        box-shadow: 0 2px 6px rgba(68, 129, 235, 0.2);
                    }

                    &.btn-cancel {
                        background: linear-gradient(135deg, #c21535, #c21535);
                        color: #ffffff;
                        box-shadow: 0 2px 6px rgba(255, 154, 158, 0.2);
                    }

                    &.btn-danger {
                        background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
                        color: #ffffff;
                        box-shadow: 0 2px 6px rgba(255, 107, 107, 0.2);
                    }

                    &.btn-warning {
                        background: linear-gradient(135deg, #ffd86f, #ffc371);
                        color: #ffffff;
                        box-shadow: 0 2px 6px rgba(255, 216, 111, 0.2);
                    }
                }
            }
        }
    }

    .empty-state {
        padding: 120rpx 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20rpx;

        text {
            font-size: 28rpx;
            color: #999;
        }
    }

    .loading-more {
        text-align: center;
        padding: 20rpx 0;

        text {
            font-size: 24rpx;
            color: #999;
        }
    }
}

.status-nav {
    background: linear-gradient(to right, #fff, #f8f9fa);
    white-space: nowrap;
    border-bottom: 1rpx solid #f5f5f5;

    .nav-items {
        display: inline-flex;
        padding: 0 20rpx;

        .nav-item {
            position: relative;
            padding: 20rpx 32rpx;
            font-size: 28rpx;
            color: #666;

            &.active {
                color: #2979ff;
                font-weight: 500;

                &::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 40rpx;
                    height: 4rpx;
                    background: linear-gradient(135deg, #2979ff, #1565c0);
                    border-radius: 2rpx;
                }
            }

            .count {
                position: absolute;
                top: 10rpx;
                right: 4rpx;
                min-width: 28rpx;
                height: 28rpx;
                line-height: 28rpx;
                text-align: center;
                background: linear-gradient(135deg, #ff5252, #d32f2f);
                color: #fff;
                font-size: 20rpx;
                border-radius: 14rpx;
                padding: 0 6rpx;
                transform: scale(0.8);
                transform-origin: right top;
                box-shadow: 0 2rpx 6rpx rgba(255, 82, 82, 0.3);
            }
        }
    }
}

.search-section {
    background: linear-gradient(to right, #fff, #f8f9fa);
    padding: 12rpx 20rpx;
    border-bottom: 1rpx solid #f5f5f5;

    .search-bar {
        display: flex;
        align-items: center;
        gap: 12rpx;

        .search-input {
            flex: 1;
            display: flex;
            align-items: center;
            background: #f5f5f5;
            border-radius: 32rpx;
            padding: 8rpx 16rpx;
            box-shadow: inset 0 2rpx 6rpx rgba(0, 0, 0, 0.05);

            input {
                flex: 1;
                margin: 0 12rpx;
                font-size: 26rpx;
                background: transparent;
            }

            .icon-close {
                color: #999;
                padding: 8rpx;
            }
        }

        .cancel-search {
            color: #666;
            font-size: 26rpx;

        }
    }
}

.order-status {
    font-size: 26rpx;
    font-weight: 500;
    padding: 4rpx 16rpx;
    border-radius: 24rpx;
    display: inline-block;
    margin-bottom: 8rpx;

    &.pending {
        background: linear-gradient(135deg, #fff8e1, #ffe0b2);
        color: #f57c00;
    }

    &.checking {
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        color: #1976d2;
    }

    &.checked {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        color: #388e3c;
    }
    &.paying{
     background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        color: #388e3c;
    }

    &.completed {
        background: linear-gradient(135deg, #e3f2fd, #90caf9);
        color: #1565c0;
    }

    &.returned {
        background: linear-gradient(135deg, #ffebee, #ffcdd2);
        color: #d32f2f;
    }
}

.delivery-filter {
    background: linear-gradient(to right, #fff, #f8f9fa);
    padding: 12rpx 20rpx;
    border-bottom: 1rpx solid #f5f5f5;

    .filter-options {
        display: flex;
        align-items: center;
        background: #f1f3f5;
        border-radius: 12rpx;
        padding: 4rpx;
        max-width: 400rpx;

        .filter-item {
            flex: 1;
            text-align: center;
            font-size: 26rpx;
            color: #666;
            padding: 8rpx 0;
            transition: all 0.3s;
            border-radius: 8rpx;

            &.active {
                background: linear-gradient(135deg, #2979ff, #1565c0);
                color: #fff;
                box-shadow: 0 2rpx 8rpx rgba(41, 121, 255, 0.3);
            }
        }
    }
}

.device-count {
    color: #e53935;
    font-size: 32rpx;
    font-weight: 600;
    margin: 0 8rpx;
}

.total-price {
    font-size: 32rpx;
    font-weight: 600;
    color: #e53935 !important;
    background: none !important;
    -webkit-background-clip: initial !important;
}

.expand-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16rpx 0;
    font-size: 24rpx;
    color: #666;
    background-color: #f8f9fa;
    border-radius: 8rpx;
    margin-top: 12rpx;
    
    text {
        margin-right: 8rpx;
    }
    
    &:active {
        background-color: #f1f3f5;
    }
}
</style>