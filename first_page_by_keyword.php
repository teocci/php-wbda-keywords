<?php

class FirstPageByKeywordService
{
    private const WILDBERRIES_API_URL = 'https://search.wb.ru/exactmatch/ru/common/v4/search';

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
        return self::WILDBERRIES_API_URL . '?TestGroup=no_test&TestID=no_test&appType=1&curr=rub&dest=-1257786&spp=29&suppressSpellcheck=false&resultset=catalog&' .
            "sort=popular&query={$keyword}";
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

    <div id="result-container"></div>
</section>


<div id="loader" class="hidden">
    <div id="loader-icon"></div>
</div>

<script src="https://unpkg.com/gridjs/dist/gridjs.umd.js"></script>
<script>
    const $form = document.getElementById('search-form')
    const $loader = document.getElementById('loader')
    const $result = document.getElementById('result-container')

    let resolver

    $form.onsubmit = event => {
        event.preventDefault()
        searchProducts(event)
    }

    window.onload = () => {
        console.log('init')

        const grid = new gridjs.Grid({
            columns: [
                {
                    name: 'ID',
                    columnId: 'id',
                    width: '80px',
                    sort: false,
                },
                {
                    name: 'Name',
                    columnId: 'name',
                    width: '200px',
                },
                {
                    name: 'Position',
                    columnId: 'position',
                    width: '200px',
                },
            ],
            search: true,
            sort: true,
            data: () => new Promise(resolve => {
                resolver = resolve
            }),
        })

        grid.on('rowClick', (...args) => {
            const id = args[1].cells[0].data
            window.open(`https://www.wildberries.ru/catalog/${id}/detail.aspx`)
        })

        grid.render($result)
    }

    function searchProducts(event) {
        const form = event.target;
        const url = `${form.action}?${new URLSearchParams(new FormData(form)).toString()}`

        showLoader()

        fetch(url)
            .then(response => response.json())
            .then(data => displayResults(data))
            .catch(error => {
                console.error('Error:', error)
                hideLoader()
            });
    }

    function displayResults(d) {
        const data = d.products.map((product, position) => [product.id, product.name, position + 1])

        console.log({data, resolver})

        resolver(data)

        hideLoader()
    }

    function showLoader() {
        $loader.classList.remove('hidden')
    }

    function hideLoader() {
        $loader.classList.add('hidden')
    }
</script>
</body>
</html>

