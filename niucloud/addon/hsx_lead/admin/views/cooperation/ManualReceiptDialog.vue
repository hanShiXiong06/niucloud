<template>
    <el-dialog v-model="visible" title="确认线下微信收款" width="min(520px, 94vw)" top="10vh" :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy" :before-close="beforeClose" @closed="clearView">
        <el-alert v-if="error" :title="error" type="warning" :closable="false" show-icon class="manual-receipt-notice"><template #default><el-button link :disabled="busy || loading" @click="read">核对原登记</el-button></template></el-alert>
        <el-alert v-if="recoveredMessage" :title="recoveredMessage" type="info" :closable="false" show-icon class="manual-receipt-notice" />
        <div v-loading="loading" class="manual-receipt-body">
            <template v-if="preview.order">
                <el-descriptions :column="1" size="small" class="manual-receipt-summary">
                    <el-descriptions-item label="订单号">{{ preview.order.order_no }}</el-descriptions-item>
                    <el-descriptions-item label="付款会员">{{ payer.name }}<span v-if="payer.no"> · UID：{{ payer.no }}</span><span v-else-if="payer.id"> · 会员ID：{{ payer.id }}</span></el-descriptions-item>
                    <el-descriptions-item label="付款手机号">{{ payer.mobile || '未登记' }}</el-descriptions-item>
                    <el-descriptions-item label="订单应付"><strong>{{ coopMoney(orderAmount) }}</strong><span class="manual-receipt-purpose">{{ coopLabel(preview.order.purpose) }}</span></el-descriptions-item>
                </el-descriptions>
                <el-alert v-if="isClosed" title="订单已关闭，不能确认收款、充值、重新开通或继续处理；不会新建或重开订单。" type="warning" :closable="false" show-icon class="manual-receipt-notice" />
                <template v-if="receipt.id">
                    <div class="manual-receipt-result"><el-tag :type="receipt.status==='posted'?'success':'warning'">{{ receipt.status_label || receiptStatus(receipt.status) }}</el-tag><strong>{{ coopMoney(receipt.amount) }}</strong></div>
                    <el-alert :title="receipt.message || (receipt.status==='posted' ? '原收款处理记录已确认。' : '确认请求已记录，充值与本单支付结果待核对，请勿重复操作。')" :type="receipt.status==='posted'?'success':'warning'" :closable="false" show-icon class="manual-receipt-notice" />
                    <template v-if="isBalanceRecord && receipt.status==='posted'">
                        <el-descriptions :column="1" border size="small"><el-descriptions-item label="线下来源">微信扫码收款（管理员确认）</el-descriptions-item><el-descriptions-item label="本次充值">{{ coopMoney(receipt.amount) }}</el-descriptions-item><el-descriptions-item label="本单支付">余额支付 {{ coopMoney(receipt.amount) }}</el-descriptions-item><el-descriptions-item label="可用余额">{{ coopMoney(receipt.balance_before) }} → {{ coopMoney(receipt.balance_after) }}</el-descriptions-item></el-descriptions>
                    </template>
                    <el-alert v-if="!isBalanceRecord" title="历史线下收款记录仅供核对，不会转换为当前余额流程再次充值。" type="info" :closable="false" show-icon class="manual-receipt-notice" />
                    <el-collapse class="manual-receipt-ledger"><el-collapse-item title="查看处理记录" name="ledger"><el-descriptions :column="1" size="small"><el-descriptions-item :label="isBalanceRecord?'内部登记号':'历史凭证编号'">{{ receipt.registration_no || receipt.external_no || '尚未返回' }}</el-descriptions-item><el-descriptions-item v-if="receipt.received_at" label="登记时间">{{ coopTime(receipt.received_at) }}</el-descriptions-item><el-descriptions-item v-if="receipt.recharge_log_id" label="充值台账">#{{ receipt.recharge_log_id }}</el-descriptions-item><el-descriptions-item v-if="receipt.debit_log_id" label="支付台账">#{{ receipt.debit_log_id }}</el-descriptions-item></el-descriptions><p v-if="isBalanceRecord" class="manual-receipt-note">内部登记号用于系统核对，不是微信交易流水号。</p></el-collapse-item></el-collapse>
                </template>
                <template v-else-if="!isClosed">
                    <el-alert :title="preview.message || '请先核实已实际收到线下微信款；确认后将充值至原付款会员余额，并立即用于本订单余额支付。'" type="info" :closable="false" show-icon class="manual-receipt-notice" />
                    <el-form v-if="preview.can_confirm===true && canConfirm" ref="formRef" :model="form" :rules="rules" label-position="top" :disabled="busy || loading || readRequired || !!attempt" @submit.prevent>
                        <el-form-item label="已实际收到的线下微信款（元）" prop="amount"><el-input v-model="form.amount" inputmode="decimal" maxlength="15"><template #prefix>¥</template></el-input></el-form-item>
                        <el-alert v-if="amountMismatch" title="实收金额必须与订单应付金额一致；不一致时不充值、不扣款，也不调整订单价格。" type="warning" :closable="false" show-icon />
                    </el-form>
                    <el-alert v-if="!canConfirm" title="当前账号仅可查看，不能确认收款或操作会员余额。" type="info" :closable="false" show-icon class="manual-receipt-notice" />
                </template>
            </template>
            <el-empty v-else-if="!loading && !error" description="请先读取原订单" :image-size="72" />
        </div>
        <template #footer><div class="manual-receipt-footer"><el-button :disabled="busy" @click="close">关闭</el-button><el-button v-if="readRequired" type="primary" :loading="loading" :disabled="busy" @click="read">核对原登记</el-button><el-button v-else-if="canContinue" type="primary" :loading="busy" :disabled="loading" @click="retry">核对并继续原处理</el-button><el-button v-else-if="!receipt.id && preview.can_confirm===true && canConfirm && !isClosed" type="primary" :loading="busy" :disabled="loading || amountMismatch" @click="submit">确认收款并支付本单</el-button><el-button v-else-if="receipt.id && canReadReceipt" :loading="loading" :disabled="busy" @click="read">刷新原记录</el-button></div></template>
    </el-dialog>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import { previewManualReceipt, confirmManualReceipt, getManualReceipt, retryManualReceipt, type ManualReceiptConfirmInput } from '../../api/manualReceipt'
import { manualRequestId } from '../../composables/manualReceipt'
import { hasLeadPagePermission } from '../../composables/navigation'
import { businessAmountCents } from '../../composables/business'
import { coopError, coopLabel, coopMoney, coopTime, type CoopRow } from '../../composables/cooperation'
const emit=defineEmits<{(event:'changed'):void}>(),user=useUserStore(),balanceMode='balance_recharge_v1'
const allowed=(name:string)=>hasLeadPagePermission(user,'hsx_lead_cooperation_finance')&&hasLeadPagePermission(user,`hsx_lead_cooperation_order_manual_receipt${name?'_'+name:''}`)
const canView=computed(()=>allowed('preview')),canConfirm=computed(()=>canView.value&&allowed('')),canReadReceipt=computed(()=>canView.value&&allowed('read')),canRetry=computed(()=>canReadReceipt.value&&allowed('retry'))
const visible=ref(false),loading=ref(false),busy=ref(false),readRequired=ref(false),error=ref(''),recoveredMessage=ref(''),preview=ref<CoopRow>({}),receipt=ref<CoopRow>({}),form=reactive({amount:'99.00'}),formRef=ref<any>(null),attempt=ref<ManualReceiptConfirmInput|null>(null)
const orderAmount=computed(()=>preview.value.order_amount??preview.value.order?.amount)
const isClosed=computed(()=>preview.value.order?.status==='closed'||receipt.value.order?.status==='closed'||receipt.value.source_order?.status==='closed')
const amountMismatch=computed(()=>{const value=businessAmountCents(form.amount),due=businessAmountCents(orderAmount.value);return value===null||value<=0||due===null||value!==due})
const isBalanceRecord=computed(()=>receipt.value.settlement_mode===balanceMode)
const canContinue=computed(()=>!!receipt.value.id&&receipt.value.status!=='posted'&&receipt.value.can_retry===true&&isBalanceRecord.value&&canRetry.value&&!isClosed.value)
const payer=computed(()=>{const order=preview.value.order||{},p=preview.value.payer||{};return{id:Number(p.member_id||order.member_id||0),name:String(p.nickname||p.name||order.payer_name||'原付款会员'),no:String(p.member_no||order.payer_member_no||''),mobile:String(p.mobile||order.payer_mobile||'')}})
const rules={amount:[{validator:(_rule:unknown,_value:unknown,done:(error?:Error)=>void)=>done(amountMismatch.value?new Error('实收金额必须与原订单应付金额一致，最多两位小数'):undefined),trigger:['blur','change']}]}
const identity=()=>{const site=Number(user.siteInfo?.site_id||0),token=String(user.token||'');return site>0&&Number.isSafeInteger(site)&&token?`${site}:${token}`:''}
let selectedId=0,sequence=0,disposed=false,requestId=''
const attempts=new Map<number,{requestId:string;payload:ManualReceiptConfirmInput}>()
const current=(who:string,id:number,turn=sequence)=>!disposed&&!!who&&who===identity()&&canView.value&&visible.value&&selectedId===id&&turn===sequence
const receiptStatus=(status:string)=>({recorded:'确认请求已记录',processing:'正在核对充值与支付',posted:'原处理已完成',attention:'确认请求待核对'}[status]||'结果待核对')
function validateReceipt(value:CoopRow,id:number,expected?:CoopRow){
    const source=Number(value?.order_id),target=Number(value?.target_order_id||0),amount=businessAmountCents(value?.amount)
    if(!Number.isSafeInteger(Number(value?.id))||Number(value.id)<=0||!Number.isSafeInteger(source)||source<=0||!Number.isSafeInteger(target)||target<0||source!==id&&target!==id||!['recorded','processing','posted','attention'].includes(value.status)||amount===null||amount<=0||typeof value.can_retry!=='boolean')throw Error('原收款记录未完整返回，请只读取原记录，不要重复确认')
    if(value.source_order&&Number(value.source_order.id)!==source||target>0&&value.order&&Number(value.order.id)!==target||id!==source&&(Number(value.source_order?.id)!==source||Number(value.order?.id)!==target))throw Error('收款记录与当前订单未对应，请重新核对')
    if(expected&&businessAmountCents(value.amount)!==businessAmountCents(expected.amount))throw Error('返回金额与本次确认不一致，请只核对原记录')
    if(value.settlement_mode===balanceMode){
        if(source!==id||target!==source)throw Error('余额收款必须对应当前原订单，不允许新建或重开订单')
        if(value.status==='posted'&&(!Number.isSafeInteger(Number(value.recharge_log_id))||Number(value.recharge_log_id)<=0||!Number.isSafeInteger(Number(value.debit_log_id))||Number(value.debit_log_id)<=0||businessAmountCents(value.balance_before)===null||businessAmountCents(value.balance_after)===null||value.order?.status!=='paid'))throw Error('充值与本单余额支付尚未完整核实，请读取原记录，勿重复充值')
    }else if(expected?.expected_fingerprint)throw Error('当前未返回配套余额收款结果，请核对配套后端，不要重复确认')
    return value
}
async function open(row:CoopRow){
    if(busy.value||!canView.value)return
    const id=Number(row.id);if(!Number.isSafeInteger(id)||id<=0)return
    sequence++;selectedId=id;preview.value={};receipt.value={};error.value='';recoveredMessage.value='';readRequired.value=false;form.amount='99.00'
    const cached=attempts.get(id);requestId=cached?.requestId||manualRequestId();attempt.value=cached?.payload||null;if(cached)form.amount=cached.payload.amount
    visible.value=true;await read()
}
async function read(){
    const who=identity(),id=selectedId;if(!current(who,id)||loading.value||busy.value)return false
    const turn=++sequence;loading.value=true;error.value='';recoveredMessage.value=''
    try{
        const existingId=Number(receipt.value.id)
        if(existingId&&canReadReceipt.value){const response=await getManualReceipt(existingId);if(!current(who,id,turn)||!canReadReceipt.value)return false;const value=validateReceipt(response.data,id,receipt.value);if(Number(value.id)!==existingId)throw Error('未返回原收款记录，请重新核对');receipt.value=value;readRequired.value=false;if(value.status==='posted'){attempts.delete(id);attempt.value=null};return true}
        const response=await previewManualReceipt(id,attempt.value?requestId:'');if(!current(who,id,turn))return false
        const value=response.data
        if(Number(value?.order?.id)!==id||!['unpaid','paid','closed'].includes(value.order.status)||businessAmountCents(value.order_amount??value.order.amount)===null||value.default_amount!=='99.00'||typeof value.can_confirm!=='boolean'||typeof value.source_fingerprint!=='string'||!/^[a-f0-9]{64}$/.test(value.source_fingerprint))throw Error('原订单收款资料未完整返回，请核对配套后端后重新读取')
        preview.value=value;receipt.value=value.receipt?validateReceipt(value.receipt,id,attempt.value||undefined):{};readRequired.value=false
        if(attempt.value&&!receipt.value.id&&value.can_confirm===true&&value.order.status==='unpaid'){attempt.value=null;attempts.delete(id);recoveredMessage.value='服务器尚无本次确认记录，金额已保留，请核对后再确认。'}
        if(receipt.value.id){attempts.delete(id);attempt.value=null}
        return true
    }catch(cause){if(current(who,id,turn)){error.value=coopError(cause,'原登记结果未核实，请重新读取');readRequired.value=true};return false}
    finally{if(turn===sequence&&!disposed)loading.value=false}
}
async function submit(){
    const who=identity(),id=selectedId,turn=sequence
    if(!current(who,id,turn)||!canConfirm.value||busy.value||loading.value||readRequired.value||receipt.value.id||preview.value.can_confirm!==true||preview.value.order?.status!=='unpaid'||isClosed.value)return
    if(amountMismatch.value){error.value='实收金额必须与订单应付金额一致；未充值、未扣款，也未修改订单价格。';return}
    busy.value=true;error.value='';recoveredMessage.value='';let sent=false
    try{
        let payload=attempt.value
        if(!payload){if(!formRef.value)return;try{await formRef.value.validate()}catch{return};if(!current(who,id,turn)||!canConfirm.value||amountMismatch.value)return;payload={amount:(businessAmountCents(form.amount)!/100).toFixed(2),request_id:requestId,expected_fingerprint:preview.value.source_fingerprint,confirm:true}}
        const frozen=JSON.parse(JSON.stringify(payload)) as ManualReceiptConfirmInput
        const identityText=payer.value.no?`UID ${payer.value.no}`:payer.value.id?`会员ID ${payer.value.id}`:'原付款会员'
        await ElMessageBox.confirm(`请确认已实际收到订单 ${preview.value.order.order_no} 的线下微信款 ${coopMoney(frozen.amount)}。将为${identityText}${payer.value.mobile?'（'+payer.value.mobile+'）':''}充值 ${coopMoney(frozen.amount)}，并立即余额支付 ${coopMoney(frozen.amount)} 完成本单；两步同时成功或同时回滚，不会再次向微信扣款。`,'确认已实际收到线下微信款',{confirmButtonText:'已收款，充值并支付',cancelButtonText:'返回核对',type:'warning',distinguishCancelAndClose:true})
        if(!current(who,id,turn)||!canConfirm.value||isClosed.value||preview.value.order?.status!=='unpaid')return
        attempts.set(id,{requestId,payload:frozen});attempt.value=frozen;sent=true
        const response=await confirmManualReceipt(id,frozen);if(!current(who,id,turn))return
        receipt.value=validateReceipt(response.data,id,frozen);readRequired.value=false
        if(receipt.value.status==='posted'){attempts.delete(id);attempt.value=null;ElMessage.success('已充值并完成本单余额支付')}else ElMessage.warning(receipt.value.message||receiptStatus(receipt.value.status))
        emit('changed')
    }catch(cause){if(current(who,id,turn)&&cause!=='cancel'&&cause!=='close'){error.value=coopError(cause,'确认结果待核对，请读取原记录，勿重复充值');if(sent)readRequired.value=true}}
    finally{busy.value=false}
}
async function retry(){
    const who=identity(),id=selectedId,turn=sequence,receiptId=Number(receipt.value.id)
    if(!current(who,id,turn)||!canContinue.value||busy.value||loading.value||readRequired.value||!receiptId)return
    busy.value=true;error.value='';const original=JSON.parse(JSON.stringify(receipt.value))
    try{
        await ElMessageBox.confirm(`继续核对同一登记的 ${coopMoney(receipt.value.amount)} 充值与本单余额支付？沿用原记录，不重复充值，不重新创建或打开订单。`,'核对原充值与支付',{confirmButtonText:'核对并继续原处理',cancelButtonText:'取消',type:'warning',distinguishCancelAndClose:true})
        if(!current(who,id,turn)||!canContinue.value||Number(receipt.value.id)!==receiptId)return
        const response=await retryManualReceipt(receiptId);if(!current(who,id,turn))return
        const value=validateReceipt(response.data,id,original);if(Number(value.id)!==receiptId)throw Error('未对应原收款记录，请只读取原记录');receipt.value=value
        if(value.status==='posted'){attempts.delete(id);attempt.value=null;ElMessage.success('原充值与本单余额支付已完成')}else ElMessage.warning(value.message||receiptStatus(value.status))
        emit('changed')
    }catch(cause){if(current(who,id,turn)&&cause!=='cancel'&&cause!=='close'){error.value=coopError(cause,'原结果待核对，请读取原记录，勿重复充值');readRequired.value=true}}
    finally{busy.value=false}
}
function clearView(){sequence++;selectedId=0;loading.value=false;preview.value={};receipt.value={};form.amount='99.00';readRequired.value=false;error.value='';recoveredMessage.value='';attempt.value=null;requestId=''}
function close(){if(!busy.value){visible.value=false;clearView()}}
function beforeClose(done:()=>void){if(!busy.value){clearView();done()}}
watch(()=>[identity(),canView.value,canConfirm.value,canReadReceipt.value,canRetry.value].join('|'),()=>{visible.value=false;clearView();attempts.clear()})
onBeforeUnmount(()=>{disposed=true;sequence++;attempts.clear()})
defineExpose({open})
</script>
<style scoped>
.manual-receipt-notice{margin:14px 0}.manual-receipt-body{min-height:100px;max-height:calc(76vh - 170px);overflow-y:auto;overflow-wrap:anywhere;padding:0 2px}.manual-receipt-summary{margin-bottom:12px}.manual-receipt-purpose{margin-left:12px;color:var(--el-text-color-secondary);font-size:12px}.manual-receipt-result{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:18px 0}.manual-receipt-result strong{font-size:24px;color:var(--el-text-color-primary)}.manual-receipt-note{font-size:12px;line-height:1.7;color:var(--el-text-color-secondary);margin:8px 0}.manual-receipt-ledger{margin-top:14px}.manual-receipt-footer{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.manual-receipt-footer .el-button+.el-button{margin-left:0}
</style>
