<template>
	<div class="main-container" v-loading="loading">
		<el-card class="card !border-none mb-[15px]" shadow="never">
			<el-page-header :content="t('addstore')" :icon="ArrowLeft" @back="back" />
		</el-card>

		<el-card class="box-card !border-none" shadow="never">
			<el-form :model="formData" label-width="180px" ref="formRef" :rules="formRules" class="page-form">
				<el-form-item :label="t('storeName')" prop="store_name">
					<el-input v-model.trim="formData.store_name" clearable :placeholder="t('storeNamePlaceholder')"
						class="input-width !w-[214px]" />
				</el-form-item>
				<el-form-item :label="t('headimg')" prop="headimg">
					<upload-image v-model="formData.headimg" @change="clearFieldError('headimg')" />
				</el-form-item>
				<el-form-item :label="t('userName')" prop="contact_name">
					<el-input v-model.trim="formData.contact_name" clearable :placeholder="t('userNamePlaceholder')"
						class="input-width !w-[214px]" />
				</el-form-item>
				<el-form-item :label="t('mobile')" prop="mobile">
					<el-input v-model.trim="formData.mobile" clearable :placeholder="t('mobilePlaceholder')"
						class="input-width !w-[214px]" />
				</el-form-item>
				<el-form-item :label="t('member')" prop="member_id">
					<div class="!w-[214px] border-[1px] border-[#e4e4e4] border-solid px-[11px] h-[30px] leading-[30px]"
						:class="formData.member_nickname ? 'text-[#000]' : 'text-[#999]'">
						{{ formData.member_nickname || t('chooseMemberPlaceholder') }}
					</div>
					<el-button class="ml-[10px]" type="primary" @click="dialogMemberVisible = true">选择会员</el-button>
				</el-form-item>
				<el-form-item :label="t('idNumber')" prop="id_number">
					<el-input v-model.trim="formData.id_number" clearable :placeholder="t('idNumberPlaceholder')"
						class="input-width !w-[214px]" />
				</el-form-item>
				<el-form-item :label="t('idCardFront')" prop="id_card_front">
					<upload-image v-model="formData.id_card_front" @change="clearFieldError('id_card_front')" />
				</el-form-item>
				<el-form-item :label="t('idCardBack')" prop="id_card_back">
					<upload-image v-model="formData.id_card_back" @change="clearFieldError('id_card_back')" />
				</el-form-item>
				<el-form-item :label="t('licenseImg')" prop="license_img">
					<upload-image v-model="formData.license_img" @change="clearFieldError('license_img')" />
				</el-form-item>
				<el-form-item :label="t('serviceRatio')" prop="service_ratio">
					<el-input v-model.trim="formData.service_ratio" maxlength="6" clearable
						class="input-width !w-[150px]" :placeholder="t('serviceRatioPlaceholder')" max="100"
						@keyup="filterDigit($event)">
						<template #append>%</template>
					</el-input>
				</el-form-item>
				<!-- 地址选择区域 -->
				<el-form-item :label="t('storeAddress')" prop="address_area">
					<el-select v-model="formData.province_info" value-key="id" clearable class="w-[200px]"
						@change="checkCity">
						<el-option :label="t('provincePlaceholder')" value="" />
						<el-option v-for="(province, index) in areaList.province" :key="index" :label="province.name"
							:value="province" />
					</el-select>
					<el-select v-model="formData.city_info" value-key="id" clearable class="w-[200px] ml-3"
						@change="checkDistrict">
						<el-option :label="t('cityPlaceholder')" value="" />
						<el-option v-for="(city, index) in areaList.city" :key="index" :label="city.name"
							:value="city" />
					</el-select>
					<el-select v-model="formData.district_info" value-key="id" clearable class="w-[200px] ml-3"
						@change="check">
						<el-option :label="t('districtPlaceholder')" value="" />
						<el-option v-for="(district, index) in areaList.district" :key="index" :label="district.name"
							:value="district" />
					</el-select>
				</el-form-item>
				<!-- <el-form-item :label="t('deliveryTime')" prop="service_time">
				    <div>
						<div v-for="(timeRange, index) in formData.service_time" :key="index" class="mb-3">
							<el-time-picker v-model="timeRange.start_time" :placeholder="t('startTime')"
								format="HH:mm" value-format="HH:mm"
								:picker-options="{selectableRange: '00:00 - 23:59'}" />
							<span class="mx-2">-</span>
							<el-time-picker v-model="timeRange.end_time" :placeholder="t('endTime')" format="HH:mm"
								value-format="HH:mm" :picker-options="{selectableRange: '00:00 - 23:59'}" />
						</div>
				    </div>
				</el-form-item> -->
				<el-form-item :label="t('chooseMap')" prop="address">
					<div>
						<div>
							<el-input v-model.trim="formData.address" clearable
								:placeholder="t('addressDetailPlaceholder')" class="input-width" />
							<el-button class="ml-3" @click="searchOn">{{ t('search') }}</el-button>
						</div>
						<div class="mt-4">
							<div id="TxMap" class="map-item w-[800px] h-[500px]"></div>
						</div>
					</div>
				</el-form-item>
			</el-form>
		</el-card>
		<div class="fixed-footer-wrap " v-if="formData.audit_status != 1">
			<div class="fixed-footer">
				<el-button type="primary" @click="onSave(formRef)">{{ t('save') }}</el-button>
				<el-button @click="back()">{{ t('cancel') }}</el-button>
			</div>
		</div>
	</div>

	<!-- 会员选择对话框 -->
	<el-dialog v-model="dialogMemberVisible" title="请选择会员" width="720px">
		<el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
			<el-form :inline="true" :model="memberTable" ref="searchFormRef">
				<el-form-item :label="t('keyword')" prop="keyword">
					<el-input v-model.trim="memberTable.keyword" :placeholder="t('keywordPlaceholder')" />
				</el-form-item>
				<el-form-item>
					<el-button type="primary" @click="getMemberListFn()">{{ t('search') }}</el-button>
					<el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
				</el-form-item>
			</el-form>
		</el-card>
		<el-table :data="memberList.data" v-loading="memberList.loading" class="member-table">
			<template #empty>
				<span>{{ !memberList.loading ? t('emptyData') : '' }}</span>
			</template>
			<el-table-column prop="nickname" label="昵称" width="180" />
			<el-table-column prop="username" label="用户名" />
			<el-table-column prop="mobile" label="手机号" width="180" />
			<el-table-column :label="t('operation')" fixed="right" min-width="50" align="right">
				<template #default="{ row }">
					<el-button type="primary" link @click="confirmEvent(row)">确定</el-button>
				</template>
			</el-table-column>
		</el-table>
		<div class="mt-[16px] flex justify-end">
			<el-pagination v-model:current-page="memberList.page" v-model:page-size="memberList.limit"
				layout="total, sizes, prev, pager, next, jumper" :total="memberList.total"
				@size-change="getMemberListFn" @current-change="getMemberListFn" />
		</div>
	</el-dialog>

</template>

<script lang="ts" setup>
import { ref, reactive, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { t } from '@/lang'
import { ArrowLeft } from "@element-plus/icons-vue"
import { ElMessage, type FormInstance } from 'element-plus'
import {
	getApplyDetail,
	addstore,
	getMemberList,
} from '@/addon/home_service/api/store'
import cloneDeep from 'lodash-es/cloneDeep'
import { filterDigit, filterNumber } from '@/utils/common'
import { useRoute, useRouter } from 'vue-router'
import { getAreaListByPid, getAreatree, getAddressInfo, getContraryAddress, getMap } from '@/app/api/sys'

const memberTable: Record<string, any> = reactive({})
// 地图相关变量
let mapFn: any = null
let mapScriptLoaded = false

// 地区列表
interface areaType {
	province: any[],
	city: any[],
	district: any[]
}
const areaList = reactive<areaType>({
	province: [],
	city: [],
	district: []
})

// 初始化地区数据
const checkAreatress = () => {
	getAreatree(1).then(res => {
		areaList.province = res.data
	})
}
checkAreatress()

// 地区选择逻辑 - 调整为酒店页面同款逻辑
const checkCity = (province: any = {}) => {
	// 若未传参（回显时触发），用formData中已有的province_id
	if (Object.keys(province).length === 0) {
		province.id = formData.province_id
	} else {
		// 正常选择时更新formData
		formData.province_id = province.id
		formData.province_name = province.name
		formData.province_info = province // 绑定选项列表中的对象（关键）
		
	}

	if (!province.id) {
		// 清空下级数据
		formData.city_info = null
		formData.city_id = ''
		formData.city_name = ''
		formData.district_info = null
		formData.district_id = ''
		formData.district_name = ''
		areaList.city = []
		areaList.district = []
		return;
	}

	// 加载城市列表并更新到areaList（确保选项列表是最新的）
	getAreaListByPid(province.id).then(res => {
		areaList.city = res.data
		// 回显时自动匹配城市
		if (formData.city_id && res.data.length) {
			const matchedCity = res.data.find((item: any) => item.id === Number(formData.city_id))
			if (matchedCity) {
				formData.city_info = matchedCity // 绑定选项列表中的对象
			}
		}
	})
};

const checkDistrict = (city: any = {}) => {
	// 若未传参（回显时触发），用formData中已有的city_id
	if (Object.keys(city).length === 0) {
		city.id = formData.city_id
	} else {
		// 正常选择时更新formData
		formData.city_id = city.id
		formData.city_name = city.name
		formData.city_info = city // 绑定选项列表中的对象（关键）
		
	}

	if (!city.id) {
		// 清空下级数据
		formData.district_info = null
		formData.district_id = ''
		formData.district_name = ''
		areaList.district = []
		return;
	}

	// 加载区县列表并更新到areaList
	getAreaListByPid(city.id).then(res => {
		areaList.district = res.data
		// 回显时自动匹配区县
		if (formData.district_id && res.data.length) {
			const matchedDistrict = res.data.find((item: any) => item.id === Number(formData.district_id))
			if (matchedDistrict) {
				formData.district_info = matchedDistrict // 绑定选项列表中的对象
			}
		}
	})
};

const check = (district: any) => {
	if (!district) {
		formData.district_id = ''
		formData.district_name = ''
		return;
	}
	formData.district_id = district.id
	formData.district_name = district.name
	formData.district_info = district // 绑定选项列表中的对象（关键）
	// 清除地址验证错误
	clearFieldError('address_area')
};

// 地址搜索逻辑
const searchOn = () => {
	if (formData.province_id && formData.city_id && formData.district_id && formData.address) {
		formData.full_address = `${formData.province_name}${formData.city_name}${formData.district_name}${formData.address}`
		getAddressInfo({ address: formData.full_address }).then(res => {
			if (res.data.result) {
				formData.lat = res.data.result.location.lat
				formData.lng = res.data.result.location.lng
			} else {
				ElMessage.error(res.data.message)
			}
		})
	}
}

// 地图初始化
const initMap = () => {
	if (mapScriptLoaded) {
		createMapInstance()
		return
	}

	getMap().then(res => {
		const mapScript = document.createElement('script')
		mapScript.type = 'text/javascript'
		mapScript.src = 'https://map.qq.com/api/gljs?v=1.exp&key=' + res.data.key
		document.body.appendChild(mapScript)

		mapScript.onload = () => {
			mapScriptLoaded = true
			createMapInstance()
		}

		mapScript.onerror = () => {
			console.error('地图脚本加载失败')
			ElMessage.error('地图加载失败，请重试')
		}
	})
}

// 地图实例创建
let markerLayer: any = null
const createMapInstance = () => {
	if (mapFn) {
		mapFn.destroy()
	}

	const latitude = formData.lat || '39.90469'
	const longitude = formData.lng || '116.40717'
	const center = new window.TMap.LatLng(latitude, longitude)

	mapFn = new window.TMap.Map('TxMap', {
		center: center,
		zoom: 17,
		viewMode: '2D',
		showControl: true
	})

	markerLayer = new window.TMap.MultiMarker({
		id: 'marker-layer',
		map: mapFn,
		minimumClusterSize: 1
	})

	markerLayer.updateGeometries({
		id: 'store',
		position: center
	})

	// 地图点击事件
	mapFn.on('click', (evt: any) => {
		const evtModel = {
			lat: evt.latLng.getLat().toFixed(6),
			lng: evt.latLng.getLng().toFixed(6)
		}

		markerLayer.updateGeometries({
			id: 'store',
			position: evt.latLng
		})

		checkAddressInfo(evtModel.lat, evtModel.lng, 1)
	})

	// 地图 idle 事件
	mapFn.on('idle', () => {
		watch(() => [formData.lat, formData.lng], (newVal) => {
			if (newVal[0] && newVal[1]) {
				const latLng = new window.TMap.LatLng(newVal[0], newVal[1])
				mapFn.panTo(latLng)
				markerLayer.updateGeometries({
					id: 'store',
					position: latLng
				})
			}
		})
	})
}

// 逆地址解析
const checkAddressInfo = (lat: any, lng: any, type: any) => {
	getContraryAddress({
		location: lat + ',' + lng
	}).then(res => {
		if (res.data.result) {
			const addressComponent = res.data.result.address_component
			formData.province_name = addressComponent.province || ''
			formData.city_name = addressComponent.city || ''
			formData.district_name = addressComponent.district || ''

			// 根据名称匹配ID
			if (formData.province_name) {
				const matchedProvince = areaList.province.find(item => item.name === formData.province_name)
				if (matchedProvince) {
					formData.province_info = matchedProvince
					formData.province_id = matchedProvince.id
					getAreaListByPid(matchedProvince.id).then(res => {
						areaList.city = res.data
						const matchedCity = areaList.city.find(item => item.name === formData.city_name)
						if (matchedCity) {
							formData.city_info = matchedCity
							formData.city_id = matchedCity.id
							getAreaListByPid(matchedCity.id).then(res => {
								areaList.district = res.data
								const matchedDistrict = areaList.district.find(item => item.name === formData.district_name)
								if (matchedDistrict) {
									formData.district_info = matchedDistrict
									formData.district_id = matchedDistrict.id
								}
							})
						}
					})
				}
			}

			if (type == 1) {
				formData.address = res.data.result.formatted_addresses.recommend
				formData.full_address = formData.province_name + formData.city_name + formData.district_name + formData.address
				formData.lat = lat
				formData.lng = lng
			}
		} else {
			ElMessage({
				type: 'warning',
				message: res.data.message || '获取地址信息失败'
			})
		}
	}).catch(err => {
		console.error('地址解析失败:', err)
		ElMessage.error('获取地址信息失败')
	})
}

// 组件挂载时初始化地图
onMounted(async () => {
	if (id) {
		await setFormData(id) // 等待数据加载
	}
	nextTick(() => {
		initMap() // 初始化地图
	})
})

// 组件卸载时清理地图
onUnmounted(() => {
	if (mapFn) {
		mapFn.destroy()
		mapFn = null
	}
})

// 师傅业务逻辑
const route = useRoute()
const router = useRouter()
const id: number = parseInt(route.query.id as string) || 0
const loading = ref(false)
const props = { multiple: true, value: 'category_id', label: 'category_name' }

/**
 * 表单数据
 */
const initialFormData = {
	headimg: '',
	member_nickname: '',
	member_id: '',
	mobile: '',
	store_name: '',
	province_info: null,
	province_id: '',
	province_name: '',
	city_info: null,
	city_id: '',
	city_name: '',
	district_info: null,
	district_id: '',
	district_name: '',
	address: '',
	full_address: '',
	lng: 0,
	lat: 0,
	id_card_back: '',
	id_card_front: '',
	service_time: [
		{ start_time: '', end_time: '' }
	]
}
const formData: Record<string, any> = reactive({ ...initialFormData })
const inputTag = ref('')
const InputRef = ref()
const handleClose = (tag: string) => {
	formData.label.splice(formData.label.indexOf(tag), 1)
}
// 会员列表
const memberList = reactive({
	page: 1,
	limit: 10,
	total: 0,
	loading: false,
	data: []
})
const getMemberListFn = (page: number = 1) => {
	memberList.loading = true
	memberList.page = page
	getMemberList({
		page: memberList.page,
		limit: memberList.limit,
		...memberTable
	}).then((res: any) => {
		memberList.loading = false
		memberList.total = res.data.total
		memberList.data = res.data.data
	})
}
getMemberListFn()
const dialogMemberVisible = ref(false)
const confirmEvent = (val: any) => {
	formData.member_id = val.member_id
	formData.member_nickname = val.nickname
	dialogMemberVisible.value = false
	// 清除会员字段的验证错误
	clearFieldError('member_id')
}

const formRef = ref<FormInstance>()
// 正则表达式
const regExp = {
	required: /[\S]+/,
	number: /^\d{0,10}$/,
	digit: /^\d{0,10}(.?\d{0,2})$/,
	special: /^\d{0,10}(.?\d{0,3})$/
}
// 表单验证规则
const validatePhone = (rule, value, callback) => {
	const phonePattern = /^1[3456789]\d{9}$/
	if (!value) {
		return callback(new Error('请输入联系电话'))
	} else if (!phonePattern.test(value)) {
		return callback(new Error('联系电话格式不正确'))
	} else {
		return callback()
	}
}

const oneRateCheck = (rule: any, value: any, callback: any) => {
	if (!value) {
		return callback(new Error(t('oneRatePlaceholderOne')))
	} else if (!regExp.digit.test(value)) {
		return callback(new Error(t('oneRatePlaceholderTwo')))
	} else if (value >= 100) {
		return callback(new Error(t('oneRatePlaceholderThree')))
	} else if (value < 0) {
		return callback(new Error(t('oneRatePlaceholderFour')))
	} else {
		return callback()
	}
}

// 地址验证规则
const validatePass = (rule: any, value: any, callback: any) => {
	if (formData.province_id == '' || formData.city_id == '' || formData.district_id == '') {
		callback(new Error(t('storeAddressPlaceholder')))
	}
	callback()
}

const validateIdnumber = (rule, value, callback) => {
	if (!value) {
		return callback(new Error('请输入身份证号码'))
	} else if (value.length != 18) {
		return callback(new Error('身份证号码格式不正确'))
	} else {
		return callback()
	}
}
const formRules = computed(() => {
	return {
		store_name: [{ required: true, message: t('storeNamePlaceholder'), trigger: 'blur' }],
		headimg: [{ required: true, message: t('headimgPlaceholder'), trigger: 'change' }],
		license_img: [{ required: true, message: t('licenseImgPlaceholder'), trigger: 'change' }],
		contact_name: [{ required: true, message: t('userNamePlaceholder'), trigger: 'blur' }],
		member_id: [{ required: true, message: t('memberPlaceholder'), trigger: 'blur' }],
		mobile: [{ required: true, validator: validatePhone, trigger: 'blur' }],
		service_ratio: [{ required: true, validator: oneRateCheck, trigger: 'blur' }],
		// 地址相关验证
		address_area: [{ required: true, validator: validatePass, trigger: 'blur' }],
		id_card_back: [{ required: true, message: t('idCardBackPlaceholder'), trigger: 'blur' }],
		id_card_front: [{ required: true, message: t('idCardFrontPlaceholder'), trigger: 'blur' }],
		id_number: [{ required: true, validator: validateIdnumber, trigger: 'blur' }],
		service_time: [
			{
				validator: (rule: any, value: any, callback: any) => {
					if (!value || value.length === 0) {
						return callback(new Error(t('tradeTimePlaceholderTwo')))
					}
					for (let i = 0; i < value.length; i++) {
						const timeRange = value[i]
						if (!timeRange.start_time || !timeRange.end_time) {
							return callback(new Error(t('tradeTimePlaceholderTwo')))
						}
						// 结束时间不能小于或等于开始时间
						if (timeRange.end_time <= timeRange.start_time) {
							return callback(new Error(t('tradeTimePlaceholderFour')))
						}
						// 确保后一个时间段的开始时间不能小于前一个时间段的结束时间
						if (i > 0 && value[i].start_time < value[i - 1].end_time) {
							return callback(new Error(t('tradeTimePlaceholderFive')))
						}
					}
					callback()
				},
				trigger: 'change',
				required: true
			}
		],
	}
})

// 保存前检查省市区ID
const onSave = async (formEl: FormInstance | undefined, status) => {
	// 防止重复提交和表单实例不存在的情况
	if (loading.value || !formEl) return


	// 执行表单验证
	await formEl.validate(async (valid) => {
		if (valid) {
			loading.value = true
			try {
				// 深拷贝表单数据，避免修改原始数据
				const submitData = cloneDeep(formData)

				// 处理分类ID：数组转逗号分隔字符串
				if (Array.isArray(submitData.category_id)) {
					// 扁平化数组并过滤空值
					submitData.category_id = submitData.category_id
						.flat()
						.filter(Boolean)
						.join(',')
				}

				// 处理费率默认值

				// 确保经纬度字段正确（与接口参数匹配）
				submitData.lng = submitData.lng || ''
				submitData.lat = submitData.lat || ''
				// 过滤不需要提交的辅助字段
				const {
					province_info,
					city_info,
					district_info,
					province_name,
					city_name,
					district_name,
					member_nickname,
					store_name,
					...params
				} = submitData
				const saveFunction = addstore
				params.store_name = submitData.store_name
				params.service_ratio = submitData.service_ratio
				// 提交数据
				const response = await saveFunction(params)
				// 处理成功结果
				history.back()
			} catch (error: any) {
				// 处理错误
			} finally {
				// 无论成功失败都关闭加载状态
				loading.value = false
			}
		}
	})
}

const back = () => {
	history.back()
}

// 清除字段验证错误
const clearFieldError = (fieldName: string) => {
	if (formRef.value) {
		formRef.value.clearValidate(fieldName)
	}
}

</script>

<style lang="scss" scoped>
.member-table :deep(.cell) {
	padding: 0 12px !important;
}

#TxMap {
	border: 1px solid #e4e4e4;
	border-radius: 4px;
}

.dialog-footer {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 20px;
}

.input-width {
	width: 500px;
}

.fixed-footer-wrap .fixed-footer {
	z-index: 1999 !important;
}
</style>