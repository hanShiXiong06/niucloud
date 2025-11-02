import { departOrder, photoTaken, startService, saveCheck, editServiceTime } from '@/addon/home_service/technician/api/order'
import useSystemStore from '@/stores/system';
import { uploadImage } from '@/app/api/system'
import { img, redirect, copy, timeStampTurnTime } from '@/utils/common'
import { openPopup, closePopup } from './popupStatus'

const systemStore = useSystemStore()

class OrderMethods {
	/**
	 * 处理订单操作
	 */
	static orderClickFunction(data : any, key : string, callback ?: () => void) : void {
		/**
		 * 开始出发
		 */
		if (key === 'action_depart') {
			// 显示确认出发弹窗
			openPopup({
				visible: true,
				order: data,
				actionKey: key,
				onConfirm: async () => {
					try {
						const params = {
							order_id: data.order_id,
							depart_lat_lng: systemStore.diyAddressInfo?.latitude + ',' + systemStore.diyAddressInfo?.longitude
						};

						const res = await departOrder(params);
						uni.showToast({ title: res.msg, icon: 'none' });
						if (callback && typeof callback === 'function') {
							callback(); // 触发刷新
						}
					} catch (err : any) {
						uni.showToast({ title: err.msg || '操作失败', icon: 'none' });
					} finally {
						closePopup();
					}
				}
			});
		}

		/**
		 * 拍照操作
		 */
		if (key === 'action_photo_taken') {
			// 存储图片列表的临时变量
			const imgList : string[] = [];
			// 显示拍照弹窗
			openPopup({
				visible: true,
				order: data,
				actionKey: key,
				onConfirm: async (popupData : any) => {
					try {
						if (data.item[0].is_force_clock_in) {
							if (!popupData || !popupData.imgList || popupData.imgList.length === 0) {
								uni.showToast({ title: '请至少上传一张照片', icon: 'none' });
								return;
							}
						}

						const params = {
							order_id: data.order_id,
							take_photos: popupData.imgList.toString()
						};

						const res = await photoTaken(params);
						uni.showToast({ title: res.msg, icon: 'none' });
						if (callback && typeof callback === 'function') {
							callback(); // 触发刷新
						}
					} catch (err : any) {
						uni.showToast({ title: err.msg || '操作失败', icon: 'none' });
					} finally {
						closePopup();
					}
				}
			});
		}

		/**
		 * 开始服务
		 */
		if (key === 'action_action_start') {
			const params = {
				order_id: data.order_id
			};

			startService(params).then((res) => {
				uni.showToast({ title: res.msg, icon: 'none' });
				if (callback && typeof callback === 'function') {
					callback();
				}
			}).catch((err) => {
				uni.showToast({ title: err.msg || '操作失败', icon: 'none' });
			});
		}

		/**
		 * 服务完成
		 */
		if (key === 'action_save_check') {
			// 存储图片列表的临时变量
			const imgList : string[] = [];
			// 显示拍照弹窗
			openPopup({
				visible: true,
				order: data,
				actionKey: key,
				onConfirm: async (popupData : any) => {
					try {
						if (data.item[0].is_finish_photograph) {
							if (!popupData || !popupData.imgList || popupData.imgList.length === 0) {
								uni.showToast({ title: '请至少上传一张照片', icon: 'none' });
								return;
							}
						}

						const params = {
							order_id: data.order_id,
							check_photos: popupData.imgList.toString()
						};
						const res = await saveCheck(params);
						uni.showToast({ title: res.msg, icon: 'none' });
						if (callback && typeof callback === 'function') {
							callback(); // 触发刷新
						}
					} catch (err : any) {
						uni.showToast({ title: err.msg || '操作失败', icon: 'none' });
					} finally {
						closePopup();
					}
				}
			});
		}

		/**
		 * 增值服务
		 */
		if (key == 'action_order_item') {
			uni.navigateTo({
				url: '/addon/home_service/technician/pages/order/additional?order_id=' + data.order_id
			})
		}
		/**
		 * 修改服务时间
		 */
		if (key === 'edit_reserve_service_time') {
			// 显示时间选择弹窗
			openPopup({
				visible: true,
				order: data,
				actionKey: key,
				onConfirm: async (popupData : any) => {
					console.log(popupData.reserve_service_time)
					if (!popupData || !popupData.reserve_service_time) {
						uni.showToast({ title: '请选择服务时间', icon: 'none' });
						return;
					}
					const date = new Date(popupData.reserve_service_time);
					// 获取年、月、日（月和日需补0）
					const year = date.getFullYear();
					const month = String(date.getMonth() + 1).padStart(2, '0'); // 月份从0开始，+1后补0
					const day = String(date.getDate()).padStart(2, '0');

					// 获取时、分、秒（均需补0）
					const hours = String(date.getHours()).padStart(2, '0');
					const minutes = String(date.getMinutes()).padStart(2, '0');
					const seconds = String(date.getSeconds()).padStart(2, '0');

					const params = {
						order_id: data.order_id,
						reserve_service_time: `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
					};
					const res = await editServiceTime(params);
					uni.showToast({ title: res.msg, icon: 'none' });
					if (callback && typeof callback === 'function') {
						callback(); // 触发刷新
					}
					console.log(3)
					closePopup();
				}
			});
		}
	}
}

export default OrderMethods;