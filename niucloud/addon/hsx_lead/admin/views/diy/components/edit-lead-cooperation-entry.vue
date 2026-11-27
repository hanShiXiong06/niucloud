<template>
    <div v-show="diyStore.editTab==='content'" class="content-wrap">
        <div v-if="!businessMode" class="edit-attr-item-wrap"><h3 class="mb-[10px]">角色入口预设</h3><div class="preset-editor"><el-radio-group v-model="presetChoice"><el-radio-button v-for="preset in leadHomePresets" :key="preset.key" :label="preset.key">{{ preset.name }}</el-radio-button></el-radio-group><p class="editor-note">{{ selectedPreset?.description }}。选择后点击应用才生效，已有自定义配色不会自动改变。</p><el-button type="primary" plain :loading="applyingPreset" @click="applyPreset">应用此预设</el-button></div></div>
        <div class="edit-attr-item-wrap"><h3 class="mb-[10px]">{{ businessMode?'业务快捷入口':'角色入口内容' }}</h3><el-form label-width="96px" class="px-[10px]">
            <el-form-item label="显示标题"><el-switch v-model="diyStore.editComponent.showTitle" :active-value="1" :inactive-value="0" /></el-form-item>
            <el-form-item label="首页标题"><el-input v-model.trim="diyStore.editComponent.title" maxlength="30" show-word-limit /></el-form-item>
            <el-form-item label="辅助说明"><el-input v-model.trim="diyStore.editComponent.subtitle" maxlength="60" show-word-limit /></el-form-item>
            <el-form-item v-if="businessMode" label="业务布局"><el-radio-group v-model="diyStore.editComponent.layoutPreset"><el-radio-button label="compact">快捷网格</el-radio-button><el-radio-button label="list">列表</el-radio-button></el-radio-group></el-form-item>
            <template v-if="!businessMode">
            <el-form-item label="门店入口标题"><el-input v-model.trim="diyStore.editComponent.storeTitle" maxlength="12" show-word-limit /></el-form-item>
            <el-form-item label="门店入口说明"><el-input v-model.trim="diyStore.editComponent.storeSubtitle" maxlength="60" placeholder="选填，留空不显示" /></el-form-item>
            <el-form-item label="门店行动文字"><el-input v-model.trim="diyStore.editComponent.storeActionText" maxlength="20" placeholder="选填，留空不显示" /></el-form-item>
            <el-form-item label="门店入口图片"><UploadImage v-model="diyStore.editComponent.storeImage" :limit="1" /><p class="editor-note">自定义图片优先；留空时可使用预设素材或统一图标。</p></el-form-item>
            <el-form-item label="客户入口标题"><el-input v-model.trim="diyStore.editComponent.customerTitle" maxlength="12" show-word-limit /></el-form-item>
            <el-form-item label="客户入口说明"><el-input v-model.trim="diyStore.editComponent.customerSubtitle" maxlength="60" placeholder="选填，留空不显示" /></el-form-item>
            <el-form-item label="客户行动文字"><el-input v-model.trim="diyStore.editComponent.customerActionText" maxlength="20" placeholder="选填，留空不显示" /></el-form-item>
            <el-form-item label="客户入口图片"><UploadImage v-model="diyStore.editComponent.customerImage" :limit="1" /></el-form-item>
            <el-form-item label="使用预设素材"><el-switch v-model="diyStore.editComponent.usePresetImages" :active-value="1" :inactive-value="0" /></el-form-item>
            <el-form-item label="显示业务入口"><el-switch v-model="diyStore.editComponent.showBusinesses" :active-value="1" :inactive-value="0" /></el-form-item>
            <el-form-item v-if="diyStore.editComponent.showBusinesses===1" label="业务分组标题"><el-input v-model.trim="diyStore.editComponent.businessTitle" maxlength="30" /></el-form-item>
            <el-form-item label="显示底部工具"><el-switch v-model="diyStore.editComponent.showTools" :active-value="1" :inactive-value="0" /></el-form-item>
            <template v-if="diyStore.editComponent.showTools===1"><el-form-item label="门店工具入口"><el-input v-model.trim="diyStore.editComponent.profileText" maxlength="30" /></el-form-item><el-form-item label="收益工具入口"><el-input v-model.trim="diyStore.editComponent.walletText" maxlength="30" /></el-form-item></template>
            <el-form-item label="轻动效"><el-switch v-model="diyStore.editComponent.animate" :active-value="1" :inactive-value="0" /></el-form-item>
            </template>
        </el-form></div>
        <el-collapse v-if="!businessMode" class="preview-help"><el-collapse-item title="找门店卡片 · 单独调整" name="stores-appearance"><RoleEntryEditorFields :model-value="diyStore.editComponent.storesAppearance||{}" :fallback="comboRoleFallback(diyStore.editComponent,'stores')" nested @update:model-value="updateCard('storesAppearance',$event)" /></el-collapse-item><el-collapse-item title="找客户卡片 · 单独调整" name="customers-appearance"><RoleEntryEditorFields :model-value="diyStore.editComponent.customersAppearance||{}" :fallback="comboRoleFallback(diyStore.editComponent,'customers')" nested @update:model-value="updateCard('customersAppearance',$event)" /></el-collapse-item></el-collapse>
        <el-collapse class="preview-help"><el-collapse-item title="业务预览与生效范围" name="preview"><p class="editor-note">业务入口由本站启用业务决定；装修只改呈现，不修改费用或客户表单。</p>
        <el-alert :title="previewMessage" :type="previewState==='denied' ? 'warning' : previewState==='error' ? 'error' : 'info'" :closable="false" show-icon class="preview-status"><template #default><el-button v-if="canReadConfig" link type="primary" :loading="previewLoading" @click="loadPreview">刷新业务预览</el-button></template></el-alert></el-collapse-item></el-collapse>
    </div>
    <div v-show="diyStore.editTab==='style'" class="style-wrap">
        <DiyAppearanceFields :allow-panel="false" /><slot name="style" />
    </div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ElMessageBox } from 'element-plus'
import UploadImage from '@/components/upload-image/index.vue'
import useDiyStore from '@/stores/modules/diy'
import DiyAppearanceFields from './DiyAppearanceFields.vue'
import RoleEntryEditorFields from './RoleEntryEditorFields.vue'
import useUserStore from '@/stores/modules/user'
import { getCooperationConfig } from '../../../api/cooperation'
import { hasLeadPagePermission } from '../../../composables/navigation'
import { businessPreview, enabledPreviewBusinesses, isBusinessPreviewRequest, previewOrigin, type BusinessPreview } from '../../../composables/diyBusinessPreview'
import { leadHomePresets,leadHomePreset } from '../../../composables/diyPresets'
import { comboRoleFallback,roleEntryError } from '../../../composables/roleEntry'
const props=defineProps<{mode?:'roles'|'business'}>()
const businessMode=computed(()=>props.mode==='business')
const diyStore=useDiyStore()
function updateCard(key:'storesAppearance'|'customersAppearance',value:Record<string,any>){diyStore.editComponent[key]=value}
const presetChoice=ref('classic'),applyingPreset=ref(false),selectedPreset=computed(()=>leadHomePresets.find(preset=>preset.key===presetChoice.value))
async function applyPreset(){if(applyingPreset.value||businessMode.value)return;const patch=leadHomePreset(presetChoice.value),target=diyStore.editComponent;if(!patch||!target)return;applyingPreset.value=true;try{await ElMessageBox.confirm('将替换本区块的布局、颜色、内间距和预设素材开关；保留自定义图片、文字与功能开关。确认应用吗？','应用首页预设',{confirmButtonText:'确认应用',cancelButtonText:'取消',type:'warning'});if(diyStore.editComponent!==target)return;Object.assign(target,patch)}catch{}finally{applyingPreset.value=false}}
const user=useUserStore(),canReadConfig=computed(()=>hasLeadPagePermission(user,'hsx_lead_cooperation_config'))
const previewLoading=ref(false),previewState=ref('waiting'),previewMessage=ref('正在读取本站启用业务，仅用于当前预览，不写入装修配置。')
let previewValue:BusinessPreview|null=null,previewSequence=0,previewSite='',disposed=false,mounted=false
const activeSite=()=>Number(user.siteInfo?.site_id||0)>0?String(user.siteInfo.site_id):''
function previewFrame(){
    if(typeof window==='undefined'||typeof document==='undefined')return null
    const frame=document.getElementById('previewIframe') as HTMLIFrameElement|null
    const origin=frame?.src?previewOrigin(frame.src,window.location.href):''
    return frame?.contentWindow&&origin?{frame,origin}:null
}
function sendPreview(){const target=previewFrame();if(!disposed&&target&&previewValue&&(!previewSite||previewSite===activeSite())&&(previewValue.state!=='ready'||canReadConfig.value))target.frame.contentWindow!.postMessage(previewValue,target.origin)}
function previewRequested(event:MessageEvent){const target=previewFrame();if(disposed||!target||event.source!==target.frame.contentWindow||event.origin!==target.origin||!isBusinessPreviewRequest(event.data))return;sendPreview()}
async function loadPreview(){
    if(previewLoading.value||disposed)return
    const sequence=++previewSequence,site=activeSite();previewSite=site
    if(!canReadConfig.value){previewState.value='denied';previewMessage.value='当前账号没有业务配置查看权限，无法预览真实业务入口；装修文字和样式仍可编辑。';previewValue=businessPreview('denied',[],previewMessage.value);sendPreview();return}
    if(!site){previewState.value='error';previewMessage.value='站点身份尚未就绪，请重新进入装修后读取业务预览。';previewValue=businessPreview('error',[],previewMessage.value);sendPreview();return}
    previewLoading.value=true;previewState.value='waiting';previewMessage.value='正在读取本站启用业务，仅用于当前预览，不写入装修配置。'
    try{const result=await getCooperationConfig();if(disposed||sequence!==previewSequence||site!==activeSite()||!canReadConfig.value)return;const items=enabledPreviewBusinesses(result.data||{});previewState.value='ready';previewMessage.value=items.length?`已读取本站${items.length}项启用业务，开关可在预览中查看；配置调整后请刷新预览。`:'本站当前没有启用业务，预览不会生成虚构业务入口。';previewValue=businessPreview('ready',items,previewMessage.value)}
    catch{if(disposed||sequence!==previewSequence)return;previewState.value='error';previewMessage.value='业务预览读取失败，请确认业务配置查看权限后重试；已保留全部装修内容。';previewValue=businessPreview('error',[],previewMessage.value)}
    finally{if(!disposed&&sequence===previewSequence){previewLoading.value=false;sendPreview()}}
}
const appearanceDefaults={primaryColor:'',titleColor:'#202124',textColor:'#606266',subtleColor:'#909399',iconColor:'#333333',borderColor:'#e4e8ef',buttonColor:'',buttonTextColor:'#ffffff',titleSize:18,textSize:14,contentPadding:16,rowGap:12,itemRadius:12,buttonRadius:6}
const defaults=computed<Record<string,any>>(()=>businessMode.value?{...appearanceDefaults,title:'服务业务',showTitle:1,subtitle:'',layoutPreset:'compact'}:{...appearanceDefaults,title:'商户协作',showTitle:0,subtitle:'',layoutPreset:'classic',storeTitle:'找门店',storeSubtitle:'发布客户需求，找门店交付',storeActionText:'去找门店',customerTitle:'找客户',customerSubtitle:'查看交付给我的客户',customerActionText:'查看客户',businessTitle:'服务业务',profileText:'我的门店',walletText:'协作收益',showTools:0,showBusinesses:0,animate:1,usePresetImages:1,storeImage:'',customerImage:''})
diyStore.editComponent.verify=(index:number)=>{const component=diyStore.value[index];if(!component||component.componentName!==(businessMode.value?'LeadBusinessNavigation':'LeadCooperationEntry'))return{code:true,message:''};for(const [key,label] of [...(component.showTitle===1?[['title','区块标题']]:[]),...(businessMode.value?[]:[['storeTitle','门店入口标题'],['customerTitle','客户入口标题']])])if(!String(component[key]||'').trim())return{code:false,message:`请填写${label}`};if(!(businessMode.value?['compact','list']:leadHomePresets.map(preset=>preset.key)).includes(component.layoutPreset))return{code:false,message:'请选择有效的布局预设'};if(!businessMode.value)for(const role of ['stores','customers'] as const){const message=roleEntryError({...comboRoleFallback(component,role),...(component[role+'Appearance']||{}),role});if(message)return{code:false,message:(role==='stores'?'找门店卡片：':'找客户卡片：')+message}}return{code:true,message:''}}
onMounted(()=>{mounted=true;diyStore.editComponent.ignore=Array.isArray(diyStore.editComponent.ignore)?diyStore.editComponent.ignore:[];Object.entries(defaults.value).forEach(([key,value])=>{if(diyStore.editComponent[key]===undefined||diyStore.editComponent[key]===null)diyStore.editComponent[key]=value});presetChoice.value=leadHomePresets.some(preset=>preset.key===diyStore.editComponent.layoutPreset)?diyStore.editComponent.layoutPreset:'classic';if(typeof window!=='undefined')window.addEventListener('message',previewRequested);void loadPreview()})
watch(()=>`${activeSite()}:${canReadConfig.value?'1':'0'}`,()=>{if(!mounted||disposed)return;previewSequence++;previewLoading.value=false;previewSite=activeSite();previewValue=businessPreview('error',[],'站点或权限已变化，正在重新核对业务预览。');sendPreview();void loadPreview()})
onBeforeUnmount(()=>{disposed=true;previewSequence++;if(typeof window!=='undefined')window.removeEventListener('message',previewRequested)})
</script>
<style scoped>.editor-note{font-size:12px;line-height:1.6;color:var(--el-text-color-secondary);margin-top:8px} .preview-status{margin-top:12px}.preview-help{margin:12px 10px}.preset-editor{padding:0 10px 12px}.preset-editor :deep(.el-radio-group){display:flex;flex-wrap:wrap;gap:6px}.preset-editor :deep(.el-radio-button__inner){border:1px solid var(--el-border-color);border-radius:4px}</style>
