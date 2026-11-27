<template>
    <view class="con">
        <movable-area class="area" :style="{ height: areaHeight ? areaHeight : imageHeight + 'rpx' }" @mouseenter="mouseenter" @mouseleave="mouseleave">
            <block v-for="(item, index) in imageList" :key="item.id">
                <movable-view
                    class="view"
                    :x="item.x"
                    :y="item.y"
                    direction="all"
                    :damping="40"
                    :disabled="item.disable"
                    @change="onChange($event, item)"
                    @touchstart="touchstart(item)"
                    @mousedown="touchstart(item)"
                    @touchend="touchend(item)"
                    @mouseup="touchend(item)"
                    :style="{ width: viewWidth + 'px', height: viewWidth + 'px', 'z-index': item.zIndex, opacity: item.opacity }"
                >
                    <view class="area-con" :style="{ width: childWidth, height: childWidth, transform: 'scale(' + item.scale + ')' }">
                        <image class="pre-image" :src="img(item.src)" mode="aspectFill"></image>
                        <view
                            class="del-con"
                            @click="delImage(item, index)"
                            @touchstart.stop="delImageMp(item, index)"
                            @touchend.stop="nothing()"
                            @mousedown.stop="nothing()"
                            @mouseup.stop="nothing()"
                        >
                            <view class="del-wrap nc-iconfont nc-icon-guanbiV6xx"></view>
                        </view>
                    </view>
                </movable-view>
            </block>
            <view class="add" v-if="imageList.length < number" :style="{ top: add.y, left: add.x, width: viewWidth + 'px', height: viewWidth + 'px' }" @click="addImages">
                <view class="add-wrap" :style="{ width: childWidth, height: childWidth }">
                    <view>
                        <view class="nc-iconfont nc-icon-xiangjiV6xx text-[50rpx] mb-[12rpx]"></view>
                        <view class="text-[22rpx]">添加图片</view>
                    </view>
                    
                </view>
            </view>
        </movable-area>

    </view>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, onMounted, nextTick, getCurrentInstance } from 'vue'
import { redirect, img } from '@/utils/common';

// 获取当前实例
const instance = getCurrentInstance();

// 定义 props
const props = defineProps({
    // 返回排序后图片
    list: {
        type: Array,
        default: function() {
            return [];
        }
    },
    // 选择图片数量限制
    number: {
        type: Number,
        default: 6
    },
    // 选择图片的数组下标
    index: {
        type: Number,
        default: 0
    },
    // 图片父容器宽度（实际显示的图片宽度为 imageWidth / 1.1 ），单位 rpx
    imageWidth: {
        type: Number,
        default: 230
    },
    // 图片高度
    imageHeight: {
        type: Number,
        default: 230
    },
    // 图片列数（cols > 0 则 imageWidth 无效）
    cols: {
        type: Number,
        default: 0
    },
    // 图片周围空白填充，单位 rpx
    padding: {
        type: Number,
        default: 10
    },
    // 拖动图片时放大倍数 [0, ∞)
    scale: {
        type: Number,
        default: 1.1
    },
    // 拖动图片时不透明度
    opacity: {
        type: Number,
        default: 0.7
    },
    // 自定义添加（需配合 @addImage 事件使用）
    custom: {
        type: Boolean,
        default: false
    },
    uploadMethod: {
        type: String,
        default: 'image'
    },
    // 是否需要等待数据
    isAWait: {
        type: Boolean,
        default: false
    }
});

// 定义 emits
const emit = defineEmits(['addImage', 'update:list']);

// 响应式数据
const imageList = ref<any>([]);
const width = ref(0);
const add = reactive({
    x: 0,
    y: 0
});
const colsValue = ref(0);
const viewWidth = ref(0);
const tempItem = ref(null);
const timer = ref<any>(null);
const changeStatus = ref(true);
const preStatus = ref(true);

// 生命周期钩子
width.value = uni.getSystemInfoSync().windowWidth;

const rpx2px = (v) => {
    return (width.value * v) / 750;
};
viewWidth.value = rpx2px(props.imageWidth);

// 计算属性
const areaHeight = computed(() => {
    if (props.isAWait && colsValue.value == 0) return '';
    let height: any = '';
    if (imageList.value.length < props.number) {
        height = Math.ceil((imageList.value.length + 1) / colsValue.value) * viewWidth.value;
    } else {
        height = Math.ceil(imageList.value.length / colsValue.value) * viewWidth.value;
    }
    if (height != 'Infinity') return height + 'px';
    else return '';
});

const childWidth = computed(() => {
    return viewWidth.value - rpx2px(props.padding) * 2 + 'px';
});



// 方法定义
const refresh = () => {
    var flag = false;
    var time = setInterval(() => {
        if (props.isAWait && props.list.length == 0) return;
        imageList.value = [];
        const query = uni.createSelectorQuery().in(instance);
        query.select('.area').boundingClientRect((data: any) => {
            colsValue.value = Math.floor(data.width / viewWidth.value);
            if (props.cols > 0) {
                colsValue.value = props.cols;
                viewWidth.value = data.width / props.cols;
            }
        });
        query.exec();
        if ((props.isAWait && colsValue.value > 0) || !props.isAWait) {
            for (let item of props.list) {
                addProperties(item);
            }
        }
        if (areaHeight.value) {
            flag = true;
            if (flag) {
                clearInterval(time);
            }
        }
    }, 10);
};

const onChange = (e, item) => {
    if (!item) return;
    item.oldX = e.detail.x;
    item.oldY = e.detail.y;
    if (e.detail.source === 'touch') {
        if (item.moveEnd) {
            item.offset = Math.sqrt(Math.pow(item.oldX - item.absX * viewWidth.value, 2) + Math.pow(item.oldY - item.absY * viewWidth.value, 2));
        }
        let x = Math.floor((e.detail.x + viewWidth.value / 2) / viewWidth.value);
        if (x >= colsValue.value) return;
        let y = Math.floor((e.detail.y + viewWidth.value / 2) / viewWidth.value);
        let index = colsValue.value * y + x;
        if (item.index != index && index < imageList.value.length) {
            changeStatus.value = false;
            for (let obj of imageList.value) {
                if (item.index > index && obj.index >= index && obj.index < item.index) {
                    change(obj, 1);
                } else if (item.index < index && obj.index <= index && obj.index > item.index) {
                    change(obj, -1);
                } else if (obj.id != item.id) {
                    obj.offset = 0;
                    obj.x = obj.oldX;
                    obj.y = obj.oldY;
                    setTimeout(() => {
                        nextTick(() => {
                            obj.x = obj.absX * viewWidth.value;
                            obj.y = obj.absY * viewWidth.value;
                        });
                    }, 0);
                }
            }
            item.index = index;
            item.absX = x;
            item.absY = y;
            sortList();
        }
    }
};

const change = (obj, i) => {
    obj.index += i;
    obj.offset = 0;
    obj.x = obj.oldX;
    obj.y = obj.oldY;
    obj.absX = obj.index % colsValue.value;
    obj.absY = Math.floor(obj.index / colsValue.value);
    setTimeout(() => {
        nextTick(() => {
            obj.x = obj.absX * viewWidth.value;
            obj.y = obj.absY * viewWidth.value;
        });
    }, 0);
};

const touchstart = (item) => {
    imageList.value.forEach(v => {
        v.zIndex = v.index + 9;
    });
    item.zIndex = 99;
    item.moveEnd = true;
    tempItem.value = item;
    const timerId = setTimeout(() => {
        item.scale = props.scale;
        item.opacity = props.opacity;
        // 只有当定时器仍为当前ID时才清除，防止清除错误的定时器
        if (timer.value === timerId) {
            clearTimeout(timer.value);
            timer.value = null;
        }
    }, 200);
    timer.value = timerId;
};

const touchend = (item) => {
    previewImage(item);
    item.scale = 1;
    item.opacity = 1;
    item.x = item.oldX;
    item.y = item.oldY;
    item.offset = 0;
    item.moveEnd = false;
    setTimeout(() => {
        nextTick(() => {
            item.x = item.absX * viewWidth.value;
            item.y = item.absY * viewWidth.value;
            tempItem.value = null;
            changeStatus.value = true;
            
        }); 
    }, 0);
};

const previewImage = (item) => {
    if (timer.value && preStatus.value && changeStatus.value && item.offset < 28.28) {
        clearTimeout(timer.value);
        timer.value = null;
        const imageList = props.list.map(imgItem => img(imgItem));
        let currentIndex = imageList.findIndex(v => v === img(item.src));
        // 确保currentIndex有效
        if (currentIndex === -1) {
            currentIndex = 0;
        }
        setTimeout(() => {
            if (preStatus.value) {
                uni.previewImage({
                    urls: imageList,
                    current: currentIndex,
                    success: () => {
                        preStatus.value = false;
                        setTimeout(() => {
                            preStatus.value = true;
                        }, 600);
                    },
                    fail: (err) => {
                        // 失败时也要重置状态，防止预览功能被永久禁用
                        preStatus.value = false;
                        setTimeout(() => {
                            preStatus.value = true;
                        }, 600);
                    },
                });
            }
        }, 0)
        
    } else if (timer.value) {
        clearTimeout(timer.value);
        timer.value = null;
    }
};

const mouseenter = () => {
    // #ifdef H5
    imageList.value.forEach(v => {
        v.disable = false;
    });
    // #endif
};

const mouseleave = () => {
    // #ifdef H5
    if (tempItem.value) {
        imageList.value.forEach(v => {
            v.disable = true;
            v.zIndex = v.index + 9;
            v.offset = 0;
            v.moveEnd = false;
            if (v.id == tempItem.value.id) {
                if (timer.value) {
                    clearTimeout(timer.value);
                    timer.value = null;
                }
                v.scale = 1;
                v.opacity = 1;
                v.x = v.oldX;
                v.y = v.oldY;
                nextTick(() => {
                    v.x = v.absX * viewWidth.value;
                    v.y = v.absY * viewWidth.value;
                    tempItem.value = null;
                });
            }
        });
        changeStatus.value = true;
    }
    // #endif
};

const addImages = () => {
    if (props.custom) {
        emit('addImage');
    } else {
        var temp = {
                list: props.list,
                index: props.index
            };
        uni.setStorageSync('selectedAlbumImg', JSON.stringify(temp));
        redirect({url: '/addon/mall/pages/goods/album', param: {att_type: props.uploadMethod, number: props.number, checked: true }})
    }
};



const delImage = (item, index) => {
    imageList.value.splice(index, 1);
    for (let obj of imageList.value) {
        if (obj.index > item.index) {
            obj.index -= 1;
            obj.x = obj.oldX;
            obj.y = obj.oldY;
            obj.absX = obj.index % colsValue.value;
            obj.absY = Math.floor(obj.index / colsValue.value);
            nextTick(() => {
                obj.x = obj.absX * viewWidth.value;
                obj.y = obj.absY * viewWidth.value;
            });
        }
    }
    add.x = (imageList.value.length % colsValue.value) * viewWidth.value + 'px';
    add.y = Math.floor(imageList.value.length / colsValue.value) * viewWidth.value + 'px';
    sortList();
};

const delImageMp = (item, index) => {
    // #ifdef MP
    delImage(item, index);
    // #endif
};

const sortList = () => {
    let list = imageList.value.slice();
    list.sort((a, b) => {
        return a.index - b.index;
    });
    for (let i = 0; i < list.length; i++) {
        list[i] = list[i].src;
    }
    emit('update:list', list);
};

const addProperties = (item) => {
    let absX = imageList.value.length % colsValue.value;
    let absY = Math.floor(imageList.value.length / colsValue.value);
    let x = absX * viewWidth.value;
    let y = absY * viewWidth.value;
    imageList.value.push({
        src: item,
        x,
        y,
        oldX: x,
        oldY: y,
        absX,
        absY,
        scale: 1,
        zIndex: 9,
        opacity: 1,
        index: imageList.value.length,
        id: guid(),
        disable: false,
        offset: 0,
        moveEnd: false
    });
    add.x = (imageList.value.length % colsValue.value) * viewWidth.value + 'px';
    add.y = Math.floor(imageList.value.length / colsValue.value) * viewWidth.value + 'px';
    sortList();
};

const nothing = () => {}



const guid = () => {
    function S4() {
        return (((1 + Math.random()) * 0x10000) | 0).toString(16).substring(1);
    }
    return S4() + S4() + '-' + S4() + '-' + S4() + '-' + S4() + '-' + S4() + S4() + S4();
};




onMounted(() => {
    refresh();
});

defineExpose({
    refresh
})
</script>

<style lang="scss" scoped>
.con {
    .area {
        width: 100%;
        .view {
            display: flex;
            justify-content: center;
            align-items: center;
            .area-con {
                position: relative;
                .pre-image {
                    width: 100%;
                    height: 100%;
                    border-radius: 8rpx;
                }
                .del-con {
                    position: absolute;
                    top: 0rpx;
                    right: 0rpx;
                    .del-wrap {
                        width: 34rpx;
                        height: 34rpx;
                        background-color: #373737;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        font-size: 24rpx;
                        color: #fff;
                        border-radius: 0 8rpx 0 20rpx;
                        line-height: normal;
                    }
                }
            }
        }
        .add {
            position: absolute;
            display: flex;
            justify-content: center;
            align-items: center;
            .add-wrap {
                display: flex;
                justify-content: center;
                align-items: center;
                border: 1px dashed #999;
                width: 106rpx;
                height: 106rpx;
                color: #999;
                border-radius: 8rpx;
                text-align: center;
                box-sizing: border-box;
            }
        }
    }
}
</style>