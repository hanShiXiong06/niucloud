<template>
  <view>
    <!-- 设备列表 -->
    <view v-if="devices.length > 0" class="space-y-2 mb-3">
      <view
        v-for="(item, index) in devices"
        :key="index"
        class="flex justify-between items-center p-2 rounded"
        style="background: rgba(241, 237, 237, 0.8); border-left: 3px solid #D1C2C2;"
      >
        <view class="flex flex-col gap-1 flex-1">
          <text class="text-sm">
            <text class="font-medium mr-1" style="color: #4f46e5;">{{ index + 1 }}</text>
            用户串号: {{ item.user_sn || item.imei }}
          </text>
          <text v-if="item.model" class="text-xs text-gray-600">
            名称: {{ item.model }}
          </text>
          <text v-if="item.initial_price" class="text-xs text-gray-600">
            定价: ¥{{ item.initial_price }}
          </text>
        </view>
        <view
          v-if="showDeleteButton"
          class="p-1"
          @click="handleRemove(index)"
        >
          <up-icon name="trash" size="14" color="#ef4444"></up-icon>
        </view>
      </view>
    </view>

    <!-- 数量输入 -->
    <up-row customStyle="margin-bottom: 8px">
      <up-col span="3">
        <view class="label">数量</view>
      </up-col>
      <up-col span="9">
        <u-number-box
          v-model="localCount"
          :min="devices.length || 1"
          :max="99"
          :disabled="devices.length > 0"
          @change="handleCountChange"
        ></u-number-box>
        <text v-if="devices.length > 0" class="text-xs text-gray-500 mt-1">
          已自动设置为设备数量
        </text>
      </up-col>
    </up-row>

    <!-- 批量添加设备弹窗 -->
    <up-popup
      :show="showAddDialog"
      mode="center"
      :round="10"
      :closeOnClickOverlay="false"
      :safeAreaInsetBottom="false"
      @close="closeAddDialog"
    >
      <view class="add-dialog">
        <view class="dialog-header">
          <view>
            <text class="dialog-title">添加回收设备</text>
            <text class="dialog-subtitle">用于用户下单前登记，门店签收时再录入完整 IMEI/SN。</text>
          </view>
        </view>

        <view class="dialog-content" :id="'dialog-content'">
          <view class="dialog-tip">
            <view class="dialog-tip__badge">提示</view>
            <text class="dialog-tip__text">设备名称和用户串号后 6 位必填。</text>
          </view>

          <!-- 设备名称输入 -->
          <view :id="'input-model'" class="form-item">
            <view class="form-label">
              <text>设备型号</text>
              <text class="required">*</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.model"
                placeholder="输入或搜索型号"
                class="custom-input custom-input--with-picker"
                @focus="handleModelFocus"
                @input="handleModelInput"
              />
              <view class="model-picker-action" @click="openModelPicker">
                <text>选择</text>
              </view>
            </view>
            <view v-if="showModelSuggestions" class="model-suggestions">
              <view v-if="modelSearching" class="model-suggestion-empty">搜索中...</view>
              <block v-else>
                <view
                  v-for="item in modelSuggestions"
                  :key="item.id"
                  class="model-suggestion-item"
                  @click="selectModelSuggestion(item)"
                >
                  <text class="model-suggestion-name">{{ item.node_name }}</text>
                </view>
              </block>
              <view v-if="!modelSearching && !modelSuggestions.length" class="model-suggestion-empty">没有找到，可直接输入</view>
            </view>
            <text class="form-hint">不知道准确型号时，可以点“选择”按分类查找。</text>
          </view>

          <!-- 用户串号输入（后6位） -->
          <view :id="'input-imei'" class="form-item">
            <view class="form-label">
              <text>用户串号后6位</text>
              <text class="required">*</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.user_sn"
                placeholder="手输后6位，或扫码录入完整串号"
                maxlength="64"
                type="text"
                class="custom-input custom-input--with-action"
                @focus="clearModelSuggestions"
                @input="handleUserSnInput"
              />
              <view class="input-action" @click="scanUserSn">
                <up-icon name="scan" size="20" color="#4f46e5"></up-icon>
              </view>
            </view>
            <text class="form-hint">用户可在手机拨号输入 *#06#，调出条形码后点击右侧扫码快速录入；也可手动输入后6位数字或字母。</text>
          </view>

          <!-- 定价输入 -->
          <view :id="'input-price'" class="form-item">
            <view class="form-label">
              <text>预估价（选填）</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.initial_price"
                placeholder="不确定可以留空"
                type="number"
                class="custom-input"
                @focus="clearModelSuggestions"
              />
            </view>
            <text class="form-hint">这里只做下单预估，不会影响门店最终质检报价</text>
          </view>
        </view>

        <view class="dialog-footer">
          <view class="dialog-button cancel" @click="closeAddDialog">
            取消
          </view>
          <view
            :id="'confirm-btn'"
            class="dialog-button confirm"
            @click="confirmAdd"
          >
            <text>确定</text>
          </view>
        </view>
      </view>
    </up-popup>

    <up-popup
      :show="showModelPicker"
      mode="bottom"
      :round="16"
      :safeAreaInsetBottom="true"
      @close="closeModelPicker"
    >
      <view class="model-picker">
        <view class="model-picker-header">
          <view>
            <text class="model-picker-title">按分类选择型号</text>
          </view>
          <view class="model-picker-close" @click="closeModelPicker">
            <up-icon name="close" size="18" color="#6b7280"></up-icon>
          </view>
        </view>
        <view class="model-picker-breadcrumb">
          <view class="breadcrumb-item root" @click="resetModelPickerPath">全部</view>
          <view
            v-for="(node, index) in modelPickerPath"
            :key="node.id"
            class="breadcrumb-item"
            @click="trimModelPickerPath(index)"
          >
            {{ node.node_name }}
          </view>
        </view>
        <scroll-view scroll-y class="model-picker-list">
          <view v-if="modelTreeLoading" class="model-picker-empty">分类加载中...</view>
          <view v-else-if="!currentModelPickerOptions.length" class="model-picker-empty">暂无下级分类</view>
          <view
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
              <text>{{ hasModelChildren(node) ? '下级' : '选择' }}</text>
              <up-icon :name="hasModelChildren(node) ? 'arrow-right' : 'checkmark'" size="14" color="#9ca3af"></up-icon>
            </view>
          </view>
        </scroll-view>
      </view>
    </up-popup>
  </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Device } from '../../../types/order'
import { getDeviceModelDictChildren, searchDeviceModelDictOptions } from '../../../api/order'

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
const modelSuggestions = ref<any[]>([])
const modelSearching = ref(false)
const modelFocused = ref(false)
const userSnFromScan = ref(false)
const showModelPicker = ref(false)
const modelTreeLoading = ref(false)
const currentModelPickerOptions = ref<any[]>([])
const modelPickerPath = ref<any[]>([])
const selectedModelPathNames = ref<string[]>([])
let modelSearchTimer: any = null
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
  showAddDialog.value = true
}

defineExpose({
  openAddDialog: handleBatchAdd
})

const closeAddDialog = () => {
  showAddDialog.value = false
  clearModelSuggestions()
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
  newDevice.value.category_id = 0
  newDevice.value.category_path = []
  selectedModelPathNames.value = []
  scheduleModelSearch()
}

const scheduleModelSearch = () => {
  if (modelSearchTimer) clearTimeout(modelSearchTimer)
  modelSearchTimer = setTimeout(searchModelSuggestions, 260)
}

const searchModelSuggestions = async () => {
  const keyword = String(newDevice.value.model || '').trim()
  if (!keyword) {
    modelSuggestions.value = []
    modelSearching.value = false
    return
  }

  modelSearching.value = true
  try {
    const res: any = await searchDeviceModelDictOptions({ keyword, limit: 20 })
    modelSuggestions.value = Array.isArray(res?.data) ? res.data : []
  } catch (error) {
    modelSuggestions.value = []
  } finally {
    modelSearching.value = false
  }
}

const selectModelSuggestion = (item: any) => {
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
  if (!currentModelPickerOptions.value.length) {
    await loadModelPickerChildren(0)
  }
}

const closeModelPicker = () => {
  showModelPicker.value = false
}

const resetModelPickerPath = async () => {
  modelPickerPath.value = []
  await loadModelPickerChildren(0)
}

const trimModelPickerPath = async (index: number) => {
  modelPickerPath.value = modelPickerPath.value.slice(0, index + 1)
  const last = modelPickerPath.value[modelPickerPath.value.length - 1]
  await loadModelPickerChildren(Number(last?.id || 0))
}

const hasModelChildren = (node: any) => {
  return Number(node?.has_children || 0) === 1
}

const handleModelPickerNode = async (node: any) => {
  if (hasModelChildren(node)) {
    modelPickerPath.value = [...modelPickerPath.value, node]
    await loadModelPickerChildren(Number(node.id || 0))
    return
  }

  const pathNodes = [...modelPickerPath.value, node]
  newDevice.value.model = node.node_name || node.model_name || ''
  newDevice.value.category_id = node.id || 0
  newDevice.value.category_path = pathNodes.map(item => item.id).filter(Boolean)
  selectedModelPathNames.value = pathNodes.map(item => item.node_name).filter(Boolean)
  closeModelPicker()
}

const loadModelPickerChildren = async (pid: number) => {
  modelTreeLoading.value = true
  try {
    const res: any = await getDeviceModelDictChildren({ pid, limit: 200 })
    currentModelPickerOptions.value = Array.isArray(res?.data) ? res.data : []
  } catch (error) {
    currentModelPickerOptions.value = []
    uni.showToast({
      title: '型号分类加载失败',
      icon: 'none'
    })
  } finally {
    modelTreeLoading.value = false
  }
}

const clearModelSuggestions = () => {
  if (modelSearchTimer) clearTimeout(modelSearchTimer)
  modelFocused.value = false
  modelSearching.value = false
  modelSuggestions.value = []
}

const normalizeUserSn = (value: any, maxLength = 64) => {
  return String(value || '').replace(/[^a-zA-Z0-9]/g, '').slice(0, maxLength)
}

const handleUserSnInput = () => {
  if (userSnFromScan.value) {
    newDevice.value.user_sn = normalizeUserSn(newDevice.value.user_sn, 64)
    return
  }
  newDevice.value.user_sn = normalizeUserSn(newDevice.value.user_sn, 6)
}

const scanUserSn = () => {
  clearModelSuggestions()
  uni.scanCode({
    scanType: ['barCode', 'qrCode'],
    success: (res: any) => {
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
    },
    fail: () => {
      uni.showToast({
        title: '扫码取消或失败，可手动输入',
        icon: 'none'
      })
    }
  })
}

const confirmAdd = () => {
  // 验证必填项
  if (!newDevice.value.model?.trim()) {
    uni.showToast({
      title: '请输入设备名称',
      icon: 'none'
    })
    return
  }

  if (!newDevice.value.user_sn?.trim()) {
    uni.showToast({
      title: '请输入串号后6位',
      icon: 'none'
    })
    return
  }

  const userSnLength = String(newDevice.value.user_sn || '').length
  if (userSnLength < 6 || (!userSnFromScan.value && userSnLength !== 6)) {
    uni.showToast({
      title: '请手动输入6位，或扫码录入完整串号',
      icon: 'none'
    })
    return
  }

  // if (!newDevice.value.initial_price?.trim()) {
  //   uni.showToast({
  //     title: '请输入定价',
  //     icon: 'none'
  //   })
  //   return
  // }

  // 添加设备
  const newDevices = [...props.devices, { ...newDevice.value }]
  emit('update:devices', newDevices)

  // 自动更新数量
  emit('update:count', newDevices.length)

  // 关闭弹窗
  showAddDialog.value = false
  clearModelSuggestions()
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

  uni.showToast({
    title: '添加成功',
    icon: 'success'
  })
}
</script>

<style scoped lang="scss">
.label {
  font-size: 14px;
  color: #374151;
}

.space-y-2 > view:not(:last-child) {
  margin-bottom: 0.5rem;
}

.add-dialog {
  width: 640rpx;
  max-height: 86vh;
  background: #fff;
  border-radius: 20rpx;
  overflow: hidden;
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding: 32rpx 32rpx 24rpx;
  border-bottom: 1px solid #f0f0f0;
}

.dialog-title {
  display: block;
  font-size: 18px;
  font-weight: 600;
  color: #333;
  line-height: 1.4;
}

.dialog-subtitle {
  display: block;
  margin-top: 6rpx;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.5;
}

.dialog-content {
  padding: 32rpx;
  max-height: 58vh;
  overflow-y: auto;
}

.dialog-tip {
  display: flex;
  align-items: flex-start;
  gap: 12rpx;
  padding: 18rpx 20rpx;
  margin-bottom: 28rpx;
  background: #f8fafc;
  border-radius: 12rpx;
}

.dialog-tip__badge {
  flex-shrink: 0;
  padding: 4rpx 10rpx;
  background: rgba(79, 70, 229, 0.1);
  color: var(--primary-color);
  border-radius: 999rpx;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
}

.dialog-tip__text {
  flex: 1;
  min-width: 0;
  font-size: 12px;
  color: #4b5563;
  line-height: 1.5;
}

.form-item {
  position: relative;
  margin-bottom: 24rpx;
}

.form-label {
  display: flex;
  align-items: center;
  margin-bottom: 12rpx;
  font-size: 14px;
  color: #333;
}

.required {
  color: #ef4444;
  margin-left: 4rpx;
}

.form-hint {
  display: block;
  margin-top: 8rpx;
  font-size: 12px;
  color: #999;
}

.search-strong-tip {
  display: flex;
  align-items: center;
  gap: 8rpx;
  padding: 14rpx 16rpx;
  margin-bottom: 12rpx;
  border-radius: 10rpx;
  background: rgba(79, 70, 229, 0.08);
  color: #4f46e5;
  font-size: 12px;
  line-height: 1.5;
}

.input-wrapper {
  width: 100%;
  position: relative;
}

.custom-input {
  width: 100%;
  height: 80rpx;
  padding: 0 24rpx;
  border: 1px solid #e5e5e5;
  border-radius: 8rpx;
  font-size: 14px;
  background: #fff;
  box-sizing: border-box;
}

.custom-input--with-action {
  padding-right: 86rpx;
}

.custom-input--with-picker {
  padding-right: 118rpx;
}

.input-action {
  position: absolute;
  right: 0;
  top: 0;
  width: 82rpx;
  height: 80rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.model-picker-action {
  position: absolute;
  right: 0;
  top: 0;
  width: 112rpx;
  height: 80rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4f46e5;
  font-size: 13px;
  font-weight: 600;
}

.custom-input:focus {
  border-color: var(--primary-color);
  outline: none;
}

.model-suggestions {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% - 2rpx);
  z-index: 20;
  border: 1px solid #e5e7eb;
  border-radius: 10rpx;
  background: #fff;
  max-height: 280rpx;
  overflow-y: auto;
  box-shadow: 0 16rpx 36rpx rgba(15, 23, 42, 0.14);
}

.model-suggestion-item {
  min-height: 72rpx;
  padding: 0 22rpx;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #f3f4f6;
  box-sizing: border-box;
}

.model-suggestion-item:last-child {
  border-bottom: none;
}

.model-suggestion-name {
  font-size: 14px;
  color: #1f2937;
  line-height: 1.4;
}

.model-suggestion-empty {
  min-height: 72rpx;
  padding: 0 22rpx;
  display: flex;
  align-items: center;
  font-size: 13px;
  color: #9ca3af;
}

.model-picker {
  height: 78vh;
  background: #fff;
  border-radius: 28rpx 28rpx 0 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.model-picker-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20rpx;
  padding: 30rpx 32rpx 20rpx;
  border-bottom: 1px solid #f3f4f6;
}

.model-picker-title {
  display: block;
  font-size: 17px;
  font-weight: 700;
  color: #111827;
}

.model-picker-close {
  width: 56rpx;
  height: 56rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.model-picker-search-tip {
  display: flex;
  align-items: center;
  gap: 8rpx;
  margin: 20rpx 32rpx 0;
  padding: 16rpx 18rpx;
  border-radius: 12rpx;
  background: rgba(79, 70, 229, 0.08);
  color: #4f46e5;
  font-size: 12px;
  line-height: 1.5;
}

.model-picker-breadcrumb {
  display: flex;
  gap: 10rpx;
  padding: 20rpx 32rpx 14rpx;
  overflow-x: auto;
  white-space: nowrap;
}

.breadcrumb-item {
  flex-shrink: 0;
  padding: 8rpx 14rpx;
  border-radius: 999rpx;
  background: #f3f4f6;
  color: #4b5563;
  font-size: 12px;
}

.breadcrumb-item.root {
  background: rgba(79, 70, 229, 0.1);
  color: #4f46e5;
}

.model-picker-list {
  flex: 1;
  min-height: 0;
  border-top: 1px solid #f3f4f6;
}

.model-picker-row {
  min-height: 96rpx;
  padding: 18rpx 32rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20rpx;
  border-bottom: 1px solid #f3f4f6;
  box-sizing: border-box;
}

.model-picker-row-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6rpx;
}

.model-picker-row-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.model-picker-row-path {
  font-size: 11px;
  color: #9ca3af;
}

.model-picker-row-action {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 6rpx;
  color: #9ca3af;
  font-size: 12px;
}

.model-picker-empty {
  min-height: 180rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 13px;
}

.dialog-footer {
  display: flex;
  border-top: 1px solid #f0f0f0;
}

.dialog-button {
  flex: 1;
  height: 100rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.dialog-button.cancel {
  color: #666;
  border-right: 1px solid #f0f0f0;
}

.dialog-button.confirm {
  color: var(--primary-color);
  font-weight: 600;
}
</style>
