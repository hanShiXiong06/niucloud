<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header><div class="flex items-start justify-between"><div><div class="text-[18px] font-semibold text-[#1d2939]">收益展示</div><div class="mt-[6px] text-[13px] text-[#667085]">数据由运营每天粘贴，仅用于项目案例展示，不参与本系统经营统计或财务核算。</div></div><el-button type="primary" :disabled="!projectId" @click="editorVisible = true">粘贴今日榜单</el-button></div></template>
            <el-alert type="warning" :closable="false" title="请确保展示数据已获得使用授权，并保留数据来源。收益案例不应表述为对客户的收益承诺。" class="mb-[16px]" />
            <div class="mb-[18px] flex items-center gap-[10px] rounded-[10px] bg-[#f8fafc] p-[14px]"><el-select v-model="projectId" class="!w-[240px]" placeholder="选择项目" @change="loadBoard"><el-option v-for="item in projects" :key="item.id" :label="item.title" :value="item.id" /></el-select><el-date-picker v-model="dateValue" type="date" value-format="YYYYMMDD" placeholder="指定日期（默认最新）" @change="loadBoard"/><el-button @click="loadBoard">刷新</el-button></div>
            <div v-if="rows.length" class="mb-[14px] flex items-end justify-between"><div><div class="text-[16px] font-semibold">{{ rows[0]?.board_title || '商家收益排行' }}</div><div class="mt-[4px] text-[12px] text-[#98a2b3]">展示日期 {{ dateText(rows[0]?.stat_date) }} · {{ rows.length }} 家门店</div></div><div class="text-[12px] text-[#98a2b3]">最后更新 {{ timeText(rows[0]?.update_at) }}</div></div>
            <el-table v-loading="loading" :data="rows" size="large"><el-table-column prop="rank_no" label="排名" width="90"><template #default="{ row }"><span class="rank" :class="`rank-${row.rank_no}`">{{ row.rank_no }}</span></template></el-table-column><el-table-column prop="store_name" label="门店" min-width="260"/><el-table-column label="收益" min-width="160"><template #default="{ row }"><b class="text-[#f04438]">¥{{ Number(row.income_amount || 0).toFixed(2) }}</b></template></el-table-column><el-table-column prop="source_text" label="原始文本" min-width="320" show-overflow-tooltip/></el-table>
            <el-empty v-if="!loading && !rows.length" description="暂无展示数据，选择项目后粘贴运营榜单即可" />
        </el-card>

        <el-dialog v-model="editorVisible" title="粘贴收益榜单" width="680px" destroy-on-close>
            <div class="mb-[12px] text-[13px] leading-[21px] text-[#667085]">支持直接粘贴群内文本，例如：<code>Top01: 宇通配件（城隍庙商城店） 收益 915.17</code>。系统会解析日期、排名、门店和金额，并整体替换同一天的展示数据。</div>
            <el-form label-position="top"><el-form-item label="榜单日期（文本中有 20260818 时可不填）"><el-date-picker v-model="editorDate" type="date" value-format="YYYYMMDD" class="!w-full"/></el-form-item><el-form-item label="榜单原文"><el-input v-model="rawText" type="textarea" :rows="15" placeholder="美团闪购 20260818 商家收益排行&#10;Top01: 宇通配件（城隍庙商城店） 收益 915.17&#10;Top02: 志辉数码商城（环城西路店） 收益 526.01" /></el-form-item></el-form>
            <template #footer><el-button @click="editorVisible = false">取消</el-button><el-button type="primary" :loading="saving" @click="replaceBoard">解析并发布</el-button></template>
        </el-dialog>
    </div>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getProjectCenterIncomeBoard, getProjectCenterProjects, replaceProjectCenterIncomeBoard } from '@/addon/hsx_project_center/api'
const projects = ref<any[]>([]), projectId = ref<any>(), dateValue = ref<any>(''), editorDate = ref<any>(''), rows = ref<any[]>([]), loading = ref(false), editorVisible = ref(false), saving = ref(false), rawText = ref('')
const pageRows = (data:any) => data?.data || data?.list || []
function timeText(value:any) { if (!value) return '-'; return new Date(Number(value) * 1000).toLocaleString() }
function dateText(value:any) { if (!value) return '-'; const text = String(value); return text.length === 8 ? `${text.slice(0,4)}-${text.slice(4,6)}-${text.slice(6)}` : text }
async function loadProjects() { const res:any = await getProjectCenterProjects({ page:1, limit:100 }); projects.value = pageRows(res.data); if (!projectId.value && projects.value.length) projectId.value = projects.value[0].id }
async function loadBoard() { if (!projectId.value) return; loading.value = true; try { const res:any = await getProjectCenterIncomeBoard({ project_id: projectId.value, stat_date: dateValue.value ? Number(dateValue.value) : 0 }); rows.value = res.data || [] } finally { loading.value = false } }
async function replaceBoard() { if (!rawText.value.trim()) return ElMessage.warning('请粘贴榜单文本'); saving.value = true; try { const res:any = await replaceProjectCenterIncomeBoard({ project_id: projectId.value, stat_date: editorDate.value ? Number(editorDate.value) : 0, text: rawText.value }); ElMessage.success(`已发布 ${res.data?.count || 0} 条展示数据`); editorVisible.value = false; rawText.value = ''; dateValue.value = ''; await loadBoard() } finally { saving.value = false } }
onMounted(async () => { await loadProjects(); await loadBoard() })
</script>
<style scoped>.rank { display:inline-flex; width:28px; height:28px; align-items:center; justify-content:center; border-radius:8px; background:#f2f4f7; color:#667085; font-weight:700}.rank-1{background:#fff3d6;color:#b54708}.rank-2{background:#f2f4f7;color:#475467}.rank-3{background:#fff1eb;color:#c4320a}</style>
