<template>
    <view class="erp-page">
        <ErpListHeader v-model="keyword" v-model:active-tab="direction" title="经营收支" placeholder="往来主体 / 类型 / 单号" :tabs="tabs" :show-scan="false" @search="reload" @tab-change="changeDirection">
            <template #right>
               <view> <u-button type="primary" size="small" text="记一笔" @click="openCreate" /></view>
            </template>
        </ErpListHeader>
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true" :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无经营收支" /></template>
            <view class="summary-grid"><view><text>经营收入</text><strong class="green">¥{{ money(summary.income) }}</strong></view><view><text>经营支出</text><strong class="orange">¥{{ money(summary.expense) }}</strong></view><view><text>待收待付</text><strong class="blue">¥{{ money(summary.unsettled) }}</strong></view></view>
            <view class="list-wrap"><view v-for="row in list" :key="`${row.direction}-${row.id}`" class="erp-card op-card">
                <view class="erp-card__head"><view><text class="card-title">{{ row.category_name || '-' }}</text><text class="card-sub">{{ row.party_name || '未记录往来主体' }}</text></view><u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" /></view>
                <view class="op-amount" :class="row.direction">{{ row.direction==='income'?'+':'-' }}¥{{ money(row.amount) }}</view>
                <view class="card-meta">{{ row.business_reason || row.remark || '未填写经营说明' }}</view>
                <view class="op-foot"><text>{{ row.source_no || row.finance_no }}</text><text>{{ formatErpTime(row.occurred_at) }}</text></view>
                <view class="op-settle"><text>已结 ¥{{ money(row.settled_amount) }} · 剩余 ¥{{ money(row.remain_amount) }}</text><text v-if="Number(row.remain_amount)>0" class="link" @click="goFinance(row)">去{{ row.direction==='income'?'收款':'付款' }} ›</text><text v-else class="closed">已闭环</text></view>
            </view></view>
        </z-paging>

        <u-popup :show="createVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="28rpx" @close="createVisible=false">
            <view class="create-popup"><view class="popup-head"><view><text class="popup-title">记一笔经营收支</text><text class="popup-sub">不影响具体设备成本</text></view><u-icon name="close" size="20" color="#64748b" @click="createVisible=false" /></view>
                <scroll-view scroll-y class="popup-body">
                    <view class="direction-switch"><view :class="{active:form.direction==='expense'}" @click="setFormDirection('expense')">经营支出</view><view :class="{active:form.direction==='income'}" @click="setFormDirection('income')">经营收入</view></view>
                    <view class="form-card">
                        <view class="field-row" @click="categoryVisible=true"><text>收支类型 *</text><view><strong>{{ selectedCategory?.name || '请选择' }}</strong><u-icon name="arrow-right" size="14" color="#94a3b8" /></view></view>
                        <view class="field-row" @click="partyVisible=true"><text>往来主体 *</text><view><strong>{{ form.party_name || '请选择' }}</strong><u-icon name="arrow-right" size="14" color="#94a3b8" /></view></view>
                        <view class="field-row"><text>金额 *</text><u-input v-model="form.amount" type="number" placeholder="0.00" :customStyle="inputStyle" /></view>
                    </view>
                    <view class="form-card"><text class="field-label">结算方式 *</text><view class="settle-options"><view :class="{active:form.settlement_mode==='pending'}" @click="form.settlement_mode='pending'"><strong>转财务结算</strong><text>生成应收/应付，可付款、收款或折账</text></view><view :class="{active:form.settlement_mode==='immediate'}" @click="form.settlement_mode='immediate'"><strong>已经现场收付</strong><text>选择账户，立即形成资金流水</text></view></view></view>
                    <view v-if="form.settlement_mode==='immediate'" class="form-card"><view class="field-row" @click="accountVisible=true"><text>资金账户 *</text><view><strong>{{ selectedAccountLabel || '请选择' }}</strong><u-icon name="arrow-right" size="14" color="#94a3b8" /></view></view><ErpVoucherUploader v-model="form.voucher_urls" title="资金凭证" /></view>
                    <view class="form-card"><text class="field-label">经营事项说明 *</text><u-textarea v-model="form.remark" placeholder="例如：7月份门店房租，合同号……" height="150rpx" /></view>
                </scroll-view>
                <view class="popup-actions"><u-button @click="createVisible=false">取消</u-button><u-button type="primary" :loading="saving" @click="submit">{{ form.settlement_mode==='pending'?'生成应收应付':'现结并记账' }}</u-button></view>
            </view>
        </u-popup>

        <u-popup :show="categoryVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="28rpx" @close="categoryVisible=false"><view class="option-popup"><view class="popup-head"><text class="popup-title">选择收支类型</text><u-icon name="close" size="20" @click="categoryVisible=false" /></view><scroll-view scroll-y class="option-list"><u-cell v-for="item in formCategories" :key="item.key" :title="item.name" :label="item.direction==='income'?'经营收入':'经营支出'" isLink @click="selectCategory(item)" /></scroll-view></view></u-popup>
        <ErpPartyPopup v-model:show="partyVisible" v-model:party-id="form.party_id" v-model:party-name="form.party_name" :role-type="form.direction==='expense'?'supplier':'customer'" />
        <ErpCapitalAccountPopup v-model:show="accountVisible" v-model="form.capital_account_id" :accounts="accounts" :title="form.direction==='income'?'选择收款账户':'选择付款账户'" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createMobileOperatingFinance, getErpFinanceCategories, getMobileCapitalAccounts, getMobileOperatingFinanceList } from '@/addon/hsx_erp/api/erp'
import { formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpPartyPopup from '@/addon/hsx_erp/components/ErpPartyPopup.vue'
import ErpCapitalAccountPopup from '@/addon/hsx_erp/components/ErpCapitalAccountPopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
const {pagingStyle}=useListHeader(300,300),pagingRef=ref<any>(null),list=ref<any[]>([]),keyword=ref(''),direction=ref(''),summary=ref({income:0,expense:0,unsettled:0}),categories=ref<any[]>([]),accounts=ref<any[]>([]),createVisible=ref(false),categoryVisible=ref(false),partyVisible=ref(false),accountVisible=ref(false),saving=ref(false)
const tabs=[{label:'全部',value:''},{label:'收入',value:'income'},{label:'支出',value:'expense'}],inputStyle={textAlign:'right',background:'#f8fafc',borderRadius:'10rpx',padding:'8rpx 12rpx'}
const form=ref<any>({direction:'expense',category_key:'',party_id:0,party_name:'',amount:'',settlement_mode:'pending',capital_account_id:0,voucher_urls:'',remark:''})
const operatingCategories=computed(()=>categories.value.filter(row=>row.scope==='operating'&&Number(row.enabled??1)===1)),formCategories=computed(()=>operatingCategories.value.filter(row=>row.direction===form.value.direction)),selectedCategory=computed(()=>operatingCategories.value.find(row=>row.key===form.value.category_key)),selectedAccountLabel=computed(()=>{const a=accounts.value.find(row=>Number(row.id)===Number(form.value.capital_account_id));return a?`${a.account_name} · ¥${money(a.balance)}`:''})
onLoad(async()=>{try{const [c,a]:any[]=await Promise.all([getErpFinanceCategories(),getMobileCapitalAccounts()]);categories.value=c?.data||[];accounts.value=a?.data?.list||[]}catch(_){ }})
function reload(){pagingRef.value?.reload()} function changeDirection(v:string){direction.value=v;reload()}
async function queryList(page:number,limit:number){try{const res:any=await getMobileOperatingFinanceList({page,limit,direction:direction.value,keyword:keyword.value});summary.value=res?.data?.summary||summary.value;pagingRef.value?.complete(res?.data?.data||[])}catch(_){pagingRef.value?.complete(false)}}
function setFormDirection(v:string){form.value.direction=v;if(!formCategories.value.some(row=>row.key===form.value.category_key))form.value.category_key=formCategories.value[0]?.key||''}
function openCreate(){form.value={direction:'expense',category_key:'',party_id:0,party_name:'',amount:'',settlement_mode:'pending',capital_account_id:accounts.value[0]?.id||0,voucher_urls:'',remark:''};setFormDirection('expense');createVisible.value=true}
function selectCategory(item:any){form.value.category_key=item.key;categoryVisible.value=false}
async function submit(){if(!form.value.category_key)return uni.showToast({title:'请选择收支类型',icon:'none'});if(!form.value.party_id)return uni.showToast({title:'请选择往来主体',icon:'none'});if(Number(form.value.amount)<=0)return uni.showToast({title:'请输入金额',icon:'none'});if(!form.value.remark.trim())return uni.showToast({title:'请填写经营事项说明',icon:'none'});if(form.value.settlement_mode==='immediate'&&!form.value.capital_account_id)return uni.showToast({title:'请选择资金账户',icon:'none'});const yes=await confirmErpSensitiveAction({title:'确认经营记账',content:`${selectedCategory.value?.name||'经营收支'} ¥${money(form.value.amount)}。${form.value.settlement_mode==='pending'?'确认后生成待结算应收/应付。':'确认后立即改变资金账户余额。'}`,confirmText:'确认记账'});if(!yes)return;saving.value=true;try{await createMobileOperatingFinance({...form.value,occurred_at:Math.floor(Date.now()/1000)});uni.showToast({title:form.value.settlement_mode==='pending'?'已生成应收应付':'经营收支已现结',icon:'success'});createVisible.value=false;reload()}finally{saving.value=false}}
function goFinance(row:any){uni.navigateTo({url:row.direction==='income'?`/addon/hsx_erp/pages/receivable/list?source_no=${row.source_no}`:`/addon/hsx_erp/pages/payable/list?source_no=${row.source_no}`})}
const money=(v:any)=>Number(v||0).toFixed(2),statusLabel=(s:string)=>({pending:'待结算',partial:'部分结算',settled:'已结清',void:'已作废'}[s]||s||'-'),statusType=(s:string)=>({pending:'warning',partial:'primary',settled:'success',void:'info'}[s]||'info')
</script>

<style scoped lang="scss">@import '@/addon/hsx_erp/styles/erp-mobile.scss';.summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10rpx;padding:18rpx 24rpx 2rpx}.summary-grid view{padding:15rpx 12rpx;border-radius:14rpx;background:#fff}.summary-grid text,.summary-grid strong{display:block}.summary-grid text{font-size:19rpx;color:#94a3b8}.summary-grid strong{margin-top:5rpx;font-size:24rpx}.green{color:#16a34a}.orange{color:#ea580c}.blue{color:#2563eb}.op-card{padding:22rpx 24rpx}.card-sub{display:block;margin-top:5rpx;color:#64748b;font-size:21rpx}.op-amount{margin-top:14rpx;font-size:36rpx;font-weight:800}.op-amount.income{color:#16a34a}.op-amount.expense{color:#ea580c}.op-foot,.op-settle{display:flex;align-items:center;justify-content:space-between;gap:14rpx;margin-top:13rpx;color:#94a3b8;font-size:20rpx}.op-settle{padding-top:12rpx;border-top:1rpx solid #f1f5f9;color:#64748b}.link{color:#2563eb}.closed{color:#16a34a}.create-popup{height:88vh;max-height:1100rpx;display:flex;flex-direction:column;background:#f8fafc;overflow:hidden}.popup-head{flex:none;display:flex;align-items:center;justify-content:space-between;padding:25rpx 28rpx;background:#fff;border-bottom:1rpx solid #eef2f7}.popup-title,.popup-sub{display:block}.popup-title{font-size:31rpx;font-weight:800;color:#0f172a}.popup-sub{margin-top:4rpx;font-size:20rpx;color:#94a3b8}.popup-body{flex:1;min-height:0;width:100%;box-sizing:border-box}.direction-switch{display:grid;grid-template-columns:1fr 1fr;gap:10rpx;margin:20rpx 24rpx;padding:6rpx;border-radius:14rpx;background:#e2e8f0}.direction-switch view{padding:14rpx;text-align:center;border-radius:10rpx;color:#64748b;font-size:23rpx}.direction-switch .active{background:#fff;color:#2563eb;font-weight:700}.form-card{margin:16rpx 24rpx;padding:20rpx;border-radius:18rpx;background:#fff}.field-row{min-height:76rpx;display:flex;align-items:center;justify-content:space-between;gap:20rpx;border-bottom:1rpx solid #f1f5f9;color:#475569;font-size:23rpx}.field-row:last-child{border-bottom:0}.field-row>view{min-width:0;display:flex;align-items:center;gap:8rpx}.field-row strong{max-width:390rpx;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#0f172a}.field-label{display:block;margin-bottom:14rpx;color:#475569;font-size:23rpx}.settle-options{display:grid;grid-template-columns:1fr 1fr;gap:12rpx}.settle-options>view{padding:16rpx;border:2rpx solid #e2e8f0;border-radius:14rpx}.settle-options .active{border-color:#3b82f6;background:#eff6ff}.settle-options strong,.settle-options text{display:block}.settle-options strong{color:#0f172a;font-size:22rpx}.settle-options text{margin-top:6rpx;color:#64748b;font-size:19rpx;line-height:1.45}.popup-actions{flex:none;display:grid;grid-template-columns:1fr 2fr;gap:14rpx;padding:16rpx 24rpx calc(16rpx + env(safe-area-inset-bottom));background:#fff;border-top:1rpx solid #e2e8f0}.option-popup{height:58vh;display:flex;flex-direction:column;background:#fff}.option-list{flex:1;min-height:0}</style>
