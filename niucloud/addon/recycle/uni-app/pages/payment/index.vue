<template>
    <view class="payment-page">

        <!-- 实名认证信息卡片 (已认证状态可折叠) -->
        <view class="auth-card" :class="{'auth-card-certified': !!authInfoId}">
            <view class="auth-header" @tap="toggleAuthExpand">
                <view class="auth-left">
                    <view class="auth-status" v-if="!!authInfoId">
                        <up-icon name="checkmark-circle-fill" color="#10b981" size="20"></up-icon>
                        <text class="status-text">已完成实名认证</text>
                    </view>
                    <view class="auth-title" v-else>实名认证</view>
                    <view class="auth-subtitle" v-if="!authInfoId">完成实名认证后可添加收款方式</view>
                </view>
                <view class="auth-toggle" v-if="!!authInfoId">
                    <up-icon :name="isAuthExpanded ? 'arrow-up' : 'arrow-down'" size="20" color="#64748b"></up-icon>
                </view>
            </view>

            <!-- 已认证时的简要信息 -->
            <view class="auth-brief" v-if="!!authInfoId && !isAuthExpanded">
                <view class="brief-item">
                    <text class="brief-label">姓名</text>
                    <text class="brief-value">{{authFormData.name}}</text>
                </view>
                <view class="brief-item">
                    <text class="brief-label">手机</text>
                    <text class="brief-value">{{maskPhone(authFormData.mobile)}}</text>
                </view>
                <view class="brief-item address-item">
                    <text class="brief-label">地址</text>
                    <text class="brief-value address-value">{{authFormData.detail_address}}</text>
                    <view class="edit-address" @tap.stop="editAddress">
                        <up-icon name="edit-pen" size="16" color="#3b82f6"></up-icon>
                    </view>
                </view>
            </view>

            <!-- 详细表单 -->
            <view class="auth-form" v-if="!authInfoId || isAuthExpanded">
                <!-- 姓名 -->
                <view class="auth-form-item">
                    <label class="label">姓名</label>
                    <input
                        class="input"
                        :class="{'disabled-input': !!authInfoId}"
                        v-model.trim="authFormData.name"
                        placeholder="请输入您的真实姓名"
                        @input="validateAuthName()"
                        @blur="validateAuthName()"
                        :disabled="!!authInfoId"
                    />
                    <text v-if="authErrors.name" class="error-text">{{ authErrors.name }}</text>
                </view>

                <!-- 手机号 -->
                <view class="auth-form-item">
                    <label class="label">手机号</label>
                    <input
                        class="input"
                        :class="{'disabled-input': !!authInfoId}"
                        type="number"
                        v-model.trim="authFormData.mobile"
                        placeholder="请输入手机号码"
                        maxlength="11"
                        @input="validateAuthMobile()"
                        @blur="validateAuthMobile()"
                        :disabled="!!authInfoId"
                    />
                    <text v-if="authErrors.mobile" class="error-text">{{ authErrors.mobile }}</text>
                </view>

                <!-- 身份证号 -->
                <view class="auth-form-item">
                    <label class="label">身份证号</label>
                    <input
                        class="input"
                        :class="{'disabled-input': !!authInfoId}"
                        v-model.trim="authFormData.id_card"
                        placeholder="请输入身份证号码"
                        idcard
                        maxlength="18"
                        @input="validateAuthIdCard()"
                        @blur="validateAuthIdCard()"
                        :disabled="!!authInfoId"
                    />
                    <text v-if="authErrors.id_card" class="error-text">{{ authErrors.id_card }}</text>
                </view>
                
                <!-- 地区选择 -->
                <view class="auth-form-item">
                    <label class="label">退货地址</label>
                    <view class="input-like area-picker-display" @click="openAuthAreaPicker" >
                        <text v-if="authFormData.province_name">{{ authFormData.province_name }} {{ authFormData.city_name }} {{ authFormData.district_name }}</text>
                        <text v-else class="placeholder-text">请选择省/市/区</text>
                    </view>
                    <text v-if="authErrors.area" class="error-text">{{ authErrors.area }}</text>
                </view>

                <!-- 详细地址 -->
                <view class="auth-form-item"> 
                    <label class="label">详细地址</label>
                    <input
                        class="input"
                        :class="{'highlight-input': !!authInfoId}"
                        v-model.trim="authFormData.detail_address"
                        placeholder="请输入街道、楼牌号等"
                    />
                    <text v-if="authErrors.detail_address" class="error-text">{{ authErrors.detail_address }}</text>
                </view>

                <!-- 身份证照片 (仅在未认证时显示) -->
                <view class="auth-form-item" v-if="!authInfoId">
                    <label class="label">身份证照片</label>
                    <view class="id-card-upload">
                        <!-- 已上传图片 -->
                        <view v-if="authFormData.card_pic" class="id-card-preview-wrapper">
                            <image 
                                class="id-card-preview"
                                :src="img(authFormData.card_pic)" 
                                mode="aspectFill"
                                @tap="previewIdCardImage"
                            />
                            <view class="id-card-delete-btn" @tap.stop="confirmDeleteIdCardImage">
                                <up-icon name="close" size="12" color="#fff"></up-icon>
                            </view>
                            <view class="id-card-overlay">
                                <up-icon name="eye" size="20" color="#fff"></up-icon>
                            </view>
                        </view>
                        
                        <!-- 上传按钮 -->
                        <view v-if="!authFormData.card_pic"
                            class="id-card-upload-trigger" 
                            :class="{'is-loading': idCardUploading}"
                            @tap="chooseIdCardImage">
                            <view v-if="!idCardUploading" class="upload-placeholder">
                                <up-icon name="camera-fill" size="32" color="#94a3b8"></up-icon>
                                <text>上传照片</text>
                            </view>
                            <view v-else class="loading-indicator">
                                <view class="loading-spinner"></view>
                                <text>上传中...</text>
                            </view>
                        </view>
                    </view>
                    <text v-if="authErrors.card_pic" class="error-text">{{ authErrors.card_pic }}</text>
                </view>
                
                <!-- 提交按钮 -->
                <view class="auth-submit-area">
                    <button 
                        class="btn-submit-auth"
                        :loading="authLoading"
                        @tap="submitAuthForm"
                    >
                        {{ authLoading ? '提交中...' : (authInfoId ? '更新退货地址' : '提交认证信息') }}
                    </button>
                </view>
            </view>
        </view>

        <!-- 收款方式区域 (仅在认证后显示) -->
        <view class="payment-section" v-if="!!authInfoId">
            <view class="section-header">
                <view class="section-title">收款方式</view>
                <view class="section-action" @tap="openPopup">
                    <up-icon name="plus-circle" size="18" color="#3b82f6"></up-icon>
                    <text>添加</text>
                </view>
            </view>
            <!-- 添加提示信息 -->
            <view class="payment-tips" v-if="paymentList.length < 2">
                <up-icon name="info-circle" size="16" color="#f59e0b"></up-icon>
                <text>收款方式建议添加2种以上，防止出现封卡问题,导致不能收款</text>
            </view>
            <!-- 收款方式列表 -->
            <view class="payment-list" v-if="paymentList.length">
                <view class="payment-item" v-for="item in paymentList" :key="item.id">
                    <view class="payment-info">
                        <view class="payment-type-icon">
                            <up-icon :name="getPayTypeIcon(item.pay_type)" size="28" color="#3b82f6"></up-icon>
                        </view>
                        <view class="payment-details">
                            <view class="payment-type-name">
                                <text>{{ item.pay_type }}</text>
                                <text class="default-tag" v-if="item.is_default">默认</text>
                            </view>
                            <view class="account-info">{{ item.account }}</view>
                        </view>
                    </view>
                    <view class="payment-actions">
                        <view class="action-item" v-if="!item.is_default" @tap="handleSetDefault(item.id)">
                            <up-icon name="checkmark-circle" size="18" color="#3b82f6"></up-icon>
                        </view>
                        <view class="action-item" @tap="handleEdit(item)">
                            <up-icon name="edit-pen" size="18" color="#3b82f6"></up-icon>
                        </view>
                        <view class="action-item" @tap="handleDelete(item.id)">
                            <up-icon name="trash" size="18" color="#ef4444"></up-icon>
                        </view>
                    </view>
                </view>
            </view>
            
            <!-- 空状态 -->
            <view class="empty-payment" v-else>
                <up-icon name="wallet" size="48" color="#94a3b8"></up-icon>
                <text>请添加收款方式</text>
                <button class="btn-add-empty" @tap="openPopup">添加收款方式</button>
            </view>
        </view>

        <!-- 简单版添加/编辑弹窗 -->
        <uni-popup ref="popup" type="center" :mask-click="false">
            <view class="popup-content">
                <view class="popup-header">
                    <view class="popup-title">{{ isEdit ? '编辑收款方式' : '添加收款方式' }}</view>
                    <view class="popup-close" @tap="hidePopup">
                        <up-icon name="close" size="20" color="#94a3b8"></up-icon>
                    </view>
                </view>
                
                <view class="popup-body">
                    <view class="form-item">
                        <view class="label">收款方式</view>
                        <picker @change="handleTypeChange" :value="typeIndex" :range="payTypes">
                            <view class="picker">
                                <up-icon :name="getPayTypeIcon(payTypes[typeIndex])" size="20" color="#3b82f6"></up-icon>
                                <text>{{ payTypes[typeIndex] }}</text>
                                <up-icon name="arrow-down" size="16" color="#94a3b8" class="picker-arrow"></up-icon>
                            </view>
                        </picker>
                    </view>
                    
                    <!-- 收款账号，仅在支付宝和银行卡时显示 -->
                    <view class="form-item" v-if="formData.pay_type !== '微信'">
                        <view class="label">收款账号</view>
                        <input type="text" v-model="formData.account" placeholder="请输入收款账号" />
                    </view>
                    
                    <!-- 收款码，仅在微信和支付宝时显示 -->
                    <view class="form-item" v-if="formData.pay_type !== '银行卡'">
                        <view class="label">收款码</view>
                        <view class="upload-qrcode" @tap="chooseImage">
                            <view class="upload-content" :class="{ 'is-loading': isUploading }">
                                <image v-if="formData.qrcode_image" :src="img(formData.qrcode_image)" mode="aspectFit"></image>
                                <view v-else class="upload-placeholder">
                                    <up-icon name="camera" size="32" color="#94a3b8"></up-icon>
                                    <text>上传收款码</text>
                                </view>
                                <view class="loading-overlay" v-if="isUploading">
                                    <view class="loading-spinner"></view>
                                    <text class="loading-text">上传中...</text>
                                </view>
                            </view>
                        </view>
                    </view>
                    
                    <view class="form-item switch-item">
                        <view class="label">设为默认</view>
                        <switch 
                            :checked="!!formData.is_default" 
                            size="mini"
                            @change="(e: any) => formData.is_default = e.detail.value ? 1 : 0" 
                            color="#3b82f6"
                        />
                    </view>
                </view>
                
                <view class="popup-footer">
                    <button class="btn-cancel" @tap="hidePopup">取消</button>
                    <button class="btn-confirm" @tap="handleSubmit">确定</button>
                </view>
            </view>
        </uni-popup>

        <!-- 地区选择器 -->
        <area-select ref="areaRefAuth" @complete="handleAuthAreaSelectComplete"></area-select>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive, computed, watch } from 'vue'
import { getPaymentList, addPayment, updatePayment, deletePayment, setDefaultPayment, type PaymentInfo } from '../../api/payment'
import { getRecycleUserAddressInfo, addRecycleUserAddress, editRecycleUserAddress } from '@/addon/recycle/api/return_order'

import { img } from '@/utils/common'
import { uploadImage } from '@/app/api/system'
import areaSelect from '@/components/area-select/area-select.vue'

// --- API Response Interface (from address.vue) ---
interface ApiResponse<T = any> {
	code: number;
	data: T;
	msg?: string;
}

// --- Authentication Form Data Interface (from address.vue) ---
interface AuthFormData {
	id?: number;
	name: string;
	mobile: string;
	id_card: string;
	address: string; // Retain address field, API might need it
	card_pic: string;
	province_id?: number;
	city_id?: number;
	district_id?: number;
	province_name?: string;
	city_name?: string;
	district_name?: string;
	detail_address?: string; // For the detailed part of the address
}

const popup = ref<any>(null)
const paymentList = ref<PaymentInfo[]>([])
const isEdit = ref(false)
const editId = ref<number | null>(null)
const payTypes = ['微信', '支付宝', '银行卡']
const typeIndex = ref(0)
const formData = ref<Partial<PaymentInfo>>({
    pay_type: '微信',
    account: '',
    qrcode_image: '',
    is_default: 0
})
const isUploading = ref(false) // For payment QR code

// --- 认证信息展开/折叠状态 ---
const isAuthExpanded = ref(false)

// --- States for Authentication (from address.vue) ---
const authFormData = reactive<AuthFormData>({
	name: '',
	mobile: '',
	id_card: '',
	address: '', // Initialize address field
	card_pic: '',
	province_id: 0,
	city_id: 0,
	district_id: 0,
	province_name: '',
	city_name: '',
	district_name: '',
	detail_address: '',
});

const authErrors = reactive({
	name: '',
	mobile: '',
	id_card: '',
	card_pic: '',
	area: '', // For area picker error
	detail_address: '', // For detail address error
});

const authLoading = ref(false); // For auth form submission
const idCardUploading = ref(false); // For ID card image upload
const authInfoId = ref(0); // To store the ID of existing auth info
const showAuthSuccess = ref(false); // For auth submission success animation
const areaRefAuth = ref<any>(null); // Ref for auth area picker

onMounted(() => {
    loadPaymentList()
    loadAuthInfo(); // Load authentication info
    
    // 如果有认证信息，默认是折叠状态
    isAuthExpanded.value = !authInfoId.value;
})

// 切换认证信息的展开/折叠状态
const toggleAuthExpand = () => {
    if (authInfoId.value) { // 只有已认证时才能折叠
        isAuthExpanded.value = !isAuthExpanded.value;
    }
}

// 编辑地址
const editAddress = () => {
    isAuthExpanded.value = true;
}

// 掩码处理手机号
const maskPhone = (phone: string) => {
    if (!phone) return '';
    return phone.substring(0, 3) + '****' + phone.substring(7);
}

// --- Authentication Logic (adapted from address.vue) ---

// Load authentication info
const loadAuthInfo = async () => {
	uni.showLoading({ title: '加载认证信息...', mask: true });
	try {
		const res = await getRecycleUserAddressInfo() as ApiResponse<AuthFormData>;
		uni.hideLoading();
		if (res && res.code === 1 && res.data) {
			authFormData.name = res.data.name || '';
			authFormData.mobile = res.data.mobile || '';
			authFormData.id_card = res.data.id_card || '';
			authFormData.card_pic = res.data.card_pic || '';
			authFormData.address =  res.data.address || '';
			authFormData.province_id = res.data.province_id || 0;
			authFormData.city_id = res.data.city_id || 0;
			authFormData.district_id = res.data.district_id || 0;
			authFormData.province_name = res.data.province_name || '';
			authFormData.city_name = res.data.city_name || '';
			authFormData.district_name = res.data.district_name || '';
			authFormData.detail_address = res.data.address || '';
			authInfoId.value = res.data.id || 0;
			validateAuthForm(false); // Initial validation without showing errors everywhere
            
            // 如果已认证，默认折叠状态
            isAuthExpanded.value = !authInfoId.value;
		}
	} catch (error) {
		uni.hideLoading();
		console.error('获取认证信息失败', error);
		uni.showToast({ title: '认证信息加载失败', icon: 'none' });
	}
};

// Validation functions for authentication
const validateAuthName = (showError = true): boolean => {
	if (!authFormData.name && !authInfoId.value) { // Only validate if not saved or editing
		if (showError) authErrors.name = '请输入姓名';
		return false;
	} else if (authFormData.name && (authFormData.name.length < 2 || authFormData.name.length > 20) && !authInfoId.value) {
		if (showError) authErrors.name = '姓名长度在2-20个字符之间';
		return false;
	}
	authErrors.name = '';
	return true;
};

const validateAuthMobile = (showError = true): boolean => {
	if (!authFormData.mobile && !authInfoId.value) {
		if (showError) authErrors.mobile = '请输入手机号码';
		return false;
	} else if (authFormData.mobile && !/^1[3-9]\d{9}$/.test(authFormData.mobile) && !authInfoId.value) {
		if (showError) authErrors.mobile = '手机号码格式不正确';
		return false;
	}
	authErrors.mobile = '';
	return true;
};

const validateAuthIdCard = (showError = true): boolean => {
	if (!authFormData.id_card && !authInfoId.value) {
		if (showError) authErrors.id_card = '请输入身份证号码';
		return false;
	} else if (authFormData.id_card && !/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/.test(authFormData.id_card) && !authInfoId.value) {
		if (showError) authErrors.id_card = '身份证号码格式不正确';
		return false;
	}
	authErrors.id_card = '';
	return true;
};

const validateAuthCardPic = (showError = true): boolean => {
	if (!authFormData.card_pic && !authInfoId.value) {
		if (showError) authErrors.card_pic = '请上传身份证照片';
		return false;
	}
	authErrors.card_pic = '';
	return true;
};

// Validate Auth Area
const validateAuthArea = (showError = true): boolean => {
    if (!authFormData.province_id) { // 不管是否已认证，地区都是必填的
        if (showError) authErrors.area = '请选择所在地区';
        return false;
    }
    authErrors.area = '';
    return true;
};

// Validate Auth Detail Address
const validateAuthDetailAddress = (showError = true): boolean => {
	if (!authFormData.detail_address && !authInfoId.value) {
		if (showError) authErrors.detail_address = '请输入详细地址';
		return false;
	}
	authErrors.detail_address = '';
	return true;
};

const validateAuthForm = (showError = true): boolean => {
    const isNameValid = validateAuthName(showError);
    const isMobileValid = validateAuthMobile(showError);
    const isIdCardValid = validateAuthIdCard(showError);
    const isCardPicValid = validateAuthCardPic(showError);
    const isAreaValid = validateAuthArea(showError);
    const isDetailAddressValid = validateAuthDetailAddress(showError);
    return isNameValid && isMobileValid && isIdCardValid && isCardPicValid && isAreaValid && isDetailAddressValid;
};

const isAuthFormValid = computed(() => {
    return authFormData.name && authFormData.mobile && authFormData.id_card && authFormData.card_pic &&
           authFormData.province_id && authFormData.detail_address && 
           !authErrors.name && !authErrors.mobile && !authErrors.id_card && !authErrors.card_pic &&
           !authErrors.area && !authErrors.detail_address;
});

// ID Card Image Handling
const chooseIdCardImage = () => {
	if (idCardUploading.value) return;
	
	uni.showActionSheet({
		itemList: ['从相册选择', '拍照'],
		success: (res) => {
			const sourceType = res.tapIndex === 0 ? ['album'] : ['camera'];
			uni.chooseImage({
				count: 1,
				sizeType: ['compressed'],
				sourceType: sourceType as ('album' | 'camera')[], // Type assertion
				success: (chooseRes) => {
					if (chooseRes.tempFilePaths && chooseRes.tempFilePaths.length > 0) {
					    uploadIdCardPhoto(chooseRes.tempFilePaths[0]);
					}
				},
				fail: (err) => {
					if (err.errMsg && err.errMsg.indexOf('cancel') === -1) {
					    uni.showToast({ title: '选择图片失败', icon: 'none' });
					}
				}
			});
		}
	});
};

const uploadIdCardPhoto = async (filePath: string) => {
	idCardUploading.value = true;
	try {
		const res = await uploadImage({ // Uses the existing uploadImage from payment
			filePath: filePath,
			name: 'file' ,
            cate_id:0
		}) as ApiResponse<{ url: string }>;
		
		if (res.code === 1 && res.data && res.data.url) {
			authFormData.card_pic = res.data.url;
			uni.showToast({ title: '身份证上传成功', icon: 'success' });
			validateAuthCardPic();
		} else {
			uni.showToast({ title: res.msg || '身份证上传失败', icon: 'none' });
		}
	} catch (error) {
		uni.showToast({ title: '身份证上传失败', icon: 'none' });
	} finally {
		idCardUploading.value = false;
	}
};

const confirmDeleteIdCardImage = () => {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除已上传的身份证照片吗？',
		confirmText: '删除',
		confirmColor: '#EF4444',
		cancelText: '取消',
		success: (res) => {
			if (res.confirm) {
				authFormData.card_pic = '';
				uni.showToast({ title: '身份证照片已删除', icon: 'success' });
				validateAuthCardPic();
			}
		}
	});
};

const previewIdCardImage = () => {
	if (authFormData.card_pic) {
		uni.previewImage({
			urls: [img(authFormData.card_pic)],
			current: 0,
			indicator: 'number',
			loop: false,
		});
	}
};

// Submit Authentication Form
const submitAuthForm = async () => {
    if (authLoading.value) return;

    if (authInfoId.value) {
        // 如果已经认证，只更新地址
        if (!validateAuthDetailAddress()) {
            uni.showToast({ title: '请检查地址信息', icon: 'none' });
            return;
        }
        
        authLoading.value = true;
        uni.showLoading({ title: '更新地址信息...', mask: true });
        const address = authFormData.province_name + authFormData.city_name + authFormData.district_name + authFormData.detail_address;
        
        try {
            let res = await editRecycleUserAddress(authInfoId.value, {
                ...authFormData,
                address:address,
            } as AuthFormData) as ApiResponse;
            
            uni.hideLoading();
            if (res && res.code === 1) {
                uni.showToast({ title: '地址更新成功!', icon: 'success' });
                loadAuthInfo(); // 刷新信息
                isAuthExpanded.value = false; // 折叠表单
            } else {
                uni.showToast({ title: (res && res.msg) || '地址更新失败', icon: 'none' });
            }
        } catch (error) {
            uni.hideLoading();
            uni.showToast({ title: '地址更新失败', icon: 'none' });
        } finally {
            authLoading.value = false;
        }
    } else {
        // 新增认证
        if (!validateAuthForm(true)) {
            uni.showToast({ title: '请完善认证信息', icon: 'none' });
            return;
        }
        
        authLoading.value = true;
        uni.showLoading({ title: '提交认证信息...', mask: true });
        
        const address = authFormData.province_name + authFormData.city_name + authFormData.district_name + authFormData.detail_address;
        try {
            const res = await addRecycleUserAddress({
                name: authFormData.name,
                mobile: authFormData.mobile,
                id_card: authFormData.id_card,
                card_pic: authFormData.card_pic,
                address: address,
            } as AuthFormData) as ApiResponse<{id: number}>;
            
            uni.hideLoading();
            if (res && res.code === 1) {
                uni.showToast({ title: '认证成功!', icon: 'success' });
                if (res.data && res.data.id) {
                    authInfoId.value = res.data.id;
                }
                loadAuthInfo(); // 刷新认证状态
            } else {
                uni.showToast({ title: (res && res.msg) || '认证失败', icon: 'none' });
            }
        } catch (error) {
            uni.hideLoading();
            uni.showToast({ title: '认证失败', icon: 'none' });
        } finally {
            authLoading.value = false;
        }
    }
};

// 获取收款方式列表
const loadPaymentList = async () => {
    try {
        const res = await getPaymentList() as ApiResponse<PaymentInfo[]>;
        if (res.code === 1 && res.data) {
            paymentList.value = Array.isArray(res.data) ? res.data : []
        } else {
            uni.showToast({ title: (res && res.msg) || '获取列表失败', icon: 'none' })
        }
    } catch (error) {
        uni.showToast({ title: '获取列表失败', icon: 'none' })
    }
}

// 处理收款方式选择
const handleTypeChange = (e: any) => {
    typeIndex.value = e.detail.value
    formData.value.pay_type = payTypes[typeIndex.value]
}

// 选择图片
const chooseImage = () => {
    uni.chooseImage({
        count: 1,
        success: (res) => {
            uploadPaymentImage(res.tempFilePaths[0])
        },
        fail: () => {
            uni.showToast({ title: '选择图片失败', icon: 'none' })
        }
    })
}

// 上传图片
const uploadPaymentImage = async (filePath: string) => {
    isUploading.value = true
    try {
        const res = await uploadImage({
            filePath: filePath,
            name: 'file'
        }) as ApiResponse<{ url: string }>;

        if (res.code === 1) {
            if (res.data && res.data.url) {
                formData.value.qrcode_image = res.data.url
            } else {
                uni.showToast({ title: '上传响应数据格式错误', icon: 'none' });
                console.error('Upload response data format error:', res);
            }
        } else {
            uni.showToast({ title: (res && res.msg) || '上传失败', icon: 'none' })
        }
    } catch (error) {
        uni.showToast({
            title: '上传失败',
            icon: 'none'
        })
    } finally {
        isUploading.value = false
    }
}

// 设置默认收款方式
const handleSetDefault = async (id: number) => {
    try {
        const res = await setDefaultPayment(id) as ApiResponse;
        if (res.code === 1) {
            uni.showToast({ title: '设置成功', icon: 'success' })
            loadPaymentList()
        } else {
            uni.showToast({ title: (res && res.msg) || '设置失败', icon: 'none' })
        }
    } catch (error) {
        uni.showToast({ title: '设置失败', icon: 'none' })
    }
}

// 编辑收款方式
const handleEdit = (item: PaymentInfo) => {
    isEdit.value = true
    editId.value = item.id
    formData.value = { ...item }
    typeIndex.value = payTypes.indexOf(item.pay_type)
    popup.value?.open()
}

// 删除收款方式
const handleDelete = (id: number) => {
    uni.showModal({
        title: '提示',
        content: '确定要删除该收款方式吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const res = await deletePayment(id) as ApiResponse;
                    if (res.code === 1) {
                        uni.showToast({ title: '删除成功', icon: 'success' })
                        loadPaymentList()
                    } else {
                        uni.showToast({ title: (res && res.msg) || '删除失败', icon: 'none' })
                    }
                } catch (error) {
                    uni.showToast({ title: '删除失败', icon: 'none' })
                }
            }
        }
    })
}

// 提交表单
const handleSubmit = async () => {
    // 根据支付类型验证不同的必填字段
    if (formData.value.pay_type !== '微信' && !formData.value.account) {
        uni.showToast({ title: '请输入收款账号', icon: 'none' })
        return
    }

    if (formData.value.pay_type !== '银行卡' && !formData.value.qrcode_image) {
        uni.showToast({ title: '请上传收款码', icon: 'none' })
        return
    }
    
    uni.showLoading({ title: '保存中...', mask: true })
    
    try {
        if (isEdit.value && editId.value) {
            // 编辑
            const res = await updatePayment(editId.value, formData.value) as ApiResponse
            if (res.code === 1) {
                uni.hideLoading()
                uni.showToast({ title: '更新成功', icon: 'success' })
                loadPaymentList()
                hidePopup()
                resetForm()
            } else {
                uni.hideLoading()
                uni.showToast({ title: (res && res.msg) || '更新失败', icon: 'none' })
            }
        } else {
            // 添加
            const res = await addPayment(formData.value) as ApiResponse
            if (res.code === 1) {
                uni.hideLoading()
                uni.showToast({ title: '添加成功', icon: 'success' })
                loadPaymentList()
                hidePopup()
                resetForm()
            } else {
                uni.hideLoading()
                uni.showToast({ title: (res && res.msg) || '添加失败', icon: 'none' })
            }
        }
    } catch (error) {
        uni.hideLoading()
        uni.showToast({ title: '操作失败', icon: 'none' })
    }
}

// 隐藏弹窗
const hidePopup = () => {
    popup.value?.close()
    resetForm()
}

// 重置表单
const resetForm = () => {
    isEdit.value = false
    editId.value = null
    formData.value = {
        pay_type: '微信',
        account: '',
        qrcode_image: '',
        is_default: 0
    }
    typeIndex.value = 0
}

// 获取支付方式图标 
const getPayTypeIcon = (type: string) => {
    switch (type) {
        case '微信':
            return 'weixin-fill'
        case '支付宝':
            return 'zhifubao'
        case '银行卡':
            return 'rmb'
        default:
            return 'rmb-circle'
    }
}

// --- Auth Area Picker --- 


const handleAuthAreaSelectComplete = (event: any) => {
	authFormData.province_id = event.province.id || 0;
	authFormData.city_id = event.city.id || 0;
	authFormData.district_id = event.district.id || 0;
	authFormData.province_name = event.province.name || '';
	authFormData.city_name = event.city.name || '';
	authFormData.district_name = event.district.name || '';
    // 清空地址
    authFormData.detail_address = '';

	validateAuthArea(); // Validate after selection

};

// Watch for changes in address parts and update the full authFormData.address
watch(
	() => [
		authFormData.province_name,
		authFormData.city_name,
		authFormData.district_name,
		authFormData.detail_address
	],
	(newValues) => {
		const [province, city, district, detail] = newValues;
		let fullAddress = '';
		if (province) fullAddress += province;
		if (city) fullAddress += ` ${city}`;
		if (district) fullAddress += ` ${district}`;
		if (detail) fullAddress += ` ${detail}`;
		authFormData.address = fullAddress.trim();
	},
	{ deep: true }
);

// 打开收款方式弹窗
const openPopup = () => {
    resetForm()
    popup.value?.open()
}

// 地区选择器
const openAuthAreaPicker = () => {
    if(areaRefAuth.value) areaRefAuth.value.open();
};
</script>

<style lang="scss">
.payment-page {
    padding: 30rpx;
    background: #f8fafc;
    min-height: 100vh;
}

/* 实名认证卡片（新样式） */
.auth-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 30rpx;
    box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.03);
    border: 1rpx solid #e2e8f0;
    transition: all 0.3s ease;
}

.auth-card-certified {
    border-color: #86efac;
}

.auth-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20rpx;
}

.auth-left {
    display: flex;
    flex-direction: column;
}

.auth-status {
    display: flex;
    align-items: center;
    gap: 8rpx;
}

.status-text {
    font-size: 32rpx;
    font-weight: 600;
    color: #10b981;
}

.auth-title {
    font-size: 36rpx;
    font-weight: 600;
    color: #1e293b;
}

.auth-subtitle {
    font-size: 26rpx;
    color: #64748b;
    margin-top: 4rpx;
}

/* 简要信息区域 */
.auth-brief {
    padding: 20rpx 0 10rpx;
    border-top: 1rpx dashed #e2e8f0;
}

.brief-item {
    display: flex;
    align-items: center;
    margin-bottom: 16rpx;
}

.brief-label {
    width: 100rpx;
    font-size: 28rpx;
    color: #64748b;
}

.brief-value {
    flex: 1;
    font-size: 28rpx;
    color: #1e293b;
    font-weight: 500;
}

.address-item {
    position: relative;
}

.address-value {
    margin-right: 60rpx;
}

.edit-address {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 44rpx;
    height: 44rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eff6ff;
    border-radius: 8rpx;
}

/* 收款方式区域 */
.payment-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.03);
    border: 1rpx solid #e2e8f0;
    /* 收款方式提示样式 */
.payment-tips {
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 16rpx;
    margin-bottom: 20rpx;
    background: #fffbeb;
    border-radius: 8rpx;
    border: 1rpx solid #fcd34d;
}

.payment-tips text {
    font-size: 24rpx;
    color: #b45309;
    line-height: 1.4;
}
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30rpx;
}

.section-title {
    font-size: 32rpx;
    font-weight: 600;
    color: #1e293b;
}

.section-action {
    display: flex;
    align-items: center;
    gap: 6rpx;
    padding: 8rpx 16rpx;
    font-size: 26rpx;
    color: #3b82f6;
    background: #eff6ff;
    border-radius: 8rpx;
}

/* 收款方式列表 */
.payment-list {
    .payment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24rpx 0;
        border-bottom: 1rpx solid #f1f5f9;
        
        &:last-child {
            border-bottom: none;
        }
        
        .payment-info {
            display: flex;
            align-items: center;
            gap: 16rpx;
            
            .payment-type-icon {
                width: 80rpx;
                height: 80rpx;
                border-radius: 12rpx;
                background: #eff6ff;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .payment-details {
                display: flex;
                flex-direction: column;
                gap: 8rpx;
                
                .payment-type-name {
                    display: flex;
                    align-items: center;
                    gap: 12rpx;
                    font-size: 30rpx;
                    font-weight: 600;
                    color: #1e293b;
                }
                
                .default-tag {
                    font-size: 20rpx;
                    padding: 2rpx 10rpx;
                    background: #3b82f6;
                    color: #fff;
                    border-radius: 20rpx;
                    font-weight: normal;
                }
                
                .account-info {
                    font-size: 26rpx;
                    color: #64748b;
                }
            }
        }
        
        .payment-actions {
            display: flex;
            gap: 16rpx;
            
            .action-item {
                width: 44rpx;
                height: 44rpx;
                border-radius: 8rpx;
                background: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: center;
                
                &:active {
                    background: #f1f5f9;
                }
            }
        }
    }
}

/* 空状态 */
.empty-payment {
    padding: 60rpx 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20rpx;
    
    text {
        color: #94a3b8;
        font-size: 28rpx;
    }
    
    .btn-add-empty {
        margin-top: 20rpx;
        background: #3b82f6;
        color: #fff;
        font-size: 28rpx;
        padding: 16rpx 40rpx;
        border-radius: 10rpx;
        line-height: 1.5;
    }
}

/* 弹窗样式 */
.popup-content {
    background: #fff;
    border-radius: 16rpx;
    width: 650rpx;
    overflow: hidden;
}

.popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30rpx;
    border-bottom: 1rpx solid #f1f5f9;
}

.popup-title {
    font-size: 32rpx;
    font-weight: 600;
    color: #1e293b;
}

.popup-close {
    padding: 8rpx;
}

.popup-body {
    padding: 30rpx;
}

.form-item {
    margin-bottom: 30rpx;
    
    .label {
        font-size: 28rpx;
        color: #475569;
        margin-bottom: 12rpx;
        font-weight: 500;
    }
    
    input, .picker {
        width: 100%;
        height: 80rpx;
        border: 1rpx solid #e2e8f0;
        border-radius: 8rpx;
        padding: 0 20rpx;
        box-sizing: border-box;
        font-size: 28rpx;
        background: #f8fafc;
        display: flex;
        align-items: center;
    }
    
    .picker {
        justify-content: space-between;
    }
    
    .upload-qrcode {
        width: 200rpx;
        height: 200rpx;
        border: 1rpx dashed #cbd5e1;
        border-radius: 8rpx;
        overflow: hidden;
        position: relative;
        
        .upload-content {
            width: 100%;
            height: 100%;
            
            image {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .upload-placeholder {
                width: 100%;
                height: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 10rpx;
                color: #94a3b8;
                font-size: 24rpx;
            }
        }
        
        .loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.85);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16rpx;
        }
        
        .loading-spinner {
            width: 40rpx;
            height: 40rpx;
            border: 3rpx solid #f3f3f3;
            border-top: 3rpx solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    }
    
    &.switch-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
}

.popup-footer {
    display: flex;
    border-top: 1rpx solid #f1f5f9;
    
    button {
        flex: 1;
        height: 100rpx;
        font-size: 32rpx;
        font-weight: 500;
        border-radius: 0;
        
        &.btn-cancel {
            background: #f8fafc;
            color: #64748b;
        }
        
        &.btn-confirm {
            background: #3b82f6;
            color: #fff;
        }
    }
}

/* 表单高亮（可编辑的地址信息） */
.highlight-input {
    background: #eff6ff !important;
    border-color: #bfdbfe !important;
}

/* 通用样式 */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 原来的身份证照片上传区样式 */
.id-card-upload {
    display: flex;
}

.id-card-preview-wrapper {
    position: relative;
    margin-right: 20rpx;
    width: 180rpx;
    height: 180rpx;
    cursor: pointer;
    border-radius: 8rpx;
    overflow: hidden;
}

.id-card-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border: 1rpx solid #e2e8f0;
    border-radius: 8rpx;
}

.id-card-delete-btn {
    position: absolute;
    top: 10rpx;
    right: 10rpx;
    width: 36rpx;
    height: 36rpx;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1;
}

.id-card-overlay {
    position: absolute;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.2s ease;
    border-radius: 8rpx;
}

.id-card-preview-wrapper:active .id-card-overlay {
    opacity: 1;
}

.id-card-upload-trigger {
    width: 180rpx;
    height: 180rpx;
    border: 1rpx dashed #cbd5e1;
    border-radius: 8rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.id-card-upload-trigger:active {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.id-card-upload-trigger .upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10rpx;
    font-size: 24rpx;
    color: #94a3b8;
}

.id-card-upload-trigger.is-loading .upload-placeholder {
    opacity: 0.5;
}

.id-card-upload-trigger .loading-indicator {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16rpx;
    border-radius: 8rpx;
    z-index: 1;
}

.loading-indicator .loading-spinner {
    width: 40rpx;
    height: 40rpx;
    border: 3rpx solid #f3f3f3;
    border-top: 3rpx solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loading-indicator .loading-text {
    font-size: 24rpx;
    color: #3b82f6;
    font-weight: 500;
}

.auth-submit-area {
    margin-top: 40rpx;
}

.btn-submit-auth {
    width: 100%;
    height: 90rpx;
    line-height: 90rpx;
    background: #10b981;
    color: #fff;
    font-size: 32rpx;
    border-radius: 8rpx;
    font-weight: 500;
    border: none;
}

.btn-submit-auth.is-disabled {
    background: #d1d5db;
    opacity: 0.7;
}

.auth-form-item {
    margin-bottom: 30rpx;
}

.auth-form-item .label {
    font-size: 28rpx;
    color: #475569;
    margin-bottom: 12rpx;
    font-weight: 500;
}

.auth-form-item .input,
.input-like {
    width: 100%;
    height: 80rpx;
    border: 1rpx solid #e2e8f0;
    border-radius: 8rpx;
    padding: 0 20rpx;
    font-size: 28rpx;
    background: #f8fafc;
    box-sizing: border-box;
    color: #1e293b;
    transition: all 0.3s ease;
}

.auth-form-item .input:focus,
.input-like:focus {
    border-color: #3b82f6;
    background: #fff;
}

.input-like {
    display: flex;
    align-items: center;
}

.input-like .placeholder-text {
    color: #94a3b8;
}

.disabled-input {
    background-color: #f1f5f9 !important;
    color: #64748b !important;
    cursor: not-allowed;
    border-color: #e2e8f0 !important;
}

.error-text {
    font-size: 24rpx;
    color: #ef4444;
    margin-top: 8rpx;
}
</style>