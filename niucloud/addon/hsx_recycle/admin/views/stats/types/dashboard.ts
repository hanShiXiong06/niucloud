export interface DashboardTabItem {
  key: string
  label: string
  visible: boolean
}

export interface DashboardMetricCard {
  key: string
  title: string
  category: string
  unit: string
  value_type: string
  value: string | number
  description: string
  caliber: string
  time_scope: string
  scope_label: string
  secondary?: {
    label: string
    value: string | number
    unit: string
    drilldown?: Record<string, any>
  }
  drilldown?: {
    target: string
    filter_key: string
    view_mode?: string
  }
}

export interface BusinessDashboard {
  date_range: {
    start_time?: string
    end_time?: string
  }
  thresholds: Record<string, any>
  cards: DashboardMetricCard[]
  todo: DashboardMetricCard[]
  today_business?: {
    order_count: number
    device_count: number
    recycled_device_count: number
    sold_device_count: number
    category_breakdown: Array<{
      category_id: number
      category_name: string
      count: number
    }>
    price_summary: {
      count: number
      min: string
      max: string
      avg: string
      label: string
    }
    price_ranges: Array<{
      key: string
      label: string
      count: number
    }>
    consignment?: {
      today_count: number
      pending_count: number
      selling_count: number
      pending_settlement_count: number
      sold_today_count: number
      sold_today_amount: string
    }
  }
  responsibility?: {
    total_pending_devices: number
    explain: string
    tasks: Array<{
      key: string
      title: string
      role: string
      order_count: number
      device_count: number
      amount: string
      is_pending: boolean
      owners: Array<{
        owner_id: number
        owner_name: string
        role_name: string
        order_count: number
        device_count: number
        amount: string
      }>
      drilldown?: {
        target: string
        filter_key: string
        view_mode?: string
      }
      route_path?: string
      route_query?: Record<string, any>
    }>
  }
  explain: string
}

export interface BusinessTrend {
  date_range: {
    start_time?: string
    end_time?: string
  }
  x_axis: string[]
  series: Array<{
    name: string
    unit: string
    type: string
    data: Array<number | string>
  }>
  explain: string
}
