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

        <scroll-view scroll-y scroll-x class="schedule-content">
            <view class="week-header">
                <view class="time-col-header"></view>
                <view class="week-day" v-for="(day, index) in weekDays" :key="index" :class="{ today: isToday(index + 1) }">
                    <text class="day-name">{{ day }}</text>
                    <text class="day-date">{{ getDateByWeekDay(index + 1) }}</text>
                </view>
            </view>
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
                                <text class="course-room">{{ formatLocation(getCourse(day, idx + 1).location || getCourse(day, idx + 1).classroom) }}</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </scroll-view>

        <!-- 周数选择弹窗 -->
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

        <!-- 首次使用引导弹窗 -->
        <u-popup :show="showInitGuide" mode="center" round="20" :close-on-click-overlay="false">
            <view class="init-guide-popup">
                <!-- 步骤1: 选择年级（学校有班级数据时显示） -->
                <view v-if="initStep === 1">
                    <view class="guide-title">选择你的班级</view>
                    <view class="guide-desc">检测到你的学校已有课表数据，请选择年级</view>
                    <scroll-view scroll-y style="max-height: 50vh;">
                        <view v-if="gradeList.length > 0" class="grade-grid">
                            <view class="grade-item" v-for="g in gradeList" :key="g" :class="{ active: selectedGrade === g }" @click="selectGrade(g)">
                                {{ g }}级
                            </view>
                        </view>
                    </scroll-view>
                    <view class="guide-footer">
                        <view class="tip-action" @click="chooseCustomSchedule">找不到？自己创建课表</view>
                    </view>
                </view>

                <!-- 步骤2: 选择班级 -->
                <view v-if="initStep === 2">
                    <view class="guide-title">选择班级</view>
                    <view class="guide-back" @click="initStep = 1">
                        <u-icon name="arrow-left" size="16" color="#999"></u-icon>
                        <text>返回</text>
                    </view>
                    <scroll-view scroll-y style="max-height: 50vh;">
                        <view v-if="classListData.length > 0" class="class-grid">
                            <view class="class-item" v-for="c in classListData" :key="c.id" @click="confirmBindClass(c)">
                                {{ formatClassName(c.name) }}
                            </view>
                        </view>
                        <view v-else class="empty-tip">
                            <text>该年级暂无班级数据</text>
                            <view class="tip-action" @click="chooseCustomSchedule">自己创建课表</view>
                        </view>
                    </scroll-view>
                </view>
            </view>
        </u-popup>

    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getSchedule, getSchoolGrades, getSchoolClasses, bindClassSchedule } from '../../api/xiaoyuan'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_schedule')

const weekDays = ['周一', '周二', '周三', '周四', '周五', '周六', '周日']
const courseList = ref<any[]>([])
const currentWeek = ref(1)
const showWeekPicker = ref(false)

// 首次引导相关
const showInitGuide = ref(false)
const initStep = ref(1)
const gradeList = ref<string[]>([])
const classListData = ref<any[]>([])
const selectedGrade = ref('')
const scheduleSchoolId = ref(0)
const isCustomSchedule = ref(true)

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
    s.setHours(0, 0, 0, 0)
    const now = new Date()
    now.setHours(0, 0, 0, 0)
    const diff = now.getTime() - s.getTime()
    if (diff < 0) return 1
    const days = Math.floor(diff / (24 * 60 * 60 * 1000))
    const w = Math.floor(days / 7) + 1
    return Math.max(1, Math.min(w, totalWeeks.value))
})

const semesterLabel = computed(() => {
    const s = getCurrentSemester()
    // 格式: 2024-2025-1 → 2024-2025 第一学期
    const parts = s.split('-')
    if (parts.length === 3) {
        const term = parts[2] === '1' ? '第一学期' : '第二学期'
        return `${parts[0]}-${parts[1]} ${term}`
    }
    return s
})

onMounted(() => {
    loadConfig()
    loadSettings()
    loadSchedule(true)
})

onShow(() => {
    loadSettings()
    loadSchedule(true)
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
        if (parsed.school_id) {
            scheduleSchoolId.value = parsed.school_id
        }
    }
    currentWeek.value = autoWeek.value
}

const goToSetting = () => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/schedule/setting?is_custom=${isCustomSchedule.value ? 1 : 0}` })
}

const loadSchedule = async (resetWeek = false) => {
    const currentSchool = uni.getStorageSync('current_school')
    const schoolId = currentSchool?.id || scheduleSchoolId.value || 0
    const res: any = await getSchedule({ semester: getCurrentSemester(), school_id: schoolId })
    if (res.code === 1) {
        if (res.data.need_init && res.data.has_class_schedule) {
            // 学校有班级课表，引导用户选择年级/班级
            scheduleSchoolId.value = schoolId
            await checkSchoolClassData()
            return
        }
        courseList.value = res.data.list || res.data || []
        isCustomSchedule.value = res.data.is_custom == 1

        // 后端返回的学期信息同步到本地
        if (res.data.week_start) {
            settings.value.startDate = res.data.week_start
        }
        if (res.data.total_weeks) {
            const weeks = res.data.total_weeks
            if (settings.value.startDate) {
                const s = new Date(settings.value.startDate.replace(/-/g, '/'))
                const end = new Date(s.getTime() + weeks * 7 * 24 * 60 * 60 * 1000)
                settings.value.endDate = `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`
            }
        }
        // 后端返回的课节时间同步到本地
        if (res.data.sections && res.data.sections.length > 0) {
            settings.value.sections = res.data.sections
        }
        // 同步完学期信息后重新计算当前周
        if (resetWeek) {
            currentWeek.value = autoWeek.value
        }
    }
}

// 检查学校是否有班级课表数据
const checkSchoolClassData = async () => {
    const schoolId = scheduleSchoolId.value || 0

    if (!schoolId) {
        return
    }

    try {
        const res: any = await getSchoolGrades({ school_id: schoolId })
        if (res.code === 1 && res.data && res.data.length > 0) {
            // 学校有班级数据，显示年级选择
            gradeList.value = res.data
            initStep.value = 1
            showInitGuide.value = true
        }
    } catch (e) {
        // 出错不弹引导
    }
}

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

// ========== 首次引导逻辑 ==========

// 去掉班级名前面的编号，如 [2518012]xxx → xxx
const formatClassName = (name: string) => {
    return name.replace(/^\[.*?\]/, '')
}

const selectGrade = async (grade: string) => {
    selectedGrade.value = grade
    initStep.value = 2
    try {
        const res: any = await getSchoolClasses({ school_id: scheduleSchoolId.value, grade })
        if (res.code === 1) {
            classListData.value = res.data || []
        }
    } catch (e) {
        classListData.value = []
    }
}

const confirmBindClass = async (classItem: any) => {
    uni.showLoading({ title: '绑定中...' })
    try {
        const res: any = await bindClassSchedule({
            class_id: classItem.id,
            school_id: scheduleSchoolId.value,
            semester: getCurrentSemester()
        })
        if (res.code === 1) {
            showInitGuide.value = false
            uni.showToast({ title: '绑定成功', icon: 'success' })
            loadSchedule(true)
        } else {
            uni.showToast({ title: res.msg || '绑定失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.showToast({ title: e.message || '绑定失败', icon: 'none' })
    } finally {
        uni.hideLoading()
    }
}

const chooseCustomSchedule = () => {
    showInitGuide.value = false
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/schedule/add' })
}

// ========== 课表展示逻辑 ==========

const formatLocation = (str: string) => {
    if (!str) return ''
    const m = str.match(/\[(.+?)\]/)
    return m ? m[1] : str
}

const getCourse = (day: number, section: number) => {
    return courseList.value.find(c => {
        const weekDay = parseInt(c.week_day)
        const sec = parseInt(c.section || c.section_start)
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
    if (course) {
        if (course.is_class_course) {
            uni.showToast({ title: '编辑后将转为个人课表', icon: 'none', duration: 1500 })
        }
        if (course.id) {
            uni.navigateTo({
                url: `/addon/sd_xiaoyuan/pages/schedule/edit?id=${course.id}`,
                fail: () => { uni.showToast({ title: '页面跳转失败', icon: 'none' }) }
            })
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
        .semester { font-size: 28rpx; font-weight: bold; color: #333; }
        .week { font-size: 26rpx; color: #00c853; font-weight: bold; }
    }

    .header-actions {
        display: flex;
        gap: 16rpx;
        .action-btn {
            width: 64rpx; height: 64rpx; background: #f5f5f5;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
        }
    }
}

.week-header {
    display: flex;
    background: #fff;
    border-bottom: 1rpx solid #f0f0f0;
    min-width: max-content;

    .time-col-header { width: 80rpx; min-width: 80rpx; flex-shrink: 0; }

    .week-day {
        width: 160rpx; min-width: 160rpx; text-align: center; padding: 12rpx 0;
        &.today {
            background: #00c853; border-radius: 8rpx;
            .day-name, .day-date { color: #fff; font-weight: bold; }
        }
        .day-name { display: block; font-size: 24rpx; color: #333; }
        .day-date { font-size: 20rpx; color: #999; }
    }
}

.schedule-content { flex: 1; }
.schedule-grid { display: flex; background: #fff; min-width: max-content; }

.time-column {
    width: 80rpx; min-width: 80rpx; flex-shrink: 0;
    .time-item {
        height: 120rpx; display: flex; flex-direction: column;
        align-items: center; justify-content: center; border-bottom: 1rpx solid #f5f5f5;
        .section-num { font-size: 24rpx; color: #333; font-weight: bold; }
        .section-time { font-size: 18rpx; color: #bbb; }
    }
}

.course-columns { display: flex; }
.course-column { width: 160rpx; min-width: 160rpx; border-left: 1rpx solid #f5f5f5; }

.course-cell {
    height: 120rpx; border-bottom: 1rpx solid #f5f5f5; padding: 3rpx;
    overflow: hidden;
}

.course-item {
    width: 100%; height: 100%; border-radius: 8rpx; padding: 6rpx;
    display: flex; flex-direction: column; overflow: hidden;
    .course-name {
        font-size: 20rpx; color: #fff; font-weight: bold;
        display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2;
        -webkit-box-orient: vertical; overflow: hidden;
        word-break: break-all;
    }
    .course-room {
        font-size: 18rpx; color: rgba(255, 255, 255, 0.8); margin-top: auto;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
}

.popup-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 24rpx; padding-bottom: 20rpx; border-bottom: 1rpx solid #f0f0f0;
    .popup-title { font-size: 32rpx; font-weight: bold; color: #333; }
    .close-btn {
        width: 60rpx; height: 60rpx; display: flex; align-items: center;
        justify-content: center; border-radius: 50%; background: #f5f5f5;
    }
}

.week-picker-popup {
    background: #fff; padding: 24rpx;
    .week-grid {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 16rpx;
        .wk-item {
            padding: 20rpx 0; background: #f8f8f8; border-radius: 12rpx;
            text-align: center; font-size: 26rpx; color: #333; position: relative;
            &.active { background: #00c853; color: #fff; font-weight: bold; }
            &.current { border: 2rpx solid #00c853; }
            .wk-hint { display: block; font-size: 18rpx; color: #00c853; margin-top: 4rpx; }
            &.active .wk-hint { color: #fff; }
        }
    }
}

/* 首次引导弹窗样式 */
.init-guide-popup {
    width: 90vw; padding: 20rpx; background: #fff; border-radius: 20rpx;
}

.guide-title {
    font-size: 36rpx; font-weight: bold; color: #333; text-align: center; margin-bottom: 16rpx;
}

.guide-desc {
    font-size: 26rpx; color: #999; text-align: center; margin-bottom: 40rpx;
}

.guide-back {
    display: flex; align-items: center; gap: 8rpx; font-size: 26rpx; color: #999;
    margin-bottom: 24rpx;
}

.guide-footer {
    margin-top: 32rpx; text-align: center;
    .tip-action { color: #409eff; font-size: 26rpx; }
}

.grade-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 16rpx; margin-top: 16rpx;
}

.class-grid {
    display: grid; grid-template-columns: repeat(1, 1fr); gap: 16rpx; margin-top: 16rpx;
}

.grade-item {
    padding: 24rpx 12rpx; background: #f8f8f8; border-radius: 12rpx;
    text-align: center; font-size: 28rpx; color: #333;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.class-item {
    padding: 24rpx 12rpx; background: #f8f8f8; border-radius: 12rpx;
    text-align: center; font-size: 28rpx; color: #333;
    word-break: break-all;
}

.grade-item.active { background: #00c853; color: #fff; font-weight: bold; }

.empty-tip {
    text-align: center; padding: 40rpx 0; color: #999; font-size: 28rpx;
    .tip-action {
        margin-top: 20rpx; color: #409eff; font-size: 28rpx;
    }
}
</style>
