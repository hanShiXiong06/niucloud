<template>
    <view class="lead-business" :class="['is-'+layout,{'is-embedded':embedded}]" :style="entryStyle">
        <view v-if="shown(settings.showTitle)&&settings.title" class="lead-business-heading">{{ settings.title }}</view>
        <view v-if="settings.subtitle" class="lead-business-subtitle">{{ settings.subtitle }}</view>
        <scroll-view v-if="entries.length" class="lead-business-scroll" :scroll-x="layout==='slide'" :show-scrollbar="false" @touchstart="touchStart" @touchmove="touchMove" @touchend="touchEnd" @scroll="scrolling"><view class="lead-business-items" :style="itemsStyle"><button v-for="business in entries" :key="business.key" class="lead-business-item" :style="{color:business.titleColor}" hover-class="lead-business-pressed" @tap="publish(business.key)"><view v-if="business.iconMode!=='none'" class="lead-business-symbol" :style="{color:business.iconColor,backgroundColor:business.iconBgColor,width:iconSize+'px',height:iconSize+'px'}"><image v-if="business.iconMode==='original'||business.iconMode==='image'&&business.image&&!failedImages.includes(business.image)" :src="img(business.iconMode==='original'?business.original:business.image)" mode="aspectFit" class="lead-business-image" @error="failedImage(business.image)" /><CoopBusinessIcon v-else-if="business.iconMode==='library'" :name="business.icon" :color="business.iconColor" size="default" /><CoopServiceLineIcon v-else :shape="business.shape" :color="business.iconColor" :size="Math.round(iconSize*.75)" /><text v-if="business.badge" class="lead-business-badge" :style="{color:business.badgeColor,backgroundColor:business.badgeBgColor}">{{ business.badge }}</text></view><view class="lead-business-label"><text>{{ business.name }}</text><text v-if="business.iconMode==='none'&&business.badge" class="lead-business-text-badge" :style="{color:business.badgeColor,backgroundColor:business.badgeBgColor}">{{ business.badge }}</text></view><u-icon v-if="layout==='list'" name="arrow-right" size="12" color="var(--lead-diy-subtle,#909399)" /></button></view></scroll-view>
        <view v-if="store.mode==='decorate'" class="lead-business-message">{{ previewMessage }}</view>
        <view v-else-if="error" class="lead-business-error" role="alert"><text>{{ error }}</text><button @tap="load">重新加载</button></view>
        <view v-else-if="loading" class="lead-business-message">正在读取服务业务…</view>
        <view v-else-if="!businesses.length" class="lead-business-message">{{ getToken()?'本站暂未开放可选业务':'登录后查看本站服务业务' }}</view>
    </view>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { img,getToken, getSiteId } from '@/utils/common'
import { cooperationBootstrap, coopError } from '../../api/cooperation'
import { interactionFeedback } from '../../utils/interaction-feedback'
import { cooperationDiyVars } from '../../utils/cooperation-diy-style'
import { readCooperationBusinessEntries, clearCooperationBusinessCache } from '../../utils/cooperation-business-cache'
import { BUSINESS_PREVIEW_REQUEST, previewParentOrigin, readBusinessPreview } from '../../utils/diy-business-preview'
import CoopBusinessIcon from './CoopBusinessIcon.vue'
import CoopServiceLineIcon from './CoopServiceLineIcon.vue'
import {presentBusinessEntries,businessPresentationLayout,businessPresentationSize} from '../../utils/cooperation-business-presentation'
const props=defineProps(['appearance','embedded'])
const store=useDiyStore(),businesses=ref<any[]>([]),error=ref(''),loading=ref(false)
const settings=computed(()=>({title:'服务业务',subtitle:'',showTitle:1,layoutPreset:'compact',...(props.appearance||{})}))
const entryStyle=computed(()=>cooperationDiyVars(settings.value))
const entries=computed(()=>presentBusinessEntries(businesses.value,settings.value)),layout=computed(()=>businessPresentationLayout(settings.value.layoutPreset)),iconSize=computed(()=>businessPresentationSize(settings.value.iconSize)),failedImages=ref<string[]>([])
const itemsStyle=computed(()=>({'--business-columns':Math.max(3,Math.min(5,Number(settings.value.columns)||4)),'--business-item-width':businessPresentationSize(settings.value.itemWidth,88,72,160)+'px'}))
let touchPoint:{x:number;y:number}|null=null,moved=false,suppressedUntil=0
const point=(event:any)=>{const value=event?.touches?.[0]||event?.changedTouches?.[0];return value?{x:Number(value.clientX??value.pageX??0),y:Number(value.clientY??value.pageY??0)}:null}
function touchStart(event:any){touchPoint=point(event);moved=false}
function touchMove(event:any){const next=point(event);if(touchPoint&&next&&(Math.abs(next.x-touchPoint.x)>8||Math.abs(next.y-touchPoint.y)>8)){moved=true;suppressedUntil=Date.now()+350}}
function touchEnd(){if(moved)suppressedUntil=Date.now()+350;touchPoint=null}
function scrolling(){suppressedUntil=Date.now()+250}
function failedImage(value:string){if(value&&!failedImages.value.includes(value))failedImages.value=[...failedImages.value,value]}
const shown=(value:unknown)=>![false,0,'0'].includes(value as any)
const previewMessage=ref('请选择右侧业务组件，读取本站业务预览。')
let disposed = false
let businessSequence=0,businessIdentity=''
let previewOrigin='',previewTimer:ReturnType<typeof setTimeout>|undefined
function receivePreview(event:MessageEvent){
    // #ifdef H5
    if(disposed||store.mode!=='decorate'||typeof window==='undefined'||event.source!==window.parent||!previewOrigin||event.origin!==previewOrigin)return
    const value=readBusinessPreview(event.data);if(!value)return
    if(previewTimer)clearTimeout(previewTimer)
    businesses.value=value.items;previewMessage.value=value.message||(value.state==='ready'&&!value.items.length?'本站当前没有启用业务。':'业务入口仅按本站当前配置预览，点击不会执行发单。')
    // #endif
}
function connectPreview(){
    // #ifdef H5
    if(store.mode!=='decorate'||typeof window==='undefined'||typeof document==='undefined')return
    previewOrigin=previewParentOrigin(document.referrer)
    if(!previewOrigin||window.parent===window){previewMessage.value='业务预览未连接管理端，请从本站首页装修入口打开。';return}
    window.addEventListener('message',receivePreview)
    window.parent.postMessage({type:BUSINESS_PREVIEW_REQUEST},previewOrigin)
    previewTimer=setTimeout(()=>{if(!disposed)previewMessage.value='尚未收到业务预览，请在右侧选中本组件；无查看权限时不会展示业务名称。'},5000)
    // #endif
}
function publish(business: string) { if (store.mode === 'decorate'||Date.now()<suppressedUntil||!businesses.value.some(row=>row.key===business)) return; interactionFeedback('navigate'); uni.navigateTo({ url:`/addon/hsx_lead/pages/task/index?task=publish&business_type=${encodeURIComponent(business)}` }) }
function businessSite(){try{return String(getSiteId(typeof uni!=='undefined'&&typeof uni.getStorageSync==='function'?uni.getStorageSync('wap_site_id')||'':'')||'')}catch{return''}}
async function load(){
    if(store.mode==='decorate'||disposed)return
    const sequence=++businessSequence,token=String(getToken()||''),site=businessSite(),identity=token?`${site}:${token}`:''
    if(!token||identity!==businessIdentity){businesses.value=[];error.value=''}
    businessIdentity=identity
    if(!token){loading.value=false;clearCooperationBusinessCache();return}
    const current=()=>!disposed&&sequence===businessSequence&&store.mode!=='decorate'&&String(getToken()||'')===token&&businessSite()===site
    const discard=()=>{if(!disposed&&sequence===businessSequence&&store.mode!=='decorate'){businesses.value=[];error.value='';businessIdentity=''}}
    error.value='';loading.value=true
    try{
        const rows=await readCooperationBusinessEntries(identity,cooperationBootstrap)
        if(!current()){discard();return}
        businesses.value=rows
    }catch(cause){if(current())error.value=coopError(cause,'业务入口读取失败');else discard()}
    finally{if(!disposed&&sequence===businessSequence)loading.value=false}
}
onMounted(() => { connectPreview(); void load() })
onBeforeUnmount(() => {
    disposed = true
    businessSequence++
    if(previewTimer)clearTimeout(previewTimer)
    // #ifdef H5
    if(typeof window!=='undefined')window.removeEventListener('message',receivePreview)
    // #endif
})
defineExpose({ refresh:load })
</script>
<style scoped lang="scss">
.lead-business { padding:var(--lead-diy-content-padding,16px);color:var(--lead-diy-text,#606266);background:transparent; }
.lead-business.is-embedded { padding:0; }
.lead-business-heading { font-size:var(--lead-diy-title-size,18px);font-weight:600;line-height:1.5;color:var(--lead-diy-title,#202124); }
.lead-business-subtitle { margin-top:4px;font-size:12px;line-height:1.6;color:var(--lead-diy-subtle,#909399); }
.lead-business-scroll { width:100%;margin-top:8px; }
.lead-business-items { display:grid;grid-template-columns:repeat(var(--business-columns,4),minmax(0,1fr));gap:var(--lead-diy-row-gap,12px);padding:8px 2px 4px;box-sizing:border-box; }
.lead-business-items button { position:relative;min-width:0;min-height:44px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:9px;margin:0;padding:10px 4px;background:transparent;font-size:var(--lead-diy-text-size,14px);line-height:22px;overflow-wrap:anywhere;box-sizing:border-box; }
.lead-business-items button::after,.lead-business-error button::after { border:0; }
.lead-business.is-list .lead-business-items { grid-template-columns:minmax(0,1fr); }
.lead-business.is-list .lead-business-items button { width:100%;flex-direction:row;align-items:center;justify-content:space-between;gap:12px;padding:14px 2px;border-bottom:1px solid var(--lead-diy-border,#e4e8ef);text-align:left; }
.lead-business.is-list .lead-business-label { flex:1;min-width:0;text-align:left; }
.lead-business.is-slide .lead-business-items { display:flex;flex-wrap:nowrap;width:max-content;min-width:100%; }
.lead-business.is-slide .lead-business-item { width:var(--business-item-width,88px);flex:none; }
.lead-business-symbol { display:flex;align-items:center;justify-content:center;flex:none;max-width:calc(100% - 8px);border-radius:var(--lead-diy-item-radius,12px);box-sizing:border-box; }
.lead-business-image { width:100%;height:100%;border-radius:var(--lead-diy-item-radius,12px); }
.lead-business-label { min-width:0;max-width:100%;text-align:center;display:flex;align-items:center;justify-content:center;gap:4px;line-height:1.5; }
.lead-business-badge,.lead-business-text-badge { font-size:9px;line-height:15px;padding:0 5px;border-radius:7px 7px 7px 1px;white-space:nowrap;box-sizing:border-box; }
.lead-business-badge { position:absolute;top:2px;right:2px;max-width:calc(100% - 4px);overflow:hidden;text-overflow:ellipsis;z-index:1; }
.lead-business.is-list .lead-business-badge { top:8px;right:22px;max-width:76px; }
.lead-business-message { margin-top:12px;font-size:12px;line-height:1.6;color:var(--lead-diy-subtle,#909399); }
.lead-business-error { display:flex;align-items:center;gap:12px;margin-top:12px;font-size:12px;line-height:20px; }
.lead-business-error>text { flex:1;min-width:0; }
.lead-business-error button { flex:none;min-height:44px;margin:0;padding:6px 0;color:var(--lead-diy-primary,var(--primary-color,#333));background:transparent;font-size:13px; }
.lead-business-pressed { opacity:.65; }
</style>
