<template>
	<!-- 置顶的搜索和分类标签栏 - 修复u-sticky组件 -->
	<u-sticky bgColor="#ffffff" :zIndex="999" :offsetTop="navHeight">
		<view class="flex px-[25rpx] items-center py-[20rpx]">
			<view class="flex flex-1 items-center bg-[#f6f6f6] rounded-[50rpx] px-[25rpx] py-[15rpx]">
				<u-icon name="search" size="20" color="#999" class="mr-2"></u-icon>
				<input type="text" :placeholder="t('searchPlaceholder')" v-model="searchName" @blur="searchTechnican"
					class="flex-1 bg-transparent text-[26rpx]" />
			</view>
			<view class="ml-[35rpx]" @click="statusShow = true"><text
					class="iconfont icona-shaixuan-36V6xx-36 pr-[5rpx] text-[26rpx]"></text> <text class=" text-[26rpx]"
					@click="handleFilter">{{ t('filter') }}</text></view>
		</view>
		<u-tabs :list="categoryList" keyName="category_name" @change="changeTabs"></u-tabs>
	</u-sticky>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" :style="themeColor()">
		<!-- 仅在小程序端显示导航栏 -->
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="false" />
		<!-- #endif -->


		<!-- 筛选器弹出层 -->
		<u-picker :show="statusShow" :columns="statusList" keyName="label" @confirm="statusConfrim"
			@cancel="statusShow = false"></u-picker>

		<!-- 技师列表内容区域 - 调整mescroll-body的样式和位置 -->
		<mescroll-body ref="mescrollRef" :down="{ use: false }" :top="getMescrollTop()" @init="mescrollInit"
			@up="getTechnicianListFn" class="custom-mescroll-body">
			<view class="mt-2 mx-[25rpx]" v-if="technicianList.length">
				<!-- 技师项 -->
				<view class="bg-white p-[24rpx] mb-[25rpx] rounded-lg" v-for="(technician, index) in technicianList"
					:key="index" @click.stop="goDetail(technician.technician_id)">
					<view class="flex">
						<!-- 头像和状态区域 - 调整状态标签位置使其覆盖头像下半部分 -->
						<view class="relative mr-3">
							<u--image :src="img(technician.headimg)" width="160rpx" height="160rpx" radius="100rpx"
								mode="aspectFill">
								<template #error>
									<image :src="img('static/resource/images/default_headimg.png')"
										class="w-20 h-20 rounded-full  border-2 border-white" mode="aspectFill"></image>
								</template>
							</u--image>
							<text
								:class="technician.status == '1' ? 'text-xs bg-green-500 text-white' : 'text-xs bg-gray-400 text-white'"
								class="px-2.5 py-0.5 rounded-full absolute top-[130rpx] left-1/2 transform -translate-x-1/2 z-10 whitespace-nowrap">
								{{ technician.status == '1' ? t('working') : t('resting') }}
							</text>
						</view>

						<!-- 技师信息 -->
						<view class="flex-1">
							<view class="flex items-center justify-between">
								<view class="flex items-center">
									<text
										class="text-[30rpx] font-bold truncate leading-5 max-w-[180rpx] mr-[15rpx]">{{ technician.real_name }}
									</text><text class="text-[24rpx] text-[#666]">{{technician.mobile}}</text>
								</view>
								<!-- 五角星和评分水平排列 -->
								<view class="flex items-center">
									<u-icon name="star-fill" size="15" color="#FF3502" class="mr-0.5"></u-icon>
									<text
										class="text-[#FF3502] text-[26rpx] leading-5 font-bold">{{ technician.evaluate_avg_scores }}</text>
								</view>
							</view>

							<text class="text-[24rpx] text-[#999] mt-1 block truncate max-w-[470rpx]">
								地址：{{ technician.full_address }}
							</text>

							<view class="flex flex-wrap mt-[15rpx] ">
								<!-- 技能标签背景颜色保持为#FFFAD9 -->
								<view class="relative z-10 flex flex-wrap">
									<text v-for="(skill, idx) in technician.category_name"
										class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
										v-show="idx < 2">{{ skill.category_name }}</text>
									<text v-if="technician.category_name.length >= 3 && !technician.showMoreTag"
										@click.stop="technician.showMoreTag = true"
										class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]">...</text>
									<text v-for="(skill, idx) in technician.category_name"
										class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
										v-show="idx >= 2 && technician.showMoreTag">{{ skill.category_name }}</text>
									<text v-if="technician.showMoreTag" @click.stop="technician.showMoreTag = false"
										class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx] flex items-center leading-1"><text
											class="iconfont iconshangV6xx-1"></text></text>
								</view>
							</view>
						</view>
					</view>

					<view class="flex justify-between mt-4">
						<button
							class="flex items-center justify-center flex-1 flex-col bg-blue-50 text-blue-500 border border-blue-500 rounded-lg py-2"
							@click.stop="goAssignOrder(technician.id)">
							<image :src="img('addon/home_service/store/technician/dispatch_order.png')"
								class="w-5 h-5  mb-[15rpx]" mode="aspectFit"></image>
							<text class="text-xs font-medium whitespace-nowrap">
								{{ t('assignOrder') }}
							</text>
						</button>
						<button
							class="flex items-center justify-center flex-col flex-1 bg-orange-50 text-orange-500 border border-orange-500 rounded-lg py-2 mx-2"
							@click.stop="goScheduleManagement(technician.technician_id)">
							<image :src="img('addon/home_service/store/technician/scheduling.png')"
								class="w-5 h-5  mb-[15rpx]" mode="aspectFit"></image>
							<text class="text-xs font-medium whitespace-nowrap">
								{{ t('scheduleManagement') }}
							</text>
						</button>
						<button
							class="flex items-center justify-center flex-col flex-1 bg-orange-50 text-orange-500 border border-orange-500 rounded-lg py-2 mr-2"
							@click.stop="showPopup(technician)">
							<text
								class="iconfont iconshezhi !bg-[#ff0000] w-5 h-5 leading-5 mb-[15rpx] rounded-[50rpx] text-[#fff] "></text>
							<text class="text-xs font-medium whitespace-nowrap">
								分佣比例
							</text>
						</button>
						<button
							class="flex items-center flex-col justify-center flex-1 text-green-500 border border-green-500 rounded-lg py-2"
							@click.stop="makeCall(technician.mobile)">
							<image :src="img('addon/home_service/store/technician/phone.png')"
								class="w-5 h-5  mb-[15rpx]" mode="aspectFit"></image>
							<text class="text-xs font-medium whitespace-nowrap">
								联系师傅
							</text>
						</button>
					</view>
				</view>
			</view>
			<view class="pl-[20rpx] pt-[0rpx]" style="width: calc(100% - 182rpx)">
				<mescroll-empty :option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }"
					v-if="!technicianList.length && !loading" class="part"></mescroll-empty>
			</view>
		</mescroll-body>
		<u-popup :show="showratoPopup" @close="showratoPopup = false" @open="open" mode="center" :round="10">
			<view class="w-[80vw] p-[30rpx]">
				<view class="text-center text-[32rpx] font-bold">
					设置分佣比例
				</view>
				<view class="flex my-[40rpx] items-center border-bottom">
					<view class="">分佣比例：</view>
					<input type="number" inputmode="decimal" pattern="\d+(\.\d+)?" maxlength="5" v-model="ratoNumber"
						class="rounded-[20rpx] p-1 px-3 flex items-center w-[280rpx]" placeholder="请输入0-100的比例"
						@input="handleRatoInput" />
					<span class="ml-[15rpx]">%</span> <!-- 增加百分比符号，引导用户理解 -->
				</view>
				<view>
					<button class="flex-1 py-3 bg-blue-500 text-white rounded-lg bg-[var(--store-bg-one)] !text-[26rpx]"
						@click="submitForm">
						确定
					</button>
				</view>
			</view>
		</u-popup>
		<!-- 底部组件 -->
		<tabbar :value="2"></tabbar>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import tabbar from '@/addon/home_service/store/components/tabbar/tabbar'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
	import { onPageScroll, onReachBottom } from '@dcloudio/uni-app';
	import { getTechnicianList, getCategoryList, getTechnicianStatus, setRate } from '@/addon/home_service/store/api/technician'
	import useSystemStore from '@/stores/system';
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '机构师傅', topStatusBar: { textColor: '#333' } })
	const systemStore = useSystemStore()
	const platform = systemStore.systemInfo.platform;
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
	const showratoPopup = ref(false)
	// 1. 小程序菜单按钮（胶囊）信息：用于计算适配距离
	const menuButtonInfo = ref({});
	menuButtonInfo.value = systemStore.menuButtonInfo

	// 2. 导航栏核心适配数据
	// 导航栏总高度（小程序：胶囊高度 + 胶囊top；H5：0）
	const navHeight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10);
		// #endif
		// #ifndef MP-WEIXIN
		return 0;
		// #endif
	});
	const settechnicianRato = ref({})
	const showPopup = (technician : any) => {
		settechnicianRato.value = technician
		ratoNumber.value = technician.order_rate
		showratoPopup.value = true
	}
	// 实时校验分佣比例输入
	const handleRatoInput = () => {
		// 1. 先过滤掉非数字和多余小数点（如 12.3.4 会变成 12.34）
		const filteredValue = ratoNumber.value.replace(/[^0-9.]/g, '').replace(/\.{2,}/g, '.');
		// 2. 限制只保留1位小数（可选，根据需求调整）
		const decimalIndex = filteredValue.indexOf('.');
		if (decimalIndex !== -1 && filteredValue.length > decimalIndex + 2) {
			ratoNumber.value = filteredValue.slice(0, decimalIndex + 2);
		} else {
			ratoNumber.value = filteredValue;
		}

		// 3. 转成数字判断范围
		const num = Number(ratoNumber.value);
		if (num > 100) {
			uni.showToast({ title: '比例不能超过100%', icon: 'none', duration: 1000 });
			ratoNumber.value = '100'; // 超过100时强制设为100
		} else if (num < 0 && ratoNumber.value) {
			uni.showToast({ title: '比例不能小于0%', icon: 'none', duration: 1000 });
			ratoNumber.value = '0'; // 小于0时强制设为0
		}
	};
	// 修复getMescrollTop函数，确保内容区域正确显示在固定头部下方
	const getMescrollTop = () => {
		// #ifdef MP-WEIXIN
		return 0 + 'px';
		// #endif
		// #ifndef MP-WEIXIN
		// 公众号模式下设置为0，确保内容紧贴顶部固定区域
		return '0px';
		// #endif
	}
	const ratoNumber = ref('')
	const submitForm = () => {
		// 1. 先去除首尾空格，判断是否为空
		const trimedValue = ratoNumber.value.trim();
		if (!trimedValue || trimedValue === '.') {
			uni.showToast({ title: '请输入有效的分佣比例', icon: 'none' });
			return;
		}

		// 2. 转数字再次校验范围
		const num = Number(trimedValue);
		if (num < 0 || num > 100) {
			uni.showToast({ title: '比例必须在0-100之间', icon: 'none' });
			return;
		}

		// 3. 校验通过，执行后续逻辑（如接口请求）
		let params = {
			technician_id: settechnicianRato.value.technician_id,
			order_rate: ratoNumber.value
		}
		setRate(params).then((res) => {
			uni.showToast({ title: '分佣比例设置成功', icon: 'none' });
			showratoPopup.value = false; // 关闭弹窗
			ratoNumber.value = ''; // 清空输入框
			getMescroll().resetUpScroll()
		})


	};
	const statusList = ref([[{ label: '全部', value: '' }]])
	const statusShow = ref(false)
	const statusValue = ref('')
	const statusConfrim = (e : any) => {
		statusValue.value = e.value[0].value
		statusShow.value = false
		getMescroll().resetUpScroll()
	}
	const searchName = ref("");
	const categoryId = ref('')
	const loading = ref<boolean>(true);
	const categoryList = ref([])
	const getCategoryListFn = () => {
		getCategoryList().then((res) => {
			categoryList.value = res.data
			let obj = { category_name: "全部", category_id: '' }
			categoryList.value.unshift(obj)
		})
	}
	const searchTechnican = () => {
		getMescroll().resetUpScroll()
	}
	const getTechnicianStatusFn = () => {
		getTechnicianStatus().then((res) => {
			Object.keys(res.data).forEach((item, index) => {
				let obj = {
					label: res.data[item],
					value: item
				}
				statusList.value[0].push(obj)
			})
		})
	}
	getTechnicianStatusFn()
	const changeTabs = (e : any) => {
		categoryId.value = e.category_id
		getMescroll().resetUpScroll()
	}
	getCategoryListFn()
	const technicianList = ref<Array<Object>>([]);
	const getTechnicianListFn = (mescroll) => {
		loading.value = true;
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			real_name: searchName.value,
			status: statusValue.value,
			category_id: categoryId.value
		}
		getTechnicianList(data).then((res) => {
			let newArr = (res.data.data as Array<Object>);
			//设置列表数据
			if (mescroll.num == 1) {
				technicianList.value = []; //如果是第一页需手动制空列表
			}
			technicianList.value = technicianList.value.concat(newArr);
			if (technicianList.value.length) {
				technicianList.value.forEach((item, index) => {
					item.showMoreTag = false
				})
			}
			mescroll.endSuccess(newArr.length);
			loading.value = false;
		}).catch(() => {
			loading.value = false;
			mescroll.endErr(); // 请求失败, 结束加载
		})
	}
	// 去派单按钮点击事件
	const goAssignOrder = (technicianId : number) => {
		redirect({
			url: '/addon/home_service/store/pages/index',
		})
	}
	const goDetail = (id : number) => {
		redirect({
			url: '/addon/home_service/store/pages/technician/detail',
			param: {
				technician_id: id
			}
		})
	}
	// 排班管理按钮点击事件
	const goScheduleManagement = (technicianId : number) => {
		redirect({
			url: '/addon/home_service/store/pages/technician/rest',
			param: {
				technician_id: technicianId
			}
		})
	}

	// 打电话按钮点击事件
	const makeCall = (phone : string) => {
		// 实现打电话逻辑
		uni.makePhoneCall({
			phoneNumber: phone
		})
	}
</script>

<style lang="scss">
	@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	/* 页面样式 */
	page {
		margin: 0;
		padding: 0;
		padding-bottom: 60px;
		/* 为底部导航栏留出空间 */
	}

	/* 自定义mescroll-body样式 */
	.custom-mescroll-body {
		margin-top: 0 !important;
		padding-top: 0 !important;
	}

	/* 确保u-sticky组件在公众号模式下正确固定 */
	:deep(.u-sticky) {
		position: sticky !important;
		/* #ifdef H5 */
		top: 0 !important;
		/* #endif */
		z-index: 999 !important;
	}

	/* 移除可能的顶部间距 */
	.component-class {
		margin-top: 0 !important;
		padding-top: 0 !important;
	}

	.border-bottom {
		border-bottom: 2rpx solid #efefef;
	}

	:deep(.u-tabbar--fixed) {
		z-index: 99 !important;
	}
</style>