<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex mb-4 justify-between items-center">
				<span class="text-lg">{{ t('cityStrategy') }}</span>
				<el-button type="primary" @click="handleAdd">{{ t('add') }}</el-button>
			</div>

			<!-- 策略表格 -->
			<el-table :data="tableData" row-key="id" size="large" v-loading="loading">
				<el-table-column prop="provinceName" :label="t('province')" min-width="120" />
				<el-table-column prop="cityName" :label="t('city')" min-width="120" />
				<el-table-column prop="value" :label="t('amount')" min-width="100">

					<template #default="{ row }">
						{{ row.value }}{{ row.way == '+' ? t('yuan') : '%' }}

					</template>
				</el-table-column>
				<el-table-column prop="way" :label="t('strategy')" min-width="100">
					<template #default="{ row }">
						<el-tag :type="row.way === '+' ? 'success' : 'primary'" effect="dark">{{ row.way === '+' ? '加' :
							'乘' }}（{{ row.way }}）</el-tag>
					</template>
				</el-table-column>
				<el-table-column :label="t('operation')" fixed="right" min-width="100">
					<template #default="{ row }">
						<el-button type="primary" link @click="handleEdit(row)">{{ t('edit') }}</el-button>
						<el-button type="primary" link @click="handleDelete(row)">{{ t('delete') }}</el-button>
					</template>
				</el-table-column>
			</el-table>
		</el-card>

		<!-- 添加/编辑弹窗 -->
		<el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" @close="handleDialogClose">
			<el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form">
				<!-- 省市区选择（仅保留省市两级） -->
				<el-form-item :label="t('storeAddress')" prop="address_area">
					<el-select v-model="formData.province_info" value-key="id" clearable class="input-width !w-[214px]"
						:disabled="currentId != ''" @change="checkCity">
						<el-option :label="t('provincePlaceholder')" value="" />
						<el-option v-for="(province, index) in areaList.province" :key="index" :label="province.name"
							:value="province" />
					</el-select>
					<el-select v-model="formData.city_info" value-key="id" clearable class="input-width !w-[214px] ml-3"
						@change="checkCitySelected" :disabled="!formData.province_info || currentId != ''">
						<el-option :label="t('cityPlaceholder')" value="" />
						<el-option v-for="(city, index) in areaList.city" :key="index" :label="city.name"
							:value="city" />
					</el-select>
				</el-form-item>

				<!-- 地区金额 -->
				<el-form-item :label="formData.way == '+' ? t('amount') : '比例'" prop="value">
					<el-input v-model="formData.value" clearable class="input-width !w-[214px]" type="number" :min="0"
						placeholder="请输入金额" /><span class="ml-2">{{ formData.way == '+' ? t('yuan') : '%' }}</span>
				</el-form-item>

				<!-- 策略选择 -->
				<el-form-item :label="t('strategy')" prop="way">
					<el-select v-model="formData.way" clearable placeholder="请选择策略" class="input-width !w-[214px]">
						<el-option label="加（+）" value="+"></el-option>
						<el-option label="乘（*）" value="*"></el-option>
					</el-select>
				</el-form-item>
			</el-form>
			<template #footer>
				<el-button @click="dialogVisible = false">{{ t('cancel') }}</el-button>
				<el-button type="primary" @click="handleSubmit">{{ t('confirm') }}</el-button>
			</template>
		</el-dialog>
	</div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { t } from '@/lang'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { getCityStrategy, saveCityStrategy, editCityStrategy, deleteCityStrategy } from '@/addon/home_service/api/city'
import { getAreaListByPid, getAreatree } from '@/app/api/sys'

// 表格数据相关
const tableData = ref<any[]>([])
const loading = ref(false)

// 省市区相关（仅省市两级）
interface AreaType {
	province: any[],
	city: any[]
}
const areaList = reactive<AreaType>({
	province: [],
	city: []
})

// 弹窗相关
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref<FormInstance>()
const currentId = ref('')

// 表单数据
const formData = reactive({
	province_info: null,
	province_id: '',
	province_name: '',
	city_info: null,
	city_id: '',
	city_name: '',
	value: 0,
	way: ''
})

// 表单验证规则
const formRules = computed(() => ({
	address_area: [
		{
			validator: (rule: any, value: any, callback: any) => {
				// 编辑模式时跳过校验
				if (currentId.value != '') {
					callback()
					return
				}
				// 新增模式时校验省市是否都已选择
				if (!formData.province_id || !formData.city_id) {
					return callback(new Error(t('pleaseSelectProvinceAndCity')))
				}
				callback()
			},
			trigger: 'change',
			required: currentId.value == ''
		}
	],
	value: [
		{ required: true, message: t('pleaseInputAmount'), trigger: 'blur' },
		{ way: 'number', min: 0, message: t('amountMustBeGreaterThanZero'), trigger: 'blur' }
	],
	way: [
		{ required: true, message: t('pleaseSelectStrategy'), trigger: 'change' }
	]
}))

// 初始化省数据
const initAreaData = async () => {
	try {
		const res = await getAreatree(1)
		areaList.province = res.data
	} catch (error) {
		console.error('获取地区数据失败:', error)
		ElMessage.error(t('loadAreaDataFailed'))
	}
}

// 城市选择处理
const checkCitySelected = (city: any) => {
	if (!city) {
		formData.city_id = ''
		formData.city_name = ''
		return
	}
	formData.city_id = city.id
	formData.city_name = city.name
	formData.city_info = city
}

// 获取策略列表数据
const fetchStrategyData = async () => {
	try {
		loading.value = true
		const res = await getCityStrategy()
		// 格式化表格数据
		tableData.value = res.data.data.map((item: any) => ({
			id: item.id,
			provinceName: item.province?.name || '',
			cityName: item.city?.name || '',
			value: item.value,
			way: item.way,
			province_id: item.province?.id,
			city_id: item.city?.id
		}))
	} catch (error) {
		console.error('获取策略数据失败:', error)
		ElMessage.error(t('loadStrategyDataFailed'))
	} finally {
		loading.value = false
	}
}

// 打开添加弹窗
const handleAdd = () => {
	dialogTitle.value = t('addCityStrategy')
	resetForm()
	dialogVisible.value = true
	currentId.value = ''
}

// 打开编辑弹窗
const handleEdit = async (row: any) => {
	dialogTitle.value = t("editCityStrategy")
	resetForm()
	currentId.value = row.id

	// 反向赋值基础数据
	formData.value = row.value
	formData.way = row.way
	formData.province_id = row.province_id
	formData.city_id = row.city_id

	// 匹配并回显省份
	const matchedProvince = areaList.province.find(p => p.id === row.province_id)
	if (matchedProvince) {
		formData.province_info = matchedProvince
		formData.province_name = matchedProvince.name

		//  await 等待城市数据加载完成
		await checkCity(matchedProvince)

		// 回显城市（加 setTimeout 确保城市列表渲染后再匹配）
		setTimeout(() => {
			const matchedCity = areaList.city.find(c => c.id === row.city_id)
			if (matchedCity) {
				formData.city_info = matchedCity
				formData.city_name = matchedCity.name
			}
		}, 300)
	}

	dialogVisible.value = true
};
const checkCity = async (province: any = {}) => {
	if (Object.keys(province).length === 0) {
		province.id = formData.province_id
	} else {
		formData.province_id = province.id
		formData.province_name = province.name
		formData.province_info = province
	}

	// 重置城市相关数据
	formData.city_info = null
	formData.city_id = ""
	formData.city_name = ""
	areaList.city = []

	if (!province.id) return

	// 异步获取城市列表（用 await 替代 .then）
	const res = await getAreaListByPid(province.id)
	areaList.city = res.data

	// 回显城市（若有历史 city_id）
	if (formData.city_id && res.data.length) {
		const matchedCity = res.data.find((item: any) => item.id === Number(formData.city_id))
		if (matchedCity) {
			formData.city_info = matchedCity
		}
	}
}

// 提交表单
const handleSubmit = async () => {
	if (!formRef.value) return
	formRef.value.validate(async (valid) => {
		console.log('valid', valid)
		if (valid) {
			try {
				// 构造提交数据（根据接口要求调整）
				const submitData = {
					id: currentId.value || undefined, // 编辑时有id，新增时无
					province_id: formData.province_id,
					city_id: formData.city_id,
					value: formData.value,
					way: formData.way
				}
				var postdm = submitData.id ? editCityStrategy : saveCityStrategy
				await postdm(submitData)
				// 关闭弹窗并刷新列表
				dialogVisible.value = false
				fetchStrategyData()
			} catch (error) {
				console.error('提交失败:', error)
			}
		}
	})

	// try {
	// 	await formRef.value.validate()

	// 	// 构造提交数据（根据接口要求调整）
	// 	const submitData = {
	// 		id: currentId.value || undefined, // 编辑时有id，新增时无
	// 		province_id: formData.province_id,
	// 		city_id: formData.city_id,
	// 		value: formData.value,
	// 		way: formData.way
	// 	}
	// 	var postdm = submitData.id?editCityStrategy:saveCityStrategy
	// 	await postdm(submitData)
	// 	// await saveCityStrategy(submitData)
	// 	ElMessage.success(currentId.value ? t('editSuccess') : t('addSuccess'))
	// 	dialogVisible.value = false
	// 	fetchStrategyData()
	// } catch (error) {
	// 	console.error('提交失败:', error)
	// 	return
	// }
}

// 重置表单
const resetForm = () => {
	formData.province_info = null
	formData.province_id = ''
	formData.province_name = ''
	formData.city_info = null
	formData.city_id = ''
	formData.city_name = ''
	formData.value = 0
	formData.way = ''

	areaList.city = []

	if (formRef.value) {
		formRef.value.resetFields()
	}
}

// 关闭弹窗
const handleDialogClose = () => {
	resetForm()
	dialogVisible.value = false
}

// 初始化
onMounted(() => {
	initAreaData()
	fetchStrategyData()
})
// 删除
const handleDelete = async (row: any) => {
	ElMessageBox.confirm(
		t('confirmDelete'),
		t('warning'),
		{
			confirmButtonText: t('confirm'),
			cancelButtonText: t('cancel'),
			type: 'warning',
		}
	).then(async () => {
		try {
			await deleteCityStrategy(row.id)

			fetchStrategyData()
		} catch (error) {
			return
		}
	})
}
</script>

<style lang="scss" scoped></style>