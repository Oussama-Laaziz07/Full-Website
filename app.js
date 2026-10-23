let ads = [], categories = [], cities = [];

function showSection(id) {
  document.querySelectorAll('.page-section').forEach(x => x.classList.add('hidden'));
  document.getElementById('section-' + id)?.classList.remove('hidden');
}
function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
async function api(path, options = {}) {
  const res = await fetch(path, {...options, headers:{...(options.headers||{}), ...(options.body ? {'Content-Type':'application/json'} : {})}});
  let data;
  try { data = await res.json(); } catch { throw new Error('API returned a non-JSON response (' + res.status + ').'); }
  if (!res.ok) throw new Error(data.error || 'Request failed (' + res.status + ')');
  return data;
}
async function loadDatabaseData() {
  const grid = document.getElementById('ads-grid');
  if (grid) grid.textContent = 'Chargement des annonces...';
  try {
    [ads,categories,cities] = await Promise.all([api('/api/ads.php'),api('/api/categories.php'),api('/api/cities.php')]);
    populateDropdowns(); renderAds();
  } catch(e) {
    console.error(e);
    if(grid) grid.textContent = 'Impossible de charger les annonces. Vérifiez les API et les journaux Vercel.';
  }
}
function populateDropdowns() {
  const cf=document.getElementById('category-filter'), yf=document.getElementById('city-filter');
  const pc=document.getElementById('post-category'), py=document.getElementById('post-city');
  if(!cf||!yf||!pc||!py)return;
  cf.innerHTML='<option value="">Toutes les catégories</option>'; yf.innerHTML='<option value="">Toutes les villes</option>';
  pc.replaceChildren(); py.replaceChildren();
  categories.forEach(x=>{cf.add(new Option(x.name,x.name));pc.add(new Option(x.name,String(x.id)));});
  cities.forEach(x=>{yf.add(new Option(x.name,x.name));py.add(new Option(x.name,String(x.id)));});
}
function renderAds(items=ads) {
  const grid=document.getElementById('ads-grid'); if(!grid)return;
  if(!items.length){grid.innerHTML='<p class="text-gray-500 col-span-full text-center py-8">Aucune annonce trouvée.</p>';return;}
  grid.innerHTML=items.map(a=>`<article class="bg-white border rounded-xl overflow-hidden shadow-sm hover:shadow-md transition flex flex-col">
    <img src="${esc(a.image||'images/Offria.jpg')}" class="h-48 w-full object-cover" alt="${esc(a.title)}" onerror="this.src='images/Offria.jpg'">
    <div class="p-4 flex-grow flex flex-col justify-between"><div>
    <span class="text-xs font-semibold uppercase text-orange-600 bg-orange-50 px-2 py-1 rounded">${esc(a.category)}</span>
    <h3 class="font-bold text-lg text-gray-900 mt-2 line-clamp-1">${esc(a.title)}</h3>
    <p class="text-xl font-extrabold text-gray-900 mt-1">${esc(a.price)} <span class="text-sm text-gray-500 font-normal">DH</span></p>
    <p class="text-xs text-gray-400 mt-1"><i class="fa-solid fa-location-dot mr-1"></i>${esc(a.city)}</p></div>
    <div class="mt-4 pt-3 border-t"><button type="button" onclick="viewAdDetails(${Number(a.id)})" class="text-orange-main font-semibold text-sm hover:underline">Voir détails &rarr;</button></div></div></article>`).join('');
}
function filterAds() {
  const q=(document.getElementById('search-input')?.value||'').toLowerCase();
  const c=document.getElementById('category-filter')?.value||'', y=document.getElementById('city-filter')?.value||'';
  renderAds(ads.filter(a=>String(a.title).toLowerCase().includes(q)&&(!c||a.category===c)&&(!y||a.city===y)));
}
function viewAdDetails(id) {
  const a=ads.find(x=>Number(x.id)===Number(id)); if(!a)return;
  const title=document.getElementById('detail-title'); title.dataset.adId=String(a.id);
  document.getElementById('detail-img').src=a.image||'images/Offria.jpg';
  document.getElementById('detail-category').textContent=a.category;
  title.textContent=a.title; document.getElementById('detail-price').textContent=a.price+' DH';
  document.getElementById('detail-city').textContent='Ville: '+a.city;
  document.getElementById('detail-desc').textContent=a.description; showSection('details');
}
async function handlePostAd(e) {
  e.preventDefault();
  const ad={title:document.getElementById('post-title').value.trim(),price:Number(document.getElementById('post-price').value),
    category_id:Number(document.getElementById('post-category').value),city_id:Number(document.getElementById('post-city').value),
    image:document.getElementById('post-img').value.trim(),description:document.getElementById('post-desc').value.trim()};
  try { await api('/api/ads.php',{method:'POST',body:JSON.stringify(ad)}); e.target.reset(); await loadDatabaseData(); showSection('home'); alert('Annonce publiée avec succès !'); }
  catch(err){alert('Erreur : '+err.message);}
}
async function sendMessage(e) {
  e.preventDefault();
  try {
    await api('/api/messages.php',{method:'POST',body:JSON.stringify({ad_id:Number(document.getElementById('detail-title').dataset.adId),
      name:document.getElementById('msg-name').value.trim(),email:document.getElementById('msg-email').value.trim(),
      message:document.getElementById('msg-text').value.trim()})});
    e.target.reset(); alert('Message envoyé !');
  } catch(err){alert('Erreur : '+err.message);}
}
document.addEventListener('DOMContentLoaded',loadDatabaseData);
