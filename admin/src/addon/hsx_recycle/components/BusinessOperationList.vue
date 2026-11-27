<template>
  <section class="operation-list" aria-label="业务操作日志">
    <div class="bar">
      <el-radio-group v-if="!source" v-model="chosen" aria-label="日志来源" size="small" @change="reset">
        <el-radio-button v-for="item in sources" :key="item[0]" :label="item[0]">{{ item[1] }}</el-radio-button>
      </el-radio-group>
      <span v-else class="quiet">按发生时间倒序 · {{ source==='erp'?'ERP':'博远' }}</span>
      <el-button text :loading="loading" @click="reset">刷新</el-button>
    </div>
    <el-alert v-if="error" type="error" :title="error" :closable="false" show-icon />
    <el-table v-else v-loading="loading" :data="rows" :row-key="rowKey" empty-text="暂无操作记录" stripe>
      <template #empty><div v-if="!loading" class="empty"><b>暂无操作记录</b><p>ERP 和博远从启用操作日志起记录，旧单不自动补录。查看不会发送业务请求。</p></div></template>
      <el-table-column label="发生时间" width="162"><template #default="{row}">{{ time(row.time) }}</template></el-table-column>
      <el-table-column v-if="!source" prop="source_label" label="来源" width="82" />
      <el-table-column label="操作" min-width="205">
        <template #default="{row}"><b>{{ row.label }}</b>
          <div v-if="row.business_no || row.external_no" class="quiet">{{ [row.business_no,row.external_no].filter(Boolean).join(' → ') }}</div>
        </template>
      </el-table-column>
      <el-table-column label="结果" width="110"><template #default="{row}">
        <el-tag size="small" :type="tone(row.result)" effect="plain">{{row.result_label}}</el-tag>
      </template></el-table-column>
      <el-table-column prop="operator" label="操作人" width="112" />
      <el-table-column label="操作记录" width="112"><template #default="{row}">
        <el-button link type="primary" @click="selected=row">{{ evidenceFor(row) && row.call_id ? '入参 / 出参' : '详情' }}</el-button>
      </template></el-table-column>
    </el-table>
    <div v-if="previous.length || next" class="pages">
      <el-button size="small" :disabled="!previous.length || loading" @click="back">上一页</el-button>
      <span>第 {{previous.length+1}} 页</span>
      <el-button size="small" :disabled="!next || loading" @click="forward">下一页</el-button>
    </div>
    <el-dialog :model-value="!!selected" title="操作记录详情" :width="evidence ? 'min(880px, 94vw)' : 'min(500px, 94vw)'" append-to-body @close="selected=null">
      <el-descriptions v-if="selected" :column="1" border>
        <el-descriptions-item label="来源 / 操作">{{selected.source_label}} · {{selected.label}}</el-descriptions-item>
        <el-descriptions-item label="结果">{{selected.result_label}}</el-descriptions-item>
        <el-descriptions-item label="发生时间">{{time(selected.time)}}</el-descriptions-item>
        <el-descriptions-item label="操作人">{{selected.operator}}</el-descriptions-item>
        <el-descriptions-item label="回收订单">{{orderText(selected)}}</el-descriptions-item>
        <el-descriptions-item v-if="selected.device_ids?.length && !selected.related_orders?.length" label="回收设备 ID">{{selected.device_ids.join('、')}}</el-descriptions-item>
        <el-descriptions-item v-if="selected.business_no" label="本站单号">{{selected.business_no}}</el-descriptions-item>
        <el-descriptions-item v-if="selected.external_no" label="外部单号">{{selected.external_no}}</el-descriptions-item>
        <el-descriptions-item v-if="selected.task_id" label="任务编号">{{selected.task_id}}</el-descriptions-item>
        <el-descriptions-item v-if="selected.call_id" label="调用编号">{{selected.call_id}}</el-descriptions-item>
        <el-descriptions-item v-if="selected.remark" label="记录说明">{{selected.remark}}</el-descriptions-item>
      </el-descriptions>
      <component v-if="selected && evidence" :is="evidence" :key="rowKey(selected)" :row="selected" />
    </el-dialog>
  </section>
</template>
<script setup lang="ts">
import {computed,defineAsyncComponent,ref,watch,onBeforeUnmount,type Component} from 'vue'
import {useRouter} from 'vue-router'
import request from '@/utils/request'
const props=defineProps<{source?: string;initialSource?:string;orderId?:number|string}>()
type Drill={source:string;route:string;load:()=>Promise<{default:Component}>}
const router=useRouter()
const entries=Object.values(import.meta.glob<Drill>('/src/addon/*/extensions/recycle-operation-details.ts',{eager:true,import:'default'}))
const drills=new Map(entries.map(entry=>[entry.source,{route:entry.route,view:defineAsyncComponent(entry.load)}] as const))
function evidenceFor(r:any){
  const e=drills.get(r?.source)
  return e && Number(props.orderId)>0 && Number(props.orderId)===Number(r.order_id) && Number(r.task_id)>0 && router.hasRoute(e.route) ? e.view : null
}
const sources=[['','全部'],['recycle','回收'],['erp','ERP'],['boyuan','博远']]
const chosen=ref(''),cursor=ref(''),previous=ref<string[]>([]),next=ref(''),rows=ref<any[]>([]),selected=ref<any>(null)
const error=ref(''),loading=ref(false);let seq=0
const evidence=computed(()=>evidenceFor(selected.value))
const time=(s:number)=>s>0?new Date(s*1000).toLocaleString('zh-CN',{hour12:false}):'未记录'
const rowKey=(r:any)=>r.rank+':'+r.id
const orderText=(r:any)=>r.related_orders?.length?r.related_orders.map((o:any)=>o.no || '#'+o.id).join('、'):(r.order_no || '未关联回收单')
const tone=(s:string)=>s==='failed'?'danger':s==='review'||s==='pending'?'warning':s==='success'?'success':'info'
async function load(){
  const id=++seq;loading.value=true;error.value='';selected.value=null;next.value='';rows.value=[]
  try{
    const response:any=await request.get('recycle/operation_logs',{params:{order_id:props.orderId||0,source:props.source||chosen.value,cursor:cursor.value,limit:20}})
    if(id!==seq)return
    if(!Array.isArray(response?.data?.rows)||typeof response?.data?.next!=='string')throw Error('未能读取完整记录')
    rows.value=response.data.rows;next.value=response.data.next
  }catch(e:any){if(id===seq)error.value=String(e?.msg || e?.message || '读取操作日志失败，请刷新重试。')}
  finally{if(id===seq)loading.value=false}
}
function reset(){cursor.value='';previous.value=[];void load()}
function back(){if(!previous.value.length)return;cursor.value=previous.value.pop()!;void load()}
function forward(){if(!next.value)return;previous.value.push(cursor.value);cursor.value=next.value;void load()}
watch(()=>[props.orderId,props.source,props.initialSource],()=>{chosen.value=sources.some(([k])=>k===props.initialSource)?props.initialSource!:'';reset()},{immediate:true})
onBeforeUnmount(()=>{seq++})
</script>
<style scoped>
.empty{line-height:1.6;padding:14px 8px}.empty p{margin:4px 0;font-size:12px}
.operation-list{min-width:0;background:var(--el-bg-color)}.bar,.pages{display:flex;align-items:center;gap:12px;padding:8px 0}.bar{justify-content:space-between}.pages{justify-content:flex-end;font-size:12px}.quiet{font-size:12px;color:var(--el-text-color-secondary);line-height:1.7;overflow-wrap:anywhere}.operation-list b{font-weight:550}
</style>
