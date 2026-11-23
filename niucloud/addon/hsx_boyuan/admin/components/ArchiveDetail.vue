<template>
    <HsxDrawer :model-value="true" size="md" :title="record.name" :subtitle="categoryName+' · 档案 '+record.id" :close-on-click-modal="true" @update:model-value="v=>{if(!v)emit('close')}">
        <div v-if="loading" class="message" role="status">正在读取这条档案的字段…</div>
        <el-alert v-else-if="error" :title="error" type="error" :closable="false" show-icon />
        <template v-else-if="detail">
            <div v-if="resource==='categories' && record.categoryPath" class="category-path"><small>分类位置</small><strong>{{ record.categoryPath }}</strong></div>
            <div class="origin">{{ origin }}<span> · {{ fields.length }} 个字段</span></div>
            <el-alert v-if="detail.notice" :title="detail.notice" type="info" :closable="false" show-icon><el-button v-if="detail.can_check_source" link type="primary" @click="load(true)">向博远核对完整字段</el-button></el-alert>
            <div class="tools"><el-input v-model="query" clearable aria-label="查找字段" placeholder="按名称或字段查找，如「电话」" />
                <el-checkbox v-model="keys">显示接口字段名</el-checkbox><el-checkbox v-model="hideEmpty">隐藏空值</el-checkbox></div>
            <div class="scroller">
              <dl v-if="visible.length" class="fields">
                <div v-for="f in visible" :key="f.key" class="field"><dt>{{ f.label }}<small v-if="keys && f.known">{{ f.key }}</small><small v-else-if="!f.known">博远扩展字段</small></dt>
                  <dd :class="{empty:f.empty}"><PartyPhone v-if="resource==='parties' && record.phone_field==='help_code' && f.key==='help_code'" :value="detail.fields.help_code" :needs-sync="detail.phone_needs_sync" /><template v-else>{{ f.display }}</template></dd></div>
              </dl><el-empty v-else :description="fields.length ? '没有符合筛选条件的字段' : '没有可展示的字段'" :image-size="54" />
            </div>
        </template>
        <template #footer><el-button v-if="error" @click="load()">重新读取</el-button><el-button @click="emit('close')">关闭</el-button></template>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import PartyPhone from './PartyPhone.vue'
import { readArchiveDetail } from '../api/opening-archives'
import { fieldRows } from '../utils/archive-fields'
const props=defineProps<{record:{id:string,name:string,phone_field?:string,categoryPath?:string},resource:string,categoryName:string,connectionKey:string}>()
const emit=defineEmits(['close'])
const loading=ref(true),error=ref(''),detail=ref<any>(null),query=ref(''),keys=ref(false),hideEmpty=ref(false)
const fields=computed(()=>fieldRows(props.resource,detail.value?.fields||{},props.record.phone_field||''))
const visible=computed(()=>{const q=query.value.trim().toLowerCase();return fields.value.filter(f=>(!hideEmpty.value || !f.empty) && (!q || f.label.toLowerCase().includes(q) || f.key.toLowerCase().includes(q)))})
const origin=computed(()=>detail.value?.source==='snapshot'?'来源：同步时保存的博远数据':detail.value?.source==='boyuan'?'来源：博远当前返回（本次只读核对）':'来源：本站已保存摘要')
let alive=true
async function load(checkSource=false){
    loading.value=true;error.value='';detail.value=null
    try{const x=(await readArchiveDetail(props.resource,props.record.id,props.connectionKey,checkSource))?.data
        if(!alive) return
        if(!x || x.id!==props.record.id || x.resource!==props.resource || x.connection_key!==props.connectionKey || !['snapshot','boyuan','summary'].includes(x.source) || !x.fields || typeof x.fields!=='object' || Array.isArray(x.fields))throw Error('返回的明细不属于当前档案，请关闭后重新选择。')
        detail.value=x
    }catch(e:any){if(alive)error.value=e?.msg || e?.message || '读取失败，请重试。'}finally{if(alive)loading.value=false}
}
onMounted(()=>load());onBeforeUnmount(()=>{alive=false;detail.value=null})
</script>
<style scoped>
.category-path{padding:10px 14px;background:var(--el-fill-color-light);border-radius:6px}.category-path small,.category-path strong{display:block}.category-path small{color:var(--el-text-color-secondary);margin-bottom:3px}.category-path strong{font-weight:500;overflow-wrap:anywhere}
.head,.tools{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.head{justify-content:space-between;margin-bottom:14px}.head h3{font-size:18px;margin:0}.head p,.origin{font-size:13px;color:var(--el-text-color-regular);margin:5px 0}.origin{margin:12px 0}.tools{gap:6px 16px;margin:12px 0}.tools .el-input{width:245px;max-width:100%}.tools .el-checkbox{margin:0}.scroller{min-width:0}.raw{padding:6px 10px;margin-top:4px;background:var(--el-fill-color-light);border-radius:4px;user-select:all}.fields{margin:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 24px}.field{padding:10px 0;border-top:1px solid var(--el-border-color-lighter);min-width:0}.field dt{font-size:13px;color:var(--el-text-color-regular)}.field small{display:block;color:var(--el-text-color-secondary);overflow-wrap:anywhere}.field dd{margin:3px 0 0;color:var(--el-text-color-primary);white-space:pre-wrap;overflow-wrap:anywhere;user-select:text}.field dd.empty,.message{color:var(--el-text-color-secondary)}.message{padding:20px 0}@media(max-width:600px){.fields{grid-template-columns:1fr}}
</style>
