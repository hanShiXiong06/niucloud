<template>
  <section class="receipt" aria-label="已有收款凭证">
    <header><small>财务 · 原收款凭证</small><h3>{{ doc?.external_no ? '核对博远收款回执' : '同步已有收款凭证' }}</h3>
      <p>这里只同步本站已经登记的凭证，不向客户收钱，也不在本站新增收款。</p></header>
    <el-alert v-if="error" type="warning" :closable="false" :title="error" show-icon />
    <p v-if="loading" role="status">正在读取原凭证…</p>
    <template v-if="doc">
      <dl><dt>本站收款凭证</dt><dd>{{ doc.source.settlement_no }}</dd><dt>本次收款</dt><dd><b>¥{{ doc.source.amount }}</b></dd>
        <dt>博远销售单</dt><dd>{{ doc.source.sale_external_no || '待核对' }}</dd><dt>本站登记人 / 账户</dt><dd>{{ doc.source.operator_name || '待核对' }} / {{ doc.source.capital_account_name || '待核对' }}</dd>
        <dt>博远收款单</dt><dd>{{ doc.external_no || '未取得单号' }}</dd><dt>核销原销售单</dt><dd>{{ doc.allocation_status || '待核对' }}</dd></dl>
      <p v-if="doc.reason">{{ doc.reason }}</p>
      <div v-for="k in fields" :key="k" class="field">
        <span>{{ names[k] }}</span>
        <el-select v-if="editable && doc.missing.includes(k)" v-model="chosen[k]" filterable remote clearable :disabled="busy"
          :remote-method="q=>lookup(k,q)" @visible-change="v=>v && lookup(k,'')" :loading="searching[k]" placeholder="搜索已同步的博远档案">
          <el-option v-for="row in choices[k]" :key="row.id" :value="row.id" :label="row.name" />
        </el-select><b v-else>{{ doc.labels?.[k] || (doc.selection?.[k] ? '已关联' : '未关联') }}</b>
      </div>
      <p v-if="lookupError" role="alert">{{ lookupError }}</p>
    </template>
    <footer>
      <el-button v-if="!task && !readOnly" type="primary" :disabled="busy || !validSource" :loading="loading" @click="prepare">准备这张已有凭证</el-button>
      <el-button v-if="task" :disabled="busy" @click="read">刷新原凭证</el-button>
      <el-button v-if="editable" type="primary" :disabled="busy || !ready" :loading="submitting" @click="send">同步已有收款凭证</el-button>
    </footer>
  </section>
</template>
<script setup lang="ts">
import {computed,onUnmounted,reactive,ref,watch} from 'vue'
import {ElMessageBox} from 'element-plus'
import {purchaseRouteId as id} from '../utils/purchase-form'
import {readOpeningRows} from '../api/opening-archives'
import {prepareOriginalReceipt,readOriginalReceipt,submitOriginalReceipt} from '../api/receipt'
const props=defineProps<{saleId:number;settlementId:number;taskId:number;readOnly:boolean}>()
const fields=['staff_id','account_id'] as const
type Key=typeof fields[number]
const names={staff_id:'博远制单人',account_id:'博远资金账户'}
const task=ref(0),doc=ref<any>(null),error=ref(''),lookupError=ref(''),loading=ref(false),submitting=ref(false),uncertain=ref(false)
const chosen=reactive({staff_id:'',account_id:''}),choices=reactive<Record<Key,any[]>>({staff_id:[],account_id:[]})
const searching=reactive({staff_id:false,account_id:false}), seq={staff_id:0,account_id:0}
let generation=0
const validSource=computed(()=>Boolean(id(props.saleId)&&id(props.settlementId)))
const busy=computed(()=>loading.value||submitting.value)
const editable=computed(()=>!props.readOnly && !uncertain.value && doc.value?.can_submit===true && doc.value?.task?.status==='awaiting_confirmation'
  && doc.value?.task?.mode==='test' && !doc.value?.external_no)
const ready=computed(()=>editable.value && fields.every(k=>!!chosen[k]) && !error.value)
function why(e:any,fallback:string){return String(e?.msg||e?.message||fallback)}
async function read(){
  if(!task.value||!validSource.value||busy.value)return
  const g=++generation;loading.value=true;doc.value=null;error.value='';lookupError.value='';++seq.staff_id;++seq.account_id
  try{
    const d=(await readOriginalReceipt(task.value))?.data
    if(g!==generation)return
    if(id(d?.task?.id)!==task.value || id(d?.source?.sale_id)!==props.saleId || id(d?.source?.settlement_id)!==props.settlementId
      || d?.task?.mode!=='test' || !d?.task?.connection_key || !Number.isSafeInteger(Number(d.task.revision)) || d.task.revision==null
      || !Array.isArray(d.missing) || d.missing.some((k:any)=>!fields.includes(k)) || !d.source.settlement_no) throw Error('原销售、凭证或账套未能匹配；未发送。')
    doc.value=d;for(const k of fields){chosen[k]=String(d.selection?.[k]||'');choices[k]=[]}
    // Only a fresh original-task read may lift a local unknown-outcome lock.
    if(d.can_submit!==true || d.task.status!=='awaiting_confirmation') uncertain.value=true
  }catch(e){if(g===generation)error.value=why(e,'无法读取该凭证。')}
  finally{if(g===generation)loading.value=false}
}
async function prepare(){
  if(task.value||!validSource.value||busy.value||props.readOnly)return
  const g=generation;loading.value=true;error.value=''
  try{const d=(await prepareOriginalReceipt(props.settlementId,props.saleId))?.data
    if(g!==generation)return
    const linked=id(d?.vendor_task_id);if(!linked)throw Error('未取得已有凭证的关联任务。请刷新原销售单核对。')
    task.value=linked
  }catch(e){if(g===generation)error.value=why(e,'准备失败，未发送收款凭证。')}
  finally{if(g===generation)loading.value=false}
  if(g===generation && task.value)await read()
}
async function lookup(k:Key,q:string){
  if(!editable.value)return
  const token=++seq[k],g=generation;searching[k]=true;lookupError.value=''
  try{const r=(await readOpeningRows(k==='staff_id'?'staff':'capital_accounts',q,1,doc.value.task.connection_key,{archive_status:'enabled'}))?.data
    if(g!==generation || seq[k]!==token)return
    if(r?.code!=='OK' || r?.data?.source!=='local_archive' || !Array.isArray(r.data.items))throw Error('未取得可用档案。')
    choices[k]=r.data.items.filter((i:any)=>i.id&&i.name)
  }catch(e){if(g===generation && seq[k]===token){choices[k]=[];lookupError.value=why(e,'档案读取失败，请重新搜索。')}}
  finally{if(g===generation && seq[k]===token)searching[k]=false}
}
async function send(){
  if(!ready.value||busy.value)return
  const g=generation,d=doc.value;submitting.value=true
  try{await ElMessageBox.confirm('把本站凭证 '+d.source.settlement_no+'（¥'+d.source.amount+'）登记到博远测试账套？不会再次收钱，核销仍以原单证据为准。','确认同步已有凭证',{type:'warning',confirmButtonText:'确认同步',cancelButtonText:'返回'})}
  catch{if(g===generation)submitting.value=false;return}
  if(g!==generation)return
  let failed=''
  try{uncertain.value=true;await submitOriginalReceipt(task.value,{revision:d.task.revision,mode:d.task.mode,connection_key:d.task.connection_key,settlement_id:props.settlementId,sale_id:props.saleId,confirm_registration:true,selection:{...chosen}})}
  catch(e){failed=why(e,'提交结果不确定，仅刷新原凭证，不重新提交。')}
  finally{if(g===generation)submitting.value=false}
  if(g!==generation)return
  await read()
  if(failed&&!doc.value?.external_no)error.value=[failed,'请核对原凭证，不要重复提交。'].join(' ')
}
watch(()=>[props.saleId,props.settlementId,props.taskId],()=>{
  ++generation;loading.value=false;submitting.value=false;uncertain.value=false;doc.value=null;error.value='';task.value=id(props.taskId)
  if(!validSource.value){error.value='未能定位这张销售单的原收款凭证。';return}
  if(task.value)void read()
},{immediate:true})
onUnmounted(()=>{++generation})
defineExpose({mayClose:()=>!busy.value})
</script>
<style scoped>
.receipt{display:grid;gap:16px;color:var(--el-text-color-primary)}.receipt h3{font-size:19px;margin:4px 0}.receipt p,.receipt small,.receipt dt{color:var(--el-text-color-regular);line-height:1.7}.receipt dl{display:grid;grid-template-columns:max-content 1fr;gap:9px 20px;margin:0;padding:14px 18px;background:var(--el-fill-color-light)}.receipt dd{margin:0;overflow-wrap:anywhere}.field,footer{display:flex;align-items:center;gap:16px}.field>span{min-width:8em}.field .el-select{max-width:320px}footer{flex-wrap:wrap;justify-content:flex-end}
</style>
