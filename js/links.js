const LINK_KEY_WP = 'wp'
const LINK_KEY_MPS = 'mps'

const LINK_WP = {
    key: LINK_KEY_WP,
    label: 'Wildberries',
    href: 'https://www.wildberries.ru/catalog/{id}/detail.aspx',
}

const LINK_MPS = {
    key: LINK_KEY_MPS,
    label: 'MPStats',
    href: 'https://mpstats.io/wb/item/{id}',
}

const LINK_LIST = [
    LINK_WP,
    LINK_MPS,
]

const LINK_MAPPER = {
    [LINK_KEY_WP]: LINK_WP,
    [LINK_KEY_MPS]: LINK_MPS,
}