<template>
    <section>
        <header class="coop-head"><h2>支付订单</h2></header>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" :extra-active="!!(mobile || purpose || paymentType || paymentChannel || refundStatus || reviewOnly)" placeholder="搜索单号、门店、付款会员或手机号" @search="search" @reload="load" @reset="resetExtraFilters">
            <el-input v-model="mobile" class="order-phone-filter" placeholder="完整手机号（精确查询）" maxlength="64" clearable aria-label="完整手机号"><template #prepend><el-select v-model="mobileScope" aria-label="手机号所属对象"><el-option label="付款会员" value="payer" /><el-option label="线索客户" value="customer" /><el-option label="门店联系人" value="store" /><el-option label="全部对象" value="all" /></el-select></template></el-input>
            <el-select v-model="purpose" clearable placeholder="全部收款类型" aria-label="收款类型"><el-option label="协作佣金" value="commission" /><el-option label="主体会员" value="membership" /></el-select>
            <el-select v-model="paymentType" clearable placeholder="全部支付方式" aria-label="支付方式"><el-option v-for="item in paymentTypes" :key="item.value" :label="item.label" :value="item.value" /></el-select>
            <el-select v-model="paymentChannel" clearable placeholder="全部支付终端" aria-label="支付终端"><el-option v-for="item in paymentChannels" :key="item.value" :label="item.label" :value="item.value" /></el-select>
            <el-select v-model="refundStatus" clearable placeholder="全部退款状态" aria-label="退款状态"><el-option v-for="item in refundStatuses" :key="item.value" :label="item.label" :value="item.value" /></el-select>
            <el-checkbox v-model="reviewOnly">仅需人工核对</el-checkbox>
        </CooperationFilter>
        <el-alert v-if="mobileError" :title="mobileError" type="warning" :closable="false" show-icon class="coop-error" />
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <div class="coop-surface"><HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="暂无符合条件的业务收款单">
            <template #order="{row}"><div class="coop-cell"><strong>{{ row.order_no }}</strong><small>{{ coopLabel(row.purpose) }} · {{ row.merchant_name || row.merchant?.name || '付款商户' }}</small><small v-if="row.lead_no">关联 {{ row.lead_no }}</small></div></template>
            <template #payer="{row}"><div class="coop-cell"><strong>{{ row.payer_name || '原付款会员资料未返回' }}</strong><small v-if="row.payer_member_no">UID：{{ row.payer_member_no }}</small><span v-if="row.payer_mobile" class="order-contact-phone">{{ row.payer_mobile }}</span><small v-else>未登记付款手机号</small><small v-if="row.mobile_match_sources?.includes('customer')" class="order-phone-match">线索客户：{{ row.customer_name || '未填写姓名' }} · {{ row.customer_mobile }}</small><small v-if="row.mobile_match_sources?.includes('store')" class="order-phone-match">门店联系人：{{ row.merchant_contact_name || '未填写姓名' }} · {{ row.merchant_mobile }}</small></div></template>
            <template #money="{row}"><div class="coop-cell"><strong class="coop-price">{{ coopMoney(row.amount) }}</strong><small v-if="row.purpose==='commission'">平台费 {{ coopMoney(row.fee_amount) }} · 净收益 {{ coopMoney(row.net_amount) }}</small><template v-else-if="row.purpose==='membership' && row.service_amount!==undefined"><small>押金 {{ coopMoney(row.deposit_amount) }} · 服务费 {{ coopMoney(row.service_amount) }}</small><small>补足经验 {{ coopMoney(row.experience_amount) }} · {{ row.experience_quantity }} 点</small><small>邀请计佣基数 {{ coopMoney(row.promotion_base_amount) }}</small></template></div></template>
            <template #status="{row}"><div class="coop-cell"><el-tag :type="coopTone(row.status)" size="small">{{ row.status==='paid'?'已确认支付':row.status==='closed'?'已关闭':'待支付' }}</el-tag><el-tag v-if="row.refund_status && row.refund_status!=='none'" :type="row.refund_status==='refunded'?'success':'warning'" size="small">{{ row.refund_status_name || '退款待核对' }}</el-tag><small v-if="Number(row.refunded_amount)>0">已退 {{ coopMoney(row.refunded_amount) }}</small><el-tag v-if="row.requires_manual_review" type="danger" size="small">已收款 · 待人工核对</el-tag></div></template>
            <template #pay="{row}"><div class="coop-cell"><strong>{{ row.payment_type_name || (row.status==='unpaid'?'尚未支付':'支付方式待核对') }}<template v-if="row.payment_channel_name"> · {{ row.payment_channel_name }}</template></strong><small>{{ row.out_trade_no || '尚无支付流水' }}</small><small>{{ row.pay_time ? `支付于 ${coopTime(row.pay_time)}` : `创建于 ${coopTime(row.create_time)}` }}</small><span v-if="row.requires_manual_review" class="coop-danger-copy">{{ row.review_message || '已收到款项，但收益入账或权益开通未完成。请核对支付流水与具体原因，避免重复收款或重复入账，再按实际结果人工处理。' }}</span></div></template>
            <template #operation="{row}"><div class="coop-cell"><el-button link type="primary" @click="showDetail(row)">查看详情</el-button><el-button v-if="showManualReceipt(row)" link type="warning" :disabled="closing || pricing || loading" @click="openManualReceipt(row)">{{ manualReceiptActionLabel(row) }}</el-button><el-button v-if="showRefundAction(row)" link :type="row.refund_status==='pending' || row.can_admin_request_refund===true?'warning':'primary'" :disabled="closing || pricing || loading" @click="openRefund(row)">{{ refundActionLabel(row) }}</el-button><el-button v-if="canPrice && row.can_reprice===true" link type="primary" :disabled="closing || pricing || loading" @click="openPrice(row)">调整待付金额</el-button><el-button v-if="canPrice && row.can_resume_reprice===true" link type="warning" :disabled="closing || pricing || loading" @click="resumePrice(row)">继续原改价处理</el-button><el-button v-if="row.replacement_order_id" link type="primary" :disabled="pricing" @click="openRelated(row.replacement_order_id)">查看新付款单</el-button><el-button v-if="canClose && row.can_close===true" link :type="row.payment_type==='hsx_lead_virtualpay'?'primary':'danger'" :loading="closing && closingOrderId===Number(row.id)" :disabled="closing || pricing || loading" @click="closeOrder(row)">{{ closeActionLabel(row) }}</el-button><small v-else-if="row.status==='unpaid'">{{ row.close_reason || '请核对付款状态' }}</small><small v-if="row.repricing_message">{{ row.repricing_message }}</small></div></template>
        </HsxTable><div class="coop-pagination"><HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading" @change="changePage" /></div></div>
        <el-dialog v-model="detailVisible" title="支付订单详情" width="min(620px, 94vw)" @close="invalidateDetail">
            <el-descriptions :column="1" border>
                <el-descriptions-item label="订单号">{{ selected.order_no }}</el-descriptions-item>
                <el-descriptions-item label="收款用途">{{ coopLabel(selected.purpose) }}</el-descriptions-item>
                <el-descriptions-item label="付款会员">{{ selected.payer_name || '原付款会员资料未返回' }}<template v-if="selected.payer_member_no"> · UID：{{ selected.payer_member_no }}</template><template v-else-if="selected.member_id"> · 会员ID：{{ selected.member_id }}</template></el-descriptions-item>
                <el-descriptions-item label="付款会员手机号">{{ selected.payer_mobile || '未登记' }}</el-descriptions-item>
                <el-descriptions-item v-if="selected.customer_mobile" label="线索客户">{{ selected.customer_name || '未填写姓名' }} · {{ selected.customer_mobile }}</el-descriptions-item>
                <el-descriptions-item v-if="selected.merchant_mobile" label="门店联系人">{{ selected.merchant_contact_name || '未填写姓名' }} · {{ selected.merchant_mobile }}</el-descriptions-item>
                <el-descriptions-item label="应付金额">{{ coopMoney(selected.amount) }}</el-descriptions-item>
                <el-descriptions-item v-if="selected.purpose==='membership'" label="服务费 / 押金 / 经验费">{{ coopMoney(selected.service_amount) }} / {{ coopMoney(selected.deposit_amount) }} / {{ coopMoney(selected.experience_amount) }}</el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ coopTime(selected.create_time) }}</el-descriptions-item>
                <el-descriptions-item label="付款截止">{{ selected.expires_at ? coopTime(selected.expires_at) : '—' }}</el-descriptions-item>
                <el-descriptions-item label="支付流水">{{ selected.out_trade_no || '尚无支付流水' }}</el-descriptions-item>
                <el-descriptions-item label="支付方式 / 终端">{{ selected.payment_type_name || '尚未确认' }}<template v-if="selected.payment_channel_name"> / {{ selected.payment_channel_name }}</template></el-descriptions-item>
                <el-descriptions-item label="退款进度">{{ selected.refund_status_name || '未申请退款' }} · 已退 {{ coopMoney(selected.refunded_amount) }} · 剩余可退 {{ coopMoney(selected.refundable_amount) }}</el-descriptions-item>
                <el-descriptions-item label="关闭时间">{{ selected.close_at ? coopTime(selected.close_at) : '—' }}</el-descriptions-item>
                <el-descriptions-item label="关闭说明">{{ selected.closure_reason || selected.close_reason || '—' }}</el-descriptions-item>
                <el-descriptions-item v-if="selected.reprices_order_id" label="原金额订单"><el-button link type="primary" @click="openRelated(selected.reprices_order_id)">查看原单 #{{ selected.reprices_order_id }}</el-button></el-descriptions-item>
                <el-descriptions-item v-if="selected.replacement_order_id" label="新金额订单"><el-button link type="primary" @click="openRelated(selected.replacement_order_id)">{{ selected.replacement_order_no || ('查看新单 #'+selected.replacement_order_id) }}</el-button></el-descriptions-item>
                <el-descriptions-item v-if="selected.pricing_reason" label="改价原因">{{ selected.pricing_reason }}</el-descriptions-item>
                <el-descriptions-item v-if="selected.repricing_message" label="改价进度">{{ selected.repricing_message }}</el-descriptions-item>
            </el-descriptions>
            <el-alert v-if="selected.requires_manual_review" :title="selected.review_message || '已收到款项，需人工核对'" type="warning" :closable="false" show-icon class="coop-error" />
            <template #footer><el-button v-if="canPriceLogs" @click="showPricingLogs(selected)">查看改价与关闭记录</el-button><el-button v-if="showManualReceipt(selected)" type="warning" @click="openManualReceipt(selected)">{{ manualReceiptActionLabel(selected) }}</el-button><el-button v-if="showRefundAction(selected)" @click="openRefund(selected)">{{ selected.can_admin_request_refund===true?'处理晚到账退款':'退款与处理记录' }}</el-button><el-button @click="detailVisible=false">关闭</el-button></template>
        </el-dialog>
        <OrderRefundDialog ref="refundDialog" @changed="refundChanged" />
        <ManualReceiptDialog ref="manualReceiptDialog" @changed="manualReceiptChanged" />
        <el-collapse class="orders-help"><el-collapse-item title="订单处理规则" name="help"><p>这里只管理会员费与协作付佣，不含客户向门店支付的货款。超时关闭由计划任务执行；支付中、结果待核实或已收款的订单不会直接关闭。关闭付佣支付单不会取消已成交工单的付佣责任。</p></el-collapse-item></el-collapse>
        <el-dialog v-model="priceVisible" title="调整待支付订单" width="min(580px, 94vw)" :close-on-click-modal="false" :show-close="!pricing" :close-on-press-escape="!pricing" :before-close="closePrice">
            <el-alert v-if="priceError" :title="priceError" type="warning" :closable="false" show-icon class="coop-error" />
            <div class="order-price-summary"><strong>{{ priceOrder.order_no }}</strong><span>原应付 {{ coopMoney(priceOrder.amount) }}</span></div>
            <el-form label-position="top" :disabled="pricing"><el-form-item :label="priceOrder.purpose==='membership' ? '新的会员服务费（元）' : '新的协作付佣总额（元）'" required><el-input-number v-model="priceAmount" :min="priceOrder.purpose==='membership'?0:0.01" :max="99999999.99" :precision="2" controls-position="right" /><p v-if="priceOrder.purpose==='membership'" class="order-price-note">只调整服务费；押金与经验费保持原订单金额。</p><p v-else class="order-price-note">沿用本单原收费规则，最低 {{ coopMoney(priceOrder.pricing_minimum) }}。</p></el-form-item><el-form-item label="改价原因" required><el-input v-model="priceReason" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="说明本次调整原因，至少4个字符" /></el-form-item></el-form>
            <div v-if="pricePreview.valid" class="order-price-breakdown"><template v-if="priceOrder.purpose==='membership'"><div><span>会员服务费</span><strong>{{ coopMoney(priceAmount) }}</strong></div><div><span>押金（不变）</span><span>{{ coopMoney(priceOrder.deposit_amount) }}</span></div><div><span>经验费（不变）</span><span>{{ coopMoney(priceOrder.experience_amount) }}</span></div></template><template v-else><div><span>发布方净收益</span><span>{{ coopMoney(pricePreview.net) }}</span></div><div><span>平台维护费</span><span>{{ coopMoney(pricePreview.fee) }}</span></div></template><div class="order-price-total"><span>新订单应付</span><strong>{{ coopMoney(pricePreview.amount) }}</strong></div></div><el-alert v-else :title="pricePreview.error" type="warning" :closable="false" show-icon />
            <el-collapse class="orders-help"><el-collapse-item title="改价如何生效？" name="price"><p>先核对并关闭原付款，再创建新金额的支付单；不会直接扣款。原订单、金额和支付记录全部保留。已收款或结果不明确时停止改价。会员原报价有效期不延长，过期需会员重新报价。</p></el-collapse-item></el-collapse>
            <template #footer><el-button :disabled="pricing" @click="closePrice()">取消</el-button><el-button type="primary" :loading="pricing" :disabled="!pricePreview.valid" @click="submitPrice">核对原单并改价</el-button></template>
        </el-dialog>
        <el-dialog v-model="pricingLogsVisible" title="订单改价与关闭记录" width="min(720px, 94vw)"><el-alert v-if="pricingLogsError" :title="pricingLogsError" type="error" :closable="false" show-icon /><HsxTable :columns="pricingLogColumns" :data="pricingLogs" :loading="pricingLogsLoading" row-key="id" empty-text="暂无改价或关闭记录"><template #operator="{row}"><div class="coop-cell"><strong>{{ row.operator_name }}</strong><small>{{ coopTime(row.create_time) }}</small></div></template><template #change="{row}"><div class="coop-cell"><strong>{{ row.action_label }}</strong><small v-if="row.detail.original_amount!==undefined">{{ coopMoney(row.detail.original_amount) }} → {{ coopMoney(row.detail.amount) }}</small><small>{{ row.detail.reason || row.detail.message }}</small><small v-if="row.detail.replacement_order_id">新订单ID：{{ row.detail.replacement_order_id }}</small></div></template></HsxTable><div class="coop-pagination"><HsxPagination :current-page="pricingLogPage" :page-size="20" :total="pricingLogTotal" :disabled="pricingLogsLoading" @change="changePricingLogPage" /></div><template #footer><el-button @click="pricingLogsVisible=false">关闭</el-button></template></el-dialog>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import CooperationFilter from './CooperationFilter.vue'
import OrderRefundDialog from './OrderRefundDialog.vue'
import ManualReceiptDialog from './ManualReceiptDialog.vue'
import { getCooperationOrders, closeCooperationOrder, repriceCooperationOrder, getOrderPricingLogs } from '../../api/cooperation'
import { businessAmountCents, cooperationFeePreview } from '../../composables/business'
import { coopLabel, coopMoney, coopTime, coopTone, coopError, useCooperationAccess, useCooperationList, type CoopRow } from '../../composables/cooperation'
const statuses=[{value:'unpaid',label:'待支付'},{value:'paid',label:'已确认支付'},{value:'closed',label:'已关闭'}],purpose=ref(''),reviewOnly=ref(false)
const mobile=ref(''),mobileScope=ref('payer'),mobileError=ref('')
const paymentType=ref(''),paymentChannel=ref(''),refundStatus=ref(''),paymentTypes=ref<CoopRow[]>([]),paymentChannels=ref<CoopRow[]>([]),refundStatuses=ref<CoopRow[]>([]),refundDialog=ref<InstanceType<typeof OrderRefundDialog>|null>(null)
const {rows,total,loading,error,query,load,search:searchList,changePage}=useCooperationList(async params=>{const result=await getCooperationOrders(params);const filters=result.data?.filters;if(filters){paymentTypes.value=filterOptions(filters.payment_types);paymentChannels.value=filterOptions(filters.payment_channels);refundStatuses.value=filterOptions(filters.refund_statuses)}return result})
function filterOptions(items:unknown):CoopRow[]{return Array.isArray(items)?items.filter(item=>typeof item?.value==='string'&&typeof item?.label==='string'):[]}
const { allows } = useCooperationAccess(), canClose=computed(()=>allows('order_close')), closing=ref(false), closingOrderId=ref(0), detailVisible=ref(false), selected=ref<CoopRow>({})
const canPrice=computed(()=>allows('order_price')&&canClose.value),canPriceLogs=computed(()=>allows('order_price_log'))
const canRefundDetail=computed(()=>allows('order_refund_detail'))
const canManualReceiptPreview=computed(()=>allows('finance')&&allows('order_manual_receipt_preview')),manualReceiptDialog=ref<InstanceType<typeof ManualReceiptDialog>|null>(null)
function showManualReceipt(row:CoopRow){if(!canManualReceiptPreview.value)return false;const recorded=Number(row.manual_receipt_id)>0;if(row.status==='closed')return recorded;return row.status==='unpaid'||row.status==='paid'&&(recorded||row.payment_type==='offlinepay')}
function manualReceiptActionLabel(row:CoopRow){return row.status==='unpaid'&&!Number(row.manual_receipt_id)?'确认线下收款':'查看收款记录'}
function openManualReceipt(row:CoopRow){if(disposed||!showManualReceipt(row)||closing.value||pricing.value||loading.value)return;detailVisible.value=false;invalidateDetail();void manualReceiptDialog.value?.open(row)}
async function manualReceiptChanged(){invalidateDetail();detailVisible.value=false;await load()}
const priceVisible=ref(false),pricing=ref(false),priceOrder=ref<CoopRow>({}),priceAmount=ref<number|null>(null),priceReason=ref(''),priceError=ref(''),priceRequest=ref('')
const pricingLogsVisible=ref(false),pricingLogsLoading=ref(false),pricingLogsError=ref(''),pricingLogs=ref<CoopRow[]>([]),pricingLogPage=ref(1),pricingLogTotal=ref(0),pricingLogOrder=ref(0);let pricingLogSequence=0
const pricePreview=computed(()=>{
    const amount=businessAmountCents(priceAmount.value);if(amount===null)return{valid:false,error:'请填写有效金额，最多两位小数',amount:0,fee:0,net:0}
    if(priceOrder.value.purpose==='membership'){const deposit=businessAmountCents(priceOrder.value.deposit_amount),experience=businessAmountCents(priceOrder.value.experience_amount);if(deposit===null||experience===null||amount+deposit+experience<1||amount+deposit+experience>9999999999)return{valid:false,error:'会员费用拆分未完整读取或总额超出范围',amount:0,fee:0,net:0};return{valid:true,error:'',amount:(amount+deposit+experience)/100,fee:0,net:0}}
    const minimum=businessAmountCents(priceOrder.value.pricing_minimum);if(minimum===null||amount<minimum)return{valid:false,error:'新金额不能低于本单原最低协作费用',amount:0,fee:0,net:0};return cooperationFeePreview(priceAmount.value,priceOrder.value.pricing_fee_percent,priceOrder.value.pricing_fee_minimum)
})
let detailSequence=0,disposed=false
function invalidateDetail(){detailSequence++}
function showDetail(row:CoopRow){if(disposed)return;invalidateDetail();selected.value=row;detailVisible.value=true}
async function openRelated(id:number){const turn=++detailSequence;try{const result=await getCooperationOrders({id,page:1,limit:1});if(disposed||turn!==detailSequence)return;const row=result.data?.data?.[0];if(Number(row?.id)!==Number(id))throw new Error('关联订单未读取，请刷新核对');showDetail(row)}catch(cause){if(!disposed&&turn===detailSequence)ElMessage.error(coopError(cause,'关联订单读取失败'))}}
function openPrice(row:CoopRow){if(pricing.value||closing.value||!canPrice.value||row.can_reprice!==true)return;priceOrder.value={...row};priceAmount.value=Number(row.purpose==='membership'?row.service_amount:row.amount);priceReason.value='';priceError.value='';priceRequest.value='reprice_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,18);priceVisible.value=true}
function closePrice(done?:()=>void){if(pricing.value)return;priceVisible.value=false;if(typeof done==='function')done()}
async function submitPrice(){
    if(pricing.value||closing.value||!canPrice.value||!priceVisible.value||!pricePreview.value.valid)return
    priceError.value='';if(priceReason.value.trim().length<4){priceError.value='请填写至少4个字符的改价原因';return}if(Math.round(Number(priceOrder.value.amount)*100)===Math.round(pricePreview.value.amount*100)){priceError.value='新应付金额与原单相同，无需改价';return}
    pricing.value=true
    try{await ElMessageBox.confirm(`原订单 ${priceOrder.value.order_no}：${coopMoney(priceOrder.value.amount)}，拟调整为 ${coopMoney(pricePreview.value.amount)}。核实未收款后关闭原单并生成新支付单，不会直接扣款。`,'确认调整待付金额',{confirmButtonText:'核对并创建新单',cancelButtonText:'继续编辑',type:'warning'})}catch{pricing.value=false;return}
    try{const payload:CoopRow={confirm:true,request_id:priceRequest.value,reason:priceReason.value.trim()};payload[priceOrder.value.purpose==='membership'?'service_amount':'amount']=Number(priceAmount.value).toFixed(2);const result=await repriceCooperationOrder(Number(priceOrder.value.id),payload);if(result.data?.repriced&&businessAmountCents(result.data.replacement_order?.amount)!==businessAmountCents(pricePreview.value.amount))throw new Error('新订单金额未按本次提交返回，请刷新核对，不要重复处理');acceptReprice(result.data,Number(priceOrder.value.id));await load()}catch(cause){priceError.value=coopError(cause,'改价结果待核对，请刷新原单，不要另开付款');await load()}finally{pricing.value=false}
}
function acceptReprice(data:CoopRow,id:number){if(Number(data?.old_order?.id)!==id||typeof data.repriced!=='boolean')throw new Error('改价结果未完整返回，请刷新原单核对');if(data.repriced){if(Number(data.replacement_id)<=0||Number(data.replacement_order?.id)!==Number(data.replacement_id)||data.old_order.status!=='closed'||!['unpaid','paid','closed'].includes(data.replacement_order.status))throw new Error('新旧订单关联未确认，请刷新核对');priceVisible.value=false;showDetail(data.replacement_order);if(data.replacement_order.status==='paid')ElMessage.success('改价后的订单已收款，请勿重复付款');else if(data.replacement_order.status==='closed')ElMessage.warning('改价后的订单已关闭，请核对原单处理记录');else ElMessage.success('已生成新金额支付单，尚未扣款')}else{priceError.value=data.message||'原订单尚未核实关闭，未创建新支付单';ElMessage.warning(priceError.value);if(!priceVisible.value||data.old_order.status!=='unpaid'){priceVisible.value=false;showDetail(data.old_order)}}}
async function resumePrice(row:CoopRow){if(pricing.value||closing.value||!canPrice.value||row.can_resume_reprice!==true)return;pricing.value=true;try{await ElMessageBox.confirm(`继续处理订单 ${row.order_no} 的原改价请求，新应付 ${coopMoney(row.pricing_target_amount)}。不会改用新金额或重复建单，原付款未核实关闭时停止。`,'继续原改价请求',{confirmButtonText:'核对并继续',cancelButtonText:'取消',type:'warning'})}catch{pricing.value=false;return}try{const result=await repriceCooperationOrder(Number(row.id),{confirm:true,resume:true});acceptReprice(result.data,Number(row.id));await load()}catch(cause){ElMessage.error(coopError(cause,'请刷新原订单，核对原改价处理状态'));await load()}finally{pricing.value=false}}
async function showPricingLogs(row:CoopRow){if(!canPriceLogs.value)return;pricingLogOrder.value=Number(row.id);pricingLogPage.value=1;pricingLogsVisible.value=true;await loadPricingLogs()}
async function loadPricingLogs(){const turn=++pricingLogSequence;pricingLogsLoading.value=true;pricingLogsError.value='';try{const result=await getOrderPricingLogs(pricingLogOrder.value,{page:pricingLogPage.value,limit:20});if(turn!==pricingLogSequence)return;if(!Array.isArray(result.data?.data))throw new Error('未返回有效订单日志');pricingLogs.value=result.data.data;pricingLogTotal.value=Number(result.data.total||0)}catch(cause){if(turn===pricingLogSequence)pricingLogsError.value=coopError(cause,'订单日志读取失败')}finally{if(turn===pricingLogSequence)pricingLogsLoading.value=false}}
function changePricingLogPage(value:{page:number;limit:number}){pricingLogPage.value=value.page;return loadPricingLogs()}
const pricingLogColumns=[{prop:'operator_name',label:'操作人 / 时间',slot:'operator',minWidth:180},{prop:'action',label:'处理与金额',slot:'change',minWidth:300}]
function closeActionLabel(row:CoopRow){return row.payment_type==='hsx_lead_virtualpay'?'核对付款结果':'关闭未付订单'}
async function closeOrder(row:CoopRow){
    if(closing.value || pricing.value || loading.value || !canClose.value || row.can_close!==true)return
    closing.value=true;closingOrderId.value=Number(row.id)
    try{
        const virtual=row.payment_type==='hsx_lead_virtualpay'
        let reason='管理员核对原虚拟付款结果'
        if(virtual)await ElMessageBox.confirm(`核对订单 ${row.order_no}（${coopMoney(row.amount)}）的原付款结果。已收款会恢复业务处理；只有渠道确认关闭，系统才会关闭订单。尚未确认时保留原单，不另行扣款。`,'核对付款结果',{confirmButtonText:'开始核对',cancelButtonText:'返回',type:'info',distinguishCancelAndClose:true})
        else{const result=await ElMessageBox.prompt(`关闭订单 ${row.order_no}（${coopMoney(row.amount)}）？系统会先核对收款，不会撤销已到账款项。`,'关闭未付款订单',{inputPlaceholder:'填写关闭原因',inputType:'textarea',inputValidator:value=>String(value||'').trim().length>=4||'请填写至少4个字符的关闭原因',confirmButtonText:'核对并关闭',cancelButtonText:'取消',distinguishCancelAndClose:true});reason=result.value.trim()}
        const response=await closeCooperationOrder(Number(row.id),{confirm:true,reason})
        if(Number(response?.data?.id)!==Number(row.id)||!['closed','paid','unpaid'].includes(response?.data?.status))throw new Error('订单关闭结果未确认，请刷新核对，勿重复处理')
        if(response.data.status==='closed')ElMessage.success('未付订单已关闭');else ElMessage.warning(response.data.status==='paid'?'原订单已实际收款，未关闭订单':response.data.close_message||'原付款结果仍需核实，未关闭订单');await load()
    }catch(cause){if(cause!=='cancel'&&cause!=='close'){ElMessage.error(coopError(cause,'付款核对结果未确认，请稍后刷新原单'));await load()}}
    finally{closing.value=false;closingOrderId.value=0}
}
function resetExtraFilters(){mobile.value='';mobileScope.value='payer';mobileError.value='';purpose.value='';reviewOnly.value=false;paymentType.value='';paymentChannel.value='';refundStatus.value=''}
function search(patch:CoopRow={}){
    mobileError.value=''
    const raw=mobile.value.trim(),digits=raw.replace(/[ +\-]/g,'')
    if(raw&&(!/^\+?[0-9][0-9 -]*$/.test(raw)||digits.length<7||digits.length>20)){mobileError.value='请输入7至20位的完整手机号，可带 +86、空格或连字符，不支持尾号查询';return}
    return searchList({...patch,mobile:raw,mobile_scope:mobileScope.value,purpose:purpose.value,payment_type:paymentType.value,payment_channel:paymentChannel.value,refund_status:refundStatus.value,requires_manual_review:reviewOnly.value?'1':''})
}
async function refundChanged(){detailVisible.value=false;invalidateDetail();await load()}
function showRefundAction(row:CoopRow){return canRefundDetail.value&&(row.status==='paid'||row.can_admin_request_refund===true||!!row.refund_status&&row.refund_status!=='none')}
function refundActionLabel(row:CoopRow){return row.can_admin_request_refund===true?'处理晚到账退款':row.refund_status==='pending'?'退款申请':row.refund_status&&row.refund_status!=='none'?'退款进度':'订单退款'}
function openRefund(row:CoopRow){if(disposed||!showRefundAction(row)||closing.value||pricing.value||loading.value)return;detailVisible.value=false;invalidateDetail();void refundDialog.value?.open(row)}
const columns=[{prop:'order_no',label:'业务单 / 付款商户',slot:'order',minWidth:250},{prop:'payer_name',label:'原付款会员 / 手机号',slot:'payer',minWidth:220},{prop:'amount',label:'应付金额 / 分账快照',slot:'money',minWidth:220},{prop:'status',label:'支付 / 退款状态',slot:'status',minWidth:160},{prop:'out_trade_no',label:'支付方式 / 终端 / 流水',slot:'pay',minWidth:250},{prop:'id',label:'操作',slot:'operation',width:180,fixed:'right' as const}]
onMounted(load)
onBeforeUnmount(()=>{disposed=true;invalidateDetail();pricingLogSequence++})
</script>
<style scoped>
.order-phone-filter{flex:0 1 360px!important;min-width:300px!important;max-width:400px!important}.order-phone-filter :deep(.el-input-group__prepend){padding:0 12px}.order-phone-filter :deep(.el-input-group__prepend .el-select){width:130px;margin:-10px -12px}.order-contact-phone{font-size:13px;font-variant-numeric:tabular-nums}.order-phone-match{color:var(--el-color-primary)!important;overflow-wrap:anywhere}
</style>
<style scoped>.orders-help{margin-top:16px}.orders-help p{line-height:1.8;font-size:13px;color:var(--el-text-color-secondary)}.order-price-summary{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:20px;font-size:14px}.order-price-note{font-size:13px;color:var(--el-text-color-secondary);line-height:1.8;width:100%;margin:8px 0 0}.order-price-breakdown{padding:16px;background:var(--el-fill-color-extra-light);border:1px solid var(--el-border-color-lighter);border-radius:6px}.order-price-breakdown>div{display:flex;justify-content:space-between;gap:12px;margin-bottom:12px}.order-price-breakdown>div:last-child{margin-bottom:0}.order-price-total{padding-top:12px;border-top:1px solid var(--el-border-color-lighter)}.order-price-total strong{color:var(--el-color-primary)}</style>
