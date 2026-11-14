/**
 * Turbo Relay Upload: browser -> relay (parallel chunks) -> this site (Remote Upload downloader).
 */
(function () {
    'use strict';

    const CHUNK = 8 * 1024 * 1024;
    // ponytail: browsers open at most 6 connections per host, so more streams would only queue.
    // The relay must serve HTTP/1.1 (not HTTP/2) or these all share one connection. Add hostnames to go past 6.
    const STREAMS = 6;
    const RETRIES = 5;

    const el = (id) => document.getElementById(id);
    const startButton = el('aam-turbo-start');
    if (!startButton) return; // relay not configured yet

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const mb = (bytes) => (bytes / 1048576).toFixed(1) + ' MB';

    function ajax(action, data) {
        const body = new URLSearchParams(Object.assign({ action: action, nonce: aamTurbo.nonce }, data));
        return fetch(aamTurbo.ajax_url, { method: 'POST', credentials: 'same-origin', body: body }).then((r) => r.json());
    }

    function show(type, html) {
        const box = el('aam-turbo-message');
        box.className = 'rfu-ai1wm-message ' + type;
        box.innerHTML = html;
        box.style.display = '';
    }

    function progress(done, total, status) {
        const pct = total ? Math.floor((done / total) * 100) : 0;
        el('aam-turbo-fill').style.width = pct + '%';
        el('aam-turbo-pct').textContent = pct + '%';
        el('aam-turbo-size').textContent = mb(done) + ' / ' + mb(total);
        el('aam-turbo-status').textContent = status;
    }

    // XHR rather than fetch: fetch cannot report upload progress.
    function putChunk(url, ticket, blob, offset, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url);
            xhr.setRequestHeader('X-Relay-Ticket', ticket);
            xhr.setRequestHeader('X-Offset', offset);
            xhr.upload.onprogress = (e) => onProgress(e.loaded);
            xhr.onload = () => (xhr.status === 200 ? resolve() : reject({ status: xhr.status, text: xhr.responseText }));
            xhr.onerror = () => reject({ status: 0, text: 'network error' });
            xhr.send(blob);
        });
    }

    async function sendToRelay(file, url, ticket) {
        const offsets = [];
        for (let offset = 0; offset < file.size; offset += CHUNK) offsets.push(offset);

        const sent = {}; // bytes sent per chunk, so a retried chunk is not counted twice
        const started = Date.now();
        const report = () => {
            const done = Object.values(sent).reduce((a, b) => a + b, 0);
            const seconds = (Date.now() - started) / 1000;
            el('aam-turbo-speed').textContent = seconds > 1 ? ((done * 8) / 1e6 / seconds).toFixed(1) + ' Mbps' : '';
            progress(done, file.size, 'Step 1 of 2: sending to relay in ' + STREAMS + ' parallel streams…');
        };

        async function worker() {
            while (offsets.length) {
                const offset = offsets.shift();
                const blob = file.slice(offset, offset + CHUNK);
                for (let attempt = 0; ; attempt++) {
                    try {
                        await putChunk(url, ticket, blob, offset, (loaded) => { sent[offset] = loaded; report(); });
                        sent[offset] = blob.size;
                        break;
                    } catch (e) {
                        // 4xx/507 will not get better on retry; network drops and 5xx might.
                        const fatal = e.status >= 400 && e.status < 500 || e.status === 507;
                        if (fatal || attempt >= RETRIES) throw new Error('Relay refused the upload: ' + (e.text || e.status));
                        sent[offset] = 0;
                        await sleep(2000 * (attempt + 1));
                    }
                }
            }
        }

        await Promise.all(Array.from({ length: STREAMS }, worker));
    }

    // The pull can outlive the HTTP request (proxies cut long requests), so also watch the progress transient.
    function pullToSite(sourceUrl, size) {
        return new Promise((resolve, reject) => {
            let settled = false;
            const finish = (fn, value) => { if (!settled) { settled = true; clearInterval(poll); fn(value); } };

            ajax('rfu_ai1wm_upload_file', { source_url: sourceUrl }).then(
                (r) => (r.success ? finish(resolve, r.data) : finish(reject, new Error(r.data.message))),
                () => {} // request dropped: the poller below decides
            );

            let poll = 0;
            // Start late so the previous run's status has been cleared server-side.
            setTimeout(() => {
                if (settled) return;
                poll = setInterval(() => {
                    ajax('rfu_ai1wm_get_progress', {}).then((r) => {
                        const d = r.data || {};
                        if (d.status === 'downloading') progress(d.downloaded, size, 'Step 2 of 2: this site is pulling the file from the relay…');
                        if (d.status === 'complete') finish(resolve, { filename: d.filename, size: d.downloaded });
                        if (d.status === 'error') finish(reject, new Error(d.error || 'Download failed.'));
                    }, () => {});
                }, 2000);
            }, 3000);
        });
    }

    startButton.addEventListener('click', async () => {
        const file = el('aam-turbo-file').files[0];
        if (!file || !/\.wpress$/i.test(file.name)) {
            return show('error', 'Choose a .wpress backup file first.');
        }

        startButton.disabled = true;
        el('aam-turbo-message').style.display = 'none';
        el('aam-turbo-progress').style.display = '';
        window.onbeforeunload = () => 'Upload in progress';

        try {
            const t = await ajax('aam_turbo_ticket', {});
            if (!t.success) throw new Error(t.data.message);

            // Relay only accepts plain file names.
            const name = file.name.replace(/\.wpress$/i, '').replace(/[^A-Za-z0-9_-]/g, '-') + '.wpress';
            await sendToRelay(file, t.data.relay + '/u/' + t.data.id + '/' + name, t.data.ticket);

            el('aam-turbo-speed').textContent = '';
            progress(0, file.size, 'Step 2 of 2: this site is pulling the file from the relay…');
            const result = await pullToSite(t.data.relay + '/f/' + t.data.id + '/' + name, file.size);

            if (Number(result.size) !== file.size) {
                throw new Error('Size mismatch: sent ' + file.size + ' bytes, site received ' + result.size + '. Delete that backup and try again.');
            }

            progress(file.size, file.size, 'Done. Relay copy deleted.');
            show('success', '✓ <strong>' + result.filename + '</strong> is in your backups. <a class="button button-primary" href="' + aamTurbo.restore_url + '">Open Backups →</a>');
        } catch (e) {
            show('error', e.message);
        }

        window.onbeforeunload = null;
        startButton.disabled = false;
    });
})();
