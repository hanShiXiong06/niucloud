<template>
    <HsxDrawer v-model="visible" title="转交客资归属" subtitle="调整内部跟进责任，不是重新分配承接门店。" size="sm" show-footer :body-loading="refreshing" :confirm-loading="saving || refreshing" :confirm-disabled="!canSubmit" confirm-text="确认转交归属" @confirm="submit">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="lead-error-banner" />
        <el-button v-if="error" link type="primary" :disabled="saving" :loading="refreshing" @click="refreshSnapshot">重新读取归属，再选择并确认</el-button>
        <el-descriptions :column="1" border><el-descriptions-item label="客户">{{ currentLead.customer_name || currentLead.lead?.customer_name || '客资客户' }}</el-descriptions-item><el-descriptions-item label="当前归属">{{ currentLead.owner_name || '未分配（历史资料）' }}</el-descriptions-item></el-descriptions>
        <el-form label-position="top" class="lead-form" :disabled="saving || refreshing" style="margin-top: 22px"><el-form-item label="转交给"><OwnerSelect v-if="visible && currentLead.can_transfer === true" v-model="targetUid" :exclude-uid="expectedOwnerUid" :disabled="saving || refreshing" @selected="targetName = $event?.name || ''" /></el-form-item><el-checkbox v-model="confirmed">我已确认交接给 {{ targetName || '所选员工' }}</el-checkbox></el-form>
        <el-alert title="转交后，客资及关联工单、分享收益改由目标员工跟进；普通员工仅能查看当前归属自己的资料，管理员仍可查看全量。不会改变承接门店、工单结果或金额，不会重新派单、发送商户通知或转款。" type="warning" :closable="false" show-icon />
        <p class="lead-muted" style="line-height: 1.8; margin-top: 16px">按本次打开时的归属核对。若他人已转交，系统会拒绝覆盖，请刷新记录后重新确认。</p>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import OwnerSelect from './OwnerSelect.vue'
import { getLead, transferLeadOwner } from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
const props = defineProps<{ modelValue: boolean; lead: Row }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'changed', value: Row): void }>()
const visible = computed({ get: () => props.modelValue, set: value => { if (!saving.value && !refreshing.value) emit('update:modelValue', value) } })
const targetUid = ref<number | ''>(''), targetName = ref(''), confirmed = ref(false), saving = ref(false), refreshing = ref(false), error = ref(''), currentLead = ref<Row>({})
const expectedOwnerUid = ref(0), snapshotId = ref(0)
const canSubmit = computed(() => props.lead.can_transfer === true && currentLead.value.can_transfer === true && snapshotId.value > 0 && Number(targetUid.value) > 0 && Number(targetUid.value) !== expectedOwnerUid.value && confirmed.value && !saving.value && !refreshing.value)
let sequence = 0
async function refreshSnapshot() { if (saving.value || refreshing.value || !visible.value || !snapshotId.value) return; const token = ++sequence, id = snapshotId.value; refreshing.value = true; confirmed.value = false; targetUid.value = ''; targetName.value = ''; try { const result = await getLead(id); if (token !== sequence || !visible.value) return; currentLead.value = result.data || {}; expectedOwnerUid.value = Number(currentLead.value.owner_uid || 0); error.value = currentLead.value.can_transfer === true ? '' : '当前账号已无转交权限，请关闭后刷新列表核对' } catch (cause) { if (token === sequence) error.value = errorText(cause, '未能读取最新归属，请关闭后刷新列表') } finally { if (token === sequence) refreshing.value = false } }
async function submit() {
    if (!canSubmit.value) return
    const token = sequence, id = snapshotId.value, owner = Number(targetUid.value); saving.value = true; error.value = ''
    try { const result = await transferLeadOwner(id, { owner_uid: owner, expected_owner_uid: expectedOwnerUid.value }); if (token !== sequence || !visible.value) return; emit('changed', result.data || {}); emit('update:modelValue', false); ElMessage.success('归属已转交，承接门店与金额未改变') }
    catch (cause) { if (token === sequence) { error.value = errorText(cause, '转交未完成，请刷新归属后重新确认。'); confirmed.value = false } }
    finally { if (token === sequence) saving.value = false }
}
watch(() => [props.modelValue, props.lead.id], () => { sequence++; targetUid.value = ''; targetName.value = ''; confirmed.value = false; error.value = ''; saving.value = false; refreshing.value = false; currentLead.value = { ...props.lead }; snapshotId.value = Number(props.lead.id || 0); expectedOwnerUid.value = Number(props.lead.owner_uid || 0) }, { immediate: true })
watch(targetUid, () => { confirmed.value = false })
onBeforeUnmount(() => { sequence++ })
</script>
