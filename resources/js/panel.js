import './app';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'admin-lte/dist/css/adminlte.min.css';
import 'admin-lte/dist/js/adminlte.min.js';

function initAdminProcurementPolling() {
    const checkbox = document.getElementById('auto-refresh-procurement');
    const stateEl = document.getElementById('procurement-poll-state');
    const pendingEl = document.getElementById('summary-pending-orders');
    const processingEl = document.getElementById('summary-processing-orders');
    const shippedEl = document.getElementById('summary-shipped-orders');
    const newOrdersEl = document.getElementById('summary-new-orders-today');
    const lastSyncEl = document.getElementById('procurement-last-sync');
    const alertEl = document.getElementById('procurement-new-order-alert');

    if (!checkbox || !stateEl || !newOrdersEl) {
        return;
    }

    const snapshotUrl = stateEl.dataset.snapshotUrl || '';
    if (!snapshotUrl) {
        return;
    }

    const pollIntervalMs = Number(stateEl.dataset.pollIntervalMs || 30000);
    let timer = null;
    const numberFormatter = new Intl.NumberFormat('id-ID');
    let baseline = {
        latestOrderId: Number(stateEl.dataset.latestOrderId || 0),
        newOrdersToday: Number(newOrdersEl.dataset.value || 0),
    };

    const updateNumber = (el, value) => {
        if (!el) return;
        el.dataset.value = String(value);
        el.textContent = numberFormatter.format(value);
    };

    const setLastSync = (text) => {
        if (!lastSyncEl) return;
        lastSyncEl.textContent = text;
    };

    const showAlert = (text) => {
        if (!alertEl) return;
        alertEl.textContent = text;
        alertEl.classList.remove('hidden');
    };

    const fetchSnapshot = async () => {
        try {
            const response = await fetch(snapshotUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('snapshot request failed');
            }

            const payload = await response.json();
            const data = payload?.data ?? {};
            const pending = Number(data.pending_orders ?? 0);
            const processing = Number(data.processing_orders ?? 0);
            const shipped = Number(data.shipped_orders ?? 0);
            const newOrdersToday = Number(data.new_orders_today ?? 0);
            const latestOrderId = Number(data.latest_order_id ?? 0);

            updateNumber(pendingEl, pending);
            updateNumber(processingEl, processing);
            updateNumber(shippedEl, shipped);
            updateNumber(newOrdersEl, newOrdersToday);

            const hasNewOrder = latestOrderId > baseline.latestOrderId || newOrdersToday > baseline.newOrdersToday;
            if (hasNewOrder) {
                const delta = Math.max(0, newOrdersToday - baseline.newOrdersToday);
                if (delta > 0) {
                    showAlert(`${delta} order pengadaan baru terdeteksi. Refresh halaman untuk melihat daftar terbaru.`);
                } else {
                    showAlert(`Order pengadaan baru terdeteksi (ID #${latestOrderId}). Refresh halaman untuk melihat daftar terbaru.`);
                }
            }

            baseline = {
                latestOrderId: Math.max(baseline.latestOrderId, latestOrderId),
                newOrdersToday,
            };

            setLastSync(
                new Date().toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                })
            );
        } catch (error) {
            setLastSync('gagal sinkronisasi');
        }
    };

    const start = () => {
        if (!checkbox.checked) return;
        timer = window.setInterval(fetchSnapshot, pollIntervalMs);
    };

    const stop = () => {
        if (!timer) return;
        clearInterval(timer);
        timer = null;
    };

    start();
    fetchSnapshot();
    checkbox.addEventListener('change', () => {
        stop();
        start();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminProcurementPolling);
} else {
    initAdminProcurementPolling();
}
