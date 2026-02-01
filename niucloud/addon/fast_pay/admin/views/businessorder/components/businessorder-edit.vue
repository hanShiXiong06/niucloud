<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateBusinessOrder') : t('addBusinessOrder')" width="50%" class="diy-dialog-wrap"
        :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
                <el-form-item :label="t('siteId')" prop="site_id">
                    <el-input v-model="formData.site_id" clearable :placeholder="t('siteIdPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('memberId')" prop="member_id">
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
                
                <el-form-item :label="t('businessId')" >
                    <el-select class="input-width" v-model="formData.business_id" clearable :placeholder="t('businessIdPlaceholder')">
                       <el-option label="请选择" value=""></el-option>
                        <el-option
                            v-for="(item, index) in businessIdList"
                            :key="index"
                            :label="item['name']"
                            :value="item['id']"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item :label="t('orderFrom')" prop="order_from">
                    <el-input v-model="formData.order_from" clearable :placeholder="t('orderFromPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('orderId')" prop="order_id">
                    <el-input v-model="formData.order_id" clearable :placeholder="t('orderIdPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('orderMoney')" prop="order_money">
                    <el-input v-model="formData.order_money" clearable :placeholder="t('orderMoneyPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('orderDiscountMoney')" prop="order_discount_money">
                    <el-input v-model="formData.order_discount_money" clearable :placeholder="t('orderDiscountMoneyPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('orderStatus')" prop="order_status">
                    <el-input v-model="formData.order_status" clearable :placeholder="t('orderStatusPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('refundStatus')" >
                    <el-input v-model="formData.refund_status" clearable :placeholder="t('refundStatusPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('outTradeNo')" >
                    <el-input v-model="formData.out_trade_no" clearable :placeholder="t('outTradeNoPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('remark')" >
                    <el-input v-model="formData.remark" clearable :placeholder="t('remarkPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('payTime')"  class="input-width">
                    <el-date-picker 
                        class="flex-1 !flex"
                        v-model="formData.pay_time"
                        clearable
                        type="datetime"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        :placeholder="t('payTimePlaceholder')">
                    </el-date-picker>
                </el-form-item>
                <el-form-item :label="t('closeReason')" >
                    <el-input v-model="formData.close_reason" clearable :placeholder="t('closeReasonPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('isEnableRefund')" >
                    <el-input v-model="formData.is_enable_refund" clearable :placeholder="t('isEnableRefundPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('closeTime')" >
                    <el-input v-model="formData.close_time" clearable :placeholder="t('closeTimePlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('ip')" >
                    <el-input v-model="formData.ip" clearable :placeholder="t('ipPlaceholder')" class="input-width" />
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
import { addBusinessOrder, editBusinessOrder, getBusinessOrderInfo, getWithMemberList, getWithBusinessList } from '@/addon/fast_pay/api/businessorder'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    member_id: '',
    business_id: '',
    order_from: '',
    order_id: '',
    order_money: '',
    order_discount_money: '',
    order_status: '',
    refund_status: '',
    out_trade_no: '',
    remark: '',
    pay_time: '',
    close_reason: '',
    is_enable_refund: '',
    close_time: '',
    ip: '',
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
    business_id: [
        { required: true, message: t('businessIdPlaceholder'), trigger: 'blur' },
        
    ]
,
    order_from: [
        { required: true, message: t('orderFromPlaceholder'), trigger: 'blur' },
        
    ]
,
    order_id: [
        { required: true, message: t('orderIdPlaceholder'), trigger: 'blur' },
        
    ]
,
    order_money: [
        { required: true, message: t('orderMoneyPlaceholder'), trigger: 'blur' },
        
    ]
,
    order_discount_money: [
        { required: true, message: t('orderDiscountMoneyPlaceholder'), trigger: 'blur' },
        
    ]
,
    order_status: [
        { required: true, message: t('orderStatusPlaceholder'), trigger: 'blur' },
        
    ]
,
    refund_status: [
        { required: true, message: t('refundStatusPlaceholder'), trigger: 'blur' },
        
    ]
,
    out_trade_no: [
        { required: true, message: t('outTradeNoPlaceholder'), trigger: 'blur' },
        
    ]
,
    remark: [
        { required: true, message: t('remarkPlaceholder'), trigger: 'blur' },
        
    ]
,
    pay_time: [
        { required: true, message: t('payTimePlaceholder'), trigger: 'blur' },
        
    ]
,
    close_reason: [
        { required: true, message: t('closeReasonPlaceholder'), trigger: 'blur' },
        
    ]
,
    is_enable_refund: [
        { required: true, message: t('isEnableRefundPlaceholder'), trigger: 'blur' },
        
    ]
,
    close_time: [
        { required: true, message: t('closeTimePlaceholder'), trigger: 'blur' },
        
    ]
,
    ip: [
        { required: true, message: t('ipPlaceholder'), trigger: 'blur' },
        
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
    let save = formData.id ? editBusinessOrder : addBusinessOrder

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
    

    
    const siteIdList = ref([] as any[])
    const setSiteIdList = async () => {
    siteIdList.value = await (await getWithMemberList({})).data
    }
    setSiteIdList()
    const memberIdList = ref([] as any[])
    const setMemberIdList = async () => {
    memberIdList.value = await (await getWithMemberList({})).data
    }
    setMemberIdList()
    const businessIdList = ref([] as any[])
    const setBusinessIdList = async () => {
    businessIdList.value = await (await getWithBusinessList({})).data
    }
    setBusinessIdList()
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if(row){
        const data = await (await getBusinessOrderInfo(row.id)).data
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
