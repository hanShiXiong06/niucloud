<template>
    <div class="account-entry">
        <div><strong>收付款资金账户</strong><p>{{ lastResult || (ready ? '只选门店会用的账户。采购、销售仅挂账时可跳过。' : '如需收付款，先在上方「其他资料」取回资金账户。') }}</p></div>
        <el-button plain :disabled="!ready" @click="open">选择资金账户</el-button>
        <HsxDialog v-model="visible" title="选择收付款资金账户" subtitle="只在本站 ERP 建立对应，不向博远发起收付款。" size="lg" show-footer
            :confirm-loading="phase === 'writing'" :close-on-press-escape="phase !== 'writing'">
            <el-alert v-if="error" :title="error" type="warning" :closable="false" show-icon />
            <p v-if="phase === 'loading'" role="status">正在读取本账套可用账户…</p>
            <template v-else-if="phase === 'edit' || phase === 'checking'">
                <label class="field-label">要使用的博远账户</label>
                <el-select v-model="selected" multiple filterable clearable collapse-tags collapse-tags-tooltip :multiple-limit="50" class="full"
                    :disabled="phase !== 'edit'" placeholder="搜索并选择账户，可多选" @change="setDefaults">
                    <el-option v-for="s in sources" :key="s.id" :value="s.id" :disabled="s.linked" :label="sourceLabel(s)+(s.linked?'（已有对应）':'')" />
                </el-select>
                <p v-if="!sources.length">本轮没有启用的博远资金账户。</p>
                <p v-else-if="sources.every(s=>s.linked)">这些账户都已有对应，无需重复建档。</p>
                <el-table v-if="selected.length" :data="selectedSources" row-key="id" class="choices">
                    <el-table-column label="博远账户" min-width="150"><template #default="{row}">{{ row.name }}<small>{{ row.company }}</small></template></el-table-column>
                    <el-table-column label="本站 ERP 账户" min-width="180"><template #default="{row}">
                        <el-select v-model="draft[row.id].local_id" filterable class="full" :disabled="phase !== 'edit'" placeholder="关联已有账户，或新建">
                            <el-option label="新建同名账户（余额 0）" :value="0" />
                            <el-option v-for="a in locals" :key="a.id" :label="a.name+' · '+(types[a.type] || a.type)+' (#'+a.id+')'" :value="a.id"
                                :disabled="selected.some(id=>id!==row.id && draft[id]?.local_id===a.id)" />
                        </el-select>
                    </template></el-table-column>
                    <el-table-column label="本站类型" min-width="115"><template #default="{row}">
                        <el-select v-if="draft[row.id].local_id === 0" v-model="draft[row.id].type" class="full" placeholder="选账户类型" :disabled="phase !== 'edit'">
                            <el-option v-for="(name,k) in types" :key="k" :label="name" :value="k" />
                        </el-select>
                        <span v-else>{{ types[locals.find(a=>a.id===draft[row.id].local_id)?.type || ''] || '—' }}</span>
                    </template></el-table-column>
                </el-table>
            </template>
            <template v-else-if="phase === 'preview' || phase === 'writing'">
                <strong>将新建 {{ newCount }} 个，关联已有 {{ plan.length - newCount }} 个。</strong>
                <p>新账户余额为 0；已有账户的名称、类型、余额和默认设置都保持不变。</p>
                <el-table :data="plan" row-key="id">
                    <el-table-column prop="name" label="博远账户" min-width="140" />
                    <el-table-column label="本站处理" min-width="210"><template #default="{row}">{{ row.action==='create'?'新建':'关联' }}：{{ row.local_name }} · {{ types[row.type] || row.type }}</template></el-table-column>
                </el-table>
                <small v-if="!canApply">当前账号只能预览，请有账户管理权限的管理员确认。</small>
            </template>
            <template #footer>
                <el-button :disabled="phase === 'writing'" @click="visible=false">关闭</el-button>
                <el-button v-if="phase==='preview'" @click="editAgain">返回修改</el-button>
                <el-button v-if="phase==='edit' || phase==='checking'" type="primary" :loading="phase==='checking'" :disabled="!complete" @click="preview">预览所选账户</el-button>
                <el-button v-if="(phase==='preview' || phase==='writing') && canApply" type="primary" :loading="phase==='writing'" @click="save">确认使用这 {{ plan.length }} 个账户</el-button>
                <el-button v-if="phase==='reload'" type="primary" @click="open">重新读取账户</el-button>
            </template>
        </HsxDialog>
    </div>
</template>
<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import HsxDialog from '@/addon/hsx_components/components/HsxDialog/index.vue'
import { readAccountOptions, previewAccounts, applyAccounts, type AccountChoice } from '../api/opening-post'

type Source = {id:number;name:string;company:string;linked:boolean;suggested_type:string}
type Local = {id:number;name:string;type:string}
type Planned = {id:number;name:string;local_id:number;local_name:string;type:string;action:'create'|'link'}
const props=defineProps<{connectionKey:string;ready:boolean;canApply:boolean}>()
const visible=ref(false), lastResult=ref(''), error=ref(''), phase=ref<'loading'|'edit'|'checking'|'preview'|'writing'|'reload'>('loading')
const sources=ref<Source[]>([]), locals=ref<Local[]>([]), types=ref<Record<string,string>>({})
const selected=ref<number[]>([]), draft=ref<Record<number,{local_id?:number;type:string}>>({})
const selectedSources=computed(()=>sources.value.filter(s=>selected.value.includes(s.id)))
const sourceLabel=(s:Source)=>s.company?s.name+' · '+s.company:s.name
const choices=computed<AccountChoice[]>(()=>selectedSources.value.map(s=>({id:s.id,local_id:draft.value[s.id]?.local_id??-1,type:draft.value[s.id]?.local_id===0?draft.value[s.id].type:''})))
const complete=computed(()=>choices.value.length>0 && choices.value.every(c=>c.local_id>0 || (c.local_id===0 && !!types.value[c.type])))
const plan=ref<Planned[]>([]), token=ref(''), frozen=ref<AccountChoice[]>([])
const newCount=computed(()=>plan.value.filter(r=>r.action==='create').length)
let serial=0
const current=(id:number)=>id===serial && visible.value && props.ready
const message=(e:any,fallback:string)=>e?.msg || e?.message || fallback
function editAgain(){token.value='';frozen.value=[];plan.value=[];error.value='';phase.value='edit'}
function setDefaults(ids:number[]) {
    for(const id of ids) if(!draft.value[id]) draft.value[id]={local_id:locals.value.length?undefined:0,type:sources.value.find(s=>s.id===id)?.suggested_type || ''}
}
async function open(){
    if(!props.ready || phase.value==='writing')return
    const id=++serial,key=props.connectionKey;visible.value=true;editAgain();phase.value='loading';selected.value=[];draft.value={};sources.value=[];locals.value=[];types.value={}
    try {
        let cursor=0,run='';const all:Source[]=[],seen=new Set<number>()
        do {
            const r=(await readAccountOptions(key,cursor)).data
            if(!current(id))return
            if(!r || !Array.isArray(r.rows) || typeof r.run!=='string' || !r.run || (run && run!==r.run) || !Number.isSafeInteger(r.next_cursor) || (r.next_cursor!==0 && r.next_cursor<=cursor))
                throw Error('档案版本或页码已变化，请重新读取。')
            run=r.run
            if(!cursor) {
                if(!Array.isArray(r.locals) || !r.types || typeof r.types!=='object')throw Error('未取得本站账户选项。')
                locals.value=r.locals;types.value=r.types
            }
            for(const row of r.rows){if(!Number.isSafeInteger(row?.id)||row.id<=0||seen.has(row.id)||typeof row.name!=='string'||typeof row.linked!=='boolean')throw Error('账户清单返回不完整。');seen.add(row.id);all.push(row)}
            cursor=r.next_cursor
        } while(cursor)
        sources.value=all;phase.value='edit'
    } catch(e){if(current(id)){error.value=message(e,'未能读取账户。');phase.value='reload'}}
}
function validPlan(rows:any,items:AccountChoice[]): rows is Planned[] {
    return Array.isArray(rows) && rows.length===items.length && rows.every((r,i)=>r?.id===items[i].id && r.local_id===items[i].local_id && r.action===(r.local_id?'link':'create') && typeof r.name==='string' && typeof r.local_name==='string' && typeof r.type==='string' && (r.local_id || r.type===items[i].type))
}
async function preview(){
    if(phase.value!=='edit'||!complete.value)return
    const id=++serial,items=choices.value.map(x=>({...x}));error.value='';phase.value='checking'
    try {
        const p=(await previewAccounts(props.connectionKey,items)).data
        if(!current(id))return
        if(!validPlan(p?.rows,items) || !p.token)throw Error('没有取得有效预览，未写入。')
        frozen.value=items;plan.value=p.rows;token.value=p.token;phase.value='preview'
    }catch(e){if(current(id)){error.value=message(e,'预览失败，未写入。');phase.value='edit'}}
}
async function save(){
    if(phase.value!=='preview'||!props.canApply||!token.value)return
    const id=++serial;phase.value='writing'
    try {
        const r=(await applyAccounts(props.connectionKey,frozen.value,token.value)).data
        if(!current(id))return
        if(!validPlan(r?.rows,frozen.value) || r.rows.some((x:Planned,i:number)=>x.name!==plan.value[i].name || x.local_name!==plan.value[i].local_name || x.type!==plan.value[i].type))throw Error('未取得与预览一致的回执。')
        lastResult.value='已新建 '+newCount.value+' 个，关联已有 '+(plan.value.length-newCount.value)+' 个；未动余额。'
        phase.value='edit';visible.value=false
    }catch(e){if(current(id)){error.value=message(e,'未收到完整回执。')+' 请重新读取账户对应，不要重复提交。';phase.value='reload'}}
    finally{if(id===serial)token.value=''}
}
watch(visible,v=>{if(!v)serial++},{flush:'sync'})
watch(()=>[props.connectionKey,props.ready,props.canApply],()=>{serial++;visible.value=false;lastResult.value='';phase.value='loading'})
onUnmounted(()=>{serial++})
</script>
<style scoped>
.account-entry{border-top:1px solid var(--el-border-color-lighter);margin-top:18px;padding-top:16px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.account-entry p,small{color:var(--el-text-color-secondary);font-size:13px;line-height:1.5;margin:5px 0}
.field-label{display:block;margin:8px 0;font-weight:600}.full{width:100%}.choices{margin-top:16px}small{display:block}
</style>
