document.querySelectorAll('[data-auto-dismiss]').forEach((notification) => {
    let timer;
    const dismiss = () => {
        window.clearTimeout(timer);
        notification.remove();

        const container = document.getElementById('flash-notifications');
        if (container && !container.querySelector('[data-auto-dismiss]')) {
            container.remove();
        }
    };

    notification.querySelector('[data-dismiss-notification]')?.addEventListener('click', dismiss);
    timer = window.setTimeout(dismiss, 10000);
});
