<template>
    <section class="wire-proof" aria-label="博远原单接口往来">
        <h3>{{ query?.call ? '本次接口往来' : '这笔业务的接口往来' }}</h3>
        <p v-if="row.action==='payment_result' && row.external_no" class="reconcile">
            博远付款单 {{row.external_no}} · 原采购核销待核对。此回执是付款登记，不需要再次付款。
        </p>
        <p v-if="loading" role="status">正在读取本站保存的接口记录…</p>
        <el-alert v-if="error" :title="error" type="warning" :closable="false">
            <el-button link type="primary" @click="load(!!cursor)">重试读取</el-button>
        </el-alert>
        <details v-for="r in rows" :key="r.call_id" :open="Boolean(query?.call) || rows.length===1">
            <summary>{{r.service || r.operation || '接口调用'}} · {{stamp(r.sent_ms)}} · {{r.delivery==='response_received'?'收到返回':r.stage==='result'?'未取得有效返回':'已发出'}}</summary>
            <small>调用编号 {{r.call_id}}<template v-if="r.http_status"> · HTTP {{r.http_status}}</template></small>
            <div class="pair">
                <ExchangePayload title="实际发送入参" :brief="r.stage==='result'?r.request_payload:r.payload" :expandable="r.request_detail" :call-id="r.call_id" :day="r.journal_day" side="request" />
                <ExchangePayload :title="r.delivery==='response_received'?'实际返回出参':'调用结果（非成功回执）'" :brief="r.stage==='result'?r.payload:null" :expandable="r.response_detail" :call-id="r.call_id" :day="r.journal_day" side="response" />
            </div>
        </details>
        <p v-if="!rows.length && !loading && !error">{{cursor?'本段日志没有匹配到本次调用，可以继续向前查。':'本站当前账套没有留存这笔调用的可读报文；不能据此判断未发送。'}}</p>
        <el-button v-if="cursor && !error" :loading="loading" link type="primary" @click="load(true)">查看更早记录</el-button>
        <small>只读本站留存报文，敏感字段不展示；查看不会重发、付款或核销。</small>
    </section>
</template>
<script setup lang="ts">
import {computed,onBeforeUnmount,ref,watch} from 'vue'
import request from '@/utils/request'
import ExchangePayload from './ExchangePayload.vue'
import {reason} from '../utils/connection'
import {evidenceQuery,appendEvidence} from '../utils/recycle-wire'
const props=defineProps<{row:any}>()
const query=computed(()=>evidenceQuery(props.row))
const rows=ref<any[]>([]),cursor=ref(''),loading=ref(false),error=ref('')
const stamp=(t:number)=>Number(t)>0?new Date(Number(t)).toLocaleString('zh-CN',{hour12:false}):'未记录时间'
let version=0
async function load(more=false){
    const id=++version,q=query.value
    if(!more){rows.value=[];cursor.value=''}
    error.value='';loading.value=!!q
    if(!q){error.value='没有可核对的原任务编号。';return}
    try{
        const r:any=await request.get('boyuan/logs',{params:{view:'exchanges',day:'history',keyword:q.trace,cursor:more?cursor.value:''}})
        if(id!==version)return
        if(!Array.isArray(r?.data?.rows) || typeof r.data.next!=='string')throw Error('没有收到有效的接口日志。')
        rows.value=appendEvidence(rows.value,r.data.rows,q)
        cursor.value=q.call && rows.value.some(x=>x.stage==='result')?'':r.data.next
    }catch(e){if(id===version)error.value=reason(e,'暂时不能查看报文，请检查当前岗位查询博远接口往来的权限。')}
    finally{if(id===version)loading.value=false}
}
watch(query,()=>load(),{immediate:true})
onBeforeUnmount(()=>{version++})
</script>
<style scoped>
.wire-proof{margin-top:18px}.wire-proof h3{font-size:15px;margin:0 0 10px}.wire-proof details{border-top:1px solid var(--el-border-color-lighter);padding:10px 0}.wire-proof summary{cursor:pointer;color:var(--el-text-color-primary)}.wire-proof small,.wire-proof p{font-size:12px;color:var(--el-text-color-secondary);line-height:1.65}.wire-proof .reconcile{color:var(--el-text-color-regular)}.pair{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px}@media(max-width:760px){.pair{grid-template-columns:1fr}}
</style>
