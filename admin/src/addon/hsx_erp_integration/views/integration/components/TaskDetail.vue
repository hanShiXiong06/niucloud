<template>
    <el-drawer v-model="detail.visible" title="同步任务详情" size="min(560px, 94vw)" class="ic-drawer" append-to-body destroy-on-close @closed="emit('finished')">
        <el-result v-if="detail.error" icon="warning" title="未能读取任务" :sub-title="detail.error"><template #extra><el-button @click="detail.open(detail.id)">重试查询</el-button></template></el-result>
        <div v-else v-loading="detail.loading" style="min-height:100px">
            <template v-if="detail.data"><dl class="ic-detail">
                <dt>任务状态</dt><dd><strong>{{label(states,detail.data.status)}}</strong></dd>
                <dt>本站原单</dt><dd>{{detail.data.source_no || '无单号'}} · {{label(business,detail.data.business_type)}} · 任务 #{{detail.id}}</dd>
                <dt>所属连接</dt><dd>{{detail.data.connection?.adapter_name || ('连接 #'+detail.data.connection_id)}}</dd>
                <dt>外部单号</dt><dd>{{detail.data.external_doc_no || '尚无外部单号回执'}}</dd>
                <template v-if="detail.data.manual && detail.data.business_type==='payment'"><dt>原采购核销</dt><dd>
                    <template v-if="detail.data.receipt?.allocation?.verified">已冲 ¥{{detail.data.receipt.allocation.applied_amount}}，剩余 ¥{{detail.data.receipt.allocation.remaining_amount}}</template>
                    <template v-else>待核对 · 已有付款凭证不代表原采购已结清，请勿再次付款</template>
                </dd></template>
                <template v-if="detail.data.manual && detail.data.business_type==='receipt'"><dt>原销售单核销</dt><dd>
                    <template v-if="detail.data.receipt?.allocation?.verified">已冲 ¥{{detail.data.receipt.allocation.applied_amount}}，剩余 ¥{{detail.data.receipt.allocation.remaining_amount}}</template>
                    <template v-else>待核对 · 收款单号不代表这张销售单已冲销，无需再收款。</template>
                </dd></template>
                <template v-if="detail.data.manual && detail.data.receipt?.vendor_task_id"><dt>厂商原任务</dt><dd>#{{detail.data.receipt.vendor_task_id}} · 使用原任务确认，不重新开单</dd></template>
                <dt>外部单据 ID</dt><dd>{{detail.data.external_doc_id || '—'}}</dd>
                <dt>创建时间</dt><dd>{{time(detail.data.created_at)}}</dd>
                <template v-if="detail.data.succeeded_at"><dt>成功时间</dt><dd>{{time(detail.data.succeeded_at)}}</dd></template>
                <template v-if="detail.data.error_message"><dt>{{ receiptOnly || detail.data.status==='succeeded' ? '回执说明' : '处理说明' }}</dt><dd>{{detail.data.error_message}} <small>{{detail.data.error_code}}</small></dd></template>
            </dl>
            <el-alert v-if="detail.data.status==='failed'" :type="detail.data.can_retry?'info':'warning'" :closable="false" show-icon :title="detail.data.can_retry?'请先补齐资料映射，再重试这张原单。':'请先核对外部原单。此处不能重复发送，也不代表款项未登记。'" />
            <el-alert v-if="detail.data.status==='unverified'" type="warning" title="回执不足以确认当前原单，请先核对，不要重新发送。" :closable="false" show-icon />
            <el-alert v-if="detail.data.manual && detail.data.status==='awaiting_confirmation'" type="info" title="关联后由经办人在原单确认发送，此处不发单、不收付款。" :closable="false" />
            <el-collapse class="mt-4"><el-collapse-item title="查看任务数据快照" name="payload"><pre class="ic-code">{{JSON.stringify(detail.data.payload,null,2)}}</pre></el-collapse-item></el-collapse>
            </template>
        </div>
        <template #footer><el-button @click="detail.visible=false">关闭</el-button><el-button v-if="detail.data" :disabled="busy" @click="detail.open(detail.id)">刷新结果</el-button>
            <el-button v-if="!receiptOnly && detail.data?.manual && permission('task_review')" :loading="busy" @click="original(true)">核对原单回执</el-button>
            <el-button v-if="!receiptOnly && detail.data?.manual && adapterPanel && detail.data.receipt?.vendor_task_id && permission('task_review')" type="primary" :disabled="busy" @click="openVendor">处理本单{{ detail.data.business_type === 'payment' ? '付款凭证' : '采购挂账' }}</el-button>
            <el-button v-if="!receiptOnly && detail.data?.can_prepare && permission('task_prepare')" type="primary" :loading="busy" @click="original(false)">关联已有原单</el-button>
            <el-button v-if="!receiptOnly && detail.data?.can_retry && permission('task_retry')" type="primary" :loading="busy" @click="retry">重试原任务</el-button></template>
    </el-drawer>
    <component v-if="activePanel && detail.visible" :is="activePanel" ref="vendor" @finished="vendorClosed" />
</template>
<script setup lang="ts">
import { computed, nextTick, ref, shallowRef, type Component } from 'vue'
import { ElMessage } from 'element-plus'
import { api } from '../../../api/console'
import { useDetail,useAction,confirmAction,permission,label,states,business,time } from '../useConsole'
const emit=defineEmits(['changed','finished'])
const detail=useDetail(api.task),{busy,run}=useAction()
const receiptOnly=ref(false)
const panels = import.meta.glob<{default:Component}>('/src/addon/*/extensions/erp-bridge-*.vue')
const activePanel=shallowRef<Component>(), vendor=ref<{open:(id:number,scope:Record<string,any>)=>void}>()
const adapterPanel=computed(()=>{
    const d=detail.data, code=d?.connection?.adapter_code
    if (!d || !['purchase','payment'].includes(d.business_type) || typeof code!=='string' || !/^[a-z][a-z0-9_]{0,40}$/.test(code)) return undefined
    const hits=Object.keys(panels).filter(path=>path.endsWith('/erp-bridge-'+code+'.vue'))
    return hits.length===1 ? panels[hits[0]] : undefined
})
function openVendor(){run(async()=>{
    const current=detail.data, id=detail.id, loader=adapterPanel.value
    if (!current || !loader || !detail.visible) return
    const receipt=await api.reviewOriginal(id)
    if (!detail.visible || detail.id!==id) return
    await detail.open(id)
    const d=detail.data, taskId=Number(receipt?.vendor_task_id)
    if (!detail.visible || detail.id!==id || !d || d.id!=id || loader!==adapterPanel.value) return
    emit('changed',id,d)
    if (!['linked','confirmed'].includes(receipt?.status) || d.receipt?.status!==receipt.status ||
        !Number.isSafeInteger(taskId) || taskId<1 || taskId!==Number(d.receipt?.vendor_task_id)) {
        ElMessage.warning('未核对到唯一的原任务，请查看本单回执，不要重复发送。'); return
    }
    const mod=await loader()
    if (!detail.visible || detail.id!==id) return
    activePanel.value=mod.default; await nextTick()
    if (detail.visible && detail.id===id) vendor.value?.open(taskId,{bridgeKind:d.business_type,settlement_id:d.business_type==='payment'?d.source_id:0,readOnly:d.connection?.status!=='enabled'||receipt.status==='confirmed'})
})}
function vendorClosed(){
    activePanel.value=undefined
    if (detail.visible && permission('task_review')) original(true)
}
function original(readOnly:boolean){run(async()=>{
    const id=detail.id
    await (readOnly ? api.reviewOriginal(id) : api.prepareOriginal(id))
    if(detail.visible && detail.id===id) await detail.open(id)
    emit('changed',id,detail.id===id?detail.data:null)
})}
function retry(){run(async()=>{const id=detail.id;if(!await confirmAction('将按现有映射重新发送原任务，可能生成外部单据。请确认已补齐资料。','重试原任务？','确认重试'))return
    try{const r=await api.retry(id);if(r.status==='succeeded')ElMessage.success('同步成功');else if(r.status==='failed')ElMessage.warning('本次重试未成功，请查看异常原因');else ElMessage.info('任务状态已更新，请查看结果')}
    finally{emit('changed');if(detail.visible && detail.id===id) await detail.open(id)} })}
function open(id:number, readOnly=false) { receiptOnly.value=readOnly; return detail.open(id) }
defineExpose({open})
</script>
