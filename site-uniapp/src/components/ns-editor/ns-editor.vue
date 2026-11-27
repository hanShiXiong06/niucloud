<template>
	<view class="pt-[100rpx] pb-[40rpx]" :style="{ paddingTop: showMoreTool ? '200rpx' : '100rpx' }">
		<!-- 操作工具 -->
		<view class="w-[100vw] fixed top-0 left-0 right-0 z-[10]">
			<view class="h-[100rpx] flex items-center justify-around bg-[#fff]">
				<u-upload @afterRead="afterRead" multiple  :maxCount="imgCount">
					<view class="iconfont iconshangchuantupian text-[44rpx]" title="插入图片"></view>
				</u-upload>
				<view class="iconfont icont text-[44rpx]" title="修改文字样式" @click="showMore" :class="{ '!text-primary': showMoreTool }"></view>
				<view class="iconfont iconbianjiqifengexian text-[44rpx]" title="分割线" @click="insertDivider"></view>
				<view class="iconfont iconnew_icon_bianjiqi_chexiao_keyong text-[44rpx]" title="撤销" @click="undo"></view>
				<view class="iconfont iconnew_icon_bianjiqi_zhongzuo_keyong text-[44rpx]" title="重做" @click="redo"></view>
			</view>
			<!-- 文字相关操作 -->
			<view class="font-more  absolute left-0 right-0 top-[100rpx] flex items-center justify-around bg-[#fff] overflow-hidden" :style="{ height: showMoreTool ? '100rpx' : 0 }">
				<view class="iconfont iconzitijiacu text-[44rpx]" title="加粗" @click="setBold" :class="{ '!text-primary': showBold }"></view>
				<view class="iconfont iconzitixieti text-[44rpx]" title="斜体" @click="setItalic" :class="{ '!text-primary': showItalic }"></view>
				<view class="iconfont iconzitixiahuaxian text-[44rpx]" title="下划线" @click="setIns" :class="{ '!text-primary': showIns }"></view>
				<view class="iconfont iconbiaotizhengwenqiehuan text-[44rpx]" title="标题" @click="setHeader" :class="{ '!text-primary': showHeader }"></view>
				<view class="iconfont iconbianjiqijuzhongduiqi text-[44rpx]" title="居中" @click="setCenter" :class="{ '!text-primary': showCenter }"></view>
				<view class="iconfont iconjuyouduiqi text-[44rpx]" title="居右" @click="setRight" :class="{ '!text-primary': showRight }"></view>
			</view>
		</view>
		<editor
			id="editor"
			class="ql-container"
			:placeholder="placeholder"
			:show-img-size="true"
			:show-img-toolbar="true"
			:show-img-resize="true"
			@ready="onEditorReady"
			@statuschange="statuschange"
			ref="editor"
		></editor>
		<view class="w-full footer">
			<view class="bg-[#fff] py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500" @click="save()">保存</button>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { img } from '@/utils/common'
import { uploadImage } from '@/app/api/system'
const prop = defineProps({
    // 点击图片时显示图片大小控件
	showImgSize: {
		type: Boolean,
		default: false
	},
	// 点击图片时显示工具栏控件
	showImgToolbar: {
		type: Boolean,
		default: false
	},
	// 点击图片时显示修改尺寸控件
	showImgResize: {
		type: Boolean,
		default: false
	},
	// 占位符
	placeholder: {
		type: String,
		default: '请输入内容...'
	},
	verify: {
		type: String,
		default: '请输入内容'
	},
	// 初始化html
	html: {
		type: String,
		default: ''
	}
})
const imgCount = ref(9999)
const showMoreTool = ref(false)
const showBold = ref(false)
const showItalic = ref(false)
const showIns = ref(false)
const showHeader = ref(false)
const showCenter = ref(false)
const showRight = ref(false)

// 是否禁用草稿箱，true 是，false 否
const disabledDraftBox = ref(true)
const draftboxHtml = ref('')
const editorCtx = ref<any>(null)

const onEditorReady = (e: any)  =>{
	uni.createSelectorQuery().in(this).select('.ql-container').fields({size: true,context: true},(res: any) => {
				editorCtx.value = res.context;
				if (prop.html) {
					disabledDraftBox.value = false;
					editorCtx.value.setContents({
						html: prop.html
					});
				} else if (draftboxHtml.value) {
					disabledDraftBox.value = false;
					editorCtx.value.setContents({
						html: draftboxHtml.value
					});
				}
			}
		).exec();
}
const undo = ()  =>{
	editorCtx.value.undo();
}

const afterRead = (event: any) => {
	event.file.forEach((item: any) => {
		uploadImage({
			filePath: item.url,
			name: 'file'
		}).then((res: any) => {
			editorCtx.value.insertImage({
					src: img(res.data.url),
					alt: '图片',
					success: (e: any) => {}
				});
		}).catch(() => {
		})
	})
}

const  insertDivider = () => {
	editorCtx.value.insertDivider();
}
const redo = () => {
	editorCtx.value.redo();
}

const showMore = () => {
	showMoreTool.value = !showMoreTool.value;
	editorCtx.value?.setContents({});
}
const setBold = () => {
	showBold.value = !showBold.value;
	editorCtx.value.format('bold');
}
const setItalic = () => {
	showItalic.value = !showItalic.value;
	editorCtx.value.format('italic');
}
const checkStatus = (name: any, detail: any, obj: any) => {
	if (detail.hasOwnProperty(name)) {
		obj = true;
	} else {
		obj = false;
	}
}

const statuschange = (e: any) => {
	var detail = e.detail;
	checkStatus('bold', detail, showBold.value);
	checkStatus('italic', detail, showItalic.value);
	checkStatus('ins', detail, showIns.value);
	checkStatus('header', detail, showHeader.value);
	if (detail.hasOwnProperty('align')) {
		if (detail.align == 'center') {
			showCenter.value = true;
			showRight.value = false;
		} else if (detail.align == 'right') {
			showCenter.value = false;
			showRight.value = true;
		} else {
			showCenter.value = false;
			showRight.value = false;
		}
	} else {
		showCenter.value = false;
		showRight.value = false;
	}
}
const setIns = () => {
	showIns.value = !showIns.value;
	editorCtx.value.format('ins');
}
const setHeader = ()  => {
	showHeader.value = !showHeader.value;
	editorCtx.value.format('header', showHeader.value ? 'H2' : false);
}
const setCenter = () => {
	showCenter.value = !showCenter.value;
	editorCtx.value.format('align', showCenter.value ? 'center' : false);
}
const setRight = ()  =>{
	showRight.value = !showRight.value;
	editorCtx.value.format('align', showRight.value ? 'right' : false);
}

const emits = defineEmits(['editOk'])
const save = ()  =>{
	editorCtx.value.getContents({
		success: (res: any) => {
			var len = res.text.length;
			if (len == 1 && res.html == '<p><br></p>') {
				uni.showToast({ title: prop.verify });
				return;
			}
			if (len < 1 || len > 5000) {
				uni.showToast({ title: '内容描述字符数应在1～5000之间' });
				return;
			}
			emits('editOk', res);
		}
	});
}
</script>

<style lang="scss" scoped>
.ql-container {
	line-height: 160%;
	font-size: 28rpx;
	height: auto;
	background-color: #fff;
	padding: 30rpx;
	margin-top: 20rpx;
	margin-bottom: 160rpx;
	&.safe-area {
		margin-bottom: 200rpx;
	}
}

.iconfont {
	font-size: 44rpx;
	color: #333;
}
.disabled {
	color: #999;
}

:deep(.ql-editor.ql-blank:before) {
	font-style: initial;
}
.font-more {
	transition: all 0.15s;
}
:deep(.u-upload){
	flex: none !important;
}

.footer {
    height: calc(80rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(80rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>
