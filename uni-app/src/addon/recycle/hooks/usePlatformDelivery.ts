import { ref, onMounted } from 'vue'
import type { PlatformDeliveryForm, AddressInfo } from '../types/order'
import { useAddressParser } from './useAddressParser'
import { getPickupTimes } from '../api/order'
import { getAddressList } from '@/app/api/member'

/**
 * 平台快递管理
 */
export function usePlatformDelivery() {
  const { parseAddressInfo } = useAddressParser()

  // 是否使用平台快递
  const enablePlatformDelivery = ref(true)

  // 预约时间选项列表
  const pickupTimeOptions = ref<Array<{ label: string; value: string }>>([])

  // 平台快递表单数据
  const platformDeliveryForm = ref<PlatformDeliveryForm>({
    sender_name: '',
    sender_mobile: '',
    province: '',
    city: '',
    district: '',
    area_text: '',
    detail_address: '',
    pickup_time: '',
    weight: '1.0'
  })

  /**
   * 加载默认地址
   */
  const loadDefaultAddress = async () => {
    try {
      const res: any = await getAddressList({})
      if (res.code === 1 && res.data && res.data.length > 0) {
        // 查找默认地址
        const defaultAddress = res.data.find((addr: any) => addr.is_default === 1)
        // 如果有默认地址，使用默认地址；否则使用第一个地址
        const addressToUse = defaultAddress || res.data[0]

        if (addressToUse) {
          fillAddressFromSelected(addressToUse)
        }
      }
    } catch (error) {
      console.error('加载默认地址失败：', error)
    }
  }

  // 跳转到地址选择页面
  const goToSelectAddress = () => {
    // 设置回调信息（在跳转后再设置，避免被 onLoad 清除）
    const callbackData = {
      back: '/addon/recycle/pages/order/order',
      delivery: 'express',
      address_id: 0
    }

    console.log('准备跳转到地址列表，回调数据：', callbackData)

    // 先跳转，然后在 onLoad 之后通过其他方式传递回调
    // 注意：不传 source 参数，避免地址被过滤
    uni.navigateTo({
      url: '/app/pages/member/address',
      success() {
        console.log('跳转成功')
        // 延迟设置回调数据，确保在 onLoad 清除之后设置
        setTimeout(() => {
          uni.setStorage({
            key: 'selectAddressCallback',
            data: callbackData,
            success() {
              console.log('回调数据已保存（延迟）')
            }
          })
        }, 100)
      },
      fail(err) {
        console.error('跳转失败：', err)
      }
    })
  }

  /**
   * 从选中的地址填充表单
   * @param address 地址对象，包含省市区和详细地址信息
   */
  const fillAddressFromSelected = (address: AddressInfo) => {
    if (!address) {
      console.error('地址数据为空')
      return
    }

    console.log('开始填充地址数据：', address)

    // 填充基本信息
    platformDeliveryForm.value.sender_name = address.name || ''
    platformDeliveryForm.value.sender_mobile = address.mobile || ''

    // 解析省市区信息
    const addressParts = parseAddressInfo(address)

    platformDeliveryForm.value.province = addressParts.province
    platformDeliveryForm.value.city = addressParts.city
    platformDeliveryForm.value.district = addressParts.district
    platformDeliveryForm.value.area_text = addressParts.areaText
    platformDeliveryForm.value.detail_address = addressParts.detailAddress

    console.log('填充后的表单数据：', platformDeliveryForm.value)

    uni.showToast({
      title: '地址已选择',
      icon: 'success'
    })
  }

  // 处理平台快递切换
  const handlePlatformDeliveryToggle = async () => {
    enablePlatformDelivery.value = true

    // 切换到平台快递时，如果没有地址则加载默认地址
    if (!platformDeliveryForm.value.sender_name) {
      await loadDefaultAddress()
    }

    // 如果没有预约时间则加载
    if (!platformDeliveryForm.value.pickup_time) {
      await loadPickupTime()
    }
  }

  // 加载预约时间
  const loadPickupTime = async () => {
    try {
      uni.showLoading({ title: '加载中...' })
      const res = await getPickupTimes()
      if (res.code === 1 && res.data) {
        // 使用服务端返回的时间生成时间选项
        const serverTime = res.data.pickup_time
        const options = generatePickupTimeOptions(serverTime)
        pickupTimeOptions.value = options

        // 默认选择第一个时间
        if (options.length > 0) {
          platformDeliveryForm.value.pickup_time = options[0].value
        }
      } else {
        uni.showToast({
          title: res.msg || '获取预约时间失败',
          icon: 'none'
        })
      }
    } catch (error) {
      console.error('获取预约时间失败：', error)
      uni.showToast({
        title: '获取预约时间失败',
        icon: 'none'
      })
    } finally {
      uni.hideLoading()
    }
  }

  /**
   * 生成预约时间选项
   * @param serverTimeStr 服务端返回的时间字符串,格式如 "2026-01-24 14:53:00"
   */
  const generatePickupTimeOptions = (serverTimeStr: string): Array<{ label: string; value: string }> => {
    try {
      // 解析服务端时间
      const serverDate = new Date(serverTimeStr.replace(/-/g, '/'))
      const now = new Date()

      // 判断是今天还是明天
      const serverDay = serverDate.getDate()
      const todayDay = now.getDate()
      const isToday = serverDay === todayDay
      const isTomorrow = serverDay === todayDay + 1

      const options: Array<{ label: string; value: string }> = []
      const year = serverDate.getFullYear()
      const month = String(serverDate.getMonth() + 1).padStart(2, '0')
      const day = String(serverDate.getDate()).padStart(2, '0')
      const datePrefix = isToday ? '今天' : isTomorrow ? '明天' : `${month}-${day}`

      if (isToday) {
        // 今天：从当前时间+2小时开始，到19:00结束
        const currentHour = serverDate.getHours()
        const startHour = Math.max(currentHour + 2, 8) // 最早8点
        const endHour = 19 // 最晚19点

        for (let hour = startHour; hour <= endHour; hour++) {
          const timeStr = String(hour).padStart(2, '0') + ':00'
          options.push({
            label: `${datePrefix} ${timeStr}前`,
            value: `${year}-${month}-${day} ${timeStr}:00` // 标准格式: 2026-01-24 08:00:00
          })
        }
      } else {
        // 明天或其他日期：从8:00到19:00
        const startHour = 8
        const endHour = 19

        for (let hour = startHour; hour <= endHour; hour++) {
          const timeStr = String(hour).padStart(2, '0') + ':00'
          options.push({
            label: `${datePrefix} ${timeStr}前`,
            value: `${year}-${month}-${day} ${timeStr}:00` // 标准格式
          })
        }
      }

      // 如果没有可用时间（比如已经超过19点），返回明天的时间
      if (options.length === 0) {
        const tomorrow = new Date(serverDate)
        tomorrow.setDate(tomorrow.getDate() + 1)
        const tomorrowYear = tomorrow.getFullYear()
        const tomorrowMonth = String(tomorrow.getMonth() + 1).padStart(2, '0')
        const tomorrowDay = String(tomorrow.getDate()).padStart(2, '0')

        for (let hour = 8; hour <= 19; hour++) {
          const timeStr = String(hour).padStart(2, '0') + ':00'
          options.push({
            label: `明天 ${timeStr}前`,
            value: `${tomorrowYear}-${tomorrowMonth}-${tomorrowDay} ${timeStr}:00` // 标准格式
          })
        }
      }

      return options
    } catch (error) {
      console.error('生成预约时间选项失败：', error)
      // 返回默认选项
      const now = new Date()
      const year = now.getFullYear()
      const month = String(now.getMonth() + 1).padStart(2, '0')
      const day = String(now.getDate()).padStart(2, '0')
      return [
        { label: '今天 19:00前', value: `${year}-${month}-${day} 19:00:00` }
      ]
    }
  }

  // 初始化：默认加载地址和预约时间
  onMounted(async () => {
    if (enablePlatformDelivery.value) {
      await Promise.all([
        loadDefaultAddress(),
        loadPickupTime()
      ])
    }
  })

  // 重置平台快递表单
  const resetPlatformDeliveryForm = () => {
    platformDeliveryForm.value = {
      sender_name: '',
      sender_mobile: '',
      province: '',
      city: '',
      district: '',
      area_text: '',
      detail_address: '',
      pickup_time: '',
      weight: '1.0'
    }
    enablePlatformDelivery.value = false
  }

  return {
    enablePlatformDelivery,
    platformDeliveryForm,
    pickupTimeOptions,
    goToSelectAddress,
    fillAddressFromSelected,
    handlePlatformDeliveryToggle,
    loadPickupTime,
    loadDefaultAddress,
    resetPlatformDeliveryForm
  }
}
