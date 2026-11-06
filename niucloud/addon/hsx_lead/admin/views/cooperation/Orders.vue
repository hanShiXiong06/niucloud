<template>
    <section>
        <header class="coop-head"><h2>协作收款</h2></header>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" placeholder="搜索业务单号、门店或支付流水" @search="search" @reload="load" @reset="purpose='';reviewOnly=false">
            <el-select v-model="purpose" clearable placeholder="全部收款类型" aria-label="收款类型"><el-option label="协作佣金" value="commission" /><el-option label="主体会员" value="membership" /></el-select>
            <el-checkbox v-model="reviewOnly">仅需人工核对</el-checkbox>
        </CooperationFilter>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <div class="coop-surface"><HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="暂无符合条件的业务收款单">
            <template #order="{row}"><div class="coop-cell"><strong>{{ row.order_no }}</strong><small>{{ coopLabel(row.purpose) }} · {{ row.merchant_name || row.merchant?.name || '付款商户' }}</small><small v-if="row.lead_no">关联 {{ row.lead_no }}</small></div></template>
            <template #money="{row}"><div class="coop-cell"><strong class="coop-price">{{ coopMoney(row.amount) }}</strong><small v-if="row.purpose==='commission'">平台费 {{ coopMoney(row.fee_amount) }} · 净收益 {{ coopMoney(row.net_amount) }}</small><template v-else-if="row.purpose==='membership' && row.service_amount!==undefined"><small>押金 {{ coopMoney(row.deposit_amount) }} · 服务费 {{ coopMoney(row.service_amount) }}</small><small>补足经验 {{ coopMoney(row.experience_amount) }} · {{ row.experience_quantity }} 点</small><small>邀请计佣基数 {{ coopMoney(row.promotion_base_amount) }}</small></template></div></template>
            <template #status="{row}"><div class="coop-cell"><el-tag :type="coopTone(row.status)" size="small">{{ row.status==='paid'?'已确认支付':row.status==='closed'?'已关闭':'待支付' }}</el-tag><el-tag v-if="row.requires_manual_review" type="danger" size="small">已收款 · 待人工核对</el-tag></div></template>
            <template #pay="{row}"><div class="coop-cell"><span>{{ row.out_trade_no || '尚无支付流水' }}</span><small>{{ row.pay_time ? `支付于 ${coopTime(row.pay_time)}` : `创建于 ${coopTime(row.create_time)}` }}</small><span v-if="row.requires_manual_review" class="coop-danger-copy">{{ row.review_message || '已收到款项，但收益入账或权益开通未完成。请核对支付流水与具体原因，避免重复收款或重复入账，再按实际结果人工处理。' }}</span></div></template>
        </HsxTable><div class="coop-pagination"><HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading" @change="changePage" /></div></div>
        <p class="coop-muted" style="margin-top:12px">不含客户货款。支付以真实到账为准；待核对记录请勿重复收款。</p>
    </section>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import CooperationFilter from './CooperationFilter.vue'
import { getCooperationOrders } from '../../api/cooperation'
import { coopLabel, coopMoney, coopTime, coopTone, useCooperationList, type CoopRow } from '../../composables/cooperation'
const statuses=[{value:'unpaid',label:'待支付'},{value:'paid',label:'已确认支付'},{value:'closed',label:'已关闭'}],purpose=ref(''),reviewOnly=ref(false)
const {rows,total,loading,error,query,load,search:searchList,changePage}=useCooperationList(getCooperationOrders)
function search(patch:CoopRow={}){return searchList({...patch,purpose:purpose.value,requires_manual_review:reviewOnly.value?'1':''})}
const columns=[{prop:'order_no',label:'业务单 / 付款商户',slot:'order',minWidth:270},{prop:'amount',label:'应付金额 / 分账快照',slot:'money',minWidth:240},{prop:'status',label:'支付 / 核对状态',slot:'status',minWidth:180},{prop:'out_trade_no',label:'支付流水 / 核对提示',slot:'pay',minWidth:280}]
onMounted(load)
</script>
