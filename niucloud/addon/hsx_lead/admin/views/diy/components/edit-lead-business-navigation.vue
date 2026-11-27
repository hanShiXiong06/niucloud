<template>
    <div class="business-editor">
        <div v-show="diyStore.editTab==='content'" class="business-content-tab">
            <div class="edit-attr-item-wrap"
                ><h3>内容与排列</h3
                ><el-form label-width="100px" class="px-[10px]">
                    <el-form-item label="显示标题"
                        ><el-switch v-model="component.showTitle" :active-value="1" :inactive-value="0"
                    /></el-form-item>
                    <el-form-item v-if="component.showTitle===1" label="标题"
                        ><el-input v-model="component.title" maxlength="30"
                    /></el-form-item>
                    <el-form-item label="副标题"><el-input v-model="component.subtitle" maxlength="80" /></el-form-item>
                    <el-form-item label="排列"
                        ><el-radio-group v-model="component.layoutPreset"
                            ><el-radio-button label="compact">网格</el-radio-button
                            ><el-radio-button label="list">列表</el-radio-button
                            ><el-radio-button label="slide">横向滑动</el-radio-button></el-radio-group
                        ></el-form-item
                    >
                    <el-form-item v-if="component.layoutPreset==='compact'" label="每行数量"
                        ><el-radio-group v-model="component.columns"
                            ><el-radio-button :label="3">3个</el-radio-button
                            ><el-radio-button :label="4">4个</el-radio-button
                            ><el-radio-button :label="5">5个</el-radio-button></el-radio-group
                        ></el-form-item
                    >
                    <el-form-item v-if="component.layoutPreset==='slide'" label="入口宽度"
                        ><el-input-number v-model="component.itemWidth" :min="72" :max="160" /> px</el-form-item
                    >
                    <el-form-item label="图标尺寸"
                        ><el-input-number v-model="component.iconSize" :min="24" :max="64" /> px</el-form-item
                    >
                </el-form></div
            >
            <div class="edit-attr-item-wrap"
                ><h3>各业务外观</h3
                ><el-alert
                    :title="previewMessage"
                    :type="previewState==='error'?'error':previewState==='denied'?'warning':'info'"
                    :closable="false"
                    show-icon
                    ><template #default
                        ><el-button
                            v-if="canReadConfig"
                            link
                            type="primary"
                            :loading="previewLoading"
                            @click="loadPreview"
                            >刷新启用业务</el-button
                        ></template
                    ></el-alert
                >
                <el-collapse class="business-items"
                    ><el-collapse-item
                        v-for="row in businesses"
                        :key="row.key"
                        :name="row.key"
                        :title="item(row.key).title||row.name"
                        ><el-form label-width="100px">
                            <el-form-item label="显示名称"
                                ><el-input
                                    :model-value="item(row.key).title"
                                    :placeholder="row.name"
                                    maxlength="30"
                                    @update:model-value="patchItem(row.key,'title',$event)"
                            /></el-form-item>
                            <el-form-item label="图标方式"
                                ><el-select
                                    :model-value="item(row.key).iconMode"
                                    @update:model-value="patchItem(row.key,'iconMode',$event)"
                                    ><el-option label="彩色线性图标" value="css" /><el-option
                                        label="原版图片"
                                        value="original" /><el-option label="图标库" value="library" /><el-option
                                        label="上传图片"
                                        value="image" /><el-option label="只显示文字" value="none" /></el-select
                            ></el-form-item>
                            <el-form-item v-if="item(row.key).iconMode==='image'" label="图标图片"
                                ><UploadImage
                                    :model-value="item(row.key).image"
                                    @update:model-value="patchItem(row.key,'image',$event)"
                            /></el-form-item>
                            <el-form-item v-if="item(row.key).iconMode==='library'" label="图标"
                                ><el-select
                                    :model-value="item(row.key).icon"
                                    @update:model-value="patchItem(row.key,'icon',$event)"
                                    ><el-option
                                        v-for="icon in iconOptions"
                                        :key="icon.value"
                                        :label="icon.label"
                                        :value="icon.value" /></el-select
                            ></el-form-item>
                            <el-form-item
                                v-if="!['image','original','none'].includes(item(row.key).iconMode)"
                                label="图标颜色"
                                ><el-color-picker
                                    :model-value="item(row.key).iconColor"
                                    @update:model-value="patchItem(row.key,'iconColor',$event||'')"
                            /></el-form-item>
                            <el-form-item v-if="item(row.key).iconMode!=='none'" label="图标底色"
                                ><el-color-picker
                                    :model-value="item(row.key).iconBgColor"
                                    show-alpha
                                    @update:model-value="patchItem(row.key,'iconBgColor',$event||'')"
                            /></el-form-item>
                            <el-form-item label="文字颜色"
                                ><el-color-picker
                                    :model-value="item(row.key).titleColor"
                                    @update:model-value="patchItem(row.key,'titleColor',$event||'')"
                            /></el-form-item>
                            <el-form-item label="角标"
                                ><el-radio-group
                                    :model-value="item(row.key).badgeMode"
                                    @update:model-value="patchItem(row.key,'badgeMode',$event)"
                                    ><el-radio-button label="none">无</el-radio-button
                                    ><el-radio-button label="hot">HOT</el-radio-button
                                    ><el-radio-button label="new">NEW</el-radio-button
                                    ><el-radio-button label="custom">自定义</el-radio-button></el-radio-group
                                ></el-form-item
                            >
                            <template v-if="item(row.key).badgeMode!=='none'"
                                ><el-form-item v-if="item(row.key).badgeMode==='custom'" label="角标文字"
                                    ><el-input
                                        :model-value="item(row.key).badgeText"
                                        maxlength="8"
                                        @update:model-value="patchItem(row.key,'badgeText',$event)" /></el-form-item
                                ><el-form-item label="角标颜色"
                                    ><el-color-picker
                                        :model-value="item(row.key).badgeColor"
                                        @update:model-value="patchItem(row.key,'badgeColor',$event||'')" /></el-form-item
                                ><el-form-item label="角标背景"
                                    ><el-color-picker
                                        :model-value="item(row.key).badgeBgColor"
                                        @update:model-value="patchItem(row.key,'badgeBgColor',$event||'')" /></el-form-item
                            ></template> </el-form></el-collapse-item
                ></el-collapse>
            </div>
        </div>
        <div v-show="diyStore.editTab==='style'" class="business-style-tab"
            ><div class="edit-attr-item-wrap"
                ><h3>内容外观</h3
                ><el-form label-width="100px"
                    ><el-form-item
                        v-for="field in styleColors.filter(row=>row.key!=='borderColor'||component.layoutPreset==='list')"
                        :key="field.key"
                        :label="field.label"
                        ><el-color-picker
                            v-model="component[field.key]"
                            :show-alpha="field.key==='borderColor'" /></el-form-item
                    ><el-form-item v-for="field in styleSizes" :key="field.key" :label="field.label"
                        ><el-input-number
                            v-model="component[field.key]"
                            :min="field.min"
                            :max="field.max"
                            :precision="0"
                        />
                        px</el-form-item
                    ></el-form
                ></div
            >
            <el-collapse class="business-help"
                ><el-collapse-item title="外观、背景与业务开关" name="help"
                    ><p
                        >这里只调整本站已启用业务的展示，不会开通业务或修改客户表单、费用。容器背景色、背景图与圆角使用下方牛云通用样式设置。彩色线性图标使用内置绘制样式，可单独修改颜色，不接受任意代码。</p
                    ></el-collapse-item
                ></el-collapse
            >
            <slot name="style" />
        </div>
    </div>
</template>
<script setup lang="ts">
import {computed,onBeforeUnmount,onMounted,ref,watch} from 'vue'
import useDiyStore from '@/stores/modules/diy'
import useUserStore from '@/stores/modules/user'
import UploadImage from '@/components/upload-image/index.vue'
import {getCooperationConfig} from '../../../api/cooperation'
import {hasLeadPagePermission} from '../../../composables/navigation'
import {businessPreview,enabledPreviewBusinesses,isBusinessPreviewRequest,previewOrigin,type BusinessPreview} from '../../../composables/diyBusinessPreview'
const diyStore=useDiyStore(),user=useUserStore(),component=computed(()=>diyStore.editComponent)
const appearanceDefaults={primaryColor:'',titleColor:'#202124',textColor:'#606266',subtleColor:'#909399',iconColor:'',borderColor:'#e4e8ef',buttonColor:'',buttonTextColor:'#ffffff',titleSize:18,textSize:14,contentPadding:16,rowGap:12,itemRadius:12,buttonRadius:6}
const styleColors=[{key:'titleColor',label:'标题颜色'},{key:'textColor',label:'正文颜色'},{key:'iconColor',label:'统一图标色'},{key:'subtleColor',label:'辅助文字'},{key:'borderColor',label:'列表分隔线'}]
const styleSizes=[{key:'titleSize',label:'标题字号',min:14,max:28},{key:'textSize',label:'正文字号',min:12,max:20},{key:'contentPadding',label:'内容内边距',min:0,max:32},{key:'rowGap',label:'入口间隔',min:0,max:32},{key:'itemRadius',label:'图标圆角',min:0,max:24}]
const canReadConfig=computed(()=>hasLeadPagePermission(user,'hsx_lead_cooperation_config'))
const businesses=ref<{key:string;name:string}[]>([]),previewLoading=ref(false),previewState=ref('waiting'),previewMessage=ref('正在读取本站启用业务…')
const iconOptions=[{value:'store',label:'门店'},{value:'customer',label:'客户'},{value:'order',label:'工单'},{value:'stock',label:'设备'},{value:'team',label:'团队'},{value:'wallet',label:'钱包'},{value:'member',label:'会员'},{value:'invite',label:'邀请'},{value:'connection',label:'连接'}]
const itemDefaults={title:'',iconMode:'css',icon:'order',image:'',iconColor:'',iconBgColor:'',titleColor:'',badgeMode:'none',badgeText:'',badgeColor:'#ffffff',badgeBgColor:'#ef6754'}
function item(key:string){const value=component.value.items?.[key],icons:Record<string,string>={purchase:'stock',new_phone:'member',rent:'order',exchange:'connection',broadband:'connection',repair:'order',home_recycle:'store'};return{...itemDefaults,icon:icons[key]||'order',...(value&&typeof value==='object'&&!Array.isArray(value)?value:{})}}
function patchItem(key:string,field:string,value:any){if(!canReadConfig.value||!businesses.value.some(row=>row.key===key)||!Object.prototype.hasOwnProperty.call(itemDefaults,field))return;const existing=component.value.items&&typeof component.value.items==='object'&&!Array.isArray(component.value.items)?component.value.items:{};component.value.items={...existing,[key]:{...(existing[key]||{}),[field]:value}}}
let disposed=false,mounted=false,sequence=0,site='',preview:BusinessPreview|null=null
const activeSite=()=>String(user.siteInfo?.site_id||'')
function previewFrame(){if(typeof window==='undefined'||typeof document==='undefined')return null;const frame=document.getElementById('previewIframe') as HTMLIFrameElement|null;const origin=frame?.src?previewOrigin(frame.src,window.location.href):'';return frame?.contentWindow&&origin?{frame,origin}:null}
function sendPreview(){const target=previewFrame();if(!disposed&&target&&preview&&site===activeSite()&&(preview.state!=='ready'||canReadConfig.value))target.frame.contentWindow!.postMessage(preview,target.origin)}
function previewRequested(event:MessageEvent){const target=previewFrame();if(disposed||!target||event.source!==target.frame.contentWindow||event.origin!==target.origin||!isBusinessPreviewRequest(event.data))return;sendPreview()}
async function loadPreview(){if(disposed||previewLoading.value)return;const run=++sequence,current=activeSite();site=current;businesses.value=[];if(!canReadConfig.value||!current){previewState.value=canReadConfig.value?'error':'denied';previewMessage.value=canReadConfig.value?'站点身份尚未就绪，请重新进入装修。':'当前账号没有业务配置查看权限，已保留全部外观设置。';preview=businessPreview(previewState.value as any,[],previewMessage.value);sendPreview();return}previewLoading.value=true;previewState.value='waiting';try{const result=await getCooperationConfig();if(disposed||run!==sequence||current!==activeSite()||!canReadConfig.value)return;businesses.value=enabledPreviewBusinesses(result.data||{});previewState.value='ready';previewMessage.value=businesses.value.length?`本站已启用 ${businesses.value.length} 项业务，展开即可单独设置。`:'本站暂未启用业务，不会生成虚构入口。';preview=businessPreview('ready',businesses.value,previewMessage.value)}catch{if(disposed||run!==sequence)return;previewState.value='error';previewMessage.value='业务读取失败，请重试。已保留全部外观设置。';preview=businessPreview('error',[],previewMessage.value)}finally{if(!disposed&&run===sequence){previewLoading.value=false;sendPreview()}}}
diyStore.editComponent.verify=(index:number)=>{const value=diyStore.value[index];if(!value||value.componentName!=='LeadBusinessNavigation')return{code:true,message:''};if(value.showTitle===1&&!String(value.title||'').trim())return{code:false,message:'请填写业务区块标题'};if(!['compact','list','slide'].includes(value.layoutPreset))return{code:false,message:'请选择有效的业务排列方式'};return{code:true,message:''}}
onMounted(()=>{mounted=true;Object.entries({...appearanceDefaults,title:'服务业务',showTitle:1,subtitle:'',layoutPreset:'compact',columns:4,iconSize:40,itemWidth:88,iconColor:'',items:{}}).forEach(([key,value])=>{if(component.value[key]===undefined||component.value[key]===null)component.value[key]=value});if(typeof window!=='undefined')window.addEventListener('message',previewRequested);void loadPreview()})
watch(()=>`${activeSite()}:${canReadConfig.value?'1':'0'}`,()=>{if(!mounted||disposed)return;sequence++;previewLoading.value=false;businesses.value=[];preview=businessPreview('waiting',[],'正在重新核对本站业务');site=activeSite();sendPreview();void loadPreview()})
onBeforeUnmount(()=>{disposed=true;sequence++;if(typeof window!=='undefined')window.removeEventListener('message',previewRequested)})
</script>
<style scoped>
.business-editor h3{margin-bottom:14px}.business-items,.business-help{margin:12px 10px}.business-help p{font-size:12px;line-height:1.6;color:var(--el-text-color-secondary)}.business-editor :deep(.el-radio-group){display:flex;flex-wrap:wrap;gap:4px}.business-editor :deep(.el-form-item__content){min-width:0}
</style>
