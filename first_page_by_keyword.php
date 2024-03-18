<?php
require 'url_helper.php';

class FirstPageByKeywordService
{
    private const WB_API_URL = 'https://search.wb.ru/exactmatch/ru/common/v5/search';

    private function hasResults($response): bool
    {
        return isset($response['data']['products']) && count($response['data']['products']) > 0;
    }

    public function sendRequests($keyword)
    {
        $url = $this->buildSearchUrl($keyword);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = json_decode(curl_exec($ch), true);

        curl_close($ch);

        if (!$this->hasResults($response)) return ['error' => 'no data found'];

        return $response['data'];
    }

    private function buildSearchUrl($keyword): string
    {
        $params = [
            'ab_testing' => 'false',
            'appType' => '1',
            'curr' => 'rub',
            'dest' => '-1257786',
            'query' => $keyword,
            'resultset' => 'catalog',
            'sort' => 'popular',
            'spp' => '30',
            'suppressSpellcheck' => 'false'
        ];

        return UrlHelper::buildUrlWithParams(self::WB_API_URL, $params);
    }
}

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['keyword'])) {
        $keyword = htmlspecialchars(trim($_GET['keyword']));

        if (empty($keyword)) exit('Keyword is required');

        $service = new FirstPageByKeywordService();
        $result = $service->sendRequests($keyword);
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
    <script src="/js/links.js" defer></script>
    <script src="/js/grid-table.js" defer></script>
    <script src="/js/loader.js" defer></script>
    <script src="/js/toaster.js" defer></script>
</head>
<body>
<section class="wrapper">
    <h1>Найти позицию продукта</h1>

    <form method="GET" id="search-form">
        <label for="keyword">Ключевое слово:</label>
        <input type="text" id="keyword" name="keyword" required>
        <br>
        <input type="submit" value="Поиск">
    </form>

    <div id="result-container" class="hidden"></div>
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
    const $result = document.getElementById('result-container')

    window.onload = () => {
        console.log('init')

        initFormListeners()
    }

    function initFormListeners() {
        $form.onsubmit = event => {
            event.preventDefault()

            searchProducts(event)
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
                hideLoader()
                hideResult()
            })
    }

    function prepareGrid() {
        removeTable($result)

        initTable()

        showLoader()
        showResult()
    }

    function initTable() {
        grid = new gridjs.Grid({
            columns: [
                {
                    name: 'No',
                    columnId: 'no',
                    width: '80px',
                },
                {
                    name: 'ID',
                    columnId: 'id',
                    width: '120px',
                    sort: false,
                    formatter: cell => {
                        const $tmp = document.createElement('div')

                        const $wrapper = document.createElement('div')
                        $wrapper.classList.add('id-wrapper')

                        const $id = document.createElement('div')
                        $id.classList.add('iw-part', 'iw-value')
                        $id.textContent = cell

                        const $icon = document.createElement('div')
                        $icon.classList.add('iw-part', 'iw-icon')
                        $icon.dataset.action = 'copy'
                        $icon.dataset.value = cell

                        const $img = document.createElement('img')
                        $img.src = '/images/copy.svg'
                        $img.alt = 'Copy Icon'

                        $icon.append($img)
                        $wrapper.append($id)
                        $wrapper.append($icon)

                        $tmp.append($wrapper)

                        return GridHTML($tmp.innerHTML)
                    },
                },
                {
                    name: 'Name',
                    columnId: 'name',
                    width: '200px',
                },
                {
                    name: 'Actions',
                    formatter: cell => {
                        const $tmp = document.createElement('div')

                        const $wrapper = document.createElement('div')
                        $wrapper.classList.add('links')

                        for (const link of LINK_LIST) {
                            const uid = cell['id']

                            const $btn = document.createElement('button')
                            $btn.id = `btn-link-${link.key}-${uid}`
                            $btn.classList.add('btn', 'btn-action')
                            $btn.dataset.id = uid
                            $btn.dataset.action = link.key
                            $btn.textContent = link.label

                            $wrapper.appendChild($btn)
                        }

                        $tmp.append($wrapper)

                        return GridHTML($tmp.innerHTML)
                    },
                },
            ],
            search: true,
            sort: true,
            data: () => new Promise(resolve => {
                resolver = resolve
            }),
        })

        grid.render($result)

        subscribe(e => {
            initTableActions(e)
            initTableClipboard(e)
        })
    }


    /**
     * @param {Object} d
     */
    function displayResults(d) {
        const data = d.products.map((product, index) => [
            index + 1,
            product.id,
            product.name,
            {no: index + 1, id: product.id, name: product.name},
        ])

        resolver(data)

        hideLoader()
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

