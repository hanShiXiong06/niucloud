<template>
    <view class="con">
        <movable-area class="area" :style="{ height: areaHeight }" @mouseenter="mouseenter" @mouseleave="mouseleave">
            <template v-for="(item, index) in initList" :key="item.id">
                <movable-view class="view" :x="item.x" :y="item.y"  :direction="direction" :damping="40" :disabled="item.src.draggable"  @change="onChange($event, item)"  @touchstart="touchstart(item)" @mousedown="touchstart(item)"  @touchend="touchend(item)" @mouseup="touchend(item)" :style="{ width: viewWidth + 'px', height: itemHeight + 'px', 'z-index': item.zIndex, opacity: item.opacity }">
                    <view class="area-con" :style="{ width: childWidth, height: itemHeight + 'px', transform: 'scale(' + item.scale + ')' }">
                        <slot :item="item.src" :index="index">
                            {{ item.src.name }}
                        </slot>
                    </view>
                </movable-view>
            </template>
        </movable-area>

    </view>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, onMounted, nextTick, getCurrentInstance } from 'vue'

// 获取当前实例
const instance = getCurrentInstance();

// 定义 props
const props = defineProps({
    // 返回排序后
    list: {
        type: Array,
        default: function() {
            return [];
        }
    },
    direction: {
        type: String,
        default: 'all'
    },
    // 宽度（实际显示的宽度为 itemWidth / 1.1 ），单位 rpx
    itemWidth: {
        type: Number,
        default: 40
    },
    // 高度
    itemHeight: {
        type: Number,
        default: 40
    },
    // 列数（cols > 0 则 itemWidth 无效）
    cols: {
        type: Number,
        default: 0
    },
    // 周围空白填充，单位 rpx
    padding: {
        type: Number,
        default: 0
    },
    // 拖动时放大倍数 [0, ∞)
    scale: {
        type: Number,
        default: 1.1
    },
    // 拖动时不透明度
    opacity: {
        type: Number,
        default: 0.7
    },
    // 自定义添加（需配合 @addImage 事件使用）
    custom: {
        type: Boolean,
        default: false
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
const initList = ref<any>([]);
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

// 生命周期钩子
width.value = uni.getSystemInfoSync().windowWidth;
const rpx2px = (v) => {
    return (width.value * v) / 750;
};
viewWidth.value = rpx2px(props.itemWidth);

// 计算属性
const areaHeight = computed(() => {
    if (props.isAWait && colsValue.value == 0) return '';
    let height: any = '';
    if(props.direction == 'all') {
        height = Math.ceil(initList.value.length / colsValue.value) * (props.itemHeight);
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
        initList.value = [];
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
        if (item.index != index && index < initList.value.length) {
            changeStatus.value = false;
            for (let obj of initList.value) {
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
    initList.value.forEach(v => {
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


const mouseenter = () => {
    // #ifdef H5
    initList.value.forEach(v => {
        v.disable = false;
    });
    // #endif
};

const mouseleave = () => {
    // #ifdef H5
    if (tempItem.value) {
        initList.value.forEach(v => {
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


const sortList = () => {
    let list = initList.value.slice();
    list.sort((a, b) => {
        return a.index - b.index;
    });
    for (let i = 0; i < list.length; i++) {
        list[i] = list[i].src;
    }
    emit('update:list', list);
};

const addProperties = (item) => {
    let absX = initList.value.length % colsValue.value;
    let absY = Math.floor(initList.value.length / colsValue.value);
    let x = absX * viewWidth.value;
    let y = absY * viewWidth.value;
    initList.value.push({
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
        index: initList.value.length,
        id: guid(),
        disable: false,
        offset: 0,
        moveEnd: false
    });
    add.x = (initList.value.length % colsValue.value) * viewWidth.value + 'px';
    add.y = Math.floor(initList.value.length / colsValue.value) * viewWidth.value + 'px';
    sortList();
};


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
            }
        }
    }
}
</style>