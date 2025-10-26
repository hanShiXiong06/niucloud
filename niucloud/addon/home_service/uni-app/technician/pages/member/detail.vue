<template>
	<view class="bg-[var(--page-bg-color)] overflow-hidden position-size" v-if="!loading"
		:style="{backgroundImage:'url(' + img('addon/home_service/store/technician/detail-bg.png') + ')'}">
		<view :style="themeColor()">
			<u-navbar :title="t('technicianDetail')" :autoBack="true" leftIconSize="0"
				:bgColor="scrollTop > 50 ? '#ffffff' : 'transparent'" placeholder>
			</u-navbar>

			<!-- 师傅基本信息区 - 正确实现背景图片 -->
			<view class="relative pt-[15rpx] px-[25rpx]">
				<view class="relative z-10 flex items-start">
					<!-- 师傅照片 -->
					<u--image :src="img(technician?.headimg || '')" width="210rpx" height="210rpx" radius="100rpx"
						mode="aspectFill">
						<template #error>
							<image :src="img('static/resource/images/default_headimg.png')"
								class="w-[210rpx] h-[210rpx] rounded-full border-2 border-white" mode="aspectFill">
							</image>
						</template>
					</u--image>

					<!-- 师傅基本信息 -->
					<view class="ml-[15px] flex-1">
						<!-- 姓名与状态 -->
						<view class="flex items-center mb-[10rpx]">
							<text
								class="text-[30rpx] font-bold text-[var(--text-color-primary)] max-w-[170px] leading-5">{{technician?.real_name || ''}}</text>
							<text
								:class="technician?.status == '1' ? 'text-xs bg-green-500 text-white' : 'text-xs bg-gray-400 text-white'"
								class="px-2.5 py-0.5 rounded-[5rpx] ml-[15rpx]">
								{{ technician?.status == '1' ? t('working') : t('resting') }}
							</text>
						</view>

						<!-- 从业信息与地址 -->
						<text
							class="text-xs text-gray-500 mb-[10rpx] block">{{t('phoneNumber')}}{{technician?.mobile || ''}}</text>
						<text class="text-xs text-gray-500 block">{{technician?.full_address || ''}}</text>
						<!-- 服务类型标签 -->
						<view class="relative z-10 flex flex-wrap"
							v-if="technician?.category_name && technician?.category_name.length">
							<text v-for="(skill, idx) in technician?.category_name"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
								v-show="idx < 2">{{ skill.category_name }}</text>
							<text v-if="technician?.category_name?.length >= 3 && !showMoreTag"
								@click="showMoreTag = true"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]">...</text>
							<text v-for="(skill, idx) in technician.category_name"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
								v-show="idx >= 2 && showMoreTag">{{ skill.category_name }}</text>
							<text v-if="showMoreTag" @click="showMoreTag = false"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx] flex items-center leading-1"><text
									class="iconfont iconshangV6xx-1"></text></text>
						</view>
					</view>
				</view>

			</view>

			<!-- 工作数据统计区 -->
			<view class="relative bg-white mt-[20rpx] p-4 mx-[24rpx] rounded-lg" style="
				background: linear-gradient( 180deg, #FFFFFF 0%, transparent 100%);
			">
				<view class="relative z-10 flex justify-center">
					<!-- 四个数据卡片水平排列 -->
					<view class="w-[80px] text-center">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician?.order_count || 0 }} <text
								class="text-[20rpx]">单</text></text>
						<text class="text-xs text-gray-500">{{t('completedOrders')}}</text>
					</view>
					<view class="w-[80px] text-center mx-[10px]">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician?.abnormal_count || 0 }}</text>
						<text class="text-xs text-gray-500">{{t('abnormalOrders')}}</text>
					</view>
					<view class="w-[80px] text-center mx-[10px]">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician?.refund_count || 0 }}</text>
						<text class="text-xs text-gray-500">{{t('refundOrders')}}</text>
					</view>
					<view class="w-[80px] text-center">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician?.positive_rate || '0%' }}</text>
						<text class="text-xs text-gray-500">{{t('positiveRate')}}</text>
					</view>
				</view>
			</view>
			<view class="px-[24rpx] pb-[30rpx] bg-[#f6f6f6] pt-[15rpx]">
				<!-- 个人简介区 -->
				<view class="bg-white p-4 rounded-lg">
					<!-- 个人简介标题 -->
					<view class="mb-[20rpx] relative flex items-center justify-between">
						<text
							class="text-[32rpx] font-bold text-[var(--text-color-primary)]">{{t('personalProfile')}}</text>
						<!-- 背景条图片 -->
						<view class="absolute bottom-[-10rpx] left-0 ">
							<image class="w-[50rpx] h-[20rpx] block"
								:src="img('addon/home_service/store/technician/background_bar.png')" mode="widthFix">
							</image>
						</view>
						<!-- 编辑按钮 -->
						<text class="text-[#2563EB] text-sm" @click="handleEdit">{{ t('edit') }}</text>
					</view>
					<!-- 基本信息项 -->
					<!-- 基本信息项 -->
					<view class="personal-info-container">
						<!-- 师傅基本信息 -->
						<view class="mb-[15px]">
							<view class="py-[10px] border-b border-gray-100">
								<text class="text-[30rpx] text-gray-700 block mb-[5px]">{{t('name')}}</text>
								<text class="text-[26rpx] text-gray-900">{{technician?.real_name || ''}}</text>
							</view>

							<view class="py-[10px] border-b border-gray-100">
								<text class="text-[30rpx] text-gray-700 block mb-[5px]">{{t('phone')}}</text>
								<text class="text-[26rpx] text-gray-900">{{technician?.mobile || ''}}</text>
							</view>
						</view>
						<!-- 资质证书 - 修改为图片展示 -->
						<view class="mb-[25px]">
							<text
								class="text-[30rpx] text-gray-700 block mb-[15px] block">{{t('qualificationCertificate')}}</text>
							<!-- 资质证书图片展示区域 -->
							<view class="grid grid-cols-4 gap-4">
								<view v-for="(item,index) in technician.certificate" :key="index">
									<u--image :src="img(item)" width="130rpx" height="130rpx" radius="10rpx">
										<view slot="error" style="font-size: 24rpx;">{{t('loadFailed')}}</view>
									</u--image>
								</view>
							</view>
						</view>

						<!-- 自我介绍 -->
						<!-- <view>
							<text
								class="text-[30rpx] text-gray-700 block mb-[15px] block">{{t('selfIntroduction')}}</text>
							<text
								class="text-sm whitespace-pre-line">{{ technician.intro || t('noIntroduction') }}</text>
						</view> -->
					</view>
				</view>

				<!-- 技能专长区域 -->
				<view class="bg-white mt-[24rpx] p-4 rounded-lg">
					<!-- 技能专长标题 -->
					<view class="mb-[20rpx] relative">
						<text class="text-[32rpx] font-bold text-[var(--text-color-primary)]">{{t('skills')}}</text>
						<view class="absolute bottom-[-10rpx] left-0 ">
							<image class="w-[50rpx] h-[20rpx] block"
								:src="img('addon/home_service/store/technician/background_bar.png')" mode="widthFix">
							</image>
						</view>
					</view>

					<!-- 技能卡片区域 -->
					<view class="mx-auto rounded-xl">
						<view class="space-y-[15px]">
							<view class="bg-[#F6FCFF] p-[25rpx] rounded-lg "
								v-for="(skillItem, index) in technician?.category_name || []" :key="index">
								<text
									class="text-sm !font-bold text-[#1773FF] skill-name">{{ skillItem?.category_name || '' }}</text>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>

	<!-- 编辑师傅信息弹窗 -->
	<u-popup :show="isDialogVisible" mode="center" :closeable="true" :mask-close-able="false" round="5" @close="isDialogVisible = false" zIndex="999">
		<view class=" bg-white rounded-xl p-[25rpx] w-[80vw]">
			<view class="text-lg font-bold text-center mb-5">{{t('editTechnicianInfo')}}</view>

			<!-- 头像上传 - 使用upload-img组件替换原有实现 -->
			<view class="flex flex-col items-center mb-5">
				<upload-img v-model="editForm.headimg" bgUrl="" :max-count="1" :multiple="false" />
			</view>

			<!-- 表单 - 修复语法错误 -->
			<u-form :model="editForm" ref="formRef" :rules="rules" labelWidth="80" required class="ml-[30rpx]">
				<u-form-item :label="t('name')" prop="name" required>
					<u-input v-model="editForm.name" :placeholder="t('pleaseEnterName')"></u-input>
				</u-form-item>
				<u-form-item :label="t('phone')" prop="phone" required>
					<u-input v-model="editForm.phone" type="number" :placeholder="t('pleaseEnterPhone')"></u-input>
				</u-form-item>
				<!-- <u-form-item :label="t('selfIntroduction')" prop="introduction" required>
					<u-input v-model="editForm.introduction" type="textarea" :rows="3" 
						:placeholder="t('pleaseEnterIntroduction')"></u-input>
				</u-form-item> -->
			</u-form>

			<!-- 按钮 -->
			<view class="flex mt-6">
				<u-button :plain="true" @click="cancelEdit" class="flex-1 mr-3">{{t('cancel')}}</u-button>
				<u-button @click="saveEdit" class="flex-1 bg-blue-500" type="primary">{{t('save')}}</u-button>
			</view>
		</view>
	</u-popup>
</template>

<script setup lang="ts">
	// 首先确保从Vue导入了nextTick
	import { ref, computed, onMounted, nextTick } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import { onLoad, onPageScroll } from '@dcloudio/uni-app'
	import { getTechnicianInfo, editTechnicianInfo } from '@/addon/home_service/technician/api/technician'
	import uploadImg from '@/addon/home_service/technician/components/upload-img/upload-img.vue'
	// 导入u-popup组件

	const technician_id = ref('')
	// 师傅详情数据 - 改为ref对象
	const loading = ref<boolean>(true);
	const technician = ref({
		real_name: '',
		mobile: '',
		full_address: '',
		category_name: [],
		order_count: 0,
		abnormal_count: 0,
		refund_count: 0,
		positive_rate: '0%',
		intro: '',
		headimg: '',
		status: '1'
	})

	// 获取师傅详情方法
	const getTechnicianDetailFn = () => {
		loading.value = true
		getTechnicianInfo(technician_id.value).then((res : any) => {
			loading.value = false
			technician.value = res.data
		}).catch((err) => {
			loading.value = false
		})
	}

	// scrollTop变量定义
	const scrollTop = ref(0)

	onPageScroll((e) => {
		scrollTop.value = e.scrollTop || 0
	})

	onLoad((data) => {
		technician_id.value = data.technician_id || ''
		scrollTop.value = 0 // 初始化scrollTop
		getTechnicianDetailFn()
	})

	const showMoreTag = ref(false)

	// 编辑相关变量
	const editForm = ref({
		name: '',
		phone: '',
		introduction: '',
		headimg: ''
	})

	const isDialogVisible = ref(false)
	const formRef = ref(null)

	// 表单验证规则
	const rules = {
		name: [
			{ required: true, message: t('nameRequired'), trigger: 'change' },
			{ min: 2, max: 20, message: t('nameLength'), trigger: 'change' }
		],
		phone: [
			{ required: true, message: t('phoneRequired'), trigger: 'change' },
			{ pattern: /^1[3-9]\d{9}$/, message: t('phoneFormat'), trigger: 'change' }
		],
		// 添加头像和简介的验证规则
		headimg: [
			{ required: true, message: t('avatarRequired'), trigger: 'change' }
		],
		// introduction: [
		// 	{ required: true, message: t('introductionRequired'), trigger: 'change' },
		// 	{ min: 10, max: 200, message: t('introductionLength'), trigger: 'change' }
		// ]
	}

	// 处理编辑按钮点击事件 - 简化实现
	const handleEdit = () => {
		try {
			console.log('编辑按钮被点击，当前师傅数据：', technician.value);
			// 打开弹窗前设置临时数据
			editForm.value = {
				name: technician.value?.real_name || '',
				phone: technician.value?.mobile || '',
				introduction: technician.value?.intro || '',
				headimg: technician.value?.headimg || ''
			}
			console.log('设置编辑表单数据：', editForm.value);
			// 直接打开弹窗，不使用nextTick
			isDialogVisible.value = true;
			console.log('弹窗状态设置为：', isDialogVisible.value);
		} catch (error) {
			console.error('处理编辑点击事件时出错：', error);
			// 即使出错也尝试打开弹窗
			isDialogVisible.value = true;
		}
	}

	// 取消编辑
	const cancelEdit = () => {
		isDialogVisible.value = false
	}

	// 保存编辑
	const saveEdit = () => {
		// 表单验证 - 添加更明确的字段判断
		const { headimg, name, phone, introduction } = editForm.value
		
		// 头像验证
		if (!headimg || headimg.trim() === '') {
			uni.showToast({
				title: t('avatarRequired'),
				icon: 'none'
			})
			return
		}
		
		// 姓名验证
		if (!name || name.trim() === '') {
			uni.showToast({
				title: t('nameRequired'),
				icon: 'none'
			})
			return
		}
		
		// 手机号验证
		if (!phone || phone.trim() === '') {
			uni.showToast({
				title: t('phoneRequired'),
				icon: 'none'
			})
			return
		}
		if (!/^1[3-9]\d{9}$/.test(phone)) {
			uni.showToast({
				title: t('phoneFormat'),
				icon: 'none'
			})
			return
		}
		
		// 个人简介验证
		// if (!introduction || introduction.trim() === '') {
		// 	uni.showToast({
		// 		title: t('introductionRequired'),
		// 		icon: 'none'
		// 	})
		// 	return
		// }
		// if (introduction.length < 10 || introduction.length > 200) {
		// 	uni.showToast({
		// 		title: t('introductionLength'),
		// 		icon: 'none'
		// 	})
		// 	return
		// }
		
		// 所有验证通过，调用API保存修改
		const params = {
			technician_id: technician_id.value,
			headimg: headimg,
			intro: introduction,
			mobile: phone,
			real_name: name
		}
		
		// 显示加载提示
		uni.showLoading({
			title: t('saving')
		})
		
		editTechnicianInfo(params).then((res: any) => {
			uni.hideLoading()
			if (res.code === 1) {
				// 更新本地数据
				technician.value.real_name = name
				technician.value.mobile = phone
				technician.value.intro = introduction
				technician.value.headimg = headimg
				// 显示成功提示
				uni.showToast({
					title: t('saveSuccess'),
					icon: 'none'
				})
				// 关闭弹窗
				isDialogVisible.value = false
			} else {
				uni.showToast({
					title: res.msg || t('saveFailed'),
					icon: 'none'
				})
			}
		}).catch(() => {
			uni.hideLoading()
			uni.showToast({
				title: t('saveFailed'),
				icon: 'none'
			})
		})
	}

	// 主题颜色处理
	const themeColor = () => {
		return {
			'--page-bg-color': '#F5F5F5'
		}
	}
</script>

<style lang="scss" scoped>
	/* 基础样式 */
	.body-bottom {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	/* 技能卡片样式 */
	.skill-card {
		border-radius: 16px;
	}

	/* 技能名称样式 */
	.skill-name {
		color: #1773FF;
	}

	.position-size {
		background-size: 100%;
	}
</style>

<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>