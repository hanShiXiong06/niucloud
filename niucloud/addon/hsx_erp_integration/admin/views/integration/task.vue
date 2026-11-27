<template>
    <ConsolePage title="同步任务" subtitle="用本站原单号或外部单号查询。同一条记录里核对单号、处理时间和异常。" :loading="q.loading" :error="q.error" :page="q.page" :size="q.size" :total="q.total" @reload="refresh" @page="q.paginate">
        <template #actions><el-button @click="refresh">刷新</el-button></template>
        <template #filters><el-input v-model="q.filters.source_no" clearable placeholder="原单号 / 外部单号" @keyup.enter="q.search" @clear="q.search" />
            <el-select v-model="q.filters.business_type" clearable placeholder="全部业务" @change="q.search"><el-option v-for="(v,k) in business" :key="k" :value="k" :label="v" /></el-select>
            <el-select v-model="q.filters.status" clearable placeholder="全部状态" @change="q.search"><el-option v-for="k in ['awaiting_confirmation','waiting_source','linked','pending','processing','succeeded','blocked','failed','unverified']" :key="k" :value="k" :label="states[k]" /></el-select>
            <el-date-picker v-model="dates" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始日期" end-placeholder="结束日期" @change="applyDates" />
            <el-button @click="q.search">查询</el-button><el-button text @click="reset">重置</el-button></template>
        <div v-if="canStats" class="ic-summary"><strong>今日全部任务</strong><span v-if="statsError">{{statsError}} <el-button link @click="loadStats">重新加载</el-button></span>
            <template v-else-if="stats"><span>共 {{stats.total}} 笔</span><span>成功 {{stats.success}}</span><span>处理中 / 待发送 {{stats.pending}}</span><span>失败 {{stats.failed}}</span></template><span v-else>读取中…</span></div>
        <el-table :data="q.rows" row-key="id" empty-text="没有匹配的同步任务">
            <el-table-column label="原单号 / 业务" min-width="190"><template #default="{row}"><strong>{{row.source_no || ('任务 #'+row.id)}}</strong><div class="ic-muted">{{label(business,row.business_type)}}</div></template></el-table-column>
            <el-table-column label="外部单号" min-width="165"><template #default="{row}">{{row.external_doc_no || '尚无回执'}}</template></el-table-column>
            <el-table-column label="状态" width="110"><template #default="{row}"><el-tag size="small" :type="row.status==='failed'?'danger':row.status==='succeeded'?'success':'info'">{{label(states,row.status)}}</el-tag></template></el-table-column>
            <el-table-column label="创建时间" min-width="155"><template #default="{row}">{{time(row.created_at)}}</template></el-table-column>
            <el-table-column v-if="permission('task_info')" label="操作" width="100"><template #default="{row}"><el-button link type="primary" @click="drawer?.open(row.id)">查看详情</el-button></template></el-table-column>
        </el-table>
    </ConsolePage>
    <TaskDetail ref="drawer" @changed="refresh" />
</template>
<script setup lang="ts">
import { computed,onMounted,reactive,ref } from 'vue'
import ConsolePage from './components/ConsolePage.vue'
import TaskDetail from './components/TaskDetail.vue'
import { api, type Row } from '../../api/console'
import { business,states,label,time,message,permission,useQuery } from './useConsole'
const q=reactive(useQuery(api.tasks,{source_no:'',business_type:'',status:'',start_date:'',end_date:''}))
const drawer=ref<InstanceType<typeof TaskDetail>>(), dates=ref<string[]|null>(null),stats=ref<Row|null>(null),statsError=ref('')
const canStats=computed(()=>permission('task_statistics'))
function applyDates(){q.filters.start_date=dates.value?.[0]||'';q.filters.end_date=dates.value?.[1]||'';q.search()}
function reset(){dates.value=null;q.reset()}
let seq=0
async function loadStats(){if(!canStats.value)return; const run=++seq;statsError.value='';try{const r=await api.statistics();if(run===seq)stats.value=r.today}catch(e){if(run===seq){stats.value=null;statsError.value=message(e)}}}
function refresh(){return Promise.all([loadStats(),q.load()])}
onMounted(refresh)
</script>
