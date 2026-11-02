<template>
    <section class="coop-space">
        <nav class="coop-subnav" aria-label="商户协作管理"><el-button v-for="item in navigation" :key="item.key" :type="tab===item.key?'primary':'default'" :plain="tab!==item.key" :aria-current="tab===item.key?'page':undefined" @click="tab=item.key">{{ item.label }}</el-button></nav>
        <Overview v-if="tab==='overview'" :can-finance="canFinance" :can-review="allows('review')" :can-config="allows('config')" @navigate="navigate" @scope="resolvedScope=$event" />
        <Leads v-else-if="tab==='leads'" :viewer-scope="resolvedScope" :can-review="allows('review')" @review="openReview" />
        <Partners v-else-if="tab==='partners'&&allows('partners')" />
        <Reviews v-else-if="tab==='reviews'&&allows('review')" :initial-lead-id="reviewLeadId" />
        <Finance v-else-if="tab==='finance'&&canFinance" />
        <Settings v-else-if="tab==='settings'&&allows('config')" />
        <el-result v-else icon="warning" title="当前账号没有此项权限" sub-title="请联系管理员授予对应的协作业务权限；不会读取或展示受限资金明细。"><template #extra><el-button @click="tab='overview'">返回协作概况</el-button></template></el-result>
    </section>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Overview from './Overview.vue'
import Leads from './Leads.vue'
import Partners from './Partners.vue'
import Reviews from './Reviews.vue'
import Finance from './Finance.vue'
import Settings from './Settings.vue'
import { useCooperationAccess, type CoopRow } from '../../composables/cooperation'
import './cooperation.css'
const props=withDefaults(defineProps<{viewerScope?:CoopRow}>(),{viewerScope:()=>({})})
const tab=ref('overview'),reviewLeadId=ref(0),resolvedScope=ref<CoopRow>({...props.viewerScope}),{allows}=useCooperationAccess()
const canFinance=computed(()=>allows('finance')&&resolvedScope.value.all_data===true)
const navigation=computed(()=>[{key:'overview',label:'协作概况'},{key:'leads',label:'协作线索'},...(allows('partners')?[{key:'partners',label:'等级与认证'}]:[]),...(allows('review')?[{key:'reviews',label:'客服抽检'}]:[]),...(canFinance.value?[{key:'finance',label:'付佣与提现'}]:[]),...(allows('config')?[{key:'settings',label:'业务配置'}]:[])])
function navigate(value:string){tab.value=value}
function openReview(id:number){reviewLeadId.value=id;tab.value='reviews'}
watch(()=>props.viewerScope,value=>{if(typeof value.all_data==='boolean')resolvedScope.value={...value}})
</script>
