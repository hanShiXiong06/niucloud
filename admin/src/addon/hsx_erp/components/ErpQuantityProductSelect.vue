<template>
    <div class="quantity-product-select">
        <el-select
            :model-value="modelValue || undefined"
            filterable
            remote
            clearable
            :remote-method="search"
            :loading="loading"
            :placeholder="placeholder"
            class="w-full"
            @visible-change="visible => visible && search('')"
            @change="select"
            @clear="clear"
        >
            <el-option v-for="item in options" :key="item.id" :label="item.display_name || item.product_name" :value="item.id">
                <div class="quantity-product-option">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ item.product_name }}</div>
                        <div class="truncate text-xs text-gray-400">{{ [item.category_name || '未分类', item.spec, item.product_code].filter(Boolean).join(' · ') }}</div>
                    </div>
                    <div class="shrink-0 text-right text-xs text-gray-500">
                        <div>库存 {{ quantityText(item.stock_quantity) }}{{ item.unit || '件' }}</div>
                        <div>均价 ¥{{ money(item.average_cost) }}</div>
                    </div>
                </div>
            </el-option>
        </el-select>
        <el-button v-if="currentProduct" type="primary" link class="!ml-2" @click="openCategory">
            {{ currentProduct.category_path ? '分类' : '补分类' }}
        </el-button>
        <el-button type="primary" link class="!ml-2" @click="openCreate">新品</el-button>

        <el-dialog v-model="createVisible" title="创建标品档案" width="520px" append-to-body destroy-on-close>
            <el-alert title="同一商品以后直接检索补货，不需要重复创建。" type="info" :closable="false" show-icon class="mb-4" />
            <el-form label-width="86px">
                <el-form-item label="商品分类" required>
                    <ErpCatalogCategorySelect v-model="form.category_path" />
                </el-form-item>
                <el-form-item label="商品名称" required><el-input v-model.trim="form.product_name" placeholder="如 iPhone 15 钢化膜" /></el-form-item>
                <el-form-item label="规格"><el-input v-model.trim="form.spec" placeholder="如 透明 / 高清" /></el-form-item>
                <el-form-item label="商品编码"><el-input v-model.trim="form.product_code" placeholder="不填自动生成 SKU 编码" /></el-form-item>
                <el-form-item label="计量单位" required>
                    <el-select v-model="form.unit" filterable allow-create class="w-full">
                        <el-option v-for="unit in units" :key="unit" :label="unit" :value="unit" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="save">创建并选中</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="categoryVisible" title="完善标品分类" width="500px" append-to-body destroy-on-close>
            <el-alert title="分类属于商品主数据，补货时会自动沿用；修改不会影响历史库存流水。" type="info" :closable="false" show-icon class="mb-4" />
            <el-form label-width="86px">
                <el-form-item label="商品"><span>{{ currentProduct?.display_name || currentProduct?.product_name }}</span></el-form-item>
                <el-form-item label="商品分类" required>
                    <ErpCatalogCategorySelect v-model="categoryForm.category_path" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="categoryVisible = false">取消</el-button>
                <el-button type="primary" :loading="categorySaving" @click="saveCategory">保存分类</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { createErpQuantityProduct, getErpQuantityProducts, updateErpQuantityProductCategory } from '@/addon/hsx_erp/api/erp'
import ErpCatalogCategorySelect from './ErpCatalogCategorySelect.vue'

const props = withDefaults(defineProps<{
    modelValue?: number
    selected?: any
    placeholder?: string
}>(), {
    modelValue: 0,
    selected: null,
    placeholder: '搜索已有商品，找不到可创建新品',
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: number): void
    (event: 'change', value: any | null): void
}>()

const units = ['件', '张', '个', '盒', '台', '套']
const loading = ref(false)
const saving = ref(false)
const categorySaving = ref(false)
const createVisible = ref(false)
const categoryVisible = ref(false)
const options = ref<any[]>([])
const form = ref({ category_path: '', product_name: '', spec: '', product_code: '', unit: '件' })
const categoryForm = ref({ category_path: '' })
const currentProduct = computed(() =>
    options.value.find(item => Number(item.id) === Number(props.modelValue))
    || (Number(props.selected?.id) === Number(props.modelValue) ? props.selected : null)
)
let sequence = 0

watch(() => props.selected, value => {
    if (value?.id && !options.value.some(item => Number(item.id) === Number(value.id))) options.value.unshift(value)
}, { immediate: true })

async function search(keyword = '') {
    const current = ++sequence
    loading.value = true
    try {
        const res: any = await getErpQuantityProducts({ keyword, page: 1, limit: 30 })
        if (current !== sequence) return
        options.value = res?.data?.data || res?.data || []
    } finally {
        if (current === sequence) loading.value = false
    }
}

function select(id: number) {
    const row = options.value.find(item => Number(item.id) === Number(id)) || null
    emit('update:modelValue', Number(id || 0))
    emit('change', row)
}

function clear() {
    emit('update:modelValue', 0)
    emit('change', null)
}

function openCreate() {
    form.value = { category_path: '', product_name: '', spec: '', product_code: '', unit: '件' }
    createVisible.value = true
}

async function save() {
    if (!form.value.category_path) return ElMessage.warning('请选择商品末级分类')
    if (!form.value.product_name.trim()) return ElMessage.warning('请填写商品名称')
    saving.value = true
    try {
        const res: any = await createErpQuantityProduct(form.value)
        const row = res?.data || {}
        if (!row.id) throw new Error('商品档案创建失败')
        options.value = [row, ...options.value.filter(item => Number(item.id) !== Number(row.id))]
        emit('update:modelValue', Number(row.id))
        emit('change', row)
        createVisible.value = false
        ElMessage.success(row.created ? '新品已创建并选中' : '已找到相同商品并选中')
    } finally {
        saving.value = false
    }
}

function openCategory() {
    if (!currentProduct.value) return
    categoryForm.value.category_path = String(currentProduct.value.category_path || '')
    categoryVisible.value = true
}

async function saveCategory() {
    if (!currentProduct.value?.id || !categoryForm.value.category_path) return ElMessage.warning('请选择商品末级分类')
    categorySaving.value = true
    try {
        const res: any = await updateErpQuantityProductCategory(Number(currentProduct.value.id), categoryForm.value)
        const row = res?.data || {}
        options.value = options.value.map(item => Number(item.id) === Number(row.id) ? { ...item, ...row } : item)
        emit('change', { ...currentProduct.value, ...row })
        categoryVisible.value = false
        ElMessage.success('商品分类已保存')
    } finally {
        categorySaving.value = false
    }
}

function quantityText(value: any) {
    return Number(value || 0).toFixed(3).replace(/\.?0+$/, '')
}
function money(value: any) { return Number(value || 0).toFixed(2) }
</script>

<style scoped>
.quantity-product-select { display:flex; align-items:center; width:100%; }
.quantity-product-option { display:flex; align-items:center; justify-content:space-between; gap:16px; width:100%; }
</style>
