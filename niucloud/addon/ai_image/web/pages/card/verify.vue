<template>
	<div class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
		<div class="max-w-3xl mx-auto">
			<!-- Header -->
			<div class="mb-12">
				<div class="flex items-center justify-between mb-6">
					<div>
						<h1
							class="text-4xl font-bold mb-2 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
							卡密兑换
						</h1>
						<p class="text-gray-400 text-sm">输入卡密码激活您的权益</p>
					</div>
				</div>
			</div>

			<!-- Card Form -->
			<div
				class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl border border-gray-700/50 shadow-xl p-8">
				<!-- Icon -->
				<div class="flex justify-center mb-6">
					<div
						class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500/20 to-blue-500/20 flex items-center justify-center border border-purple-500/30">
						<svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
							</path>
						</svg>
					</div>
				</div>

				<!-- Input Group -->
				<div class="space-y-4">
					<div>
						<label class="block text-sm font-medium text-gray-300 mb-2">
							卡密码
						</label>
						<input v-model="cardNum" type="text" placeholder="请输入卡密码"
							class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all"
							@keyup.enter="submit" />
					</div>

					<!-- Submit Button -->
					<button @click="submit" :disabled="loading"
						class="w-full px-6 py-3 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-medium transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-xl hover:shadow-purple-500/25 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center gap-2">
						<svg v-if="loading" class="animate-spin h-5 w-5" fill="none" stroke="currentColor"
							viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
							</path>
						</svg>
						<span>{{ loading ? '激活中...' : '立即激活' }}</span>
					</button>
				</div>

				<!-- Tips -->
				<div class="mt-6 p-4 bg-blue-500/10 border border-blue-500/20 rounded-lg">
					<div class="flex items-start gap-3">
						<svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
							viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
							</path>
						</svg>
						<div class="flex-1">
							<h4 class="text-sm font-medium text-blue-400 mb-1">温馨提示</h4>
							<ul class="text-xs text-gray-400 space-y-1">
								<li>• 请确保卡密码输入正确</li>
								<li>• 每个卡密只能使用一次</li>
								<li>• 激活后权益将立即生效</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { verifyNum } from '@/addon/ai_image/api/aiimage'
import { ElMessage } from 'element-plus'

const cardNum = ref('')
const loading = ref(false)

const submit = async () => {
	if (!cardNum.value) {
		return ElMessage.error('请输入卡密码')
	}

	loading.value = true
	try {
		const res = await verifyNum(cardNum.value)
		ElMessage.success('激活成功')
		cardNum.value = ''
	} catch (error) {
		console.error('激活失败:', error)
	} finally {
		loading.value = false
	}
}
</script>

<style lang="scss" scoped></style>
