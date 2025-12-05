<!-- uni-app/src/addon/phone_shop/pages/manager/add_goods.vue -->
<template>
    <view class="add-goods-container">
        <!-- 顶部导航栏 -->
        <view class="flex items-center fixed left-0 right-0 z-10 bg-transparent detail-head"
            :class="{ '!bg-[#fff]': detailHeadBgChange }" :style="navbarInnerStyle">
            <text class="nc-iconfont nc-icon-zuoV6xx" :style="navbarInnerArrowStyle" @click="nav_back"></text>

            <view class="publish-btn" @click="submitForm">
                <text>{{ goods_id ? '保存' : '发布' }}</text>
            </view>
        </view>

        <!-- 主内容区域 -->
        <scroll-view scroll-y class="content-scroll">
            <!-- 图片上传区 -->
            <view class="form-item upload-section">
                <!-- <upload-img ref="uploadImg" :modelValue="formData.goods_image"
                    @update:modelValue="val => formData.goods_image = val" :max="9" :multiple="true"
                    @change="handleImagesChange" /> -->

                <upload-img class="mt-[20rpx]" v-model="formData.goods_image" :max-count="9" :multiple="true" />
                <text class="upload-tip">上传商品图片，第一张将作为封面图</text>
            </view>

            <!-- 分类选择 -->
            <view class="form-item category-section">
                <view class="section-header">
                    <text class="title">属性</text>
                    <text class="path">{{ displayCategoryNames || '请选择' }}</text>
                </view>
                <view class="section-content ">
                    <view class="text-[22rpx] w-[100rpx] section-content-label"> 一级分类: </view>
                    <scroll-view scroll-x class="scroll-tags" :show-scrollbar="false">
                        <view class="tag-list">
                            <view v-for="category in categoryTree" :key="category.category_id" class="tag"
                                :class="{ active: isSelected(category) }" @tap="handleFirstLevelSelect(category)">{{
                                    category.category_name }}</view>
                        </view>
                    </scroll-view>
                </view>
                <view class="section-content " v-if="selectedFirstLevel?.child_list?.length">
                    <view class="text-[22rpx] w-[100rpx] section-content-label"> 二级分类: </view>
                    <scroll-view scroll-x class="scroll-tags" :show-scrollbar="false">
                        <view class="tag-list">
                            <text v-for="subCategory in selectedFirstLevel.child_list" :key="subCategory.category_id"
                                class="tag" :class="{ active: isSelected(subCategory) }"
                                @tap="handleSecondLevelSelect(subCategory)">{{
                                    subCategory.category_name }}</text>
                        </view>
                    </scroll-view>
                </view>

                <!-- 内存选择 -->
                <view class=" section-content memory-section" v-if="memoryList.length">
                    <view class="text-[22rpx] w-[100rpx] section-content-label"> 内存: </view>


                    <scroll-view scroll-x class="scroll-tags" :show-scrollbar="false">
                        <view class="memory-list tag-list">

                            <view class="memory-item tag" v-for="item in memoryList" :key="item.spec_id"
                                :class="{ 'active': formData.memory_id === item.spec_id.toString() }"
                                @click="selectMemory(item)">
                                <text>{{ item.spec_name }} </text>
                            </view>
                        </view>
                    </scroll-view>

                </view>

                <!-- 品牌选择 -->
                <view class="section-content  brand-section ">
                    <!-- 横向滚动 -->
                    <view class="text-[22rpx] w-[100rpx] section-content-label"> 等级: </view>
                    <scroll-view scroll-x class="scroll-tags" :show-scrollbar="false">
                        <view class="tag-list">
                            <text v-for="brand in brandList" :class="{ active: formData.brand_id == brand.brand_id }"
                                :key="brand.brand_id" class="tag" @click="handleBrandSelect(brand)">{{
                                    brand.brand_name }}</text>
                        </view>
                    </scroll-view>
                </view>
            </view>
            <!--  标题 -->
            <view class="form-item title-section">
                <input type="text" v-model="fullGoodsName" placeholder="请输入商品标题" />
            </view>
            <!-- sn -->
            <view class="form-item sn-section">
                <input type="text" v-model="formData.sku_no" placeholder="请输入商品SN码" />
            </view>

            <!-- 商品描述 -->
            <view class="form-item desc-section ">
                <textarea class="goods-desc text-sm box-border" v-model="formData.goods_desc"
                    placeholder="添加商品描述，让买家更了解商品..." :maxlength="500" auto-height />
            </view>

            <!-- 价格和库存 -->
            <view class="form-item price-section">
                <view class="price-tabs">
                    <view class="tab" :class="{ active: activeTab === 'price' }" @click="activeTab = 'price'">价格</view>
                    <view class="tab" :class="{ active: activeTab === 'stock' }" @click="activeTab = 'stock'">库存</view>
                </view>
                <view class="tab-content">
                    <view v-if="activeTab === 'price'">
                        <view class="price-row">
                            <text class="label">同行价格</text>
                            <view class="price-input-wrap">
                                <text class="currency">¥</text>
                                <input class="price-input" type="digit" v-model="formData.market_price"
                                    placeholder="0.00" />
                            </view>
                        </view>
                        <view class="price-row">
                            <text class="label">价格</text>
                            <view class="price-input-wrap">
                                <text class="currency">¥</text>
                                <input class="price-input" type="digit" v-model="formData.price" placeholder="0.00" />
                            </view>
                        </view>
                        <view class="price-row">
                            <text class="label">成本价格</text>
                            <view class="price-input-wrap">
                                <text class="currency">¥</text>
                                <input class="price-input" type="digit" v-model="formData.cost_price"
                                    placeholder="0.00" />
                            </view>
                        </view>
                    </view>
                    <view v-else-if="activeTab === 'stock'">
                        <view class="stock-row">

                            <view class="stock-control flex justify-between">
                                <!--  开关  -->
                                <text class="label">上下架</text>
                                <up-switch v-model="formData.status" size='20' :active-value="1" :inactive-value="0" />
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </scroll-view>
    </view>
</template>



<script lang="ts" setup>
import { defineComponent, reactive, computed, ref, onMounted } from 'vue';
import { addGoods, getGoodsCategoryTree, getBrandList, getMemoryList, getGoodsDetail, updateGoods } from '@/addon/phone_shop/api/goods';
import uploadImg from '@/addon/phone_shop/pages/evaluate/components/upload-img'
import { getVerifierInfo, getCheckVerifier } from '@/app/api/verify'
import { onShow, onLoad, onPageScroll } from '@dcloudio/uni-app'
// 引入common
import { img, redirect, getToken } from '@/utils/common'
// 

interface Category {
    category_id: number;
    category_name: string;
    image: string;
    level: number;
    pid: number;
    category_full_name: string;
    group_name: string;
    child_list?: Category[];
}

interface Brand {
    brand_id: number;
    brand_name: string;
    create_time: string;
    desc: string;
    logo: string;
    site_id: number;
    sort: number;
}

interface FormData {
    goods_name: string;
    goods_desc: string;
    goods_cover: string;
    price: string;
    stock: number;
    goods_category: string[];
    label_ids: string[];
    service_ids: string[];
    status: number;
    goods_image: string[];
    brand_id: string;
    sku_no: string;
    market_price: string;
    cost_price: string;
    memory_id: string;
}

interface ApiResponse<T> {
    code: number;
    data: T;
    message: string;
}

// 获取商品ID
const goods_id = ref<string>('');

// 获取页面参数
onLoad((options: any) => {

    if (options.id) {
        goods_id.value = options.id;
        getGoodsInfo();
    }
});

// 获取商品详情
const getGoodsInfo = async () => {
    try {
        const res = await getGoodsDetail({ goods_id: goods_id.value });
        if (res.code === 1 && res.data) {
            const goodsData = res.data;
            // 填充表单数据
            formData.goods_name = goodsData.goods.goods_name;

            formData.sub_title = goodsData.goods.sub_title;

            formData.sku_no = goodsData.sku_no;
            formData.category_id = goodsData.goods.goods_category;
            formData.brand_id = goodsData.goods.brand_id;
            formData.memory_id = goodsData.goods.memory_ids?.toString();  // 确保转换为字符串
            // formData.memory_name = goodsData.goods.memory_name;
            formData.market_price = goodsData.market_price;
            formData.price = goodsData.price;
            formData.cost_price = goodsData.cost_price;
            formData.status = goodsData.goods.status;
            formData.goods_image = goodsData.goods.goods_image.split(',');
            fullGoodsName.value = goodsData.goods.goods_name;

            // 设置分类
            await loadCategoryTree();
            // // 将返回的分类ID字符串转换为数组
            const categoryIds = goodsData.goods.goods_category.map(Number);


            // // 设置分类树选中状态
            setCategoryByIds(categoryIds);
        }
    } catch (error) {
        uni.showToast({
            title: '获取商品信息失败',
            icon: 'none'
        });
    }
};

const formData = reactive<FormData>({
    full_goods_name: '',
    goods_name: '',
    goods_desc: '',
    goods_cover: '',
    price: '',
    stock: 1,
    goods_category: [],
    label_ids: [],
    service_ids: [],
    status: 1,
    goods_image: [],
    brand_id: '',
    sku_no: '',
    market_price: '',
    memory_id: '',
    memory_name: '',
    cost_price: ''
});

// 根据分类ID数组设置分类树选中状态
const setCategoryByIds = (categoryIds: number[]) => {
    if (!categoryIds || categoryIds.length < 2) return;

    const firstLevelId = categoryIds[0];
    const secondLevelId = categoryIds[1];

    // 查找并设置一级分类
    const firstLevel = categoryTree.value.find(category => category.category_id === firstLevelId);
    if (firstLevel) {
        selectedFirstLevel.value = firstLevel;
        formData.goods_category = [firstLevelId.toString()];

        // 查找并设置二级分类
        if (firstLevel.child_list) {
            const secondLevel = firstLevel.child_list.find(category => category.category_id === secondLevelId);
            if (secondLevel) {
                formData.category_id = secondLevel.category_id;
                formData.goods_category.push(secondLevelId.toString());
                // 获取内存列表
                fetchMemoryList();
            }
        }
    }
}

// loading
const loading = ref(false);
// 处理图片变化
const handleImagesChange = (images: string[]) => {
    formData.goods_image = images;
    if (images.length > 0) {
        formData.goods_cover = images[0];
    }
};

// 提交表单
const submitForm = async () => {
    try {
        // 表单验证
        // if (!formData.full_goods_name) {
        //     uni.showToast({
        //         title: '请输入商品标题',
        //         icon: 'none'
        //     });
        //     return;
        // }
        console.log(formData.category_id);

        // if (!formData.category_id) {
        //     uni.showToast({
        //         title: '请选择商品分类',
        //         icon: 'none'
        //     });
        //     return;
        // }
        if (!formData.brand_id) {
            uni.showToast({
                title: '请选择商品品牌',
                icon: 'none'
            });
            return;
        }
        if (!formData.goods_image || formData.goods_image.length === 0) {
            uni.showToast({
                title: '请上传商品图片',
                icon: 'none'
            });
            return;
        }

        const submitData = {
            ...formData,
            goods_image: formData.goods_image
        };

        let res;
        // memory_ids = [memory_id]
        submitData.memory_ids = submitData.memory_id;
        // full_goods_name 
        submitData.goods_name = fullGoodsName.value;
        if (goods_id.value) {
            // 更新商品
            res = await updateGoods(goods_id.value, submitData);
        } else {
            // 添加商品
            res = await addGoods(submitData);
        }

        if (res.code === 1) {
            uni.showToast({
                title: goods_id.value ? '修改成功' : '发布成功',
                icon: 'success'
            });
            setTimeout(() => {
                nav_back();
            }, 1500);
        } else {
            uni.showToast({
                title: res.msg || (goods_id.value ? '修改失败' : '发布失败'),
                icon: 'none'
            });
        }
    } catch (error) {
        uni.showToast({
            title: goods_id.value ? '修改失败' : '发布失败',
            icon: 'none'
        });
    }
};

// 分类相关数据
const categoryTree = ref<Category[]>([]);
const brandList = ref<Brand[]>([]);
const showCategoryPopup = ref(false);

// 显示分类名称
const displayCategoryNames = computed(() => {
    const names = formData.goods_category
        .map(id => getCategoryNameById(id))
        .filter(Boolean);
    // 拼接到 商品的名字
    formData.goods_name = names.length ? names.join(' ') : '';
    return names.length ? names.join(' > ') : '';
});

// 获取分类树
const loadCategoryTree = async () => {
    try {
        const res = await getGoodsCategoryTree() as ApiResponse<Category[]>;
        if (res?.data) {
            categoryTree.value = res.data;
        }
    } catch (error) {
        console.error('获取分类失败:', error);
        uni.showToast({
            title: '获取分类失败',
            icon: 'none'
        });
    }
};

// 获取品牌列表
const loadBrandList = async () => {
    try {
        const res = await getBrandList() as ApiResponse<Brand[]>;
        if (res?.data) {
            brandList.value = res.data;
        }
    } catch (error) {
        console.error('获取品牌失败:', error);
        uni.showToast({
            title: '获取品牌失败',
            icon: 'none'
        });
    }
};

// 当前选中的一级分类
const selectedFirstLevel = ref<Category | null>(null);

// 选择一级分类
const handleFirstLevelSelect = (category: Category) => {
    if (selectedFirstLevel.value?.category_id === category.category_id) {
        selectedFirstLevel.value = null;
        formData.goods_category = [];
        formData.category_id = '';
    } else {
        selectedFirstLevel.value = category;
        formData.goods_category = [category.category_id.toString()];
        if (!category.child_list?.length) {
            showCategoryPopup.value = false;
        }
    }
};

// 选择二级分类
const handleSecondLevelSelect = (category: Category) => {
    if (selectedFirstLevel.value) {
        formData.category_id = category.category_id;
        formData.goods_category = [
            selectedFirstLevel.value.category_id.toString(),
            category.category_id.toString()
        ];
        fetchMemoryList();
    }
};

// 选择品牌
const handleBrandSelect = (brand: Brand) => {
    formData.brand_id = brand.brand_id.toString();
};

// 判断分类是否被选中
const isSelected = (category: Category): boolean => {
    if (!formData.goods_category) return false;
    return formData.goods_category.includes(category.category_id.toString());
}

// 根据分类ID获取分类名称
const getCategoryNameById = (categoryId: string): string => {
    const findCategory = (categories: Category[]): string => {
        for (const category of categories) {
            if (category.category_id.toString() === categoryId) {
                return category.category_name;
            }
            if (category.child_list?.length) {
                const name = findCategory(category.child_list);
                if (name) return name;
            }
        }
        return '';
    };
    return findCategory(categoryTree.value);
};

// 导航返回
const nav_back = () => {
    uni.navigateBack();
};

// 导航到首页
const nav_home = () => {
    // 跳转首页
    redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' })
};

// 检测是否是核销员
const checkIsVerifier = () => {
    getCheckVerifier().then((res: any) => {
        if (!res.data) {
            uni.showToast({
                title: '非操作员无此权限',
                icon: 'none'
            });
            setTimeout(() => {
                if (getCurrentPages().length > 1) {
                    uni.navigateBack({
                        delta: 1
                    });
                } else {
                    redirect({
                        url: '/app/pages/member/index',
                        mode: 'reLaunch'
                    });
                }
            }, 500);
        } else {
            loading.value = false;
        }
    })
}

// getMemoryList 
const memoryList = ref([])
const fetchMemoryList = async () => {
    // 获取选中的分类 的 id 

    const categoryId = formData.goods_category[1]

    const res = await getMemoryList({ id: categoryId }) as ApiResponse<any>;
    if (res?.data) {
        memoryList.value = res.data;
    }
}

// selectMemory 
const selectMemory = (item: any) => {
    // 更新内存ID
    formData.memory_id = item.spec_id.toString();
    // 仅在goods_name包含特定字符时进行替换
    formData.memory_name = item.spec_name
}

onMounted(() => {
    loadCategoryTree();
    loadBrandList();
    if (getToken()) checkIsVerifier();
})

onShow(() => {
    if (getToken()) checkIsVerifier();
})

// full_goods_name 
const fullGoodsName = computed(() => {
    // 如果没有 memory_name, 判断是否 有 memory_id 
    // 如果有 memory_id , 从 memory_list 中找到对应的 memory_name
    if (!formData.memory_name && formData.memory_id) {
        const memory = memoryList.value.find((item: any) => item.spec_id.toString() === formData.memory_id)

        if (memory) {
            formData.memory_name = memory.spec_name
        }

    }

    return formData.full_goods_name || formData.goods_name + ' ' + formData.memory_name
})

// 导航栏相关
const navbarInnerStyle = ref({})
const navbarInnerArrowStyle = ref({})
const detailHeadBgChange = ref(false)

// 监听页面滚动
onPageScroll(({ scrollTop }) => {
    detailHeadBgChange.value = scrollTop > 50
})

onMounted(() => {
    // 获取系统信息
    const systemInfo = uni.getSystemInfoSync()

    // #ifdef MP-WEIXIN
    // 获取胶囊按钮位置信息
    const menuButton = uni.getMenuButtonBoundingClientRect()

    // 计算导航栏内部样式
    navbarInnerStyle.value = {
        height: menuButton.height + 'px',
        'margin-top': menuButton.top - systemInfo.statusBarHeight + 'px',
        'padding-top': systemInfo.statusBarHeight + 'px'
    }

    // 计算返回箭头样式
    navbarInnerArrowStyle.value = {
        color: '#303133',
        'line-height': menuButton.height + 'px',
        'padding-left': '26rpx'
    }
    // #endif

    // #ifndef MP-WEIXIN
    navbarInnerStyle.value = {
        height: '44px',
        'padding-top': systemInfo.statusBarHeight + 'px'
    }
    navbarInnerArrowStyle.value = {
        color: '#303133',
        'line-height': '44px',
        'padding-left': '26rpx'
    }
    // #endif
})

// 价格区域 Tab 切换
const activeTab = ref('price')
</script>


<style lang="scss" scoped>
.add-goods-container {
    min-height: 100vh;
    background-color: #f8f8f8;
    position: relative;
    box-sizing: border-box;


    .detail-head {
        transition: background-color 0.3s;
        backdrop-filter: blur(10px);



        .title {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-size: 32rpx;
            font-weight: 500;
            color: #303133;
        }

        .publish-btn {
            position: absolute;
            // #ifdef MP-WEIXIN
            right: calc(88px + 26rpx); // 胶囊按钮宽度 + 间距
            // #endif
            // #ifndef MP-WEIXIN
            right: 26rpx;
            // #endif
            background: linear-gradient(to right, #19be6b, #2ecc71);
            padding: 12rpx 32rpx;
            border-radius: 32rpx;

            text {
                color: #fff;
                font-size: 28rpx;
                font-weight: 500;
            }

            &:active {
                opacity: 0.9;
            }
        }
    }

    .content-scroll {
        // #ifdef MP-WEIXIN
        padding-top: 120rpx;
        // #endif
        // #ifndef MP-WEIXIN
        padding-top: 88rpx;
        // #endif

        padding: 20rpx;
        padding-top: 50px;
        padding-bottom: 500rpx;
        box-sizing: border-box;
    }

    .form-item {
        background-color: #fff;
        margin-bottom: 20rpx;
        border-radius: 16rpx;
        padding: 30rpx;
        box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
    }

    .upload-section {
        // #ifdef MP-WEIXIN
        margin-top: 120rpx;
        // #endif
    }

    .category-section {
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24rpx;
            padding-bottom: 16rpx;
            border-bottom: 1px solid #f5f5f5;

            .title {
                font-size: 28rpx;
                color: #333;
                font-weight: 500;
            }

            .path {
                font-size: 24rpx;
                color: #19be6b;
            }
        }



        .section-content {
            margin-bottom: 24rpx;

            &:last-child {
                margin-bottom: 0;
            }

            .section-label {
                display: flex;
                align-items: center;
                margin-bottom: 12rpx;

                .label-text {
                    font-size: 26rpx;
                    color: #666;
                    min-width: 120rpx;
                }

                .label-colon {
                    color: #666;
                    margin: 0 8rpx;
                }
            }
        }

        .scroll-tags {
            width: 100%;
            white-space: nowrap;

            &::-webkit-scrollbar {
                display: none;
            }

            .tag-list {
                display: inline-flex;
                padding: 4rpx 0;

                .tag {
                    display: inline-flex;
                    align-items: center;
                    padding: 12rpx 24rpx;
                    margin-right: 16rpx;
                    background: #f5f5f5;
                    border-radius: 28rpx;
                    font-size: 24rpx;
                    color: #666;
                    transition: all 0.2s;

                    &.active {
                        background: rgba(25, 190, 107, 0.1);
                        color: #19be6b;
                    }

                    &:active {
                        opacity: 0.8;
                    }

                    &:last-child {
                        margin-right: 0;
                    }
                }
            }
        }
    }

    .price-section {
        position: fixed;
        right: 2px;
        left: 2px;
        bottom: 10rpx;

        .price-tabs {
            display: flex;
            margin-bottom: 24rpx;
            border-radius: 12rpx;
            background: #f5f5f5;
            padding: 4rpx;

            .tab {
                flex: 1;
                text-align: center;
                padding: 16rpx 0;
                font-size: 26rpx;
                color: #666;
                border-radius: 8rpx;
                transition: all 0.3s;

                &.active {
                    background: #fff;
                    color: #19be6b;
                    box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05);
                }
            }
        }

        .tab-content {

            .price-row,
            .status-row {
                display: flex;
                align-items: center;
                margin-bottom: 20rpx;

                &:last-child {
                    margin-bottom: 0;
                }

                .label {
                    width: 140rpx;
                    font-size: 26rpx;
                    color: #333;
                }

                .price-input-wrap {
                    flex: 1;
                    display: flex;
                    align-items: center;
                    background: #f8f8f8;
                    border-radius: 12rpx;
                    padding: 16rpx 24rpx;

                    .currency {
                        font-size: 26rpx;
                        color: #333;
                        margin-right: 8rpx;
                    }

                    .price-input {
                        flex: 1;
                        font-size: 28rpx;
                        background: transparent;
                    }
                }
            }
        }

        .status-content {
            .status-row {
                justify-content: space-between;
                padding: 16rpx 0;
            }
        }
    }
}
</style>