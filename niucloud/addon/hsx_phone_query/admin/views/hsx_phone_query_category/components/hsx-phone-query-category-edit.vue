<template>
    <el-dialog v-model="showDialog"
        :title="formData.id ? t('updateHsxPhoneQueryCategory') : t('addHsxPhoneQueryCategory')" width="50%"
        class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form"
            v-loading="loading">
            <el-form-item label="查询渠道" prop="channel_key">
                <el-select class="input-width" v-model="formData.channel_key" placeholder="请选择查询渠道" @change="handleChannelChange">
                    <el-option v-for="item in channelList" :key="item.value" :label="item.name" :value="item.value" />
                </el-select>
            </el-form-item>

            <el-form-item :label="t('typeId')" prop="type_id">
                <el-select class="input-width" v-model="formData.type_id" clearable
                    :placeholder="t('typeIdPlaceholder')">
                    <el-option label="请选择" value=""></el-option>
                    <el-option v-for="(item, index) in type_idList" :key="index" :label="item.name"
                        :value="item.value" />
                </el-select>
            </el-form-item>

            <el-form-item :label="t('name')" prop="name">
                <el-input v-model="formData.name" clearable :placeholder="t('namePlaceholder')" class="input-width" />
            </el-form-item>
            
            <el-form-item label="服务编码" prop="service_code">
                <el-input v-model="formData.service_code" clearable placeholder="如 apple_coverage" class="input-width" />
            </el-form-item>
            
            <el-form-item label="查询参数" prop="query_param">
                <el-select class="input-width" v-model="formData.query_param" clearable placeholder="请选择接口查询参数">
                    <el-option label="SN / 序列号" value="sn" />
                    <el-option label="IMEI" value="imei" />
                    <el-option label="爱查 code" value="code" />
                    <el-option label="手机号 phone" value="phone" />
                    <el-option label="条码 barcode" value="barcode" />
                </el-select>
            </el-form-item>

            <el-form-item :label="t('price')" prop="price">
                <el-input v-model="formData.price" clearable :placeholder="t('pricePlaceholder')" class="input-width" />
            </el-form-item>
            
            <el-form-item label="成本价" prop="cost_price">
                <el-input v-model="formData.cost_price" clearable placeholder="第三方接口成本，可为空" class="input-width" />
            </el-form-item>

        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)">{{
                    t('confirm')
                }}</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { t } from '@/lang'
import { ElMessage, type FormInstance } from 'element-plus'
import { addHsxPhoneQueryCategory, editHsxPhoneQueryCategory, getHsxPhoneQueryCategoryInfo } from '@/addon/hsx_phone_query/api/hsx_phone_query_category'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    
    id: '',
    channel_key: '3023_main',
    channel_name: '3023Data',
    type_id: 1,
    service_code: '',
    query_param: 'sn',
    name: '',
    price: '',
    cost_price: '',
    is_show: 1,
    sort: 0
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        type_id: [
            { required: true, message: t('typeIdPlaceholder'), trigger: 'blur' },

        ]
        ,
        channel_key: [
            { required: true, message: '请选择查询渠道', trigger: 'change' },
        ],
        name: [
            { required: true, message: t('namePlaceholder'), trigger: 'blur' },

        ]
        ,
        price: [
            { required: true, message: t('pricePlaceholder'), trigger: 'blur' },

        ]

    }
})

const emit = defineEmits(['complete'])

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    let save = formData.id ? editHsxPhoneQueryCategory : addHsxPhoneQueryCategory

    await formEl.validate(async (valid) => {
        if (!valid) return

        loading.value = true
        const data = { ...formData }
        save(data).then(() => {
            ElMessage.success(formData.id ? '查询项目已保存' : '查询项目已添加')
            showDialog.value = false
            emit('complete')
        }).catch((err) => {
            ElMessage.error(err?.message || err?.msg || '保存失败，请检查填写内容后重试')
        }).finally(() => {
            loading.value = false
        })
    })
}

const type_idList = ref([
    { name: '苹果查询', value: 1 },
    { name: '安卓查询', value: 2 },
    { name: '其他查询', value: 9 }
])

const channelList = ref([
    { name: '3023Data', value: '3023_main', query_param: 'sn' },
    { name: '爱查助手', value: 'gkdt_main', query_param: 'code' }
])

const handleChannelChange = (value: string) => {
    const channel = channelList.value.find(item => item.value === value)
    formData.channel_name = channel?.name || ''
    formData.query_param = channel?.query_param || 'sn'
}


const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    try {
        if (row) {
            const data = await (await getHsxPhoneQueryCategoryInfo(row.id)).data
            if (data) Object.keys(formData).forEach((key: string) => {
                if (data[key] != undefined) formData[key] = data[key]
            })
        } else {
            handleChannelChange(formData.channel_key)
        }
    } catch (err: any) {
        ElMessage.error(err?.message || err?.msg || '查询项目详情加载失败')
    }
    loading.value = false
}

// 验证手机号格式
const mobileVerify = (rule: any, value: any, callback: any) => {
    if (value && !/^1[3-9]\d{9}$/.test(value)) {
        callback(new Error(t('generateMobile')))
    } else {
        callback()
    }
}

// 验证身份证号
const idCardVerify = (rule: any, value: any, callback: any) => {
    if (value && !/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/.test(value)) {
        callback(new Error(t('generateIdCard')))
    } else {
        callback()
    }
}

// 验证邮箱号
const emailVerify = (rule: any, value: any, callback: any) => {
    if (value && !/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/.test(value)) {
        callback(new Error(t('generateEmail')))
    } else {
        callback()
    }
}

// 验证请输入整数
const numberVerify = (rule: any, value: any, callback: any) => {
    if (!Number.isInteger(value)) {
        callback(new Error(t('generateNumber')))
    } else {
        callback()
    }
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped></style>
<style lang="scss">
.diy-dialog-wrap .el-form-item__label {
    height: auto !important;
}
</style>
