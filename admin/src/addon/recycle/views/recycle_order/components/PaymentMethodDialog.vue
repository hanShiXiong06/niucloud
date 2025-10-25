<template>
    <el-dialog v-model="dialogVisible" title="收款方式" width="650px" :destroy-on-close="true">
        <div v-if="paymentInfoData && paymentInfoData.length > 0">
            <!-- 订单摘要信息卡片 -->
            <el-card v-if="currentPaymentInfo && currentPaymentInfo.order_summary" shadow="never" class="mb-4">
                <template #header>
                    <div class="card-header">
                        <span>订单 #{{ currentPaymentInfo.order_summary.order_id }}</span>
                        <el-tag :type="currentPaymentInfo.order_summary.status === 7 ? 'success' : 'warning'"
                            size="small">
                            ¥{{ currentPaymentInfo.order_summary.total_amount }}
                        </el-tag>
                    </div>
                </template>

                <!-- 主要订单信息 -->
                <!-- <div class="order-info-grid">
                  
                    <div class="info-item">
                        <div class="info-label">客户:</div>
                        <div class="info-value">{{ currentPaymentInfo.order_summary.customer_name || '暂无' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">手机:</div>
                        <div class="info-value">{{ currentPaymentInfo.order_summary.customer_mobile || '暂无' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">配送:</div>
                        <div class="info-value">{{ currentPaymentInfo.order_summary.delivery_type_name || '暂无' }}</div>
                    </div>
                </div> -->

                
                <!-- <div class="stats-container">
                    <div class="stat-box">
                        <span class="stat-value success">{{ currentPaymentInfo.order_summary.success_count || 0
                        }}</span>
                        <span class="stat-label">已回收</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value warning">{{ currentPaymentInfo.order_summary.confirmed_count || 0
                        }}</span>
                        <span class="stat-label">已确认</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value info">{{ currentPaymentInfo.order_summary.checked_count || 0 }}</span>
                        <span class="stat-label">已质检</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value danger">{{ currentPaymentInfo.order_summary.returned_count || 0
                        }}</span>
                        <span class="stat-label">已退回</span>
                    </div>
                </div>

                
                <div class="progress-section">
                    <div class="progress-label">
                        <span>设备处理情况</span>
                        <span>{{ currentPaymentInfo.order_summary.device_count || 0 }} 台设备</span>
                    </div>
                    <el-progress :percentage="deviceProgressPercentage" :color="progressColor" :format="() => ''"
                        :stroke-width="10" />
                </div> -->

                <!-- 设备详情按钮 -->
                <el-collapse>
                    <el-collapse-item title="设备详情列表" name="devices">
                        <el-table :data="currentPaymentInfo.order_summary.devices" size="small" border>
                            <el-table-column prop="model" label="型号" min-width="120"></el-table-column>
                            <el-table-column prop="imei" label="IMEI" min-width="120"
                                show-overflow-tooltip></el-table-column>
                            <el-table-column prop="final_price" label="价格" width="80">
                                <template #default="scope">
                                    <span class="price">¥{{ scope.row.final_price }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="status_name" label="状态" width="80">
                                <template #default="scope">
                                    <el-tag size="small" :type="scope.row.status === 6 ? 'danger' : 'success'">
                                        {{ scope.row.status_name }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-collapse-item>
                </el-collapse>
            </el-card>

            <!-- 支付方式选择 -->
            <el-card shadow="never" class="payment-method-card">
                <!-- <template #header> -->
                    <template >
                    <div class="card-header">
                        <span>选择收款方式</span>
                    </div>
                </template>

                <el-radio-group v-model="selectedPayTypeIndex" class="payment-radio-group">
                    <el-radio-button v-for="(item, index) in paymentInfoData" :key="index" :label="index">
                        {{ item.pay_type }}
                    </el-radio-button>
                </el-radio-group>

                <!-- 收款信息 -->
                <div v-if="currentPaymentInfo" class="payment-info">
                    <div class="account-info">
                        <el-text type="info">收款账号：</el-text>
                        <el-text type="primary">{{ currentPaymentInfo.account }}</el-text>
                    </div>

                    <div class="qrcode-container">
                        <div v-if="currentPaymentInfo.qrcode_image">
                            <el-image class="qrcode" :src="currentPaymentInfo.qrcode_image" fit="contain" />
                        </div>
                        <div v-else class="no-qrcode">
                            <el-icon>
                                <Picture />
                            </el-icon>
                            <span>暂无收款码</span>
                        </div>
                    </div>
                </div>
            </el-card>
        </div>
        <div v-else class="empty-state">
            <el-empty description="该商户暂无收款方式信息" />
        </div>

        <!-- 对话框底部按钮 -->
        <template #footer>
            <div class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleConfirmPayment"
                    :disabled="!currentPaymentInfo || !currentPaymentInfo.order_summary">
                    确认已打款
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Picture } from '@element-plus/icons-vue'

// 定义支付信息接口
interface PaymentInfoItem {
    pay_type: string;
    account: string;
    qrcode_image?: string;
    order_summary?: {
        order_id: number | string;
        status?: number;
        delivery_type_name?: string;
        device_count?: number;
        customer_name?: string;
        customer_mobile?: string;
        total_amount?: number | string;
        checked_count?: number;
        confirmed_count?: number;
        success_count?: number;
        returned_count?: number;
        devices?: Array<{
            model: string;
            imei: string;
            final_price: number | string;
            status: number;
            status_name: string;
        }>;
    };
    [key: string]: any;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    paymentInfo: {
        type: Array as () => PaymentInfoItem[],
        default: () => []
    }
})

const emit = defineEmits(['update:visible', 'payment-confirmed'])

// 内部状态
const dialogVisible = ref(props.visible)
const paymentInfoData = ref<PaymentInfoItem[]>(props.paymentInfo)
const selectedPayTypeIndex = ref(0)

// 计算当前选择的支付方式
const currentPaymentInfo = computed(() => {
    if (!paymentInfoData.value || paymentInfoData.value.length === 0 || selectedPayTypeIndex.value < 0) {
        return null
    }
    return paymentInfoData.value[selectedPayTypeIndex.value]
})

// 计算设备处理进度百分比
const deviceProgressPercentage = computed(() => {
    if (!currentPaymentInfo.value || !currentPaymentInfo.value.order_summary) return 0

    const { device_count = 0, success_count = 0, returned_count = 0 } = currentPaymentInfo.value.order_summary
    if (device_count === 0) return 0

    // 已回收 + 已退回的设备占总数的百分比
    return Math.round(((success_count + returned_count) / device_count) * 100)
})

// 计算进度条颜色
const progressColor = computed(() => {
    if (!currentPaymentInfo.value || !currentPaymentInfo.value.order_summary) return '#409eff'

    const { device_count = 0, success_count = 0, returned_count = 0 } = currentPaymentInfo.value.order_summary

    // 计算成功率
    const successRate = device_count > 0 ? (success_count / device_count) : 0

    // 根据成功率设置颜色
    if (successRate > 0.8) return '#67c23a' // 绿色，主要是成功的
    if (successRate > 0.5) return '#e6a23c' // 黄色，成功和退回参半
    return '#f56c6c' // 红色，主要是退回的
})

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
})

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
})

// 监听paymentInfo变化
watch(() => props.paymentInfo, (newVal) => {
    paymentInfoData.value = newVal
    // 默认选中第一个支付方式
    selectedPayTypeIndex.value = newVal.length > 0 ? 0 : -1
})

// 确认打款
const handleConfirmPayment = () => {
    if (!currentPaymentInfo.value || !currentPaymentInfo.value.order_summary) {
        ElMessage.error('无法获取订单信息')
        return
    }

    // 发送确认事件，并传递当前支付方式信息
    emit('payment-confirmed', {
        orderId: currentPaymentInfo.value.order_summary.order_id,
        payType: currentPaymentInfo.value.pay_type
    })
}
</script>

<style lang="scss" scoped>
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
}

.stats-container {
    display: flex;
    justify-content: space-between;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.stat-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 12px;
    background-color: #f5f7fa;
    border-radius: 4px;
    min-width: 70px;
}

.stat-value {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 4px;

    &.success {
        color: #67c23a;
    }

    &.warning {
        color: #e6a23c;
    }

    &.info {
        color: #409eff;
    }

    &.danger {
        color: #f56c6c;
    }
}

.stat-label {
    font-size: 12px;
    color: #606266;
}

.progress-section {
    margin-bottom: 16px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #606266;
    margin-bottom: 4px;
}

.payment-method-card {
    margin-top: 16px;
}

.payment-radio-group {
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.payment-info {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.account-info {
    margin-bottom: 16px;
    text-align: center;
}

.qrcode-container {
    display: flex;
    justify-content: center;
}

.qrcode {
    width: 180px;
    height: 180px;
    border: 1px solid #ebeef5;
    border-radius: 4px;
}

.no-qrcode {
    width: 180px;
    height: 180px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: #f5f7fa;
    border-radius: 4px;
    color: #909399;

    .el-icon {
        font-size: 32px;
        margin-bottom: 8px;
    }
}

.empty-state {
    padding: 32px 0;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
}

.price {
    color: #ff6b00;
    font-weight: 500;
}

.mb-4 {
    margin-bottom: 16px;
}
</style>