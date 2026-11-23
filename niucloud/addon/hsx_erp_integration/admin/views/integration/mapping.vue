<template>
    <ConsolePage title="资料映射" subtitle="把本站的一条资料对应到外部 ERP 档案。保存映射不会创建外部档案或补发业务单。" :loading="q.loading" :error="q.error" :page="q.page" :size="q.size" :total="q.total" @reload="refresh" @page="q.paginate">
        <template #actions><el-button @click="refresh">刷新</el-button><el-button v-if="canAdd" type="primary" :disabled="!options.length" @click="edit()">新建映射</el-button></template>
        <template #filters><el-select v-if="canOptions" v-model="q.filters.connection_id" clearable filterable placeholder="全部连接" @change="q.search"><el-option v-for="c in options" :key="c.id" :label="c.adapter_name" :value="c.id" /></el-select>
            <el-select v-model="q.filters.resource_type" clearable placeholder="全部资料类型" @change="q.search"><el-option v-for="(title,k) in resource" :key="k" :value="k" :label="title" /></el-select>
            <el-input v-model="q.filters.keyword" clearable placeholder="搜索名称或编码" @keyup.enter="q.search" @clear="q.search" /><el-button @click="q.search">查询</el-button><el-button text @click="q.reset">重置</el-button></template>
        <el-alert v-if="optionError" type="warning" :closable="false" :title="'未能读取连接：'+optionError"><el-button link @click="loadOptions">重新加载</el-button></el-alert>
        <el-alert v-else-if="canOptions && optionsReady && !options.length" type="info" :closable="false" title="还没有连接，请先到「连接管理」保存一个 ERP 连接。" />
        <el-table :data="q.rows" row-key="id" empty-text="没有匹配的资料映射">
            <el-table-column label="本地资料" min-width="165"><template #default="{row}"><strong>{{row.local_name || '未命名'}}</strong><div class="ic-muted">{{label(resource,row.resource_type)}} · {{row.local_code || ('#'+row.local_id)}}</div></template></el-table-column>
            <el-table-column label="外部 ERP 档案" min-width="180"><template #default="{row}">{{row.external_name || row.external_code}}<div v-if="row.external_name" class="ic-muted">{{row.external_code}}</div></template></el-table-column>
            <el-table-column label="所属连接" min-width="130"><template #default="{row}">{{options.find(c=>Number(c.id)===Number(row.connection_id))?.adapter_name || ('连接 #'+row.connection_id)}}</template></el-table-column>
            <el-table-column label="更新于" min-width="165"><template #default="{row}">{{time(row.updated_at)}}</template></el-table-column>
            <el-table-column label="操作" width="140"><template #default="{row}"><el-button v-if="permission('mapping_edit')" link type="primary" @click="edit(row)">编辑</el-button><el-button v-if="permission('mapping_del')" link type="danger" :disabled="busy" @click="remove(row)">移除</el-button></template></el-table-column>
        </el-table>
    </ConsolePage>
    <el-drawer v-model="visible" :title="id?'编辑资料映射':'新建资料映射'" size="min(500px, 94vw)" class="ic-drawer" destroy-on-close :close-on-click-modal="false">
        <el-form label-position="top" class="ic-form">
            <template v-if="!id">
                <el-form-item label="所属连接" required><el-select v-model="form.connection_id" filterable @change="changeConnection"><el-option v-for="c in options" :key="c.id" :label="c.adapter_name" :value="c.id" /></el-select></el-form-item>
                <el-form-item label="资料类型" required><el-select v-model="form.resource_type" :disabled="!types.length" :placeholder="form.connection_id&&!types.length?'该适配器暂无可映射类型':'请选择类型'" @change="changeType"><el-option v-for="k in types" :key="k" :label="label(resource,k)" :value="k" /></el-select></el-form-item>
                <el-form-item label="本站资料" required><el-select v-model="form.local_id" filterable remote :remote-method="searchLocal" :loading="looking" :disabled="!form.resource_type" placeholder="输入名称或编码搜索" @visible-change="(v:boolean)=>v && searchLocal('')">
                    <el-option v-for="item in candidates" :key="item.id" :value="item.id" :label="(item.name||item.code)+' · '+(item.code||item.id)" />
                </el-select><span v-if="lookupError" class="ic-help" role="alert">{{lookupError}} <el-button link type="primary" @click="searchLocal(lastKeyword)">重试</el-button></span></el-form-item>
            </template>
            <el-form-item v-else label="本站资料"><strong>{{form.local_name || ('#'+form.local_id)}}</strong>（{{label(resource,form.resource_type)}}）</el-form-item>
            <el-form-item label="外部档案编码" required><el-input v-model="form.external_code" maxlength="100" placeholder="与外部 ERP 中的编码一致" /></el-form-item>
            <el-form-item label="外部档案名称"><el-input v-model="form.external_name" maxlength="200" placeholder="选填，方便同事核对" /></el-form-item>
            <el-form-item label="外部档案 ID"><el-input v-model="form.external_id" maxlength="100" placeholder="选填，外部系统的唯一 ID" /></el-form-item>
        </el-form>
        <el-alert v-if="error" type="error" :closable="false" :title="error" />
        <template #footer><el-button @click="visible=false">取消</el-button><el-button type="primary" :loading="saving" @click="save">保存映射</el-button></template>
    </el-drawer>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { api, type Row } from '../../api/console'
import ConsolePage from './components/ConsolePage.vue'
import { useQuery,useAction,confirmAction,permission,message,resource,label,time } from './useConsole'
const q=reactive(useQuery(api.mappings,{connection_id:'',resource_type:'',keyword:''}))
const options=ref<Row[]>([]),optionsReady=ref(false),optionError=ref(''),candidates=ref<Row[]>([])
const canOptions=computed(()=>permission('mapping_connections')),canAdd=computed(()=>permission('mapping_add')&&canOptions.value&&permission('mapping_resources'))
let optionsSeq=0
async function loadOptions(){if(!canOptions.value)return;const token=++optionsSeq;optionError.value='';try{const r=await api.options();if(token===optionsSeq){options.value=r.list;optionsReady.value=true}}catch(e){if(token===optionsSeq){optionError.value=message(e);options.value=[]}}}
const refresh=()=>Promise.all([q.load(),loadOptions()])
const visible=ref(false),saving=ref(false),looking=ref(false),error=ref(''),lookupError=ref(''),id=ref(0),lastKeyword=ref('')
const fresh=()=>({connection_id:null,resource_type:'',local_id:null,local_name:'',external_name:'',external_code:'',external_id:''})
const form=reactive<Row>(fresh())
const types=computed<string[]>(()=>options.value.find(c=>c.id===form.connection_id)?.resource_types || [])
let serial=0
function changeType(){++serial;candidates.value=[];looking.value=false;lookupError.value='';form.local_id=null;lastKeyword.value=''}
function changeConnection(){form.resource_type=types.value.includes(q.filters.resource_type)?q.filters.resource_type:(types.value.length===1?types.value[0]:'');changeType()}
function edit(row?:Row){error.value='';id.value=row?.id||0;Object.assign(form,fresh());changeType();if(row)Object.assign(form,row);else{form.connection_id=q.filters.connection_id||(options.value.length===1?options.value[0].id:null);changeConnection()}visible.value=true}
async function searchLocal(key:string){if(!form.resource_type)return;const token=++serial;lastKeyword.value=key;looking.value=true;lookupError.value='';try{const v=await api.resources(form.resource_type,key);if(token===serial)candidates.value=v.list}catch(e){if(token===serial){candidates.value=[];lookupError.value=message(e)}}finally{if(token===serial)looking.value=false}}
async function save(){if(saving.value)return;error.value='';if(!form.connection_id||!form.resource_type||!form.local_id||!form.external_code?.trim()){error.value='请选择本地资料并填写外部档案编码';return}
    saving.value=true;try{await api.saveMapping(id.value,{connection_id:form.connection_id,resource_type:form.resource_type,local_id:form.local_id,external_code:form.external_code,external_name:form.external_name,external_id:form.external_id});visible.value=false;ElMessage.success('映射已保存');await q.load()}catch(e){error.value=message(e)}finally{saving.value=false}}
const {busy,run}=useAction()
function remove(row:Row){run(async()=>{if(!await confirmAction('移除后，后续业务可能需要重新关联。不会删除两边的基础资料。','移除 '+(row.local_name||'此映射')+'？','确认移除'))return;await api.deleteMapping(row.id);ElMessage.success('映射已移除');await q.load()})}
onMounted(refresh)
</script>
