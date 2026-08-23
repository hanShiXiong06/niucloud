<template>
    <el-dialog v-model="state.visible" title="更正客户群编号" width="520px" destroy-on-close append-to-body>
        <el-alert type="info" :closable="false" show-icon class="mb-[16px]" title="这里只更正业务编号，不修改数据库 ID；已有资料与审核工单会继续关联当前客户群。" />
        <div class="mb-[16px] rounded-[10px] bg-[#f8fafc] p-[12px] text-[13px] leading-[22px] text-[#667085]">
            <div>项目：{{ state.row?.project_title || '-' }}　客户：{{ state.row?.member_name || '-' }} {{ state.row?.member_mobile || '' }}</div>
            <div>门店：{{ state.row?.store_name || '未填写' }}</div>
        </div>
        <el-form label-position="top">
            <el-form-item label="当前编号"><b class="text-[16px] text-[#155eef]">{{ state.row?.group_no || '-' }}</b></el-form-item>
            <el-form-item label="正确群编号" required><el-input v-model="state.groupNo" maxlength="40" clearable placeholder="例如 0819-2，也可粘贴完整群名称" /></el-form-item>
            <el-form-item label="更正原因" required><el-input v-model="state.reason" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="例如：客户误填为 0819-1，群内实际编号为 0819-2" /></el-form-item>
        </el-form>
        <template #footer><el-button @click="state.visible = false">取消</el-button><el-button type="primary" :loading="state.saving" @click="submit">确认更正</el-button></template>
    </el-dialog>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { correctProjectCenterApplicationGroupNo, correctProjectCenterGroupNo } from '@/addon/hsx_project_center/api'

const emit = defineEmits<{ (event:'success', row:any):void }>()
const state = reactive<any>({ visible: false, saving: false, row: null, scope: 'group', targetId: 0, groupNo: '', reason: '' })

function open(row:any, scope:'group'|'application' = 'group') {
    const groupId = Number(row?.group_id || row?.id || 0)
    if (!groupId) return ElMessage.warning('当前工单未关联客户群，不能更正群编号')
    const targetId = scope === 'application' ? Number(row?.id || 0) : groupId
    if (!targetId) return ElMessage.warning('未找到需要更正的业务记录')
    Object.assign(state, {
        visible: true,
        saving: false,
        row: { ...row, group_id: groupId },
        scope,
        targetId,
        groupNo: String(row.group_no || ''),
        reason: ''
    })
}

async function submit() {
    const groupNo = String(state.groupNo || '').trim()
    const reason = String(state.reason || '').trim()
    if (!groupNo) return ElMessage.warning('请填写正确群编号')
    if (!reason) return ElMessage.warning('请填写更正原因')
    await ElMessageBox.confirm(
        `确认将客户群编号从“${state.row?.group_no || '-'}”更正为“${groupNo}”吗？系统会保留旧编号兼容，但真实微信群名称也应同步修改。`,
        '确认更正群编号',
        { type: 'warning', confirmButtonText: '确认更正', cancelButtonText: '再核对一下' }
    )
    state.saving = true
    try {
        const payload = {
            group_no: groupNo,
            expected_group_no_full: String(state.row?.group_no_full || state.row?.group_no || ''),
            reason
        }
        const response:any = state.scope === 'application'
            ? await correctProjectCenterApplicationGroupNo(state.targetId, payload)
            : await correctProjectCenterGroupNo(state.targetId, payload)
        const row = response?.data || { ...state.row, group_no: groupNo }
        ElMessage.success('群编号已更正，原资料关联保持不变')
        state.visible = false
        emit('success', row)
    } finally { state.saving = false }
}

defineExpose({ open })
</script>
