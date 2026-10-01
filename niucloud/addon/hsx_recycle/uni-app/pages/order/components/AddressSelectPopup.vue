<template>
  <OrderTaskPopup :show="show && !showEditPopup" title="选择寄件地址" height="70vh" @close="handleClose">
    <view v-if="loading" class="address-state"><up-loading-icon mode="circle" /><text>正在加载地址</text></view>
    <view v-else-if="loadFailed" class="address-state">
      <text>地址加载失败</text><OrderUiButton @click="loadAddressList">重新加载</OrderUiButton>
    </view>
    <view v-else-if="!addressList.length" class="address-state"><up-icon name="map" size="36" color="var(--recycle-text-sub)" /><text>暂无寄件地址</text></view>
    <view v-else class="address-list">
      <view v-for="item in addressList" :key="item.id" class="address-item" @tap="selectAddress(item)">
        <view class="address-copy">
          <text class="address-text">{{ item.full_address }}</text>
          <view class="address-contact"><text>{{ item.name }}</text><text>{{ item.mobile }}</text><text v-if="item.is_default" class="default-badge">默认</text></view>
        </view>
        <up-icon name="arrow-right" size="16" color="var(--recycle-text-sub)" />
      </view>
    </view>
    <template #footer><OrderUiButton block variant="primary" icon="plus" @click="showEditPopup = true">添加寄件地址</OrderUiButton></template>
  </OrderTaskPopup>
  <AddressEditPopup :show="showEditPopup" @update:show="showEditPopup = $event" @success="handleAddressAdded" />
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getAddressList } from '@/app/api/member'
import AddressEditPopup from './AddressEditPopup.vue'
import OrderTaskPopup from './OrderTaskPopup.vue'
import OrderUiButton from './OrderUiButton.vue'

interface Props {
  show: boolean
}

interface AddressItem {
  id: number
  name: string
  mobile: string
  full_address: string
  is_default: number
  province: string
  city: string
  district: string
  address: string
  [key: string]: any
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:show': [value: boolean]
  'select': [address: AddressItem]
}>()

const loading = ref(false)
const loadFailed = ref(false)
const addressList = ref<AddressItem[]>([])
const showEditPopup = ref(false)

// 加载地址列表
const loadAddressList = async () => {
  if (loading.value) return
  try {
    loading.value = true
    loadFailed.value = false
    const res: any = await getAddressList({})
    if (res.code === 1 && res.data) {
      addressList.value = Array.isArray(res.data) ? res.data : []
    } else {
      loadFailed.value = true
    }
  } catch (error) {
    loadFailed.value = true
    console.error('加载地址列表失败：', error)
    uni.showToast({
      title: '加载地址失败',
      icon: 'none'
    })
  } finally {
    loading.value = false
  }
}

// 监听弹窗显示状态，显示时加载地址列表
watch(() => props.show, (newVal) => {
  if (newVal) {
    loadAddressList()
  }
})

// 选择地址
const selectAddress = (address: AddressItem) => {
  emit('select', address)
  handleClose()
}

// 关闭弹窗
const handleClose = () => {
  emit('update:show', false)
}

// 地址添加成功后刷新列表
const handleAddressAdded = () => {
  loadAddressList()
}
</script>

<style scoped lang="scss">
.address-list { padding: 0 16px; }
.address-item { display: flex; align-items: center; gap: 12px; padding: 20px 0; border-bottom: 1rpx solid var(--recycle-line); }
.address-item:active { background: var(--recycle-bg-soft); }
.address-copy { flex: 1; min-width: 0; }
.address-text { display: block; font-size: 15px; line-height: 24px; font-weight: 500; overflow-wrap: anywhere; }
.address-contact { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-top: 8px; font-size: 13px; line-height: 20px; color: var(--recycle-text-sub); }
.default-badge { color: var(--recycle-brand); padding: 0 6px; background: var(--recycle-bg-soft); border-radius: 4px; font-size: 11px; }
.address-state { min-height: 220px; display: flex; flex-direction: column; gap: 16px; align-items: center; justify-content: center; font-size: 14px; color: var(--recycle-text-sub); }
</style>
