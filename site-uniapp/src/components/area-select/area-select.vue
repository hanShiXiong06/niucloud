<template>
    <u-popup :show="show" @close="show = false" mode="bottom" :round="10">
        <view @touchmove.prevent.stop class="popup-common">
            <view class="title">请选择地区</view>
            <view class="flex p-[30rpx] pt-[0] text-sm font-500">
                <view v-if="areaList.province.length" :class="getAreaTabClass('province')" @click="currSelect = 'province'">
                    <view v-if="selected.province">{{ selected.province.name }}</view>
                    <view v-else>请选择</view>
                </view>
                <view v-if="areaList.city.length" :class="getAreaTabClass('city')" @click="currSelect = 'city'">
                    <view v-if="selected.city">{{ selected.city.name }}</view>
                    <view v-else>请选择</view>
                </view>
                <view v-if="areaList.district.length" :class="getAreaTabClass('district')" @click="currSelect = 'district'">
                    <view v-if="selected.district">{{ selected.district.name }}</view>
                    <view v-else>请选择</view>
                </view>
            </view>
            <scroll-view scroll-y="true" class="h-[50vh]">
                <view class="flex p-[30rpx] pt-0 text-sm">
                    <view v-if="areaList.province.length" v-show="currSelect == 'province'">
                        <view v-for="item in areaList.province" :class="getAreaItemClass(selected.province, item)" @click="selected.province = item">{{ item.name }}</view>
                    </view>
                    <view v-if="areaList.city.length" v-show="currSelect == 'city'">
                        <view v-for="item in areaList.city" :class="getAreaItemClass(selected.city, item)" @click="selected.city = item">{{ item.name }}</view>
                    </view>
                    <view v-if="areaList.district.length" v-show="currSelect == 'district'">
                        <view v-for="item in areaList.district" :class="getAreaItemClass(selected.district, item)" @click="selected.district = item">{{ item.name }}</view>
                    </view>
                </view>
            </scroll-view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { getAreaListByPid, getAreaByCode } from '@/app/api/system'

const prop = defineProps({
    areaId: {
        type: Number,
        default: 0
    }
})

const show = ref(false)
const areaList = reactive({
    province: [],
    city: [],
    district: []
})
const currSelect = ref('province')

const selected = reactive({
    province: null,
    city: null,
    district: null
})

getAreaListByPid(0).then(({ data }) => {
    areaList.province = data
}).catch()

watch(() => prop.areaId, (nval, oval) => {
    if (nval && !oval) {
        getAreaByCode(nval).then(({ data }) => {
            data.province && (selected.province = data.province)
            data.city && (selected.city = data.city)
            data.district && (selected.district = data.district)
        })
    }
}, {
    immediate: true
})

/**
 * 监听省变更
*/
watch(() => selected.province, () => {
    getAreaListByPid(selected.province.id).then(({ data }) => {
        areaList.city = data
        currSelect.value = 'city'

        if (selected.city) {
            let isExist = false
            for (let i = 0; i < data.length; i++) {
                if (selected.city.id == data[i].id) {
                    isExist = true
                    break
                }
            }
            if (!isExist) {
                selected.city = null
            }
        }
    }).catch()
}, { deep: true })

/**
 * 监听市变更
 */
watch(() => selected.city, (nval) => {
    if (nval) {
        getAreaListByPid(selected.city.id).then(({ data }) => {
            areaList.district = data
            currSelect.value = 'district'

            if (selected.district) {
                let isExist = false
                for (let i = 0; i < data.length; i++) {
                    if (selected.district.id == data[i].id) {
                        isExist = true
                        break
                    }
                }
                if (!isExist) {
                    selected.district = null
                }
            }
        }).catch()
    } else {
        areaList.district = []
        selected.district = null
    }

}, { deep: true })

const emits = defineEmits(['complete'])

const getAreaTabClass = (type: string) => {
    return currSelect.value === type ? 'flex-1 pr-[10rpx] text-[var(--primary-color)]' : 'flex-1 pr-[10rpx]'
}

const getAreaItemClass = (selectedItem: any, item: any) => {
    return selectedItem && selectedItem.id == item.id
        ? 'h-[80rpx] flex items-center text-[var(--primary-color)]'
        : 'h-[80rpx] flex items-center'
}

/**
 * 监听区县变更
 */
watch(() => selected.district, (nval) => {
    if (nval) {
        currSelect.value = 'district'
        emits('complete', selected)
        show.value = false
    }
}, { deep: true })

const open = () => {
    show.value = true
}

defineExpose({
    open
})
</script>

<style lang="scss" scoped></style>
