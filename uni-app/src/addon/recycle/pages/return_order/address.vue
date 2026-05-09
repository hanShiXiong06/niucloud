<template>
	<view class="min-h-screen bg-gray-50 p-4 pb-32">
		<!-- 头部标题 -->
		<view class="pt-4 mb-6 text-center">
			<view class="text-xl font-bold text-gray-800 mb-2">实名认证</view>
			<view class="text-sm text-gray-500">请提供您的真实信息，以便于我们处理打款</view>
		</view>

		<!-- 表单卡片 -->
		<view class="bg-white rounded-xl shadow-md p-6 mb-6">
			<!-- 姓名 -->
			<view class="mb-5 relative">
				<label class="block text-sm font-medium text-gray-700 mb-2">
					<text class="text-rose-500 mr-1">*</text>姓名
				</label>
				<input
					class="w-full py-3 px-4cut rounded-lg text-gray-800 border border-gray-200 focus:border-[#A9B7B7] focus:ring-1 focus:ring-[#A9B7B7] transition duration-200 ease-in-out"
					v-model.trim="formData.name"
					placeholder="请输入您的真实姓名"
					placeholder-class="text-gray-400"
					@input="validateName"
					@blur="validateName"
				/>
				<text v-if="errors.name" class="text-xs text-rose-500 mt-1.5 block absolute -bottom-4 left-1">{{ errors.name }}</text>
			</view>
			
			<!-- 手机号 -->
			<view class="mb-5 relative">
				<label class="block text-sm font-medium text-gray-700 mb-2">
					<text class="text-rose-500 mr-1">*</text>手机号
				</label>
				<input
					class="w-full py-3 px-4cut rounded-lg text-gray-800 border border-gray-200 focus:border-[#A9B7B7] focus:ring-1 focus:ring-[#A9B7B7] transition duration-200 ease-in-out"
					type="number"
					v-model.trim="formData.mobile"
					placeholder="请输入手机号码"
					placeholder-class="text-gray-400"
					maxlength="11"
					@input="validateMobile"
					@blur="validateMobile"
				/>
				<text v-if="errors.mobile" class="text-xs text-rose-500 mt-1.5 block absolute -bottom-4 left-1">{{ errors.mobile }}</text>
			</view>
			
			<!-- 身份证号 -->
			<view class="mb-3 relative">
				<label class="block text-sm font-medium text-gray-700 mb-2">
					<text class="text-rose-500 mr-1">*</text>身份证号
				</label>
				<input
					class="w-full py-3 px-4cut rounded-lg text-gray-800 border border-gray-200 focus:border-[#A9B7B7] focus:ring-1 focus:ring-[#A9B7B7] transition duration-200 ease-in-out"
					v-model.trim="formData.id_card"
					placeholder="请输入身份证号码"
					placeholder-class="text-gray-400"
					idcard
					maxlength="18"
					@input="validateIdCard"
					@blur="validateIdCard"
				/>
				<text v-if="errors.id_card" class="text-xs text-rose-500 mt-1.5 block absolute -bottom-4 left-1">{{ errors.id_card }}</text>
			</view>
		</view>

		<!-- 身份证上传 -->
		<view class="bg-white rounded-xl shadow-md p-6 mb-6">
			<view class="text-base font-medium text-gray-800 mb-2">身份证照片</view>
			<view class="text-xs text-gray-500 mb-4">请上传您的身份证照片，确保信息清晰可见，否则无法打款</view>
			
			<view class="flex flex-wrap">
				<!-- 已上传图片 -->
				<view v-if="formData.card_pic" class="relative mr-4 mb-4 group">
					<image 
						class="w-40 h-40 rounded-lg object-cover shadow-sm border border-gray-100 cursor-pointer"
						:src="img(formData.card_pic)" 
						mode="aspectFill"
						@tap="previewImage"
					/>
					<view 
						class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-black bg-opacity-60 flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity duration-200"
						@tap.stop="confirmDeleteImage"
					>
						<text class="text-white text-lg leading-none font-bold">×</text>
					</view>
					<view class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-lg pointer-events-none">
					    <text class="iconfont icon-eye text-white text-2xl"></text>
					</view>
				</view>
				
				<!-- 上传按钮 -->
				<view v-if="!formData.card_pic" 
					class="w-40 h-40 bg-gray-100 rounded-lg border-2 border-gray-300 border-dashed flex flex-col items-center justify-center cursor-pointer hover:bg-gray-200 transition-colors duration-200"
					@tap="chooseImage">
					<view class="mb-2 text-gray-400">
						<text class="iconfont icon-camera text-4xl"></text>
					</view>
					<text class="text-sm text-gray-600">上传照片</text>
					<text class="text-xs text-gray-400 mt-1">支持JPG/PNG</text>
				</view>
			</view>
			<text v-if="errors.card_pic" class="text-xs text-rose-500 mt-2 block">{{ errors.card_pic }}</text>
		</view>
		
		<!-- 提交按钮 -->
		<view class="fixed left-0 right-0 bottom-0 bg-white p-4 shadow-top z-10">
			<button 
				:class="[
					'w-full py-3 px-4 rounded-lg font-medium  text-[#fff] text-base shadow-md transition-all duration-300 ease-in-out',
					isFormValid && !loading ?  'bg-gray-300 cursor-not-allowed':'bg-[#6bb251] hover:opacity-90' 
				]"
				:disabled="!isFormValid || loading"
				:loading="loading"
				@tap="submitForm"
			>
				{{ loading ? '提交中...' : '提交信息' }}
			</button>
		</view>

		<!-- 加载遮罩层 -->
		<view v-if="uploading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
			<view class="bg-white rounded-lg p-6 flex flex-col items-center shadow-xl">
				<view class="w-8 h-8 mb-4 border-4 border-gray-200 border-t-[#A9B7B7] rounded-full animate-spin"></view>
				<text class="text-gray-700 text-sm">图片上传中...</text>
			</view>
		</view>
		
		<!-- 提交成功动画 -->
		<view v-if="showSuccess" 
		    class="fixed inset-0 bg-white bg-opacity-95 flex flex-col items-center justify-center z-50 transition-opacity duration-300 ease-in-out"
		    :class="showSuccess ? 'opacity-100' : 'opacity-0 pointer-events-none'">
			<view class="success-checkmark">
				<view class="check-icon">
					<span class="icon-line line-tip"></span>
					<span class="icon-line line-long"></span>
					<view class="icon-circle"></view>
					<view class="icon-fix"></view>
				</view>
			</view>
			<text class="mt-4 text-lg font-medium text-gray-700">提交成功</text>
		</view>
		
	</view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { img } from '@/utils/common';
import { uploadImage } from '@/app/api/system';
import { getRecycleUserAddressInfo, addRecycleUserAddress, editRecycleUserAddress } from '@/addon/recycle/api/return_order';

// API返回类型
interface ApiResponse<T = any> {
	code: number;
	data: T;
	msg?: string;
}

// 表单数据接口
interface FormData {
	id?: number;
	name: string;
	mobile: string;
	id_card: string;
	address: string; // 保留地址字段，即使UI上没有，API可能需要
	card_pic: string;
}

// 表单数据
const formData = reactive<FormData>({
	name: '',
	mobile: '',
	id_card: '',
	address: '', // 初始化地址字段
	card_pic: '',
});

// 表单错误信息
const errors = reactive({
	name: '',
	mobile: '',
	id_card: '',
	card_pic: ''
});

// 状态变量
const loading = ref(false); // 提交按钮loading
const uploading = ref(false); // 图片上传loading
const addressId = ref(0);
const showSuccess = ref(false);

// --- 表单验证 --- 
const validateName = (): boolean => {
	if (!formData.name) {
		errors.name = '请输入姓名';
		return false;
	} else if (formData.name.length < 2 || formData.name.length > 20) {
		errors.name = '姓名长度在2-20个字符之间';
		return false;
	}
	errors.name = '';
	return true;
};

const validateMobile = (): boolean => {
	if (!formData.mobile) {
		errors.mobile = '请输入手机号码';
		return false;
	} else if (!/^1[3-9]\d{9}$/.test(formData.mobile)) {
		errors.mobile = '手机号码格式不正确';
		return false;
	}
	errors.mobile = '';
	return true;
};

const validateIdCard = (): boolean => {
	if (!formData.id_card) {
		errors.id_card = '请输入身份证号码';
		return false;
	} else if (!/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/.test(formData.id_card)) {
		errors.id_card = '身份证号码格式不正确';
		return false;
	}
	errors.id_card = '';
	return true;
};

const validateCardPic = (): boolean => {
	if (!formData.card_pic) {
		errors.card_pic = '请上传身份证照片';
		return false;
	}
	errors.card_pic = '';
	return true;
};

// 验证所有字段
const validateForm = (): boolean => {
    const isNameValid = validateName();
    const isMobileValid = validateMobile();
    const isIdCardValid = validateIdCard();
    const isCardPicValid = validateCardPic();
    return isNameValid && isMobileValid && isIdCardValid && isCardPicValid;
};

// 计算属性：表单是否有效
const isFormValid = computed(() => {
    // 仅当所有字段都有值且错误消息为空时才认为有效
    return formData.name && formData.mobile && formData.id_card && formData.card_pic && 
           !errors.name && !errors.mobile && !errors.id_card && !errors.card_pic;
});

// --- 生命周期钩子 --- 
onMounted(async () => {
	loadAddressInfo();
});

// --- 数据加载 --- 
const loadAddressInfo = async () => {
	displayToast('正在加载数据...', 'loading');
	try {
		const res = await getRecycleUserAddressInfo() as ApiResponse<FormData>;
		uni.hideLoading(); // 隐藏loading
		console.log('获取地址信息响应:', res);
		
		if (res && res.code === 1 && res.data) {
			formData.name = res.data.name || '';
			formData.mobile = res.data.mobile || '';
			formData.id_card = res.data.id_card || '';
			formData.card_pic = res.data.card_pic || '';
			formData.address = res.data.address || ''; // 加载地址
			addressId.value = res.data.id || 0;
			// 初始验证一次
			validateForm();
		}
	} catch (error) {
		uni.hideLoading();
		console.error('获取地址信息失败', error);
		displayToast('数据加载失败，请稍后重试', 'error');
	}
};

// --- 图片处理 --- 
const chooseImage = () => {
	if (uploading.value) return;
	
	uni.showActionSheet({
		itemList: ['从相册选择', '拍照'],
		success: (res) => {
			const sourceType = res.tapIndex === 0 ? ['album'] : ['camera'];
			
			uni.chooseImage({
				count: 1,
				sizeType: ['compressed'],
				sourceType: sourceType,
				success: (chooseRes) => {
					if (chooseRes.tempFilePaths && chooseRes.tempFilePaths.length > 0) {
					    uploadCardImage(chooseRes.tempFilePaths[0]);
					}
				},
				fail: (err) => {
					console.log('选择图片失败', err);
					if (err.errMsg.indexOf('cancel') === -1) { // 用户主动取消时不提示
					    displayToast('选择图片失败', 'error');
					}
				}
			});
		}
	});
};

const uploadCardImage = async (filePath: string) => {
	uploading.value = true;
	console.log('开始上传图片', filePath);
	try {
		const res = await uploadImage({
			filePath: filePath,
			name: 'file' // 与后端接口匹配的字段名
		}) as ApiResponse<{ url: string }>; // 添加类型断言
		console.log('上传结果:', res);
		
		if (res.code === 1 && res.data && res.data.url) {
			formData.card_pic = res.data.url;
			displayToast('图片上传成功', 'success');
			validateCardPic(); // 上传成功后校验图片字段
		} else {
			displayToast(res.msg || '图片上传失败', 'error');
		}
	} catch (error) {
		console.error('上传图片错误', error);
		displayToast('图片上传失败，请检查网络连接', 'error');
	} finally {
		uploading.value = false;
	}
};

const confirmDeleteImage = () => {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除已上传的身份证照片吗？删除后需重新上传。',
		confirmText: '删除',
		confirmColor: '#EF4444', // 红色确认按钮
		cancelText: '取消',
		success: (res) => {
			if (res.confirm) {
				formData.card_pic = '';
				displayToast('照片已删除', 'success');
				validateCardPic(); // 删除后校验图片字段
			}
		}
	});
};

const previewImage = () => {
	if (formData.card_pic) {
		uni.previewImage({
			urls: [img(formData.card_pic)],
			current: 0,
			indicator: 'number',
			loop: false,
		});
	}
};

// --- 表单提交 --- 
const submitForm = async () => {
	if (loading.value) return;

	// 提交前再次完整校验
    if (!validateForm()) {
        displayToast('请检查并完善表单信息', 'warning');
        // 尝试滚动到第一个错误字段（uni-app可能不支持直接滚动到元素）
        uni.pageScrollTo({ scrollTop: 0, duration: 300 });
        return;
    }
	
	loading.value = true;
	
	try {
		const submitData: Partial<FormData> = {
			name: formData.name,
			mobile: formData.mobile,
			id_card: formData.id_card,
			card_pic: formData.card_pic,
			address: formData.address || '', // 确保地址有值
		};
		
		console.log('准备提交数据:', submitData);
		
		let res: ApiResponse;
		if (addressId.value) {
			console.log(`编辑地址 ID: ${addressId.value}`);
			res = await editRecycleUserAddress(addressId.value, submitData as FormData) as ApiResponse;
		} else {
			console.log('新增地址');
			res = await addRecycleUserAddress(submitData as FormData) as ApiResponse;
		}
		
		console.log('提交响应:', res);
		
		if (res && res.code === 1) {
			showSuccess.value = true; // 显示成功动画
			setTimeout(() => {
				showSuccess.value = false; // 动画结束后隐藏
				try {
					uni.navigateBack({
						delta: 1,
						fail: (err: any) => {
							console.error('返回上一页失败', err);
							uni.switchTab({ url: '/addon/recycle/pages/order/index' }); // 兜底跳转
						}
					});
				} catch (navError) {
					console.error('导航错误', navError);
					uni.switchTab({ url: '/addon/recycle/pages/order/index' }); // 兜底跳转
				}
			}, 1800); // 动画持续时间 + 短暂显示
		} else {
			displayToast(res?.msg || '提交失败，请稍后重试', 'error');
		}
	} catch (error) {
		console.error('提交表单时发生错误', error);
		displayToast('提交过程中发生错误，请稍后重试', 'error');
	} finally {
		loading.value = false;
	}
};

// --- 辅助函数 --- 
const displayToast = (message: string, type: 'success' | 'error' | 'warning' | 'info' | 'loading' = 'info', duration: number = 2000) => {
	if (!message) return;
	
	let iconType: 'success' | 'error' | 'loading' | 'none' = 'none';
	if (type === 'success') iconType = 'success';
	else if (type === 'error') iconType = 'error';
	else if (type === 'loading') iconType = 'loading';
	
	// loading需要手动隐藏
	if (type === 'loading') {
	    uni.showLoading({ title: message, mask: true });
	} else {
        // 先隐藏可能存在的loading
	    uni.hideLoading();
	    uni.showToast({
	    	title: message,
	    	icon: iconType,
	    	duration: duration,
	    	mask: false // 非loading时不建议加mask
	    });
	}
};

</script>

<style>
/* --- 字体图标 --- */


.iconfont {
	font-family: "iconfont" !important;
	font-size: 16px;
	font-style: normal;
	-webkit-font-smoothing: antialiased;
	-moz-osx-font-smoothing: grayscale;
}

.icon-camera:before {
	content: "\e6ac";
}
.icon-eye:before {
  content: "\e7f9";
}

/* --- 输入框 Placehoder 样式 -- */
.uni-input-placeholder,
input::placeholder {
  color: #a0aec0; /* 对应 Tailwind 的 gray-400 */
}

/* --- 页面背景 -- */
.address-page {
	background-color: #f8fafc; /* 对应 Tailwind 的 gray-50 */
}

/* --- 固定底部的阴影 --- */
.shadow-top {
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
}

/* --- 成功动画样式 (基于 Font Awesome Checkmark) --- */
.success-checkmark {
  width: 80px;
  height: 115px;
  margin: 0 auto;
}

.success-checkmark .check-icon {
  width: 80px;
  height: 80px;
  position: relative;
  border-radius: 50%;
  box-sizing: content-box;
  border: 4px solid #A9B7B7;
}

.success-checkmark .check-icon::before {
  top: 3px;
  left: -2px;
  width: 30px;
  transform-origin: 100% 50%;
  border-radius: 100px 0 0 100px;
}

.success-checkmark .check-icon::after {
  top: 0;
  left: 30px;
  width: 60px;
  transform-origin: 0 50%;
  border-radius: 0 100px 100px 0;
  animation: rotate-circle 4.25s ease-in;
}

.success-checkmark .check-icon::before, 
.success-checkmark .check-icon::after {
  content: '';
  height: 100px;
  position: absolute;
  background: #f8fafc; /* 遮罩用页面背景色 */
  transform: rotate(-45deg);
}

.success-checkmark .icon-line {
  height: 5px;
  background-color: #A9B7B7;
  display: block;
  border-radius: 2px;
  position: absolute;
  z-index: 10;
}

.success-checkmark .icon-line.line-tip {
  top: 46px;
  left: 14px;
  width: 25px;
  transform: rotate(45deg);
  animation: icon-line-tip 0.75s;
}

.success-checkmark .icon-line.line-long {
  top: 38px;
  right: 8px;
  width: 47px;
  transform: rotate(-45deg);
  animation: icon-line-long 0.75s;
}

.success-checkmark .icon-circle {
  top: -4px;
  left: -4px;
  z-index: 10;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  position: absolute;
  box-sizing: content-box;
  border: 4px solid rgba(169, 183, 183, .5); /* #A9B7B7 with opacity */
}

.success-checkmark .icon-fix {
  top: 8px;
  width: 5px;
  left: 26px;
  z-index: 1;
  height: 85px;
  position: absolute;
  transform: rotate(-45deg);
  background-color: #f8fafc; /* 遮罩用页面背景色 */
}

@keyframes rotate-circle {
  0% { transform: rotate(-45deg); }
  5% { transform: rotate(-45deg); }
  12% { transform: rotate(-405deg); }
  100% { transform: rotate(-405deg); }
}

@keyframes icon-line-tip {
  0% { width: 0; left: 1px; top: 19px; }
  54% { width: 0; left: 1px; top: 19px; }
  70% { width: 50px; left: -8px; top: 37px; }
  84% { width: 17px; left: 21px; top: 48px; }
  100% { width: 25px; left: 14px; top: 46px; }
}

@keyframes icon-line-long {
  0% { width: 0; right: 46px; top: 54px; }
  65% { width: 0; right: 46px; top: 54px; }
  84% { width: 55px; right: 0px; top: 35px; }
  100% { width: 47px; right: 8px; top: 38px; }
}

/* --- 禁用按钮样式 --- */
button[disabled] {
  opacity: 0.6;
}

/* 微调输入框在iOS上的默认样式 */
input {
  -webkit-appearance: none;
  appearance: none;
}
</style>
