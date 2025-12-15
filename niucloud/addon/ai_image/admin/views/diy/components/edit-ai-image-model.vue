<template>
	<!-- 内容 -->
	<div class="content-wrap" v-show="diyStore.editTab == 'content'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">基础设置</h3>
			<el-form label-width="100px" class="px-[10px]">
				<el-form-item label="布局样式">
					<el-radio-group v-model="diyStore.editComponent.style">
						<el-radio label="style1">瀑布流（样式1）</el-radio>
						<el-radio label="style2">单列（样式2）</el-radio>
					</el-radio-group>
				</el-form-item>
				
				<el-form-item label="卡片颜色">
					<div>
						<color-picker v-model:pureColor="diyStore.editComponent.bgcolor"
							v-model:gradientColor="diyStore.editComponent.bgcolor" format="hex6" shape="square"
							useType="both" />
					</div>
				</el-form-item>

				<el-form-item label="标题颜色">
					<div>
						<el-color-picker v-model="diyStore.editComponent.titlecolor" show-alpha
							:predefine="diyStore.predefineColors" />
					</div>
				</el-form-item>

				<el-form-item label="描述颜色">
					<div>
						<el-color-picker v-model="diyStore.editComponent.desccolor" show-alpha
							:predefine="diyStore.predefineColors" />
					</div>
				</el-form-item>

				<el-form-item label="按钮文字">
					<el-input v-model="diyStore.editComponent.buttonText" placeholder="如：同款、做同款" clearable />
				</el-form-item>

				<el-form-item label="按钮颜色">
					<div>
						<el-color-picker v-model="diyStore.editComponent.buttonColor" show-alpha
							:predefine="diyStore.predefineColors" />
					</div>
				</el-form-item>

				<el-form-item label="统计文字">
					<el-input v-model="diyStore.editComponent.statsText" placeholder="如：xx万人在使用" clearable />
				</el-form-item>

				<el-form-item label="图片数量">
					<el-slider v-model="diyStore.editComponent.maxImages" :min="1" :max="6" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>

				<el-form-item label="文字对齐">
					<el-radio-group v-model="diyStore.editComponent.textAlign">
						<el-radio label="left">居左</el-radio>
						<el-radio label="center">居中</el-radio>
					</el-radio-group>
				</el-form-item>

				<el-form-item label="内容内边距">
					<el-slider v-model="diyStore.editComponent.contentPadding" :min="0" :max="40" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>
			</el-form>
		</div>

		<div class="edit-attr-item-wrap mt-[20px]">
			<h3 class="mb-[10px]">样式设置</h3>
			<el-form label-width="100px" class="px-[10px]">
				<el-form-item label="卡片圆角">
					<el-slider v-model="diyStore.editComponent.cardRadius" :min="0" :max="50" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>

				<el-form-item label="卡片间距">
					<el-slider v-model="diyStore.editComponent.cardGap" :min="0" :max="40" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>

				<el-form-item label="标题大小">
					<el-slider v-model="diyStore.editComponent.titleSize" :min="20" :max="40" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>

				<el-form-item label="描述大小">
					<el-slider v-model="diyStore.editComponent.descSize" :min="18" :max="32" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>

				<el-form-item label="按钮大小">
					<el-slider v-model="diyStore.editComponent.buttonSize" :min="20" :max="32" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>
			</el-form>
		</div>
	</div>

	<!-- 样式 -->
	<div class="style-wrap" v-show="diyStore.editTab == 'style'">
		<!-- 组件样式 -->

		<slot name="style">

		</slot>
	</div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import useDiyStore from '@/stores/modules/diy'
import { ColorPicker } from "vue3-colorpicker";
import "vue3-colorpicker/style.css";
import { reactive, ref, watch, onMounted } from 'vue'
const pureColor = ref<ColorInputWithoutInstance>("red");
const gradientColor = ref("linear-gradient(0deg, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, 1) 100%)");

const diyStore = useDiyStore()
diyStore.editComponent.ignore = []; // 忽略公共属性

// 初始化默认值
onMounted(() => {
	ensureDefaultValues();
});

const ensureDefaultValues = () => {
	// 基础设置默认值
	if (!diyStore.editComponent.style) {
		diyStore.editComponent.style = 'style1'; // 默认瀑布流
	}
	if (!diyStore.editComponent.bgcolor) {
		diyStore.editComponent.bgcolor = 'rgba(30, 41, 59, 0.6)';
	}
	if (!diyStore.editComponent.titlecolor) {
		diyStore.editComponent.titlecolor = '#e2e8f0';
	}
	if (!diyStore.editComponent.desccolor) {
		diyStore.editComponent.desccolor = '#cbd5e1';
	}
	if (!diyStore.editComponent.buttonText) {
		diyStore.editComponent.buttonText = '同款';
	}
	if (!diyStore.editComponent.buttonColor) {
		diyStore.editComponent.buttonColor = 'rgba(34, 211, 238, 0.9)';
	}
	if (!diyStore.editComponent.statsText) {
		diyStore.editComponent.statsText = 'xx万人在使用';
	}
	if (!diyStore.editComponent.maxImages) {
		diyStore.editComponent.maxImages = 3;
	}
	if (!diyStore.editComponent.textAlign) {
		diyStore.editComponent.textAlign = 'center';
	}
	if (diyStore.editComponent.contentPadding === undefined) {
		diyStore.editComponent.contentPadding = 16;
	}

	// 样式设置默认值
	if (diyStore.editComponent.cardRadius === undefined) {
		diyStore.editComponent.cardRadius = 24;
	}
	if (diyStore.editComponent.cardGap === undefined) {
		diyStore.editComponent.cardGap = 12;
	}
	if (diyStore.editComponent.titleSize === undefined) {
		diyStore.editComponent.titleSize = 30;
	}
	if (diyStore.editComponent.descSize === undefined) {
		diyStore.editComponent.descSize = 22;
	}
	if (diyStore.editComponent.buttonSize === undefined) {
		diyStore.editComponent.buttonSize = 24;
	}
};

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