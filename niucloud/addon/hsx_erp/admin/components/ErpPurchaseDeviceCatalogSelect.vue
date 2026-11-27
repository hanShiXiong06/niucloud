<template>
    <div class="purchase-catalog">
        <div v-if="state === 'loading'" class="catalog-note" role="status">正在读取商品目录…</div>
        <div v-else-if="state === 'error'" class="catalog-note" role="alert">
            {{ error }} <el-button link type="primary" @click="init">重试</el-button>
        </div>
        <template v-else-if="state === 'local'">
            <ErpCatalogProductSelect :model-value="modelValue" :category-path="categoryPath"
                placeholder="先选分类，可继续选择品牌、系列和型号" @change="node => emit('change', node)" />
        </template>
        <template v-else>
            <div class="catalog-source">资料来源：{{ sourceLabel }}
                <el-popover v-if="maintenance.hint" trigger="click" placement="bottom-start" :width="310">
                    <template #reference><el-button link type="primary" size="small">如何更新？</el-button></template>
                    <div>{{ maintenance.hint }}</div>
                    <div class="catalog-actions">
                        <a v-for="link in links" :key="link.href" :href="link.href" target="_blank" rel="noopener noreferrer">{{ link.label }} ↗</a>
                        <el-button link type="primary" size="small" @click="init">重新读取目录</el-button>
                    </div>
                </el-popover>
            </div>
            <div class="catalog-controls">
                <el-tree-select :model-value="categoryId" :data="tree" node-key="id" :props="{ value: 'id', label: 'label', children: 'children' }"
                    check-strictly :expand-on-click-node="false" filterable clearable placeholder="选择商品分类" aria-label="采购商品分类"
                    :empty-text="tree.length ? '无匹配分类' : '尚无已同步的分类'" @change="chooseCategory" />
                <el-select :model-value="productId" filterable remote clearable :remote-method="search" :loading="busy" :disabled="!tree.length || selecting"
                    :placeholder="selecting ? '正在核对商品…' : '搜索型号或商品编码'" aria-label="采购目录型号"
                    @visible-change="open => { if (open) search('') }" @change="chooseProduct">
                    <el-option v-if="productId && !rows.some(r => r.id === productId)" :value="productId" :label="productName" />
                    <el-option v-for="r in rows" :key="r.id" :value="r.id" :label="r.label">
                        <span>{{ r.label }}</span><small class="product-code"> {{ r.code }}{{ r.detail ? ' · ' + r.detail : '' }}</small>
                    </el-option>
                    <template #empty><div class="catalog-note catalog-empty">{{ productError || (keyword ? '没有匹配的商品，可更换关键词或清除分类' : '此分类暂无可选商品') }}</div></template>
                    <template v-if="more" #footer><el-button link type="primary" :loading="paging" @click.stop="fetchPage(false)">加载更多商品</el-button></template>
                </el-select>
            </div>
            <div v-if="productError" class="catalog-note" role="alert">{{ productError }} <el-button link type="primary" @click="init">刷新目录</el-button></div>
            <div v-else-if="!tree.length" class="catalog-note">暂无可用分类。请在{{ sourceLabel }}维护分类，再回到基础资料同步。</div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getErpGoodsCatalogHierarchy } from '@/addon/hsx_erp/api/erp'
import ErpCatalogProductSelect from './ErpCatalogProductSelect.vue'

const props = defineProps<{ modelValue?: number | string, categoryPath?: string, productName?: string, selection?: Record<string,string> | null }>()
const emit = defineEmits<{ (e:'change', node:any):void }>()
const router = useRouter()
const maintenance = ref<{ hint?:string, links?:Array<{ name:string, label:string, query?:Record<string,string> }> }>({})
const links = computed(() => (maintenance.value.links || []).filter(l => l.name && router.hasRoute(l.name))
    .map(l => ({ label: l.label, href: router.resolve({ name: l.name, query: l.query }).href })))
const state = ref<'loading'|'local'|'managed'|'error'>('loading')
const sourceLabel = ref(''), scope = ref(''), error = ref(''), productError = ref('')
const categories = ref<any[]>([]), rows = ref<any[]>([]), page = ref(0), more = ref(false)
const busy = ref(false), paging = ref(false), selecting = ref(false), keyword = ref('')
let sequence = 0, selectSequence = 0, initSequence = 0
const categoryId = computed(() => props.selection?.scope === scope.value ? props.selection?.category_id || '' : '')
const productId = computed(() => props.selection?.scope === scope.value ? props.selection?.product_id || '' : '')
const tree = computed(() => {
    const nodes = new Map<string, any>(categories.value.map(c => [c.id, { ...c, children: [] }]))
    const roots:any[] = []
    nodes.forEach(n => { if (!n.parent_id) roots.push(n); else nodes.get(n.parent_id)?.children.push(n) })
    return roots
})
const message = (e:any) => String(e?.msg || e?.message || '商品目录读取失败，请重试')
const request = async (params:any) => {
    const res:any = await getErpGoodsCatalogHierarchy({ scene: 'purchase_device', ...params })
    if (typeof res?.data?.managed !== 'boolean') throw new Error('采购目录接口未就绪，请更新 ERP 后端并重试')
    return res.data
}
async function init() {
    const n = ++initSequence
    ++sequence; ++selectSequence; selecting.value = busy.value = paging.value = false
    state.value = 'loading'; error.value = productError.value = ''; rows.value = []; more.value = false
    try {
        const data = await request({ node_type: 'root' })
        if (n !== initSequence) return
        if (data.managed && (!data.scope || !Array.isArray(data.list))) throw new Error('未读到来源分类，请刷新目录')
        if (props.selection?.scope && (props.selection.scope !== data.scope || !data.list?.some((c:any) => c.id === props.selection?.category_id))) emit('change', null)
        scope.value = data.scope || ''; categories.value = data.list || []; sourceLabel.value = data.source_label || ''
        maintenance.value = data.maintenance || {}
        state.value = data.managed ? 'managed' : 'local'
    } catch (e) { if (n === initSequence) { state.value = 'error'; error.value = message(e) } }
}
function chooseCategory(id?:string) {
    ++sequence; ++selectSequence; selecting.value = busy.value = paging.value = false
    rows.value = []; more.value = false; keyword.value = ''; productError.value = ''
    const c = categories.value.find(r => r.id === id)
    emit('change', c ? { ...c, node_type: 'category', catalog_reference: { scope: scope.value, category_id: c.id } } : null)
}
function search(word:string) { keyword.value = word.trim(); return fetchPage(true) }
async function fetchPage(reset:boolean) {
    if (!reset && (busy.value || paging.value || !more.value)) return
    const n = ++sequence, next = reset ? 1 : page.value + 1
    if (reset) { busy.value = true; paging.value = false; rows.value = []; more.value = false } else paging.value = true
    productError.value = ''
    try {
        const data = await request({ node_type: 'products', category_id: categoryId.value, expected_scope: scope.value, keyword: keyword.value, page: next })
        if (n !== sequence) return
        if (!data.managed || !Array.isArray(data.list)) throw new Error('目录已变更，请刷新目录')
        rows.value = reset ? data.list : [...rows.value, ...data.list]
        page.value = next; more.value = data.more === true
    } catch(e) { if (n === sequence) productError.value = message(e) }
    finally { if (n === sequence) busy.value = paging.value = false }
}
async function chooseProduct(id?:string) {
    if (!id) { chooseCategory(categoryId.value); return }
    const r = rows.value.find(row => row.id === id)
    if (!r) return
    const n = ++selectSequence
    selecting.value = true; productError.value = ''
    try {
        const data = await request({ node_type: 'selection', category_id: r.category_id, product_id: r.id, expected_scope: scope.value })
        if (n !== selectSequence) return
        if (!data.managed || data.choice?.catalog_reference?.product_id !== id) throw new Error('未能确认所选商品，请刷新目录')
        emit('change', data.choice)
    } catch(e) {
        // A failed replacement must not leave a different, previously selected SKU ready to submit.
        if (n === selectSequence) { chooseCategory(r.category_id); productError.value = message(e) }
    }
    finally { if (n === selectSequence) selecting.value = false }
}
onMounted(init)
onBeforeUnmount(() => { ++sequence; ++selectSequence; ++initSequence })
</script>

<style scoped>
.purchase-catalog { width: 100%; min-width: 0; }
.catalog-source,.catalog-note,.product-code { color: var(--el-text-color-secondary); font-size: 12px; }
.catalog-source { margin: -2px 0 5px; line-height: 18px; }
.catalog-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 8px; }
.catalog-actions a { color: var(--el-color-primary); }
.catalog-controls { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); gap: 8px; }
.catalog-controls :deep(.el-select) { width: 100%; min-width: 0; }
.catalog-note { line-height: 1.6; }.catalog-empty { padding: 12px 16px; }.product-code { margin-left: 6px; }
@media (max-width: 600px) { .catalog-controls { grid-template-columns: 1fr; } }
</style>
