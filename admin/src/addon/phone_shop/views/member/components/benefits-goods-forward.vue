<template>
    <el-form ref="formRef" :model="formData" label-width="112px">
        <el-form-item class="!mb-[12px]">
            <el-checkbox v-model="formData.is_use" :true-label="1" :false-label="0">
                开启同行商品转发
            </el-checkbox>
        </el-form-item>
        <template v-if="formData.is_use">
            <el-alert class="mb-[16px]" type="info" :closable="false" show-icon
                title="拥有当前会员等级的客户可下载商品图文素材；当前等级也是申请审核通过后自动设置的目标等级。" />
            <el-form-item label="开放主动申请">
                <el-switch v-model="formData.allow_apply" :active-value="1" :inactive-value="0" />
            </el-form-item>
            <template v-if="formData.allow_apply">
                <el-form-item label="申请资料表单" required>
                    <el-select v-model="formData.form_id" class="w-[320px]" filterable placeholder="选择已启用的万能表单">
                        <el-option v-for="item in formList" :key="item.form_id" :label="item.page_title || item.title" :value="item.form_id" />
                    </el-select>
                    <span class="ml-[10px] text-[13px] text-gray-400">客户无权限时填写此表单</span>
                </el-form-item>
                <el-form-item label="审核负责人" required>
                    <el-select v-model="formData.reviewer_uid" class="w-[320px]" filterable placeholder="选择接收企微提醒的负责人" @change="reviewerChange">
                        <el-option v-for="item in userList" :key="item.uid" :label="userLabel(item)" :value="item.uid" />
                    </el-select>
                    <span class="ml-[10px] text-[13px] text-gray-400">提交后立即生成审核任务并推送负责人</span>
                </el-form-item>
            </template>
        </template>
    </el-form>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getDiyFormList } from '@/app/api/diy_form'
import { getAllUserList } from '@/app/api/user'

const props = defineProps({ modelValue: { type: Object, default: () => ({}) } })
const emits = defineEmits(['update:modelValue'])
const formRef = ref()
const formList = ref<any[]>([])
const userList = ref<any[]>([])
const formData = ref({ is_use: 0, allow_apply: 0, form_id: 0, reviewer_uid: 0, reviewer_name: '' })
const value = computed({ get: () => props.modelValue, set: val => emits('update:modelValue', val) })

// 只在父级已有保存值首次注入时回显。不能在每次父级更新时重新创建对象，
// 否则会与下面的 v-model 回写互相触发，造成会员等级编辑页渲染死循环。
watch(() => value.value, (newValue, oldValue) => {
    if ((!oldValue || !Object.keys(oldValue).length) && Object.keys(newValue || {}).length) {
        formData.value = { ...formData.value, ...newValue }
    }
}, { immediate: true })

watch(() => formData.value, () => {
    value.value = formData.value
}, { deep: true })

const userLabel = (item: any) => item.real_name || item.username || item.nickname || `用户#${item.uid}`
const reviewerChange = (uid: number) => {
    const user = userList.value.find(item => Number(item.uid) === Number(uid))
    formData.value.reviewer_name = user ? userLabel(user) : ''
}

onMounted(async () => {
    const [forms, users] = await Promise.all([getDiyFormList({ status: 1 }), getAllUserList({})])
    formList.value = forms.data || []
    userList.value = users.data || []
})

const verify = async () => {
    if (formData.value.is_use && formData.value.allow_apply && !formData.value.form_id) {
        ElMessage.warning('请选择同行身份申请表单')
        return false
    }
    if (formData.value.is_use && formData.value.allow_apply && !formData.value.reviewer_uid) {
        ElMessage.warning('请选择同行身份审核负责人')
        return false
    }
    return true
}
defineExpose({ verify })
</script>
