import { ref, nextTick } from 'vue'
import * as echarts from 'echarts'

/**
 * 图表管理 Hook
 */
export function useCharts() {
  const userCategoryChart = ref<HTMLElement>()
  const adminUserChart = ref<HTMLElement>()
  const overviewRingChart = ref<HTMLElement>()
  const businessTrendChart = ref<HTMLElement>()
  const financeTrendChart = ref<HTMLElement>()
  const ledgerSourceChart = ref<HTMLElement>()
  const ledgerStatusChart = ref<HTMLElement>()
  const ledgerCategoryChart = ref<HTMLElement>()
  const ledgerPriceChart = ref<HTMLElement>()
  const memberRegisterTrendChart = ref<HTMLElement>()
  const memberChannelChart = ref<HTMLElement>()
  const memberActivityChart = ref<HTMLElement>()

  let userCategoryChartInstance: echarts.ECharts | null = null
  let adminUserChartInstance: echarts.ECharts | null = null
  let overviewRingChartInstance: echarts.ECharts | null = null
  let businessTrendChartInstance: echarts.ECharts | null = null
  let financeTrendChartInstance: echarts.ECharts | null = null
  let ledgerSourceChartInstance: echarts.ECharts | null = null
  let ledgerStatusChartInstance: echarts.ECharts | null = null
  let ledgerCategoryChartInstance: echarts.ECharts | null = null
  let ledgerPriceChartInstance: echarts.ECharts | null = null
  let memberRegisterTrendChartInstance: echarts.ECharts | null = null
  let memberChannelChartInstance: echarts.ECharts | null = null
  let memberActivityChartInstance: echarts.ECharts | null = null

  // 初始化普通用户分类饼图
  const initUserCategoryChart = () => {
    if (!userCategoryChart.value) return

    userCategoryChartInstance = echarts.init(userCategoryChart.value)
  }

  // 更新普通用户分类饼图
  const updateUserCategoryChart = (data: any[]) => {
    if (!userCategoryChartInstance) return

    // 处理空数据情况 - 显示空的饼图结构
    if (!data || data.length === 0) {
      const option = {
        title: {
          text: '设备分类分布',
          left: 'center',
          textStyle: {
            fontSize: 14
          }
        },
        tooltip: {
          trigger: 'item',
          formatter: '暂无数据'
        },
        legend: {
          orient: 'horizontal',
          bottom: '0',
          data: []
        },
        series: [
          {
            name: '设备类型',
            type: 'pie',
            radius: ['30%', '70%'],
            center: ['50%', '45%'],
            avoidLabelOverlap: false,
            itemStyle: {
              color: '#E5E7EB'
            },
            label: {
              show: true,
              formatter: '暂无数据',
              position: 'center',
              fontSize: 14,
              color: '#9CA3AF'
            },
            labelLine: {
              show: false
            },
            data: [
              { value: 1, name: '暂无数据', itemStyle: { color: '#E5E7EB' } }
            ]
          }
        ]
      }
      userCategoryChartInstance.setOption(option, true)
      return
    }

    const chartData = data.map((item: any, index: number) => ({
      value: item.count,
      name: item.category_name,
      itemStyle: {
        color: [
          '#409EFF',
          '#67C23A',
          '#E6A23C',
          '#F56C6C',
          '#909399',
          '#13C2C2',
          '#722ED1'
        ][index % 7]
      }
    }))

    const option = {
      title: {
        text: '设备分类分布',
        left: 'center'
      },
      tooltip: {
        trigger: 'item',
        formatter: '{a} <br/>{b}: {c}台 ({d}%)'
      },
      legend: {
        orient: 'horizontal',
        bottom: '0'
      },
      series: [
        {
          name: '设备类型',
          type: 'pie',
          radius: ['30%', '70%'],
          center: ['50%', '45%'],
          avoidLabelOverlap: false,
          label: {
            show: true,
            formatter: '{b}: {c}台'
          },
          labelLine: {
            show: true
          },
          data: chartData
        }
      ]
    }

    userCategoryChartInstance.setOption(option)
  }

  // 初始化管理员用户对比图表
  const initAdminUserChart = () => {
    if (!adminUserChart.value) return

    adminUserChartInstance = echarts.init(adminUserChart.value)
  }

  // 更新管理员用户对比图表
  const updateAdminUserChart = (userDetailStats: any[]) => {
    if (!adminUserChartInstance) return

    // 处理空数据情况 - 显示空的柱状图结构
    if (!userDetailStats || userDetailStats.length === 0) {
      const option = {
        title: {
          text: '员工工作量对比',
          left: 'center',
          textStyle: {
            fontSize: 14
          }
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        legend: {
          top: '30',
          data: ['签收数量', '质检数量', '定价数量', '打款数量']
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: [],
          axisLabel: {
            rotate: 45,
            color: '#9CA3AF'
          },
          axisLine: {
            lineStyle: {
              color: '#E5E7EB'
            }
          }
        },
        yAxis: {
          type: 'value',
          axisLabel: {
            color: '#9CA3AF'
          },
          axisLine: {
            lineStyle: {
              color: '#E5E7EB'
            }
          },
          splitLine: {
            lineStyle: {
              color: '#E5E7EB'
            }
          }
        },
        graphic: {
          type: 'text',
          left: 'center',
          top: 'middle',
          style: {
            text: '暂无数据',
            fontSize: 14,
            fill: '#9CA3AF'
          }
        },
        series: [
          {
            name: '签收数量',
            type: 'bar',
            data: [],
            itemStyle: { color: '#13C2C2' }
          },
          {
            name: '质检数量',
            type: 'bar',
            data: [],
            itemStyle: { color: '#409EFF' }
          },
          {
            name: '定价数量',
            type: 'bar',
            data: [],
            itemStyle: { color: '#67C23A' }
          },
          {
            name: '打款数量',
            type: 'bar',
            data: [],
            itemStyle: { color: '#E6A23C' }
          }
        ]
      }
      adminUserChartInstance.setOption(option, true)
      return
    }

    const users = userDetailStats.map((item) => item.user_name)
    const signedData = userDetailStats.map((item) => item.signed_device_count)
    const checkData = userDetailStats.map((item) => item.check_count)
    const priceData = userDetailStats.map((item) => item.price_count)
    const paymentData = userDetailStats.map((item) => item.payment_count)

    const option = {
      title: {
        text: '员工工作量对比',
        left: 'center'
      },
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow'
        }
      },
      legend: {
        top: '30'
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        data: users,
        axisLabel: {
          rotate: 45
        }
      },
      yAxis: {
        type: 'value'
      },
      series: [
        {
          name: '签收数量',
          type: 'bar',
          data: signedData,
          itemStyle: { color: '#13C2C2' }
        },
        {
          name: '质检数量',
          type: 'bar',
          data: checkData,
          itemStyle: { color: '#409EFF' }
        },
        {
          name: '定价数量',
          type: 'bar',
          data: priceData,
          itemStyle: { color: '#67C23A' }
        },
        {
          name: '打款数量',
          type: 'bar',
          data: paymentData,
          itemStyle: { color: '#E6A23C' }
        }
      ]
    }

    adminUserChartInstance.setOption(option)
  }

  // 初始化运营概览环形图
  const initOverviewRingChart = () => {
    if (!overviewRingChart.value) return

    overviewRingChartInstance = echarts.init(overviewRingChart.value)
  }

  // 更新运营概览环形图
  const updateOverviewRingChart = (overviewData: any) => {
    if (!overviewRingChartInstance) return

    // 构建图表数据
    const chartData = [
      { 
        value: overviewData.today_order_count || 0, 
        name: '订单',
        itemStyle: { color: '#3B82F6' }
      },
      { 
        value: overviewData.today_check_count || 0, 
        name: '质检',
        itemStyle: { color: '#10B981' }
      },
      { 
        value: overviewData.today_payment_count || 0, 
        name: '打款',
        itemStyle: { color: '#F59E0B' }
      },
      { 
        value: overviewData.today_return_count || 0, 
        name: '退货',
        itemStyle: { color: '#EF4444' }
      }
    ]

    // 检查是否所有数据都为0
    const hasData = chartData.some(item => item.value > 0)

    // 如果所有数据都为0，显示灰色的环形图
    if (!hasData) {
      const option = {
        title: {
          text: '业务分布',
          left: 'center',
          top: '5%',
          textStyle: {
            fontSize: 14,
            fontWeight: 'bold',
            color: '#9CA3AF'
          }
        },
        tooltip: {
          trigger: 'item',
          formatter: '暂无数据'
        },
        legend: {
          orient: 'horizontal',
          bottom: '5%',
          left: 'center',
          textStyle: {
            fontSize: 12,
            color: '#9CA3AF'
          }
        },
        graphic: {
          type: 'text',
          left: 'center',
          top: 'middle',
          style: {
            text: '暂无数据',
            fontSize: 14,
            fill: '#9CA3AF'
          }
        },
        series: [
          {
            name: '业务类型',
            type: 'pie',
            radius: ['45%', '70%'],
            center: ['50%', '50%'],
            avoidLabelOverlap: false,
            label: {
              show: false
            },
            labelLine: {
              show: false
            },
            data: [
              { value: 1, name: '订单', itemStyle: { color: '#E5E7EB' } },
              { value: 1, name: '质检', itemStyle: { color: '#E5E7EB' } },
              { value: 1, name: '打款', itemStyle: { color: '#E5E7EB' } },
              { value: 1, name: '退货', itemStyle: { color: '#E5E7EB' } }
            ]
          }
        ]
      }
      overviewRingChartInstance.setOption(option, true)
      return
    }

    // 有数据时显示正常的环形图（显示所有项，包括为0的）
    const option = {
      title: {
        text: '业务分布',
        left: 'center',
        top: '5%',
        textStyle: {
          fontSize: 14,
          fontWeight: 'bold',
          color: '#374151'
        }
      },
      tooltip: {
        trigger: 'item',
        formatter: '{a} <br/>{b}: {c} ({d}%)'
      },
      legend: {
        orient: 'horizontal',
        bottom: '5%',
        left: 'center',
        textStyle: {
          fontSize: 12
        }
      },
      series: [
        {
          name: '业务类型',
          type: 'pie',
          radius: ['45%', '70%'],
          center: ['50%', '50%'],
          avoidLabelOverlap: true,
          label: {
            show: true,
            position: 'outside',
            formatter: '{b}\n{c}',
            fontSize: 12
          },
          labelLine: {
            show: true,
            length: 10,
            length2: 10
          },
          emphasis: {
            label: {
              show: true,
              fontSize: 14,
              fontWeight: 'bold'
            },
            itemStyle: {
              shadowBlur: 10,
              shadowOffsetX: 0,
              shadowColor: 'rgba(0, 0, 0, 0.5)'
            }
          },
          data: chartData
        }
      ]
    }

    overviewRingChartInstance.setOption(option)
  }

  // 初始化会员注册趋势图
  const initBusinessTrendChart = () => {
    if (!businessTrendChart.value) return
    businessTrendChartInstance = echarts.init(businessTrendChart.value)
  }

  const buildTrendOption = (trendData: any, onlyUnits: string[] = []) => {
    const xAxis = trendData?.x_axis || []
    const series = Array.isArray(trendData?.series) ? trendData.series : []
    const displaySeries = onlyUnits.length
      ? series.filter((item: any) => onlyUnits.includes(item.unit))
      : series
    const hasData = displaySeries.some((item: any) => Array.isArray(item.data) && item.data.some((value: any) => Number(value) > 0))

    return {
      tooltip: {
        trigger: 'axis',
        formatter: (params: any[]) => {
          if (!params || !params.length) return ''
          const date = params[0].axisValue
          const lines = params.map((item) => {
            const unit = displaySeries[item.seriesIndex]?.unit || ''
            return `${item.marker}${item.seriesName}: ${item.value}${unit}`
          })
          return [date, ...lines].join('<br/>')
        }
      },
      legend: {
        top: 0,
        type: 'scroll'
      },
      grid: {
        top: 48,
        left: 44,
        right: 44,
        bottom: 34,
        containLabel: true
      },
      xAxis: {
        type: 'category',
        data: xAxis,
        axisLabel: {
          color: '#667085'
        }
      },
      yAxis: [
        {
          type: 'value',
          name: '数量/金额',
          axisLabel: {
            color: '#667085'
          },
          splitLine: {
            lineStyle: {
              color: '#EAECF0'
            }
          }
        },
        {
          type: 'value',
          name: '比例',
          min: 0,
          max: 100,
          axisLabel: {
            formatter: '{value}%',
            color: '#667085'
          },
          splitLine: {
            show: false
          }
        }
      ],
      graphic: hasData ? [] : {
        type: 'text',
        left: 'center',
        top: 'middle',
        style: {
          text: '暂无趋势数据',
          fontSize: 14,
          fill: '#98A2B3'
        }
      },
      series: displaySeries.map((item: any, index: number) => ({
        name: item.name,
        type: item.type || 'line',
        data: item.data || [],
        smooth: item.type !== 'bar',
        yAxisIndex: item.unit === '%' ? 1 : 0,
        barMaxWidth: 28,
        itemStyle: {
          color: ['#2563EB', '#12B76A', '#F79009', '#7C3AED', '#F04438'][index % 5]
        },
        lineStyle: {
          width: 2
        }
      }))
    }
  }

  const updateBusinessTrendChart = (trendData: any) => {
    if (!businessTrendChartInstance) return
    businessTrendChartInstance.setOption(buildTrendOption(trendData), true)
  }

  const initFinanceTrendChart = () => {
    if (!financeTrendChart.value) return
    financeTrendChartInstance = echarts.init(financeTrendChart.value)
  }

  const updateFinanceTrendChart = (trendData: any) => {
    if (!financeTrendChartInstance) return
    financeTrendChartInstance.setOption(buildTrendOption(trendData, ['元']), true)
  }

  const chartColors = ['#2563EB', '#12B76A', '#F79009', '#7C3AED', '#F04438', '#13C2C2', '#667085']

  const emptyGraphic = (text = '暂无数据') => ({
    type: 'text',
    left: 'center',
    top: 'middle',
    style: {
      text,
      fontSize: 14,
      fill: '#98A2B3'
    }
  })

  const initLedgerSourceChart = () => {
    if (!ledgerSourceChart.value) return
    if (ledgerSourceChartInstance) return
    ledgerSourceChartInstance = echarts.init(ledgerSourceChart.value)
  }

  const updateLedgerSourceChart = (sourceData: any[] = [], deliveryData: any[] = []) => {
    if (!ledgerSourceChartInstance) return
    const sourceSeries = sourceData.map((item, index) => ({
      name: item.label,
      value: Number(item.order_count || 0),
      itemStyle: { color: chartColors[index % chartColors.length] }
    }))
    const deliverySeries = deliveryData.map((item, index) => ({
      name: item.label,
      value: Number(item.order_count || 0),
      itemStyle: { color: chartColors[(index + 2) % chartColors.length] }
    }))
    const hasData = [...sourceSeries, ...deliverySeries].some(item => item.value > 0)

    ledgerSourceChartInstance.setOption({
      tooltip: {
        trigger: 'item',
        formatter: '{a}<br/>{b}: {c}单 ({d}%)'
      },
      legend: {
        bottom: 0,
        type: 'scroll'
      },
      graphic: hasData ? [] : emptyGraphic('暂无来源数据'),
      series: [
        {
          name: '下单来源',
          type: 'pie',
          radius: ['34%', '52%'],
          center: ['32%', '45%'],
          avoidLabelOverlap: true,
          label: { formatter: '{b}\n{c}单' },
          data: sourceSeries
        },
        {
          name: '配送方式',
          type: 'pie',
          radius: ['34%', '52%'],
          center: ['72%', '45%'],
          avoidLabelOverlap: true,
          label: { formatter: '{b}\n{c}单' },
          data: deliverySeries
        }
      ]
    }, true)
  }

  const initLedgerStatusChart = () => {
    if (!ledgerStatusChart.value) return
    if (ledgerStatusChartInstance) return
    ledgerStatusChartInstance = echarts.init(ledgerStatusChart.value)
  }

  const updateLedgerStatusChart = (orderStatusData: any[] = [], deviceStatusData: any[] = []) => {
    if (!ledgerStatusChartInstance) return
    const labels = Array.from(new Set([
      ...orderStatusData.map(item => item.label),
      ...deviceStatusData.map(item => item.label)
    ]))
    const orderMap = orderStatusData.reduce((map, item) => {
      map[item.label] = Number(item.order_count || 0)
      return map
    }, {} as Record<string, number>)
    const deviceMap = deviceStatusData.reduce((map, item) => {
      map[item.label] = Number(item.device_count || 0)
      return map
    }, {} as Record<string, number>)
    const hasData = labels.some(label => (orderMap[label] || 0) > 0 || (deviceMap[label] || 0) > 0)

    ledgerStatusChartInstance.setOption({
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      legend: { top: 0 },
      grid: { top: 44, left: 36, right: 24, bottom: 40, containLabel: true },
      xAxis: {
        type: 'category',
        data: labels,
        axisLabel: { color: '#667085', interval: 0, rotate: labels.length > 6 ? 30 : 0 }
      },
      yAxis: {
        type: 'value',
        axisLabel: { color: '#667085' },
        splitLine: { lineStyle: { color: '#EAECF0' } }
      },
      graphic: hasData ? [] : emptyGraphic('暂无状态数据'),
      series: [
        {
          name: '订单',
          type: 'bar',
          barMaxWidth: 28,
          data: labels.map(label => orderMap[label] || 0),
          itemStyle: { color: '#2563EB' }
        },
        {
          name: '设备',
          type: 'bar',
          barMaxWidth: 28,
          data: labels.map(label => deviceMap[label] || 0),
          itemStyle: { color: '#12B76A' }
        }
      ]
    }, true)
  }

  const initLedgerCategoryChart = () => {
    if (!ledgerCategoryChart.value) return
    if (ledgerCategoryChartInstance) return
    ledgerCategoryChartInstance = echarts.init(ledgerCategoryChart.value)
  }

  const updateLedgerCategoryChart = (data: any[] = []) => {
    if (!ledgerCategoryChartInstance) return
    const rows = [...data]
      .sort((a, b) => Number(b.count || 0) - Number(a.count || 0))
      .slice(0, 10)
      .reverse()
    const hasData = rows.some(item => Number(item.count || 0) > 0)

    ledgerCategoryChartInstance.setOption({
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: (params: any[]) => {
          const item = rows[params?.[0]?.dataIndex] || {}
          return `${item.category_name || '-'}<br/>数量：${item.count || 0}台<br/>金额：¥${item.amount || '0.00'}<br/>占比：${item.rate || 0}%`
        }
      },
      grid: { top: 20, left: 24, right: 36, bottom: 20, containLabel: true },
      xAxis: {
        type: 'value',
        axisLabel: { color: '#667085' },
        splitLine: { lineStyle: { color: '#EAECF0' } }
      },
      yAxis: {
        type: 'category',
        data: rows.map(item => item.category_name || '未分类'),
        axisLabel: { color: '#667085' }
      },
      graphic: hasData ? [] : emptyGraphic('暂无分类数据'),
      series: [
        {
          name: '设备数量',
          type: 'bar',
          barMaxWidth: 20,
          data: rows.map(item => Number(item.count || 0)),
          itemStyle: { color: '#2563EB', borderRadius: [0, 6, 6, 0] },
          label: { show: true, position: 'right', formatter: '{c}台', color: '#475467' }
        }
      ]
    }, true)
  }

  const initLedgerPriceChart = () => {
    if (!ledgerPriceChart.value) return
    if (ledgerPriceChartInstance) return
    ledgerPriceChartInstance = echarts.init(ledgerPriceChart.value)
  }

  const updateLedgerPriceChart = (data: any[] = []) => {
    if (!ledgerPriceChartInstance) return
    const hasData = data.some(item => Number(item.count || 0) > 0)
    ledgerPriceChartInstance.setOption({
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: (params: any[]) => {
          const item = data[params?.[0]?.dataIndex] || {}
          return `${item.label || '-'}<br/>数量：${item.count || 0}台<br/>金额：¥${item.amount || '0.00'}`
        }
      },
      grid: { top: 24, left: 36, right: 24, bottom: 30, containLabel: true },
      xAxis: {
        type: 'category',
        data: data.map(item => item.label),
        axisLabel: { color: '#667085' }
      },
      yAxis: {
        type: 'value',
        axisLabel: { color: '#667085' },
        splitLine: { lineStyle: { color: '#EAECF0' } }
      },
      graphic: hasData ? [] : emptyGraphic('暂无价格区间数据'),
      series: [
        {
          name: '设备数量',
          type: 'bar',
          barMaxWidth: 32,
          data: data.map(item => Number(item.count || 0)),
          itemStyle: { color: '#F79009', borderRadius: [6, 6, 0, 0] }
        }
      ]
    }, true)
  }

  // 初始化会员注册趋势图
  const initMemberRegisterTrendChart = () => {
    if (!memberRegisterTrendChart.value) return
    memberRegisterTrendChartInstance = echarts.init(memberRegisterTrendChart.value)
  }

  // 更新会员注册趋势图
  const updateMemberRegisterTrendChart = (data: any[]) => {
    if (!memberRegisterTrendChartInstance) return

    const dates = data.map(item => item.date)
    const counts = data.map(item => item.count)
    
    // 计算统计数据
    const maxCount = Math.max(...counts, 0)
    const minCount = Math.min(...counts, 0)
    const avgCount = counts.length > 0 ? (counts.reduce((a, b) => a + b, 0) / counts.length).toFixed(1) : 0

    const option = {
      title: {
        text: '注册趋势',
        left: 'center',
        textStyle: {
          fontSize: 14,
          fontWeight: 'bold'
        }
      },
      tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(255, 255, 255, 0.95)',
        borderColor: '#e5e7eb',
        borderWidth: 1,
        textStyle: {
          color: '#374151'
        },
        axisPointer: {
          type: 'cross',
          crossStyle: {
            color: '#9ca3af'
          },
          lineStyle: {
            color: '#d1d5db',
            type: 'dashed'
          }
        },
        formatter: (params: any) => {
          const param = params[0]
          return `
            <div style="padding: 4px 8px;">
              <div style="font-weight: 600; margin-bottom: 4px;">${param.axisValue}</div>
              <div style="display: flex; align-items: center;">
                <span style="display: inline-block; width: 10px; height: 10px; background: ${param.color}; border-radius: 50%; margin-right: 6px;"></span>
                <span>${param.seriesName}：<strong style="font-size: 16px; color: #3B82F6;">${param.value}</strong> 人</span>
              </div>
            </div>
          `
        }
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '10%',
        top: '15%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: dates,
        axisLine: {
          lineStyle: {
            color: '#e5e7eb'
          }
        },
        axisLabel: {
          rotate: 45,
          fontSize: 11,
          color: '#6b7280',
          margin: 10
        },
        axisTick: {
          show: true,
          lineStyle: {
            color: '#e5e7eb'
          }
        },
        splitLine: {
          show: true,
          lineStyle: {
            color: '#f3f4f6',
            type: 'dashed'
          }
        }
      },
      yAxis: {
        type: 'value',
        name: '注册数',
        nameTextStyle: {
          color: '#6b7280',
          fontSize: 12,
          padding: [0, 0, 0, 0]
        },
        minInterval: 1,
        axisLine: {
          show: true,
          lineStyle: {
            color: '#e5e7eb'
          }
        },
        axisLabel: {
          color: '#6b7280',
          fontSize: 11,
          formatter: '{value} 人'
        },
        splitLine: {
          show: true,
          lineStyle: {
            color: '#f3f4f6',
            type: 'dashed'
          }
        }
      },
      series: [
        {
          name: '注册用户',
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: 8,
          showSymbol: true,
          lineStyle: {
            width: 3,
            color: '#3B82F6',
            shadowColor: 'rgba(59, 130, 246, 0.3)',
            shadowBlur: 10,
            shadowOffsetY: 5
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(59, 130, 246, 0.4)' },
              { offset: 1, color: 'rgba(59, 130, 246, 0.05)' }
            ])
          },
          itemStyle: {
            color: '#3B82F6',
            borderWidth: 2,
            borderColor: '#fff',
            shadowColor: 'rgba(59, 130, 246, 0.5)',
            shadowBlur: 8
          },
          emphasis: {
            focus: 'series',
            itemStyle: {
              color: '#2563eb',
              borderWidth: 3,
              shadowBlur: 15,
              symbolSize: 12
            }
          },
          label: {
            show: true,
            position: 'top',
            color: '#374151',
            fontSize: 10,
            fontWeight: 600,
            formatter: '{c}',
            distance: 8
          },
          markPoint: {
            symbol: 'pin',
            symbolSize: 50,
            itemStyle: {
              color: '#ef4444'
            },
            data: [
              { type: 'max', name: '最大值', label: { fontSize: 11 } },
              { type: 'min', name: '最小值', label: { fontSize: 11 }, itemStyle: { color: '#10b981' } }
            ]
          },
          markLine: {
            silent: true,
            lineStyle: {
              color: '#f59e0b',
              type: 'dashed',
              width: 1
            },
            label: {
              formatter: '平均: {c} 人',
              fontSize: 11,
              color: '#f59e0b'
            },
            data: [
              { type: 'average', name: '平均值' }
            ]
          },
          data: counts
        }
      ]
    }

    memberRegisterTrendChartInstance.setOption(option, true)
  }

  // 初始化会员渠道图
  const initMemberChannelChart = () => {
    if (!memberChannelChart.value) return
    memberChannelChartInstance = echarts.init(memberChannelChart.value)
  }

  // 更新会员渠道图
  const updateMemberChannelChart = (data: any[]) => {
    if (!memberChannelChartInstance) return

    const chartData = data.map((item: any, index: number) => ({
      value: item.count,
      name: item.channel_name,
      itemStyle: {
        color: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'][index % 7]
      }
    }))

    const option = {
      title: {
        text: '注册渠道',
        left: 'center',
        textStyle: {
          fontSize: 14,
          fontWeight: 'bold'
        }
      },
      tooltip: {
        trigger: 'item',
        formatter: '{b}: {c} ({d}%)'
      },
      legend: {
        orient: 'vertical',
        right: '10',
        top: 'center',
        textStyle: {
          fontSize: 12
        }
      },
      series: [
        {
          name: '注册渠道',
          type: 'pie',
          radius: ['40%', '70%'],
          center: ['40%', '50%'],
          avoidLabelOverlap: false,
          label: {
            show: true,
            formatter: '{b}\n{c}人',
            fontSize: 11
          },
          labelLine: {
            show: true,
            length: 10,
            length2: 5
          },
          data: chartData
        }
      ]
    }

    memberChannelChartInstance.setOption(option, true)
  }

  // 初始化会员活跃度图
  const initMemberActivityChart = () => {
    if (!memberActivityChart.value) return
    memberActivityChartInstance = echarts.init(memberActivityChart.value)
  }

  // 更新会员活跃度图
  const updateMemberActivityChart = (data: any[]) => {
    if (!memberActivityChartInstance) return

    const dates = data.map(item => item.date)
    const activeCounts = data.map(item => item.active_count)
    const newCounts = data.map(item => item.new_count)

    const option = {
      title: {
        text: '活跃度统计',
        left: 'center',
        textStyle: {
          fontSize: 14,
          fontWeight: 'bold'
        }
      },
      tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(255, 255, 255, 0.95)',
        borderColor: '#e5e7eb',
        borderWidth: 1,
        textStyle: {
          color: '#374151'
        },
        axisPointer: {
          type: 'cross',
          crossStyle: {
            color: '#9ca3af'
          },
          lineStyle: {
            color: '#d1d5db',
            type: 'dashed'
          }
        },
        formatter: (params: any) => {
          let result = `<div style="padding: 6px 10px;">
            <div style="font-weight: 600; margin-bottom: 6px; font-size: 13px;">${params[0].axisValue}</div>`
          
          params.forEach((param: any) => {
            result += `
              <div style="display: flex; align-items: center; margin-bottom: 4px;">
                <span style="display: inline-block; width: 10px; height: 10px; background: ${param.color}; border-radius: 50%; margin-right: 8px;"></span>
                <span style="flex: 1;">${param.seriesName}：</span>
                <strong style="font-size: 15px; margin-left: 8px;" 
                        style="color: ${param.color};">${param.value}</strong>
                <span style="margin-left: 2px; color: #9ca3af;">人</span>
              </div>
            `
          })
          
          result += `</div>`
          return result
        }
      },
      legend: {
        data: ['活跃用户', '新增用户'],
        top: '8%',
        right: '10%',
        itemWidth: 20,
        itemHeight: 12,
        textStyle: {
          fontSize: 12,
          color: '#6b7280'
        },
        icon: 'roundRect'
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '10%',
        top: '20%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: dates,
        axisLine: {
          lineStyle: {
            color: '#e5e7eb'
          }
        },
        axisLabel: {
          rotate: 45,
          fontSize: 11,
          color: '#6b7280',
          margin: 10
        },
        axisTick: {
          show: true,
          lineStyle: {
            color: '#e5e7eb'
          }
        },
        splitLine: {
          show: true,
          lineStyle: {
            color: '#f3f4f6',
            type: 'dashed'
          }
        }
      },
      yAxis: {
        type: 'value',
        name: '用户数',
        nameTextStyle: {
          color: '#6b7280',
          fontSize: 12,
          padding: [0, 0, 0, 0]
        },
        minInterval: 1,
        axisLine: {
          show: true,
          lineStyle: {
            color: '#e5e7eb'
          }
        },
        axisLabel: {
          color: '#6b7280',
          fontSize: 11,
          formatter: '{value} 人'
        },
        splitLine: {
          show: true,
          lineStyle: {
            color: '#f3f4f6',
            type: 'dashed'
          }
        }
      },
      series: [
        {
          name: '活跃用户',
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: 8,
          showSymbol: true,
          lineStyle: {
            width: 3,
            color: '#10B981',
            shadowColor: 'rgba(16, 185, 129, 0.3)',
            shadowBlur: 10,
            shadowOffsetY: 5
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(16, 185, 129, 0.4)' },
              { offset: 1, color: 'rgba(16, 185, 129, 0.05)' }
            ])
          },
          itemStyle: {
            color: '#10B981',
            borderWidth: 2,
            borderColor: '#fff',
            shadowColor: 'rgba(16, 185, 129, 0.5)',
            shadowBlur: 8
          },
          emphasis: {
            focus: 'series',
            itemStyle: {
              color: '#059669',
              borderWidth: 3,
              shadowBlur: 15,
              symbolSize: 12
            }
          },
          label: {
            show: true,
            position: 'top',
            color: '#10B981',
            fontSize: 10,
            fontWeight: 600,
            formatter: '{c}',
            distance: 5
          },
          markPoint: {
            symbol: 'pin',
            symbolSize: 45,
            itemStyle: {
              color: '#10B981'
            },
            data: [
              { type: 'max', name: '峰值', label: { fontSize: 10 } }
            ]
          },
          markLine: {
            silent: true,
            lineStyle: {
              color: '#10B981',
              type: 'dashed',
              width: 1,
              opacity: 0.6
            },
            label: {
              formatter: '活跃平均: {c}',
              fontSize: 10,
              color: '#10B981'
            },
            data: [
              { type: 'average', name: '平均值' }
            ]
          },
          data: activeCounts
        },
        {
          name: '新增用户',
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: 8,
          showSymbol: true,
          lineStyle: {
            width: 3,
            color: '#3B82F6',
            shadowColor: 'rgba(59, 130, 246, 0.3)',
            shadowBlur: 10,
            shadowOffsetY: 5
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(59, 130, 246, 0.4)' },
              { offset: 1, color: 'rgba(59, 130, 246, 0.05)' }
            ])
          },
          itemStyle: {
            color: '#3B82F6',
            borderWidth: 2,
            borderColor: '#fff',
            shadowColor: 'rgba(59, 130, 246, 0.5)',
            shadowBlur: 8
          },
          emphasis: {
            focus: 'series',
            itemStyle: {
              color: '#2563eb',
              borderWidth: 3,
              shadowBlur: 15,
              symbolSize: 12
            }
          },
          label: {
            show: true,
            position: 'bottom',
            color: '#3B82F6',
            fontSize: 10,
            fontWeight: 600,
            formatter: '{c}',
            distance: 5
          },
          markPoint: {
            symbol: 'pin',
            symbolSize: 45,
            itemStyle: {
              color: '#3B82F6'
            },
            data: [
              { type: 'max', name: '峰值', label: { fontSize: 10 } }
            ]
          },
          markLine: {
            silent: true,
            lineStyle: {
              color: '#3B82F6',
              type: 'dashed',
              width: 1,
              opacity: 0.6
            },
            label: {
              formatter: '新增平均: {c}',
              fontSize: 10,
              color: '#3B82F6'
            },
            data: [
              { type: 'average', name: '平均值' }
            ]
          },
          data: newCounts
        }
      ]
    }

    memberActivityChartInstance.setOption(option, true)
  }

  // 窗口大小变化时重新调整图表
  const handleResize = () => {
    userCategoryChartInstance?.resize()
    adminUserChartInstance?.resize()
    overviewRingChartInstance?.resize()
    businessTrendChartInstance?.resize()
    financeTrendChartInstance?.resize()
    ledgerSourceChartInstance?.resize()
    ledgerStatusChartInstance?.resize()
    ledgerCategoryChartInstance?.resize()
    ledgerPriceChartInstance?.resize()
    memberRegisterTrendChartInstance?.resize()
    memberChannelChartInstance?.resize()
    memberActivityChartInstance?.resize()
  }

  // 销毁图表
  const disposeCharts = () => {
    userCategoryChartInstance?.dispose()
    adminUserChartInstance?.dispose()
    overviewRingChartInstance?.dispose()
    businessTrendChartInstance?.dispose()
    financeTrendChartInstance?.dispose()
    ledgerSourceChartInstance?.dispose()
    ledgerStatusChartInstance?.dispose()
    ledgerCategoryChartInstance?.dispose()
    ledgerPriceChartInstance?.dispose()
    memberRegisterTrendChartInstance?.dispose()
    memberChannelChartInstance?.dispose()
    memberActivityChartInstance?.dispose()
  }

  return {
    // Refs
    userCategoryChart,
    adminUserChart,
    overviewRingChart,
    businessTrendChart,
    financeTrendChart,
    ledgerSourceChart,
    ledgerStatusChart,
    ledgerCategoryChart,
    ledgerPriceChart,
    memberRegisterTrendChart,
    memberChannelChart,
    memberActivityChart,
    // 方法
    initUserCategoryChart,
    updateUserCategoryChart,
    initAdminUserChart,
    updateAdminUserChart,
    initOverviewRingChart,
    updateOverviewRingChart,
    initBusinessTrendChart,
    updateBusinessTrendChart,
    initFinanceTrendChart,
    updateFinanceTrendChart,
    initLedgerSourceChart,
    updateLedgerSourceChart,
    initLedgerStatusChart,
    updateLedgerStatusChart,
    initLedgerCategoryChart,
    updateLedgerCategoryChart,
    initLedgerPriceChart,
    updateLedgerPriceChart,
    initMemberRegisterTrendChart,
    updateMemberRegisterTrendChart,
    initMemberChannelChart,
    updateMemberChannelChart,
    initMemberActivityChart,
    updateMemberActivityChart,
    handleResize,
    disposeCharts
  }
}
