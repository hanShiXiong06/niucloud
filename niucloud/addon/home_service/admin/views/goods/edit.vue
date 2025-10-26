<template>
	<div class="main-container" v-loading="loading">
		<el-card class="card !border-none mb-[15px]" shadow="never">
			<el-page-header :content="id ? t('editGoods') : t('addGoods')" :icon="ArrowLeft" @back="back" />
		</el-card>
		<el-card class="box-card !border-none" shadow="never">
			<el-tabs v-model="activeName">
				<el-tab-pane :label="t('basicInfoTab')" name="basic">
					<el-form :model="formData" label-width="100px" ref="basicFormRef" :rules="formRules"
						class="page-form">
						<el-form-item :label="t('goodsName')" prop="goods_name">
							<el-input v-model.trim="formData.goods_name" clearable
								:placeholder="t('goodsNamePlaceholder')" class="input-width" maxlength="60" />
						</el-form-item>
						<el-form-item :label="t('goodsSubTitle')" prop="goods_subtitle">
							<el-input v-model.trim="formData.goods_subtitle" clearable
								:placeholder="t('goodsSubTitlePlaceholder')" class="input-width" maxlength="60" />
						</el-form-item>
						<el-form-item :label="t('categoryId')" prop="goods_category">
							<el-cascader class="input-width" v-model="formData.goods_category" :options="categoryList"
								@change="handleCategoryChange" :props="{ value: 'value', label: 'label' }" />
							<div class="ml-[10px]">
								<span class="cursor-pointer text-primary mr-[10px]"
									@click="checkCategory(true)">{{ t('refresh') }}</span>
								<span class="cursor-pointer text-primary"
									@click="toPosterEvent2">{{ t('addGoodsproject') }}</span>
							</div>
						</el-form-item>
						<el-form-item :label="t('guarantee')" prop="guarantee_id">
							<el-select class="input-width" v-model="formData.guarantee_id" multiple clearable>
								<el-option v-for="item in guaranteeList" :key="item.id" :label="item.guarantee_title"
									:value="item.id.toString()" />
							</el-select>
						</el-form-item>
						<el-form-item :label="t('goodsImage')" prop="goods_image">
							<div>
								<upload-image v-model="formData.goods_image" :limit="6" />
								<div class="text-[12px] text-[#999]">建议上传图片的大小为150px*150px</div>
							</div>
						</el-form-item>
						<el-form-item :label="t('virtualSaleNum')" prop="virtual_sale_num">
							<el-input v-model.trim="formData.virtually_sale" clearable
								:placeholder="t('virtualSaleNumPlaceholder')" class="input-width" maxlength="10" />
						</el-form-item>
						<el-form-item :label="t('sort')" prop="sort">
							<el-input v-model.trim="formData.sort" clearable :placeholder="t('sortPlaceholder')"
								class="input-width" show-word-limit maxlength="8" />
						</el-form-item>
						<el-form-item :label="t('status')">
							<el-radio-group v-model="formData.status" class="ml-4">
								<el-radio label="1">{{ t('up') }}</el-radio>
								<el-radio label="0">{{ t('down') }}</el-radio>
							</el-radio-group>
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
					</el-form>
				</el-tab-pane>
				<el-tab-pane :label="t('goodsChargeTab')" name="goods_charge">
					<el-form :model="formData" label-width="100px" ref="goodsFormRef" :rules="formRules"
						class="page-form">
						<el-form-item :label="t('goodsModel')">
							<el-radio-group v-model="formData.buy_type" class="ml-4">
								<el-radio label="reservation">{{ t('order') }}</el-radio>
								<el-radio label="buy">{{ t('fixedPrice') }}</el-radio>
							</el-radio-group>
						</el-form-item>
						<!-- 预约 -->
						<template v-if="formData.buy_type == 'reservation'">
							<el-form-item :label="t('freeReservation')" prop="free">
								<el-switch v-model="formData.free" />
							</el-form-item>
							<el-form-item :label="t('orderPrice')" prop="price" v-if="!formData.free">
								<div>
									<el-input v-model.trim="formData.price" clearable class="!w-[160px]" maxlength="8"
										@keyup="filterDigit($event)">
										<template #append>
											<span>元</span>
										</template>
									</el-input>
									<p class="text-[12px] text-[#a9a9a9]">0即为免费预约</p>
								</div>
							</el-form-item>
							<el-form-item :label="t('priceList')">
								<div class="add-product">
									<el-table :data="formData.price_list" style="width: 800px">
										<el-table-column :label="t('productName')" class="ml-[20px]">
											<template #default="scope">
												<el-form-item :prop="`price_list[${scope.$index}].name`"
													:rules="[{ required: true, message: t('productNamePlaceholder'), trigger: 'blur' }]">
													<el-input v-model.trim="scope.row.name" clearable
														:placeholder="t('productNamePlaceholder')" class="!w-[200px]"
														maxlength="60" />
												</el-form-item>
											</template>
										</el-table-column>
										<el-table-column :label="t('productPrice')" width="200">
											<template #default="scope">
												<el-form-item :prop="`price_list[${scope.$index}].price`" :rules="[{
                                                    trigger: 'blur',
                                                    validator: (rule: any, value: any, callback: any) => {
                                                        if (value == '') {
                                                            callback(t('productPricePlaceholder'))
                                                        } else if (value < 0) {
                                                            callback(t('priceNotZeroTips'))
                                                        } else {
                                                            callback()
                                                        }
                                                    }
                                                }]">
													<el-input v-model.trim="scope.row.price" clearable
														class="!w-[160px]" @keyup="filterDigit($event)" maxlength="8">
														<template #append>
															<span>元</span>
														</template>
													</el-input>
												</el-form-item>
											</template>
										</el-table-column>
										<el-table-column :label="t('productUnit')" width="200">
											<template #default="scope">
												<el-form-item :prop="`price_list[${scope.$index}].unit`"
													:rules="[{ required: true, message: t('productUnitTips'), trigger: 'blur' }]">
													<el-input v-model.trim="scope.row.unit" clearable
														:placeholder="t('productUnitPlaceholder')" class="!w-[160px]" />
												</el-form-item>
											</template>
										</el-table-column>
										<el-table-column :label="t('operations')" width="150">
											<template #default="scope">
												<el-button link type="primary" @click.prevent="deleteRow(scope.$index)">
													{{ t('delete') }}
												</el-button>
											</template>
										</el-table-column>
									</el-table>
									<el-button type="primary" @click="onAddItem()"
										class="mt-[15px]">{{ t('addItem') }}</el-button>
									<p class="text-[12px] text-[#a9a9a9]">价目表显示在项目详情作为参考，价格透明，有助于客户下单，预约后实际支付按照师傅报价结算
									</p>
								</div>
							</el-form-item>
						</template>
						<!-- 一口价 -->
						<template v-if="formData.buy_type == 'buy'">
							<el-form-item :label="t('goodsSpecification')">
								<el-radio-group v-model="formData.spec_type" class="ml-4">
									<el-radio label="single">{{ t('singleSpecification') }}</el-radio>
									<el-radio label="multi">{{ t('moreSpecification') }}</el-radio>
								</el-radio-group>
							</el-form-item>
							<!-- 单规格 -->
							<template v-if="formData.spec_type == 'single'">
								<el-form-item :label="t('goodsPrice')" prop="price">
									<el-input v-model.trim="formData.price" clearable class="!w-[160px]"
										@keyup="filterDigit($event)" maxlength="8">
										<template #append>
											<span>元</span>
										</template>
									</el-input>
								</el-form-item>
								<el-form-item :label="t('goodsUnit')" prop="sku_unit">
									<el-input v-model.trim="formData.sku_unit" clearable
										:placeholder="t('productUnitPlaceholder')" class="!w-[160px]" maxlength="8" />
								</el-form-item>
							</template>
							<!-- 多规格 -->
							<template v-if="formData.spec_type == 'multi'">
								<el-form-item>
									<div class="server-tables">
										<el-table :data="formData.goods_sku_data" style="width: 1500px">
											<el-table-column :label="t('productName')">
												<template #default="scope">
													<el-form-item :prop="`goods_sku_data[${scope.$index}].sku_name`"
														:rules="[{ required: true, message: t('productNamePlaceholder'), trigger: 'blur' }]">

														<el-input v-model.trim="scope.row.sku_name" clearable
															:placeholder="t('productNamePlaceholder')"
															class="!w-[300px]" maxlength="60" />
													</el-form-item>
												</template>
											</el-table-column>
											<el-table-column label="图片" width="150">
												<template #default="scope">
													<upload-image v-model="scope.row.sku_image" :limit="1" width="50px"
														height="50px" />
												</template>
											</el-table-column>
											<el-table-column :label="t('goodsPrice')" width="250">
												<template #default="scope">
													<el-form-item :prop="`goods_sku_data[${scope.$index}].price`"
														:rules="[{
                                                        trigger: 'blur',
                                                        validator: (rule: any, value: any, callback: any) => {
                                                            if (value === '') {
                                                                callback(t('goodsPricePlaceholder'))
                                                            } else if (value <= 0) {
                                                                callback(t('goodsPriceNotZeroTips'))
                                                            } else {
                                                                callback()
                                                            }
                                                        }
                                                    }]">
														<el-input v-model.trim="scope.row.price" clearable
															class="!w-[160px]" @keyup="filterDigit($event)"
															maxlength="8">
															<template #append>
																<span>元</span>
															</template>
														</el-input>
													</el-form-item>
												</template>
											</el-table-column>
											<el-table-column :label="t('productUnit')" width="240">
												<template #default="scope">
													<el-form-item :prop="`goods_sku_data[${scope.$index}].sku_unit`"
														:rules="[{ required: true, message: t('productUnitTips'), trigger: 'blur' }]">
														<el-input v-model.trim="scope.row.sku_unit" clearable
															:placeholder="t('productUnitPlaceholder')"
															class="!w-[160px]" maxlength="8" />
													</el-form-item>
												</template>
											</el-table-column>
											<el-table-column label="默认规格" width="230">
												<template #default="scope">
													<el-form-item>
														<el-switch v-model="scope.row.is_default" :active-value="1"
															:inactive-value="0"
															@change="specValueIsDefaultChangeListener($event, scope.$index)" />
													</el-form-item>
												</template>
											</el-table-column>
											<el-table-column :label="t('operations')" width="150">
												<template #default="scope">
													<el-button link type="primary"
														@click.prevent="deleteMoreRow(scope.$index)">
														{{ t('delete') }}
													</el-button>
												</template>
											</el-table-column>
										</el-table>
										<el-button type="primary" @click="AddMoreItem()"
											class="mt-[15px]">{{ t('addspec') }}</el-button>
									</div>
								</el-form-item>
							</template>
						</template>
						<!-- <el-form-item :label="t('ifaAfterSale')">
                            <el-radio-group v-model="formData.after_sales" class="ml-4">
                                <el-radio :label="0">{{ t('afterSale') }}</el-radio>
                                <el-radio :label="1">{{ t('noAfterSale') }}</el-radio>
                            </el-radio-group>
                        </el-form-item> -->
						<el-form-item :label="t('memberDiscount')" v-if="formData.buy_type == 'buy'">
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
						</el-form-item>
					</el-form>
				</el-tab-pane>
				<el-tab-pane :label="t('orderTab')" name="order">
					<el-form :model="formData" label-width="100px" ref="orderFormRef" class="page-form">
						<el-form-item :label="t('orderGoout')">
							<div>
								<el-radio-group v-model="formData.is_force_departure">
									<el-radio :label="1">{{ t('open') }}</el-radio>
									<el-radio :label="0">{{ t('close') }}</el-radio>
								</el-radio-group>
								<div class="text-[12px] text-[#999] leading-[20px]">{{ t('orderGooutWarning') }}</div>
							</div>
						</el-form-item>
						<el-form-item :label="t('orderEnd')">
							<div>
								<el-radio-group v-model="formData.is_force_clock_in">
									<el-radio :label="1">{{ t('open') }}</el-radio>
									<el-radio :label="0">{{ t('close') }}</el-radio>
								</el-radio-group>
								<div class="text-[12px] text-[#999] leading-[20px]">{{ t('orderEndWarning') }}</div>
							</div>
						</el-form-item>
						<el-form-item :label="t('finshPicture')">
							<div>
								<el-radio-group v-model="formData.is_finish_photograph">
									<el-radio :label="1">{{ t('open') }}</el-radio>
									<el-radio :label="0">{{ t('close') }}</el-radio>
								</el-radio-group>
								<div class="text-[12px] text-[#999] leading-[20px]">{{ t('finshPicturePl') }}</div>
							</div>
						</el-form-item>
						<el-form-item :label="t('orderOrderModel')">
							<div>
								<el-radio-group v-model="formData.grab_orders">
									<el-radio :label="1">{{ t('open') }}</el-radio>
									<el-radio :label="0">{{ t('close') }}</el-radio>
								</el-radio-group>
								<div class="text-[12px] text-[#999] leading-[20px]">{{ t('orderOrderModelWarning') }}
								</div>
							</div>
						</el-form-item>
						<el-form-item :label="t('orderAddItem')">
							<div class="add-product">
								<el-table :data="formData.additional_manage" style="width: auto" height="320">
									<el-table-column :label="t('addItemName')" class="ml-[20px]" width="200">
										<template #header="scope">
											<span class="text-red-500">*</span>	{{t('addItemName')}}
										</template>
										<template #default="scope">
											<el-form-item :prop="`additional_manage[${scope.$index}].name`"
												:rules="[{ required: true, message: t('addItemNamePlaceholder'), trigger: 'blur' }]">
												<el-input v-model.trim="scope.row.name" clearable
													:placeholder="t('addItemNamePlaceholder')" class="!w-[200px]"
													maxlength="60" />
											</el-form-item>
										</template>
									</el-table-column>
									<el-table-column :label="t('addItemPrice')" width="200">
										<template #header="scope">
											<span class="text-red-500">*</span>	{{t('addItemPrice')}}
										</template>
										<template #default="scope">
											<el-form-item :prop="`additional_manage[${scope.$index}].price`" :rules="[{
			                                      trigger: 'blur',
			                                      validator: (rule: any, value: any, callback: any) => {
			                                          if (value == '') {
			                                              callback(t('addItemPricePlaceholder'))
			                                          } else if(value == 0){
														   callback('价格不能为0')
													  }else if (value < 0) {
			                                              callback(t('addItemPricePlaceholder'))
			                                          } else {
			                                              callback()
			                                          }
			                                      }
			                                  }]">
												<el-input v-model.trim="scope.row.price" clearable class="!w-[160px]"
													@keyup="filterDigit($event)" maxlength="8">
													<template #append>
														<span>元</span>
													</template>
												</el-input>
											</el-form-item>
										</template>
									</el-table-column>
									<el-table-column :label="t('addItemImg')" width="150">
										<template #header="scope">
											<span class="text-red-500">*</span>	{{t('addItemImg')}}
										</template>
										<template #default="scope">
											<el-form-item :prop="`additional_manage[${scope.$index}].img`"
												class="addImage"
												:rules="[{
													trigger: 'change',
													validator: (rule: any, value: any, callback: any) => {
														if (scope.row.price && scope.row.price > 0 && !value) {
															return callback('请上传增值图片')
														}
														callback()
													}
												}]">
												<upload-image v-model="scope.row.img" :limit="1" />
											</el-form-item>
										</template>
									</el-table-column>
									<el-table-column :label="t('commsionRate')" width="200">
									 
											<template #header="scope">
												<div>
													<span class="text-red-500">*</span>	{{t('commsionRate')}}
													<el-tooltip
														content="
															用于计算订单增项项目的基础佣金比例 <br>
															增项佣金 = 增项金额 × 增项佣金比例 × 门店 / 师傅分成比例
														"
														raw-content
														>
														<el-icon><QuestionFilled /></el-icon>
													</el-tooltip>
												</div>
											</template>
										<template #default="scope">
											<el-form-item :prop="`additional_manage[${scope.$index}].commission_rate`"
												:rules="[{
												  trigger: ['blur', 'change'],
												  validator: (rule: any, value: any, callback: any) => {
													const val = value ? value.toString().trim() : '';
													if (!/^\d+(\.\d+)?$/.test(val)) {
													  return callback(t('commsionRateNumber')); 
													}
													const num = parseFloat(val);
													if (num < 0) {
													  return callback(t('commsionRateMin')); 
													}
													if (num > 100) {
													  return callback(t('commsionRateMax')); 
													}
													callback();
												  }
												}]">
												<el-input v-model.trim="scope.row.commission_rate" clearable
													class="!w-[160px]" @keyup="filterDigit($event)" maxlength="5"
													:placeholder="t('commsionRatePlaceholder')">
													<template #append>
														<span>%</span>
													</template>
												</el-input>
											</el-form-item>
										</template>
									</el-table-column>
									<el-table-column :label="t('operations')" width="150">
										<template #default="scope">
											<el-button link type="primary"
												@click.prevent="deleteRoworder(scope.$index)">
												{{ t('delete') }}
											</el-button>
										</template>
									</el-table-column>
								</el-table>
								<el-button type="primary" @click="onAddItemorder()"
									class="mt-[15px]">{{ t('addItem') }}</el-button>
								<!-- <p class="text-[12px] text-[#a9a9a9]">价目表显示在项目详情作为参考，价格透明，有助于客户下单，预约后实际支付按照师傅报价结算</p> -->
							</div>
						</el-form-item>




					</el-form>
				</el-tab-pane>
				<el-tab-pane :label="t('goodsDescTab')" name="detail">
					<el-form :model="formData" label-width="100px" ref="detailFormRef" :rules="formRules"
						class="page-form">
						<el-form-item :label="t('goodsContent')" prop="goods_content">
							<editor v-model="formData.goods_content" />
						</el-form-item>
					</el-form>
				</el-tab-pane>
			</el-tabs>
		</el-card>
		<div class="fixed-footer-wrap">
			<div class="fixed-footer">
				<el-button type="primary" @click="onSave()">{{ t('save') }}</el-button>
				<el-button @click="back()">{{ t('cancel') }}</el-button>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
	import { ref, reactive, computed } from 'vue'
	import { t } from '@/lang'
	import { cloneDeep } from 'lodash-es'
	import type { FormInstance, ElMessage } from 'element-plus'
	import { ArrowLeft } from '@element-plus/icons-vue'
	import { getCategoryTree } from '@/addon/home_service/api/category'
	import { addGoods, editGoods, getGoodsDetail, getGoodsGuarantee } from '@/addon/home_service/api/goods'
	import { useRoute, useRouter } from 'vue-router'
	import { filterNumber, filterDigit } from '@/utils/common'
	import { getPosterList } from '@/app/api/poster'
	const guaranteeList = ref([])
	const getGoodsGuaranteeFn = () => {
		getGoodsGuarantee().then((res) => {
			guaranteeList.value = res.data.data
		})
	}
	getGoodsGuaranteeFn()
	const route = useRoute()
	const router = useRouter()
	const id : number = parseInt(route.query.id)
	const loading = ref(false)
	const activeName = ref('basic')
	/**
	   * 表单数据
	   */
	const initialFormData = {
		goods_id: '',
		goods_name: '', // 项目名称
		goods_subtitle: '',
		goods_image: '', // 项目图片
		goods_category: '', // 项目分类
		guarantee_id: [],//服务保障
		virtually_sale: '', // 虚拟销量
		sort: '', // 排序
		status: '1', // 状态 0下架 1上架
		buy_type: 'reservation', // 服务模式 reservation-预约 buy-购买(一口价)
		price_list: [], // 价目表
		spec_type: 'single',
		free: false,
		price: '', // 预约价格 单规格服务价
		sku_unit: '次', // 单规格单位
		goods_sku_data: [], // 多规格
		after_sales: 0, // 是否支持售后0-是 1-否
		goods_content: '',
		poster_id: '',
		member_discount: '',
		is_force_departure: 0,
		is_force_clock_in: 0,
		is_finish_photograph: 1,
		grab_orders: 1,
		additional_manage: [],
		top_category: ""
	}
	const formData : Record<string, any> = reactive({ ...initialFormData })

	const setFormData = async (id : number = 0) => {
		loading.value = true;
		Object.assign(formData, initialFormData);

		// 获取服务详情
		const data = await (await getGoodsDetail({ goods_id: id })).data.goods_info;
		Object.keys(formData).forEach((key : string) => {
			if (data[key] != undefined) formData[key] = data[key];
		});

		// 关键：将末级分类ID转换为路径数组（用于级联选择器回显）
		if (data.goods_category) {
			const categoryId = Number(data.goods_category);
			// 生成路径数组（顶级ID -> ... -> 末级ID）
			const path = getCategoryPath(categoryId, categoryList);
			if (path.length > 0) {
				formData.goods_category = path; // 级联选择器绑定的是路径数组
				formData.top_category = path[0]; // 直接取顶级ID
			}
		}
		if (data.guarantee_id) {
			formData.guarantee_id = data.guarantee_id.split(',').map(id => id.toString());
		}
		// 其他原有逻辑（单规格/多规格处理）
		if (formData.spec_type === 'single') {
			formData.price = data.sku_list[0].price;
			formData.sku_unit = data.sku_list[0].sku_unit;
			if (Number(formData.price) === 0) {
				formData.free = true;
			}
		}
		formData.goods_sku_data = data.sku_list;
		if (data.additional_manage !== '' && data.additional_manage.length) {
			formData.additional_manage = JSON.parse(data.additional_manage);
		}

		loading.value = false;
	};

	// 辅助函数：根据末级分类ID生成完整路径数组
	const getCategoryPath = (targetId : number, categoryTree : any[]) : number[] => {
		for (const topItem of categoryTree) {
			// 若目标是顶级分类
			if (topItem.value === targetId) {
				return [topItem.value];
			}
			// 递归查找子分类
			const findPath = (children : any[], currentPath : number[]) : number[] => {
				for (const child of children) {
					const newPath = [...currentPath, child.value];
					if (child.value === targetId) {
						return newPath; // 找到目标，返回完整路径
					}
					if (child.children && child.children.length > 0) {
						const result = findPath(child.children, newPath);
						if (result.length > 0) return result;
					}
				}
				return [];
			};
			// 从顶级分类开始查找
			const path = findPath(topItem.children || [], [topItem.value]);
			if (path.length > 0) return path;
		}
		return [];
	};
	if (id) setFormData(id)

	const categoryList = reactive([])

	const checkCategory = async (row : any = null) => {
		// 增加错误捕获，避免接口异常导致的Promise挂起
		try {
			const res = await getCategoryTree(); // 使用await简化异步逻辑
			const data = res.data;

			if (data) {
				const goodsCategoryTree : any[] = [];
				// 遍历顶级分类
				data.forEach((item : any) => {
					const children : any[] = [];
					// 处理子分类（增加空值判断）
					if (item?.children?.length) {
						item.children.forEach((childItem : any) => {
							children.push({
								value: childItem.category_id,
								label: childItem.category_name
							});
						});
					}
					// 推入顶级分类（确保结构完整）
					goodsCategoryTree.push({
						value: item.category_id,
						label: item.category_name,
						children
					});
				});
				// 清空并更新分类列表
				categoryList.splice(0, categoryList.length, ...goodsCategoryTree);
			}

			// 刷新后重新生成路径（优化类型判断）
			if (formData.goods_category) {
				// 安全获取末级ID（区分数组和非数组情况）
				const lastId = Array.isArray(formData.goods_category)
					? formData.goods_category[formData.goods_category.length - 1]
					: formData.goods_category;

				// 只有当末级ID有效时才重新计算路径
				if (lastId) {
					const newPath = getCategoryPath(Number(lastId), categoryList);
					if (newPath.length > 0) {
						formData.goods_category = newPath;
						formData.top_category = newPath[0];
					}
				}
			}

			// 刷新成功提示（判断row是否为true，避免非布尔值导致的异常）
			if (row === true) {
				ElMessage.success(t('refreshSuccess'));
			}
		} catch (error) {
			// 错误处理，避免Promise链断裂
			console.error('获取分类失败:', error);
			ElMessage.error(t('refreshFailed')); // 增加失败提示
		}
	};


	checkCategory()

	const handleCategoryChange = (path : number[]) => {
		// path格式：[顶级ID, 二级ID, ..., 选中项ID]
		if (Array.isArray(path) && path.length > 0) {
			formData.top_category = path[0]; // 取第一个元素作为顶级分类ID
			formData.goods_category = path[path.length - 1]; // 选中项ID（末级）
		} else {
			formData.top_category = ''; // 未选择时清空
			formData.goods_category = '';
		}
	};
	// 海报列表下拉框
	const posterOptions = reactive([])

	// 跳转到海报列表，添加海报
	const toPosterEvent = () => {
		const url = router.resolve({
			path: '/poster/list'
		})
		window.open(url.href)
	}
	// 跳转到项目分类
	const toPosterEvent2 = () => {
		const url = router.resolve({
			path: '/site/home_service/goods/category'
		})
		window.open(url.href)
	}
	// 服务海报
	const refreshGoodsPoster = (bool = false) => {
		getPosterList({
			type: 'home_service_goods'
		}).then((res) => {
			const data = res.data
			if (data) {
				posterOptions.splice(0, posterOptions.length, ...data)
				if (bool) {
					ElMessage({
						message: t('refreshSuccess'),
						type: 'success'
					})
				}
			}
			if (bool) {
				ElMessage.success(t('refreshSuccess'))
			}
		})
	}

	refreshGoodsPoster()

	const basicFormRef = ref<FormInstance>()
	const goodsFormRef = ref<FormInstance>()
	const orderFormRef = ref<FormInstance>()
	const detailFormRef = ref<FormInstance>()

	// 正则表达式
	const regExp = {
		required: /[\S]+/,
		number: /^\d{0,10}$/,
		digit: /^\d{0,10}(.?\d{0,2})$/,
		special: /^\d{0,10}(.?\d{0,3})$/
	}
	// 表单验证规则
	const formRules = computed(() => {
		return {
			goods_name: [{ required: true, message: t('goodsNamePlaceholder'), trigger: 'blur' }],
			goods_subtitle: [{ required: true, message: t('goodsSubTitlePlaceholder'), trigger: 'blur' }],
			goods_image: [{ required: true, message: t('goodsImagePlaceholder'), trigger: 'change' }],
			goods_category: [{ required: true, message: t('categoryIdPlaceholder'), trigger: 'change' }],
			goods_content: [{ required: true, message: t('goods_contentPlaceholder'), trigger: 'change' }],
			guarantee_id: [
				{
					required: true,  // 若服务保障非必填，可改为false
					message: t('selectGuarantee'),  // 语言包加“请选择服务保障”
					trigger: 'change'
				}
			],
			sort: [
				{
					trigger: 'blur',
					validator: (rule : any, value : any, callback : any) => {
						if (isNaN(value) || !regExp.number.test(value)) {
							callback(new Error(t('sortTips')))
						} else {
							callback()
						}
					}
				}
			],
			price: [
				{
					required: true,
					trigger: 'blur',
					validator: (rule : any, value : any, callback : any) => {
						if (value == '' && formData.buy_type == 'reservation') {
							callback(new Error(t('orderPricePricePlaceholder')))
						} else if (value == '' && formData.buy_type == 'buy') {
							callback(new Error(t('goodsPricePlaceholder')))
						} else if (value < 0 && formData.buy_type == 'reservation') {
							callback(new Error(t('orderPriceNotZeroTips')))
						} else if (value <= 0 && formData.buy_type == 'buy') {
							callback(new Error(t('goodsPriceNotZeroTips')))
						} else {
							callback()
						}
					}
				}
			],
			sku_unit: [{ required: true, message: t('productUnitTips'), trigger: 'blur' }],
		}
	})
	const verify = (callback : any) => {
		const formRef = [
			{
				key: 'basic',
				verify: false,
				ref: basicFormRef.value
			},
			{
				key: 'goods_charge',
				verify: false,
				ref: goodsFormRef.value
			},
			{
				key: 'order',
				verify: false,
				ref: orderFormRef.value
			},
			{
				key: 'detail',
				verify: false,
				ref: detailFormRef.value
			}
		]
		formRef.forEach((el : any, index) => {
			el.ref.validate((valid : any) => {
				el.verify = valid
			})
		})

		setTimeout(() => {
			let verify = true
			// 检测验证，并且定位tab页面
			for (let i = 0; i < formRef.length; i++) {
				if (formRef[i].verify == false) {
					activeName.value = formRef[i].key
					verify = false
					break
				}
			}
			if (verify && callback) callback()
		}, 10)
	}

	const onSave = () => {
		verify(() => {
			loading.value = true
			if (formData.goods_sku_data.length) {
				const isDefault = formData.goods_sku_data.some((item) => {
					return item.is_default
				})
				if (!isDefault) {
					ElMessage({
						message: '请选择多规格的默认规格',
						type: 'error'
					})
					return
				}
			}
			if (formData.free) {
				formData.price = 0
			}
			const data = cloneDeep(formData)
			if (Array.isArray(data.guarantee_id) && data.guarantee_id.length > 0) {
				data.guarantee_id = data.guarantee_id.join(','); // 数组转字符串：["1","2"] → "1,2"
			} else {
				data.guarantee_id = ''; // 未选择时传空字符串
			}
			data.price_list = JSON.stringify(data.price_list)
			data.additional_manage = JSON.stringify(data.additional_manage)
			data.goods_sku_data = JSON.stringify(data.goods_sku_data)
			const save = id ? editGoods : addGoods
			save(data)
				.then((res) => {
					loading.value = false
					history.back()
				})
				.catch(() => {
					loading.value = false
				})
		})
	}

	const back = () => {
		history.back()
	}


	// 添加增项
	const onAddItemorder = () => {
		formData.additional_manage.push({
			name: '',
			price: '0',
			img: '',
			commission_rate: 0
		})
	}
	// 删除增项
	const deleteRoworder = (index : number) => {
		formData.additional_manage.splice(index, 1)
	}

	// 添加项目
	const onAddItem = () => {
		formData.price_list.push({
			name: '',
			price: '0',
			img: []
		})
	}
	// 删除项目
	const deleteRow = (index : number) => {
		formData.price_list.splice(index, 1)
	}
	// 多规格添加项目
	const AddMoreItem = () => {
		if (formData.goods_sku_data.length > 0) {
			formData.goods_sku_data.push({
				sku_image: '',
				sku_name: '',
				price: '0',
				sku_unit: '次',
				is_default: 0
			})
		} else {
			formData.goods_sku_data.push({
				sku_image: '',
				sku_name: '',
				price: '0',
				sku_unit: '次',
				is_default: 1
			})
		}
	}
	const deleteMoreRow = (index : number) => {
		formData.goods_sku_data.splice(index, 1)
	}
	// 设置默认规格
	const specValueIsDefaultChangeListener = (value : any, key : any) => {
		for (const k in formData.goods_sku_data) {
			if (k == key) {
				formData.goods_sku_data[k].is_default = value
			} else {
				formData.goods_sku_data[k].is_default = 0
			}
		}
	}
</script>

<style scoped>
	:deep(.product-price .el-form-item) {
		margin-bottom: 0 !important;
	}

	.add-product :deep(.el-table__body .el-table__cell) {
		height: 68px !important;
	}

	.add-product :deep(.el-table__cell .cell) {
		overflow: initial !important;
		padding: 0 12px;
	}

	.server-tables :deep(.el-table__body .el-table__cell) {
		height: 68px !important;
	}

	.server-tables :deep(.el-table__cell .cell) {
		overflow: initial !important;
		padding: 0 12px;
	}

	::v-deep .addImage .image-wrap {
		width: 60px !important;
		height: 60px !important;
	}
</style>