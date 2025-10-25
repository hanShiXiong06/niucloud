<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateRecycleDeviceModel') : t('addRecycleDeviceModel')" width="50%" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
                <el-form-item :label="t('brandId')" prop="brand_id">
                    <el-input v-model="formData.brand_id" clearable :placeholder="t('brandIdPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('modelName')" prop="model_name">
                    <el-input v-model="formData.model_name" clearable :placeholder="t('modelNamePlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('networkModel')" >
                    <el-input v-model="formData.network_model" clearable :placeholder="t('networkModelPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('capacity')" >
                    <el-input v-model="formData.capacity" clearable :placeholder="t('capacityPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('deviceType')" >
                    <el-input v-model="formData.device_type" clearable :placeholder="t('deviceTypePlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('status')" prop="status">
                    <el-input v-model="formData.status" clearable :placeholder="t('statusPlaceholder')" class="input-width" />
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
import { ref, reactive, computed, watch } from 'vue'
import { useDictionary } from '@/app/api/dict'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { addRecycleDeviceModel, editRecycleDeviceModel, getRecycleDeviceModelInfo } from '@/addon/recycle/api/recycle_device_model'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    brand_id: '',
    model_name: '',
    network_model: '',
    capacity: '',
    device_type: '',
    status: '',
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
    brand_id: [
        { required: true, message: t('brandIdPlaceholder'), trigger: 'blur' },
        
    ]
,
    model_name: [
        { required: true, message: t('modelNamePlaceholder'), trigger: 'blur' },
        
    ]
,
    network_model: [
        { required: true, message: t('networkModelPlaceholder'), trigger: 'blur' },
        
    ]
,
    capacity: [
        { required: true, message: t('capacityPlaceholder'), trigger: 'blur' },
        
    ]
,
    device_type: [
        { required: true, message: t('deviceTypePlaceholder'), trigger: 'blur' },
        
    ]
,
    status: [
        { required: true, message: t('statusPlaceholder'), trigger: 'blur' },
        
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
    let save = formData.id ? editRecycleDeviceModel : addRecycleDeviceModel

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true

            let data = formData

            save(data).then(res => {
                loading.value = false
                showDialog.value = false
                emit('complete')
            }).catch(err => {
                loading.value = false
            })
        }
    })
}

// 获取字典数据
    

    
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if(row){
        const data = await (await getRecycleDeviceModelInfo(row.id)).data
        if (data) Object.keys(formData).forEach((key: string) => {
            if (data[key] != undefined) formData[key] = data[key]
        })
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
.diy-dialog-wrap .el-form-item__label{
    height: auto  !important;
}
</style>
