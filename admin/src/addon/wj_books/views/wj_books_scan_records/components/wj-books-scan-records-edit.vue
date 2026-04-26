<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateWjBooksScanRecords') : t('addWjBooksScanRecords')" width="50%" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
                <el-form-item :label="t('memberId')" prop="member_id">
    <el-select class="input-width" v-model="formData.member_id" clearable :placeholder="t('memberIdPlaceholder')">
       <el-option label="请选择" value=""></el-option>
        <el-option
            v-for="(item, index) in memberIdList"
            :key="index"
            :label="item['member_no']"
            :value="item['member_id']"
        />
    </el-select>
</el-form-item>

                <el-form-item :label="t('isbn')" prop="isbn">
    <el-input v-model="formData.isbn" clearable :placeholder="t('isbnPlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('scanTime')"  class="input-width">
    <el-date-picker 
        class="flex-1 !flex"
        v-model="formData.scan_time"
        clearable
        type="datetime"
        value-format="YYYY-MM-DD HH:mm:ss"
        :placeholder="t('scanTimePlaceholder')">
    </el-date-picker>
</el-form-item>
                <el-form-item :label="t('scanType')" prop="scan_type">
    <el-radio-group v-model="formData.scan_type">
        <el-radio :label="1">扫码</el-radio>
        <el-radio :label="2">手动输入</el-radio>
    </el-radio-group>
</el-form-item>

                <el-form-item :label="t('status')" prop="status">
    <el-radio-group v-model="formData.status">
        <el-radio :label="0">仅扫描</el-radio>
        <el-radio :label="1">已加入回收车</el-radio>
        <el-radio :label="2">已提交回收</el-radio>
    </el-radio-group>
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
import { addWjBooksScanRecords, editWjBooksScanRecords, getWjBooksScanRecordsInfo, getWithMemberList } from '@/addon/wj_books/api/wj_books_scan_records'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    member_id: '',
    isbn: '',
    scan_time: '',
    scan_type: 1,
    status: 0,
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
    isbn: [
        { required: true, message: t('isbnPlaceholder'), trigger: 'blur' },
        
    ]
,
    scan_time: [
        { required: true, message: t('scanTimePlaceholder'), trigger: 'blur' },
        
    ]
,
    scan_type: [
        { required: true, message: t('scanTypePlaceholder'), trigger: 'blur' },
        
    ]
,
    status: [
        { required: true, message: t('statusPlaceholder'), trigger: 'blur' },
        
    ]
,
    }
})

const emit = defineEmits(['complete'])

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    let save = formData.id ? editWjBooksScanRecords : addWjBooksScanRecords

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
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if(row){
        const data = await (await getWjBooksScanRecordsInfo(row.id)).data
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
