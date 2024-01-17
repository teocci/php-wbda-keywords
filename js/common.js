function createIdCopyIcon($holder, value) {
    const $wrapper = document.createElement('div')
    $wrapper.classList.add('id-wrapper')

    const $id = document.createElement('div')
    $id.classList.add('iw-part', 'iw-value')
    $id.textContent = value

    const $icon = document.createElement('div')
    $icon.classList.add('iw-part', 'iw-icon')
    $icon.dataset.action = 'copy'
    $icon.dataset.value = value

    const $img = document.createElement('img')
    $img.src = '/images/copy.svg'
    $img.alt = 'Copy Icon'

    $icon.append($img)
    $wrapper.append($id)
    $wrapper.append($icon)

    $holder.append($wrapper)
}