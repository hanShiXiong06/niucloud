
/**
 * 获取权限
 * @param {string} params.permission  对应权限的scope，例如 scope.bluetooth
 * @param {String} params.permissionName  提示框显示的权限名称，例如 蓝牙
 * @param {String} params.customPopup  将实现自定义的弹窗代替系统弹窗
 * @param {Function} params.success  授权成功回调函数
 * @param {Function} params.fail  授权失败回调函数
 * @param {Function} params.cancel  取消继续授权回调函数
 */
export const getPermission = (params: any) => {
    const _permission = `scope.${params.permission}`
	const _tipsContent = `您拒绝了${params.permissionName}权限，将导致部分功能不能正常使用，去设置权限？`

	uni.getSetting({
		success(res) {
			// 判断是否有相关权限属性
			if (res.authSetting.hasOwnProperty(_permission)) {
				// 属性存在，且为false
				if (!res.authSetting[_permission]) {
					uni.showModal({
						title: '提示',
						content: _tipsContent,
						success: (res)=> {
							if(res.confirm) {
								uni.openSetting()
							}else {
								if(params.cancel) {
									setTimeout(()=> {
										params.cancel()
									}, 200)
								}
							}
						}
					})
				}else {
					params.success && params.success()
				}
			} else {
				// 属性不存在，需要授权
				uni.authorize({
					scope: _permission,
					success() {
						// 授权成功
						params.success && params.success()
					},
					fail() {
						uni.showModal({
							title: '提示',
							content: _tipsContent,
							success: (res)=> {
								if(res.confirm) {
									uni.openSetting()
								}else {
									if(params.cancel) {
										setTimeout(()=> {
											params.cancel()
										}, 200)
									}
								}
							}
						})
					}
				})
			}
		}
	})
}