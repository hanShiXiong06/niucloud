<template>
    <el-dialog v-model="dialogVisible" title="请选择上门时间" width="700px">
        <div v-loading="loading" class="time-select-container flex h-[500px]">
            <!-- 左侧日期列表 -->
            <div class="date-list w-[220px] bg-[#f8f8f8] overflow-y-auto border-r border-solid border-[#e4e4e4]">
                <template v-for="(dateItem, dateIndex) in dateList" :key="dateIndex">
                    <div 
                        v-if="dateItem && dateItem.children && dateItem.children.length > 0"
                        :class="['date-item cursor-pointer px-[20px] py-[16px] text-[14px] border-b border-solid border-[#eee]', { 'active bg-white': dateIndex == dateActive }]"
                        @click="selectDate(dateIndex)"
                    >
                        <div>{{ dateItem.label }}</div>
                        <div class="text-[12px] text-[#999] mt-[4px]">{{ dateItem.week }}</div>
                    </div>
                </template>
            </div>
            
            <!-- 右侧时间列表 -->
            <div class="time-list flex-1 overflow-y-auto px-[20px]">
                <div v-if="dateList.length > 0 && dateList[dateActive] && dateList[dateActive].children">
                    <template v-for="(timeItem, timeIndex) in dateList[dateActive].children" :key="timeIndex">
                        <div 
                            v-if="timeItem && !timeItem.disabled"
                            :class="['time-item flex items-center justify-between py-[16px] border-b border-solid border-[#eee] cursor-pointer', { 'text-primary': timeIndex == timeActive }]"
                            @click="selectTime(timeIndex, timeItem)"
                        >
                            <span class="text-[14px]">{{ timeItem.begin }} - {{ timeItem.end }}</span>
                            <i v-if="timeIndex == timeActive" class="iconfont iconxuanzhong text-[18px]"></i>
                        </div>
                    </template>
                </div>
                <div v-else class="text-center text-[#999] py-[40px]">
                    <p>暂无可预约时间</p>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmFn">确定</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { getOrderConfig } from '@/addon/home_service/api/order'

const dialogVisible = ref(false)
const loading = ref(false)
const dateList = ref<any[]>([])
const dateActive = ref(0)
const timeActive = ref(0)
const selectedTime = ref<any>(null)

// 打开对话框
const open = () => {
    dialogVisible.value = true
    dateActive.value = 0
    timeActive.value = 0
    selectedTime.value = null
    generateTimeList()
}

// 根据配置生成可预约时间列表
const generateTimeList = () => {
    loading.value = true
    getOrderConfig().then((res: any) => {
        const config = res.data.reserve || res.data.value?.reserve
        if (!config) {
            ElMessage.warning('未配置预约时间')
            loading.value = false
            return
        }

        const weekDays = config.week ? config.week.split(',').map(Number) : []
        const startSeconds = Number(config.start) || 0
        const endSeconds = Number(config.end) || 0
        const intervalMinutes = Number(config.interval) || 60
        const advanceDays = Number(config.advance) || 0
        
        // 生成时间段模板
        const timeSlots = generateTimeSlots(startSeconds, endSeconds, intervalMinutes)
        
        // 生成日期列表
        const currentTime = new Date()
        const currentTimestamp = currentTime.getTime()
        const advanceTime = currentTimestamp + (advanceDays * 60 * 60 * 1000) // 提前时间
        
        dateList.value = []
        
        for (let i = 0; i < 10; i++) {
            const date = new Date(currentTime.getTime() + i * 24 * 60 * 60 * 1000)
            date.setHours(0, 0, 0, 0)
            const dayOfWeek = date.getDay() == 0 ? 7 : date.getDay()
            
            // 检查是否在配置的星期中
            if (weekDays.includes(dayOfWeek)) {
                const dateStr = formatDate(date)
                const children: any[] = []
                
                // 为每个日期生成时间段，并过滤掉已过期的
                timeSlots.forEach((slot: any) => {
                    const slotDate = new Date(date.getTime())
                    const [hours, minutes] = slot.begin.split(':').map(Number)
                    slotDate.setHours(hours, minutes, 0, 0)
                    const slotTimestamp = slotDate.getTime()
                    
                    // 只添加未过期且大于提前时间的时间段
                    if (slotTimestamp > advanceTime) {
                        children.push({
                            ...slot,
                            timestamp: slotTimestamp,
                            disabled: false
                        })
                    }
                })
                
                if (children.length > 0) {
                    dateList.value.push({
                        date: dateStr,
                        label: getDateLabel(date, i),
                        week: getWeekLabel(date),
                        children: children
                    })
                }
            }
        }
        
        // 默认选中第一个可用时间
        if (dateList.value.length > 0 && dateList.value[0].children.length > 0) {
            dateActive.value = 0
            timeActive.value = 0
            updateSelectedTime()
        } else {
            ElMessage.warning('暂无可预约时间')
        }
        
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}

// 生成时间段
const generateTimeSlots = (startSeconds: number, endSeconds: number, intervalMinutes: number) => {
    const slots: any[] = []
    const intervalSeconds = intervalMinutes * 60
    
    for (let seconds = startSeconds; seconds < endSeconds; seconds += intervalSeconds) {
        const beginHours = Math.floor(seconds / 3600)
        const beginMinutes = Math.floor((seconds % 3600) / 60)
        
        const endTimeSeconds = seconds + intervalSeconds
        const endHours = Math.floor(endTimeSeconds / 3600)
        const endMinutes = Math.floor((endTimeSeconds % 3600) / 60)
        
        slots.push({
            begin: `${padZero(beginHours)}:${padZero(beginMinutes)}`,
            end: `${padZero(endHours)}:${padZero(endMinutes)}`,
            disabled: false
        })
    }
    
    return slots
}

// 格式化日期 YYYY-MM-DD
const formatDate = (date: Date): string => {
    const year = date.getFullYear()
    const month = padZero(date.getMonth() + 1)
    const day = padZero(date.getDate())
    return `${year}-${month}-${day}`
}

// 获取日期标签
const getDateLabel = (date: Date, dayOffset: number): string => {
    const month = date.getMonth() + 1
    const day = date.getDate()
    
    if (dayOffset == 0) {
        return '今天'
    } else if (dayOffset == 1) {
        return '明天'
    } else if (dayOffset == 2) {
        return '后天'
    } else {
        return `${month}月${day}日`
    }
}

// 获取星期标签
const getWeekLabel = (date: Date): string => {
    const weekDays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六']
    return weekDays[date.getDay()]
}

// 补零
const padZero = (num: number): string => {
    return num < 10 ? `0${num}` : `${num}`
}

// 更新选中的时间
const updateSelectedTime = () => {
    if (dateList.value.length > 0 && dateList.value[dateActive.value]) {
        const currentDate = dateList.value[dateActive.value]
        if (currentDate && currentDate.children && currentDate.children.length > 0) {
            const currentTime = currentDate.children[timeActive.value]
            
            if (currentTime) {
                // 从 date 字符串中提取月日
                const dateParts = currentDate.date.split('-')
                const month = parseInt(dateParts[1])
                const day = parseInt(dateParts[2])
                
                selectedTime.value = {
                    date: currentDate.date,
                    label: currentDate.label,
                    week: currentDate.week,
                    begin: currentTime.begin,
                    end: currentTime.end,
                    timestamp: currentTime.timestamp,
                    display: `${month}月${day}日 ${currentTime.begin}-${currentTime.end}`
                }
            }
        }
    }
}

// 选择日期
const selectDate = (index: number) => {
    dateActive.value = index
    timeActive.value = 0
    updateSelectedTime()
}

// 选择时间
const selectTime = (index: number, timeItem: any) => {
    if (timeItem.disabled) return
    timeActive.value = index
    updateSelectedTime()
}

// 确认选择
const confirmFn = () => {
    if (!selectedTime.value) {
        ElMessage.warning('请选择上门时间')
        return
    }
    emit('confirm', selectedTime.value)
    dialogVisible.value = false
}

const emit = defineEmits(['confirm'])
defineExpose({
    open
})
</script>

<style lang="scss" scoped>
.time-select-container {
    border: 1px solid #e4e4e4;
    border-radius: 4px;
    overflow: hidden;
}

.date-list {
    .date-item {
        transition: all 0.2s;
        
        &:hover {
            background-color: #f0f0f0;
        }
        
        &.active {
            font-weight: 500;
            color: var(--el-color-primary);
        }
    }
}

.time-list {
    .time-item {
        transition: all 0.2s;
        
        &:hover {
            background-color: #f8f8f8;
        }
        
        &.text-primary {
            font-weight: 500;
        }
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>
