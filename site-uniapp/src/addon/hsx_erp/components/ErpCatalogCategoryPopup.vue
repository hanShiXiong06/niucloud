<template>
    <view>
        <view class="category-trigger" :class="{ 'category-trigger--disabled': disabled }" @click="open">
            <view class="category-trigger__copy">
                <text :class="modelValue ? 'category-trigger__value' : 'category-trigger__placeholder'">
                    {{ leafName || placeholder }}
                </text>
                <text v-if="modelValue && showPath" class="category-trigger__path">{{ pathText }}</text>
            </view>
            <u-icon name="arrow-right" color="#94a3b8" size="16" />
        </view>

        <u-popup :show="visible" mode="bottom" round="20" :safe-area-inset-bottom="true" @close="close">
            <view class="category-popup">
                <view class="category-head">
                    <view>
                        <text class="category-head__title">选择商品分类</text>
                        <text class="category-head__sub">逐级选择，只能确认末级分类</text>
                    </view>
                    <u-icon name="close" color="#94a3b8" size="20" @click="close" />
                </view>
                <view class="category-search">
                    <u-search v-model="keyword" placeholder="搜索分类名称" :showAction="false" bgColor="#f1f5f9"
                        @search="search" @clear="resetSearch" />
                </view>
                <scroll-view v-if="!keyword" scroll-x class="category-breadcrumb">
                    <view class="category-breadcrumb__inner">
                        <text class="category-breadcrumb__item" @click="backTo(-1)">全部分类</text>
                        <template v-for="(item, index) in stack" :key="item.category_path">
                            <u-icon name="arrow-right" color="#cbd5e1" size="12" />
                            <text class="category-breadcrumb__item" :class="{ active: index === stack.length - 1 }"
                                @click="backTo(index)">{{ item.label }}</text>
                        </template>
                    </view>
                </scroll-view>
                <scroll-view scroll-y class="category-list">
                    <view v-if="loading" class="category-empty"><u-loading-icon size="26" /><text>正在加载分类</text></view>
                    <view v-else-if="!list.length" class="category-empty">
                        <u-icon name="list-dot" color="#cbd5e1" size="30" />
                        <text>没有找到可用分类</text>
                    </view>
                    <view v-for="item in list" v-else :key="item.category_path" class="category-row" @click="choose(item)">
                        <view class="category-row__copy">
                            <text class="category-row__name">{{ item.label }}</text>
                            <text v-if="keyword" class="category-row__path">{{ item.path_text || item.category_path }}</text>
                        </view>
                        <text v-if="Number(item.product_count || 0) > 0" class="category-row__count">{{ item.product_count }} 个型号</text>
                        <u-icon :name="Number(item.is_leaf || 0) === 1 ? 'checkmark-circle' : 'arrow-right'"
                            :color="Number(item.is_leaf || 0) === 1 ? '#2563eb' : '#cbd5e1'" size="18" />
                    </view>
                </scroll-view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { getMobileErpGoodsCatalogHierarchy } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    modelValue?: string
    placeholder?: string
    disabled?: boolean
    showPath?: boolean
}>(), {
    modelValue: '',
    placeholder: '请选择末级分类',
    disabled: false,
    showPath: false,
})
const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void
    (event: 'change', value: { category_name: string; category_path: string } | null): void
}>()

const visible = ref(false)
const loading = ref(false)
const keyword = ref('')
const list = ref<any[]>([])
const stack = ref<any[]>([])
const leafName = computed(() => String(props.modelValue || '').split('/').filter(Boolean).pop() || '')
const pathText = computed(() => String(props.modelValue || '').replace(/\//g, ' / '))

function open() {
    if (props.disabled) return
    visible.value = true
    keyword.value = ''
    stack.value = []
    load()
}
function close() { visible.value = false }
async function load(parent: any = null) {
    loading.value = true
    try {
        const params = parent
            ? { node_type: 'category', category_path: parent.category_path, category_only: 1, limit: 200 }
            : { node_type: 'root', category_only: 1, limit: 200 }
        const res: any = await getMobileErpGoodsCatalogHierarchy(params)
        list.value = Array.isArray(res?.data?.list) ? res.data.list : []
    } finally { loading.value = false }
}
async function search() {
    if (!keyword.value.trim()) return resetSearch()
    loading.value = true
    try {
        const res: any = await getMobileErpGoodsCatalogHierarchy({
            node_type: 'root', category_only: 1, keyword: keyword.value.trim(), limit: 100,
        })
        list.value = Array.isArray(res?.data?.list) ? res.data.list : []
    } finally { loading.value = false }
}
function resetSearch() {
    keyword.value = ''
    load(stack.value[stack.value.length - 1] || null)
}
function choose(item: any) {
    if (Number(item.is_leaf || 0) === 1) {
        const categoryPath = String(item.category_path || '')
        const categoryName = String(item.label || categoryPath.split('/').pop() || '')
        emit('update:modelValue', categoryPath)
        emit('change', { category_name: categoryName, category_path: categoryPath })
        close()
        return
    }
    stack.value.push(item)
    load(item)
}
function backTo(index: number) {
    stack.value = index < 0 ? [] : stack.value.slice(0, index + 1)
    load(stack.value[stack.value.length - 1] || null)
}
</script>

<style scoped lang="scss">
.category-trigger { min-height:88rpx; display:flex; align-items:center; justify-content:space-between; gap:16rpx; padding:0 22rpx; border:1px solid #dbe4f0; border-radius:16rpx; background:#fff; }
.category-trigger--disabled { opacity:.55; }
.category-trigger__copy { min-width:0; flex:1; display:flex; flex-direction:column; gap:4rpx; }
.category-trigger__value { color:#334155; font-size:27rpx; font-weight:600; }
.category-trigger__placeholder { color:#94a3b8; font-size:27rpx; }
.category-trigger__path { overflow:hidden; color:#94a3b8; font-size:21rpx; text-overflow:ellipsis; white-space:nowrap; }
.category-popup { min-height:760rpx; max-height:88vh; background:#f8fafc; }
.category-head { display:flex; align-items:center; justify-content:space-between; padding:30rpx 30rpx 22rpx; background:#fff; }
.category-head__title,.category-head__sub { display:block; }
.category-head__title { color:#0f172a; font-size:34rpx; font-weight:700; }
.category-head__sub { margin-top:5rpx; color:#94a3b8; font-size:23rpx; }
.category-search { padding:0 24rpx 20rpx; background:#fff; }
.category-breadcrumb { width:100%; background:#fff; border-top:1px solid #f1f5f9; }
.category-breadcrumb__inner { display:inline-flex; align-items:center; gap:10rpx; padding:20rpx 24rpx; white-space:nowrap; }
.category-breadcrumb__item { color:#64748b; font-size:24rpx; }
.category-breadcrumb__item.active { color:#2563eb; font-weight:600; }
.category-list { height:590rpx; padding:18rpx 24rpx; box-sizing:border-box; }
.category-row { display:flex; align-items:center; gap:16rpx; min-height:92rpx; margin-bottom:14rpx; padding:0 24rpx; border:1px solid #e2e8f0; border-radius:18rpx; background:#fff; }
.category-row__copy { min-width:0; flex:1; display:flex; flex-direction:column; gap:5rpx; }
.category-row__name { color:#1e293b; font-size:28rpx; font-weight:600; }
.category-row__path { overflow:hidden; color:#94a3b8; font-size:21rpx; text-overflow:ellipsis; white-space:nowrap; }
.category-row__count { flex-shrink:0; color:#94a3b8; font-size:21rpx; }
.category-empty { height:380rpx; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16rpx; color:#94a3b8; font-size:25rpx; }
</style>
