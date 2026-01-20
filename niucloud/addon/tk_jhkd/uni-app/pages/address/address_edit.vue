<template>
	<view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()">
		<view class="tk-card p-2  mb-4">
			<up-textarea clearable border="none" v-model="addressInfo" height="62" placeholder="在此处输入粘贴地址可以快速自动识别"
				maxlength="240" @keydown.enter="handleEnter" @keydown.shift.enter="handleShiftEnter"
				@paste="handlePaste" autoHeight />
			<view class="flex items-center ml-2" @click="fanyiAddressEvent()">
				<view>
					<up-icon :name="img('addon/tk_jhkd/icon/local.png')" color="#c3c4d5" size="20"></up-icon>
				</view>
				<view class="ml-2 text-[#1061FE]">地址识别</view>
			</view>
		</view>
		<view class="tk-card !p-3 !mt-4">
			<view class="font-bold">联系人信息</view>
			<up-form labelPosition="left" :model="formData" labelWidth="200rpx" errorType='toast' :rules="rules"
				ref="formRef">
				<view class="mt-[10rpx]">
					<up-form-item :label="t('name')" prop="name">
						<up-input v-model.trim="formData.name" border="none" clearable maxlength="25"
							:placeholder="t('namePlaceholder')" />
					</up-form-item>
				</view>
				<view class="mt-[10rpx]">
					<up-form-item :label="t('mobile')" prop="mobile">
						<up-input v-model="formData.mobile" border="none" clearable
							:placeholder="t('mobilePlaceholder')" />
					</up-form-item>
				</view>
				<view class="mt-[10rpx]">
					<up-form-item :label="t('selectArea')" prop="area" @click="selectArea">
						<view class="flex justify-between flex-1 items-center">
							<view v-if="!formData.area" class="text-gray-placeholder text-[30rpx]">
								{{ t('selectAreaPlaceholder') }}
							</view>
							<view v-else class="text-[26rpx] mt-1">{{ formData.area }}</view>
							<view>
								<up-icon name="arrow-right" color="#3B3B3B" size="18"></up-icon>
							</view>
						</view>

					</up-form-item>
				</view>
				<view class="mt-[10rpx]">
					<up-form-item :label="t('address')" prop="address">
						<up-input v-model.trim="formData.address" border="none" clearable maxlength="120"
							:placeholder="t('addressPlaceholder')" />
					</up-form-item>
				</view>
				<!-- <view class="mt-[10rpx]">
					<up-form-item :label="t('defaultAddress')" prop="name">
						<up-switch v-model="formData.is_default" size="20" :activeValue="1" :inactiveValue="0"
							activeColor="#0057FE" />
					</up-form-item>
				</view> -->
			</up-form>
		</view>
		<view class="w-full footer">
			<view v-if="formData.area == '' || formData.address == '' || formData.name == '' || formData.mobile == ''"
				class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class="bg-[#BDBDBD] text-[#fff] h-[80rpx] leading-[80rpx] rounded-[100rpx] text-[26rpx] font-500">保存</button>
			</view>
			<view v-else
				class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class="bg-[#0057FE] text-[#fff] h-[80rpx] leading-[80rpx] rounded-[100rpx] text-[26rpx] font-500"
					@click="save">保存</button>
			</view>
		</view>
		<address-select ref="areaRef" @complete="areaSelectComplete" :area-id="formData.district_id" />

	</view>

</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { redirect, img } from '@/utils/common'
import { t } from '@/locale'
import { addAddress, editAddress, getAddressInfo } from '@/app/api/member'
import { fanyiAddress } from '@/addon/tk_jhkd/api/tkjhkd'
import addressSelect from '@/addon/tk_jhkd/pages/components/area-select.vue'
const addressInfo = ref()
const formData = ref({
	id: 0,
	name: '',
	mobile: '',
	province_id: 0,
	city_id: 0,
	district_id: 0,
	address: '',
	full_address: '',
	is_default: 0,
	area: '',
	type: 'address'
})

const areaRef = ref()
const formRef = ref(null)
const type = ref('')
const source = ref('')
const btnDisabled = ref(false)
const handlePaste = () => {
	setTimeout(() => {
		fanyiAddressEvent()
	}, 500)
}
const fanyiAddressEvent = () => {
	if (!addressInfo.value) {
		uni.$u.toast('请填写解析地址')
		return
	}
	fanyiAddress({ address: addressInfo.value }).then(res => {
		Object.assign(formData.value, res.data)
		uni.$u.toast('地址解析成功')
		addressInfo.value = ''

	})
}
onLoad((data) => {
	if (data.id) {
		getAddressInfo(data.id)
			.then(({ data }) => {
				data && Object.assign(formData.value, data)
			})
			.catch()
	}
	type.value = data.type || ''
	source.value = data.source || ''

})

const rules = computed(() => {
	return {
		'name': {
			type: 'string',
			required: true,
			message: t('namePlaceholder'),
			trigger: ['blur', 'change'],
		},
		'mobile': [
			{
				type: 'string',
				required: true,
				message: t('mobilePlaceholder'),
				trigger: ['blur', 'change'],
			},
			{
				validator(rule, value, callback) {
					let mobile = /^1[3-9]\d{9}$/;
					if (!mobile.test(value)) {
						callback(new Error(t('mobileError')))
					} else {
						callback()
					}
				}
			}
		],
		'area': {
			validator() {
				return !uni.$u.test.isEmpty(formData.value.area)
			},
			message: t('selectAreaPlaceholder')
		},
		'address': {
			type: 'string',
			required: true,
			message: t('addressPlaceholder'),
			trigger: ['blur', 'change'],
		}
	}
})
const selectArea = () => {
	areaRef.value.open()
}
const areaSelectComplete = (event) => {
	formData.value.province_id = event.province.id || 0
	formData.value.city_id = event.city.id || 0
	formData.value.district_id = event.district.id || 0
	formData.value.area = `${event.province.name || ''}${event.city.name || ''}${event.district.name || ''}`
}

const operateLoading = ref(false)
const save = () => {
	if (type.value == 'copy') {
		formData.value.id = 0
	}
	const save = formData.value.id ? editAddress : addAddress
	formRef.value.validate().then(() => {
		if (operateLoading.value) return
		operateLoading.value = true
		btnDisabled.value = true
		formData.value.full_address = formData.value.area + formData.value.address
		save(formData.value).then((res) => {
			operateLoading.value = false
			setTimeout(() => {
				btnDisabled.value = false
				redirect({
					url: '/addon/tk_jhkd/pages/address/address',
					mode: 'redirectTo',
					param: { type: type.value, source: source.value }
				})
			}, 1000)
		}).catch(() => {
			operateLoading.value = false
			btnDisabled.value = false
		})
	})
}
</script>

<style lang="scss" scoped>
@import '@/addon/tk_jhkd/utils/styles/common.scss';

page {
	--primary-color: #0057FE;
	--primary-color-dark: #F26F3E;
	--primary-color-disabled: #FFB397;
	--primary-color-light: #FFEAE2;
	--page-bg-color: #f7f7f7;
	--price-text-color: #e1251b;
}

.tk-tag {
	padding: 4rpx 8rpx;
	background: var(--primary-color);
	color: #ffffff;
	border-radius: 8rpx;
	font-size: 22rpx;
}

.tk-card1 {
	background-color: rgba(255, 255, 255, 0.9);

	border-radius: 12rpx;

	bbbox-shadow: 0 1px 1px 0 rgba(234, 234, 234, 0.2), 0 2px 2px 0 rgba(231, 231, 231, 0.2);
}
</style>