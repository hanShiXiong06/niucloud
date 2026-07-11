<template>
    <view class="erp-page">
        <ErpPageHeader :title="isCompensation ? '售后补差详情' : '销售退货详情'" />
        <view class="detail-wrap">
            <view v-if="loading" class="empty-card">正在加载...</view>
            <template v-else-if="detail">
                <view class="form-card hero-card">
                    <view class="hero-head"><view><text class="hero-label">{{ isCompensation ? '补差客户' : '退货客户' }}</text><text class="hero-name">{{ detail.party_name || '-' }}</text></view><u-tag :text="statusLabel(detail.status)" :type="statusType(detail.status)" plain plainFill /></view>
                    <view class="identity"><ErpCopyText :value="detail.return_no" :title="isCompensation ? '补差单号' : '退货单号'" block /><ErpCopyText :value="detail.sale_no" title="原销售单号" block /></view>
                    <view class="meta-grid"><view><text>处理方式</text><strong>{{ refundModeLabel(detail.refund_mode) }}</strong></view><view><text>操作人</text><strong>{{ detail.operator_name || '-' }}</strong></view><view><text>设备</text><strong>{{ items.length }} 台</strong></view><view><text>{{ isCompensation ? '补差金额' : '退款金额' }}</text><strong class="orange">¥{{ money(detail.total_amount) }}</strong></view></view>
                    <view v-if="detail.remark" class="reason">{{ detail.remark }}</view>
                </view>

                <view class="section-title">设备与账务明细</view>
                <view v-for="item in items" :key="item.id" class="erp-card device-card">
                    <view class="erp-card__head"><view><text class="card-title">{{ item.model || '-' }}</text><text class="card-meta">{{ item.spec || '未填写规格' }}</text></view><u-tag :text="assetStatus(item.asset_status)" :type="item.asset_status === 'in_stock' ? 'success' : 'primary'" plain size="mini" /></view>
                    <view class="device-id">IMEI {{ item.imei || '-' }} · {{ item.asset_no || '-' }}</view>
                    <view class="money-grid"><view><text>原售价</text><strong>¥{{ money(item.sale_price) }}</strong></view><view><text>{{ isCompensation ? '补差' : '本次退货' }}</text><strong class="orange">¥{{ money(item.return_price) }}</strong></view><view><text>已收货款</text><strong>¥{{ money(item.received_amount) }}</strong></view><view><text>退款应付</text><strong>¥{{ money(item.refund_payable_amount) }}</strong></view></view>
                    <view v-if="item.refund_payable_no" class="payable-box" @click="goPayable"><view><text>客户退款应付</text><ErpCopyText :value="item.refund_payable_no" title="应付单号" /></view><view><strong>已付 ¥{{ money(item.refund_settled_amount) }}</strong><text>剩余 ¥{{ money(item.refund_remain_amount) }} ›</text></view></view>
                    <view v-if="item.reason" class="reason">原因：{{ item.reason }}</view>
                </view>

                <view class="process-card"><text class="process-card__title">处理结果</text><text>{{ processText }}</text></view>
            </template>
        </view>
        <view v-if="detail" class="float-bar">
            <u-button v-if="hasPayable" type="primary" plain @click="goPayable">查看并处理应付</u-button>
            <u-button v-if="detail.can_cancel" type="error" :loading="cancelling" @click="cancelReturn">撤销退货</u-button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { cancelMobileSaleReturn, getMobileSaleReturnInfo } from '@/addon/hsx_erp/api/erp'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpCopyText from '@/addon/hsx_erp/components/ErpCopyText.vue'
const id=ref(0),detail=ref<any>(null),loading=ref(false),cancelling=ref(false)
const items=computed(()=>detail.value?.items||[]),isCompensation=computed(()=>detail.value?.business_type==='after_sale_compensation'),hasPayable=computed(()=>items.value.some((row:any)=>row.refund_payable_no))
const processText=computed(()=>{if(detail.value?.status==='cancelled')return '本单已撤销，未继续执行退款。';if(isCompensation.value)return detail.value?.refund_mode==='cash'?'补差已从指定账户实际支付，设备继续由客户持有。':'补差已形成客户应付，等待财务付款或折账。';return detail.value?.refund_mode==='cash'?'设备已回库，退款已从指定账户支出。':'设备已回库，已按设备生成客户退款应付。'})
onLoad((q:any)=>id.value=Number(q?.id||0));onShow(load)
async function load(){if(!id.value)return;loading.value=true;try{const res:any=await getMobileSaleReturnInfo(id.value);detail.value=res?.data||null}finally{loading.value=false}}
function goPayable(){if(!detail.value)return;uni.navigateTo({url:`/addon/hsx_erp/pages/payable/detail?party_id=${Number(detail.value.party_id||0)}&source_type=sale_return&purchase_order_id=${Number(detail.value.id||0)}&party_name=${encodeURIComponent(detail.value.party_name||'')}`})}
async function cancelReturn(){if(cancelling.value)return;const yes=await confirmErpSensitiveAction({title:'确认撤销销售退货',content:'仅限退款应付尚未付款或折账。撤销后设备恢复原销售关系，退款应付作废。',confirmText:'确认撤销',cancelText:'返回'});if(!yes)return;cancelling.value=true;try{await cancelMobileSaleReturn(id.value,{remark:'移动端详情撤销退货'});uni.showToast({title:'退货已撤销',icon:'success'});await load()}catch(e:any){uni.showToast({title:e?.message||'撤销失败',icon:'none'})}finally{cancelling.value=false}}
const money=(v:any)=>Number(v||0).toFixed(2),refundModeLabel=(v:string)=>v==='cash'?'现场退款':'转财务处理',assetStatus=(v:string)=>({in_stock:'已回库',sold:'客户持有',returned:'已退'}[v]||v||'-'),statusLabel=(v:string)=>({pending:'待确认',confirmed:'已完成',cancelled:'已撤销'}[v]||v||'-'),statusType=(v:string)=>({pending:'warning',confirmed:'success',cancelled:'info'}[v]||'info')
</script>
<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.detail-wrap{padding:22rpx 22rpx 150rpx}.hero-head{display:flex;align-items:flex-start;justify-content:space-between}.hero-label,.hero-name{display:block}.hero-label{color:#94a3b8;font-size:21rpx}.hero-name{margin-top:5rpx;color:#0f172a;font-size:34rpx;font-weight:750}.identity{display:grid;gap:10rpx;margin-top:18rpx}.meta-grid,.money-grid{display:grid;grid-template-columns:1fr 1fr;gap:12rpx;margin-top:16rpx}.meta-grid view,.money-grid view{padding:14rpx;border-radius:12rpx;background:#f8fafc}.meta-grid text,.meta-grid strong,.money-grid text,.money-grid strong{display:block}.meta-grid text,.money-grid text{color:#94a3b8;font-size:20rpx}.meta-grid strong,.money-grid strong{margin-top:5rpx;color:#334155;font-size:24rpx}.orange{color:#ea580c!important}.device-id{margin-top:12rpx;color:#64748b;font-size:22rpx}.payable-box{display:flex;align-items:center;justify-content:space-between;gap:14rpx;margin-top:15rpx;padding:15rpx;border-radius:12rpx;background:#eff6ff;color:#1d4ed8}.payable-box view{min-width:0}.payable-box text,.payable-box strong{display:block;font-size:21rpx}.payable-box strong{margin-bottom:5rpx}.reason{margin-top:14rpx;padding:14rpx;border-radius:10rpx;background:#f8fafc;color:#475569;font-size:22rpx;line-height:1.5}.process-card{margin:20rpx 0;padding:20rpx;border-radius:16rpx;background:#ecfdf5;color:#166534;font-size:23rpx;line-height:1.6}.process-card__title{display:block;font-size:26rpx;font-weight:700}.empty-card{padding:60rpx;text-align:center;color:#94a3b8}
</style>
