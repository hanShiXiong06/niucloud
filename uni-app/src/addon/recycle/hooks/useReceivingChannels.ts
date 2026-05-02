import { ref, onMounted } from 'vue'
import {
  checkExpressEnabled,
  getExpressProviders,
  type ExpressProvider
} from '../api/express'

/**
 * 渠道项接口
 */
export interface ChannelItem {
  name: string
  value: string
  sort: number
  memo: string
  provider: string
  provider_name: string
  support_quote?: boolean
  support_cancel?: boolean
  support_track?: boolean
}

/**
 * 收货渠道管理
 * 管理后台配置的统一快递服务商
 */
export function useReceivingChannels() {
  // 渠道列表
  const channels = ref<ChannelItem[]>([])

  // 加载状态
  const loading = ref(false)

  // 默认选中的渠道 value（优先使用默认服务商）
  const defaultChannelValue = ref<string>('')

  /**
   * 获取收货渠道配置
   */
  const fetchChannels = async () => {
    try {
      loading.value = true

      const [checkRes, providersRes]: any[] = await Promise.all([
        checkExpressEnabled(),
        getExpressProviders()
      ])

      const checkData = checkRes?.data || {}
      const isEnabled = checkRes?.code === 1 && !!checkData.enabled
      const providers = Array.isArray(providersRes?.data) ? providersRes.data : []

      if (isEnabled && providersRes?.code === 1 && providers.length > 0) {
        channels.value = providers
          .map((provider: ExpressProvider, index: number): ChannelItem => {
            const isDefault = Number(provider.is_default || 0) === 1
            return {
              name: provider.provider_name || '平台快递',
              value: provider.provider,
              sort: isDefault ? 1000 : 100 - index,
              memo: '',
              provider: provider.provider,
              provider_name: provider.provider_name || '',
              support_quote: provider.support_quote,
              support_cancel: provider.support_cancel,
              support_track: provider.support_track
            }
          })
          .sort((a: ChannelItem, b: ChannelItem) => b.sort - a.sort)

        const activeProvider = checkData.provider || ''
        const activeChannel = channels.value.find(channel => channel.provider === activeProvider)
        defaultChannelValue.value = activeChannel?.value || channels.value[0]?.value || ''
      } else {
        channels.value = []
        defaultChannelValue.value = ''
      }
    } catch (error) {
      console.error('获取收货渠道配置失败：', error)
      channels.value = []
      defaultChannelValue.value = ''
    } finally {
      loading.value = false
    }
  }

  /**
   * 根据 value 获取渠道信息
   */
  const getChannelByValue = (value: string): ChannelItem | undefined => {
    return channels.value.find(ch => ch.value === value)
  }

  /**
   * 检查是否为平台快递渠道
   * @param value 渠道 value
   */
  const isPlatformChannel = (value: string): boolean => {
    return channels.value.some(ch => ch.value === value)
  }

  /**
   * 获取默认的平台快递状态
   * 只要后端有可用服务商，就默认使用平台快递
   */
  const getDefaultPlatformDeliveryState = (): boolean => {
    return !!defaultChannelValue.value && isPlatformChannel(defaultChannelValue.value)
  }

  // 组件挂载时自动加载渠道配置
  onMounted(() => {
    fetchChannels()
  })

  return {
    channels,
    loading,
    defaultChannelValue,
    fetchChannels,
    getChannelByValue,
    isPlatformChannel,
    getDefaultPlatformDeliveryState
  }
}
