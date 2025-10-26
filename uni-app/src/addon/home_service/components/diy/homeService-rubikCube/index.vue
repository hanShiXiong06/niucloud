<template>
    <view :style="warpCss" class="p-[15rpx] pt-[25rpx]">
		<view class="text-[30rpx] font-bold mb-[25rpx]" :style="{color:diyComponent.textColor}">活动专区</view>
		<view class="flex flex-col">
			<view @click="redirect({url:diyComponent.topLink.url})">
				<up-image radius="5" width="100%" height="200rpx" :src="img(diyComponent.topBgUrl || '')" model="aspectFill">
				    <template #error>
				        <image class="w-[100%] h-[200rpx] overflow-hidden" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
				    </template>
				</up-image>
			</view>
			<view class="flex mt-[15rpx]">
				<view class="flex-1 mr-[15rpx]" @click="redirect({url:diyComponent.leftLink.url})">
					<up-image radius="5" width="100%" height="200rpx" :src="img(diyComponent.bottomLeftBgUrl || '')" model="aspectFill">
					    <template #error>
					        <image class="w-[100%] h-[200rpx] overflow-hidden" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
					    </template>
					</up-image>
				</view>
				<view class="flex-1" @click="redirect({url:diyComponent.rightLink.url})">
					<up-image radius="5" width="100%" height="200rpx" :src="img(diyComponent.bottomRightBgUrl || '')" model="aspectFill">
					    <template #error>
					        <image class="w-[100%] h-[200rpx] overflow-hidden" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
					    </template>
					</up-image>
				</view>
			</view>
		</view>
    </view>
</template>

<script setup lang="ts">
// 魔方
import { ref, onMounted, computed, watch, nextTick, getCurrentInstance } from 'vue';
import useDiyStore from '@/app/stores/diy';
import { img ,redirect} from '@/utils/common';
import useSystemStore from "@/stores/system";

const props = defineProps(['component', 'index']);

const diyStore = useDiyStore();

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index];
    } else {
        return props.component;
    }
})

const systemStore = useSystemStore()
systemStore.systemInfo = uni.getSystemInfoSync();


const warpCss = computed(() => {
    let style = '';
    style += 'position:relative;';
    if (diyComponent.value.componentStartBgColor) {
        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${ diyComponent.value.componentGradientAngle },${ diyComponent.value.componentStartBgColor },${ diyComponent.value.componentEndBgColor });`;
        else style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
    }

    if (diyComponent.value.componentBgUrl) {
        style += `background-image:url('${ img(diyComponent.value.componentBgUrl) }');`;
        style += 'background-size: cover;background-repeat: no-repeat;';
    }
	if (diyComponent.value.bgUrl) {
		style += 'background-image:url(' + img(diyComponent.value.bgUrl) + ');';
		style += 'background-size: 100% 100%;';
		style += 'background-repeat: no-repeat;';
	}
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
    if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
    return style;
})

onMounted(() => {
    refresh();
    // 装修模式下刷新
    if (diyStore.mode == 'decorate') {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                if (newValue && newValue.componentName == 'RubikCube') {
                    refresh();
                }
            }
        )
    } else {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                refresh();
            }
        )
    }
});

const instance = getCurrentInstance();
const height = ref(0)

const refresh = () => {
    if (diyStore.mode == 'decorate') {
		
        // diyComponent.value.list.forEach((item: any) => {
        //     // 装修模式下设置默认图
        //     if (item.imageUrl == '') {
        //         item.imgWidth = 690;
        //         item.imgHeight = 330;
        //     }
        // });
    }
}
</script>

<style lang="scss">
.rubik-cube {
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    .item {
        text-align: center;
        line-height: 0;
        overflow: hidden;
        image {
            width: 100%;
            max-width: 100%;
            height: 100%;
        }
    }
}

// 一行两个
.rubik-cube .item.row1-of2 {
    box-sizing: border-box;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

.rubik-cube .item.row1-of2:nth-child(1) {
    margin-left: 0 !important;
}

.rubik-cube .item.row1-of2:nth-child(2) {
    margin-right: 0 !important;
}

// 一行三个
.rubik-cube .item.row1-of3 {
    box-sizing: border-box;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

.rubik-cube .item.row1-of3:nth-child(1) {
    margin-left: 0 !important;
}

.rubik-cube .item.row1-of3:nth-child(3) {
    margin-right: 0 !important;
}

// 一行四个
.rubik-cube .item.row1-of4 {
    box-sizing: border-box;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

.rubik-cube .item.row1-of4:nth-child(1) {
    margin-left: 0 !important;
}

.rubik-cube .item.row1-of4:nth-child(4) {
    margin-right: 0 !important;
}

// 两左两右
.rubik-cube .item.row2-lt-of2-rt {
    // width: 50%;
    display: inline-block;
    box-sizing: border-box;
}

.rubik-cube .item.row2-lt-of2-rt:nth-child(1) {
    margin-left: 0 !important;
    margin-top: 0 !important;
}

.rubik-cube .item.row2-lt-of2-rt:nth-child(2) {
    margin-right: 0 !important;
    margin-top: 0 !important;
}

.rubik-cube .item.row2-lt-of2-rt:nth-child(3) {
    margin-left: 0 !important;
    margin-bottom: 0 !important;
}

.rubik-cube .item.row2-lt-of2-rt:nth-child(4) {
    margin-right: 0 !important;
    margin-bottom: 0 !important;
}

// 一左两右
.rubik-cube .template-left,
.rubik-cube .template-right {
    // width: 50%;
    box-sizing: border-box;
}

.rubik-cube .template-left .item.row1-lt-of2-rt:nth-child(1) {
    margin-bottom: 0;
}

.rubik-cube .template-right .item.row1-lt-of2-rt:nth-child(2) {
    margin-bottom: 0 !important;
}

.rubik-cube.row1-lt-of2-rt .template-right {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

// 一上两下
.rubik-cube .item.row1-tp-of2-bm:nth-child(1) {
    width: 100%;
    box-sizing: border-box;
    margin-top: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

.rubik-cube .item.row1-tp-of2-bm:nth-child(2) {
    // width: 50%;
    box-sizing: border-box;
    margin-left: 0 !important;
    margin-bottom: 0 !important;
}

.rubik-cube .item.row1-tp-of2-bm:nth-child(3) {
    // width: 50%;
    box-sizing: border-box;
    margin-right: 0 !important;
    margin-bottom: 0 !important;
}

// 一左三右
.rubik-cube .template-left .item.row1-lt-of1-tp-of2-bm {
    width: 100%;
    box-sizing: border-box;
}

.rubik-cube .template-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.rubik-cube .template-bottom .item:nth-child(2) {
    margin-right: 0 !important;
}
</style>
