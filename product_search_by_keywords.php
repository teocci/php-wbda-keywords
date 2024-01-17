<?php

class ProductSearchByKeywordsService
{
    private const PAGE_LIMIT = 50;
    private const CONCURRENT_REQUESTS = 5;
    private const WILDBERRIES_API_URL = 'https://search.wb.ru/exactmatch/ru/common/v4/search';

    public function searchProducts($keywords, $productId): array
    {
        $results = [];

        $keywords = explode(',', $keywords);

        foreach ($keywords as $keyword) {
            $result = $this->searchProduct($keyword, $productId);
            $results[] = $result;
        }

        return [
            'product_id' => $productId,
            'positions' => $results,
        ];
    }

    public function searchProduct($keyword, $productId): array
    {
        $responses = $this->sendRequests($keyword);

        foreach ($responses as $page => $response) {
            if (!$this->hasResults($response)) continue;

            $position = $this->findProductPosition($response, $productId);
            if ($position === null || $position < 1) continue;

            return [
                'keyword' => $keyword,
                'page' => $page,
                'position' => $position,
            ];
        }

        return [
            'keyword' => $keyword,
            'error' => 'Product not found',
        ];
    }

    private function hasResults($response): bool
    {
        return isset($response['data']['products']) && count($response['data']['products']) > 0;
    }

    private function sendRequests($keyword): array
    {
        $responses = [];
        $handles = [];

        $mh = curl_multi_init();

        for ($page = 1; $page <= self::PAGE_LIMIT; $page++) {
            $url = $this->buildSearchUrl($keyword, $page);

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $handles[$page] = ['ch' => $ch, 'page' => $page];

            curl_multi_add_handle($mh, $ch);

            if (count($handles) >= self::CONCURRENT_REQUESTS) {
                $this->executeMultiHandle($mh, $handles, $responses);
            }
        }

        // Execute any remaining handles
        $this->executeMultiHandle($mh, $handles, $responses);

        curl_multi_close($mh);

        return $responses;
    }

    private function executeMultiHandle($mh, &$handles, &$responses): void
    {
        $active = 0;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) == -1) usleep(1);

            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        foreach ($handles as $data) {
            $ch = $data['ch'];
            $page = $data['page'];

            $chResponse = curl_multi_getcontent($ch);
            $responses[$page] = json_decode($chResponse, true);

            curl_multi_remove_handle($mh, $ch);
        }

        $handles = []; // Reset the active handles array
    }

    private function buildSearchUrl($keyword, $page): string
    {
        return self::WILDBERRIES_API_URL . '?TestGroup=no_test&TestID=no_test&appType=1&curr=rub&dest=-1257786&spp=29&suppressSpellcheck=false&resultset=catalog&' .
            "page={$page}&sort=popular&query={$keyword}";
    }

    private function findProductPosition($response, $productId): int|null
    {
        foreach ($response['data']['products'] as $position => $product) {
            if ($product['id'] == $productId) {
                return $position + 1; // Positions start from 1, not 0
            }
        }

        return null;
    }
}

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['keywords']) && isset($_GET['product_id'])) {
        $keywords = htmlspecialchars(trim($_GET['keywords']));
        $productId = htmlspecialchars(trim($_GET['product_id']));

        if (empty($keywords) || empty($productId)) exit;

        $service = new ProductSearchByKeywordsService();
        $result = $service->searchProducts($keywords, $productId);
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты поиска продуктов</title>
    <link href="https://unpkg.com/gridjs/dist/theme/mermaid.min.css" rel="stylesheet"/>
    <link href="/css/style.css" rel="stylesheet"/>
    <link href="/css/tags.css" rel="stylesheet"/>
    <script src="/js/tags.js" defer></script>
    <script src="/js/links.js" defer></script>
    <script src="/js/grid-table.js" defer></script>
    <script src="/js/loader.js" defer></script>
    <script src="/js/toaster.js" defer></script>
</head>
<body>
<section class="wrapper">
    <h1>Найти позицию продукта</h1>

    <form method="GET" id="search-form">
        <label for="keywords">Ключевое слово (через запятую, максимум 5):</label>
        <div id="tags"></div>
        <input type="text" id="keywords" name="keywords" required>
        <br>
        <label for="product-id">Идентификаторы продуктов:</label>
        <input type="text" id="product-id" name="product_id" required>
        <br>
        <input type="submit" value="Поиск">
    </form>

    <div id="result-container" class="hidden">
        <div id="result-title" class="rc-title rc-part">
            <div class="iw-label iw-part">Код товара:</div>
            <div id="rt-value" class="iw-value iw-part"></div>
            <div id="rt-icon" class="iw-icon iw-part" data-action="copy">
                <img src="/images/copy.svg" alt="Copy Icon">
            </div>
        </div>
        <div id="result-content" class="rc-content rc-part"></div>
    </div>
</section>
<div id="toast" class="action-notification">
    <p class="action-notification-message"></p>
</div>
<div id="loader" class="hidden">
    <div id="loader-icon"></div>
</div>
<script src="https://unpkg.com/gridjs/dist/gridjs.umd.js"></script>
<script>
    const $form = document.getElementById('search-form')
    const $keywords = document.getElementById('keywords')
    const $container = document.getElementById('result-container')
    const $value = document.getElementById('rt-value')
    const $icon = document.getElementById('rt-icon')
    const $result = document.getElementById('result-content')

    window.onload = () => {
        console.log('init')

        initFormListeners()
    }

    function initFormListeners() {
        $form.onsubmit = event => {
            event.preventDefault()

            searchProducts(event)
        }

        $icon.onclick = event => {
            event.preventDefault()

            const id = $icon.dataset.value
            console.log(`copy: ${id}`)
            copyToClipboard(id)
        }

        $keywords.oninput = event => {
            convertToTags()
        }
    }

    function searchProducts(event) {
        const form = event.target
        const url = `${form.action}?${new URLSearchParams(new FormData(form)).toString()}`

        prepareGrid()

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error)

                displayResults(data)
            })
            .catch(error => {
                console.error('Error:', error)
                hideContainer()
                hideLoader()
            })
    }

    function prepareGrid() {
        removeTable($result)

        initTable()

        showLoader()
        showContainer()
    }

    function initTable() {
        grid = new Grid({
            columns: [
                {
                    name: 'No',
                    columnId: 'no',
                    width: '80px',
                },
                {
                    name: 'фраза',
                    columnId: 'keyword',
                },
                {
                    name: 'Страница',
                    columnId: 'page',
                    width: '120px',
                },
                {
                    name: 'Позиция',
                    columnId: 'position',
                    width: '120px',
                },
            ],
            sort: true,
            data: () => new Promise(resolve => {
                resolver = resolve
            }),
        })

        grid.render($result)
    }

    /**
     * @param {Object} d
     * @param {string} d.keyword
     * @param {?Object[]} d.positions
     * @param {string} d.positions[].keyword
     * @param {string} d.positions[].page
     * @param {string} d.positions[].position
     * @param {?string} d.error
     */
    function displayResults(d) {
        hideLoader()

        if (d.error) {
            renderError(d.error)
            return
        }

        if (d.positions == null || d.positions.length === 0) {
            renderError('Продукты не найдены')
            return
        }

        const data = d.positions.map((p, index) => [
            index + 1,
            p.keyword,
            p.page == null ? 'Страница не найден' : p.page,
            p.position == null ? 'Продукт не найден' : p.position,
        ])

        resolver(data)

        $value.textContent = `${d['product_id']}`
        $icon.dataset.value = `${d['product_id']}`

        showContainer()
        hideLoader()
    }

    function showContainer() {
        $container.classList.remove('hidden')
    }

    function hideContainer() {
        $container.classList.add('hidden')
    }

    function renderError(error) {
        $result.innerHTML = `<div class="error">${error}</div>`
    }
</script>
</body>
</html>
