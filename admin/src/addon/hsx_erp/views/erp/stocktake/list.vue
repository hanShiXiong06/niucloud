<template>
    <ErpDesktopPage class="main-container">
        <section class="erp-list-surface">
            <ErpWorkspaceHeader page="stocktake" :filter-count="Number(!!search.keyword) + Number(!!search.warehouse_id)">
                <template #title>库存盘点</template>
                <template #subtitle>PC 负责建单与差异复核，店员可在移动端扫码完成设备级盘点。</template>
                <template #extra>
                    <el-button aria-label="刷新盘点列表" title="刷新" :icon="Refresh" :loading="loading" @click="loadList" />
                    <el-button type="primary" :icon="Plus" @click="openCreate">新建盘点</el-button>
                </template>

            <HsxSearchPanel :show-layout-switch="false">
                <template #extra>
                    <el-button type="primary" :icon="Search" :loading="loading" @click="searchStocktakes">查询</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </template>
                <el-form :inline="true" @submit.prevent>
                    <el-form-item label="关键词"><el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="盘点单号 / 仓库 / 操作人" @keyup.enter="searchStocktakes" /></el-form-item>
                    <el-form-item label="仓库"><el-select v-model="search.warehouse_id" clearable class="!w-[220px]" placeholder="全部仓库"><el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" /></el-select></el-form-item>
                </el-form>
            </HsxSearchPanel>

            <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 erp-metrics">
                <div v-for="item in summaryCards" :key="item.label" class="summary-card"><div class="text-xs text-[color:var(--el-text-color-secondary)]">{{ item.label }}</div><div class="mt-2 text-2xl font-semibold" :class="item.class">{{ item.value }}</div></div>
            </div>

            </ErpWorkspaceHeader>

            <el-tabs v-model="search.status" class="mt-5" @tab-change="search.page = 1; loadList()">
                <el-tab-pane label="全部" name=""/><el-tab-pane label="盘点中" name="counting"/><el-tab-pane label="待复核" name="pending_review"/><el-tab-pane label="已完成" name="completed"/><el-tab-pane label="已取消" name="cancelled"/>
            </el-tabs>

            <ErpDataTable :data="rows" v-loading="loading" empty-text="暂无盘点任务" @row-dblclick="openDetail">
                <el-table-column label="盘点任务" min-width="210"><template #default="{row}"><div class="font-medium text-[color:var(--el-text-color-primary)]">{{ row.stocktake_no }}</div><div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ row.warehouse_name }}{{ row.location_name ? ' / '+row.location_name : ' / 全部库位' }}</div></template></el-table-column>
                <el-table-column label="进度" min-width="180"><template #default="{row}"><el-progress :percentage="row.progress_percent || 0" :stroke-width="8"/><div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">已扫 {{ row.scanned_count }} / 应盘 {{ row.expected_count }}</div></template></el-table-column>
                <el-table-column label="差异" min-width="220"><template #default="{row}"><div class="flex flex-wrap gap-1"><el-tag v-if="row.missing_count" type="danger" size="small">盘亏 {{row.missing_count}}</el-tag><el-tag v-if="row.surplus_count" type="warning" size="small">盘盈 {{row.surplus_count}}</el-tag><el-tag v-if="row.location_mismatch_count" type="warning" size="small">位置不符 {{row.location_mismatch_count}}</el-tag><el-tag v-if="row.status_abnormal_count" type="danger" size="small">状态异常 {{row.status_abnormal_count}}</el-tag><span v-if="!row.difference_count" class="text-[color:var(--el-text-color-secondary)]">暂无差异</span></div></template></el-table-column>
                <el-table-column label="责任人" width="150"><template #default="{row}"><div>{{ row.counter_name || '-' }}</div><div v-if="row.workflow_mode==='team'" class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">复核：{{row.reviewer_name||'-'}}</div></template></el-table-column>
                <el-table-column label="状态" width="110"><template #default="{row}"><el-tag :type="row.status_meta?.type || 'info'">{{row.status_meta?.label||row.status}}</el-tag></template></el-table-column>
                <el-table-column label="创建时间" width="170"><template #default="{row}">{{formatTime(row.create_at)}}</template></el-table-column>
                <el-table-column label="操作" width="100" fixed="right"><template #default="{row}"><el-button type="primary" link @click="openDetail(row)">查看</el-button></template></el-table-column>
            </ErpDataTable>
            <div class="mt-4 flex justify-end erp-pagination"><el-pagination v-model:current-page="search.page" v-model:page-size="search.limit" layout="total, prev, pager, next" :total="total" @current-change="loadList"/></div>
        </section>

        <HsxDialog class="erp-desktop-overlay" :confirm-loading="createDialog.loading" v-model="createDialog.visible" title="新建库存盘点" width="560px" destroy-on-close>
            <HsxNotice default-expanded title="创建时生成库存快照；盘点期间不锁死销售，提交时系统会再次核对设备状态。" type="info" :closable="false" show-icon class="mb-5"/>
            <el-form label-width="92px">
                <el-form-item label="盘点仓库" required><el-select v-model="createDialog.form.warehouse_id" class="w-full" placeholder="请选择仓库" @change="onCreateWarehouse"><el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id"/></el-select></el-form-item>
                <el-form-item label="盘点库位"><el-select v-model="createDialog.form.location_id" class="w-full" clearable placeholder="全部库位"><el-option v-for="item in createLocations" :key="item.id" :label="item.location_name" :value="item.id"/></el-select><div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">留空表示盘点整个仓库。</div></el-form-item>
                <el-form-item label="工作方式"><el-radio-group v-model="createDialog.form.workflow_mode"><el-radio-button label="simple">小团队</el-radio-button><el-radio-button label="team">分工复核</el-radio-button></el-radio-group><div class="mt-1 w-full text-xs text-[color:var(--el-text-color-secondary)]">小团队由同一人盘点和确认；分工复核适合仓管与主管协作。</div></el-form-item>
                <el-form-item label="盘点人"><el-select v-model="createDialog.form.counter_uid" class="w-full" filterable><el-option v-for="item in staff" :key="item.uid" :label="item.name" :value="item.uid"/></el-select></el-form-item>
                <el-form-item v-if="createDialog.form.workflow_mode==='team'" label="复核人" required><el-select v-model="createDialog.form.reviewer_uid" class="w-full" filterable><el-option v-for="item in staff" :key="item.uid" :label="item.name" :value="item.uid"/></el-select></el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="createDialog.form.remark" type="textarea" :rows="3" placeholder="可填写盘点原因或交接说明"/></el-form-item>
            </el-form>
            <template #footer><el-button :disabled="createDialog.loading" @click="createDialog.visible=false">取消</el-button><el-button :disabled="createDialog.loading" type="primary" :loading="createDialog.loading" @click="createTask">创建并开始盘点</el-button></template>
        </HsxDialog>

        <HsxDrawer class="erp-desktop-overlay" v-model="detail.visible" size="82%" destroy-on-close @closed="detail.row=null">
            <template #header><div><div class="text-lg font-semibold">{{detail.row?.stocktake_no || '盘点详情'}}</div><div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{detail.row?.warehouse_name}} {{detail.row?.location_name ? '/ '+detail.row.location_name : '/ 全部库位'}}</div></div></template>
            <div v-loading="detail.loading" class="detail-wrap">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-6"><div v-for="item in detailCards" :key="item.label" class="summary-card"><div class="text-xs text-[color:var(--el-text-color-secondary)]">{{item.label}}</div><div class="mt-2 text-xl font-semibold" :class="item.class">{{item.value}}</div></div></div>
                <div v-if="detail.row?.status==='counting'" class="mt-4 flex items-center gap-2 rounded-lg bg-[var(--el-color-primary-light-9)] p-3"><el-input v-model.trim="detail.scanCode" placeholder="可在 PC 手动输入 IMEI / SN / 资产号" @keyup.enter="scanCode"/><el-button type="primary" :loading="detail.scanLoading" @click="scanCode">登记</el-button></div>
                <div class="mt-5 flex items-center justify-between"><el-tabs v-model="itemSearch.result" class="flex-1" @tab-change="loadItems"><el-tab-pane label="全部" name=""/><el-tab-pane label="待盘" name="pending"/><el-tab-pane label="正常" name="normal"/><el-tab-pane label="盘亏" name="missing"/><el-tab-pane label="盘盈" name="surplus"/><el-tab-pane label="位置/状态异常" name="abnormal"/></el-tabs><el-input v-model.trim="itemSearch.keyword" clearable class="ml-4 !w-[250px]" placeholder="设备名 / IMEI / 资产号" @keyup.enter="loadItems"/></div>
                <el-table :data="items" v-loading="itemsLoading" size="large">
                    <el-table-column label="设备" min-width="220"><template #default="{row}"><div class="font-medium">{{row.model||'未填写设备名称'}}</div><div class="mt-1 text-xs text-[color:var(--el-text-color-secondary)]">{{row.spec||'-'}}</div></template></el-table-column>
                    <el-table-column label="识别信息" min-width="190"><template #default="{row}"><div>{{ erpSerialText(row) }}</div></template></el-table-column>
                    <el-table-column label="账面位置" min-width="160"><template #default="{row}">{{row.expected_warehouse_name||'-'}}<span v-if="row.expected_location_name"> / {{row.expected_location_name}}</span></template></el-table-column>
                    <el-table-column label="实际位置" min-width="160"><template #default="{row}">{{row.actual_warehouse_name||'-'}}<span v-if="row.actual_location_name"> / {{row.actual_location_name}}</span></template></el-table-column>
                    <el-table-column label="结果" width="110"><template #default="{row}"><el-tag :type="row.result_meta?.type||'info'">{{row.result_meta?.label||row.result}}</el-tag></template></el-table-column>
                    <el-table-column label="扫码人" width="130"><template #default="{row}">{{row.scanner_name||'-'}}</template></el-table-column>
                    <el-table-column label="处理" min-width="180"><template #default="{row}"><span v-if="row.resolution_status==='resolved'" class="text-[color:var(--el-color-success)]">已处理 · {{actionLabel(row.resolution_action)}}</span><el-button v-else-if="detail.row?.status==='pending_review' && !['normal','pending'].includes(row.result)" type="primary" link @click="openResolve(row)">处理差异</el-button><span v-else class="text-[color:var(--el-text-color-secondary)]">-</span></template></el-table-column>
                </el-table>
                <div class="mt-4 flex justify-end"><el-pagination v-model:current-page="itemSearch.page" v-model:page-size="itemSearch.limit" layout="total, prev, pager, next" :total="itemTotal" @current-change="loadItems"/></div>
            </div>
            <template #footer><div class="flex justify-between"><el-button v-if="['counting','pending_review'].includes(detail.row?.status)" type="danger" plain @click="cancelTask">取消盘点</el-button><span/><div><el-button @click="detail.visible=false">关闭</el-button><el-button v-if="detail.row?.status==='counting'" type="primary" @click="submitTask">提交差异复核</el-button><el-button v-if="detail.row?.status==='pending_review'" type="success" :disabled="detail.row?.unresolved_count>0" @click="completeTask">确认完成盘点</el-button></div></div></template>
        </HsxDrawer>

        <HsxDialog class="erp-desktop-overlay" :confirm-loading="resolveDialog.loading" v-model="resolveDialog.visible" title="处理盘点差异" width="520px" :destroy-on-close="false">
            <el-form label-width="92px"><el-form-item label="设备">{{resolveDialog.row?.model||'-'}} · {{ erpSerialText(resolveDialog.row) }}</el-form-item><el-form-item label="处理方式" required><el-radio-group v-model="resolveDialog.form.action" class="flex flex-col items-start gap-2"><el-radio v-for="item in resolveActions" :key="item.value" :label="item.value">{{item.label}}</el-radio></el-radio-group></el-form-item><el-form-item v-if="resolveDialog.form.action==='correct_location'" label="实际库位" required><el-select v-model="resolveDialog.form.actual_location_id" class="w-full"><el-option v-for="item in detail.row?.locations||[]" :key="item.id" :label="item.location_name" :value="item.id"/></el-select></el-form-item><el-form-item label="处理说明" required><el-input v-model.trim="resolveDialog.form.remark" type="textarea" :rows="3" placeholder="请记录复核事实与处理原因"/></el-form-item></el-form>
            <template #footer><el-button :disabled="resolveDialog.loading" @click="resolveDialog.visible=false">取消</el-button><el-button :disabled="resolveDialog.loading" type="primary" :loading="resolveDialog.loading" @click="resolveItem">确认处理</el-button></template>
        </HsxDialog>
    </ErpDesktopPage>
</template>

<script setup lang="ts">
import ErpDesktopPage from '@/addon/hsx_erp/components/ErpDesktopPage.vue'
import ErpWorkspaceHeader from '@/addon/hsx_erp/components/ErpWorkspaceHeader.vue'
import ErpDataTable from '@/addon/hsx_erp/components/ErpDataTable.vue'
import { HsxSearchPanel, HsxDialog, HsxDrawer, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { erpSerialText } from '@/addon/hsx_erp/utils/display'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { cancelErpStocktake, completeErpStocktake, createErpStocktake, getErpStaffOptions, getErpStocktakeInfo, getErpStocktakeItems, getErpStocktakeList, resolveErpStocktakeItem, scanErpStocktake, submitErpStocktake } from '@/addon/hsx_erp/api/erp'
const hsxFeedback = useFeedback()

const loading=ref(false), rows=ref<any[]>([]), total=ref(0), warehouses=ref<any[]>([]), staff=ref<any[]>([])
const search=reactive<any>({keyword:'',status:'',warehouse_id:'',page:1,limit:15})
function resetSearch() {
    Object.assign(search, { keyword: '', warehouse_id: '', status: '' })
    return searchStocktakes()
}
function searchStocktakes() {
    search.keyword = String(search.keyword || '').trim()
    search.page = 1
    return loadList()
}
const createDialog=reactive<any>({visible:false,loading:false,form:{warehouse_id:'',location_id:'',scope_type:'all',workflow_mode:'simple',counter_uid:0,reviewer_uid:0,remark:''}})
const detail=reactive<any>({visible:false,loading:false,row:null,scanCode:'',scanLoading:false})
const itemSearch=reactive<any>({keyword:'',result:'',page:1,limit:30}); const items=ref<any[]>([]),itemTotal=ref(0),itemsLoading=ref(false)
const resolveDialog=reactive<any>({visible:false,loading:false,row:null,form:{action:'',actual_location_id:0,remark:''}})
const createLocations=computed(()=>warehouses.value.find(i=>Number(i.id)===Number(createDialog.form.warehouse_id))?.locations||[])
const summaryCards=computed(()=>[{label:'任务总数',value:total.value,class:''},{label:'本页盘点中',value:rows.value.filter(i=>i.status==='counting').length,class:'text-[color:var(--erp-text-accent)]'},{label:'本页待复核',value:rows.value.filter(i=>i.status==='pending_review').length,class:'text-[color:var(--el-color-warning)]'},{label:'本页差异设备',value:rows.value.reduce((n,i)=>n+Number(i.difference_count||0),0),class:'text-[color:var(--el-color-danger)]'}])
const detailCards=computed(()=>{const r=detail.row||{};return[{label:'应盘',value:r.expected_count||0,class:''},{label:'已扫',value:r.scanned_count||0,class:'text-[color:var(--erp-text-accent)]'},{label:'正常',value:r.normal_count||0,class:'text-[color:var(--el-color-success)]'},{label:'盘亏',value:r.missing_count||0,class:'text-[color:var(--el-color-danger)]'},{label:'盘盈',value:r.surplus_count||0,class:'text-[color:var(--el-color-warning)]'},{label:'待处理',value:r.unresolved_count||0,class:'text-[color:var(--el-color-warning)]'}]})
const resolveActions=computed(()=>({missing:[{value:'confirm_missing',label:'确认盘亏（设备标记为已盘亏）'},{value:'keep_in_stock',label:'设备仍在库，保留库存'}],surplus:[{value:'pending_inbound',label:'转待入库登记（本次不自动入库）'},{value:'exclude',label:'排除，不属于本次库存'}],location_mismatch:[{value:'correct_location',label:'按实际位置修正库位'},{value:'keep_current',label:'保留账面位置'}],status_abnormal:[{value:'business_review',label:'转业务人工核查'},{value:'keep_current',label:'保留当前业务状态'}]} as any)[resolveDialog.row?.result]||[])
const formatTime=(v:any)=>v?new Date(Number(v)*1000).toLocaleString('zh-CN',{hour12:false}):'-'
const actionLabel=(v:string)=>({confirm_missing:'确认盘亏',keep_in_stock:'保留库存',pending_inbound:'待入库',exclude:'排除',correct_location:'修正库位',keep_current:'保留现状',business_review:'业务核查'} as any)[v]||v||'-'
async function loadBase(){const [w,s]:any=await Promise.all([getErpWarehouseOptions(),getErpStaffOptions()]);warehouses.value=Array.isArray(w?.data)?w.data:[];staff.value=s?.data?.users||[]}
async function loadList(){loading.value=true;try{const r:any=await getErpStocktakeList(search);rows.value=r?.data?.data||[];total.value=Number(r?.data?.total||0)}finally{loading.value=false}}
function openCreate(){const me=staff.value[0]?.uid||0;Object.assign(createDialog.form,{warehouse_id:warehouses.value[0]?.id||'',location_id:'',scope_type:'all',workflow_mode:'simple',counter_uid:me,reviewer_uid:me,remark:''});createDialog.visible=true}
function onCreateWarehouse(){createDialog.form.location_id=''}
async function createTask(){if(!createDialog.form.warehouse_id)return hsxFeedback.warning('请选择盘点仓库');if(createDialog.form.workflow_mode==='team'&&!createDialog.form.reviewer_uid)return hsxFeedback.warning('请选择复核人');createDialog.loading=true;try{const r:any=await createErpStocktake(createDialog.form);const taskId=Number(r?.data?.id||r?.data||0);if(!taskId)throw new Error('盘点任务创建成功，但未返回任务编号');createDialog.visible=false;await loadList();openDetail({id:taskId})}finally{createDialog.loading=false}}
async function openDetail(row:any){detail.visible=true;detail.loading=true;itemSearch.page=1;itemSearch.result='';try{const r:any=await getErpStocktakeInfo(Number(row.id));detail.row=r?.data||row;await loadItems()}finally{detail.loading=false}}
async function loadItems(){if(!detail.row?.id)return;itemsLoading.value=true;try{const params:any={...itemSearch};if(params.result==='abnormal'){delete params.result;params.resolution_status='pending'}const r:any=await getErpStocktakeItems(detail.row.id,params);let list=r?.data?.data||[];if(itemSearch.result==='abnormal')list=list.filter((i:any)=>['location_mismatch','status_abnormal'].includes(i.result));items.value=list;itemTotal.value=Number(r?.data?.total||0)}finally{itemsLoading.value=false}}
async function refreshDetail(){const r:any=await getErpStocktakeInfo(detail.row.id);detail.row=r?.data||detail.row;await loadItems();await loadList()}
async function scanCode(){if(!detail.scanCode)return;detail.scanLoading=true;try{const r:any=await scanErpStocktake(detail.row.id,{code:detail.scanCode});hsxFeedback.success(r?.data?.duplicate?'该设备已盘过，已更新扫码次数':'盘点成功');detail.scanCode='';await refreshDetail()}finally{detail.scanLoading=false}}
async function submitTask(){await ElMessageBox.confirm('未扫码的账面设备将标记为盘亏并进入复核，确认提交吗？','提交盘点',{type:'warning'});await submitErpStocktake(detail.row.id,detail.row.workflow_mode==='simple');hsxFeedback.success('盘点已提交');await refreshDetail()}
function openResolve(row:any){resolveDialog.row=row;Object.assign(resolveDialog.form,{action:'',actual_location_id:Number(row.actual_location_id||0),remark:''});resolveDialog.visible=true}
async function resolveItem(){if(!resolveDialog.form.action||!resolveDialog.form.remark)return hsxFeedback.warning('请选择处理方式并填写说明');resolveDialog.loading=true;try{await resolveErpStocktakeItem(detail.row.id,resolveDialog.row.id,resolveDialog.form);resolveDialog.visible=false;hsxFeedback.success('差异已处理');await refreshDetail()}finally{resolveDialog.loading=false}}
async function completeTask(){await ElMessageBox.confirm('确认后将正式写入盘亏状态和库位修正，并保留库存流水。','完成盘点',{type:'warning'});await completeErpStocktake(detail.row.id);hsxFeedback.success('盘点已完成');await refreshDetail()}
async function cancelTask(){const r:any=await ElMessageBox.prompt('取消后任务仅保留记录，不修改库存。请输入取消原因：','取消盘点',{inputValidator:(v:string)=>!!v?.trim()||'请输入原因',type:'warning'});await cancelErpStocktake(detail.row.id,r.value);hsxFeedback.success('盘点已取消');await refreshDetail()}
onMounted(async()=>{await loadBase();await loadList()})
</script>
<style scoped>.summary-card{border:1px solid var(--el-border-color-lighter);border-radius:10px;background:var(--el-fill-color-light);padding:16px}.detail-wrap{padding:0 4px 24px}</style>
