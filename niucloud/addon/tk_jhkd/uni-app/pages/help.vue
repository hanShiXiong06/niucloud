<template>
	<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getHelpListFn">
		<up-collapse @change="change" @close="close" @open="open">
			<block v-for="(item, index) in listData">
				<up-collapse-item :title="item.title">
					<text class="up-collapse-content">{{ item.content }}</text>
				</up-collapse-item>
			</block>
		</up-collapse>
		<mescroll-empty v-if="!listData" :option="{ tip: '没有地址数据' }"></mescroll-empty>
	</mescroll-body>

	<tabbar addon="tk_jhkd" />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import {
	help
} from '@/addon/tk_jhkd/api/tkjhkd'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
const listData = ref()
let loading = ref<boolean>(false);
const getHelpListFn = (mescroll) => {
	let data = ref({});
	loading.value = false;
	data.value.page = mescroll.num;
	data.value.page_size = mescroll.size;
	help(data.value).then((res) => {
		let newArr = res.data.data;
		mescroll.endSuccess(newArr.length);
		//设置列表数据
		if (mescroll.num == 1) {
			listData.value = []; //如果是第一页需手动制空列表
		}
		listData.value = listData.value.concat(newArr);
		loading.value = true;
	}).catch((e) => {
		console.log('erro', e)
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}
</script>

<style lang="scss" scoped>
@import '@/addon/tk_jhkd/utils/styles/common.scss';
</style>