<template>
    <section class="desk-card journal" aria-label="单据与接口往来">
        <button v-if="!standalone" type="button" class="opener" :aria-expanded="open" @click="open=!open">
            <span><strong>单据与接口往来</strong><small>查本机应付、博远单号，以及每次发送和返回</small></span><span class="link">{{ open?'收起 ↑':'展开查看 ↓' }}</span>
        </button>
        <div v-if="open" class="content" :class="{standalone}">
            <el-radio-group :model-value="view" aria-label="记录类型" @update:model-value="changeView">
                <el-radio-button v-if="views.includes('purchases')" label="purchases">采购单</el-radio-button>
                <el-radio-button v-if="views.includes('payments')" label="payments">付款凭证</el-radio-button>
                <el-radio-button v-if="views.includes('documents')" label="documents">按设备查单据</el-radio-button>
                <el-radio-button v-if="operations" label="operations">操作记录</el-radio-button>
                <el-radio-button v-if="views.includes('exchanges')" label="exchanges">按时间查接口</el-radio-button>
            </el-radio-group>
            <div v-if="origin" class="origin"><el-button link type="primary" @click="backToDoc">← 返回单据明细</el-button><span>{{origin.serial}} · {{history ? '本笔任务的历史报文' : '按日期查询'}}</span><el-button v-if="history" link @click="leaveHistory">改按日期查</el-button></div>
            <BusinessLedger v-if="ledgerKind" v-show="isLedger" ref="ledger" :kind="ledgerKind" :initial="initial" :can-trace="views.includes('exchanges')" @trace="showBillCalls" />
            <component :is="operations" v-if="view==='operations' && operations" source="boyuan" />
            <template v-if="!isLedger && view!=='operations'">
            <form v-if="!history" class="filters" @submit.prevent="load(false)">
                <el-date-picker v-if="view==='exchanges'" v-model="day" type="date" placeholder="全部日期 · 最近在前" value-format="YYYY-MM-DD" clearable aria-label="请求日期" @change="load(false)" />
                <el-input v-model.trim="keyword" :placeholder="view==='documents'?'串号 / 本站单号 / 博远单号':'业务单号 / 接口名 / 调用编号'" clearable :maxlength="128" @clear="load(false)" />
                <el-button type="primary" native-type="submit" :loading="loading">查询</el-button>
            </form>
            <p v-if="error" role="alert" class="error">{{ error }} <el-button link type="primary" @click="load(false)">重试</el-button></p>
            <template v-else-if="view==='documents'">
                <div class="grid head" aria-hidden="true"><span>设备 / 串号</span><span>本站应付</span><span>博远采购单</span><span>博远付款单</span></div>
                <details v-for="r in docs" :key="r.id" class="entry" :open="expandedId===r.id">
                    <summary class="grid"><span><strong>{{r.model || '未填型号'}}</strong><small>{{r.serial}}</small></span>
                        <span>{{localState(r.payable)}}</span><span>{{brief(r.purchase,'暂无采购回执')}}<small v-if="r.purchase.length===1 && r.purchase[0].external_no">{{r.purchase[0].status_label}}</small></span><span>{{brief(r.payments,'暂无付款回执')}} <i>⌄</i></span></summary>
                    <div class="inset"><p>本站采购单：{{r.number}}</p>
                        <p v-if="hasAmounts(r.payable)">本机应付 ¥{{money(r.payable.amount)}} · 本站已结算 ¥{{money(r.payable.paid)}} · 还欠 ¥{{money(r.payable.remaining)}}</p>
                        <p v-for="s in r.settlements" :key="s.id">本站付款凭证：{{s.number}} · 分摊至本机 ¥{{money(s.amount)}} · {{time(s.at*1000)}}</p>
                        <section v-for="d in [...r.purchase,...r.payments]" :key="d.key"><b>{{d.provider_label}} · {{d.external_no ? d.external_no+' · ' : ''}}{{d.status_label}}</b>
                            <p>{{d.pc_next_step || d.next_step}}</p><small v-if="d.receipt_details?.length">{{ receiptText(d.receipt_details) }}</small>
                            <el-button v-if="views.includes('exchanges') && d.trace_ref" link type="primary" @click="showCalls(r,d)">查这笔接口往来</el-button>
                        </section>
                    </div>
                </details>
                <p v-if="!docs.length && !loading" class="muted">没有匹配的设备，请核对串号或单号。</p>
                <el-pagination v-if="total>10" layout="prev, pager, next, total" :page-size="10" :total="total" :current-page="page" @current-change="turn" />
            </template>
            <template v-else>
                <p class="muted">{{history?'按这次业务的调用记录查看，不限发送日期。':day?'按指定发送日期显示。':'已自动列出最近接口往来，不限今天。'}}展开记录可对照发送入参和返回出参；收到响应不等于完成核销。</p>
                <details v-for="r in calls" :key="r.call_id" class="entry">
                    <summary class="wire"><span>{{time(r.sent_ms)}}</span><strong>{{r.service || r.operation}}</strong><span>{{r.business_no || r.trace_id}}</span><span>{{delivery(r)}} <i>⌄</i></span></summary>
                    <div class="inset"><p>调用编号 {{r.call_id}}<span v-if="r.http_status"> · HTTP {{r.http_status}}</span><span v-if="r.elapsed_ms != null"> · {{r.elapsed_ms}} ms</span><span v-if="r.error_code"> · {{r.error_code}}</span></p>
                        <div class="pair">
                            <ExchangePayload :title="'发送入参 · '+time(r.sent_ms)" :brief="r.stage==='result'?r.request_payload:r.payload" :expandable="r.request_detail" :call-id="r.call_id" :day="r.journal_day || lockedQuery.day" side="request" />
                            <ExchangePayload :title="(r.delivery==='response_received'?'返回出参':'调用结果')+' · '+time(r.finished_ms)" :brief="r.stage==='result'?r.payload:null" :expandable="r.response_detail" :call-id="r.call_id" :day="r.journal_day || lockedQuery.day" side="response" />
                        </div>
                    </div>
                </details>
                <p v-if="!calls.length && !loading" class="muted">{{ next?'本段没有匹配记录，可继续往前查。':'当前条件下没有留存可读的接口报文。' }} 旧单仍可查已保存的单号；没有历史日志不能证明当时未发送。</p>
                <el-button v-if="next" :loading="loading" @click="load(true)">查看更早记录</el-button>
                <small class="privacy">长报文先显示预览，有已记录的明细可以展开；密钥、签名和收款资料不展示。查看日志不会重新调用博远。</small>
            </template>
            <p v-if="loading" class="muted" role="status">正在读取本站记录…</p>
            </template>
        </div>
    </section>
</template>
<script setup lang="ts">
import {computed,defineAsyncComponent,nextTick,ref,watch,onBeforeUnmount} from 'vue'
import {useRouter} from 'vue-router'
import request from '@/utils/request'
import {reason} from '../utils/connection'
import ExchangePayload from './ExchangePayload.vue'
import BusinessLedger from './BusinessLedger.vue'
const props=defineProps<{views:string[];initial?:string;standalone?:boolean}>()
const router=useRouter()
// Supplied by the optional log owner; never import its view as a hard dependency.
const optionalLists=import.meta.glob('/src/addon/*/extensions/business-operation-list.vue')
const auditLoader=optionalLists['/src/addon/hsx_recycle/extensions/business-operation-list.vue']
const operations=props.standalone && auditLoader && router.hasRoute('recycle_business_operation_logs') ? defineAsyncComponent(auditLoader as any) : null
type LedgerKind='purchases'|'payments'
const open=ref(!!(props.initial || props.standalone)),view=ref(props.standalone&&props.views.includes('purchases')?'purchases':props.views[0]),keyword=ref(props.initial || ''),day=ref<string|null>(null)
const origin=ref<null|{keyword:string;page:number;id:number;serial:string;ledger?:LedgerKind}>(null),history=ref(false),expandedId=ref<number|null>(null)
const isLedger=computed(()=>view.value==='purchases'||view.value==='payments')
const ledgerKind=computed(()=>isLedger.value?view.value as LedgerKind:origin.value?.ledger)
const ledger=ref<InstanceType<typeof BusinessLedger>|null>(null)
const loading=ref(false),error=ref(''),docs=ref<any[]>([]),calls=ref<any[]>([]),total=ref(0),page=ref(1),next=ref('')
let seq=0,lockedQuery={keyword:'',day:''}
function localDay(d:Date){return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-')}
const money=(v:unknown)=>Number(v).toFixed(2)
const hasAmounts=(v:any)=>['pending','partial','settled'].includes(v?.state) && [v.amount,v.paid,v.remaining].every(n=>n!=null && String(n).trim()!=='' && Number.isFinite(Number(n)))
const receiptText=(fields:any[])=>fields.map(v=>v.label+'：'+v.value).join('　')
const time=(v:unknown)=>Number(v)>0?new Date(Number(v)).toLocaleString('zh-CN',{hour12:false}):'无记录'
const localState=(h:any)=>h?.state==='void'?'已作废':!hasAmounts(h)?'待核对':h.state==='settled'?'已结清':'还欠 ¥'+money(h.remaining)
const brief=(ds:any[],fallback:string)=>!ds.length?fallback:ds.length>1?ds.length+' 笔 · 展开查看':ds[0].external_no || ds[0].status_label
const delivery=(r:any)=>r.stage!=='result'?'未见返回':r.delivery==='not_sent'?'未发出':r.delivery!=='response_received'?'未获确认响应':r.gateway_reply==='reported_error'?'博远返回异常':Number(r.http_status)>=400?'HTTP 异常':'收到响应'
function backToDoc(){const old=origin.value;if(!old)return;view.value=old.ledger || 'documents';keyword.value=old.keyword;page.value=old.page;expandedId.value=old.id;origin.value=null;history.value=false
    if(old.ledger){++seq;void nextTick(()=>ledger.value?.reopen());return}void load(false,true)}
function leaveHistory(){history.value=false;void load(false)}
function changeView(v:any){if((!props.views.includes(v) && !(v==='operations'&&operations))||v===view.value)return;if(origin.value && v===(origin.value.ledger||'documents')){backToDoc();return}origin.value=null;history.value=false;expandedId.value=null;day.value=null;view.value=v;keyword.value='';void load(false)}
function showBillCalls(trace:string,bill:string){if(!isLedger.value || !props.views.includes('exchanges') || !trace)return
    origin.value={keyword:'',page:1,id:0,serial:bill,ledger:view.value as LedgerKind};view.value='exchanges';history.value=true;keyword.value=trace;day.value=null;void load(false)}
function showCalls(r:any,d:any){origin.value={keyword:lockedQuery.keyword,page:page.value,id:r.id,serial:r.serial};view.value='exchanges'
    history.value=!!d.trace_ref
    keyword.value=d.trace_ref || (d.payment_task_id?(r.settlements.find((s:any)=>s.id===d.settlement_id)?.number || r.number):r.number)
    const when=d.event_at || r.settlements.find((s:any)=>s.id===d.settlement_id)?.at
    if(when)day.value=localDay(new Date(when*1000));void load(false)}
function turn(n:number){page.value=n;void load(false,true)}
async function load(more=false,keepPage=false){
    if(!open.value || isLedger.value || view.value==='operations'){++seq;loading.value=false;return}
    const queryDay=history.value?'history':day.value || 'recent'
    if(more && (lockedQuery.day!==queryDay || lockedQuery.keyword!==keyword.value))more=false
    const id=++seq
    if(!more){if(!keepPage){page.value=1;expandedId.value=null}next.value='';docs.value=[];calls.value=[];total.value=0;lockedQuery={keyword:keyword.value,day:queryDay}}
    error.value='';loading.value=true
    try{const res:any=await request.get('boyuan/logs',{params:{view:view.value,keyword:lockedQuery.keyword,day:lockedQuery.day,page:page.value,cursor:more?next.value:''}})
        if(id!==seq)return
        const data=res?.data;if(!Array.isArray(data?.rows))throw Error('服务器没有返回有效记录，请确认后端已更新。')
        if(view.value==='documents'){docs.value=data.rows;total.value=Number(data.total)}
        else {const items=new Map(calls.value.map((r:any)=>[r.call_id,r]));for(const r of data.rows)if(!items.has(r.call_id)||r.stage==='result')items.set(r.call_id,r)
            calls.value=[...items.values()];next.value=data.next || ''}
    }catch(e){if(id===seq)error.value=reason(e,'暂时无法读取，请重试。')}
    finally{if(id===seq)loading.value=false}
}
watch(open,on=>{if(on)void load(false);else{seq++;loading.value=false}},{immediate:true})
onBeforeUnmount(()=>{seq++})
</script>
<style scoped>
.content.standalone{margin-top:0}.origin{display:flex;align-items:center;gap:12px;font-size:13px;color:var(--el-text-color-secondary);margin:12px 0}.inset .reconcile-note{color:var(--el-text-color-primary)}
.journal{margin-top:16px}.opener{width:100%;border:0;background:transparent;cursor:pointer;color:inherit;text-align:left;padding:0;display:flex;align-items:center;justify-content:space-between}.opener strong{font-size:18px}.opener small,.entry small,.privacy{display:block;color:var(--el-text-color-secondary);font-size:12px;line-height:1.6}.opener small{margin-top:4px}.link,.entry i{color:var(--el-color-primary);font-size:13px}.entry i{float:right;font-style:normal}.content{margin-top:18px}.filters{display:flex;align-items:center;gap:10px;margin:14px 0;flex-wrap:wrap}.filters .el-input{max-width:300px}.grid,.wire{display:grid;grid-template-columns:minmax(140px,1.35fr) minmax(90px,.75fr) minmax(125px,1fr) minmax(180px,1.4fr);gap:12px;align-items:center;padding:12px}.wire{grid-template-columns:155px 1.5fr 1fr 120px;overflow-wrap:anywhere}.head{font-size:12px;color:var(--el-text-color-secondary);background:var(--el-fill-color-light)}.entry{border-bottom:1px solid var(--el-border-color-lighter);font-size:13px}.entry summary{cursor:pointer;list-style:none}.entry summary::-webkit-details-marker{display:none}.entry summary:hover,.entry[open] summary{background:var(--el-fill-color-light)}.inset{padding:10px 16px 16px 24px;border-left:2px solid var(--el-color-primary-light-5)}.inset section{margin:8px 0}.inset p,.muted{font-size:13px;color:var(--el-text-color-regular);margin:6px 0;line-height:1.6}.pair{display:grid;grid-template-columns:1fr 1fr;gap:12px}.pair section{min-width:0}pre{margin:6px 0;max-height:260px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere;background:var(--el-fill-color-light);padding:10px}.privacy,.el-pagination{margin-top:12px}.error{color:var(--el-color-danger)}@media(max-width:900px){.grid,.wire{grid-template-columns:1.2fr 1fr}.pair{grid-template-columns:1fr}}
</style>
