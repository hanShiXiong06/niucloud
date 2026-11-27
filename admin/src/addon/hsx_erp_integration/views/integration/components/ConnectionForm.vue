<template>
    <el-drawer v-model="show" :title="id ? '编辑连接' : '新建 ERP 连接'" size="min(520px, 94vw)" class="ic-drawer" destroy-on-close :close-on-click-modal="false">
        <el-result v-if="loadError" icon="warning" title="配置未能加载" :sub-title="loadError"><template #extra><el-button @click="load">重新加载</el-button></template></el-result>
        <div v-else v-loading="loading">
            <el-empty v-if="!loading && !adapters.length" description="暂无可用适配器，请先安装并给本站授权对应 ERP 插件" />
            <el-form v-else label-position="top" class="ic-form" @submit.prevent="submit">
                <el-form-item label="ERP 系统" required><el-select v-model="form.adapter" :disabled="!!id" placeholder="请选择 ERP" @change="selectAdapter">
                    <el-option v-for="a in adapters" :key="a.code" :label="a.name" :value="a.code" /></el-select></el-form-item>
                <template v-if="driver">
                    <el-form-item label="连接名称" required><el-input v-model="form.name" maxlength="100" placeholder="例如：门店采购账套" /></el-form-item>
                    <el-alert v-if="manual" type="info" :closable="false" title="人工原单模式：沿用本站已配置的厂商账套，不需要再填密钥。保存后核对连接，启用后只记录新业务，不会自动发单。" />
                    <p v-if="manual && id" class="ic-help">已绑定：{{ form.config.mode==='production'?'正式':'测试' }}账套 · {{ form.config.account_code || '未取得' }}</p>
                    <p v-else-if="manual && driver.manual_target" class="ic-help">本次绑定：{{ driver.manual_target.mode==='production'?'正式':'测试' }}账套 · {{ driver.manual_target.account_code }}</p>
                    <p v-if="manual && driver.manual_setup_hint" class="ic-help">{{driver.manual_setup_hint}}</p>
                    <el-form-item v-if="manual && !id && driver.manual_scope_options?.length" label="本次适用范围" required>
                        <el-select v-model="form.scope" placeholder="请选择本次要启用的范围">
                            <el-option v-for="option in driver.manual_scope_options" :key="option.value" :label="option.label" :value="option.value" />
                        </el-select>
                        <p class="ic-help">保存后范围不能更换，也不会启用其他业务。</p>
                    </el-form-item>
                    <p v-if="manual && id && form.scope" class="ic-help">适用范围：{{ boundScopeLabel || '原限定范围' }}（不可修改）</p>
                    <el-form-item v-for="f in (manual ? [] : driver.fields)" :key="f.name" :label="f.label" :required="f.required">
                        <el-select v-if="f.type==='select'" v-model="form.config[f.name]"><el-option v-for="o in f.options" :key="o.value" :value="o.value" :label="o.label" /></el-select>
                        <el-input v-else v-model="form.config[f.name]" :type="isSecret(f) ? 'password' : (f.type==='number' ? 'number' : 'text')" :show-password="isSecret(f)" :autocomplete="isSecret(f) ? 'new-password' : 'off'" :placeholder="saved[f.name] ? '已设置，留空保留' : f.placeholder || '请填写' + f.label" />
                        <p v-if="f.help" class="ic-help">{{ f.help }}</p>
                    </el-form-item>
                    <el-form-item label="要同步的业务" required><el-checkbox-group v-model="form.business">
                        <el-checkbox v-for="v in (manual ? driver.manual_business_types : driver.supported_business_types)" :key="v" :label="v">{{ label(business,v) }}</el-checkbox>
                    </el-checkbox-group></el-form-item>
                    <p v-if="!manual" class="ic-help">新连接默认暂停。更换账号或地址时请重新输入密钥；配置变更后，测试通过才能启用。</p>
                </template>
            </el-form>
        </div>
        <el-alert v-if="formError" :title="formError" type="error" :closable="false" show-icon />
        <template #footer><el-button @click="show=false">取消</el-button><el-button type="primary" :loading="saving" :disabled="loading || !!loadError || !driver" @click="submit">保存连接</el-button></template>
    </el-drawer>
</template>
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { api, type Adapter, type Field, type Row } from '../../../api/console'
import { business, label, message } from '../useConsole'
const props=defineProps<{modelValue:boolean; id:number; preset:string}>()
const emit=defineEmits(['update:modelValue','saved'])
const show=computed({get:()=>props.modelValue,set:v=>emit('update:modelValue',v)})
const adapters=ref<Adapter[]>([]),saved=ref<Row>({}),loading=ref(false),saving=ref(false),loadError=ref(''),formError=ref('')
const form=reactive({adapter:'',name:'',scope:'',config:{} as Row,business:[] as string[]})
const boundScopeLabel=ref('')
const driver=computed(()=>adapters.value.find(a=>a.code===form.adapter))
const existingMode=ref(''),manual=computed(()=>props.id ? existingMode.value==='manual' : Boolean(driver.value?.manual_business_types?.length))
const isSecret=(f:Field)=>f.type==='password'||/secret|password|token|private.key/i.test(f.name)
let seq=0
function selectAdapter() {
    form.scope=''; boundScopeLabel.value=''; form.config={}; form.business=manual.value ? (driver.value?.manual_business_types||[]).filter(k=>k==='purchase'||k==='payment') : []
    for(const f of driver.value?.fields||[]) form.config[f.name]=isSecret(f)?'':f.default??''
    if(!form.name && driver.value) form.name=driver.value.name
}
async function load() {
    const token=++seq
    Object.assign(form,{adapter:'',name:'',config:{},business:[]}); adapters.value=[]; saved.value={}
    loadError.value=''; formError.value=''; loading.value=true
    try {
        const [catalog, old]=await Promise.all([api.adapters(true), props.id?api.connection(props.id):Promise.resolve(null)])
        if(token!==seq)return
        adapters.value=catalog.list
        form.adapter=old?.adapter_code || (catalog.list.some(a=>a.code===props.preset)?props.preset:(catalog.list.length===1?catalog.list[0].code:''))
        existingMode.value=old?.sync_mode || ''; selectAdapter()
        if(old){ form.name=old.adapter_name; form.scope=old.manual_scope || ''; boundScopeLabel.value=old.manual_scope_label || driver.value?.manual_scope_options?.find(o=>o.value===form.scope)?.label || ''; form.business=old.business_types||[]; Object.assign(form.config,old.config); saved.value=old.saved_secrets||{} }
    } catch(e){ if(token===seq)loadError.value=message(e) }
    finally{ if(token===seq)loading.value=false }
}
watch(()=>[props.modelValue,props.id,props.preset],()=>{ if(props.modelValue)load(); else { ++seq; form.config={}; saved.value={} } },{immediate:true})
async function submit(){
    if(saving.value||loading.value||!driver.value)return
    formError.value=''
    if(!form.name.trim()){formError.value='请填写连接名称';return}
    if(manual.value && !props.id && driver.value.manual_scope_options?.length && !driver.value.manual_scope_options.some(o=>o.value===form.scope)){formError.value='请选择本次适用范围';return}
    for(const f of (manual.value?[]:driver.value.fields)) if(f.required && String(form.config[f.name]??'').trim()==='' && !saved.value[f.name]){formError.value='请填写'+f.label;return}
    if(!form.business.length){formError.value='请勾选要同步的业务';return}
    saving.value=true
    try{ const result=await api.saveConnection(props.id,{adapter_name:form.name.trim(),adapter_code:form.adapter,sync_mode:manual.value?'manual':'async',...(manual.value && !props.id && form.scope ? {manual_scope:form.scope}:{}),config:manual.value?{}:{...form.config},business_types:[...form.business]})
        ElMessage.success(result.status==='disabled'?'连接已保存，当前未启用':'连接已保存');show.value=false;emit('saved')
    }catch(e){formError.value=message(e)}finally{saving.value=false}
}
</script>
