/* empty css             *//* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-aae906bf.js";/* empty css                   *//* empty css                 *//* empty css                    *//* empty css                        *//* empty css                *//* empty css                     *//* empty css                  */import{t as o}from"./index-5f4ce139.js";import{c as $,d as z,e as F}from"./tools-40d591ad.js";import{i as N}from"./common-465e36b3.js";import{_ as L}from"./add-table.vue_vue_type_script_setup_true_lang-f85eef82.js";import{a as U}from"./vue-router-b5675730.js";import{E as G}from"./index-548a7823.js";import{E as R}from"./index-4862d1b3.js";import{E as S}from"./index-95693143.js";import{a as j,E as I}from"./index-624573cc.js";import{E as M}from"./index-acd12562.js";import{a as q,E as A}from"./index-a9458a49.js";import{E as H}from"./index-800b62de.js";import{v as J}from"./directive-a07a10ed.js";import{d as K,M as O,r as C,b as w,e as Q,q as a,p as n,f as _,v as p,x as i,u as e,L as W,m as X}from"./runtime-core.esm-bundler-7c3fd514.js";import"./el-overlay-f7f710bd.js";import"./plugin-vue_export-helper-edbdb6f8.js";import"./index-f02197a7.js";import"./index-868cd458.js";import"./index-a3cf5375.js";import"./event-9519ab40.js";import"./focus-trap-bb1e8c7a.js";import"./index-7b0897f9.js";import"./error-492b6a5b.js";import"./el-switch-3d36d31d.js";import"./index-cf47f151.js";import"./index-2083be2e.js";import"./index-47617222.js";import"./validator-62f68fe3.js";import"./el-radio-c9a1047c.js";import"./index-2f0b1bf3.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-avatar-7d17482e.js";import"./index-be5dc120.js";import"./common-cc37bda4.js";import"./common-2cf17469.js";/* empty css                  */import"./index-4683bff4.js";import"./index-c656f08b.js";import"./_baseClone-cf40e5b2.js";import"./_Uint8Array-de4f83bb.js";import"./_initCloneObject-bc5ed9bb.js";import"./index-470ade69.js";import"./isEqual-f40f939e.js";import"./flatten-b3585bb8.js";import"./_isIterateeCall-7a6fae02.js";import"./index-9fbce820.js";import"./index-2804b007.js";const Y={class:"main-container"},Z={class:"flex"},ee={class:"mt-[16px]"},te={class:"mt-[16px] flex justify-end"},st=K({__name:"index",setup(oe){const y=U();let t=O({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{table_name:"",table_content:""}});const g=C(),d=(m=1)=>{t.loading=!0,t.page=m,$({page:t.page,limit:t.limit,...t.searchParam}).then(l=>{t.loading=!1,t.data=l.data.data,t.total=l.data.total}).catch(()=>{t.loading=!1})};d();const f=C(null),E=()=>{f.value.setFormData(),f.value.showDialog=!0},k=m=>{G.confirm(o("codeDeleteTips"),o("warning"),{confirmButtonText:o("confirm"),cancelButtonText:o("cancel"),type:"warning"}).then(()=>{z(m).then(()=>{d()}).catch(()=>{})})},x=m=>{y.push("/tools/code/edit?id="+m.id)},T=m=>{F({id:m}).then(l=>{window.open(N(l.data.file),"_blank")}).catch(()=>{})};return(m,l)=>{const s=R,h=S,b=j,P=I,v=M,c=q,D=A,V=H,B=J;return w(),Q("div",Y,[a(v,{class:"box-card !border-none",shadow:"never"},{default:n(()=>[_("div",Z,[a(s,{type:"primary",onClick:E},{default:n(()=>[p(" /> "+i(e(o)("addCode")),1)]),_:1})]),a(v,{class:"box-card !border-none my-[16px] table-search-wrap",shadow:"never"},{default:n(()=>[a(P,{inline:!0,model:e(t).searchParam,ref_key:"searchFormRef",ref:g},{default:n(()=>[a(b,{label:e(o)("tableName"),prop:"table_name"},{default:n(()=>[a(h,{modelValue:e(t).searchParam.table_name,"onUpdate:modelValue":l[0]||(l[0]=r=>e(t).searchParam.table_name=r),placeholder:e(o)("tableNamePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(b,{label:e(o)("tableContent"),prop:"table_content"},{default:n(()=>[a(h,{modelValue:e(t).searchParam.table_content,"onUpdate:modelValue":l[1]||(l[1]=r=>e(t).searchParam.table_content=r),placeholder:e(o)("tableContentPlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(b,null,{default:n(()=>[a(s,{type:"primary",onClick:l[2]||(l[2]=r=>d())},{default:n(()=>[p(i(e(o)("search")),1)]),_:1}),a(s,{onClick:l[3]||(l[3]=r=>{var u;return(u=g.value)==null?void 0:u.resetFields()})},{default:n(()=>[p(i(e(o)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),_("div",ee,[W((w(),X(D,{data:e(t).data,size:"large"},{empty:n(()=>[_("span",null,i(e(t).loading?"":e(o)("emptyData")),1)]),default:n(()=>[a(c,{prop:"table_name","show-overflow-tooltip":!0,label:e(o)("tableName"),"min-width":"120"},null,8,["label"]),a(c,{prop:"table_content","show-overflow-tooltip":!0,label:e(o)("tableContent"),"min-width":"120"},null,8,["label"]),a(c,{prop:"edit_type",label:e(o)("editType"),"min-width":"150",align:"center"},{default:n(({row:r})=>[p(i(r.edit_type==1?e(o)("popup"):e(o)("page")),1)]),_:1},8,["label"]),a(c,{label:e(o)("createTime"),"min-width":"180",align:"center"},{default:n(({row:r})=>[p(i(r.create_time||""),1)]),_:1},8,["label"]),a(c,{label:e(o)("operation"),fixed:"right",width:"180","show-overflow-tooltip":!0},{default:n(({row:r})=>[a(s,{type:"primary",link:"",onClick:u=>x(r)},{default:n(()=>[p(i(e(o)("edit")),1)]),_:2},1032,["onClick"]),a(s,{type:"primary",link:"",onClick:u=>T(r.id)},{default:n(()=>[p(i(e(o)("download")),1)]),_:2},1032,["onClick"]),a(s,{type:"danger",link:"",onClick:u=>k(r.id)},{default:n(()=>[p(i(e(o)("delete")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[B,e(t).loading]]),_("div",te,[a(V,{"current-page":e(t).page,"onUpdate:currentPage":l[4]||(l[4]=r=>e(t).page=r),"page-size":e(t).limit,"onUpdate:pageSize":l[5]||(l[5]=r=>e(t).limit=r),layout:"total, sizes, prev, pager, next, jumper",total:e(t).total,onSizeChange:l[6]||(l[6]=r=>d()),onCurrentChange:d},null,8,["current-page","page-size","total"])])]),a(L,{ref_key:"addCodeDialog",ref:f,onComplete:d},null,512)]),_:1})])}}});export{st as default};
