<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateAiimageCreate') : t('addAiimageCreate')" width="50%" class="diy-dialog-wrap" :destroy-on-close="true">
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
                
                <el-form-item :label="t('modelId')" >
                    <el-select class="input-width" v-model="formData.model_id" clearable :placeholder="t('modelIdPlaceholder')">
                       <el-option label="请选择" value=""></el-option>
                        <el-option
                            v-for="(item, index) in modelIdList"
                            :key="index"
                            :label="item['name']"
                            :value="item['id']"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item :label="t('prompt')" >
                    <el-input v-model="formData.prompt" clearable :placeholder="t('promptPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('imageUrls')" >
                    <el-input v-model="formData.image_urls" clearable :placeholder="t('imageUrlsPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('aspectRatio')" >
                    <el-input v-model="formData.aspect_ratio" clearable :placeholder="t('aspectRatioPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('images')" >
                    <el-input v-model="formData.images" clearable :placeholder="t('imagesPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('status')" >
                    <el-input v-model="formData.status" clearable :placeholder="t('statusPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('point')" >
                    <el-input v-model="formData.point" clearable :placeholder="t('pointPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('isSelf')" >
                    <el-input v-model="formData.is_self" clearable :placeholder="t('isSelfPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('msg')" >
                    <el-input v-model="formData.msg" clearable :placeholder="t('msgPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('platform')" >
                    <el-input v-model="formData.platform" clearable :placeholder="t('platformPlaceholder')" class="input-width" />
                </el-form-item>
                
                <el-form-item :label="t('channel')" >
                    <el-input v-model="formData.channel" clearable :placeholder="t('channelPlaceholder')" class="input-width" />
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
import { addAiimageCreate, editAiimageCreate, getAiimageCreateInfo, getWithMemberList, getWithAiimageModelList } from '@/addon/ai_image/api/aiimagecreate'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    member_id: '',
    model_id: '',
    prompt: '',
    image_urls: '',
    aspect_ratio: '',
    images: '',
    status: '',
    point: '',
    is_self: '',
    msg: '',
    platform: '',
    channel: '',
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
    model_id: [
        { required: true, message: t('modelIdPlaceholder'), trigger: 'blur' },
        
    ]
,
    prompt: [
        { required: true, message: t('promptPlaceholder'), trigger: 'blur' },
        
    ]
,
    image_urls: [
        { required: true, message: t('imageUrlsPlaceholder'), trigger: 'blur' },
        
    ]
,
    aspect_ratio: [
        { required: true, message: t('aspectRatioPlaceholder'), trigger: 'blur' },
        
    ]
,
    images: [
        { required: true, message: t('imagesPlaceholder'), trigger: 'blur' },
        
    ]
,
    status: [
        { required: true, message: t('statusPlaceholder'), trigger: 'blur' },
        
    ]
,
    point: [
        { required: true, message: t('pointPlaceholder'), trigger: 'blur' },
        
    ]
,
    is_self: [
        { required: true, message: t('isSelfPlaceholder'), trigger: 'blur' },
        
    ]
,
    msg: [
        { required: true, message: t('msgPlaceholder'), trigger: 'blur' },
        
    ]
,
    platform: [
        { required: true, message: t('platformPlaceholder'), trigger: 'blur' },
        
    ]
,
    channel: [
        { required: true, message: t('channelPlaceholder'), trigger: 'blur' },
        
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
    let save = formData.id ? editAiimageCreate : addAiimageCreate

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
    const modelIdList = ref([] as any[])
    const setModelIdList = async () => {
    modelIdList.value = await (await getWithAiimageModelList({})).data
    }
    setModelIdList()
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if(row){
        const data = await (await getAiimageCreateInfo(row.id)).data
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
