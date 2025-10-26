<template>
	<div>
		<div class="leading-[1]" @click="show">
			<slot>
				<el-button :disabled="prop.disabled">{{ t('selectServices') }}</el-button>
				<div class="inline-block ml-[10px] text-[14px]" v-show="goodsIds.length">
					<span>{{ t('goodsSelectPopupSelect') }}</span>
					<span class="text-primary mx-[2px]">{{ goodsIds.length }}</span>
					<span>{{ t('goodsSelectPopupPiece') }}</span>
				</div>
			</slot>
		</div>
		<el-dialog v-model="showDialog" :title="t('goodsSelectPopupSelectGoodsDialog')" width="1000px"
			:destroy-on-close="true" :close-on-click-modal="false">
			<el-form :inline="true" :model="goodsTable.searchParam" ref="searchFormRef">
				<el-form-item :label="t('goodsSelectPopupGoodsName')" prop="goods_name" class="form-item-wrap">
					<el-input v-model.trim="goodsTable.searchParam.goods_name"
						:placeholder="t('goodsSelectPopupGoodsNamePlaceholder')" maxlength="60" />
				</el-form-item>
				<el-form-item :label="t('goodsSelectPopupGoodsCategory')" prop="goods_category" class="form-item-wrap">
					<el-cascader v-model="goodsTable.searchParam.goods_category" :options="goodsCategoryOptions"
						:placeholder="t('goodsSelectPopupGoodsCategoryPlaceholder')" clearable
						:props="{ value: 'value', label: 'label', emitPath: false }" />
				</el-form-item>
				<el-form-item class="form-item-wrap">
					<el-button type="primary" @click="loadGoodsList()">{{ t('search') }}</el-button>
					<el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
				</el-form-item>
			</el-form>

			<div class="table w-[100%]" v-loading="goodsTable.loading">
				<div class="table-head flex items-center bg-[#f5f7f9] py-[8px] px-[8px] pr-[25px]">
					<div class="w-[3%] flex-shrink-0"></div>
					<div class="w-[7%] flex-shrink-0">
						<el-checkbox v-model="staircheckAll" v-if="prop.max > 1" :indeterminate="isStairIndeterminate"
							@change="handleCheckAllChange" />
					</div>
					<di class="w-[50%] flex-shrink-0">服务信息</di>
					<div class="w-[20%] flex-shrink-0">服务价格</div>
					<div class="w-[20%] flex-shrink-0">销量</div>
				</div>
				<div class="table-body h-[350px] overflow-y-auto">
					<div v-for="(row, rowIndex) in goodsTable.data" :key="rowIndex" class="flex flex-col">
						<!-- 内容 -->
						<div class="flex items-center border-solid border-[#e5e7eb] py-[8px] border-b-[1px] px-[8px]">
							<div v-if="prop.mode == 'spu'" class="w-[3%] flex-shrink-0"></div>
							<div v-if="prop.mode == 'sku' && row.skuList.length > 1"
								class="w-[3%] flex-shrink-0 cursor-pointer text-center !text-[10px]"
								@click="secondLevelArrowChange(row)"
								:class="{ 'iconfont iconxiangyoujiantou': row.skuList.length, 'arrow-show': row.isShow }">
							</div>
							<div v-if="prop.mode == 'sku' && row.skuList.length <= 1" class="w-[3%] flex-shrink-0">
							</div>
							<div class="w-[7%] flex-shrink-0">
								<el-checkbox v-model="row.secondLevelCheckAll"
									:indeterminate="row.isSecondLevelIndeterminate"
									@change="secondLevelHandleCheckAllChange($event, row)" />
							</div>
							<div class="flex items-center cursor-pointer w-[50%] flex-shrink-0 pr-[8px]">
								<div class="min-w-[60px] h-[60px] flex items-center justify-center">
									<el-image v-if="row.goods_cover_thumb_small" class="w-[60px] h-[60px]"
										:src="img(row.goods_cover_thumb_small)" fit="contain">
										<template #error>
											<div class="image-slot">
												<img class="w-[60px] h-[60px]"
													src="@/addon/home_service/assets/goods_default.png" />
											</div>
										</template>
									</el-image>
									<img v-else class="w-[60px] h-[60px]" src="@/addon/home_service/assets/goods_default.png"
										fit="contain" />
								</div>
								<div class="ml-2 flex flex-col items-start">
									<span :title="row.goods_name" class="multi-hidden leading-[1.4]">{{ row.goods_name
									}}</span>
									<span class="text-primary text-[12px]">{{ row.goods_type_name }}</span>
									<span
										class="px-[4px]  text-[12px] text-[#fff] rounded-[4px] bg-primary leading-[18px]"
										v-if="row.is_gift == 1">赠品</span>
								</div>
							</div>
							<div class="w-[20%] flex-shrink-0">￥{{ row.goodsSku.price }}</div>
							<div class="w-[20%] flex-shrink-0">{{ row.sale_num }}</div>
						</div>

						<div v-show="prop.mode == 'sku' && row.skuList.length > 1">
							<!-- 子级 -->
							<div v-for="(item, index) in row.skuList" :key="index"
								class="flex items-center py-[8px] border-solid border-transparent border-b-[1px] px-[8px]"
								:class="{ 'hidden': !row.isShow, 'border-[#e5e7eb]': index == (row.skuList.length - 1) }">
								<div class="w-[3%] flex-shrink-0"></div>
								<div class="w-[7%] flex-shrink-0">
									<el-checkbox v-model="item.threeLevelCheckAll"
										@change="subChildHandleCheckAllChange($event, row, item)" />
								</div>
								<div class="flex items-center cursor-pointer w-[50%] flex-shrink-0 pr-[8px]">
									<div class="min-w-[60px] h-[60px] flex items-center justify-center">
										<el-image v-if="item.sku_image" class="w-[60px] h-[60px]"
											:src="img(item.sku_image)" fit="contain">
											<template #error>
												<div class="image-slot">
													<img class="w-[60px] h-[60px]"
														src="@/addon/home_service/assets/goods_default.png" />
												</div>
											</template>
										</el-image>
										<img v-else class="w-[60px] h-[60px]"
											src="@/addon/home_service/assets/goods_default.png" fit="contain" />
									</div>
									<div class="ml-2">
										<span :title="item.sku_name || row.goods_name"
											class="multi-hidden leading-[1.4]">{{ item.sku_name ||
												row.goods_name }}</span>
										<span class="text-primary text-[12px]">{{ row.goods_type_name }}</span>
									</div>
								</div>
								<div class="w-[20%] flex-shrink-0">￥{{ item.price }}</div>
								<div class="w-[20%] flex-shrink-0">{{ item.stock }}</div>
							</div>
						</div>
					</div>

					<div v-if="!goodsTable.data.length && !goodsTable.loading"
						class="h-[60px] flex items-center justify-center border-solid border-[#e5e7eb] py-[12px] border-b-[1px]">
						暂无数据</div>
				</div>
			</div>

			<div class="mt-[16px] flex">
				<div class="flex items-center flex-1">
					<div class="mr-[10px]" v-show="selectGoodsNum">
						<span>{{ t('goodsSelectPopupBeforeTip') }}</span>
						<span class="text-primary mx-[2px]">{{ selectGoodsNum }}</span>
						<span>{{ t('goodsSelectPopupAfterTip') }}</span>
					</div>
					<el-button type="primary" link @click="clear" v-show="selectGoodsNum">{{
						t('goodsSelectPopupClearGoods')
					}}</el-button>
				</div>
				<el-pagination v-model:current-page="goodsTable.page" v-model:page-size="goodsTable.limit"
					layout="total, sizes, prev, pager, next, jumper" :total="goodsTable.total"
					@size-change="loadGoodsList()" @current-change="loadGoodsList" />
			</div>

			<template #footer>
				<span class="dialog-footer">
					<el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
					<el-button type="primary" @click="save">{{ t('confirm') }}</el-button>
				</span>
			</template>
		</el-dialog>
	</div>

</template>

<script lang="ts" setup>
import { t } from '@/lang'
import { ref, reactive, computed, nextTick } from 'vue'
import { cloneDeep } from 'lodash-es'
import { img, deepClone } from '@/utils/common'
import { ElMessage } from 'element-plus'
import { getGoodsSelectPageList, getGoodsSkuNoPageList, getCategoryTree } from '@/addon/home_service/api/goods'

const prop = defineProps({
	modelValue: {
		type: [String, Array],
		default: () => [] // 确保默认是数组
	},
	max: {
		type: Number,
		default: 0
	},
	min: {
		type: Number,
		default: 0
	},
	mode: {
		type: String,
		default: 'spu' // spu：按服务，sku：按多规格
	},
	way: {
		type: String,
		default: '' // 选择方式，空：代表全部， single：单一
	},
	isGift: {
		type: [String, Number],
		default: 0 // 查询是否赠品，0：不查赠品，1：查询赠品
	},
	disabled: {
		type: Boolean,
		default: false
	},
	goodsType: {
		type: String,
		default: ''
	}
})

const emit = defineEmits(['update:modelValue', 'goodsSelect'])

// 通过prop.mode来决定 数据前缀是sku_还是goods_
let replacePrefix = prop.mode == "sku" ? 'sku_' : 'goods_';

const isStairIndeterminate = ref(false);
const staircheckAll = ref(false);

const goodsIds: any = computed({
	get() {
		// 确保返回值是数组
		return Array.isArray(prop.modelValue) ? prop.modelValue : []
	},
	set(value) {
		emit('update:modelValue', value)
	}
})

const showDialog = ref(false)

// 已选服务列表
const selectGoods: any = reactive({})

// 已选服务列表id
const selectGoodsId: any = reactive([])

// 已选服务数量
const selectGoodsNum: any = computed(() => {
	return Object.keys(selectGoods).length
})

const goodsTable = reactive({
	page: 1,
	limit: 10,
	total: 0,
	loading: true,
	data: [],
	searchParam: {
		keyword: '',
		goods_category: [],
		select_type: 'all',
		goods_ids: '',
		verify_goods_ids: '',
		verify_sku_ids: '',
		goods_type: '',
		is_gift: 0
	}
})

goodsTable.searchParam.is_gift = prop.isGift ? prop.isGift : 0;
goodsTable.searchParam.goods_type = prop.goodsType ? prop.goodsType : '';

const searchFormRef = ref()

// 查询全部/已选服务
const handleSelectTypeChange = (value: any) => {
	loadGoodsList()
}

// 服务分类
const goodsCategoryOptions: any = reactive([])

// 服务类型
const goodsType: any = reactive([])

// 初始化数据
const initData = () => {
	// 查询服务分类树结构
	getCategoryTree().then((res) => {
		const data = res.data
		if (data) {
			const goodsCategoryTree: any = []
			data.forEach((item: any) => {
				const children: any = []
				if (item.child_list) {
					item.child_list.forEach((childItem: any) => {
						children.push({
							value: childItem.category_id,
							label: childItem.category_name
						})
					})
				}
				goodsCategoryTree.push({
					value: item.category_id,
					label: item.category_name,
					children
				})
			})
			goodsCategoryOptions.splice(0, goodsCategoryOptions.length, ...goodsCategoryTree)
		}
	})
}

initData()

const goodsListTableRef = ref()

// 箭头选择事件
const secondLevelArrowChange = (data) => {
	data.isShow = !data.isShow;
}

// 一级复选框
const handleCheckAllChange = (isSelect) => {
	isStairIndeterminate.value = false;
	goodsTable.data.forEach((item, index) => {
		item.secondLevelCheckAll = isSelect;
		item.skuList.forEach((subItem, subIndex) => {
			subItem.threeLevelCheckAll = isSelect;
		});
	});
	if (isSelect) {
		goodsTable.data.forEach((item: any) => {
			if (prop.mode == 'spu') {
				selectGoods[replacePrefix + item.goods_id] = item
				selectGoodsId.push(item.goods_id)
			} else {
				item.skuList.forEach((skuItem: any) => {
					selectGoodsId.push(skuItem.sku_id);
					selectGoods[replacePrefix + skuItem.sku_id] = deepClone(skuItem);
					selectGoods[replacePrefix + skuItem.sku_id].goods_name = item.goods_name;
					selectGoods[replacePrefix + skuItem.sku_id].goods_type_name = item.goods_type_name;
					selectGoods[replacePrefix + skuItem.sku_id].goods_type = item.goods_type;
				})

			}
		})
	} else {
		// 未选中，删除当前页面的数据
		goodsTable.data.forEach((item: any) => {
			if (prop.mode == 'spu') {
				selectGoodsId.splice(selectGoodsId.indexOf(item.goods_id), 1)
				delete selectGoods[replacePrefix + item.goods_id]
			} else {
				item.skuList.forEach((skuItem: any) => {
					selectGoodsId.splice(selectGoodsId.indexOf(skuItem.sku_id), 1)
					delete selectGoods[replacePrefix + skuItem.sku_id]
				})
			}
		})
	}
}

// 二级复选框
const secondLevelHandleCheckAllChange = (isSelect, row) => {

	row.skuList.forEach((item, index) => {
		item.threeLevelCheckAll = isSelect;
	});
	detectionAllSelect();
	if (prop.mode == 'spu') {
		if (isSelect) {
			selectGoodsId.push(row.goods_id)
			selectGoods[replacePrefix + row.goods_id] = deepClone(row)
		} else {
			selectGoodsId.splice(selectGoodsId.indexOf(row.goods_id), 1)
			// 未选中，删除当前服务
			delete selectGoods[replacePrefix + row.goods_id]
		}
	} else {
		if (isSelect) {
			row.skuList.forEach((item, index) => {
				selectGoodsId.push(item.sku_id);
				selectGoods[replacePrefix + item.sku_id] = deepClone(item);
				selectGoods[replacePrefix + item.sku_id].goods_name = row.goods_name;
				selectGoods[replacePrefix + item.sku_id].goods_type_name = row.goods_type_name;
				selectGoods[replacePrefix + item.sku_id].goods_type = row.goods_type;
			});
		} else {
			row.skuList.forEach((item, index) => {
				selectGoodsId.splice(selectGoodsId.indexOf(item.sku_id), 1)
				// 未选中，删除当前服务
				delete selectGoods[replacePrefix + item.sku_id]
			});
		}
	}

	// 当所选数量超出限制数量【prop.max】时，自动截断
	if (prop.max && prop.max > 0 && Object.keys(selectGoods).length > prop.max) {
		let len = Object.keys(selectGoods).length - prop.max;
		let goodsIdCopy = cloneDeep(selectGoodsId);
		goodsIdCopy.forEach((item, index) => {
			if (index < len) {
				let indent = selectGoodsId.indexOf(item)
				delete selectGoods[replacePrefix + selectGoodsId[indent]]
				selectGoodsId.splice(indent, 1)
			}
		});
		setGoodsSelected();
	}
}

// 三级复选框
const subChildHandleCheckAllChange = (selected: any, parentData: any, data: any) => {
	let selectNum = 0;
	parentData.skuList.forEach((item, index) => {
		if (item.threeLevelCheckAll) {
			selectNum++;
		}
	});
	if (selectNum > 0 && selectNum != parentData.skuList.length) {
		parentData.secondLevelCheckAll = false;
		parentData.isSecondLevelIndeterminate = true;
	} else if (selectNum == parentData.skuList.length) {
		parentData.isSecondLevelIndeterminate = false;
		parentData.secondLevelCheckAll = true
	} else {
		parentData.isSecondLevelIndeterminate = false;
		parentData.secondLevelCheckAll = false;
	}

	detectionAllSelect();

	let currSku = deepClone(data)

	if (selected) {
		selectGoodsId.push(currSku.sku_id);
		currSku.goods_name = parentData.goods_name;
		currSku.goods_type_name = parentData.goods_type_name;
		currSku.goods_type = parentData.goods_type;
		selectGoods[replacePrefix + currSku.sku_id] = currSku;
	} else {
		selectGoodsId.splice(selectGoodsId.indexOf(currSku.sku_id), 1)
		delete selectGoods[replacePrefix + currSku.sku_id]
	}
}

// 检测是否选中
const detectionAllSelect = () => {
	let selectNum = 0;
	goodsTable.data.forEach((item, index) => {
		if (item.secondLevelCheckAll) {
			selectNum++;
		}
	});

	if (selectNum > 0 && selectNum != goodsTable.data.length) {
		staircheckAll.value = false;
		isStairIndeterminate.value = true;
	} else if (selectNum > 0 && selectNum == goodsTable.data.length) {
		isStairIndeterminate.value = false;
		staircheckAll.value = true
	} else {
		isStairIndeterminate.value = false;
		staircheckAll.value = false;
	}
}

/**
 * 获取服务列表 - 核心修改：加载完成后自动选中goodsIds中的服务
 */
const loadGoodsList = (page: number = 1, callback: any = null) => {
	isStairIndeterminate.value = false;
	staircheckAll.value = false;
	goodsTable.loading = true;
	goodsTable.data = [];
	goodsTable.page = page

	const searchData = cloneDeep(goodsTable.searchParam);

	if (searchData.select_type == 'selected') {
		const goods_ids = <any>[]
		for (let k in selectGoods) {
			goods_ids.push(parseInt(k.replace(replacePrefix, '')))
		}
		searchData[replacePrefix + 'ids'] = goods_ids
	} else {
		searchData[replacePrefix + 'ids'] = '';
	}

	getGoodsSelectPageList({
		page: goodsTable.page,
		limit: goodsTable.limit,
		...searchData
	}).then(res => {
		let goodsTableData = cloneDeep(res.data.data);
		goodsTableData.forEach((item: any) => {
			item.isShow = false;
			item.isSecondLevelIndeterminate = false;
			item.secondLevelCheckAll = false;
			// 初始化sku选中状态
			if (item.skuList && item.skuList.length) {
				item.skuList.forEach((skuItem: any) => {
					skuItem.threeLevelCheckAll = false;
					skuItem.goods_type = item.goods_type;
				})
			}
		})

		// 关键修改1：根据goodsIds设置选中状态
		if (goodsIds.value.length) {
			if (prop.mode === 'spu') {
				// SPU模式：直接匹配服务ID
				goodsTableData.forEach(item => {
					if (goodsIds.value.includes(item.goods_id)) {
						item.secondLevelCheckAll = true;
						selectGoods[replacePrefix + item.goods_id] = deepClone(item);
					}
				});
			} else {
				// SKU模式：匹配规格ID
				goodsTableData.forEach(item => {
					item.skuList.forEach(sku => {
						if (goodsIds.value.includes(sku.sku_id)) {
							sku.threeLevelCheckAll = true;
							selectGoods[replacePrefix + sku.sku_id] = deepClone(sku);
							selectGoods[replacePrefix + sku.sku_id].goods_name = item.goods_name;
							selectGoods[replacePrefix + sku.sku_id].goods_type_name = item.goods_type_name;
						}
					});
					// 处理二级复选框状态
					const selectedSkuCount = item.skuList.filter(sku => sku.threeLevelCheckAll).length;
					item.secondLevelCheckAll = selectedSkuCount === item.skuList.length;
					item.isSecondLevelIndeterminate = selectedSkuCount > 0 && selectedSkuCount < item.skuList.length;
				});
			}
		}

		// 执行回调（如果有）
		if (callback) {
			callback(prop.mode == "spu" ? res.data.verify_goods_ids : res.data.verify_sku_ids, res.data.select_goods_list);
		}

		// 关键修改2：强制刷新选中状态
		nextTick(() => {
			setGoodsSelected();
		});

		goodsTable.data = goodsTableData;
		goodsTable.total = res.data.total;
		goodsTable.loading = false;

	}).catch(() => {
		goodsTable.loading = false;
	})
}

// 表格设置选中状态 - 优化选中状态同步
const setGoodsSelected = () => {
	nextTick(() => {
		if (prop.mode == "spu") {
			for (let i = 0; i < goodsTable.data.length; i++) {
				goodsTable.data[i].secondLevelCheckAll = !!selectGoods[replacePrefix + goodsTable.data[i].goods_id];
			}
		} else {
			for (let i = 0; i < goodsTable.data.length; i++) {
				const row = goodsTable.data[i];
				let selectedCount = 0;

				row.skuList.forEach((item, index) => {
					const isSelected = !!selectGoods[replacePrefix + item.sku_id];
					item.threeLevelCheckAll = isSelected;
					if (isSelected) selectedCount++;
				});

				row.secondLevelCheckAll = selectedCount === row.skuList.length;
				row.isSecondLevelIndeterminate = selectedCount > 0 && selectedCount < row.skuList.length;
			}
		}
		detectionAllSelect();
	});
}

const resetForm = (formEl: FormInstance | undefined) => {
	if (!formEl) return;
	formEl.resetFields();
	loadGoodsList();
}

const show = () => {
	// 清空现有选中状态
	for (let k in selectGoods) {
		delete selectGoods[k];
	}
	selectGoodsId.length = 0;

	replacePrefix = prop.mode == "sku" ? 'sku_' : 'goods_';

	// 检测服务id集合是否存在，移除不存在的服务id
	if (prop.mode == 'sku') {
		goodsTable.searchParam.verify_sku_ids = goodsIds.value;
	} else {
		goodsTable.searchParam.verify_goods_ids = goodsIds.value;
	}

	// 预先加载已选服务数据
	getGoodsSkuNoPageListFn().then(() => {
		// 加载服务列表（此时会自动选中goodsIds中的服务）
		loadGoodsList(1, (verify_ids: any) => {
			console.log('有效服务ID:', verify_ids);
		});
	});

	showDialog.value = true;
}

// 预加载已选服务详情
const getGoodsSkuNoPageListFn = () => {
	return new Promise((resolve) => {
		const searchData = cloneDeep(goodsTable.searchParam);
		// 确保ID为数字类型
		goodsIds.value.forEach((item: any, index: any, arr) => {
			arr[index] = Number(item);
		});

		getGoodsSkuNoPageList({ ...searchData }).then((res: any) => {
			const selectGoodsData = res.data || [];
			// 清空现有选中
			for (let k in selectGoods) {
				delete selectGoods[k];
			}

			// 赋值已选择的服务
			if (prop.mode == 'sku') {
				selectGoodsData.forEach((goods: any) => {
					goods.skuList.forEach((sku: any) => {
						if (goodsIds.value.includes(sku.sku_id)) {
							selectGoods[replacePrefix + sku.sku_id] = {
								...deepClone(sku),
								goods_name: goods.goods_name,
								goods_type_name: goods.goods_type_name,
								goods_type: goods.goods_type
							};
						}
					});
				});
			} else {
				selectGoodsData.forEach((goods: any) => {
					if (goodsIds.value.includes(goods.goods_id)) {
						selectGoods[replacePrefix + goods.goods_id] = deepClone(goods);
					}
				});
			}
			resolve(true);
		}).catch(() => {
			resolve(true);
		});
	});
}

// 清空已选服务
const clear = () => {
	for (let k in selectGoods) {
		delete selectGoods[k];
	}
	selectGoodsId.length = 0;
	setGoodsSelected();
}

const save = () => {
	if (prop.min && selectGoodsNum.value < prop.min) {
		ElMessage({
			type: 'warning',
			message: `${t('goodsSelectPopupGoodsMinTip')}${prop.min}${t('goodsSelectPopupPiece')}`,
		});
		return;
	}

	if (prop.max && prop.max > 0 && selectGoodsNum.value > prop.max) {
		ElMessage({
			type: 'warning',
			message: `${t('goodsSelectPopupGoodsMaxTip')}${prop.max}${t('goodsSelectPopupPiece')}`,
		});
		return;
	}

	if (prop.way == 'single') {
		let realTypeNum = 0;
		let virtualTypeNum = 0;
		for (let k in selectGoods) {
			if (selectGoods[k].goods_type == "virtual") {
				virtualTypeNum++;
			} else if (selectGoods[k].goods_type == "real") {
				realTypeNum++;
			}
		}

		if (realTypeNum !== Object.keys(selectGoods).length && virtualTypeNum !== Object.keys(selectGoods).length) {
			ElMessage({
				type: 'warning',
				message: `${t('wayPlaceholder')}`,
			});
			return;
		}
	}

	let ids: any = [];
	for (let k in selectGoods) {
		ids.push(parseInt(k.replace(replacePrefix, '')));
	}

	goodsIds.value.splice(0, goodsIds.value.length, ...ids);
	emit('goodsSelect', selectGoods);
	initSearchParam();
	showDialog.value = false;
}

// 重置表单搜索
const initSearchParam = () => {
	goodsTable.searchParam.keyword = '';
	goodsTable.searchParam.goods_category = [];
	goodsTable.searchParam.select_type = 'all';
	goodsTable.searchParam.goods_ids = '';
	goodsTable.searchParam.verify_goods_ids = '';
	goodsTable.searchParam.verify_sku_ids = '';
	goodsTable.searchParam.goods_type = '';
}

defineExpose({
	showDialog,
	selectGoods,
	selectGoodsNum
})
</script>

<style lang="scss" scoped>
.form-item-wrap {
	margin-right: 10px !important;
	margin-bottom: 10px !important;

	&.last-child {
		margin-right: 0 !important;
	}
}

.arrow-show {
	transform: rotate(90deg);
}
</style>