{{-- resources/views/filament/widgets/agent-budgetaire.blade.php --}}
<div
    x-data="{ scrollToBottom() { const el = document.getElementById('chat-messages'); if (el) el.scrollTop = el.scrollHeight; } }"
    x-init="$watch('$wire.messages', () => $nextTick(() => scrollToBottom()))"
>

    {{-- ─────────────────────────────────────────────────────────
         BOUTON FLOTTANT — coin bas droite sur toutes les pages
         ───────────────────────────────────────────────────────── --}}
    @if(!$ouvert)
    <div style="position:fixed;bottom:24px;right:24px;z-index:9999;">
        <button
            wire:click="ouvrir"
            title="Ouvrir Budget Suite Assistant"
            style="
                width:56px;height:56px;border-radius:50%;
                background:linear-gradient(135deg,#3b82f6,#1d4ed8);
                border:none;cursor:pointer;box-shadow:0 4px 20px rgba(59,130,246,0.5);
                display:flex;align-items:center;justify-content:center;
                transition:transform 0.2s,box-shadow 0.2s;
            "
            onmouseover="this.style.transform='scale(1.1)';this.style.boxShadow='0 6px 25px rgba(59,130,246,0.7)'"
            onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 4px 20px rgba(59,130,246,0.5)'"
        >
            <svg width="26" height="26" fill="none" stroke="white" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
            </svg>
        </button>
    </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────
         FENÊTRE DE CHAT
         ───────────────────────────────────────────────────────── --}}
    @if($ouvert)
    <div style="
        position:fixed;bottom:24px;right:24px;z-index:9999;
        width:380px;height:560px;
        background:var(--color-background-primary, #fff);
        border-radius:16px;
        box-shadow:0 20px 60px rgba(0,0,0,0.25);
        display:flex;flex-direction:column;
        border:1px solid var(--color-border-secondary, #e2e8f0);
        overflow:hidden;
        font-family:inherit;
    ">

        {{-- En-tête ──────────────────────────────────────────── --}}
        <div style="
            background:linear-gradient(135deg,#3b82f6,#1d4ed8);
            padding:12px 16px;
            display:flex;align-items:center;justify-content:space-between;
            flex-shrink:0;
        ">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="
                    width:36px;height:36px;border-radius:50%;
                    background:rgba(255,255,255,0.2);
                    display:flex;align-items:center;justify-content:center;
                ">
                    <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 001.5 2.12V19.5a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v-1.666a2.25 2.25 0 001.5-2.12V3.104m4.5 0c.251.023.501.05.75.082M19.5 4.5c-.376-.023-.75-.05-1.125-.082"/>
                    </svg>
                </div>
                <div>
                    <div style="color:white;font-weight:700;font-size:.9rem;">Budget Suite Assistant</div>
                    <div style="color:rgba(255,255,255,.75);font-size:.72rem;">
                        @if($enAttente)
                            <span>✍️ En train de répondre…</span>
                        @else
                            <span>● En ligne</span>
                        @endif
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:6px;">
                <button
                    wire:click="viderHistorique"
                    title="Nouvelle conversation"
                    style="background:rgba(255,255,255,.2);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                >
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </button>
                <button
                    wire:click="fermer"
                    title="Fermer"
                    style="background:rgba(255,255,255,.2);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                >
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Zone de messages ─────────────────────────────────── --}}
        <div
            id="chat-messages"
            style="
                flex:1;overflow-y:auto;padding:14px;
                display:flex;flex-direction:column;gap:10px;
                background:var(--color-background-secondary,#f8fafc);
            "
        >
            @foreach($messages as $msg)
                @if($msg['role'] === 'user')
                    {{-- Message utilisateur --}}
                    <div style="display:flex;justify-content:flex-end;">
                        <div style="max-width:80%;">
                            <div style="
                                background:linear-gradient(135deg,#3b82f6,#1d4ed8);
                                color:white;padding:10px 14px;
                                border-radius:16px 16px 4px 16px;
                                font-size:.82rem;line-height:1.4;
                            ">{{ $msg['content'] }}</div>
                            <div style="text-align:right;font-size:.65rem;color:#94a3b8;margin-top:3px;">
                                {{ $msg['time'] ?? '' }}
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Message assistant --}}
                    <div style="display:flex;justify-content:flex-start;gap:8px;">
                        <div style="
                            width:28px;height:28px;border-radius:50%;flex-shrink:0;
                            background:linear-gradient(135deg,#3b82f6,#1d4ed8);
                            display:flex;align-items:center;justify-content:center;margin-top:2px;
                        ">
                            <svg width="14" height="14" fill="none" stroke="white" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/>
                            </svg>
                        </div>
                        <div style="max-width:85%;">
                            <div style="
                                background:var(--color-background-primary,#fff);
                                border:1px solid var(--color-border-secondary,#e2e8f0);
                                padding:10px 14px;
                                border-radius:4px 16px 16px 16px;
                                font-size:.82rem;line-height:1.5;
                                color:var(--color-text-primary,#1e293b);
                                white-space:pre-wrap;
                            ">{{ $msg['content'] }}</div>
                            <div style="font-size:.65rem;color:#94a3b8;margin-top:3px;">
                                {{ $msg['time'] ?? '' }}
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Indicateur de chargement --}}
            @if($enAttente)
            <div style="display:flex;justify-content:flex-start;gap:8px;">
                <div style="
                    width:28px;height:28px;border-radius:50%;flex-shrink:0;
                    background:linear-gradient(135deg,#3b82f6,#1d4ed8);
                    display:flex;align-items:center;justify-content:center;
                ">
                    <svg width="14" height="14" fill="none" stroke="white" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/>
                    </svg>
                </div>
                <div style="
                    background:var(--color-background-primary,#fff);
                    border:1px solid var(--color-border-secondary,#e2e8f0);
                    padding:12px 16px;border-radius:4px 16px 16px 16px;
                    display:flex;gap:4px;align-items:center;
                ">
                    <div style="width:7px;height:7px;border-radius:50%;background:#3b82f6;animation:pulse 1s infinite 0s;"></div>
                    <div style="width:7px;height:7px;border-radius:50%;background:#3b82f6;animation:pulse 1s infinite 0.2s;"></div>
                    <div style="width:7px;height:7px;border-radius:50%;background:#3b82f6;animation:pulse 1s infinite 0.4s;"></div>
                </div>
            </div>
            @endif
        </div>

        {{-- Suggestions rapides --}}
        @if(count($messages) <= 1)
        <div style="padding:8px 14px;display:flex;flex-wrap:wrap;gap:5px;border-top:1px solid var(--color-border-secondary,#e2e8f0);background:var(--color-background-secondary,#f8fafc);">
            @foreach([
                '❓ Pourquoi je ne peux pas modifier ?',
                '💰 Vérifier disponible budgétaire',
                '📋 Expliquer le workflow BC',
                '🔐 Mes permissions',
            ] as $suggestion)
            <button
                wire:click="envoyerSuggestion('{{ $suggestion }}')"
                style="
                    font-size:.7rem;padding:4px 8px;
                    border:1px solid #3b82f6;border-radius:12px;
                    background:white;color:#3b82f6;cursor:pointer;
                    transition:all 0.15s;white-space:nowrap;
                "
                onmouseover="this.style.background='#3b82f6';this.style.color='white'"
                onmouseout="this.style.background='white';this.style.color='#3b82f6'"
            >{{ $suggestion }}</button>
            @endforeach
        </div>
        @endif

        {{-- Zone de saisie ───────────────────────────────────── --}}
        <div style="
            padding:12px 14px;border-top:1px solid var(--color-border-secondary,#e2e8f0);
            background:var(--color-background-primary,#fff);flex-shrink:0;
        ">
            <div style="display:flex;gap:8px;align-items:center;">
                <input
                    wire:model="messageInput"
                    wire:keydown.enter="envoyer"
                    type="text"
                    placeholder="Posez votre question…"
                    @if($enAttente) disabled @endif
                    style="
                        flex:1;padding:10px 14px;
                        border:1px solid var(--color-border-secondary,#e2e8f0);
                        border-radius:24px;font-size:.82rem;
                        background:var(--color-background-secondary,#f8fafc);
                        color:var(--color-text-primary,#1e293b);
                        outline:none;
                    "
                    onfocus="this.style.borderColor='#3b82f6'"
                    onblur="this.style.borderColor='var(--color-border-secondary,#e2e8f0)'"
                >
                <button
                    wire:click="envoyer"
                    @if($enAttente) disabled @endif
                    style="
                        width:40px;height:40px;border-radius:50%;border:none;
                        background:{{ $enAttente ? '#94a3b8' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)' }};
                        cursor:{{ $enAttente ? 'not-allowed' : 'pointer' }};
                        display:flex;align-items:center;justify-content:center;
                        flex-shrink:0;transition:opacity 0.2s;
                    "
                >
                    <svg width="18" height="18" fill="none" stroke="white" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                    </svg>
                </button>
            </div>
            <div style="text-align:center;font-size:.6rem;color:#94a3b8;margin-top:5px;">
                Budget Suite Assistant • Propulsé par KISINIT
            </div>
        </div>
    </div>
    @endif

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50%       { opacity: 1;   transform: scale(1.2); }
        }
    </style>
</div>