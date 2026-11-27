<template>
    <div class="content-wrap" v-show="diyStore.editTab === 'content'">
        <slot name="field"></slot>

        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">定位规则</h3>
            <el-form label-width="108px" class="px-[10px]" @submit.prevent>
                <el-form-item label="采集方式">
                    <el-radio-group v-model="diyStore.editComponent.mode" class="location-mode">
                        <el-radio label="both">当前位置 + 地图选择</el-radio>
                        <el-radio label="current_only">仅获取当前位置</el-radio>
                        <el-radio label="map_only">仅地图选择</el-radio>
                    </el-radio-group>
                    <div class="setting-tip">“当前位置”适合门店现场采集；“地图选择”用于定位失败或需要人工校正。</div>
                </el-form-item>

                <el-form-item label="必须解析地址">
                    <el-switch v-model="diyStore.editComponent.requireAddress" />
                    <div class="setting-tip">开启后，只有经纬度但未解析出地址时不能提交。</div>
                </el-form-item>

                <el-form-item label="定位精度">
                    <el-input-number
                        v-model="diyStore.editComponent.maxAccuracyMeters"
                        :min="0"
                        :max="5000"
                        :step="10"
                        controls-position="right"
                    />
                    <span class="ml-[8px] text-gray-500">米</span>
                    <div class="setting-tip">0 表示不限制。只校验设备实时定位，地图人工选点不校验精度。</div>
                </el-form-item>

                <el-form-item label="定位有效期">
                    <el-input-number
                        v-model="diyStore.editComponent.maxAgeMinutes"
                        :min="0"
                        :max="10080"
                        :step="5"
                        controls-position="right"
                    />
                    <span class="ml-[8px] text-gray-500">分钟</span>
                    <div class="setting-tip">0 表示不限制；设置后，超时定位需重新采集。</div>
                </el-form-item>

                <el-form-item label="展示经纬度">
                    <el-switch v-model="diyStore.editComponent.showCoordinates" />
                </el-form-item>
            </el-form>
        </div>

        <slot name="other"></slot>

        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">隐私设置</h3>
            <el-form label-width="108px" class="px-[10px]">
                <el-form-item label="隐私保护">
                    <el-switch v-model="diyStore.editComponent.field.privacyProtection" />
                    <div class="setting-tip">定位属于敏感信息，开启后按万能表单隐私规则展示。</div>
                </el-form-item>
            </el-form>
        </div>
    </div>

    <div class="style-wrap" v-show="diyStore.editTab === 'style'">
        <slot name="style-field"></slot>
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import useDiyStore from '@/stores/modules/diy'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = ['componentBgUrl']

const allowedModes = ['both', 'current_only', 'map_only']

diyStore.editComponent.mode = allowedModes.includes(diyStore.editComponent.mode)
    ? diyStore.editComponent.mode
    : 'both'
diyStore.editComponent.requireAddress = diyStore.editComponent.requireAddress !== false
diyStore.editComponent.maxAccuracyMeters = Number(diyStore.editComponent.maxAccuracyMeters || 0)
diyStore.editComponent.maxAgeMinutes = Number(diyStore.editComponent.maxAgeMinutes || 0)
diyStore.editComponent.showCoordinates = diyStore.editComponent.showCoordinates !== false

diyStore.editComponent.verify = (index: number) => {
    const result = { code: true, message: '' }
    const component = diyStore.value[index]

    if (!component || component.componentName !== 'ProjectFormLocation') return result

    if (!allowedModes.includes(component.mode)) {
        result.code = false
        result.message = '请选择定位采集方式'
        return result
    }

    const maxAccuracyMeters = Number(component.maxAccuracyMeters || 0)
    if (maxAccuracyMeters < 0 || maxAccuracyMeters > 5000) {
        result.code = false
        result.message = '定位精度限制应在 0 至 5000 米之间'
        return result
    }

    const maxAgeMinutes = Number(component.maxAgeMinutes || 0)
    if (maxAgeMinutes < 0 || maxAgeMinutes > 10080) {
        result.code = false
        result.message = '定位有效期应在 0 至 10080 分钟之间'
    }

    return result
}

defineExpose({})
</script>

<style lang="scss" scoped>
.location-mode {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.setting-tip {
    width: 100%;
    margin-top: 6px;
    color: #98a2b3;
    font-size: 12px;
    line-height: 19px;
}
</style>
