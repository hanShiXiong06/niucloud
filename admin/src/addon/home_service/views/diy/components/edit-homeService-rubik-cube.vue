<template>
	<!-- 内容 -->
	<div class="content-wrap" v-show="diyStore.editTab == 'content'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('topImagebG') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('bgUrl')">
					<upload-image v-model="diyStore.editComponent.topBgUrl" :limit="1" />
				</el-form-item>
				<el-form-item :label="t('link')">
					<diy-link v-model="diyStore.editComponent.topLink" />
				</el-form-item>
			</el-form>
		</div>
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('bottomLeftBg') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('bgUrl')">
					<upload-image v-model="diyStore.editComponent.bottomLeftBgUrl" :limit="1" />
				</el-form-item>
				<el-form-item :label="t('link')">
					<diy-link v-model="diyStore.editComponent.leftLink" />
				</el-form-item>
			</el-form>
		</div>
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('bottomRight') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('bgUrl')">
					<upload-image v-model="diyStore.editComponent.bottomRightBgUrl" :limit="1" />
				</el-form-item>
				<el-form-item :label="t('link')">
					<diy-link v-model="diyStore.editComponent.rightLink" />
				</el-form-item>
			</el-form>
		</div>
		
	</div>

	<!-- 样式 -->
	<div class="style-wrap" v-show="diyStore.editTab == 'style'">
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('bgUrl') }}</h3>
			<el-form label-width="80px" class="px-[10px]">
				<el-form-item :label="t('bgUrl')">
					<upload-image v-model="diyStore.editComponent.bgUrl" :limit="1" />
				</el-form-item>
			</el-form>
		</div>
		<div class="edit-attr-item-wrap">
			<h3 class="mb-[10px]">{{ t('topTitleColor') }}</h3>
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