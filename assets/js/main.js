(function(){
"use strict";
var PHONE="601116320694",EMAIL="work@esrmy.com";
// Payment pages for the Subscribe buttons (from the payment gateway). Empty = button opens WhatsApp instead.
var PAY_LINKS={quarterly:"",yearly:""};
Array.prototype.forEach.call(document.querySelectorAll("[data-plan]"),function(b){var u=PAY_LINKS[b.getAttribute("data-plan")];if(u){b.href=u;b.removeAttribute("target");b.removeAttribute("rel")}});
var L=document.documentElement.lang==="ms"?"ms":"en";
var M={en:{subMonthly:"For a resident employee paid the same amount all year",subZero:"Below the tax threshold after reliefs",subNr:"Non-residents pay a flat 30%",errNeed:"Please add your name and a WhatsApp number or email.",msgPcb:"PCB calculator: please send me the full breakdown and a demo",msgEa:"EA form guide: please send me the EA checklist",msgCalc:"My calculation",name:"Name",company:"Company",contact:"WhatsApp number or email",size:"Number of employees",salary:"Monthly salary"},
ms:{subMonthly:"Untuk pekerja pemastautin yang dibayar amaun sama sepanjang tahun",subZero:"Di bawah paras cukai selepas pelepasan",subNr:"Bukan pemastautin dikenakan kadar rata 30%",errNeed:"Sila isi nama anda dan nombor WhatsApp atau e-mel.",msgPcb:"Kalkulator PCB: sila hantar pecahan penuh dan demo",msgEa:"Panduan borang EA: sila hantar senarai semak EA",msgCalc:"Kiraan saya",name:"Nama",company:"Syarikat",contact:"Nombor WhatsApp atau e-mel",size:"Bilangan pekerja",salary:"Gaji bulanan"}}[L];
function RM(n){return "RM"+(Number(n)||0).toLocaleString("en-MY",{minimumFractionDigits:2,maximumFractionDigits:2})}
var BANDS=[[0,5000,0],[5000,20000,.01],[20000,35000,.03],[35000,50000,.06],[50000,70000,.11],[70000,100000,.19],[100000,400000,.25],[400000,600000,.26],[600000,2000000,.28],[2000000,Infinity,.30]];
function tax(P,cat){if(P<=0)return 0;var x=0;BANDS.forEach(function(b){if(P>b[0])x+=(Math.min(P,b[1])-b[0])*b[2]});if(P<=35000)x-=cat===2?800:400;return x}
function tr2(x){return Math.floor(x*100+1e-9)/100}function r5(x){return Math.ceil(x*20-1e-9)/20}function r2(x){return Math.round(x*100)/100}
var last=null,f=document.getElementById("pcbForm");
function $(id){return document.getElementById(id)}
function calc(){
  var v=function(k){return Number(f.elements[k].value)||0};
  var wage=v("salary")+v("allow"),zak=v("zakat"),cat=Number(f.elements.marital.value),kids=Math.max(0,Math.floor(v("kids"))),fx=f.elements.nat.value==="fx",nr=f.elements.res.value==="nr";
  var up=function(x){return Math.ceil(x-1e-9)},sw=Math.min(wage,6000);
  var epfEe=up(wage*(fx?.02:.11)),epfEr=up(wage*(fx?.02:wage<=5000?.13:.12)),socEe=fx?0:r2(sw*.005),socEr=r2(sw*(fx?.0125:.0175)),eisEe=fx?0:r2(sw*.002),eisEr=fx?0:r2(sw*.002);
  var pcb,P=null;
  if(nr){pcb=r5(tr2(wage*.30))}else{var K1=Math.min(epfEe,4000),K2=Math.min(K1,Math.max(0,(4000-K1)/11)),rel=9000+(cat===2?4000:0)+2000*kids;P=(wage-K1)+(wage-K2)*11-rel;pcb=r5(tr2(Math.max(0,tax(P,cat))/12));if(pcb<10)pcb=0}
  var pcbNet=Math.max(0,r2(pcb-zak)),net=r2(wage-epfEe-socEe-eisEe-pcbNet-zak);
  last={wage:wage,pcb:pcbNet};
  $("pcbOut").textContent=RM(pcbNet);$("pcbSub").textContent=nr?M.subNr:(P!==null&&P<=0?M.subZero:M.subMonthly);
  var set=function(id,val){$(id).textContent=val};
  set("vGross",RM(wage));set("vEpf","−"+RM(epfEe));set("vSocso","−"+RM(socEe));set("vEis","−"+RM(eisEe));set("vPcb","−"+RM(pcbNet));set("vZakat","−"+RM(zak));set("vNet",RM(net));
  $("rowZakat").hidden=!zak;set("vEr1",RM(epfEr));set("vEr2",RM(socEr));set("vEr3",RM(eisEr));
}
if(f){f.addEventListener("input",calc);f.addEventListener("change",calc);calc()}
document.addEventListener("click",function(e){var b=e.target.closest("[data-send]");if(!b)return;var kind=b.getAttribute("data-kind"),fm=document.querySelector('.lead-form[data-kind="'+kind+'"]'),err=document.querySelector('[data-err="'+kind+'"]');
  var name=fm.elements.name.value.trim(),company=fm.elements.company.value.trim(),contact=fm.elements.contact.value.trim(),size=fm.elements.size.value;
  if(!name||!contact){err.textContent=M.errNeed;return}err.textContent="";
  var head=kind==="pcb"?M.msgPcb:M.msgEa,body=head+"\n\n"+M.name+": "+name+"\n"+M.company+": "+(company||"-")+"\n"+M.contact+": "+contact+"\n"+M.size+": "+size;
  if(kind==="pcb"&&last)body+="\n\n"+M.msgCalc+": "+M.salary+" "+RM(last.wage)+", PCB "+RM(last.pcb);
  if(b.getAttribute("data-send")==="wa")window.open("https://wa.me/"+PHONE+"?text="+encodeURIComponent(body),"_blank","noopener");
  else location.href="mailto:"+EMAIL+"?subject="+encodeURIComponent(head)+"&body="+encodeURIComponent(body);
});
})();
