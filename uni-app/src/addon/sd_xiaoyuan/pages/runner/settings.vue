<template>
    <view class="settings-page">
        <!-- 服务类型 -->
        <view class="section">
            <view class="section-title">接单服务类型</view>
            <view class="section-desc">选择您愿意接的任务类型，根据所在学校接单</view>
            <view class="type-list">
                <view 
                    class="type-item" 
                    :class="{ active: settings.accept_types.includes(type.key) }"
                    v-for="type in taskTypes"
                    :key="type.key"
                    @click="toggleType(type.key)"
                >
                    <u-icon :name="type.icon" size="20" :color="settings.accept_types.includes(type.key) ? '#333' : '#999'"></u-icon>
                    <text class="type-name">{{ type.name }}</text>
                    <u-icon v-if="settings.accept_types.includes(type.key)" name="checkmark" size="16" color="#333"></u-icon>
                </view>
            </view>
        </view>

        <!-- 个人信息 -->
        <view class="section">
            <view class="section-title">个人信息</view>
            <view class="info-item">
                <text class="label">头像</text>
                <xy-upload v-model="runnerInfo.avatar" :maxCount="1" />
            </view>
            <view class="info-item">
                <text class="label">姓名</text>
                <input class="value-input" v-model="runnerInfo.real_name" placeholder="请输入姓名" />
            </view>
            <view class="info-item">
                <text class="label">手机号</text>
                <input class="value-input" v-model="runnerInfo.mobile" type="number" placeholder="请输入手机号" />
            </view>
            <view class="info-item">
                <text class="label">所属学校</text>
                <text class="value-text">{{ runnerInfo.school_name || '未设置' }}</text>
            </view>
            <view class="info-item">
                <text class="label">评分</text>
                <view class="value">
                    <u-icon name="star-fill" size="16" color="#ff9500"></u-icon>
                    <text class="value-text" style="margin-left: 6rpx;">{{ runnerInfo.score }}分</text>
                </view>
            </view>
        </view>

        <!-- 其他设置 -->
        <view class="section">
            <view class="section-title">其他</view>
            <view class="menu-item" @click="goToAgreement">
                <text>服务协议</text>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>
            <view class="menu-item" @click="goToHelp">
                <text>帮助中心</text>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>
        </view>

        <!-- 保存按钮 -->
        <button class="save-btn" @click="saveSettings">保存设置</button>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getRunnerInfo, setRange, updateRunnerInfo } from '../../api/runner'
import xyUpload from '../../components/xy-upload.vue'
import { img } from '@/utils/common'

const runnerInfo = ref<any>({})
const settings = ref({
    accept_types: ['EXPRESS', 'BUY', 'ERRAND', 'PRINT', 'CLEAN', 'TRASH', 'CARRY', 'HELP']
})

const taskTypes = [
    { key: 'EXPRESS', name: '代取快递', icon: 'gift' },
    { key: 'BUY', name: '帮我买', icon: 'shopping-cart' },
    { key: 'ERRAND', name: '跑腿代办', icon: 'map' },
    { key: 'PRINT', name: '帮打印', icon: 'file-text' },
    { key: 'CLEAN', name: '代清洁', icon: 'star' },
    { key: 'TRASH', name: '扔垃圾', icon: 'trash' },
    { key: 'CARRY', name: '帮搬运', icon: 'car' },
    { key: 'HELP', name: '帮帮忙', icon: 'question-circle' }
]

onMounted(() => {
    loadRunnerInfo()
})

const loadRunnerInfo = async () => {
    try {
        const res: any = await getRunnerInfo()
        if (res.code === 1 && res.data) {
            runnerInfo.value = res.data
            if (res.data.accept_types) {
                try {
                    const types = typeof res.data.accept_types === 'string' ? JSON.parse(res.data.accept_types) : res.data.accept_types
                    if (Array.isArray(types) && types.length > 0) {
                        settings.value.accept_types = types
                    }
                } catch (e) {}
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const toggleType = (key: string) => {
    const index = settings.value.accept_types.indexOf(key)
    if (index > -1) {
        if (settings.value.accept_types.length > 1) {
            settings.value.accept_types.splice(index, 1)
        } else {
            uni.showToast({ title: '至少选择一种服务类型', icon: 'none' })
        }
    } else {
        settings.value.accept_types.push(key)
    }
}

const saveSettings = async () => {
    try {
        uni.showLoading({ title: '保存中...' })
        await Promise.all([
            setRange({ accept_types: JSON.stringify(settings.value.accept_types) }),
            updateRunnerInfo({
                real_name: runnerInfo.value.real_name,
                mobile: runnerInfo.value.mobile,
                avatar: runnerInfo.value.avatar
            })
        ])
        uni.hideLoading()
        uni.showToast({ title: '保存成功', icon: 'success' })
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const goToAgreement = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/agreement' })
}

const goToHelp = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/help' })
}
</script>

<style lang="scss" scoped>
.settings-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
    padding-bottom: 150rpx;
}

.section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child {
        border-bottom: none;
    }
    
    .label {
        font-size: 28rpx;
        color: #333;
    }
    
    .range-control {
        display: flex;
        align-items: center;
        flex: 1;
        margin-left: 30rpx;
        
        slider {
            flex: 1;
        }
        
        .range-value {
            width: 80rpx;
            text-align: right;
            font-size: 28rpx;
            color: #000000;
            font-weight: bold;
        }
    }
}

.type-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20rpx;
}

.type-item {
    display: flex;
    align-items: center;
    padding: 16rpx 24rpx;
    background: #f8f8f8;
    border-radius: 30rpx;
    border: 2rpx solid transparent;
    
    &.active {
        background: #c0fe95;
        border-color: #c0fe95;
        
        .type-name {
            color: #333;
            font-weight: bold;
        }
        
        .icon-check {
            color: #333;
            margin-left: 8rpx;
        }
    }
    
    .type-name {
        font-size: 26rpx;
        color: #666;
    }
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child {
        border-bottom: none;
    }
    
    .label {
        font-size: 28rpx;
        color: #333;
    }
    
    .value {
        display: flex;
        align-items: center;
        font-size: 28rpx;
        color: #999;
        
        .avatar {
            width: 60rpx;
            height: 60rpx;
            border-radius: 50%;
            margin-right: 16rpx;
        }
    }
    
    .value-input {
        flex: 1;
        text-align: right;
        font-size: 28rpx;
        color: #333;
    }
}

.menu-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child {
        border-bottom: none;
    }
    
    text {
        font-size: 28rpx;
        color: #333;
    }
    
    .icon-arrow-right {
        color: #ccc;
    }
}

.save-btn {
    position: fixed;
    bottom: 30rpx;
    left: 30rpx;
    right: 30rpx;
    height: 88rpx;
    line-height: 88rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
}
</style>
