<template>
    <component :is="embeddedCategories?'section':DeskShell" v-bind="embeddedCategories?{}:{title:'基础资料',subtitle:'查看本站已取回的博远档案。'}">
        <template v-if="!embeddedCategories" #extra>
            <el-button :loading="booting" :disabled="syncBusy" @click="loadIndex">刷新目录</el-button>
            <el-button v-if="router.hasRoute(routes.opening)" plain type="primary" @click="router.push({name:routes.opening})">同步与建账</el-button>
        </template>
        <el-alert v-if="indexError" role="alert" type="error" :closable="false" :title="indexError" show-icon />
        <el-skeleton v-else-if="booting" :rows="6" animated aria-label="正在加载资料目录" />
        <el-result v-else-if="!canBrowse" icon="info" :title="contextKey ? '无档案查看权限' : '本站尚未连接博远账套'" :sub-title="contextKey ? '请联系管理员授予搜索基础资料权限。' : '先在连接设置填写并核验账套，再同步需要的类别。'">
            <template v-if="!contextKey && router.hasRoute(routes.connection)" #extra><el-button @click="router.push({name:routes.connection})">去连接设置</el-button></template>
        </el-result>
        <component v-else :is="embeddedCategories?'section':HsxCatalogLayout" v-bind="embeddedCategories?{}:{groups:navigation,modelValue:kind,label:'基础资料分类'}" @update:model-value="navigate">
            <header class="directory-head"><div><h2>{{ titleFor(kind) }}</h2><span>已取回 {{ current?.count }} 条</span></div>
                <div class="directory-tools"><small>{{ syncCaption }}</small>
                    <el-radio-group v-if="kind==='categories'" :model-value="categoryView" aria-label="商品分类展示方式" size="small" @change="switchCategoryView">
                        <el-radio-button value="tree">层级浏览</el-radio-button><el-radio-button value="list">明细列表</el-radio-button>
                    </el-radio-group>
                    <el-button v-if="realAccount && current?.capabilities?.start" type="primary" :loading="syncBusy" :disabled="!current?.ready" @click="syncCurrent">{{ syncBusy?'正在同步':inFlight?'查看同步进度':syncLabel }}</el-button>
                </div></header>
            <p v-if="realAccount" class="source-note">如需新增、修改或删除{{ titleFor(kind) }}，请在博远 ERP 操作。<template v-if="current?.ready && current?.capabilities?.start">保存后，回到这里点击「{{ syncLabel }}」。</template><template v-else>{{ current?.ready ? '保存后，请有同步权限的同事在此同步。' : '请先在连接设置核验账套，再同步资料。' }}</template></p>
            <el-alert v-if="displayFeedback" :type="displayFeedback.type" :title="displayFeedback.text" :closable="false" show-icon role="status" />
            <CategoryDirectory v-if="treeMode" :key="contextKey+':'+directoryVersion" :connection-key="contextKey" :empty-message="emptyReason(current,false)"
                @detail="openCategory" @list-only="usePagedCategories" />
            <template v-else>
            <el-alert v-if="kind==='categories' && categoryNotice" type="info" :title="categoryNotice" :closable="false" show-icon />
            <form class="filters" @submit.prevent>
                <HsxSearchInput v-model="keyword" class="lookup" :maxlength="100" aria-label="档案搜索" :placeholder="kind==='parties' ? '名称、档案编号或完整电话' : '名称、档案编码或 ID'" @search="applySearch" />
                <el-select v-if="kind==='parties'" v-model="role" aria-label="往来身份" placeholder="全部身份" @change="search(1)">
                    <el-option label="全部身份" value="" /><el-option label="仅看客户" value="customer" /><el-option label="仅看供应商" value="supplier" />
                </el-select>
                <el-select v-model="status" aria-label="档案状态" placeholder="全部状态" @change="search(1)"><el-option label="全部状态" value="" /><el-option label="启用" value="enabled" /><el-option label="停用" value="disabled" /><el-option label="状态未提供" value="unknown" /></el-select>
                <el-button v-if="keyword || filtered" link type="primary" @click="reset">重置</el-button>
            </form>
            <el-alert v-if="listError" type="error" :closable="false" show-icon :title="listError"><el-button link type="primary" @click="search(page)">重新读取</el-button></el-alert>
            <HsxTable v-else row-key="id" :data="records" :columns="catalogColumns(kind)" :loading="loading" :empty-text="empty">
                <template #record="{row}"><el-button link type="primary" class="record-name" @click="openDetail(row)">{{ row.name }}</el-button></template>
                <template #partyPhone="{row}"><PartyPhone v-if="row.phone_field==='help_code'" :value="row.phone_number" :needs-sync="row.phone_state==='refresh'" /><span v-else>{{ row.phone_masked || '—' }}</span></template>
                <template #status="{row}"><HsxTag :tone="archiveStatus(row)==='启用'?'success':'neutral'" dot>{{ archiveStatus(row) }}</HsxTag></template>
                <template #empty><div class="empty" role="status">{{ loading ? '正在读取档案…' : empty }}<el-button v-if="!loading && filtered" link type="primary" @click="reset">清除筛选</el-button></div></template>
            </HsxTable>
            <footer v-if="!listError && !loading && total>0"><HsxPagination :current-page="page" :page-size="limit" :page-sizes="[20,50,100]" :total="total" @change="paginate" /></footer>
            </template>
        </component>
        <ArchiveDetail v-if="detailRow && canBrowse" :key="contextKey+':'+kind+':'+detailRow.id"
            :resource="kind" :record="detailRow" :category-name="titleFor(kind)" :connection-key="contextKey" @close="detailRow=null" />
    </component>
</template>
<script setup lang="ts">
import {computed,ref,watch,onMounted,onActivated,onDeactivated,onBeforeUnmount} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import DeskShell from '../components/DeskShell.vue'
import ArchiveDetail from '../components/ArchiveDetail.vue'
import CategoryDirectory from '../components/CategoryDirectory.vue'
import PartyPhone from '../components/PartyPhone.vue'
import HsxCatalogLayout from '@/addon/hsx_components/components/HsxCatalogLayout/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxTag from '@/addon/hsx_components/components/HsxTag/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxSearchInput from '@/addon/hsx_components/components/HsxSearchInput/index.vue'
import {directory,titleFor,catalogColumns,archiveStatus,emptyReason} from '../utils/catalog'
import {advanceReference,openingProgress} from '../utils/opening'
import {routes,reason} from '../utils/connection'
import {readOpeningJobs,readOpeningRows,queueOpening,readJobPage} from '../api/opening-archives'
const props=defineProps<{embeddedCategories?:boolean}>()
const router=useRouter(),route=useRoute()
function requested(){if(props.embeddedCategories)return 'categories';const k=String(route.query.resource || '');return titleFor(k)?k:'parties'}
const kind=ref(requested()),metadata=ref<any[]>([]),contextKey=ref(''),booting=ref(true),indexError=ref('')
const records=ref<any[]>([]),total=ref(0),page=ref(1),limit=ref(20),keyword=ref(''),applied=ref(''),role=ref(''),status=ref(''),loading=ref(false),listError=ref(''),detailRow=ref<any>(null)
const current=computed(()=>metadata.value.find(r=>r.resource===kind.value))
const realAccount=computed(()=>['test','production'].includes(current.value?.mode))
const syncLabel=computed(()=>kind.value==='categories'?'同步最新分类':'同步'+titleFor(kind.value))
const syncBusy=ref(false),directoryVersion=ref(0)
type Feedback={type:'info'|'success'|'warning'|'error';text:string}
const syncFeedback=ref<Feedback|null>(null)
const inFlight=computed(()=>['queued','running','retry'].includes(current.value?.job?.status))
const displayFeedback=computed<Feedback|null>(()=>syncFeedback.value || (realAccount.value && current.value?.job?.reason &&
    ['failed','retry','needs_review'].includes(current.value.job.status)?{type:current.value.job.status==='failed'?'error':'warning',text:current.value.job.reason}:null))
const categoryView=ref<'tree'|'list'>('tree'), categoryNotice=ref('')
const treeMode=computed(()=>kind.value==='categories' && categoryView.value==='tree')
const canBrowse=computed(()=>!!contextKey.value && current.value?.capabilities?.search===true)
const navigation=computed(()=>directory.map(g=>({label:g.label,items:g.items.map(([key,label])=>({key,label,count:metadata.value.find(m=>m.resource===key)?.count}))})))
const syncCaption=computed(()=>{const j=current.value?.job,t=Number(current.value?.last_success_at)
    if(j?.status==='needs_review')return '本轮已取回部分资料，尚未确认取全'
    if(j?.status==='failed')return '最近一次同步未完成'
    if(j?.status==='retry')return '等待重试 · 已读 '+Number(j.processed || 0)+' 条'
    if(['queued','running'].includes(j?.status))return '同步中 · 已读 '+Number(j.processed || 0)+' 条'
    return t>0?'上次同步 '+new Date(t*1000).toLocaleString('zh-CN',{hour12:false}):kind.value==='categories' && current.value?.count?'仅展示已取回的分类，尚未确认取全':'尚未完成同步'})
const filtered=computed(()=>!!(applied.value || role.value || status.value)),empty=computed(()=>emptyReason(current.value,filtered.value))
let active=true,indexSeq=0,req=0
let syncEpoch=0
function invalidateSync(){++syncEpoch;syncBusy.value=false;syncFeedback.value=null}
watch(kind,invalidateSync,{flush:'sync'})
async function syncCurrent(){
    if(!active || syncBusy.value || !realAccount.value || !canBrowse.value || !current.value?.ready || current.value.capabilities?.start!==true)return
    const ticket=++syncEpoch,key=contextKey.value,type=kind.value,mode=current.value.mode
    const here=()=>active && ticket===syncEpoch && key===contextKey.value && type===kind.value
    syncBusy.value=true;syncFeedback.value=null
    try {
        let id=Number(current.value.job?.id)
        if(!inFlight.value){
            const accepted=(await queueOpening([type],key,'refresh'))?.data
            if(!here())return
            if(accepted?.queued!==true || !Array.isArray(accepted.ids) || accepted.ids.length!==1)throw Error('没有收到同步任务回执，请先刷新状态。')
            id=Number(accepted.ids[0])
        }
        if(!Number.isSafeInteger(id) || id<1)throw Error('没有取得有效的同步编号，请刷新页面。')
        const readStatus=async()=>{
            const rows=(await readOpeningJobs())?.data
            if(!here())return null
            if(!openingProgress(rows).valid || !rows.every((r:any)=>r.connection_key===key && r.mode===mode))throw Error('账套或资料状态已变化，请刷新页面。')
            const row=rows.find((r:any)=>r.resource===type)
            if(Number(row?.job?.id)!==id || row.capabilities?.search!==true)throw Error('任务或查看权限已变化，请刷新页面。')
            metadata.value=rows
            return row
        }
        for(let poll=0;poll<25 && here();poll++){
            let row=await readStatus(); if(!row || !here())return
            if(row.ready && row.capabilities?.read_now===true && row.capabilities?.start===true && ['queued','retry','running'].includes(row.job.status) && Number(row.job.next_at || 0)*1000<=Date.now()){
                await advanceReference(row,here,readJobPage,x=>{syncFeedback.value={type:'info',text:'已读 '+x.pages+' 页，共 '+x.processed+' 条。'}})
                if(!here())return
                row=await readStatus(); if(!row || !here())return
            }
            const j=row.job
            if(!['queued','running','retry'].includes(j.status)){
                syncFeedback.value=j.status==='completed'?{type:'success',text:titleFor(type)+'同步完成，本次取回 '+j.processed+' 条。'}
                    :j.status==='needs_review'?{type:'warning',text:'本轮完整性待核对：'+(j.reason || '未能确认全部资料已取回。')}
                    :{type:'error',text:j.reason || '同步未完成，请检查连接后重试。'}
                detailRow.value=null;directoryVersion.value++
                if(!treeMode.value)await search(1)
                return
            }
            syncFeedback.value={type:j.status==='retry'?'warning':'info',text:j.status==='retry'?'等待重试：'+(j.reason || '暂未收到博远结果。'):'已读 '+j.processed+' 条，正在等待这次任务的结果。'}
            if(poll<24) await new Promise(resolve=>setTimeout(resolve,5000))
        }
        if(here())syncFeedback.value={type:'info',text:'本次任务仍未结束，点击「查看同步进度」可继续查看原任务。'}
    }catch(e){if(here())syncFeedback.value={type:'error',text:reason(e,'未确认同步结果，请刷新页面查看进度。')}}
    finally{if(ticket===syncEpoch)syncBusy.value=false}
}
function wipe(){++req;detailRow.value=null;records.value=[];total.value=0;loading.value=false;listError.value=''}
function clearCriteria(){keyword.value='';applied.value='';role.value='';status.value='';page.value=1}
function reset(){clearCriteria();return search(1)}
function applySearch(value:string|number){applied.value=String(value).trim();return search(1)}
function navigate(k:string){if(!props.embeddedCategories && titleFor(k) && k!==kind.value)void router.replace({name:routes.catalog,query:{resource:k}})}
watch(()=>route.query.resource,()=>{if(!active)return;const k=requested();if(k!==kind.value){kind.value=k;categoryView.value='tree';categoryNotice.value='';wipe();clearCriteria();if(canBrowse.value)void search(1)}})
function switchCategoryView(v:unknown){
    if(!active || !canBrowse.value || kind.value!=='categories' || (v!=='tree' && v!=='list') || v===categoryView.value)return
    categoryNotice.value='';categoryView.value=v;wipe();clearCriteria();if(v==='list')void search(1)
}
function usePagedCategories(){switchCategoryView('list');categoryNotice.value='分类数量较多，已切换为完整的分页列表。可按名称或编码搜索。'}
function openCategory(row:{id:string;name:string;categoryPath:string}){if(active && canBrowse.value && treeMode.value)detailRow.value=row}
function paginate(p:{page:number,limit:number}){if(![20,50,100].includes(p.limit))return;limit.value=p.limit;return search(p.page)}
async function loadIndex(){
    if(!active)return
    invalidateSync()
    const seq=++indexSeq,previous=contextKey.value;wipe();contextKey.value='';metadata.value=[];booting.value=true;indexError.value=''
    try{const raw=(await readOpeningJobs())?.data;if(!active || seq!==indexSeq)return
        if(!openingProgress(raw).valid || typeof raw[0].connection_key!=='string' || !raw.every((r:any)=>r.connection_key===raw[0].connection_key && r.mode===raw[0].mode && Number.isSafeInteger(r.count) && r.count>=0))throw Error('资料目录或账套信息不完整，请刷新目录。')
        if(previous && previous!==raw[0].connection_key){ clearCriteria();categoryView.value='tree';categoryNotice.value='' }
        metadata.value=raw;contextKey.value=raw[0].connection_key;booting.value=false
        if(canBrowse.value)await search(page.value)
    }catch(e){if(active && seq===indexSeq)indexError.value=reason(e,'无法读取目录，请核对查看权限后刷新。')}
    finally{if(active && seq===indexSeq)booting.value=false}
}
async function search(p:number){
    if(!active || !canBrowse.value || treeMode.value || !Number.isSafeInteger(p) || p<1)return
    wipe();const seq=req,key=contextKey.value,type=kind.value;page.value=p;loading.value=true
    try{const receipt=(await readOpeningRows(type,applied.value,p,key,{limit:limit.value,role:type==='parties'?role.value:'',archive_status:status.value}))?.data
        if(!active || req!==seq || key!==contextKey.value || type!==kind.value)return
        const data=receipt?.data
        if(receipt?.code!=='OK' || data?.source!=='local_archive' || !Array.isArray(data.items) || !Number.isSafeInteger(data.total) || data.total<0 || !data.items.every((r:any)=>typeof r.id==='string' && typeof r.name==='string'))throw Error('没有取得有效的档案列表，请重新读取。')
        total.value=data.total;records.value=data.items
        const last=Math.max(1,Math.ceil(data.total/limit.value));if(p>last && !records.value.length)await search(last)
    }catch(e){if(active && seq===req)listError.value=reason(e,'档案读取失败，请重试。')}
    finally{if(seq===req)loading.value=false}
}
function openDetail(row:any){if(canBrowse.value && records.value.includes(row))detailRow.value=row}
function leave(){active=false;++indexSeq;invalidateSync();wipe();clearCriteria();categoryView.value='tree';categoryNotice.value='';contextKey.value='';metadata.value=[]}
onMounted(loadIndex);onDeactivated(leave);onActivated(()=>{if(!active){active=true;kind.value=requested();void loadIndex()}});onBeforeUnmount(leave)
</script>
<style scoped>
.directory-head,.directory-head>div,.filters,footer,.empty{display:flex;align-items:center;gap:12px}.directory-head{justify-content:space-between;flex-wrap:wrap}.directory-head h2{margin:0;font-size:19px;color:var(--el-text-color-primary)}.directory-head small,.directory-head span{color:var(--el-text-color-secondary);font-size:12px}
.filters{margin:18px 0 16px;flex-wrap:wrap;gap:8px}.lookup{width:min(310px,100%)}.filters .el-select{width:126px}.filters .el-button{margin:0}
.record-name{max-width:100%;white-space:normal;line-height:1.4;justify-content:flex-start;text-align:left}.empty{padding:30px;justify-content:center;flex-wrap:wrap}footer{justify-content:flex-end;margin-top:16px;overflow-x:auto}
.directory-tools{flex-wrap:wrap}
.source-note{margin:10px 0 14px;color:var(--el-text-color-regular);font-size:14px;line-height:1.6}
</style>
