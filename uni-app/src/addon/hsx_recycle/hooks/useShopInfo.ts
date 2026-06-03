import { ref } from 'vue'
import type { ShopInfo } from '../types/order'
import { getShopAddressList } from '../api/recycle'
import { copy } from '@/utils/common'

/**
 * 商家信息管理
 */
export function useShopInfo() {
  const shopInfo = ref<ShopInfo>({
    name: '',
    mobile: '',
    business_hours: '',
    lat: '',
    lng: '',
    full_address: '',
    address: ''
  })

  // 获取商家信息
  const fetchShopInfo = async () => {
    try {
      const res = await getShopAddressList()
      if (res.data && res.data.data && res.data.data.length > 0) {
        const data = res.data.data[0]
        shopInfo.value = {
          name: data.contact_name || '',
          mobile: data.mobile || '',
          business_hours: data.business_hours || '',
          lat: data.lat || '',
          lng: data.lng || '',
          full_address: data.full_address || '',
          address: data.address || ''
        }
      }
    } catch (error) {
      uni.showToast({
        title: '无邮寄地址!请联系商家',
        icon: 'none'
      })
      console.error('获取地址失败', error)
    }
  }

  // 复制商家信息
  const copyShopInfo = () => {
    const text = `${shopInfo.value.name}, ${shopInfo.value.mobile}, ${shopInfo.value.full_address}`
    copy(text, () => {
      uni.showToast({
        title: '复制成功',
        icon: 'success'
      })
    })
  }

  // 打开地图导航
  const openLocation = () => {
    if (!shopInfo.value.lat || !shopInfo.value.lng) {
      uni.showToast({
        title: '无法获取商家位置信息',
        icon: 'none'
      })
      return
    }

    uni.openLocation({
      latitude: parseFloat(shopInfo.value.lat),
      longitude: parseFloat(shopInfo.value.lng),
      name: shopInfo.value.name || '商家地址',
      address: shopInfo.value.full_address || shopInfo.value.address,
      success: () => {
        console.log('打开导航成功')
      },
      fail: (err) => {
        console.error('打开导航失败：', err)
        uni.showToast({
          title: '打开导航失败',
          icon: 'none'
        })
      }
    })
  }

  return {
    shopInfo,
    fetchShopInfo,
    copyShopInfo,
    openLocation
  }
}
