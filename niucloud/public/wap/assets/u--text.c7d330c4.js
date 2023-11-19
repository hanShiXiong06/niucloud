import{A as e,B as t,C as n,i,j as o,w as l,G as s,H as a,E as r,p as u,I as p,b2 as d,q as c,t as f,D as m,m as y,k as x,x as h,aG as g,b0 as S}from"./index-98826dc8.js";import{_ as k}from"./u-icon.f3535e52.js";import{_ as b}from"./_plugin-vue_export-helper.1b428a4d.js";const $=b({name:"u-link",mixins:[t,n,{props:{color:{type:String,default:e.link.color},fontSize:{type:[String,Number],default:e.link.fontSize},underLine:{type:Boolean,default:e.link.underLine},href:{type:String,default:e.link.href},mpTips:{type:String,default:e.link.mpTips},lineColor:{type:String,default:e.link.lineColor},text:{type:String,default:e.link.text}}}],computed:{linkStyle(){return{color:this.color,fontSize:uni.$u.addUnit(this.fontSize),lineHeight:uni.$u.addUnit(uni.$u.getPx(this.fontSize)+2),textDecoration:this.underLine?"underline":"none"}}},methods:{openLink(){window.open(this.href),this.$emit("click")}}},[["render",function(e,t,n,d,c,f){const m=p;return i(),o(m,{class:"u-link",onClick:r(f.openLink,["stop"]),style:u([f.linkStyle,e.$u.addStyle(e.customStyle)])},{default:l((()=>[s(a(e.text),1)])),_:1},8,["onClick","style"])}],["__scopeId","data-v-a339e5f1"]]),_={computed:{value(){const{text:e,mode:t,format:n,href:i}=this;return"price"===t?(/^\d+(\.\d+)?$/.test(e)||uni.$u.error("金额模式下，text参数需要为金额格式"),uni.$u.test.func(n)?n(e):uni.$u.priceFormat(e,2)):"date"===t?(!uni.$u.test.date(e)&&uni.$u.error("日期模式下，text参数需要为日期或时间戳格式"),uni.$u.test.func(n)?n(e):n?uni.$u.timeFormat(e,n):uni.$u.timeFormat(e,"yyyy-mm-dd")):"phone"===t?uni.$u.test.func(n)?n(e):"encrypt"===n?`${e.substr(0,3)}****${e.substr(7)}`:e:"name"===t?("string"!=typeof e&&uni.$u.error("姓名模式下，text参数需要为字符串格式"),uni.$u.test.func(n)?n(e):"encrypt"===n?this.formatName(e):e):"link"===t?(!uni.$u.test.url(i)&&uni.$u.error("超链接模式下，href参数需要为URL格式"),e):e}},methods:{formatName(e){let t="";if(2===e.length)t=e.substr(0,1)+"*";else if(e.length>2){let n="";for(let t=0,i=e.length-2;t<i;t++)n+="*";t=e.substr(0,1)+n+e.substr(-1,1)}else t=e;return t}}},v={props:{type:{type:String,default:e.text.type},show:{type:Boolean,default:e.text.show},text:{type:[String,Number],default:e.text.text},prefixIcon:{type:String,default:e.text.prefixIcon},suffixIcon:{type:String,default:e.text.suffixIcon},mode:{type:String,default:e.text.mode},href:{type:String,default:e.text.href},format:{type:[String,Function],default:e.text.format},call:{type:Boolean,default:e.text.call},openType:{type:String,default:e.text.openType},bold:{type:Boolean,default:e.text.bold},block:{type:Boolean,default:e.text.block},lines:{type:[String,Number],default:e.text.lines},color:{type:String,default:e.text.color},size:{type:[String,Number],default:e.text.size},iconStyle:{type:[Object,String],default:e.text.iconStyle},decoration:{tepe:String,default:e.text.decoration},margin:{type:[Object,String,Number],default:e.text.margin},lineHeight:{type:[String,Number],default:e.text.lineHeight},align:{type:String,default:e.text.align},wordWrap:{type:String,default:e.text.wordWrap}}};const w=b({name:"u--text",mixins:[t,n,v],components:{uvText:b({name:"u--text",mixins:[t,n,_,v],emits:["click"],computed:{valueStyle(){const e={textDecoration:this.decoration,fontWeight:this.bold?"bold":"normal",wordWrap:this.wordWrap,fontSize:uni.$u.addUnit(this.size)};return!this.type&&(e.color=this.color),this.isNvue&&this.lines&&(e.lines=this.lines),this.lineHeight&&(e.lineHeight=uni.$u.addUnit(this.lineHeight)),!this.isNvue&&this.block&&(e.display="block"),uni.$u.deepMerge(e,uni.$u.addStyle(this.customStyle))},isNvue:()=>!1,isMp:()=>!1},data:()=>({}),methods:{clickHandler(){this.call&&"phone"===this.mode&&d({phoneNumber:this.text}),this.$emit("click")}}},[["render",function(e,t,n,r,d,S){const b=p,_=c(f("u-icon"),k),v=h,w=c(f("u-link"),$),I=g;return e.show?(i(),o(v,{key:0,class:m(["u-text",[]]),style:u({margin:e.margin,justifyContent:"left"===e.align?"flex-start":"center"===e.align?"center":"flex-end"}),onClick:S.clickHandler},{default:l((()=>["price"===e.mode?(i(),o(b,{key:0,class:m(["u-text__price",e.type&&`u-text__value--${e.type}`]),style:u([S.valueStyle])},{default:l((()=>[s("￥")])),_:1},8,["class","style"])):y("v-if",!0),e.prefixIcon?(i(),o(v,{key:1,class:"u-text__prefix-icon"},{default:l((()=>[x(_,{name:e.prefixIcon,customStyle:e.$u.addStyle(e.iconStyle)},null,8,["name","customStyle"])])),_:1})):y("v-if",!0),"link"===e.mode?(i(),o(w,{key:2,text:e.value,href:e.href,underLine:""},null,8,["text","href"])):e.openType&&S.isMp?(i(),o(I,{key:3,class:"u-reset-button u-text__value",style:u([S.valueStyle]),"data-index":e.index,openType:e.openType,onGetuserinfo:e.onGetUserInfo,onContact:e.onContact,onGetphonenumber:e.onGetPhoneNumber,onError:e.onError,onLaunchapp:e.onLaunchApp,onOpensetting:e.onOpenSetting,lang:e.lang,"session-from":e.sessionFrom,"send-message-title":e.sendMessageTitle,"send-message-path":e.sendMessagePath,"send-message-img":e.sendMessageImg,"show-message-card":e.showMessageCard,"app-parameter":e.appParameter},{default:l((()=>[s(a(e.value),1)])),_:1},8,["style","data-index","openType","onGetuserinfo","onContact","onGetphonenumber","onError","onLaunchapp","onOpensetting","lang","session-from","send-message-title","send-message-path","send-message-img","show-message-card","app-parameter"])):(i(),o(b,{key:4,class:m(["u-text__value",[e.type&&`u-text__value--${e.type}`,e.lines&&`u-line-${e.lines}`]]),style:u([S.valueStyle])},{default:l((()=>[s(a(e.value),1)])),_:1},8,["style","class"])),e.suffixIcon?(i(),o(v,{key:5,class:"u-text__suffix-icon"},{default:l((()=>[x(_,{name:e.suffixIcon,customStyle:e.$u.addStyle(e.iconStyle)},null,8,["name","customStyle"])])),_:1})):y("v-if",!0)])),_:1},8,["style","onClick"])):y("v-if",!0)}],["__scopeId","data-v-b9da4249"]])}},[["render",function(e,t,n,l,s,a){const r=S("uvText");return i(),o(r,{type:e.type,show:e.show,text:e.text,prefixIcon:e.prefixIcon,suffixIcon:e.suffixIcon,mode:e.mode,href:e.href,format:e.format,call:e.call,openType:e.openType,bold:e.bold,block:e.block,lines:e.lines,color:e.color,decoration:e.decoration,size:e.size,iconStyle:e.iconStyle,margin:e.margin,lineHeight:e.lineHeight,align:e.align,wordWrap:e.wordWrap,customStyle:e.customStyle},null,8,["type","show","text","prefixIcon","suffixIcon","mode","href","format","call","openType","bold","block","lines","color","decoration","size","iconStyle","margin","lineHeight","align","wordWrap","customStyle"])}]]);export{w as _};
