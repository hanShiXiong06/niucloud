<template>
    <el-dialog v-model="visible" title="客户终止 / 退款处理" width="560px" destroy-on-close>
        <div v-loading="loading">
            <el-alert class="mb-[16px]" type="warning" :closable="false" title="未付款退出请使用“客户放弃办理”；确认客户已经付款后，才在这里发起退款。" />
            <div class="mb-[16px] rounded-[10px] bg-[#f8fafc] p-[14px] text-[13px] leading-[22px] text-[#475467]">
                <div>群编号：{{ group.group_no || '-' }}</div>
                <div>客户 / 门店：{{ group.member_name || group.store_name || '-' }}</div>
                <div>项目：{{ group.project_title || '-' }}</div>
            </div>

            <template v-if="!refund.id || refund.status === 'cancelled'">
                <el-form label-width="96px">
                    <el-form-item label="待退金额" required>
                        <el-input-number v-model="form.amount" :min="0.01" :precision="2" :step="100" class="!w-[220px]" />
                        <span class="ml-[8px] text-[12px] text-[#98a2b3]">请以群内真实流水为准</span>
                    </el-form-item>
                    <el-form-item label="终止原因" required>
                        <el-input v-model="form.reason" type="textarea" :rows="4" maxlength="1000" show-word-limit placeholder="例如：客户决定不再参与项目，已在群内申请退回项目款" />
                    </el-form-item>
                </el-form>
            </template>

            <template v-else>
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="退款单号">{{ refund.refund_no }}</el-descriptions-item>
                    <el-descriptions-item label="状态"><el-tag :type="refund.status === 'refunded' ? 'success' : 'warning'">{{ refund.status_name }}</el-tag></el-descriptions-item>
                    <el-descriptions-item label="退款金额">¥{{ refund.amount }}</el-descriptions-item>
                    <el-descriptions-item label="退款原因">{{ refund.reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="发起时间">{{ timeText(refund.requested_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="refund.status === 'pending'" label="待处理时长"><span :class="Number(refund.pending_seconds) >= 86400 ? 'text-[#d92d20] font-medium' : ''">{{ durationText(refund.pending_seconds) }}</span></el-descriptions-item>
                    <el-descriptions-item v-if="refund.refunded_at" label="完成时间">{{ timeText(refund.refunded_at) }}</el-descriptions-item>
                </el-descriptions>
                <el-form v-if="refund.status === 'pending'" class="mt-[16px]" label-width="96px">
                    <el-form-item label="退款凭证" required>
                        <upload-image v-model="completeForm.proof" :limit="1" width="88px" height="88px" image-text="上传凭证" />
                    </el-form-item>
                    <el-form-item label="处理备注">
                        <el-input v-model="completeForm.remark" type="textarea" :rows="3" maxlength="1000" show-word-limit placeholder="可填写退款账户、流水尾号或其他核对信息" />
                    </el-form-item>
                </el-form>
                <div v-if="refund.status === 'refunded' && refund.proof" class="mt-[16px]">
                    <div class="mb-[8px] text-[13px] text-[#667085]">退款凭证</div>
                    <el-image :src="img(refund.proof)" class="h-[88px] w-[88px] rounded-[8px]" fit="cover" :preview-src-list="[img(refund.proof)]" preview-teleported />
                </div>
            </template>
        </div>
        <template #footer>
            <el-button @click="visible = false">关闭</el-button>
            <template v-if="!refund.id || refund.status === 'cancelled'">
                <el-button v-permission="'hsx_project_center_refund_request'" type="danger" :loading="saving" @click="requestRefund">确认进入待退款</el-button>
            </template>
            <template v-else-if="refund.status === 'pending'">
                <el-button v-permission="'hsx_project_center_refund_cancel'" :loading="saving" @click="cancelRefund">取消退款并恢复流程</el-button>
                <el-button v-permission="'hsx_project_center_refund_complete'" type="success" :loading="saving" @click="completeRefund">确认已退款</el-button>
            </template>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { img } from '@/utils/common'
import { cancelProjectCenterRefund, completeProjectCenterRefund, getProjectCenterRefund, requestProjectCenterRefund } from '@/addon/hsx_project_center/api'

const emit = defineEmits(['success'])
const visible = ref(false), loading = ref(false), saving = ref(false)
const group = ref<any>({}), refund = ref<any>({})
const form = reactive({ amount: 0, reason: '' })
const completeForm = reactive({ proof: '', remark: '' })

function timeText(value:any) { return value ? new Date(Number(value) * 1000).toLocaleString() : '-' }
function durationText(value:any) { const seconds = Math.max(0, Number(value || 0)); const days = Math.floor(seconds / 86400); const hours = Math.floor((seconds % 86400) / 3600); return days > 0 ? `${days} 天 ${hours} 小时` : `${Math.max(1, hours)} 小时内` }

async function open(row:any) {
    group.value = { ...row }
    Object.assign(form, { amount: Number(row.project_payment_amount || 0), reason: '' })
    Object.assign(completeForm, { proof: '', remark: '' })
    visible.value = true
    loading.value = true
    try {
        const res:any = await getProjectCenterRefund(Number(row.id || 0))
        refund.value = res.data || {}
        if (refund.value.status === 'pending') completeForm.remark = String(refund.value.remark || '')
    } finally { loading.value = false }
}

async function requestRefund() {
    if (Number(form.amount) <= 0) return ElMessage.warning('请填写实际待退金额')
    if (!form.reason.trim()) return ElMessage.warning('请填写客户终止办理及退款原因')
    await ElMessageBox.confirm('确认客户已经付款，并将本次办理冻结为“待退款”吗？', '发起退款', { type: 'warning' })
    saving.value = true
    try {
        const res:any = await requestProjectCenterRefund(Number(group.value.id), form)
        refund.value = res.data || {}
        ElMessage.success('已进入待退款流程')
        emit('success')
    } finally { saving.value = false }
}

async function completeRefund() {
    const proof = Array.isArray(completeForm.proof) ? String(completeForm.proof[0] || '') : String(completeForm.proof || '')
    if (!proof) return ElMessage.warning('请上传退款凭证')
    await ElMessageBox.confirm('确认款项已经实际退回客户吗？确认后本次办理将正式关闭。', '确认已退款', { type: 'warning' })
    saving.value = true
    try {
        const res:any = await completeProjectCenterRefund(Number(group.value.id), { proof, remark: completeForm.remark })
        refund.value = res.data || {}
        ElMessage.success('退款已完成，本次办理已关闭')
        emit('success')
    } finally { saving.value = false }
}

async function cancelRefund() {
    const result:any = await ElMessageBox.prompt('请填写取消退款并恢复办理的原因', '取消退款', {
        inputValidator: (value) => String(value || '').trim() ? true : '请填写原因',
        type: 'warning', confirmButtonText: '确认恢复', cancelButtonText: '返回',
    })
    saving.value = true
    try {
        const res:any = await cancelProjectCenterRefund(Number(group.value.id), { reason: result.value })
        refund.value = res.data || {}
        ElMessage.success('退款已取消，原办理状态已恢复')
        visible.value = false
        emit('success')
    } finally { saving.value = false }
}

defineExpose({ open })
</script>
