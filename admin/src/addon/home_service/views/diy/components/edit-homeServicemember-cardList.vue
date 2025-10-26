<template>
	<!-- 内容 -->
	<div class="content-wrap" v-show="diyStore.editTab == 'content'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('activeCubeBlockContentImage') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('swiperImage')">
					<upload-image v-model="diyStore.editComponent.leftBg" :limit="1" />
				</el-form-item>
			</el-form>
			<!-- <el-form-item :label="t('link')">
			    <diy-link v-model="diyStore.editComponent.linkUrl" />
			</el-form-item> -->
		</div>
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('activeCubeBlockContent') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('bgUrl')">
					<upload-image v-model="diyStore.editComponent.bgUrl" :limit="1" />
				</el-form-item>
			</el-form>
		</div>
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t("selectSource") }}</h3>
			
			<el-form-item :label="t('goodsSelectPopupSelectGoodsButton')">
			    <el-radio-group v-model="diyStore.editComponent.source">
			        <el-radio label="all">{{ t('全部次卡') }}</el-radio>
			        <el-radio label="custom">{{ t('manualSelectionSources') }}</el-radio>
			    </el-radio-group>
			</el-form-item>
			<el-form-item :label="t('selectCategory')" v-if="diyStore.editComponent.source == 'category'">
			    <div class="flex items-center w-full">
			        <div class="cursor-pointer ml-auto" @click="categoryShowDialogOpen(index)">
			            <span class="text-[var(--el-color-primary)]">{{ diyStore.editComponent.goods_category_name }}</span>
			            <span class="iconfont iconxiangyoujiantou"></span>
			        </div>
			    </div>
			</el-form-item>
			<el-form-item :label="t('goodsNum')" v-if="diyStore.editComponent.source == 'all' || diyStore.editComponent.source == 'category'">
			    <el-slider show-input class="diy-nav-slider" v-model="diyStore.editComponent.num" :min="1" max="3" size="small" />
			</el-form-item>
			<el-form-item :label="t('customGoods')" v-if="diyStore.editComponent.source == 'custom'">
			    <card-select-popup ref="goodsSelectPopupRef" v-model="diyStore.editComponent.goods_ids" :min="1"
			    	:max="4" />
			</el-form-item>
			<el-dialog v-model="categoryShowDialog" :title="t('goodsCategoryTitle')" width="750px"
				:destroy-on-close="true" :close-on-click-modal="false">
				<el-table :data="categoryTable.data" ref="categoryTableRef" size="large"
					v-loading="categoryTable.loading" height="450px" @selection-change="handleSelectionChange"
					row-key="category_id" :expand-row-keys="expand_category_ids"
					:tree-props="{ hasChildren: 'hasChildren', children: 'child_list' }">
					<template #empty>
						<span>{{ !categoryTable.loading ? t('emptyData') : '' }}</span>
					</template>
					<el-table-column type="selection" width="55" />
					<el-table-column :label="t('categoryName')" min-width="120">
						<template #default="{ row }">
							<span class="order-2">{{ row.category_name }}</span>
						</template>
					</el-table-column>
					<el-table-column :label="t('categoryImage')" width="170" align="left">
						<template #default="{ row }">
							<div class="h-[30px]">
								<el-image class="w-[30px] h-[30px] " :src="img(row.image)" fit="contain">
									<template #error>
										<div class="image-slot">
											<img class="w-[30px] h-[30px]"
												src="@/addon/home_service/assets/category_default.png" />
										</div>
									</template>
								</el-image>
							</div>
						</template>
					</el-table-column>
				</el-table>
				<div class="flex items-center justify-end mt-[15px]">
					<el-button type="primary" @click="saveCategoryId">{{ t('confirm') }}</el-button>
					<el-button @click="categoryShowDialog = false">{{ t('cancel') }}</el-button>
				</div>
			</el-dialog>
		</div>

	</div>

	<!-- 样式 -->
	<div class="style-wrap" v-show="diyStore.editTab == 'style'">
		<!-- 组件样式 -->
		<slot name="style"></slot>
	</div>
</template>

<script lang="ts" setup>
	import { getCategoryTree } from '@/addon/home_service/api/goods'
	import { t } from '@/lang'
	import { img } from '@/utils/common'
	import useDiyStore from '@/stores/modules/diy'
	import { ref, reactive, onMounted, nextTick } from 'vue'
	import { ElTable } from 'element-plus'
	import cardSelectPopup from '@/addon/home_service/views/goods/components/card-select-popup.vue'

	const diyStore : any = useDiyStore()
	diyStore.editComponent.ignore = [] // 忽略公共属性

	// 组件验证
	diyStore.editComponent.verify = (index : number) => {
		const res = { code: true, message: '' }

		if (diyStore.value[index].source == 'category') {
			if (diyStore.value[index].goods_category == '') {
				res.code = false
				res.message = t('goodsCategoryPlaceholder')
			}
		} else if (diyStore.value[index].source == 'custom') {
			if (diyStore.value[index].goods_ids.length == 0) {
				res.code = false
				res.message = t('goodsPlaceholder')
			}
		}
		return res
	}

	const categoryShowDialog = ref(false)

	const categoryTable = reactive({
		loading: true,
		data: []
	})
	onMounted(() => {
		loadCategoryList()
		btnStyleList.forEach((item, index, arr) => {
			if (item.type == 'button') {
				if (diyStore.editComponent.style == 'style-3') {
					item.isShow = false
				} else {
					item.isShow = true
				}
			}
		})
	})

	const styleChangeFn = (style) => {
		btnStyleList.forEach((item, index, arr) => {
			if (item.type == 'button') {
				if (style == 'style-3') {
					item.isShow = false
				} else {
					item.isShow = true
				}
			}
		})

		if (style == 'style-3') {
			diyStore.editComponent.btnStyle.style = btnStyleList[1].value
			diyStore.editComponent.btnStyle.cartEvent = 'detail'

			diyStore.editComponent.saleStyle.isShow = false
			diyStore.editComponent.labelStyle.isShow = false
		} else {
			diyStore.editComponent.btnStyle.style = btnStyleList[0].value

			diyStore.editComponent.saleStyle.isShow = true
			diyStore.editComponent.labelStyle.isShow = true
		}
		diyStore.editComponent.style = style
	}

	const btnStyleList = reactive([
	])

	const changeBtnStyle = (item : any) => {
		diyStore.editComponent.btnStyle.style = item.value
	}

	const categoryTableRef = ref<InstanceType<typeof ElTable>>()
	/**
	 * 获取服务分类列表
	 */
	let currCategoryData : any = null
	const loadCategoryList = () => {
		categoryTable.loading = true

		getCategoryTree().then(res => {
			categoryTable.loading = false
			categoryTable.data = res.data
		}).catch(() => {
			categoryTable.loading = false
		})
	}

	// 选择服务分类
	const handleSelectionChange = (val : string | any[]) => {
		let data = ''
		if (val) data = val[val.length - 1]
		if (val.length > 1) categoryTableRef.value!.clearSelection()
		if (data) categoryTableRef.value!.toggleRowSelection(data, true)
		currCategoryData = data
	}

	const saveCategoryId = () => {
		diyStore.editComponent.goods_category = currCategoryData.category_id
		diyStore.editComponent.goods_category_name = currCategoryData.category_name
		categoryShowDialog.value = false
	}

	const categoryShowDialogOpen = () => {
		categoryShowDialog.value = true
		nextTick(() => {
			setRowSelection()
		})
	}

	// 分类数据选中回填,设置展开行
	const expand_category_ids = ref<Array<any>>([])
	const setRowSelection = () => {
		expand_category_ids.value = []
		categoryTable.data.forEach((el : any) => {
			if (diyStore.editComponent.goods_category == el.category_id) {
				categoryTableRef.value!.toggleRowSelection(el, true)
			} else if (el.child_list && el.child_list.length) {
				el.child_list.forEach((v : any) => {
					if (diyStore.editComponent.goods_category == v.category_id) {
						expand_category_ids.value.push(el.category_id.toString())
						categoryTableRef.value!.toggleRowSelection(v, true)
					}
				})
			}
		})
	}

	defineExpose({})
</script>
<style lang="scss">
	.goods-list-slider {
		.el-slider__input {
			width: 100px;
		}
	}
</style>