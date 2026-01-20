<template>
	<!-- 内容 -->
	<div class="content-wrap" v-show="diyStore.editTab == 'content'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">基础设置</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item label="送字背景">
					<div>
						<color-picker v-model:pureColor="diyStore.editComponent.songbackground"
							v-model:gradientColor="diyStore.editComponent.songbackground" format="hex6" shape="square"
							useType="both" />
					</div>
				</el-form-item>
				<h3 class="mb-[10px]">中间字体颜色</h3>
				<el-form-item label="上面颜色">
					<div>
						<el-color-picker v-model="diyStore.editComponent.qsfontcolor" show-alpha
							:predefine="diyStore.predefineColors" />
					</div>
				</el-form-item>
				<el-form-item label="选择颜色">
					<div>
						<el-color-picker v-model="diyStore.editComponent.slfontcolor" show-alpha
							:predefine="diyStore.predefineColors" />
					</div>
				</el-form-item>
				<h3 class="mb-[10px]">按钮设置</h3>
				<el-form-item label="按钮背景">
					<div>
						<color-picker v-model:pureColor="diyStore.editComponent.btbackground"
							v-model:gradientColor="diyStore.editComponent.btbackground" format="hex6" shape="square"
							useType="both" />
					</div>
				</el-form-item>
				<el-form-item label="字体颜色">
					<div>
						<el-color-picker v-model="diyStore.editComponent.btfontcolor" show-alpha
							:predefine="diyStore.predefineColors" />
					</div>
				</el-form-item>

			</el-form>
		</div>
	</div>

	<!-- 样式 -->
	<div class="style-wrap" v-show="diyStore.editTab == 'style'">
		<!-- 组件样式 -->
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">基础样式</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item label="内边距">
					<el-slider v-model="diyStore.editComponent.padding" max="14" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>
			</el-form>
		</div>
		<slot name="style">

		</slot>
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