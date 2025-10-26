<template>
    <!-- 内容 -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">{{ t("manyGoodsListCategorySet") }}</h3>
            <el-form label-width="80px" class="px-[10px]">
                <!-- <el-form-item :label="t('dataSources')">
                    <el-radio-group v-model="diyStore.editComponent.source">
                        <el-radio label="custom">{{ t('manyGoodsListSourceDiy') }}</el-radio>
                        <el-radio label="goods_category">{{ t('manyGoodsListSourceCategory') }}</el-radio>
                    </el-radio-group>
                </el-form-item> -->
                <!-- <el-form-item :label="t('goodsCategoryTitle')" v-show="diyStore.editComponent.source == 'goods_category'">
                    <el-input v-model.trim="diyStore.editComponent.goods_category_name" :placeholder="t('selectCategory')" readonly class="select-diy-page-input" @click="firstCategoryShowDialogOpen()">
                        <template #suffix>
                            <div @click.stop="clearCategory">
                                <el-icon v-if="diyStore.editComponent.goods_category_name">
                                    <Close />
                                </el-icon>
                                <el-icon v-else>
                                    <ArrowRight />
                                </el-icon>
                            </div>
                        </template>
                    </el-input>
                </el-form-item> -->
                <div >
                    <p class="text-sm text-gray-400 mb-[10px]">{{ t('dragMouseAdjustOrder') }}</p>
                    <div ref="goodsBoxRef">
                        <div v-for="(item,index) in diyStore.editComponent.list" :key="item.id"
                             class="item-wrap p-[10px] pb-0 relative border border-dashed border-gray-300 mb-[16px]">
							 <el-form-item :label="t('swiperImage')">
								 <upload-image v-model="item.bgUrl" :limit="4"/>
							 </el-form-item>
                            <el-form-item :label="t('manyGoodsListCategoryName')">
                                <el-input v-model.trim="item.title" clearable maxlength="4" show-word-limit />
                            </el-form-item>
                            <!-- <el-form-item :label="t('manyGoodsListSubTitle')" v-show="diyStore.editComponent.headStyle == 'style-1'">
                                <el-input v-model.trim="item.desc" clearable maxlength="5" show-word-limit />
                            </el-form-item> -->
                            <el-form-item :label="t('goodsSelectPopupSelectGoodsButton')">
                                <el-radio-group v-model="item.source">
                                    <el-radio label="all">{{ t('goodsSelectPopupAllGoods') }}</el-radio>
                                    <el-radio label="category">{{ t('selectCategory') }}</el-radio>
                                    <el-radio label="custom">{{ t('manualSelectionSources') }}</el-radio>
                                </el-radio-group>
                            </el-form-item>
                            <el-form-item :label="t('selectCategory')" v-if="item.source == 'category'">
                                <div class="flex items-center w-full">
                                    <div class="cursor-pointer ml-auto" @click="categoryShowDialogOpen(index)">
                                        <span class="text-[var(--el-color-primary)]">{{ item.goods_category_name }}</span>
                                        <span class="iconfont iconxiangyoujiantou"></span>
                                    </div>
                                </div>
                            </el-form-item>
							<el-form-item :label="t('goodsNum')" v-if="item.source == 'all' || item.source == 'category'">
							    <el-slider show-input class="diy-nav-slider" v-model="item.num" :min="1" max="5" size="small" />
							</el-form-item>
                            <el-form-item :label="t('customGoods')" v-if="item.source == 'custom'">
                                <goods-select-popup ref="goodsSelectPopupRef" v-model="item.goods_ids" :min="1" :max="99" />
                            </el-form-item>
                            <el-form-item :label="t('image')" v-show="diyStore.editComponent.headStyle == 'style-3'">
                                <upload-image v-model="item.imageUrl" :limit="1" />
                            </el-form-item>

                            <div class="del absolute cursor-pointer z-[2] top-[-8px] right-[-8px]"
                                 v-show="diyStore.editComponent.list.length > 1"
                                 @click="diyStore.editComponent.list.splice(index,1)">
                                <icon name="element CircleCloseFilled" color="#bbb" size="20px" />
                            </div>

                        </div>
                    </div>

                    <el-button class="w-full" @click="addItem">{{ t('manyGoodsLisAddItem') }}</el-button>
                </div>

            </el-form>
            <!-- 选择一级服务分类弹出框 -->
            <el-dialog v-model="firstCategoryShowDialog" :title="t('goodsCategoryTitle')" width="750px" :destroy-on-close="true" :close-on-click-modal="false">
                <el-table :data="firstCategoryTable.data" ref="firstCategoryTableRef" size="large"
                          v-loading="firstCategoryTable.loading" height="450px"
                          @current-change="handleCurrentCategoryChange" row-key="category_id" highlight-current-row>
                    <template #empty>
                        <span>{{ !firstCategoryTable.loading ? t('emptyData') : '' }}</span>
                    </template>
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
                                            <img class="w-[30px] h-[30px]" src="@/addon/home_service/assets/category_default.png" />
                                        </div>
                                    </template>
                                </el-image>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="flex items-center justify-end mt-[15px]">
                    <el-button type="primary" @click="saveFirstCategoryId">{{ t('confirm') }}</el-button>
                    <el-button @click="firstCategoryShowDialog = false">{{ t('cancel') }}</el-button>
                </div>
            </el-dialog>

            <el-dialog v-model="categoryShowDialog" :title="t('goodsCategoryTitle')" width="750px" :destroy-on-close="true" :close-on-click-modal="false">
                <div class="table w-[100%] mt-[15px]" v-loading="categoryTable.loading">
                    <div class="table-head flex items-center bg-[#f5f7f9] py-[10px] text-[14px]" :style="{ paddingRight: scrollBarWidth + 'px' }">
                        <div class="w-[6%]"></div>
                        <div class="w-[10%]">
                            <!-- <el-checkbox v-model="staircheckAll" :indeterminate="isStairIndeterminate" @change="handleCheckAllChange" /> -->
                        </div>
                        <div class="w-[50%]">{{ t('categoryName') }}</div>
                        <div class="w-[34%] h-[30px] leading-[30px]">{{ t('categoryImage') }}</div>
                    </div>
                    <div class="table-body max-h-[500px] overflow-y-auto" ref="tableBodyRef">
                        <!-- 遍历一级分类 -->
                        <div v-for="(row, rowIndex) in categoryTable.data" :key="rowIndex" class="flex flex-col">
                            <div class="flex items-center border-solid border-[#e5e7eb] py-[10px] border-b-[1px]">
                                <!-- 图标：展开/收起子级 -->
                                <div v-if="row.children && row.children.length" class="w-[6%] cursor-pointer text-center !text-[10px]" @click="secondLevelArrowChange(row)" :class="{ 'iconfont iconxiangyoujiantou': row.children.length, 'arrow-show': row.isShow }"></div>
                                <div v-else class="w-[6%]"></div>
                                <!-- 一级分类复选框 -->
                                <div class="w-[10%]">
                                    <el-checkbox v-model="row.secondLevelCheckAll" :indeterminate="row.isSecondLevelIndeterminate" @change="handleCheckboxChange($event, row)" />
                                </div>
                                <!-- 一级分类名称 -->
                                <div class="ml-2 flex flex-col items-start w-[50%]">
                                    <span :title="row.category_name" class="multi-hidden leading-[1.4] mr-5 text-[14px] text-[#666]">
                                        {{ row.category_name }}
                                    </span>
                                </div>
                                <!-- 一级分类图片 -->
                                <div class="flex items-center cursor-pointer w-[34%]">
                                    <div class="min-w-[30px] h-[30px] flex items-center justify-center">
                                        <el-image v-if="row.image" class="w-[30px] h-[30px]" :src="img(row.image)" fit="contain">
                                            <template #error>
                                                <div class="image-slot">
                                                    <img class="w-[30px] h-[30px]" src="@/addon/home_service/assets/category_default.png" />
                                                </div>
                                            </template>
                                        </el-image>
                                        <img v-else class="w-[30px] h-[30px]" src="@/addon/home_service/assets/category_default.png" fit="contain" />
                                    </div>
                                </div>
                            </div>
                            <!-- 子级分类 -->
                            <div v-show="row.children && row.isShow">
                                <div  v-for="(item, index) in row.children" :key="index" class="flex items-center py-[10px] border-solid border-b-[1px]" :class="{ 'hidden': !row.isShow, 'border-[#e5e7eb]': index == (row.children.length - 1) }">
                                    <div class="w-[9%]"></div>
                                    <!-- 子级分类复选框 -->
                                    <div class="w-[7%]">
                                        <el-checkbox v-model="item.threeLevelCheckAll" @change="handleCheckboxChange($event, item, row)" />
                                    </div>
                                    <!-- 子级分类名称 -->
                                    <div class="ml-2 flex flex-col items-start w-[50%]">
                                        <span :title="item.category_name" class="multi-hidden leading-[1.4] mr-5 text-[14px] text-[#666]">
                                            {{ item.category_name }}
                                        </span>
                                    </div>
                                    <!-- 子级分类图片 -->
                                    <div class="flex items-center cursor-pointer w-[34%]">
                                        <div class="min-w-[30px] h-[30px] flex items-center justify-center">
                                            <el-image v-if="row.image" class="w-[30px] h-[30px]" :src="img(row.image)" fit="contain">
                                                <template #error>
                                                    <div class="image-slot">
                                                        <img class="w-[30px] h-[30px]" src="@/addon/home_service/assets/category_default.png" />
                                                    </div>
                                                </template>
                                            </el-image>
                                            <img v-else class="w-[30px] h-[30px]" src="@/addon/home_service/assets/category_default.png" fit="contain" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!categoryTable.data.length && !categoryTable.loading" class="h-[60px] flex items-center justify-center border-solid border-[#e5e7eb] py-[12px] border-b-[1px]">{{ t('emptyData') }}</div>
                    </div>
                </div>
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
import { t } from '@/lang'
import { img } from '@/utils/common'
import useDiyStore from '@/stores/modules/diy'
import { ref, reactive, onMounted, nextTick } from 'vue'
import { ElTable } from 'element-plus'
import { range } from 'lodash-es'
import Sortable from 'sortablejs'
import { getCategoryTree, getCategoryList } from '@/addon/home_service/api/goods'
import goodsSelectPopup from '@/addon/home_service/views/goods/components/goods-select-popup.vue'

const diyStore: any = useDiyStore()
diyStore.editComponent.ignore = ['componentBgUrl'] // 忽略公共属性

// 组件验证
diyStore.editComponent.verify = (index: number) => {
    const res = { code: true, message: '' }

    if (diyStore.value[index].source == 'custom') {

        diyStore.value[index].list.forEach((item: any) => {
            if (item.source === 'category') {
                if (item.goods_category == '') {
                    res.code = false
                    res.message = t('goodsCategoryPlaceholder')
                    return res
                }
            } else if (item.source == 'custom') {
                if (item.goods_ids.length == 0) {
                    res.code = false
                    res.message = t('goodsPlaceholder')
                }
            }
        });
    } else if (diyStore.value[index].source == 'goods_category') {
        if (diyStore.value[index].goods_category == '') {
            res.code = false
            res.message = t('goodsCategoryPlaceholder')
            return res
        }
    }

    return res
}

diyStore.editComponent.list.forEach((item: any) => {
    if (!item.id) item.id = diyStore.generateRandom()
})

// 一级服务分类
const firstCategoryShowDialog = ref(false)

const firstCategoryTable = reactive({
    loading: true,
    data: [],
    searchParam: {
        level: 1
    }
})

const firstCategoryTableRef = ref<InstanceType<typeof ElTable>>()

/**
 * 获取服务分类列表
 */
const loadCategoryList = () => {
    firstCategoryTable.loading = true

    getCategoryList({
        ...firstCategoryTable.searchParam
    }).then(res => {
        firstCategoryTable.loading = false
        firstCategoryTable.data = res.data.data
    }).catch(() => {
        firstCategoryTable.loading = false
    })
}

const saveFirstCategoryId = () => {
    diyStore.editComponent.goods_category = currFirstCategory.category_id
    diyStore.editComponent.goods_category_name = currFirstCategory.category_name;
    firstCategoryShowDialog.value = false
}

const firstCategoryShowDialogOpen = () => {
    firstCategoryShowDialog.value = true
    if (currFirstCategory) {
        setTimeout(() => {
            firstCategoryTableRef.value!.setCurrentRow(currFirstCategory)
        }, 200)
    }
}

const btnStyleList = reactive([
    {
        isShow: true,
        type: 'button',
        title: diyStore.editComponent.btnStyle.text,
        value: 'button'
    },
    {
        isShow: true,
        type: 'icon',
        title: 'nc-icon-jiahaoV6xx',
        value: 'nc-icon-jiahaoV6xx'
    },
    {
        isShow: true,
        type: 'icon',
        title: 'nc-icon-gouwuche1',
        value: 'nc-icon-gouwuche1'
    }
])
diyStore.editComponent.btnStyle.style = 'nc-icon-jiahaoV6xx';

const changeBtnStyle = (item: any) => {
    diyStore.editComponent.btnStyle.style = item.value
}

const clearCategory = () => {
    diyStore.editComponent.goods_category = ''
    diyStore.editComponent.goods_category_name = '';
}

// 选择服务分类
let currFirstCategory: any = {}
const handleCurrentCategoryChange = (val: string | any[]) => {
    currFirstCategory = val
}

// 服务分类树结构
const categoryShowDialog = ref(false)

const goodsBoxRef = ref()

const categoryTable = reactive({
    loading: true,
    data: []
})

onMounted(() => {
    loadCategoryTree()

    loadCategoryList()

    nextTick(() => {
        const sortable = Sortable.create(goodsBoxRef.value, {
            group: 'item-wrap',
            animation: 200,
            onEnd: event => {
                const temp = diyStore.editComponent.list[event.oldIndex!]
                diyStore.editComponent.list.splice(event.oldIndex!, 1)
                diyStore.editComponent.list.splice(event.newIndex!, 0, temp)
                sortable.sort(
                    range(diyStore.editComponent.list.length).map(value => {
                        return value.toString()
                    })
                )
            }
        })
    })
    window.addEventListener("resize", getScrollBarWidth);
})

/**
 * 获取服务分类列表
 */
// let currCategoryData: any = null
const loadCategoryTree = () => {
    categoryTable.loading = true

    getCategoryTree().then(res => {
        categoryTable.loading = false
        categoryTable.data = res.data
        categoryTable.data.forEach((item: any) => {
            // 初始化一级分类的字段
            item.isShow = false; // 控制子级是否展开
            item.isSecondLevelIndeterminate = false; // 一级分类不确定状态
            item.secondLevelCheckAll = false; // 一级分类复选框状态

            // 如果有子分类（children），初始化子分类的字段
            if (item.children && item.children.length) {
                item.children.forEach((childItem: any) => {
                    childItem.threeLevelCheckAll = false // 子分类复选框状态
                })
            }
        })
    }).catch(() => {
        categoryTable.loading = false
    })
}

const addItem = () => {
    diyStore.editComponent.list.push({
        id: diyStore.generateRandom(),
        title: "分类",
        desc: "分类描述",
        source: "all",
        goods_category: '',
        goods_category_name: '请选择',
        goods_ids: [],
        imageUrl: ''
    })
}

const scrollBarWidth = ref(0);
const tableBodyRef = ref(null);

const getScrollBarWidth = () => {
    nextTick(() => {
        if (tableBodyRef.value) {
            scrollBarWidth.value = tableBodyRef.value.offsetWidth - tableBodyRef.value.clientWidth;
        }
    })
}

// 选择服务分类
let selectIndex = 0; // 当前选择的下标
const selectedCategories = ref({});
// 方法：切换子级展开/收起
const secondLevelArrowChange = (row:any) => {
    row.isShow = !row.isShow;
    nextTick(() => getScrollBarWidth()); 
};

const saveCategoryId = () => {
    const selected = selectedCategories.value[selectIndex];
    if (!selected || !selected.category_id) { // 确保 `category_id` 存在
        ElMessage({
            type: 'warning',
            message: t('请选择分类'),
        });
        return;
    }
    diyStore.editComponent.list[selectIndex].goods_category = selectedCategories.value[selectIndex].category_id
    diyStore.editComponent.list[selectIndex].goods_category_name = selectedCategories.value[selectIndex].category_name;
    categoryShowDialog.value = false
}

const clearAllSelections = () => {
    categoryTable.data.forEach((row: any) => {
        row.secondLevelCheckAll = false;
        if (row.children) {
            row.children.forEach((child: any) => {
                child.threeLevelCheckAll = false;
            });
        }
    });
}

const categoryShowDialogOpen = (index:any) => {
    selectIndex = index;
    clearAllSelections();

    // 设置 isShow 状态
    categoryTable.data.forEach((row: any) => {
        row.isShow = false; // 默认所有分类都是合住的
    });
    // 确保 `selectedCategories.value[selectIndex]` 存在
    if (!selectedCategories.value[selectIndex]) {
        selectedCategories.value[selectIndex] = {}; // 初始化为空对象
    }
    // 回显已选中的分类
    nextTick(() => {
        const selectedCategory = diyStore.editComponent.list[selectIndex];
        // 初始化 selectedCategories.value[selectIndex]
        if (!selectedCategories.value[selectIndex]) {
            selectedCategories.value[selectIndex] = {}; // 初始化为空对象
            selectedCategories.value[selectIndex].category_id = selectedCategory.goods_category;
            selectedCategories.value[selectIndex].category_name = selectedCategory.goods_category_name;
        }


        if (selectedCategory) {
            categoryTable.data.forEach((row: any) => {
                if (row.category_id === selectedCategory.goods_category) {
                    row.secondLevelCheckAll = true;
                    row.isShow = true; // 展开选中的一级分类
                }
                if (row.children) {
                    row.children.forEach((child: any) => {
                        if (child.category_id === selectedCategory.goods_category) {
                            child.threeLevelCheckAll = true;
                            row.isShow = true;
                        }
                    });
                }
            });
        }
    });
    nextTick(() => getScrollBarWidth());
    categoryShowDialog.value = true;
}

// 处理复选框变化
const handleCheckboxChange = (checked: any, target: any, parentRow: any) => {
    clearAllSelections(); // 清空所有复选框的选中状态
    if (checked) {
        // 设置当前选中的分类
        if (parentRow) {
            // 如果是子分类
            target.threeLevelCheckAll = checked;
            selectedCategories.value[selectIndex] = target;
            parentRow.isShow = true; // 展开父级分类
        } else {
            // 如果是一级分类
            target.secondLevelCheckAll = checked;
            selectedCategories.value[selectIndex] = target;
            target.isShow = true; // 展开选中的一级分类
        }
    } else {
        // 取消勾选时，清空选中的分类 ID
        delete selectedCategories.value[selectIndex];
    }
}

defineExpose({})

</script>

<style lang="scss" scoped>
.arrow-show {
  transform: rotate(90deg) !important; /* 提高优先级 */
}
</style>
