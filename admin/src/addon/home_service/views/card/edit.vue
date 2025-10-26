<template>
	<div class="main-container">
		<el-card class="card !border-none mb-[15px]" shadow="never">
			<el-page-header :content="id ? t('editCard') : t('addCard')" :icon="ArrowLeft" @back="back" />
		</el-card>
		<el-card class="box-card !border-none" shadow="never">
			<el-tabs v-model="activeName">
				<el-tab-pane :label="t('cardInfo')" name="basic">
					<el-form :model="formData" label-width="100px" ref="basicFormRef" :rules="formRules"
						class="page-form">
						<el-form-item :label="t('cardName')" prop="card_name">
							<el-input v-model.trim="formData.card_name" clearable
								:placeholder="t('cardNamePlaceholder')" class="input-width" maxlength="60" />
						</el-form-item>
						<el-form-item :label="t('status')">
							<el-radio-group v-model="formData.status" class="ml-4">
								<el-radio label="1">{{ t('cardStatusUp') }}</el-radio>
								<el-radio label="0">{{ t('cardStatusDown') }}</el-radio>
							</el-radio-group>
						</el-form-item>
						<el-form-item :label="t('time')">
							<el-radio-group v-model="formData.valid_type" class="ml-4">
								<el-radio :label="item.key" v-for="(item,index) in validTypeList"
									:key="index">{{ item.title}}</el-radio>
							</el-radio-group>
						</el-form-item>
						<el-form-item :label="t('cardImage')" prop="card_image">
							<div>
								<upload-image v-model="formData.card_image" :limit="1" />
								<div class="text-[12px] text-[#999]">建议上传图片的大小为150px*150px</div>
							</div>
						</el-form-item>
						<el-form-item :label="t('guarantee')" prop="guarantee_id">
							<el-select class="input-width" v-model="formData.guarantee_id" multiple clearable>
								<el-option v-for="item in guaranteeList" :key="item.id" :label="item.guarantee_title"
									:value="item.id.toString()" />
							</el-select>
						</el-form-item>
						<el-form-item :label="t('sale')" prop="virtual_sale_num">
							<el-input v-model.trim="formData.virtually_sale" clearable
								:placeholder="t('salePlacrholder')" class="input-width" maxlength="10" />
						</el-form-item>
						<el-form-item :label="t('sort')" prop="sort">
							<el-input v-model.trim="formData.sort" clearable :placeholder="t('sortPlaceholder')"
								class="input-width" show-word-limit maxlength="8" />
						</el-form-item>
						<el-form-item :label="t('poster')">
							<el-select v-model="formData.poster_id" :placeholder="t('posterPlaceholder')" clearable>
								<el-option v-for="item in posterOptions" :key="item.id" :label="item.name"
									:value="item.id" />
							</el-select>
							<div class="ml-[10px]">
								<span class="cursor-pointer text-primary mr-[10px]"
									@click="refreshGoodsPoster(true)">{{ t('refresh') }}</span>
								<span class="cursor-pointer text-primary"
									@click="toPosterEvent">{{ t('addGoodsPoster') }}</span>
							</div>
						</el-form-item>
						<!-- <el-form-item :label="t('memberDiscount')">
							<div>
								<el-radio-group v-model="formData.member_discount">
									<el-radio label="">{{ t('nonparticipation') }}</el-radio>
									<el-radio label="discount">{{ t('discount') }}</el-radio>
									<el-radio label="fixed_price">{{ t('goodsFixedPrice') }}</el-radio>
								</el-radio-group>
								<div class="text-[12px] text-[#999] leading-[20px]"
									v-if="formData.member_discount == 'discount'">{{ t('discountHint') }}</div>
								<div class="text-[12px] text-[#999] leading-[20px]"
									v-if="formData.member_discount == 'fixed_price'">{{ t('fixedPriceHint') }}</div>
							</div>
						</el-form-item> -->
					</el-form>
				</el-tab-pane>
				<el-tab-pane :label="t('cardGoods')" name="goods_charge">
					<el-form :model="formData" label-width="100px" ref="goodsFormRef" class="page-form">
						<el-form-item :label="t('selectServices')" prop="goods_sku_data">
							<div>
								<el-table :data="formData.goods_sku_data" border>
									<el-table-column prop="goods_name" :label="t('goodsName')" width="150" />
									<el-table-column prop="sku_name" :label="t('skuName')" width="150" />
									<el-table-column prop="sku_unit" :label="t('skuUnit')" width="100" />
									<el-table-column prop="original_price" :label="t('originalPrice')" width="100" />
									<el-table-column prop="" :label="t('cardOncePrcie')" width="180">
										<template #default="{ row }">
											<el-tooltip :content="t('priceRequired')" placement="top" :disabled="!row.priceError">
												<el-input v-model="row.price" :placeholder="t('cardOncePrciePlaceholder')"
													:class="{'error-input': row.priceError}"
													clearable @input="calculateTotalPrice" @blur="validateGoodsItem(row)" />
											</el-tooltip>
										</template>
									</el-table-column>
									<el-table-column prop="" :label="t('cardNum')" width="180">
										<template #default="{ row }">
											<el-tooltip :content="t('countRequired')" placement="top" :disabled="!row.countError">
												<el-input v-model="row.max_use_times" :placeholder="t('cardNumPlaceholder')"
													:class="{'error-input': row.countError}"
													clearable @input="calculateTotalPrice" @blur="validateGoodsItem(row)" />
											</el-tooltip>
										</template>
									</el-table-column>
									<el-table-column :label="t('operation')" min-width="120">
										<template #default="{ $index }">
											<el-button type="primary" link
												@click="deleteEvent($index)">{{ t('delete') }}</el-button>
										</template>
									</el-table-column>
								</el-table>
								<el-form-item prop="goods_data" class="mt-4">
									<goods-select-popup ref="goodsSelectPopupRef" v-model="formData.goods_ids"
										mode="sku" @goodsSelect="goodsSelect" :min="1" :max="99" />
								</el-form-item>
							</div>
						</el-form-item>
						<el-form-item :label="t('originalPrice')" prop="originalTotalPrice">
							<el-input v-model="formData.originalTotalPrice" clearable class="!w-[160px]" disabled>
								<template #append>
									<span>元</span>
								</template>
							</el-input>
						</el-form-item>
						<el-form-item :label="t('cardPrcie')" prop="cardTotalPrice">
							<el-input v-model="formData.cardTotalPrice" clearable class="!w-[160px]" disabled>
								<template #append>
									<span>元</span>
								</template>
							</el-input>
						</el-form-item>
					</el-form>
				</el-tab-pane>
				<el-tab-pane :label="t('cardDetail')" name="detail">
					<el-form :model="formData" label-width="100px" ref="detailFormRef" :rules="formRules"
						class="page-form">
						<el-form-item :label="t('cardDetailText')" prop="card_content">
							<editor v-model="formData.card_content" />
						</el-form-item>
					</el-form>
				</el-tab-pane>
			</el-tabs>
		</el-card>
		<div class="fixed-footer-wrap">
			<div class="fixed-footer">
				<el-button type="primary" @click="onSave()" :loading="loading">{{ t('save') }}</el-button>
				<el-button @click="back()">{{ t('cancel') }}</el-button>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
	import { ref, reactive, computed } from 'vue'
	import { t } from '@/lang'
	import { cloneDeep } from 'lodash-es'
	import type { FormInstance } from 'element-plus'
	import { ArrowLeft } from '@element-plus/icons-vue'
	import { addGoods, editGoods, getGoodsDetail, getvalidType } from '@/addon/home_service/api/card'
	import goodsSelectPopup from '@/addon/home_service/views/card/components/goods-select-popup.vue'
	import { useRoute, useRouter } from 'vue-router'
	import { getPosterList } from '@/app/api/poster'
	import {  getGoodsGuarantee } from '@/addon/home_service/api/goods'
	import { ElMessage } from 'element-plus'
	// 假设upload-image和editor组件已正确引入

	const route = useRoute()
	const router = useRouter()
	const id : number = parseInt(route.query.id as string) || 0
	const loading = ref(false)
	const activeName = ref('basic')
	const validTypeList = ref([])
	const guaranteeList = ref([])
	const getGoodsGuaranteeFn = () => {
		getGoodsGuarantee().then((res) => {
			guaranteeList.value = res.data.data
		})
	}
	getGoodsGuaranteeFn()
	// 计算总价的方法
	const calculateTotalPrice = () => {
		let originalTotal = 0
		let cardTotal = 0

		formData.goods_sku_data.forEach(item => {
			// 确保数值有效
			const originalPrice = Number(item.original_price) || 0
			const price = Number(item.price) || 0
			const maxUseTimes = Number(item.max_use_times) || 0

			originalTotal += originalPrice * maxUseTimes
			cardTotal += price * maxUseTimes
		})

		// 保留两位小数
		formData.originalTotalPrice = originalTotal.toFixed(2)
		formData.cardTotalPrice = cardTotal.toFixed(2)
	}

	// 验证单个服务项的价格和次数
	const validateGoodsItem = (row : any) => {
		row.priceError = !row.price || isNaN(Number(row.price)) || Number(row.price) <= 0
		row.countError = !row.max_use_times || isNaN(Number(row.max_use_times)) || Number(row.max_use_times) <= 0
		return !row.priceError && !row.countError
	}

	// 验证所有服务项
	const validateAllGoodsItems = () => {
		let allValid = true
		formData.goods_sku_data.forEach((item : any) => {
			if (!validateGoodsItem(item)) {
				allValid = false
			}
		})
		return allValid
	}

	const getvalidTypeFn = () => {
		getvalidType().then((res) => {
			Object.keys(res.data).forEach((item) => {
				validTypeList.value.push({ key: item, title: res.data[item] })
			})
		}).catch(err => {
		})
	}
	getvalidTypeFn()

	/**
	 * 表单数据
	 */
	const initialFormData = {
		guarantee_id: [],//服务保障
		goods_id: '',
		card_name: '', // 项目名称
		card_image: '', // 项目图片
		virtually_sale: '', // 虚拟销量
		sort: '', // 排序
		status: '1', // 状态 0下架 1上架
		member_level: '0', // 会员等级
		valid_type: 'monthly_card',
		sku_unit: '次', // 单规格单位
		goods_sku_data: [], // 多规格
		card_content: '',
		poster_id: '',
		goods_ids: [], // 存储选中服务的ID
		originalTotalPrice: '0.00', // 原价总计
		cardTotalPrice: '0.00',// 卡价总计
		member_discount: ""
	}
	const formData : Record<string, any> = reactive({ ...initialFormData })

	// 加载详情数据
	const setFormData = async (id : number = 0) => {
		Object.assign(formData, initialFormData)
		try {
			const res = await getGoodsDetail({ card_id: id })
			const data = res.data.goods_info
			Object.keys(formData).forEach((key : string) => {
				if (data[key] !== undefined) formData[key] = data[key]
			})
			if (data.guarantee_id) {
				formData.guarantee_id = data.guarantee_id.split(',').map(id => id.toString());
			}
			data.sku_list.forEach((item,index)=>{
				formData.goods_ids.push(item.goods_sku_id)
				item.sku_id = item.goods_sku_id
			})
			formData.goods_sku_data = data.sku_list
			// 为已有的服务项添加验证相关属性
			if (formData.goods_sku_data && formData.goods_sku_data.length) {
				formData.goods_sku_data.forEach((item : any) => {
					item.priceError = false
					item.countError = false
				})
			}
			// 计算总价
			calculateTotalPrice()
		} catch (err) {
		}
	}
	if (id) setFormData(id)


	// 海报列表下拉框
	const posterOptions = reactive([])

	// 跳转到海报列表，添加海报
	const toPosterEvent = () => {
		const url = router.resolve({
			path: '/poster/list'
		})
		window.open(url.href)
	}

	// 刷新服务海报
	const refreshGoodsPoster = (bool = false) => {
		getPosterList({
			type: 'home_service_goods'
		}).then((res) => {
			const data = res.data
			if (data) {
				posterOptions.splice(0, posterOptions.length, ...data)
				if (bool) {
					ElMessage.success(t('refreshSuccess'))
				}
			}
		}).catch(err => {
		})
	}

	refreshGoodsPoster()

	// 表单引用
	const basicFormRef = ref<FormInstance>()
	const goodsFormRef = ref<FormInstance>()
	const detailFormRef = ref<FormInstance>()

	// 选择服务 - 处理新增和取消选中的情况
	const goodsSelect = (value: any) => {
	  if (!value) {
	    formData.goods_sku_data = [];
	    formData.goods_ids = [];
	    calculateTotalPrice();
	    return;
	  }
	  // 关键修改：将sku_id统一转为字符串类型，避免类型不匹配
	  const newSelectedIds = Object.values(value).map((item: any) => String(item.sku_id));
	  // 1. 保留仍被选中的服务（用字符串类型比较）
	  const remainingItems = formData.goods_sku_data.filter(
	    (item: any) => newSelectedIds.includes(String(item.sku_id))
	  );
	  // 2. 添加新选择的服务（去重）
	  Object.values(value).forEach((item: any) => {
	    const skuIdStr = String(item.sku_id);
	    // 检查当前服务是否已存在（用字符串类型比较）
	    const exists = remainingItems.some((i: any) => String(i.sku_id) === skuIdStr);
	    if (!exists) {
	      remainingItems.push({
	        ...item,
	        original_price: item.price, // 保存原始价格
	        price: '', // 新服务清空单价
	        max_use_times: '', // 新服务清空次数
	        priceError: false,
	        countError: false
	      });
	    }
	  });
	  // 3. 更新数据
	  formData.goods_sku_data = remainingItems;
	  formData.goods_ids = newSelectedIds.map(Number); // 转为数字存入goods_ids（保持与后端一致）
	  // 4. 重新计算总价
	  calculateTotalPrice();
	};
	// 删除服务 - 同步删除goods_ids中的数据
	const deleteEvent = (index : number) => {
		// 获取要删除的服务ID
		const deletedId = formData.goods_sku_data[index].sku_id

		// 1. 从表格数据中删除
		formData.goods_sku_data.splice(index, 1)

		// 2. 从goods_ids中删除对应的ID
		formData.goods_ids = formData.goods_ids.filter((id : any) => id !== deletedId)

		// 3. 重新计算总价
		calculateTotalPrice()
	}

	// 正则表达式
	const regExp = {
		required: /[\S]+/,
		number: /^\d{0,10}$/,
		digit: /^\d{0,10}(?:\.\d{1,2})?$/
	}

	// 表单验证规则
	const formRules = computed(() => {
		return {
			card_name: [{
				required: true,
				message: t('cardNamePlaceholder'),
				trigger: 'blur'
			}],
			card_content: [{
				required: true,
				message: t('pleaseCompleteDetailInfo'),
				trigger: 'blur'
			}],
			card_image: [{
				required: true,
				message: t('goodsImagePlaceholder'),
				trigger: 'change'
			}],
			guarantee_id: [
				{
					required: true,  // 若服务保障非必填，可改为false
					message: t('selectGuarantee'),  // 语言包加“请选择服务保障”
					trigger: 'change'
				}
			],
			sort: [{
				trigger: 'blur',
				validator: (rule : any, value : any, callback : any) => {
					if (!value) {
						callback();
						return;
					}
					if (isNaN(value) || !regExp.number.test(value)) {
						callback(new Error(t('sortTips')))
					} else {
						callback()
					}
				}
			}],
			originalTotalPrice: [{
				required: true,
				message: t('pleaseCompleteTableData'),
				trigger: 'change'
			}],
			cardTotalPrice: [{
				required: true,
				message: t('pleaseCompleteTableData'),
				trigger: 'change'
			}],
			goods_content: [{
				required: true,
				message: t('pleaseCompleteTableData'),
				trigger: 'input'
			}],
			// 服务选择验证
			goods_sku_data: [{
				required: true,
				validator: (rule : any, value : any, callback : any) => {
					if (!value || value.length === 0) {
						callback(new Error(t('pleaseSelectGoods')));
					} else {
						// 检查是否所有服务都填写了价格和次数
						const allValid = value.every((item : any) => {
							return item.price && !isNaN(Number(item.price)) && Number(item.price) > 0 &&
								item.max_use_times && !isNaN(Number(item.max_use_times)) && Number(item.max_use_times) > 0;
						});

						if (!allValid) {
							callback(new Error(t('pleaseCompleteGoodsInfo')));
						} else {
							callback();
						}
					}
				},
				trigger: ['change', 'blur']
			}]
		}
	})

	// 表单验证
	const verify = (callback : any) => {
		// 分别验证三个表单
		const validateBasic = new Promise(resolve => {
			basicFormRef.value?.validate(valid => resolve(valid));
		});

		const validateGoods = new Promise(resolve => {
			goodsFormRef.value?.validate(valid => resolve(valid));
		});

		const validateDetail = new Promise(resolve => {
			detailFormRef.value?.validate(valid => resolve(valid));
		});

		// 执行所有验证
		Promise.all([validateBasic, validateGoods, validateDetail]).then(([basicValid, goodsValid, detailValid]) => {
			// 额外验证服务项的价格和次数
			const allGoodsItemsValid = formData.goods_sku_data.length > 0 ? validateAllGoodsItems() : false;
			// 验证未选择服务的情况
			const hasGoods = formData.goods_sku_data.length > 0;

			// 综合判断所有验证结果
			const allValid =
				basicValid &&
				goodsValid &&
				detailValid &&
				allGoodsItemsValid &&
				hasGoods;

			// 根据验证结果切换到对应的标签页
			if (!basicValid) {
				activeName.value = 'basic';
				ElMessage.error(t('请完善基础信息'));
			} else if (!hasGoods || !allGoodsItemsValid || !goodsValid) {
				activeName.value = 'goods_charge';
				if (!hasGoods) ElMessage.error(t('pleaseSelectGoods'));
				else if (!allGoodsItemsValid) ElMessage.error(t('pleaseCompleteGoodsInfo'));
			} else if (!detailValid) {
				activeName.value = 'detail';
				ElMessage.error(t('pleaseCompleteDetailInfo'));
			}

			if (allValid && callback) {
				callback();
			}
		});
	}

	// 保存表单
	const onSave = () => {
		verify(() => {
			loading.value = true
			const data = cloneDeep(formData)
			// 处理需要序列化的数据
			if (Array.isArray(data.guarantee_id) && data.guarantee_id.length > 0) {
				data.guarantee_id = data.guarantee_id.join(','); // 数组转字符串：["1","2"] → "1,2"
			} else {
				data.guarantee_id = ''; // 未选择时传空字符串
			}
			let arr = []
			data.goods_sku_data.forEach((item,index)=>{
				let obj = {
					goods_name:item.goods_name,
					sku_name:item.sku_name,
					sku_image:item.sku_image,
					price:item.price,
					original_price:item.original_price,
					sku_unit:item.sku_unit,
					max_use_times:item.max_use_times,
					goods_sku_id:item.sku_id,
					goods_id:item.goods_id
				}
				arr.push(obj)
			})
			data.goods_sku_data = JSON.stringify(arr)
			data.card_id = id
			// 根据是否有id判断是新增还是编辑
			const saveFn = id ? editGoods : addGoods

			saveFn(data)
				.then(() => {
					loading.value = false
					history.back()
				})
				.catch(err => {
					loading.value = false
				})
		})
	}

	// 返回上一页
	const back = () => {
		history.back()
	}
</script>

<style lang="scss" scoped>
	// 表格文本超出一行显示省略号
	:deep(.el-table__cell .cell) {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		padding: 12px;
	}

	// 表单样式
	.page-form {
		padding: 20px;
		background-color: #fff;
	}

	.input-width {
		width: 300px;
	}

	// 固定底部样式
	.fixed-footer-wrap {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background-color: #fff;
		border-top: 1px solid #e5e7eb;
		padding: 10px 20px;
		z-index: 100;
	}

	.fixed-footer {
		text-align: right;
	}

	.fixed-footer button {
		margin-left: 10px;
	}

	// 错误提示文字样式（仅显示文字，无输入框边框变化）
	.error-text {
		color: #f56c6c;
		font-size: 12px;
		margin-top: 4px;
	}

	// 错误输入框样式
	:deep(.error-input .el-input__wrapper) {
		box-shadow: 0 0 0 1px #f56c6c inset !important;
	}
	
	:deep(.error-input .el-input__wrapper:hover) {
		box-shadow: 0 0 0 1px #f56c6c inset !important;
	}
</style>