<?php

require_once __DIR__ . "/classes/DatasetException.php";
require_once __DIR__ . "/classes/DataReader.php";
require_once __DIR__ . "/classes/CrimeAnalyzer.php";
require_once __DIR__ . "/classes/ResultRenderer.php";

// Show the timestamp in Washington, DC time.
date_default_timezone_set("America/New_York");

$renderer    = new ResultRenderer();
$submitted   = false;   // true once the form has been posted
$results     = null;    // the 10 statistics (null until a file is analyzed)
$error       = null;    // error message to display, if any
$recordCount = 0;
$fileName    = "";
$timestamp   = "";
$seconds     = 0.0;

// $_SERVER is a superglobal: it tells us whether the form was submitted.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted = true;
    $startTime = microtime(true);
    $timestamp = date("Y-m-d H:i:s T");

    try {
        // $_FILES is a superglobal that holds the uploaded file's info.
        if (!isset($_FILES["dataset"])) {
            throw new DatasetException("No file was received.");
        }

        $fileName = (string)($_FILES["dataset"]["name"] ?? "");

        $reader   = new DataReader();
        $features = $reader->readFile($_FILES["dataset"]);
        $recordCount = count($features);

        $analyzer = new CrimeAnalyzer($features);
        $results  = $analyzer->analyze();
    } catch (DatasetException $e) {
        // Expected problem with the upload: show it to the user.
        $error = $e->getMessage();
    } catch (Throwable $e) {
        // Anything unexpected: show a generic message instead of crashing.
        $error = "Something went wrong while processing the file.";
    }

    // Total time spent reading and analyzing the data.
    $seconds = round(microtime(true) - $startTime, 4);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DC Crime Incident Data Analysis</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1>DC Crime Incident Data Analysis</h1>
  <p>Upload the JSON or GeoJSON crime dataset to calculate.</p>

  <div class="upload-area">
    <form method="post" enctype="multipart/form-data"
          action="<?= htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8") ?>">
      <label for="picker">Choose a dataset file:</label>
      <input type="file" id="picker" name="dataset"
             accept=".json,.geojson,application/json,application/geo+json">
      <button type="submit">Analyze</button>
    </form>
  </div>

  <p id="error" role="alert"><?= $renderer->renderError($error) ?></p>

  <div id="output" aria-live="polite">
    <?= $renderer->renderQuestions($results) ?>
  </div>

  <?php if ($submitted): ?>
  <div id="processing-info">
    <h2>Processing Information</h2>
    <?= $renderer->renderProcessingInfo($timestamp, $seconds, $recordCount, $fileName) ?>
  </div>
  <?php endif; ?>
</body>
</html>
