<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="edit-page" v-if="isFeatureEnabled">
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
            <button class="delete-btn" @click="handleDelete">删除</button>
            <button class="submit-btn" @click="handleSubmit">保存</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getCourseDetail, editCourse, deleteCourse } from '../../api/xiaoyuan'
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

const courseId = ref('')

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
        courseId.value = options.id
        loadDetail()
    }
})

const loadDetail = async () => {
    try {
        uni.showLoading({ title: '加载中...' })
        const res: any = await getCourseDetail({ id: courseId.value })
        uni.hideLoading()
        
        if (res.code === 1) {
            const data = res.data
            formData.value = {
                course_name: data.course_name || '',
                teacher: data.teacher || '',
                classroom: data.classroom || '',
                week_day: parseInt(data.week_day) || 1,
                section: parseInt(data.section) || 1,
                start_week: parseInt(data.start_week) || 1,
                end_week: parseInt(data.end_week) || 16,
                week_type: parseInt(data.week_type) || 0,
                color: data.color || '#52c41a',
                semester: getCurrentSemester()
            }
        } else {
            uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

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
    
    uni.showLoading({ title: '保存中...' })
    try {
        const res: any = await editCourse({ ...formData.value, id: courseId.value })
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

const handleDelete = () => {
    uni.showModal({
        title: '确认删除',
        content: '确定要删除这门课程吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    uni.showLoading({ title: '删除中...' })
                    const result: any = await deleteCourse({ id: courseId.value })
                    uni.hideLoading()
                    
                    if (result.code === 1) {
                        uni.showToast({ title: '删除成功', icon: 'success' })
                        setTimeout(() => uni.navigateBack(), 1500)
                    } else {
                        uni.showToast({ title: result.msg || '删除失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.hideLoading()
                    uni.showToast({ title: '网络错误', icon: 'none' })
                }
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.edit-page {
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
    right: 0;
    z-index: 22;
    padding: 20rpx 30rpx;
    background: #fff;
    display: flex;
    gap: 20rpx;
}

.delete-btn {
    flex: 1;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ff4d4f;
    color: #fff;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
}

.submit-btn {
    flex: 2;
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
