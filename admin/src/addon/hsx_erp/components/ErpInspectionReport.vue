<template>
    <section class="inspection-report">
        <div class="inspection-report__header">
            <div><strong>质检报告</strong><span class="inspection-report__muted">{{ items.length }} 项</span></div>
            <div class="inspection-report__badges">
                <el-tag size="small" type="success" effect="plain">正常 {{ report?.counts?.normal || 0 }}</el-tag>
                <el-tag size="small" type="warning" effect="plain">需关注 {{ report?.counts?.general || 0 }}</el-tag>
                <el-tag size="small" type="danger" effect="plain">异常 {{ report?.counts?.abnormal || 0 }}</el-tag>
                <el-tag v-if="report?.counts?.unknown" size="small" type="info" effect="plain">未分级 {{ report.counts.unknown }}</el-tag>
            </div>
        </div>
        <template v-if="items.length">
            <div class="inspection-report__filters">
                <el-input v-model="keyword" placeholder="搜索项目或结果，如电池、屏幕" clearable />
                <el-checkbox v-model="attentionOnly">只看需关注／异常</el-checkbox>
            </div>
            <div v-if="visibleItems.length" class="inspection-report__grid">
                <div v-for="item in visibleItems" :key="item.key" class="inspection-report__item" :class="`inspection-report__item--${item.severity}`">
                    <div class="inspection-report__name">{{ item.name }}</div>
                    <div class="inspection-report__value">{{ item.value }}</div>
                </div>
            </div>
            <div v-else class="inspection-report__empty">没有符合条件的质检项目</div>
            <el-button v-if="filteredItems.length > 8" link type="primary" class="inspection-report__expand" @click="expanded = !expanded">
                {{ expanded ? '收起' : `展开全部 ${filteredItems.length} 项` }}
            </el-button>
        </template>
        <div v-else class="inspection-report__empty">暂无结构化质检记录</div>
        <details v-if="report?.legacy_text" class="inspection-report__legacy">
            <summary>查看历史质检原文</summary><p>{{ report.legacy_text }}</p>
        </details>
        <div class="inspection-report__notes">
            <strong>人工备注</strong>
            <div v-if="!report?.manual_notes?.length" class="inspection-report__muted">暂无人工备注</div>
            <div v-for="(note, index) in report?.manual_notes || []" :key="index" class="inspection-report__note">
                <span>{{ note.label }}</span><p>{{ note.text }}</p>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
interface InspectionItem { key: string; name: string; value: string; severity: string }
interface InspectionReport { items?: InspectionItem[]; counts?: Record<string, number>; manual_notes?: { label: string; text: string }[]; legacy_text?: string }
const props = defineProps<{ report?: InspectionReport }>()
const keyword = ref('')
const attentionOnly = ref(false)
const expanded = ref(false)
const items = computed(() => props.report?.items || [])
const filteredItems = computed(() => {
    const query = keyword.value.trim().toLowerCase()
    return items.value.filter(item => (!attentionOnly.value || ['general', 'abnormal'].includes(item.severity)) && (!query || `${item.name} ${item.value}`.toLowerCase().includes(query)))
})
const visibleItems = computed(() => expanded.value ? filteredItems.value : filteredItems.value.slice(0, 8))
watch(() => props.report, () => { keyword.value = ''; attentionOnly.value = false; expanded.value = false })
watch([keyword, attentionOnly], () => { expanded.value = false })
</script>

<style scoped>
.inspection-report{margin-top:20px;padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#334155;font-size:13px}
.inspection-report__header,.inspection-report__badges,.inspection-report__filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.inspection-report__header{justify-content:space-between}strong{font-size:14px;color:#0f172a}.inspection-report__muted{color:#94a3b8;margin-left:10px;font-size:12px}
.inspection-report__filters{margin:16px 0}.inspection-report__filters .el-input{width:280px;max-width:100%}.inspection-report__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.inspection-report__item{display:grid;grid-template-columns:minmax(80px,1fr) minmax(0,1.4fr);gap:10px;padding:10px 12px;background:#f8fafc;border-radius:6px;line-height:1.6}.inspection-report__name{color:#64748b}.inspection-report__value{overflow-wrap:anywhere}.inspection-report__item--general{background:#fffbeb}.inspection-report__item--abnormal{background:#fef2f2;color:#b91c1c}
.inspection-report__expand{margin-top:12px}.inspection-report__empty{padding:18px 0;color:#94a3b8}.inspection-report__notes{border-top:1px solid #e2e8f0;margin-top:16px;padding-top:16px}.inspection-report__notes>.inspection-report__muted{margin:10px 0 0}.inspection-report__note{margin-top:12px}.inspection-report__note>span{font-size:12px;color:#94a3b8}.inspection-report__note p,.inspection-report__legacy p{margin:5px 0;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.8}.inspection-report__legacy{margin:10px 0;color:#64748b}.inspection-report__legacy summary{cursor:pointer}
@media(max-width:900px){.inspection-report__grid{grid-template-columns:1fr}.inspection-report{padding:14px}}
</style>
