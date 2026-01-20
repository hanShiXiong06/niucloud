<template>
	<!-- 内容 -->
	<div class="content-wrap" v-show="diyStore.editTab == 'content'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">基础设置</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item label="图标大小">
					<el-slider v-model="diyStore.editComponent.iconsize" max="60" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>
				<el-form-item label="圆角大小">
					<el-slider v-model="diyStore.editComponent.radiussize" max="100" show-input size="small"
						class="ml-[10px] horz-blank-slider" />
				</el-form-item>
				<el-form-item label="图标间距">
					<el-slider v-model="diyStore.editComponent.mrsize" max="16" show-input size="small"
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