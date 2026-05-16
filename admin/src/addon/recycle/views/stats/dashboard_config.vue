<template>
  <div class="main-container dashboard-config-page">
    <el-card class="box-card !border-none" shadow="never" v-loading="loading">
      <template #header>
        <div class="page-header">
          <div>
            <div class="text-page-title">首页配置</div>
            <div class="page-subtitle">按组件控制回收概况首页展示内容，可限制到角色或指定员工。</div>
          </div>
          <div class="header-actions">
            <el-button @click="fetchData">刷新</el-button>
            <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
          </div>
        </div>
      </template>

      <el-alert
        class="mb-[16px]"
        type="info"
        :closable="false"
        show-icon
        title="角色和员工都为空时，表示该组件默认对普通员工可见；站点管理员始终可见。"
      />

      <el-table :data="widgetList" size="large" row-key="widget_id">
        <el-table-column prop="widget_name" label="组件名称" min-width="180">
          <template #default="{ row }">
            <el-input v-model.trim="row.widget_name" maxlength="100" />
            <div class="field-tip">{{ row.widget_key }}</div>
          </template>
        </el-table-column>

        <el-table-column prop="widget_type" label="类型" width="110">
          <template #default="{ row }">
            <el-tag>{{ typeName(row.widget_type) }}</el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="data_key" label="指标标识" min-width="180" show-overflow-tooltip />

        <el-table-column prop="data_scope" label="数据范围" width="150">
          <template #default="{ row }">
            <el-select v-model="row.data_scope">
              <el-option label="看自己" value="own" />
              <el-option label="看全站" value="site" />
              <el-option label="分配范围" value="assigned" />
              <el-option label="无数据" value="none" />
            </el-select>
          </template>
        </el-table-column>

        <el-table-column label="可见角色" min-width="220">
          <template #default="{ row }">
            <el-select
              v-model="row.role_ids"
              multiple
              clearable
              collapse-tags
              collapse-tags-tooltip
              placeholder="不限制角色"
            >
              <el-option
                v-for="role in roleOptions"
                :key="role.role_id"
                :label="role.role_name"
                :value="role.role_id"
              />
            </el-select>
          </template>
        </el-table-column>

        <el-table-column label="指定员工" min-width="220">
          <template #default="{ row }">
            <el-select
              v-model="row.uids"
              multiple
              clearable
              filterable
              collapse-tags
              collapse-tags-tooltip
              placeholder="不限制员工"
            >
              <el-option
                v-for="user in userOptions"
                :key="user.uid"
                :label="userLabel(user)"
                :value="user.uid"
              />
            </el-select>
          </template>
        </el-table-column>

        <el-table-column prop="sort" label="排序" width="110" align="center">
          <template #default="{ row }">
            <el-input-number v-model="row.sort" :min="0" :max="9999" controls-position="right" />
          </template>
        </el-table-column>

        <el-table-column prop="status" label="启用" width="100" align="center">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0" />
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { onMounted, ref } from 'vue'
import { allRole } from '@/app/api/sys'
import { getDashboardWidgets, saveDashboardWidgets, getUserList } from '@/addon/recycle/api/stats'

const loading = ref(false)
const saving = ref(false)
const widgetList = ref<any[]>([])
const roleOptions = ref<any[]>([])
const userOptions = ref<any[]>([])

const typeName = (type: string) => {
  const map: Record<string, string> = {
    stat: '指标',
    chart: '图表',
    table: '表格',
    action: '动作',
    section: '区块'
  }
  return map[type] || type
}

const userLabel = (user: any) => {
  return user.real_name || user.username || `UID ${user.uid}`
}

const normalizeWidget = (row: any) => {
  return {
    ...row,
    role_ids: Array.isArray(row.role_ids) ? row.role_ids.map((id: any) => Number(id)).filter(Boolean) : [],
    uids: Array.isArray(row.uids) ? row.uids.map((id: any) => Number(id)).filter(Boolean) : [],
    status: Number(row.status || 0),
    sort: Number(row.sort || 0)
  }
}

const fetchOptions = async () => {
  const [roleRes, userRes] = await Promise.all([
    allRole(),
    getUserList()
  ])
  roleOptions.value = Array.isArray(roleRes.data)
    ? roleRes.data.map((role: any) => ({ ...role, role_id: Number(role.role_id) }))
    : []
  userOptions.value = Array.isArray(userRes.data) ? userRes.data : []
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await getDashboardWidgets()
    widgetList.value = Array.isArray(res.data) ? res.data.map(normalizeWidget) : []
  } finally {
    loading.value = false
  }
}

const save = async () => {
  saving.value = true
  try {
    await saveDashboardWidgets({ widgets: widgetList.value })
    fetchData()
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  loading.value = true
  try {
    await fetchOptions()
    await fetchData()
  } finally {
    loading.value = false
  }
})
</script>

<style lang="scss" scoped>
.dashboard-config-page {
  .page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  .page-subtitle {
    margin-top: 6px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .field-tip {
    margin-top: 4px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  :deep(.el-select) {
    width: 100%;
  }

  :deep(.el-input-number) {
    width: 92px;
  }
}
</style>
