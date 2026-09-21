<template>
    <div class="mall-category">
        <el-cascader :model-value="modelValue || undefined" :options="tree" :props="{ emitPath: false }"
            filterable clearable :disabled="loading" placeholder="请选择或搜索商城分类" class="w-full"
            @update:model-value="value => emit('update:modelValue', Number(value || 0))" />
        <div v-if="loading" class="mall-category__hint">正在加载本站商城分类…</div>
        <div v-else-if="error" class="mall-category__error">{{ error }} <el-button link type="primary" @click="load">重新加载</el-button></div>
        <div v-else-if="!options.length" class="mall-category__error">暂无可选分类，请先在商城创建并启用分类，再点击 <el-button link type="primary" @click="load">刷新</el-button></div>
        <div v-else-if="modelValue && !options.some(item => item.value === modelValue)" class="mall-category__error">原分类已失效，请重新选择。</div>
        <div v-else class="mall-category__hint">客户按此分类查找商品，不修改 ERP 分类。</div>
    </div>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getErpListingCatalog } from '@/addon/hsx_erp/api/erp'
defineProps<{ modelValue: number }>()
const emit = defineEmits(['update:modelValue'])
const options = ref<any[]>([])
const loading = ref(false)
const error = ref('')
const tree = computed(() => {
    const roots: any[] = []
    for (const item of options.value) {
        let level = roots
        item.path.forEach((id: number, index: number) => {
            let node = level.find(value => value.value === id)
            if (!node) { node = { value: id, label: item.names[index] }; level.push(node) }
            if (index < item.path.length - 1) { node.children ||= []; level = node.children }
        })
    }
    return roots
})
async function load() {
    if (loading.value) return
    loading.value = true
    error.value = ''
    try { const res: any = await getErpListingCatalog(); options.value = res?.data?.options || [] }
    catch (e: any) { options.value = []; error.value = e?.message || e?.msg || '商城分类加载失败，请重试' }
    finally { loading.value = false }
}
onMounted(load)
</script>
<style scoped>
.mall-category{width:100%;min-width:0}.mall-category__hint,.mall-category__error{font-size:12px;line-height:1.6;margin-top:6px;color:#64748b}.mall-category__error{color:#b45309}
</style>
