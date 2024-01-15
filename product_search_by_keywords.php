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
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 20px;
        }

        .wrapper {
            max-width: 800px;
            min-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            width: calc(100% - 12px);
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        #tags {
            display: flex;
            flex-wrap: wrap;
            margin: 5px 0;
        }

        #tags .tag {
            background-color: #3498db;
            color: white;
            padding: 5px 10px;
            margin: 5px;
            border-radius: 5px;
            cursor: pointer;
        }

        #tags .tag:hover {
            background-color: #2980b9;
        }

        #tags .tag[data-tag=""] {
            display: none;
        }

        input[type="submit"] {
            background-color: #4caf50;
            color: #fff;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        #result-container {
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 20px;
        }

        .product-result {
            margin-bottom: 20px;
        }

        .error {
            color: red;
        }

        #loader {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8); /* Translucent background for the loader */
            padding: 20px;
            border-radius: 10px;
        }

        #loader-icon {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .hidden {
            display: none !important;
        }
    </style>
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

    <div id="result-container" class="hidden"></div>
</section>


<div id="loader" class="hidden">
    <div id="loader-icon"></div>
</div>

<script>
    const MAX_TAGS = 5;

    const $form = document.getElementById('search-form')
    const $loader = document.getElementById('loader')
    const $result = document.getElementById('result-container')
    const $keywords = document.getElementById('keywords')
    const $tags = document.getElementById('tags')

    $form.onsubmit = event => {
        event.preventDefault()

        searchProducts(event)
    }

    $keywords.oninput = event => {
        convertToTags()
    }

    function searchProducts(event) {
        const form = event.target;
        const url = `${form.action}?${new URLSearchParams(new FormData(form)).toString()}`

        showLoader()
        hideResult()

        fetch(url)
            .then(response => response.json())
            .then(data => displayResults(data))
            .catch(error => {
                console.error('Error:', error)
                hideResult()
                hideLoader()
            });
    }

    /**
     * @param {Object} results
     * @param {string} results.keyword
     * @param {?Object[]} results.positions
     * @param {string} results.positions[].keyword
     * @param {string} results.positions[].page
     * @param {string} results.positions[].position
     * @param {?string} results.error
     */
    function displayResults(results) {
        $result.innerHTML = ''

        if (results.error) {
            $result.innerHTML = `<div class="error">${results.error}</div>`
            return;
        }

        if (results.positions == null || results.positions.length === 0) {
            $result.innerHTML = `<div class="error">Продукты не найдены</div>`
            return;
        }

        $result.innerHTML = `<div>
                <h2>Код товара: ${results['product_id']}</h2>
                <ul>
                    ${results.positions.map(item => `<li class="product-result">
                        <strong>ключевое слово:</strong> ${item.keyword}<br>
                        <strong>Страница:</strong> ${item.page == null ? 'Страница не найден' : item.page}<br>
                        <strong>Позиция:</strong> ${item.position == null ? 'Продукт не найден' : item.position}
                    </li>`).join('')}
                </ul>
            </div>`

        showResult()
        hideLoader()
    }

    function convertToTags() {
        const keywords = $keywords.value.split(',').map(tag => tag.trim());

        // Clear existing tags
        $tags.innerHTML = '';

        // Add new tags
        for (let i = 0; i < Math.min(keywords.length, MAX_TAGS); i++) {
            const $tag = document.createElement('div');
            $tag.className = 'tag';
            $tag.textContent = keywords[i];
            $tag.dataset.tag = keywords[i];
            $tags.append($tag);
        }
    }

    function showLoader() {
        $loader.classList.remove('hidden')
    }

    function hideLoader() {
        $loader.classList.add('hidden')
    }

    function showResult() {
        $result.classList.remove('hidden')
    }

    function hideResult() {
        $result.classList.add('hidden')
    }
</script>
</body>
</html>
