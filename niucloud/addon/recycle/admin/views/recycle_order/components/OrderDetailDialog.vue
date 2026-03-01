<template>
    <el-dialog
        v-model="dialogVisible"
        title="订单详情"
        :width="isMobile ? '95vw' : '800px'"
        top="4vh"
        class="diy-dialog-wrap order-detail-dialog"
        :destroy-on-close="true"
    >
        <div v-if="orderData" class="order-detail">
            <!-- 订单基本信息 -->
               <!-- 会员信息 -->

            <el-descriptions title="会员信息" :column="isMobile ? 1 : 2" border v-if="orderData.member">
                <el-descriptions-item label="会员ID">{{ orderData.member.member_id }}</el-descriptions-item>
                <el-descriptions-item label="用户名">{{ orderData.member.username || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="昵称">{{ orderData.member.nickname || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="手机号">{{ orderData.member.mobile || '暂无' }}</el-descriptions-item>
            </el-descriptions>
            <el-divider />
            <el-descriptions title="订单信息" :column="isMobile ? 1 : 2" border>
                <el-descriptions-item label="订单编号">{{ orderData.id || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="订单状态">
                    <el-tag :type="orderData.status === 7 ? 'success' : 'info'">{{ orderData.status_name }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="打款方式">{{ orderData.pay_type || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="总金额">
                    <span style="color: #ff6b00; font-weight: bold;">¥{{ totalAmount }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="配送方式">{{ orderData.delivery_type_name || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="快递公司">{{ orderData.express_company || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="快递单号">{{ orderData.express_no || '暂无' }}</el-descriptions-item>

                <el-descriptions-item label="设备数量">{{ deviceCount }}</el-descriptions-item>

                <el-descriptions-item label="创建时间">{{ orderData.create_at || '暂无' }}</el-descriptions-item>

                <el-descriptions-item label="打款时间">
                    {{ orderData.pay_time ? new Date(orderData.pay_time * 1000).toLocaleString() : '暂无' }}
                </el-descriptions-item>
                <el-descriptions-item label="收款账号">{{ orderData.pay_account || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="备注" :span="2">{{ orderData.remark || '暂无备注' }}</el-descriptions-item>
            </el-descriptions>

            <!-- 打款凭证图片 -->
            <template v-if="paymentImageList.length > 0">
                <el-divider />
                <h3>📸 打款凭证</h3>
                <div class="payment-images">
                    <el-image
                        v-for="(img, index) in paymentImageList"
                        :key="index"
                        :src="img"
                        fit="cover"
                        class="payment-image"
                        @click="handlePreview(index)"
                    />
                </div>
                <!-- 单独的图片预览器 -->
                <el-image-viewer
                    v-if="showImageViewer"
                    :url-list="paymentImageList"
                    :initial-index="previewIndex"
                    @close="showImageViewer = false"
                    teleported
                />
            </template>

            <!-- 设备列表 -->
            <el-divider />
            <h3>设备清单</h3>
            <el-table v-if="!isMobile" :data="orderData.devices" style="width: 100%" border stripe>
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="imei" label="IMEI" min-width="120" />
                <el-table-column prop="model" label="型号" min-width="120" />

                <el-table-column prop="final_price" label="最终价格" width="100">
                    <template #default="scope">
                        <span style="color: #ff6b00; font-weight: bold;">¥{{ scope.row.final_price }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status_name" label="状态" width="120">
                    <template #default="scope">
                        <el-tag :type="scope.row.status === 6 ? 'danger' : 'success'">{{ scope.row.status_name
                            }}</el-tag>
                    </template>
                </el-table-column>
            </el-table>
            <div v-else class="mt-2 space-y-2">
                <div
                    v-for="device in orderData.devices || []"
                    :key="device.id"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-3"
                >
                    <div class="mb-1 flex items-start justify-between gap-2">
                        <div class="text-sm font-semibold text-gray-800">{{ device.model || '未知型号' }}</div>
                        <el-tag size="small" :type="device.status === 6 ? 'danger' : 'success'">
                            {{ device.status_name }}
                        </el-tag>
                    </div>
                    <div class="text-xs text-gray-500 break-all">IMEI：{{ device.imei || '暂无' }}</div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-xs text-gray-500">设备ID：{{ device.id }}</span>
                        <span class="text-sm font-semibold text-orange-500">¥{{ device.final_price }}</span>
                    </div>
                </div>
            </div>

            <!-- 退货信息 -->
            <template v-if="returnOrderList.length > 0">
                <el-divider />
                <h3>退货信息</h3>
                <div v-for="returnOrder in returnOrderList" :key="returnOrder.id" class="return-order-card">
                    <el-descriptions :column="isMobile ? 1 : 2" border size="small">
                        <el-descriptions-item label="退货单号">{{ returnOrder.order_no }}</el-descriptions-item>
                        <el-descriptions-item label="退货状态">
                            <el-tag size="small" :type="returnOrderStatusType(returnOrder.status)">{{ returnOrder.status_name }}</el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="快递公司">{{ returnOrder.express_company || '暂无' }}</el-descriptions-item>
                        <el-descriptions-item label="快递单号">{{ returnOrder.express_no || '暂无' }}</el-descriptions-item>
                        <el-descriptions-item label="退货地址" :span="2">{{ returnOrder.return_address || '暂无' }}</el-descriptions-item>
                        <el-descriptions-item label="备注" :span="2">{{ returnOrder.remark || returnOrder.comment || '暂无' }}</el-descriptions-item>
                        <el-descriptions-item label="创建时间">{{ returnOrder.create_at || '暂无' }}</el-descriptions-item>
                        <el-descriptions-item label="完成时间">{{ returnOrder.over_at || '暂无' }}</el-descriptions-item>
                    </el-descriptions>
                    <!-- 退货设备列表 -->
                    <div v-if="returnOrder.return_devices && returnOrder.return_devices.length > 0" class="mt-2">
                        <div class="text-sm font-semibold text-gray-700 mb-1">退货设备</div>
                        <el-table v-if="!isMobile" :data="returnOrder.return_devices" size="small" border stripe>
                            <el-table-column label="IMEI" min-width="120">
                                <template #default="scope">{{ scope.row.device?.imei || '暂无' }}</template>
                            </el-table-column>
                            <el-table-column label="型号" min-width="100">
                                <template #default="scope">{{ scope.row.device?.model || '暂无' }}</template>
                            </el-table-column>
                            <el-table-column label="状态" width="100">
                                <template #default="scope">
                                    <el-tag size="small">{{ scope.row.status_name || '暂无' }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-else class="space-y-1">
                            <div
                                v-for="rd in returnOrder.return_devices"
                                :key="rd.id"
                                class="rounded border border-gray-200 bg-gray-50 p-2 text-xs"
                            >
                                <div>IMEI：{{ rd.device?.imei || '暂无' }} | 型号：{{ rd.device?.model || '暂无' }}</div>
                                <div class="mt-1">
                                    <el-tag size="small">{{ rd.status_name || '暂无' }}</el-tag>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <template #footer>
            <div :class="isMobile ? 'dialog-footer mobile-footer' : 'dialog-footer'">
                <el-button :class="isMobile ? '!ml-0 w-full' : ''" @click="dialogVisible = false">关闭</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { ElImageViewer } from 'element-plus'
import { getReturnOrderList } from '@/addon/recycle/api/recycle_return_order'

// 定义接口
interface OrderDetail {
    id: number | string;
    status: number;
    status_name: string;
    customer_name?: string;
    customer_phone?: string;
    pay_type?: string;
    pay_account?: string;
    payment_images?: string;
    total_amount?: number | string;
    delivery_type_name?: string;
    express_company?: string;
    express_no?: string;
    device_count?: number;
    pay_time?: number;
    create_at?: string;
    update_at?: string;
    remark?: string;
    member?: {
        member_id: number | string;
        username?: string;
        nickname?: string;
        mobile?: string;
    };
    devices?: Array<{
        id: number | string;
        imei: string;
        model: string;
        initial_price: number | string;
        final_price: number | string;
        status: number;
        status_name: string;
    }>;
    [key: string]: any;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    orderDetail: {
        type: Object as () => OrderDetail | null,
        default: null
    }
})

const emit = defineEmits(['update:visible'])

// 内部状态
const dialogVisible = ref(props.visible)
const orderData = ref<OrderDetail | null>(props.orderDetail)
const isMobile = ref(false)
const returnOrderList = ref<any[]>([])

const updateResponsiveState = () => {
    isMobile.value = window.innerWidth <= 768
}

// 计算设备总数量
const deviceCount = computed(() => {
    if (!orderData.value || !orderData.value.devices) return 0
    return orderData.value.devices.length
})

// 计算设备总金额
const totalAmount = computed(() => {
    if (!orderData.value || !orderData.value.devices) return 0
    // 累加所有设备的final_price
    return orderData.value.devices.reduce((sum, device) => {
        const price = parseFloat(device.final_price as string) || 0
        return sum + price
    }, 0).toFixed(2)
})

// 计算打款凭证图片列表
const paymentImageList = computed(() => {
    if (!orderData.value || !orderData.value.payment_images) return []
    // payment_images 是逗号分隔的字符串
    return orderData.value.payment_images.split(',').filter(img => img.trim())
})

// 图片预览状态
const showImageViewer = ref(false)
const previewIndex = ref(0)

// 点击图片预览
const handlePreview = (index: number) => {
    previewIndex.value = index
    showImageViewer.value = true
}

// 退货订单状态标签类型
const returnOrderStatusType = (status: number) => {
    const map: Record<number, string> = {
        0: 'warning',
        1: '',
        2: 'success',
        3: 'info'
    }
    return map[status] || 'info'
}

// 加载退货订单数据
const loadReturnOrders = async (orderId: number | string) => {
    try {
        const res = await getReturnOrderList({ order_id: orderId })
        const data = res.data || []
        returnOrderList.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        returnOrderList.value = []
    }
}

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
})

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
})

// 监听orderDetail变化
watch(() => props.orderDetail, (newVal) => {
    orderData.value = newVal
    returnOrderList.value = []
    if (newVal && newVal.id) {
        loadReturnOrders(newVal.id)
    }
})

onMounted(() => {
    updateResponsiveState()
    window.addEventListener('resize', updateResponsiveState)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateResponsiveState)
})
</script>

<style lang="scss" scoped>
.order-detail {
    padding: 10px;

    h3 {
        margin: 15px 0;
        font-size: 16px;
        font-weight: bold;
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
}

.mobile-footer {
    width: 100%;
}

.payment-images {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 0;

    .payment-image {
        width: 120px;
        height: 120px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        cursor: pointer;
        transition: transform 0.2s;

        &:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    }
}

.return-order-card {
    margin-bottom: 16px;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fafafa;
}

@media (max-width: 768px) {
    .order-detail {
        padding: 6px;
    }

    .payment-images {
        gap: 8px;

        .payment-image {
            width: 92px;
            height: 92px;
        }
    }

    .return-order-card {
        padding: 8px;
        margin-bottom: 10px;
    }
}
</style>
