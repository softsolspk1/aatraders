<?php
// AA TRADERS - Dedicated AI Pharma Copilot Workspace
$pageTitle = 'AI Pharma Copilot';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">AI Pharma Copilot &bull; Distributor Intelligence</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Interactive natural language assistant querying live batch shelf-lives, credit risk, procurement and sales performance</p>
    </div>
</div>

<div class="card" style="background: linear-gradient(135deg, #090d16, #1e293b); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.12); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);">
    <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #0284c7, #10b981); display: flex; align-items: center; justify-content: center; font-size: 22px;">
                🧠
            </div>
            <div>
                <h3 style="font-size: 16px; font-weight: 700; color: #ffffff;">AA TRADERS AI Intelligence Core</h3>
                <p style="font-size: 12px; color: #94a3b8;">Safe, controlled ERP data querying engine with zero hallucination</p>
            </div>
        </div>
        <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4);">
            ● Online &bull; Live DB
        </span>
    </div>

    <div class="card-body" style="padding: 24px;">
        <div style="margin-bottom: 16px;">
            <label style="font-size: 13px; color: #cbd5e1; font-weight: 600; margin-bottom: 8px; display: block;">
                Ask your question in natural language:
            </label>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="copilotInput" class="copilot-input" style="padding: 12px 16px; font-size: 15px;" placeholder="e.g. Which products will expire in the next 90 days?">
                <button id="copilotSendBtn" class="btn btn-primary btn-lg" style="padding: 12px 24px;">
                    Ask Copilot
                </button>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <div style="font-size: 12px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 10px;">
                ⚡ Recommended Executive Prompts
            </div>
            <div class="copilot-chips">
                <span class="copilot-chip" data-query="Which products will expire in the next 90 days?">🔴 Which products will expire in the next 90 days?</span>
                <span class="copilot-chip" data-query="Show me customers exceeding credit limit">⚠️ Show me customers exceeding credit limit</span>
                <span class="copilot-chip" data-query="What should I purchase next month?">🔮 What should I purchase next month? (Reorder suggestions)</span>
                <span class="copilot-chip" data-query="Which sales reps are below their monthly target?">📈 Which sales reps are below their monthly target?</span>
                <span class="copilot-chip" data-query="What is the current stock valuation across warehouses?">🏢 What is the current stock valuation across warehouses?</span>
                <span class="copilot-chip" data-query="Show top selling pharmaceutical products">💊 Show top selling pharmaceutical products</span>
            </div>
        </div>

        <!-- Result container -->
        <div id="copilotResult" style="display: none; margin-top: 20px;"></div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
