<template>
    <div class="flex items-center justify-center w-5 h-5">
        <!-- AI作图 - 火花图标 -->
        <el-icon v-if="icon === 'sparkles'" class="w-5 h-5">
            <StarFilled />
        </el-icon>
        
        <!-- 信用卡/套餐图标 -->
        <el-icon v-else-if="icon === 'credit-card'" class="w-5 h-5">
            <CreditCard />
        </el-icon>
        
        <!-- 历史 - 时钟图标 -->
        <el-icon v-else-if="icon === 'history'" class="w-5 h-5">
            <Clock />
        </el-icon>
        
        <!-- 帮助 - 问号图标 -->
        <el-icon v-else-if="icon === 'help-circle'" class="w-5 h-5">
            <QuestionFilled />
        </el-icon>
        
        <!-- 视频图标 -->
        <el-icon v-else-if="icon === 'video'" class="w-5 h-5">
            <VideoPlay />
        </el-icon>
        
        <!-- 图片图标 -->
        <el-icon v-else-if="icon === 'image'" class="w-5 h-5">
            <Picture />
        </el-icon>
        
        <!-- 文档图标 -->
        <el-icon v-else-if="icon === 'document'" class="w-5 h-5">
            <Document />
        </el-icon>
        
        <!-- 默认图标 -->
        <el-icon v-else class="w-5 h-5">
            <Menu />
        </el-icon>
    </div>
</template>

<script lang="ts" setup>
import { 
    StarFilled, 
    CreditCard, 
    Clock, 
    QuestionFilled, 
    VideoPlay, 
    Picture, 
    Document, 
    Menu 
} from '@element-plus/icons-vue'

interface Props {
    icon: string
}

defineProps<Props>()
</script>
