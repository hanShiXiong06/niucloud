<template>
    <section><b>{{title}}</b>
        <el-button v-if="expandable" link type="primary" :loading="loading" :aria-expanded="expanded" @click="toggle">{{expanded?'收起明细':'展开更多业务字段'}}</el-button>
        <span v-if="error" class="failure" role="alert">{{error}}</span>
        <pre>{{value==null?'没有可展示的报文':JSON.stringify(value,null,2)}}</pre>
    </section>
</template>
<script setup lang="ts">
import {computed,ref,onBeforeUnmount} from 'vue'
import request from '@/utils/request'
import {reason} from '../utils/connection'
const props=defineProps<{title:string;brief?:any;expandable?:boolean;callId:string;day:string;side:'request'|'response'}>()
const expanded=ref(false),loading=ref(false),full=ref<any>(null),error=ref('')
const value=computed(()=>expanded.value?full.value:props.brief)
let live=true
async function toggle(){
    if(expanded.value){expanded.value=false;return}
    if(full.value!==null){expanded.value=true;return}
    if(loading.value)return
    error.value='';loading.value=true
    try{const r:any=await request.get('boyuan/logs',{params:{view:'detail',day:props.day,call_id:props.callId,side:props.side}})
        if(!live)return
        if(r?.data?.payload==null || typeof r.data.payload!=='object')throw Error('没有可读的明细，请使用预览。')
        full.value=r.data.payload;expanded.value=true
    }catch(e){if(live)error.value=reason(e,'报文暂时读不到，可重试。')}
    finally{if(live)loading.value=false}
}
onBeforeUnmount(()=>{live=false})
</script>
<style scoped>
section{min-width:0}.failure{display:block;color:var(--el-color-danger)}pre{margin:6px 0;max-height:260px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere;background:var(--el-fill-color-light);padding:10px}
</style>
