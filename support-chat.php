<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-box {
            height: 420px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .1);
            border-radius: 10px;
            padding: 12px;
        }

        .chat-msg {
            max-width: 85%;
            padding: 10px 12px;
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .chat-user {
            background: #fff3cd;
            margin-left: auto;
        }

        .chat-bot {
            background: #f1f3f5;
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Chat Support</h2>
            <div class="d-flex gap-2">
                <button id="clearChatBtn" type="button" class="btn btn-outline-danger">Clear Chat</button>
                <a class="btn btn-outline-secondary" href="help-center.php">Help Center</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div id="chatBox" class="chat-box"></div>
                <div class="d-flex gap-2 mt-3">
                    <input id="chatInput" class="form-control"
                        placeholder="Type a message (e.g., order status 45872134, last viewed product, price of headphones)">
                    <button id="sendBtn" class="btn btn-warning"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div class="small text-muted mt-2">Tip: "last viewed product", "price of laptop", "reviews of TechWorld
                    Store", "order status 45872134".</div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">Quick Actions</div>
                    <div class="card-body d-grid gap-2">
                        <button class="btn btn-outline-primary quick" data-msg="order status">Order Status</button>
                        <button class="btn btn-outline-primary quick" data-msg="last viewed product">Last Viewed
                            Product</button>
                        <button class="btn btn-outline-primary quick" data-msg="return refund">Return / Refund</button>
                        <button class="btn btn-outline-primary quick" data-msg="shipping info">Shipping Info</button>
                        <button class="btn btn-outline-primary quick" data-msg="payment methods">Payment Help</button>
                        <button class="btn btn-outline-primary quick" data-msg="recommend headphones">Recommend
                            Products</button>
                        <button class="btn btn-outline-primary quick" data-msg="store reviews">Store Reviews</button>
                        <a class="btn btn-outline-secondary" href="contact.php">Contact</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const box = document.getElementById('chatBox');
            const input = document.getElementById('chatInput');
            const send = document.getElementById('sendBtn');
            const quicks = document.querySelectorAll('.quick');
            const clearChatBtn = document.getElementById('clearChatBtn');
            const storageKey = 'shophub_chat_history_v1';
            const maxAgeMs = 24 * 60 * 60 * 1000;

            function escapeHtml(value) {
                return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            function readState() {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (!raw) return { items: [] };
                    const state = JSON.parse(raw);
                    if (!state || !Array.isArray(state.items) || !state.savedAt) return { items: [] };
                    if (Date.now() - Number(state.savedAt) > maxAgeMs) {
                        localStorage.removeItem(storageKey);
                        return { items: [] };
                    }
                    return state;
                } catch (e) {
                    return { items: [] };
                }
            }

            function writeState(items) {
                try {
                    localStorage.setItem(storageKey, JSON.stringify({
                        savedAt: Date.now(),
                        items: items
                    }));
                } catch (e) {
                }
            }

            function getItems() {
                const state = readState();
                return Array.isArray(state.items) ? state.items : [];
            }

            function saveItem(item) {
                const items = getItems();
                items.push(item);
                writeState(items);
            }

            function clearChat() {
                localStorage.removeItem(storageKey);
                box.innerHTML = '';
                renderDefaultWelcome();
            }

            function createMessageNode(text, who) {
                const div = document.createElement('div');
                div.className = 'chat-msg ' + (who === 'user' ? 'chat-user' : 'chat-bot');
                div.innerHTML = escapeHtml(text);
                return div;
            }

            function createSuggestionsNode(list) {
                const wrap = document.createElement('div');
                wrap.className = 'mb-2';
                wrap.innerHTML = list.map(s => {
                    const href = escapeHtml(String(s.href || '#'));
                    const label = escapeHtml(String(s.label || ''));
                    return '<a class="btn btn-sm btn-outline-secondary me-2 mb-2" href="' + href + '">' + label + '</a>';
                }).join('');
                return wrap;
            }

            function appendNode(node) {
                box.appendChild(node);
                box.scrollTop = box.scrollHeight;
            }

            function addMsg(text, who, shouldSave) {
                appendNode(createMessageNode(text, who));
                if (shouldSave !== false) {
                    saveItem({ type: 'message', who: who, text: String(text || '') });
                }
            }

            function addSuggestions(list, shouldSave) {
                if (!list || !list.length) return;
                appendNode(createSuggestionsNode(list));
                if (shouldSave !== false) {
                    saveItem({ type: 'suggestions', list: list });
                }
            }

            function renderHistory() {
                const items = getItems();
                if (!items.length) {
                    renderDefaultWelcome();
                    return;
                }

                items.forEach(item => {
                    if (item.type === 'message') {
                        appendNode(createMessageNode(item.text || '', item.who === 'user' ? 'user' : 'bot'));
                    } else if (item.type === 'suggestions' && Array.isArray(item.list)) {
                        appendNode(createSuggestionsNode(item.list));
                    }
                });
                box.scrollTop = box.scrollHeight;
            }

            function renderDefaultWelcome() {
                addMsg('Hi! Main ShopHub Support bot hoon. Main order status, returns, shipping, payments, product prices, store reviews aur recently viewed products me help kar sakta hoon. Agar aap order check karna chahte hain to apna order number bhejein, for example: 45872134.', 'bot');
                addSuggestions([
                    { label: 'My Orders', href: 'orders.php' },
                    { label: 'Track Order', href: 'track-order.php' },
                    { label: 'Returns', href: 'returns.php' },
                    { label: 'Products', href: 'products.php' }
                ]);
            }

            async function sendMsg(text) {
                const msg = (text || '').trim();
                if (!msg) return;

                addMsg(msg, 'user');
                input.value = '';
                send.disabled = true;

                try {
                    const body = new URLSearchParams();
                    body.set('message', msg);
                    body.set('csrf_token', (window.CSRF_TOKEN || ''));
                    const res = await fetch('api/chatbot.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                        body: body.toString()
                    });
                    const data = await res.json();
                    if (!data || !data.success) throw new Error('bad response');
                    addMsg(data.reply || 'OK', 'bot');
                    addSuggestions(data.suggestions || []);
                } catch (e) {
                    addMsg('Sorry, chatbot is unavailable right now. Please try again or use Contact page.', 'bot');
                } finally {
                    send.disabled = false;
                    input.focus();
                }
            }

            send.addEventListener('click', () => sendMsg(input.value));
            clearChatBtn.addEventListener('click', clearChat);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMsg(input.value);
                }
            });
            quicks.forEach(b => b.addEventListener('click', () => sendMsg(b.getAttribute('data-msg') || '')));

            renderHistory();
        })();
    </script>
</body>

</html>