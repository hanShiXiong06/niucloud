<template>
  <view>
    <!-- 设备列表 -->
    <view v-if="devices.length > 0" class="device-list">
      <view
        v-for="(item, index) in devices"
        :key="index"
        class="device-row"
      >
        <text class="device-index">{{ index + 1 }}</text>
        <view class="device-info">
          <text class="device-name">{{ item.model || '回收设备' }}</text>
          <view class="device-detail"><text class="device-detail-label">用户串号</text><text class="device-serial">{{ item.user_sn || item.imei }}</text></view>
          <view v-if="item.initial_price" class="device-detail"><text class="device-detail-label">预估价</text><text class="device-price">¥{{ item.initial_price }}</text></view>
        </view>
        <button
          v-if="showDeleteButton"
          class="device-remove"
          aria-label="删除设备"
          @click="handleRemove(index)"
        >
          <up-icon name="trash" size="18" color="var(--recycle-text-sub)"></up-icon>
        </button>
      </view>
    </view>

    <!-- 数量输入 -->
    <view class="device-quantity-row">
      <text class="device-quantity-label">回收数量</text>
      <view class="device-quantity-control">
        <view class="device-quantity-input">
        <u-number-box
          v-model="localCount"
          :min="devices.length || 1"
          :max="99"
          :disabled="devices.length > 0"
          @change="handleCountChange"
        ></u-number-box>
        <text class="device-quantity-unit">台</text>
        </view>
        <text v-if="devices.length > 0" class="device-quantity-note">
          已登记 {{ devices.length }} 台明细
        </text>
      </view>
    </view>

    <!-- 下单前的轻量登记，不替代门店签收时的完整串号。 -->
    <OrderTaskPopup
      :show="showAddDialog"
      title="添加回收设备"
      :subtitle="'第 ' + (devices.length + 1) + ' 台'"
      height="auto"
      :scrollable="false"
      :closeOnOverlay="false"
      @close="closeAddDialog"
      @open="measureDialogContent"
    >
      <view class="add-dialog">
        <scroll-view
          scroll-y
          :show-scrollbar="false"
          :scroll-into-view="errorFieldId"
          class="dialog-scroll"
          :style="{ height: `${dialogContentHeight}px` }"
        >
        <view class="dialog-content">
          <view id="input-model" class="form-item">
            <view class="form-label">
              <text>设备型号</text>
              <text class="required">*</text>
            </view>
            <view class="input-wrapper" :class="{ 'input-wrapper--invalid': fieldErrors.model }">
              <input
                v-model="newDevice.model"
                placeholder="输入或搜索型号"
                confirm-type="next"
                :cursor-spacing="24"
                class="custom-input custom-input--with-picker"
                @focus="handleModelFocus"
                @input="handleModelInput"
              />
              <button class="model-picker-action" @click="openModelPicker">
                <text>选型号</text>
                <up-icon name="arrow-right" size="12" color="var(--recycle-brand, #3b82f6)"></up-icon>
              </button>
            </view>
            <text v-if="fieldErrors.model" class="field-error">{{ fieldErrors.model }}</text>
            <scroll-view
              v-if="showModelSuggestions"
              scroll-y
              class="model-suggestions"
              :style="{ height: `${Math.min(Math.max(modelSuggestions.length, 1), 4) * 48}px` }"
            >
              <view v-if="modelSearching" class="model-suggestion-empty">搜索中...</view>
              <view v-else-if="modelSearchFailed" class="model-suggestion-empty">搜索暂不可用，可直接输入</view>
              <block v-else>
                <button
                  v-for="item in modelSuggestions"
                  :key="item.id"
                  class="model-suggestion-item"
                  @click="selectModelSuggestion(item)"
                >
                  <text class="model-suggestion-name">{{ item.node_name }}</text>
                </button>
              </block>
              <view v-if="!modelSearching && !modelSearchFailed && !modelSuggestions.length" class="model-suggestion-empty">未找到匹配型号，可使用当前名称</view>
            </scroll-view>
          </view>

          <view id="input-imei" class="form-item">
            <view class="form-label-row">
              <view class="form-label">
                <text>{{ userSnFromScan ? '设备串号' : '串号后 6 位' }}</text>
                <text class="required">*</text>
              </view>
              <button class="text-button" :aria-expanded="showSnHelp" @click="toggleSnHelp">
                <up-icon name="question-circle" size="14" color="var(--recycle-text-sub, #6b7280)"></up-icon>
                <text>{{ showSnHelp ? '收起帮助' : '串号在哪？' }}</text>
              </button>
            </view>
            <view class="input-wrapper" :class="{ 'input-wrapper--invalid': fieldErrors.user_sn }">
              <input
                v-model="newDevice.user_sn"
                placeholder="输入后 6 位数字或字母"
                maxlength="64"
                type="text"
                confirm-type="done"
                :cursor-spacing="24"
                class="custom-input custom-input--with-action"
                @focus="clearModelSuggestions"
                @input="handleUserSnInput"
                @confirm="confirmAdd"
              />
              <button class="input-action" aria-label="扫码录入串号" @click="scanUserSn">
                <up-icon name="scan" size="22" color="var(--recycle-brand, #3b82f6)"></up-icon>
              </button>
            </view>
            <text v-if="fieldErrors.user_sn" class="field-error">{{ fieldErrors.user_sn }}</text>
            <view v-if="showSnHelp" class="serial-help">
              <text>在设备拨号页输入 *#06#，或前往「设置 → 关于本机」查看 IMEI / SN。</text>
              <text>手填后 6 位即可，也可扫码录入完整串号。</text>
            </view>
          </view>

          <view class="optional-section">
            <button class="optional-toggle" :aria-expanded="showEstimatedPrice" @click="toggleEstimatedPrice">
              <text>预估价</text>
              <view class="optional-summary">
                <text>{{ newDevice.initial_price ? `¥${newDevice.initial_price}` : '选填' }}</text>
                <up-icon :name="showEstimatedPrice ? 'arrow-up' : 'arrow-down'" size="13" color="var(--recycle-text-sub, #6b7280)"></up-icon>
              </view>
            </button>
            <view v-if="showEstimatedPrice" class="optional-content">
            <view class="input-wrapper price-input">
              <text class="price-symbol">¥</text>
              <input
                v-model="newDevice.initial_price"
                placeholder="不确定可以留空"
                type="digit"
                confirm-type="done"
                :cursor-spacing="24"
                class="custom-input"
                @focus="clearModelSuggestions"
                @confirm="confirmAdd"
              />
            </view>
            <text class="form-hint">最终价格以门店质检报价为准</text>
            </view>
          </view>
        </view>
        </scroll-view>

      </view>
      <template #footer>
        <OrderUiButton block @click="closeAddDialog">取消</OrderUiButton>
        <OrderUiButton block variant="primary" @click="confirmAdd">添加设备</OrderUiButton>
      </template>
    </OrderTaskPopup>

    <OrderTaskPopup
      :show="showModelPicker"
      title="选择型号"
      :scrollable="false"
      :z-index="10085"
      @close="closeModelPicker"
    >
      <view class="model-picker">
        <view class="model-picker-breadcrumb">
          <button class="breadcrumb-item root" :disabled="modelTreeLoading" @click="resetModelPickerPath">全部</button>
          <button
            v-for="(node, index) in modelPickerPath"
            :key="node.id"
            class="breadcrumb-item"
            :disabled="modelTreeLoading"
            @click="trimModelPickerPath(index)"
          >
            {{ node.node_name }}
          </button>
        </view>
        <scroll-view scroll-y class="model-picker-list">
          <view v-if="modelTreeLoading" class="model-picker-empty">分类加载中...</view>
          <view v-else-if="modelTreeFailed" class="model-picker-empty">
            <text>分类加载失败</text>
            <button class="text-button" @click="retryModelPicker">重试</button>
          </view>
          <view v-else-if="!currentModelPickerOptions.length" class="model-picker-empty">暂无型号，可返回手动输入</view>
          <block v-else>
          <button
            v-for="node in currentModelPickerOptions"
            :key="node.id"
            class="model-picker-row"
            @click="handleModelPickerNode(node)"
          >
            <view class="model-picker-row-main">
              <text class="model-picker-row-title">{{ node.node_name }}</text>
              <text v-if="node.model_full_name" class="model-picker-row-path">{{ node.model_full_name }}</text>
            </view>
            <view class="model-picker-row-action">
              <up-icon :name="hasModelChildren(node) ? 'arrow-right' : 'plus'" size="16" color="var(--recycle-text-sub, #6b7280)"></up-icon>
            </view>
          </button>
          </block>
        </scroll-view>
      </view>
    </OrderTaskPopup>
  </view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import type { Device } from '../../../types/order'
import { getDeviceModelDictChildren, searchDeviceModelDictOptions } from '../../../api/order'
import OrderTaskPopup from './OrderTaskPopup.vue'
import OrderUiButton from './OrderUiButton.vue'

interface Props {
  devices: Device[]
  count: number
  showAddButton?: boolean
  showBatchButton?: boolean
  showDeleteButton?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showAddButton: true,
  showBatchButton: true,
  showDeleteButton: true
})

const emit = defineEmits<{
  'update:devices': [devices: Device[]]
  'update:count': [count: number]
  'add-single-device': []
  'add-device': []
}>()

const localCount = ref(props.count)
const showAddDialog = ref(false)
const showSnHelp = ref(false)
const showEstimatedPrice = ref(true)
const fieldErrors = ref({ model: '', user_sn: '' })
const errorFieldId = ref('')
const dialogContentHeight = ref(280)
const instance = getCurrentInstance()
const modelSuggestions = ref<any[]>([])
const modelSearching = ref(false)
const modelSearchFailed = ref(false)
const modelFocused = ref(false)
const userSnFromScan = ref(false)
const showModelPicker = ref(false)
const modelTreeLoading = ref(false)
const modelTreeFailed = ref(false)
const currentModelPickerOptions = ref<any[]>([])
const modelPickerPath = ref<any[]>([])
const selectedModelPathNames = ref<string[]>([])
let modelSearchTimer: any = null
let modelSearchVersion = 0
let modelTreeVersion = 0
let dialogVersion = 0
const newDevice = ref<Device>({
  imei: '',
  user_sn: '',
  model: '',
  initial_price: '',
  category_id: 0,
  category_path: []
})

const showModelSuggestions = computed(() => {
  return modelFocused.value && String(newDevice.value.model || '').trim().length > 0
})

// scroll-view 在小程序中需要明确高度；内容按实测收缩，上限由 CSS 限制。
const measureDialogContent = async () => {
  if (!showAddDialog.value) return
  await nextTick()
  uni.createSelectorQuery().in(instance?.proxy).select('.dialog-content').boundingClientRect((rect: any) => {
    if (showAddDialog.value && rect?.height) dialogContentHeight.value = Math.ceil(rect.height)
  }).exec()
}

watch(() => [
  showAddDialog.value, showSnHelp.value, showEstimatedPrice.value, showModelSuggestions.value,
  modelSearching.value, modelSearchFailed.value, modelSuggestions.value.length,
  fieldErrors.value.model, fieldErrors.value.user_sn
], measureDialogContent)

const toggleSnHelp = () => {
  clearModelSuggestions()
  showSnHelp.value = !showSnHelp.value
}

const toggleEstimatedPrice = () => {
  clearModelSuggestions()
  showEstimatedPrice.value = !showEstimatedPrice.value
}

const normalizeCount = (value: any) => {
  const rawValue = typeof value === 'object' && value !== null ? value.value : value
  const count = Number(rawValue)
  return Number.isFinite(count) && count > 0 ? count : 1
}

watch(() => props.count, (newVal) => {
  localCount.value = normalizeCount(newVal)
})

// 监听设备数量变化，自动更新数量
watch(() => props.devices.length, (newLength) => {
  if (newLength > 0) {
    localCount.value = newLength
    emit('update:count', newLength)
  }
})

const handleCountChange = (value: any) => {
  const count = normalizeCount(value)
  localCount.value = count

  // 如果有设备，不允许手动修改数量
  if (props.devices.length === 0) {
    emit('update:count', count)
  }
}

const handleRemove = (index: number) => {
  const newDevices = [...props.devices]
  newDevices.splice(index, 1)
  emit('update:devices', newDevices)

  // 自动更新数量
  const newCount = newDevices.length || 1
  emit('update:count', newCount)
}

const handleBatchAdd = () => {
  dialogVersion++
  showAddDialog.value = true
}

defineExpose({
  openAddDialog: handleBatchAdd
})

const closeAddDialog = () => {
  dialogVersion++
  showAddDialog.value = false
  closeModelPicker()
  clearModelSuggestions()
  showSnHelp.value = false
  showEstimatedPrice.value = false
  fieldErrors.value = { model: '', user_sn: '' }
  errorFieldId.value = ''
  userSnFromScan.value = false
  newDevice.value = {
    imei: '',
    user_sn: '',
    model: '',
    initial_price: '',
    category_id: 0,
    category_path: []
  }
  selectedModelPathNames.value = []
  modelPickerPath.value = []
}

const handleModelFocus = () => {
  modelFocused.value = true
  scheduleModelSearch()
}

const handleModelInput = () => {
  fieldErrors.value.model = ''
  newDevice.value.category_id = 0
  newDevice.value.category_path = []
  selectedModelPathNames.value = []
  scheduleModelSearch()
}

const scheduleModelSearch = () => {
  if (modelSearchTimer) clearTimeout(modelSearchTimer)
  const version = ++modelSearchVersion
  modelSuggestions.value = []
  modelSearchFailed.value = false
  modelSearching.value = Boolean(String(newDevice.value.model || '').trim())
  modelSearchTimer = setTimeout(() => searchModelSuggestions(version), 260)
}

const searchModelSuggestions = async (version = ++modelSearchVersion) => {
  const keyword = String(newDevice.value.model || '').trim()
  if (!keyword) {
    modelSuggestions.value = []
    modelSearching.value = false
    return
  }

  modelSearching.value = true
  try {
    const res: any = await searchDeviceModelDictOptions({ keyword, limit: 20 })
    if (version !== modelSearchVersion) return
    modelSuggestions.value = Array.isArray(res?.data) ? res.data : []
  } catch (error) {
    if (version !== modelSearchVersion) return
    modelSuggestions.value = []
    modelSearchFailed.value = true
  } finally {
    if (version === modelSearchVersion) modelSearching.value = false
  }
}

const selectModelSuggestion = (item: any) => {
  fieldErrors.value.model = ''
  newDevice.value.model = item.node_name || ''
  newDevice.value.category_id = item.id || 0
  newDevice.value.category_path = resolveModelPath(item)
  selectedModelPathNames.value = resolveModelPathNames(item)
  clearModelSuggestions()
}

const resolveModelPath = (item: any): Array<string | number> => {
  if (Array.isArray(item?.category_path) && item.category_path.length) {
    return item.category_path
  }
  return [item?.id || 0].filter(Boolean)
}

const resolveModelPathNames = (item: any): string[] => {
  if (Array.isArray(item?.category_path_names) && item.category_path_names.length) {
    return item.category_path_names.map((value: any) => String(value)).filter(Boolean)
  }
  if (item?.model_full_name) {
    return String(item.model_full_name).split('/').filter(Boolean)
  }
  return [item?.node_name || ''].filter(Boolean)
}

const openModelPicker = async () => {
  clearModelSuggestions()
  showModelPicker.value = true
  await loadModelPickerChildren(0, [])
}

const closeModelPicker = () => {
  modelTreeVersion++
  showModelPicker.value = false
  modelTreeLoading.value = false
  modelTreeFailed.value = false
  modelPickerPath.value = []
  currentModelPickerOptions.value = []
}

const resetModelPickerPath = async () => {
  await loadModelPickerChildren(0, [])
}

const trimModelPickerPath = async (index: number) => {
  const path = modelPickerPath.value.slice(0, index + 1)
  await loadModelPickerChildren(Number(path[path.length - 1]?.id || 0), path)
}

const hasModelChildren = (node: any) => {
  return Number(node?.has_children || 0) === 1
}

const handleModelPickerNode = async (node: any) => {
  if (modelTreeLoading.value) return
  if (hasModelChildren(node)) {
    await loadModelPickerChildren(Number(node.id || 0), [...modelPickerPath.value, node])
    return
  }

  const pathNodes = [...modelPickerPath.value, node]
  newDevice.value.model = node.node_name || node.model_name || ''
  newDevice.value.category_id = node.id || 0
  newDevice.value.category_path = pathNodes.map(item => item.id).filter(Boolean)
  selectedModelPathNames.value = pathNodes.map(item => item.node_name).filter(Boolean)
  fieldErrors.value.model = ''
  closeModelPicker()
}

const retryModelPicker = () => {
  const path = modelPickerPath.value
  return loadModelPickerChildren(Number(path[path.length - 1]?.id || 0), path)
}

const loadModelPickerChildren = async (pid: number, path: any[] = []) => {
  const version = ++modelTreeVersion
  modelPickerPath.value = path
  modelTreeLoading.value = true
  modelTreeFailed.value = false
  currentModelPickerOptions.value = []
  try {
    const res: any = await getDeviceModelDictChildren({ pid, limit: 200 })
    if (version !== modelTreeVersion) return
    currentModelPickerOptions.value = Array.isArray(res?.data) ? res.data : []
  } catch (error) {
    if (version !== modelTreeVersion) return
    modelTreeFailed.value = true
  } finally {
    if (version === modelTreeVersion) modelTreeLoading.value = false
  }
}

const clearModelSuggestions = () => {
  modelSearchVersion++
  if (modelSearchTimer) clearTimeout(modelSearchTimer)
  modelFocused.value = false
  modelSearching.value = false
  modelSearchFailed.value = false
  modelSuggestions.value = []
}

const normalizeUserSn = (value: any, maxLength = 64) => {
  return String(value || '').replace(/[^a-zA-Z0-9]/g, '').slice(0, maxLength)
}

const handleUserSnInput = () => {
  fieldErrors.value.user_sn = ''
  if (userSnFromScan.value) {
    newDevice.value.user_sn = normalizeUserSn(newDevice.value.user_sn, 64)
    return
  }
  newDevice.value.user_sn = normalizeUserSn(newDevice.value.user_sn, 6)
}

const scanUserSn = () => {
  clearModelSuggestions()
  const version = dialogVersion
  uni.scanCode({
    scanType: ['barCode', 'qrCode'],
    success: (res: any) => {
      if (!showAddDialog.value || version !== dialogVersion) return
      const code = normalizeUserSn(res?.result || '', 64)
      if (code.length < 6) {
        uni.showToast({
          title: '未识别到有效串号，请手动输入后6位',
          icon: 'none'
        })
        return
      }
      userSnFromScan.value = true
      newDevice.value.user_sn = code
      fieldErrors.value.user_sn = ''
    },
    fail: (error: any) => {
      if (!showAddDialog.value || version !== dialogVersion || /cancel/i.test(error?.errMsg || '')) return
      uni.showToast({
        title: '暂时无法扫码，可手动输入后6位',
        icon: 'none'
      })
    }
  })
}

const confirmAdd = async () => {
  if (!showAddDialog.value) return
  clearModelSuggestions()
  const userSnLength = String(newDevice.value.user_sn || '').length
  fieldErrors.value = {
    model: newDevice.value.model?.trim() ? '' : '请填写或选择型号',
    user_sn: userSnLength >= 6 && (userSnFromScan.value || userSnLength === 6) ? '' : '请输入后 6 位，或扫描完整串号'
  }
  if (fieldErrors.value.model || fieldErrors.value.user_sn) {
    errorFieldId.value = ''
    await nextTick()
    errorFieldId.value = fieldErrors.value.model ? 'input-model' : 'input-imei'
    return
  }

  // 添加设备
  const newDevices = [...props.devices, { ...newDevice.value }]
  emit('update:devices', newDevices)

  // 自动更新数量
  emit('update:count', newDevices.length)

  // 关闭弹窗
  closeAddDialog()

  uni.showToast({
    title: '添加成功',
    icon: 'success'
  })
}

onBeforeUnmount(() => {
  dialogVersion++
  clearModelSuggestions()
  modelTreeVersion++
})
</script>

<style scoped lang="scss">
.device-row { display: flex; align-items: flex-start; gap: 20rpx; padding: 18rpx 0; border-bottom: 1rpx solid var(--recycle-line); }
.device-index { flex-shrink: 0; min-width: 28rpx; padding-top: 2rpx; color: var(--recycle-text-sub); font-size: 24rpx; line-height: 40rpx; font-variant-numeric: tabular-nums; }
.device-info { flex: 1; min-width: 0; }
.device-name { display: block; font-size: 28rpx; line-height: 42rpx; font-weight: 600; color: var(--recycle-text-main); overflow-wrap: anywhere; }
.device-detail { display: flex; align-items: baseline; gap: 16rpx; margin-top: 8rpx; font-size: 24rpx; line-height: 36rpx; }
.device-detail-label { flex-shrink: 0; color: var(--recycle-text-sub); }
.device-serial { min-width: 0; color: var(--recycle-text-sub); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.device-price { color: var(--recycle-text-main); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.device-remove { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 72rpx; height: 72rpx; margin: 0 -12rpx 0 0; padding: 0; background: transparent; border: 0; border-radius: 8rpx; }
.device-remove::after { border: 0; }
.device-remove:active { background: var(--recycle-bg-soft); }
.device-quantity-row { display: flex; align-items: flex-start; gap: 24rpx; padding: 12rpx 0; }
.device-quantity-label { flex-shrink: 0; width: 112rpx; font-size: 26rpx; line-height: 60rpx; color: var(--recycle-text-sub); }
.device-quantity-control { flex: 1; min-width: 0; }
.device-quantity-input { display: flex; align-items: center; gap: 16rpx; }
.device-quantity-unit { color: var(--recycle-text-sub); font-size: 24rpx; }
.device-quantity-note { display: block; margin-top: 6rpx; font-size: 22rpx; line-height: 32rpx; color: var(--recycle-text-sub); }
.device-quantity-control :deep(.u-number-box__minus), .device-quantity-control :deep(.u-number-box__plus), .device-quantity-control :deep(.u-number-box__input) { background: var(--recycle-bg-soft) !important; }
.device-quantity-control :deep(.u-number-box__input) { color: var(--recycle-text-main) !important; }

.add-dialog {
  width: 100%;
  display: flex;
  flex-direction: column;
  color: var(--recycle-text-main, #1f2937);
  background: var(--recycle-bg-card, #fff);
  overflow: hidden;
}

.add-dialog button,
.model-picker button {
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  color: inherit;
  font-size: inherit;
  line-height: 1.5;
  text-align: left;

  &::after { border: none; }
  &:active { opacity: 0.7; }
}

.dialog-header {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 12px 12px 8px 20px;
}

.dialog-heading {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 8px;
}

.dialog-title {
  font-size: 18px;
  font-weight: 600;
  line-height: 1.4;
}

.dialog-count {
  font-size: 12px;
  color: var(--recycle-text-sub, #6b7280);
}

.icon-button {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.dialog-scroll {
  min-height: 0;
  max-height: calc(88vh - 190px - env(safe-area-inset-bottom));
}

.dialog-content {
  box-sizing: border-box;
  padding: 8px 20px 4px;
}

.form-item {
  margin-bottom: 18px;
}

.form-label {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 500;
  line-height: 22px;
}

.form-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;

  .form-label { margin-bottom: 0; }
}

.add-dialog .text-button,
.model-picker .text-button {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 28px;
  flex-shrink: 0;
  font-size: 12px;
  color: var(--recycle-text-sub, #6b7280);
}

.required {
  color: #ef4444;
  margin-left: 4px;
}

.form-hint {
  display: block;
  margin-top: 8px;
  font-size: 12px;
  line-height: 1.5;
  color: var(--recycle-text-sub, #6b7280);
}

.serial-help {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
  padding-left: 10px;
  border-left: 2px solid var(--recycle-line, #e5e7eb);
  color: var(--recycle-text-sub, #6b7280);
  font-size: 12px;
  line-height: 1.6;
}

.field-error {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  line-height: 1.5;
  color: #dc2626;
}

.optional-section {
  border-top: 1px solid var(--recycle-line, #e5e7eb);
}

.add-dialog .optional-toggle {
  width: 100%;
  min-height: 44px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
}

.optional-summary {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--recycle-text-sub, #6b7280);
  font-size: 12px;
  overflow-wrap: anywhere;
}

.optional-content {
  padding-bottom: 12px;
}

.input-wrapper {
  width: 100%;
  position: relative;
}

.custom-input {
  width: 100%;
  height: 46px;
  padding: 0 12px;
  border: 1px solid var(--recycle-line, #e5e7eb);
  border-radius: 6px;
  font-size: 14px;
  color: var(--recycle-text-main, #1f2937);
  background: var(--recycle-bg-card, #fff);
  box-sizing: border-box;
}

.custom-input--with-action {
  padding-right: 48px;
}

.custom-input--with-picker {
  padding-right: 88px;
}

.input-action {
  position: absolute;
  right: 0;
  top: 0;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.add-dialog .model-picker-action {
  position: absolute;
  right: 0;
  top: 0;
  width: 84px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: var(--recycle-brand, #3b82f6);
  font-size: 13px;
  font-weight: 600;
}

.custom-input:focus {
  border-color: var(--recycle-brand, #3b82f6);
  outline: none;
}

.input-wrapper--invalid .custom-input {
  border-color: #dc2626;
}

.price-input .custom-input { padding-left: 32px; }

.price-symbol {
  position: absolute;
  left: 12px;
  top: 0;
  z-index: 1;
  line-height: 46px;
  font-size: 14px;
}

.model-suggestions {
  margin-top: 8px;
  border-radius: 6px;
  background: var(--recycle-bg-soft, #f7f7f8);
  overflow: hidden;
}

.add-dialog .model-suggestion-item {
  min-height: 48px;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid var(--recycle-line, #e5e7eb);
  box-sizing: border-box;
}

.add-dialog .model-suggestion-item:last-child {
  border-bottom: none;
}

.model-suggestion-name {
  font-size: 14px;
  color: var(--recycle-text-main, #1f2937);
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.model-suggestion-empty {
  min-height: 48px;
  padding: 8px 12px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  font-size: 12px;
  line-height: 1.5;
  color: var(--recycle-text-sub, #6b7280);
}

.model-picker {
  height: 100%;
  min-height: 0;
  background: var(--recycle-bg-card, #fff);
  color: var(--recycle-text-main, #1f2937);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.model-picker-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 12px;
  padding: 12px 12px 8px 20px;
}

.model-picker-title {
  display: block;
  font-size: 17px;
  font-weight: 600;
}

.model-picker-breadcrumb {
  display: flex;
  flex-shrink: 0;
  gap: 8px;
  padding: 12px 16px;
  overflow-x: auto;
  white-space: nowrap;
}

.model-picker .breadcrumb-item {
  flex-shrink: 0;
  padding: 8px 10px;
  border-radius: 4px;
  background: var(--recycle-bg-soft, #f7f7f8);
  color: var(--recycle-text-sub, #6b7280);
  font-size: 12px;
}

.model-picker .breadcrumb-item.root {
  color: var(--recycle-brand, #3b82f6);
}

.model-picker-list {
  flex: 1;
  height: 0;
  min-height: 0;
  border-top: 1px solid var(--recycle-line, #e5e7eb);
}

.model-picker .model-picker-row {
  min-height: 52px;
  padding: 12px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid var(--recycle-line, #e5e7eb);
  box-sizing: border-box;
}

.model-picker-row-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow-wrap: anywhere;
}

.model-picker-row-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--recycle-text-main, #1f2937);
}

.model-picker-row-path {
  font-size: 11px;
  color: var(--recycle-text-sub, #6b7280);
}

.model-picker-row-action {
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.model-picker-empty {
  min-height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--recycle-text-sub, #6b7280);
  font-size: 13px;
}

.dialog-footer {
  flex-shrink: 0;
  display: flex;
  gap: 12px;
  padding: 12px 20px 18px;
  border-top: 1px solid var(--recycle-line, #e5e7eb);
}

.add-dialog .dialog-button {
  flex: 1;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  font-size: 15px;
}

.add-dialog .dialog-button.cancel {
  color: var(--recycle-text-sub, #6b7280);
  background: var(--recycle-bg-soft, #f7f7f8);
}

.add-dialog .dialog-button.confirm {
  flex: 2;
  color: var(--recycle-button-text, #fff);
  background: var(--recycle-button-bg, #111827);
  font-weight: 600;
}
</style>
