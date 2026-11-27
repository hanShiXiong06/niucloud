<template>
    <section>
        <header class="coop-head"
            ><h2>工作台</h2><el-button :icon="Refresh" :loading="loading" @click="load">刷新</el-button></header
        >
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <el-alert
            v-if="setupMessage"
            :title="setupMessage"
            type="warning"
            :closable="false"
            show-icon
            class="coop-error"
            ><template #default
                ><el-button link type="primary" @click="navigate('settings')">配置业务规则</el-button></template
            ></el-alert
        >
        <div class="coop-facts">
            <HsxStatCard
                title="协作线索"
                :value="value('leads_total','total')"
                :loading="loading"
                description="当前可见范围"
            />
            <HsxStatCard title="等待接单" :value="value('assigned')" :loading="loading" />
            <HsxStatCard title="跟进中" :value="value('following')" :loading="loading" />
            <HsxStatCard title="成交待付佣" :value="value('unpaid')" :loading="loading" />
        </div>
        <div v-if="canLeads || canReview" class="coop-surface">
            <div class="coop-section-caption"><h3>待办处理</h3></div>
            <div class="coop-overview-todos">
                <button v-if="canLeads" type="button" @click="navigate('leads',{status:'assigned'})"
                    ><b>跟进交付</b><span>{{ value('assigned') }} 条待接单</span></button
                >
                <button v-if="canLeads" type="button" @click="navigate('leads',{status:'unpaid'})"
                    ><b>成交待付佣</b><span>{{ value('unpaid') }} 条</span></button
                >
                <button v-if="canReview" type="button" @click="navigate('reviews')"
                    ><b>客服待复核</b><span>{{ value('pending_reviews') }} 条</span></button
                >
            </div>
        </div>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter, type LocationQueryRaw } from 'vue-router'
import { Refresh } from '@element-plus/icons-vue'
import HsxStatCard from '@/addon/hsx_components/components/HsxStatCard/index.vue'
import { getCooperationConfig, getCooperationDashboard } from '../../api/cooperation'
import { coopError, type CoopRow } from '../../composables/cooperation'
import { leadPageLocation } from '../../composables/navigation'
const props = withDefaults(defineProps<{ canReview?: boolean; canConfig?: boolean; canLeads?: boolean }>(), { canReview: false, canConfig: false, canLeads: false })
const router=useRouter()
const dashboard=ref<CoopRow>({}),setup=ref<CoopRow|null>(null),setupError=ref(''),loading=ref(false),error=ref('')
let sequence=0,disposed=false
const setupMessage=computed(()=>{
    if(!props.canConfig)return''
    if(setupError.value)return'业务配置状态暂未读取，请重新核对。'
    if(!setup.value)return''
    if(!setup.value.enabled)return'商户协作未启用，请完成业务配置。'
    if(!setup.value.fee_confirmed)return'平台费规则未确认，暂不能创建佣金支付单。'
    return''
})
function navigate(page:'leads'|'reviews'|'settings',query:LocationQueryRaw={}){void router.push(leadPageLocation(page,query))}
async function loadSetup(token:number){
    if(!props.canConfig)return
    setupError.value=''
    try{const result=await getCooperationConfig();if(token===sequence&&!disposed)setup.value=result.data||{}}
    catch(cause){if(token===sequence&&!disposed)setupError.value=coopError(cause)}
}
function value(key:string,alternate=''){
    const data=dashboard.value,raw=data[key]??data.counts?.[key]??(alternate?data[alternate]??data.counts?.[alternate]:undefined)
    return raw===undefined||raw===null?'—':Number(raw)
}
async function load(){
    const token=++sequence;loading.value=true;error.value='';void loadSetup(token)
    try{const result=await getCooperationDashboard();if(token===sequence&&!disposed)dashboard.value=result.data||{}}
    catch(cause){if(token===sequence&&!disposed){dashboard.value={};error.value=coopError(cause,'概况加载失败，请重试。')}}
    finally{if(token===sequence&&!disposed)loading.value=false}
}
onMounted(load);onBeforeUnmount(()=>{disposed=true;sequence++})
</script>
<style scoped>
.coop-overview-todos{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.coop-overview-todos button{border:1px solid var(--el-border-color-lighter);background:var(--hsx-bg-surface,var(--el-bg-color));padding:15px;border-radius:6px;text-align:left;cursor:pointer;color:var(--el-text-color-primary)}
.coop-overview-todos b{display:block;font-size:14px;margin-bottom:7px}
.coop-overview-todos span{color:var(--el-text-color-secondary);font-size:12px}
.coop-overview-todos button:hover{border-color:var(--el-color-primary-light-5)}
.coop-overview-todos button:focus-visible{outline:2px solid var(--el-color-primary);outline-offset:2px}
@media(max-width:1000px){.coop-overview-todos{grid-template-columns:1fr}}
</style>
