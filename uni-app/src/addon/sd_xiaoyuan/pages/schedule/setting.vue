<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="setting-page" v-if="isFeatureEnabled">
        <view class="setting-group">
            <text class="group-title">学期时间</text>
            <view v-if="!isCustom" class="class-tip">已绑定班级课表，学期时间由学校统一管理</view>
            <view class="setting-row">
                <text class="row-label">开学日期</text>
                <picker v-if="isCustom" mode="date" :value="settings.startDate" @change="onStartDateChange">
                    <view class="date-val">{{ settings.startDate || '请选择' }} <u-icon name="arrow-right" size="14" color="#999"></u-icon></view>
                </picker>
                <text v-else class="row-value">{{ settings.startDate || '未设置' }}</text>
            </view>
            <view class="setting-row">
                <text class="row-label">结束日期</text>
                <picker v-if="isCustom" mode="date" :value="settings.endDate" @change="onEndDateChange">
                    <view class="date-val">{{ settings.endDate || '请选择' }} <u-icon name="arrow-right" size="14" color="#999"></u-icon></view>
                </picker>
                <text v-else class="row-value">{{ settings.endDate || '未设置' }}</text>
            </view>
            <view class="setting-row">
                <text class="row-label">总周数</text>
                <text class="row-value">{{ totalWeeks }}周{{ isCustom ? '（自动计算）' : '' }}</text>
            </view>
        </view>

        <view class="setting-group">
            <view class="group-title-row">
                <text class="group-title">上课时间段</text>
                <view v-if="isCustom" class="add-section-btn" @click="addSection">
                    <u-icon name="plus" size="14" color="#00c853"></u-icon>
                    <text>添加</text>
                </view>
            </view>
            <view v-if="!isCustom" class="class-tip">课节时间由学校统一管理</view>
            <view class="section-list">
                <view class="section-row" v-for="(s, idx) in settings.sections" :key="idx">
                    <text class="section-idx">第{{ idx + 1 }}节</text>
                    <template v-if="isCustom">
                        <picker mode="time" :value="s.start" @change="onSectionStartChange($event, idx)">
                            <view class="time-pick">{{ s.start }}</view>
                        </picker>
                        <text class="section-sep">~</text>
                        <picker mode="time" :value="s.end" @change="onSectionEndChange($event, idx)">
                            <view class="time-pick">{{ s.end }}</view>
                        </picker>
                        <view class="del-section" v-if="settings.sections.length > 1" @click="removeSection(idx)">
                            <u-icon name="close" size="14" color="#ff4d4f"></u-icon>
                        </view>
                    </template>
                    <template v-else>
                        <text class="time-readonly">{{ s.start }}</text>
                        <text class="section-sep">~</text>
                        <text class="time-readonly">{{ s.end }}</text>
                    </template>
                </view>
            </view>
        </view>

        <view class="setting-footer">
            <button class="save-btn" @click="saveSetting">保存设置</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_schedule')

const STORAGE_KEY = 'sd_xiaoyuan_schedule_settings'
const isCustom = ref(true)

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

onLoad((options) => {
    loadConfig()
    if (options?.is_custom === '0') {
        isCustom.value = false
    }
    loadSettings()
})

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
}

const onStartDateChange = (e: any) => {
    settings.value.startDate = e.detail.value
}

const onEndDateChange = (e: any) => {
    settings.value.endDate = e.detail.value
}

const onSectionStartChange = (e: any, idx: number) => {
    settings.value.sections[idx].start = e.detail.value
}

const onSectionEndChange = (e: any, idx: number) => {
    settings.value.sections[idx].end = e.detail.value
}

const addSection = () => {
    const last = settings.value.sections[settings.value.sections.length - 1]
    settings.value.sections.push({ start: last?.end || '08:00', end: '08:45' })
}

const removeSection = (idx: number) => {
    settings.value.sections.splice(idx, 1)
}

const saveSetting = () => {
    uni.setStorageSync(STORAGE_KEY, JSON.stringify(settings.value))
    uni.showToast({ title: '设置已保存', icon: 'success' })
    setTimeout(() => {
        uni.navigateBack()
    }, 1000)
}
</script>

<style lang="scss" scoped>
.setting-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 24rpx;
    padding-bottom: 160rpx;
}

.setting-group {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 24rpx;

    .group-title {
        font-size: 28rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 20rpx;
        display: block;
    }

    .class-tip {
        font-size: 24rpx;
        color: #ff9800;
        background: #fff8e1;
        padding: 16rpx 20rpx;
        border-radius: 8rpx;
        margin-bottom: 16rpx;
    }

    .group-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20rpx;

        .group-title { margin-bottom: 0; }

        .add-section-btn {
            display: flex;
            align-items: center;
            gap: 4rpx;
            padding: 8rpx 20rpx;
            background: #f0faf0;
            border-radius: 20rpx;

            text { font-size: 24rpx; color: #00c853; }
        }
    }
}

.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f8f8f8;

    .row-label { font-size: 28rpx; color: #333; }
    .row-value { font-size: 26rpx; color: #999; }

    .date-val {
        display: flex;
        align-items: center;
        gap: 8rpx;
        font-size: 26rpx;
        color: #00c853;
    }
}

.section-list {
    .section-row {
        display: flex;
        align-items: center;
        gap: 12rpx;
        padding: 16rpx 0;
        border-bottom: 1rpx solid #f8f8f8;

        .section-idx {
            font-size: 24rpx;
            color: #666;
            width: 80rpx;
            flex-shrink: 0;
        }

        .time-pick {
            padding: 10rpx 20rpx;
            background: #f5f5f5;
            border-radius: 8rpx;
            font-size: 26rpx;
            color: #333;
        }

        .section-sep { font-size: 24rpx; color: #999; }

        .time-readonly {
            padding: 10rpx 20rpx;
            background: #f5f5f5;
            border-radius: 8rpx;
            font-size: 26rpx;
            color: #666;
        }

        .del-section {
            width: 48rpx;
            height: 48rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff0f0;
            flex-shrink: 0;
        }
    }
}

.setting-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;

    .save-btn {
        width: 100%;
        height: 80rpx;
        line-height: 80rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        border: none;
        border-radius: 40rpx;
        font-size: 30rpx;
        font-weight: bold;

        &::after { border: none; }
    }
}
</style>
