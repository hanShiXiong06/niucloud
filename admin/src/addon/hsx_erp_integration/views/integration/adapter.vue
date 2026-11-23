<template>
    <ConsolePage title="可用适配器" subtitle="这里只列出本站可用的 ERP 对接插件。适配器可用，不代表账号已连接。" :loading="q.loading" :error="q.error" @reload="q.load">
        <template #actions><el-button @click="q.load">刷新</el-button></template>
        <el-table :data="q.rows" row-key="code" empty-text="本站暂无可用适配器。请先安装对应的 ERP 插件，并分配给本站。">
            <el-table-column label="ERP 系统" min-width="180"><template #default="{row}"><strong>{{ row.name }}</strong><div class="ic-muted">{{ row.code }} · v{{ row.version }}</div></template></el-table-column>
            <el-table-column label="适配器声明的业务" min-width="240"><template #default="{row}">{{ row.supported_business_types.map((v: string) => label(business,v)).join('、') || '—' }}</template></el-table-column>
            <el-table-column label="需要对应的资料" min-width="180"><template #default="{row}">{{ Object.keys(row.mapping_requirements || {}).map(v => label(resource,v)).join('、') || '无' }}</template></el-table-column>
            <el-table-column label="操作" width="110"><template #default="{row}"><el-button v-if="canCreate" link type="primary" @click="setup(row.code)">配置连接</el-button><span v-else class="ic-muted">只读</span></template></el-table-column>
        </el-table>
    </ConsolePage>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import ConsolePage from './components/ConsolePage.vue'
import { api } from '../../api/console'
import { business, resource, label, permission, useQuery } from './useConsole'
const router = useRouter()
const canCreate = computed(() => permission('connection_add') && permission('connection_adapters') && router.hasRoute('hsx_erp_integration_connection'))
const q = reactive(useQuery(async () => { const { list } = await api.adapters(); return { list, count: list.length } }))
function setup(code: string) { router.push({ name: 'hsx_erp_integration_connection', query: { adapter: code } }) }
onMounted(q.load)
</script>
