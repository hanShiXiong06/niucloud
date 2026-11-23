<template>
    <el-button v-for="item in visible" :key="item.name" :icon="Connection" @click="open(item)">{{ item.label }}</el-button>
    <el-drawer v-if="active" :model-value="true" :title="active.label" size="min(860px, 94vw)" append-to-body @close="close">
        <el-alert v-if="loadError" title="无法加载操作列表" type="error" :closable="false">
            <el-button link type="primary" @click="open(active)">重试</el-button>
        </el-alert>
        <component v-else-if="content" :is="content" v-bind="active.panel?.props" />
        <el-skeleton v-else :rows="5" animated />
    </el-drawer>
</template>
<script setup lang="ts">
import { computed, shallowRef, ref, type Component } from 'vue'
import { useRouter } from 'vue-router'
import { Connection } from '@element-plus/icons-vue'
type Desk = { name: string; label: string; query?: Record<string, string>; panel?: { load: () => Promise<{default:Component}>; props?: Record<string,string> } }
type Entry = { desk?: Desk }
const props = defineProps<{workbench?:boolean}>()
const opening = Object.values(import.meta.glob<Entry>('/src/addon/*/extensions/erp-opening.ts', { eager:true, import:'default' }))
const workbench = Object.values(import.meta.glob<Entry>('/src/addon/*/extensions/erp-workbench.ts', { eager:true, import:'default' }))
const router = useRouter()
const visible = computed(() => (props.workbench ? [...opening,...workbench] : opening).map(e=>e?.desk).filter((d): d is Desk =>
    !!d && typeof d.name==='string' && typeof d.label==='string' && !!d.label.trim() && router.hasRoute(d.name))
    .filter((d,i,items)=>items.findIndex(e=>e.name===d.name)===i))
const active=shallowRef<Desk|null>(null),content=shallowRef<Component|null>(null),loadError=ref(false)
let revision=0
function close(){revision++;active.value=null;content.value=null}
async function open(item:Desk|null) {
    if(!item || !visible.value.includes(item) || !router.hasRoute(item.name))return
    if(!item.panel){void router.push({name:item.name,query:item.query});return}
    const id=++revision;active.value=item;content.value=null;loadError.value=false
    try {const view=await item.panel.load();if(revision===id)content.value=view.default}
    catch {if(revision===id)loadError.value=true}
}
</script>
