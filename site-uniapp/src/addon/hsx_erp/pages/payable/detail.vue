<template>
    <view class="erp-page">
        <ErpPageHeader title="应付详情" />
        <view class="detail-wrap">
            <view v-if="loading" class="empty-card">正在加载...</view>
            <view v-else-if="error" class="empty-card">{{ error }}</view>
            <template v-else>
                <view class="form-card">
                    <view class="form-card-title">付款对象</view>
                    <view class="party-name">{{ partyName || '-' }}</view>
                    <ErpFinanceSourceSummary v-if="items[0]" :row="items[0]" direction="payable" />
                    <view class="summary-grid">
                        <view><text>应付合计</text><strong>¥{{ money(total.amount) }}</strong></view>
                        <view><text>已付</text><strong class="green">¥{{ money(total.paid) }}</strong></view>
                        <view><text>剩余</text><strong class="orange">¥{{ money(total.remain) }}</strong></view>
                    </view>
                </view>

                <view class="section-title">{{ isNonDevicePay ? '经营费用明细' : '关联设备' }}（{{ items.length }}）</view>
                <view v-if="!items.length" class="empty-card">{{ isNonDevicePay ? '该应付暂无费用明细' : '该应付暂无设备明细' }}</view>
                <view v-for="item in items" :key="item.payable_id || item.id" class="erp-card">
                    <view class="erp-card__head"><view><text class="card-title">{{ isNonDevicePay ? (item.category_name || '经营支出') : (item.model || '其他支出') }}</text><text class="card-meta">{{ isNonDevicePay ? (item.source_no || item.payable_no || '-') : identity(item) }}</text></view><u-tag :text="statusLabel(item.payable_status)" :type="statusType(item.payable_status)" plain size="mini" /></view>
                    <ErpCopyText :value="item.payable_no" title="应付单号" block />
                    <ErpCopyText :value="item.source_no || item.purchase_no" title="来源单号" block />
                    <view v-if="item.spec" class="card-meta">{{ item.spec }}</view>
                    <view v-if="item.business_reason || item.remark" class="reason">{{ item.business_reason || item.remark }}</view>
                    <view class="erp-card__foot finance-foot"><view class="amount-box"><text class="amt-label">应付</text><text class="amt-value">¥{{ money(item.payable_amount) }}</text></view><view class="amount-box"><text class="amt-label">已付</text><text class="amt-value green">¥{{ money(item.allocated_paid) }}</text></view><view class="amount-box"><text class="amt-label">剩余</text><text class="amt-value orange">¥{{ money(item.allocated_remain) }}</text></view></view>
                    <view v-for="settlement in item.settlements || []" :key="settlement.settlement_id" class="settlement">
                        <view class="settlement__head"><text>{{ settlement.settlement_type_text || '结算' }}</text><strong>¥{{ money(settlement.applied_amount) }}</strong></view>
                        <ErpCopyText :value="settlement.settlement_no" title="结算单号" block />
                        <text class="card-meta">{{ settlement.pay_method_text || settlement.capital_account_name || '-' }}</text>
                        <ErpVoucherUploader v-for="ledger in settlement.money_ledgers || []" :key="ledger.ledger_no" :model-value="ledger.voucher_urls" title="收付款凭证" readonly />
                    </view>
                </view>
            </template>
        </view>
        <view v-if="total.remain > 0" class="float-bar">
            <u-button type="warning" plain @click="offsetVisible = true">应收应付折账</u-button>
            <u-button type="primary" @click="payVisible = true">{{ isNonDevicePay ? '确认经营付款' : '按设备付款' }}</u-button>
        </view>
        <ErpPayConfirmModal v-model:show="payVisible" :party-id="partyId" :party-name="partyName" :purchase-order-id="purchaseOrderId" :source-type="sourceType" :source-row="items[0] || {}" :accounts="accounts" @success="load" />
        <ErpOffsetConfirmModal v-model:show="offsetVisible" :party-id="partyId" :party-name="partyName" :accounts="accounts" @success="load" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getMobileCapitalAccounts, getMobilePayablePartyItems } from '@/addon/hsx_erp/api/erp'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpFinanceSourceSummary from '@/addon/hsx_erp/components/ErpFinanceSourceSummary.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import ErpCopyText from '@/addon/hsx_erp/components/ErpCopyText.vue'
import ErpPayConfirmModal from '@/addon/hsx_erp/components/ErpPayConfirmModal.vue'
import ErpOffsetConfirmModal from '@/addon/hsx_erp/components/ErpOffsetConfirmModal.vue'
const partyId=ref(0), partyName=ref(''), sourceType=ref(''), purchaseOrderId=ref(0), items=ref<any[]>([]), loading=ref(false), error=ref(''),accounts=ref<any[]>([]),payVisible=ref(false),offsetVisible=ref(false)
const total=computed(()=>items.value.reduce((sum,row)=>({amount:sum.amount+Number(row.payable_amount||0),paid:sum.paid+Number(row.allocated_paid||0),remain:sum.remain+Number(row.allocated_remain||0)}),{amount:0,paid:0,remain:0}))
const isNonDevicePay=computed(()=>String(sourceType.value||'').includes('operating_')||items.value.some((row:any)=>String(row.biz_scene||'').includes('operating_')||String(row.category_statement_group||'').includes('operating_')))
onLoad((q:any)=>{partyId.value=Number(q?.party_id||0);partyName.value=decodeURIComponent(String(q?.party_name||''));sourceType.value=decodeURIComponent(String(q?.source_type||''));purchaseOrderId.value=Number(q?.purchase_order_id||0)})
onShow(()=>{load();loadAccounts()})
async function loadAccounts(){try{const res:any=await getMobileCapitalAccounts();accounts.value=res?.data?.list||[]}catch(_){accounts.value=[]}}
async function load(){if(!partyId.value){error.value='缺少付款对象';return}loading.value=true;error.value='';try{const params:any={page:1,limit:200,source_type:sourceType.value};if(purchaseOrderId.value)params.purchase_order_id=purchaseOrderId.value;const res:any=await getMobilePayablePartyItems(partyId.value,params);items.value=res?.data?.data||[]}catch(e:any){error.value=e?.message||'应付详情加载失败'}finally{loading.value=false}}
const money=(v:any)=>Number(v||0).toFixed(2)
const identity=(row:any)=>[row.imei?`IMEI ${row.imei}`:'',row.asset_no?`资产号 ${row.asset_no}`:''].filter(Boolean).join(' · ')||'未关联设备'
const statusLabel=(s:string)=>({pending:'待付款',partial:'部分付款',settled:'已结清',void:'已作废'}[s]||s||'-')
const statusType=(s:string)=>({pending:'warning',partial:'primary',settled:'success',void:'info'}[s]||'info')
</script>
<style scoped lang="scss">
.detail-wrap{padding-bottom:150rpx}
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.party-name{margin:6rpx 0 16rpx;color:#0f172a;font-size:34rpx;font-weight:750}.summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12rpx;margin:18rpx 0 ;}.summary-grid view{padding:16rpx;border-radius:12rpx;background:#f8fafc}.summary-grid text,.summary-grid strong{display:block}.summary-grid text{color:#94a3b8;font-size:20rpx}.summary-grid strong{margin-top:7rpx;color:#0f172a;font-size:27rpx}.green{color:#16a34a!important}.orange{color:#ea580c!important}.reason{margin-top:12rpx;padding:14rpx;border-radius:10rpx;background:#f8fafc;color:#475569;font-size:22rpx;line-height:1.5}.settlement{margin-top:16rpx;padding:16rpx;border-radius:12rpx;background:#f8fafc}.settlement__head{display:flex;justify-content:space-between;color:#334155;font-size:24rpx}.settlement__head strong{color:#2563eb}.empty-card{margin:22rpx;padding:50rpx 20rpx;border-radius:16rpx;background:#fff;color:#94a3b8;text-align:center}
</style>
