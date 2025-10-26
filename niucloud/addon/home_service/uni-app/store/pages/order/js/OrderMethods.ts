import { dispatchOrder,reminderOrder,transferOrder } from '@/addon/home_service/store/api/order'
import { uploadImage } from '@/app/api/system'
import {  redirect } from '@/utils/common'


class OrderMethods {
	/**
	 * 处理订单操作
	 */
	static orderClickFunction(data : any, key : string, callback ?: () => void) : void {
		console.log(key)
		/**
		 * 去派单
		 */
		if (key === 'action_dispatch' || key ==='action_transfer') {
			// 显示确认出发弹窗
			redirect({url:'/addon/home_service/store/pages/order/dispatchOrder',param:{order_id:data.order_id,key:key}})
		}
		/**
		 * 派单
		 */
		
		if(key === 'dispatch'){
			let params = {
				technician_id:data.id,
				order_id:data.order_id
			}
			dispatchOrder(params).then((res) => {
				uni.showToast({ title: res.msg, icon: 'none' });
				if (callback && typeof callback === 'function') {
					callback();
				}
			}).catch((err) => {
				uni.showToast({ title: err.msg || '操作失败', icon: 'none' });
			});
		}
		if(key == 'transfer'){
			let params = {
				technician_id:data.id,
				order_id:data.order_id
			}
			transferOrder(params).then((res) => {
				uni.showToast({ title: res.msg, icon: 'none' });
				if (callback && typeof callback === 'function') {
					callback();
				}
			}).catch((err) => {
				uni.showToast({ title: err.msg || '操作失败', icon: 'none' });
			});
		}
		/**
		 * 派单
		 */
		console.log(data)
		if(key === 'action_reminder'){
			let params = {
				order_id:data.order_id
			}
			reminderOrder(params).then((res) => {
				uni.showToast({ title: res.msg, icon: 'none' });
				if (callback && typeof callback === 'function') {
					callback();
				}
			}).catch((err) => {
			});
		}
		
	}
}

export default OrderMethods;