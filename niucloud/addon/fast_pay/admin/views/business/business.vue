<template>
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <div class="flex justify-between items-center">
        <span class="text-lg">{{ pageName }}</span>
        <el-button type="primary" @click="addEvent">
          {{ t("addBusiness") }}
        </el-button>
      </div>

      <el-card
        class="box-card !border-none my-[10px] table-search-wrap"
        shadow="never"
      >
        <el-form
          :inline="true"
          :model="businessTable.searchParam"
          ref="searchFormRef"
        >
          <el-form-item label="会员" prop="member_id">
            <el-select
              class="input-width"
              v-model="businessTable.searchParam.member_id"
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
          <el-form-item :label="t('mchId')" prop="mch_id">
            <el-input
              v-model="businessTable.searchParam.mch_id"
              :placeholder="t('mchIdPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('name')" prop="name">
            <el-input
              v-model="businessTable.searchParam.name"
              :placeholder="t('namePlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('status')" prop="status">
            <el-input
              v-model="businessTable.searchParam.status"
              :placeholder="t('statusPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('overTime')" prop="over_time">
            <el-date-picker
              v-model="businessTable.searchParam.over_time"
              type="datetimerange"
              format="YYYY-MM-DD hh:mm:ss"
              :start-placeholder="t('startDate')"
              :end-placeholder="t('endDate')"
            />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadBusinessList()">{{
              t("search")
            }}</el-button>
            <el-button @click="resetForm(searchFormRef)">{{
              t("reset")
            }}</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <div class="mt-[10px]">
        <el-table
          :data="businessTable.data"
          size="large"
          v-loading="businessTable.loading"
        >
          <template #empty>
            <span>{{ !businessTable.loading ? t("emptyData") : "" }}</span>
          </template>
          <el-table-column
            prop="member_id_name"
            :label="t('memberId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="admin_id"
            :label="t('adminId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="mch_id"
            :label="t('mchId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="name"
            :label="t('name')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column :label="t('banner')" width="100" align="left">
            <template #default="{ row }">
              <el-avatar v-if="row.banner" :src="img(row.banner)" />
              <el-avatar v-else icon="UserFilled" />
            </template>
          </el-table-column>
          <el-table-column
            prop="desc"
            :label="t('desc')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="address"
            :label="t('address')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="content"
            :label="t('content')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="lat"
            :label="t('lat')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="lng"
            :label="t('lng')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="type"
            :label="t('type')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="active_num"
            :label="t('activeNum')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="status"
            :label="t('status')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="over_time"
            :label="t('overTime')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            :label="t('operation')"
            fixed="right"
            min-width="120"
          >
            <template #default="{ row }">
              <el-button type="primary" link @click="editEvent(row)">{{
                t("edit")
              }}</el-button>
              <el-button type="primary" link @click="deleteEvent(row.id)">{{
                t("delete")
              }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end">
          <el-pagination
            v-model:current-page="businessTable.page"
            v-model:page-size="businessTable.limit"
            layout="total, sizes, prev, pager, next, jumper"
            :total="businessTable.total"
            @size-change="loadBusinessList()"
            @current-change="loadBusinessList"
          />
        </div>
      </div>

      <edit ref="editBusinessDialog" @complete="loadBusinessList" />
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from "vue";
import { t } from "@/lang";
import { useDictionary } from "@/app/api/dict";
import {
  getBusinessList,
  deleteBusiness,
  getWithMemberList,
} from "@/addon/fast_pay/api/business";
import { img } from "@/utils/common";
import { ElMessageBox, FormInstance } from "element-plus";
import Edit from "@/addon/fast_pay/views/business/components/business-edit.vue";
import { useRoute } from "vue-router";
const route = useRoute();
const pageName = route.meta.title;

let businessTable = reactive({
  page: 1,
  limit: 10,
  total: 0,
  loading: true,
  data: [],
  searchParam: {
    member_id: "",
    mch_id: "",
    name: "",
    status: "",
    over_time: [],
  },
});

const searchFormRef = ref<FormInstance>();

// 选中数据
const selectData = ref<any[]>([]);

// 字典数据

/**
 * 获取商户列列表
 */
const loadBusinessList = (page: number = 1) => {
  businessTable.loading = true;
  businessTable.page = page;

  getBusinessList({
    page: businessTable.page,
    limit: businessTable.limit,
    ...businessTable.searchParam,
  })
    .then((res) => {
      businessTable.loading = false;
      businessTable.data = res.data.data;
      businessTable.total = res.data.total;
    })
    .catch(() => {
      businessTable.loading = false;
    });
};
loadBusinessList();

const editBusinessDialog: Record<string, any> | null = ref(null);

/**
 * 添加商户列
 */
const addEvent = () => {
  editBusinessDialog.value.setFormData();
  editBusinessDialog.value.showDialog = true;
};

/**
 * 编辑商户列
 * @param data
 */
const editEvent = (data: any) => {
  editBusinessDialog.value.setFormData(data);
  editBusinessDialog.value.showDialog = true;
};

/**
 * 删除商户列
 */
const deleteEvent = (id: number) => {
  ElMessageBox.confirm(t("businessDeleteTips"), t("warning"), {
    confirmButtonText: t("confirm"),
    cancelButtonText: t("cancel"),
    type: "warning",
  }).then(() => {
    deleteBusiness(id)
      .then(() => {
        loadBusinessList();
      })
      .catch(() => {});
  });
};

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

const resetForm = (formEl: FormInstance | undefined) => {
  if (!formEl) return;
  formEl.resetFields();
  loadBusinessList();
};
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
  word-break: break-all;
  text-overflow: ellipsis;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
</style>
