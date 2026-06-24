<template>
    <div class="edit-xiaoyuan-user-menu" v-show="diyStore.editTab == 'content'">
        <el-form label-width="90px" class="px-2">
            <el-form-item label="分组标题">
                <el-input v-model="diyStore.editComponent.title" placeholder="如：校园服务" @change="onTitleChange" />
                <div class="text-xs text-gray-400 mt-1">每个「个人中心菜单」组件为一组，可拖多个实现多分组</div>
            </el-form-item>
            <el-form-item label="每行个数">
                <el-radio-group v-model="diyStore.editComponent.column">
                    <el-radio :label="4">4个</el-radio>
                    <el-radio :label="5">5个</el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>
        <h3 class="mb-2 px-2">菜单项</h3>
        <div class="menu-list">
            <div class="menu-item" v-for="(item, idx) in diyStore.editComponent.list" :key="idx">
                <el-form label-width="80px" class="px-2">
                    <el-form-item label="名称">
                        <el-input v-model="item.name" size="small" />
                    </el-form-item>
                    <el-form-item label="图标">
                        <el-input v-model="item.icon" size="small" placeholder="u-icon名称" />
                    </el-form-item>
                    <el-form-item label="图标色">
                        <el-color-picker v-model="item.iconColor" size="small" />
                    </el-form-item>
                    <el-form-item label="背景">
                        <el-input v-model="item.bgColor" size="small" placeholder="渐变色或纯色" />
                    </el-form-item>
                    <el-form-item label="功能开关">
                        <el-input v-model="item.featureKey" size="small" placeholder="如 enable_sign、open_fenxiao，留空=始终显示" />
                    </el-form-item>
                    <el-form-item label="接单员位">
                        <el-switch v-model="item.runnerSlot" size="small" />
                        <span class="text-xs text-gray-400 ml-1">开启后按接单员状态切换名称链接</span>
                    </el-form-item>
                    <el-form-item label="链接">
                        <diy-link v-model="item.link" />
                    </el-form-item>
                    <el-form-item label="显示">
                        <el-switch v-model="item.isShow" size="small" />
                    </el-form-item>
                </el-form>
                <el-button type="danger" link @click="removeItem(idx)">删除</el-button>
                <el-divider />
            </div>
        </div>
        <el-button type="primary" plain class="m-2" @click="addItem">添加菜单</el-button>
    </div>
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <slot name="style"></slot>
    </div>
</template>

<script setup lang="ts">
import useDiyStore from '@/stores/modules/diy'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = []

const addItem = () => {
    if (!diyStore.editComponent.list) diyStore.editComponent.list = []
    diyStore.editComponent.list.push({
        name: '新菜单',
        icon: 'grid',
        iconColor: '#fff',
        bgColor: 'linear-gradient(135deg, #c0fe95, #a8e063)',
        featureKey: '',
        runnerSlot: false,
        url: '',
        link: { name: '', title: '', url: '', parent: 'SD_XIAOYUAN_LINK' },
        isShow: true
    })
}

const removeItem = (idx: number) => {
    diyStore.editComponent.list.splice(idx, 1)
}

const onTitleChange = (val: string) => {
    if (val) {
        diyStore.editComponent.componentTitle = val
    }
}
</script>

<style scoped>
.menu-list {
    max-height: 420px;
    overflow-y: auto;
}
.menu-item {
    background: #f9f9f9;
    padding: 8px;
    margin: 0 8px 8px;
    border-radius: 6px;
}
</style>
