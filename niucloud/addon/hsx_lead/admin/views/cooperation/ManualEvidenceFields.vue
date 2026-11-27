<template>
    <el-form ref="formRef" :model="modelValue" :rules="rules" label-position="top" :disabled="disabled" class="manual-evidence-form" @submit.prevent>
        <el-form-item :label="refund ? '本笔实际退款（元）' : '实际已收金额（元）'" prop="amount"><el-input :model-value="modelValue.amount" :readonly="amountReadonly" inputmode="decimal" maxlength="15" @update:model-value="update('amount',$event)"><template #prefix>¥</template></el-input></el-form-item>
        <div class="manual-evidence-row"><el-form-item :label="refund ? '实际退款方式' : '实际收款方式'" prop="method"><el-select :model-value="modelValue.method" placeholder="按实际凭证选择" @update:model-value="update('method',$event)"><el-option v-for="item in methods" :key="item.value" :value="item.value" :label="item.label" /></el-select></el-form-item><el-form-item label="实际到账时间" prop="received_at"><el-date-picker :model-value="modelValue.received_at" type="datetime" value-format="X" placeholder="选择实际到账时间" :disabled-date="futureDate" @update:model-value="update('received_at',$event || '')" /></el-form-item></div>
        <el-form-item :label="refund ? '实际退款账户 / 经办信息' : '实际收款账户 / 经办信息'" prop="account_label"><el-input :model-value="modelValue.account_label" maxlength="100" show-word-limit placeholder="填写账户名称与必要尾号；现金填写经办信息" @update:model-value="update('account_label',$event)" /></el-form-item>
        <el-form-item label="交易流水号 / 现金收据号" prop="external_no"><el-input :model-value="modelValue.external_no" maxlength="100" show-word-limit placeholder="按实际交易凭证填写，现金也需要收据号" @update:model-value="update('external_no',$event)" /></el-form-item>
        <el-form-item label="核对说明" prop="reason"><el-input :model-value="modelValue.reason" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="说明到账核对依据；金额有变化时说明调整原因" @update:model-value="update('reason',$event)" /></el-form-item>
        <el-form-item prop="confirm"><el-checkbox :model-value="modelValue.confirm" @update:model-value="update('confirm',$event === true)">{{ refund ? '我已核实款项已实际退还给客户，确认登记以上退款信息' : '我已核实款项已实际到账，确认登记以上收款信息' }}</el-checkbox></el-form-item>
    </el-form>
</template>
<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { manualEvidenceError, manualEvidencePayload, manualMethods, type ManualEvidence } from '../../composables/manualReceipt'
const props = withDefaults(defineProps<{ modelValue: ManualEvidence; disabled?: boolean; refund?: boolean; amountReadonly?: boolean; methods?: { value: string; label: string }[] }>(), { disabled: false, refund: false, amountReadonly: false, methods: () => manualMethods })
const emit = defineEmits<{ (event: 'update:modelValue', value: ManualEvidence): void }>(), formRef = ref<any>(null)
function update(key: keyof ManualEvidence, value: any) { if (!props.disabled) emit('update:modelValue', { ...props.modelValue, [key]: value, ...(key==='confirm'?{}:{confirm:false}) }) }
const errorFor = (key: keyof ManualEvidence) => (_rule: unknown, _value: unknown, callback: (error?: Error) => void) => {
    // ElSelect fires field validation in the same turn as update:modelValue.
    // Wait for the parent's replacement object to reach this shared form.
    void nextTick().then(() => {
    const candidate = { ...props.modelValue }, error = manualEvidenceError(candidate)
    const relevant: Record<keyof ManualEvidence, string[]> = { amount: ['金额'], method: ['方式'], account_label: ['账户'], external_no: ['流水', '收据'], received_at: ['时间'], reason: ['核对说明'], confirm: ['勾选'] }
    callback(error && relevant[key].some(word => error.includes(word)) ? new Error(error) : undefined)
    })
}
const rules = Object.fromEntries(['amount', 'method', 'account_label', 'external_no', 'received_at', 'reason', 'confirm'].map(key => [key, [{ validator: errorFor(key as keyof ManualEvidence), trigger: key === 'reason' || key === 'external_no' || key === 'account_label' ? 'blur' : 'change' }]]))
function futureDate(date: Date) { const end = new Date(); end.setHours(23,59,59,999); return date.getTime() > end.getTime() }
async function validate() { if (!formRef.value) return null; try { await formRef.value.validate(); return manualEvidencePayload(props.modelValue) } catch { return null } }
defineExpose({ validate })
</script>
<style scoped>
.manual-evidence-form{margin-top:20px}.manual-evidence-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px}.manual-evidence-row :deep(.el-select),.manual-evidence-row :deep(.el-date-editor){width:100%;min-width:0}.manual-evidence-form :deep(.el-checkbox){height:auto;white-space:normal;line-height:1.7}.manual-evidence-form :deep(.el-checkbox__label){white-space:normal;overflow-wrap:anywhere}@media(max-width:600px){.manual-evidence-row{grid-template-columns:minmax(0,1fr);gap:0}}
</style>
