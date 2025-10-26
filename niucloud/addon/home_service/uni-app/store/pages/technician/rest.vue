<template>
  <view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" v-if="!loading">
	  <!-- #ifdef MP-WEIXIN -->
	   	<u-navbar title="排版管理" autoBack placeholder>
	  	</u-navbar>
	  <!-- #endif -->
    <!-- 日历部分 -->
    <view class="bg-white p-4 m-[24rpx] rounded-lg">
      <view class="mb-2">
        <text class="text-[29rpx] font-medium">{{ t('calendarTitle') }}</text>
      </view>

      <!-- 年月切换 -->
      <view class="flex justify-between items-center mb-4">
        <text class="text-gray-500" @click="prevMonth"><text
          class="iconfont iconjiantou3 text-[24rpx]"></text></text>
        <text class="text-[32rpx] font-medium">{{ currentYear }}年{{ currentMonth }}月</text>
        <text class="text-gray-500" @click="nextMonth"><text class="iconfont iconarrow-right"></text></text>
      </view>

      <!-- 星期标题 -->
      <view class="grid grid-cols-7 mb-2">
        <text v-for="(day, index) in weekDays" :key="index" class="text-center text-gray-500 text-sm">
          {{ t(`${['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][index]}`) }}
        </text>
      </view>

			<!-- 日历网格 - 只显示当月的日期 -->
			<view class="grid grid-cols-7 gap-2 ">
				<!-- 上个月的空白格子 -->
				<view v-for="empty in startEmptyDays" :key="`empty-${empty}`"
					class="h-10 flex items-center justify-center">
					<text class="text-gray-200"></text>
				</view>

				<!-- 当月日期 -->
				<view v-for="day in daysInMonth" :key="day" class="h-10 flex items-center justify-center relative ">
					<text class="w-8 h-8 flex items-center justify-center rounded-full !text-[28rpx]" :class="{
              '!bg-[var(--store-bg-one)] !text-[#ffffff]': selectedDate === `${currentYear}-${currentMonth.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`,
              'text-[#a3a3a3]': isPastDate(day),
              'text-[var(--text-color)]': !isPastDate(day),
              'date-text': true
            }" @click="handleDateClick(day)">
						{{ day }}
					</text>
					<!-- 小圆点标记 -->
					<view v-if="markedDays.includes(day)"
						class="w-2 h-2 !bg-[#f9dcb9] rounded-full absolute bottom-1 z-10"></view>
				</view>
			</view>
		</view>

		<!-- 休息时间安排 -->
		<view class="bg-white p-4 m-[24rpx] rounded-lg">
			<view class="mb-4">
				<text class="text-[28rpx] font-medium">{{ t('restTimeArrangement') }}</text>
			</view>

			<!-- 开始时间 -->
			<view class="bg-[#e1f2ff] rounded-lg p-4 pb-3 mb-3 flex justify-between items-center"
				@click="selectTime('start')">
				<view>
					<text class="text-[26rpx] font-medium mb-1 block">{{ t('startTime') }}</text>
					<text class="text-[#666] text-sm">{{ startTime }}</text>
				</view>
				<text class="text-gray-400"><text class="iconfont iconarrow-right"></text></text>
			</view>

			<!-- 结束时间 -->
			<view class="bg-[#f8fbff] rounded-lg p-4 pb-3 flex justify-between items-center" @click="selectTime('end')">
				<view>
					<text class="text-[26rpx] font-medium mb-1 block">{{ t('endTime') }}</text>
					<text class="text-[#666] text-[28rpx]">{{ endTime }}</text>
				</view>
				<text class="text-gray-400"><text class="iconfont iconarrow-right"></text></text>
			</view>
		</view>

		<!-- 请假原因 -->
		<view class="bg-white m-[24rpx] rounded-lg p-4">
			<view class="mb-4">
				<text class="text-[28rpx] font-medium">{{ t('leaveReason') }}</text>
			</view>

			<view class="bg-white border border-gray-100 rounded-lg flex justify-between items-center"
				@click="selectReason">
				<text class="text-[26rpx]">{{ selectedReason }}</text>
				<text class="text-gray-400"><text class="iconfont iconarrow-right"></text></text>
			</view>
		</view>

		<!-- u-picker组件 -->
		<u-picker :show="showPicker" :columns="pickerColumns" @confirm="handlePickerConfirm"
			@cancel="handlePickerCancel" />

		<!-- 开始时间选择器 -->
		<u-datetime-picker :show="showStartTimePicker" v-model="startTimePickerValue" mode="time" :filter="timeFilter"
			@confirm="handleStartTimeConfirm" @cancel="handleStartTimeCancel" />

		<!-- 结束时间选择器 -->
		<u-datetime-picker :show="showEndTimePicker" v-model="endTimePickerValue" mode="time" :filter="timeFilter"
			@confirm="handleEndTimeConfirm" @cancel="handleEndTimeCancel" />
	</view>
	
	<view class="w-full footer bg-[#fff]">
		<view
			class="bg-[#fff] py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border flex items-center">
			<button v-if="hasRestRecord"
				class="flex-1 py-3 border border-red-500 text-[#ff0000] rounded-lg !text-[26rpx] bg-[#ececec] mr-[30rpx]"
				@click="cancelRestRecord">
				取消请假
			</button>
			<button class="flex-1 py-3 bg-blue-500 text-white rounded-lg bg-[var(--store-bg-one)] !text-[26rpx]"
				@click="submitForm">
				{{ t('confirm') }}
			</button>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { t } from '@/locale'
import { getTechnicianRestRecords, submitTechnicianRestRecord, cancelTechnicianRestRecord, getTechnicianRestReasons } from '@/addon/home_service/store/api/technician'
import { onLoad } from '@dcloudio/uni-app' // 添加导入
const loading = ref<boolean>(true);
// 从URL获取师傅ID
const technicianId = ref<number>(0); // 默认为0

// 使用onLoad生命周期钩子函数获取师傅ID
onLoad((data: any) => {
  if (data.technician_id) {
    technicianId.value = Number(data.technician_id);
  }
})

// 接口定义
interface RestRecord {
  id: number;
  date: string;
  start_time: string;
  end_time: string;
  reason: string;
}

interface RestReason {
  [key : string] : string
}

// 日历相关状态
const currentYear = ref(new Date().getFullYear())
const currentMonth = ref(new Date().getMonth() + 1)
const selectedDate = ref(`${currentYear.value}-${currentMonth.value.toString().padStart(2, '0')}-${new Date().getDate().toString().padStart(2, '0')}`)
const restRecords = ref<RestRecord[]>([])

// 时间选择相关状态
const startTime = ref('05:00')
const endTime = ref('22:00')
const showStartTimePicker = ref(false)
const showEndTimePicker = ref(false)
const startTimePickerValue = ref('05:00')
const endTimePickerValue = ref('22:00')
const weekDays = ['日', '一', '二', '三', '四', '五', '六']
// u-picker相关状态
const showPicker = ref(false)
const pickerColumns = ref<Array<Array<string>>>([])
// 请假理由相关状态
const selectedReason = ref(t('personalAffairs'))
const reasonOptions = ref<string[]>([])
const reasonMap = ref<RestReason>({}) // 用于存储 理由文本 -> ID 的映射

// 计算有标记的日期
const markedDays = computed(() => {
  const days: number[] = []
  restRecords.value.forEach(record => {
    // 确保只处理当前月份的记录
    const recordDate = new Date(record.date)
    if (recordDate.getFullYear() === currentYear.value &&
      recordDate.getMonth() + 1 === currentMonth.value) {
      const day = parseInt(record.date.split('-')[2])
      days.push(day)
    }
  })
  return days
})

// 星期标题 - 直接使用国际化键名

// 计算当月第一天是星期几
const firstDayOfMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value - 1, 1).getDay()
})

// 计算当月有多少天
const daysInMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value, 0).getDate()
})

// 计算需要显示的上个月的空白格子数
const startEmptyDays = computed(() => {
  return firstDayOfMonth.value
})

// 判断是否为过去的日期
const isPastDate = (day : number) : boolean => {
  const today = new Date()
  const selected = new Date(currentYear.value, currentMonth.value - 1, day)
  return selected < new Date(today.getFullYear(), today.getMonth(), today.getDate())
}

// 月份切换方法
const prevMonth = () : void => {
  if (currentMonth.value === 1) {
    currentMonth.value = 12
    currentYear.value -= 1
  } else {
    currentMonth.value -= 1
  }
  updateSelectedDate()
  loadRestRecords()
}

const nextMonth = () : void => {
  if (currentMonth.value === 12) {
    currentMonth.value = 1
    currentYear.value += 1
  } else {
    currentMonth.value += 1
  }
  updateSelectedDate()
  loadRestRecords()
}

// 更新选中日期
const updateSelectedDate = () : void => {
  const today = new Date()
  if (currentYear.value === today.getFullYear() && currentMonth.value === today.getMonth() + 1) {
    selectedDate.value = `${currentYear.value}-${currentMonth.value.toString().padStart(2, '0')}-${today.getDate().toString().padStart(2, '0')}`
  } else {
    selectedDate.value = `${currentYear.value}-${currentMonth.value.toString().padStart(2, '0')}-01`
  }
}

// 处理日期点击
const handleDateClick = (day : number) : void => {
  // 确保日期格式化一致，月份和日期都有前导零
  const dateStr = `${currentYear.value}-${currentMonth.value.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`
  selectedDate.value = dateStr

  // 查找该日期的休息记录
  const record = restRecords.value.find(r => r.date === dateStr)
  if (record && record.hour_array && record.hour_array.length > 0) {
    // 设置开始时间和结束时间
    startTime.value = record.hour_array[0]
    endTime.value = record.hour_array[record.hour_array.length - 1]

    // 如果有备注，设置请假原因
    if (record.notes) {
      // 检查备注是否在原因选项中，如果不在则添加
      if (!reasonOptions.value.includes(record.notes)) {
        reasonOptions.value.push(record.notes)
      }
      selectedReason.value = record.notes
    }
  } else {
    // 如果没有记录，重置时间和原因
    startTime.value = '05:00'
    endTime.value = '22:00'
    selectedReason.value = reasonOptions.value[0] || t('personalAffairs')
  }
}

// 生成时间段数组函数
const generateHourArray = (start : string, end : string) : string[] => {
  const [startHour, startMinute] = start.split(':').map(Number)
  const [endHour, endMinute] = end.split(':').map(Number)
  const hours : string[] = []

  let currentHour = startHour
  let currentMinute = startMinute

  while (true) {
    hours.push(`${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`)

    // 增加30分钟
    currentMinute += 30
    if (currentMinute >= 60) {
      currentMinute = 0
      currentHour += 1
    }

    // 检查是否达到结束时间
    if (currentHour > endHour || (currentHour === endHour && currentMinute > endMinute)) {
      break
    }
  }

  return hours
}

// 时间过滤器函数 - 实现半小时间隔
const timeFilter = (type : string, vals : any[]) => {
  // 只处理分钟部分
  if (type === 'minute') {
    return vals.filter((val : number) => val % 30 === 0)
  }
  return vals
}

// 时间选择方法
const selectTime = (type : 'start' | 'end') : void => {
  // 设置当前选中的时间到picker
  if (type === 'start') {
    startTimePickerValue.value = startTime.value
    showStartTimePicker.value = true
  } else {
    endTimePickerValue.value = endTime.value
    showEndTimePicker.value = true
  }
}

// 处理开始时间确认
const handleStartTimeConfirm = (e : any) : void => {
  const newStartTime = e.value

  // 时间验证
  if (compareTime(newStartTime, endTime.value) >= 0) {
    uni.showToast({
      title: '开始时间不能大于等于结束时间',
      icon: 'none'
    })
    return
  }

  startTime.value = newStartTime
  showStartTimePicker.value = false
}

// 处理开始时间取消
const handleStartTimeCancel = () : void => {
  showStartTimePicker.value = false
}

// 处理结束时间确认
const handleEndTimeConfirm = (e : any) : void => {
  const newEndTime = e.value

  // 时间验证
  if (compareTime(newEndTime, startTime.value) <= 0) {
    uni.showToast({
      title: '结束时间不能小于等于开始时间',
      icon: 'none'
    })
    return
  }

  endTime.value = newEndTime
  showEndTimePicker.value = false
}

// 处理结束时间取消
const handleEndTimeCancel = () : void => {
  showEndTimePicker.value = false
}

// 比较两个时间字符串 (HH:MM)
const compareTime = (time1 : string, time2 : string) : number => {
  const [h1, m1] = time1.split(':').map(Number)
  const [h2, m2] = time2.split(':').map(Number)
  const totalMinutes1 = h1 * 60 + m1
  const totalMinutes2 = h2 * 60 + m2

  return totalMinutes1 - totalMinutes2
}

// 请假原因选择方法
const selectReason = () : void => {
  // 准备picker的columns数据
  pickerColumns.value = [reasonOptions.value]
  // 显示u-picker
  showPicker.value = true
}

// 处理picker确认选择
const handlePickerConfirm = (e : any) : void => {
  // 获取选中的值
  const selectedValue = e.value[0]
  // 更新选中的理由
  selectedReason.value = selectedValue
  // 关闭picker
  showPicker.value = false
}

// 处理picker取消选择
const handlePickerCancel = () : void => {
  // 关闭picker
  showPicker.value = false
}

// 提交表单方法
const submitForm = () : void => {
  // 验证开始时间和结束时间
  if (compareTime(endTime.value, startTime.value) <= 0) {
    uni.showToast({
      title: '结束时间不能小于开始时间',
      icon: 'none'
    })
    return
  }

  // 生成时间段数组
  const hourArray = generateHourArray(startTime.value, endTime.value)
  const hourString = hourArray.join(',')

  // 获取选中理由对应的ID
  const reasonId = reasonMap.value[selectedReason.value] || '';

  // 构建请求参数
  const params = {  
    date: selectedDate.value,
    hour: hourString,
    notes: reasonId, // 使用理由ID而不是文本
    technician_id: technicianId.value
  }

  // 提交表单
  submitTechnicianRestRecord(params).then((res) => {
    if (res.code === 1) {
      loadRestRecords();
    }
  }).catch(() => {
    uni.showToast({
      title: '提交失败，请重试',
      icon: 'none'
    });
  })
}

// 取消请假
const cancelRestRecord = () : void => {
  // 显示确认对话框
  uni.showModal({
    title: '确认取消',
    content: '确定要取消所选日期的请假吗？',
    success: (resModal) => {
      if (resModal.confirm) {
        // 调用取消请假API
        cancelTechnicianRestRecord({ 
          date: selectedDate.value,
          technician_id: technicianId.value 
        }).then((res) => {
          if (res.code === 1) {
            // 重新加载休息记录
            loadRestRecords();

            // 重置表单
            startTime.value = '05:00';
            endTime.value = '22:00';
            selectedReason.value = reasonOptions.value[0] || t('personalAffairs');
          }
        }).catch(() => {
          console.error('取消请假失败');
          uni.showToast({
            title: '取消失败，请重试',
            icon: 'none'
          });
        });
      }
    }
  });
}

// 加载请假理由image.png
const loadRestReasons = () : void => {
  getTechnicianRestReasons().then((res) => {
    if (res.code === 1 && res.data) {
      // 转换为数组格式用于选择器
      const reasons : string[] = []
      const reasonMapObj : RestReason = {}

      for (const key in res.data) {
        if (res.data.hasOwnProperty(key)) {
          reasons.push(res.data[key])
          reasonMapObj[res.data[key]] = key // 理由文本 -> ID 的映射
        }
      }

      if (reasons.length > 0) {
        reasonOptions.value = reasons
        reasonMap.value = reasonMapObj
        // 如果当前选择的理由不在新列表中，设置为第一个
        if (!reasons.includes(selectedReason.value)) {
          selectedReason.value = reasons[0]
        }
      }
    }
  }).catch(() => {
    console.error('加载请假理由失败')
    // 保留默认理由选项
  })
}

// 加载休息记录
const loadRestRecords = () : void => {
  loading.value = true;
  const dateParam = `${currentYear.value}-${currentMonth.value.toString().padStart(2, '0')}`;
  getTechnicianRestRecords({ 
    date: dateParam, 
    technician_id: technicianId.value 
  }).then((res) => {
    loading.value = false;
    if (res.code === 1 && res.data) {
      restRecords.value = res.data;
    }
  }).catch(() => {
    console.error('加载休息记录失败');
    loading.value = false;
  });
}

// 监听选中日期变化
watch(selectedDate, (newDate) => {
  // 查找新选中日期的休息记录
  const record = restRecords.value.find(r => r.date === newDate)
  if (record && record.hour_array && record.hour_array.length > 0) {
    // 设置开始时间和结束时间
    startTime.value = record.hour_array[0]
    endTime.value = record.hour_array[record.hour_array.length - 1]

    // 反向选中请假理由 - 根据ID查找对应的文本
    if (record.notes && Object.keys(reasonMap.value).length > 0) {
      // 查找ID对应的理由文本
      const reasonText = Object.keys(reasonMap.value).find(text => reasonMap.value[text] === record.notes)
      if (reasonText) {
        selectedReason.value = reasonText
      } else {
        selectedReason.value = reasonOptions.value[0] || t('personalAffairs')
      }
    }
  }
})

// 初始化
onMounted(() => {
  loadRestRecords()
  loadRestReasons()
})

// 判断当前选中日期是否有请假记录
const hasRestRecord = computed(() => {
  return restRecords.value.some(record => record.date === selectedDate.value)
})

// 取消按钮处理函数
const handleCancel = (): void => {
  // 查找该日期的休息记录
  const record = restRecords.value.find(r => r.date === selectedDate.value)
  if (record) {
    // 如果有记录，重置为记录的值
    startTime.value = record.hour_array[0]
    endTime.value = record.hour_array[record.hour_array.length - 1]
    selectedReason.value = record.notes || t('personalAffairs')
  } else {
    // 如果没有记录，重置为默认值
    startTime.value = '05:00'
    endTime.value = '22:00'
    selectedReason.value = reasonOptions.value[0] || t('personalAffairs')
  }
}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
  /* 自定义样式 */
  .date-text {
    font-size: 26rpx !important;
    font-weight: 600 !important;
  }
  // 底部安全区域适配
  .footer {
    height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
  }
</style>