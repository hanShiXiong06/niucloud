<template>
    <HsxDrawer v-model="visible" title="门店资料表单" subtitle="控制商户入驻与编辑时的资料要求" size="md" :body-loading="loading" :show-footer="true" :confirm-loading="saving" :confirm-disabled="!ready || loading || !canEdit" confirm-text="保存表单要求" @confirm="save">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="profile-settings-error"><template #default><el-button link type="primary" :disabled="saving || loading" @click="load">重新读取</el-button></template></el-alert>
        <template v-if="ready">
            <el-form label-position="top" :disabled="saving || loading || !canEdit">
                <h3 class="profile-settings-title">照片与资质</h3>
                <el-table :data="documentRows" class="profile-settings-docs">
                    <el-table-column label="资料" prop="label" min-width="120" />
                    <el-table-column label="显示" width="90"><template #default="{row}"><el-switch v-model="fields[row.key].visible" @change="value => !value && (fields[row.key].required=false)" /></template></el-table-column>
                    <el-table-column label="必填" width="90"><template #default="{row}"><el-switch v-model="fields[row.key].required" :disabled="!fields[row.key].visible" /></template></el-table-column>
                    <el-table-column label="顺序" width="135"><template #default="{row}"><el-input-number v-model="fields[row.key].sort" :min="0" :max="999" :precision="0" controls-position="right" style="width:110px" /></template></el-table-column>
                </el-table>
                <p class="profile-settings-note">营业执照仍私密保存，仅负责人和授权平台人员可查看。关闭显示不会删除已上传资料。</p>
                <h3 class="profile-settings-title">补充资料</h3>
                <el-form-item label="启用万能表单"><el-switch v-model="extra.enabled" /></el-form-item>
                <el-form-item v-if="extra.enabled" label="选择资料表单" required>
                    <el-select v-model="selectedExtraForm" filterable placeholder="搜索并选择本站万能表单" style="width:100%">
                        <el-option v-for="item in forms" :key="item.form_id" :value="Number(item.form_id)" :label="item.title || '表单 #'+item.form_id"><span>{{ item.title }}</span><span v-if="Number(item.status)!==1" class="profile-settings-option-state">未启用</span></el-option>
                    </el-select>
                    <p class="profile-settings-note">字段名称、顺序、必填及选项在牛云万能表单中维护。</p>
                    <el-button link type="primary" @click="openForms">打开万能表单管理</el-button>
                </el-form-item>
                <el-collapse><el-collapse-item title="哪些资料由业务固定校验？" name="core"><p class="profile-settings-note">门店名称、联系人、联系电话、完整地区、详细地址与经营能力由协作业务校验，不能通过表单配置关闭。补充资料暂支持单行／多行文本、手机号、邮箱、数字、单选、多选和日期；不支持的字段会明确阻止保存。</p></el-collapse-item></el-collapse>
            </el-form>
        </template>
        <template #footer><el-button :disabled="saving" @click="visible=false">关闭</el-button><el-button v-if="canEdit" type="primary" :disabled="!ready || loading" :loading="saving" @click="save">保存表单要求</el-button></template>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import { getProfileFormSettings, saveProfileFormSettings } from '../../api/cooperation'
import { coopError, type CoopRow } from '../../composables/cooperation'
const props=withDefaults(defineProps<{modelValue:boolean;canEdit?:boolean}>(),{canEdit:false})
const emit=defineEmits<{(event:'update:modelValue',value:boolean):void}>()
const user=useUserStore(),router=useRouter(),loading=ref(false),saving=ref(false),ready=ref(false),error=ref(''),version=ref(''),forms=ref<CoopRow[]>([])
const fields=reactive<CoopRow>({storefront_image:{visible:true,required:false,sort:0},business_license:{visible:true,required:false,sort:1}}),extra=reactive({enabled:false,form_id:0})
const selectedExtraForm=computed<number|undefined>({get:()=>extra.form_id||undefined,set:value=>{extra.form_id=Number(value||0)}})
const documentRows=[{key:'storefront_image',label:'门头照片'},{key:'business_license',label:'营业执照'}]
const visible=computed({get:()=>props.modelValue,set:value=>{if(!saving.value)emit('update:modelValue',value)}})
let sequence=0,disposed=false
const identity=()=>String(user.siteInfo?.site_id||'')+':'+String(user.token||'')
function install(data:CoopRow){
    if(!data||typeof data.version!=='string'||!data.version||!data.fields?.storefront_image||!data.fields?.business_license||!Array.isArray(data.forms))throw Error('门店表单配置返回不完整，请核对配套后端')
    version.value=data.version;for(const row of documentRows)fields[row.key]={...data.fields[row.key]};Object.assign(extra,{enabled:data.extra_form?.enabled===true,form_id:Number(data.extra_form?.form_id||0)});forms.value=data.forms;ready.value=true
    if(data.configuration_error)error.value=String(data.configuration_error)
}
async function load(){const turn=++sequence,actor=identity();loading.value=true;ready.value=false;error.value='';try{const response=await getProfileFormSettings();if(!disposed&&turn===sequence&&props.modelValue&&actor===identity())install(response.data)}catch(cause){if(!disposed&&turn===sequence)error.value=coopError(cause,'门店表单要求读取失败，请重试')}finally{if(!disposed&&turn===sequence)loading.value=false}}
async function save(){
    if(saving.value||loading.value||!ready.value||!props.canEdit||!props.modelValue)return
    if(extra.enabled&&!forms.value.some(row=>Number(row.form_id)===Number(extra.form_id))){error.value='请选择本站已有的万能表单';return}
    const actor=identity(),turn=sequence;saving.value=true;error.value=''
    try{const response=await saveProfileFormSettings({expected_version:version.value,fields:JSON.parse(JSON.stringify(fields)),extra_form:{enabled:extra.enabled,form_id:extra.enabled?Number(extra.form_id):0}});if(!disposed&&turn===sequence&&props.modelValue&&actor===identity()){install(response.data);ElMessage.success('门店资料要求已保存')}}catch(cause){if(!disposed&&turn===sequence)error.value=coopError(cause,'保存未完成，已保留修改，请核对后重试')}finally{saving.value=false}
}
function openForms(){if(!saving.value)router.push('/site/diy_form/list')}
watch(()=>[props.modelValue,identity()],()=>{sequence++;error.value='';ready.value=false;loading.value=false;if(props.modelValue)void load()},{immediate:true})
onBeforeUnmount(()=>{disposed=true;sequence++})
</script>
<style scoped>
.profile-settings-title{font-size:15px;margin:0 0 16px;color:var(--el-text-color-primary)}.profile-settings-title:not(:first-child){margin-top:28px}.profile-settings-note{font-size:12px;line-height:1.8;color:var(--el-text-color-secondary);margin:10px 0}.profile-settings-error{margin-bottom:16px}.profile-settings-docs{width:100%}.profile-settings-option-state{float:right;margin-left:16px;color:var(--el-text-color-secondary);font-size:12px}
</style>
