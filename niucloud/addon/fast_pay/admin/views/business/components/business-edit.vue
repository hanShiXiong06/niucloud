<template>
  <el-dialog
    v-model="showDialog"
    :title="formData.id ? t('updateBusiness') : t('addBusiness')"
    width="50%"
    class="diy-dialog-wrap"
    :destroy-on-close="true"
  >
    <el-form
      :model="formData"
      label-width="120px"
      ref="formRef"
      :rules="formRules"
      class="page-form"
      v-loading="loading"
    >
      <el-form-item label="会员" prop="member_id">
        <el-select
          class="input-width"
          v-model="formData.member_id"
          clearable
          :placeholder="t('memberIdPlaceholder')"
        >
          <div class="mt-2 mb-2 ml-4">
            <el-input
              @change="change"
              v-model="keyword"
              style="width: 200px"
              placeholder="搜索会员支持昵称/会员名"
            >
              <template #append>搜索 </template></el-input
            >
          </div>
          <el-option label="请选择" value=""></el-option>
          <el-option
            v-for="(item, index) in memberIdList"
            :key="index"
            :label="item['nickname']"
            :value="item['member_id']"
          />
        </el-select>
      </el-form-item>

      <!-- <el-form-item :label="t('adminId')">
        <el-input
          v-model="formData.admin_id"
          clearable
          :placeholder="t('adminIdPlaceholder')"
          class="input-width"
        />
      </el-form-item> -->

      <el-form-item :label="t('name')" prop="name">
        <el-input
          v-model="formData.name"
          clearable
          :placeholder="t('namePlaceholder')"
          class="input-width"
        />
      </el-form-item>

      <el-form-item :label="t('banner')" prop="banner">
        <upload-image v-model="formData.banner" />
      </el-form-item>

      <el-form-item :label="t('desc')" prop="desc">
        <el-input
          v-model="formData.desc"
          clearable
          :placeholder="t('descPlaceholder')"
          class="input-width"
        />
      </el-form-item>
      <el-form-item :label="t('mchId')" prop="mch_id">
        <el-input
          v-model="formData.mch_id"
          clearable
          :placeholder="t('mchIdPlaceholder')"
          class="input-width"
        />
      </el-form-item>
      <!-- <el-form-item :label="t('address')">
        <el-input
          v-model="formData.address"
          clearable
          :placeholder="t('addressPlaceholder')"
          class="input-width"
        />
      </el-form-item> -->
      <!-- 
      <el-form-item :label="t('content')">
        <editor v-model="formData.content" />
      </el-form-item> -->
      <!-- <el-form-item :label="t('lat')">
        <el-input
          v-model="formData.lat"
          clearable
          :placeholder="t('latPlaceholder')"
          class="input-width"
        />
      </el-form-item>

      <el-form-item :label="t('lng')">
        <el-input
          v-model="formData.lng"
          clearable
          :placeholder="t('lngPlaceholder')"
          class="input-width"
        />
      </el-form-item> -->
      <el-form-item label="支付跳转" prop="type">
        <el-radio-group v-model="formData.type">
          <el-radio :label="'0'">视频号主页</el-radio>
          <el-radio :label="'1'">视频号视频</el-radio>
          <el-radio :label="'2'">HTTP链接</el-radio>
          <el-radio :label="'3'">小程序</el-radio>
        </el-radio-group>
        <div class="text-sm text-gray-400 select-text ml-2">
          支付成功后执行动作，仅小程序支持视频号相关跳转
        </div>
      </el-form-item>
      <el-form-item
        v-if="formData.type == 0 || formData.type == 1"
        label="视频号ID"
        prop="finderUserName"
      >
        <el-input
          v-model="formData.finderUserName"
          style="width: 200px"
          placeholder="请输入视频号ID"
        />
      </el-form-item>
      <el-form-item v-if="formData.type == 1" label="视频ID" prop="feedId">
        <el-input
          type="textarea"
          v-model="formData.feedId"
          style="width: 200px"
          placeholder="请输入视频ID"
        />
      </el-form-item>

      <el-form-item v-if="formData.type == 2" :label="t('page')">
        <el-input
          v-model="formData.page"
          clearable
          :placeholder="t('pagePlaceholder')"
          class="input-width"
        />
        <div class="text-sm text-gray-400 select-text ml-2">
          跳转链接“http”或“https”开头，例：https://baidu.com
        </div>
      </el-form-item>

      <el-form-item v-if="formData.type == 3" :label="t('miniAppid')">
        <el-input
          v-model="formData.mini_appid"
          clearable
          :placeholder="t('miniAppidPlaceholder')"
          class="input-width"
        />
      </el-form-item>

      <el-form-item v-if="formData.type == 3" :label="t('miniPage')">
        <el-input
          v-model="formData.mini_page"
          clearable
          :placeholder="t('miniPagePlaceholder')"
          class="input-width"
        />
      </el-form-item>

      <el-form-item :label="t('activeNum')" prop="active_num">
        <el-input
          type="number"
          v-model="formData.active_num"
          clearable
          :placeholder="t('activeNumPlaceholder')"
          class="input-width"
        />
      </el-form-item>

      <el-form-item :label="t('status')" prop="status">
        <el-select
          class="w-[280px]"
          v-model="formData.status"
          clearable
          :placeholder="t('statusPlaceholder')"
        >
          <el-option label="禁用" value="0"></el-option>
          <el-option label="正常" value="1"></el-option>
        </el-select>
      </el-form-item>

      <el-form-item :label="t('overTime')" class="input-width" prop="over_time">
        <el-date-picker
          class="flex-1 !flex"
          v-model="formData.over_time"
          clearable
          type="datetime"
          value-format="YYYY-MM-DD HH:mm:ss"
          :placeholder="t('overTimePlaceholder')"
        >
        </el-date-picker>
      </el-form-item>
    </el-form>

    <template #footer>
      <span class="dialog-footer">
        <el-button @click="showDialog = false">{{ t("cancel") }}</el-button>
        <el-button
          type="primary"
          :loading="loading"
          @click="confirm(formRef)"
          >{{ t("confirm") }}</el-button
        >
      </span>
    </template>
  </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch } from "vue";
import { useDictionary } from "@/app/api/dict";
import { t } from "@/lang";
import type { FormInstance } from "element-plus";
import {
  addBusiness,
  editBusiness,
  getBusinessInfo,
  getWithMemberList,
} from "@/addon/fast_pay/api/business";
import selectLink from "@/addon/fast_pay/views/business/select-link/index.vue";
let showDialog = ref(false);
const loading = ref(false);

/**
 * 表单数据
 */
const initialFormData = {
  id: "",
  member_id: "",
  admin_id: "",
  mch_id: "",
  name: "",
  banner: "",
  desc: "",
  address: "",
  content: "",
  lat: "",
  lng: "",
  type: "",
  finderUserName: "",
  feedId: "",
  page: "",
  mini_appid: "",
  mini_page: "",
  active_num: "",
  status: "",
  over_time: "",
};
const formData: Record<string, any> = reactive({ ...initialFormData });

const formRef = ref<FormInstance>();

// 表单验证规则
const formRules = computed(() => {
  return {
    member_id: [
      { required: true, message: t("memberIdPlaceholder"), trigger: "blur" },
    ],
    admin_id: [
      { required: true, message: t("adminIdPlaceholder"), trigger: "blur" },
    ],
    mch_id: [
      { required: true, message: t("mchIdPlaceholder"), trigger: "blur" },
    ],
    name: [{ required: true, message: t("namePlaceholder"), trigger: "blur" }],
    banner: [
      { required: true, message: t("bannerPlaceholder"), trigger: "blur" },
    ],
    desc: [{ required: true, message: t("descPlaceholder"), trigger: "blur" }],
    address: [
      { required: true, message: t("addressPlaceholder"), trigger: "blur" },
    ],
    content: [
      { required: true, message: t("contentPlaceholder"), trigger: "blur" },
    ],
    lat: [{ required: true, message: t("latPlaceholder"), trigger: "blur" }],
    lng: [{ required: true, message: t("lngPlaceholder"), trigger: "blur" }],
    type: [{ required: true, message: t("typePlaceholder"), trigger: "blur" }],
    finderUserName: [
      {
        required: true,
        message: t("finderUserNamePlaceholder"),
        trigger: "blur",
      },
    ],
    feedId: [
      { required: true, message: t("feedIdPlaceholder"), trigger: "blur" },
    ],
    page: [{ required: true, message: t("pagePlaceholder"), trigger: "blur" }],
    mini_appid: [
      { required: true, message: t("miniAppidPlaceholder"), trigger: "blur" },
    ],
    mini_page: [
      { required: true, message: t("miniPagePlaceholder"), trigger: "blur" },
    ],
    active_num: [
      { required: true, message: t("activeNumPlaceholder"), trigger: "blur" },
    ],
    status: [
      { required: true, message: t("statusPlaceholder"), trigger: "blur" },
    ],
    over_time: [
      { required: true, message: t("overTimePlaceholder"), trigger: "blur" },
    ],
  };
});

const emit = defineEmits(["complete"]);

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
  if (loading.value || !formEl) return;
  let save = formData.id ? editBusiness : addBusiness;

  await formEl.validate(async (valid) => {
    if (valid) {
      loading.value = true;

      let data = formData;

      save(data)
        .then((res) => {
          loading.value = false;
          showDialog.value = false;
          emit("complete");
        })
        .catch((err) => {
          loading.value = false;
        });
    }
  });
};

// 获取字典数据

const change = () => {
  setMemberIdList();
};
const keyword = ref();
const memberIdList = ref([]);
const setMemberIdList = async () => {
  memberIdList.value = await (
    await getWithMemberList({ keyword: keyword.value })
  ).data.data;
};
setMemberIdList();
const setFormData = async (row: any = null) => {
  Object.assign(formData, initialFormData);
  loading.value = true;
  if (row) {
    const data = await (await getBusinessInfo(row.id)).data;
    if (data)
      Object.keys(formData).forEach((key: string) => {
        if (data[key] != undefined) formData[key] = data[key];
      });
  }
  loading.value = false;
};

// 验证手机号格式
const mobileVerify = (rule: any, value: any, callback: any) => {
  if (value && !/^1[3-9]\d{9}$/.test(value)) {
    callback(new Error(t("generateMobile")));
  } else {
    callback();
  }
};

// 验证身份证号
const idCardVerify = (rule: any, value: any, callback: any) => {
  if (
    value &&
    !/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/.test(
      value
    )
  ) {
    callback(new Error(t("generateIdCard")));
  } else {
    callback();
  }
};

// 验证邮箱号
const emailVerify = (rule: any, value: any, callback: any) => {
  if (value && !/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/.test(value)) {
    callback(new Error(t("generateEmail")));
  } else {
    callback();
  }
};

// 验证请输入整数
const numberVerify = (rule: any, value: any, callback: any) => {
  if (!Number.isInteger(value)) {
    callback(new Error(t("generateNumber")));
  } else {
    callback();
  }
};

defineExpose({
  showDialog,
  setFormData,
});
</script>

<style lang="scss" scoped></style>
<style lang="scss">
.diy-dialog-wrap .el-form-item__label {
  height: auto !important;
}
</style>
