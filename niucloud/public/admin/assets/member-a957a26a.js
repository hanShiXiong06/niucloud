import{g as q,a4 as J,r as g,m as s,n as f,F as l,E as o,q as c,L as i,u as a,K as p,I as C,J as E,D as v,a1 as K}from"./base-d2ce4248.js";/* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-057b5f2c.js";import"./el-tooltip-58212670.js";/* empty css                 *//* empty css                    *//* empty css                        *//* empty css                *//* empty css                     *//* empty css                       *//* empty css                  */import{_ as O}from"./default_headimg-a897263d.js";import{t}from"./index-578c83eb.js";import{d as G}from"./storage-e62e496d.js";import{B as Q,v as W,p as X,C as Z,D as ee}from"./member-e2fc0e43.js";import{u as te,a as le}from"./vue-router-d3dc2686.js";import{_ as ae}from"./add-member.vue_vue_type_script_setup_true_lang-b61f9118.js";import{_ as re}from"./edit-member.vue_vue_type_script_setup_true_lang-9e557a54.js";import{E as oe}from"./index-faa3f8c5.js";import{E as ne}from"./index-953c712f.js";import{E as ie}from"./index-9997ff5d.js";import{a as me,E as se}from"./index-f579a83b.js";import{a as pe,E as de}from"./index-83fe4dc1.js";import{E as ce}from"./index-1b587cec.js";import{E as ue}from"./index-32160c2f.js";import{a as _e,E as be}from"./index-d4538bff.js";import{E as fe}from"./index-0ba64799.js";import{E as ge}from"./index-aaab07eb.js";import{v as he}from"./directive-3f066692.js";import"./el-overlay-7451f13b.js";import"./event-f83e96f5.js";import"./index-28969730.js";import"./focus-trap-b41dd321.js";import"./el-radio-b620ac73.js";import"./index-758a5fe7.js";import"./index-92c8bc76.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-avatar-4397f45a.js";import"./index-3118dac6.js";import"./common-dd6d00bb.js";import"./common-2cf17469.js";import"./index-4b7c0a63.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-f7de127f.js";import"./attachment-84ee7a05.js";/* empty css                 *//* empty css                    *//* empty css                   */import"./index-3ff0840c.js";import"./index-5e746953.js";import"./index-3ae544fb.js";import"./index-e41f0205.js";import"./index-13c7facf.js";import"./isEqual-51ec1a47.js";import"./_Uint8Array-6ca580e8.js";import"./flatten-2fc24abf.js";import"./_initCloneObject-5fe9c070.js";import"./strings-986fee93.js";import"./index-0a26aa04.js";import"./_isIterateeCall-9ac2a284.js";const ve={class:"main-container"},ye={class:"flex justify-between items-center"},ke={class:"text-[24px]"},xe={class:"mt-[10px]"},we={class:"flex items-center"},Ce=["src"],Ee={key:1,class:"w-[50px] h-[50px] mr-[10px]",src:O,alt:""},De={class:"flex flex flex-col"},Pe={class:"flex flex-col items-start"},Me={class:"my-[3px]"},Le={class:"flex items-center"},Ve={class:"mt-[16px] flex justify-end"},Kt=q({__name:"member",setup(Te){const $=te().meta.title,n=J({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{keyword:"",register_type:"",member_label:"",register_channel:"",create_time:[]}}),D=g(),P=g([]);(async()=>{P.value=await(await Q({})).data})();let M=g([]);(async()=>{M.value=await(await W()).data})();const F=m=>{m&&(m.resetFields(),u())},u=(m=1)=>{n.loading=!0,n.page=m,X({page:n.page,limit:n.limit,...n.searchParam}).then(r=>{n.loading=!1,n.data=r.data.data,n.total=r.data.total}).catch(()=>{n.loading=!1})};u();const B=le(),k=g(null),x=g(null);function z(m){let r=g({type:"member_label",id:m.member_id,title:t("setLable"),data:m});x.value.setDialogType(r.value),x.value.showDialog=!0}function I(m){oe.confirm(t("memberDeleteTips"),t("warning"),{confirmButtonText:t("confirm"),cancelButtonText:t("cancel"),type:"warning"}).then(()=>{Z(m.member_id).then(()=>{u()}).catch(()=>{})})}const N=()=>{k.value.setFormData(),k.value.showDialog=!0},S=m=>{B.push(`/member/detail?id=${m.member_id}`)},L=(m,r)=>{ee({status:r,member_ids:[m.member_id]}).then(_=>{_.code>=0&&u()})};return(m,r)=>{const _=ne,U=ie,h=me,y=pe,V=de,R=ce,Y=se,T=ue,d=_e,w=fe,j=be,A=ge,H=he;return s(),f("div",ve,[l(T,{class:"box-card !border-none",shadow:"never"},{default:o(()=>[c("div",ye,[c("span",ke,i(a($)),1),l(_,{type:"primary",onClick:N},{default:o(()=>[p(i(a(t)("addMember")),1)]),_:1})]),l(T,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:o(()=>[l(Y,{inline:!0,model:n.searchParam,ref_key:"searchFormRef",ref:D},{default:o(()=>[l(h,{label:a(t)("memberInfo"),prop:"keyword"},{default:o(()=>[l(U,{modelValue:n.searchParam.keyword,"onUpdate:modelValue":r[0]||(r[0]=e=>n.searchParam.keyword=e),class:"w-[240px]",placeholder:a(t)("memberInfoPlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(h,{label:a(t)("registerChannel"),prop:"register_channel"},{default:o(()=>[l(V,{modelValue:n.searchParam.register_channel,"onUpdate:modelValue":r[1]||(r[1]=e=>n.searchParam.register_channel=e),clearable:"",placeholder:a(t)("channelPlaceholder"),class:"input-width"},{default:o(()=>[l(y,{label:a(t)("selectPlaceholder"),value:""},null,8,["label"]),(s(!0),f(C,null,E(P.value,(e,b)=>(s(),v(y,{label:e,value:b},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),l(h,{label:a(t)("memberLabel"),prop:"member_label"},{default:o(()=>[l(V,{modelValue:n.searchParam.member_label,"onUpdate:modelValue":r[2]||(r[2]=e=>n.searchParam.member_label=e),"collapse-tags":"",clearable:"",placeholder:a(t)("memberLabelPlaceholder"),class:"input-width"},{default:o(()=>[l(y,{label:a(t)("selectPlaceholder"),value:""},null,8,["label"]),(s(!0),f(C,null,E(a(M),e=>(s(),v(y,{label:e.label_name,value:e.label_id},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),l(h,{label:a(t)("createTime"),prop:"create_time"},{default:o(()=>[l(R,{modelValue:n.searchParam.create_time,"onUpdate:modelValue":r[3]||(r[3]=e=>n.searchParam.create_time=e),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":a(t)("startDate"),"end-placeholder":a(t)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),l(h,null,{default:o(()=>[l(_,{type:"primary",onClick:r[4]||(r[4]=e=>u())},{default:o(()=>[p(i(a(t)("search")),1)]),_:1}),l(_,{onClick:r[5]||(r[5]=e=>F(D.value))},{default:o(()=>[p(i(a(t)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),c("div",xe,[K((s(),v(j,{data:n.data,size:"large"},{empty:o(()=>[c("span",null,i(n.loading?"":a(t)("emptyData")),1)]),default:o(()=>[l(d,{prop:"member_no",label:a(t)("memberNo"),"min-width":"120"},null,8,["label"]),l(d,{prop:"nickname","show-overflow-tooltip":!0,label:a(t)("nickname"),"min-width":"170"},{default:o(({row:e})=>[c("div",we,[e.headimg?(s(),f("img",{key:0,class:"w-[50px] h-[50px] mr-[10px]",src:a(G)(e.headimg),alt:""},null,8,Ce)):(s(),f("img",Ee)),c("div",De,[c("span",null,i(e.nickname||""),1)])])]),_:1},8,["label"]),l(d,{prop:"mobile",label:a(t)("mobile"),"min-width":"120"},null,8,["label"]),l(d,{prop:"point",label:a(t)("point"),"min-width":"80",align:"right"},{default:o(({row:e})=>[p(i(Number.parseInt(e.point)),1)]),_:1},8,["label"]),l(d,{prop:"balance",label:a(t)("balance"),"min-width":"130",align:"right"},null,8,["label"]),l(d,{prop:"member_label",label:a(t)("lable"),"min-width":"120",align:"center"},{default:o(({row:e})=>[c("div",Pe,[(s(!0),f(C,null,E(e.member_label_array,(b,ze)=>(s(),f("div",Me,[l(w,{class:"ml-[13px]",type:"info"},{default:o(()=>[p(i(b.label_name),1)]),_:2},1024)]))),256))])]),_:1},8,["label"]),l(d,{prop:"register_channel_name",label:a(t)("registerChannel"),"min-width":"110",align:"center"},null,8,["label"]),l(d,{prop:"member_label",label:a(t)("status"),"min-width":"120",align:"center"},{default:o(({row:e})=>[e.status==1?(s(),v(w,{key:0,type:"success",onClick:b=>L(e,0),class:"cursor-pointer"},{default:o(()=>[p(i(a(t)("normal")),1)]),_:2},1032,["onClick"])):(s(),v(w,{key:1,type:"error",onClick:b=>L(e,1),class:"cursor-pointer"},{default:o(()=>[p(i(a(t)("lock")),1)]),_:2},1032,["onClick"]))]),_:1},8,["label"]),l(d,{label:a(t)("createTime"),"min-width":"150",align:"center"},{default:o(({row:e})=>[p(i(e.create_time||""),1)]),_:1},8,["label"]),l(d,{label:a(t)("lastVisitTime"),"min-width":"150",align:"center"},{default:o(({row:e})=>[p(i(e.last_visit_time||""),1)]),_:1},8,["label"]),l(d,{label:a(t)("operation"),fixed:"right",width:"180"},{default:o(({row:e})=>[c("div",Le,[l(_,{type:"primary",link:"",onClick:b=>S(e)},{default:o(()=>[p(i(a(t)("detail")),1)]),_:2},1032,["onClick"]),l(_,{type:"primary",link:"",onClick:b=>z(e)},{default:o(()=>[p(i(a(t)("setLable")),1)]),_:2},1032,["onClick"]),l(_,{type:"primary",link:"",onClick:b=>I(e)},{default:o(()=>[p(i(a(t)("memberDelete")),1)]),_:2},1032,["onClick"])])]),_:1},8,["label"])]),_:1},8,["data"])),[[H,n.loading]]),c("div",Ve,[l(A,{"current-page":n.page,"onUpdate:currentPage":r[6]||(r[6]=e=>n.page=e),"page-size":n.limit,"onUpdate:pageSize":r[7]||(r[7]=e=>n.limit=e),layout:"total, sizes, prev, pager, next, jumper",total:n.total,onSizeChange:r[8]||(r[8]=e=>u()),onCurrentChange:u},null,8,["current-page","page-size","total"])])]),l(ae,{ref_key:"addMemberDialog",ref:k,onComplete:r[9]||(r[9]=e=>u())},null,512),l(re,{ref_key:"editMemberDialog",ref:x,onComplete:r[10]||(r[10]=e=>u())},null,512)]),_:1})])}}});export{Kt as default};
