import{d as l,r as d,S as i,f as p,g as t,h as m,y as u,x,u as v,bl as f,bm as h,e as g,A as I,B as b}from"./base-d77b0726.js";/* empty css                  */import{b as S}from"./vue-router-57155f94.js";import{E as B}from"./index-91bdda63.js";import{_ as y}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-e37943c3.js";import"./index-f2dc9b9f.js";import"./index-6245131d.js";const k="/admin/assets/error-da01d378.png",o=e=>(f("data-v-7728c846"),e=e(),h(),e),w={class:"error"},C={class:"flex items-center"},E=o(()=>t("div",null,[t("img",{class:"w-[300px]",src:k})],-1)),N={class:"text-left ml-[100px]"},V=o(()=>t("div",{class:"error-text text-[28px] font-bold"},"404错误！",-1)),R=o(()=>t("div",{class:"text-[#222] text-[20px] mt-[15px]"},"哎呀，出错了！您访问的页面不存在...",-1)),U=o(()=>t("div",{class:"text-[#c4c2c2] text-[12px] mt-[5px]"},"尝试检查URL的错误，然后点击浏览器刷新按钮。",-1)),$={class:"mt-[40px]"},A=l({__name:"404",setup(e){let s=null;const a=d(5),r=S();return s=setInterval(()=>{a.value===0?(clearInterval(s),r.go(-1)):a.value--},1e3),i(()=>{s&&clearInterval(s)}),(n,c)=>{const _=B;return g(),p("div",w,[t("div",C,[m(n.$slots,"content",{},()=>[E],!0),t("div",N,[V,R,U,t("div",$,[u(_,{class:"bottom",onClick:c[0]||(c[0]=D=>v(r).go(-1))},{default:x(()=>[I(b(a.value)+" 秒后返回上一页",1)]),_:1})])])])])}}});const J=y(A,[["__scopeId","data-v-7728c846"]]);export{J as default};