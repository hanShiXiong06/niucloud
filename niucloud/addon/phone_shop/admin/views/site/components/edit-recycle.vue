<template>
    <el-dialog v-model="showDialog" :title="formData.id ? '编辑回收商信息' : '添加回收商信息'" width="50%">
        <el-tabs v-model="activeTab">
            <!-- 基本信息表单 -->
            <el-tab-pane label="基本信息" name="basic">
                <el-form :model="formData" label-width="120px" ref="basicFormRef" :rules="basicFormRules"
                    class="page-form">
                    <el-form-item label="联系人" prop="contact_name">
                        <el-input v-model="formData.contact_name" placeholder="请输入联系人姓名" />
                    </el-form-item>
                    <el-form-item label="联系电话" prop="contact_mobile">
                        <el-input v-model="formData.contact_mobile" placeholder="请输入联系电话" />
                    </el-form-item>
                    <el-form-item label="所在地区" prop="area">
                        <el-input v-model="formData.area" placeholder="请输入地区，格式：河北-石家庄" />
                        <div class="text-gray-400 text-sm mt-1">提示：请按照"省份-城市"的格式填写，例如：河北-石家庄</div>
                    </el-form-item>
                    <el-form-item label="详细地址" prop="address">
                        <el-input v-model="formData.address" type="textarea" placeholder="请输入详细地址" />
                    </el-form-item>
                    <el-form-item label="经营品类" prop="category">
                        <el-input v-model="formData.category" placeholder="请输入经营品类，格式：1,2,3" />
                    </el-form-item>
                    <el-form-item label="状态" prop="status">
                        <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <div class="flex justify-center mt-4">
                        <el-button @click="showDialog = false">取消</el-button>
                        <el-button type="primary" @click="submitBasicForm">保存基本信息</el-button>
                    </div>
                </el-form>
            </el-tab-pane>

            <!-- 价格配置表单 -->
            <el-tab-pane label="价格配置" name="price" :disabled="!formData.id">
                <el-form :model="priceForm" label-width="120px" ref="priceFormRef" :rules="priceFormRules"
                    class="page-form">
                    <el-form-item label="加价类型" prop="price_type">
                        <el-radio-group v-model="priceForm.price_type">
                            <el-radio :label="1">统一加价</el-radio>
                            <el-radio :label="2">区间加价</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <!-- 统一加价 -->
                    <template v-if="priceForm.price_type === 1">
                        <el-form-item label="加价金额" prop="member_markup">
                            <el-input-number v-model="priceForm.member_markup" :min="0" :max="1000" 
                                />
                            <span class="ml-2">元</span>
                        </el-form-item>
                    </template>

                    <!-- 区间加价 -->
                    <template v-if="priceForm.price_type === 2">
                        <div v-for="(range, index) in priceForm.price_ranges" :key="index" class="price-range-item">
                            <el-form-item :label="'价格区间' + (index + 1)">
                                <el-input-number v-model="range.min_price" :min="0" placeholder="最小价格" />
                                <span class="mx-2">至</span>
                                <el-input-number v-model="range.max_price" :min="0" placeholder="最大价格" />
                                <span class="mx-2">加价比例</span>
                                <el-input-number v-model="range.member_markup" :min="0" :max="1000" 
                                    :step="1" />
                                <span class="ml-2">元</span>
                                <el-button type="danger" link @click="removePriceRange(index)"
                                    v-if="priceForm.price_ranges.length > 1">
                                    删除
                                </el-button>
                            </el-form-item>
                        </div>
                        <el-button type="primary" @click="addPriceRange">添加价格区间</el-button>
                    </template>
                    <div class="flex justify-center mt-4">
                        <el-button @click="showDialog = false">取消</el-button>
                        <el-button type="primary" @click="submitPriceForm">保存价格配置</el-button>
                    </div>
                </el-form>
            </el-tab-pane>
        </el-tabs>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'
import { addRecycler, updateRecycler, addRecyclerPriceConfig, updateRecyclerPriceConfig, getRecyclerPriceConfig } from '@/addon/phone_shop/api/site'

const showDialog = ref(false)
const activeTab = ref('basic')
const basicFormRef = ref<FormInstance>()
const priceFormRef = ref<FormInstance>()

// 基本信息表单数据
const formData = reactive({
    id: '',
    contact_name: '',
    contact_mobile: '',
    area: '',
    address: '',
    category: '',
    status: 1
})

// 价格配置表单数据
const priceForm = reactive({
    id: '',
    recycler_id: '',
    price_type: 1,
    member_markup: 0,
    non_member_markup: 0,
    price_ranges: [{
        min_price: 0,
        max_price: 0,
        member_markup: 0
    }]
})

// 基本信息验证规则
const basicFormRules = {
    contact_name: [{ required: true, message: '请输入联系人姓名', trigger: 'blur' }],
    contact_mobile: [
        { required: true, message: '请输入联系电话', trigger: 'blur' },
        { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号码', trigger: 'blur' }
    ],
    area: [
        { required: true, message: '请输入所在地区', trigger: 'blur' },
        { pattern: /^[\u4e00-\u9fa5]+-[\u4e00-\u9fa5]+$/, message: '请按照"省份-城市"的格式填写，例如：河北-石家庄', trigger: 'blur' }
    ],
    address: [{ required: true, message: '请输入详细地址', trigger: 'blur' }],
    category: [{ required: true, message: '请选择经营品类', trigger: 'blur' }]
}

// 价格配置验证规则
const priceFormRules = {
    price_type: [{ required: true, message: '请选择加价类型', trigger: 'change' }],
    member_markup: [{ required: true, message: '请输入会员加价比例', trigger: 'blur' }],
    non_member_markup: [{ required: true, message: '请输入非会员加价比例', trigger: 'blur' }]
}

// 添加价格区间
const addPriceRange = () => {
    priceForm.price_ranges.push({
        min_price: 0,
        max_price: 0,
        member_markup: 0
    })
}

// 删除价格区间
const removePriceRange = (index: number) => {
    priceForm.price_ranges.splice(index, 1)
}

// 提交基本信息表单
const submitBasicForm = async () => {
    if (!basicFormRef.value) return

    await basicFormRef.value.validate(async (valid) => {
        if (valid) {
            try {
                const params = { ...formData }
                if (formData.id) {
                    await updateRecycler(params)
                } else {
                    const res = await addRecycler(params)
                    formData.id = res.data
                }
                ElMessage.success('保存基本信息成功')
                emit('success')
                // 如果是新增，保存成功后切换到价格配置标签
                if (!params.id) {
                    activeTab.value = 'price'
                }
            } catch (error) {
                console.error('保存基本信息失败:', error)
            }
        }
    })
}

// 提交价格配置表单
const submitPriceForm = async () => {
    if (!priceFormRef.value) return

    await priceFormRef.value.validate(async (valid) => {
        if (valid) {
            try {
                const params = {
                    ...priceForm,
                    recycler_id: formData.id
                }

                if (priceForm.id) {
                    await updateRecyclerPriceConfig(params)
                } else {
                    await addRecyclerPriceConfig(params)
                }
              
                emit('success')
                showDialog.value = false
            } catch (error) {
                console.error('保存价格配置失败:', error)
            }
        }
    })
}

// 设置表单数据
const setFormData = async (data: any = null) => {
    
    if (data) {
        // 设置基本信息
        Object.assign(formData, data)

        // 获取价格配置
        try {
            const res = await getRecyclerPriceConfig(data.id)
            console.log(res)
            if (res.data) {
                priceForm.id = res.data.id
                priceForm.price_type = res.data.price_type
                priceForm.member_markup = res.data.member_markup
                priceForm.non_member_markup = res.data.non_member_markup
                if (res.data.price_ranges) {
                    priceForm.price_ranges = res.data.price_ranges
                }
            }
        } catch (error) {
            console.error('获取价格配置失败:', error)
        }
    } else {
        // 重置基本信息
        Object.assign(formData, {
            id: '',
            contact_name: '',
            contact_mobile: '',
            area: '',
            address: '',
            category: '',
            status: 1
        })

        // 重置价格配置
        Object.assign(priceForm, {
            id: '',
            recycler_id: '',
            price_type: 1,
            member_markup: 0,
            non_member_markup: 0,
            price_ranges: [{
                min_price: 0,
                max_price: 0,
                member_markup: 0
            }]
        })
    }
}

defineExpose({
    showDialog,
    setFormData
})

const emit = defineEmits(['success'])
</script>

<style scoped>
.price-range-item {
    margin-bottom: 10px;
    padding: 10px;
    border: 1px dashed #dcdfe6;
    border-radius: 4px;
}
</style>