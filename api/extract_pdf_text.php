<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred.');
    }

    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExtension !== 'pdf') {
        throw new Exception('Unsupported file type. Please upload a PDF file.');
    }

    // Parse PDF text
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($fileTmpPath);

    $text = $pdf->getText();
    $extractedText = trim($text);

    if (empty($extractedText)) {
        throw new Exception('Could not extract any text from the PDF document. Please make sure the PDF contains readable text.');
    }

    sendJSONResponse(true, ['text' => $extractedText], 'Text extracted successfully');

} catch (Exception $e) {
    handleAPIError($e, 'Failed to extract text from PDF document');
}
?>