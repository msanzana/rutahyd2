import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createRequire} from 'node:module';
const require=createRequire('/tmp/v109d4a-js/package.json');
const {JSDOM}=require('jsdom');
const root=process.argv[2],read=p=>readFileSync(root+'/'+p,'utf8');
const dom=new JSDOM('<main id="root"></main>',{url:'https://rutahd.example',runScripts:'outside-only'}),w=dom.window;
w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
w.HTMLDialogElement.prototype.close=function(){this.open=false;this.dispatchEvent(new w.Event('close'));};
w.HTMLElement.prototype.scrollIntoView=function(){};
const tick=()=>new Promise(r=>setTimeout(r,0)),calls=[];
w.fetch=async(url,options)=>{calls.push([url,options]);return new Response(JSON.stringify({id:9}),{headers:{'Content-Type':'application/json'}});};
w.Response=Response;
w.eval(read('assets/js/catalogos-api.js'));
const client=w.RutaHDApiCatalogos;client.configure('https://rutahd.example');client.authenticate('abc.def.ghi');
await client.counts(2,'SKU',{estado:'EN_CONTEO'});
assert.equal(calls[0][0],'https://rutahd.example/api/v1/inventario/conteos?page=2&buscar=SKU&estado=EN_CONTEO');
assert.equal(calls[0][1].headers.Authorization,'Bearer abc.def.ghi');
await client.countMeasure(9,12,'contar',{cantidad:'0'});
assert.equal(calls[1][0],'https://rutahd.example/api/v1/inventario/conteos/9/lineas/12/contar');
let enabled=false,permitted=true,created=null,measured=null,lastQuery=null,pagers=0;
w.RutaHDAccesos={isAdmin:()=>false,has:()=>permitted};
w.RutaHDListUI={renderPager:()=>{pagers++;},action:(label,fn)=>{const b=w.document.createElement('button');b.textContent=label;b.onclick=fn;return b;}};
const h={id:9,numero_interno:'CNT-9',bodega:'Bodega CI',bodega_id:1,alcance:'SELECTIVO',estado:'EN_CONTEO',conteo_ciego:1,fecha_corte:'2026-11-26'};
w.RutaHDApiCatalogos={
 counts:async(p,q,f)=>{lastQuery=[p,q,f];return {data:[h],total:1,current_page:1,last_page:1,escrituras_habilitadas:enabled};},
 transferWarehouses:async()=>({bodegas:[{id:1,nombre:'Bodega CI'}]}),
 count:async()=>({conteo:h,detalles:[{id:99,producto_id:7,sku:'CI-7',producto:'Producto CI',contado:false,recontado:false}],usuario_actual_id:1,escrituras_habilitadas:enabled,cierre_habilitado:false}),
 countCreate:async data=>{created=data;return {id:9};},
 countMeasure:async(...data)=>{measured=data;return {};},
};
w.RutaHDBuscadorProductos={openSingle:async()=>({id:7,sku:'CI-7',nombre:'Producto CI'})};
w.eval(read('assets/js/inventario-v104.js'));
const host=w.document.querySelector('#root'),button=(n,l)=>[...n.querySelectorAll('button')].find(b=>b.textContent===l),field=(n,l)=>{const e=n.querySelector('[aria-label="'+l+'"]');return e.matches('input,select,textarea')?e:e.querySelector('input,select,textarea');};
w.RutaHDInventarioV104.counts(host);await tick();assert.equal(pagers,2);assert.equal(button(host,'+ Nuevo conteo').hidden,true);
button(host,'Búsqueda avanzada').click();assert.equal(button(host,'Búsqueda avanzada').getAttribute('aria-expanded'),'true');
field(host,'Buscar').value='CI';field(host,'Estado').value='EN_CONTEO';host.querySelector('form').dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();
assert.equal(lastQuery[1],'CI');assert.equal(lastQuery[2].estado,'EN_CONTEO');
button(host,'Ver CNT-9').click();await tick();let d=w.document.querySelector('dialog[open]');assert.ok(d.textContent.includes('CI-7'));assert.ok(!d.querySelector('th')?.textContent.includes('Saldo de corte'));assert.equal(button(d,'Contar CI-7'),undefined);assert.equal(button(d,'Enviar a revisión'),undefined);d.close();
enabled=true;w.RutaHDInventarioV104.counts(host);await tick();assert.equal(button(host,'+ Nuevo conteo').hidden,false);
button(host,'+ Nuevo conteo').click();await tick();d=w.document.querySelector('dialog[open]');assert.equal(field(d,'Código').value,'Automático');field(d,'Bodega').value='1';
button(d,'Buscar producto').click();await tick();button(d,'Iniciar conteo').click();await tick();await tick();
assert.equal(created.productos[0],7);assert.equal(created.conteo_ciego,true);assert.equal(created.bodega_id,1);
d=w.document.querySelector('dialog[open]');button(d,'Contar CI-7').click();d=w.document.querySelector('dialog[open]');field(d,'Cantidad física').value='0';button(d,'Guardar').click();await tick();await tick();assert.equal(measured[0],9);assert.equal(measured[1],99);assert.equal(measured[2],'contar');assert.equal(measured[3].cantidad,'0');
w.document.querySelector('dialog[open]').close();permitted=false;w.RutaHDInventarioV104.counts(host);await tick();assert.equal(button(host,'+ Nuevo conteo').hidden,true);
assert.match(read('assets/js/app-produccion.js'),/RutaHDInventarioV104\.counts\(content\)/);assert.match(read('index.html'),/data-nav="bodega_conteos"/);assert.match(read('index.html'),/inventario-v104\.js\?v=20261126-v109d4a/);
console.log('V109D4A_FRONT_OK');
