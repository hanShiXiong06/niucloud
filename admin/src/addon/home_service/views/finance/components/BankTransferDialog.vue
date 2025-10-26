<template>
	<el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" :before-close="handleClose">
		<el-form :model="transferForm" ref="transferFormRef" :rules="dialogType === 'transfer' ? transferRules : {}" label-width="100px">
			<!-- 提现ID（隐藏字段） -->
			<input type="hidden" v-model="transferForm.cash_out_id">
			<!-- 收款人 + 复制图标 -->
			<el-form-item label="收款人" prop="payee">
				<el-input v-model="transferForm.payee" placeholder="请输入收款人" class="input-width !w-[300px]" readonly />
				<el-tooltip content="复制收款人" placement="top">
					<el-icon class="copy-icon" @click="copyText(transferForm.payee)">
						<DocumentCopy />
					</el-icon>
				</el-tooltip>
			</el-form-item>

			<!-- 转账金额 + 复制图标 -->
			<el-form-item label="转账金额" prop="amount">
				<el-input v-model="transferForm.amount" placeholder="请输入转账金额" class="input-width !w-[300px]"
					type="number" readonly />
				<el-tooltip content="复制转账金额" placement="top">
					<el-icon class="copy-icon" @click="copyText(transferForm.amount)">
						<DocumentCopy />
					</el-icon>
				</el-tooltip>
			</el-form-item>

			<!-- 开户行 + 复制图标 -->
			<el-form-item label="提现方式" prop="transfer_type_name">
				<el-input v-model="transferForm.transfer_type_name" placeholder="请输入开户行" class="input-width !w-[300px]"
					readonly />
				<el-tooltip content="复制开户行" placement="top">
					<el-icon class="copy-icon" @click="copyText(transferForm.transfer_type_name)">
						<DocumentCopy />
					</el-icon>
				</el-tooltip>
			</el-form-item>

			<div  v-if="transferForm.transfer_type == 'bank' ">
				<!-- 开户行 + 复制图标 -->
				<el-form-item label="开户行" prop="bankName">
					<el-input v-model="transferForm.bankName" placeholder="请输入开户行" class="input-width !w-[300px]"
						readonly />
					<el-tooltip content="复制开户行" placement="top">
						<el-icon class="copy-icon" @click="copyText(transferForm.bankName)">
							<DocumentCopy />
						</el-icon>
					</el-tooltip>
				</el-form-item>

				<!-- 银行卡号 + 复制图标 -->
				<el-form-item label="银行卡号" prop="bankCardNo"  >
					<el-input v-model="transferForm.bankCardNo" placeholder="请输入银行卡号" class="input-width !w-[300px]"
						readonly />
					<el-tooltip content="复制银行卡号" placement="top">
						<el-icon class="copy-icon" @click="copyText(transferForm.bankCardNo)">
							<DocumentCopy />
						</el-icon>
					</el-tooltip>
				</el-form-item>
			</div>
			<div v-else>
				<el-form-item label="收款码" prop="bankCardNo"  >
					<div class="min-w-[60px] h-[60px] flex items-center justify-center">
						<el-image v-if="transferForm.transfer_payment_code" class="w-[60px] h-[60px]"
							:src="img(transferForm.transfer_payment_code)" :preview-src-list="[img(transferForm.transfer_payment_code)]" fit="contain">
							<template #error>
								<div class="image-slot">
									<img class="w-[60px] h-[60px]"
										src="@/addon/home_service/assets/goods_default.png" />
								</div>
							</template>
						</el-image>
						<img v-else class="w-[60px] h-[60px]"
							src="@/addon/home_service/assets/goods_default.png" fit="contain" />
					</div>
				</el-form-item>
			</div>
			<el-form-item label="上传凭证" prop="bankCardNo"  v-if="dialogType != 'transfer' && transferForm?.transfer_voucher">
			 
					<div class="min-w-[60px] h-[60px] flex items-center justify-center">
						<el-image v-if="transferForm?.transfer_voucher" class="w-[60px] h-[60px]"
							:src="img(transferForm?.transfer_voucher)" :preview-src-list="[img(transferForm?.transfer_voucher)]" fit="contain">
							<template #error>
								<div class="image-slot">
									<img class="w-[60px] h-[60px]"
										src="@/addon/home_service/assets/goods_default.png" />
								</div>
							</template>
						</el-image>
						<img v-else class="w-[60px] h-[60px]"
							src="@/addon/home_service/assets/goods_default.png" fit="contain" />
					</div>
			</el-form-item>
			<!-- 备注信息 -->
			<el-form-item v-if="dialogType === 'transfer'" label="备注" prop="remark">
				<el-input v-model="transferForm.remark" placeholder="请输入转账备注信息" type="textarea" rows="3"
					class="input-width !w-[300px]" />
			</el-form-item>

			<!-- 上传凭证 -->
			<el-form-item v-if="dialogType === 'transfer'" label="上传凭证" prop="voucher">
				<div v-if="transferForm.voucher">
					<el-image style="width: 100px; height: 100px" :src="img(transferForm.voucher)" :zoom-rate="1.2"
						:max-scale="7" :min-scale="0.2" :preview-src-list="[img(transferForm.voucher)]" show-progress
						:initial-index="4" fit="cover" />
				</div>
				<div v-else>
					<upload-image v-model="transferForm.voucher" :limit="1" />
				</div>
			</el-form-item>
		</el-form>

		<template #footer>
			<span class="dialog-footer">
				<el-button @click="dialogVisible = false">关闭</el-button>
				<el-button v-if="dialogType === 'transfer'" type="primary" @click="handleConfirmTransfer">确认转账</el-button>
			</span>
		</template>
	</el-dialog>
</template>

<script lang="ts" setup>
	import { ref, reactive, nextTick, computed } from 'vue';
	import { ElMessage, FormInstance, ElTooltip, ElIcon } from 'element-plus';
	import { DocumentCopy } from '@element-plus/icons-vue'; // 引入复制图标
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common';
	import { setCashOutTransfer } from '@/addon/home_service/api/finance';
	import { defineEmits } from 'vue';

	// 定义emit事件
	const emit = defineEmits(['transfer-success']);
	// 弹框显隐控制
	const dialogVisible = ref(false);
	// 操作类型：transfer 转账，view 查看
	const dialogType = ref<'transfer' | 'view'>('transfer');
	// 弹框标题
	const dialogTitle = computed(() => {
		return dialogType.value === 'transfer' ? '银行转账' : '提现详情';
	});

	// 表单数据
	const transferForm = reactive({
		cash_out_id: '', // 提现ID
		payee: '',
		amount: '',
		bankName: '',
		bankCardNo: '',
		remark: '', // 备注信息
		voucher: '', // 上传的凭证文件
		transfer_type_name:'',
		transfer_type:'',
		transfer_payment_code:'',
		transfer_voucher:''
	});

	// 上传文件列表
	const fileList = ref([]);

	// 表单校验规则
	const transferRules = reactive({
		remark: [
			{ max: 200, message: '备注信息不能超过200个字符', trigger: 'blur' }
		],
		voucher: [
			{ required: true, message: '请上传转账凭证', trigger: 'change' },
		],
	});

	// 表单引用（用于校验）
	const transferFormRef = ref<FormInstance>();

	/**
	 * 父组件调用：打开弹框并传入提现数据
	 * @param rowData 提现行数据（含提现ID、收款人、金额、开户行、卡号等）
	 * @param type 操作类型：transfer 转账，view 查看
	 */
	const openDialog = (rowData : any, type: 'transfer' | 'view' = 'transfer') => {
		dialogType.value = type;
		// 填充只读字段（从提现行数据获取）
		transferForm.cash_out_id = rowData.id || '';
		transferForm.payee = rowData.payee || rowData.related_name || '张三';
		transferForm.amount = rowData.money || '1000.00';
		transferForm.bankName = rowData.transfer_bank || '中国工商银行';
		transferForm.bankCardNo = rowData.transfer_account || '6222 0808 0808 8888';
		transferForm.transfer_payment_code = rowData.transfer_payment_code;
		
		transferForm.remark = ''; // 重置备注
		transferForm.voucher = ''; // 重置凭证
		
		transferForm.transfer_type = rowData.transfer_type;

		transferForm.transfer_type_name = rowData.transfer_type_name || '银行卡';
		if(dialogType.value != 'transfer' && rowData.status == 2){
			transferForm.transfer_voucher = rowData.transfer.transfer_voucher

		}
 
		// 重置文件和表单校验
		fileList.value = [];
		dialogVisible.value = true;

		nextTick(() => {
			transferFormRef.value?.clearValidate();
		});
	};

	/**
	 * 处理文件上传（更新文件列表 + 表单数据）
	 */
	const handleFileChange = (file : any, list : any[]) => {
		fileList.value = list;
		transferForm.voucher = file.raw; // 存储文件对象（可转FormData传给后端）
	};

	/**
	 * 上传前校验（限制格式、大小）
	 */
	const beforeUpload = (file : any) => {
		const isImageOrPdf = file.type.includes('image') || file.type === 'application/pdf';
		const isLt2M = file.size / 1024 / 1024 < 2;

		if (!isImageOrPdf) {
			ElMessage.error('只能上传图片（PNG/JPG/JPEG）或PDF文件');
			return false;
		}
		if (!isLt2M) {
			ElMessage.error('文件大小不能超过2MB');
			return false;
		}
		return true;
	};

	/**
	 * 确认转账（触发表单校验 + 调用接口）
	 */
	const handleConfirmTransfer = () => {
		transferFormRef.value?.validate((valid) => {
			if (valid) {
				let param = {
					cash_out_id:transferForm.cash_out_id,
					transfer_voucher:transferForm.voucher,
					transfer_remark:transferForm.remark
				}
				// 调用转账接口
			setCashOutTransfer(param).then((res: any) => {
				dialogVisible.value = false;
				// 通知父组件转账成功，刷新表格数据
				emit('transfer-success');
			}).catch(() => {
			});
			}
		});
	};

	/**
	 * 关闭弹框（重置表单）
	 */
	const handleClose = () => {
		dialogVisible.value = false;
		transferFormRef.value?.resetFields();
		fileList.value = [];
	};

	/**
	 * 复制文本到剪贴板
	 */
	const copyText = (text : string) => {
		navigator.clipboard.writeText(text).then(() => {
			ElMessage.success('复制成功');
		}).catch(() => {
			ElMessage.error('复制失败，请重试');
		});
	};

	// 暴露方法给父组件调用
	defineExpose({
		openDialog,
	});
</script>

<style lang="scss" scoped>
	.upload-demo {
		width: 100%;
		border: 1px dashed #dcdcdc;
		border-radius: 6px;
		padding: 20px;
		text-align: center;
	}

	.copy-icon {
		cursor: pointer;
		margin-left: 8px;
		color: #606266;
		font-size: 18px;

		&:hover {
			color: #409eff; //  hover 时变主色调
		}
	}
</style>