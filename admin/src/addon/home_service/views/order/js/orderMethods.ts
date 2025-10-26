import selectTechnician from '@/addon/home_service/views/order/components/select-technician.vue';
class OrderMethods {
	/**
	 * 完成服务
	 */
	static orderClickFunction(data : any, key : string, callback ?: () => void ,openDialog?: (dialogKey: string) => void) : string {
		/**
		 * 开始出发
		 */
		let params = {}

		// 根据不同key触发不同弹框
		switch (key) {
			case 'action_dispatch':
				// 需要打开「待派单弹框」，传递对应的弹框key
				openDialog?.('action_dispatch')
				break
			case 'action_transfer':
			// 需要打开「重新派单弹框」，传递对应的弹框key
			openDialog?.('action_transfer')
			break
			case 'select_tag':
				// 需要打开「标签选择弹框」
				openDialog?.('tagSelect')
				break
			case 'confirm_action':
				// 需要打开「确认弹框」
				openDialog?.('confirm')
				break
			// 其他操作...
			case 'action_depart':
				// 原有逻辑...
				break
			case 'action_delete':
				// 删除订单操作
				return 'delete'
			case 'action_follow':
				// 回访订单操作
				openDialog?.('action_follow')
				break
			
		}
	}
}

export default OrderMethods;