(function(){
'use strict';
function draw(box){
 var raw=box.getAttribute('data-swimlog-progress'),pts;try{pts=JSON.parse(raw||'[]');}catch(e){return;}if(pts.length<2)return;
 var canvas=box.querySelector('canvas'),tip=box.querySelector('.swimlog-progress-tooltip'),ctx=canvas.getContext('2d'),dpr=window.devicePixelRatio||1,w=Math.max(320,box.clientWidth),h=Math.max(240,Math.min(380,Math.round(w*.5))),pad={l:58,r:20,t:24,b:48};
 canvas.width=w*dpr;canvas.height=h*dpr;canvas.style.width=w+'px';canvas.style.height=h+'px';ctx.setTransform(dpr,0,0,dpr,0,0);
 var vals=pts.map(function(p){return p.duration;}),min=Math.min.apply(null,vals),max=Math.max.apply(null,vals),range=Math.max(1000,max-min),lo=min-range*.12,hi=max+range*.12;
 function x(i){return pad.l+(w-pad.l-pad.r)*(i/(pts.length-1));}
 function y(v){return pad.t+(h-pad.t-pad.b)*((v-lo)/(hi-lo));}
 function fmt(ms){var s=ms/1000,m=Math.floor(s/60),r=(s-m*60).toFixed(1);return m+':'+(r<10?'0':'')+r;}
 ctx.clearRect(0,0,w,h);ctx.font='12px sans-serif';ctx.fillStyle=getComputedStyle(box).color;ctx.strokeStyle='currentColor';ctx.globalAlpha=.35;
 for(var i=0;i<4;i++){var v=lo+(hi-lo)*i/3,yy=y(v);ctx.beginPath();ctx.moveTo(pad.l,yy);ctx.lineTo(w-pad.r,yy);ctx.stroke();ctx.globalAlpha=1;ctx.fillText(fmt(v),4,yy+4);ctx.globalAlpha=.35;}
 ctx.globalAlpha=1;ctx.lineWidth=2;ctx.beginPath();pts.forEach(function(p,i){var xx=x(i),yy=y(p.duration);if(i===0)ctx.moveTo(xx,yy);else ctx.lineTo(xx,yy);});ctx.stroke();
 pts.forEach(function(p,i){ctx.beginPath();ctx.arc(x(i),y(p.duration),5,0,Math.PI*2);ctx.fill();});
 ctx.textAlign='center';ctx.fillText(pts[0].shortDate,pad.l,h-18);ctx.fillText(pts[pts.length-1].shortDate,w-pad.r,h-18);ctx.textAlign='start';
 function show(ev){var rect=canvas.getBoundingClientRect(),mx=(ev.touches?ev.touches[0].clientX:ev.clientX)-rect.left,nearest=0,best=Infinity;pts.forEach(function(p,i){var d=Math.abs(x(i)-mx);if(d<best){best=d;nearest=i;}});var p=pts[nearest];tip.textContent=p.date+' — '+p.time;tip.hidden=false;tip.style.left=Math.max(0,Math.min(w-180,x(nearest)-80))+'px';tip.style.top=Math.max(0,y(p.duration)-44)+'px';}
 canvas.addEventListener('mousemove',show);canvas.addEventListener('touchstart',show,{passive:true});canvas.addEventListener('mouseleave',function(){tip.hidden=true;});
}
function all(){document.querySelectorAll('.swimlog-progress-chart').forEach(draw);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',all);else all();
var timer;window.addEventListener('resize',function(){clearTimeout(timer);timer=setTimeout(all,150);});
})();