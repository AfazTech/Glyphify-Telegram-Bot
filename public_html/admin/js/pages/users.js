// ============================================================
// Users Page
// ============================================================

let currentUsersPage = 1;
const USERS_PER_PAGE = 20;
let allUsers = [];

function initUsers() {
    loadUsers();
}

async function loadUsers(page = 1) {
    try {
        const result = await API.getUsers(USERS_PER_PAGE);
        allUsers = result.data || [];
        renderUsers(allUsers, page);
    } catch (error) {
        showToast('❌ خطا در بارگذاری کاربران: ' + error.message, 'error');
    }
}

function renderUsers(users, page = 1) {
    const container = document.getElementById('users-list');
    if (!container) return;

    const start = (page - 1) * USERS_PER_PAGE;
    const end = start + USERS_PER_PAGE;
    const pageUsers = users.slice(start, end);

    if (pageUsers.length === 0) {
        container.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-dim);">هیچ کاربری یافت نشد</td></tr>';
        return;
    }

    let html = '';
    pageUsers.forEach(user => {
        const status = user.blocked 
            ? '<span class="badge badge-danger">بلاک</span>'
            : '<span class="badge badge-success">فعال</span>';
        
        const createdAt = user.created_at ? new Date(user.created_at).toLocaleDateString('fa-IR') : '-';
        
        html += `
            <tr>
                <td>${user.user_id}</td>
                <td>${user.username ? '@' + user.username : '-'}</td>
                <td>${user.first_name || ''} ${user.last_name || ''}</td>
                <td>${status}</td>
                <td>${createdAt}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewUser(${user.user_id})">👁️</button>
                    ${user.blocked 
                        ? `<button class="btn btn-sm btn-success" onclick="unblockUserById(${user.user_id})">🔓</button>`
                        : `<button class="btn btn-sm btn-danger" onclick="openBlockModal(${user.user_id})">🔒</button>`
                    }
                    <button class="btn btn-sm btn-warning" onclick="showUserLanguageModal(${user.user_id})">🌍</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUserById(${user.user_id})">🗑️</button>
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;
    renderPagination(users.length, page);
}

function renderPagination(total, currentPage) {
    const container = document.getElementById('users-pagination');
    if (!container) return;

    const totalPages = Math.ceil(total / USERS_PER_PAGE);
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="${i === currentPage ? 'active' : ''}" onclick="loadUsers(${i})">${i}</button>`;
    }
    container.innerHTML = html;
}

async function searchUsers() {
    const query = document.getElementById('search-user').value.trim();
    if (!query) {
        loadUsers();
        return;
    }

    try {
        const result = await API.searchUsers(query);
        allUsers = result.data || [];
        renderUsers(allUsers, 1);
        showToast(`🔍 ${allUsers.length} کاربر یافت شد`, 'info');
    } catch (error) {
        showToast('❌ خطا در جستجو: ' + error.message, 'error');
    }
}

async function viewUser(userId) {
    try {
        const result = await API.getUser(userId);
        const user = result.data;
        
        document.getElementById('user-modal-title').textContent = `👤 اطلاعات کاربر ${user.user_id}`;
        document.getElementById('user-modal-content').innerHTML = `
            <div class="stats-grid" style="padding: 0;">
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;">${user.user_id}</div>
                    <div class="stat-label">🆔 شناسه</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;">${user.username ? '@' + user.username : '-'}</div>
                    <div class="stat-label">🔹 یوزرنیم</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;">${user.first_name || ''} ${user.last_name || ''}</div>
                    <div class="stat-label">📛 نام</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;">${user.language || 'fa'}</div>
                    <div class="stat-label">🌍 زبان</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;">${user.blocked ? '🚫 بلاک' : '✅ فعال'}</div>
                    <div class="stat-label">وضعیت</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;">${user.join_mandatory_channels ? '✅' : '❌'}</div>
                    <div class="stat-label">عضویت در کانال‌ها</div>
                </div>
            </div>
            <div style="padding: 20px; background: var(--bg-hover); border-radius: 12px; margin-top: 16px;">
                <p><strong>📅 تاریخ عضویت:</strong> ${user.created_at ? new Date(user.created_at).toLocaleString('fa-IR') : '-'}</p>
                <p><strong>🔄 آخرین بروزرسانی:</strong> ${user.updated_at ? new Date(user.updated_at).toLocaleString('fa-IR') : '-'}</p>
                ${user.block_reason ? `<p><strong>⚠️ دلیل بلاک:</strong> ${user.block_reason}</p>` : ''}
                ${user.blocked_until ? `<p><strong>⏳ بلاک تا:</strong> ${new Date(user.blocked_until).toLocaleString('fa-IR')}</p>` : ''}
            </div>
        `;
        document.getElementById('user-modal').classList.add('active');
    } catch (error) {
        showToast('❌ خطا در دریافت اطلاعات کاربر: ' + error.message, 'error');
    }
}

function closeUserModal() {
    document.getElementById('user-modal').classList.remove('active');
}

function openBlockModal(userId) {
    document.getElementById('block-user-id').value = userId;
    document.getElementById('block-reason').value = '';
    document.getElementById('block-duration').value = '';
    document.getElementById('block-modal').classList.add('active');
}

function closeBlockModal() {
    document.getElementById('block-modal').classList.remove('active');
}

async function blockUser(event) {
    event.preventDefault();
    const userId = document.getElementById('block-user-id').value;
    const reason = document.getElementById('block-reason').value;
    const duration = document.getElementById('block-duration').value;

    try {
        await API.blockUser(userId, {
            reason: reason || 'بلاک توسط ادمین',
            duration: duration ? parseInt(duration) : null
        });
        showToast('✅ کاربر با موفقیت بلاک شد', 'success');
        closeBlockModal();
        loadUsers();
    } catch (error) {
        showToast('❌ خطا در بلاک کاربر: ' + error.message, 'error');
    }
}

async function unblockUserById(userId) {
    if (!confirm('آیا از آنبلاک این کاربر اطمینان دارید؟')) return;

    try {
        await API.unblockUser(userId);
        showToast('✅ کاربر با موفقیت آنبلاک شد', 'success');
        loadUsers();
    } catch (error) {
        showToast('❌ خطا در آنبلاک کاربر: ' + error.message, 'error');
    }
}

async function deleteUserById(userId) {
    if (!confirm('⚠️ آیا از حذف کامل این کاربر اطمینان دارید؟ این عمل غیرقابل بازگشت است!')) return;

    try {
        await API.deleteUser(userId);
        showToast('✅ کاربر با موفقیت حذف شد', 'success');
        loadUsers();
    } catch (error) {
        showToast('❌ خطا در حذف کاربر: ' + error.message, 'error');
    }
}
