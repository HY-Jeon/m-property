// 자체 배너 광고 노출/클릭 트래킹 → FastAPI 트래킹 서버 (POST {endpoint}/events)

const endpoint = document.querySelector('meta[name="tracker-endpoint"]')?.content?.trim();

function getSessionId() {
    const key = 'ad_session_id';
    try {
        let id = sessionStorage.getItem(key);
        if (!id) {
            id = crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            sessionStorage.setItem(key, id);
        }
        return id;
    } catch {
        return null;
    }
}

function send(event, adId) {
    const url = `${endpoint.replace(/\/+$/, '')}/events`;
    const body = JSON.stringify({
        event,
        ad_id: adId,
        page: location.pathname,
        referrer: document.referrer || null,
        ts: new Date().toISOString(),
        session_id: getSessionId(),
    });

    // text/plain → CORS simple request (preflight 없음)
    const blob = new Blob([body], { type: 'text/plain' });
    if (navigator.sendBeacon?.(url, blob)) {
        return;
    }
    fetch(url, { method: 'POST', body: blob, keepalive: true, mode: 'cors' }).catch(() => {});
}

if (endpoint) {
    document.querySelectorAll('[data-ad-slot]').forEach((slot) => {
        const adId = slot.dataset.adId;

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    send('impression', adId);
                    observer.disconnect();
                }
            }, { threshold: 0.5 });
            observer.observe(slot);
        } else {
            send('impression', adId);
        }

        slot.querySelector('a')?.addEventListener('click', () => send('click', adId));
    });
}
