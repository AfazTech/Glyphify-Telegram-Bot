const escapeHtml = (s) => {
    if (!s) return '';
    return String(s).replace(/[&<>]/g, (m) => {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
};

const formatDate = (ts) => {
    if (!ts) return '-';
    try {
        const date = new Date(ts);
        if (isNaN(date.getTime())) return String(ts);
        return new Intl.DateTimeFormat('fa-IR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    } catch(e) {
        return String(ts);
    }
};

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0';
    return new Intl.NumberFormat('fa-IR').format(num);
};

const downloadJSON = (data, name) => {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: name });
    a.click();
    URL.revokeObjectURL(a.href);
};

const toast = (msg, type = 'info') => {
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(t => t.remove());
    
    const t = Object.assign(document.createElement('div'), { className: `toast ${type}`, textContent: msg });
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
};

const confirmAction = (msg) => {
    return confirm(msg);
};
