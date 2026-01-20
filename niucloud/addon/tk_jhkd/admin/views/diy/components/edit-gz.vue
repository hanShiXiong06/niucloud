<template>
	<!-- 内容 -->
	<div class="content-wrap" v-show="diyStore.editTab == 'content'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">基础设置</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item label="按钮背景">
					<div>
						<color-picker v-model:pureColor="diyStore.editComponent.btbackground"
							v-model:gradientColor="diyStore.editComponent.btbackground" format="hex6" shape="square"
							useType="both" />
					</div>
				</el-form-item>
				<el-form-item label="文本设置">
					<el-input v-model="diyStore.editComponent.text" maxlength="20" placeholder="请输入内容" />
				</el-form-item>
				<el-form-item label="提醒时间">
					<el-input type="number" v-model="diyStore.editComponent.min" placeholder="请输入内容" />
					<span class="text-gray-400 text-xs">单位天，如填写3,3天内提醒用户一次</span>
				</el-form-item>
				<el-form-item label="跳转链接">
					<diy-link v-model="diyStore.editComponent.link" />
				</el-form-item>
			</el-form>
		</div>
	</div>

	<!-- 样式 -->
	<div class="style-wrap" v-show="diyStore.editTab == 'style'">


	</div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import useDiyStore from '@/stores/modules/diy'
import { ColorPicker } from "vue3-colorpicker";
import "vue3-colorpicker/style.css";
import { reactive, ref, watch } from 'vue'
const pureColor = ref<ColorInputWithoutInstance>("red");
const gradientColor = ref("linear-gradient(0deg, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, 1) 100%)");

const diyStore = useDiyStore()
diyStore.editComponent.ignore = []; // 忽略公共属性

defineExpose({})

</script>

<style lang="scss">
.horz-blank-slider {
	.el-slider__input {
		width: 100px;
	}
}
</style>
<style lang="scss" scoped></style>