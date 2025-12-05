<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateAiimagePackage') : t('addAiimagePackage')"
        width="50%" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form"
            v-loading="loading">
            <el-form-item :label="t('type')" prop="type">
                <el-radio-group v-model="formData.type" size="small" class="input-width">
                    <el-radio-button label="point">充值</el-radio-button>
                    <el-radio-button label="card">卡密</el-radio-button>
                </el-radio-group>
            </el-form-item>
            <el-form-item :label="t('status')" prop="status">

                <el-radio-group v-model="formData.status" size="small" class="input-width">
                    <el-radio-button :label="0">禁用</el-radio-button>
                    <el-radio-button :label="1">启用</el-radio-button>
                </el-radio-group>
            </el-form-item>
            <el-form-item :label="t('name')" prop="name">
                <el-input v-model="formData.name" clearable :placeholder="t('namePlaceholder')" class="input-width" />
            </el-form-item>

            <el-form-item :label="t('image')" prop="image">
                <upload-image v-model="formData.image" />
            </el-form-item>

            <el-form-item :label="t('price')" prop="price">
                <el-input-number min="0" v-model="formData.price" clearable :placeholder="t('pricePlaceholder')"
                    class="input-width" />
            </el-form-item>

            <el-form-item :label="t('point')" prop="point">
                <el-input-number min="1" v-model="formData.point" clearable :placeholder="t('pointPlaceholder')"
                    class="input-width" />
            </el-form-item>

            <el-form-item v-if="formData.type == 'card'" :label="t('生成卡密数量')" prop="num">
                <el-input-number min="1" max="50" v-model="formData.num" clearable :placeholder="t('numPlaceholder')"
                    class="input-width" />
            </el-form-item>
            <el-form-item v-if="formData.type == 'card'" :label="t('天数')" prop="day">
                <el-input-number min="1" v-model="formData.day" clearable :placeholder="t('请输入有效天数')"
                    class="input-width" />
            </el-form-item>
            <el-form-item :label="t('排序')" prop="sort">
                <el-input-number min="0" v-model="formData.sort" clearable :placeholder="t('请输入排序')"
                    class="input-width" />
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
import { addAiimagePackage, editAiimagePackage, getAiimagePackageInfo } from '@/addon/ai_image/api/aiimagepackage'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    name: '',
    image: '',
    price: '',
    point: '',
    num: '',
    type: 'point',
    day: '',
    limit: '',
    status: 1,
    sort: '',
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        name: [
            { required: true, message: t('namePlaceholder'), trigger: 'blur' },

        ]
        ,
        image: [
            { required: true, message: t('imagePlaceholder'), trigger: 'blur' },

        ]
        ,
        price: [
            { required: true, message: t('pricePlaceholder'), trigger: 'blur' },

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
        limit: [
            { required: true, message: t('limitPlaceholder'), trigger: 'blur' },

        ]
        ,
        status: [
            { required: true, message: t('statusPlaceholder'), trigger: 'blur' },

        ]
        ,
        sort: [
            { required: true, message: t('sortPlaceholder'), trigger: 'blur' },

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
    let save = formData.id ? editAiimagePackage : addAiimagePackage

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
    if (row) {
        const data = await (await getAiimagePackageInfo(row.id)).data
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
.diy-dialog-wrap .el-form-item__label {
    height: auto !important;
}
</style>
