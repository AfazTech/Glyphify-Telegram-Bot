// ============================================================
// Language Management Page
// ============================================================

function initLanguages() {
    loadLanguages();
    loadDefaultLanguage();
}

async function loadLanguages() {
    try {
        const result = await API.getLanguages();
        const languages = result.data || [];
        
        const container = document.getElementById('languages-list');
        if (!container) return;
        
        if (languages.length === 0) {
            container.innerHTML = '<div class="info-message">هیچ زبانی یافت نشد</div>';
            return;
        }
        
        let html = '';
        languages.forEach(lang => {
            html += `
                <tr>
                    <td>${lang.code}</td>
                    <td>${lang.name}</td>
                    <td>
                        <span class="badge ${lang.default ? 'badge-success' : 'badge-warning'}">
                            ${lang.default ? '✅ پیش‌فرض' : '❌'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="setDefaultLanguage('${lang.code}')">
                            ${lang.default ? '👑' : '⭐'} تنظیم پیش‌فرض
                        </button>
                    </td>
                </tr>
            `;
        });
        
        container.innerHTML = html;
        showToast('✅ زبان‌ها بارگذاری شدند', 'success');
    } catch (error) {
        showToast('❌ خطا در بارگذاری زبان‌ها: ' + error.message, 'error');
    }
}

async function loadDefaultLanguage() {
    try {
        const result = await API.getLanguages();
        const languages = result.data || [];
        const defaultLang = languages.find(l => l.default);
        
        const container = document.getElementById('default-language-display');
        if (container && defaultLang) {
            container.innerHTML = `
                <div class="info-message">
                    🌍 زبان پیش‌فرض فعلی: <strong>${defaultLang.name}</strong> (${defaultLang.code})
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading default language:', error);
    }
}

async function setDefaultLanguage(langCode) {
    if (!confirm(`آیا از تغییر زبان پیش‌فرض به "${langCode}" اطمینان دارید؟`)) return;
    
    try {
        await API.setDefaultLanguage(langCode);
        showToast(`✅ زبان پیش‌فرض به ${langCode} تغییر یافت`, 'success');
        loadLanguages();
        loadDefaultLanguage();
    } catch (error) {
        showToast('❌ خطا در تغییر زبان پیش‌فرض: ' + error.message, 'error');
    }
}

async function reloadLanguages() {
    try {
        await API.reloadLanguages();
        showToast('✅ فایل‌های زبان با موفقیت بازخوانی شدند', 'success');
        loadLanguages();
    } catch (error) {
        showToast('❌ خطا در بازخوانی زبان‌ها: ' + error.message, 'error');
    }
}

async function showUserLanguageModal(userId) {
    try {
        const result = await API.getUserLanguage(userId);
        const lang = result.data.language;
        
        const languages = await API.getLanguages();
        const langOptions = languages.data || [];
        
        let optionsHtml = langOptions.map(l => 
            `<option value="${l.code}" ${l.code === lang ? 'selected' : ''}>${l.name}</option>`
        ).join('');
        
        document.getElementById('user-lang-user-id').value = userId;
        document.getElementById('user-language-select').innerHTML = optionsHtml;
        document.getElementById('user-lang-modal').classList.add('active');
    } catch (error) {
        showToast('❌ خطا در دریافت زبان کاربر: ' + error.message, 'error');
    }
}

async function updateUserLanguage(event) {
    event.preventDefault();
    const userId = document.getElementById('user-lang-user-id').value;
    const language = document.getElementById('user-language-select').value;
    
    try {
        await API.setUserLanguage(userId, language);
        showToast('✅ زبان کاربر با موفقیت تغییر یافت', 'success');
        document.getElementById('user-lang-modal').classList.remove('active');
        loadUsers();
    } catch (error) {
        showToast('❌ خطا در تغییر زبان کاربر: ' + error.message, 'error');
    }
}

function closeUserLangModal() {
    document.getElementById('user-lang-modal').classList.remove('active');
}
