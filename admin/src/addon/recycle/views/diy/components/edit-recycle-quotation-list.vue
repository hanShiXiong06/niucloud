<template>
  <div class="edit-recycle-quotation-list">
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">内容设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="标题">
            <el-input v-model="diyStore.editComponent.title" placeholder="请输入标题" clearable maxlength="20" show-word-limit />
          </el-form-item>
          <el-form-item label="副标题">
            <el-input v-model="diyStore.editComponent.subtitle" placeholder="请输入副标题" clearable maxlength="30" show-word-limit />
          </el-form-item>
          <el-form-item label="按钮文字">
            <el-input v-model="diyStore.editComponent.actionText" placeholder="请输入按钮文字" clearable maxlength="8" show-word-limit />
          </el-form-item>
          <el-form-item label="显示数量">
            <el-input-number v-model="diyStore.editComponent.limit" :min="1" :max="20" />
          </el-form-item>
          <el-form-item label="刷新按钮">
            <el-switch v-model="diyStore.editComponent.showRefresh" />
          </el-form-item>
          <el-form-item label="展示样式">
            <el-radio-group v-model="diyStore.editComponent.displayStyle">
              <el-radio label="list">列表</el-radio>
              <el-radio label="graphic">图文导航</el-radio>
            </el-radio-group>
          </el-form-item>
          <template v-if="diyStore.editComponent.displayStyle === 'graphic'">
            <el-form-item label="导航图片">
              <upload-image v-model="diyStore.editComponent.navImageUrl" :limit="1" />
            </el-form-item>
            <el-form-item label="每行数量">
              <el-radio-group v-model="diyStore.editComponent.navRowCount">
                <el-radio :label="3">3个</el-radio>
                <el-radio :label="4">4个</el-radio>
                <el-radio :label="5">5个</el-radio>
              </el-radio-group>
            </el-form-item>
          </template>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <div class="header-row">
          <h3 class="mb-[10px]">前台展示开关</h3>
          <el-button size="small" type="primary" :loading="saving" @click="saveDisplayConfig">保存开关</el-button>
        </div>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="展示模式">
            <el-radio-group v-model="displayConfig.mode">
              <el-radio label="all">全部启用报价单</el-radio>
              <el-radio label="custom">仅展示已开启</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="总开关">
            <el-switch v-model="displayConfig.enabled" :active-value="1" :inactive-value="0" />
          </el-form-item>
          <el-form-item v-if="displayConfig.mode === 'custom'" label="报价列表">
            <div class="dataset-list">
              <div v-for="item in datasetList" :key="item.dataset_id || item.id" class="dataset-item">
                <div class="dataset-main">
                  <div class="dataset-title">{{ item.title || item.dataset_name || item.price_name }}</div>
                  <div class="dataset-meta">{{ item.quotation_id }} · {{ item.dataset_id }} · {{ item.last_sync_status_name || '待同步' }}</div>
                </div>
                <el-switch v-model="item.display_enabled" :active-value="1" :inactive-value="0" />
              </div>
            </div>
          </el-form-item>
        </el-form>
      </div>
    </div>

    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">颜色设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="标题颜色">
            <el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="副标题">
            <el-color-picker v-model="diyStore.editComponent.subtitleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="按钮颜色">
            <el-color-picker v-model="diyStore.editComponent.buttonColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap" v-if="diyStore.editComponent.displayStyle === 'graphic'">
        <h3 class="mb-[10px]">图文导航</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="图片大小">
            <el-slider v-model="diyStore.editComponent.navImageSize" show-input size="small" class="ml-[10px]" :min="24" :max="64" />
          </el-form-item>
          <el-form-item label="图片圆角">
            <el-slider v-model="diyStore.editComponent.navAroundRadius" show-input size="small" class="ml-[10px]" :min="0" :max="50" />
          </el-form-item>
        </el-form>
      </div>

      <slot name="style"></slot>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import useDiyStore from '@/stores/modules/diy'
import { getQuotationV2DatasetAll, getQuotationV2DisplayConfig, saveQuotationV2DisplayConfig } from '@/addon/recycle/api/quotation'

const diyStore = useDiyStore()
const saving = ref(false)
const datasetList = ref<any[]>([])
const displayConfig = reactive({
  enabled: 1,
  mode: 'all',
  dataset_ids: [] as number[]
})

function syncSelectedIds() {
  displayConfig.dataset_ids = datasetList.value
    .filter(item => Number(item.display_enabled) === 1)
    .map(item => Number(item.dataset_id || item.id))
    .filter(id => Number.isFinite(id) && id > 0)
}

async function loadDisplayConfig() {
  const [datasetRes, configRes] = await Promise.all([
    getQuotationV2DatasetAll({ limit: 100 }),
    getQuotationV2DisplayConfig()
  ])

  const rows = Array.isArray(datasetRes?.data)
    ? datasetRes.data
    : Array.isArray((datasetRes as any)?.rows)
      ? (datasetRes as any).rows
      : Array.isArray((datasetRes as any)?.list)
        ? (datasetRes as any).list
        : []

  const config = configRes?.data?.config || configRes?.data || {}
  const selectedIds = new Set<number>((config.dataset_ids || []).map((item: any) => Number(item)).filter((id: number) => Number.isFinite(id) && id > 0))

  datasetList.value = rows.map((item: any) => ({
    ...item,
    display_enabled: config.mode === 'all' ? 1 : (selectedIds.has(Number(item.dataset_id || item.id)) ? 1 : 0)
  }))

  displayConfig.enabled = Number(config.enabled ?? 1) ? 1 : 0
  displayConfig.mode = config.mode || 'all'
  syncSelectedIds()
}

async function saveDisplayConfig() {
  syncSelectedIds()
  saving.value = true
  try {
    await saveQuotationV2DisplayConfig({
      enabled: displayConfig.enabled,
      mode: displayConfig.mode,
      dataset_ids: displayConfig.mode === 'custom' ? displayConfig.dataset_ids : []
    })
    ElMessage.success('保存成功')
  } finally {
    saving.value = false
  }
}

diyStore.editComponent.verify = () => {
  return { code: true, message: '' }
}

onMounted(() => {
  if (!diyStore.editComponent.title) diyStore.editComponent.title = '今日报价'
  if (!diyStore.editComponent.subtitle) diyStore.editComponent.subtitle = '实时同步回收报价单'
  if (!diyStore.editComponent.actionText) diyStore.editComponent.actionText = '查看'
  if (!diyStore.editComponent.limit) diyStore.editComponent.limit = 5
  if (diyStore.editComponent.showRefresh === undefined) diyStore.editComponent.showRefresh = true
  if (!diyStore.editComponent.displayStyle) diyStore.editComponent.displayStyle = 'list'
  if (!diyStore.editComponent.navImageUrl) diyStore.editComponent.navImageUrl = ''
  if (!diyStore.editComponent.navRowCount) diyStore.editComponent.navRowCount = 4
  if (!diyStore.editComponent.navImageSize) diyStore.editComponent.navImageSize = 40
  if (diyStore.editComponent.navAroundRadius === undefined) diyStore.editComponent.navAroundRadius = 20
  if (!diyStore.editComponent.componentStartBgColor) diyStore.editComponent.componentStartBgColor = ''
  if (!diyStore.editComponent.componentEndBgColor) diyStore.editComponent.componentEndBgColor = ''
  if (!diyStore.editComponent.componentGradientAngle) diyStore.editComponent.componentGradientAngle = 'to bottom'
  if (!diyStore.editComponent.componentBgUrl) diyStore.editComponent.componentBgUrl = ''
  if (diyStore.editComponent.componentBgAlpha === undefined) diyStore.editComponent.componentBgAlpha = 0
  if (diyStore.editComponent.topRounded === undefined) diyStore.editComponent.topRounded = 0
  if (diyStore.editComponent.bottomRounded === undefined) diyStore.editComponent.bottomRounded = 0
  if (!diyStore.editComponent.titleColor) diyStore.editComponent.titleColor = '#111827'
  if (!diyStore.editComponent.subtitleColor) diyStore.editComponent.subtitleColor = '#6B7280'
  if (!diyStore.editComponent.buttonColor) diyStore.editComponent.buttonColor = '#2563EB'
  if (!diyStore.editComponent.margin) {
    diyStore.editComponent.margin = {
      top: 10,
      bottom: 10,
      both: 12
    }
  }

  loadDisplayConfig().catch(() => {
    datasetList.value = []
  })
})
</script>

<style lang="scss" scoped>
.edit-recycle-quotation-list {
  padding: 10px;

  .edit-attr-item-wrap {
    margin-bottom: 20px;
    padding: 10px;
    border-radius: 4px;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }

  .header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .dataset-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
  }

  .dataset-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #fafafa;
  }

  .dataset-main {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .dataset-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
  }

  .dataset-meta {
    font-size: 12px;
    color: #6b7280;
  }
}
</style>
