<template>
    <el-dialog v-model="showDialog" title="线下销售" width="600px" :before-close="handleClose">
        <el-form :model="formData" :rules="formRules" ref="formRef" label-width="100px">
            <el-form-item label="商品信息">
                <div class="goods-info">
                    <el-image
                        v-if="goodsInfo.goods_cover"
                        :src="img(goodsInfo.goods_cover)"
                        class="goods-image"
                        fit="cover"
                    />
                    <div class="goods-detail">
                        <div class="goods-name">{{ goodsInfo.goods_name }}</div>
                        <div class="goods-sku text-gray-500">SKU: {{ goodsInfo.sku_no || '-' }}</div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="当前库存">
                <el-tag :type="goodsInfo.stock > 0 ? 'success' : 'danger'">
                    {{ goodsInfo.stock }}
                </el-tag>
            </el-form-item>

            <el-form-item label="商品价格">
                <span class="text-primary text-lg">¥{{ goodsInfo.price }}</span>
            </el-form-item>

            <el-form-item label="选择会员" prop="member_id" required>
                <el-select
                    v-model="formData.member_id"
                    filterable
                    remote
                    reserve-keyword
                    placeholder="请输入会员昵称/手机号搜索"
                    :remote-method="searchMemberFn"
                    :loading="memberLoading"
                    style="width: 100%"
                >
                    <el-option
                        v-for="item in memberOptions"
                        :key="item.member_id"
                        :label="`${item.nickname || item.username} (${item.mobile})`"
                        :value="item.member_id"
                    >
                        <div class="flex items-center justify-between">
                            <span>{{ item.nickname || item.username }}</span>
                            <span class="text-gray-400 text-sm">{{ item.mobile }}</span>
                        </div>
                    </el-option>
                </el-select>
            </el-form-item>

            <el-form-item label="销售数量" prop="num" required>
                <el-input-number
                    v-model="formData.num"
                    :min="1"
                    :max="goodsInfo.stock"
                    :precision="0"
                    :controls="true"
                />
                <span class="ml-2 text-gray-500">库存: {{ goodsInfo.stock }}</span>
            </el-form-item>

            <el-form-item label="销售单价" prop="sale_price">
                <el-input-number
                    v-model="formData.sale_price"
                    :min="0"
                    :precision="2"
                    :controls="true"
                    placeholder="留空则使用商品价格"
                />
                <span class="ml-2 text-gray-500">原价: ¥{{ goodsInfo.price }}</span>
            </el-form-item>

            <el-form-item label="总金额">
                <span class="text-primary text-xl font-bold">
                    ¥{{ totalMoney.toFixed(2) }}
                </span>
            </el-form-item>

            <el-form-item label="备注">
                <el-input
                    v-model="formData.remark"
                    type="textarea"
                    :rows="3"
                    maxlength="200"
                    show-word-limit
                    placeholder="请输入备注信息"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="handleClose">取消</el-button>
            <el-button type="primary" @click="handleSubmit" :loading="submitLoading">
                确认销售
            </el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { createOfflineSale } from '@/addon/phone_shop/api/sale'
import { getMemberList } from '@/app/api/member'
import { img } from '@/utils/common'

const emit = defineEmits(['success'])

// 弹窗显示控制
const showDialog = ref(false)
const submitLoading = ref(false)
const memberLoading = ref(false)

// 商品信息
const goodsInfo = ref<any>({})

// 表单数据
const formData = reactive({
    goods_id: 0,
    sku_id: 0,
    member_id: 0,
    num: 1,
    sale_price: 0,
    remark: ''
})

// 表单验证规则
const formRules: FormRules = {
    member_id: [
        { required: true, message: '请选择会员', trigger: 'change' }
    ],
    num: [
        { required: true, message: '请输入销售数量', trigger: 'blur' },
        { type: 'number', min: 1, message: '数量必须大于0', trigger: 'blur' }
    ]
}

const formRef = ref<FormInstance>()

// 会员选项
const memberOptions = ref<any[]>([])

// 计算总金额
const totalMoney = computed(() => {
    const price = formData.sale_price || goodsInfo.value.price || 0
    return price * formData.num
})

// 监听销售数量变化，校验库存
watch(() => formData.num, (newVal) => {
    if (newVal > goodsInfo.value.stock) {
        ElMessage.warning('销售数量不能超过库存')
        formData.num = goodsInfo.value.stock
    }
})

/**
 * 显示弹窗
 */
const show = (data: any) => {
    goodsInfo.value = data
    formData.goods_id = data.goods_id
    formData.sku_id = data.goodsSku?.sku_id || data.sku_id
    formData.num = 1
    formData.sale_price = data.goodsSku?.price || data.price || 0
    formData.member_id = 0
    formData.remark = ''
    memberOptions.value = []

    showDialog.value = true
}

/**
 * 搜索会员
 */
const searchMemberFn = (query: string) => {
    if (query) {
        memberLoading.value = true
        getMemberList({
            keyword: query,
            page: 1,
            limit: 20
        }).then((res: any) => {
            memberOptions.value = res.data.list || []
        }).finally(() => {
            memberLoading.value = false
        })
    } else {
        memberOptions.value = []
    }
}

/**
 * 提交表单
 */
const handleSubmit = () => {
    formRef.value?.validate((valid) => {
        if (valid) {
            // 再次确认
            ElMessageBox.confirm(
                `确认将商品 "${goodsInfo.value.goods_name}" 销售给选中会员吗？`,
                '确认销售',
                {
                    confirmButtonText: '确认',
                    cancelButtonText: '取消',
                    type: 'warning'
                }
            ).then(() => {
                submitLoading.value = true
                createOfflineSale(formData)
                    .then((res: any) => {
                        ElMessage.success('销售成功')
                        showDialog.value = false
                        emit('success')
                    })
                    .finally(() => {
                        submitLoading.value = false
                    })
            })
        }
    })
}

/**
 * 关闭弹窗
 */
const handleClose = () => {
    formRef.value?.resetFields()
    showDialog.value = false
}

// 导出方法供父组件调用
defineExpose({
    show
})
</script>

<style scoped lang="scss">
.goods-info {
    display: flex;
    align-items: center;
    gap: 12px;

    .goods-image {
        width: 80px;
        height: 80px;
        border-radius: 4px;
        flex-shrink: 0;
    }

    .goods-detail {
        flex: 1;

        .goods-name {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .goods-sku {
            font-size: 12px;
        }
    }
}
</style>
