<template>
    <section :aria-label="payment ? '付款凭证列表' : '采购单列表'" class="ledger">
        <form class="toolbar" @submit.prevent="search">
            <el-input v-model.trim="draft" :placeholder="payment ? '付款凭证号 / 串号 / 博远单号' : '采购单号 / 串号 / 博远单号'" clearable :maxlength="128" @clear="search" />
            <el-button type="primary" native-type="submit" :loading="busy">查询</el-button>
            <span class="hint">最近{{payment ? '登记' : '采购'}}在前 · 共 {{ total }} {{ payment ? ' 张凭证' : ' 张采购单' }}</span>
        </form>
        <el-alert v-if="error" type="error" :closable="false" :title="error"><el-button link @click="load">重新读取</el-button></el-alert>
        <el-table v-else v-loading="busy" :data="rows" row-key="id" :empty-text="busy ? '正在读取…' : term ? '没有匹配单据，可清空筛选' : '暂无相关单据'" class="bills">
            <el-table-column :label="payment?'本站付款凭证号':'本站采购单号'" min-width="220">
                <template #default="{row}"><el-button class="number" link type="primary" @click="inspect(row)">{{row.number}}</el-button></template>
            </el-table-column>
            <el-table-column :label="payment?'登记时间':'采购时间'" min-width="155"><template #default="{row}">{{when(row.at)}}</template></el-table-column>
            <el-table-column :label="payment?'凭证金额':'采购金额'" align="right" min-width="108"><template #default="{row}">{{money(row.amount)}}</template></el-table-column>
            <el-table-column label="关联设备" align="center" width="100"><template #default="{row}">{{row.device_count==null?'—':row.device_count+' 台'}}</template></el-table-column>
            <el-table-column label="本站状态" min-width="110"><template #default="{row}">{{row.status_label || '未记录'}}</template></el-table-column>
            <el-table-column label="操作" align="right" width="96"><template #default="{row}"><el-button link type="primary" @click="inspect(row)">查看明细</el-button></template></el-table-column>
        </el-table>
        <el-pagination v-if="total>10" layout="prev, pager, next, total" :page-size="10" :total="total" :current-page="page" @current-change="turn" />
        <el-drawer v-model="shown" :title="payment?'付款凭证明细':'采购单明细'" size="min(740px, 92vw)" :append-to-body="true">
            <template v-if="selected">
                <header class="facts"><strong>{{selected.number}}</strong><span>{{payment?'本站登记金额':'本站采购金额'}} {{money(selected.amount)}}</span></header>
                <p class="caption">{{payment?'以下金额是这张凭证对各台设备的分摊，不是多次付款。':'按设备核对本站应付和博远返回的单号。'}}</p>
                <p v-if="payment" class="notice">付款已在本站登记，无需再付。未取得博远指定核销明细时，原采购核销仍待核对。</p>
                <el-alert v-if="detailError" type="error" :closable="false" :title="detailError"><el-button link @click="loadDetail">重新读取明细</el-button></el-alert>
                <div v-else v-loading="detailBusy" class="devices">
                    <el-empty v-if="!detailBusy && !lines.length" description="未找到可核对的设备关联，请核对原单。" />
                    <article v-for="r in lines" :key="r.id">
                        <header><strong>{{r.model||'未填型号'}}</strong><span>{{r.serial || '未记录串号'}}</span></header>
                        <div v-if="payment" v-for="s in r.settlements" :key="s.id" class="allocation">本张凭证分摊 {{money(s.amount)}}</div>
                        <div v-if="payment" class="caption">来源采购单：{{r.number || '待核对'}}</div>
                        <div v-if="hasAmounts(r.payable)" class="caption">本站应付 {{money(r.payable.amount)}} · 已结算 {{money(r.payable.paid)}} · 剩余 {{money(r.payable.remaining)}}</div>
                        <div v-else class="caption">本机原应付：待核对</div>
                        <div v-for="d in (payment ? r.payments : r.purchase)" :key="d.key" class="receipt">
                            <div><strong>{{d.provider_label}}</strong> {{d.external_no||'暂无单号'}} · {{d.status_label}}</div>
                            <p v-if="d.pc_next_step || d.next_step" class="caption">{{d.pc_next_step || d.next_step}}</p>
                            <el-button v-if="canTrace && d.trace_ref" link type="primary" @click="trace(d)">查看接口收发</el-button>
                        </div>
                    </article>
                </div>
                <el-pagination v-if="!detailError && lineTotal>10" :page-size="10" layout="prev, pager, next, total" :total="lineTotal" :current-page="linePage" @current-change="turnDetail" />
            </template>
        </el-drawer>
    </section>
</template>
<script setup lang="ts">
import {computed,onBeforeUnmount,ref,watch} from 'vue'
import request from '@/utils/request'
import {reason} from '../utils/connection'
type Bill={id:number;number:string;at:number;amount:string|number|null;device_count:number|null;status_label?:string}
const props=defineProps<{kind:'purchases'|'payments';initial?:string;canTrace:boolean}>()
const emit=defineEmits<{(e:'trace',trace:string,label:string):void}>()
const payment=computed(()=>props.kind==='payments')
const draft=ref(props.initial||''),term=ref(draft.value),page=ref(1),rows=ref<Bill[]>([]),total=ref(0),busy=ref(false),error=ref('')
const selected=ref<Bill|null>(null),shown=ref(false),lines=ref<any[]>([]),linePage=ref(1),lineTotal=ref(0),detailBusy=ref(false),detailError=ref('')
let listEpoch=0,detailEpoch=0
const valid=(v:unknown)=>v!=null && String(v).trim()!=='' && Number.isFinite(Number(v))
const money=(v:unknown)=>valid(v)?'¥'+Number(v).toFixed(2):'待核对'
const hasAmounts=(h:any)=>['partial','pending','settled'].includes(h?.state)&&[h.amount,h.paid,h.remaining].every(valid)
const when=(v:number)=>v>0?new Date(v*1000).toLocaleString('zh-CN',{hour12:false}):'—'
async function load(){
    const epoch=++listEpoch;busy.value=true;error.value='';rows.value=[];total.value=0
    try{const res:any=await request.get('boyuan/logs',{params:{view:props.kind,page:page.value,keyword:term.value}})
        if(epoch!==listEpoch)return
        if(!Array.isArray(res?.data?.rows)||!Number.isFinite(Number(res.data.total)))throw Error('没有取得单据列表，请重新读取。')
        rows.value=res.data.rows;total.value=Number(res.data.total)
    }catch(e){if(epoch===listEpoch)error.value=reason(e,'暂时无法读取单据。')}
    finally{if(epoch===listEpoch)busy.value=false}
}
function search(){term.value=draft.value;page.value=1;void load()}
function turn(n:number){page.value=n;void load()}
function inspect(r:Bill){selected.value=r;linePage.value=1;shown.value=true;void loadDetail()}
function turnDetail(n:number){linePage.value=n;void loadDetail()}
async function loadDetail(){
    const bill=selected.value;if(!bill)return
    const epoch=++detailEpoch;lines.value=[];lineTotal.value=0;detailBusy.value=true;detailError.value=''
    try{const res:any=await request.get('boyuan/logs',{params:{view:payment.value?'payment_detail':'purchase_detail',id:bill.id,page:linePage.value}})
        if(epoch!==detailEpoch)return
        if(Number(res?.data?.id)!==Number(bill.id)||!Array.isArray(res?.data?.rows))throw Error('单据明细不完整，请重新核对。')
        lines.value=res.data.rows;lineTotal.value=Number(res.data.total)
    }catch(e){if(epoch===detailEpoch)detailError.value=reason(e,'无法读取这张原单。')}
    finally{if(epoch===detailEpoch)detailBusy.value=false}
}
function trace(d:any){shown.value=false;emit('trace',d.trace_ref,selected.value!.number)}
function reopen(){if(selected.value)shown.value=true}
defineExpose({reopen})
watch(()=>[props.kind,props.initial],()=>{++detailEpoch;shown.value=false;selected.value=null;draft.value=props.initial||'';search()},{immediate:true})
onBeforeUnmount(()=>{++listEpoch;++detailEpoch})
</script>
<style scoped>
.toolbar,.facts,article header{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.toolbar{margin:16px 0}.toolbar .el-input{max-width:320px}.hint,.caption,article header span{color:var(--el-text-color-secondary);font-size:13px}.hint{margin-left:auto}.caption{line-height:1.65;margin:5px 0}.facts{justify-content:space-between}.facts strong{font-size:17px;overflow-wrap:anywhere}.facts span,.allocation{font-variant-numeric:tabular-nums}.notice{padding:9px 12px;background:var(--el-color-info-light-9);border-radius:4px;font-size:13px}.devices{min-height:70px}.devices article{padding:14px 0;border-bottom:1px solid var(--el-border-color-lighter)}.receipt{margin-top:8px;font-size:13px}.el-pagination{justify-content:flex-end;margin-top:12px}
</style>
