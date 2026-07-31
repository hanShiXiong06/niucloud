<template>
    <view class="erp-page trace-detail-page">
        <ErpPageHeader title="串号生命周期" subtitle="从第一次入库到当前状态，一条时间轴看懂设备经历" />
        <view v-if="loading" class="loading-wrap"><u-loading-icon size="34" /></view>
        <view v-else-if="error" class="error-wrap"><u-empty mode="data" :text="error" /><u-button type="primary" size="small" text="重新加载" @click="load" /></view>
        <scroll-view v-else scroll-y class="trace-scroll">
            <view class="trace-summary">
                <view class="trace-summary__head">
                    <view><text class="trace-model">{{ detail.model || '未填写设备名称' }}</text><text class="trace-spec">{{ detail.spec || '未填写规格' }}</text></view>
                    <u-tag :text="statusLabel(detail.current_status)" :type="statusType(detail.current_status)" plain plainFill size="mini" />
                </view>
                <view class="serial-box"><text>IMEI / SN</text><strong selectable>{{ detail.serial_no || '-' }}</strong></view>
                <view class="trace-stats">
                    <view><strong>{{ detail.inbound_count || 0 }}</strong><text>入库次数</text></view>
                    <view><strong>{{ detail.sale_count || 0 }}</strong><text>销售次数</text></view>
                    <view><strong>{{ detail.after_sale_count || 0 }}</strong><text>售后退回</text></view>
                    <view><strong>{{ detail.purchase_return_count || 0 }}</strong><text>采购退货</text></view>
                </view>
            </view>

            <view class="cycle-section">
                <view class="section-head"><text>入库周期</text><text>每次重新入库都是一段独立业务</text></view>
                <scroll-view scroll-x class="cycle-scroll">
                    <view class="cycle-list">
                        <view v-for="cycle in detail.cycles || []" :key="cycle.id" class="cycle-card" :class="{'cycle-card--current':cycle.is_current}" @click="goAsset(cycle.id)">
                            <text class="cycle-no">第 {{ cycle.cycle_no }} 次</text>
                            <text v-if="canViewSupplier" class="cycle-party">{{ cycle.party_name || '未记录供应商' }}</text>
                            <text class="cycle-time">{{ formatErpTime(cycle.stock_in_at || cycle.create_at) }}</text>
                            <text class="cycle-link">{{ cycle.is_current ? '当前周期' : '查看档案' }} ›</text>
                        </view>
                    </view>
                </scroll-view>
            </view>

            <view class="section-head timeline-head"><text>完整流转时间轴</text><text>由早到晚，颜色代表不同业务阶段</text></view>
            <ErpAssetLifecycleTimeline :flows="detail.timeline || []" :current-status="detail.current_status" />
            <view class="bottom-space" />
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getMobileSerialTraceDetail } from '@/addon/hsx_erp/api/erp'
import { formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpAssetLifecycleTimeline from '@/addon/hsx_erp/components/ErpAssetLifecycleTimeline.vue'
const id=ref(0),loading=ref(true),error=ref(''),detail=ref<any>({})
const canViewSupplier=computed(()=>Number(detail.value?.capabilities?.view_supplier||0)===1)
onLoad((query:any)=>{id.value=Number(query?.id||0);load()})
async function load(){if(!id.value){error.value='缺少设备参数';loading.value=false;return}loading.value=true;error.value='';try{const res:any=await getMobileSerialTraceDetail(id.value);detail.value=res?.data||{}}catch(e:any){error.value=e?.message||'串号生命周期加载失败'}finally{loading.value=false}}
function goAsset(assetId:number){uni.navigateTo({url:`/addon/hsx_erp/pages/stock/detail?id=${Number(assetId||0)}`})}
const statusLabel=(s:string)=>({in_stock:'在库',sold:'已售',returned:'已采退',void:'已作废'}[s]||s||'-')
const statusType=(s:string)=>({in_stock:'success',sold:'primary',returned:'error',void:'info'}[s]||'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.trace-detail-page{height:100vh;overflow:hidden}.trace-scroll{height:calc(100vh - 112rpx)}.loading-wrap,.error-wrap{padding:100rpx 40rpx;display:flex;flex-direction:column;align-items:center;gap:20rpx}.trace-summary{margin:20rpx 24rpx;padding:24rpx;border-radius:22rpx;background:linear-gradient(135deg,#eff6ff,#fff);border:1rpx solid #dbeafe}.trace-summary__head{display:flex;justify-content:space-between;align-items:flex-start;gap:18rpx}.trace-model,.trace-spec{display:block}.trace-model{color:#0f172a;font-size:31rpx;font-weight:800}.trace-spec{margin-top:5rpx;color:#64748b;font-size:22rpx}.serial-box{display:flex;align-items:center;justify-content:space-between;margin-top:18rpx;padding:15rpx 17rpx;border-radius:12rpx;background:rgba(255,255,255,.78)}.serial-box text{color:#64748b;font-size:21rpx}.serial-box strong{color:#1d4ed8;font-size:26rpx}.trace-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8rpx;margin-top:17rpx}.trace-stats view{text-align:center}.trace-stats strong,.trace-stats text{display:block}.trace-stats strong{color:#0f172a;font-size:28rpx}.trace-stats text{margin-top:4rpx;color:#64748b;font-size:18rpx}.section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14rpx;margin:28rpx 24rpx 16rpx}.section-head text:first-child{color:#0f172a;font-size:28rpx;font-weight:800}.section-head text:last-child{color:#94a3b8;font-size:19rpx}.cycle-scroll{width:100%;white-space:nowrap}.cycle-list{display:inline-flex;gap:14rpx;padding:0 24rpx 10rpx}.cycle-card{width:280rpx;padding:18rpx;border:1rpx solid #e2e8f0;border-radius:16rpx;background:#fff;box-sizing:border-box}.cycle-card--current{border-color:#60a5fa;background:#eff6ff}.cycle-card text{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cycle-no{color:#2563eb;font-size:22rpx;font-weight:800}.cycle-party{margin-top:7rpx;color:#334155;font-size:23rpx;font-weight:650}.cycle-time{margin-top:5rpx;color:#94a3b8;font-size:19rpx}.cycle-link{margin-top:12rpx;color:#2563eb;font-size:20rpx}.timeline-head{margin-top:30rpx}.bottom-space{height:50rpx}
</style>
