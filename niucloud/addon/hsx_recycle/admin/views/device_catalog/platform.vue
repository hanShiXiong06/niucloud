<template>
    <HsxPage title="公共型号与质检模板库">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon />
        <el-card v-if="!preview?.initialized" shadow="never" v-loading="loading">
            <h3>将 100005 的现有资料转为公共库</h3>
            <p>保留型号、模板、字段和选项的原 ID，只改变资料归属。不复制、不合并其他站点，不处理订单、设备、库存或财务数据。</p>
            <div v-if="preview" class="counts"><el-tag>型号 / 分类 {{ preview.counts.recycle_device_model_dict }}</el-tag><el-tag>质检模板 {{ preview.counts.recycle_check_template }}</el-tag><el-tag>字段 {{ preview.counts.recycle_check_field }}</el-tag><el-tag>选项 {{ preview.counts.recycle_check_option }}</el-tag></div>
            <el-alert v-for="message in preview?.blockers || []" :key="message" :title="message" type="warning" :closable="false" />
            <div class="init-actions"><el-button :loading="loading" @click="load">重新预检</el-button><el-button type="primary" :disabled="!preview || !!preview.blockers.length || loading" :loading="saving" @click="promote">确认转为公共库</el-button></div>
            <p class="hint">执行前请备份数据库。成功后 100005 自动使用公共库；其他站点需自行开启，不删除它们原有资料。此动作只执行一次。</p>
        </el-card>
        <template v-else>
            <el-alert title="公共资料库已启用 · 原 100005 的资料 ID 保持不变" type="success" :closable="false" show-icon />
            <el-tabs v-model="tab" class="catalog-tabs">
                <el-tab-pane label="型号分类" name="model"><ModelList v-if="tab === 'model'" /></el-tab-pane>
                <el-tab-pane label="质检模板" name="template"><TemplateList v-if="tab === 'template'" /></el-tab-pane>
                <el-tab-pane label="导入检测表" name="import"><CatalogImport v-if="tab === 'import'" /></el-tab-pane>
                <el-tab-pane label="选项级别" name="severity"><SeverityList v-if="tab === 'severity'" /></el-tab-pane>
                <el-tab-pane label="站点维护授权" name="sites"><CatalogSites v-if="tab === 'sites'" /></el-tab-pane>
            </el-tabs>
        </template>
    </HsxPage>
</template>
<script setup lang="ts">
import { defineAsyncComponent, onMounted, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { HsxPage } from '@/addon/hsx_components/core'
import { previewPublicCatalog, promotePublicCatalog } from '@/addon/hsx_recycle/api/device_catalog'
import CatalogSites from './sites.vue'
const ModelList = defineAsyncComponent(() => import('../recycle_device_model_dict/list.vue'))
const TemplateList = defineAsyncComponent(() => import('../check/template.vue'))
const CatalogImport = defineAsyncComponent(() => import('../check/catalog.vue'))
const SeverityList = defineAsyncComponent(() => import('../check/severity.vue'))
const preview = ref<any>(), tab = ref('model'), loading = ref(false), saving = ref(false), error = ref('')
async function load() {
    loading.value = true; error.value = ''
    try { const response = await previewPublicCatalog(); preview.value = response.data }
    catch (e: any) { error.value = e?.msg || e?.message || '预检失败，请重试'; preview.value = undefined }
    finally { loading.value = false }
}
async function promote() {
    try { await ElMessageBox.confirm('确认已经备份数据库？将 100005 的现有资料原地转为公共库，保留原 ID。100005 自动启用公共库，其他站点不变。', '确认转为公共库', { type: 'warning', confirmButtonText: '已备份，确认转换', cancelButtonText: '暂不执行' }) }
    catch { return }
    saving.value = true; error.value = ''
    try { await promotePublicCatalog(preview.value.fingerprint); await load() }
    catch (e: any) { error.value = e?.msg || e?.message || '转换未完成，请重新预检；不要手工改编号' }
    finally { saving.value = false }
}
onMounted(load)
</script>
<style scoped>h3 { margin:0 0 12px; }p { line-height:1.8; }.counts { display:flex; flex-wrap:wrap; gap:12px; margin:16px 0; }.init-actions { display:flex; gap:10px; margin-top:20px; }.hint { font-size:13px; color:var(--el-text-color-secondary); }.catalog-tabs { margin-top:16px; }.el-alert + .el-alert { margin-top:8px; }</style>
