/* ─────────────────────────────────────────────────────────────────────────────
   For Gloria — single page flow
   Vanilla JS only. Each answer is saved before the next view appears, and she
   resumes from the last saved step if she closes the page.
   ───────────────────────────────────────────────────────────────────────────── */

(() => {
    'use strict';

    const TOTAL_STEPS = 6;
    const TOKEN_KEY = 'gloria.proposal.token';
    const API_SAVE = 'api/save.php';
    const API_STATE = 'api/state.php';
    const NETWORK_MESSAGE =
        "We couldn't save that just now. Check your connection and tap again — your answer is still here.";

    const body = document.body;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const progress = document.getElementById('progress');
    const progressLabel = document.getElementById('progress-label');
    const progressTrack = document.getElementById('progress-track');
    const progressFill = document.getElementById('progress-fill');
    const toast = document.getElementById('toast');

    const answers = {
        comfortable: null,
        likes: '',
        dislikes: '',
        future_serious: '',
        lovely_future: '',
        forever: null,
    };

    const stepFields = {
        2: 'likes',
        3: 'dislikes',
    };

    const choiceFields = {
        1: 'comfortable',
        4: 'future_serious',
        5: 'lovely_future',
        6: 'forever',
    };

    let csrf = body.dataset.csrf || '';
    let token = readToken();
    let currentStep = 0;
    let completed = false;
    let saving = false;
    let activeView = document.querySelector('.view.is-active');
    let leavingTimer = null;
    let toastTimer = null;
    let toastHideTimer = null;
    let celebrationStarted = false;

    class ApiError extends Error {
        constructor(message, status = 0) {
            super(message);
            this.name = 'ApiError';
            this.status = status;
        }
    }

    // ── Boot ─────────────────────────────────────────────────────────────────

    const stateReady = loadState();

    stateReady.then((state) => {
        if (state) {
            hydrate(state);
        }
    });

    bindEvents();
    seedBackgroundHearts();
    handlePhotoFallback();
    initMusic();
    prepareTextareas();

    document.addEventListener('visibilitychange', () => {
        body.classList.toggle('is-paused', document.hidden);
    });

    // ── State + token ────────────────────────────────────────────────────────

    function validToken(value) {
        return typeof value === 'string' && /^[a-f0-9]{32}$/.test(value);
    }

    function readToken() {
        try {
            const stored = window.localStorage.getItem(TOKEN_KEY);
            return validToken(stored) ? stored : '';
        } catch (error) {
            return '';
        }
    }

    function writeToken(value) {
        try {
            window.localStorage.setItem(TOKEN_KEY, value);
        } catch (error) {
            /* Private mode / storage full: the HttpOnly cookie still covers us. */
        }
    }

    function clearTokenStorage() {
        try {
            window.localStorage.removeItem(TOKEN_KEY);
        } catch (error) {
            /* ignore */
        }
    }

    async function loadState(retried = false) {
        try {
            const url = token ? `${API_STATE}?token=${encodeURIComponent(token)}` : API_STATE;
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 7000);

            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            window.clearTimeout(timeout);

            if (!response.ok) {
                return null;
            }

            const data = await response.json();
            if (!data || data.ok !== true) {
                return null;
            }

            if (typeof data.csrf_token === 'string' && data.csrf_token !== '') {
                csrf = data.csrf_token;
            }

            if (data.found !== true) {
                // Local storage could be stale while the server cookie is still
                // valid — give the cookie one chance before forgetting the token.
                if (token && !retried) {
                    token = '';
                    clearTokenStorage();
                    return loadState(true);
                }
                return null;
            }

            if (validToken(data.session_token)) {
                token = data.session_token;
                writeToken(token);
            }

            return data;
        } catch (error) {
            return null;
        }
    }

    function hydrate(state) {
        if (state.answers && typeof state.answers === 'object') {
            Object.assign(answers, state.answers);
        }
        if (state.forever === 'yes' || state.forever === 'no' || state.forever === 'not_ready') {
            answers.forever = state.forever;
        }

        currentStep = Number(state.current_step) || 0;
        completed = state.completed === true;

        fillAnswers();

        if (completed) {
            if (answers.forever === 'yes') {
                showView('yes');
            } else if (answers.forever === 'no') {
                showView('no');
            } else if (answers.forever === 'not_ready') {
                showView('not-ready');
            } else {
                // Defensive: a completed row with no final answer restarts the last step.
                showView('6');
            }
            return;
        }

        if (currentStep >= 1 && currentStep < TOTAL_STEPS) {
            showView(String(currentStep + 1));
            showToast('Welcome back, Gloria 💗');
        }
    }

    function fillAnswers() {
        Object.entries(stepFields).forEach(([step, field]) => {
            const textarea = document.getElementById(field);
            if (!textarea) {
                return;
            }
            textarea.value = answers[field] || '';
            autoGrow(textarea);
        });
    }

    // ── Events ───────────────────────────────────────────────────────────────

    function bindEvents() {
        const proceed = document.querySelector('[data-action="proceed"]');
        if (proceed) {
            proceed.addEventListener('click', async () => {
                await stateReady;
                if (!activeView || activeView.id !== 'view-welcome') {
                    return;
                }
                showView('1');
            });
        }

        document.querySelectorAll('[data-choice]').forEach((button) => {
            button.addEventListener('click', () => handleChoice(button));
        });

        document.querySelectorAll('[data-form-step]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                handleTextStep(form);
            });

            form.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                    event.preventDefault();
                    form.requestSubmit();
                }
            });
        });

        document.querySelectorAll('.step-form textarea').forEach((textarea) => {
            const step = Number(textarea.closest('[data-form-step]').dataset.formStep);
            const error = document.getElementById(`error-${step}`);

            textarea.addEventListener('input', () => {
                autoGrow(textarea);
                textarea.setAttribute('aria-invalid', 'false');
                hideError(error);
            });
        });
    }

    // ── Choices ──────────────────────────────────────────────────────────────

    async function handleChoice(button) {
        if (saving) {
            return;
        }

        const step = Number(button.dataset.step);
        const choice = button.dataset.choice;
        const allowed = step === 6 ? ['yes', 'no', 'not_ready'] : ['yes', 'no'];
        if (!Number.isInteger(step) || !allowed.includes(choice) || !choiceFields[step]) {
            return;
        }

        const group = button.closest('.choices');
        const buttons = group ? Array.from(group.querySelectorAll('[data-choice]')) : [button];
        const error = document.getElementById(`error-${step}`);
        const field = choiceFields[step];

        hideError(error);
        saving = true;
        setChoiceBusy(buttons, true, button);

        try {
            await saveStep(step, { [field]: choice });
            answers[field] = choice;

            if (step === 6) {
                completed = true;
                if (choice === 'yes') {
                    showView('yes');
                } else if (choice === 'no') {
                    showView('no');
                } else {
                    showView('not-ready');
                }
            } else {
                showView(String(step + 1));
            }
        } catch (error_) {
            showError(error, messageFor(error_));
            setChoiceBusy(buttons, false);
        } finally {
            saving = false;
        }
    }

    function setChoiceBusy(buttons, busy, selected) {
        buttons.forEach((button) => {
            button.disabled = busy;
            button.classList.toggle('is-selected', busy && button === selected);
        });
    }

    // ── Text steps ───────────────────────────────────────────────────────────

    async function handleTextStep(form) {
        if (saving) {
            return;
        }

        const step = Number(form.dataset.formStep);
        const field = stepFields[step];
        const textarea = form.querySelector('textarea');
        const error = document.getElementById(`error-${step}`);
        const submit = form.querySelector('button[type="submit"]');

        if (!field || !textarea) {
            return;
        }

        const value = textarea.value.trim();

        if (value.length < 3) {
            showError(error, 'Just a few more words, please 💗');
            textarea.setAttribute('aria-invalid', 'true');
            textarea.focus();
            return;
        }

        hideError(error);
        saving = true;
        form.classList.add('is-saving');
        textarea.disabled = true;
        if (submit) {
            submit.disabled = true;
        }

        try {
            await saveStep(step, { [field]: value });
            answers[field] = value;
            showView(String(step + 1));
        } catch (error_) {
            showError(error, messageFor(error_));
            textarea.setAttribute('aria-invalid', 'true');
            textarea.focus();
        } finally {
            saving = false;
            form.classList.remove('is-saving');
            textarea.disabled = false;
            if (submit) {
                submit.disabled = false;
            }
        }
    }

    // ── Saving ───────────────────────────────────────────────────────────────

    async function saveStep(step, payload, allowRetry = true) {
        const send = async () => {
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 15000);

            try {
                const response = await fetch(API_SAVE, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-Token': csrf,
                    },
                    body: JSON.stringify({
                        csrf_token: csrf,
                        session_token: token || null,
                        step,
                        ...payload,
                    }),
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (error) {
                    data = null;
                }

                return { response, data };
            } finally {
                window.clearTimeout(timeout);
            }
        };

        let { response, data } = await send();

        // The PHP session may have expired while she was writing. Refresh the
        // CSRF token once and retry silently so her words are never lost.
        if (response.status === 419 && allowRetry) {
            const refreshed = await refreshCsrf();
            if (refreshed) {
                ({ response, data } = await send());
            }
        }

        if (!response.ok || !data || data.ok !== true) {
            const message = data && typeof data.message === 'string' && data.message !== ''
                ? data.message
                : NETWORK_MESSAGE;
            throw new ApiError(message, response.status);
        }

        if (typeof data.csrf_token === 'string' && data.csrf_token !== '') {
            csrf = data.csrf_token;
        }

        if (validToken(data.session_token)) {
            token = data.session_token;
            writeToken(token);
        }

        if (typeof data.current_step === 'number') {
            currentStep = data.current_step;
        }

        if (typeof data.completed === 'boolean') {
            completed = data.completed;
        }

        if (data.forever === 'yes' || data.forever === 'no' || data.forever === 'not_ready') {
            answers.forever = data.forever;
        }

        return data;
    }

    async function refreshCsrf() {
        try {
            const url = token ? `${API_STATE}?token=${encodeURIComponent(token)}` : API_STATE;
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                return false;
            }
            const data = await response.json();
            if (data && data.ok === true && typeof data.csrf_token === 'string' && data.csrf_token !== '') {
                csrf = data.csrf_token;
                return true;
            }
        } catch (error) {
            return false;
        }

        return false;
    }

    function messageFor(error) {
        if (error instanceof ApiError && error.message) {
            return error.message;
        }
        return NETWORK_MESSAGE;
    }

    function showError(element, message) {
        if (!element) {
            return;
        }
        element.textContent = message;
        element.hidden = false;
    }

    function hideError(element) {
        if (!element) {
            return;
        }
        element.hidden = true;
        element.textContent = '';
    }

    // ── View transitions ─────────────────────────────────────────────────────

    function showView(name) {
        const target = document.getElementById(`view-${name}`);
        if (!target) {
            return;
        }

        if (name === 'yes') {
            body.classList.add('is-celebrating');
            body.classList.remove('is-closed');
        } else if (name === 'no' || name === 'not-ready') {
            body.classList.remove('is-celebrating');
            body.classList.add('is-closed');
        } else {
            body.classList.remove('is-celebrating', 'is-closed');
        }

        if (target === activeView) {
            updateProgress(name);
            if (name === 'yes') {
                startCelebration();
            }
            return;
        }

        if (leavingTimer) {
            window.clearTimeout(leavingTimer);
            leavingTimer = null;
        }

        const previous = activeView;
        if (previous) {
            previous.classList.remove('is-active');
            previous.classList.add('is-leaving');
            const old = previous;

            leavingTimer = window.setTimeout(() => {
                if (old !== activeView) {
                    old.hidden = true;
                    old.classList.remove('is-leaving');
                }
                leavingTimer = null;
            }, 190);
        }

        target.hidden = false;
        target.classList.remove('is-leaving');
        target.classList.remove('is-active');
        void target.offsetWidth; // restart the entrance animation
        target.classList.add('is-active');
        activeView = target;

        updateProgress(name);

        window.scrollTo({
            top: 0,
            behavior: reducedMotion ? 'auto' : 'smooth',
        });

        const heading = target.querySelector('[tabindex="-1"]');
        if (heading) {
            window.setTimeout(() => heading.focus({ preventScroll: true }), 90);
        }

        if (name === 'yes') {
            startCelebration();
        }
    }

    function updateProgress(name) {
        const step = Number(name);
        const isStep = Number.isInteger(step) && step >= 1 && step <= TOTAL_STEPS;

        if (!progress) {
            return;
        }

        progress.hidden = !isStep;
        if (!isStep) {
            return;
        }

        if (progressLabel) {
            progressLabel.textContent = `${step} of ${TOTAL_STEPS}`;
        }
        if (progressFill) {
            progressFill.style.width = `${(step / TOTAL_STEPS) * 100}%`;
        }
        if (progressTrack) {
            progressTrack.setAttribute('aria-valuenow', String(step));
            progressTrack.setAttribute('aria-valuetext', `Step ${step} of ${TOTAL_STEPS}`);
        }
    }

    // ── Textareas ────────────────────────────────────────────────────────────

    function prepareTextareas() {
        document.querySelectorAll('.step-form textarea').forEach((textarea) => {
            autoGrow(textarea);
        });
    }

    function autoGrow(textarea) {
        if (!textarea) {
            return;
        }
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 360)}px`;
    }

    // ── Floating background hearts ───────────────────────────────────────────

    function seedBackgroundHearts() {
        const wrap = document.getElementById('bg-hearts');
        if (!wrap || reducedMotion) {
            return;
        }

        const count = window.innerWidth < 640 ? 9 : 15;

        for (let index = 0; index < count; index += 1) {
            const heart = document.createElement('span');
            const alpha = (0.05 + Math.random() * 0.11).toFixed(2);

            heart.className = 'bg-heart';
            heart.textContent = Math.random() > 0.45 ? '♥' : '♡';
            heart.setAttribute('aria-hidden', 'true');
            heart.style.left = `${(Math.random() * 100).toFixed(2)}%`;
            heart.style.setProperty('--size', `${(11 + Math.random() * 24).toFixed(1)}px`);
            heart.style.setProperty('--drift', `${(Math.random() * 150 - 75).toFixed(1)}px`);
            heart.style.setProperty('--alpha', alpha);
            heart.style.animationDuration = `${(20 + Math.random() * 24).toFixed(1)}s`;
            heart.style.animationDelay = `${(-Math.random() * 38).toFixed(1)}s`;

            wrap.appendChild(heart);
        }
    }

    // ── Photo placeholder fallback ───────────────────────────────────────────

    function handlePhotoFallback() {
        const photo = document.querySelector('[data-photo]');
        const portrait = document.getElementById('portrait');
        if (!photo || !portrait) {
            return;
        }

        const markMissing = () => portrait.classList.add('is-missing');

        if (photo.complete && photo.naturalWidth === 0) {
            markMissing();
        }

        photo.addEventListener('error', markMissing);
    }

    // ── Music (off by default, never autoplays) ──────────────────────────────

    function initMusic() {
        const audio = document.getElementById('music');
        const toggle = document.getElementById('music-toggle');
        if (!audio || !toggle) {
            return;
        }

        const label = toggle.querySelector('.music-label');

        const render = (playing) => {
            toggle.classList.toggle('is-playing', playing);
            toggle.setAttribute('aria-pressed', playing ? 'true' : 'false');
            if (label) {
                label.textContent = playing ? 'Pause music' : 'Play soft music';
            }
        };

        audio.volume = 0.15;
        audio.addEventListener('play', () => render(true));
        audio.addEventListener('pause', () => render(false));

        toggle.addEventListener('click', async () => {
            if (!audio.paused) {
                audio.pause();
                return;
            }

            try {
                await audio.play();
                fadeVolume(audio, 0.45, 1400);
            } catch (error) {
                // No file yet? Hide the control rather than show a broken one.
                console.warn(
                    '[for-gloria] Add a song at assets/audio/music.mp3 to enable the music toggle.'
                );
                toggle.hidden = true;
            }
        });
    }

    function fadeVolume(audio, target, duration) {
        const from = audio.volume;
        const started = performance.now();

        const tick = (now) => {
            const progress_ = Math.min(1, (now - started) / duration);
            audio.volume = from + (target - from) * progress_;
            if (progress_ < 1 && !audio.paused) {
                window.requestAnimationFrame(tick);
            }
        };

        window.requestAnimationFrame(tick);
    }

    // ── Celebration ──────────────────────────────────────────────────────────

    function startCelebration() {
        if (celebrationStarted) {
            return;
        }
        celebrationStarted = true;

        if (reducedMotion) {
            return;
        }

        launchConfetti(7000);
        spawnRisingHearts(11000);
    }

    function launchConfetti(duration) {
        if (typeof window.confetti !== 'function') {
            window.addEventListener('load', () => launchConfetti(3200), { once: true });
            return;
        }

        const colors = ['#d90429', '#ffffff', '#ffb3c1', '#8d0801', '#f47283', '#ffd6dd'];
        const base = { colors, disableForReducedMotion: true, zIndex: 80 };
        const fire = (options) => window.confetti({ ...base, ...options });

        fire({
            particleCount: 180,
            spread: 105,
            startVelocity: 58,
            origin: { y: 0.64 },
            scalar: 1.05,
            ticks: 300,
        });
        fire({
            particleCount: 110,
            angle: 58,
            spread: 62,
            startVelocity: 64,
            origin: { x: 0, y: 0.78 },
            scalar: 0.95,
        });
        fire({
            particleCount: 110,
            angle: 122,
            spread: 62,
            startVelocity: 64,
            origin: { x: 1, y: 0.78 },
            scalar: 0.95,
        });

        window.setTimeout(() => {
            fire({
                particleCount: 120,
                spread: 150,
                startVelocity: 38,
                origin: { y: 0.55 },
                scalar: 1.2,
                shapes: ['star', 'circle'],
            });
        }, 320);

        window.setTimeout(() => {
            fire({
                particleCount: 90,
                spread: 120,
                startVelocity: 34,
                origin: { y: 0.5 },
                scalar: 1.1,
                gravity: 0.9,
                ticks: 260,
            });
        }, 850);

        const endsAt = Date.now() + duration;
        const interval = window.setInterval(() => {
            if (Date.now() > endsAt) {
                window.clearInterval(interval);
                return;
            }

            fire({
                particleCount: 6,
                angle: 60,
                spread: 70,
                startVelocity: 52,
                origin: { x: 0, y: 0.8 },
                scalar: 0.9,
                shapes: ['circle', 'square'],
            });
            fire({
                particleCount: 6,
                angle: 120,
                spread: 70,
                startVelocity: 52,
                origin: { x: 1, y: 0.8 },
                scalar: 0.9,
                shapes: ['circle', 'square'],
            });

            if (Math.random() > 0.55) {
                fire({
                    particleCount: 14,
                    spread: 360,
                    startVelocity: 24,
                    origin: { x: 0.5, y: 0.46 },
                    scalar: 0.7,
                    gravity: 0.8,
                    ticks: 200,
                    shapes: ['star'],
                });
            }
        }, 210);
    }

    function spawnRisingHearts(duration) {
        const container = document.getElementById('rising-hearts');
        if (!container) {
            return;
        }

        const colors = ['#d90429', '#f47283', '#b00322', '#e8596c'];
        const endsAt = Date.now() + duration;

        const spawn = () => {
            if (Date.now() > endsAt) {
                return;
            }

            const heart = document.createElement('span');
            heart.className = 'rising-heart';
            heart.textContent = Math.random() > 0.25 ? '♥' : '♡';
            heart.setAttribute('aria-hidden', 'true');
            heart.style.left = `${(Math.random() * 100).toFixed(2)}%`;
            heart.style.fontSize = `${(12 + Math.random() * 20).toFixed(1)}px`;
            heart.style.color = colors[Math.floor(Math.random() * colors.length)];
            heart.style.animationDuration = `${(4 + Math.random() * 4).toFixed(1)}s`;
            heart.style.setProperty('--sway', `${(Math.random() * 120 - 60).toFixed(1)}px`);
            heart.style.setProperty('--o', (0.55 + Math.random() * 0.4).toFixed(2));

            heart.addEventListener('animationend', () => heart.remove());
            container.appendChild(heart);

            window.setTimeout(spawn, 130 + Math.random() * 190);
        };

        spawn();
    }

    // ── Toast ────────────────────────────────────────────────────────────────

    function showToast(message, duration = 3800) {
        if (!toast) {
            return;
        }

        window.clearTimeout(toastTimer);
        window.clearTimeout(toastHideTimer);

        toast.textContent = message;
        toast.hidden = false;
        window.requestAnimationFrame(() => toast.classList.add('is-visible'));

        toastTimer = window.setTimeout(() => {
            toast.classList.remove('is-visible');
            toastHideTimer = window.setTimeout(() => {
                toast.hidden = true;
            }, 320);
        }, duration);
    }
})();
