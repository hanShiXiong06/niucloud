<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="add-page" v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="form-item">
                <text class="label">课程名称</text>
                <input class="input" v-model="formData.course_name" placeholder="请输入课程名称" />
            </view>
            
            <view class="form-item">
                <text class="label">授课教师</text>
                <input class="input" v-model="formData.teacher" placeholder="请输入教师姓名（选填）" />
            </view>
            
            <view class="form-item">
                <text class="label">上课教室</text>
                <input class="input" v-model="formData.classroom" placeholder="如：教学楼A-301" />
            </view>
            
            <view class="form-item">
                <text class="label">星期</text>
                <picker :range="weekDays" @change="onWeekDayChange">
                    <view class="picker-value">
                        {{ weekDays[formData.week_day - 1] }}
                        <text class="iconfont icon-arrow-right"></text>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <text class="label">节次</text>
                <picker :range="sections" @change="onSectionChange">
                    <view class="picker-value">
                        第{{ formData.section }}节
                        <text class="iconfont icon-arrow-right"></text>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <text class="label">开始周</text>
                <picker :range="weeks" @change="onStartWeekChange">
                    <view class="picker-value">
                        第{{ formData.start_week }}周
                        <text class="iconfont icon-arrow-right"></text>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <text class="label">结束周</text>
                <picker :range="weeks" @change="onEndWeekChange">
                    <view class="picker-value">
                        第{{ formData.end_week }}周
                        <text class="iconfont icon-arrow-right"></text>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <text class="label">周类型</text>
                <picker :range="weekTypes" range-key="name" @change="onWeekTypeChange">
                    <view class="picker-value">
                        {{ weekTypes[formData.week_type].name }}
                        <text class="iconfont icon-arrow-right"></text>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <text class="label">颜色</text>
                <view class="color-list">
                    <view 
                        class="color-item" 
                        v-for="color in colors" 
                        :key="color"
                        :style="{ background: color }"
                        :class="{ active: formData.color === color }"
                        @click="formData.color = color"
                    ></view>
                </view>
            </view>
        </view>

        <view class="submit-bar">
            <button class="submit-btn" @click="handleSubmit">保存</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { addCourse, editCourse, getSchedule } from '../../api/xiaoyuan'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_schedule')

const weekDays = ['周一', '周二', '周三', '周四', '周五', '周六', '周日']
const sections = Array.from({ length: 12 }, (_, i) => `第${i + 1}节`)
const weeks = Array.from({ length: 20 }, (_, i) => `第${i + 1}周`)
const weekTypes = [
    { value: 0, name: '每周' },
    { value: 1, name: '单周' },
    { value: 2, name: '双周' }
]
const colors = ['#52c41a', '#ff6b00', '#ff6b6b', '#9c27b0', '#00bcd4', '#795548', '#607d8b']

const isEdit = ref(false)
const courseId = ref(0)

// 计算当前学期
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

const formData = ref({
    course_name: '',
    teacher: '',
    classroom: '',
    week_day: 1,
    section: 1,
    start_week: 1,
    end_week: 16,
    week_type: 0,
    color: '#52c41a',
    semester: getCurrentSemester()
})

onLoad((options: any) => {
    loadConfig()
    if (options?.id) {
        isEdit.value = true
        courseId.value = parseInt(options.id)
    }
    
    if (options?.day) {
        formData.value.week_day = parseInt(options.day)
    }
    
    if (options?.section) {
        formData.value.section = parseInt(options.section)
    }
})

const onWeekDayChange = (e: any) => {
    formData.value.week_day = parseInt(e.detail.value) + 1
}

const onSectionChange = (e: any) => {
    formData.value.section = parseInt(e.detail.value) + 1
}

const onStartWeekChange = (e: any) => {
    formData.value.start_week = parseInt(e.detail.value) + 1
}

const onEndWeekChange = (e: any) => {
    formData.value.end_week = parseInt(e.detail.value) + 1
}

const onWeekTypeChange = (e: any) => {
    formData.value.week_type = parseInt(e.detail.value)
}

const handleSubmit = async () => {
    if (!formData.value.course_name.trim()) {
        uni.showToast({ title: '请输入课程名称', icon: 'none' })
        return
    }
    
    // 检查该时间段是否已有课程（非编辑模式下）
    if (!isEdit.value) {
        const checkRes: any = await getSchedule({})
        if (checkRes.code === 1) {
            const existingCourses = checkRes.data.list || checkRes.data || []
            const conflict = existingCourses.find((c: any) => {
                const weekDay = parseInt(c.week_day)
                const section = parseInt(c.section)
                const startWeek = parseInt(c.start_week) || 1
                const endWeek = parseInt(c.end_week) || 99
                // 检查星期和节次是否相同，且周次有重叠
                if (weekDay === formData.value.week_day && section === formData.value.section) {
                    const newStart = formData.value.start_week
                    const newEnd = formData.value.end_week
                    // 检查周次是否有重叠
                    return !(newEnd < startWeek || newStart > endWeek)
                }
                return false
            })
            if (conflict) {
                uni.showToast({ title: `该时间段已有课程「${conflict.course_name}」`, icon: 'none', duration: 2500 })
                return
            }
        }
    }
    
    // 获取当前选择的学校
    const currentSchool = uni.getStorageSync('current_school')
    
    uni.showLoading({ title: '保存中...' })
    try {
        const api = isEdit.value ? editCourse : addCourse
        const data = isEdit.value 
            ? { ...formData.value, id: courseId.value } 
            : { ...formData.value, school_id: currentSchool?.id || 0 }
        const res: any = await api(data)
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '保存成功', icon: 'success' })
            setTimeout(() => uni.navigateBack(), 1500)
        } else {
            uni.showToast({ title: res.msg || '保存失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.add-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 120rpx;
}

.form-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 0 20rpx;
}

.form-item {
    display: flex;
    align-items: center;
    padding: 8rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child { border-bottom: none; }
    
    .label {
        font-size: 28rpx;
        color: #333;
        width: 160rpx;
        flex-shrink: 0;
    }
    
    .input {
        flex: 1;
        font-size: 28rpx;
    }
    
    .picker-value {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 28rpx;
        color: #666;
        
        .iconfont { color: #ccc; }
    }
}

.color-list {
    display: flex;
    gap: 16rpx;
    flex: 1;
}

.color-item {
    width: 50rpx;
    height: 50rpx;
    border-radius: 8rpx;
    
    &.active {
        box-shadow: 0 0 0 4rpx #fff, 0 0 0 6rpx currentColor;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;z-index: 22;
    padding: 20rpx 30rpx;
    background: #fff;
}

.submit-btn {
    width: 100%;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
}
</style>
