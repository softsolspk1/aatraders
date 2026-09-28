// AA TRADERS - PDMS Client Logic & Interactive Helpers

document.addEventListener('DOMContentLoaded', () => {
    initModals();
    initDemoRoleSwitcher();
    initCopilot();
});

// Modal Controller
function initModals() {
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
}

// Demo Role Switcher
function initDemoRoleSwitcher() {
    const roleSelect = document.getElementById('demoRoleSwitcher');
    if (roleSelect) {
        roleSelect.addEventListener('change', (e) => {
            const username = e.target.value;
            if (username) {
                window.location.href = `login.php?switch_user=${encodeURIComponent(username)}`;
            }
        });
    }
}

// AI Copilot Query Handler
function initCopilot() {
    const btn = document.getElementById('copilotSendBtn');
    const input = document.getElementById('copilotInput');
    const resultBox = document.getElementById('copilotResult');

    if (btn && input) {
        const sendQuery = (queryText) => {
            const q = queryText || input.value.trim();
            if (!q) return;

            if (resultBox) {
                resultBox.style.display = 'block';
                resultBox.innerHTML = `
                    <div style="padding: 16px; text-align: center; color: #94a3b8;">
                        <span style="display: inline-block; animation: pulse 1s infinite;">🧠</span> 
                        Analyzing pharmaceutical batches, ledger balances & sales data...
                    </div>
                `;
            }

            fetch(`ajax.php?action=copilot_ask&q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    renderCopilotResult(data);
                })
                .catch(err => {
                    if (resultBox) {
                        resultBox.innerHTML = `<div style="padding: 14px; color: #f87171;">Error analyzing request: ${err.message}</div>`;
                    }
                });
        };

        btn.addEventListener('click', () => sendQuery());
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendQuery();
        });

        // Quick chip clicks
        document.querySelectorAll('.copilot-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const text = chip.dataset.query || chip.innerText.replace(/[🔴🟠🟡🟢💡📈⚠️]/g, '').trim();
                input.value = text;
                sendQuery(text);
            });
        });
    }
}

function renderCopilotResult(res) {
    const resultBox = document.getElementById('copilotResult');
    if (!resultBox) return;

    if (!res.success) {
        resultBox.innerHTML = `<div style="padding: 14px; color: #f87171;">${res.message || 'No response'}</div>`;
        return;
    }

    const payload = res.payload;
    let html = `
        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 16px; margin-top: 14px; border: 1px solid rgba(255, 255, 255, 0.1);">
            <div style="font-weight: 700; color: #38bdf8; margin-bottom: 6px; font-size: 15px;">
                💡 ${escapeHtml(payload.title)}
            </div>
            <div style="font-size: 13px; color: #e2e8f0; margin-bottom: 12px;">
                ${escapeHtml(payload.summary)}
            </div>
    `;

    if (payload.data && payload.data.length > 0) {
        html += `<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse; font-size: 12.5px; color: #f1f5f9;"><thead><tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.15); text-align: left;">`;
        const keys = Object.keys(payload.data[0]);
        keys.forEach(k => {
            html += `<th style="padding: 6px 10px; color: #94a3b8; text-transform: uppercase;">${k.replace(/_/g, ' ')}</th>`;
        });
        html += `</tr></thead><tbody>`;

        payload.data.forEach(row => {
            html += `<tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">`;
            keys.forEach(k => {
                let val = row[k] ?? '-';
                if (k.includes('price') || k.includes('amount') || k.includes('valuation') || k.includes('balance') || k.includes('revenue')) {
                    if (!isNaN(val) && val !== '-') val = 'Rs. ' + Number(val).toLocaleString();
                }
                html += `<td style="padding: 8px 10px;">${escapeHtml(String(val))}</td>`;
            });
            html += `</tr>`;
        });

        html += `</tbody></table></div>`;
    }

    html += `</div>`;
    resultBox.innerHTML = html;
}

// Helper: Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Table live search filter
window.filterTable = function(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toLowerCase();
    const table = document.getElementById(tableId);
    if (!table) return;

    const trs = table.getElementsByTagName('tr');
    for (let i = 1; i < trs.length; i++) {
        const text = trs[i].textContent || trs[i].innerText;
        trs[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
    }
};

// WhatsApp Dispatch Link Generator
window.sendWhatsAppInvoice = function(phone, invNum, customerName, totalAmount, balanceAmount) {
    let cleanPhone = phone.replace(/[^0-9]/g, '');
    if (cleanPhone.startsWith('0')) {
        cleanPhone = '92' + cleanPhone.substring(1);
    }
    const msg = `Dear ${customerName},\nYour invoice *#${invNum}* from *AA TRADERS* for *Rs. ${totalAmount}* has been processed.\nOutstanding Balance: *Rs. ${balanceAmount}*.\nFor batch verification & queries contact: +92 21 34567890. Thank you for your business!`;
    const url = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`;
    window.open(url, '_blank');
};
