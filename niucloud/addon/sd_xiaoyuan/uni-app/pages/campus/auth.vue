<template>
    <view class="campus-auth-page">
        <!-- Custom Header -->

        <view class="main-content">
        <!-- 已认证状态 -->
        <view class="auth-success" v-if="!showForm && authInfo.status === 1" style="position: relative;z-index: 2;">
            <view class="success-icon">
                <u-icon name="checkmark-circle-fill" size="60" color="#52c41a"></u-icon>
            </view>
            <text class="title">已完成校园认证</text>
            <view class="auth-info">
                <view class="info-item">
                    <text class="label">姓名</text>
                    <text class="value">{{ authInfo.real_name }}</text>
                </view>
                <view class="info-item">
                    <text class="label">身份</text>
                    <text class="value">{{ getIdentityTypeName(authInfo.identity_type) }}</text>
                </view>
                <view class="info-item">
                    <text class="label">学校</text>
                    <text class="value">{{ authInfo.school_name }}</text>
                </view>
                <view class="info-item">
                    <text class="label">学号</text>
                    <text class="value">{{ authInfo.student_no }}</text>
                </view>
                <view class="info-item" v-if="authInfo.college || authInfo.major">
                    <text class="label">院系/专业</text>
                    <text class="value">{{ authInfo.college || authInfo.major || '-' }}</text>
                </view>
                <view class="info-item" v-if="authInfo.campus">
                    <text class="label">校区</text>
                    <text class="value">{{ authInfo.campus }}</text>
                </view>
            </view>
        </view>

        <!-- 审核中状态 -->
        <view class="auth-pending" v-else-if="!showForm && authInfo.status === 0" style="position: relative;z-index: 2;">
            <view class="pending-icon">
                <u-icon name="clock-fill" size="60" color="#ff9500"></u-icon>
            </view>
            <text class="title">认证审核中</text>
            <text class="desc">您的认证申请正在审核中，请耐心等待</text>
        </view>

        <!-- 审核拒绝状态 -->
        <view class="auth-rejected" v-else-if="authInfo.status === 2 && !showForm" style="position: relative;z-index: 2;">
            <view class="rejected-icon">
                <u-icon name="close-circle-fill" size="60" color="#ff4d4f"></u-icon>
            </view>
            <text class="title">认证未通过</text>
            <text class="desc">{{ authInfo.refuse_reason || '您的认证申请未通过审核' }}</text>
            <button class="retry-btn" @click="resetForm">重新申请</button>
        </view>

        <!-- 未认证/申请表单 -->
        <view class="auth-form" v-else>
           

            <view class="form-section">
                <view class="form-item">
                    <text class="label required">真实姓名</text>
                    <input v-model="formData.real_name" placeholder="请输入真实姓名" />
                </view>
                <!-- 身份类型默认学生，隐藏选择 -->
                <view class="form-item">
                    <text class="label required">学校名称</text>
                    <view class="select-wrap" @click="showSchoolPicker = true">
                        <text class="select-value" :class="{placeholder: !formData.school_id}">{{ selectedSchoolName || '请选择学校' }}</text>
                        <u-icon name="arrow-down" size="24" color="#999"></u-icon>
                    </view>
                </view>
                <view class="form-item" v-if="campusList.length > 0">
                    <text class="label">校区</text>
                    <view class="select-wrap" @click="showCampusPicker = true">
                        <text class="select-value" :class="{placeholder: !formData.campus}">{{ formData.campus || '请选择校区' }}</text>
                        <u-icon name="arrow-down" size="24" color="#999"></u-icon>
                    </view>
                </view>
                <view class="form-item">
                    <text class="label required">学号</text>
                    <input v-model="formData.student_no" placeholder="请输入学号" />
                </view>
                <view class="form-item">
                    <text class="label">院系/部门</text>
                    <input v-model="formData.college" placeholder="请输入院系或部门（选填）" />
                </view>
                <view class="form-item">
                    <text class="label required">证件照片</text>
                    <xy-upload v-model="formData.cert_image" :maxCount="1" />
                    <text class="tip">请上传清晰的证件照片，用于身份审核</text>
                </view>
            </view>

            <button class="submit-btn" @click="submitAuth">提交认证</button>
        </view>
        
        <!-- 学校选择弹窗 -->
        <u-popup :show="showSchoolPicker" mode="bottom" @close="showSchoolPicker = false" round="16">
            <view class="picker-popup">
                <view class="picker-header">
                    <text class="picker-title">选择学校</text>
                    <u-icon name="close" size="24" @click="showSchoolPicker = false"></u-icon>
                </view>
                <scroll-view scroll-y class="picker-list">
                    <view 
                        class="picker-item" 
                        v-for="school in schoolList" 
                        :key="school.id"
                        :class="{active: formData.school_id === school.id}"
                        @click="onSchoolSelect(school)"
                    >
                        <text>{{ school.name }}</text>
                        <u-icon v-if="formData.school_id === school.id" name="checkmark" size="20" color="#333"></u-icon>
                    </view>
                    <view v-if="schoolList.length === 0" class="picker-empty">
                        <text>暂无学校数据</text>
                    </view>
                </scroll-view>
            </view>
        </u-popup>
        
        <!-- 校区选择弹窗 -->
        <u-popup :show="showCampusPicker" mode="bottom" @close="showCampusPicker = false" round="16">
            <view class="picker-popup">
                <view class="picker-header">
                    <text class="picker-title">选择校区</text>
                    <u-icon name="close" size="24" @click="showCampusPicker = false"></u-icon>
                </view>
                <scroll-view scroll-y class="picker-list">
                    <view 
                        class="picker-item" 
                        v-for="(campus, index) in campusList" 
                        :key="index"
                        :class="{active: formData.campus === campus}"
                        @click="onCampusSelect(campus)"
                    >
                        <text>{{ campus }}</text>
                        <u-icon v-if="formData.campus === campus" name="checkmark" size="20" color="#333"></u-icon>
                    </view>
                </scroll-view>
            </view>
        </u-popup>
    </view>
    </view> <!-- Add this closing tag -->
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { getCampusAuthInfo, applyCampusAuth, getSchoolList, getSchoolCampusList } from '../../api/xiaoyuan'
import xyUpload from '../../components/xy-upload.vue'

const authInfo = ref<any>({})
const showForm = ref(false)
const statusBarHeight = ref(0)
const navBarHeight = ref(44)

const formData = ref({
    real_name: '',
    identity_type: 'STUDENT',
    school_id: 0,
    campus: '',
    student_no: '',
    college: '',
    cert_image: ''
})

const schoolList = ref<any[]>([])
const campusList = ref<string[]>([])
const showSchoolPicker = ref(false)
const showCampusPicker = ref(false)

const selectedSchoolName = computed(() => {
    const school = schoolList.value.find(s => s.id === formData.value.school_id)
    return school ? school.name : ''
})

const identityTypes = [
    { key: 'STUDENT', name: '学生' },
    { key: 'TEACHER', name: '教师' },
    { key: 'STAFF', name: '教职工' }
]

const identityTypeMap: Record<string, string> = {
    'STUDENT': '学生',
    'TEACHER': '教师',
    'STAFF': '教职工'
}

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    loadAuthInfo()
    loadSchoolList()
})

const goBack = () => {
    // 如果已认证通过，直接跳转到个人中心
    if (authInfo.value.status === 1) {
        uni.reLaunch({ url: '/addon/sd_xiaoyuan/pages/user/index' })
        return
    }
    
    const pages = getCurrentPages()
    if (pages.length > 1) {
        uni.navigateBack()
    } else {
        uni.reLaunch({ url: '/addon/sd_xiaoyuan/pages/user/index' })
    }
}

const loadSchoolList = async () => {
    try {
        const res: any = await getSchoolList()
        if (res.code === 1 && res.data) {
            schoolList.value = res.data.list || res.data || []
        }
    } catch (e: any) {
        console.error('加载学校列表失败:', e)
        uni.showToast({ title: e.msg || '加载学校列表失败', icon: 'none' })
    }
}

const onSchoolSelect = async (school: any) => {
    formData.value.school_id = school.id
    formData.value.campus = ''
    showSchoolPicker.value = false
    
    // 加载校区列表
    try {
        const res: any = await getSchoolCampusList(school.id)
        if (res.code === 1 && res.data) {
            campusList.value = res.data || []
        }
    } catch (e) {
        campusList.value = []
    }
}

const onCampusSelect = (campus: string) => {
    formData.value.campus = campus
    showCampusPicker.value = false
}

const loadAuthInfo = async () => {
    try {
        const res: any = await getCampusAuthInfo()
        if (res.code === 1 && res.data) {
            authInfo.value = res.data
            // 如果已认证且有学校信息，保存到缓存
            if (res.data.school_id && res.data.school_name) {
                const cachedSchool = {
                    id: res.data.school_id,
                    name: res.data.school_name,
                    campus: res.data.campus || ''
                }
                uni.setStorageSync('current_school', cachedSchool)
            }
            // 如果已经认证通过，直接跳转到个人中心
            // if (res.data.status === 1) {
            //     setTimeout(() => {
            //         uni.reLaunch({ url: '/addon/sd_xiaoyuan/pages/user/index' })
            //     }, 500)
            // }
        }
    } catch (e) {
        console.error(e)
    }
}

const getIdentityTypeName = (type: string) => {
    return identityTypeMap[type] || type
}

const resetForm = () => {
    console.log('重置表单')
    formData.value = {
        real_name: '',
        identity_type: 'STUDENT',
        school_id: 0,
        campus: '',
        student_no: '',
        college: '',
        cert_image: ''
    }
    showForm.value = true
}

const submitAuth = async () => {
    if (!formData.value.real_name) {
        uni.showToast({ title: '请输入真实姓名', icon: 'none' })
        return
    }
    if (!formData.value.school_id) {
        uni.showToast({ title: '请选择学校', icon: 'none' })
        return
    }
    // 如果有校区列表，则校区必选
    if (campusList.value.length > 0 && !formData.value.campus) {
        uni.showToast({ title: '请选择校区', icon: 'none' })
        return
    }
    if (!formData.value.student_no) {
        uni.showToast({ title: '请输入学号', icon: 'none' })
        return
    }
    if (!formData.value.cert_image) {
        uni.showToast({ title: '请上传证件照片', icon: 'none' })
        return
    }
    
    try {
        uni.showLoading({ title: '提交中...' })
        const res: any = await applyCampusAuth(formData.value)
        uni.hideLoading()
        
        if (res.code === 1) {
            // 保存选择的学校到缓存
            const school = schoolList.value.find(s => s.id === formData.value.school_id)
            if (school) {
                uni.setStorageSync('current_school', { ...school, campus: formData.value.campus || '' })
            }
            uni.showToast({ title: '提交成功', icon: 'success' })
            setTimeout(() => {
                uni.reLaunch({ url: '/addon/sd_xiaoyuan/pages/user/index' })
            }, 1500)
        } else {
            uni.showToast({ title: res.msg || '提交失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.msg || '提交失败', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.campus-auth-page {
    min-height: 100vh;
    background: #f7f7f7;
    padding: 20rpx;
}


.main-content {
    box-sizing: border-box;
    position: relative;
    z-index: 1;
}

.auth-success, .auth-pending, .auth-rejected {
    background: #fff;
    border-radius: 16rpx;
    padding: 60rpx 30rpx;
    text-align: center;
    
    .title {
        display: block;
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 16rpx;
    }
    
    .desc {
        display: block;
        font-size: 28rpx;
        color: #999;
    }
}

.success-icon {
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    background: #e6ffd6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30rpx;
}

.pending-icon {
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    background: #fff7e6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30rpx;
}

.rejected-icon {
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    background: #fff2f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30rpx;
}

.auth-info {
    margin-top: 40rpx;
    text-align: left;
    
    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 20rpx 0;
        border-bottom: 1rpx solid #f0f0f0;
        
        &:last-child {
            border-bottom: none;
        }
        
        .label {
            font-size: 28rpx;
            color: #666;
        }
        
        .value {
            font-size: 28rpx;
            color: #333;
        }
    }
}

.retry-btn {
    margin-top: 40rpx;
    width: 100%;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #333;
    font-size: 32rpx;
    font-weight: 500;
    border-radius: 44rpx;
    border: none;
}

.auth-form {
    margin-top: 0;
    padding-top: 30rpx;
    position: relative;
    z-index: 21;
    .form-header {
        text-align: center;
        padding: 0rpx 0;
        
        .title {
            display: block;
            font-size: 40rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 16rpx;
        }
        
        .desc {
            font-size: 28rpx;
            color: #999;
        }
    }
}

.form-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
}

.form-item {
    margin-bottom: 20rpx;
    
    &:last-child {
        margin-bottom: 0;
    }
    
    .label {
        display: block;
        font-size: 28rpx;
        color: #333;
        margin-bottom: 16rpx;
        
        &.required::before {
            content: '*';
            color: #ff4d4f;
            margin-right: 8rpx;
        }
    }
    
    input {
        width: 100%;
        height: 72rpx;
        background: #f8f8f8;
        border-radius: 12rpx;
        padding: 0 24rpx;
        font-size: 28rpx;
        box-sizing: border-box;
    }
    
    .tip {
        display: block;
        font-size: 24rpx;
        color: #999;
        margin-top: 12rpx;
    }
}

.type-list {
    display: flex;
    gap: 20rpx;
}

.type-item {
    padding: 16rpx 30rpx;
    background: #f8f8f8;
    border-radius: 30rpx;
    font-size: 26rpx;
    color: #666;
    border: 2rpx solid transparent;
    
    &.active {
        background: #c0fe95;
        color: #333;
        border-color: #c0fe95;
        font-weight: bold;
    }
}

.upload-area {
    width: 100%;
    height: 300rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    overflow: hidden;
    
    image {
        width: 100%;
        height: 100%;
    }
    
    .upload-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16rpx;
        
        text {
            font-size: 26rpx;
            color: #999;
        }
    }
}

.submit-btn {
    margin-top: 40rpx;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32rpx;
    background: linear-gradient(to right, #aaf69b, #d1ff7c) !important;
    color: #333 !important;
    font-weight: 500;
    border-radius: 44rpx;
    border: none;
}

.select-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    height: 72rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 0 24rpx;
    box-sizing: border-box;
    
    .select-value {
        font-size: 28rpx;
        color: #333;
        
        &.placeholder {
            color: #999;
        }
    }
}

.picker-popup {
    background: #fff;
    padding: 30rpx;
    
    .picker-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20rpx;
        
        .picker-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
    }
    
    .picker-list {
        max-height: 600rpx;
    }
    
    .picker-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24rpx 16rpx;
        border-bottom: 1rpx solid #f0f0f0;
        
        &:last-child {
            border-bottom: none;
        }
        
        &.active {
            background: #c0fe95;
        }
        
        text {
            font-size: 28rpx;
            color: #333;
        }
    }
    
    .picker-empty {
        text-align: center;
        padding: 60rpx 0;
        color: #999;
        font-size: 28rpx;
    }
}
</style>
