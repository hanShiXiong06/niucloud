import{t as e,x as t,y as i,e as n,f as r,n as l,p as s}from"./index-e80c244a.js";import{_ as a}from"./_plugin-vue_export-helper.1b428a4d.js";const d=a({name:"u-line",mixins:[t,i,{props:{color:{type:String,default:e.line.color},length:{type:[String,Number],default:e.line.length},direction:{type:String,default:e.line.direction},hairline:{type:Boolean,default:e.line.hairline},margin:{type:[String,Number],default:e.line.margin},dashed:{type:Boolean,default:e.line.dashed}}}],computed:{lineStyle(){const e={};return e.margin=this.margin,"row"===this.direction?(e.borderBottomWidth="1px",e.borderBottomStyle=this.dashed?"dashed":"solid",e.width=uni.$u.addUnit(this.length),this.hairline&&(e.transform="scaleY(0.5)")):(e.borderLeftWidth="1px",e.borderLeftStyle=this.dashed?"dashed":"solid",e.height=uni.$u.addUnit(this.length),this.hairline&&(e.transform="scaleX(0.5)")),e.borderColor=this.color,uni.$u.deepMerge(e,uni.$u.addStyle(this.customStyle))}}},[["render",function(e,t,i,a,d,o){const h=s;return n(),r(h,{class:"u-line",style:l([o.lineStyle])},null,8,["style"])}],["__scopeId","data-v-45e31c7a"]]);export{d as _};
