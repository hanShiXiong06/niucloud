<template>
    <view>
        <view class="trigger" :class="{ 'trigger--selected': modelValue }" @click="open">
            <view class="trigger__copy">
                <text class="trigger__title">{{ selected?.product_name || '选择已有商品' }}</text>
                <text class="trigger__sub">{{ selected ? [selected.category_name || '未分类', selected.spec, selected.product_code].filter(Boolean).join(' · ') : '搜索补货商品，找不到可创建新品' }}</text>
            </view>
            <u-icon name="arrow-right" color="#94a3b8" size="16" />
        </view>

        <u-popup :show="show" mode="bottom" round="20" :safe-area-inset-bottom="true" @close="close">
            <view class="popup">
                <view class="head">
                    <view>
                        <text class="head__title">{{ categoryTarget ? '完善商品分类' : (creating ? '创建新品' : '选择补货商品') }}</text>
                        <text class="head__sub">{{ categoryTarget ? '分类保存后继续本次补货' : (creating ? '创建一次，以后直接检索补货' : '库存数量与移动平均成本会自动更新') }}</text>
                    </view>
                    <view class="head__actions">
                        <text class="head__link" @click="toggleMode">{{ creating || categoryTarget ? '返回列表' : '新增' }}</text>
                        <u-icon name="close" color="#94a3b8" size="20" @click="close" />
                    </view>
                </view>

                <view v-if="categoryTarget" class="create-form category-repair">
                    <view class="repair-product">
                        <text class="repair-product__name">{{ categoryTarget.product_name }}</text>
                        <text class="repair-product__meta">{{ [categoryTarget.spec, categoryTarget.product_code].filter(Boolean).join(' · ') }}</text>
                    </view>
                    <view class="category-field">
                        <text class="category-field__label">商品分类 *</text>
                        <ErpCatalogCategoryPopup v-model="categoryForm.category_path" />
                    </view>
                    <view class="submit-wrap"><u-button type="primary" :loading="categorySaving" text="保存分类并选中" @click="saveCategory" /></view>
                </view>

                <view v-else-if="creating" class="create-form">
                    <view class="category-field">
                        <text class="category-field__label">商品分类 *</text>
                        <ErpCatalogCategoryPopup v-model="form.category_path" />
                    </view>
                    <view class="form-field"><text>商品名称 *</text><u-input v-model="form.product_name" border="none" placeholder="如 iPhone 15 钢化膜" /></view>
                    <view class="form-field"><text>规格</text><u-input v-model="form.spec" border="none" placeholder="如 透明 / 高清" /></view>
                    <view class="form-field"><text>商品编码</text><u-input v-model="form.product_code" border="none" placeholder="不填自动生成" /></view>
                    <view class="unit-row">
                        <text>计量单位</text>
                        <view class="unit-list">
                            <view v-for="unit in units" :key="unit" class="unit" :class="{ active: form.unit === unit }" @click="form.unit = unit">{{ unit }}</view>
                        </view>
                    </view>
                    <view class="submit-wrap"><u-button type="primary" :loading="saving" text="创建并选中" @click="save" /></view>
                </view>

                <template v-else>
                    <view class="search">
                        <u-search v-model="keyword" placeholder="商品名称 / 规格 / 编码" :showAction="false" bgColor="#f1f5f9" @search="search" @clear="search" />
                    </view>
                    <scroll-view scroll-y class="list">
                        <view v-if="loading" class="empty"><u-loading-icon size="28" /><text>正在查询商品档案</text></view>
                        <view v-else-if="!list.length" class="empty">
                            <u-icon name="search" color="#cbd5e1" size="30" />
                            <text>没有找到商品</text>
                            <text class="empty__action" @click="creating = true">创建新品</text>
                        </view>
                        <view v-for="item in list" v-else :key="item.id" class="product" :class="{ selected: Number(item.id) === Number(modelValue) }" @click="choose(item)">
                            <view class="product__main">
                                <text class="product__name">{{ item.product_name }}</text>
                                <text class="product__meta">{{ [item.category_name || '未分类', item.spec, item.product_code].filter(Boolean).join(' · ') }}</text>
                            </view>
                            <view class="product__stock">
                                <text>库存 {{ quantityText(item.stock_quantity) }}{{ item.unit || '件' }}</text>
                                <text>均价 ¥{{ money(item.average_cost) }}</text>
                            </view>
                            <u-icon :name="Number(item.id) === Number(modelValue) ? 'checkmark-circle-fill' : 'arrow-right'" :color="Number(item.id) === Number(modelValue) ? '#2563eb' : '#cbd5e1'" size="18" />
                        </view>
                    </scroll-view>
                </template>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { createMobileErpQuantityProduct, getMobileErpQuantityProducts, updateMobileErpQuantityProductCategory } from '@/addon/hsx_erp/api/erp'
import ErpCatalogCategoryPopup from './ErpCatalogCategoryPopup.vue'

const props = withDefaults(defineProps<{ modelValue?: number; selected?: any }>(), { modelValue: 0, selected: null })
const emit = defineEmits<{
    (event: 'update:modelValue', value: number): void
    (event: 'change', value: any | null): void
}>()

const units = ['件', '张', '个', '盒', '台', '套']
const show = ref(false)
const creating = ref(false)
const loading = ref(false)
const saving = ref(false)
const categorySaving = ref(false)
const keyword = ref('')
const list = ref<any[]>([])
const form = ref({ category_path: '', product_name: '', spec: '', product_code: '', unit: '件' })
const categoryTarget = ref<any>(null)
const categoryForm = ref({ category_path: '' })

function open() {
    show.value = true
    creating.value = false
    categoryTarget.value = null
    keyword.value = ''
    search()
}
function close() { show.value = false }
function toggleMode() {
    if (creating.value || categoryTarget.value) {
        creating.value = false
        categoryTarget.value = null
        return
    }
    form.value = { category_path: '', product_name: '', spec: '', product_code: '', unit: '件' }
    creating.value = true
}
async function search() {
    loading.value = true
    try {
        const res: any = await getMobileErpQuantityProducts({ keyword: keyword.value, page: 1, limit: 50 })
        list.value = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
    } finally { loading.value = false }
}
function choose(item: any) {
    if (!String(item.category_path || '').trim()) {
        categoryTarget.value = item
        categoryForm.value.category_path = ''
        return
    }
    emit('update:modelValue', Number(item.id || 0))
    emit('change', item)
    close()
}
async function save() {
    if (!form.value.category_path) return uni.showToast({ title: '请选择商品末级分类', icon: 'none' })
    if (!form.value.product_name.trim()) return uni.showToast({ title: '请填写商品名称', icon: 'none' })
    saving.value = true
    try {
        const res: any = await createMobileErpQuantityProduct(form.value)
        const row = res?.data || {}
        if (!row.id) throw new Error('商品档案创建失败')
        choose(row)
        uni.showToast({ title: row.created ? '新品已创建' : '已选择相同商品', icon: 'none' })
    } catch (error: any) {
        uni.showToast({ title: error?.message || '创建失败', icon: 'none' })
    } finally { saving.value = false }
}
async function saveCategory() {
    if (!categoryTarget.value?.id || !categoryForm.value.category_path) {
        return uni.showToast({ title: '请选择商品末级分类', icon: 'none' })
    }
    categorySaving.value = true
    try {
        const res: any = await updateMobileErpQuantityProductCategory(Number(categoryTarget.value.id), categoryForm.value)
        const row = { ...categoryTarget.value, ...(res?.data || {}) }
        list.value = list.value.map(item => Number(item.id) === Number(row.id) ? row : item)
        categoryTarget.value = null
        choose(row)
        uni.showToast({ title: '分类已保存', icon: 'none' })
    } catch (error: any) {
        uni.showToast({ title: error?.message || '分类保存失败', icon: 'none' })
    } finally { categorySaving.value = false }
}
function quantityText(value: any) { return Number(value || 0).toFixed(3).replace(/\.?0+$/, '') }
function money(value: any) { return Number(value || 0).toFixed(2) }
</script>

<style scoped lang="scss">
.trigger { display:flex; align-items:center; justify-content:space-between; gap:18rpx; min-height:94rpx; padding:0 24rpx; border:1px solid #dbe4f0; border-radius:18rpx; background:#fff; }
.trigger--selected { border-color:#93c5fd; background:#f8fbff; }
.trigger__copy { min-width:0; display:flex; flex-direction:column; gap:6rpx; }
.trigger__title { color:#334155; font-size:28rpx; font-weight:650; }
.trigger__sub { color:#94a3b8; font-size:23rpx; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.popup { min-height:780rpx; max-height:88vh; background:#f8fafc; }
.head { display:flex; align-items:center; justify-content:space-between; padding:30rpx 30rpx 22rpx; background:#fff; }
.head__title,.head__sub { display:block; }
.head__title { color:#0f172a; font-size:34rpx; font-weight:700; }
.head__sub { margin-top:5rpx; color:#94a3b8; font-size:23rpx; }
.head__actions { display:flex; align-items:center; gap:28rpx; }
.head__link { color:#2563eb; font-size:26rpx; }
.search { padding:20rpx 24rpx; background:#fff; border-top:1px solid #f1f5f9; }
.list { height:650rpx; padding:18rpx 24rpx; box-sizing:border-box; }
.product { display:flex; align-items:center; gap:18rpx; margin-bottom:16rpx; padding:24rpx; border:1px solid #e2e8f0; border-radius:20rpx; background:#fff; }
.product.selected { border-color:#60a5fa; background:#eff6ff; }
.product__main { min-width:0; flex:1; display:flex; flex-direction:column; gap:7rpx; }
.product__name { color:#1e293b; font-size:29rpx; font-weight:650; }
.product__meta { color:#94a3b8; font-size:23rpx; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.product__stock { flex-shrink:0; display:flex; flex-direction:column; align-items:flex-end; gap:5rpx; color:#64748b; font-size:22rpx; }
.empty { height:420rpx; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16rpx; color:#94a3b8; font-size:26rpx; }
.empty__action { color:#2563eb; }
.create-form { margin:22rpx; padding:0 24rpx; border-radius:22rpx; background:#fff; }
.category-field { padding:24rpx 0; border-bottom:1px solid #eef2f7; }
.category-field__label { display:block; margin-bottom:16rpx; color:#334155; font-size:27rpx; font-weight:600; }
.repair-product { padding:28rpx 0 8rpx; display:flex; flex-direction:column; gap:7rpx; }
.repair-product__name { color:#1e293b; font-size:30rpx; font-weight:650; }
.repair-product__meta { color:#94a3b8; font-size:23rpx; }
.form-field { display:flex; align-items:center; gap:24rpx; min-height:104rpx; border-bottom:1px solid #eef2f7; color:#334155; font-size:27rpx; }
.form-field > text { width:145rpx; flex-shrink:0; }
.unit-row { padding:25rpx 0; color:#334155; font-size:27rpx; }
.unit-list { display:flex; flex-wrap:wrap; gap:14rpx; margin-top:18rpx; }
.unit { min-width:72rpx; padding:12rpx 20rpx; text-align:center; color:#64748b; border-radius:14rpx; background:#f1f5f9; }
.unit.active { color:#2563eb; background:#eff6ff; box-shadow:inset 0 0 0 1px #93c5fd; }
.submit-wrap { padding:28rpx 0; }
</style>
