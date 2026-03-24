<template>
    <view class="runner-apply">
        <view class="form-section">
            <view class="form-item">
                <text class="label required">真实姓名</text>
                <input v-model="formData.real_name" placeholder="请输入真实姓名" />
            </view>
            <view class="form-item">
                <text class="label required">手机号码</text>
                <input v-model="formData.mobile" type="number" placeholder="请输入手机号码" maxlength="11" />
            </view>
            <view class="form-item">
                <text class="label required">所属学校</text>
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
                <text class="label required">性别</text>
                <view class="sex-select">
                    <view class="sex-item" :class="{ active: formData.sex === 1 }" @click="formData.sex = 1">
                        <u-icon name="man" size="24" :color="formData.sex === 1 ? '#1890ff' : '#999'"></u-icon>
                        <text>男</text>
                    </view>
                    <view class="sex-item" :class="{ active: formData.sex === 2 }" @click="formData.sex = 2">
                        <u-icon name="woman" size="24" :color="formData.sex === 2 ? '#ff4d88' : '#999'"></u-icon>
                        <text>女</text>
                    </view>
                </view>
            </view>
            <view class="form-item">
                <text class="label required">头像</text>
                <xy-upload v-model="formData.avatar" :maxCount="1" />
            </view>
            <view class="form-item">
                <text class="label required">学生证照片</text>
                <xy-upload v-model="formData.student_cert" :maxCount="1" />
                <text class="tip">请上传清晰的学生证照片，用于身份审核</text>
            </view>
        </view>

        <view class="agreement-section">
            <view class="checkbox" :class="{ checked: agreed }" @click="agreed = !agreed">
                <u-icon v-if="agreed" name="checkmark" size="16" color="#333"></u-icon>
            </view>
            <text class="agreement-text">
                我已阅读并同意
                <text class="link" @click.stop="viewAgreement">《接单员服务协议》</text>
            </text>
        </view>

        <button class="submit-btn" :disabled="!canSubmit" @click="submitApply">提交申请</button>

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
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { applyRunner } from '../../api/runner'
import { getSchoolList, getSchoolCampusList } from '../../api/xiaoyuan'
import xyUpload from '../../components/xy-upload.vue'

const formData = ref({
    real_name: '',
    mobile: '',
    school_id: 0,
    campus: '',
    avatar: '',
    student_cert: '',
    sex: 0
})

const schoolList = ref<any[]>([])
const campusList = ref<string[]>([])
const showSchoolPicker = ref(false)
const showCampusPicker = ref(false)
const agreed = ref(false)

const selectedSchoolName = computed(() => {
    const school = schoolList.value.find(s => s.id === formData.value.school_id)
    return school ? school.name : ''
})

const canSubmit = computed(() => {
    return formData.value.real_name && 
           formData.value.mobile && 
           formData.value.school_id &&
           formData.value.avatar &&
           formData.value.student_cert && 
           formData.value.sex > 0 &&
           agreed.value
})

onMounted(() => {
    loadSchoolList()
})

const loadSchoolList = async () => {
    try {
        const res: any = await getSchoolList()
        if (res.code === 1 && res.data) {
            schoolList.value = res.data.list || res.data || []
        }
    } catch (e: any) {
        uni.showToast({ title: e.msg || '加载学校列表失败', icon: 'none' })
    }
}

const onSchoolSelect = async (school: any) => {
    formData.value.school_id = school.id
    formData.value.campus = ''
    showSchoolPicker.value = false
    
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

const viewAgreement = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/runner/agreement'
    })
}

const submitApply = async () => {
    if (!formData.value.real_name) {
        uni.showToast({ title: '请输入真实姓名', icon: 'none' })
        return
    }
    if (!formData.value.mobile || formData.value.mobile.length !== 11) {
        uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
        return
    }
    if (!formData.value.school_id) {
        uni.showToast({ title: '请选择学校', icon: 'none' })
        return
    }
    if (!formData.value.student_cert) {
        uni.showToast({ title: '请上传学生证照片', icon: 'none' })
        return
    }
    if (!formData.value.avatar) {
        uni.showToast({ title: '请上传头像', icon: 'none' })
        return
    }
    if (!agreed.value) {
        uni.showToast({ title: '请同意服务协议', icon: 'none' })
        return
    }
    
    try {
        uni.showLoading({ title: '提交中...' })
        const res: any = await applyRunner(formData.value)
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '申请成功', icon: 'success' })
            setTimeout(() => {
                // 返回上一页并触发刷新
                uni.$emit('runnerApplySuccess')
                uni.navigateBack()
            }, 1500)
        } else {
            uni.showToast({ title: res.msg || '申请失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.msg || '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.runner-apply {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
    padding-bottom: 150rpx;
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
        font-size: 24rpx;
        color: #999;
        margin-top: 12rpx;
    }

    .sex-select {
        display: flex;
        gap: 24rpx;

        .sex-item {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12rpx;
            height: 80rpx;
            background: #f8f8f8;
            border-radius: 12rpx;
            border: 2rpx solid transparent;

            text { font-size: 28rpx; color: #666; }

            &.active {
                background: #f0fff0;
                border-color: #00c853;
                text { color: #333; font-weight: bold; }
            }
        }
    }
}

.upload-area {
    width: 160rpx;
    height: 160rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    overflow: hidden;
    
    &.cert {
        width: 100%;
        height: 300rpx;
    }
    
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
            font-size: 24rpx;
            color: #999;
        }
    }
}

.agreement-section {
    display: flex;
    align-items: center;
    padding: 24rpx;
    
    .checkbox {
        width: 40rpx;
        height: 40rpx;
        border: 2rpx solid #ddd;
        border-radius: 50%;
        margin-right: 16rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        
        &.checked {
            background: #c0fe95;
            border-color: #c0fe95;
            
            .iconfont {
                color: #333;
                font-size: 24rpx;
                font-weight: bold;
            }
        }
    }
    
    .agreement-text {
        font-size: 26rpx;
        color: #666;
        
        .link {
            color: #000000;
        }
    }
}

.submit-btn {
    position: fixed;
    bottom: 30rpx;
    left: 30rpx;
    right: 30rpx;
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
    
    &[disabled] {
        background: #ccc;
        color: #fff;
        box-shadow: none;
    }
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
        min-height: 300rpx;
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
