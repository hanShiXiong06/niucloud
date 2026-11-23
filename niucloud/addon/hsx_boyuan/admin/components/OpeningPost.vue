<template>
    <section class="opening-post" aria-labelledby="opening-post-title">
        <div class="heading"><div><span class="step">第二步 · 本站 ERP</span><h2 id="opening-post-title">把资料建入本站 ERP</h2></div>
            <el-button v-if="allowed" link :disabled="working" @click="inspect()">重新检查</el-button>
        </div>
        <p v-if="!allowed">请有期初建账权限的员工核对本站账目。</p>
        <p v-else-if="phase === 'checking'" role="status">正在检查本站待收、待付和可用资料…</p>
        <template v-else-if="gate">
            <p v-if="gate.ready !== true" class="notice">{{ gate.reason || '本站尚不符合期初建账条件。' }}</p>
            <p v-else>本站没有待收、待付。先看将要新增的资料，确认后才会建档。<span v-if="gate.account">博远账套：{{ gate.account }}</span></p>
            <div v-if="gate.ready !== true && (gate.receivable_count != null || gate.payable_count != null)" class="meta">
                待收：{{ gate.receivable_count ?? '未取得' }} 笔　待付：{{ gate.payable_count ?? '未取得' }} 笔
            </div>
            <template v-if="gate.ready === true">
                <div class="categories" aria-label="选择建档资料">
                    <el-checkbox v-for="r in gate.resources" :key="r.resource" v-model="chosen" :label="r.resource" :disabled="working || !r.ready || !gate.can_preview" @change="clearPlan()">{{ r.name }}</el-checkbox>
                </div>
                <p v-for="r in unavailable" :key="r.resource" class="meta">{{ r.name }}：{{ r.reason || '请先完成此类博远档案的同步。' }}</p>
                <p class="meta">新仓自带一个本站默认库位，由本次操作人负责；核实前禁止直售和调拨。商品只建目录，不增加库存。往来单位保留博远的客户、供应商身份。</p>
                <p v-if="!gate.can_preview">当前账号没有预览权限，请联系管理员。</p>
                <div v-if="phase === 'previewing' || phase === 'writing'" role="status" class="progress">{{ progressText }}<p v-if="phase === 'writing'">请保持页面打开。若中途离开，回来后先重新预览；已经建好的资料会保留。</p></div>
                <div v-if="phase === 'preview'" class="summary" aria-live="polite">
                    <strong>预计新增 {{ summary.create }} 条</strong><span>已有 {{ summary.existing }} 条</span><span v-if="summary.conflict">冲突 {{ summary.conflict }} 条</span><span v-if="summary.skip">跳过 {{ summary.skip }} 条</span>
                </div>
                <div v-if="phase === 'done' || phase === 'interrupted'" class="summary" aria-live="polite">
                    <strong>{{ phase === 'interrupted' ? '已确认新增' : '本次新增' }} {{ posted }} 条</strong>
                    <span v-if="phase === 'done'">保留已有 {{ finalCounts.existing }} 条</span>
                    <span v-if="finalCounts.conflict">待核对 {{ finalCounts.conflict }} 条</span>
                    <span v-if="finalCounts.skip">跳过 {{ finalCounts.skip }} 条</span>
                </div>
                <p v-if="phase === 'done' && findings.length">有资料未建入，请按下方明细核对。已有资料没有被覆盖。</p>
                <p v-if="phase === 'preview' && !summary.create">本次没有可新增的资料，无需确认写入。</p>
            </template>
        </template>
        <el-alert v-if="problem" :title="problem" type="warning" :closable="false" show-icon />
        <div v-if="findings.length && ['preview','done','interrupted'].includes(phase)" class="findings">
            <el-button link @click="details = !details">{{ details ? '收起' : '查看' }}未建档明细（{{ findings.length }}）</el-button>
            <template v-if="details">
                <el-table :data="visibleFindings" size="small">
                    <el-table-column prop="kind" label="类别" width="105" /><el-table-column prop="name" label="档案" min-width="160" />
                    <el-table-column prop="reason" label="原因" min-width="160" />
                </el-table>
                <el-pagination v-if="findings.length > 10" :current-page="detailPage" :page-size="10" :total="findings.length" layout="prev, pager, next" @current-change="v => detailPage = v" />
            </template>
        </div>
        <div v-if="gate?.ready && gate?.can_preview && !['confirming','writing'].includes(phase)" class="actions">
            <el-button v-if="phase !== 'preview' || !summary.create || !gate.can_apply" :type="phase === 'done' ? 'default' : 'primary'" :loading="phase === 'previewing'" :disabled="working || !chosen.length" @click="preview">{{ phase === 'done' || phase === 'interrupted' ? '重新预览' : '预览建档结果' }}</el-button>
            <template v-if="phase === 'preview' && summary.create && gate.can_apply">
                <el-button type="primary" :disabled="working" @click="post">确认建入 ERP（{{ summary.create }} 条）</el-button>
                <el-button link :disabled="working" @click="preview">重新预览</el-button>
            </template>
            <small v-if="phase === 'preview' && !gate.can_apply && summary.create">当前仅可预览，确认写入需要建档权限。</small>
        </div>
        <OpeningAccounts v-if="gate?.ready && gate.can_read_accounts" :connection-key="connectionKey" :ready="gate.accounts_ready === true" :can-apply="gate.can_apply_accounts === true" />
    </section>
</template>
<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { ElMessageBox } from 'element-plus'
import { readOpeningCheck, previewOpening, applyOpening } from '../api/opening-post'
import OpeningAccounts from './OpeningAccounts.vue'

type Resource = {resource:string; name:string; ready:boolean; reason?:string}
type Gate = {ready:boolean;reason?:string;account?:string;can_preview:boolean;can_apply:boolean;accounts_ready?:boolean;can_read_accounts?:boolean;can_apply_accounts?:boolean;receivable_count?:number;payable_count?:number;resources:Resource[]}
type Action = 'create'|'existing'|'conflict'|'skip'
type Row = {id:string|number;name:string;action:Action;reason?:string}
type Batch = {resource:string;cursor:number;token:string;next:number;rows:Row[]}
type Finding = {kind:string;name:string;reason:string}
type Phase = 'idle'|'checking'|'previewing'|'preview'|'confirming'|'writing'|'done'|'interrupted'
const props=defineProps<{connectionKey:string;rows:any[]}>()
const allowed=computed(()=>!!props.connectionKey && props.rows[0]?.mode !== 'mock' && props.rows[0]?.capabilities?.can_check_opening === true)
const gate=ref<Gate|null>(null), chosen=ref<string[]>([]), phase=ref<Phase>('idle'), problem=ref(''), progressText=ref('')
const batches=ref<Batch[]>([]), findings=ref<Finding[]>([]), posted=ref(0), details=ref(false), detailPage=ref(1)
const counts=()=>({create:0,existing:0,skip:0,conflict:0})
const finalCounts=ref(counts())
const summary=computed(()=>{const c=counts(); for (const b of batches.value) for(const r of b.rows) c[r.action]++; return c})
const visibleFindings=computed(()=>findings.value.slice((detailPage.value-1)*10,detailPage.value*10))
const unavailable=computed(()=>gate.value?.resources.filter(r=>!r.ready)||[])
const working=computed(()=>['checking','previewing','confirming','writing'].includes(phase.value))
const explanation=(e:any, fallback:string)=>typeof e?.msg === 'string' ? e.msg : typeof e?.message === 'string' ? e.message : fallback
const kind=(r:string)=>gate.value?.resources.find(k=>k.resource===r)?.name || r
const prepared=(rows:Row[],r:string)=>rows.filter(x=>x.action==='conflict'||x.action==='skip').map(x=>({kind:kind(r),name:x.name || String(x.id),reason:x.reason || (x.action==='conflict'?'存在冲突，需要核对。':'不符合建档条件。')}))
let sequence=0, live=true
function clearPlan() {
    if(working.value) return
    batches.value=[];findings.value=[];details.value=false;detailPage.value=1;phase.value='idle';posted.value=0;finalCounts.value=counts();problem.value=''
}
async function inspect() {
    const id=++sequence
    phase.value='idle';clearPlan();gate.value=null;chosen.value=[]
    if(!allowed.value || !live)return
    phase.value='checking'
    try {
        const result=(await readOpeningCheck(props.connectionKey)).data
        if(id!==sequence || !live)return
        if(typeof result?.ready!=='boolean' || !Array.isArray(result?.resources) || new Set(result.resources.map((r:any)=>r.resource)).size!==result.resources.length ||
            !result.resources.every((r:any)=>typeof r.resource==='string' && r.resource && typeof r.name==='string' && typeof r.ready==='boolean')) throw Error('未取得完整的建档条件，请重新检查。')
        gate.value=result;chosen.value=result.resources.filter((r:Resource)=>r.ready).map((r:Resource)=>r.resource)
    } catch(e){if(id===sequence && live)problem.value=explanation(e,'未能检查本站账目，暂不能建档。')}
    finally {if(id===sequence && live)phase.value='idle'}
}
const validRows=(rows:any):rows is Row[]=>Array.isArray(rows)&&rows.every(r=>r && ['string','number'].includes(typeof r.id) && String(r.id)!=='' && typeof r.name==='string' && ['create','existing','conflict','skip'].includes(r.action))
function assertNext(start:number,next:any){if(!Number.isSafeInteger(next)||next<0||(next!==0&&next<=start))throw Error('分页进度异常，已停止。')}
async function preview() {
    if(!allowed.value||working.value||!gate.value?.ready||!gate.value?.can_preview||!chosen.value.length)return
    const pick=[...new Set(chosen.value)];if(!pick.every(k=>gate.value?.resources.some(r=>r.ready&&r.resource===k)))return
    clearPlan();const id=++sequence,key=props.connectionKey;phase.value='previewing';const plan:Batch[]=[],exceptions:Finding[]=[]
    try {
        for(const resource of pick){
            let cursor=0,pages=0; const seen=new Set<string>()
            do{
                progressText.value='正在预览「'+kind(resource)+'」第 '+(++pages)+' 批…'
                const data=(await previewOpening(key,resource,cursor)).data
                if(!live||sequence!==id)return
                if(!validRows(data?.rows)||typeof data.token!=='string'||!data.token) throw Error('未取得可确认的预览，请重新读取。')
                assertNext(cursor,data.next_cursor)
                for (const r of data.rows){const k=String(r.id);if(seen.has(k))throw Error('预览出现重复档案，已停止。');seen.add(k)}
                if(data.next_cursor && !data.rows.length) throw Error('未取得本批档案，已停止。')
                plan.push({resource,cursor,token:data.token,next:data.next_cursor,rows:data.rows});exceptions.push(...prepared(data.rows,resource));cursor=data.next_cursor
            }while(cursor)
        }
        batches.value=plan;findings.value=exceptions;phase.value='preview'
    }catch(e){if(live&&id===sequence){problem.value=explanation(e,'预览失败，未写入 ERP。');phase.value='idle'}}
}
async function post() {
    if(!allowed.value||phase.value!=='preview'||!gate.value?.ready||!gate.value.can_apply||!summary.value.create)return
    const id=++sequence,key=props.connectionKey,plan=batches.value
    phase.value='confirming'
    try {await ElMessageBox.confirm('从博远账套 '+(gate.value.account || '（当前账套）')+' 预计新增 '+summary.value.create+' 条 ERP 基础资料，不覆盖已有资料，也不导入库存或余额。','确认建入本站 ERP？',{confirmButtonText:'确认建档',cancelButtonText:'返回核对'})}
    catch{if(live&&sequence===id)phase.value='preview';return}
    if(!live||sequence!==id||!allowed.value||key!==props.connectionKey)return
    phase.value='writing';finalCounts.value=counts();findings.value=[];posted.value=0
    let attempted=false
    try{
        for (let i=0;i<plan.length;i++) {
            const b=plan[i];let result=b.rows
            if(result.some(r=>r.action==='create')){
                progressText.value='正在建入「'+kind(b.resource)+'」 · '+(i+1)+' / '+plan.length+' 批'
                attempted=true
                const data=(await applyOpening(key,b.resource,b.cursor,b.token)).data
                if(!live||sequence!==id)return
                if(!validRows(data?.rows) || data.next_cursor!==b.next || data.rows.length!==b.rows.length || !data.rows.every((r:Row,i:number)=>String(r.id)===String(b.rows[i].id)))throw Error('回执与本次预览不一致。')
                result=data.rows
            }
            for (const r of result) finalCounts.value[r.action]++
            posted.value=finalCounts.value.create
            findings.value.push(...prepared(result,b.resource))
        }
        phase.value='done'
    }catch(e){if(live&&sequence===id){problem.value=explanation(e,'未收到完整回执。')+' 本次已停止；请重新预览本站现有档案后再确认，不要重复提交原批次。';phase.value='interrupted'}}
    finally {if(live&&sequence===id&&attempted)batches.value=[]}
}
// Never reuse a preview after an account change, a permission change, or a newer archive snapshot.
watch(()=>JSON.stringify([allowed.value,props.connectionKey,props.rows.filter(r=>['products','warehouses','parties','capital_accounts'].includes(r.resource)).map(r=>[r.resource,r.ready,r.job?.id,r.job?.status])]),()=>{
    if(allowed.value && phase.value==='writing'){
        sequence++; phase.value='interrupted';gate.value=null;batches.value=[]
        problem.value='资料状态已改变，后续批次已停止。已确认新增 '+posted.value+' 条；当前批次结果需核实。请重新检查并预览，不能重复提交原批次。'
        return
    }
    return inspect()
},{immediate:true})
onUnmounted(()=>{live=false;sequence++})
</script>
<style scoped>
.opening-post {margin:18px 0;padding:20px;background:var(--el-bg-color);border:1px solid var(--el-border-color-light);border-radius:8px;}
.heading,.actions,.summary,.categories {display:flex;gap:8px 16px;align-items:center;flex-wrap:wrap;}
.heading {justify-content:space-between;}.step {font-size:13px;color:var(--el-color-primary);}
h2 {font-size:18px;margin:5px 0;}p,small,.meta {font-size:13px;line-height:1.6;color:var(--el-text-color-regular);margin:6px 0;}
.notice {color:var(--el-color-warning-dark-2);}.categories,.actions,.findings {margin-top:12px;}
.categories :deep(.el-checkbox){margin-right:0;}.summary,.progress {margin:12px 0;padding:10px 12px;background:var(--el-fill-color-light);font-size:14px;}
</style>
