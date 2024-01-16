<?php

class FirstPageADSByKeywordService
{
    private const WB_API_URL = 'https://catalog-ads.wildberries.ru/api/v6/search';

    private function hasResults($response): bool
    {
        return isset($response['pages']) && isset($response['adverts']) &&
            count($response['adverts']) > 0;
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

        return $response;
    }

    private function buildSearchUrl($keyword): string
    {
        return self::WB_API_URL . "?keyword={$keyword}";
    }
}

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['keyword'])) {
        $keyword = htmlspecialchars(trim($_GET['keyword']));

        if (empty($keyword)) exit('Keyword is required');

        $service = new FirstPageADSByKeywordService();
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
        <label for="keyword">Ключевое слово:</label>
        <input type="text" id="keyword" name="keyword" required>
        <br>
        <input type="submit" value="Поиск">
    </form>

    <div id="result-container" class="hidden"></div>
</section>


<div id="loader" class="hidden">
    <div id="loader-icon"></div>
</div>

<script src="https://unpkg.com/gridjs/dist/gridjs.umd.js"></script>
<script>
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

    const STATUS_UID_INIT = 0
    const STATUS_UID_LOADING = 1
    const STATUS_UID_LOADED = 2
    const STATUS_UID_RENDERED = 3
    const STATUS_UID_ERROR = 4

    const $form = document.getElementById('search-form')
    const $loader = document.getElementById('loader')
    const $result = document.getElementById('result-container')


    const Grid = gridjs.Grid
    const GridHTML = gridjs.html

    let resolver
    let grid

    $form.onsubmit = event => {
        event.preventDefault()
        searchProducts(event)
    }

    window.onload = () => {
        console.log('init')
        initTable()
    }

    function initTable() {
        grid = new Grid({
            columns: [
                {
                    name: 'ID',
                    columnId: 'id',
                    width: '120px',
                    sort: false,
                },
                {
                    name: 'CPM',
                    columnId: 'cpm',
                    width: '80px',
                },
                {
                    name: 'Subject',
                    columnId: 'subject',
                    width: '150px',
                },
                {
                    name: 'Actions',
                    formatter: cell => {
                        const $wrapper = document.createElement('div')
                        $wrapper.classList.add('links')

                        for (const link of LINK_LIST) {
                            const uid = cell['id']

                            const $btn = document.createElement('button')
                            $btn.id = `btn-link-${link.key}-${uid}`
                            $btn.classList.add('btn', 'btn-action',)
                            $btn.dataset.id = uid
                            $btn.dataset.action = link.key
                            $btn.textContent = link.label

                            $wrapper.appendChild($btn)
                        }

                        return GridHTML($wrapper.innerHTML)
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
        })
    }

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

    function removeTable() {
        if (grid != null) grid.destroy()

        const children = Array.from($result.children)
        children.forEach(child => {
            child.remove()
        })
    }

    function searchProducts(event) {
        const form = event.target;
        const url = `${form.action}?${new URLSearchParams(new FormData(form)).toString()}`

        prepareGrid()

        fetch(url)
            .then(response => response.json())
            .then(data => displayResults(data))
            .catch(error => {
                console.error('Error:', error)
                hideLoader()
            });
    }

    function prepareGrid() {
        removeTable()

        initTable()

        showLoader()
        showResult()
    }

    /**
     * @param {Object} d
     * @param {Object[]} d.adverts
     * @param {?string} d.adverts[].code
     * @param {number} d.adverts[].advertId
     * @param {number} d.adverts[].id
     * @param {number} d.adverts[].cpm
     * @param {number} d.adverts[].subject
     * @param {Object[]} d.pages
     * @param {number} d.pages[].page
     * @param {number} d.pages[].count
     * @param {number[]} d.pages[].positions
     * @param {number} d.minCPM
     * @param {number[]} d.prioritySubjects
     * @param {Object} d.sortWeights
     * @param {number} d.sortWeights.cpm
     * @param {number} d.sortWeights.delivery
     */
    function displayResults(d) {
        const data = d.adverts.map(p => [p.id, p.cpm, p.subject, p])

        resolver(data)

        hideLoader()
    }

    function subscribe(fn) {
        grid.config.store.subscribe(e => {
            e.status === STATUS_UID_RENDERED && fn(e)
        })
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

