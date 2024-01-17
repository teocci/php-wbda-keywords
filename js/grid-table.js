const STATUS_UID_INIT = 0
const STATUS_UID_LOADING = 1
const STATUS_UID_LOADED = 2
const STATUS_UID_RENDERED = 3
const STATUS_UID_ERROR = 4

const Grid = gridjs.Grid
const GridHTML = gridjs.html

let resolver
let grid

function initTableActions() {
    const links = document.querySelectorAll('[id^="btn-link-"]')

    links.forEach($link => {
        console.log({$link, id: $link.dataset.id})
        $link.onclick = event => {
            event.preventDefault()

            const $btn = event.target
            const action = $btn.dataset.action
            const id = $btn.dataset.id

            const link = LINK_MAPPER[action]
            const href = link.href.replace('{id}', id)

            console.log({$btn, link})

            window.open(href, '_blank')
        }
    })
}

function initTableClipboard() {
    const copyButtons = document.querySelectorAll('.iw-icon[data-action="copy"]')

    copyButtons.forEach($icon => {
        $icon.onclick = event => {
            event.preventDefault()

            const id = $icon.dataset.value
            console.log({$icon})
            console.log(`copy: ${id}`)
            copyToClipboard(id)
        }
    })
}

function removeTable($holder) {
    if (grid != null) grid.destroy()

    const children = Array.from($holder.children)
    children.forEach(child => {
        child.remove()
    })
}

function subscribe(fn) {
    if (grid == null) return

    grid.config.store.subscribe(e => {
        e.status === STATUS_UID_RENDERED && fn(e)
    })
}