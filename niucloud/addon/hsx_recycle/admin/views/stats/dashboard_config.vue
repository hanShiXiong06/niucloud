<template>
  <div class="main-container dashboard-config-page">
    <el-card class="box-card !border-none" shadow="never" v-loading="loading">
      <template #header>
        <div class="page-header">
          <div>
            <div class="text-page-title">看板配置</div>
            <div class="page-subtitle">配置每个指标的可见范围，管理员始终可见所有内容</div>
          </div>
          <div class="header-actions">
            <el-button @click="fetchData">刷新</el-button>
            <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
          </div>
        </div>
      </template>

      <div v-for="group in groupedWidgets" :key="group.label" class="widget-group">
        <div class="group-header">
          <span class="group-title">{{ group.label }}</span>
          <span class="group-count">{{ group.items.length }} 项</span>
        </div>

        <div class="widget-cards">
          <div
            v-for="widget in group.items"
            :key="widget.widget_id"
            class="widget-card"
            :class="{ 'widget-card--disabled': !widget.status }"
          >
            <div class="widget-card__top">
              <div class="widget-card__name">{{ widget.widget_name }}</div>
              <el-switch v-model="widget.status" :active-value="1" :inactive-value="0" size="small" />
            </div>

            <div class="widget-card__visibility">
              <el-radio-group v-model="widget._visibility" size="small" @change="onVisibilityChange(widget)">
                <el-radio-button value="all">全员可见</el-radio-button>
                <el-radio-button value="roles">指定角色</el-radio-button>
                <el-radio-button value="users">指定人员</el-radio-button>
              </el-radio-group>
            </div>

            <div v-if="widget._visibility === 'roles'" class="widget-card__detail">
              <el-select
                v-model="widget.role_ids"
                multiple
                placeholder="选择可见角色"
                collapse-tags
                collapse-tags-tooltip
                size="small"
              >
                <el-option
                  v-for="role in roleOptions"
                  :key="role.role_id"
                  :label="role.role_name"
                  :value="role.role_id"
                />
              </el-select>
            </div>

            <div v-if="widget._visibility === 'users'" class="widget-card__detail">
              <el-select
                v-model="widget.uids"
                multiple
                filterable
                placeholder="选择可见人员"
                collapse-tags
                collapse-tags-tooltip
                size="small"
              >
                <el-option
                  v-for="user in userOptions"
                  :key="user.uid"
                  :label="userLabel(user)"
                  :value="user.uid"
                />
              </el-select>
            </div>

            <div class="widget-card__footer">
              <span class="widget-card__scope">{{ scopeName(widget.data_scope) }}</span>
              <span class="widget-card__type">{{ typeName(widget.widget_type) }}</span>
            </div>
          </div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { onMounted, ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { allRole } from '@/app/api/sys'
import { getDashboardWidgets, saveDashboardWidgets, getUserList } from '@/addon/hsx_recycle/api/stats'

const loading = ref(false)
const saving = ref(false)
const widgetList = ref<any[]>([])
const roleOptions = ref<any[]>([])
const userOptions = ref<any[]>([])

const typeName = (type: string) => {
  const map: Record<string, string> = { stat: '指标', chart: '图表', table: '表格', action: '动作', section: '区块' }
  return map[type] || type
}

const scopeName = (scope: string) => {
  const map: Record<string, string> = { own: '个人数据', site: '全站数据', assigned: '分配范围', none: '无数据' }
  return map[scope] || scope
}

const userLabel = (user: any) => user.real_name || user.username || `UID ${user.uid}`

const getVisibility = (widget: any): string => {
  if (widget.role_ids?.length) return 'roles'
  if (widget.uids?.length) return 'users'
  return 'all'
}

const onVisibilityChange = (widget: any) => {
  if (widget._visibility === 'all') {
    widget.role_ids = []
    widget.uids = []
    widget.config = { ...widget.config, default_visible: true }
  } else if (widget._visibility === 'roles') {
    widget.uids = []
    widget.config = { ...widget.config, default_visible: false }
  } else if (widget._visibility === 'users') {
    widget.role_ids = []
    widget.config = { ...widget.config, default_visible: false }
  }
}

const groupedWidgets = computed(() => {
  const groups: Record<string, { label: string, items: any[] }> = {}
  const boardMap: Record<string, string> = {
    business: '业务看板',
    finance: '财务看板',
    user: '用户看板'
  }

  for (const w of widgetList.value) {
    const board = w.config?.board || ''
    const label = boardMap[board] || '通用指标'
    if (!groups[label]) groups[label] = { label, items: [] }
    groups[label].items.push(w)
  }

  return Object.values(groups)
})

const normalizeWidget = (row: any) => {
  const normalized = {
    ...row,
    role_ids: Array.isArray(row.role_ids) ? row.role_ids.map((id: any) => Number(id)).filter(Boolean) : [],
    uids: Array.isArray(row.uids) ? row.uids.map((id: any) => Number(id)).filter(Boolean) : [],
    config: {
      ...(row.config && typeof row.config === 'object' ? row.config : {}),
    },
    status: Number(row.status || 0),
    sort: Number(row.sort || 0),
    _visibility: 'all'
  }
  normalized._visibility = getVisibility(normalized)
  if (normalized._visibility === 'all' && normalized.config?.default_visible === false) {
    normalized._visibility = 'roles'
  }
  return normalized
}

const fetchOptions = async () => {
  const [roleRes, userRes] = await Promise.all([allRole(), getUserList()])
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
    const payload = widgetList.value.map(w => {
      const { _visibility, ...rest } = w
      if (_visibility === 'all') {
        rest.role_ids = []
        rest.uids = []
        rest.config = { ...rest.config, default_visible: true }
      } else {
        rest.config = { ...rest.config, default_visible: false }
      }
      return rest
    })
    await saveDashboardWidgets({ widgets: payload })
    ElMessage.success('配置已保存')
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
}

.widget-group {
  margin-bottom: 32px;
}

.group-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.group-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.group-count {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  background: var(--el-fill-color-light);
  padding: 2px 8px;
  border-radius: 10px;
}

.widget-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.widget-card {
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 8px;
  padding: 16px;
  transition: all 0.2s;

  &:hover {
    border-color: var(--el-color-primary-light-5);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  &--disabled {
    opacity: 0.5;
  }
}

.widget-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.widget-card__name {
  font-size: 14px;
  font-weight: 500;
  color: var(--el-text-color-primary);
}

.widget-card__visibility {
  margin-bottom: 10px;
}

.widget-card__detail {
  margin-bottom: 10px;

  :deep(.el-select) {
    width: 100%;
  }
}

.widget-card__footer {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 10px;
  border-top: 1px solid var(--el-border-color-extra-light);
}

.widget-card__scope,
.widget-card__type {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  background: var(--el-fill-color-light);
  padding: 2px 8px;
  border-radius: 4px;
}
</style>