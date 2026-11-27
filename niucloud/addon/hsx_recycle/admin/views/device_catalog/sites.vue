<template>
    <section>
        <el-alert title="使用公共库与维护公共库是两回事" description="站点自行选择资料来源；这里只授权谁可以新增、修改、删除公共资料。授权后仍遵循员工原有操作权限，修改会影响全部使用公共库的站点。" type="warning" :closable="false" show-icon />
        <div class="site-search"><el-input v-model.trim="keyword" placeholder="搜索站点名称 / ID" clearable @keyup.enter="search" @clear="search" /><el-button type="primary" @click="search">查询</el-button></div>
        <el-alert v-if="error" :title="error" type="error" :closable="false" />
        <el-table :data="rows" v-loading="loading" border empty-text="没有匹配的站点">
            <el-table-column prop="site_name" label="站点" min-width="200" />
            <el-table-column prop="site_id" label="站点 ID" width="120" />
            <el-table-column label="当前资料来源" width="140"><template #default="{ row }"><el-tag :type="row.catalog.effective ? 'success' : 'info'">{{ row.catalog.effective ? '公共库' : '本站库' }}</el-tag></template></el-table-column>
            <el-table-column label="允许维护公共库" min-width="200"><template #default="{ row }"><el-switch :model-value="row.catalog.can_manage_public" :loading="saving === row.site_id" :disabled="saving > 0" @change="value => changeGrant(row, Boolean(value))" /><span class="grant-label">{{ row.catalog.can_manage_public ? '已授权' : '未授权，仅可使用' }}</span></template></el-table-column>
        </el-table>
        <el-pagination v-model:current-page="page" :page-size="20" :total="total" layout="total, prev, pager, next" @current-change="load" />
    </section>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { getCatalogSites, grantCatalogSite } from '@/addon/hsx_recycle/api/device_catalog'
const keyword = ref(''), page = ref(1), total = ref(0), rows = ref<any[]>([]), loading = ref(false), saving = ref(0), error = ref('')
async function load() {
    loading.value = true; error.value = ''
    try { const response = await getCatalogSites({ keyword: keyword.value, page: page.value }); rows.value = response.data.data; total.value = response.data.total }
    catch { error.value = '站点列表加载失败，请重新查询。' }
    finally { loading.value = false }
}
function search() { page.value = 1; load() }
async function changeGrant(row: any, allowed: boolean) {
    try { await ElMessageBox.confirm(allowed ? `允许“${row.site_name}”维护公共库？该站点修改将影响所有使用公共库的站点。` : `撤销“${row.site_name}”的公共库维护权限？仍可正常使用公共资料。`, '公共库维护授权', { type: 'warning', confirmButtonText: allowed ? '确认授权' : '撤销授权', cancelButtonText: '取消' }) }
    catch { return }
    saving.value = row.site_id
    try { const response = await grantCatalogSite(row.site_id, allowed); row.catalog = response.data }
    catch { /* 保留原状态。 */ }
    finally { saving.value = 0 }
}
onMounted(load)
</script>
<style scoped>.site-search { display:flex; gap:10px; margin:16px 0; max-width:430px; }.grant-label { margin-left:12px; color:var(--el-text-color-secondary); }.el-pagination { margin-top:16px; justify-content:flex-end; }</style>
