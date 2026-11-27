<template>
	<view v-if="visibleSync" :class="{ 'uni-drawer--visible': showDrawer, 'uni-drawer--right': rightMode }" class="uni-drawer" @touchmove.stop.prevent="moveHandle">
		<view class="uni-drawer__mask" @tap="close" />
		<view class="uni-drawer__content"><slot /></view>
	</view>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
	/**
	 * 显示状态
	 */
	visible: {
		type: Boolean,
		default: false
	},
	/**
	 * 显示模式（左、右），只在初始化生效
	 */
	mode: {
		type: String,
		default: ''
	},
	/**
	 * 蒙层显示状态
	 */
	mask: {
		type: Boolean,
		default: true
	}
});

const emit = defineEmits(['close']);

const visibleSync = ref(false);
const showDrawer = ref(false);
const rightMode = ref(false);
const closeTimer = ref(null);
const watchTimer = ref(null);


watch(() => props.visible, (val) => {
	clearTimeout(watchTimer.value);
	watchTimer.value = setTimeout(() => {
		showDrawer.value = val;
	}, 100);
	
	if (visibleSync.value) {
		clearTimeout(closeTimer.value);
	}
	
	if (val) {
		visibleSync.value = val;
	} else {
		watchTimer.value = setTimeout(() => {
			visibleSync.value = val;
		}, 300);
	}
});

onMounted(() => {
	visibleSync.value = props.visible;
	setTimeout(() => {
		showDrawer.value = props.visible;
	}, 100);
	rightMode.value = props.mode === 'right';
});

const close = () => {
	showDrawer.value = false;
	closeTimer.value = setTimeout(() => {
		visibleSync.value = false;
		emit('close');
	}, 200);
};

const moveHandle = () => {};

// 对外暴露方法
defineExpose({
	close
});
</script>

<style>
@charset "UTF-8";

.uni-drawer {
	display: block;
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	overflow: hidden;
	visibility: hidden;
	z-index: 999;
	height: 100%;
}

.uni-drawer.uni-drawer--right .uni-drawer__content {
	left: auto;
	right: 0;
	transform: translatex(100%);
}

.uni-drawer.uni-drawer--visible {
	visibility: visible;
}

.uni-drawer.uni-drawer--visible .uni-drawer__content {
	transform: translatex(0);
}

.uni-drawer.uni-drawer--visible .uni-drawer__mask {
	display: block;
	opacity: 1;
}

.uni-drawer__mask {
	display: block;
	opacity: 0;
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.4);
	transition: opacity 0.3s;
}

.uni-drawer__content {
	display: block;
	position: absolute;
	top: 0;
	left: 0;
	width: 61.8%;
	height: 100%;
	background: #fff;
	transition: all 0.3s ease-out;
	transform: translatex(-100%);
}

.safe-area {
	padding-bottom: 68rpx;
	padding-top: 44rpx;
	box-sizing: border-box;
}
</style>