<template>
    <el-dialog v-model="dialogVisible" title="订单详情" width="800" class="diy-dialog-wrap" :destroy-on-close="true">
        <div v-if="orderData" class="order-detail">
            <!-- 订单基本信息 -->
               <!-- 会员信息 -->
          
            <el-descriptions title="会员信息" :column="2" border v-if="orderData.member">
                <el-descriptions-item label="会员ID">{{ orderData.member.member_id }}</el-descriptions-item>
                <el-descriptions-item label="用户名">{{ orderData.member.username || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="昵称">{{ orderData.member.nickname || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="手机号">{{ orderData.member.mobile || '暂无' }}</el-descriptions-item>
            </el-descriptions>
            <el-divider />
            <el-descriptions title="订单信息" :column="2" border>
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
                <el-descriptions-item label="备注" :span="2">{{ orderData.remark || '暂无备注' }}</el-descriptions-item>
            </el-descriptions>

            <!-- 设备列表 -->
            <el-divider />
            <h3>设备清单</h3>
            <el-table :data="orderData.devices" style="width: 100%" border stripe>
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
        </div>
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="dialogVisible = false">关闭</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, computed } from 'vue'

// 定义接口
interface OrderDetail {
    id: number | string;
    status: number;
    status_name: string;
    customer_name?: string;
    customer_phone?: string;
    pay_type?: string;
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
</style>