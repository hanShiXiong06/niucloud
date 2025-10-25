<template>
    <view :style="warpCss">
        <view :style="maskLayer"></view>
        <view class="diy-recycle-category relative">
            <!-- 数据来源判断 -->
            <template v-if="diyComponent.dataSource === 'manual'">
                <!-- 手动配置数据渲染 -->
                <template v-if="diyComponent.displayMode === 'singleSlide'">
                    <scroll-view scroll-x class="whitespace-nowrap py-2">
                    <view class="recycle-category-item inline-flex flex-col items-center box-border py-2"
                              v-for="(item, index) in diyComponent.list" :key="item.id"
                              :style="{ width: 100 / diyComponent.rowCount + '%' }"
                              @click="handleManualItemClick(item.link)">
                            <view class="category-img relative flex items-center justify-center"
                                  :style="{ width: diyComponent.imageSize * 2 + 'rpx', height: diyComponent.imageSize * 2 + 'rpx' }">
                                <image v-if="item.image" :src="img(item.image)" mode="aspectFill" 
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                                <image v-else :src="img('static/resource/images/diy/figure.png')" mode="aspectFill"
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                            </view>
                            <text class="category-text w-full text-center truncate pt-[16rpx]"
                                  :style="{ fontSize: diyComponent.font.size * 2 + 'rpx', fontWeight: diyComponent.font.weight, color: diyComponent.font.color }">
                                  {{ item.title }}
                            </text>
                        </view>
                    </scroll-view>
                </template>
                <template v-else-if="diyComponent.displayMode === 'multiFixed'">
                    <view class="recycle-category-list-fixed flex flex-wrap py-2">
                        <view class="recycle-category-item flex flex-col items-center box-border py-2"
                              v-for="(item, index) in diyComponent.list" :key="item.id"
                              :style="{ width: 100 / diyComponent.rowCount + '%' }"
                              @click="handleManualItemClick(item.link)">
                            <view class="category-img relative flex items-center justify-center"
                                  :style="{ width: diyComponent.imageSize * 2 + 'rpx', height: diyComponent.imageSize * 2 + 'rpx' }">
                                <image v-if="item.image" :src="img(item.image)" mode="aspectFill" 
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                                <image v-else :src="img('static/resource/images/diy/figure.png')" mode="aspectFill"
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                            </view>
                            <text class="category-text w-full text-center truncate pt-[16rpx]"
                                  :style="{ fontSize: diyComponent.font.size * 2 + 'rpx', fontWeight: diyComponent.font.weight, color: diyComponent.font.color }">
                                  {{ item.title }}
                            </text>
                        </view>
                    </view>
                </template>
            </template>
            <template v-else-if="diyComponent.dataSource === 'api'">
                <!-- API数据渲染 (热门推荐) -->
                <template v-if="diyComponent.displayMode === 'singleSlide'">
                    <scroll-view scroll-x class="whitespace-nowrap py-2">
                        <view class="recycle-category-item inline-flex flex-col items-center box-border py-2"
                              v-for="(item, index) in categoryListFromApi" :key="item.category_id"
                              :style="{ width: 100 / diyComponent.rowCount + '%' }"
                              @click="handleApiItemClick(item)">
                            <view class="category-img relative flex items-center justify-center"
                                  :style="{ width: diyComponent.imageSize * 2 + 'rpx', height: diyComponent.imageSize * 2 + 'rpx' }">
                                <!-- API数据优先展示 item.image (图标) -->
                                <image v-if="item.image" :src="img(item.image)" mode="aspectFill" 
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                                <!-- 如果没有 item.image，可以考虑 item.images 作为备用，或显示占位图 -->
                                <image v-else-if="item.images" :src="img(item.images)" mode="aspectFill"
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />       
                                <image v-else :src="img('static/resource/images/diy/figure.png')" mode="aspectFill"
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                            </view>
                            <text class="category-text w-full text-center truncate pt-[16rpx]"
                                  :style="{ fontSize: diyComponent.font.size * 2 + 'rpx', fontWeight: diyComponent.font.weight, color: diyComponent.font.color }">
                                  {{ item.category_name }}
                            </text>
                    </view>
                </scroll-view>
                </template>
                <template v-else-if="diyComponent.displayMode === 'multiFixed'">
                    <view class="recycle-category-list-fixed flex flex-wrap py-2">
                        <view class="recycle-category-item flex flex-col items-center box-border py-2"
                              v-for="(item, index) in categoryListFromApi" :key="item.category_id"
                              :style="{ width: 100 / diyComponent.rowCount + '%' }"
                              @click="handleApiItemClick(item)">
                            <view class="category-img relative flex items-center justify-center"
                                  :style="{ width: diyComponent.imageSize * 2 + 'rpx', height: diyComponent.imageSize * 2 + 'rpx' }">
                                <image v-if="item.image" :src="img(item.image)" mode="aspectFill" 
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                                <image v-else-if="item.images" :src="img(item.images)" mode="aspectFill"
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />    
                                <image v-else :src="img('static/resource/images/diy/figure.png')" mode="aspectFill"
                                       :style="{ maxWidth: diyComponent.imageSize * 2 + 'rpx', maxHeight: diyComponent.imageSize * 2 + 'rpx', borderRadius: diyComponent.aroundRadius * 2 + 'rpx' }" />
                            </view>
                            <text class="category-text w-full text-center truncate pt-[16rpx]"
                                  :style="{ fontSize: diyComponent.font.size * 2 + 'rpx', fontWeight: diyComponent.font.weight, color: diyComponent.font.color }">
                                  {{ item.category_name }}
                            </text>
                        </view>
            </view>
                </template>
            </template>
            <template v-else-if="diyComponent.dataSource === 'tree'">
                <!-- 树形分类数据渲染 -->
                                <view class="tree-category-container py-2">
                    <view v-for="(firstLevel, index) in filteredTreeData" :key="firstLevel.category_id" 
                          class="category-group" 
                          :style="{ marginBottom: getTreeStyle.groupMargin + 'rpx' }">
                        <!-- 一级分类 -->
                        <view class="first-level-category flex flex-col items-center py-2 mb-2" 
                              @click="handleTreeItemClick(firstLevel)"
                              :style="{ 
                                borderBottom: getTreeStyle.showDivider ? '2rpx solid ' + getTreeStyle.dividerColor : 'none',
                                paddingBottom: getTreeStyle.showDivider ? '32rpx' : '16rpx',
                                marginBottom: '32rpx'
                              }">
                            <!-- 一级分类图标 -->
                            <view v-if="getTreeStyle.showFirstLevelIcon" 
                                  class="category-img relative flex items-center justify-center"
                                  :style="{ 
                                    width: getTreeStyle.firstLevelImageSize * 2 + 'rpx', 
                                    height: getTreeStyle.firstLevelImageSize * 2 + 'rpx' 
                                  }">
                                <image v-if="firstLevel.image" :src="img(firstLevel.image)" mode="aspectFill" 
                                       :style="{ 
                                         maxWidth: getTreeStyle.firstLevelImageSize * 2 + 'rpx', 
                                         maxHeight: getTreeStyle.firstLevelImageSize * 2 + 'rpx', 
                                         borderRadius: diyComponent.aroundRadius * 2 + 'rpx' 
                                       }" />
                                <image v-else-if="firstLevel.images" :src="img(firstLevel.images)" mode="aspectFill"
                                       :style="{ 
                                         maxWidth: getTreeStyle.firstLevelImageSize * 2 + 'rpx', 
                                         maxHeight: getTreeStyle.firstLevelImageSize * 2 + 'rpx', 
                                         borderRadius: diyComponent.aroundRadius * 2 + 'rpx' 
                                       }" />       
                                <image v-else :src="img('static/resource/images/diy/figure.png')" mode="aspectFill"
                                       :style="{ 
                                         maxWidth: getTreeStyle.firstLevelImageSize * 2 + 'rpx', 
                                         maxHeight: getTreeStyle.firstLevelImageSize * 2 + 'rpx', 
                                         borderRadius: diyComponent.aroundRadius * 2 + 'rpx' 
                                       }" />
                            </view>
                            
                            <!-- 一级分类文字 -->
                            <text class="category-text text-center truncate" 
                                  :style="{ 
                                    fontSize: getTreeStyle.firstLevelFontSize * 2 + 'rpx', 
                                    fontWeight: getTreeStyle.firstLevelFontWeight, 
                                    color: getTreeStyle.firstLevelFontColor,
                                    paddingTop: getTreeStyle.showFirstLevelIcon ? '16rpx' : '0'
                                  }">
                                  {{ firstLevel.category_name }}
                            </text>
                        </view>
                        
                        <!-- 二级分类横向排列 -->
                        <view class="second-level-categories" 
                              :style="{ 
                                paddingLeft: getTreeStyle.secondLevelPadding + 'rpx',
                                paddingRight: getTreeStyle.secondLevelPadding + 'rpx' 
                              }">
                            <template v-if="diyComponent.displayMode === 'singleSlide'">
                                <scroll-view scroll-x class="whitespace-nowrap">
                                    <view class="inline-flex">
                                        <view class="recycle-category-item inline-flex flex-col items-center box-border py-2 mx-1"
                                              v-for="(secondLevel, idx) in firstLevel.visibleChildren" :key="secondLevel.category_id"
                                              :style="{ width: (100 / diyComponent.rowCount - 2) + '%', minWidth: '120rpx' }"
                                              @click="handleTreeItemClick(secondLevel)">
                                            <view class="category-img relative flex items-center justify-center"
                                                  :style="{ 
                                                    width: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                    height: getTreeStyle.secondLevelImageSize * 2 + 'rpx' 
                                                  }">
                                                <image v-if="secondLevel.image" :src="img(secondLevel.image)" mode="aspectFill" 
                                                       :style="{ 
                                                         maxWidth: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                         maxHeight: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                         borderRadius: diyComponent.aroundRadius * 1.5 + 'rpx' 
                                                       }" />
                                                <image v-else-if="secondLevel.images" :src="img(secondLevel.images)" mode="aspectFill"
                                                       :style="{ 
                                                         maxWidth: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                         maxHeight: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                         borderRadius: diyComponent.aroundRadius * 1.5 + 'rpx' 
                                                       }" />       
                                                <image v-else :src="img('static/resource/images/diy/figure.png')" mode="aspectFill"
                                                       :style="{ 
                                                         maxWidth: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                         maxHeight: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                         borderRadius: diyComponent.aroundRadius * 1.5 + 'rpx' 
                                                       }" />
                                            </view>
                                            <text class="category-text w-full text-center truncate pt-[12rpx]"
                                                  :style="{ 
                                                    fontSize: getTreeStyle.secondLevelFontSize * 2 + 'rpx', 
                                                    fontWeight: diyComponent.font.weight, 
                                                    color: getTreeStyle.secondLevelFontColor,
                                                    opacity: getTreeStyle.secondLevelOpacity
                                                  }">
                                                  {{ secondLevel.category_name }}
                                            </text>
                                        </view>
                                    </view>
                                </scroll-view>
                            </template>
                            <template v-else-if="diyComponent.displayMode === 'multiFixed'">
                                <view class="flex flex-wrap">
                                    <view class="recycle-category-item flex flex-col items-center box-border py-2"
                                          v-for="(secondLevel, idx) in firstLevel.visibleChildren" :key="secondLevel.category_id"
                                          :style="{ width: 100 / diyComponent.rowCount + '%' }"
                                          @click="handleTreeItemClick(secondLevel)">
                                        <view class="category-img relative flex items-center justify-center"
                                              :style="{ 
                                                width: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                height: getTreeStyle.secondLevelImageSize * 2 + 'rpx' 
                                              }">
                                            <image v-if="secondLevel.image" :src="img(secondLevel.image)" mode="aspectFill" 
                                                   :style="{ 
                                                     maxWidth: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                     maxHeight: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                     borderRadius: diyComponent.aroundRadius * 1.5 + 'rpx' 
                                                   }" />
                                            <image v-else-if="secondLevel.images" :src="img(secondLevel.images)" mode="aspectFill"
                                                   :style="{ 
                                                     maxWidth: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                     maxHeight: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                     borderRadius: diyComponent.aroundRadius * 1.5 + 'rpx' 
                                                   }" />    
                                            <image v-else :src="img('static/resource/images/diy/figure.png')" mode="aspectFill"
                                                   :style="{ 
                                                     maxWidth: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                     maxHeight: getTreeStyle.secondLevelImageSize * 2 + 'rpx', 
                                                     borderRadius: diyComponent.aroundRadius * 1.5 + 'rpx' 
                                                   }" />
                                        </view>
                                        <text class="category-text w-full text-center truncate pt-[12rpx]"
                                              :style="{ 
                                                fontSize: getTreeStyle.secondLevelFontSize * 2 + 'rpx', 
                                                fontWeight: diyComponent.font.weight, 
                                                color: getTreeStyle.secondLevelFontColor,
                                                opacity: getTreeStyle.secondLevelOpacity
                                              }">
                                              {{ secondLevel.category_name }}
                                        </text>
                                    </view>
                                </view>
                            </template>
                        </view>
                    </view>
                </view>
            </template>
        </view>
    </view>
</template>

<script lang="ts" setup>
import { ref, computed, watch, onMounted } from 'vue';
import { img } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import { getHot, getCategoryTree } from '@/addon/recycle/api/recycle'; // 添加树形分类API
import { useRouter } from 'vue-router'; // 用于API数据跳转

// API数据结构 (参考原文件，确保字段正确)
interface RecycleCategoryFromApi {
    category_id: number;
    category_name: string;
    image?: string; // 图标
    images?: string; // 预览图 (备用)
    // ... 其他API可能返回的字段
}

// 树形分类数据结构
interface TreeCategoryItem {
    category_id: number;
    category_name: string;
    image?: string;
    images?: string;
    child_list?: TreeCategoryItem[];
    level: number;
    pid: number;
    category_full_name: string;
    is_show: number;
    sort: number;
    site_id: number;
    need_vip: number;
    [key: string]: any;
}

interface ApiResponse {
    code: number;
    message: string;
    data: {
        data: RecycleCategoryFromApi[];
        [key: string]: any;
    };
}

// 树形API响应结构
interface TreeApiResponse {
    code: number;
    message: string;
    data: TreeCategoryItem[];
}

const props = defineProps({
    component: {
        type: Object as () => any, 
        default: () => ({})
    },
    index: {
        type: Number,
        default: 0
    }
});

const diyStore = useDiyStore();
const router = useRouter();

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        // 在装修模式下，确保默认值存在，特别是新的 dataSource
        const comp = diyStore.value[props.index];
        if (!comp.dataSource) comp.dataSource = 'manual'; // 如果后台未设置，默认手动
        if (!comp.list) comp.list = [];
        if (!comp.font) comp.font = { size: 14, weight: 400, color: '#333333' };
        // ... 其他确保默认值的逻辑
        return comp;
    } else {
        const comp = { ...props.component }; // 拷贝一份，避免直接修改 props
        if (!comp.dataSource) comp.dataSource = 'manual';
        if (!comp.list) comp.list = [];
        if (!comp.font) comp.font = { size: 14, weight: 400, color: '#333333' };
        return comp;
    }
});

// API 数据列表
const categoryListFromApi = ref<RecycleCategoryFromApi[]>([]);

// 树形分类数据
const treeData = ref<TreeCategoryItem[]>([]);

// 过滤后的树形数据（用于分组显示）
const filteredTreeData = computed(() => {
    const filtered: (TreeCategoryItem & { visibleChildren: TreeCategoryItem[] })[] = [];
    const maxCount = diyComponent.value.treeMaxCount || 20; // 获取配置的最大数量
    
    // 只处理有二级分类的一级分类
    treeData.value.forEach((firstLevel: TreeCategoryItem) => {
        if (filtered.length >= maxCount) return; // 数量限制
        
        // 只有当一级分类有子分类时才处理，并且过滤显示状态
        const visibleChildList = firstLevel.child_list?.filter((child: TreeCategoryItem) => child.is_show === 1) || [];
        
        if (visibleChildList.length > 0) {
            // 添加一级分类和其可见的子分类
            filtered.push({
                ...firstLevel,
                visibleChildren: visibleChildList
            });
        }
    });
    
    return filtered;
});

// 获取API分类数据
const fetchCategoryListFromApi = async () => {
    if (diyComponent.value.dataSource === 'api') {
        try {
            const res = await getHot() as ApiResponse; // 修改调用以匹配实际API
            if (res.code === 1 && res.data && Array.isArray(res.data.data)) {
                // 应用数量限制
                const limit = diyComponent.value.apiItemLimit || 15;
                categoryListFromApi.value = res.data.data.slice(0, limit);
            } else {
                categoryListFromApi.value = [];
            }
        } catch (error) {
            console.error("获取API回收分类失败:", error);
            categoryListFromApi.value = [];
        }
    }
};

// 获取树形分类数据
const fetchTreeCategoryData = async () => {
    if (diyComponent.value.dataSource === 'tree') {
        try {
            const res = await getCategoryTree() as TreeApiResponse;
            if (res.code === 1 && Array.isArray(res.data)) {
                // 过滤只显示 is_show = 1 的分类
                treeData.value = res.data.filter((item: TreeCategoryItem) => item.is_show === 1);
            } else {
                treeData.value = [];
            }
        } catch (error) {
            console.error("获取树形分类数据失败:", error);
            treeData.value = [];
        }
    }
};

onMounted(() => {
    fetchCategoryListFromApi();
    fetchTreeCategoryData();
});

// 监听数据源或相关配置变化，重新获取API数据
watch(() => diyComponent.value.dataSource, (newSource) => {
    if (newSource === 'api') {
        fetchCategoryListFromApi();
    } else if (newSource === 'tree') {
        fetchTreeCategoryData();
    }
});

// 监听API配置变化
watch(() => diyComponent.value.apiItemLimit, () => {
    if (diyComponent.value.dataSource === 'api') {
        fetchCategoryListFromApi();
    }
});

// 监听树形配置变化 - 这些改变会通过 computed 自动重新计算 flattenedTreeData
watch(() => diyComponent.value.treeMaxCount, () => {
    // flattenedTreeData 是 computed，会自动重新计算
});

// --- 样式相关的 computed properties --- (基本不变，从 diyComponent.value 获取)
const imageSize = computed(() => diyComponent.value.imageSize || 35);
const aroundRadius = computed(() => diyComponent.value.aroundRadius || 5);
const fontSize = computed(() => (diyComponent.value.font && diyComponent.value.font.size) || 14);
const fontWeight = computed(() => (diyComponent.value.font && diyComponent.value.font.weight) || 400);
const fontColor = computed(() => (diyComponent.value.font && diyComponent.value.font.color) || '#333333');

// 树形分类样式配置
const getTreeStyle = computed(() => {
    const defaultStyle = {
        // 一级分类样式
        showFirstLevelIcon: true,
        firstLevelImageSize: 50,
        firstLevelFontSize: 18,
        firstLevelFontColor: '#333333',
        firstLevelFontWeight: 500,
        
        // 二级分类样式
        secondLevelImageSize: 36,
        secondLevelFontSize: 14,
        secondLevelFontColor: '#666666',
        secondLevelOpacity: 0.85,
        
        // 布局设置
        groupMargin: 20,
        secondLevelPadding: 20,
        showDivider: true,
        dividerColor: '#f0f0f0'
    };
    
    return { ...defaultStyle, ...(diyComponent.value.treeStyle || {}) };
});

// --- 点击处理函数 ---
// 处理手动配置项的点击
const handleManualItemClick = (link: any) => {
    if (diyStore.mode === 'decorate') return;
    diyStore.toRedirect(link);
};

// 处理API数据项的点击
const handleApiItemClick = (item: RecycleCategoryFromApi) => {
    if (diyStore.mode === 'decorate') return;

    if (item.images && item.images.length > 0) {
        const imageUrl = img(item.images);
        uni.previewImage({
            urls: [imageUrl],
            current: imageUrl,
            success: () => {
                // console.log('预览图片成功');
            },
            fail: (err) => {
                console.error("预览图片失败:", err);
                // 可选：如果预览失败，可以toast提示用户
                // uni.showToast({ title: '图片无法预览', icon: 'none' });
            }
        });
    } else {
        // 如果没有 images 字段，可以给用户一个提示，或者什么都不做
        // uni.showToast({ title: '没有可预览的图片', icon: 'none' });
        console.log('API item does not have images for preview:', item);
        // 如果希望在没有预览图时 fallback 到之前的跳转逻辑 (比如跳转到详情页)，可以取消下面行的注释:
        // router.push({ path: '/addon/recycle/pages/category/detail', query: { category_id: item.category_id } });
    }
};

// 处理树形分类项的点击
const handleTreeItemClick = (item: TreeCategoryItem) => {
    if (diyStore.mode === 'decorate') return;
    
    // 树形分类的点击逻辑：预览图片
    console.log('item', item);
    if (item.images && item.images.length > 0) {
        const imageUrl = img(item.images);
        uni.previewImage({
            urls: [imageUrl],
            current: imageUrl,
            success: () => {
                // console.log('预览图片成功');
            },
            fail: (err) => {
                console.error("预览图片失败:", err);
                // 可选：如果预览失败，可以toast提示用户
                // uni.showToast({ title: '图片无法预览', icon: 'none' });
            }
        });
    } else if (item.image && item.image.length > 0) {
        // 如果没有 images，尝试使用 image 字段
        const imageUrl = img(item.image);
        uni.previewImage({
            urls: [imageUrl],
            current: imageUrl,
            success: () => {
                console.log('预览图片成功');
            },
            fail: (err) => {
                console.error("预览图片失败:", err);
            }
        });
    } else {
        // 如果没有可预览的图片，给用户提示
        uni.showToast({ 
            title: '暂无图片可预览', 
            icon: 'none',
            duration: 2000
        });
        console.log('Tree item does not have images for preview:', item);
    }
};

// --- 组件整体样式 --- (基本不变，从 diyComponent.value 获取)
const warpCss = computed(() => {
    let style = '';
    style += 'position:relative;';
    const currentComponent = diyComponent.value;
    if (currentComponent.componentStartBgColor) {
        if (currentComponent.componentStartBgColor && currentComponent.componentEndBgColor) {
            style += `background:linear-gradient(${currentComponent.componentGradientAngle || 'to bottom'},${currentComponent.componentStartBgColor},${currentComponent.componentEndBgColor});`;
        } else {
            style += 'background-color:' + currentComponent.componentStartBgColor + ';';
        }
    } 
    if (currentComponent.componentBgUrl) {
        style += `background-image:url('${img(currentComponent.componentBgUrl)}');`;
        style += 'background-size: cover;background-repeat: no-repeat;';
    }
    if (currentComponent.topRounded) {
        style += 'border-top-left-radius:' + currentComponent.topRounded * 2 + 'rpx;';
        style += 'border-top-right-radius:' + currentComponent.topRounded * 2 + 'rpx;';
    } 
    if (currentComponent.bottomRounded) {
        style += 'border-bottom-left-radius:' + currentComponent.bottomRounded * 2 + 'rpx;';
        style += 'border-bottom-right-radius:' + currentComponent.bottomRounded * 2 + 'rpx;';
    }
    if (currentComponent.margin) {
        const margin = currentComponent.margin;
        style += `margin: ${margin.top * 2}rpx ${margin.both * 2}rpx ${margin.bottom * 2}rpx ${margin.both * 2}rpx;`;
    }
    return style;
});

const maskLayer = computed(() => {
    let style = '';
    const currentComponent = diyComponent.value;
    if (currentComponent.componentBgUrl) {
        style += 'position:absolute;top:0;left:0;width:100%;height:100%;';
        style += `background: rgba(0,0,0,${currentComponent.componentBgAlpha !== undefined ? currentComponent.componentBgAlpha / 10 : 0});`;
        if (currentComponent.topRounded) {
            style += 'border-top-left-radius:' + currentComponent.topRounded * 2 + 'rpx;';
            style += 'border-top-right-radius:' + currentComponent.topRounded * 2 + 'rpx;';
        }
        if (currentComponent.bottomRounded) {
            style += 'border-bottom-left-radius:' + currentComponent.bottomRounded * 2 + 'rpx;';
            style += 'border-bottom-right-radius:' + currentComponent.bottomRounded * 2 + 'rpx;';
        }
    }
    return style;
});

</script>

<style lang="scss" scoped>
.diy-recycle-category {
    .recycle-category-item {
        box-sizing: border-box;
        .category-img {
            overflow: hidden;
            image {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
        }
    }
    
    .tree-category-container {
        .category-group {
            .second-level-categories {
                .recycle-category-item {
                    transition: all 0.2s ease;
                    
                    &:active {
                        transform: scale(0.95);
                        opacity: 0.7;
                    }
                }
            }
        }
    }
}
</style>