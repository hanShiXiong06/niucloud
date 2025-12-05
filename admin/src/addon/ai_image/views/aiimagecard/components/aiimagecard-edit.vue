<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('生成卡密') : t('生成卡密')" width="50%" class="diy-dialog-wrap"
        :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form"
            v-loading="loading">


            <el-form-item :label="t('生成数量')" prop="num">
                <el-input-number min="0" v-model="formData.num" clearable :placeholder="t('请输入生成数量')"
                    class="input-width" />
            </el-form-item>

            <el-form-item :label="t('积分')" prop="point">
                <el-input-number min="0" v-model="formData.point" clearable :placeholder="t('pointPlaceholder')"
                    class="input-width" />
            </el-form-item>

            <el-form-item :label="t('到期时间')" prop="expire_time">
                <el-date-picker v-model="formData.expire_time" type="datetime" clearable :placeholder="t('请选择到期时间')"
                    class="input-width" />
                <span class="ml-2 text-gray-400">默认永久有效</span>
            </el-form-item>

            <el-form-item label="所属用户">
                <el-select class="input-width" v-model="formData.pid" clearable :placeholder="t('memberIdPlaceholder')">
                    <div class="mt-2 mb-2 ml-4">
                        <el-input @change="change" v-model="keyword" style="width: 200px" placeholder="搜索会员支持昵称/会员名">
                            <template #append>搜索 </template></el-input>
                    </div>
                    <el-option label="请选择" value=""></el-option>
                    <el-option v-for="(item, index) in memberIdList" :key="index" :label="item['nickname']"
                        :value="item['member_id']" />
                </el-select>
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
import { addAiimageCard, editAiimageCard, getAiimageCardInfo, getWithMemberList } from '@/addon/ai_image/api/aiimagecard'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    num: 50,
    point: 50,
    pid: '',
    expire_time: '',
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
        num: [
            { required: true, message: t('numPlaceholder'), trigger: 'blur' },

        ]
        ,
        point: [
            { required: true, message: t('pointPlaceholder'), trigger: 'blur' },

        ]
        ,
        is_use: [
            { required: true, message: t('isUsePlaceholder'), trigger: 'blur' },

        ]
        ,
        use_time: [
            { required: true, message: t('useTimePlaceholder'), trigger: 'blur' },

        ]
        ,
        is_export: [
            { required: true, message: t('isExportPlaceholder'), trigger: 'blur' },

        ]
        ,
        pid: [
            { required: true, message: t('pidPlaceholder'), trigger: 'blur' },

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
    let save = formData.id ? editAiimageCard : addAiimageCard

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

// 获取字典数据

// 监听 keyword 变化


const change = () => {
    setMemberIdList();
};
const keyword = ref();
watch(keyword.value, () => {
    change()
})
const memberIdList = ref([]);
const setMemberIdList = async () => {
    memberIdList.value = await (
        await getWithMemberList({ keyword: keyword.value })
    ).data.data;
};

setMemberIdList();
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if (row) {
        const data = await (await getAiimageCardInfo(row.id)).data
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
