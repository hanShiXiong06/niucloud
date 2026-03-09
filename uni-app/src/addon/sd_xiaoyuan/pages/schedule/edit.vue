<template>
    <view class="edit-page">
        <view class="form-card">
            <u-form :model="form" ref="formRef" :rules="rules" label-width="80">
                <u-form-item label="课程名称" prop="name" required>
                    <u-input v-model="form.name" placeholder="请输入课程名称" />
                </u-form-item>
                <u-form-item label="教师" prop="teacher">
                    <u-input v-model="form.teacher" placeholder="请输入教师姓名" />
                </u-form-item>
                <u-form-item label="教室" prop="classroom">
                    <u-input v-model="form.classroom" placeholder="请输入教室" />
                </u-form-item>
                <u-form-item label="周数" prop="weeks" required>
                    <u-checkbox-group v-model="form.weeks" placement="row">
                        <u-checkbox v-for="week in weekOptions" :key="week" :name="week" :label="`${week}周`" />
                    </u-checkbox-group>
                </u-form-item>
            </u-form>
        </view>

        <view class="btn-group">
            <u-button type="error" @click="handleDelete">删除</u-button>
            <u-button type="primary" @click="handleSubmit">保存</u-button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getCourseDetail, editCourse, deleteCourse } from '../../api/xiaoyuan'

const formRef = ref()
const form = ref({
    id: 0,
    name: '',
    teacher: '',
    classroom: '',
    weeks: [] as number[]
})

const weekOptions = Array.from({ length: 20 }, (_, i) => i + 1)

const rules = {
    name: [{ required: true, message: '请输入课程名称' }],
    weeks: [{ required: true, message: '请选择周数' }]
}

onLoad((options: any) => {
    if (options?.id) {
        form.value.id = parseInt(options.id)
        loadDetail()
    }
})

const loadDetail = async () => {
    try {
        const res: any = await getCourseDetail({ id: form.value.id })
        if (res.code === 1) {
            Object.assign(form.value, res.data)
        }
    } catch (e) {
        uni.showToast({ title: '加载失败', icon: 'none' })
    }
}

const handleSubmit = async () => {
    try {
        await formRef.value.validate()
        const res: any = await editCourse(form.value)
        if (res.code === 1) {
            uni.showToast({ title: '保存成功', icon: 'success' })
            setTimeout(() => {
                uni.navigateBack()
            }, 1500)
        }
    } catch (e) {
        console.error(e)
    }
}

const handleDelete = () => {
    uni.showModal({
        title: '确认删除',
        content: '确定要删除这门课程吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const result: any = await deleteCourse({ id: form.value.id })
                    if (result.code === 1) {
                        uni.showToast({ title: '删除成功', icon: 'success' })
                        setTimeout(() => {
                            uni.navigateBack()
                        }, 1500)
                    }
                } catch (e) {
                    uni.showToast({ title: '删除失败', icon: 'none' })
                }
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.edit-page {
    padding: 20rpx;
    background: #f7f7f7;
    min-height: 100vh;
}

.form-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 30rpx;
}

.btn-group {
    display: flex;
    gap: 20rpx;
    margin-top: 40rpx;

    button {
        flex: 1;
    }
}
</style>
