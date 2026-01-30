<template>
    <el-dialog v-model="showDialog" :title="dialogTitle" width="700px" :close-on-click-modal="false">
        <el-form :model="formData" label-width="120px" v-if="needPaymentInfo">
            <!-- 订单商品列表 -->
            <el-form-item label="订单商品">
                <el-table :data="orderGoods" border style="width: 100%">
                    <el-table-column label="商品信息" min-width="200">
                        <template #default="{ row }">
                            <div>{{ row.goods_name }}</div>
                            <div style="color: #999; font-size: 12px;">{{ row.sku_name }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="原价" width="100">
                        <template #default="{ row }">
                            ¥{{ row.original_price }}
                        </template>
                    </el-table-column>
                    <el-table-column label="单价" width="120">
                        <template #default="{ row }">
                            <el-input-number
                                v-model="row.price"
                                :precision="2"
                                :min="0"
                                :max="999999"
                                size="small"
                                :disabled="row.is_deleted"
                                @change="calculateTotal"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column label="数量" width="80">
                        <template #default="{ row }">
                            <span :style="{ textDecoration: row.is_deleted ? 'line-through' : 'none' }">
                                {{ row.num }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="小计" width="100">
                        <template #default="{ row }">
                            <span :style="{ textDecoration: row.is_deleted ? 'line-through' : 'none', color: row.is_deleted ? '#999' : '' }">
                                ¥{{ (row.price * row.num).toFixed(2) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="80" fixed="right">
                        <template #default="{ row, $index }">
                            <el-button
                                v-if="!row.is_deleted"
                                type="danger"
                                link
                                size="small"
                                @click="removeOrderGoods($index)"
                            >
                                删除
                            </el-button>
                            <el-button
                                v-else
                                type="primary"
                                link
                                size="small"
                                @click="restoreOrderGoods($index)"
                            >
                                恢复
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-form-item>

            <!-- 订单总金额 -->
            <el-form-item label="订单总金额">
                <span style="font-size: 18px; font-weight: bold; color: #f56c6c;">
                    ¥{{ formData.order_money }}
                </span>
                <span style="color: #999; font-size: 12px; margin-left: 10px;">
                    （原金额：¥{{ currentOrder?.order_money || 0 }}）
                </span>
            </el-form-item>

            <!-- 收款账户 -->
            <el-form-item label="收款账户" required>
                <el-radio-group v-model="formData.offline_pay_account" placeholder="请选择收款账户">
                    <el-radio
                        v-for="account in offlineAccounts"
                        :key="account.name"
                        :label="account.name"
                    >
                        <span>{{ account.name }}</span>
                        <span style="color: #999; margin-left: 8px;">({{ getAccountTypeText(account.type) }})</span>
                    </el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>
        <div v-else>
            <p>{{ confirmMessage }}</p>
        </div>
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" @click="handleConfirm" :loading="loading">确认</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, computed } from 'vue'
import { confirmHoldOrderPayment, getOrderDetail } from '@/addon/phone_shop/api/order'
import { getPayConfigList } from '@/app/api/sys'
import { ElMessage } from 'element-plus'

const showDialog = ref(false)
const loading = ref(false)
const currentOrder = ref<any>(null)

// 线下收款账户列表
const offlineAccounts = ref<any[]>([])

// 选中的账户对象
const selectedAccount = ref<any>(null)

// 订单商品列表
const orderGoods = ref<any[]>([])

const formData = ref({
    order_money: '',
    pay_type: '',
    offline_pay_account: ''
})

const emit = defineEmits(['complete'])

// 根据订单状态判断是否需要支付信息
const needPaymentInfo = computed(() => {
    // 支持字符串和数字类型的状态值
    return currentOrder.value?.status == 10 // HOLD状态需要输入支付信息
})

const dialogTitle = computed(() => {
    if (!currentOrder.value) return ''
    const status = Number(currentOrder.value.status)
    switch (status) {
        case 10: return '确认收款'
        case 2: return '确认发货'
        case 3: return '确认收货'
        default: return '确认操作'
    }
})

const confirmMessage = computed(() => {
    if (!currentOrder.value) return ''
    const status = Number(currentOrder.value.status)
    switch (status) {
        case 2: return '确认该订单已发货？'
        case 3: return '确认该订单已收货？订单将标记为完成。'
        default: return '确认执行此操作？'
    }
})

/**
 * 加载线下收款账户 - 从PC渠道的hsx_offlinepay配置中获取
 */
const loadOfflineAccounts = async () => {
    try {
        const res = await getPayConfigList()
        // 从PC渠道获取hsx_offlinepay配置
        const pcChannel = res.data?.pc
        if (pcChannel && pcChannel.pay_type) {
            const hsxOfflinePay = pcChannel.pay_type.find((item: any) => item.key === 'hsx_offlinepay')
            if (hsxOfflinePay && hsxOfflinePay.config && hsxOfflinePay.config.accounts) {
                offlineAccounts.value = hsxOfflinePay.config.accounts
            }
        }
    } catch (error) {
        console.error('加载收款账户失败:', error)
    }
}

/**
 * 获取账户类型文本
 */
const getAccountTypeText = (type: string) => {
    const typeMap: Record<string, string> = {
        wechat: '微信',
        alipay: '支付宝',
        bank: '银行卡'
    }
    return typeMap[type] || type
}

/**
 * 将账户类型映射为支付类型
 */
const mapAccountTypeToPayType = (accountType: string) => {
    const typeMap: Record<string, string> = {
        wechat: 'wechatpay',
        alipay: 'alipay',
        bank: 'bank_transfer'
    }
    return typeMap[accountType] || accountType
}

/**
 * 计算订单总金额
 */
const calculateTotal = () => {
    const total = orderGoods.value.reduce((sum, item) => {
        // 只计算未删除的商品
        if (!item.is_deleted) {
            return sum + (item.price * item.num)
        }
        return sum
    }, 0)
    formData.value.order_money = total.toFixed(2)
}

/**
 * 删除订单商品（标记为删除，不参与计算）
 */
const removeOrderGoods = (index: number) => {
    orderGoods.value[index].is_deleted = true
    calculateTotal()
}

/**
 * 恢复订单商品
 */
const restoreOrderGoods = (index: number) => {
    orderGoods.value[index].is_deleted = false
    calculateTotal()
}

const show = async (order: any) => {
    currentOrder.value = order
    formData.value = {
        order_money: order.order_money || '',
        pay_type: '',
        offline_pay_account: ''
    }
    selectedAccount.value = null
    orderGoods.value = []

    // 加载收款账户列表
    loadOfflineAccounts()

    // 如果是挂单状态，加载订单商品详情
    if (order.status == 10) {
        try {
            const res = await getOrderDetail(order.order_id)
            if (res.data && res.data.order_goods) {
                // 初始化订单商品列表，保存原价和当前价格
                orderGoods.value = res.data.order_goods.map((item: any) => ({
                    ...item,
                    original_price: item.price, // 保存原价
                    price: item.price, // 当前可编辑的价格
                    is_deleted: item.is_deleted || false // 初始化删除状态
                }))
                // 计算初始总金额
                calculateTotal()
            }
        } catch (error) {
            console.error('加载订单详情失败:', error)
        }
    }

    showDialog.value = true
}

const handleConfirm = async () => {
    // 如果是挂单状态，需要验证支付信息
    if (needPaymentInfo.value) {
        if (!formData.value.order_money || parseFloat(formData.value.order_money) <= 0) {
            ElMessage.warning('请输入有效的订单金额')
            return
        }
        if (!formData.value.offline_pay_account) {
            ElMessage.warning('请选择收款账户')
            return
        }

        // 根据选中的账户名称找到对应的账户对象，获取账户类型
        const selectedAccountObj = offlineAccounts.value.find(
            account => account.name === formData.value.offline_pay_account
        )

        if (!selectedAccountObj) {
            ElMessage.warning('收款账户信息无效')
            return
        }

        // 将账户类型映射为支付类型
        formData.value.pay_type = mapAccountTypeToPayType(selectedAccountObj.type)
    }

    loading.value = true
    try {
        // 准备订单商品价格数据
        const orderGoodsData = orderGoods.value.map(item => ({
            order_goods_id: item.order_goods_id,
            price: item.price,
            goods_money: (item.price * item.num).toFixed(2),
            is_deleted: item.is_deleted ? 1 : 0  // 发送删除状态
        }))

        await confirmHoldOrderPayment({
            order_id: currentOrder.value.order_id,
            order_money: formData.value.order_money,
            pay_type: formData.value.pay_type,
            offline_pay_account: formData.value.offline_pay_account,
            order_goods: orderGoodsData // 发送更新后的商品价格
        })

        showDialog.value = false
        emit('complete')
    } catch (error) {
        console.error('确认失败:', error)
    } finally {
        loading.value = false
    }
}

defineExpose({
    show
})
</script>

<style scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>
