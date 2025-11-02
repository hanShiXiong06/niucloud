<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<u-tabs :list="orderStatus" @click="click" keyName="label" @change="changeTabs"></u-tabs>
		<view class="m-[25rpx]" v-if="!loading">
			<view class="bg-[#fff] rounded-lg p-[25rpx] mb-[25rpx]" v-for="(orderItem,orderIndex) in orderList" :key="orderIndex" v-if="orderList.length">
				<view class="flex justify-between">
					<view>{{orderItem.create_time}}</view>
					<view :class="orderItem.is_pay == '待付款' ? 'text-[#ff0000]' : ''">{{orderItem.is_pay}}</view>
				</view>
				<view class="flex mt-[15rpx] justify-between" v-if="orderItem.item_list && orderItem.item_list.length > 0">
					<view class="w-[77%] ">
						<u-scroll-list :indicator="false">
							<view v-for="(item, index) in orderItem.item_list" :key="index" :indicator="false">
								<up-image :src="img(item.item_image)" width="110rpx" height="110rpx" radius="10rpx" class="mr-[20rpx]"
									>
									<template #error>
									    <u-icon name="photo" color="#999" size="50"></u-icon>
									</template>
								</up-image>
							</view>
						</u-scroll-list>
					</view>
					<view class="w-[20%] flex flex-col  justify-center items-center pb-[20px]">
						<view>
							<text class="text-[22rpx]">￥</text>
							<text
								class="text-[28rpx] text-[32rpx]  price-font">{{orderItem.item_money}}</text>
						</view>
						<view class="text-[26rpx] text-[#666666]">
							共{{orderItem.item_count}}件
						</view>
					</view>
				</view>
				<view class="flex justify-between items-center" v-if="orderItem.service_fee && Number(orderItem.service_fee)">
					<view class="text-[26rpx]">
						附加服务费
					</view>
					<view>
						<text class="text-[22rpx]">￥</text>
						<text
							class="text-[28rpx] text-[30rpx]   price-font">{{orderItem.service_fee}}</text>
					</view>
				</view>
				<view class="flex justify-between items-center">
					<view class="text-[26rpx]">
					</view>
					<view>
						<text class="text-[24rpx]">实付款</text>
						<text class="text-[24rpx]">￥</text>
						<text class="text-[28rpx] text-[32rpx] text-[var(--price-text-color)] price-font">{{orderItem.total_money}}</text>
					</view>
				</view>
			</view>
			<view class=" h-[500rpx] flex flex-col justify-center items-center bg-[#fff] rounded-lg" v-else>
				<u-empty :icon="img('static/resource/images/order_empty.png')" />
			</view>
		</view>
		<view class="bg-[#fff] m-[25rpx] rounded-lg" v-if="!loading && goodsItem && goodsItem.length > 0">
			<view class="">
				<view class="flex py-[25rpx] justify-between">
					<view class="flex items-center">
						<view class="w-[10rpx] h-[40rpx] rounded-tr-[15rpx] rounded-br-[15rpx] bg-[var(--technician-bg-one)] mr-[25rpx]">
						</view>
						<view class="text-[26rpx]">
							<view class="text-[30rpx] font-bold">可添加服务</view>
							<view class="mt-[15rpx] text-[24rpx] text-[#999999]">添加前请与客户沟通好购买意向</view>
						</view>
					</view>
					<view class="text-[26rpx] pr-[25rpx]">
						已选{{chooseNum}}件
					</view>
				</view>
			</view>
			<view class="px-[25rpx] pb-[25rpx]">
				<view class="flex justify-between mb-[25rpx]" v-for="(item,index) in goodsItem" :key="index">
					<view class="mr-[25rpx]">
						<up-image :src="img(item.img)" width="160rpx" height="160rpx" radius="10rpx"
							>
							<template #error>
							    <u-icon name="photo" color="#999" size="50"></u-icon>
							</template>
						</up-image>
					</view>
					<view class="flex-1 flex flex-col justify-around">
						<view class="text-[28rpx] leading-4 font-bold">
							{{item.name}}
						</view>
						<!-- <view class="text-[24rpx] text-[#666566]">
							配件属性名称
						</view> -->
						<view class="flex justify-between items-end">
							<view class="flex items-end text-[var(--price-text-color)]">
								<text class="text-[24rpx] pb-[4rpx]">￥</text>
								<text
									class="text-[34rpx] text-[30rpx]  price-font">{{item.price}}</text>
							</view>
							<view>
								<u-number-box v-model="item.num" min="0" @change="changeInput"
									button-size="30"></u-number-box>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>

		<view class="bg-[#fff] m-[25rpx] rounded-lg" v-if="!loading">
			<view class="">
				<view class="flex py-[25rpx] justify-between">
					<view class="flex items-center">
						<view class="w-[10rpx] h-[40rpx] rounded-tr-[15rpx] rounded-br-[15rpx] bg-[var(--technician-bg-one)] mr-[25rpx]">
						</view>
						<view class="text-[26rpx]">
							<view class="text-[30rpx] font-bold">服务费用</view>
						</view>
					</view>
				</view>
			</view>
			<view class="mx-[25rpx] pb-[25rpx]">
				<view class="bg-[#f6f6f6] flex justify-between p-[25rpx] rounded-lg">
					<view class="text-[28rpx] leading-5">
						服务费用：
					</view>
					<view>
						<input type="number" placeholder="请输入" min="0" class="w-[250rpx] text-right" v-model="servicePrice" @blur="inputS" />
					</view>
				</view>
			</view>
		</view>
	</view>
	<view class="w-full footer bg-[#fff]" :style="themeColor()">
		<view
			class="py-[var(--top-m)] px-[var(--sidebar-m)] z-index-999 fixed bottom-0 left-0 right-0 box-border flex justify-between items-end bg-[#fff]">
			<view>
				<text class="text-[24rpx]">实付款</text>
				<text class="text-[24rpx] text-[var(--price-text-color)]">￥</text>
				<text class="text-[28rpx] text-[38rpx] !text-[var(--price-text-color)] price-font">{{allMoney}}</text>
			</view>
			<button hover-class="none"
				class="relative z-index-999 !text-[#fff] !bg-[var(--technician-bg-one)] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500 mx-0 pt-0 px-[80rpx]"
				@click="submitForm"
				:class="{'opacity-50': btnDisabled}">确定并提交
			</button>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted, onUnmounted } from 'vue'
	import { grabAddOrderDetail,getGoodsItem,editGoodsItem,addGoodsItem } from '@/addon/home_service/technician/api/order'
	import { onLoad } from '@dcloudio/uni-app'
	import { img, redirect, copy } from '@/utils/common'
	import { t } from '@/locale'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '增项服务', topStatusBar: { textColor: '#333' } })
	const loading = ref<boolean>(true);
	const chooseNum = ref(0)
	const goodsItem = ref([])
	const orderStatus = ref([
		{
			label: '全部',
			value: ''
		},
		{
			label: "待付款",
			value: 0
		},
		{
			label: "已付款",
			value: 1
		}
	])
	const changeInput = (e:any) =>{
		chooseNum.value = e.value
		calcAllNum()
	}
	const allMoney = ref(0)
	const inputS = () =>{
		// 校验规则：不能输入负数或0，小数最多2位
		if (servicePrice.value) {
			// 移除所有非数字字符，只保留数字和小数点
			let value = servicePrice.value.replace(/[^\d.]/g, '');
			
			// 检查小数点，确保只有一个小数点
			const parts = value.split('.');
			if (parts.length > 2) {
				value = parts[0] + '.' + parts.slice(1).join('');
			}
			
			// 如果小数点前没有数字，补零
			if (value.startsWith('.')) {
				value = '0' + value;
			}
			
			// 处理整数部分的前导零
			if (parts[0] && parts[0].startsWith('0') && parts[0].length > 1) {
				parts[0] = parseInt(parts[0], 10).toString();
			}
			
			// 如果有小数点，限制小数位为2位
			if (parts.length === 2) {
				value = parts[0] + '.' + (parts[1].substring(0, 2) || '00');
			} else {
				value = parts[0];
			}
			
			// 转换为数字检查是否为正数
			const numValue = parseFloat(value);
			if (isNaN(numValue) || numValue < 0) {
				uni.showToast({ title: '请输入大于0的金额', icon: 'none' });
				servicePrice.value = '';
			} else {
				servicePrice.value = value;
			}
		}
		calcAllNum()
	}
	// 滚动到顶部
	const scrollToTop = () => {
	  uni.pageScrollTo({
	    scrollTop: 0, // 滚动到的位置（顶部为0）
	    duration: 0, // 滚动动画时长（毫秒，可选）
	    success: () => {
	      console.log("滚动到顶部成功");
	    },
	    fail: (err) => {
	      console.error("滚动失败：", err);
	    },
	  });
	};
	const calcAllNum = () =>{
		chooseNum.value = 0
		allMoney.value = 0
		if(goodsItem.value && goodsItem.value?.length){
			setTimeout(()=>{
				goodsItem.value.forEach((item,index)=>{
					if(item.num){
					chooseNum.value += Number(item.num)
						allMoney.value += Number(item.price) * Number(item.num)
					}
				})
				allMoney.value += Number(servicePrice.value)
			})
		}else{
			allMoney.value = Number(servicePrice.value)
		}
		
	}
	const servicePrice = ref('')
	const getGoodsItemFn = () =>{
		let params = {
			order_id:orderId.value
		}
		getGoodsItem(params).then((res)=>{
			goodsItem.value = res.data
			getDetail()
		}).catch((err)=>{
			loading.value = false;
		})
	}
	const changeTabs = (e) =>{
		payStatus.value = e.value
		getDetail()
	}
	const orderId = ref('')
	onLoad((option : any) => {
		  setTimeout(() => {
		     scrollToTop();
		   });
		orderId.value = option.order_id || 0
		getGoodsItemFn()
	})
	const orderList = ref([])
	const payStatus = ref('')
	const submitForm = () =>{
		if(allMoney.value<0){
			uni.showToast({
				title:'请输入正确的服务费用',
				icon:'none'
			})
			return;
		}
		loading.value = true;
		let item_list = [
		]
		if(servicePrice.value){
			item_list.push({
				item_name:'服务费',
				item_image:'',
				price:servicePrice.value,
				is_service_fee:1
			})
		}
		goodsItem.value.forEach((item,index)=>{
			let obj = {
				item_name:item.name,
				item_image:item.img,
				price:item.price,
				num:item.num,
				order_itme_commission_ratio:item.commission_rate
			}
			if(item.num){
				item_list.push(obj)
			}
		})
		
		let param = {
			order_id:orderId.value,
			item_list:item_list,
		}
		if(is_edit.value){
			editGoodsItem(param).then((res)=>{
				loading.value = false;
				uni.navigateBack()
			}).catch((err)=>{
				loading.value = false;
			})
		}else{
			addGoodsItem(param).then((res)=>{
				loading.value = false;
				uni.navigateBack()
			}).catch((err)=>{
				loading.value = false;
			})
		}
		
	}
	const is_edit =ref(false)
	// 修改getDetail方法中的匹配逻辑
	const getDetail = () => {
	  loading.value = true;
	  let params = {
	    order_id: orderId.value,
	    is_pay: payStatus.value
	  }
	  grabAddOrderDetail(params).then((res) => {
	    loading.value = false;
	    orderList.value = res.data;
	    let hasPendingPayment = false;
	    let pendingPaymentItem = null; // 保存待付款项
	    
	    orderList.value.forEach((item) => {
	      if (item.is_pay === '待付款') {
	        hasPendingPayment = true;
	        pendingPaymentItem = item;
	        servicePrice.value = item.service_fee;
	      }
	    });
	    is_edit.value = hasPendingPayment;
	    
	    if (!is_edit.value) return;
	    
	    const goodsNameMap = new Map();
	    goodsItem.value.forEach((goods, index) => {
	      goodsNameMap.set(goods.name, index);
	    });
	    
	    // 只处理待付款的那一项
	    if (pendingPaymentItem && pendingPaymentItem.item_list && pendingPaymentItem.item_list.length > 0) {
	      pendingPaymentItem.item_list.forEach(item2 => {
	        // 排除服务费用项的匹配
	        if (item2.is_service_fee) {
	          return; // 跳过服务费用项
	        }
	        
	        const goodsIndex = goodsNameMap.get(item2.item_name);
	        if (goodsIndex !== undefined) {
	          // 确保num是数字类型
	          goodsItem.value[goodsIndex].num = Number(item2.num) || 0;
	          goodsItem.value = [...goodsItem.value];
	        }
	      });
	    }
	    
	    calcAllNum();
	  }).catch((err) => {
	    loading.value = false;
	  });
	};
</script>

<style scoped>
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>