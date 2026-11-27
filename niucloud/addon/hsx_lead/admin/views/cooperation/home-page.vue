<template>
    <PageShell permission="hsx_lead_cooperation_home">
        <header class="coop-head"><h2>协作首页装修</h2></header>
        <section class="coop-surface lead-home-decoration">
            <div
                ><el-tag effect="plain" type="info">手机端首页</el-tag><h3>每张卡片，单独装修</h3
                ><p>标题区、找门店、找客户与业务入口独立摆放，配色、背景图、插画和按钮均可分别调整。</p></div
            >
            <div class="lead-home-actions"
                ><el-button type="primary" :disabled="applying" @click="decorate">打开首页装修</el-button
                ><el-button :disabled="!canApply||applying||readRequired" :loading="applying" @click="applyBlueWhite"
                    >备份后应用蓝白预设</el-button
                ></div
            >
        </section>
        <el-alert
            v-if="progress||error"
            :title="error||progress"
            :type="error?'warning':'info'"
            :closable="false"
            show-icon
            class="lead-home-status"
        />
        <section v-if="backupId||readRequired" class="coop-surface lead-home-backup"
            ><p v-if="backupId"
                >原首页已备份为页面 #{{ backupId
                }}，没有切换当前使用页。可打开备份核对，也可在牛云微页面列表中恢复使用。</p
            ><el-button v-if="backupId" @click="decorate(backupId)">查看备份</el-button
            ><el-button v-if="readRequired" :loading="applying" @click="verifyResult">核对首页状态</el-button></section
        >
        <el-collapse class="lead-home-help"
            ><el-collapse-item title="预设、独立卡片与恢复" name="scope"
                ><p class="coop-muted"
                    >装修器左侧可添加多个“独立角色卡片”，分别选择找门店或找客户；外层背景、背景图与上下圆角使用牛云通用样式。组合入口仍可选，在其中展开两张卡也能分别修改。业务规则仍在业务配置维护。</p
                ><p class="coop-muted"
                    >顶部“页面模板”可选择“蓝白原版 ·
                    独立卡片”。直接切换模板只替换编辑草稿，牛云会提示确认；替换已有首页前建议先复制备份。本页“备份后应用”会经一次确认，先复制并核对原首页，再替换当前排版和文案并回读保存结果，不自动切换到备份页。失败时备份保留，不自动回滚或重复覆盖。</p
                ></el-collapse-item
            ></el-collapse
        >
    </PageShell>
</template>
<script setup lang="ts">
import {computed,onBeforeUnmount,ref} from 'vue'
import { useRouter } from 'vue-router'
import {ElMessageBox} from 'element-plus'
import useUserStore from '@/stores/modules/user'
import {getDecoratePage,getDiyPageInfo,getDiyTemplatePages,copyDiy,editDiyPage} from '@/app/api/diy'
import {hasLeadPagePermission} from '../../composables/navigation'
import {applyBlueWhiteHome,diyPageSource} from '../../composables/diyHomePreset'
import PageShell from '../components/PageShell.vue'
const router=useRouter(),user=useUserStore(),applying=ref(false),error=ref(''),progress=ref(''),backupId=ref(0),readRequired=ref(false)
const canApply=computed(()=>hasLeadPagePermission(user,'hsx_lead_cooperation_home')&&hasLeadPagePermission(user,'page_decorate'))
const site=()=>String(user.siteInfo?.site_id||''),identity=()=>site()+':'+String(user.token||'')
let pending:{id:number;value:string;identity:string}|null=null
let disposed=false
onBeforeUnmount(()=>{disposed=true})
function decorate(id?:number){const target=router.resolve({path:'/decorate/edit',query:{...(Number.isSafeInteger(id)?{id}:{name:'DIY_HSX_LEAD_INDEX'}),back:'/site/hsx_lead/cooperation/home',addon:'hsx_lead'}});window.open(target.href,'_blank','noopener')}
async function applyBlueWhite(){if(disposed||applying.value||readRequired.value||!canApply.value)return;applying.value=true;error.value='';const owner=identity();const assertCurrent=()=>{if(disposed||!site()||identity()!==owner||!canApply.value)throw Error('页面、站点、登录或权限已变化，已中止；请重新进入本页核对')};try{assertCurrent();try{await ElMessageBox.confirm('将替换当前首页的排版、文案和样式为蓝白原版。系统会先复制并核对当前完整装修数据，原备份页保留，可在牛云微页面列表恢复。确认继续吗？','备份后应用蓝白预设',{confirmButtonText:'确认备份并应用',cancelButtonText:'取消',type:'warning'})}catch{return}assertCurrent();await applyBlueWhiteHome({getDecoratePage,getDiyPageInfo,getDiyTemplatePages,copyDiy,editDiyPage},{assertCurrent,stage:value=>progress.value=value,backup:value=>backupId.value=value,beforeWrite:(id,value)=>{pending={id,value,identity:owner};readRequired.value=true}});pending=null;readRequired.value=false;progress.value='蓝白首页已保存并回读核对，请打开装修继续调整';}catch(cause:any){error.value=(cause?.message||'应用未完成，请核对当前首页')+(backupId.value?`；原备份 #${backupId.value} 保留`:'')}finally{applying.value=false}}
async function verifyResult(){if(disposed||applying.value||!pending||!canApply.value)return;applying.value=true;try{if(identity()!==pending.identity)throw Error('站点或登录已变化，请返回原站点核对');const result=await getDiyPageInfo(pending.id);if(disposed||identity()!==pending.identity||!canApply.value)throw Error('页面、站点、登录或权限已变化，请返回原站点核对');const active=await getDecoratePage({type:'DIY_HSX_LEAD_INDEX'});if(disposed||identity()!==pending.identity||!canApply.value)throw Error('页面、站点、登录或权限已变化，请返回原站点核对');if(Number(active.data?.use_template?.id)!==pending.id||Number(result.data?.id)!==pending.id||Number(result.data?.is_default)!==1||result.data?.name!=='DIY_HSX_LEAD_INDEX'||result.data?.type!=='DIY_HSX_LEAD_INDEX'||result.data?.template!=='hsx_lead_index'||diyPageSource(result.data?.value)!==pending.value)throw Error('当前内容不是本次蓝白预设，请打开装修器或备份核对，不要直接重复覆盖');readRequired.value=false;pending=null;error.value='';progress.value='已核对蓝白首页保存成功，备份页保留'}catch(cause:any){error.value=cause?.message||'读取失败，请稍后核对'}finally{applying.value=false}}
</script>
<style scoped>
.lead-home-decoration{display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;padding:28px}.lead-home-decoration h3{font-size:20px;margin:14px 0 10px;color:var(--el-text-color-primary)}.lead-home-decoration p{color:var(--el-text-color-secondary);line-height:1.7}.lead-home-help{margin-top:18px}.lead-home-help p+p{margin-top:12px}.lead-home-actions{display:flex;gap:10px;flex-wrap:wrap}.lead-home-status{margin-top:14px}.lead-home-backup{margin-top:14px}.lead-home-backup p{font-size:13px;color:var(--el-text-color-secondary);line-height:1.8}
</style>
