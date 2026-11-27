<template>
    <ConsolePage title="异常处理" subtitle="先核对原单，再记录处理结果。这里的“已处理”不会把业务单改为同步成功。" :loading="q.loading" :error="q.error" :page="q.page" :size="q.size" :total="q.total" @reload="q.load" @page="q.paginate">
        <template #actions><el-button @click="q.load">刷新</el-button><el-button v-if="canDelete" :disabled="!selected.length || busy" @click="remove">删除已选记录</el-button></template>
        <template #filters><el-select v-model="q.filters.resolved" clearable placeholder="所有处理状态" @change="q.search"><el-option label="待处理" value="0" /><el-option label="已处理" value="1" /></el-select>
            <el-select v-model="q.filters.failure_type" clearable placeholder="全部原因" @change="q.search"><el-option v-for="(v,k) in reasons" :key="k" :value="k" :label="v" /></el-select>
            <el-input v-model="q.filters.source_no" clearable placeholder="搜索本站原单号" @keyup.enter="q.search" @clear="q.search" /><el-button @click="q.search">查询</el-button><el-button text @click="q.reset">重置</el-button></template>
        <el-table :data="q.rows" row-key="id" empty-text="没有匹配的异常记录" @selection-change="select">
            <el-table-column v-if="canDelete" type="selection" width="42" :selectable="(r:Row)=>Number(r.resolved)===1" />
            <el-table-column label="原单号 / 业务" min-width="175"><template #default="{row}"><strong>{{row.source_no || ('任务 #'+row.task_id)}}</strong><div class="ic-muted">{{label(business,row.business_type)}}</div></template></el-table-column>
            <el-table-column label="异常原因" min-width="130"><template #default="{row}">{{label(reasons,row.failure_type)}}</template></el-table-column>
            <el-table-column label="处理状态" width="100"><template #default="{row}"><el-tag size="small" :type="Number(row.resolved)?'info':'warning'">{{Number(row.resolved)?'已处理':'待处理'}}</el-tag></template></el-table-column>
            <el-table-column label="发生时间" min-width="155"><template #default="{row}">{{time(row.created_at)}}</template></el-table-column>
            <el-table-column v-if="permission('failure_info')" label="操作" width="105"><template #default="{row}"><el-button link type="primary" @click="open(row.id)">{{!Number(row.resolved)&&permission('failure_resolve')?'查看并处理':'查看详情'}}</el-button></template></el-table-column>
        </el-table>
    </ConsolePage>
    <el-drawer v-model="detail.visible" class="ic-drawer" title="异常详情" size="min(540px, 94vw)" destroy-on-close>
        <el-result v-if="detail.error" icon="warning" title="未能读取异常" :sub-title="detail.error"><template #extra><el-button @click="open(detail.id)">重试查询</el-button></template></el-result>
        <div v-else v-loading="detail.loading" style="min-height:100px"><template v-if="detail.data">
            <dl class="ic-detail"><dt>业务单</dt><dd>{{detail.data.source_no || ('任务 #'+detail.data.task_id)}} · {{label(business,detail.data.business_type)}}</dd>
                <dt>异常原因</dt><dd>{{label(reasons,detail.data.failure_type)}}<div>{{detail.data.error_detail || '无附加说明'}}</div></dd>
                <dt>任务状态</dt><dd>{{detail.data.task ? label(states,detail.data.task.status) : '未查到原任务'}}</dd>
                <dt>外部单号</dt><dd>{{detail.data.task?.external_doc_no || '尚无单号回执'}}</dd>
                <dt>处理状态</dt><dd>{{Number(detail.data.resolved)?'已处理':'待处理'}}</dd>
                <template v-if="Number(detail.data.resolved)"><dt>处理说明</dt><dd>{{detail.data.resolution_note || '—'}}</dd><dt>处理时间</dt><dd>{{time(detail.data.resolved_at)}} · 员工 #{{detail.data.resolved_by}}</dd></template>
            </dl>
            <el-alert v-if="!Number(detail.data.resolved)" type="info" :closable="false" :title="detail.data.failure_type==='mapping_missing'?'先到资料映射补齐信息，再到原任务核对能否重试。':'请到外部 ERP 核对原单，不要因缺少回执重复下单或付款。'" />
            <el-button v-if="detail.data.task && permission('task_info')" class="mt-3" link type="primary" @click="taskDrawer?.open(detail.data.task_id)">查看原任务</el-button>
            <el-form v-if="canResolve" class="mt-4" label-position="top"><el-form-item label="处理说明（必填）"><el-input v-model="note" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="记录核对了什么、如何处理，方便下一位同事查阅" /></el-form-item></el-form>
        </template></div>
        <template #footer><el-button @click="detail.visible=false">关闭</el-button><el-button v-if="canResolve" type="primary" :loading="busy" :disabled="!note.trim()" @click="resolve">登记为已处理</el-button></template>
    </el-drawer>
    <TaskDetail ref="taskDrawer" @changed="refreshDetail" />
</template>
<script setup lang="ts">
import { computed,onMounted,reactive,ref,watch } from 'vue'
import { ElMessage } from 'element-plus'
import { api,type Row } from '../../api/console'
import ConsolePage from './components/ConsolePage.vue'
import TaskDetail from './components/TaskDetail.vue'
import { useQuery,useDetail,useAction,confirmAction,permission,reasons,business,states,label,time } from './useConsole'
const q=reactive(useQuery(api.failures,{resolved:'0',failure_type:'',source_no:''})),detail=useDetail(api.failure)
const selected=ref<number[]>([]),note=ref(''),taskDrawer=ref<InstanceType<typeof TaskDetail>>()
const canDelete=computed(()=>permission('failure_batch_delete')),canResolve=computed(()=>detail.data && !Number(detail.data.resolved) && permission('failure_resolve'))
const {busy,run}=useAction()
function open(id:number){note.value='';detail.open(id)}
function select(rows:Row[]){selected.value=rows.filter(r=>Number(r.resolved)===1).map(r=>r.id)}
watch(()=>q.rows,()=>{selected.value=[]})
async function refreshDetail(){await Promise.all([q.load(),detail.visible?detail.open(detail.id):undefined])}
function resolve(){run(async()=>{const id=detail.id;if(!canResolve.value||!note.value.trim())return;await api.resolve(id,note.value.trim());ElMessage.success('已登记处理结果，未重发业务单');await refreshDetail()})}
function remove(){run(async()=>{const ids=[...selected.value];if(!ids.length||!await confirmAction('仅删除选中的已处理异常记录，不会删除或重发原任务。','删除 '+ids.length+' 条记录？','确认删除'))return;const r=await api.deleteResolved(ids);ElMessage.success('已删除 '+r.count+' 条记录');await q.load()})}
onMounted(q.load)
</script>
