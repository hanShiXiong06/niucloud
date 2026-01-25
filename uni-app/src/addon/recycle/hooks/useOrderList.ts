import { ref } from 'vue'
import type { OrderListItem, OrderFilters, MescrollUpOption } from '../types/order'
import { getOrderList } from '../api/order'

/**
 * 订单列表管理
 * 集成 mescroll 实现下拉刷新和上拉加载
 */
export function useOrderList() {
  // 订单列表
  const orderList = ref<OrderListItem[]>([])

  // mescroll 实例
  const mescrollRef = ref<any>(null)

  // 加载状态
  const loading = ref(false)

  // mescroll 上拉加载配置
  const upOption: MescrollUpOption = {
    auto: true,  // 自动加载第一页
    page: {
      num: 0,  // 当前页码
      size: 10  // 每页数量
    },
    noMoreSize: 5,  // 如果列表已无数据,可设置列表的总数量要大于等于5条才显示无更多数据
    empty: {
      tip: '暂无订单数据'
    }
  }

  // mescroll 下拉刷新配置
  const downOption = {
    auto: false,  // 不自动下拉刷新
    textInOffset: '下拉刷新',
    textOutOffset: '释放更新',
    textLoading: '加载中...'
  }

  // 空状态配置
  const emptyOption = {
    tip: '暂无订单数据',
    icon: '/static/images/empty/order.png'
  }

  /**
   * 下拉刷新回调
   */
  const mescrollDown = (mescroll: any) => {
    // 重置页码
    mescroll.resetUpScroll()
  }

  /**
   * 上拉加载回调
   */
  const mescrollUp = async (mescroll: any, filters: OrderFilters) => {
    try {
      loading.value = true

      const params: any = {
        page: mescroll.num,
        limit: mescroll.size
      }

      // 添加筛选条件
      if (filters.status !== 0) {
        params.status = filters.status
      }
      if (filters.delivery_type !== 0) {
        params.delivery_type = filters.delivery_type
      }
      if (filters.search_keyword) {
        params.keyword = filters.search_keyword
      }

      const res = await getOrderList(params)

      if (res.code === 1) {
        const list = res.data.data || []  // API 返回的是 res.data.data 不是 res.data.list

        // 第一页清空列表
        if (mescroll.num === 1) {
          orderList.value = []
        }

        // 追加数据
        orderList.value = [...orderList.value, ...list]

        // 数据加载完成
        mescroll.endSuccess(list.length)

        // 判断是否还有更多数据
        if (list.length < mescroll.size) {
          mescroll.endUpScroll(false)  // 没有更多数据
        }
      } else {
        // 加载失败
        mescroll.endErr()
        uni.showToast({
          title: res.msg || '加载失败',
          icon: 'none'
        })
      }
    } catch (error) {
      console.error('加载订单列表失败：', error)
      mescroll.endErr()
      uni.showToast({
        title: '加载失败',
        icon: 'none'
      })
    } finally {
      loading.value = false
    }
  }

  /**
   * 刷新列表
   */
  const refreshList = () => {
    if (mescrollRef.value && mescrollRef.value.mescroll) {
      mescrollRef.value.mescroll.resetUpScroll()
    }
  }

  /**
   * 从列表中移除订单
   */
  const removeOrder = (orderId: number) => {
    const index = orderList.value.findIndex(item => item.id === orderId)
    if (index > -1) {
      orderList.value.splice(index, 1)
    }
  }

  /**
   * 更新订单状态
   */
  const updateOrderStatus = (orderId: number, status: number, statusName: string) => {
    const order = orderList.value.find(item => item.id === orderId)
    if (order) {
      order.status = status
      order.status_name = statusName
    }
  }

  return {
    orderList,
    mescrollRef,
    loading,
    upOption,
    downOption,
    emptyOption,
    mescrollDown,
    mescrollUp,
    refreshList,
    removeOrder,
    updateOrderStatus
  }
}
