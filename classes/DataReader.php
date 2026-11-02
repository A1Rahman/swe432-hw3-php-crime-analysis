<?php

require_once __DIR__ . "/DatasetException.php";

/**
 * Responsible ONLY for reading and validating the uploaded file.
 * It returns the list of GeoJSON features (the crime records) or
 * throws a DatasetException with a user-friendly message.
 */
class DataReader
{
    // Properties every record must have for the 10 questions to work.
    private const REQUIRED_FIELDS = [
        "REPORT_DAT", "START_DATE", "OFFENSE", "SHIFT", "WARD", "METHOD"
    ];

    // Allowed file extensions (lowercase).
    private const ALLOWED_EXTENSIONS = ["json", "geojson"];

    /**
     * Read the uploaded file (an entry of $_FILES) and return its records.
     *
     * @param array $file One element of the $_FILES superglobal
     * @return array The list of GeoJSON features
     * @throws DatasetException If anything is wrong with the file
     */
    public function readFile(array $file): array
    {
        $this->checkUpload($file);

        $content = file_get_contents($file["tmp_name"]);
        if ($content === false) {
            throw new DatasetException("Unable to read the uploaded file.");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DatasetException("Invalid JSON: " . json_last_error_msg());
        }

        return $this->extractFeatures($data);
    }

    // Verify the upload succeeded and has an accepted extension.
    private function checkUpload(array $file): void
    {
        $error = $file["error"] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new DatasetException("Please choose a file to upload.");
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new DatasetException("The file is too large for the server's upload limit.");
        }

        if ($error !== UPLOAD_ERR_OK || !isset($file["tmp_name"])) {
            throw new DatasetException("The file could not be uploaded (error code {$error}).");
        }

        $extension = strtolower(pathinfo($file["name"] ?? "", PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new DatasetException("Only .json and .geojson files are allowed.");
        }
    }

    // Make sure the decoded JSON looks like our crime dataset.
    private function extractFeatures($data): array
    {
        if (!is_array($data) || !isset($data["features"]) || !is_array($data["features"])) {
            throw new DatasetException("Invalid dataset: expected a GeoJSON object with a \"features\" array.");
        }

        $features = $data["features"];

        if (count($features) === 0) {
            throw new DatasetException("The dataset contains no records.");
        }

        // Every record needs a "properties" object.
        foreach ($features as $index => $feature) {
            if (!is_array($feature) || !isset($feature["properties"]) || !is_array($feature["properties"])) {
                $row = $index + 1;
                throw new DatasetException("Record #{$row} has no \"properties\" object.");
            }
        }

        // Check the first record for the fields the statistics rely on.
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $features[0]["properties"])) {
                throw new DatasetException("This does not look like the DC crime dataset: missing field \"{$field}\".");
            }
        }

        return $features;
    }
}
