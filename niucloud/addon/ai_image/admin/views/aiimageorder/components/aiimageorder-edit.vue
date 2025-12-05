<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateAiimageOrder') : t('addAiimageOrder')" width="50%" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
                <el-form-item :label="t('siteId')" >
                    <el-input v-model="formData.site_id" clearable :placeholder="t('siteIdPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('memberId')" >
                    <el-select class="input-width" v-model="formData.member_id" clearable :placeholder="t('memberIdPlaceholder')">
                       <el-option label="请选择" value=""></el-option>
                        <el-option
                            v-for="(item, index) in memberIdList"
                            :key="index"
                            :label="item['nickname']"
                            :value="item['member_id']"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item :label="t('packageId')" >
                    <el-select class="input-width" v-model="formData.package_id" clearable :placeholder="t('packageIdPlaceholder')">
                       <el-option label="请选择" value=""></el-option>
                        <el-option
                            v-for="(item, index) in packageIdList"
                            :key="index"
                            :label="item['name']"
                            :value="item['id']"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item :label="t('orderId')" >
                    <el-input v-model="formData.order_id" clearable :placeholder="t('orderIdPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('name')" >
                    <el-input v-model="formData.name" clearable :placeholder="t('namePlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('image')">
                    <upload-image v-model="formData.image" />
                </el-form-item>
                
                <el-form-item :label="t('orderMoney')" >
                    <el-input v-model="formData.order_money" clearable :placeholder="t('orderMoneyPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('point')" >
                    <el-input v-model="formData.point" clearable :placeholder="t('pointPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('num')" >
                    <el-input v-model="formData.num" clearable :placeholder="t('numPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('type')" >
                    <el-input v-model="formData.type" clearable :placeholder="t('typePlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('day')" >
                    <el-input v-model="formData.day" clearable :placeholder="t('dayPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('status')" >
                    <el-input v-model="formData.status" clearable :placeholder="t('statusPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('outTradeNo')" >
                    <el-input v-model="formData.out_trade_no" clearable :placeholder="t('outTradeNoPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('payTime')" >
                    <el-input v-model="formData.pay_time" clearable :placeholder="t('payTimePlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('pid')" >
                    <el-input v-model="formData.pid" clearable :placeholder="t('pidPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('closeTime')" >
                    <el-input v-model="formData.close_time" clearable :placeholder="t('closeTimePlaceholder')" class="input-width" />
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
import { addAiimageOrder, editAiimageOrder, getAiimageOrderInfo, getWithMemberList, getWithAiimagePackageList } from '@/addon/ai_image/api/aiimageorder'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    member_id: '',
    package_id: '',
    order_id: '',
    name: '',
    image: '',
    order_money: '',
    point: '',
    num: '',
    type: '',
    day: '',
    status: '',
    out_trade_no: '',
    pay_time: '',
    pid: '',
    close_time: '',
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
    member_id: [
        { required: true, message: t('memberIdPlaceholder'), trigger: 'blur' },
        
    ]
,
    package_id: [
        { required: true, message: t('packageIdPlaceholder'), trigger: 'blur' },
        
    ]
,
    order_id: [
        { required: true, message: t('orderIdPlaceholder'), trigger: 'blur' },
        
    ]
,
    name: [
        { required: true, message: t('namePlaceholder'), trigger: 'blur' },
        
    ]
,
    image: [
        { required: true, message: t('imagePlaceholder'), trigger: 'blur' },
        
    ]
,
    order_money: [
        { required: true, message: t('orderMoneyPlaceholder'), trigger: 'blur' },
        
    ]
,
    point: [
        { required: true, message: t('pointPlaceholder'), trigger: 'blur' },
        
    ]
,
    num: [
        { required: true, message: t('numPlaceholder'), trigger: 'blur' },
        
    ]
,
    type: [
        { required: true, message: t('typePlaceholder'), trigger: 'blur' },
        
    ]
,
    day: [
        { required: true, message: t('dayPlaceholder'), trigger: 'blur' },
        
    ]
,
    status: [
        { required: true, message: t('statusPlaceholder'), trigger: 'blur' },
        
    ]
,
    out_trade_no: [
        { required: true, message: t('outTradeNoPlaceholder'), trigger: 'blur' },
        
    ]
,
    pay_time: [
        { required: true, message: t('payTimePlaceholder'), trigger: 'blur' },
        
    ]
,
    pid: [
        { required: true, message: t('pidPlaceholder'), trigger: 'blur' },
        
    ]
,
    close_time: [
        { required: true, message: t('closeTimePlaceholder'), trigger: 'blur' },
        
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
    let save = formData.id ? editAiimageOrder : addAiimageOrder

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
    

    
    const memberIdList = ref([] as any[])
    const setMemberIdList = async () => {
    memberIdList.value = await (await getWithMemberList({})).data
    }
    setMemberIdList()
    const packageIdList = ref([] as any[])
    const setPackageIdList = async () => {
    packageIdList.value = await (await getWithAiimagePackageList({})).data
    }
    setPackageIdList()
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if(row){
        const data = await (await getAiimageOrderInfo(row.id)).data
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
