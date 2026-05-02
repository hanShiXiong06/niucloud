import { ref, onMounted } from 'vue'
import type { PlatformDeliveryForm, AddressInfo } from '../types/order'
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

  // 当前激活的服务商，目前 2.0 阶段仅支持 yisu
  const activeProvider = ref('')

  // 预约时间选项列表，保留字段用于兼容旧组件绑定
  const pickupTimeOptions = ref<Array<{ label: string; value: string }>>([])

  // 亿速不需要预约时间，保留字段用于兼容旧组件绑定
  const needPickupTime = ref(false)

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
   * 检测当前启用的快递服务商
   */
  const detectProvider = async () => {
    try {
      const res: any = await checkExpressEnabled()
      if (res.code === 1 && res.data) {
        activeProvider.value = res.data.provider || ''
        needPickupTime.value = false
      }
    } catch (error) {
      console.error('检测快递服务商失败：', error)
      activeProvider.value = ''
      needPickupTime.value = false
    }
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
    enablePlatformDelivery.value = true

    // 先检测服务商（如果还没检测过）
    if (!activeProvider.value) {
      await detectProvider()
    }

    // 切换到平台快递时，如果没有地址则加载默认地址
    if (!platformDeliveryForm.value.sender_name) {
      await loadDefaultAddress()
    }

  }

  // 2.0 阶段仅支持亿速，亿速不需要预约时间。
  const loadPickupTime = async () => {
    pickupTimeOptions.value = []
    platformDeliveryForm.value.pickup_time = ''
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
      weight: '1.0'
    }
    enablePlatformDelivery.value = false
  }

  return {
    enablePlatformDelivery,
    activeProvider,
    needPickupTime,
    platformDeliveryForm,
    pickupTimeOptions,
    fillAddressFromSelected,
    handlePlatformDeliveryToggle,
    loadPickupTime,
    loadDefaultAddress,
    resetPlatformDeliveryForm
  }
}
