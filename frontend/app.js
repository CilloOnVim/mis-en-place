const API_BASE = 'http://localhost:8080/api';

// ── Initialization & MPA Routing ─────────────────────────────────
window.onload = () => {
  const token = localStorage.getItem('jwt');
  const path = window.location.pathname;
  const isAuthPage = path.endsWith('index.html') || path === '/' || path.endsWith('/');

  if (token) {
    if (isAuthPage) {
      routeUser(); // If logged in and on the login page, kick them to their dashboard
    } else {
      initPage(); // If on a dashboard, load the data
    }
  } else {
    if (!isAuthPage) window.location.href = 'index.html'; // If not logged in, kick them to Auth
  }

  // Set date in topbars
  const dateStr = new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  if (document.getElementById('topbar-date')) document.getElementById('topbar-date').textContent = dateStr;
  if (document.getElementById('admin-date-display')) document.getElementById('admin-date-display').textContent = dateStr;
};

function routeUser() {
  const role = localStorage.getItem('mep_role');
  if (role === 'admin') window.location.href = 'admin.html';
  else window.location.href = 'dashboard.html';
}

function initPage() {
  const role = localStorage.getItem('mep_role');
  if (document.getElementById('admin-wrapper') && role === 'admin') {
    loadAdminData();
    loadAdminTransactions();
    loadAdminComments();
    loadAdminPosts(); // <--- ADD THIS LINE
  } else if (document.getElementById('dashboard-wrapper')) {
    loadProfileData();
    loadFeed();
  }
}

function logoutUser() {
  localStorage.removeItem('jwt');
  localStorage.removeItem('mep_role');
  window.location.href = 'index.html';
}

// ── Toast helper ─────────────────────────────────────
function showToast(msg, duration = 3200) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), duration);
}

// ── Auth Logic ─────────────────────────────────────
function toggleAuth(type) {
  const authWrapper = document.getElementById('auth-wrapper');
  if (!authWrapper) return;

  if (type === 'login') {
    document.getElementById('register-section').classList.add('hidden');
    document.getElementById('login-section').classList.remove('hidden');
  } else {
    document.getElementById('login-section').classList.add('hidden');
    document.getElementById('register-section').classList.remove('hidden');
  }
}

async function registerUser() {
  const email = document.getElementById('reg-email').value;
  const password = document.getElementById('reg-password').value;
  try {
    const res = await fetch(`${API_BASE}/auth/register.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password })
    });
    if (res.ok) { showToast('🎉 Account created! Please log in.'); toggleAuth('login'); }
    else { const data = await res.json(); showToast('❌ ' + data.error); }
  } catch (e) { showToast('🔌 Server connection failed.'); }
}

async function loginUser() {
  const email = document.getElementById('login-email').value;
  const password = document.getElementById('login-password').value;
  try {
    const res = await fetch(`${API_BASE}/auth/login.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password })
    });
    const data = await res.json();
    if (res.ok) {
      localStorage.setItem('jwt', data.token);
      localStorage.setItem('mep_role', data.role || 'user');
      routeUser();
    } else { showToast('❌ ' + data.error); }
  } catch (e) { showToast('🔌 Server connection failed.'); }
}

// ── Password Reset Engine ─────────────────────────────
function openForgotPasswordModal() { if (document.getElementById('forgotPasswordModal')) document.getElementById('forgotPasswordModal').classList.add('open'); }
function closeForgotPasswordModal() { if (document.getElementById('forgotPasswordModal')) { document.getElementById('forgotPasswordModal').classList.remove('open'); document.getElementById('reset-email').value = ''; } }
function closeResetPasswordModal() { if (document.getElementById('resetPasswordModal')) { document.getElementById('resetPasswordModal').classList.remove('open'); document.getElementById('reset-token').value = ''; document.getElementById('reset-new-password').value = ''; } }

async function requestPasswordReset() {
  const email = document.getElementById('reset-email').value;
  if (!email) { showToast('⚠️ Please enter your email.'); return; }
  try {
    const res = await fetch(`${API_BASE}/auth/request_reset.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email })
    });
    const data = await res.json();
    if (res.ok) {
      closeForgotPasswordModal();
      prompt("DEVELOPER OVERRIDE - COPY THIS EXACT TOKEN:", data.dev_token);
      document.getElementById('resetPasswordModal').classList.add('open');
    } else { showToast('❌ ' + (data.error || 'Failed to request reset.')); }
  } catch (e) { showToast('🔌 Network error.'); }
}

async function submitPasswordReset() {
  const token = document.getElementById('reset-token').value.trim();
  const new_password = document.getElementById('reset-new-password').value;
  if (!token || new_password.length < 6) { showToast('⚠️ Valid token and min 6-char password required.'); return; }
  try {
    const res = await fetch(`${API_BASE}/auth/reset_password.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token, new_password })
    });
    const data = await res.json();
    if (res.ok) {
      closeResetPasswordModal();
      showToast('✅ Password reset successful. You can now log in.');
    } else { showToast('❌ ' + (data.error || 'Failed to reset password.')); }
  } catch (e) { showToast('🔌 Network error.'); }
}

// ── Dashboard User Data ─────────────────────────────────────
async function loadProfileData() {
  const token = localStorage.getItem('jwt');
  try {
    const res = await fetch(`${API_BASE}/profile/dashboard.php`, { headers: { 'Authorization': `Bearer ${token}` } });
    if (res.ok) {
      const data = await res.json();
      const name = data.data.display_name;
      const pts = data.data.points_balance || 0;
      document.getElementById('sidebar-name').innerText = name;
      document.getElementById('topbar-name').innerText = name.split(' ')[0];
      document.getElementById('wallet-balance').innerHTML = `${Number(pts).toLocaleString()} <span class="wallet-pts-label">pts</span>`;

      const ptsWidget = document.querySelector('.points-big');
      if (ptsWidget) ptsWidget.textContent = Number(pts).toLocaleString();
      const pct = Math.min((pts / 2000) * 100, 100);
      const fill = document.querySelector('.points-bar-inner');
      if (fill) fill.style.width = pct + '%';
      const wfill = document.getElementById('wallet-fill');
      if (wfill) wfill.style.width = pct + '%';
    }
  } catch (e) { }
}

async function submitProfileUpdate() {
  const token = localStorage.getItem('jwt');
  const newName = document.getElementById('edit-name').value;
  if (!newName) return;
  try {
    const res = await fetch(`${API_BASE}/profile/update_profile.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }, body: JSON.stringify({ display_name: newName })
    });
    if (res.ok) { closeEditProfile(); showToast('✅ Profile updated!'); loadProfileData(); }
  } catch (e) { }
}

// ── Marketplace ────────────────────────────────────────
async function unlockRecipe(postId, cost) {
  const token = localStorage.getItem('jwt');
  if (!confirm(`Are you sure you want to spend ${cost} points to unlock this recipe?`)) return;
  try {
    const spendRes = await fetch(`${API_BASE}/profile/spend_points.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }, body: JSON.stringify({ points: cost })
    });
    if (!spendRes.ok) { const spendData = await spendRes.json(); showToast('❌ ' + spendData.error); return; }

    const unlockRes = await fetch(`${API_BASE}/posts/unlock_recipe.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }, body: JSON.stringify({ post_id: postId, points_paid: cost })
    });
    if (unlockRes.ok) { showToast('🎉 Recipe unlocked successfully!'); loadProfileData(); loadFeed(); }
    else { showToast('⚠️ Points deducted but failed to unlock. Contact support.'); }
  } catch (e) { showToast('🔌 Network error during checkout.'); }
}

async function buyPoints(points, price) {
  const token = localStorage.getItem('jwt');
  const buyerName = document.getElementById('sidebar-name').innerText;
  if (!confirm(`Purchase ${points} points for ₱${price}?`)) return;
  try {
    const res = await fetch(`${API_BASE}/profile/buy_points.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }, body: JSON.stringify({ points: points, php_amount: price, buyer_name: buyerName })
    });
    if (res.ok) { showToast(`✅ Purchased ${points} points!`); loadProfileData(); }
    else { showToast('❌ Transaction failed. Try again.'); }
  } catch (e) { showToast('🔌 Network connection failed.'); }
}

// ── Feed & Posts ─────────────────────────────────────────
async function loadFeed(feedType = 'all') {
  const token = localStorage.getItem('jwt');
  const feedContainer = document.getElementById('recipe-feed');
  if (!feedContainer) return;

  feedContainer.innerHTML = `
    <div style="background:var(--card); border-radius:var(--radius-lg); border:1px solid var(--border); overflow:hidden; margin-bottom:20px;">
      <div style="height:180px; background: linear-gradient(90deg, var(--bg) 25%, var(--bg-deep) 50%, var(--bg) 75%); background-size:200% 100%; animation: shimmer 1.8s infinite;"></div>
      <div style="padding:20px 22px;">
        <div style="height:11px; background:var(--bg); border-radius:6px; margin-bottom:10px; width:28%; animation: shimmer 1.8s infinite; background-size:200% 100%;"></div>
        <div style="height:20px; background:var(--bg); border-radius:6px; margin-bottom:8px; width:65%; animation: shimmer 1.8s infinite; background-size:200% 100%;"></div>
        <div style="height:13px; background:var(--bg); border-radius:6px; width:88%; animation: shimmer 1.8s infinite; background-size:200% 100%;"></div>
      </div>
    </div>`;


  try {
    // The Smart Router: Hit the global feed OR the user's private vault
    const endpoint = feedType === 'saved' ? '/posts/get_saved_posts.php' : '/posts/get_posts.php';
    const res = await fetch(`${API_BASE}${endpoint}?_=${Date.now()}`, { headers: { 'Authorization': `Bearer ${token}` } });
    const data = await res.json();
    if (res.ok && data.data.length > 0) {
      feedContainer.innerHTML = '';
      const categoryEmoji = { 'Filipino Classics': '🍲', 'Modern Filipino': '🍽️', 'Healthy': '🥗', 'Desserts': '🍮', 'Street Food': '🍢', 'Drinks': '🥤', 'default': '🍳' };
      const categoryGrad = { 'Filipino Classics': 'linear-gradient(135deg,#C4713A,#E8A44A)', 'Modern Filipino': 'linear-gradient(135deg,#3D2314,#C4713A)', 'Healthy': 'linear-gradient(135deg,#4A5C3A,#7C8C6E)', 'Desserts': 'linear-gradient(135deg,#8B5A2B,#E8A44A)', 'Street Food': 'linear-gradient(135deg,#C4713A,#8B5A2B)', 'Drinks': 'linear-gradient(135deg,#2A4A5A,#3D7A8A)', 'default': 'linear-gradient(135deg,#C4713A,#E8A44A)' };

      data.data.forEach((post, idx) => {
        const emoji = categoryEmoji[post.category] || categoryEmoji['default'];
        const grad = categoryGrad[post.category] || categoryGrad['default'];
        let ingredientsHTML = '';

        if (post.locked) {
          ingredientsHTML = `
            <div class="locked-overlay">
              <div class="locked-icon">🔒</div>
              <div class="locked-title">Premium Recipe</div>
              <div class="locked-sub">Unlock the full ingredient list and smart scaling engine to cook this dish.</div>
              <button class="unlock-btn" onclick="unlockRecipe(${post.id}, ${post.points_cost})">🏅 Unlock for ${post.points_cost} pts</button>
            </div>`;
        } else {
          try {
            const parsedData = typeof post.recipe_data === 'string' ? JSON.parse(post.recipe_data) : (post.recipe_data || {});
            const ingredientsList = Array.isArray(parsedData) ? parsedData : (parsedData.ingredients || []);
            const baseServings = parsedData.base_servings || 1;
            if (ingredientsList.length > 0) {
              ingredientsHTML = `
                <div style="margin: 14px 0; padding: 16px; background: rgba(196,113,58,0.04); border-radius: 14px; border: 1.5px dashed rgba(196,113,58,0.25);">
                  <div class="scaler-bar">
                    <span class="scaler-label">🛒 Ingredients</span>
                    <div class="scaler-control">
                      <button class="scale-btn" onclick="scaleRecipe(this, -1)">−</button>
                      <span class="serving-display" data-base="${baseServings}" data-current="${baseServings}">${baseServings} Serving${baseServings !== 1 ? 's' : ''}</span>
                      <button class="scale-btn" onclick="scaleRecipe(this, 1)">+</button>
                    </div>
                  </div>
                  <ul class="ingredient-grid">`;
              ingredientsList.forEach(ing => {
                ingredientsHTML += `<li><strong><span class="ing-val" data-base-amt="${ing.amount}">${ing.amount}</span><span class="ing-unit-label"> ${ing.unit}</span></strong>&nbsp;${ing.ingredient}</li>`;
              });
              ingredientsHTML += '</ul></div>';
            }
          } catch (e) { console.error('Error parsing ingredients for post ID: ' + post.id); }
        }

        let photoStyle = `background: ${grad};`;
        let emojiLayer = `<div class="recipe-photo-emoji">${emoji}</div>`;
        if (post.image_url) {
          photoStyle = `background: url('${API_BASE}/posts/uploads/${post.image_url}') center/cover no-repeat;`;
          emojiLayer = '';
        }

        feedContainer.innerHTML += `
          <div class="recipe-card" style="animation-delay:${idx * 0.06}s">
            <div class="recipe-photo" style="${photoStyle}">
              ${emojiLayer}
              <div class="recipe-photo-labels">
                <span class="recipe-tag">${post.category}</span>
                ${post.locked ? `<span class="recipe-lock-tag">🔒 ${post.points_cost} pts</span>` : ''}
              </div>
            </div>
            <div class="recipe-body">
              <div class="recipe-title">${post.title}</div>
              <div class="recipe-desc">${post.description}</div>
              <div class="recipe-author-row">
                <div class="author-chip">👨‍🍳</div>
                <div class="author-name">by <strong>${post.author_name}</strong></div>
                <div class="recipe-likes">
                  <button class="like-btn" onclick="toggleLike(${post.id}, this)" style="${post.has_liked ? 'color: var(--error); background: rgba(192,57,43,0.08);' : ''}">
                    <span class="heart-icon">${post.has_liked ? '❤️' : '🤍'}</span> 
                    <span class="like-count">${post.likes_count || 0}</span>
                  </button>
                  <button class="like-btn" onclick="toggleComments(${post.id})">💬 Reply</button>
                  <button class="like-btn" onclick="toggleSave(${post.id}, this)">🔖 Save</button>
                </div>
              </div>
              ${ingredientsHTML}
            </div>

            <div class="comments-section hidden" id="comments-section-${post.id}" style="border-top: 1px solid var(--border); padding: 16px 22px; background: rgba(0,0,0,0.01);">
              <div id="comments-list-${post.id}" style="max-height: 250px; overflow-y: auto; margin-bottom: 12px; display: flex; flex-direction: column; gap: 10px;">
                <div style="font-size: 12px; color: var(--muted);">Loading comments...</div>
              </div>
              <div style="display:flex; gap:8px;">
                <input type="text" id="comment-input-${post.id}" placeholder="Add a comment..." style="flex:1; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; outline:none; font-family: 'DM Sans'; font-size: 13px; background: var(--white);">
                <button class="btn-sm" style="background: var(--clay); color: white; border: none;" onclick="submitComment(${post.id})">Post</button>
              </div>
            </div>

          </div>`;

      });
    } else {
      feedContainer.innerHTML = `<div style="text-align:center; padding:48px 0; color:var(--muted);"><div style="font-size:48px; margin-bottom:12px;">🍳</div><div style="font-family:'Playfair Display',serif; font-size:20px; color:var(--dark); margin-bottom:8px;">No recipes yet</div><div style="font-size:14px;">Be the first to share something delicious!</div></div>`;
    }
  } catch (e) { feedContainer.innerHTML = `<div style="text-align:center; padding:40px 0; color:var(--error);"><div style="font-size:32px; margin-bottom:10px;">⚡</div><div style="font-size:14px;">Failed to load feed. Check your connection.</div></div>`; }
}

async function submitPost() {
  const token = localStorage.getItem('jwt');
  const title = document.getElementById('post-title').value;
  const desc = document.getElementById('post-desc').value;
  const category = document.getElementById('post-category').value;
  const authorName = document.getElementById('sidebar-name').innerText;
  const price = parseInt(document.getElementById('post-price').value) || 0;
  const servings = parseInt(document.getElementById('post-servings').value) || 1;

  if (!title || !desc) { showToast('⚠️ Title and description are required.'); return; }

  const ingredientRows = document.querySelectorAll('#ingredients-container > div');
  const recipeDataArray = [];
  ingredientRows.forEach(row => {
    const name = row.querySelector('.ing-name').value.trim();
    const amt = row.querySelector('.ing-amt').value;
    const unit = row.querySelector('.ing-unit').value;
    if (name) recipeDataArray.push({ ingredient: name, amount: parseFloat(amt) || 0, unit: unit });
  });

  const advancedRecipeData = { base_servings: servings, ingredients: recipeDataArray };
  const formData = new FormData();
  formData.append('title', title);
  formData.append('description', desc);
  formData.append('category', category);
  formData.append('author_name', authorName);
  formData.append('points_cost', price);
  formData.append('recipe_data', JSON.stringify(advancedRecipeData));

  const photoInput = document.getElementById('photo-input');
  if (photoInput && photoInput.files.length > 0) formData.append('photo', photoInput.files[0]);

  try {
    const res = await fetch(`${API_BASE}/posts/create_post.php`, {
      method: 'POST', headers: { 'Authorization': `Bearer ${token}` }, body: formData
    });
    if (res.ok) { closeModal(); showToast('🚀 Recipe published! +50 points earned.'); loadFeed(); loadProfileData(); }
    else { const data = await res.json(); showToast('❌ ' + data.error); }
  } catch (e) { showToast('🔌 Failed to publish recipe.'); }
}

async function toggleLike(postId, btnElement) {
  const token = localStorage.getItem('jwt');
  const countSpan = btnElement.querySelector('.like-count');
  const iconSpan = btnElement.querySelector('.heart-icon');

  let currentCount = parseInt(countSpan.innerText);
  const isCurrentlyLiked = iconSpan.innerText === '❤️';

  if (isCurrentlyLiked) {
    iconSpan.innerText = '🤍'; countSpan.innerText = Math.max(0, currentCount - 1);
    btnElement.style.color = 'var(--muted)'; btnElement.style.background = 'none';
  } else {
    iconSpan.innerText = '❤️'; countSpan.innerText = currentCount + 1;
    btnElement.style.color = 'var(--error)'; btnElement.style.background = 'rgba(192,57,43,0.08)';
  }

  try {
    const res = await fetch(`${API_BASE}/posts/toggle_like.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }, body: JSON.stringify({ post_id: postId })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error);
    countSpan.innerText = data.new_count;
  } catch (e) {
    showToast('🔌 Failed to toggle like.');
    if (isCurrentlyLiked) {
      iconSpan.innerText = '❤️'; countSpan.innerText = currentCount;
      btnElement.style.color = 'var(--error)'; btnElement.style.background = 'rgba(192,57,43,0.08)';
    } else {
      iconSpan.innerText = '🤍'; countSpan.innerText = currentCount;
      btnElement.style.color = 'var(--muted)'; btnElement.style.background = 'none';
    }
  }
}

async function toggleSave(postId, btnElement) {
  const token = localStorage.getItem('jwt');

  // Optimistic UI update: instantly change the button text so it feels lightning fast
  const isCurrentlySaved = btnElement.innerText.includes('Saved');
  if (isCurrentlySaved) {
    btnElement.innerText = '🔖 Save';
    btnElement.style.color = 'var(--muted)';
    btnElement.style.background = 'none';
  } else {
    btnElement.innerText = '🏷️ Saved';
    btnElement.style.color = 'var(--clay)';
    btnElement.style.background = 'rgba(196,113,58,0.1)';
  }

  try {
    const res = await fetch(`${API_BASE}/posts/toggle_save.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ post_id: postId })
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error);

    showToast(data.message); // Will say "Saved!" or "Removed"

  } catch (e) {
    showToast('🔌 Failed to save recipe.');
    // Revert the UI if the server actually rejected it
    if (isCurrentlySaved) {
      btnElement.innerText = '🏷️ Saved';
      btnElement.style.color = 'var(--clay)';
      btnElement.style.background = 'rgba(196,113,58,0.1)';
    } else {
      btnElement.innerText = '🔖 Save';
      btnElement.style.color = 'var(--muted)';
      btnElement.style.background = 'none';
    }
  }
}

function switchFeed(type) {
  const tabAll = document.getElementById('tab-all');
  const tabSaved = document.getElementById('tab-saved');

  if (type === 'saved') {
    tabAll.style.background = 'transparent'; tabAll.style.color = 'var(--muted)'; tabAll.style.border = '1.5px solid var(--border)';
    tabSaved.style.background = 'var(--clay)'; tabSaved.style.color = 'white'; tabSaved.style.border = 'none';
  } else {
    tabSaved.style.background = 'transparent'; tabSaved.style.color = 'var(--muted)'; tabSaved.style.border = '1.5px solid var(--border)';
    tabAll.style.background = 'var(--clay)'; tabAll.style.color = 'white'; tabAll.style.border = 'none';
  }

  loadFeed(type);
}

// ── Admin Data ──────────────────────────────────────
async function loadAdminData() {
  const token = localStorage.getItem('jwt');
  try {
    // CACHE BUSTER ADDED: Forces the browser to fetch fresh data
    const res = await fetch(`${API_BASE}/auth/admin_get_users.php`, {
      headers: { 'Authorization': `Bearer ${token}` },
      cache: 'no-store'
    });

    const data = await res.json();
    if (res.ok) {
      document.getElementById('admin-total-users').innerText = data.data.length;
      const tableBody = document.getElementById('admin-user-table');
      tableBody.innerHTML = '';

      data.data.forEach(user => {
        // Safe default if the column was just added
        const currentStatus = user.status || 'active';

        const roleDropdown = `
          <select onchange="adminUpdateRole(${user.id}, this.value)" style="padding: 4px 8px; border-radius: 999px; border: 1px solid var(--border); font-size: 11px; font-weight: 500; font-family: 'DM Sans'; text-transform: uppercase; cursor: pointer; outline: none; background: ${user.role === 'admin' ? 'rgba(139,26,26,0.08)' : user.role === 'chef' ? 'rgba(34,197,94,0.08)' : 'rgba(196,113,58,0.08)'}; color: ${user.role === 'admin' ? 'var(--admin-accent)' : user.role === 'chef' ? '#16a34a' : 'var(--clay)'};">
            <option value="user" ${user.role === 'user' ? 'selected' : ''}>USER</option>
            <option value="chef" ${user.role === 'chef' ? 'selected' : ''}>CHEF</option>
            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>ADMIN</option>
          </select>
        `;

        const banButton = currentStatus === 'banned'
          ? `<button class="btn-sm" style="background:var(--success); color:white; border:none; padding: 6px 12px; margin-left: 8px;" onclick="adminToggleBan(${user.id}, '${user.email}')">🟢 Unban</button>`
          : `<button class="btn-sm" style="background:var(--error); color:white; border:none; padding: 6px 12px; margin-left: 8px;" onclick="adminToggleBan(${user.id}, '${user.email}')">🔨 Ban</button>`;

        const statusBadge = currentStatus === 'banned'
          ? `<span style="color: var(--error); font-size: 11px; font-weight: bold; background: rgba(192,57,43,0.1); padding: 2px 6px; border-radius: 4px;">BANNED</span>`
          : `<span style="color: var(--success); font-size: 11px; font-weight: bold;">ACTIVE</span>`;

        tableBody.innerHTML += `
          <tr style="${currentStatus === 'banned' ? 'opacity: 0.6; background: rgba(0,0,0,0.02);' : ''}">
            <td>
              <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:30px; height:30px; border-radius:8px; background:linear-gradient(135deg,var(--clay),var(--amber)); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0;">👤</div>
                <div>
                  <div style="line-height: 1;">${user.email}</div>
                  <div style="margin-top: 4px;">${statusBadge}</div>
                </div>
              </div>
            </td>
            <td>${roleDropdown}</td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td>${banButton}</td>
          </tr>
        `;
      });

    }
  } catch (e) { }
}

async function loadAdminTransactions() {
  const token = localStorage.getItem('jwt');
  try {
    const res = await fetch(`${API_BASE}/profile/admin_get_transactions.php`, { headers: { 'Authorization': `Bearer ${token}` } });
    const data = await res.json();
    if (res.ok) {
      document.getElementById('admin-total-revenue').innerText = '₱ ' + Number(data.totals.revenue || 0).toLocaleString();
      document.getElementById('admin-total-points').innerText = Number(data.totals.points || 0).toLocaleString();
      const list = document.getElementById('admin-transactions-list');

      // CORRECT LOCATION: Building the transaction list UI
      list.innerHTML = data.data.length === 0 ? '<div style="text-align:center; padding:24px 0; color:var(--muted); font-size:12px;">No transactions yet.</div>' : '';

      data.data.forEach(tx => {
        list.innerHTML += `<div class="tx-item"><div><div style="font-size:13px; font-weight:500; color:var(--dark);">${tx.buyer_name}</div><div style="font-size:11px; color:var(--muted); margin-top:2px;">${new Date(tx.created_at).toLocaleString()}</div></div><div style="text-align:right;"><div style="font-size:13px; color:var(--amber); font-weight:bold;">+${tx.points_added} pts</div><div style="font-size:11px; color:#16a34a; font-weight:500;">₱ ${tx.php_amount}</div></div></div>`;
      });
    }
  } catch (e) { }
}

async function loadAdminComments() {
  const token = localStorage.getItem('jwt');
  try {
    const res = await fetch(`${API_BASE}/posts/admin_get_comments.php`, { headers: { 'Authorization': `Bearer ${token}` } });
    const data = await res.json();
    if (res.ok) {
      const tableBody = document.getElementById('admin-comments-table');
      if (!tableBody) return;
      tableBody.innerHTML = '';

      if (data.data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--muted);">Platform is clean. No comments.</td></tr>';
        return;
      }

      data.data.forEach(c => {
        tableBody.innerHTML += `
          <tr>
            <td><strong>${c.author_name}</strong></td>
            <td style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${c.comment_text}">${c.comment_text}</td>
            <td style="color:var(--clay); font-size: 12px;">${c.post_title || 'Unknown Post'}</td>
            <td style="font-size: 12px;">${new Date(c.created_at).toLocaleDateString()}</td>
            <td>
              <button class="btn-sm" style="background:var(--error); color:white; border:none; padding: 6px 12px;" onclick="adminDeleteComment(${c.id})">🗑️ Scrub</button>
            </td>
          </tr>
        `;
      });
    }
  } catch (e) { console.error("Failed to load moderation queue."); }
}

async function adminDeleteComment(commentId) {
  const token = localStorage.getItem('jwt');
  if (!confirm("Are you certain? This executes a hard delete on the database row.")) return;

  try {
    const res = await fetch(`${API_BASE}/posts/admin_delete_comment.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }, body: JSON.stringify({ comment_id: commentId })
    });

    if (res.ok) {
      showToast('🗑️ Comment scrubbed from the platform.');
      loadAdminComments(); // Instantly rebuild the table
    } else {
      const data = await res.json();
      showToast('❌ ' + data.error);
    }
  } catch (e) { showToast('🔌 Database connection failed.'); }
}

// ── Admin Post Moderation ─────────────────────────────────
window.adminPostsCache = []; // Holds the data in memory

async function loadAdminPosts() {
  const token = localStorage.getItem('jwt');
  try {
    const res = await fetch(`${API_BASE}/posts/admin_get_posts.php?_=${Date.now()}`, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await res.json();

    if (res.ok) {
      window.adminPostsCache = data.data; // Cache the data for the Inspection Modal

      const tableBody = document.getElementById('admin-posts-table');
      if (!tableBody) return;
      tableBody.innerHTML = '';

      if (data.data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--muted);">No recipes exist on the platform.</td></tr>';
        return;
      }

      data.data.forEach(p => {
        tableBody.innerHTML += `
          <tr>
            <td><strong>${p.author_name}</strong></td>
            <td style="color:var(--clay); font-weight: 500;">${p.title}</td>
            <td style="font-size: 11px;">
              <span style="background: rgba(196,113,58,0.1); padding: 3px 8px; border-radius: 6px;">${p.category}</span>
            </td>
            <td style="font-size: 12px;">${new Date(p.created_at).toLocaleDateString()}</td>
            <td>
              <button class="btn-sm" style="background:var(--clay); color:white; border:none; padding: 6px 12px; margin-right:4px;" onclick="viewAdminPost(${p.id})">👁️ View</button>
              <button class="btn-sm" style="background:var(--error); color:white; border:none; padding: 6px 12px;" onclick="adminDeletePost(${p.id}, '${p.title.replace(/'/g, "\\'")}')">🗑️ Delete</button>
            </td>
          </tr>
        `;
      });
    }
  } catch (e) { console.error("Failed to load recipes queue."); }
}

async function adminDeletePost(postId, postTitle) {
  const token = localStorage.getItem('jwt');
  if (!confirm(`CRITICAL WARNING: Are you absolutely sure you want to delete "${postTitle}"? This will also wipe all comments and likes attached to it.`)) return;

  try {
    const res = await fetch(`${API_BASE}/posts/admin_delete_post.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ post_id: postId })
    });

    if (res.ok) {
      showToast('🗑️ Recipe and associated data annihilated.');
      loadAdminPosts(); // Rebuild the post table
      loadAdminComments(); // Rebuild the comments table in case we orphaned any
    } else {
      const data = await res.json();
      showToast('❌ ' + data.error);
    }
  } catch (e) { showToast('🔌 Database connection failed.'); }
}

function viewAdminPost(postId) {
  // Find the exact post in our cached memory
  const post = window.adminPostsCache.find(p => p.id === postId);
  if (!post) return;

  const container = document.getElementById('admin-post-preview-container');

  // Replicate the styling logic from the main feed
  const categoryEmoji = { 'Filipino Classics': '🍲', 'Modern Filipino': '🍽️', 'Healthy': '🥗', 'Desserts': '🍮', 'Street Food': '🍢', 'Drinks': '🥤', 'default': '🍳' };
  const categoryGrad = { 'Filipino Classics': 'linear-gradient(135deg,#C4713A,#E8A44A)', 'Modern Filipino': 'linear-gradient(135deg,#3D2314,#C4713A)', 'Healthy': 'linear-gradient(135deg,#4A5C3A,#7C8C6E)', 'Desserts': 'linear-gradient(135deg,#8B5A2B,#E8A44A)', 'Street Food': 'linear-gradient(135deg,#C4713A,#8B5A2B)', 'Drinks': 'linear-gradient(135deg,#2A4A5A,#3D7A8A)', 'default': 'linear-gradient(135deg,#C4713A,#E8A44A)' };

  const emoji = categoryEmoji[post.category] || categoryEmoji['default'];
  const grad = categoryGrad[post.category] || categoryGrad['default'];

  let photoStyle = `background: ${grad};`;
  let emojiLayer = `<div class="recipe-photo-emoji">${emoji}</div>`;
  if (post.image_url) {
    photoStyle = `background: url('${API_BASE}/posts/uploads/${post.image_url}') center/cover no-repeat;`;
    emojiLayer = '';
  }

  // Render ingredients (Bypassing the Lock so Admin can inspect)
  let ingredientsHTML = '';
  try {
    const parsedData = typeof post.recipe_data === 'string' ? JSON.parse(post.recipe_data) : (post.recipe_data || {});
    const ingredientsList = Array.isArray(parsedData) ? parsedData : (parsedData.ingredients || []);
    if (ingredientsList.length > 0) {
      ingredientsHTML = `<div style="margin: 14px 0; padding: 16px; background: rgba(196,113,58,0.04); border-radius: 14px; border: 1.5px dashed rgba(196,113,58,0.25);"><div class="scaler-bar"><span class="scaler-label">🛒 Ingredients (Admin Override)</span></div><ul class="ingredient-grid">`;
      ingredientsList.forEach(ing => {
        ingredientsHTML += `<li><strong><span class="ing-val">${ing.amount}</span><span class="ing-unit-label"> ${ing.unit}</span></strong>&nbsp;${ing.ingredient}</li>`;
      });
      ingredientsHTML += '</ul></div>';
    }
  } catch (e) { }

  // Inject the final card
  container.innerHTML = `
    <div class="recipe-card" style="margin:0; box-shadow:var(--shadow-md); border:1px solid var(--border);">
      <div class="recipe-photo" style="${photoStyle}">
        ${emojiLayer}
        <div class="recipe-photo-labels">
          <span class="recipe-tag">${post.category}</span>
          ${post.points_cost > 0 ? `<span class="recipe-lock-tag" style="background:var(--error); color:white;">🔒 Premium (${post.points_cost} pts)</span>` : ''}
        </div>
      </div>
      <div class="recipe-body">
        <div class="recipe-title">${post.title}</div>
        <div class="recipe-desc">${post.description}</div>
        <div class="recipe-author-row" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
          <div class="author-chip">👨‍🍳</div>
          <div class="author-name">by <strong>${post.author_name}</strong></div>
          <div class="recipe-likes">❤️ ${post.likes_count || 0} Likes</div>
        </div>
        ${ingredientsHTML}
      </div>
    </div>
  `;

  document.getElementById('adminPostModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

// ── Modals & Utilities ──────────────────────────────────────
function addIngredientRow() {
  const container = document.getElementById('ingredients-container');
  const row = document.createElement('div');
  row.className = 'ingredient-row';
  row.innerHTML = `<input type="text" class="ing-name" placeholder="e.g. Garlic" style="flex:2;"><input type="number" class="ing-amt" placeholder="Qty" style="flex:1;"><select class="ing-unit" style="flex:1.2;"><option value="pcs">pcs</option><option value="tbsp">tbsp</option><option value="tsp">tsp</option><option value="cup">cup</option><option value="g">grams</option><option value="kg">kg</option><option value="ml">ml</option><option value="L">liters</option></select><button type="button" class="del-ingredient-btn" onclick="this.parentElement.remove()">✕</button>`;
  container.appendChild(row);
}

function openModal() { document.getElementById('recipeModal').classList.add('open'); document.body.style.overflow = 'hidden'; document.getElementById('ingredients-container').innerHTML = ''; addIngredientRow(); document.querySelectorAll('.tier-btn').forEach(b => b.classList.remove('selected')); document.querySelector('.tier-btn').classList.add('selected'); document.getElementById('post-price').value = '0'; document.getElementById('upload-preview').innerHTML = ''; }
function closeModal() { document.getElementById('recipeModal').classList.remove('open'); document.body.style.overflow = ''; document.getElementById('post-title').value = ''; document.getElementById('post-desc').value = ''; document.getElementById('post-price').value = '0'; document.getElementById('post-servings').value = '1'; }
function openEditProfile() { document.getElementById('editProfileModal').classList.add('open'); document.body.style.overflow = 'hidden'; document.getElementById('edit-name').value = document.getElementById('sidebar-name').innerText; }
function closeEditProfile() { document.getElementById('editProfileModal').classList.remove('open'); document.body.style.overflow = ''; }
function selectTier(btn, value) { document.querySelectorAll('.tier-btn').forEach(b => b.classList.remove('selected')); btn.classList.add('selected'); document.getElementById('post-price').value = value; }

function handlePhotoPreview(input) {
  const preview = document.getElementById('upload-preview');
  preview.innerHTML = '';
  Array.from(input.files).slice(0, 5).forEach((file, i) => {
    const thumb = document.createElement('div');
    thumb.className = 'upload-preview-thumb';
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = e => { thumb.style.background = 'none'; thumb.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:8px;">`; if (i === 0) thumb.innerHTML += `<div style="position:absolute; top:2px; left:2px; font-size:9px; background:var(--clay); color:white; border-radius:3px; padding:1px 4px;">Cover</div>`; };
      reader.readAsDataURL(file);
    } else { thumb.innerHTML = '🎥'; }
    preview.appendChild(thumb);
  });
}

function scaleRecipe(btnElement, delta) {
  const counterWrapper = btnElement.parentElement;
  const displaySpan = counterWrapper.querySelector('.serving-display');
  const amountSpans = counterWrapper.closest('.recipe-card').querySelectorAll('.ing-val');
  const baseServings = parseInt(displaySpan.getAttribute('data-base'));
  let currentServings = parseInt(displaySpan.getAttribute('data-current'));
  let newServings = Math.max(1, currentServings + delta);
  displaySpan.setAttribute('data-current', newServings);
  displaySpan.innerText = newServings + (newServings === 1 ? ' Serving' : ' Servings');
  const scalingRatio = newServings / baseServings;
  amountSpans.forEach(span => { span.innerText = Number((parseFloat(span.getAttribute('data-base-amt')) * scalingRatio).toFixed(2)); });
}

document.querySelectorAll('.modal-backdrop').forEach(backdrop => { backdrop.addEventListener('click', function (e) { if (e.target === this) { this.classList.remove('open'); document.body.style.overflow = ''; } }); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open')); document.body.style.overflow = ''; } });

// ── Comments Engine ──────────────────────────────────────
async function toggleComments(postId) {
  const section = document.getElementById(`comments-section-${postId}`);
  if (section.classList.contains('hidden')) {
    section.classList.remove('hidden');
    await loadComments(postId);
  } else {
    section.classList.add('hidden');
  }
}

async function loadComments(postId) {
  const listContainer = document.getElementById(`comments-list-${postId}`);
  try {
    const res = await fetch(`${API_BASE}/posts/get_comments.php?post_id=${postId}`);
    const data = await res.json();

    if (res.ok) {
      if (data.data.length === 0) {
        listContainer.innerHTML = '<div style="font-size: 12px; color: var(--muted); text-align: center; padding: 10px 0;">No comments yet. Start the conversation!</div>';
        return;
      }

      listContainer.innerHTML = '';
      data.data.forEach(comment => {
        const time = new Date(comment.created_at).toLocaleDateString() + ' ' + new Date(comment.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        listContainer.innerHTML += `
          <div style="font-size: 13px; color: var(--dark); background: var(--white); padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
              <strong style="color: var(--clay); font-weight: 600;">${comment.author_name}</strong>
              <span style="font-size: 10px; color: var(--muted);">${time}</span>
            </div>
            <div style="line-height: 1.5;">${comment.comment_text}</div>
          </div>
        `;
      });
      // Auto-scroll to bottom
      listContainer.scrollTop = listContainer.scrollHeight;
    }
  } catch (e) {
    listContainer.innerHTML = '<div style="font-size: 12px; color: var(--error);">Failed to load comments.</div>';
  }
}

async function submitComment(postId) {
  const token = localStorage.getItem('jwt');
  const inputEl = document.getElementById(`comment-input-${postId}`);
  const text = inputEl.value.trim();
  const authorName = document.getElementById('sidebar-name').innerText;

  if (!text) return;

  try {
    const res = await fetch(`${API_BASE}/posts/add_comment.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ post_id: postId, author_name: authorName, comment_text: text })
    });

    if (res.ok) {
      inputEl.value = ''; // Clear the input
      await loadComments(postId); // Refresh the thread
      showToast('💬 Comment posted! (+5 points)');

      // Opt-in logic: Award the user points for commenting by hitting the Profile API if you want
    } else {
      showToast('❌ Failed to post comment.');
    }
  } catch (e) {
    showToast('🔌 Network connection failed.');
  }
}

// ── Role Management Engine ──────────────────────────────
async function adminUpdateRole(targetUserId, newRole) {
  const token = localStorage.getItem('jwt');

  if (!confirm(`Are you sure you want to change this user's role to ${newRole.toUpperCase()}?`)) {
    loadAdminData(); // If they cancel, reload the table to reset the dropdown visually
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/auth/admin_update_role.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ target_user_id: targetUserId, new_role: newRole })
    });

    if (res.ok) {
      showToast(`✅ Role successfully updated to ${newRole.toUpperCase()}`);
      loadAdminData(); // Refresh the colors and layout
    } else {
      const data = await res.json();
      showToast('❌ ' + data.error);
      loadAdminData(); // Reset the dropdown if the server rejected it
    }
  } catch (e) {
    showToast('🔌 Network connection failed.');
    loadAdminData();
  }
}

// ── Admin Ban Engine ──────────────────────────────
async function adminToggleBan(targetUserId, userEmail) {
  const token = localStorage.getItem('jwt');
  if (!confirm(`Are you sure you want to toggle the ban status for ${userEmail}?`)) return;

  try {
    const res = await fetch(`${API_BASE}/auth/admin_toggle_ban.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ target_user_id: targetUserId })
    });

    if (res.ok) {
      const data = await res.json();
      showToast(data.new_status === 'banned' ? `🔨 ${userEmail} has been BANNED.` : `🟢 ${userEmail} has been restored.`);
      loadAdminData(); // Instantly refresh the table
    } else {
      const data = await res.json();
      showToast('❌ ' + data.error);
    }
  } catch (e) { showToast('🔌 Network connection failed.'); }
}
