// ============================================================
// Join Mandatory Page
// ============================================================

function initJoinMandatory() {
    console.log('🔗 Initializing join mandatory');
    loadMandatoryChannels();
}

async function loadMandatoryChannels() {
    const tbody = document.getElementById('mandatory-channels-list');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" class="loading">⏳ در حال بارگذاری...</td></tr>';
    
    try {
        const result = await API.getMandatoryChannels();
        const channels = result.data || [];
        
        if (channels.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-dim);">هیچ کانالی یافت نشد</td></tr>';
            return;
        }
        
        tbody.innerHTML = channels.map(c => `
            <tr>
                <td>${c.chat_id}</td>
                <td>${escapeHtml(c.title || '-')}</td>
                <td>${c.link ? `<a href="${c.link}" target="_blank" style="color: var(--primary);">${c.link}</a>` : '-'}</td>
                <td>${c.active ? '<span class="badge badge-success">✅ فعال</span>' : '<span class="badge badge-danger">❌ غیرفعال</span>'}</td>
                <td>${formatDate(c.created_at)}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="toggleChannel(${c.chat_id})">🔄 تغییر</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteChannel(${c.chat_id})">🗑️ حذف</button>
                </td>
             </tr>
        `).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="error-message">❌ خطا: ${error.message}</td></tr>`;
        showToast('❌ خطا در بارگذاری کانال‌ها: ' + error.message, 'error');
    }
}

function openChannelModal() {
    document.getElementById('channel-chat-id').value = '';
    document.getElementById('channel-title').value = '';
    document.getElementById('channel-link').value = '';
    document.getElementById('channel-modal').classList.add('active');
}

function closeChannelModal() {
    document.getElementById('channel-modal').classList.remove('active');
}

async function addMandatoryChannel(event) {
    event.preventDefault();
    const chatId = document.getElementById('channel-chat-id').value.trim();
    const title = document.getElementById('channel-title').value.trim();
    const link = document.getElementById('channel-link').value.trim();
    
    if (!chatId) {
        showToast('❌ شناسه کانال الزامی است', 'error');
        return;
    }
    
    try {
        await API.addMandatoryChannel({
            chat_id: parseInt(chatId),
            title: title || null,
            link: link || null
        });
        showToast('✅ کانال با موفقیت اضافه شد', 'success');
        closeChannelModal();
        await loadMandatoryChannels();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function toggleChannel(chatId) {
    try {
        await API.toggleMandatoryChannel(chatId);
        showToast('✅ وضعیت کانال تغییر کرد', 'success');
        await loadMandatoryChannels();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function deleteChannel(chatId) {
    if (!confirm('⚠️ آیا از حذف این کانال مطمئن هستید؟')) return;
    
    try {
        await API.removeMandatoryChannel(chatId);
        showToast('✅ کانال حذف شد', 'success');
        await loadMandatoryChannels();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}
