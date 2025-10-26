import { checkOrder, cancelOrder, deleteOrder } from '@/addon/home_service/user/api/order'
import useSystemStore from '@/stores/system';
import { uploadImage } from '@/app/api/system'
import useConfigStore from "@/stores/config";
import { redirect } from '@/utils/common'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
const systemStore = useSystemStore()

class OrderMethods {
	/**
	 * 处理订单操作
	 */
	static orderClickFunction(data : any, key : string, callback ?: () => void) : void {
		/**
		 * 验收操作
		 */
		if (key === 'again_check') {
			const params = {
				order_id: data.order_id
			};

			checkOrder(params).then((res) => {
				uni.showToast({ title: res.msg, icon: 'none' });
				if (callback && typeof callback === 'function') {
					callback();
				}
			}).catch((err) => {
				callback();
			});
		}
		/**
		 * 取消订单
		 */
		if (key === 'action_cancel') {
			uni.showModal({
				title: '提示',
				content: '您确定要取消该订单吗？',
				confirmColor: useConfigStore().themeColor['--primary-color'],
				success: res => {
					if (res.confirm) {
						cancelOrder(data.order_id).then((res) => {
							callback();
						}).catch(() => {
							callback();
						})
					}
				}
			})
		}
		/**
		 * 删除订单
		 */
		if (key === 'action_delete') {
			uni.showModal({
				title: '提示',
				content: '您确定要删除该订单吗？',
				confirmColor: useConfigStore().themeColor['--primary-color'],
				success: res => {
					if (res.confirm) {
						deleteOrder(data.order_id).then((res) => {
							callback();
						}).catch(() => {
							callback();
						})
					}
				}
			})
		}
		
		/**
		 * 评价订单
		 */
		if (key === 'action_order_review_share') {
			redirect({url:'/addon/home_service/user/pages/order/evaluate/evaluate',param:{order_id:data.order_id}})
		}
		
		/**
		 * 售后订单
		 */
		if (key === 'action_refund') {
			redirect({url:'/addon/home_service/user/pages/order/refund/apply',param:{order_id:data.order_id}})
			useSubscribeMessage().request('home_service_refund')
		}
		
		/**
		 * 联系师傅
		 */
		if (key === 'action_contact_technician') {
			uni.makePhoneCall({
				phoneNumber: data.technician?.mobile,
			});
		}
		
		/**
		 * 再来一单
		 */
		if (key === 'action_order_again') {
			
			const datas = {
			    sku_id: data.item[0].item_id,
			    num: 1
			};
			
			uni.setStorage({
				key: 'o2oCreateData',
				data: {
					sku: datas
				},
				success: () => {
					redirect({ url: '/addon/home_service/user/pages/order/payment', param: { id: data.item[0].goods_id } })
				}
			});
		}
		
		
		
		
	}
}

export default OrderMethods;