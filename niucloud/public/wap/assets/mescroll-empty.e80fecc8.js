import{i as t,j as i,w as e,k as o,m as n,K as p,L as s,I as l,p as c,M as r,x as a}from"./index-42b5dd66.js";import{m,G as u}from"./useMescroll.0e1ee78a.js";import{_ as y}from"./_plugin-vue_export-helper.1b428a4d.js";const d=y({props:{option:{type:Object,default:()=>({})}},computed:{icon(){if(null!=this.option.icon)return this.option.icon;{let t=m.getType();return this.option.i18n?this.option.i18n[t].icon:u.i18n[t].up.empty.icon||u.up.empty.icon}},tip(){if(null!=this.option.tip)return this.option.tip;{let t=m.getType();return this.option.i18n?this.option.i18n[t].tip:u.i18n[t].up.empty.tip||u.up.empty.tip}},btnText(){if(this.option.i18n){let t=m.getType();return this.option.i18n[t].btnText}return this.option.btnText}},methods:{emptyClick(){this.$emit("emptyclick")}}},[["render",function(m,u,y,d,f,h){const x=r,k=a;return t(),i(k,{class:l(["mescroll-empty",{"empty-fixed":y.option.fixed}]),style:c({"z-index":y.option.zIndex,top:y.option.top})},{default:e((()=>[o(k,null,{default:e((()=>[h.icon?(t(),i(x,{key:0,class:"empty-icon",src:h.icon,mode:"widthFix"},null,8,["src"])):n("v-if",!0)])),_:1}),h.tip?(t(),i(k,{key:0,class:"empty-tip"},{default:e((()=>[p(s(h.tip),1)])),_:1})):n("v-if",!0),h.btnText?(t(),i(k,{key:1,class:"empty-btn",onClick:h.emptyClick},{default:e((()=>[p(s(h.btnText),1)])),_:1},8,["onClick"])):n("v-if",!0)])),_:1},8,["class","style"])}],["__scopeId","data-v-16920704"]]);export{d as M};
