const $loader = document.getElementById('loader')

function showLoader() {
    $loader.classList.remove('hidden')
}

function hideLoader() {
    $loader.classList.add('hidden')
}