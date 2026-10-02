import { ref, onMounted } from 'vue'
import type { PlatformDeliveryForm, AddressInfo, PickupTimeDay } from '../types/order'
import type { ExpressCheckResult } from '../api/express'
import { useAddressParser } from './useAddressParser'
import { checkExpressEnabled } from '../api/express'
import { getAddressList } from '@/app/api/member'

/**
 * 平台快递管理
 */
export function usePlatformDelivery() {
  const { parseAddressInfo } = useAddressParser()

  // 是否使用平台快递
  const enablePlatformDelivery = ref(false)

  // 是否能预约以本站服务端检查为准，不因有一个服务商名称就当作已配置。
  const checkingPickup = ref(true)
  const pickupAvailable = ref(false)
  const pickupUnavailableReason = ref('正在确认门店取件服务')
  const carrierName = ref('')
  const paymentTips = ref('')
  const pickupTimeSupported = ref(false)
  const needPickupTime = ref(false)
  const pickupTimeOptions = ref<PickupTimeDay[]>([])

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

  // 展示与提交复用服务器校验结果；客户已经选定的日期不能被默认时间覆盖。
  const applyPickupPolicy = (data: Partial<ExpressCheckResult>) => {
    paymentTips.value = String(data.payment_tips || '').trim()
    pickupTimeSupported.value = data.pickup_time_supported === true
    needPickupTime.value = pickupTimeSupported.value && data.pickup_time_required === true
    pickupTimeOptions.value = pickupTimeSupported.value && Array.isArray(data.pickup_time_options) ? data.pickup_time_options : []
    platformDeliveryForm.value.pickup_time_required = needPickupTime.value
    platformDeliveryForm.value.pickup_time = pickupTimeSupported.value ? String(data.pickup_time || '') : ''
    platformDeliveryForm.value.pickup_time_text = pickupTimeSupported.value ? String(data.pickup_time_text || '') : ''
    if (data.pickup_time_changed || !pickupTimeSupported.value) platformDeliveryForm.value.pickup_time_selected = false
    return data.pickup_time_changed === true
  }

  /**
   * 检测当前启用的快递服务商
   */
  const detectProvider = async () => {
    checkingPickup.value = true
    try {
      const res: any = await checkExpressEnabled(platformDeliveryForm.value.pickup_time_selected ? platformDeliveryForm.value.pickup_time : '')
      if (res.code !== 1 || !res.data) throw new Error('pickup unavailable')
      const data = res.data
      pickupAvailable.value = Boolean(data.pickup_enabled ?? data.enabled) && data.has_shop_address === true
      carrierName.value = String(data.carrier_name || '')
      applyPickupPolicy(data)
      pickupUnavailableReason.value = pickupAvailable.value ? '' : String(data.unavailable_reason || '门店暂未配置可用的上门取件服务，请联系门店或自行寄件')
    } catch (_) {
      pickupAvailable.value = false
      paymentTips.value = ''
      pickupUnavailableReason.value = '暂时无法确认上门取件服务，请稍后重试或联系门店'
      pickupTimeSupported.value = false
      pickupTimeOptions.value = []
      needPickupTime.value = false
      platformDeliveryForm.value.pickup_time_required = false
      platformDeliveryForm.value.pickup_time = ''
      platformDeliveryForm.value.pickup_time_text = ''
    } finally {
      checkingPickup.value = false
      if (!pickupAvailable.value) enablePlatformDelivery.value = false
    }
    return pickupAvailable.value
  }

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

  /**
   * 从选中的地址填充表单
   * @param address 地址对象，包含省市区和详细地址信息
   */
  const fillAddressFromSelected = (address: AddressInfo) => {
    if (!address) {
      console.error('地址数据为空')
      return
    }

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

    uni.showToast({
      title: '地址已选择',
      icon: 'success'
    })
  }

  // 处理平台快递切换
  const handlePlatformDeliveryToggle = async () => {
    if (!pickupAvailable.value) return
    enablePlatformDelivery.value = true

    // 切换到平台快递时，如果没有地址则加载默认地址
    if (!platformDeliveryForm.value.sender_name) {
      await loadDefaultAddress()
    }

  }

  // 初始化：检测服务商 + 加载地址
  onMounted(async () => {
    // 始终检测服务商，以便子组件能知道是否需要显示预约时间
    await detectProvider()

    if (enablePlatformDelivery.value) {
      await loadDefaultAddress()
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
      weight: '1.0',
      pickup_time_required: needPickupTime.value
    }
    enablePlatformDelivery.value = false
  }

  return {
    enablePlatformDelivery,
    checkingPickup,
    pickupAvailable,
    pickupUnavailableReason,
    carrierName,
    paymentTips,
    pickupTimeSupported,
    pickupTimeOptions,
    applyPickupPolicy,
    needPickupTime,
    platformDeliveryForm,
    detectProvider,
    fillAddressFromSelected,
    handlePlatformDeliveryToggle,
    loadDefaultAddress,
    resetPlatformDeliveryForm
  }
}
