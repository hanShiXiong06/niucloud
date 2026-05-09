import { ref, computed, type Ref } from 'vue'
import type { OrderDetailDevice } from '../types/order'

/**
 * 设备选择管理
 * 处理设备的多选、全选、复制IMEI等功能
 */
export function useDeviceSelection(devicesRef: Ref<OrderDetailDevice[]>) {
  // 选中的设备ID列表
  const selectedDeviceIds = ref<number[]>([])

  // 选中状态映射（用于checkbox绑定）
  const selectedMap = ref<Record<number, boolean>>({})

  // 是否全选
  const isAllSelected = computed({
    get: () => {
      return devicesRef.value.length > 0 && selectedDeviceIds.value.length === devicesRef.value.length
    },
    set: (val) => {
      if (val) {
        // 全选
        selectedDeviceIds.value = devicesRef.value.map(device => device.id)
        devicesRef.value.forEach(device => {
          selectedMap.value[device.id] = true
        })
      } else {
        // 取消全选
        selectedDeviceIds.value = []
        devicesRef.value.forEach(device => {
          selectedMap.value[device.id] = false
        })
      }
    }
  })

  // 选中数量
  const selectedCount = computed(() => selectedDeviceIds.value.length)

  // 检查设备是否被选中
  const isDeviceSelected = (deviceId: number): boolean => {
    return !!selectedMap.value[deviceId]
  }

  // 切换设备选中状态
  const toggleDeviceSelection = (deviceId: number) => {
    selectedMap.value[deviceId] = !selectedMap.value[deviceId]

    if (selectedMap.value[deviceId]) {
      // 选中：添加到列表
      if (!selectedDeviceIds.value.includes(deviceId)) {
        selectedDeviceIds.value.push(deviceId)
      }
    } else {
      // 取消选中：从列表移除
      const index = selectedDeviceIds.value.indexOf(deviceId)
      if (index !== -1) {
        selectedDeviceIds.value.splice(index, 1)
      }
    }
  }

  // 全选/取消全选
  const toggleSelectAll = () => {
    isAllSelected.value = !isAllSelected.value
  }

  // 复制选中设备的IMEI
  const copySelectedIMEIs = () => {
    if (selectedDeviceIds.value.length === 0) {
      uni.showToast({
        title: '请先选择设备',
        icon: 'none'
      })
      return
    }

    const imeis = devicesRef.value
      .filter(device => selectedDeviceIds.value.includes(device.id))
      .map(device => device.imei)
      .join('\n')

    uni.setClipboardData({
      data: imeis,
      success: () => {
        uni.showToast({
          title: '已复制选中IMEI',
          icon: 'success'
        })
      }
    })
  }

  // 获取选中的可确认设备
  const getSelectedPendingDevices = () => {
    return devicesRef.value.filter(
      device => selectedDeviceIds.value.includes(device.id) && [3, 4, 7, 8].includes(Number(device.status))
    )
  }

  // 重置选择状态
  const resetSelection = () => {
    selectedDeviceIds.value = []
    selectedMap.value = {}
    devicesRef.value.forEach(device => {
      selectedMap.value[device.id] = false
    })
  }

  // 初始化选择状态
  const initSelection = () => {
    selectedDeviceIds.value = []
    selectedMap.value = {}
    devicesRef.value.forEach(device => {
      selectedMap.value[device.id] = false
    })
  }

  return {
    selectedDeviceIds,
    selectedMap,
    isAllSelected,
    selectedCount,
    isDeviceSelected,
    toggleDeviceSelection,
    toggleSelectAll,
    copySelectedIMEIs,
    getSelectedPendingDevices,
    resetSelection,
    initSelection
  }
}
