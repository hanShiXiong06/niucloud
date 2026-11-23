<template>
    <section ref="panel" class="category-directory" aria-label="商品分类层级">
        <el-skeleton v-if="loading" :rows="5" animated aria-label="正在读取分类层级" />
        <el-alert v-else-if="error" :title="error" type="error" :closable="false" show-icon>
            <el-button link type="primary" @click="load">重新读取</el-button>
        </el-alert>
        <template v-else>
            <div class="filters">
                <HsxSearchInput v-model="keyword" class="search" aria-label="搜索商品分类" placeholder="分类名称、编码或路径" auto-search :debounce="180" :maxlength="100" @search="applySearch" />
                <el-select v-model="level" aria-label="分类级别" placeholder="全部级别">
                    <el-option label="全部级别" value="" />
                    <el-option v-for="n in catalog.levels" :key="n" :label="levelName(n)" :value="String(n)" />
                    <el-option v-if="catalog.issues.length" label="层级待核对" value="0" />
                </el-select>
                <el-select v-model="status" aria-label="分类状态" placeholder="全部状态">
                    <el-option label="全部状态" value="" /><el-option label="启用" value="enabled" /><el-option label="停用" value="disabled" /><el-option label="状态未提供" value="unknown" />
                </el-select>
                <el-button v-if="filtered || keyword" link type="primary" @click="clearFilters">重置</el-button>
            </div>
            <div v-if="catalog.byId.size" class="summary">
                <span role="status">{{ filtered ? '找到 '+matches.length+' 个分类' : catalog.roots.length+' 个一级分类' }}<template v-if="!filtered && catalog.levels.length"> · 最深{{ levelName(catalog.levels[catalog.levels.length-1]) }}</template></span>
                <el-button v-if="catalog.issues.length && level!=='0'" link type="warning" @click="showIssues">层级待核对（{{ catalog.issues.length }}）</el-button>
                <el-button v-if="!filtered && pageBranchIds.length" link type="primary" @click="togglePage">{{ pageExpanded ? '收起本页' : '展开本页' }}</el-button>
            </div>
            <HsxTable row-key="id" :data="present" :columns="columns" :empty-text="emptyText">
                <template #category="{row}">
                    <div class="category-cell" :class="{located:located===row.id}" :style="{paddingLeft: filtered ? '0' : Math.min(Math.max(row.depth-1,0),8)*16+'px'}">
                        <template v-if="!filtered">
                            <button v-if="row.childIds.length" class="caret" :aria-label="(expanded.has(row.id)?'收起':'展开')+' '+row.name" :aria-expanded="expanded.has(row.id)" @click="toggle(row.id)"></button>
                            <span v-else class="caret-space" aria-hidden="true"></span>
                        </template>
                        <div class="identity">
                            <button class="name" :data-category-id="row.id" title="查看分类详情" @click="detail(row)">{{ row.name }}</button>
                            <small v-if="filtered && row.depth" class="path">{{ pathOf(row) }}</small>
                            <small v-if="row.issue" class="issue">{{ row.issue }}<template v-if="row.parent_name"> · 上级：{{ row.parent_name }}</template></small>
                        </div>
                        <span v-if="!filtered && row.childIds.length" class="children-count">{{ row.childIds.length }} 个下级</span>
                    </div>
                </template>
                <template #level="{row}"><span :class="{issue:!row.depth}">{{ levelName(row.depth) }}</span></template>
                <template #status="{row}"><HsxTag :tone="row.status==='enabled'?'success':'neutral'" dot>{{ statusLabel(row) }}</HsxTag></template>
                <template #locate="{row}"><el-button v-if="row.depth" link type="primary" @click="locate(row)">定位到层级</el-button><span v-else>—</span></template>
                <template #empty><div class="empty" role="status">{{ emptyText }}<el-button v-if="filtered" link type="primary" @click="clearFilters">清除筛选</el-button></div></template>
            </HsxTable>
            <footer v-if="pageTotal>pageSize || pageSize!==20"><span>{{ filtered?'按匹配分类分页':'按一级分类分页' }}</span><HsxPagination :current-page="page" :page-size="pageSize" :page-sizes="[20,50,100]" :total="pageTotal" @change="paginate" /></footer>
        </template>
    </section>
</template>
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, shallowRef, watch } from 'vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxTag from '@/addon/hsx_components/components/HsxTag/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxSearchInput from '@/addon/hsx_components/components/HsxSearchInput/index.vue'
import type { HsxTableColumn } from '@/addon/hsx_components/types'
import { readCategoryDirectory } from '../api/opening-archives'
import { reason } from '../utils/connection'
import { indexCategories, levelName, lineage, matchCategories, statusText, visibleBranches } from '../utils/category-directory'
import type { CategoryNode } from '../utils/category-directory'

const props = defineProps<{ connectionKey: string; emptyMessage: string }>()
const emit = defineEmits<{(e:'detail', record:{id:string; name:string; categoryPath:string}):void; (e:'list-only'):void}>()
const catalog = shallowRef(indexCategories([])), expanded = shallowRef(new Set<string>())
const keyword = ref(''), term = ref(''), level = ref(''), status = ref(''), page = ref(1), pageSize = ref(20), located = ref('')
const loading = ref(true), error = ref(''), panel = ref<HTMLElement>()
let request = 0
const filtered = computed(() => !!(term.value || level.value || status.value))
const matches = computed(() => filtered.value ? matchCategories(catalog.value, term.value, level.value, status.value) : [])
const offset = computed(() => (page.value - 1) * pageSize.value)
const roots = computed(() => catalog.value.roots.slice(offset.value, offset.value + pageSize.value))
const pageTotal = computed(() => filtered.value ? matches.value.length : catalog.value.roots.length)
const present = computed(() => filtered.value ? matches.value.slice(offset.value,offset.value + pageSize.value) : visibleBranches(catalog.value, roots.value, expanded.value))
const pageBranchIds = computed(() => visibleBranches(catalog.value, roots.value, null).filter(n => n.childIds.length).map(n => n.id))
const pageExpanded = computed(() => pageBranchIds.value.length>0 && pageBranchIds.value.every(id=>expanded.value.has(id)))
const emptyText = computed(() => filtered.value ? '没有符合条件的分类' : catalog.value.issues.length ? '当前没有可确认的一级分类，请查看「层级待核对」。' : props.emptyMessage)
const columns = computed<HsxTableColumn<any>[]>(() => [
    {prop:'name',label:'分类名称',slot:'category',minWidth:250,showOverflowTooltip:false},
    {prop:'depth',label:'级别',slot:'level',width:84},
    {prop:'code',label:'分类编码',minWidth:110},
    {prop:'status',label:'状态',slot:'status',width:92},
    ...(filtered.value ? [{prop:'locate',label:'操作',slot:'locate',width:110}] : [])
])
function pathOf(row:CategoryNode) { return lineage(catalog.value,row).map(n=>n.name).join(' / ') }
function statusLabel(row:CategoryNode) { return statusText[row.status] }
function applySearch(value:string|number, trigger?:string) {
    const next=String(value).trim()
    if(trigger==='debounce' && next!==keyword.value.trim())return
    term.value=next
}
function clearFilters() { keyword.value=''; term.value=''; level.value=''; status.value='' }
function showIssues() { clearFilters(); level.value='0' }
watch([term,level,status],()=>{ page.value=1; located.value='' },{flush:'sync'})
function paginate(p:{page:number;limit:number}) { if ([20,50,100].includes(p.limit)) {pageSize.value=p.limit;page.value=p.page} }
function toggle(id:string) { const set=new Set(expanded.value); if(set.has(id))set.delete(id); else set.add(id); expanded.value=set }
function togglePage() { const set=new Set(expanded.value),fold=pageExpanded.value;pageBranchIds.value.forEach(id=>fold?set.delete(id):set.add(id));expanded.value=set }
function detail(row:CategoryNode) { emit('detail',{id:row.id,name:row.name,categoryPath:row.depth?pathOf(row):'层级待核对 · '+row.issue}) }
async function locate(row:CategoryNode) {
    const ancestors=lineage(catalog.value,row)
    if(!ancestors.length)return
    const index=catalog.value.roots.indexOf(ancestors[0])
    if(index<0)return
    clearFilters();page.value=1+Math.floor(index/pageSize.value);located.value=row.id
    expanded.value=new Set([...expanded.value,...ancestors.slice(0,-1).map(n=>n.id)])
    await nextTick()
    const button=Array.from(panel.value?.querySelectorAll<HTMLButtonElement>('button[data-category-id]') || []).find(el=>el.dataset.categoryId===row.id)
    button?.focus({preventScroll:true});button?.scrollIntoView({block:'nearest'})
}
async function load() {
    const seq=++request, key=props.connectionKey
    loading.value=true; error.value='';catalog.value=indexCategories([]);expanded.value=new Set();clearFilters();located.value='';page.value=1
    try {
        const data=(await readCategoryDirectory(key))?.data
        if(seq!==request || key!==props.connectionKey)return
        if(data?.source!=='local_archive' || data.resource!=='categories' || data.connection_key!==key || typeof data.list_only!=='boolean' || !Array.isArray(data.items))throw Error('未取得当前账套的分类层级，请刷新后重试。')
        if(data.list_only){ emit('list-only');return }
        catalog.value=indexCategories(data.items)
    } catch(e) { if(seq===request)error.value=reason(e,'未能读取分类层级，请重试或切换到明细列表。') }
    finally { if(seq===request)loading.value=false }
}
watch(()=>props.connectionKey,load,{immediate:true})
onBeforeUnmount(()=>{++request})
</script>
<style scoped>
.filters,.summary,footer,.empty{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.filters{margin:18px 0 12px}.search{width:min(310px,100%)}.filters .el-select{width:126px}
.summary{min-height:30px;margin-bottom:5px;color:var(--el-text-color-regular);font-size:13px}.summary .el-button:last-child{margin-left:auto}.category-cell{display:flex;align-items:center;gap:5px;min-height:30px}.identity{min-width:0}.name,.caret{font:inherit;cursor:pointer;border:none;background:transparent}.name{color:var(--el-color-primary);text-align:left;overflow-wrap:anywhere;padding:3px 0}.name:hover{text-decoration:underline}.caret,.caret-space{width:24px;min-width:24px;height:24px}.caret{display:inline-grid;place-content:center;border-radius:4px}.caret:hover{background:var(--el-fill-color)}.caret:before{content:'';width:7px;height:7px;border-right:1.6px solid var(--el-text-color-regular);border-bottom:1.6px solid var(--el-text-color-regular);transform:rotate(-45deg)}.caret[aria-expanded=true]:before{transform:rotate(45deg)}.caret:focus-visible,.name:focus-visible{outline:2px solid var(--el-color-primary);outline-offset:1px}
.children-count,.path,footer>span{color:var(--el-text-color-secondary);font-size:12px}.children-count{white-space:nowrap}.path,.issue{display:block;line-height:1.5;overflow-wrap:anywhere}.issue{color:var(--el-color-warning-dark-2)}.located{background:var(--el-color-primary-light-9);border-radius:4px}.empty{justify-content:center;padding:22px}footer{margin-top:14px;justify-content:flex-end}
</style>
