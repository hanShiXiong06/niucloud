import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getTodayStats,
  getUserStats,
  getOverviewStats,
  getUserDetailStats,
  getUserList,
  getMemberStatsOverview,
  getMemberRegisterTrend,
  getMemberChannelStats,
  getMemberInviteRank,
  getMemberActivityStats
} from '@/addon/recycle/api/stats'

/**
 * 统计数据管理 Hook
 */
export function useStatsData() {
  // 用户角色和基本信息
  const userRole = ref('user')
  const currentUserName = ref('')
  const currentUserId = ref(0)

  // 查询参数
  const queryParams = ref({
    start_time: '',
    end_time: ''
  })

  // 普通用户工作统计数据
  const userWorkStats = ref<any>({
    signed_order_count: 0,
    signed_device_count: 0,
    sign_category_breakdown: [],
    check_count: 0,
    check_category_breakdown: [],
    price_count: 0,
    price_category_breakdown: [],
    payment_count: 0,
    payment_category_breakdown: [],
    recycle_count: 0,
    return_count: 0,
    total_amount: 0
  })

  // 管理员数据
  const overviewStats = ref<any>({
    today_order_count: 0,
    yesterday_order_count: 0,
    today_check_count: 0,
    today_check_breakdown: [],
    today_payment_amount: 0,
    today_payment_count: 0,
    today_return_count: 0
  })

  const userDetailStats = ref<any[]>([])
  const userList = ref<any[]>([])
  const userLoading = ref(false)

  // 会员统计数据
  const memberStatsOverview = ref<any>({
    total_members: 0,
    new_members: 0,
    active_members: 0,
    invite_members: 0
  })
  const memberRegisterTrend = ref<any[]>([])
  const memberChannelStats = ref<any[]>([])
  const memberInviteRank = ref<any[]>([])
  const memberActivityStats = ref<any[]>([])
  const memberLoading = ref(false)

  // 获取用户角色和基本信息
  const fetchUserRole = async () => {
    try {
      const res = await getTodayStats({})
      if (res.data) {
        userRole.value = res.data.user_role || 'user'
        currentUserName.value = res.data.user_name || '用户'
        currentUserId.value = res.data.user_id || 0
      }
    } catch (e) {
      console.error('获取用户角色失败', e)
    }
  }

  // 获取普通用户工作统计
  const fetchUserWorkStats = async () => {
    try {
      const params = {
        ...queryParams.value,
        user_id: currentUserId.value
      }
      const res = await getUserStats(params)

      if (res.data && res.data.length > 0) {
        const userData = res.data[0]
        userWorkStats.value = {
          signed_order_count: userData.signed_order_count || 0,
          signed_device_count: userData.signed_device_count || 0,
          sign_category_breakdown: userData.sign_category_breakdown || [],
          check_count: userData.check_count || 0,
          check_category_breakdown: userData.check_category_breakdown || [],
          price_count: userData.price_count || 0,
          price_category_breakdown: userData.price_category_breakdown || [],
          payment_count: userData.payment_count || 0,
          payment_category_breakdown: userData.payment_category_breakdown || [],
          recycle_count: userData.recycle_count || 0,
          return_count: userData.return_count || 0,
          total_amount: userData.total_amount || 0
        }
      } else {
        // 重置数据
        userWorkStats.value = {
          signed_order_count: 0,
          signed_device_count: 0,
          sign_category_breakdown: [],
          check_count: 0,
          check_category_breakdown: [],
          price_count: 0,
          price_category_breakdown: [],
          payment_count: 0,
          payment_category_breakdown: [],
          recycle_count: 0,
          return_count: 0,
          total_amount: 0
        }
      }
    } catch (e) {
      console.error('获取用户工作统计失败', e)
      ElMessage.error('获取工作统计失败')
    }
  }

  // 获取管理员概况统计
  const fetchOverviewStats = async () => {
    try {
      const res = await getOverviewStats(queryParams.value)
      overviewStats.value = res.data || {}
    } catch (e) {
      console.error('获取概况统计失败', e)
      ElMessage.error('获取概况统计失败')
    }
  }

  // 获取用户列表
  const fetchUserList = async () => {
    try {
      const res = await getUserList()
      userList.value = res.data || []
    } catch (e) {
      console.error('获取用户列表失败', e)
    }
  }

  // 获取用户详细统计
  const fetchUserDetailStats = async (selectedUserId: string = '') => {
    userLoading.value = true
    try {
      const params = {
        ...queryParams.value,
        user_id: selectedUserId
      }
      const res = await getUserDetailStats(params)
      userDetailStats.value = res.data || []
    } catch (e) {
      console.error('获取用户详细统计失败', e)
      ElMessage.error('获取用户统计失败')
    } finally {
      userLoading.value = false
    }
  }

  // 获取会员统计概览
  const fetchMemberStatsOverview = async () => {
    memberLoading.value = true
    try {
      const res = await getMemberStatsOverview(queryParams.value)
      memberStatsOverview.value = res.data || {}
    } catch (e) {
      console.error('获取会员统计概览失败', e)
      ElMessage.error('获取会员统计概览失败')
    } finally {
      memberLoading.value = false
    }
  }

  // 获取会员注册趋势
  const fetchMemberRegisterTrend = async () => {
    try {
      const res = await getMemberRegisterTrend(queryParams.value)
      memberRegisterTrend.value = res.data || []
    } catch (e) {
      console.error('获取会员注册趋势失败', e)
      ElMessage.error('获取会员注册趋势失败')
    }
  }

  // 获取会员渠道统计
  const fetchMemberChannelStats = async () => {
    try {
      const res = await getMemberChannelStats(queryParams.value)
      memberChannelStats.value = res.data || []
    } catch (e) {
      console.error('获取会员渠道统计失败', e)
      ElMessage.error('获取会员渠道统计失败')
    }
  }

  // 获取拉新排行榜
  const fetchMemberInviteRank = async () => {
    try {
      const res = await getMemberInviteRank(queryParams.value)
      memberInviteRank.value = res.data || []
    } catch (e) {
      console.error('获取拉新排行榜失败', e)
      ElMessage.error('获取拉新排行榜失败')
    }
  }

  // 获取会员活跃度统计
  const fetchMemberActivityStats = async () => {
    try {
      const res = await getMemberActivityStats(queryParams.value)
      memberActivityStats.value = res.data || []
    } catch (e) {
      console.error('获取会员活跃度统计失败', e)
      ElMessage.error('获取会员活跃度统计失败')
    }
  }

  // 获取所有会员统计数据
  const fetchAllMemberStats = async () => {
    await Promise.all([
      fetchMemberStatsOverview(),
      fetchMemberRegisterTrend(),
      fetchMemberChannelStats(),
      fetchMemberInviteRank(),
      fetchMemberActivityStats()
    ])
  }

  // 获取所有数据
  const fetchData = async () => {
    await fetchUserRole()

    if (userRole.value === 'admin') {
      await fetchOverviewStats()
      await fetchUserList()
      await fetchUserDetailStats()
      await fetchAllMemberStats()
    } else {
      await fetchUserWorkStats()
    }
  }

  return {
    // 状态
    userRole,
    currentUserName,
    currentUserId,
    queryParams,
    userWorkStats,
    overviewStats,
    userDetailStats,
    userList,
    userLoading,
    // 会员统计状态
    memberStatsOverview,
    memberRegisterTrend,
    memberChannelStats,
    memberInviteRank,
    memberActivityStats,
    memberLoading,
    // 方法
    fetchUserRole,
    fetchUserWorkStats,
    fetchOverviewStats,
    fetchUserList,
    fetchUserDetailStats,
    fetchData,
    // 会员统计方法
    fetchMemberStatsOverview,
    fetchMemberRegisterTrend,
    fetchMemberChannelStats,
    fetchMemberInviteRank,
    fetchMemberActivityStats,
    fetchAllMemberStats
  }
}


