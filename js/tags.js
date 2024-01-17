const MAX_TAGS = 5

const $tags = document.getElementById('tags')

function convertToTags() {
    const keywords = $keywords.value.split(',').map(tag => tag.trim())

    // Clear existing tags
    $tags.innerHTML = ''

    // Add new tags
    for (let i = 0; i < Math.min(keywords.length, MAX_TAGS); i++) {
        const $tag = document.createElement('div')
        $tag.className = 'tag'
        $tag.textContent = keywords[i]
        $tag.dataset.tag = keywords[i]
        $tags.append($tag)
    }
}