import{E as O}from"./base-962c0c23.js";/* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-7525671c.js";import"./el-tooltip-58212670.js";/* empty css                 *//* empty css                    *//* empty css                        *//* empty css                 *//* empty css                *//* empty css                *//* empty css                     *//* empty css                       *//* empty css                  */import{_ as Q}from"./site_logo-f276251b.js";import{d as J}from"./storage-abe718b1.js";import{t as l}from"./index-105fbad0.js";import{j as K,q as X,r as Z,s as ee,t as te}from"./site-fd0c6646.js";import{u as ae,a as le}from"./vue-router-79053937.js";import{_ as oe}from"./edit-site.vue_vue_type_script_setup_true_lang-0aa58e1b.js";/* empty css                   */import{a as se}from"./index-d57cc47d.js";import{E as re}from"./index-bba9e58c.js";import{E as ie}from"./index-93f2c618.js";import{a as ne,E as pe}from"./index-61c777fa.js";import{a as me,E as de}from"./index-b933df38.js";import{E as ue}from"./index-43ea62d5.js";import{E as ce}from"./index-69523418.js";import{E as _e}from"./index-d1bcad42.js";import{a as fe,E as ge}from"./index-92e1b5d5.js";import{E as he}from"./index-4f5c40a5.js";import{E as ve}from"./index-100b1469.js";import{v as be}from"./directive-c0c3e9a3.js";import{d as xe,r as x,M as ye,Q as ke,b as n,e as f,q as t,p as r,f as m,x as i,u as a,v as d,F as L,t as T,m as g,L as Ee,C as we}from"./runtime-core.esm-bundler-dc7a07d7.js";import{_ as Ce}from"./_plugin-vue_export-helper-c27b6911.js";import"./el-overlay-60700377.js";import"./event-ff03ec12.js";import"./index-5d86eb33.js";import"./focus-trap-b8b5a003.js";import"./el-radio-bfd4b1ad.js";import"./index-8bcaafa6.js";import"./index-7a123a20.js";import"./el-avatar-3bb47ce2.js";import"./common-080b9b9f.js";import"./common-2cf17469.js";import"./_Uint8Array-6ff3cafa.js";import"./_initCloneObject-28e6bdaa.js";import"./strings-4868a118.js";import"./isEqual-c7d5e6d9.js";import"./flatten-d5d1dc97.js";import"./index-179d7e6f.js";import"./index-df51d91a.js";import"./_isIterateeCall-c579b126.js";const Pe={class:"main-container"},Ve={class:"flex justify-between items-center"},De={class:"text-[24px]"},Se={class:"flex items-center"},Le={class:"text-base"},Te={class:"mt-[20px]"},Ie={class:"flex items-center"},Fe=["src"],Ue={key:1,class:"w-[50px] h-[50px] mr-[10px]",src:Q,alt:""},Ye={class:"flex flex flex-col"},$e={key:0},ze={key:1},Me={class:"mt-[16px] flex justify-end"},Ne=xe({__name:"list",setup(Be){const I=ae().meta.title,E=location.origin+"/site/login",w=x([]),C=x([]),o=ye({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{keywords:"",group_id:"",status:"",create_time:[],expire_time:[]}});(async()=>{w.value=await(await K({})).data})(),(async()=>{C.value=await(await X()).data})();const P=x(),F=p=>{p&&(p.resetFields(),u())},u=(p=1)=>{o.loading=!0,o.page=p,Z({page:o.page,limit:o.limit,...o.searchParam}).then(s=>{o.loading=!1,o.data=s.data.data,o.total=s.data.total}).catch(()=>{o.loading=!1})};u();const U=le(),y=x(null),Y=p=>{y.value.setFormData(),y.value.showDialog=!0},$=p=>{U.push("/admin/site/info?id="+p.site_id)},z=p=>{se({message:l("siteUrlDevelopMessage"),grouping:!0,type:"success"})},M=()=>{window.open(E)},N=(p,s)=>{p==1&&ee({site_id:s}).then(c=>{u()}),p==3&&te({site_id:s}).then(c=>{u()})};return(p,s)=>{const c=re,B=ie,h=ne,v=me,V=de,D=ue,j=pe,S=ce,H=ke("Warning"),R=O,G=_e,_=fe,k=he,q=ge,A=ve,W=be;return n(),f("div",Pe,[t(S,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[m("div",Ve,[m("span",De,i(a(I)),1),t(c,{type:"primary",class:"w-[100px]",onClick:Y},{default:r(()=>[d(i(a(l)("addSite")),1)]),_:1})]),t(S,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:r(()=>[t(j,{inline:!0,model:o.searchParam,ref_key:"searchFormRef",ref:P},{default:r(()=>[t(h,{label:a(l)("siteName"),prop:"keywords"},{default:r(()=>[t(B,{modelValue:o.searchParam.keywords,"onUpdate:modelValue":s[0]||(s[0]=e=>o.searchParam.keywords=e),placeholder:a(l)("siteNamePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("groupId"),prop:"group_id"},{default:r(()=>[t(V,{modelValue:o.searchParam.group_id,"onUpdate:modelValue":s[1]||(s[1]=e=>o.searchParam.group_id=e),clearable:"",placeholder:a(l)("groupIdPlaceholder"),class:"input-width"},{default:r(()=>[t(v,{label:a(l)("selectPlaceholder"),value:""},null,8,["label"]),(n(!0),f(L,null,T(w.value,e=>(n(),g(v,{label:e.group_name,value:e.group_id},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("status"),prop:"status"},{default:r(()=>[t(V,{modelValue:o.searchParam.status,"onUpdate:modelValue":s[2]||(s[2]=e=>o.searchParam.status=e),clearable:"",placeholder:a(l)("groupIdPlaceholder"),class:"input-width"},{default:r(()=>[t(v,{label:a(l)("selectPlaceholder"),value:""},null,8,["label"]),(n(!0),f(L,null,T(C.value,(e,b)=>(n(),g(v,{label:e,value:b},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("createTime"),prop:"create_time"},{default:r(()=>[t(D,{modelValue:o.searchParam.create_time,"onUpdate:modelValue":s[3]||(s[3]=e=>o.searchParam.create_time=e),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":a(l)("startDate"),"end-placeholder":a(l)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("expireTime"),prop:"expire_time"},{default:r(()=>[t(D,{modelValue:o.searchParam.expire_time,"onUpdate:modelValue":s[4]||(s[4]=e=>o.searchParam.expire_time=e),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":a(l)("startDate"),"end-placeholder":a(l)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),t(h,null,{default:r(()=>[t(c,{type:"primary",onClick:s[5]||(s[5]=e=>u())},{default:r(()=>[d(i(a(l)("search")),1)]),_:1}),t(c,{onClick:s[6]||(s[6]=e=>F(P.value))},{default:r(()=>[d(i(a(l)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),t(G,{class:"warm-prompt",type:"info"},{default:r(()=>[m("div",Se,[t(R,{class:"mr-2",size:"18"},{default:r(()=>[t(H)]),_:1}),m("p",Le,[d(i(a(l)("operationTip"))+" ",1),m("span",{class:"cursor-pointer",onClick:M},i(E))])])]),_:1}),m("div",Te,[Ee((n(),g(q,{data:o.data,size:"large"},{empty:r(()=>[m("span",null,i(o.loading?"":a(l)("emptyData")),1)]),default:r(()=>[t(_,{prop:"site_id",label:a(l)("siteId"),"min-width":"80","show-overflow-tooltip":!0},null,8,["label"]),t(_,{label:a(l)("siteInfo"),"min-width":"180",align:"left"},{default:r(({row:e})=>[m("div",Ie,[e.logo?(n(),f("img",{key:0,class:"w-[50px] h-[50px] mr-[10px]",src:a(J)(e.logo),alt:""},null,8,Fe)):(n(),f("img",Ue)),m("div",Ye,[m("span",null,i(e.site_name||""),1)])])]),_:1},8,["label"]),t(_,{prop:"group_name",label:a(l)("groupId"),"min-width":"80","show-overflow-tooltip":!0},null,8,["label"]),t(_,{label:a(l)("status"),"min-width":"80",align:"center"},{default:r(({row:e})=>[e.status==1?(n(),g(k,{key:0,class:"ml-2",type:"success"},{default:r(()=>[d(i(e.status_name),1)]),_:2},1024)):e.status==3?(n(),g(k,{key:1,class:"ml-2",type:"error"},{default:r(()=>[d(i(e.status_name),1)]),_:2},1024)):(n(),g(k,{key:2,class:"ml-2",type:"error"},{default:r(()=>[d(i(e.status_name),1)]),_:2},1024))]),_:1},8,["label"]),t(_,{prop:"create_time",label:a(l)("createTime"),"min-width":"140","show-overflow-tooltip":!0},null,8,["label"]),t(_,{prop:"expire_time",label:a(l)("expireTime"),"min-width":"140","show-overflow-tooltip":!0},{default:r(({row:e})=>[e.expire_time==0?(n(),f("div",$e,"永久")):(n(),f("div",ze,i(e.expire_time),1))]),_:1},8,["label"]),t(_,{label:a(l)("operation"),fixed:"right",width:"200"},{default:r(({row:e})=>[t(c,{type:"primary",link:"",onClick:b=>z(e)},{default:r(()=>[d(i(a(l)("url")),1)]),_:2},1032,["onClick"]),t(c,{type:"primary",link:"",onClick:b=>$(e)},{default:r(()=>[d(i(a(l)("info")),1)]),_:2},1032,["onClick"]),e.status==1||e.status==3?(n(),g(c,{key:0,type:"primary",link:"",onClick:b=>N(e.status,e.site_id)},{default:r(()=>[d(i(e.status==1?a(l)("closeTxt"):a(l)("openTxt")),1)]),_:2},1032,["onClick"])):we("",!0)]),_:1},8,["label"])]),_:1},8,["data"])),[[W,o.loading]]),m("div",Me,[t(A,{"current-page":o.page,"onUpdate:currentPage":s[7]||(s[7]=e=>o.page=e),"page-size":o.limit,"onUpdate:pageSize":s[8]||(s[8]=e=>o.limit=e),layout:"total, sizes, prev, pager, next, jumper",total:o.total,onSizeChange:s[9]||(s[9]=e=>u()),onCurrentChange:u},null,8,["current-page","page-size","total"])])])]),_:1}),t(oe,{ref_key:"addSiteDialog",ref:y,onComplete:s[10]||(s[10]=e=>u())},null,512)])}}});const Wt=Ce(Ne,[["__scopeId","data-v-8e969299"]]);export{Wt as default};