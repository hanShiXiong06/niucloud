<template>
    <el-form ref="formRef" :model="formData" label-width="138px">
        <el-form-item class="!mb-[12px]">
            <el-checkbox v-model="formData.is_use" :true-label="1" :false-label="0">允许当前等级推广项目并获得佣金</el-checkbox>
            <el-tooltip placement="top" :width="360"><template #content><div class="leading-[22px]">只有打开本权益的会员等级，才可以生成项目邀请卡片并成为佣金受益人。项目自身还必须开启“邀请分佣”；两个开关缺一不可。</div></template><el-icon class="ml-[8px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip>
        </el-form-item>
        <template v-if="formData.is_use">
            <el-alert class="mb-[18px]" type="info" :closable="false" show-icon><template #title>等级决定“有没有资格、按什么系数算”；具体项目决定“基础佣金是多少”。系统在客户资料审核通过时固定等级快照。</template></el-alert>
            <el-form-item><template #label><span>一级佣金系数</span><el-tooltip placement="top" :width="370" content="直接邀请客户参与项目时使用。示例：项目一级基础佣金为 300 元，当前等级系数为 120%，实际一级佣金为 300 × 120% = 360 元。"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template><el-input-number v-model="formData.first_coefficient" :min="0" :max="500" :precision="2" :step="10" class="!w-[220px]" /><span class="ml-[8px] text-[13px] text-[#667085]">%</span></el-form-item>
            <el-form-item><template #label><span>参与二级分佣</span><el-tooltip placement="top" :width="390" content="关系示例：A 邀请 B，B 再邀请 C。C 的项目审核通过后，B 是一级受益人，A 是二级受益人。关闭后，当前等级会员即使位于二级关系也不获得佣金。"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template><el-switch v-model="formData.second_enabled" :active-value="1" :inactive-value="0" /></el-form-item>
            <el-form-item v-if="formData.second_enabled"><template #label><span>二级佣金系数</span><el-tooltip placement="top" :width="370" content="二级受益人使用。示例：项目二级基础佣金为 100 元，当前等级系数为 80%，实际二级佣金为 100 × 80% = 80 元。"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template><el-input-number v-model="formData.second_coefficient" :min="0" :max="500" :precision="2" :step="10" class="!w-[220px]" /><span class="ml-[8px] text-[13px] text-[#667085]">%</span></el-form-item>
        </template>
    </el-form>
</template>
<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { QuestionFilled } from '@element-plus/icons-vue'
const props = defineProps({ modelValue: { type: Object, default: () => ({}) } })
const emits = defineEmits(['update:modelValue'])
const formRef = ref()
const formData = ref({ is_use: 0, first_coefficient: 100, second_enabled: 1, second_coefficient: 100 })
const value = computed({ get: () => props.modelValue, set: val => emits('update:modelValue', val) })
watch(() => value.value, (newValue, oldValue) => { if ((!oldValue || !Object.keys(oldValue).length) && Object.keys(newValue || {}).length) formData.value = { ...formData.value, ...newValue } }, { immediate: true })
watch(() => formData.value, () => { value.value = formData.value }, { deep: true })
const verify = async () => { if (!formData.value.is_use) return true; if (Number(formData.value.first_coefficient) <= 0 && (!formData.value.second_enabled || Number(formData.value.second_coefficient) <= 0)) { ElMessage.warning('一级和二级佣金系数不能同时为 0；如不允许推广，请直接关闭该权益'); return false } return true }
defineExpose({ verify })
</script>
