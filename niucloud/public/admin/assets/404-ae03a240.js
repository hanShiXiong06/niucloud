import{d as l,r as d,S as i,f as p,g as t,h as m,y as u,x,u as v,aP as f,aQ as h,e as g,A as I,B as S}from"./base-06478700.js";/* empty css                  */import{a as B}from"./vue-router-d09a2c28.js";import{E as y}from"./index-c2f001d3.js";import{_ as b}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-2fcd1254.js";import"./index-818c0ce2.js";const k="/admin/assets/error-da01d378.png",o=e=>(f("data-v-7728c846"),e=e(),h(),e),w={class:"error"},C={class:"flex items-center"},E=o(()=>t("div",null,[t("img",{class:"w-[300px]",src:k})],-1)),N={class:"text-left ml-[100px]"},V=o(()=>t("div",{class:"error-text text-[28px] font-bold"},"404错误！",-1)),R=o(()=>t("div",{class:"text-[#222] text-[20px] mt-[15px]"},"哎呀，出错了！您访问的页面不存在...",-1)),U=o(()=>t("div",{class:"text-[#c4c2c2] text-[12px] mt-[5px]"},"尝试检查URL的错误，然后点击浏览器刷新按钮。",-1)),$={class:"mt-[40px]"},A=l({__name:"404",setup(e){let s=null;const a=d(5),c=B();return s=setInterval(()=>{a.value===0?(clearInterval(s),c.go(-1)):a.value--},1e3),i(()=>{s&&clearInterval(s)}),(n,r)=>{const _=y;return g(),p("div",w,[t("div",C,[m(n.$slots,"content",{},()=>[E],!0),t("div",N,[V,R,U,t("div",$,[u(_,{class:"bottom",onClick:r[0]||(r[0]=D=>v(c).go(-1))},{default:x(()=>[I(S(a.value)+" 秒后返回上一页",1)]),_:1})])])])])}}});const F=b(A,[["__scopeId","data-v-7728c846"]]);export{F as default};
