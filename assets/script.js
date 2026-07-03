/**
 * 海龟汤推理馆 — 前端交互引擎 v2
 * 包含：鼠标光效、卡片3D tilt、消息轮询、聊天/提问、涟漪特效
 */
(function() {
    'use strict';

    // ============ 鼠标光晕追踪 ============
    const cursorGlow = document.createElement('div');
    cursorGlow.className = 'cursor-glow';
    document.body.appendChild(cursorGlow);

    let mouseX = 0, mouseY = 0;
    let glowX = 0, glowY = 0;

    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });

    document.addEventListener('mouseleave', () => {
        cursorGlow.style.opacity = '0';
    });

    document.addEventListener('mouseenter', () => {
        cursorGlow.style.opacity = '0.5';
    });

    function animateGlow() {
        glowX += (mouseX - glowX) * 0.08;
        glowY += (mouseY - glowY) * 0.08;
        cursorGlow.style.left = glowX + 'px';
        cursorGlow.style.top = glowY + 'px';
        requestAnimationFrame(animateGlow);
    }
    animateGlow();

    // ============ 卡片 3D Tilt 效果 ============
    function initTiltCards() {
        document.querySelectorAll('.room-card, .feature-card, .stat-card, .soup-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = (y - centerY) / centerY * -8;
                const rotateY = (x - centerX) / centerX * 8;

                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px) scale(1.02)`;

                card.style.setProperty('--mouse-x', x + 'px');
                card.style.setProperty('--mouse-y', y + 'px');
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0) scale(1)';
                card.style.setProperty('--mouse-x', '50%');
                card.style.setProperty('--mouse-y', '50%');
            });
        });
    }

    // ============ 卡片点击涟漪 ============
    function initRipple() {
        document.addEventListener('click', (e) => {
            const target = e.target.closest('.room-card, .feature-card, .stat-card, .soup-card');
            if (!target || target.querySelector('.click-ripple-temp')) return;

            const ripple = document.createElement('div');
            const rect = target.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.cssText = `
                position: absolute;
                left: ${e.clientX - rect.left - size/2}px;
                top: ${e.clientY - rect.top - size/2}px;
                width: ${size}px;
                height: ${size}px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(255,77,106,0.25), transparent 70%);
                animation: rippleOut 0.8s ease-out forwards;
                pointer-events: none;
                z-index: 10;
            `;
            ripple.classList.add('click-ripple-temp');
            target.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove());
        });
    }

    // 涟漪动画 — 注入样式
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        @keyframes rippleOut {
            from { transform: scale(0); opacity: 1; }
            to { transform: scale(2); opacity: 0; }
        }
        .room-card, .feature-card, .stat-card, .soup-card {
            transform-style: preserve-3d;
            transition: transform 0.1s ease-out, box-shadow 0.4s ease, border-color 0.4s ease !important;
        }
    `;
    document.head.appendChild(rippleStyle);

    // ============ 导航栏滚动效果 ============
    function initNavScroll() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // ============ API 基础 ============
    const API_BASE = 'api/';

    async function apiPost(endpoint, data) {
        const formData = new FormData();
        for (const key in data) {
            formData.append(key, data[key]);
        }
        const resp = await fetch(API_BASE + endpoint, {
            method: 'POST',
            body: formData
        });
        return resp.json();
    }

    async function apiGet(endpoint) {
        const resp = await fetch(API_BASE + endpoint);
        return resp.json();
    }

    // ============ 聊天/消息引擎 ============
    window.TurtleChat = {
        roomId: 0,
        lastMsgId: 0,
        pollTimer: null,
        pollInterval: 1500,
        questionCount: 0,
        maxQuestions: 20,

        init: function(roomId, maxQuestions) {
            this.roomId = roomId;
            this.maxQuestions = maxQuestions || 20;
            this.startPoll();
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            const chatInput = document.getElementById('chat-input');
            const chatBtn = document.getElementById('chat-send');
            if (chatInput && chatBtn) {
                chatBtn.addEventListener('click', () => self.sendChat());
                chatInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') self.sendChat();
                });
            }

            const askInput = document.getElementById('ask-input');
            const askBtn = document.getElementById('ask-send');
            if (askInput && askBtn) {
                askBtn.addEventListener('click', () => self.sendQuestion());
                askInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') self.sendQuestion();
                });
            }
        },

        startPoll: function() {
            this.pollTimer = setInterval(() => this.fetchMessages(), this.pollInterval);
            this.fetchMessages();
        },

        stopPoll: function() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        fetchMessages: function() {
            const self = this;
            apiGet('get_messages.php?room_id=' + this.roomId + '&after_id=' + this.lastMsgId)
                .then(data => {
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach((msg, i) => {
                            setTimeout(() => self.appendMessage(msg), i * 50);
                        });
                        self.lastMsgId = data.messages[data.messages.length - 1].id;
                    }
                    if (data.question_count !== undefined) {
                        self.questionCount = data.question_count;
                        self.updateQuestionUI();
                    }
                    if (data.room_status) {
                        self.updateRoomStatus(data.room_status);
                    }
                })
                .catch(err => console.error('轮询错误:', err));
        },

        sendChat: function() {
            const input = document.getElementById('chat-input');
            const content = input.value.trim();
            if (!content) return;

            const btn = document.getElementById('chat-send');
            btn.disabled = true;
            input.value = '';

            apiPost('send_message.php', {
                room_id: this.roomId,
                content: content,
                type: 'chat'
            }).then(() => {
                btn.disabled = false;
                input.focus();
            }).catch(() => {
                btn.disabled = false;
                input.value = content;
            });
        },

        sendQuestion: function() {
            if (this.questionCount >= this.maxQuestions) {
                this.showToast('提问次数已用完！');
                return;
            }

            const input = document.getElementById('ask-input');
            const content = input.value.trim();
            if (!content) return;

            const btn = document.getElementById('ask-send');
            btn.disabled = true;
            input.value = '';

            apiPost('send_message.php', {
                room_id: this.roomId,
                content: content,
                type: 'question'
            }).then(() => {
                btn.disabled = false;
                this.questionCount++;
                this.updateQuestionUI();
                input.focus();
            }).catch(() => {
                btn.disabled = false;
                input.value = content;
            });
        },

        appendMessage: function(msg) {
            const container = document.getElementById('chat-messages');
            if (!container) return;

            const div = document.createElement('div');
            div.className = 'chat-message msg-' + msg.type;

            let headerHtml = '';
            if (msg.type !== 'system') {
                const time = new Date(msg.created_at).toLocaleTimeString('zh-CN', {hour:'2-digit',minute:'2-digit'});
                headerHtml = `<div class="msg-header">
                    <span class="msg-username">${this.escapeHtml(msg.username || '系统')}</span>
                    <span class="msg-time">${time}</span>
                </div>`;
            }

            let bodyHtml;
            if (msg.type === 'answer') {
                bodyHtml = `<span class="answer-badge answer-${this.escapeHtml(msg.content)}">${this.escapeHtml(msg.content)}</span>`;
            } else {
                bodyHtml = this.escapeHtml(msg.content);
            }

            div.innerHTML = headerHtml + `<div class="msg-body">${bodyHtml}</div>`;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        },

        updateQuestionUI: function() {
            const remaining = document.getElementById('question-remaining');
            const askInput = document.getElementById('ask-input');
            const askBtn = document.getElementById('ask-send');

            if (remaining) {
                const left = Math.max(0, this.maxQuestions - this.questionCount);
                remaining.textContent = left;
                remaining.style.color = left <= 3 ? 'var(--accent)' : 'var(--green)';
            }

            if (this.questionCount >= this.maxQuestions) {
                if (askInput) askInput.disabled = true;
                if (askBtn) askBtn.disabled = true;
            }
        },

        updateRoomStatus: function(status) {
            const el = document.getElementById('room-status-badge');
            if (el) {
                const labels = {waiting:'等待中', playing:'游戏中', ended:'已结束'};
                el.textContent = labels[status] || status;
                el.className = 'room-status status-' + status;
            }
        },

        escapeHtml: function(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        showToast: function(msg) {
            const old = document.querySelector('.toast');
            if (old) old.remove();

            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(-50%) translateY(-20px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 2200);
        }
    };

    // ============ 真人主持按钮（房主用） ============
    window.HostPanel = {
        init: function(roomId) {
            this.roomId = roomId;
            document.querySelectorAll('.host-answer-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const answer = btn.getAttribute('data-answer');
                    this.sendAnswer(answer);
                });
            });
        },

        sendAnswer: function(answer) {
            apiPost('send_message.php', {
                room_id: this.roomId,
                content: answer,
                type: 'answer'
            }).catch(err => console.error('回答发送失败:', err));
        }
    };

    // ============ 汤谱搜索 ============
    window.SoupSearch = {
        init: function() {
            const searchInput = document.getElementById('soup-search');
            const tagFilter = document.getElementById('tag-filter');
            const diffFilter = document.getElementById('diff-filter');
            const sortFilter = document.getElementById('sort-filter');

            if (searchInput) searchInput.addEventListener('input', () => this.filter());
            if (tagFilter) tagFilter.addEventListener('change', () => this.filter());
            if (diffFilter) diffFilter.addEventListener('change', () => this.filter());
            if (sortFilter) sortFilter.addEventListener('change', () => this.filter());
        },

        filter: function() {
            const keyword = (document.getElementById('soup-search')?.value || '').toLowerCase();
            const tag = document.getElementById('tag-filter')?.value || '';
            const diff = document.getElementById('diff-filter')?.value || '';

            document.querySelectorAll('.soup-card').forEach(card => {
                const title = card.getAttribute('data-title')?.toLowerCase() || '';
                const tags = card.getAttribute('data-tags')?.toLowerCase() || '';
                const difficulty = card.getAttribute('data-difficulty') || '';

                let show = true;
                if (keyword && !title.includes(keyword) && !tags.includes(keyword)) show = false;
                if (tag && !tags.includes(tag.toLowerCase())) show = false;
                if (diff && difficulty !== diff) show = false;

                card.style.display = show ? '' : 'none';
                if (show) {
                    card.style.animation = 'none';
                    card.offsetHeight;
                    card.style.animation = '';
                }
            });
        }
    };

    // ============ 卡片飞入系统 ============
    function initFlyCards() {
        const directions = ['tl', 't', 'tr', 'r', 'br', 'b', 'bl', 'l'];
        // 目标：所有卡片类元素
        const cardSelector = '.soup-card, .room-card, .feature-card, .stat-card, .card';
        const cards = document.querySelectorAll(cardSelector);

        cards.forEach((card, index) => {
            // 给卡片打上 fly-card 标记
            card.classList.add('fly-card');

            // 随机方向（基于 index 做伪随机，保证刷新后方向不变）
            const dir = directions[index % directions.length];
            card.dataset.flyDir = dir;

            // 交错延迟 60-500ms
            const delay = 60 + (index % 8) * 55 + Math.floor(index / 8) * 20;
            card.style.animationDelay = delay + 'ms';

            // 触发飞入
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    card.classList.add('fly-in', 'dir-' + dir);
                });
            });

            // 动画完成后清理
            card.addEventListener('animationend', function handler() {
                card.classList.add('fly-done');
                card.style.animationDelay = '';
                card.removeEventListener('animationend', handler);
            }, { once: true });
        });
    }

    // ============ 初始化 ============
    document.addEventListener('DOMContentLoaded', () => {
        initNavScroll();
        initFlyCards();
        initTiltCards();
        initRipple();

        // 数字跳动动画
        document.querySelectorAll('.stat-number[data-count]').forEach(el => {
            const target = parseInt(el.getAttribute('data-count'));
            if (isNaN(target)) return;
            const duration = 1500;
            const start = performance.now();
            function update(now) {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(eased * target);
                if (progress < 1) requestAnimationFrame(update);
            }
            requestAnimationFrame(update);
        });
    });

})();
