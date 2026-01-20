<template>
    <up-popup :show="show" @close="handleClose" mode="bottom" :round="10">
        <view class="area-select-container">
            <!-- 标题栏 -->
            <view class="header">
                <view class="cancel-btn" @click="handleClose">取消</view>
                <view class="title">选择地区</view>
                <view class="confirm-btn" @click="handleConfirm" :class="{'disabled': !canConfirm}">确定</view>
            </view>
            
            <!-- 选项卡导航 -->
            <view class="tab-nav">
                <view 
                    class="tab-item" 
                    :class="{'active': currSelect === 'province'}"
                    @click="switchTab('province')"
                >
                    {{ selected.province ? selected.province.name : '请选择省份' }}
                </view>
                <view 
                    class="tab-item" 
                    :class="{'active': currSelect === 'city', 'disabled': !selected.province}"
                    @click="selected.province && switchTab('city')"
                    v-if="areaList.city.length > 0 || selected.city"
                >
                    {{ selected.city ? selected.city.name : '请选择城市' }}
                </view>
                <view 
                    class="tab-item" 
                    :class="{'active': currSelect === 'district', 'disabled': !selected.city}"
                    @click="selected.city && switchTab('district')"
                    v-if="areaList.district.length > 0 || selected.district"
                >
                    {{ selected.district ? selected.district.name : '请选择区县' }}
                </view>
            </view>
            
            <!-- 当前选择路径 -->
            <view class="selection-path" v-if="hasSelection">
                <text class="path-text">已选择：</text>
                <text class="path-item">{{ selected.province?.name || '' }}</text>
                <text class="path-separator" v-if="selected.province && selected.city"> > </text>
                <text class="path-item">{{ selected.city?.name || '' }}</text>
                <text class="path-separator" v-if="selected.city && selected.district"> > </text>
                <text class="path-item">{{ selected.district?.name || '' }}</text>
            </view>
            
            <!-- 列表区域 -->
            <scroll-view 
                scroll-y="true" 
                class="area-list" 
                :scroll-top="scrollTop"
                :scroll-with-animation="true"
                :show-scrollbar="false"
            >
                <!-- 省份列表 -->
                <view class="list-container" v-show="currSelect === 'province'">
                    <view class="list-title">请选择省份</view>
                    <view 
                        class="list-item" 
                        v-for="item in areaList.province" 
                        :key="item.id"
                        :class="{'active': selected.province && selected.province.id === item.id}"
                        @click="selectProvince(item)"
                    >
                        <text class="item-name">{{ item.name }}</text>
                        <text class="iconfont icon-check" v-if="selected.province && selected.province.id === item.id"></text>
                    </view>
                </view>
                
                <!-- 城市列表 -->
                <view class="list-container" v-show="currSelect === 'city'">
                    <view class="list-title">请选择城市</view>
                    <view 
                        class="list-item" 
                        v-for="item in areaList.city" 
                        :key="item.id"
                        :class="{'active': selected.city && selected.city.id === item.id}"
                        @click="selectCity(item)"
                    >
                        <text class="item-name">{{ item.name }}</text>
                        <text class="iconfont icon-check" v-if="selected.city && selected.city.id === item.id"></text>
                    </view>
                </view>
                
                <!-- 区县列表 -->
                <view class="list-container" v-show="currSelect === 'district'">
                    <view class="list-title">请选择区县</view>
                    <view 
                        class="list-item" 
                        v-for="item in areaList.district" 
                        :key="item.id"
                        :class="{'active': selected.district && selected.district.id === item.id}"
                        @click="selectDistrict(item)"
                    >
                        <text class="item-name">{{ item.name }}</text>
                        <text class="iconfont icon-check" v-if="selected.district && selected.district.id === item.id"></text>
                    </view>
                </view>
                
                <!-- 加载状态 -->
                <view class="loading-more" v-if="isLoading">
                    <view class="loading-spinner"></view>
                    <text>加载中...</text>
                </view>
                
                <!-- 空状态 -->
                <view class="empty-state" v-if="!isLoading && getCurrentList().length === 0">
                    <text>暂无数据</text>
                </view>
            </scroll-view>
        </view>
    </up-popup>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { getAreaListByPid, getAreaByCode } from '@/app/api/system'

const props = defineProps({
    areaId: {
        type: Number,
        default: 0
    }
})

const emits = defineEmits(['complete', 'cancel'])

// 状态变量
const show = ref(false)
const scrollTop = ref(0)
const currSelect = ref('province')
const isLoading = ref(false)

// 地区数据
const areaList = reactive({
    province: [],
    city: [],
    district: []
})

// 已选择的地区
const selected = reactive({
    province: null,
    city: null,
    district: null
})

// 计算属性：是否有选择
const hasSelection = computed(() => {
    return selected.province !== null
})

// 计算属性：是否可以确认
const canConfirm = computed(() => {
    return selected.province !== null
})

// 获取当前显示的列表
const getCurrentList = () => {
    switch (currSelect.value) {
        case 'province':
            return areaList.province
        case 'city':
            return areaList.city
        case 'district':
            return areaList.district
        default:
            return []
    }
}

// 初始化省份数据
const initProvinceData = async () => {
    if (areaList.province.length > 0) return
    
    isLoading.value = true
    try {
        const { data } = await getAreaListByPid(0)
        areaList.province = data || []
    } catch (err) {
        console.error('获取省份数据失败:', err)
        areaList.province = []
    } finally {
        isLoading.value = false
    }
}

// 切换选项卡
const switchTab = (tab) => {
    if (currSelect.value !== tab) {
        currSelect.value = tab
        resetScroll()
    }
}

// 重置滚动位置
const resetScroll = () => {
    scrollTop.value = scrollTop.value === 0 ? 0.1 : 0
}

// 选择省份
const selectProvince = async (province) => {
    selected.province = province
    selected.city = null
    selected.district = null
    
    // 清空下级数据
    areaList.city = []
    areaList.district = []
    
    // 加载城市数据
    isLoading.value = true
    try {
        const { data } = await getAreaListByPid(province.id)
        areaList.city = data || []
        
        if (areaList.city.length > 0) {
            switchTab('city')
        }
    } catch (err) {
        console.error('获取城市数据失败:', err)
        areaList.city = []
    } finally {
        isLoading.value = false
    }
}

// 选择城市
const selectCity = async (city) => {
    selected.city = city
    selected.district = null
    
    // 清空下级数据
    areaList.district = []
    
    // 加载区县数据
    isLoading.value = true
    try {
        const { data } = await getAreaListByPid(city.id)
        areaList.district = data || []
        
        if (areaList.district.length > 0) {
            switchTab('district')
        }
    } catch (err) {
        console.error('获取区县数据失败:', err)
        areaList.district = []
    } finally {
        isLoading.value = false
    }
}

// 选择区县
const selectDistrict = (district) => {
    selected.district = district
    // 选择区县后自动完成选择并关闭弹窗
    emits('complete', selected)
    show.value = false
}

// 处理确认
const handleConfirm = () => {
    if (!canConfirm.value) return
    
    emits('complete', selected)
    show.value = false
}

// 处理关闭
const handleClose = () => {
    emits('cancel')
    show.value = false
}

// 监听 areaId 变化，初始化已选择的地区
watch(() => props.areaId, async (newVal, oldVal) => {
    if (newVal && newVal !== oldVal) {
        isLoading.value = true
        try {
            const { data } = await getAreaByCode(newVal)
            
            // 重置选择
            selected.province = null
            selected.city = null
            selected.district = null
            
            // 设置已选择的地区
            if (data.province) {
                selected.province = data.province
                
                // 加载城市数据
                if (data.province.id) {
                    const cityRes = await getAreaListByPid(data.province.id)
                    areaList.city = cityRes.data || []
                }
            }
            
            if (data.city) {
                selected.city = data.city
                
                // 加载区县数据
                if (data.city.id) {
                    const districtRes = await getAreaListByPid(data.city.id)
                    areaList.district = districtRes.data || []
                }
            }
            
            if (data.district) {
                selected.district = data.district
            }
            
            // 设置当前选项卡
            if (data.district && areaList.district.length > 0) {
                currSelect.value = 'district'
            } else if (data.city && areaList.city.length > 0) {
                currSelect.value = 'city'
            } else {
                currSelect.value = 'province'
            }
        } catch (err) {
            console.error('获取地区数据失败:', err)
        } finally {
            isLoading.value = false
        }
    }
}, {
    immediate: true
})

// 打开选择器
const open = () => {
    show.value = true
    currSelect.value = 'province'
    resetScroll()
    initProvinceData()
}

// 暴露方法
defineExpose({
    open
})
</script>

<style lang="scss" scoped>
.area-select-container {
    background-color: #fff;
    border-radius: 20rpx 20rpx 0 0;
    overflow: hidden;
    padding-bottom: env(safe-area-inset-bottom);
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 30rpx;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        background-color: #fff;
        
        .title {
            font-size: 32rpx;
            font-weight: 600;
            color: #333;
            flex: 1;
            text-align: center;
        }
        
        .cancel-btn, .confirm-btn {
            padding: 10rpx 20rpx;
            font-size: 28rpx;
            border-radius: 8rpx;
            transition: all 0.2s ease;
        }
        
        .cancel-btn {
            color: #666;
            
            &:active {
                background-color: rgba(0, 0, 0, 0.05);
            }
        }
        
        .confirm-btn {
            color: var(--primary-color);
            font-weight: 500;
            
            &:active {
                background-color: rgba(var(--primary-color-rgb), 0.1);
            }
            
            &.disabled {
                color: #ccc;
            }
        }
    }
    
    .tab-nav {
        display: flex;
        padding: 15rpx 30rpx;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        background-color: #fafafa;
        
        .tab-item {
            position: relative;
            padding: 10rpx 16rpx;
            margin-right: 20rpx;
            font-size: 26rpx;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180rpx;
            border-radius: 8rpx;
            background-color: #fff;
            border: 1px solid #e8e8e8;
            transition: all 0.3s ease;
            
            &.active {
                color: #fff;
                background-color: var(--primary-color);
                border-color: var(--primary-color);
                font-weight: 500;
                box-shadow: 0 2rpx 8rpx rgba(var(--primary-color-rgb), 0.3);
            }
            
            &.disabled {
                color: #ccc;
                background-color: #f5f5f5;
                border-color: #f0f0f0;
            }
        }
    }
    
    .selection-path {
        padding: 20rpx 30rpx;
        font-size: 26rpx;
        color: #666;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        background-color: #f9f9f9;
        
        .path-text {
            color: #999;
            margin-right: 10rpx;
        }
        
        .path-item {
            color: var(--primary-color);
            font-weight: 500;
            max-width: 150rpx;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            background-color: rgba(var(--primary-color-rgb), 0.1);
            padding: 4rpx 8rpx;
            border-radius: 6rpx;
        }
        
        .path-separator {
            margin: 0 8rpx;
            color: #999;
        }
    }
    
    .area-list {
        height: 60vh;
        
        .list-container {
            padding: 0 30rpx;
            
            .list-title {
                padding: 20rpx 0 10rpx;
                font-size: 28rpx;
                color: #333;
                font-weight: 500;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                margin-bottom: 10rpx;
            }
            
            .list-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                height: 90rpx;
                font-size: 30rpx;
                color: #333;
                border-bottom: 1px solid rgba(0, 0, 0, 0.03);
                border-radius: 8rpx;
                margin: 2rpx 0;
                padding: 0 10rpx;
                transition: all 0.2s ease;
                
                &:hover {
                    background-color: rgba(0, 0, 0, 0.02);
                }
                
                &.active {
                    color: var(--primary-color);
                    background-color: rgba(var(--primary-color-rgb), 0.08);
                    border-color: rgba(var(--primary-color-rgb), 0.2);
                    
                    .iconfont {
                        color: var(--primary-color);
                    }
                }
                
                &:last-child {
                    border-bottom: none;
                }
                
                .item-name {
                    flex: 1;
                }
                
                .iconfont {
                    font-size: 32rpx;
                    color: var(--primary-color);
                }
            }
        }
        
        .loading-more {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60rpx 0;
            color: #999;
            font-size: 26rpx;
            
            .loading-spinner {
                width: 30rpx;
                height: 30rpx;
                margin-right: 10rpx;
                border: 2rpx solid #eee;
                border-top-color: var(--primary-color);
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }
        }
        
        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100rpx 0;
            color: #999;
            font-size: 28rpx;
        }
    }
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style>
