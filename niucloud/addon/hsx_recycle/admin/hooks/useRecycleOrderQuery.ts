import { ref, reactive } from 'vue'
import type { Ref } from 'vue'

export interface PaginationState {
  page: number
  limit: number
  total: number
}

export interface QuickSearchFormModel {
  keyword: string
  status: string | number
  delivery_type: string
}

export interface AdvancedSearchFormModel {
  order_id: string | number
  order_no: string
  express_no: string
  status: Array<string | number>
  member_id: string | number
  user_mobile: string
  delivery_type: Array<string | number>
  logistics_vehicle_no: string
  order_source: string
  device_imei: string
  device_model: string
  device_count_min: number | null
  device_count_max: number | null
  amount_min: number | null
  amount_max: number | null
  create_time_range: string[]
  update_time_range: string[]
  sign_at: string[]
  complete_at: string[]
  pay_time: string[]
}

interface UseRecycleOrderQueryOptions {
  pagination: Ref<PaginationState>
  setPagination: (paginationInfo: Partial<PaginationState>) => void
  fetchList: (params: Record<string, any>) => Promise<any>
  onError?: (message: string) => void
}

const createQuickSearchForm = (): QuickSearchFormModel => ({
  keyword: '',
  status: '',
  delivery_type: ''
})

const createAdvancedSearchForm = (): AdvancedSearchFormModel => ({
  order_id: '',
  order_no: '',
  express_no: '',
  status: [],
  member_id: '',
  user_mobile: '',
  delivery_type: [],
  logistics_vehicle_no: '',
  order_source: '',
  device_imei: '',
  device_model: '',
  device_count_min: null,
  device_count_max: null,
  amount_min: null,
  amount_max: null,
  create_time_range: [],
  update_time_range: [],
  sign_at: [],
  complete_at: [],
  pay_time: []
})

export const buildOrderQueryParams = (params: {
  page: number
  limit: number
  quickSearchForm: QuickSearchFormModel
  advancedSearchForm: AdvancedSearchFormModel
}) => {
  const { page, limit, quickSearchForm, advancedSearchForm } = params
  const queryParams: Record<string, any> = {
    page,
    limit
  }

  if (quickSearchForm.keyword) queryParams.keyword = quickSearchForm.keyword
  if (quickSearchForm.status) queryParams.status = quickSearchForm.status
  if (quickSearchForm.delivery_type) queryParams.delivery_type = quickSearchForm.delivery_type

  if (advancedSearchForm.order_id) queryParams.order_id = advancedSearchForm.order_id
  if (advancedSearchForm.order_no) queryParams.order_no = advancedSearchForm.order_no
  if (advancedSearchForm.express_no) queryParams.express_no = advancedSearchForm.express_no
  if (advancedSearchForm.status && advancedSearchForm.status.length > 0) {
    queryParams.status = advancedSearchForm.status.join(',')
  }
  if (advancedSearchForm.member_id) queryParams.member_id = advancedSearchForm.member_id
  if (advancedSearchForm.user_mobile) queryParams.user_mobile = advancedSearchForm.user_mobile
  if (advancedSearchForm.delivery_type && advancedSearchForm.delivery_type.length > 0) {
    queryParams.delivery_type = advancedSearchForm.delivery_type.join(',')
  }
  if (advancedSearchForm.logistics_vehicle_no) queryParams.logistics_vehicle_no = advancedSearchForm.logistics_vehicle_no
  if (advancedSearchForm.order_source) queryParams.order_source = advancedSearchForm.order_source
  if (advancedSearchForm.device_imei) queryParams.device_imei = advancedSearchForm.device_imei
  if (advancedSearchForm.device_model) queryParams.device_model = advancedSearchForm.device_model
  if (advancedSearchForm.device_count_min) queryParams.device_count_min = advancedSearchForm.device_count_min
  if (advancedSearchForm.device_count_max) queryParams.device_count_max = advancedSearchForm.device_count_max
  if (advancedSearchForm.amount_min) queryParams.amount_min = advancedSearchForm.amount_min
  if (advancedSearchForm.amount_max) queryParams.amount_max = advancedSearchForm.amount_max

  if (advancedSearchForm.create_time_range && advancedSearchForm.create_time_range.length === 2) {
    queryParams.create_time_start = advancedSearchForm.create_time_range[0]
    queryParams.create_time_end = advancedSearchForm.create_time_range[1]
  }
  if (advancedSearchForm.update_time_range && advancedSearchForm.update_time_range.length === 2) {
    queryParams.update_time_start = advancedSearchForm.update_time_range[0]
    queryParams.update_time_end = advancedSearchForm.update_time_range[1]
  }
  if (advancedSearchForm.pay_time && advancedSearchForm.pay_time.length === 2) {
    queryParams.pay_time = advancedSearchForm.pay_time
  }
  if (advancedSearchForm.sign_at && advancedSearchForm.sign_at.length === 2) {
    queryParams.sign_at = advancedSearchForm.sign_at
  }
  if (advancedSearchForm.complete_at && advancedSearchForm.complete_at.length === 2) {
    queryParams.complete_at = advancedSearchForm.complete_at
  }

  return queryParams
}

export function useRecycleOrderQuery<T = any>(options: UseRecycleOrderQueryOptions) {
  const { pagination, setPagination, fetchList, onError } = options

  const loading = ref(false)
  const list = ref<T[]>([])
  const statusCounts = ref<Record<string, number>>({})
  const filterMeta = ref<Record<string, any>>({})
  const viewMode = ref('')
  const paymentMode = ref<'order' | 'device'>('order')
  const flowMode = ref<'order' | 'device'>('order')

  const quickSearchForm = reactive<QuickSearchFormModel>(createQuickSearchForm())
  const advancedSearchForm = reactive<AdvancedSearchFormModel>(createAdvancedSearchForm())

  const showAdvancedSearch = ref(true)
  const searchPanelCollapsed = ref(false)
  const activeTab = ref('')

  const getList = async (page = pagination.value.page) => {
    loading.value = true

    try {
      const currentPage = Number(page) > 0 ? Number(page) : pagination.value.page
      if (currentPage !== pagination.value.page) {
        setPagination({ page: currentPage })
      }

      const queryParams = buildOrderQueryParams({
        page: currentPage,
        limit: pagination.value.limit,
        quickSearchForm,
        advancedSearchForm
      })

      const res = await fetchList(queryParams)

      if (res.data.data) {
        list.value = res.data.data
      } else if (res.data.list) {
        list.value = res.data.list
      } else {
        list.value = []
        console.error('API返回的数据结构不符合预期:', res.data)
      }

      const total = res.data.total || res.data.count || 0
      statusCounts.value = res.data.status_counts || {}
      filterMeta.value = res.data.filter_meta || {}
      viewMode.value = res.data.view_mode || ''
      flowMode.value = res.data.flow_mode === 'device' ? 'device' : 'order'
      paymentMode.value = res.data.payment_mode === 'device' ? 'device' : flowMode.value
      setPagination({ total })
    } catch (error) {
      console.error('获取列表失败:', error)
      onError && onError('获取列表失败')
    } finally {
      loading.value = false
    }
  }

  const handleSizeChange = (val: number) => {
    setPagination({
      limit: val,
      page: pagination.value.page
    })
    getList(1)
  }

  const handleCurrentChange = (val: number) => {
    setPagination({
      page: val,
      limit: pagination.value.limit
    })
    getList(val)
  }

  const getStatusCount = (status: string | number) => {
    if (status === 'all') {
      return pagination.value.total
    }
    return statusCounts.value[status] || 0
  }

  const resetQuickSearchForm = () => {
    quickSearchForm.keyword = ''
    quickSearchForm.status = ''
    quickSearchForm.delivery_type = ''
  }

  const resetAdvancedSearchForm = () => {
    Object.assign(advancedSearchForm, createAdvancedSearchForm())
  }

  const quickSearch = () => {
    resetAdvancedSearchForm()
    setPagination({ page: 1 })
    getList(1)
  }

  const resetQuickSearch = () => {
    resetQuickSearchForm()
    activeTab.value = ''
    setPagination({ page: 1 })
    getList(1)
  }

  const advancedSearch = () => {
    resetQuickSearchForm()
    activeTab.value = ''
    setPagination({ page: 1 })
    getList(1)
  }

  const resetAdvancedSearch = () => {
    resetAdvancedSearchForm()
    activeTab.value = ''
    setPagination({ page: 1 })
    getList(1)
  }

  const handleMemberChange = (memberId: number | string | null) => {
    advancedSearchForm.member_id = memberId ?? ''
    setPagination({ page: 1 })
    getList(1)
  }

  const handleTabClick = (tab: any) => {
    activeTab.value = tab.props.name
    quickSearchForm.status = tab.props.name
    advancedSearchForm.status = []
    setPagination({ page: 1 })
    getList(1)
  }

  return {
    loading,
    list,
    statusCounts,
    filterMeta,
    viewMode,
    paymentMode,
    flowMode,
    quickSearchForm,
    advancedSearchForm,
    showAdvancedSearch,
    searchPanelCollapsed,
    activeTab,
    getList,
    handleSizeChange,
    handleCurrentChange,
    getStatusCount,
    resetQuickSearchForm,
    resetAdvancedSearchForm,
    quickSearch,
    resetQuickSearch,
    advancedSearch,
    resetAdvancedSearch,
    handleMemberChange,
    handleTabClick
  }
}
