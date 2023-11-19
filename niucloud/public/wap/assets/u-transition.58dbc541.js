import{V as t,aI as e,W as i,X as s,i as n,j as a,w as o,T as r,I as l,p as u,m as d,x as h}from"./index-42b5dd66.js";import{_ as c}from"./_plugin-vue_export-helper.1b428a4d.js";const m={props:{show:{type:Boolean,default:t.transition.show},mode:{type:String,default:t.transition.mode},duration:{type:[String,Number],default:t.transition.duration},timingFunction:{type:String,default:t.transition.timingFunction}}},v=t=>({enter:`u-${t}-enter u-${t}-enter-active`,"enter-to":`u-${t}-enter-to u-${t}-enter-active`,leave:`u-${t}-leave u-${t}-leave-active`,"leave-to":`u-${t}-leave-to u-${t}-leave-active`});const p=c({name:"u-transition",data:()=>({inited:!1,viewStyle:{},status:"",transitionEnded:!1,display:!1,classes:""}),emits:["click","beforeEnter","enter","afterEnter","beforeLeave","leave","afterLeave"],computed:{mergeStyle(){const{viewStyle:t,customStyle:e}=this;return{transitionDuration:`${this.duration}ms`,transitionTimingFunction:this.timingFunction,...uni.$u.addStyle(e),...t}}},mixins:[i,s,{methods:{clickHandler(){this.$emit("click")},async vueEnter(){const t=v(this.mode);this.status="enter",this.$emit("beforeEnter"),this.inited=!0,this.display=!0,this.classes=t.enter,await e(),await uni.$u.sleep(20),this.$emit("enter"),this.transitionEnded=!1,this.$emit("afterEnter"),this.classes=t["enter-to"]},async vueLeave(){if(!this.display)return;const t=v(this.mode);this.status="leave",this.$emit("beforeLeave"),this.classes=t.leave,await e(),this.transitionEnded=!1,this.$emit("leave"),setTimeout(this.onTransitionEnd,this.duration),this.classes=t["leave-to"]},onTransitionEnd(){this.transitionEnded||(this.transitionEnded=!0,this.$emit("leave"===this.status?"afterLeave":"afterEnter"),!this.show&&this.display&&(this.display=!1,this.inited=!1))}}},m],watch:{show:{handler(t){t?this.vueEnter():this.vueLeave()},immediate:!0}}},[["render",function(t,e,i,s,c,m){const v=h;return c.inited?(n(),a(v,{key:0,class:l(["u-transition",c.classes]),ref:"u-transition",onClick:t.clickHandler,style:u([m.mergeStyle]),onTouchmove:t.noop},{default:o((()=>[r(t.$slots,"default",{},void 0,!0)])),_:3},8,["onClick","class","style","onTouchmove"])):d("v-if",!0)}],["__scopeId","data-v-bb806228"]]);export{p as _};
