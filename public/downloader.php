<?php

declare(strict_types=1);

use App\Downloader\MevzuatGovDownloader;
use App\Http\HttpClient;
use App\Logger\ImportLogger;
use App\Repository\DocumentRepository;
use App\Repository\FetchJobRepository;
use App\Service\FetchJobRunner;
use App\SourceRegistry;

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/sources.php';
$registry = new SourceRegistry($config);
$pdo = new PDO('mysql:host=127.0.0.1;dbname=mevzuat_ingestion;charset=utf8mb4', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$jobId = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sourceKey = (string)($_POST['source_key'] ?? 'mevzuat_gov');
        $mode = (string)($_POST['fetch_mode'] ?? 'list');

        $source = $registry->get($sourceKey);

        $fetchJobRepository = new FetchJobRepository($pdo);
        $documentRepository = new DocumentRepository($pdo);
        $logger = new ImportLogger();

        $runner = new FetchJobRunner($fetchJobRepository, $logger);
        $downloader = new MevzuatGovDownloader(new HttpClient(), $documentRepository, $fetchJobRepository);

        $jobRow = [
            'source_id' => (int)($_POST['source_id'] ?? 1),
            'status' => 'running',
            'requested_by' => 'admin-page',
            'throttle_profile' => (string)($_POST['throttle_profile'] ?? 'safe'),
            'max_pages' => (int)($_POST['max_pages'] ?? 5),
            'max_items' => (int)($_POST['max_items'] ?? 250),
            'notes' => 'fetch_mode=' . $mode,
            'filters' => [
                'aranacak_ifade' => (string)($_POST['aranacak_ifade'] ?? ''),
            ],
            'page_size' => (int)($_POST['page_size'] ?? 25),
            'search_url' => $source['base_url'] . $source['endpoints']['search'],
        ];

        $runner->run($downloader, $jobRow);
        $jobId = 'created_and_executed';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Mevzuat Downloader Admin</title>
</head>
<body>
<h1>Document Downloader</h1>

<?php if ($jobId): ?>
    <p><strong>Job status:</strong> <?= htmlspecialchars((string)$jobId, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color:red"><strong>Error:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post">
    <label>Source</label>
    <select name="source_key">
        <option value="mevzuat_gov">mevzuat.gov.tr</option>
        <option value="bedesten">bedesten.adalet.gov.tr</option>
    </select><br>

    <label>Fetch mode</label>
    <select name="fetch_mode">
        <option value="list">list</option>
        <option value="detail">detail</option>
        <option value="full_content">full content</option>
    </select><br>

    <label>Search expression</label>
    <input type="text" name="aranacak_ifade" value=""><br>

    <label>Page size</label>
    <input type="number" name="page_size" value="25" min="1" max="100"><br>

    <label>Max pages</label>
    <input type="number" name="max_pages" value="5" min="1"><br>

    <label>Max items</label>
    <input type="number" name="max_items" value="250" min="1"><br>

    <label>Throttle profile</label>
    <select name="throttle_profile">
        <option value="safe">safe</option>
        <option value="very_safe">very_safe</option>
    </select><br>

    <button type="submit">Start Job</button>
</form>
</body>
</html>
