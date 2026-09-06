<template>
    <div class="area-config">
        <el-alert class="mb-[14px]" type="info" :closable="false" show-icon>
            <template #title>只勾选“允许参与”的地区。勾选省会自动勾选全部市和区县，勾选市会自动勾选全部区县；未勾选地区暂不可参与。</template>
        </el-alert>
        <div class="grid grid-cols-2 gap-x-[14px]">
            <el-form-item label="查询器标题"><el-input v-model="local.title" maxlength="50" show-word-limit /></el-form-item>
            <el-form-item label="查询按钮"><el-input v-model="local.button_text" maxlength="20" show-word-limit /></el-form-item>
        </div>
        <el-form-item label="查询前提示"><el-input v-model="local.tips" type="textarea" :rows="2" maxlength="200" show-word-limit /></el-form-item>
        <div class="grid grid-cols-2 gap-x-[14px]">
            <el-form-item label="可以参与提示"><el-input v-model="local.eligible_text" type="textarea" :rows="2" maxlength="200" show-word-limit /></el-form-item>
            <el-form-item label="暂不可参与提示"><el-input v-model="local.ineligible_text" type="textarea" :rows="2" maxlength="200" show-word-limit /></el-form-item>
        </div>

        <div class="area-picker">
            <div class="area-picker-head">
                <div><div class="font-medium text-[#344054]">可参与地区</div><div class="mt-[4px] text-[12px] text-[#98a2b3]">已选 {{ local.allowed_area_ids.length }} 个有效范围</div></div>
                <el-input v-model="keyword" clearable class="!w-[220px]" placeholder="搜索省、市、区" />
            </div>
            <div v-loading="loading" class="area-tree-wrap">
                <el-tree ref="treeRef" :data="treeData" node-key="id" show-checkbox default-expand-all
                    :props="treeProps" :filter-node-method="filterNode" @check="syncCheckedAreas">
                    <template #default="{ data }">
                        <div class="area-node"><span>{{ data.name }}</span><small>{{ levelText(data.level) }}</small></div>
                    </template>
                </el-tree>
            </div>
            <el-empty v-if="!loading && !treeData.length" :image-size="60" description="系统地区数据为空，请先刷新系统地区数据" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, reactive, ref, watch } from 'vue'
import { getAreatree } from '@/app/api/sys'

const props = defineProps({ modelValue: { type: Object, default: () => ({}) } })
const emit = defineEmits(['update:modelValue'])
const defaults = {
    enabled: 1,
    allowed_area_ids: [] as number[],
    title: '先查询您的地区是否可参与',
    tips: '选择门店所在省、市、区，确认可以参与后再付款。',
    button_text: '查询是否可以参加',
    eligible_text: '当前地区可以参加，请继续完成付款。',
    ineligible_text: '当前地区暂不在可参与范围内，请联系工作人员确认。',
    enabled_at: 0
}
const local = reactive<any>({ ...defaults })
const loading = ref(false), keyword = ref(''), treeData = ref<any[]>([]), treeRef = ref<any>()
const treeProps = { children: 'child', label: 'name' }
let hydrating = false

function hydrate(value:any) {
    hydrating = true
    Object.assign(local, defaults, value || {}, {
        allowed_area_ids: Array.from(new Set((value?.allowed_area_ids || []).map(Number).filter((id:number) => id > 0)))
    })
    nextTick(() => {
        treeRef.value?.setCheckedKeys(local.allowed_area_ids, false)
        hydrating = false
    })
}
function syncCheckedAreas() {
    if (hydrating) return
    const checkedNodes = treeRef.value?.getCheckedNodes(false, false) || []
    const checkedSet = new Set(checkedNodes.map((node:any) => Number(node.id)).filter((id:number) => id > 0))
    local.allowed_area_ids = checkedNodes
        .filter((node:any) => Number(node.id) > 0 && !checkedSet.has(Number(node.pid || 0)))
        .map((node:any) => Number(node.id))
}
function filterNode(value:string, data:any) { return !value || String(data?.name || '').includes(value.trim()) }
function levelText(level:number) { return ({ 1:'省', 2:'市', 3:'区县' } as Record<number,string>)[Number(level)] || '' }

watch(() => props.modelValue, hydrate, { immediate: true, deep: true })
watch(keyword, value => treeRef.value?.filter(value))
watch(local, value => {
    if (hydrating) return
    emit('update:modelValue', JSON.parse(JSON.stringify(value)))
}, { deep: true })

onMounted(async () => {
    loading.value = true
    try {
        const res:any = await getAreatree(3)
        treeData.value = Array.isArray(res.data) ? res.data : []
        await nextTick()
        treeRef.value?.setCheckedKeys(local.allowed_area_ids, false)
    } finally { loading.value = false }
})
</script>

<style scoped>
.area-config{margin:12px 0 18px;padding:16px;border:1px solid #dfe7f3;border-radius:12px;background:#fbfcff}
.area-picker{overflow:hidden;border:1px solid #e4e9f1;border-radius:10px;background:#fff}.area-picker-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 14px;border-bottom:1px solid #edf0f5}.area-tree-wrap{height:330px;overflow:auto;padding:10px 12px}.area-node{display:flex;min-width:0;flex:1;align-items:center;justify-content:space-between;gap:12px}.area-node small{flex:none;color:#98a2b3;font-size:11px}
</style>
