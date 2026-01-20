import { redirect } from "@/utils/common"
export async function goto(url: string) {
	redirect({ url: url })
}
export async function gotourl(url: string) {
	console.log('goto跳转网站测试')
}
export async function goback() {
	uni.navigateBack()
}