<template>
    <section>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" placeholder="搜索门店、单号或打款凭据" @search="search" @reload="load" />
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <div class="coop-surface"><HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="暂无对应的提现申请">
            <template #merchant="{row}"><div class="coop-cell"><strong>{{ row.merchant_name || row.merchant?.name || '申请门店' }}</strong><small>{{ row.withdrawal_no }}</small><small>{{ coopTime(row.create_time) }}</small></div></template>
            <template #amount="{row}"><strong class="coop-price">{{ coopMoney(row.amount) }}</strong></template>
            <template #payee="{row}"><div class="coop-cell"><strong>{{ payee(row).name || '待核对收款人' }}</strong><small>{{ payeeType(payee(row).type) }} · {{ payee(row).masked_account || payee(row).account || '账号未返回' }}</small></div></template>
            <template #status="{row}"><div class="coop-cell"><el-tag :type="coopTone(row.status)" size="small">{{ row.status==='paid'?'已登记实际打款':row.status==='rejected'?'已驳回并解冻':'待核对 / 资金已冻结' }}</el-tag><small v-if="row.receipt_no">凭证 {{ row.receipt_no }}</small></div></template>
            <template #actions="{row}"><div class="coop-actions"><el-button v-if="row.status==='pending'" link type="primary" @click="open(row,'paid')">核对并登记打款</el-button><el-button v-if="row.status==='pending'" link type="danger" @click="open(row,'reject')">驳回</el-button><el-button v-else link @click="open(row,'view')">查看记录</el-button></div></template>
        </HsxTable><div class="coop-pagination"><HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading" @change="changePage" /></div></div>
        <p class="coop-muted" style="margin-top:12px">申请提现会冻结对应余额。财务实际转账后才能登记打款；驳回会解冻，不会产生自动银行转账。</p>
        <HsxDrawer v-model="visible" :title="action==='paid'?'核对并登记实际打款':action==='reject'?'驳回提现申请':'提现处理记录'" :subtitle="selected.withdrawal_no || ''" size="sm" :confirm-loading="saving || detailLoading" :body-loading="detailLoading">
            <el-alert v-if="saveError" :title="saveError" type="error" :closable="false" show-icon class="coop-error" />
            <div class="coop-kv"><span>申请门店</span><span>{{ selected.merchant_name || selected.merchant?.name || '未返回' }}</span><span>提现金额</span><span class="coop-price">{{ coopMoney(selected.amount) }}</span><span>收款人</span><span>{{ payee(selected).name || '未返回' }}</span><span>收款方式</span><span>{{ payeeType(payee(selected).type) }}</span><span>收款账号</span><span>{{ payee(selected).account || payee(selected).masked_account || '未返回，不能确认打款' }}</span></div>
            <el-form v-if="action!=='view'" label-position="top" class="coop-form" :disabled="saving" style="margin-top:22px">
                <template v-if="action==='paid'"><el-alert title="这里仅登记财务已经完成的转账，不会自动从银行卡或微信转款。请核对真实收款人、账户和金额。" type="warning" show-icon :closable="false" class="coop-error" /><el-form-item label="实际转账凭证号"><el-input v-model="form.receipt_no" maxlength="100" placeholder="银行 / 微信 / 支付宝实际转账流水号" /></el-form-item><el-form-item label="经办说明（选填）"><el-input v-model="form.remark" type="textarea" :rows="3" maxlength="1000" /></el-form-item><el-checkbox v-model="form.confirm">我已核对收款人和金额，且实际转账已完成</el-checkbox></template>
                <el-form-item v-else label="驳回原因"><el-input v-model="form.reason" type="textarea" :rows="4" maxlength="1000" placeholder="让商户明确知道哪里需要修正，驳回后余额会解冻" /></el-form-item>
            </el-form>
            <div v-else class="coop-readout" style="margin-top:20px">{{ selected.remark || selected.reason || '处理记录已保留' }}<br />凭证：{{ selected.receipt_no || '未登记' }}<br />处理时间：{{ coopTime(selected.processed_at) }}</div>
            <template #footer><el-button :disabled="saving" @click="visible=false">关闭</el-button><el-button v-if="action!=='view'" :type="action==='reject'?'danger':'primary'" :disabled="detailLoading||!detailReady||(action==='paid'&&(!form.confirm||!String(form.receipt_no).trim()))" :loading="saving" @click="submit">{{ action==='paid'?'确认已实际打款':'驳回并解冻余额' }}</el-button></template>
        </HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import CooperationFilter from './CooperationFilter.vue'
import { getCooperationWithdrawal, getCooperationWithdrawals, payCooperationWithdrawal, rejectCooperationWithdrawal } from '../../api/cooperation'
import { coopError, coopMoney, coopTime, coopTone, useCooperationList, type CoopRow } from '../../composables/cooperation'
const statuses=[{value:'pending',label:'待核对 / 已冻结'},{value:'paid',label:'已登记打款'},{value:'rejected',label:'已驳回并解冻'}]
const {rows,total,loading,error,query,load,search,changePage}=useCooperationList(getCooperationWithdrawals)
const columns=[{prop:'withdrawal_no',label:'申请门店 / 提现单',slot:'merchant',minWidth:250},{prop:'amount',label:'提现金额',slot:'amount',width:135},{prop:'payee',label:'收款资料',slot:'payee',minWidth:210},{prop:'status',label:'当前处理状态',slot:'status',minWidth:210},{prop:'actions',label:'操作',slot:'actions',width:190,fixed:'right' as const}]
const payee=(row:CoopRow)=>row.payee||{type:row.payee_type,name:row.payee_name,account:row.payee_account,masked_account:row.masked_account}
const payeeType=(value:unknown)=>({bank:'银行卡',wechat:'微信',alipay:'支付宝'}[String(value)]||'未返回')
const visible=ref(false),selected=ref<CoopRow>({}),action=ref<'paid'|'reject'|'view'>('view'),saving=ref(false),saveError=ref(''),detailLoading=ref(false),detailReady=ref(false),form=reactive({receipt_no:'',remark:'',reason:'',confirm:false});let detailSequence=0,disposed=false
async function open(row:CoopRow,value:'paid'|'reject'|'view'){const token=++detailSequence;selected.value={...row};action.value=value;Object.assign(form,{receipt_no:'',remark:'',reason:'',confirm:false});saveError.value='';visible.value=true;detailReady.value=false;detailLoading.value=true;try{const result=await getCooperationWithdrawal(Number(row.id));if(token!==detailSequence||disposed||!visible.value)return;selected.value=result.data||{};detailReady.value=Number(selected.value.id)===Number(row.id);if(!detailReady.value)throw new Error('未返回完整提现记录，不能继续处理')}catch(cause){if(token===detailSequence&&!disposed)saveError.value=coopError(cause,'提现详情读取失败，请确认具备资金及全站业务权限')}finally{if(token===detailSequence&&!disposed)detailLoading.value=false}}
async function submit(){if(saving.value||action.value==='view'||!detailReady.value||detailLoading.value)return;saveError.value='';if(selected.value.status!=='pending'){saveError.value='该申请已不在待处理状态，请关闭并刷新核对';return}if(action.value==='paid'&&(!form.confirm||!form.receipt_no.trim())){saveError.value='请填写实际转账凭证号，并确认已实际打款';return}if(action.value==='reject'&&form.reason.trim().length<4){saveError.value='请填写明确的驳回原因（至少4个字符）';return}saving.value=true;try{await ElMessageBox.confirm(action.value==='paid'?`确认已向“${payee(selected.value).name||'该收款人'}”实际支付 ${coopMoney(selected.value.amount)}？系统只记账，不再执行转账。`:`确认驳回该 ${coopMoney(selected.value.amount)} 提现申请并解冻余额？`,'再次核对资金操作',{type:'warning',confirmButtonText:action.value==='paid'?'已核对，登记打款':'确认驳回并解冻',cancelButtonText:'返回核对'});if(action.value==='paid')await payCooperationWithdrawal(Number(selected.value.id),{receipt_no:form.receipt_no.trim(),remark:form.remark.trim(),confirm:true});else await rejectCooperationWithdrawal(Number(selected.value.id),{reason:form.reason.trim()});visible.value=false;ElMessage.success(action.value==='paid'?'已登记实际打款，不会再次转账':'申请已驳回，对应余额已解冻');await load()}catch(cause){if(cause!=='cancel'&&cause!=='close'){saveError.value=coopError(cause,'处理结果尚未确认，请先关闭并刷新记录核对，勿重复转账');form.confirm=false}}finally{saving.value=false}}
onMounted(load);onBeforeUnmount(()=>{disposed=true;detailSequence++})
</script>
