<template>
    <section>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" placeholder="搜索业务单号、门店或支付流水" @search="search" @reload="load" @reset="purpose='';reviewOnly=false">
            <el-select v-model="purpose" clearable placeholder="全部收款类型" aria-label="收款类型"><el-option label="协作佣金" value="commission" /><el-option label="发单权益" value="publishing" /><el-option label="靠谱认证" value="certification" /></el-select>
            <el-checkbox v-model="reviewOnly">仅需人工核对</el-checkbox>
        </CooperationFilter>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <div class="coop-surface"><HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="暂无符合条件的业务收款单">
            <template #order="{row}"><div class="coop-cell"><strong>{{ row.order_no }}</strong><small>{{ coopLabel(row.purpose) }} · {{ row.merchant_name || row.merchant?.name || '付款商户' }}</small><small v-if="row.lead_no">关联 {{ row.lead_no }}</small></div></template>
            <template #money="{row}"><div class="coop-cell"><strong class="coop-price">{{ coopMoney(row.amount) }}</strong><small v-if="row.purpose==='commission'">平台费 {{ coopMoney(row.fee_amount) }} · 净收益 {{ coopMoney(row.net_amount) }}</small></div></template>
            <template #status="{row}"><div class="coop-cell"><el-tag :type="coopTone(row.status)" size="small">{{ row.status==='paid'?'已确认支付':row.status==='closed'?'已关闭':'待支付' }}</el-tag><el-tag v-if="row.requires_manual_review" type="danger" size="small">权益未自动开通 · 待核对</el-tag></div></template>
            <template #pay="{row}"><div class="coop-cell"><span>{{ row.out_trade_no || '尚无支付流水' }}</span><small>{{ row.pay_time ? `支付于 ${coopTime(row.pay_time)}` : `创建于 ${coopTime(row.create_time)}` }}</small><span v-if="row.requires_manual_review" class="coop-danger-copy">{{ row.review_message || '已收到款项但商户状态发生变化，未自动开通权益。请核对原支付流水并人工处理，不要再次收款。' }}</span></div></template>
        </HsxTable><div class="coop-pagination"><HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading" @change="changePage" /></div></div>
        <p class="coop-muted" style="margin-top:12px">是否支付以框架确认结果为准；创建支付单、打开收银台都不代表已收款。此处不提供跳过支付的“手动改为已付”。标记待核对的真实收款需财务核对原流水及退款安排，不会自动退款或自动开通权益。</p>
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
const columns=[{prop:'order_no',label:'业务单 / 付款商户',slot:'order',minWidth:270},{prop:'amount',label:'应付金额 / 分账快照',slot:'money',minWidth:240},{prop:'status',label:'支付 / 权益状态',slot:'status',minWidth:180},{prop:'out_trade_no',label:'支付流水 / 核对提示',slot:'pay',minWidth:280}]
onMounted(load)
</script>
