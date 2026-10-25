const container = document.getElementById('toasts');
const currentUserId = Number(document.querySelector('meta[name="user-id"]')?.content);

function toast(message, url) {
    const item = document.createElement('a');
    item.href = url;
    item.className = 'block rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-lg transition-opacity duration-500 hover:bg-gray-50';
    item.textContent = message;
    container.append(item);

    setTimeout(() => item.classList.add('opacity-0'), 6000);
    setTimeout(() => item.remove(), 6500);
}

function updateBadges({ id, statusLabel, statusClasses }) {
    document.querySelectorAll(`[data-live-order-status="${id}"]`).forEach((badge) => {
        badge.className = `rounded px-2 py-0.5 text-xs ${statusClasses}`;
        badge.textContent = statusLabel;
    });
}

if (window.Echo && container) {
    window.Echo.private('admin.orders')
        .listen('.order.paid', (order) => {
            toast(`New order ${order.orderNumber} from ${order.customer}: ${order.total}`, order.url);
        })
        .listen('.order.status-changed', (change) => {
            updateBadges(change);

            // The admin who made the change already sees it on their own page.
            if (change.changedById !== currentUserId) {
                toast(`${change.changedBy} moved ${change.orderNumber} to ${change.statusLabel}`, change.url);
            }
        });
}
