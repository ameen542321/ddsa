const flashMessagesRoot = document.querySelector('[data-ui-flash-messages]');

if (flashMessagesRoot && window.Swal?.fire) {
    const flashMessages = [
        { icon: 'success', title: flashMessagesRoot.dataset.successMessage },
        { icon: 'error', title: flashMessagesRoot.dataset.errorMessage },
        { icon: 'warning', title: flashMessagesRoot.dataset.warningMessage },
        { icon: 'info', title: flashMessagesRoot.dataset.infoMessage },
    ];

    flashMessages.forEach((flashMessage) => {
        if (!flashMessage.title) return;

        // تبقى رسالة الحالة في مركز الشاشة على جميع المقاسات بدل الظهور في زاوية مختلفة.
        window.Swal.fire({
            icon: flashMessage.icon,
            title: flashMessage.title,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    });
}
