<template>
    <details class="delivery" @toggle="toggle">
        <summary>手动同步设置 <small>管理员 · 测试账套</small></summary>
        <p v-if="loading" role="status">正在读取当前账套…</p>
        <el-alert v-if="error" :title="error" type="warning" :closable="false" show-icon />
        <div v-if="state && !loading" class="body">
            <p><strong>测试账套 {{ state.account_id || '未核验' }}</strong> · {{ active ? '手动投递中' : state.paused ? '已暂停' : '未开启手动投递' }}</p>
            <details class="muted"><summary>查看开关明细</summary><p>业务同步：{{ state.enabled ? '开' : '关' }}　采购入库：{{ state.purchase_enabled ? '开' : '关' }}　暂停：{{ state.paused ? '是' : '否' }}　方式：{{ state.purchase_mode === 'manual' ? '人工确认' : '自动' }}</p></details>
            <p v-if="!state.eligible" class="muted">{{ state.reason }}</p>
            <template v-else>
                <p class="muted">开启后，在采购或销售原单核对后提交。暂停只停发单，仍可查旧单；不会改动仓库归属或财务设置。恢复后，已确认排队的任务可继续执行。</p>
                <div v-if="!active" class="actions">
                    <el-input v-model="confirmAccount" placeholder="手动输入上方账套编号" aria-label="确认账套编号" autocomplete="off" maxlength="128" :disabled="busy" />
                    <el-button type="primary" :loading="busy" :disabled="!canEnable" @click="change('enable')">开启手动投递</el-button>
                </div>
                <el-button v-if="canPause" :disabled="busy" @click="change('pause')">暂停投递</el-button>
            </template>
        </div>
        <el-button v-if="!loading" link :disabled="busy" @click="load">刷新设置</el-button>
    </details>
</template>
<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { readManualDelivery, setManualDelivery } from '../api/opening-archives'
const props=defineProps<{connectionKey:string}>()
const state=ref<any>(null), loading=ref(false), busy=ref(false), error=ref(''), confirmAccount=ref('')
let seq=0
const active=computed(()=>!!state.value && state.value.enabled && state.value.purchase_enabled && !state.value.paused && state.value.purchase_mode==='manual')
const canEnable=computed(()=>state.value?.eligible===true && !active.value && !busy.value && !loading.value && !!confirmAccount.value && confirmAccount.value===state.value.account_id)
const canPause=computed(()=>state.value?.eligible===true && (state.value.enabled || state.value.purchase_enabled || !state.value.paused))
const problem=(e:any)=>e?.msg || e?.message || '请求未确认，请刷新设置查看结果。'
function accept(v:any) {
    if(!v || !Number.isInteger(v.revision) || (v.eligible && (!v.connection_key || v.connection_key!==props.connectionKey))) throw Error('账套已变更，请刷新页面后核对。')
    state.value=v
}
async function load() {
    if (busy.value) return
    const n=++seq; state.value=null; confirmAccount.value=''; error.value=''; loading.value=true
    try { const r=await readManualDelivery(); if(n===seq) accept(r.data) }
    catch(e){ if(n===seq) error.value=problem(e) }
    finally {if(n===seq) loading.value=false}
}
function toggle(e:Event) { if((e.target as HTMLDetailsElement).open) load() }
async function change(intent:'enable'|'pause') {
    if(busy.value || loading.value || !(intent==='enable'?canEnable.value:canPause.value)) return
    const n=++seq, old=state.value, account=confirmAccount.value; busy.value=true
    try {
        if(intent==='pause') {
            try { await ElMessageBox.confirm('暂停后不再投递，历史单据和同步结果仍可查看。','暂停本站投递',{confirmButtonText:'确认暂停',cancelButtonText:'返回'}) }
            catch {return}
        }
        if(n!==seq) return
        const r=await setManualDelivery({intent,revision:old.revision,connection_key:old.connection_key,confirm_account:intent==='enable'?account:''})
        if(n!==seq) return
        accept(r.data); confirmAccount.value=''; error.value=''
        if(intent==='enable' ? !active.value : state.value.enabled || state.value.purchase_enabled || !state.value.paused) throw Error('返回的开关状态与操作不一致，请刷新核对。')
        ElMessage.success(intent==='enable'?'手动投递已开启':'投递已暂停')
    } catch(e) { if(n===seq){state.value=null; confirmAccount.value=''; error.value=problem(e)} }
    finally { if(n===seq) busy.value=false }
}
onBeforeUnmount(()=>{seq++})
</script>
<style scoped>
.delivery{margin-top:16px;padding:14px 18px;border:1px solid var(--el-border-color-light);border-radius:8px;background:var(--el-bg-color)}
summary{cursor:pointer;font-weight:600}small,.muted{color:var(--el-text-color-secondary);font-size:13px}small{margin-left:8px;font-weight:400}
.body p{margin:12px 0;line-height:1.6}.actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.actions .el-input{max-width:220px}.delivery .el-alert{margin:12px 0}
</style>
