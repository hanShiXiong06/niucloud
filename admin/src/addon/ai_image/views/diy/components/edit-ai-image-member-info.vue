<template>
	<!-- 内容 -->
	<div class="content-wrap" v-show="diyStore.editTab == 'content'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('memberStyle') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('bgUrl')">
					<upload-image v-model="diyStore.editComponent.bgUrl" :limit="1" />
				</el-form-item>
			</el-form>
			<h3 class="mb-[10px]">会员信息显示</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-radio-group v-model="diyStore.editComponent.show_member">
					<el-radio :label="true">{{ t('显示') }}</el-radio>
					<el-radio :label="false">{{ t('隐藏') }}</el-radio>
				</el-radio-group>
			</el-form>
		</div>
	</div>

	<!-- 样式 -->
	<div class="style-wrap" v-show="diyStore.editTab == 'style'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('memberStyle') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('textColor')">
					<el-color-picker v-model="diyStore.editComponent.textColor" />
				</el-form-item>
			</el-form>
		</div>

		<!-- 组件样式 -->
		<slot name="style"></slot>
	</div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import useDiyStore from '@/stores/modules/diy'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = ['componentBgUrl'] // 忽略公共属性

defineExpose({})

</script>

<style lang="scss" scoped></style>
