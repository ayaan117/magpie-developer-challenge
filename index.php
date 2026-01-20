<?php
// Simple API endpoint to run the scraper
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'scrape') {
    header('Content-Type: application/json');
    
    // Run the scraper
    require 'src/Scrape.php';
    $scraper = new \App\Scrape();
    ob_start();
    $scraper->run();
    $output = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Scraping completed',
        'output' => $output
    ]);
    exit;
}

// Load and display products
$products = [];
if (file_exists('output.json')) {
    $json = file_get_contents('output.json');
    $products = json_decode($json, true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smartphone Scraper</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        header p {
            color: #666;
            font-size: 14px;
        }

        .controls {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        button:hover {
            background: #5568d3;
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .stats {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .stat {
            background: #f0f0f0;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 13px;
            color: #555;
        }

        .stat strong {
            color: #333;
        }

        .status {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            font-size: 13px;
            display: none;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            display: block;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .product-info {
            padding: 15px;
        }

        .product-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.4;
            min-height: 40px;
        }

        .product-price {
            font-size: 16px;
            color: #667eea;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .product-details {
            font-size: 12px;
            color: #666;
            line-height: 1.6;
        }

        .detail-item {
            margin-bottom: 5px;
        }

        .detail-label {
            font-weight: 500;
            color: #333;
        }

        .availability {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            margin-top: 8px;
        }

        .availability.in-stock {
            background: #d4edda;
            color: #155724;
        }

        .availability.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }

        .empty-state h2 {
            margin-bottom: 15px;
            font-size: 24px;
        }

        .empty-state p {
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading {
            display: none;
            text-align: center;
            color: white;
            margin-top: 15px;
        }

        .loading.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Smartphone Scraper</h1>
            <p>Extract and view smartphone product data</p>

            <div class="controls">
                <button id="scrapeBtn" onclick="runScraper()">🚀 Run Scraper</button>
                <button onclick="location.reload()">🔄 Refresh</button>
            </div>

            <div class="stats">
                <div class="stat">
                    <strong id="productCount"><?php echo count($products); ?></strong> products
                </div>
                <div class="stat">
                    <strong id="availableCount"><?php echo count(array_filter($products, fn($p) => $p['isAvailable'])); ?></strong> available
                </div>
            </div>

            <div id="status" class="status"></div>
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Scraping in progress...</p>
            </div>
        </header>

        <div id="content">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <h2>No data yet</h2>
                    <p>Click the "Run Scraper" button to fetch smartphone data</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if (!empty($product['imageUrl'])): ?>
                                <div class="product-image">
                                    <img src="<?php echo htmlspecialchars($product['imageUrl']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="product-info">
                                <div class="product-title"><?php echo htmlspecialchars($product['title']); ?></div>
                                <?php if ($product['price'] !== null): ?>
                                    <div class="product-price">£<?php echo number_format($product['price'], 2); ?></div>
                                <?php endif; ?>
                                <div class="product-details">
                                    <?php if (!empty($product['colour'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Color:</span> <?php echo htmlspecialchars($product['colour']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($product['capacityMB'] !== null): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Storage:</span> <?php echo intval($product['capacityMB'] / 1024); ?>GB
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($product['shippingDate'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Ships:</span> <?php echo htmlspecialchars($product['shippingDate']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="availability <?php echo $product['isAvailable'] ? 'in-stock' : 'out-of-stock'; ?>">
                                    <?php echo $product['isAvailable'] ? '✓ In Stock' : '✗ Out of Stock'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function runScraper() {
            const btn = document.getElementById('scrapeBtn');
            const status = document.getElementById('status');
            const loading = document.getElementById('loading');

            btn.disabled = true;
            loading.classList.add('active');
            status.className = 'status info';
            status.textContent = 'Starting scraper...';

            try {
                const response = await fetch('index.php?action=scrape', {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    status.className = 'status success';
                    status.textContent = '✓ Scraping completed! Reloading...';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    status.className = 'status error';
                    status.textContent = '✗ Error: ' + (data.message || 'Unknown error');
                }
            } catch (error) {
                status.className = 'status error';
                status.textContent = '✗ Error: ' + error.message;
            } finally {
                btn.disabled = false;
                loading.classList.remove('active');
            }
        }
    </script>
</body>
</html>
