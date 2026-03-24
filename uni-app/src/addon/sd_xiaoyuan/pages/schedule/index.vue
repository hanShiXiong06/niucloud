<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="schedule-page" v-if="isFeatureEnabled">
        <view class="page-header">
            <view class="semester-info" @click="showWeekPicker = true">
                <text class="semester">{{ semesterLabel }}</text>
                <text class="week">第{{ currentWeek }}周</text>
                <u-icon name="arrow-down" size="16" color="#00c853"></u-icon>
            </view>
            <view class="header-actions">
                <view class="action-btn" @click="goToAdd">
                    <u-icon name="plus" size="22" color="#333"></u-icon>
                </view>
                <view class="action-btn setting" @click="goToSetting">
                    <u-icon name="setting" size="22" color="#333"></u-icon>
                </view>
            </view>
        </view>

        <view class="week-header">
            <view class="time-col-header"></view>
            <view class="week-day" v-for="(day, index) in weekDays" :key="index" :class="{ today: isToday(index + 1) }">
                <text class="day-name">{{ day }}</text>
                <text class="day-date">{{ getDateByWeekDay(index + 1) }}</text>
            </view>
        </view>

        <scroll-view scroll-y class="schedule-content">
            <view class="schedule-grid">
                <view class="time-column">
                    <view class="time-item" v-for="(t, idx) in settings.sections" :key="idx">
                        <text class="section-num">{{ idx + 1 }}</text>
                        <text class="section-time">{{ t.start }}</text>
                    </view>
                </view>
                <view class="course-columns">
                    <view class="course-column" v-for="day in 7" :key="day">
                        <view class="course-cell" v-for="(t, idx) in settings.sections" :key="idx" @click="handleCellClick(day, idx + 1)">
                            <view class="course-item" v-if="getCourse(day, idx + 1)" :style="{ background: getCourse(day, idx + 1).color }">
                                <text class="course-name">{{ getCourse(day, idx + 1).course_name }}</text>
                                <text class="course-room">{{ getCourse(day, idx + 1).classroom }}</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </scroll-view>

        <u-popup :show="showWeekPicker" mode="bottom" round="20" @close="showWeekPicker = false">
            <view class="week-picker-popup">
                <view class="popup-header">
                    <text class="popup-title">选择周数</text>
                    <view class="close-btn" @click="showWeekPicker = false">
                        <u-icon name="close" size="20" color="#999"></u-icon>
                    </view>
                </view>
                <scroll-view scroll-y style="max-height: 60vh;">
                    <view class="week-grid">
                        <view class="wk-item" v-for="week in totalWeeks" :key="week" :class="{ active: currentWeek === week, current: week === autoWeek }" @click="selectWeek(week)">
                            <text>第{{ week }}周</text>
                            <text class="wk-hint" v-if="week === autoWeek">本周</text>
                        </view>
                    </view>
                </scroll-view>
            </view>
        </u-popup>


    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getSchedule } from '../../api/xiaoyuan'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_schedule')

const weekDays = ['周一', '周二', '周三', '周四', '周五', '周六', '周日']
const courseList = ref<any[]>([])
const currentWeek = ref(1)
const showWeekPicker = ref(false)

const defaultSections = [
    { start: '08:00', end: '08:45' },
    { start: '08:55', end: '09:40' },
    { start: '10:00', end: '10:45' },
    { start: '10:55', end: '11:40' },
    { start: '14:00', end: '14:45' },
    { start: '14:55', end: '15:40' },
    { start: '16:00', end: '16:45' },
    { start: '16:55', end: '17:40' },
    { start: '19:00', end: '19:45' },
    { start: '19:55', end: '20:40' },
]

const settings = ref({
    startDate: '',
    endDate: '',
    sections: [...defaultSections],
})

const totalWeeks = computed(() => {
    if (!settings.value.startDate || !settings.value.endDate) return 20
    const s = new Date(settings.value.startDate.replace(/-/g, '/'))
    const e = new Date(settings.value.endDate.replace(/-/g, '/'))
    const diff = e.getTime() - s.getTime()
    if (diff <= 0) return 1
    return Math.ceil(diff / (7 * 24 * 60 * 60 * 1000))
})

const autoWeek = computed(() => {
    if (!settings.value.startDate) return 1
    const s = new Date(settings.value.startDate.replace(/-/g, '/'))
    const now = new Date()
    const diff = now.getTime() - s.getTime()
    if (diff < 0) return 1
    const w = Math.ceil(diff / (7 * 24 * 60 * 60 * 1000))
    return Math.max(1, Math.min(w, totalWeeks.value))
})

const semesterLabel = computed(() => {
    if (!settings.value.startDate) return '未设置学期'
    const d = new Date(settings.value.startDate.replace(/-/g, '/'))
    const y = d.getFullYear()
    const m = d.getMonth() + 1
    return m >= 8 ? `${y}-${y + 1} 第一学期` : `${y - 1}-${y} 第二学期`
})

onMounted(() => {
    loadConfig()
    loadSettings()
    loadSchedule()
})

onShow(() => {
    loadSettings()
    loadSchedule()
})

const STORAGE_KEY = 'sd_xiaoyuan_schedule_settings'

const loadSettings = () => {
    const saved = uni.getStorageSync(STORAGE_KEY)
    if (saved) {
        const parsed = JSON.parse(saved)
        settings.value.startDate = parsed.startDate || ''
        settings.value.endDate = parsed.endDate || ''
        if (parsed.sections && parsed.sections.length > 0) {
            settings.value.sections = parsed.sections
        }
    }
    currentWeek.value = autoWeek.value
}

const goToSetting = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/schedule/setting' })
}

const loadSchedule = async () => {
    const res: any = await getSchedule({ semester: getCurrentSemester() })
    console.log('课表数据:', res)
    if (res.code === 1) {
        courseList.value = res.data.list || res.data || []
        console.log('课程列表:', courseList.value)
    }
}

// 添加获取当前学期的函数
const getCurrentSemester = () => {
    const now = new Date()
    const month = now.getMonth() + 1
    const year = now.getFullYear()
    
    if (month >= 9) {
        return `${year}-${year + 1}-1`
    } else if (month >= 2) {
        return `${year - 1}-${year}-2`
    } else {
        return `${year - 1}-${year}-1`
    }
}

const getCourse = (day: number, section: number) => {
    return courseList.value.find(c => {
        const weekDay = parseInt(c.week_day)
        const sec = parseInt(c.section)
        const startWeek = parseInt(c.start_week) || 1
        const endWeek = parseInt(c.end_week) || 99
        return weekDay === day && sec === section && currentWeek.value >= startWeek && currentWeek.value <= endWeek
    })
}

const isToday = (day: number) => {
    const today = new Date().getDay()
    return today === 0 ? day === 7 : day === today
}

const getDateByWeekDay = (day: number) => {
    if (!settings.value.startDate) {
        const today = new Date()
        const currentDay = today.getDay() || 7
        const diff = day - currentDay
        const target = new Date(today.getTime() + diff * 86400000)
        return `${target.getMonth() + 1}/${target.getDate()}`
    }
    const s = new Date(settings.value.startDate.replace(/-/g, '/'))
    const weekOffset = (currentWeek.value - 1) * 7
    const dayOffset = weekOffset + (day - 1)
    const target = new Date(s.getTime() + dayOffset * 86400000)
    return `${target.getMonth() + 1}/${target.getDate()}`
}

const handleCellClick = (day: number, section: number) => {
    const course = getCourse(day, section)
    console.log('点击的课程:', course)
    if (course) {
        console.log('课程ID:', course.id)
        if (course.id) {
            // 尝试使用绝对路径
            const url = `/addon/sd_xiaoyuan/pages/schedule/edit?id=${course.id}`
            console.log('导航到:', url)
            uni.navigateTo({ 
                url: url,
                fail: (err) => {
                    console.error('导航失败:', err)
                    uni.showToast({ title: '页面跳转失败', icon: 'none' })
                }
            })
        } else {
            uni.showToast({ title: '课程数据异常，无法编辑', icon: 'none' })
        }
    } else {
        uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/schedule/add?day=${day}&section=${section}&week=${currentWeek.value}` })
    }
}

const goToAdd = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/schedule/add' })
}

const selectWeek = (week: number) => {
    currentWeek.value = week
    showWeekPicker.value = false
    loadSchedule()
}

</script>

<style lang="scss" scoped>
.schedule-page {
    min-height: 100vh;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24rpx;
    background: #fff;

    .semester-info {
        display: flex;
        align-items: center;
        gap: 12rpx;

        .semester {
            font-size: 28rpx;
            font-weight: bold;
            color: #333;
        }

        .week {
            font-size: 26rpx;
            color: #00c853;
            font-weight: bold;
        }
    }

    .header-actions {
        display: flex;
        gap: 16rpx;

        .action-btn {
            width: 64rpx;
            height: 64rpx;
            background: #f5f5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }
}

.week-header {
    display: flex;
    background: #fff;
    border-bottom: 1rpx solid #f0f0f0;

    .time-col-header {
        width: 80rpx;
    }

    .week-day {
        flex: 1;
        text-align: center;
        padding: 12rpx 0;

        &.today {
            background: #00c853;
            border-radius: 8rpx;
            .day-name, .day-date { color: #fff; font-weight: bold; }
        }

        .day-name {
            display: block;
            font-size: 24rpx;
            color: #333;
        }

        .day-date {
            font-size: 20rpx;
            color: #999;
        }
    }
}

.schedule-content { flex: 1; }

.schedule-grid {
    display: flex;
    background: #fff;
}

.time-column {
    width: 80rpx;

    .time-item {
        height: 120rpx;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-bottom: 1rpx solid #f5f5f5;

        .section-num { font-size: 24rpx; color: #333; font-weight: bold; }
        .section-time { font-size: 18rpx; color: #bbb; }
    }
}

.course-columns { flex: 1; display: flex; }
.course-column { flex: 1; border-left: 1rpx solid #f5f5f5; }

.course-cell {
    height: 120rpx;
    border-bottom: 1rpx solid #f5f5f5;
    padding: 3rpx;
}

.course-item {
    width: 100%;
    height: 100%;
    border-radius: 8rpx;
    padding: 6rpx;
    display: flex;
    flex-direction: column;
    overflow: hidden;

    .course-name {
        font-size: 20rpx;
        color: #fff;
        font-weight: bold;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .course-room {
        font-size: 18rpx;
        color: rgba(255, 255, 255, 0.8);
        margin-top: auto;
    }
}

.popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24rpx;
    padding-bottom: 20rpx;
    border-bottom: 1rpx solid #f0f0f0;

    .popup-title { font-size: 32rpx; font-weight: bold; color: #333; }

    .close-btn {
        width: 60rpx; height: 60rpx;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; background: #f5f5f5;
    }
}

.week-picker-popup {
    background: #fff;
    padding: 24rpx;

    .week-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16rpx;

        .wk-item {
            padding: 20rpx 0;
            background: #f8f8f8;
            border-radius: 12rpx;
            text-align: center;
            font-size: 26rpx;
            color: #333;
            position: relative;

            &.active { background: #00c853; color: #fff; font-weight: bold; }
            &.current { border: 2rpx solid #00c853; }

            .wk-hint {
                display: block;
                font-size: 18rpx;
                color: #00c853;
                margin-top: 4rpx;
            }

            &.active .wk-hint { color: #fff; }
        }
    }
}

</style>
