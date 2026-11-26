import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createRequire} from 'node:module';
const require=createRequire('/tmp/v109c6b-js/package.json');
const {JSDOM}=require('jsdom');
const root=process.argv[2],read=p=>readFileSync(root+'/'+p,'utf8');
const dom=new JSDOM('<main id="root"></main>',{url:'https://rutahd.example',runScripts:'outside-only'});
const w=dom.window;
w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
w.HTMLDialogElement.prototype.close=function(){this.open=false;this.dispatchEvent(new w.Event('close'));};
w.HTMLElement.prototype.scrollIntoView=function(){};
const tick=()=>new Promise(r=>setTimeout(r,0)),calls=[];
w.fetch=async(url,options)=>{calls.push([url,options]);return new Response(JSON.stringify({id:9}),{headers:{'Content-Type':'application/json'}});};
w.Response=Response;
w.eval(read('assets/js/catalogos-api.js'));
const client=w.RutaHDApiCatalogos;client.configure('https://rutahd.example');client.authenticate('abc.def.ghi');
await client.transfers(2,'SKU',{estado:'PARCIAL'});
assert.equal(calls[0][0],'https://rutahd.example/api/v1/inventario/traspasos?page=2&buscar=SKU&estado=PARCIAL');
assert.equal(calls[0][1].headers.Authorization,'Bearer abc.def.ghi');
await client.transferReceiptAction(9,12,'confirmar',{version:2});
assert.equal(calls[1][0],'https://rutahd.example/api/v1/inventario/traspasos/9/recepciones/12/confirmar');
let enabled=false,permitted=true,created=null,receipt=null,lastQuery=null,pagers=0;
w.RutaHDAccesos={isAdmin:()=>false,has:()=>permitted};
w.RutaHDListUI={renderPager:()=>{pagers++;},action:(label,fn)=>{const b=w.document.createElement('button');b.textContent=label;b.onclick=fn;return b;}};
const h={id:9,numero_interno:'TRP-9',bodega_origen:'Origen',bodega_destino:'Destino',modalidad:'DESPACHO_RECEPCION',estado_traspaso:'PARCIAL',estado_operacion:'INV_CONFIRMADA',justificacion:'Prueba CI'};
w.RutaHDApiCatalogos={
 transfers:async(p,q,f)=>{lastQuery=[p,q,f];return {data:[h],total:1,current_page:1,last_page:1,escrituras_habilitadas:enabled};},
 transferWarehouses:async()=>({bodegas:[{id:1,nombre:'Origen'},{id:2,nombre:'Destino'}]}),
 transfer:async()=>({traspaso:h,detalles:[{id:99,producto_id:7,sku:'CI-7',producto:'Producto CI',cantidad_solicitada:'3',cantidad_despachada:'3',cantidad_recibida:'1',pendiente_recepcion:'2',costo_cpp_snapshot:'500'}],operaciones:[],recepcion_activa:null,escrituras_habilitadas:enabled}),
 transferCreate:async data=>{created=data;return {id:9};},
 transferReceiptCreate:async(id,data)=>{receipt=[id,data];return {id:9};}
};
w.RutaHDBuscadorProductos={openSingle:async()=>({id:7,sku:'CI-7',nombre:'Producto CI'})};
w.eval(read('assets/js/inventario-v104.js'));
const host=w.document.querySelector('#root'),button=(n,l)=>[...n.querySelectorAll('button')].find(b=>b.textContent===l),field=(n,l)=>n.querySelector('[aria-label="'+l+'"]');
w.RutaHDInventarioV104.transfers(host);await tick();assert.equal(pagers,2);assert.equal(button(host,'+ Nuevo traspaso').hidden,true);
button(host,'Búsqueda avanzada').click();assert.equal(button(host,'Búsqueda avanzada').getAttribute('aria-expanded'),'true');
field(host,'Buscar').value='CI';field(host,'Estado').value='PARCIAL';host.querySelector('form').dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();
assert.equal(lastQuery[1],'CI');assert.equal(lastQuery[2].estado,'PARCIAL');
button(host,'Ver TRP-9').click();await tick();let d=w.document.querySelector('dialog[open]');assert.ok(d.textContent.includes('CI-7'));assert.equal(button(d,'Nueva recepción'),undefined);d.close();
enabled=true;w.RutaHDInventarioV104.transfers(host);await tick();assert.equal(button(host,'+ Nuevo traspaso').hidden,false);
button(host,'+ Nuevo traspaso').click();await tick();d=w.document.querySelector('dialog[open]');assert.equal(field(d,'Código').value,'Automático');field(d,'Origen').value='1';field(d,'Destino').value='2';field(d,'Modalidad').value='DESPACHO_RECEPCION';
button(d,'Buscar producto').click();await tick();field(d,'Cantidad CI-7').value='1,12345678';field(d,'Justificación obligatoria').value='Solicitud de prueba CI';button(d,'Guardar').click();await tick();await tick();
assert.equal(created.items[0].producto_id,7);assert.equal(created.items[0].cantidad,'1.12345678');assert.equal(created.bodega_destino_id,2);
d=w.document.querySelector('dialog[open]');button(d,'Nueva recepción').click();await tick();d=w.document.querySelector('dialog[open]');field(d,'Cantidad CI-7').value='1';field(d,'Justificación obligatoria').value='Recepción de prueba CI';button(d,'Guardar').click();await tick();await tick();assert.equal(receipt[0],9);assert.equal(receipt[1].items[0].producto_id,7);assert.equal(receipt[1].items[0].cantidad,'1');
w.document.querySelector('dialog[open]').close();permitted=false;w.RutaHDInventarioV104.transfers(host);await tick();assert.equal(button(host,'+ Nuevo traspaso').hidden,true);
assert.match(read('assets/js/app-produccion.js'),/RutaHDInventarioV104\.transfers\(content\)/);assert.match(read('index.html'),/data-nav="bodega_traspasos"/);assert.match(read('index.html'),/inventario-v104\.js\?v=20261126-v109c6b/);
console.log('V109C6B_FRONT_OK');
