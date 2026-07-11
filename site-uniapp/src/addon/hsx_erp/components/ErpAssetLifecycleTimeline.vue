<template>
    <view class="lifecycle">
        <view class="lifecycle-legend">
            <view v-for="item in legends" :key="item.tone"><text class="legend-dot" :class="`tone-${item.tone}`" />{{ item.label }}</view>
        </view>
        <view v-if="!nodes.length" class="lifecycle-empty">暂无可展示的设备流转记录</view>
        <view v-for="(node, index) in nodes" :key="node.key" class="timeline-row">
            <view class="timeline-axis">
                <view class="timeline-dot" :class="`tone-${node.tone}`">{{ node.mark }}</view>
                <view v-if="index < nodes.length - 1" class="timeline-line" />
            </view>
            <view class="timeline-card" :class="`timeline-card--${node.tone}`">
                <view class="timeline-card__head">
                    <view>
                        <text class="timeline-action">{{ node.title }}</text>
                        <text class="timeline-cycle">第 {{ node.cycle_no }} 次入库周期</text>
                    </view>
                    <text class="timeline-time">{{ formatErpTime(node.occurred_at || node.create_at) }}</text>
                </view>
                <view v-if="node.before_status || node.after_status" class="status-flow">
                    <text>{{ node.before_status_text || statusLabel(node.before_status) }}</text>
                    <text class="status-arrow">→</text>
                    <text class="status-after">{{ node.after_status_text || statusLabel(node.after_status) }}</text>
                </view>
                <view class="timeline-meta">
                    <text v-if="node.party_name">{{ partyPrefix(node.action) }}：{{ node.party_name }}</text>
                    <text v-if="node.source_no">单据：{{ node.source_no }}</text>
                    <text v-if="node.asset_no">资产：{{ node.asset_no }}</text>
                </view>
                <text v-if="node.remark" class="timeline-remark">{{ node.remark }}</text>
            </view>
        </view>
        <view v-if="nodes.length" class="current-result" :class="`current-result--${currentTone}`">
            <text class="current-result__label">设备现在</text>
            <text class="current-result__value">{{ statusLabel(currentStatus) }}</text>
            <text class="current-result__hint">这是该串号所有 ERP 流转后的最终状态</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'

const props = withDefaults(defineProps<{ flows?: any[]; currentStatus?: string }>(), { flows: () => [], currentStatus: '' })
const legends = [
    { tone:'purchase', label:'采购' }, { tone:'sale', label:'销售' },
    { tone:'after-sale', label:'售后' }, { tone:'purchase-return', label:'采退' },
]
const meta:Record<string,{tone:string;mark:string;title:string}> = {
    inbound:{tone:'purchase',mark:'入',title:'采购入库'}, sold:{tone:'sale',mark:'售',title:'销售出库'},
    sale_return:{tone:'after-sale',mark:'退',title:'客户退货入库'}, sale_return_cancel:{tone:'after-sale',mark:'撤',title:'撤销销售退货'},
    sale_cancel:{tone:'after-sale',mark:'撤',title:'撤销销售'}, sale_item_cancel:{tone:'after-sale',mark:'撤',title:'撤销单台销售'},
    purchase_return:{tone:'purchase-return',mark:'采',title:'退还供应商'}, purchase_cancel:{tone:'purchase-return',mark:'撤',title:'撤销采购'},
    refurbish:{tone:'cost',mark:'整',title:'整备费用'}, cost_adjust:{tone:'cost',mark:'调',title:'成本调整'},
    flow:{tone:'other',mark:'流',title:'流转设置'}, flow_set:{tone:'other',mark:'流',title:'流转设置'},
}
const nodes = computed(() => (props.flows || []).map((flow:any,index:number) => {
    const current = meta[String(flow.action || '')] || {tone:'other',mark:'记',title:flow.action_text || '设备记录'}
    return {...flow,...current,title:flow.action_text || current.title,key:`${flow.id || index}-${flow.cycle_no || 1}`}
}))
const statusLabel=(s:string)=>({in_stock:'在我的库存',sold:'已销售给客户',returned:'已退还供应商',void:'已作废',available_for_sale:'可销售'}[s]||s||'未知状态')
const partyPrefix=(action:string)=>['inbound','purchase_return','purchase_cancel'].includes(action)?'供应商':['sold','sale_return','sale_return_cancel','sale_cancel','sale_item_cancel'].includes(action)?'客户':'往来方'
const currentTone=computed(()=>({in_stock:'purchase',sold:'sale',returned:'purchase-return',void:'other'} as any)[props.currentStatus]||'other')
</script>

<style scoped lang="scss">
.lifecycle{padding:0 24rpx 30rpx}.lifecycle-legend{display:flex;align-items:center;gap:22rpx;margin:6rpx 0 26rpx;padding:16rpx 18rpx;border-radius:14rpx;background:#f8fafc;color:#64748b;font-size:21rpx}.lifecycle-legend view{display:flex;align-items:center;gap:7rpx}.legend-dot{width:13rpx;height:13rpx;border-radius:50%}.timeline-row{display:flex;align-items:stretch;gap:18rpx}.timeline-axis{position:relative;width:54rpx;flex:none;display:flex;justify-content:center}.timeline-dot{position:relative;z-index:1;width:50rpx;height:50rpx;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20rpx;font-weight:800;box-shadow:0 0 0 8rpx #fff}.timeline-line{position:absolute;top:50rpx;bottom:-18rpx;width:4rpx;background:#e2e8f0}.timeline-card{flex:1;min-width:0;margin-bottom:22rpx;padding:20rpx;border:1rpx solid #e2e8f0;border-left-width:6rpx;border-radius:16rpx;background:#fff;box-shadow:0 3rpx 14rpx rgba(15,23,42,.04)}.timeline-card__head{display:flex;align-items:flex-start;justify-content:space-between;gap:14rpx}.timeline-action,.timeline-cycle{display:block}.timeline-action{color:#0f172a;font-size:27rpx;font-weight:750}.timeline-cycle{margin-top:4rpx;color:#94a3b8;font-size:19rpx}.timeline-time{flex:none;color:#64748b;font-size:20rpx}.status-flow{display:flex;align-items:center;gap:10rpx;margin-top:14rpx;padding:11rpx 13rpx;border-radius:10rpx;background:#f8fafc;color:#64748b;font-size:21rpx}.status-arrow{color:#94a3b8}.status-after{color:#0f172a;font-weight:700}.timeline-meta{display:flex;flex-wrap:wrap;gap:7rpx 16rpx;margin-top:13rpx;color:#64748b;font-size:20rpx}.timeline-remark{display:block;margin-top:11rpx;padding-top:10rpx;border-top:1rpx dashed #e2e8f0;color:#475569;font-size:20rpx;line-height:1.5}.current-result{margin-left:72rpx;padding:20rpx 22rpx;border-radius:16rpx;background:#eff6ff}.current-result text{display:block}.current-result__label{color:#64748b;font-size:20rpx}.current-result__value{margin-top:5rpx;color:#1d4ed8;font-size:30rpx;font-weight:800}.current-result__hint{margin-top:5rpx;color:#64748b;font-size:20rpx}.lifecycle-empty{padding:70rpx 0;text-align:center;color:#94a3b8;font-size:23rpx}.tone-purchase{background:#3b82f6}.tone-sale{background:#16a34a}.tone-after-sale{background:#f59e0b}.tone-purchase-return{background:#ef4444}.tone-cost{background:#8b5cf6}.tone-other{background:#94a3b8}.timeline-card--purchase{border-left-color:#3b82f6}.timeline-card--sale{border-left-color:#16a34a}.timeline-card--after-sale{border-left-color:#f59e0b}.timeline-card--purchase-return{border-left-color:#ef4444}.timeline-card--cost{border-left-color:#8b5cf6}.timeline-card--other{border-left-color:#94a3b8}.current-result--sale{background:#f0fdf4}.current-result--sale .current-result__value{color:#15803d}.current-result--purchase-return{background:#fef2f2}.current-result--purchase-return .current-result__value{color:#dc2626}.current-result--other{background:#f8fafc}.current-result--other .current-result__value{color:#475569}
</style>
