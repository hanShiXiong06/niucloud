<template>
    <section class="desk-card trace-card" aria-label="接口调试">
        <header><h2>接口调试</h2><HsxTag :tone="active?'success':'neutral'" dot>{{ active?'记录中':'未开启' }}</HsxTag></header>
        <p>{{ active ? '详细调试记录到 '+until+'，届时自动关闭。' : '日常请求和返回在「对接工作台 → 单据与接口往来」查看。遇到异常，可临时扩大报文样本并记录本站处理步骤。' }}</p>
        <el-button v-if="canEdit" :disabled="disabled" :loading="busy" @click="toggle">{{active?'停止记录':'开启 15 分钟'}}</el-button>
        <p v-if="hint" class="desk-caption">{{hint}}</p>
        <details v-if="command" :open="active || undefined">
            <summary>如何查看这次日志</summary>
            <p>先重现问题，或点「核验已保存的账套」做只读检查。在服务器项目的 niucloud 目录执行：</p>
            <code tabindex="0">{{command}}</code>
            <el-button link type="primary" @click="copy">{{copyLabel}}</el-button>
            <p class="desk-caption">同一 call_id 的 request 是入参，result 是返回。文件含脱敏样本；不需要打开全站调试。</p>
        </details>
    </section>
</template>
<script setup lang="ts">
import {computed,onBeforeUnmount,ref,watch} from 'vue'
import HsxTag from '@/addon/hsx_components/components/HsxTag/index.vue'
import type {ConnectionState} from '../api/connection'
const props=defineProps<{status:NonNullable<ConnectionState['interface_trace']>;canEdit:boolean;disabled:boolean;busy:boolean;hint:string}>()
const emit=defineEmits<{(e:'toggle',intent:'start'|'stop',windowId:string):void}>()
const active=ref(false),copyLabel=ref('复制查看命令')
let timer:ReturnType<typeof setTimeout>|undefined
watch(()=>props.status,s=>{clearTimeout(timer);const ms=(s.expires_at-s.server_time)*1000
    active.value=!!s.window_id && ms>0;copyLabel.value='复制查看命令'
    if(active.value)timer=setTimeout(()=>{active.value=false},ms)
},{immediate:true})
onBeforeUnmount(()=>clearTimeout(timer))
const until=computed(()=>new Date(props.status.expires_at*1000).toLocaleTimeString('zh-CN',{hour12:false}))
const command=computed(()=>/^BW[a-f0-9]{16}$/.test(props.status.last_window_id) ? "find runtime -type f -name '*.log' -mtime -2 -exec grep -H -F -- '"+props.status.last_window_id+"' {} +" : '')
function toggle(){if(!props.disabled && props.canEdit)emit('toggle',active.value?'stop':'start',active.value?props.status.window_id:'')}
async function copy(){try{await navigator.clipboard.writeText(command.value);copyLabel.value='已复制'}catch{copyLabel.value='请选中上方命令复制'}}
</script>
<style scoped>
.trace-card header{display:flex;align-items:center;justify-content:space-between;gap:8px}.trace-card p{font-size:13px;line-height:1.6;margin:10px 0}.trace-card details{margin-top:12px}.trace-card summary{cursor:pointer;font-weight:550;font-size:13px}.trace-card code{display:block;padding:9px;border-radius:4px;background:var(--el-fill-color-light);font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere;user-select:all}
</style>
