import"./base-0e92f4db.js";/* empty css                   *//* empty css                *//* empty css                        *//* empty css                    *//* empty css               */import"./el-tooltip-4ed993c7.js";/* empty css                  */import{ag as getStorageList}from"./index-fac59425.js";/* empty css                  */import{t}from"./index-2d1c7ba3.js";import{_ as _sfc_main$1}from"./storage-local.vue_vue_type_script_setup_true_lang-de5a1209.js";import{_ as _sfc_main$2}from"./storage-qiniu.vue_vue_type_script_setup_true_lang-f407829e.js";import{_ as _sfc_main$3}from"./storage-ali.vue_vue_type_script_setup_true_lang-c5995b0c.js";import{_ as _sfc_main$4}from"./storage-tencent.vue_vue_type_script_setup_true_lang-3d4f26c1.js";import{u as useRoute}from"./vue-router-8b032575.js";import{a as ElTableColumn,E as ElTable}from"./index-395859da.js";import{E as ElTag}from"./index-66750d66.js";import{E as ElButton}from"./index-e09a20f5.js";import{E as ElCard}from"./index-2668a8ea.js";import{v as vLoading}from"./directive-c6f70b8e.js";import{d as defineComponent,r as ref,M as reactive,b as openBlock,e as createElementBlock,q as createVNode,p as withCtx,f as createBaseVNode,x as toDisplayString,u as unref,L as withDirectives,m as createBlock,v as createTextVNode,C as createCommentVNode}from"./runtime-core.esm-bundler-67034826.js";import"./common-46715e7e.js";import"./index-72686045.js";import"./event-a537c4cb.js";import"./index-81f2aa1e.js";import"./el-main-7a89c415.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-ebd2990f.js";import"./el-overlay-3eff2fc5.js";import"./index-defed8ff.js";import"./focus-trap-83769a43.js";import"./index-6cae7119.js";import"./index-d87ae4a2.js";import"./el-form-item-c2dd2ffe.js";/* empty css                 */import"./index-e9d9b1a1.js";import"./index-8cefa3ab.js";import"./index-ef31373f.js";import"./index-de22cd40.js";/* empty css                 */import"./index-9aa10ae4.js";import"./index-fd563016.js";import"./isEqual-97c7f2d5.js";import"./_isIterateeCall-7d0e706f.js";import"./debounce-f6ba9d12.js";import"./index-c6aa1547.js";const _hoisted_1={class:"main-container"},_hoisted_2={class:"flex justify-between items-center"},_hoisted_3={class:"text-[20px]"},_hoisted_4={class:"mt-[16px]"},_sfc_main=defineComponent({__name:"storage",setup(__props){const route=useRoute(),pageName=route.meta.title,localDialog=ref(null),qiniuDialog=ref(null),aliyunDialog=ref(null),tencentDialog=ref(null);let storageTableData=reactive({data:[]});const loading=ref(!0),loadStorageList=()=>{loading.value=!0,getStorageList().then(r=>{storageTableData.data=r.data,loading.value=!1}).catch(()=>{loading.value=!1})};loadStorageList();const editEvent=data=>{eval(data.storage_type+"Dialog.value.setFormData(data)"),eval(data.storage_type+"Dialog.value.showDialog = true;")};return(r,e)=>{const a=ElTableColumn,i=ElTag,l=ElButton,n=ElTable,s=ElCard,m=vLoading;return openBlock(),createElementBlock("div",_hoisted_1,[createVNode(s,{class:"box-card !border-none",shadow:"never"},{default:withCtx(()=>[createBaseVNode("div",_hoisted_2,[createBaseVNode("span",_hoisted_3,toDisplayString(unref(pageName)),1)]),createBaseVNode("div",_hoisted_4,[withDirectives((openBlock(),createBlock(n,{data:unref(storageTableData).data,size:"large"},{empty:withCtx(()=>[createBaseVNode("span",null,toDisplayString(loading.value?"":unref(t)("emptyData")),1)]),default:withCtx(()=>[createVNode(a,{prop:"name",label:unref(t)("name"),"min-width":"100","show-overflow-tooltip":!0},null,8,["label"]),createVNode(a,{label:unref(t)("isUse"),"min-width":"180",align:"center"},{default:withCtx(({row:o})=>[o.is_use==1?(openBlock(),createBlock(i,{key:0,class:"ml-2",type:"success"},{default:withCtx(()=>[createTextVNode(toDisplayString(unref(t)("statusNormal")),1)]),_:1})):createCommentVNode("",!0),o.is_use==0?(openBlock(),createBlock(i,{key:1,class:"ml-2",type:"error"},{default:withCtx(()=>[createTextVNode(toDisplayString(unref(t)("statusDeactivate")),1)]),_:1})):createCommentVNode("",!0)]),_:1},8,["label"]),createVNode(a,{label:unref(t)("operation"),fixed:"right",align:"right",width:"100"},{default:withCtx(({row:o})=>[createVNode(l,{type:"primary",link:"",onClick:c=>editEvent(o)},{default:withCtx(()=>[createTextVNode(toDisplayString(unref(t)("config")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[m,loading.value]])]),createVNode(_sfc_main$1,{ref_key:"localDialog",ref:localDialog,onComplete:e[0]||(e[0]=o=>loadStorageList())},null,512),createVNode(_sfc_main$2,{ref_key:"qiniuDialog",ref:qiniuDialog,onComplete:e[1]||(e[1]=o=>loadStorageList())},null,512),createVNode(_sfc_main$3,{ref_key:"aliyunDialog",ref:aliyunDialog,onComplete:e[2]||(e[2]=o=>loadStorageList())},null,512),createVNode(_sfc_main$4,{ref_key:"tencentDialog",ref:tencentDialog,onComplete:e[3]||(e[3]=o=>loadStorageList())},null,512)]),_:1})])}}});export{_sfc_main as default};
