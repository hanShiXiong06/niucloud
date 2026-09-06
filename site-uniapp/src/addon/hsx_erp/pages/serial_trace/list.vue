<template>
    <view class="erp-page">
        <ErpListHeader v-model="keyword" :placeholder="canViewSupplier ? '输入 IMEI / SN / 型号 / 供货商' : '输入 IMEI / SN / 型号'" :show-scan="true" :compact-mp="true" @search="reload" @scan="scan">
            <template #below>
                <ErpQuickFilterBar :items="quickFilters" @change="onQuickFilter" />
            </template>
        </ErpListHeader>

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true" :default-page-size="15" :paging-style="pagingStyle">
              <view class="trace-tip">同一串号允许多次入库；每次入库作为独立记录，最新记录排在最上面。</view>
            <template #empty><u-empty mode="list" text="暂无串号记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card trace-card" @click="goDetail(row)">
                    <view class="erp-card__head">
                        <view class="trace-title"><text class="card-title">{{ row.model || '未填写设备名称' }}</text><text class="trace-serial">{{ row.serial_no || '-' }}</text></view>
                        <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="trace-main">
                        <view v-if="canViewSupplier"><text>供货商</text><strong>{{ row.party_name || '未记录' }}</strong></view>
                        <view><text>入库时间</text><strong>{{ formatErpTime(row.stock_in_at || row.create_at) }}</strong></view>
                    </view>
                    <view class="trace-meta">{{ row.spec || '未填写规格' }} · {{ row.warehouse_name || '-' }}{{ row.location_name ? ' / ' + row.location_name : '' }}</view>
                    <view class="trace-foot"><text v-if="Number(row.inbound_count) > 1" class="repeat">该串号累计入库 {{ row.inbound_count }} 次</text><text v-else>首次入库记录</text><text class="detail">查看完整流转 ›</text></view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { getMobileSerialTraceList } from '@/addon/hsx_erp/api/erp'
import { formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpQuickFilterBar from '@/addon/hsx_erp/components/ErpQuickFilterBar.vue'
import { showErpError } from '@/addon/hsx_erp/utils/error'
const { pagingStyle } = useListHeader({ tabs: true, compactMp: true, h5TopRpx: 178 })
const keyword=ref(''),list=ref<any[]>([]),pagingRef=ref<any>(null)
const capabilities=ref<any>({view_supplier:0})
const canViewSupplier=computed(()=>Number(capabilities.value?.view_supplier||0)===1)
const status=ref(''),datePreset=ref('')
const quickFilters=computed(()=>[
    {key:'status',label:'设备状态',title:'设备状态',value:status.value,options:[
        {label:'全部状态',value:''},{label:'在库',value:'in_stock'},{label:'已售',value:'sold'},{label:'已退货',value:'returned'},{label:'已作废',value:'void'},
    ]},
    {key:'date',label:'入库时间',title:'入库时间',value:datePreset.value,options:[
        {label:'全部时间',value:''},{label:'今日入库',value:'today'},{label:'本月入库',value:'month'},{label:'上月入库',value:'last_month'},
    ]},
])
const reload=()=>pagingRef.value?.reload()
const onQuickFilter=({key,value}:{key:string,value:string|number})=>{if(key==='status')status.value=String(value);if(key==='date')datePreset.value=String(value);reload()}
async function queryList(page:number,limit:number){try{const range=dateRange(datePreset.value);const res:any=await getMobileSerialTraceList({keyword:keyword.value,status:status.value,...range,page,limit});capabilities.value=res?.data?.capabilities||capabilities.value;pagingRef.value?.complete(res?.data?.data||[])}catch(error){pagingRef.value?.complete(false);showErpError(error,'串号轨迹加载失败，请检查网络后重试')}}
async function scan(){try{keyword.value=await scanErpCode();reload()}catch(e:any){if(!String(e?.errMsg||'').includes('cancel'))uni.showToast({title:e?.message||'扫码失败',icon:'none'})}}
function goDetail(row:any){uni.navigateTo({url:`/addon/hsx_erp/pages/serial_trace/detail?id=${Number(row.id||0)}`})}
const statusLabel=(s:string)=>({in_stock:'在库',sold:'已售',returned:'已退货',void:'已作废'}[s] || '状态待确认')
const statusType=(s:string)=>({in_stock:'success',sold:'primary',returned:'warning',void:'info'}[s]||'info')
function dateRange(value:string){
    if(!value)return {}
    const now=new Date(),year=now.getFullYear(),month=now.getMonth()
    let start=new Date(year,month,1),end=new Date(year,month+1,1)
    if(value==='today'){start=new Date(year,month,now.getDate());end=new Date(year,month,now.getDate()+1)}
    if(value==='last_month'){start=new Date(year,month-1,1);end=new Date(year,month,1)}
    return {start_at:Math.floor(start.getTime()/1000),end_at:Math.floor(end.getTime()/1000)-1}
}
</script>
<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.trace-tip{margin:14rpx 22rpx 0;padding:16rpx 18rpx;border-radius:14rpx;background:#eff6ff;color:#475569;font-size:22rpx;line-height:1.5}.trace-card{padding:22rpx 24rpx}.trace-title{min-width:0;display:flex;flex-direction:column;gap:7rpx}.trace-serial{color:#2563eb;font-size:25rpx;font-weight:700}.trace-main{display:grid;grid-template-columns:1fr 1fr;gap:12rpx;margin-top:16rpx}.trace-main view{padding:14rpx;border-radius:12rpx;background:#f8fafc}.trace-main text,.trace-main strong{display:block}.trace-main text{color:#94a3b8;font-size:20rpx}.trace-main strong{margin-top:5rpx;color:#334155;font-size:23rpx}.trace-meta{margin-top:12rpx;color:#64748b;font-size:22rpx}.trace-foot{display:flex;justify-content:space-between;gap:16rpx;margin-top:15rpx;padding-top:14rpx;border-top:1rpx solid #f1f5f9;color:#94a3b8;font-size:21rpx}.trace-foot .repeat{color:#c2410c}.trace-foot .detail{color:#2563eb}
</style>
