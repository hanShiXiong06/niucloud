import { ref } from 'vue'
import type { Device } from '../types/order'

/**
 * 设备管理逻辑
 */
export function useDeviceManagement() {
  const phoneList = ref<Device[]>([])

  // 添加设备到列表
  const addDevices = (devices: Device[]) => {
    devices.forEach(device => {
      phoneList.value.push({
        imei: device.imei,
        initial_price: device.initial_price
      })
    })
  }

  // 删除设备
  const removeDevice = (index: number) => {
    phoneList.value.splice(index, 1)
  }

  // 扫描 IMEI
  const scanIMEI = (): Promise<string> => {
    return new Promise((resolve, reject) => {
      uni.scanCode({
        onlyFromCamera: true,
        success: (res) => {
          if (res.errMsg === 'scanCode:ok') {
            resolve(res.result)
          } else {
            uni.showToast({
              title: res.errMsg || '扫码失败',
              icon: 'none'
            })
            reject(new Error(res.errMsg))
          }
        },
        fail: (err) => {
          uni.showToast({
            title: '扫码失败',
            icon: 'none'
          })
          reject(err)
        }
      })
    })
  }

  // 处理数量变化
  const handleCountChange = (value: number) => {
    // 如果手动修改数量小于已扫描的手机数量，删除多余的手机记录
    if (value < phoneList.value.length) {
      phoneList.value = phoneList.value.slice(0, value)
    }
  }

  return {
    phoneList,
    addDevices,
    removeDevice,
    scanIMEI,
    handleCountChange
  }
}
