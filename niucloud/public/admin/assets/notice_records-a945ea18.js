import{d as I,R as D,r as V,e as n,f as d,y as o,x as i,g as f,B as s,u as l,F as N,z as T,v as y,A as k,Q as O,H as E}from"./base-06478700.js";/* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-0d58768e.js";import"./el-tooltip-58212670.js";/* empty css                        *//* empty css                    *//* empty css                */import{a as K,E as M}from"./el-form-item-314d006d.js";/* empty css                  *//* empty css                       */import{t}from"./index-e5b4f072.js";import{c as F}from"./notice-7b311aa2.js";import{_ as A}from"./notice-records-info.vue_vue_type_script_setup_true_lang-d68267b1.js";import{u as G}from"./vue-router-d09a2c28.js";import{E as Q}from"./index-b68e8463.js";import{a as q,b as J,E as W}from"./index-35e821cc.js";import{E as X}from"./index-8d5b0ccb.js";import{E as Z}from"./index-c2f001d3.js";import{E as ee}from"./index-e10fccde.js";import{a as te,E as ae}from"./index-4bec4464.js";import{E as oe}from"./index-137757c0.js";import{v as le}from"./directive-cb2d3366.js";import"./common-92a35870.js";import"./index-2fcd1254.js";import"./event-10eba222.js";import"./index-adb89d14.js";import"./el-main-9a0960e7.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-6b67c4ac.js";import"./el-overlay-42a687c6.js";import"./index-9fe5de95.js";import"./focus-trap-3e826cdc.js";import"./index-f27d6ce0.js";import"./index-818c0ce2.js";import"./index-b52d0f2a.js";import"./index-2a269c7c.js";import"./index-e4abfaa5.js";import"./index-9ee9102c.js";import"./index-34d55b7e.js";import"./strings-fe930bc4.js";import"./isEqual-42d4b10f.js";import"./debounce-1db848fd.js";import"./index-5a0d60aa.js";import"./validator-6e9db238.js";import"./index-40e21e72.js";import"./index-992fe6cc.js";import"./_isIterateeCall-1dc0e2ff.js";const re={class:"main-container"},ie={class:"flex justify-between items-center"},ne={class:"text-[20px]"},se={class:"mt-[10px]"},me={key:0},pe={key:1},ce={key:2},de={class:"mt-[16px] flex justify-end"},_t=I({__name:"notice_records",setup(ue){const L=G().meta.title,e=D({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{key:"",receiver:"",create_time:[]}}),h=D({buyer:{label:t("buyerNotice"),list:[]},seller:{label:t("sellerNotice"),list:[]}});(async()=>{await F().then(m=>{Object.keys(m.data).forEach(a=>{const b=m.data[a],p={value:a,name:b.name};b.receiver_type==0?h.buyer.list.push(p):h.seller.list.push(p)})}).catch(()=>{})})();const w=V(),u=(m=1)=>{e.loading=!0,e.page=m,F({page:e.page,limit:e.limit,...e.searchParam}).then(a=>{e.loading=!1,e.data=a.data.data,e.total=a.data.total}).catch(()=>{e.loading=!1})};u();const g=V(null),z=m=>{g.value.setFormData(m),g.value.showDialog=!0};return(m,a)=>{const b=Q,p=K,x=q,B=J,R=W,U=X,v=Z,$=M,P=ee,c=te,j=ae,S=oe,Y=le;return n(),d("div",re,[o(P,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[f("div",ie,[f("span",ne,s(l(L)),1)]),o(P,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:i(()=>[o($,{inline:!0,model:e.searchParam,ref_key:"searchFormRef",ref:w},{default:i(()=>[o(p,{label:l(t)("searchReceiver"),prop:"receiver"},{default:i(()=>[o(b,{modelValue:e.searchParam.receiver,"onUpdate:modelValue":a[0]||(a[0]=r=>e.searchParam.receiver=r),placeholder:l(t)("receiverPlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(p,{label:l(t)("noticeKey"),prop:"key"},{default:i(()=>[o(R,{modelValue:e.searchParam.key,"onUpdate:modelValue":a[1]||(a[1]=r=>e.searchParam.key=r),clearable:"",placeholder:l(t)("groupIdPlaceholder"),class:"input-width"},{default:i(()=>[o(x,{label:l(t)("selectPlaceholder"),value:""},null,8,["label"]),(n(!0),d(N,null,T(h,(r,_)=>(n(),y(B,{key:_,label:r.label},{default:i(()=>[(n(!0),d(N,null,T(r.list,(C,H)=>(n(),y(x,{label:C.name,value:C.value,key:H},null,8,["label","value"]))),128))]),_:2},1032,["label"]))),128))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),o(p,{label:l(t)("createTime"),prop:"create_time"},{default:i(()=>[o(U,{modelValue:e.searchParam.create_time,"onUpdate:modelValue":a[2]||(a[2]=r=>e.searchParam.create_time=r),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":l(t)("startDate"),"end-placeholder":l(t)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),o(p,null,{default:i(()=>[o(v,{type:"primary",onClick:a[3]||(a[3]=r=>u())},{default:i(()=>[k(s(l(t)("search")),1)]),_:1}),o(v,{onClick:a[4]||(a[4]=r=>{var _;return(_=w.value)==null?void 0:_.resetFields()})},{default:i(()=>[k(s(l(t)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),f("div",se,[O((n(),y(j,{data:e.data,size:"large"},{empty:i(()=>[f("span",null,s(e.loading?"":l(t)("emptyData")),1)]),default:i(()=>[o(c,{prop:"name",label:l(t)("noticeKey"),"min-width":"120"},null,8,["label"]),o(c,{prop:"notice_type",label:l(t)("noticeType"),"min-width":"120"},{default:i(({row:r})=>[r.notice_type=="sms"?(n(),d("div",me,s(l(t)("sms")),1)):E("",!0),r.notice_type=="wechat"?(n(),d("div",pe,s(l(t)("wechat")),1)):E("",!0),r.notice_type=="weapp"?(n(),d("div",ce,s(l(t)("weapp")),1)):E("",!0)]),_:1},8,["label"]),o(c,{prop:"nickname",label:l(t)("nickname"),"min-width":"120"},null,8,["label"]),o(c,{prop:"receiver",label:l(t)("receiver"),"min-width":"120"},null,8,["label"]),o(c,{prop:"create_time",label:l(t)("createTime"),"min-width":"140"},null,8,["label"]),o(c,{label:l(t)("operation"),fixed:"right",align:"right",width:"100"},{default:i(({row:r})=>[o(v,{type:"primary",link:"",onClick:_=>z(r)},{default:i(()=>[k(s(l(t)("info")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[Y,e.loading]]),f("div",de,[o(S,{"current-page":e.page,"onUpdate:currentPage":a[5]||(a[5]=r=>e.page=r),"page-size":e.limit,"onUpdate:pageSize":a[6]||(a[6]=r=>e.limit=r),layout:"total, sizes, prev, pager, next, jumper",total:e.total,onSizeChange:a[7]||(a[7]=r=>u()),onCurrentChange:u},null,8,["current-page","page-size","total"])])]),o(A,{ref_key:"recordsDialog",ref:g,onComplete:u},null,512)]),_:1})])}}});export{_t as default};
