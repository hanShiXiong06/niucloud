<template>
    <DeskShell title="业务往来" subtitle="核对本站采购、付款凭证和博远回执。这里仅供查询，不会重新发单或打款。">
        <section v-if="loading || error || !views.length" class="desk-card">
            <el-skeleton v-if="loading" :rows="3" animated aria-label="读取查看权限" />
            <template v-else-if="error"><el-alert type="error" :closable="false" :title="error" /><el-button class="desk-retry" @click="read">重新读取</el-button></template>
            <el-empty v-else :description="available ? '没有单据或接口往来的查看权限，请联系站点管理员。' : '本站没有可查看的博远连接记录。'" />
        </section>
        <ExchangeCenter v-else standalone :views="views" :initial="keyword" :key="keyword" />
    </DeskShell>
</template>
<script setup lang="ts">
import {computed,onMounted,onBeforeUnmount,onActivated,onDeactivated,ref} from 'vue'
import {useRoute} from 'vue-router'
import request from '@/utils/request'
import DeskShell from '../components/DeskShell.vue'
import ExchangeCenter from '../components/ExchangeCenter.vue'
import {reason} from '../utils/connection'
const route=useRoute()
const keyword=computed(()=>String(route.query.purchase_no || route.query.keyword || '').slice(0,128))
const views=ref<string[]>([]),loading=ref(true),available=ref(false),error=ref('')
let seq=0, inactive=false
async function read(){
    const id=++seq;loading.value=true;error.value='';views.value=[]
    try {
        const res:any=await request.get('boyuan/logs',{params:{view:'access'}})
        if(id!==seq)return
        if(typeof res?.data?.available!=='boolean'||!Array.isArray(res.data.views))throw Error('未能读取查看权限，请核对后端版本。')
        available.value=res.data.available
        views.value=available.value?['purchases','payments','documents','exchanges'].filter(v=>res.data.views.includes(v)):[]
    }catch(e){if(id===seq)error.value=reason(e,'无法读取业务往来，请重试。')}
    finally{if(id===seq)loading.value=false}
}
onMounted(read);onDeactivated(()=>{inactive=true;seq++});onActivated(()=>{if(inactive){inactive=false;void read()}});onBeforeUnmount(()=>{seq++})
</script>
