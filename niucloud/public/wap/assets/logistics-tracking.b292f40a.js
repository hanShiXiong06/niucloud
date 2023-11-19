import{A as t,B as e,C as a,q as s,t as i,i as r,j as n,w as l,D as o,p as c,m as p,k as u,F as d,G as h,H as m,x as f,I as _,d as x,r as v,n as y,P as D,Q as C,R as g,a7 as b,K as w,bn as k}from"./index-c8487b3e.js";import{_ as I}from"./u-tabs.fa0eaf2b.js";import{_ as S}from"./u-icon.e1505caa.js";import{_ as z}from"./u--text.e0f8727c.js";import{_ as $}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as j}from"./u-popup.41f7f1c4.js";import{b as L}from"./order.e6f765ef.js";/* empty css                                                                       */const P=$({name:"u-steps-item",mixins:[e,a,{props:{title:{type:[String,Number],default:t.stepsItem.title},desc:{type:[String,Number],default:t.stepsItem.desc},iconSize:{type:[String,Number],default:t.stepsItem.iconSize},error:{type:Boolean,default:t.stepsItem.error}}}],data:()=>({index:0,childLength:0,showLine:!1,size:{height:0,width:0},parentData:{direction:"row",current:0,activeColor:"",inactiveColor:"",activeIcon:"",inactiveIcon:"",dot:!1}}),watch:{parentData(t,e){}},created(){this.init()},computed:{lineStyle(){var t,e;const a={};return"row"===this.parentData.direction?(a.width=this.size.width+"px",a.left=this.size.width/2+"px"):a.height=this.size.height+"px",a.backgroundColor=(null==(e=null==(t=this.parent.children)?void 0:t[this.index+1])?void 0:e.error)?uni.$u.color.error:this.index<this.parentData.current?this.parentData.activeColor:this.parentData.inactiveColor,a},statusClass(){const{index:t,error:e}=this,{current:a}=this.parentData;return a==t?!0===e?"error":"process":e?"error":a>t?"finish":"wait"},statusColor(){let t="";switch(this.statusClass){case"finish":t=this.parentData.activeColor;break;case"error":t=uni.$u.color.error;break;case"process":t=this.parentData.dot?this.parentData.activeColor:"transparent";break;default:t=this.parentData.inactiveColor}return t},contentStyle(){const t={};return"column"===this.parentData.direction?(t.marginLeft=this.parentData.dot?"2px":"6px",t.marginTop=this.parentData.dot?"0px":"6px"):(t.marginTop=this.parentData.dot?"2px":"6px",t.marginLeft=this.parentData.dot?"2px":"6px"),t}},mounted(){this.parent&&this.parent.updateFromChild(),uni.$u.sleep().then((()=>{this.getStepsItemRect()}))},methods:{init(){if(this.updateParentData(),!this.parent)return uni.$u.error("u-steps-item必须要搭配u-steps组件使用");this.index=this.parent.children.indexOf(this),this.childLength=this.parent.children.length},updateParentData(){this.getParentData("u-steps")},updateFromParent(){this.init()},getStepsItemRect(){this.$uGetRect(".u-steps-item").then((t=>{this.size=t}))}}},[["render",function(t,e,a,x,v,y){const D=f,C=s(i("u-icon"),S),g=_,b=s(i("u--text"),z);return r(),n(D,{class:o(["u-steps-item",[`u-steps-item--${v.parentData.direction}`]]),ref:"u-steps-item"},{default:l((()=>[v.index+1<v.childLength?(r(),n(D,{key:0,class:o(["u-steps-item__line",[`u-steps-item__line--${v.parentData.direction}`]]),style:c([y.lineStyle])},null,8,["class","style"])):p("v-if",!0),u(D,{class:o(["u-steps-item__wrapper",[`u-steps-item__wrapper--${v.parentData.direction}`,v.parentData.dot&&`u-steps-item__wrapper--${v.parentData.direction}--dot`]])},{default:l((()=>[d(t.$slots,"icon",{},(()=>[v.parentData.dot?(r(),n(D,{key:0,class:"u-steps-item__wrapper__dot",style:c({backgroundColor:y.statusColor})},null,8,["style"])):v.parentData.activeIcon||v.parentData.inactiveIcon?(r(),n(D,{key:1,class:"u-steps-item__wrapper__icon"},{default:l((()=>[u(C,{name:v.index<=v.parentData.current?v.parentData.activeIcon:v.parentData.inactiveIcon,size:t.iconSize,color:v.index<=v.parentData.current?v.parentData.activeColor:v.parentData.inactiveColor},null,8,["name","size","color"])])),_:1})):(r(),n(D,{key:2,style:c({backgroundColor:"process"===y.statusClass?v.parentData.activeColor:"transparent",borderColor:y.statusColor}),class:"u-steps-item__wrapper__circle"},{default:l((()=>["process"===y.statusClass||"wait"===y.statusClass?(r(),n(g,{key:0,class:"u-steps-item__wrapper__circle__text",style:c({color:v.index==v.parentData.current?"#ffffff":v.parentData.inactiveColor})},{default:l((()=>[h(m(v.index+1),1)])),_:1},8,["style"])):(r(),n(C,{key:1,color:"error"===y.statusClass?"error":v.parentData.activeColor,size:"12",name:"error"===y.statusClass?"close":"checkmark"},null,8,["color","name"]))])),_:1},8,["style"]))]),!0)])),_:3},8,["class"]),u(D,{class:o(["u-steps-item__content",[`u-steps-item__content--${v.parentData.direction}`]]),style:c([y.contentStyle])},{default:l((()=>[u(b,{text:t.title,type:v.parentData.current==v.index?"main":"content",lineHeight:"20px",size:v.parentData.current==v.index?14:13},null,8,["text","type","size"]),d(t.$slots,"desc",{},(()=>[u(b,{text:t.desc,type:"tips",size:"12"},null,8,["text"])]),!0)])),_:3},8,["class","style"]),p(' <view\n\t\t    class="u-steps-item__line"\n\t\t    v-if="showLine && parentData.direction === \'column\'"\n\t\t\t:class="[`u-steps-item__line--${parentData.direction}`]"\n\t\t    :style="[lineStyle]"\n\t\t></view> ')])),_:3},8,["class"])}],["__scopeId","data-v-45a927e1"]]);const F=$({name:"u-steps",mixins:[e,a,{props:{direction:{type:String,default:t.steps.direction},current:{type:[String,Number],default:t.steps.current},activeColor:{type:String,default:t.steps.activeColor},inactiveColor:{type:String,default:t.steps.inactiveColor},activeIcon:{type:String,default:t.steps.activeIcon},inactiveIcon:{type:String,default:t.steps.inactiveIcon},dot:{type:Boolean,default:t.steps.dot}}}],data:()=>({}),watch:{children(){this.updateChildData()},parentData(){this.updateChildData()}},computed:{parentData(){return[this.current,this.direction,this.activeColor,this.inactiveColor,this.activeIcon,this.inactiveIcon,this.dot]}},methods:{updateChildData(){this.children.map((t=>{uni.$u.test.func((t||{}).updateFromParent())&&t.updateFromParent()}))},updateFromChild(){this.updateChildData()}},created(){this.children=[]}},[["render",function(t,e,a,s,i,c){const p=f;return r(),n(p,{class:o(["u-steps",[`u-steps--${t.direction}`]])},{default:l((()=>[d(t.$slots,"default",{},void 0,!0)])),_:3},8,["class"])}],["__scopeId","data-v-9d7fe081"]]),N=$(x({__name:"logistics-tracking",setup(t,{expose:e}){let a=v(!1);const o=v([]),c=v({}),d=async t=>{let e=await L(t);c.value=e.data},x=()=>{a.value=!1},S=v(0),z=t=>{S.value=t.index;let e={id:t.id,mobile:t.mobile};d(e)};return e({packageList:o,open:t=>{d(t),a.value=!0}}),(t,e)=>{const d=f,v=s(i("u-tabs"),I),$=_,L=s(i("u-steps-item"),P),N=s(i("u-steps"),F),R=b,B=s(i("u-popup"),j);return r(),n(d,{class:""},{default:l((()=>[u(B,{show:y(a),mode:"bottom",round:10,onClose:x,closeable:!0},{default:l((()=>[Object.keys(c.value).length?(r(),n(d,{key:0,class:"h-[70vh] px-[24rpx] bg-page"},{default:l((()=>[u(d,{class:"text-center text-[32rpx] leading-8"},{default:l((()=>[h(m(y(w)("detailedInformation")),1)])),_:1}),o.value.length>1?(r(),n(d,{key:0,class:"flex mt-[10rpx] menu"},{default:l((()=>[u(v,{list:o.value,onClick:z,current:S.value,itemStyle:"font-size:28rpx;",lineWidth:"55",lineColor:"#ff4500"},null,8,["list","current"])])),_:1})):p("v-if",!0),u(d,{class:"text-[28rpx] mt-[35rpx]"},{default:l((()=>[u(d,{class:"flex justify-between mb-[20rpx]"},{default:l((()=>[u($,{class:"mr-[20rpx]"},{default:l((()=>[h(m(c.value.company.company_name),1)])),_:1}),u(d,null,{default:l((()=>[u($,{class:"mr-[14rpx]"},{default:l((()=>[h(m(c.value.express_number),1)])),_:1}),u($,{onClick:e[0]||(e[0]=t=>y(k)(c.value.express_number))},{default:l((()=>[h(m(y(w)("copy")),1)])),_:1})])),_:1})])),_:1})])),_:1}),u(d,{class:"parcel",style:{height:"56vh"}},{default:l((()=>[0==c.value.traces.success?(r(),n(d,{key:0,class:"h-[40vh] flex items-center justify-center"},{default:l((()=>[u(d,{class:""},{default:l((()=>[u($,{class:"iconfont iconzanwuwuliuxinxi text-[180rpx] text-[#bfbfbf]"}),u(d,{class:"text-[28rpx] text-[#bfbfbf] leading-8"},{default:l((()=>[h("暂无物流信息～～")])),_:1})])),_:1})])),_:1})):(r(),n(R,{key:1,"scroll-y":"true",style:{height:"56vh",padding:"20rpx","box-sizing":"border-box"},class:"bg-white rounded-md"},{default:l((()=>[u(N,{current:"0",dot:"",direction:"column",activeColor:"#ff4500"},{default:l((()=>[(r(!0),D(g,null,C(c.value.traces.list,((t,e)=>(r(),n(L,{title:t.remark,desc:t.datetime},null,8,["title","desc"])))),256))])),_:1})])),_:1}))])),_:1})])),_:1})):p("v-if",!0)])),_:1},8,["show"])])),_:1})}}}),[["__scopeId","data-v-21ffcf1f"]]);export{N as l};
