<template>
    <div>
        <div class="table w-[100%] mt-[15px]" v-loading="categoryTable.loading">
            <div class="table-head flex items-center bg-[#f5f7f9] py-[10px] text-[14px]" :style="{ paddingRight: scrollBarWidth + 'px' }">
                <div class="w-[6%]"></div>
                <div class="w-[10%]"></div>
                <div class="w-[50%]">分类名称</div>
                <div class="w-[34%] h-[30px] leading-[30px]">分类图片</div>
            </div>
            <div class="table-body max-h-[500px] overflow-y-auto" ref="tableBodyRef">
                <!-- 遍历一级分类 -->
                <div v-for="(row, rowIndex) in categoryTable.data" :key="rowIndex" class="flex flex-col">
                    <div class="flex items-center border-solid border-[#e5e7eb] py-[10px] border-b-[1px]">
                        <!-- 图标：展开/收起子级 -->
                        <div v-if="row.child_list && row.child_list.length" class="w-[6%] cursor-pointer text-center !text-[10px]" @click="secondLevelArrowChange(row)" :class="{ 'iconfont iconxiangyoujiantou': row.child_list.length, 'arrow-show': row.isShow }"></div>
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
                                <el-image v-if="row.img" class="w-[30px] h-[30px]" :src="img(row.img)" fit="contain">
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
                    <div v-show="row.child_list && row.isShow">
                        <div v-for="(item, index) in row.child_list" :key="index" class="flex items-center py-[10px] border-solid border-b-[1px]" :class="{ 'hidden': !row.isShow, 'border-[#e5e7eb]': index == (row.child_list.length - 1) }">
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
                                    <el-image v-if="row.img" class="w-[30px] h-[30px]" :src="img(row.img)" fit="contain">
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
                <div v-if="!categoryTable.data.length && !categoryTable.loading" class="h-[60px] flex items-center justify-center border-solid border-[#e5e7eb] py-[12px] border-b-[1px]">暂无数据</div>
            </div>
        </div>
        <!-- 移除组件内的确认/取消按钮，保留外层弹窗按钮 -->
    </div>
</template>

<script lang="ts" setup>
import { img } from '@/utils/common'
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import { getCategoryTree } from '@/addon/home_service/api/goods'
import { cloneDeep } from 'lodash-es'

const prop = defineProps({
    categoryId: {
        type: Number || String,
        default: ''
    }
})
const categoryId: any = computed(() => prop.categoryId)

// 已选商品分类
let currCategoryData: any = null

const categoryTable = reactive({
    loading: true,
    data: [] as any[]
})

const scrollBarWidth = ref(0)
const tableBodyRef = ref<any>(null)

onMounted(() => {
    window.addEventListener('resize', getScrollBarWidth)
})

const getScrollBarWidth = () => {
    nextTick(() => {
        if (tableBodyRef.value) {
            scrollBarWidth.value = tableBodyRef.value.offsetWidth - tableBodyRef.value.clientWidth
        }
    })
}

// 方法：切换子级展开/收起
const secondLevelArrowChange = (row:any) => {
    row.isShow = !row.isShow
    nextTick(() => getScrollBarWidth())
}

const loadCategoryList = () => {
    categoryTable.loading = true

    getCategoryTree().then(res => {
        categoryTable.loading = false
        const srcList = res.data || []
        // 映射为与 shop 组件一致的字段
        const mapped = srcList.map((item:any) => ({
            ...item,
            img: item.image,
            child_list: item.children || []
        }))
        // 初始化字段
        mapped.forEach((item:any) => {
            item.isShow = false
            item.isSecondLevelIndeterminate = false
            item.secondLevelCheckAll = false
            if (item.child_list && item.child_list.length) {
                item.child_list.forEach((child:any) => {
                    child.threeLevelCheckAll = false
                })
            }
        })
        categoryTable.data = mapped

        // 回显选中
        if (categoryId.value) {
            let obj:any = {}
            categoryTable.data.forEach((row:any) => {
                if (row.category_id === categoryId.value) {
                    row.secondLevelCheckAll = true
                    row.isShow = true
                    obj = cloneDeep(row)
                }
                if (row.child_list) {
                    row.child_list.forEach((child:any) => {
                        if (child.category_id === categoryId.value) {
                            child.threeLevelCheckAll = true
                            row.isShow = true
                            obj = cloneDeep(child)
                        }
                    })
                }
            })
            currCategoryData = cloneDeep(obj)
        }
    }).catch(() => {
        categoryTable.loading = false
    })
}

const clearAllSelections = () => {
    categoryTable.data.forEach((row:any) => {
        row.secondLevelCheckAll = false
        if (row.child_list) {
            row.child_list.forEach((child:any) => {
                child.threeLevelCheckAll = false
            })
        }
    })
}

// 处理复选框变化（单选）
const handleCheckboxChange = (checked:any, target:any, parentRow?:any) => {
    clearAllSelections()
    if (checked) {
        if (parentRow) {
            target.threeLevelCheckAll = checked
            currCategoryData = target
            parentRow.isShow = true
        } else {
            target.secondLevelCheckAll = checked
            currCategoryData = target
            target.isShow = true
        }
    } else {
        currCategoryData = null
    }
}

loadCategoryList()

// 查找选中分类的父链（支持多级）
const findCategoryPathById = (id:any) => {
    const path:number[] = []
    const dfs = (nodes:any[], trail:number[]):boolean => {
        for (const n of nodes) {
            const nextTrail = [...trail, n.category_id]
            if (n.category_id === id) {
                path.splice(0, path.length, ...nextTrail)
                return true
            }
            const children = n.child_list || n.children || []
            if (children.length && dfs(children, nextTrail)) return true
        }
        return false
    }
    dfs(categoryTable.data, [])
    return path
}

const getData = () => {
    if (!currCategoryData) {
        ElMessage({ type: 'warning', message: '请选择分类' })
        return
    }
    const selectedId = currCategoryData.category_id
    const pathIds = findCategoryPathById(selectedId)
    // 组装更易匹配的参数（兼容多级）：goods_category + pid/ppid/pppid + category_path
    const qs:string[] = []
    qs.push(`goods_category=${selectedId}`)
    // 倒数第二是直接父级
    if (pathIds.length >= 2) qs.push(`pid=${pathIds[pathIds.length - 2]}`)
    if (pathIds.length >= 3) qs.push(`ppid=${pathIds[pathIds.length - 3]}`)
    if (pathIds.length >= 4) qs.push(`pppid=${pathIds[pathIds.length - 4]}`)
    // 完整父链（含自身），逗号分隔
    qs.push(`category_path=${pathIds.join(',')}`)

    return {
        name: 'HOME_SERVICE_GOODS_CATEGORY',
        parent: 'HOME_SERVICE',
        title: currCategoryData.category_name,
        url: `/addon/home_service/user/pages/goods/category?${qs.join('&')}`,
        action: ''
    }
}

defineExpose({ getData })
</script>

<style lang="scss" scoped>
.arrow-show {
  transform: rotate(90deg) !important;
}
</style>