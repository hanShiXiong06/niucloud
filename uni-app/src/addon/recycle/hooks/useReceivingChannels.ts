import { ref, onMounted } from 'vue'
import { getReceivingChannels } from '../api/order'

/**
 * 渠道项接口
 */
export interface ChannelItem {
  name: string
  value: string
  sort: number
  memo: string
}

/**
 * 收货渠道管理
 * 管理后台配置的收货渠道（如顺丰快递等）
 */
export function useReceivingChannels() {
  // 渠道列表
  const channels = ref<ChannelItem[]>([])

  // 加载状态
  const loading = ref(false)

  // 默认选中的渠道 value（根据 sort 最大值）
  const defaultChannelValue = ref<string>('')

  /**
   * 获取收货渠道配置
   */
  const fetchChannels = async () => {
    try {
      loading.value = true
      const res: any = await getReceivingChannels()

      if (res.code === 1 && res.data && res.data.dictionary) {
        // 按 sort 降序排序（sort 值越大优先级越高）
        channels.value = res.data.dictionary.sort(
          (a: ChannelItem, b: ChannelItem) => b.sort - a.sort
        )

        // 设置默认渠道
        if (channels.value.length > 0) {
          defaultChannelValue.value = channels.value[0].value
        } else {
          defaultChannelValue.value = ''
        }
      } else {
        // 接口返回失败，设置为空
        channels.value = []
        defaultChannelValue.value = ''
      }
    } catch (error) {
      console.error('获取收货渠道配置失败：', error)
      // 加载失败，设置为空
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
    return value === '1' // value 为 "1" 表示平台快递（如顺丰）
  }

  /**
   * 获取默认的平台快递状态
   * 如果默认渠道是平台快递（value=1），返回 true，否则返回 false
   */
  const getDefaultPlatformDeliveryState = (): boolean => {
    return isPlatformChannel(defaultChannelValue.value)
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
