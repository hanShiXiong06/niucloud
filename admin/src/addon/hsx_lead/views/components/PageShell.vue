<template>
    <HsxPage content-width="wide" padding="compact" class="lead-workbench coop-space">
        <el-alert v-if="!canView" title="当前账号没有此页面权限" type="warning" :closable="false" show-icon />
        <el-skeleton v-else-if="scopeLoading" :rows="6" animated />
        <el-alert v-else-if="scopeError" :title="scopeError" type="error" :closable="false" show-icon><template #default><el-button link type="primary" @click="loadScope">重新读取</el-button></template></el-alert>
        <el-alert v-else-if="requireAllData && scope.all_data !== true" title="协作收款仅对具有全站业务权限的财务开放" type="warning" :closable="false" show-icon />
        <slot v-else :scope="scope" />
    </HsxPage>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import HsxPage from '@/addon/hsx_components/components/HsxPage/index.vue'
import useUserStore from '@/stores/modules/user'
import { getCooperationScope } from '../../api/cooperation'
import { coopError, type CoopRow } from '../../composables/cooperation'
import { hasLeadPagePermission } from '../../composables/navigation'
import '@/addon/hsx_components/styles/theme.scss'
import './lead.css'
import '../cooperation/cooperation.css'
const props=withDefaults(defineProps<{permission:string;scopeSource?:'cooperation';requireAllData?:boolean}>(),{requireAllData:false})
const user=useUserStore(),canView=computed(()=>hasLeadPagePermission(user,props.permission))
const scope=ref<CoopRow>({all_data:false,can_transfer:false}),scopeLoading=ref(false),scopeError=ref('')
let generation=0,disposed=false
async function loadScope(){
    const token=++generation;scopeError.value='';scope.value={all_data:false,can_transfer:false}
    if(!canView.value||!props.scopeSource){scopeLoading.value=false;return}
    scopeLoading.value=true
    try{
        const result=await getCooperationScope()
        if(disposed||token!==generation)return
        if(typeof result.data?.all_data!=='boolean')throw new Error('未返回有效的数据范围，请重新读取')
        scope.value=result.data
    }catch(cause){if(!disposed&&token===generation)scopeError.value=coopError(cause,'数据范围读取失败，请重新读取')}
    finally{if(!disposed&&token===generation)scopeLoading.value=false}
}
watch(()=>[canView.value,props.scopeSource],loadScope,{immediate:true})
onBeforeUnmount(()=>{disposed=true;generation++})
</script>
