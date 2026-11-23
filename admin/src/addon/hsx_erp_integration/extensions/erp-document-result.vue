<template><TaskDetail ref="detail" @changed="changed" @finished="emit('finished')" /></template>
<script setup lang="ts">
import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import { api } from '../api/console'
import TaskDetail from '../views/integration/components/TaskDetail.vue'
const emit=defineEmits(['updated','finished'])
const detail=ref<InstanceType<typeof TaskDetail>>()
const busy=ref(false)
async function open(id:number,context?:Record<string,unknown>){
    if(id>0) { await detail.value?.open(id, context?.readOnly===true); return }
    const source=Number(context?.purchase_source_id);
    if(busy.value || !Number.isSafeInteger(source) || source<=0) return
    busy.value=true
    try {
        const payment=context?.kind==='payment', settlement=Number(context?.original_source_id)
        if(payment && (!Number.isSafeInteger(settlement)||settlement<=0)) return
        const r=await (payment?api.capturePaymentSource(settlement,source):api.capturePurchaseSource(source))
        if(!Number.isSafeInteger(Number(r.task_id)) || Number(r.task_id)<1) throw new Error('没有取得本地桥任务，请刷新本单，不要重新开单。')
        ElMessage.success(payment?'已关联原付款凭证，没有再次付款。':'已关联原采购，仅核对本地记录，未发送新单。')
        await detail.value?.open(Number(r.task_id))
    } catch(e:any){ ElMessage.warning(e?.msg||e?.message||'暂时无法关联，请刷新原单后核对。') }
    finally {busy.value=false}
}
defineExpose({ open })
function changed(id:number, data:any) {
    if (!Number.isSafeInteger(id) || !data || Number(data.id)!==id) return
    const receipt=data.receipt || {}, payment=data.business_type==='payment'
    const confirmed=data.status==='succeeded' && receipt.status==='confirmed' && Number(receipt.vendor_task_id)>0 &&
        typeof receipt.external_no==='string' && !!receipt.external_no && receipt.external_no===data.external_doc_no
    const statuses:Record<string,string>={awaiting_confirmation:'待人工关联',linked:'已关联原任务',waiting_source:'等待原单',blocked:'需人工处理',failed:'需人工处理'}
    let nextStep=data.business_type==='sale'?'出库回执不代表已收款。':'在原单内查看或核对本次采购批次。'
    if (payment) {
        nextStep='这里只跟踪已有付款登记，不代表再次出款或本机应付已结清。'
        if (confirmed) nextStep=receipt.allocation?.verified===true
            ? '付款凭证已登记；任务内可查看其采购批次核销证据，批次金额不能直接视为本机结清。'
            : '已登记付款凭证；尚无核销到原采购的证据，不代表本机已结清。'
    }
    emit('updated',id,{status_label:confirmed?(payment?'付款单已登记':'采购回执已核实'):(statuses[data.status]||'待核对'),
        tone:confirmed?'success':(['linked','waiting_source'].includes(data.status)?'info':'warning'),
        external_no:confirmed?receipt.external_no:'',next_step:nextStep})
}
</script>
