<template>
    <div class="main-container">
        <div class="detail-head">
            <div class="left" @click="back()">
                <span class="iconfont iconxiangzuojiantou !text-xs"></span>
                <span class="ml-[1px]">{{ t('returnToPreviousPage') }}</span>
            </div>
            <span class="adorn">|</span>
            <span class="right">{{ pageName }}</span>
        </div>
        <el-card class="box-card !border-none" shadow="never">
            <el-form :model="formData" label-width="90px" ref="formRef" :rules="formRules" class="page-form">

                <el-form-item :label="t('title')" prop="title">
                    <el-input v-model="formData.title" clearable :placeholder="t('titlePlaceholder')"
                        class="input-width" />
                </el-form-item>

                <el-form-item :label="t('image')" prop="image">
                    <upload-image v-model="formData.image" />
                </el-form-item>

                <el-form-item :label="t('desc')" prop="desc">
                    <el-input v-model="formData.desc" clearable :placeholder="t('descPlaceholder')"
                        class="input-width" />
                </el-form-item>

                <el-form-item :label="t('sort')" prop="sort">
                    <el-input-number min="0" v-model="formData.sort" clearable :placeholder="t('sortPlaceholder')"
                        class="input-width" />
                </el-form-item>

                <el-form-item :label="t('content')" prop="content">
                    <editor v-model="formData.content" />
                </el-form-item>

            </el-form>
        </el-card>
        <div class="fixed-footer-wrap">
            <div class="fixed-footer">
                <el-button type="primary" @click="onSave(formRef)">{{ t('save') }}</el-button>
                <el-button @click="back()">{{ t('cancel') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import type { FormInstance } from 'element-plus'
import { getAiimageHelpInfo, addAiimageHelp, editAiimageHelp } from '@/addon/ai_image/api/aiimagehelp';
import { useRoute } from 'vue-router'

const route = useRoute()
const id: number = parseInt(route.query.id);
const loading = ref(false)
const pageName = route.meta.title



/**
 * 表单数据
 */
const initialFormData = {
    id: 0,
    cat_id: 0,
    title: '',
    image: '',
    desc: '',
    content: '',
    view_num: 0,
    sort: 0,
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const setFormData = async (id: number = 0) => {
    Object.assign(formData, initialFormData)
    const data = await (await getAiimageHelpInfo(id)).data
    Object.keys(formData).forEach((key: string) => {
        if (data[key] != undefined) formData[key] = data[key]
    })
}
if (id) setFormData(id);

const formRef = ref<FormInstance>()
// 选中数据
const selectData = ref<any[]>([])

// 字典数据



// 表单验证规则
const formRules = computed(() => {
    return {
        cat_id: [
            { required: true, message: t('catIdPlaceholder'), trigger: 'blur' },

        ]
        ,
        title: [
            { required: true, message: t('titlePlaceholder'), trigger: 'blur' },

        ]
        ,
        image: [
            { required: true, message: t('imagePlaceholder'), trigger: 'blur' },

        ]
        ,
        desc: [
            { required: true, message: t('descPlaceholder'), trigger: 'blur' },

        ]
        ,
        content: [
            { required: true, message: t('contentPlaceholder'), trigger: 'blur' },

        ]
        ,
        view_num: [
            { required: true, message: t('viewNumPlaceholder'), trigger: 'blur' },

        ]
        ,
        sort: [
            { required: true, message: t('sortPlaceholder'), trigger: 'blur' },

        ]

    }
})

const onSave = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true
            let data = formData

            const save = id ? editAiimageHelp : addAiimageHelp
            save(data).then(res => {
                loading.value = false
                history.back()
            }).catch(err => {
                loading.value = false
            })

        }
    })
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
const back = () => {
    history.back()
}
</script>

<style lang="scss" scoped></style>
