<template>
    <ConsolePage title="连接管理" subtitle="绑定本站厂商账套，核对后启用。人工原单模式只接收新业务，暂停后仍能查旧回执。" :loading="q.loading" :error="q.error" :page="q.page" :size="q.size" :total="q.total" @reload="q.load" @page="q.paginate">
        <template #actions><el-button @click="q.load">刷新</el-button><el-button v-if="canAdd" type="primary" @click="edit(0)">新建连接</el-button></template>
        <template #filters><el-input v-model="q.filters.keyword" clearable placeholder="搜索连接名称" @keyup.enter="q.search" @clear="q.search" />
            <el-select v-model="q.filters.status" clearable placeholder="全部状态" @change="q.search"><el-option label="已启用" value="enabled" /><el-option label="已暂停" value="disabled" /></el-select>
            <el-button @click="q.search">查询</el-button><el-button text @click="q.reset">重置</el-button></template>
        <el-table :data="q.rows" row-key="id" empty-text="没有匹配的连接；首次使用请新建连接">
            <el-table-column label="连接 / 适配器" min-width="180"><template #default="{row}"><strong>{{row.adapter_name}}</strong><div class="ic-muted">{{row.adapter_code}}</div></template></el-table-column>
            <el-table-column label="同步业务" min-width="170"><template #default="{row}">{{(row.business_types||[]).map((v:string)=>label(business,v)).join('、') || '—'}}<div v-if="row.manual_scope_label" class="ic-muted">{{row.manual_scope_label}}</div></template></el-table-column>
            <el-table-column label="状态" width="100"><template #default="{row}"><el-tag :type="row.status==='enabled'?'success':'info'" size="small">{{label(states,row.status)}}</el-tag></template></el-table-column>
            <el-table-column label="最近测试" min-width="155"><template #default="{row}">{{row.last_test_result==='success'?'测试通过':row.last_test_result==='failed'?'测试未通过':'尚未测试'}}<div v-if="row.last_test_at" class="ic-muted">{{time(row.last_test_at)}}</div></template></el-table-column>
            <el-table-column label="操作" min-width="250"><template #default="{row}"><div class="ic-inline">
                <el-button v-if="canEdit" link type="primary" @click="edit(row.id)">编辑</el-button>
                <el-button v-if="permission('connection_test')" link type="primary" :disabled="busy" @click="test(row)">测试</el-button>
                <el-button v-if="permission('connection_status')" link type="primary" :disabled="busy || (row.status!=='enabled' && row.last_test_result!=='success')" @click="toggle(row)">{{ row.status==='enabled'?'暂停':'启用' }}</el-button>
                <el-button v-if="permission('connection_del') && row.status==='disabled'" link type="danger" :disabled="busy" @click="remove(row)">删除</el-button>
            </div></template></el-table-column>
        </el-table>
    </ConsolePage>
    <ConnectionForm v-model="formOpen" :id="formId" :preset="preset" @saved="q.load" />
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { api, type Row } from '../../api/console'
import ConsolePage from './components/ConsolePage.vue'
import ConnectionForm from './components/ConnectionForm.vue'
import { business, states, label, permission, time, useQuery, useAction, confirmAction } from './useConsole'
const route=useRoute(),router=useRouter()
const q=reactive(useQuery(api.connections,{status:'',keyword:''}))
const formOpen=ref(false),formId=ref(0),preset=ref('')
const canAdd=computed(()=>permission('connection_add') && permission('connection_adapters'))
const canEdit=computed(()=>permission('connection_edit') && permission('connection_adapters') && permission('connection_info'))
const {busy,run}=useAction()
function edit(id:number,code=''){ formId.value=id;preset.value=code;formOpen.value=true }
watch(()=>route.query.adapter,code=>{ if(typeof code==='string' && canAdd.value){edit(0,code);const query={...route.query};delete query.adapter;router.replace({query})} },{immediate:true})
function test(row:Row){run(async()=>{ if(!await confirmAction(row.sync_mode==='manual'?'核对本站厂商连接、已验证的账套和环境，不创建或发送业务单。':'将用已保存的账号向外部 ERP 测试连通性，不创建业务单。','测试 '+row.adapter_name,'开始测试'))return
    try{await api.test(row.id);ElMessage.success('连接测试通过')}finally{await q.load()} })}
function toggle(row:Row){run(async()=>{const on=row.status!=='enabled'; if(!await confirmAction(on?(row.sync_mode==='manual'?'启用后，新单会出现在 ERP 原单上等待人工关联。不会补历史单，不会自动发送或付款。':'启用后，已接入的业务事件可向此外部账套发送。'):'暂停桥后不再关联新业务，历史回执可查；厂商已确认的任务不被撤销。',on?'启用连接？':'暂停连接？',on?'确认启用':'确认暂停'))return
    await api.status(row.id,on?'enabled':'disabled');ElMessage.success(on?'已启用':'已暂停');await q.load()})}
function remove(row:Row){run(async()=>{if(!await confirmAction('仅可删除未使用的暂停连接：'+row.adapter_name,'删除连接？','删除'))return;await api.deleteConnection(row.id);ElMessage.success('连接已删除');await q.load()})}
onMounted(q.load)
</script>
