/* empty css             *//* empty css                   *//* empty css                             *//* empty css                */import{b as L,q as F}from"./index-aae906bf.js";/* empty css                     */import{e as O,i as E,_ as V,a as z,b as A,c as B,d as M}from"./index-243a2593.js";import{t as o}from"./index-5f4ce139.js";import{i as T}from"./common-465e36b3.js";import{a as $}from"./vue-router-b5675730.js";import{E as q}from"./index-a63d1984.js";import{E as R}from"./index-acd12562.js";import{a as U,E as W}from"./index-9fa80202.js";import{v as G}from"./directive-a07a10ed.js";import{d as H,r as x,L as J,b as g,e as y,f as t,q as a,p as e,x as i,u as s,v as h,F as K,t as P,n as Q,as as X,at as Y}from"./runtime-core.esm-bundler-7c3fd514.js";import{_ as Z}from"./_plugin-vue_export-helper-c27b6911.js";import"./el-overlay-f7f710bd.js";import"./plugin-vue_export-helper-edbdb6f8.js";import"./index-f02197a7.js";import"./index-868cd458.js";import"./index-a3cf5375.js";import"./event-9519ab40.js";import"./focus-trap-bb1e8c7a.js";import"./index-7b0897f9.js";import"./error-492b6a5b.js";import"./el-switch-3d36d31d.js";import"./index-4862d1b3.js";import"./index-cf47f151.js";import"./index-2083be2e.js";import"./index-95693143.js";import"./index-47617222.js";import"./validator-62f68fe3.js";/* empty css                 *//* empty css                  */import"./el-radio-c9a1047c.js";import"./index-2f0b1bf3.js";/* empty css                   */import"./el-avatar-7d17482e.js";import"./index-be5dc120.js";import"./common-cc37bda4.js";import"./common-2cf17469.js";const u=f=>(X("data-v-fdbc2991"),f=f(),Y(),f),tt={class:"main-container flex"},et={class:"main-body flex-1 mr-[15px]"},st={class:"card-header"},at={class:"text-[14px]"},ot={class:"text-[14px] mb-[9px]"},it={class:"text-[14px] mb-[9px]"},rt={class:"text-[14px] mb-[9px]"},nt={class:"card-header"},lt={class:"text-[14px]"},ct=u(()=>t("img",{class:"w-[40px] h-[40px]",src:V,alt:""},null,-1)),dt={class:"mt-[10px] text-[14px]"},pt=u(()=>t("img",{class:"w-[40px] h-[40px]",src:z,alt:""},null,-1)),_t={class:"mt-[10px] text-[14px]"},mt=u(()=>t("img",{class:"w-[40px] h-[40px]",src:A,alt:""},null,-1)),xt={class:"mt-[10px] text-[14px]"},ut={class:"flex justify-center flex-col items-center cursor-pointer"},ft=u(()=>t("img",{class:"w-[40px] h-[40px]",src:B,alt:""},null,-1)),vt={class:"mt-[10px] text-[14px]"},ht={class:"flex justify-center flex-col items-center cursor-pointer"},bt=u(()=>t("img",{class:"w-[40px] h-[40px]",src:M,alt:""},null,-1)),gt={class:"mt-[10px] text-[14px]"},yt={class:"mt-[15px] flex"},wt={class:"card-header"},St={class:"text-[14px]"},Ct={class:"card-header"},Et={class:"text-[14px]"},It={class:"main-aside w-[300px]"},kt={class:"card-header"},jt={class:"text-[14px]"},Dt={class:"card-header"},Nt={class:"text-[14px]"},Lt=["src"],Ft={class:"text-[14px]"},Ot={class:"text-[12px] mt-[8px] text-gray-400"},Vt=H({__name:"site_index",setup(f){const w=x(!0),S=x(null),C=x(null);let r=x({today_data:{},system:{},version:{},about:[],visit_stat:{},member_stat:{},site_info:{}});(async(p=0)=>{r.value=await(await O()).data,w.value=!1,setTimeout(()=>{I()},20)})();const I=()=>{const p=E(S.value),c=x({legend:{},xAxis:{data:[]},yAxis:{},tooltip:{trigger:"axis"},series:[{name:o("pageView"),type:"line",data:[]}]});c.value.xAxis.data=r.value.visit_stat.date,c.value.series[0].data=r.value.visit_stat.value,p.setOption(c.value);const _=E(C.value),n=x({legend:{},tooltip:{},series:[{type:"pie",data:[]}]}),v=r.value.member_stat.type.length;for(let l=0;l<v;l++){let d={};d.name=r.value.member_stat.type[l],d.value=r.value.member_stat.value[l],n.value.series[0].data.push(d)}_.setOption(n.value),window.addEventListener("resize",()=>{p.resize(),_.resize()})},k=$(),b=p=>{k.push(p)};return(p,c)=>{const _=q,n=L,v=F,l=R,d=U,j=W,D=G;return J((g(),y("div",tt,[t("div",et,[a(l,{class:"box-card !border-none",shadow:"never"},{header:e(()=>[t("div",st,[t("span",at,i(s(o)("todayData")),1)])]),default:e(()=>[a(v,null,{default:e(()=>[a(n,{span:8},{default:e(()=>[a(_,{value:s(r).today_data.total_member_count},{title:e(()=>[t("div",ot,i(s(o)("memberNumb")),1)]),_:1},8,["value"])]),_:1}),a(n,{span:8},{default:e(()=>[a(_,{value:s(r).today_data.total_order_money,precision:2},{title:e(()=>[t("div",it,i(s(o)("orderMoney")),1)]),_:1},8,["value"])]),_:1}),a(n,{span:8},{default:e(()=>[a(_,{value:s(r).today_data.total_visit_count},{title:e(()=>[t("div",rt,i(s(o)("numberOfVisitors")),1)]),_:1},8,["value"])]),_:1})]),_:1})]),_:1}),a(l,{class:"box-card !border-none mt-[15px]",shadow:"never"},{header:e(()=>[t("div",nt,[t("span",lt,i(s(o)("commonlyUsedFunction")),1)])]),default:e(()=>[a(v,{gutter:20,justify:"space-between"},{default:e(()=>[a(n,{span:4},{default:e(()=>[t("div",{class:"flex justify-center flex-col items-center cursor-pointer",onClick:c[0]||(c[0]=m=>b("/article/list"))},[ct,t("span",dt,i(s(o)("articleList")),1)])]),_:1}),a(n,{span:4},{default:e(()=>[t("div",{class:"flex justify-center flex-col items-center cursor-pointer",onClick:c[1]||(c[1]=m=>b("/member/member"))},[pt,t("span",_t,i(s(o)("memberManagement")),1)])]),_:1}),a(n,{span:4},{default:e(()=>[t("div",{class:"flex justify-center flex-col items-center cursor-pointer",onClick:c[2]||(c[2]=m=>b("/member/balance"))},[mt,t("span",xt,i(s(o)("balanceAccount")),1)])]),_:1}),a(n,{span:4},{default:e(()=>[t("div",ut,[ft,t("span",vt,i(s(o)("administrator")),1)])]),_:1}),a(n,{span:4},{default:e(()=>[t("div",ht,[bt,t("span",gt,i(s(o)("WebDecoration")),1)])]),_:1})]),_:1})]),_:1}),t("div",yt,[a(l,{class:"box-card !border-none flex-1 mr-[15px]",shadow:"never"},{header:e(()=>[t("div",wt,[t("span",St,i(s(o)("accessMessage")),1)])]),default:e(()=>[t("div",{ref_key:"visitStat",ref:S,style:{width:"100%",height:"300px"}},null,512)]),_:1}),a(l,{class:"box-card !border-none flex-1",shadow:"never"},{header:e(()=>[t("div",Ct,[t("span",Et,i(s(o)("memberDistribution")),1)])]),default:e(()=>[t("div",{ref_key:"memberStat",ref:C,style:{width:"100%",height:"300px"}},null,512)]),_:1})])]),t("div",It,[a(l,{class:"box-card !border-none",shadow:"never"},{header:e(()=>[t("div",kt,[t("span",jt,i(s(o)("siteInfo")),1)])]),default:e(()=>[a(j,{column:1},{default:e(()=>[a(d,{label:s(o)("siteName")},{default:e(()=>[h(i(s(r).site_info.site_name),1)]),_:1},8,["label"]),a(d,{label:s(o)("groupName")},{default:e(()=>[h(i(s(r).site_info.group_name),1)]),_:1},8,["label"]),a(d,{label:s(o)("statusName")},{default:e(()=>[h(i(s(r).site_info.status_name),1)]),_:1},8,["label"]),a(d,{label:s(o)("expireTime")},{default:e(()=>[h(i(s(r).site_info.expire_time?s(r).site_info.expire_time:s(o)("permanent")),1)]),_:1},8,["label"])]),_:1})]),_:1}),a(l,{class:"box-card !border-none mt-[15px]",shadow:"never"},{header:e(()=>[t("div",Dt,[t("span",Nt,i(s(o)("serviceSupport")),1)])]),default:e(()=>[(g(!0),y(K,null,P(s(r).about,(m,N)=>(g(),y("div",{class:Q(["flex","items-center","pt-[40px]","pb-[40px]",{"border-gray-300 border-b-[1px]":N==0}])},[t("img",{class:"w-[120px] h-[120px] mr-[8px]",src:s(T)(m.image),alt:""},null,8,Lt),t("div",null,[t("p",Ft,i(m.name),1),t("p",Ot,i(m.desc),1)])],2))),256))]),_:1})])])),[[D,w.value]])}}});const Se=Z(Vt,[["__scopeId","data-v-fdbc2991"]]);export{Se as default};
