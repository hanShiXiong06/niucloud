<template>
    <el-dialog v-model="showDialog" title="线下销售" width="980px" :destroy-on-close="true">
        <el-form :model="formData" label-width="100px" ref="formRef" :rules="formRules" v-loading="loading">

            <!-- 选择会员 -->
            <el-form-item label="选择用户" required>
                <MemberSelector v-model="formData.member_id" ref="memberSelectorRef" @change="handleMemberChange" />
            </el-form-item>

            <!-- 商品列表 -->
            <el-form-item label="商品列表" required>
                <el-table :data="formData.goods_list" border style="width: 100%">
                    <el-table-column label="商品信息" min-width="200">
                        <template #default="{ row }">
                            <div class="flex items-center">
                               
                                <div class="ml-2">
                                    <div>{{ row.goods_name }}</div>
                                    <div class="text-sm text-gray-500">{{ row.sku_name }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="SN" min-width="100">
                        <template #default="{ row }">
                            <div class="text-sm text-gray-500">{{ row.sku_no }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="原价" width="100">
                        <template #default="{ row }">
                            ¥{{ row.original_price }}
                        </template>
                    </el-table-column>
                    <el-table-column label="售价" width="150">
                        <template #default="{ row }">
                            <el-input-number
                                v-model="row.sale_price"
                                :precision="2"
                                :min="0"
                                :max="999999"
                                size="small"
                                @change="calculateTotal"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column label="数量" width="150">
                        <template #default="{ row }">
                            <el-input-number
                                v-model="row.num"
                                :min="1"
                                :max="row.stock"
                                size="small"
                                @change="calculateTotal"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column label="小计" width="100">
                        <template #default="{ row }">
                            ¥{{ (row.sale_price * row.num).toFixed(2) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="80" fixed="right">
                        <template #default="{ $index }">
                            <el-button
                                type="danger"
                                link
                                size="small"
                                @click="removeGoods($index)"
                                :disabled="formData.goods_list.length <= 1"
                            >
                                删除
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-button class="mt-2" type="primary" size="small" @click="showGoodsSelector = true">
                    + 添加商品
                </el-button>
            </el-form-item>

            <!-- 订单金额 -->
            <el-form-item label="订单金额">
                <span class="text-lg font-bold text-red-500">¥{{ totalAmount }}</span>
            </el-form-item>

            <!-- 支付状态 -->
            <el-form-item label="支付状态" prop="pay_status" required>
                <el-radio-group v-model="formData.pay_status">
                    <el-radio label="paid">已付款</el-radio>
                    <el-radio label="unpaid">未付款</el-radio>
                    <el-radio label="hold">挂单</el-radio>
                </el-radio-group>
            </el-form-item>

            <!-- 收款账户(仅已付款时显示) -->
            <el-form-item
                v-if="formData.pay_status === 'paid'"
                label="收款账户"
                prop="offline_pay_account"
                required
            >
                <el-radio-group v-model="formData.offline_pay_account" placeholder="请选择收款账户">
                    <el-radio
                        v-for="account in offlineAccounts"
                        :key="account.name"
                        :label="`${account.name} (${getAccountTypeText(account.type)})`"
                        :value="account.name"
                    >
                        <span>{{ account.name }}</span>
                        <span style="color: #999; margin-left: 8px">({{ getAccountTypeText(account.type) }})</span>
                    </el-radio>
                </el-radio-group>
            </el-form-item>

            <!-- 配送方式 -->
            <el-form-item label="配送方式" prop="delivery_type" required>
                <el-radio-group v-model="formData.delivery_type">
                    <el-radio label="store">到店自提</el-radio>
                    <el-radio label="express">物流配送</el-radio>
                </el-radio-group>
            </el-form-item>

            <!-- 收货人信息 -->
            <el-form-item label="收货人姓名" prop="taker_name">
                <el-input
                    v-model="formData.taker_name"
                    placeholder="请输入收货人姓名"
                    clearable
                />
            </el-form-item>

            <el-form-item label="收货人电话" prop="taker_mobile">
                <el-input
                    v-model="formData.taker_mobile"
                    placeholder="请输入收货人电话"
                    clearable
                />
            </el-form-item>

            <!-- 备注 -->
            <el-form-item label="备注">
                <el-input
                    v-model="formData.remark"
                    type="textarea"
                    rows="3"
                    placeholder="订单备注"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                    确认下单
                </el-button>
            </span>
        </template>

        <!-- 商品选择器弹窗 -->
        <goods-selector-dialog
            v-model="showGoodsSelector"
            @confirm="handleGoodsSelected"
        />
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { getMemberInfo } from '@/app/api/member'
import { getPayConfigList } from '@/app/api/sys'
import { createOfflineOrder } from '@/addon/phone_shop/api/offline_order'
import { img } from '@/utils/common'
import GoodsSelectorDialog from './goods-selector-dialog.vue'

import MemberSelector from './member-select.vue'

const emit = defineEmits(['success'])

const showDialog = ref(false)
const loading = ref(false)
const submitLoading = ref(false)
const memberLoading = ref(false)
const showGoodsSelector = ref(false)

// 会员选项
const memberOptions = ref<any[]>([])

// 线下收款账户列表
const offlineAccounts = ref<any[]>([])

// 表单数据
const formData = reactive({
    member_id: null,
    goods_list: [] as any[],
    pay_status: 'paid',
    offline_pay_account:'微信',
    delivery_type: 'store',  // 默认到店自提
    taker_name: '',
    taker_mobile: '',
    remark: ''
})

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules: FormRules = {
    member_id: [{ required: true, message: '请选择会员', trigger: 'change' }],
    pay_status: [{ required: true, message: '请选择支付状态', trigger: 'change' }],
    offline_pay_account: [{ required: true, message: '请选择收款账户', trigger: 'change' }],
    delivery_type: [{ required: true, message: '请选择配送方式', trigger: 'change' }]
}

// 计算订单总金额
const totalAmount = computed(() => {
    return formData.goods_list.reduce((sum, item) => {
        return sum + (item.sale_price * item.num)
    }, 0).toFixed(2)
})

/**
 * 监听会员选择变化，自动填充收货人信息和计算会员价格
 */
watch(() => formData.member_id, async (newMemberId) => {
    if (newMemberId) {
        try {
            const res = await getMemberInfo(newMemberId)
            if (res.data) {
                // 自动填充会员的默认信息
                formData.taker_name = res.data.nickname || res.data.username || ''
                formData.taker_mobile = res.data.mobile || ''

                // 根据会员等级设置商品价格
                const memberLevel = res.data.member_level || null
                updateGoodsPriceByMemberLevel(memberLevel)
            }
        } catch (error) {
            console.error('获取会员信息失败:', error)
        }
    }
})

/**
 * 处理会员变化
 */
const handleMemberChange = () => {
    // watch会自动处理
}

/**
 * 根据会员等级更新商品价格
 */
const updateGoodsPriceByMemberLevel = (memberLevel: string | null) => {
    formData.goods_list.forEach(item => {
        if (memberLevel) {
            // 有会员等级：使用会员价格（market_price）
            item.sale_price = item.market_price || item.original_price
        } else {
            // 没有会员等级：使用零售价（price）
            item.sale_price = item.original_price
        }
    })

    // 重新计算总金额
    calculateTotal()
}

/**
 * 显示弹窗
 */
const show = (goodsData: any) => {
    showDialog.value = true
    loading.value = true

    // 初始化商品列表
    formData.goods_list = [{
        goods_id: goodsData.goods_id,
        sku_id: goodsData.goodsSku.sku_id,
        goods_name: goodsData.goods_name,
        sku_name: goodsData.sub_title,
        sn: goodsData.goodsSku.sn,
        goods_cover: goodsData.goods_cover,
        original_price: goodsData.goodsSku.price,
        market_price: goodsData.goodsSku.market_price || goodsData.goodsSku.price,  // 会员价格
        sale_price: goodsData.goodsSku.price,
        num: 1,
        stock: goodsData.goodsSku.stock,
        sku_no: goodsData.goodsSku.sku_no
    }]

    // 加载线下收款账户
    loadOfflineAccounts()

    loading.value = false
}

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
 * 计算总金额
 */
const calculateTotal = () => {
    // 触发computed重新计算
}

/**
 * 删除商品
 */
const removeGoods = (index: number) => {
    if (formData.goods_list.length <= 1) {
        ElMessage.warning('至少保留一个商品')
        return
    }
    formData.goods_list.splice(index, 1)
}

/**
 * 处理商品选择
 */
const handleGoodsSelected = async (goods: any) => {
    // 检查是否已添加
    const exists = formData.goods_list.some(item => item.sku_id === goods.sku_id)
    if (exists) {
        ElMessage.warning('该商品已添加')
        return
    }

    formData.goods_list.push({
        goods_id: goods.goods_id,
        sku_id: goods.sku_id,
        goods_name: goods.goods_name,
        sku_name: goods.sku_name,
        goods_cover: goods.goods_cover,
        original_price: goods.price,
        market_price: goods.market_price || goods.price,  // 会员价格
        sale_price: goods.price,
        num: 1,
        stock: goods.stock,
        sku_no: goods.sku_no || ''
    })

    // 如果已经选择了会员，根据会员等级设置价格
    if (formData.member_id) {
        try {
            const res = await getMemberInfo(formData.member_id)
            if (res.data && res.data.member_level) {
                updateGoodsPriceByMemberLevel(res.data.member_level)
            }
        } catch (error) {
            console.error('获取会员信息失败:', error)
        }
    }
}

/**
 * 提交表单
 */
const handleSubmit = async () => {
    if (!formRef.value) return

    await formRef.value.validate(async (valid) => {
        if (!valid) return

        // 验证商品列表
        if (formData.goods_list.length === 0) {
            ElMessage.error('请至少添加一个商品')
            return
        }

        // 验证已付款时必须选择收款账户
        if (formData.pay_status === 'paid' && !formData.offline_pay_account) {
            ElMessage.error('请选择收款账户')
            return
        }

        submitLoading.value = true

        try {
            const res = await createOfflineOrder(formData)
            ElMessage.success('下单成功')
            showDialog.value = false
            emit('success', res.data)
        } catch (error: any) {
            ElMessage.error(error.message || '下单失败')
        } finally {
            submitLoading.value = false
        }
    })
}

defineExpose({
    show
})
</script>

<style lang="scss" scoped>
.flex {
    display: flex;
}
.items-center {
    align-items: center;
}
.ml-2 {
    margin-left: 8px;
}
.mt-2 {
    margin-top: 8px;
}
.text-sm {
    font-size: 14px;
}
.text-lg {
    font-size: 18px;
}
.text-gray-500 {
    color: #6b7280;
}
.text-red-500 {
    color: #ef4444;
}
.font-bold {
    font-weight: bold;
}
</style>
