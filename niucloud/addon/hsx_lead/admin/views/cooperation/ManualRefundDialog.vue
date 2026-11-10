<template>
    <el-dialog v-model="visible" title="确认线下实际退款" width="min(640px, 94vw)" top="8vh" :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy" :before-close="beforeClose" @closed="clearView">
        <el-alert v-if="error" :title="error" type="warning" :closable="false" show-icon class="manual-refund-notice"><template #default><el-button link :disabled="busy || loading" @click="read">核对原退款</el-button></template></el-alert>
        <el-alert v-if="recoveredMessage" :title="recoveredMessage" type="info" :closable="false" show-icon class="manual-refund-notice" />
        <div v-loading="loading" class="manual-refund-body">
            <el-descriptions v-if="source.order_id" :column="1" border size="small"><el-descriptions-item label="原收款单">{{ source.order_no || '#'+source.order_id }}</el-descriptions-item><el-descriptions-item label="已批准退款号">{{ source.refund_no }}</el-descriptions-item><el-descriptions-item label="本笔退款金额">{{ coopMoney(source.amount) }}</el-descriptions-item></el-descriptions>
            <el-alert v-if="!terminal" :title="attempt || Number(source.manual_refund_receipt_id)>0 ? '本笔已提交实际退款登记，请核对原结果，不要再次转账。' : source.manual_refund_message || '请先在系统外完成实际退款、核实对方已收到款项，再登记交易流水。本窗口不会自动转账。'" type="warning" :closable="false" show-icon class="manual-refund-notice" />
            <el-alert v-if="result.message" :title="result.message" :type="terminal?'success':'warning'" :closable="false" show-icon class="manual-refund-notice" />
            <el-tag v-if="terminal" type="success">实际退款已确认</el-tag>
            <ManualEvidenceFields v-else-if="source.can_confirm_manual_refund===true || attempt" ref="evidenceRef" v-model="evidence" refund amount-readonly :disabled="busy || loading || readRequired || !!attempt" />
            <el-alert v-if="attempt && !terminal" title="已保留本次实际退款信息，只能核对或继续同一登记；请勿再次向客户转账。" type="info" :closable="false" show-icon class="manual-refund-notice" />
        </div>
        <template #footer><div class="manual-refund-footer"><el-button :disabled="busy" @click="close">关闭</el-button><el-button v-if="readRequired" type="primary" :loading="loading" :disabled="busy" @click="read">核对原退款</el-button><el-button v-else-if="!terminal && (source.can_confirm_manual_refund===true || attempt)" type="primary" :loading="busy" :disabled="loading || !canOperate" @click="submit">{{ attempt ? '继续原退款登记' : '已实际退款，确认登记' }}</el-button><el-button v-else :loading="loading" :disabled="busy" @click="read">刷新原退款结果</el-button></div></template>
    </el-dialog>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import ManualEvidenceFields from './ManualEvidenceFields.vue'
import { confirmManualRefund } from '../../api/manualReceipt'
import { emptyManualEvidence, manualEvidencePayload, manualRequestId } from '../../composables/manualReceipt'
import { businessAmountCents } from '../../composables/business'
import { hasLeadPagePermission } from '../../composables/navigation'
import { coopError, coopMoney, type CoopRow } from '../../composables/cooperation'
const emit = defineEmits<{ (event:'changed'): void }>(), user = useUserStore()
const canOperate = computed(() => hasLeadPagePermission(user,'hsx_lead_cooperation_finance') && hasLeadPagePermission(user,'hsx_lead_cooperation_order_manual_refund'))
const visible = ref(false), busy = ref(false), loading = ref(false), readRequired = ref(false), error = ref(''),recoveredMessage=ref(''), source = ref<CoopRow>({}), result = ref<CoopRow>({}), evidence = ref(emptyManualEvidence()), evidenceRef = ref<InstanceType<typeof ManualEvidenceFields> | null>(null), attempt = ref<CoopRow | null>(null)
const terminal = computed(() => result.value.status==='posted' && result.value.refund_status==='success' || ['refunded','success'].includes(String(source.value.status)) || source.value.refund_status==='success')
const identity = () => { const site=Number(user.siteInfo?.site_id||0),token=String(user.token||'');return site>0&&Number.isSafeInteger(site)&&token?`${site}:${token}`:'' }
let selectedKey='',sequence=0,disposed=false,requestId='',reader:(()=>Promise<CoopRow|null>)|null=null
const attempts=new Map<string,{requestId:string;payload:CoopRow}>()
const keyOf=(value:CoopRow)=>`${Number(value.order_id)}:${String(value.refund_no||'')}`
const current=(actor:string,key:string,turn=sequence)=>!disposed&&!!actor&&actor===identity()&&canOperate.value&&visible.value&&key===selectedKey&&turn===sequence
function validSource(value:CoopRow,key:string){if(keyOf(value)!==key||!Number.isSafeInteger(Number(value.order_id))||Number(value.order_id)<=0||!String(value.refund_no||'')||businessAmountCents(value.amount)===null||Number(businessAmountCents(value.amount))<=0||typeof value.manual_refund_required!=='boolean'||typeof value.can_confirm_manual_refund!=='boolean')throw Error('原退款资料不完整，请返回原申请核对');return value}
async function open(value:CoopRow,readOriginal:()=>Promise<CoopRow|null>){
    if(busy.value||!canOperate.value)return
    const key=keyOf(value);if(!Number(value.order_id)||!String(value.refund_no||'')||typeof readOriginal!=='function')return
    sequence++;selectedKey=key;reader=readOriginal;source.value={};result.value={};error.value='';recoveredMessage.value='';readRequired.value=false;evidence.value=emptyManualEvidence()
    const cached=attempts.get(key);requestId=cached?.requestId||manualRequestId();attempt.value=cached?.payload||null;if(cached)evidence.value={...cached.payload,received_at:String(cached.payload.received_at)} as any
    visible.value=true;await read()
}
async function read(){
    const actor=identity(),key=selectedKey;if(!current(actor,key)||loading.value||busy.value||!reader)return false
    const turn=++sequence;loading.value=true;error.value='';recoveredMessage.value=''
    try{const value=await reader();if(!current(actor,key,turn))return false;source.value=validSource(value||{},key);readRequired.value=false;if(!attempt.value)evidence.value.amount=String(source.value.amount);if(attempt.value&&source.value.can_confirm_manual_refund===true&&Number(source.value.manual_refund_receipt_id||0)===0){attempt.value=null;attempts.delete(key);recoveredMessage.value='原退款尚无已保存的实际退款凭据，填写内容已保留。请核对后重新确认。'};if(terminal.value){attempts.delete(key);attempt.value=null};return true}
    catch(cause){if(current(actor,key,turn)){error.value=coopError(cause,'原退款未核实，请重新读取');readRequired.value=true};return false}
    finally{if(turn===sequence&&!disposed)loading.value=false}
}
async function submit(){
    const actor=identity(),key=selectedKey,turn=sequence
    if(!current(actor,key,turn)||busy.value||loading.value||readRequired.value||terminal.value||source.value.manual_refund_required!==true||source.value.can_confirm_manual_refund!==true&&!attempt.value)return
    busy.value=true;error.value='';recoveredMessage.value='';let sent=false
    try{
        let payload=attempt.value
        if(!payload){const validated=await evidenceRef.value?.validate();if(!validated||!current(actor,key,turn))return;payload={...manualEvidencePayload(evidence.value),request_id:requestId,refund_no:String(source.value.refund_no)}}
        const frozen=JSON.parse(JSON.stringify(payload));if(businessAmountCents(frozen.amount)!==businessAmountCents(source.value.amount))throw Error('本笔金额与已批准退款不一致，请核对原退款，不要修改金额')
        await ElMessageBox.confirm(`请确认已将 ${coopMoney(frozen.amount)} 实际退还给客户，退款号 ${source.value.refund_no}，交易流水 / 收据号 ${frozen.external_no}。这里只登记已发生的退款，不会自动转账。`,'核实实际退款到账',{confirmButtonText:'已核实退款，确认登记',cancelButtonText:'返回核对',type:'warning',distinguishCancelAndClose:true})
        if(!current(actor,key,turn))return
        attempts.set(key,{requestId,payload:frozen});attempt.value=frozen;sent=true
        const response=await confirmManualRefund(Number(source.value.order_id),frozen)
        if(!current(actor,key,turn))return
        const value=response.data
        if(Number(value?.order_id)!==Number(source.value.order_id)||value.refund_no!==source.value.refund_no||businessAmountCents(value.amount)!==businessAmountCents(source.value.amount)||!Number.isSafeInteger(Number(value.manual_receipt_id))||Number(value.manual_receipt_id)<=0||!['posted','attention'].includes(value.status)||!['success','wait'].includes(value.refund_status)||value.status==='posted'&&value.refund_status!=='success')throw Error('实际退款登记结果未完整确认，请核对原退款，勿再次转账')
        result.value=value;readRequired.value=false
        if(terminal.value){attempts.delete(key);attempt.value=null;ElMessage.success('实际退款已确认，退款台账已更新')}else ElMessage.warning(value.message||'退款凭据已记录，台账仍需核对，请勿再次转账')
        emit('changed')
    }catch(cause){if(current(actor,key,turn)&&cause!=='cancel'&&cause!=='close'){error.value=coopError(cause,'退款登记结果待核对，请读取原退款，勿再次转账');if(sent)readRequired.value=true}}
    finally{busy.value=false}
}
function clearView(){sequence++;selectedKey='';reader=null;source.value={};result.value={};error.value='';recoveredMessage.value='';loading.value=false;readRequired.value=false;evidence.value=emptyManualEvidence();attempt.value=null;requestId=''}
function close(){if(!busy.value){visible.value=false;clearView()}}
function beforeClose(done:()=>void){if(!busy.value){clearView();done()}}
watch(()=>[identity(),canOperate.value].join('|'),()=>{visible.value=false;clearView();attempts.clear()})
onBeforeUnmount(()=>{disposed=true;sequence++;attempts.clear()})
defineExpose({open})
</script>
<style scoped>
.manual-refund-body{min-height:100px;max-height:calc(78vh - 180px);overflow-y:auto;overflow-wrap:anywhere;padding:0 2px}.manual-refund-notice{margin:16px 0}.manual-refund-footer{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.manual-refund-footer .el-button+.el-button{margin-left:0}
</style>
