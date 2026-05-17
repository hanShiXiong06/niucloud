import { ref, nextTick } from 'vue'
import * as echarts from 'echarts'

/**
 * 图表管理 Hook
 */
export function useCharts() {
  const userCategoryChart = ref<HTMLElement>()
  const adminUserChart = ref<HTMLElement>()
  const overviewRingChart = ref<HTMLElement>()
  const memberRegisterTrendChart = ref<HTMLElement>()
  const memberChannelChart = ref<HTMLElement>()
  const memberActivityChart = ref<HTMLElement>()

  let userCategoryChartInstance: echarts.ECharts | null = null
  let adminUserChartInstance: echarts.ECharts | null = null
  let overviewRingChartInstance: echarts.ECharts | null = null
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
    memberRegisterTrendChartInstance?.resize()
    memberChannelChartInstance?.resize()
    memberActivityChartInstance?.resize()
  }

  // 销毁图表
  const disposeCharts = () => {
    userCategoryChartInstance?.dispose()
    adminUserChartInstance?.dispose()
    overviewRingChartInstance?.dispose()
    memberRegisterTrendChartInstance?.dispose()
    memberChannelChartInstance?.dispose()
    memberActivityChartInstance?.dispose()
  }

  return {
    // Refs
    userCategoryChart,
    adminUserChart,
    overviewRingChart,
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


