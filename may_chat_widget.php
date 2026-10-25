
<?php
// MindCare: May Chat Widget
// Include this file once, immediately before </body>.
?>

<style>
/* =========================
   Floating chat button
========================= */
#may-launch {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 9998;
    border: 0;
    border-radius: 999px;
    padding: 10px 16px 10px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #117f76;
    color: #fff;
    cursor: pointer;
    box-shadow: 0 8px 28px #0f465033;
    font: 600 14px Tahoma, Arial, sans-serif;
    transition: transform .2s ease, box-shadow .2s ease;
}

#may-launch:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px #0f465044;
}

#may-launch .avatar,
#may-head .avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid #d7e3e1;
    flex: 0 0 38px;
}

#may-launch .avatar img,
#may-head .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

#may-launch .launch-title {
    display: block;
    text-align: left;
}

#may-launch small {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    font-weight: 400;
}

/* =========================
   Chat window
========================= */
#may-panel {
    position: fixed;
    right: 22px;
    bottom: 88px;
    z-index: 9999;
    width: min(370px, calc(100vw - 24px));
    height: min(540px, calc(100vh - 110px));
    display: none;
    flex-direction: column;
    background: #fff;
    border: 1px solid #dce6f2;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 18px 60px #0e1e3c3d;
    color: #17264b;
    font-family: Tahoma, Arial, sans-serif;
}

#may-panel.open {
    display: flex;
    animation: mayPanelIn .22s ease;
}

@keyframes mayPanelIn {
    from {
        opacity: 0;
        transform: translateY(10px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* =========================
   Chat header
========================= */
#may-head {
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 9px;
    background: #f8fafc;
    border-bottom: 1px solid #e7edf4;
    flex-shrink: 0;
}

#may-head .meta {
    flex: 1;
    min-width: 0;
}

#may-head strong {
    display: block;
    font-size: 14px;
}

#may-head small {
    display: block;
    margin-top: 4px;
    color: #66748b;
    font-size: 11px;
}

.may-icon {
    width: 30px;
    height: 30px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #66748b;
    cursor: pointer;
    font: 22px Tahoma, Arial, sans-serif;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .2s ease, color .2s ease;
}

.may-icon:hover {
    background: #e9f3f1;
    color: #117f76;
}

#may-reset {
    font-size: 21px;
    transition: transform .3s ease, color .2s ease;
}

#may-reset:hover {
    transform: rotate(-180deg);
}

/* =========================
   Messages
========================= */
#may-messages {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    scroll-behavior: smooth;
}

#may-messages::-webkit-scrollbar {
    width: 5px;
}

#may-messages::-webkit-scrollbar-thumb {
    background: #dce5ec;
    border-radius: 10px;
}

.may-msg {
    max-width: 88%;
    padding: 10px 12px;
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.65;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    animation: mayPop .22s ease both;
}

@keyframes mayPop {
    from {
        opacity: 0;
        transform: translateY(7px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.may-msg.bot {
    align-self: flex-start;
    background: #f0f2f5;
    color: #263247;
    border-bottom-left-radius: 5px;
}

.may-msg.user {
    align-self: flex-end;
    background: #117f76;
    color: #fff;
    border-bottom-right-radius: 5px;
}

/* =========================
   Small typing indicator
========================= */
.may-msg.bot.may-typing-msg {
    width: fit-content;
    max-width: 70px;
    min-width: 0;
    padding: 9px 12px;
    border-radius: 18px;
    border-bottom-left-radius: 5px;
    background: #f0f2f5;
}

.may-typing {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    height: 12px;
    min-height: 12px;
}

.may-typing span {
    width: 6px;
    height: 6px;
    flex: 0 0 6px;
    border-radius: 50%;
    background: #a1a5ad;
    display: block;
    animation: mayTyping 1s infinite ease-in-out;
}

.may-typing span:nth-child(2) {
    animation-delay: .15s;
}

.may-typing span:nth-child(3) {
    animation-delay: .3s;
}

@keyframes mayTyping {
    0%, 60%, 100% {
        opacity: .45;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-2px);
    }
}

/* =========================
   Suggested messages
========================= */
#may-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 12px 10px;
    flex-shrink: 0;
}

.may-suggest {
    border: 1px solid #cfe5e2;
    border-radius: 999px;
    padding: 6px 10px;
    background: #fff;
    color: #0d675f;
    cursor: pointer;
    font: 11px Tahoma, Arial, sans-serif;
    transition: background .2s ease, transform .2s ease;
}

.may-suggest:hover {
    background: #eaf7f4;
    transform: translateY(-1px);
}

/* =========================
   Input and send button
========================= */
#may-form {
    display: flex;
    gap: 8px;
    align-items: center;
    padding: 9px;
    border-top: 1px solid #e7edf4;
    flex-shrink: 0;
}

#may-input {
    min-width: 0;
    flex: 1;
    border: 1px solid #d5deea;
    border-radius: 999px;
    padding: 11px 13px;
    font: 13px Tahoma, Arial, sans-serif;
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease;
}

#may-input:focus {
    border-color: #117f76;
    box-shadow: 0 0 0 3px #117f7615;
}

#may-send {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    border: 0;
    border-radius: 50%;
    background: #117f76;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    transition: transform .2s ease, background .2s ease;
}

#may-send:hover {
    background: #0d6d65;
    transform: scale(1.05);
}

#may-note {
    text-align: center;
    color: #7b8798;
    font-size: 10px;
    line-height: 1.5;
    padding: 0 10px 9px;
    flex-shrink: 0;
}

/* =========================
   Mobile
========================= */
@media (max-width: 480px) {
    #may-launch {
        right: 12px;
        bottom: 12px;
    }

    #may-panel {
        right: 8px;
        bottom: 74px;
        width: calc(100vw - 16px);
        height: min(560px, calc(100vh - 90px));
    }

    #may-head {
        gap: 6px;
        padding: 10px;
    }
}

@media (prefers-reduced-motion: reduce) {
    #may-panel.open,
    .may-msg,
    .may-typing span {
        animation: none;
    }

    #may-messages {
        scroll-behavior: auto;
    }
    
/* แก้การแบ่งพื้นที่ของหน้าต่างแชท */
#may-panel {
    display: none;
    flex-direction: column;
    box-sizing: border-box;
    min-height: 0;
}

#may-panel.open {
    display: flex;
}

/* ให้พื้นที่ข้อความเลื่อนแยกจากส่วนล่าง */
#may-messages {
    flex: 1 1 0%;
    height: 0;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px;
    box-sizing: border-box;
    scroll-behavior: smooth;
}

/* ป้องกันกล่องข้อความถูกบีบ */
#may-messages .may-msg {
    flex: 0 0 auto;
    max-width: 88%;
    box-sizing: border-box;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: normal;
}

/* จุดกำลังพิมพ์ยังคงมีขนาดเล็ก */
#may-messages .may-typing-msg {
    width: fit-content;
    max-width: 70px;
    flex: 0 0 auto;
}

/* ปุ่มแนะนำต้องอยู่ใต้พื้นที่ข้อความเสมอ */
#may-suggestions {
    position: relative;
    flex: 0 0 auto;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 12px;
    background: #fff;
    box-sizing: border-box;
}

/* ช่องพิมพ์และข้อความแจ้งเตือนไม่ถูกบีบ */
#may-form,
#may-note {
    position: relative;
    flex: 0 0 auto;
    background: #fff;
    box-sizing: border-box;
}

}
</style>

<!-- ปุ่มเปิดแชท -->
<button
    id="may-launch"
    type="button"
    aria-controls="may-panel"
    aria-expanded="false"
>
    <span class="avatar">
        <img
            src="https://cdn.discordapp.com/attachments/1280536102024904777/1562841883238207519/Gemini_Generated_Image_lwbljvlwbljvlwbl-photoaidcom-cropped.jpg?ex=6adec12d&is=6add6fad&hm=0ff5b596388108d77ef4057a8fb31a183de1c29234beca25cd02684e2a1a7df4&"
            alt="โลโก้ MindCare"
        >
    </span>

    <span class="launch-title">
        คุยกับเมย์
        <small>MindCare AI</small>
    </span>
</button>

<!-- หน้าต่างแชท -->
<section
    id="may-panel"
    role="dialog"
    aria-label="แชทกับเมย์ MindCare"
    aria-hidden="true"
>
    <header id="may-head">
        <span class="avatar">
            <img
                src="https://cdn.discordapp.com/attachments/1280536102024904777/1562841883238207519/Gemini_Generated_Image_lwbljvlwbljvlwbl-photoaidcom-cropped.jpg?ex=6adec12d&is=6add6fad&hm=0ff5b596388108d77ef4057a8fb31a183de1c29234beca25cd02684e2a1a7df4&"
                alt="โลโก้ MindCare"
            >
        </span>

        <div class="meta">
            <strong>เมย์ · MindCare</strong>
            <small>ผู้ช่วยรับฟังและดูแลสุขภาพใจ</small>
        </div>

        <button
            class="may-icon"
            id="may-reset"
            type="button"
            title="เริ่มแชทใหม่"
            aria-label="เริ่มแชทใหม่"
        >↻</button>

        <button
            class="may-icon"
            id="may-min"
            type="button"
            aria-label="ย่อหน้าต่างแชท"
            title="ย่อ"
        >−</button>

        <button
            class="may-icon"
            id="may-close"
            type="button"
            aria-label="ปิดแชท"
            title="ปิด"
        >×</button>
    </header>

    <div id="may-messages" aria-live="polite" aria-relevant="additions">
        <div class="may-msg bot">สวัสดีค่ะ เมย์จาก MindCare ยินดีรับฟังนะคะ 💚 วันนี้มีเรื่องอะไรที่อยากพูดคุยหรือให้เมย์ช่วยไหมคะ</div>
    </div>

    <div id="may-suggestions">
        <button class="may-suggest" type="button">ช่วงนี้เครียดมาก</button>
        <button class="may-suggest" type="button">อยากทำแบบประเมิน</button>
        <button class="may-suggest" type="button">อยากคุยเรื่องการเรียน</button>
    </div>

    <form id="may-form">
        <input
            id="may-input"
            type="text"
            maxlength="2000"
            autocomplete="off"
            placeholder="พิมพ์ข้อความถึงเมย์..."
            aria-label="พิมพ์ข้อความถึงเมย์"
            required
        >

        <button id="may-send" type="submit" aria-label="ส่งข้อความ">➤</button>
    </form>

    <div id="may-note">
        เมย์ให้ข้อมูลและการรับฟังเบื้องต้น ไม่ใช่การวินิจฉัยหรือบริการฉุกเฉิน
    </div>
</section>

<script>

(function () {
    'use strict';

    const launch = document.getElementById('may-launch');
    const panel = document.getElementById('may-panel');
    const input = document.getElementById('may-input');
    const messages = document.getElementById('may-messages');
    const form = document.getElementById('may-form');
    const suggestions = document.getElementById('may-suggestions');
    const closeBtn = document.getElementById('may-close');
    const minBtn = document.getElementById('may-min');
    const resetBtn = document.getElementById('may-reset');
    const sendBtn = document.getElementById('may-send');

    if (
        !launch || !panel || !input || !messages ||
        !form || !suggestions || !resetBtn
    ) {
        console.error('May Chat: ไม่พบองค์ประกอบที่จำเป็น');
        return;
    }

    // API ของเมย์บน XAMPP
    const API_URL = '/mindcare/api/may_chat.php';

    const initialMessages = messages.innerHTML;
    const activeRequests = new Set();
    let resetVersion = 0;

    function openPanel(show) {
        panel.classList.toggle('open', show);
        panel.setAttribute('aria-hidden', show ? 'false' : 'true');
        launch.setAttribute('aria-expanded', show ? 'true' : 'false');

        if (show) input.focus();
    }

    
function scrollToBottom() {
    requestAnimationFrame(function () {
        messages.scrollTop = messages.scrollHeight;
    });
}


    function addMessage(text, role) {
        const div = document.createElement('div');
        div.className = 'may-msg ' + role;
        div.textContent = text;
        messages.appendChild(div);
        scrollToBottom();
        return div;
    }

    // จุดกำลังพิมพ์ขนาดเล็กตามดีไซน์เดิม
    function addTyping() {
        const div = document.createElement('div');
        div.className = 'may-msg bot may-typing-msg';
        div.setAttribute('role', 'status');
        div.setAttribute('aria-label', 'เมย์กำลังพิมพ์');

        const dots = document.createElement('div');
        dots.className = 'may-typing';

        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('span');
            dot.setAttribute('aria-hidden', 'true');
            dots.appendChild(dot);
        }

        div.appendChild(dots);
        messages.appendChild(div);
        scrollToBottom();

        return div;
    }

    async function sendMessage(text) {
        const userText = text.trim();

        if (!userText) return;

        addMessage(userText, 'user');

        const typing = addTyping();
        const versionAtStart = resetVersion;
        const controller = new AbortController();

        activeRequests.add(controller);

        if (sendBtn) sendBtn.disabled = true;

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: userText
                }),
                signal: controller.signal
            });

            const data = await response.json();

            // ถ้าผู้ใช้รีเซ็ตแชทระหว่างรอ ไม่แสดงคำตอบเก่า
            if (versionAtStart !== resetVersion) return;

            if (!response.ok || !data.ok) {
                throw new Error(
                    data.error || 'ไม่สามารถรับคำตอบจากเมย์ได้'
                );
            }

            if (typeof data.reply !== 'string' || !data.reply.trim()) {
                throw new Error('เมย์ยังไม่มีข้อความตอบกลับค่ะ');
            }

            // แสดงคำตอบจริงจาก Gemini
            addMessage(data.reply.trim(), 'bot');

        } catch (error) {
            if (versionAtStart !== resetVersion) return;

            if (error.name === 'AbortError') return;

            console.error('May Chat API error:', error);

            addMessage(
                'ขอโทษนะคะ ตอนนี้เมย์ยังตอบไม่ได้ชั่วคราว 💚 ' +
                'ลองใหม่อีกครั้งได้ไหมคะ หากยังไม่ได้ กรุณาตรวจสอบการเชื่อมต่อ AI',
                'bot'
            );

        } finally {
            typing.remove();
            activeRequests.delete(controller);

            if (sendBtn) {
                sendBtn.disabled = activeRequests.size > 0;
            }

            scrollToBottom();
        }
    }

    launch.addEventListener('click', function () {
        openPanel(!panel.classList.contains('open'));
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            openPanel(false);
        });
    }

    if (minBtn) {
        minBtn.addEventListener('click', function () {
            openPanel(false);
        });
    }

    // ปุ่มข้อความแนะนำ
    suggestions.addEventListener('click', function (event) {
        const btn = event.target.closest('.may-suggest');
        if (!btn) return;

        const text = btn.textContent.trim();
        input.value = '';
        sendMessage(text);
        input.focus();
    });

    // ส่งข้อความจริงไปยัง PHP API
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const text = input.value.trim();
        if (!text) return;

        input.value = '';
        sendMessage(text);
        input.focus();
    });

    // เริ่มบทสนทนาใหม่
    resetBtn.addEventListener('click', function () {
        resetVersion++;

        // ยกเลิกคำขอเก่าทั้งหมด
        activeRequests.forEach(function (controller) {
            controller.abort();
        });
        activeRequests.clear();

        if (sendBtn) sendBtn.disabled = false;

        messages.innerHTML = initialMessages;
        input.value = '';
        scrollToBottom();
        input.focus();
    });

})();
</script>
