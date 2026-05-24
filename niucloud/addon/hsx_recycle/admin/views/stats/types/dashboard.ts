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
