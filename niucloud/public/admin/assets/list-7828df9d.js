import{g as J,r as x,a4 as K,aa as O,m as n,n as f,F as t,E as r,q as m,L as i,u as a,K as d,I as L,J as T,D as g,a1 as Q,T as X,G as Z}from"./base-45eb5090.js";/* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-ad71a852.js";import"./el-tooltip-58212670.js";/* empty css                        *//* empty css                    *//* empty css                 *//* empty css                *//* empty css                     *//* empty css                       *//* empty css                  */import{_ as ee}from"./site_logo-f276251b.js";import{d as te}from"./storage-4159d1ed.js";import{t as l}from"./index-3694a2b2.js";import{n as ae,t as le,v as oe,w as se,x as re}from"./site-1a478ebf.js";import{u as ie,a as ne}from"./vue-router-fcbaaf02.js";import{_ as pe}from"./edit-site.vue_vue_type_script_setup_true_lang-d16be7d9.js";/* empty css                   */import{a as me}from"./index-aef37b98.js";import{E as de}from"./index-25c37860.js";import{E as ue}from"./index-4ce9333e.js";import{a as ce,E as _e}from"./index-2bfbe5a7.js";import{a as fe,E as ge}from"./index-cc9a73f0.js";import{E as he}from"./index-91e87b83.js";import{E as ve}from"./index-fc3020f4.js";import{E as be}from"./index-7c3a8eaa.js";import{a as xe,E as ye}from"./index-cbbcd330.js";import{E as ke}from"./index-201145a4.js";import{E as Ee}from"./index-e841b684.js";import{v as we}from"./directive-9f485fe5.js";import{_ as Ce}from"./_plugin-vue_export-helper-c27b6911.js";import"./el-overlay-616d6124.js";import"./event-4977bef7.js";import"./index-cd1661d3.js";import"./focus-trap-318ae2e0.js";import"./el-radio-2719e44c.js";import"./index-9670e877.js";import"./index-3be486d3.js";import"./el-avatar-bc58ad46.js";import"./common-af78c857.js";import"./common-2cf17469.js";import"./_Uint8Array-e584e472.js";import"./_initCloneObject-983ff8c8.js";import"./strings-2444fdb3.js";import"./isEqual-f877b6c1.js";import"./flatten-0fc8b7eb.js";import"./index-f79599e2.js";import"./index-c0090d79.js";import"./_isIterateeCall-104f1f93.js";const Pe={class:"main-container"},De={class:"flex justify-between items-center"},Ve={class:"text-[20px]"},Se={class:"flex items-center"},Le={class:"text-base"},Te={class:"mt-[20px]"},Ie={class:"flex items-center"},Fe=["src"],Ue={key:1,class:"w-[50px] h-[50px] mr-[10px]",src:ee,alt:""},Ye={class:"flex flex flex-col"},$e={key:0},ze={key:1},Ne={class:"mt-[16px] flex justify-end"},Me=J({__name:"list",setup(Be){const I=ie().meta.title,E=location.origin+"/site/login",w=x([]),C=x([]),o=K({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{keywords:"",group_id:"",status:"",create_time:[],expire_time:[]}});(async()=>{w.value=await(await ae({})).data})(),(async()=>{C.value=await(await le()).data})();const P=x(),F=p=>{p&&(p.resetFields(),u())},u=(p=1)=>{o.loading=!0,o.page=p,oe({page:o.page,limit:o.limit,...o.searchParam}).then(s=>{o.loading=!1,o.data=s.data.data,o.total=s.data.total}).catch(()=>{o.loading=!1})};u();const U=ne(),y=x(null),Y=p=>{y.value.setFormData(),y.value.showDialog=!0},$=p=>{U.push("/admin/site/info?id="+p.site_id)},z=p=>{me({message:l("siteUrlDevelopMessage"),grouping:!0,type:"success"})},N=()=>{window.open(E)},M=(p,s)=>{p==1&&se({site_id:s}).then(c=>{u()}),p==3&&re({site_id:s}).then(c=>{u()})};return(p,s)=>{const c=de,B=ue,h=ce,v=fe,D=ge,V=he,G=_e,S=ve,H=O("Warning"),R=Z,j=be,_=xe,k=ke,A=ye,W=Ee,q=we;return n(),f("div",Pe,[t(S,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[m("div",De,[m("span",Ve,i(a(I)),1),t(c,{type:"primary",class:"w-[100px]",onClick:Y},{default:r(()=>[d(i(a(l)("addSite")),1)]),_:1})]),t(S,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:r(()=>[t(G,{inline:!0,model:o.searchParam,ref_key:"searchFormRef",ref:P},{default:r(()=>[t(h,{label:a(l)("siteName"),prop:"keywords"},{default:r(()=>[t(B,{modelValue:o.searchParam.keywords,"onUpdate:modelValue":s[0]||(s[0]=e=>o.searchParam.keywords=e),placeholder:a(l)("siteNamePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("groupId"),prop:"group_id"},{default:r(()=>[t(D,{modelValue:o.searchParam.group_id,"onUpdate:modelValue":s[1]||(s[1]=e=>o.searchParam.group_id=e),clearable:"",placeholder:a(l)("groupIdPlaceholder"),class:"input-width"},{default:r(()=>[t(v,{label:a(l)("selectPlaceholder"),value:""},null,8,["label"]),(n(!0),f(L,null,T(w.value,e=>(n(),g(v,{label:e.group_name,value:e.group_id},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("status"),prop:"status"},{default:r(()=>[t(D,{modelValue:o.searchParam.status,"onUpdate:modelValue":s[2]||(s[2]=e=>o.searchParam.status=e),clearable:"",placeholder:a(l)("groupIdPlaceholder"),class:"input-width"},{default:r(()=>[t(v,{label:a(l)("selectPlaceholder"),value:""},null,8,["label"]),(n(!0),f(L,null,T(C.value,(e,b)=>(n(),g(v,{label:e,value:b},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("createTime"),prop:"create_time"},{default:r(()=>[t(V,{modelValue:o.searchParam.create_time,"onUpdate:modelValue":s[3]||(s[3]=e=>o.searchParam.create_time=e),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":a(l)("startDate"),"end-placeholder":a(l)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),t(h,{label:a(l)("expireTime"),prop:"expire_time"},{default:r(()=>[t(V,{modelValue:o.searchParam.expire_time,"onUpdate:modelValue":s[4]||(s[4]=e=>o.searchParam.expire_time=e),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":a(l)("startDate"),"end-placeholder":a(l)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),t(h,null,{default:r(()=>[t(c,{type:"primary",onClick:s[5]||(s[5]=e=>u())},{default:r(()=>[d(i(a(l)("search")),1)]),_:1}),t(c,{onClick:s[6]||(s[6]=e=>F(P.value))},{default:r(()=>[d(i(a(l)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),t(j,{class:"warm-prompt",type:"info"},{default:r(()=>[m("div",Se,[t(R,{class:"mr-2",size:"18"},{default:r(()=>[t(H)]),_:1}),m("p",Le,[d(i(a(l)("operationTip"))+" ",1),m("span",{class:"cursor-pointer",onClick:N},i(E))])])]),_:1}),m("div",Te,[Q((n(),g(A,{data:o.data,size:"large"},{empty:r(()=>[m("span",null,i(o.loading?"":a(l)("emptyData")),1)]),default:r(()=>[t(_,{prop:"site_id",label:a(l)("siteId"),"min-width":"80","show-overflow-tooltip":!0},null,8,["label"]),t(_,{label:a(l)("siteInfo"),"min-width":"180",align:"left"},{default:r(({row:e})=>[m("div",Ie,[e.logo?(n(),f("img",{key:0,class:"w-[50px] h-[50px] mr-[10px]",src:a(te)(e.logo),alt:""},null,8,Fe)):(n(),f("img",Ue)),m("div",Ye,[m("span",null,i(e.site_name||""),1)])])]),_:1},8,["label"]),t(_,{prop:"group_name",label:a(l)("groupId"),"min-width":"80","show-overflow-tooltip":!0},null,8,["label"]),t(_,{label:a(l)("status"),"min-width":"80",align:"center"},{default:r(({row:e})=>[e.status==1?(n(),g(k,{key:0,class:"ml-2",type:"success"},{default:r(()=>[d(i(e.status_name),1)]),_:2},1024)):e.status==3?(n(),g(k,{key:1,class:"ml-2",type:"error"},{default:r(()=>[d(i(e.status_name),1)]),_:2},1024)):(n(),g(k,{key:2,class:"ml-2",type:"error"},{default:r(()=>[d(i(e.status_name),1)]),_:2},1024))]),_:1},8,["label"]),t(_,{prop:"create_time",label:a(l)("createTime"),"min-width":"140","show-overflow-tooltip":!0},null,8,["label"]),t(_,{prop:"expire_time",label:a(l)("expireTime"),"min-width":"140","show-overflow-tooltip":!0},{default:r(({row:e})=>[e.expire_time==0?(n(),f("div",$e,"永久")):(n(),f("div",ze,i(e.expire_time),1))]),_:1},8,["label"]),t(_,{label:a(l)("operation"),fixed:"right",width:"200"},{default:r(({row:e})=>[t(c,{type:"primary",link:"",onClick:b=>z(e)},{default:r(()=>[d(i(a(l)("url")),1)]),_:2},1032,["onClick"]),t(c,{type:"primary",link:"",onClick:b=>$(e)},{default:r(()=>[d(i(a(l)("info")),1)]),_:2},1032,["onClick"]),e.status==1||e.status==3?(n(),g(c,{key:0,type:"primary",link:"",onClick:b=>M(e.status,e.site_id)},{default:r(()=>[d(i(e.status==1?a(l)("closeTxt"):a(l)("openTxt")),1)]),_:2},1032,["onClick"])):X("",!0)]),_:1},8,["label"])]),_:1},8,["data"])),[[q,o.loading]]),m("div",Ne,[t(W,{"current-page":o.page,"onUpdate:currentPage":s[7]||(s[7]=e=>o.page=e),"page-size":o.limit,"onUpdate:pageSize":s[8]||(s[8]=e=>o.limit=e),layout:"total, sizes, prev, pager, next, jumper",total:o.total,onSizeChange:s[9]||(s[9]=e=>u()),onCurrentChange:u},null,8,["current-page","page-size","total"])])])]),_:1}),t(pe,{ref_key:"addSiteDialog",ref:y,onComplete:s[10]||(s[10]=e=>u())},null,512)])}}});const jt=Ce(Me,[["__scopeId","data-v-b69bf594"]]);export{jt as default};