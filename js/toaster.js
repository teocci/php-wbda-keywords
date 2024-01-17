const $toast = document.getElementById('toast')

function toast(message) {
    showToast()
    $toast.querySelector('.action-notification-message').textContent = message

    setTimeout(() => {
        hideToast()
    }, 1000)
}

function copyToClipboard(numberText) {
    const textarea = document.createElement('textarea')
    textarea.classList.add('hidden')
    textarea.value = numberText
    document.body.appendChild(textarea)

    numberText = numberText.trim()

    navigator.clipboard
        .writeText(numberText)
        .then(() => {
            toast('Артикул скопирован')
        })
        .catch((err) => {
            console.error('Невозможно скопировать в буфер обмена:', err)
        }).finally(() => {

        document.body.removeChild(textarea)
    })
}

function showToast() {
    $toast.classList.add('show')
}

function hideToast() {
    $toast.classList.remove('show')
}