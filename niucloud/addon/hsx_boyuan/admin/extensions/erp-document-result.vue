<template>
    <el-drawer v-model="visible" :title="receipt ? '博远收款凭证' : '博远原单核对'" size="min(920px, 96vw)" append-to-body destroy-on-close
        :before-close="beforeClose" :close-on-click-modal="false" @closed="emit('finished')">
        <el-alert v-if="problem" type="warning" :title="problem" :closable="false" show-icon />
        <p v-else-if="loading" role="status">正在读取原单…</p>
        <OriginalSaleReceiptPanel v-else-if="visible && receipt" ref="receiptView" :key="version" :sale-id="receipt.saleId" :settlement-id="receipt.sid" :task-id="receipt.task" :read-only="readOnly" />
        <PurchaseReview v-else-if="visible" ref="review" :key="version" embedded :original-task-id="purchaseId"
            :original-source-id="sourceId" :payment-target="payment" :read-only="readOnly" />
    </el-drawer>
</template>
<script setup lang="ts">
import { onUnmounted, ref } from 'vue'
import PurchaseReview from '../views/purchase.vue'
import OriginalSaleReceiptPanel from '../components/OriginalSaleReceiptPanel.vue'
import {getPaymentTask} from '../api/purchase'
import {purchaseRouteId as id} from '../utils/purchase-form'
const emit=defineEmits<{(event:'finished'):void}>()
const visible=ref(false), loading=ref(false), problem=ref(''), version=ref(0), purchaseId=ref(0), sourceId=ref(0), readOnly=ref(false)
const payment=ref<{taskId:number,settlementId:number}>(), review=ref<InstanceType<typeof PurchaseReview>>()
const receipt=ref<{saleId:number;sid:number;task:number}>(),receiptView=ref<InstanceType<typeof OriginalSaleReceiptPanel>>()
async function open(taskId:number, context:Record<string,any>={}) {
    const token=++version.value
    problem.value=''; payment.value=undefined; receipt.value=undefined; purchaseId.value=0; sourceId.value=0; loading.value=false
    visible.value=true; readOnly.value=context.readOnly===true
    try {
        if(context.kind==='receipt' || context.bridgeKind==='receipt') {
            const sid=id(context.settlement_id), saleId=id(context.sale_id), task=id(taskId)
            if(!sid || !saleId || (context.receipt_task_id && id(context.receipt_task_id)!==task)
              || (!task && id(context.original_source_id)!==sid)) throw Error('未能匹配销售原单及已有收款凭证，请刷新核对。')
            receipt.value={sid,saleId,task}
        } else if (context.bridgeKind==='payment') {
            const sid=id(context.settlement_id)
            if (!sid || !id(taskId)) throw Error('未能定位这张付款凭证，请返回原单刷新。')
            loading.value=true
            const row=(await getPaymentTask(taskId))?.data
            if (token!==version.value || !visible.value) return
            purchaseId.value=id(row?.parent_purchase_task_id)
            if (!purchaseId.value || id(row?.task?.id)!==id(taskId) || id(row?.source?.settlement_id)!==sid) throw Error('付款回执不属于当前凭证，请核对原单，不要重新登记。')
            payment.value={taskId:id(taskId),settlementId:sid}
        } else if (context.kind==='payment') {
            purchaseId.value=id(context.purchase_task_id)
            if (!purchaseId.value || purchaseId.value!==id(taskId) || !id(context.settlement_id)) throw Error('未能唯一匹配本凭证与采购单，请先核对原单。')
            payment.value={taskId:id(context.payment_task_id),settlementId:id(context.settlement_id)}
        } else if (id(taskId)) {
            purchaseId.value=id(taskId)
            if (context.purchase_task_id && purchaseId.value!==id(context.purchase_task_id)) throw Error('原采购关联不一致，未打开其他任务。')
        } else if (id(context.purchase_source_id) && !readOnly.value) sourceId.value=id(context.purchase_source_id)
        else throw Error('尚无可核对的原单，请刷新记录或联系管理员。')
    } catch(e:any) { if (token===version.value) problem.value=e?.msg || e?.message || '原单读取失败；没有再次提交。' }
    finally { if (token===version.value) loading.value=false }
}
async function beforeClose(done:()=>void) { if(receiptView.value && !receiptView.value.mayClose())return; if (!review.value || await review.value.mayClose()) done() }
onUnmounted(()=>{ ++version.value })
defineExpose({open})
</script>
